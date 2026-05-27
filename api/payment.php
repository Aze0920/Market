<?php
/**
 * 支付相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_REQUEST['action'] ?? '';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => '请先登录'], 401);
    }
    return $_SESSION['user_id'];
}

function requireAdmin() {
    global $db;
    $userId = requireAuth();
    $user = $db->getUserById($userId);
    if (!$user || $user['role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => '需要管理员权限'], 403);
    }
    return $user;
}

function sanitizeString($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function validateId($id) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $id);
}

function baseUrl() {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function normalizeApiUrl($url) {
    $url = trim((string)$url);
    if ($url !== '' && substr($url, -1) !== '/') {
        $url .= '/';
    }
    return $url;
}

function getPayMethodsFromRequest() {
    $raw = $_POST['pay_methods'] ?? ['alipay', 'wxpay'];
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : explode(',', $raw);
    }
    $allowed = ['alipay', 'wxpay', 'qqpay', 'cashier'];
    $methods = array_values(array_intersect($allowed, array_map('trim', (array)$raw)));
    return empty($methods) ? ['alipay', 'wxpay'] : $methods;
}

function attachPaymentOrderEmails($orders) {
    global $db;
    return array_map(function($order) use ($db) {
        if (!empty($order['user_id'])) {
            $user = $db->getUserById($order['user_id']);
            if ($user && !empty($user['email'])) {
                $order['user_id_email'] = $user['email'];
            }
        }
        return $order;
    }, $orders);
}

function expirePendingPaymentOrders($orders = null) {
    global $db;
    $orders = $orders === null ? $db->getPaymentOrders() : $orders;
    $now = time();
    $expireSeconds = 10 * 60;
    $result = [];
    foreach ($orders as $order) {
        if (($order['status'] ?? 'pending') === 'pending' && !empty($order['created_at']) && ($now - (int)$order['created_at']) >= $expireSeconds) {
            $order['status'] = 'unpaid';
            $order['paid_at'] = null;
            $order['expired_at'] = $order['expired_at'] ?? $now;
            $db->updatePaymentOrder($order['id'], [
                'status' => 'unpaid',
                'paid_at' => null,
                'expired_at' => $order['expired_at']
            ]);
        }
        $result[] = $order;
    }
    return $result;
}

function buildPaymentUpdateFromPost($requireSecret = true) {
    $name = sanitizeString($_POST['name'] ?? '');
    $type = sanitizeString($_POST['type'] ?? 'yipay');
    $apiUrl = normalizeApiUrl(filter_var($_POST['api_url'] ?? '', FILTER_SANITIZE_URL));
    $partnerId = sanitizeString($_POST['partner_id'] ?? '');
    $key = sanitizeString($_POST['key'] ?? '');
    $feeRate = max(0, min(1, floatval($_POST['fee_rate'] ?? 0)));
    $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : true;
    $payMethods = getPayMethodsFromRequest();
    $submitMode = sanitizeString($_POST['submit_mode'] ?? 'url_redirect');
    $sortOrder = intval($_POST['sort_order'] ?? 0);

    if ($type !== 'yipay') {
        jsonResponse(['success' => false, 'message' => '当前仅支持易支付接口'], 400);
    }
    if (empty($name) || empty($apiUrl) || empty($partnerId) || ($requireSecret && empty($key))) {
        jsonResponse(['success' => false, 'message' => '请填写完整的支付接口信息'], 400);
    }
    if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
        jsonResponse(['success' => false, 'message' => 'API地址格式不正确'], 400);
    }
    if (!in_array($submitMode, ['url_redirect', 'form_post'], true)) {
        $submitMode = 'url_redirect';
    }

    $update = [
        'name' => $name,
        'type' => $type,
        'api_url' => $apiUrl,
        'partner_id' => $partnerId,
        'fee_rate' => $feeRate,
        'enabled' => $enabled,
        'pay_methods' => $payMethods,
        'submit_mode' => $submitMode,
        'sort_order' => $sortOrder
    ];

    if ($key !== '') {
        $update['key'] = $key;
    }

    return $update;
}

function completeOnlineProductPurchase($order, $payMethod = '') {
    global $db;
    if (!empty($order['related_id'])) return null;

    $product = $db->getProductById($order['product_id'] ?? '');
    $buyer = $db->getUserById($order['user_id'] ?? '');
    $quantity = max(1, intval($order['quantity'] ?? 1));
    if (!$product || !$buyer || ($product['stock'] ?? 0) < $quantity || ($product['seller_id'] ?? '') === ($buyer['id'] ?? '')) {
        return null;
    }

    $levels = $db->getMembershipLevels();
    $seller = $db->getUserById($product['seller_id']);
    $sellerLevelName = $seller ? ($seller['membership_level'] ?? 'Free') : 'Free';
    $sellerLevel = $levels[$sellerLevelName] ?? $levels['Free'];
    $feeRate = floatval($sellerLevel['fee_rate'] ?? 0);
    $price = floatval($product['price']) * $quantity;
    $sellerAmount = $price * (1 - $feeRate);
    $fee = $price * $feeRate;

    $deliveryList = [];
    foreach ($product['account_list'] as $idx => $acc) {
        if (empty($acc['sold'])) {
            $delivery = $acc;
            $delivery['account_index'] = $idx;
            $deliveryList[] = $delivery;
            if (count($deliveryList) >= $quantity) break;
        }
    }
    if (count($deliveryList) < $quantity) return null;

    foreach ($deliveryList as $delivery) {
        $product['account_list'][$delivery['account_index']]['sold'] = true;
    }
    $product['stock'] -= $quantity;
    $product['sales'] += $quantity;
    $db->updateProduct($product);

    if ($seller) {
        $db->updateUser($seller['id'], ['balance' => floatval($seller['balance'] ?? 0) + $sellerAmount]);
    }

    $productOrder = [
        'id' => 'id_' . time() . '_' . bin2hex(random_bytes(6)),
        'buyer_id' => $buyer['id'],
        'buyer_name' => sanitizeString($buyer['username']),
        'seller_id' => $product['seller_id'],
        'seller_name' => sanitizeString($product['seller_name']),
        'product_id' => $product['id'],
        'product_title' => sanitizeString($product['title']),
        'price' => $price,
        'unit_price' => $product['price'],
        'quantity' => $quantity,
        'fee' => $fee,
        'seller_amount' => $sellerAmount,
        'pay_method' => $payMethod ?: ($order['pay_type'] ?? ''),
        'purchase_date' => time(),
        'delivery_info' => [
            'items' => array_map(function($deliveryInfo) {
                return [
                    'email' => $deliveryInfo['email'] ?? '',
                    'password' => $deliveryInfo['password'] ?? '',
                    'client_id' => $deliveryInfo['client_id'] ?? 'N/A',
                    'fresh_token' => $deliveryInfo['fresh_token'] ?? 'N/A',
                    'content' => $deliveryInfo['content'] ?? '',
                    'format' => $deliveryInfo['format'] ?? 'pipe'
                ];
            }, $deliveryList),
            'locked' => !empty($product['pickup_password_enabled']),
            'password_required' => !empty($product['pickup_password_enabled'])
        ]
    ];
    $db->addOrder($productOrder);

    if ($seller) {
        $db->createPaymentOrder([
            'trade_no' => 'INC' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $seller['id'],
            'payment_config_id' => 'balance',
            'pay_type' => 'balance_income',
            'amount' => $sellerAmount,
            'actual_amount' => $sellerAmount,
            'fee' => $fee,
            'status' => 'paid',
            'type' => 'product_sale_income',
            'title' => '商品销售收入',
            'description' => '售出商品：' . $product['title'] . ' × ' . $quantity,
            'related_id' => $productOrder['id'],
            'paid_at' => time()
        ]);
    }

    $db->updatePaymentOrder($order['id'], ['related_id' => $productOrder['id'], 'fee' => $fee]);
    return $productOrder;
}

class YiPay {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    private function signParams($params) {
        ksort($params);
        $signParts = [];
        foreach ($params as $key => $value) {
            if ($value !== '' && $value !== null && $key !== 'sign' && $key !== 'sign_type') {
                $signParts[] = $key . '=' . $value;
            }
        }
        return md5(implode('&', $signParts) . $this->config['key']);
    }

    public function createOrder($orderNo, $amount, $type, $notifyUrl, $returnUrl, $name = '账户充值') {
        $params = [
            'pid' => $this->config['partner_id'],
            'out_trade_no' => $orderNo,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'name' => $name,
            'money' => number_format((float)$amount, 2, '.', '')
        ];

        if ($type !== 'cashier' && $type !== '') {
            $params['type'] = $type;
        }

        $params['sign'] = $this->signParams($params);
        $params['sign_type'] = 'MD5';

        $submitUrl = normalizeApiUrl($this->config['api_url']) . 'submit.php?' . http_build_query($params);
        $apiData = $this->createApiOrder($params);
        $paymentUrl = $apiData['payment_url'] ?: $submitUrl;

        return [
            'url' => $paymentUrl,
            'qrcode_url' => $apiData['qrcode_url'],
            'qrcode_content' => $apiData['qrcode_content'],
            'params' => $params,
            'submit_mode' => $this->config['submit_mode'] ?? 'url_redirect'
        ];
    }

    private function createApiOrder($params) {
        $result = ['payment_url' => '', 'qrcode_url' => '', 'qrcode_content' => ''];
        $apiUrl = normalizeApiUrl($this->config['api_url']) . 'mapi.php';
        $payload = http_build_query($params);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: KeyNestPayment/1.0\r\n",
                'content' => $payload,
                'timeout' => 8,
                'ignore_errors' => true,
            ]
        ]);
        $body = @file_get_contents($apiUrl, false, $context);
        if (!$body) {
            return $result;
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return $result;
        }
        $result['payment_url'] = $this->firstUrl($data, ['payurl', 'payment_url', 'url', 'jump_url', 'trade_url', 'pay_url']);
        $result['qrcode_url'] = $this->firstUrl($data, ['qrcode', 'qr_code', 'qrcode_url', 'code_img_url', 'img', 'image', 'qrimg', 'qr_img']);
        $result['qrcode_content'] = $this->firstString($data, ['code_url', 'qr_code_url', 'qrcode_content', 'payinfo', 'pay_info']);
        return $result;
    }

    private function firstUrl($data, $keys) {
        foreach ($keys as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                $value = trim($data[$key]);
                if (preg_match('/^https?:\/\/[^\s<>"\']+$/i', $value)) {
                    return $value;
                }
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $nested = $this->firstUrl($value, $keys);
                if ($nested !== '') return $nested;
            }
        }
        return '';
    }

    private function firstString($data, $keys) {
        foreach ($keys as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return trim($data[$key]);
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $nested = $this->firstString($value, $keys);
                if ($nested !== '') return $nested;
            }
        }
        return '';
    }

    public function verifyNotify($data) {
        $sign = $data['sign'] ?? '';
        unset($data['sign'], $data['sign_type'], $data['action']);
        return $sign !== '' && hash_equals($sign, $this->signParams($data));
    }
}

switch ($action) {
    case 'get_configs':
        $user = null;
        if (isset($_SESSION['user_id'])) {
            $user = $db->getUserById($_SESSION['user_id']);
        }

        $configs = ($user && $user['role'] === 'admin') ? $db->getPaymentConfigs() : $db->getActivePaymentConfigs();
        if (!$user || $user['role'] !== 'admin') {
            foreach ($configs as &$config) {
                unset($config['key'], $config['remark']);
            }
        }

        jsonResponse([
            'success' => true,
            'configs' => array_values($configs),
            'notify_url' => baseUrl() . '/api/payment.php?action=notify',
            'return_url' => baseUrl() . '/'
        ]);

    case 'add_config':
        requireAdmin();
        $config = $db->addPaymentConfig(buildPaymentUpdateFromPost(true));
        jsonResponse(['success' => true, 'message' => '支付接口已添加', 'config' => $config]);

    case 'update_config':
        requireAdmin();
        $id = $_POST['id'] ?? '';
        if (empty($id) || !validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }

        $current = $db->getPaymentConfig($id);
        if (!$current) {
            jsonResponse(['success' => false, 'message' => '支付接口不存在'], 404);
        }

        $update = buildPaymentUpdateFromPost(empty($current['key']));
        if (empty($update['key']) && !empty($current['key'])) {
            unset($update['key']);
        }

        if ($db->updatePaymentConfig($id, $update)) {
            jsonResponse(['success' => true, 'message' => '支付接口已更新']);
        }
        jsonResponse(['success' => false, 'message' => '更新失败'], 400);

    case 'delete_config':
        requireAdmin();
        $id = $_POST['id'] ?? '';
        if (empty($id) || !validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $db->deletePaymentConfig($id);
        jsonResponse(['success' => true, 'message' => '支付接口已删除']);

    case 'create_order':
        $userId = requireAuth();
        $configId = $_POST['payment_config_id'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $payType = sanitizeString($_POST['pay_type'] ?? 'alipay');

        if ($amount <= 0 || $amount > 1000000) {
            jsonResponse(['success' => false, 'message' => '金额必须大于0'], 400);
        }

        $config = $db->getPaymentConfig($configId);
        if (!$config || empty($config['enabled'])) {
            jsonResponse(['success' => false, 'message' => '支付方式不可用'], 400);
        }

        $allowedMethods = $config['pay_methods'] ?? ['alipay', 'wxpay'];
        if (!in_array($payType, $allowedMethods, true)) {
            jsonResponse(['success' => false, 'message' => '该接口不支持当前支付类型'], 400);
        }

        $fee = $amount * floatval($config['fee_rate'] ?? 0);
        $actualAmount = $amount + $fee;

        $order = $db->createPaymentOrder([
            'user_id' => $userId,
            'payment_config_id' => $configId,
            'pay_type' => $payType,
            'amount' => $amount,
            'actual_amount' => $actualAmount,
            'fee' => $fee,
            'type' => 'recharge',
            'title' => '在线充值',
            'description' => '通过支付接口充值余额'
        ]);

        $yipay = new YiPay($config);
        $notifyUrl = baseUrl() . '/api/payment.php?action=notify';
        $returnUrl = baseUrl() . '/';
        $paymentData = $yipay->createOrder($order['trade_no'], $actualAmount, $payType, $notifyUrl, $returnUrl);

        jsonResponse([
            'success' => true,
            'order' => $order,
            'payment_url' => $paymentData['url'],
            'qrcode_url' => $paymentData['qrcode_url'] ?? '',
            'qrcode_content' => $paymentData['qrcode_content'] ?? '',
            'payment_params' => $paymentData['params'],
            'submit_mode' => $paymentData['submit_mode']
        ]);

    case 'create_membership_order':
        $userId = requireAuth();
        $configId = $_POST['payment_config_id'] ?? '';
        $targetLevel = trim((string)($_POST['level'] ?? ''));
        $payType = sanitizeString($_POST['pay_type'] ?? 'alipay');
        $user = $db->getUserById($userId);
        $levels = $db->getMembershipLevels();

        if (!$user) {
            jsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        if (!isset($levels[$targetLevel]) || empty($levels[$targetLevel]['enabled']) || empty($levels[$targetLevel]['can_upgrade'])) {
            jsonResponse(['success' => false, 'message' => '无效的会员等级'], 400);
        }

        $currentLevel = $user['membership_level'] ?? 'Free';
        $currentPriority = intval($levels[$currentLevel]['priority'] ?? 0);
        $targetPriority = intval($levels[$targetLevel]['priority'] ?? 0);
        if ($targetPriority <= $currentPriority) {
            jsonResponse(['success' => false, 'message' => '只能升级到更高级别'], 400);
        }

        $amount = floatval($levels[$targetLevel]['cost'] ?? 0);
        if ($amount <= 0) {
            $db->updateUser($userId, ['membership_level' => $targetLevel]);
            jsonResponse(['success' => true, 'message' => '会员已开通', 'paid_directly' => true]);
        }

        $config = $db->getPaymentConfig($configId);
        if (!$config || empty($config['enabled'])) {
            jsonResponse(['success' => false, 'message' => '支付方式不可用'], 400);
        }

        $allowedMethods = $config['pay_methods'] ?? ['alipay', 'wxpay'];
        if (!in_array($payType, $allowedMethods, true)) {
            jsonResponse(['success' => false, 'message' => '该接口不支持当前支付类型'], 400);
        }

        $fee = $amount * floatval($config['fee_rate'] ?? 0);
        $actualAmount = $amount + $fee;
        $order = $db->createPaymentOrder([
            'user_id' => $userId,
            'payment_config_id' => $configId,
            'pay_type' => $payType,
            'amount' => $amount,
            'actual_amount' => $actualAmount,
            'fee' => $fee,
            'type' => 'membership_upgrade',
            'target_level' => $targetLevel,
            'title' => '在线会员升级',
            'description' => '开通 ' . $targetLevel . ' 会员'
        ]);

        $yipay = new YiPay($config);
        $notifyUrl = baseUrl() . '/api/payment.php?action=notify';
        $returnUrl = baseUrl() . '/#page=dashboard&tab=membership';
        $paymentData = $yipay->createOrder($order['trade_no'], $actualAmount, $payType, $notifyUrl, $returnUrl, '开通' . $targetLevel . '会员');

        jsonResponse([
            'success' => true,
            'order' => $order,
            'payment_url' => $paymentData['url'],
            'qrcode_url' => $paymentData['qrcode_url'] ?? '',
            'qrcode_content' => $paymentData['qrcode_content'] ?? '',
            'payment_params' => $paymentData['params'],
            'submit_mode' => $paymentData['submit_mode']
        ]);

    case 'create_product_order':
        $userId = requireAuth();
        $configId = $_POST['payment_config_id'] ?? '';
        $productId = $_POST['product_id'] ?? '';
        $quantity = max(1, min(100, intval($_POST['quantity'] ?? 1)));
        $payType = sanitizeString($_POST['pay_type'] ?? 'alipay');
        $user = $db->getUserById($userId);

        if (!validateId($productId)) {
            jsonResponse(['success' => false, 'message' => '无效的商品ID'], 400);
        }
        $product = $db->getProductById($productId);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (($product['stock'] ?? 0) < $quantity) {
            jsonResponse(['success' => false, 'message' => '库存不足'], 400);
        }
        if (($product['seller_id'] ?? '') === $userId) {
            jsonResponse(['success' => false, 'message' => '不能购买自己的商品'], 400);
        }

        $config = $db->getPaymentConfig($configId);
        if (!$config || empty($config['enabled'])) {
            jsonResponse(['success' => false, 'message' => '支付方式不可用'], 400);
        }
        $allowedMethods = $config['pay_methods'] ?? ['alipay', 'wxpay'];
        if (!in_array($payType, $allowedMethods, true)) {
            jsonResponse(['success' => false, 'message' => '该接口不支持当前支付类型'], 400);
        }

        $amount = floatval($product['price']) * $quantity;
        $fee = $amount * floatval($config['fee_rate'] ?? 0);
        $actualAmount = $amount + $fee;
        $order = $db->createPaymentOrder([
            'user_id' => $userId,
            'payment_config_id' => $configId,
            'pay_type' => $payType,
            'amount' => $amount,
            'actual_amount' => $actualAmount,
            'fee' => $fee,
            'type' => 'product_online_purchase',
            'product_id' => $productId,
            'quantity' => $quantity,
            'title' => '在线支付商品订单',
            'description' => '购买商品：' . ($product['title'] ?? '') . ' × ' . $quantity
        ]);

        $yipay = new YiPay($config);
        $notifyUrl = baseUrl() . '/api/payment.php?action=notify';
        $returnUrl = baseUrl() . '/#page=dashboard&tab=orders';
        $paymentData = $yipay->createOrder($order['trade_no'], $actualAmount, $payType, $notifyUrl, $returnUrl, '购买商品');

        jsonResponse([
            'success' => true,
            'order' => $order,
            'payment_url' => $paymentData['url'],
            'qrcode_url' => $paymentData['qrcode_url'] ?? '',
            'qrcode_content' => $paymentData['qrcode_content'] ?? '',
            'payment_params' => $paymentData['params'],
            'submit_mode' => $paymentData['submit_mode']
        ]);

    case 'notify':
        $data = $_GET;
        if (empty($data['out_trade_no']) && !empty($_POST['out_trade_no'])) {
            $data = $_POST;
        }

        $tradeNo = $data['out_trade_no'] ?? '';
        $order = $db->getPaymentOrderByTradeNo($tradeNo);
        if (!$order) {
            echo 'fail';
            exit;
        }

        $config = $db->getPaymentConfig($order['payment_config_id']);
        if (!$config) {
            echo 'fail';
            exit;
        }

        $yipay = new YiPay($config);
        if (!$yipay->verifyNotify($data)) {
            echo 'fail';
            exit;
        }

        if ($order['status'] === 'paid') {
            echo 'success';
            exit;
        }
        if (($order['status'] ?? '') === 'unpaid') {
            echo 'fail';
            exit;
        }

        $db->updatePaymentOrder($order['id'], [
            'status' => 'paid',
            'paid_at' => time(),
            'notify_data' => $data
        ]);

        $user = $db->getUserById($order['user_id']);
        if ($user) {
            if (($order['type'] ?? 'recharge') === 'membership_upgrade') {
                $targetLevel = $order['target_level'] ?? '';
                $levels = $db->getMembershipLevels();
                if ($targetLevel !== '' && isset($levels[$targetLevel])) {
                    $currentLevel = $user['membership_level'] ?? 'Free';
                    $currentPriority = intval($levels[$currentLevel]['priority'] ?? 0);
                    $targetPriority = intval($levels[$targetLevel]['priority'] ?? 0);
                    if ($targetPriority > $currentPriority) {
                        $db->updateUser($order['user_id'], ['membership_level' => $targetLevel]);
                    }
                }
            } elseif (($order['type'] ?? '') === 'product_online_purchase') {
                completeOnlineProductPurchase($order, $order['pay_type'] ?? '');
            } else {
                $db->updateUser($order['user_id'], [
                    'balance' => $user['balance'] + $order['amount']
                ]);
            }
        }

        echo 'success';
        exit;

    case 'get_order_status':
        $userId = requireAuth();
        $id = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
        if ($id === '') {
            jsonResponse(['success' => false, 'message' => '缺少订单ID'], 400);
        }
        expirePendingPaymentOrders();
        $order = $db->getPaymentOrder($id);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        $user = $db->getUserById($userId);
        if (($order['user_id'] ?? '') !== $userId && (!$user || ($user['role'] ?? '') !== 'admin')) {
            jsonResponse(['success' => false, 'message' => '无权查看该订单'], 403);
        }
        jsonResponse([
            'success' => true,
            'order' => [
                'id' => $order['id'] ?? '',
                'trade_no' => $order['trade_no'] ?? '',
                'status' => $order['status'] ?? 'pending',
                'type' => $order['type'] ?? 'recharge',
                'pay_type' => $order['pay_type'] ?? '',
                'amount' => $order['amount'] ?? 0,
                'actual_amount' => $order['actual_amount'] ?? 0,
                'paid_at' => $order['paid_at'] ?? null,
                'created_at' => $order['created_at'] ?? null
            ]
        ]);

    case 'get_orders':
        requireAdmin();
        $orders = expirePendingPaymentOrders($db->getPaymentOrders());
        usort($orders, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        $orders = attachPaymentOrderEmails($orders);
        jsonResponse(['success' => true, 'orders' => $orders]);

    case 'update_order_status':
        requireAdmin();
        $id = trim((string)($_POST['id'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $allowed = ['pending', 'paid', 'failed', 'cancelled', 'unpaid'];
        if ($id === '' || !in_array($status, $allowed, true)) {
            jsonResponse(['success' => false, 'message' => '订单ID或状态无效'], 400);
        }
        $order = $db->getPaymentOrder($id);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        $update = ['status' => $status];
        if ($status === 'paid' && empty($order['paid_at'])) {
            $update['paid_at'] = time();
        }
        if ($status !== 'paid') {
            $update['paid_at'] = null;
        }
        if (!$db->updatePaymentOrder($id, $update)) {
            jsonResponse(['success' => false, 'message' => '状态更新失败'], 400);
        }
        jsonResponse(['success' => true, 'message' => '订单状态已更新']);

    case 'delete_order':
        requireAdmin();
        $id = trim((string)($_POST['id'] ?? ''));
        if ($id === '') {
            jsonResponse(['success' => false, 'message' => '缺少订单ID'], 400);
        }
        if (!$db->deletePaymentOrder($id)) {
            jsonResponse(['success' => false, 'message' => '订单不存在或删除失败'], 404);
        }
        jsonResponse(['success' => true, 'message' => '订单已删除']);

    case 'delete_unpaid_orders':
        requireAdmin();
        expirePendingPaymentOrders();
        $count = $db->deletePaymentOrdersByStatus(['pending', 'failed', 'cancelled', 'unpaid']);
        jsonResponse(['success' => true, 'message' => '已删除 ' . $count . ' 条未支付订单', 'count' => $count]);

    case 'delete_all_orders':
        requireAdmin();
        $count = $db->deleteAllPaymentOrders();
        jsonResponse(['success' => true, 'message' => '已删除全部订单，共 ' . $count . ' 条', 'count' => $count]);

    case 'get_my_orders':
        $userId = requireAuth();
        $orders = expirePendingPaymentOrders($db->getPaymentOrders($userId));
        jsonResponse(['success' => true, 'orders' => array_values($orders)]);

    default:
        jsonResponse(['success' => false, 'message' => '无效的操作'], 400);
}
