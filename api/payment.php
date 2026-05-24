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

        return [
            'url' => normalizeApiUrl($this->config['api_url']) . 'submit.php?' . http_build_query($params),
            'params' => $params,
            'submit_mode' => $this->config['submit_mode'] ?? 'url_redirect'
        ];
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
            'type' => 'recharge'
        ]);

        $yipay = new YiPay($config);
        $notifyUrl = baseUrl() . '/api/payment.php?action=notify';
        $returnUrl = baseUrl() . '/';
        $paymentData = $yipay->createOrder($order['trade_no'], $actualAmount, $payType, $notifyUrl, $returnUrl);

        jsonResponse([
            'success' => true,
            'order' => $order,
            'payment_url' => $paymentData['url'],
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
            'target_level' => $targetLevel
        ]);

        $yipay = new YiPay($config);
        $notifyUrl = baseUrl() . '/api/payment.php?action=notify';
        $returnUrl = baseUrl() . '/#page=dashboard&tab=membership';
        $paymentData = $yipay->createOrder($order['trade_no'], $actualAmount, $payType, $notifyUrl, $returnUrl, '开通' . $targetLevel . '会员');

        jsonResponse([
            'success' => true,
            'order' => $order,
            'payment_url' => $paymentData['url'],
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
            } else {
                $db->updateUser($order['user_id'], [
                    'balance' => $user['balance'] + $order['amount']
                ]);
            }
        }

        echo 'success';
        exit;

    case 'get_orders':
        requireAdmin();
        $orders = $db->getPaymentOrders();
        usort($orders, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        jsonResponse(['success' => true, 'orders' => $orders]);

    case 'update_order_status':
        requireAdmin();
        $id = trim((string)($_POST['id'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $allowed = ['pending', 'paid', 'failed', 'cancelled'];
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
        $count = $db->deletePaymentOrdersByStatus(['pending', 'failed', 'cancelled']);
        jsonResponse(['success' => true, 'message' => '已删除 ' . $count . ' 条未支付订单', 'count' => $count]);

    case 'delete_all_orders':
        requireAdmin();
        $count = $db->deleteAllPaymentOrders();
        jsonResponse(['success' => true, 'message' => '已删除全部订单，共 ' . $count . ' 条', 'count' => $count]);

    case 'get_my_orders':
        $userId = requireAuth();
        $orders = $db->getPaymentOrders($userId);
        jsonResponse(['success' => true, 'orders' => array_values($orders)]);

    default:
        jsonResponse(['success' => false, 'message' => '无效的操作'], 400);
}
