<?php
/**
 * 商品相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/SubdomainHelper.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function genId() {
    return 'id_' . time() . '_' . bin2hex(random_bytes(6));
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => '请先登录'], 401);
    }
    return $_SESSION['user_id'];
}

function getCurrentUser() {
    global $db;
    if (!isset($_SESSION['user_id'])) return null;
    return $db->getUserById($_SESSION['user_id']);
}

function normalizeBootstrapIcon($icon, $fallback = 'bi-tag') {
    $icon = trim((string)$icon);
    if ($icon === '' || !preg_match('/^bi(-[a-z0-9-]+)+$/', $icon)) {
        return $fallback;
    }
    return $icon;
}

function normalizeBadgeGradient($gradient, $fallback = 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)') {
    $gradient = trim((string)$gradient);
    if ($gradient === '' || preg_match('/[<>"\']/', $gradient)) {
        return $fallback;
    }
    return substr($gradient, 0, 255);
}

function attachSellerBadgeMeta(array &$target, $seller, array $levels, array $config = []) {
    if (!is_array($seller)) {
        $freeLevel = $levels['Free'] ?? ['priority' => 0, 'icon' => 'bi-person', 'gradient' => 'linear-gradient(135deg, #6c757d 0%, #495057 100%)'];
        $target['seller_role'] = 'user';
        $target['seller_is_admin'] = false;
        $target['seller_membership_level'] = 'Free';
        $target['seller_membership_priority'] = intval($freeLevel['priority'] ?? 0);
        $target['seller_badge_icon'] = $freeLevel['icon'] ?? 'bi-person';
        $target['seller_badge_gradient'] = normalizeBadgeGradient($freeLevel['gradient'] ?? '', 'linear-gradient(135deg, #6c757d 0%, #495057 100%)');
        $target['seller_badge_text'] = 'Free';
        $target['seller_custom_label'] = null;
        $target['seller_is_vip'] = false;
        return;
    }

    if (($seller['role'] ?? '') === 'admin') {
        $target['seller_role'] = 'admin';
        $target['seller_is_admin'] = true;
        $target['seller_membership_level'] = $seller['membership_level'] ?? 'Free';
        $target['seller_membership_priority'] = 9999;
        $target['seller_badge_icon'] = normalizeBootstrapIcon($config['admin_badge_icon'] ?? 'bi-shield-fill-check', 'bi-shield-fill-check');
        $target['seller_badge_gradient'] = normalizeBadgeGradient($config['admin_badge_gradient'] ?? '', 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)');
        $target['seller_badge_text'] = trim((string)($config['admin_badge_text'] ?? '管理员')) ?: '管理员';
        $target['seller_custom_label'] = null;
        $target['seller_is_vip'] = true;
        return;
    }

    $levelName = $seller['membership_level'] ?? 'Free';
    $level = $levels[$levelName] ?? ($levels['Free'] ?? []);
    $freeLevel = $levels['Free'] ?? ['priority' => 0];
    $levelPriority = intval($level['priority'] ?? 0);
    $freePriority = intval($freeLevel['priority'] ?? 0);

    $target['seller_role'] = 'user';
    $target['seller_is_admin'] = false;
    $target['seller_membership_level'] = $levelName;
    $target['seller_membership_priority'] = $levelPriority;
    $target['seller_badge_icon'] = normalizeBootstrapIcon($level['icon'] ?? 'bi-person', 'bi-person');
    $target['seller_badge_gradient'] = normalizeBadgeGradient($level['gradient'] ?? '', 'linear-gradient(135deg, #6c757d 0%, #495057 100%)');
    $target['seller_badge_text'] = $levelName;
    $target['seller_is_vip'] = $levelPriority > $freePriority || strcasecmp($levelName, 'Free') !== 0;

    $customLabel = null;
    if (!empty($level['custom_label_enabled'])) {
        $text = trim((string)($seller['custom_label_text'] ?? ''));
        $textLen = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($text !== '' && $textLen >= 1 && $textLen <= 10) {
            $customLabel = [
                'text' => $text,
                'icon' => normalizeBootstrapIcon($seller['custom_label_icon'] ?? 'bi-tag', 'bi-tag'),
                'gradient' => normalizeBadgeGradient($seller['custom_label_gradient'] ?? '', $target['seller_badge_gradient']),
            ];
        }
    }
    $target['seller_custom_label'] = $customLabel;
}

function userHasMerchantCertification($user) {
    $methods = is_array($user['payment_methods'] ?? null) ? $user['payment_methods'] : [];
    $paymentComplete = false;
    foreach ($methods as $method) {
        if (is_array($method) && trim((string)($method['account'] ?? '')) !== '' && trim((string)($method['qrcode'] ?? '')) !== '') {
            $paymentComplete = true;
            break;
        }
    }
    return !empty($user['qq_openid'])
        && $paymentComplete
        && !empty($user['merchant_rules_accepted'])
        && ($user['merchant_status'] ?? 'none') === 'approved';
}

function merchantCertificationMessage($user) {
    $methods = is_array($user['payment_methods'] ?? null) ? $user['payment_methods'] : [];
    $paymentComplete = false;
    foreach ($methods as $method) {
        if (is_array($method) && trim((string)($method['account'] ?? '')) !== '' && trim((string)($method['qrcode'] ?? '')) !== '') {
            $paymentComplete = true;
            break;
        }
    }
    if (empty($user['qq_openid'])) return '您还未绑定 QQ，请先到控制台个人中心绑定 QQ 后再开通商家';
    if (!$paymentComplete) return '您还未完成商家认证，请先到控制台完善收款方式';
    if (empty($user['merchant_rules_accepted'])) return '请先阅读并同意商家守则、免责声明与商家质保';
    if (($user['merchant_status'] ?? 'none') === 'pending') return '您的商家重新开通申请正在审核中，请等待管理员审核';
    if (($user['merchant_status'] ?? 'none') === 'rejected') return '您的商家重新开通申请未通过，请修改资料后重新提交';
    return '您还未完成商家认证，请先到控制台完成商家开通';
}

function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function sanitizeMarkdown($str) {
    return trim((string)$str);
}

function encryptSensitive($plaintext) {
    if (empty($plaintext) || $plaintext === 'N/A') {
        return $plaintext;
    }
    $key = getenv('KEYNEST_ENCRYPTION_KEY') ?: 'KeyNestDefaultEncKey2024!';
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return $plaintext;
    }
    return base64_encode($iv . $encrypted);
}

function decryptSensitive($ciphertext) {
    if (empty($ciphertext) || $ciphertext === 'N/A') {
        return $ciphertext;
    }
    $key = getenv('KEYNEST_ENCRYPTION_KEY') ?: 'KeyNestDefaultEncKey2024!';
    $data = base64_decode($ciphertext, true);
    if ($data === false || strlen($data) < 17) {
        return $ciphertext;
    }
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        return $ciphertext;
    }
    return $decrypted;
}

function parseAccountLine($line) {
    $line = trim((string)$line);
    if ($line === '' || strlen($line) > 2000) {
        return null;
    }

    $parts = array_map('trim', explode('|', $line));
    if (count($parts) >= 2) {
        return [
            'email' => sanitizeString($parts[0]),
            'password' => encryptSensitive($parts[1]),
            'client_id' => sanitizeString($parts[2] ?? 'N/A'),
            'fresh_token' => encryptSensitive($parts[3] ?? 'N/A'),
            'content' => $line,
            'format' => 'pipe',
            'sold' => false
        ];
    }

    return [
        'email' => 'N/A',
        'password' => encryptSensitive('N/A'),
        'client_id' => 'N/A',
        'fresh_token' => encryptSensitive('N/A'),
        'content' => $line,
        'format' => 'line',
        'sold' => false
    ];
}

function publishFeePerAccountForUser($userId) {
    global $db;
    $user = $db->getUserById($userId);
    $levels = $db->getMembershipLevels();
    $levelName = $user['membership_level'] ?? 'Free';
    $level = $levels[$levelName] ?? ($levels['Free'] ?? []);
    return max(0, floatval($level['publish_fee_per_account'] ?? 0));
}

function markDeferredPublishFee(array &$accounts, $feePerItem) {
    $feePerItem = max(0, floatval($feePerItem));
    foreach ($accounts as &$account) {
        if (!is_array($account)) {
            continue;
        }
        $account['publish_fee_pending'] = $feePerItem > 0;
        $account['publish_fee_amount'] = $feePerItem;
    }
    unset($account);
}

function deferredPublishFeeForDelivery(array $deliveryList) {
    $amount = 0;
    foreach ($deliveryList as $delivery) {
        if (!empty($delivery['publish_fee_pending'])) {
            $amount += max(0, floatval($delivery['publish_fee_amount'] ?? 0));
        }
    }
    return $amount;
}

function refundableStockFeeForDeletedItems(array $deletedItems) {
    $count = 0;
    foreach ($deletedItems as $item) {
        if (!is_array($item) || !empty($item['sold'])) {
            continue;
        }
        if (!array_key_exists('publish_fee_pending', $item) || empty($item['publish_fee_pending'])) {
            $count++;
        }
    }
    return $count;
}

function refundDeletedUnsoldStock($userId, $productTitle, $deletedItems) {
    global $db;
    if (is_int($deletedItems)) {
        $refundableCount = max(0, $deletedItems);
    } else {
        $refundableCount = refundableStockFeeForDeletedItems(is_array($deletedItems) ? $deletedItems : []);
    }
    if ($refundableCount <= 0) {
        return 0;
    }
    $refundPerItem = publishFeePerAccountForUser($userId);
    $refundAmount = $refundPerItem * $refundableCount;
    if ($refundAmount <= 0) {
        return 0;
    }
    $user = $db->getUserById($userId);
    if (!$user) {
        return 0;
    }
    $db->updateUser($userId, ['balance' => floatval($user['balance'] ?? 0) + $refundAmount]);
    $db->createPaymentOrder([
        'trade_no' => 'REF' . date('YmdHis') . rand(1000, 9999),
        'user_id' => $userId,
        'payment_config_id' => 'balance',
        'pay_type' => 'balance_refund',
        'amount' => $refundAmount,
        'actual_amount' => $refundAmount,
        'fee' => 0,
        'status' => 'paid',
        'type' => 'publish_fee_refund',
        'title' => '删除未售库存退费',
        'description' => '删除未售库存退还历史发布扣费：' . $productTitle . ' × ' . $refundableCount,
        'paid_at' => time()
    ]);
    return $refundAmount;
}

function productPublishFail($message, $context = []) {
    if (isset($GLOBALS['api_logger'])) {
        $GLOBALS['api_logger']->logApiRequest('warning', array_merge([
            'event' => 'publish_failed',
            'reason' => $message
        ], $context));
    }
    jsonResponse(['success' => false, 'message' => $message], 400);
}

function validateId($id) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $id);
}

function applySubdomainProductScope($db, array &$filters) {
    $scope = $db->resolveSubdomainProductScope();
    if ($scope === null) {
        return true;
    }
    if (($scope['mode'] ?? '') === 'blocked') {
        return false;
    }
    if (!empty($scope['seller_id'])) {
        $filters['seller_id'] = $scope['seller_id'];
    }
    return true;
}

function subdomainProductListMeta($db) {
    require_once __DIR__ . '/../core/SubdomainHelper.php';
    $scope = $db->resolveSubdomainProductScope();
    if ($scope === null || ($scope['mode'] ?? '') !== 'blocked') {
        return null;
    }
    return [
        'blocked' => true,
        'prefix' => $scope['prefix'] ?? '',
        'full_domain' => $scope['full_domain'] ?? '',
        'reason' => $scope['reason'] ?? 'not_found',
        'message' => $scope['message'] ?? SubdomainHelper::blockedPublicMessage($scope['reason'] ?? 'not_found', $scope['full_domain'] ?? ''),
    ];
}

function productAllowedOnCurrentSubdomain($db, array $product) {
    $scope = $db->resolveSubdomainProductScope();
    if ($scope === null) {
        return true;
    }
    if (($scope['mode'] ?? '') === 'blocked') {
        return false;
    }
    return (string)($product['seller_id'] ?? '') === (string)($scope['seller_id'] ?? '');
}

function productRatingStats($comments) {
    $good = 0;
    $bad = 0;
    foreach ($comments as $comment) {
        if ((int)($comment['rating'] ?? 0) >= 4) {
            $good++;
        } else {
            $bad++;
        }
    }
    return ['good' => $good, 'bad' => $bad, 'total' => count($comments)];
}

function enrichSoldStockBuyers($productId, array &$stock) {
    global $db;
    $needsLookup = false;
    foreach ($stock as $item) {
        if (!empty($item['sold']) && empty($item['buyer_name'])) {
            $needsLookup = true;
            break;
        }
    }
    if (!$needsLookup) {
        return;
    }

    $contentToBuyer = [];
    foreach ($db->getOrders() as $order) {
        if (($order['product_id'] ?? '') !== $productId) {
            continue;
        }
        $buyerName = trim((string)($order['buyer_name'] ?? ''));
        if ($buyerName === '' && !empty($order['guest_order'])) {
            $buyerName = '游客';
        }
        if ($buyerName === '') {
            continue;
        }
        foreach (($order['delivery_info']['items'] ?? []) as $deliveryItem) {
            $content = trim((string)($deliveryItem['content'] ?? ''));
            if ($content !== '') {
                $contentToBuyer[$content] = $buyerName;
            }
        }
    }

    foreach ($stock as &$item) {
        if (empty($item['sold']) || !empty($item['buyer_name'])) {
            continue;
        }
        $content = trim((string)($item['content'] ?? ''));
        if ($content !== '' && isset($contentToBuyer[$content])) {
            $item['buyer_name'] = $contentToBuyer[$content];
        }
    }
    unset($item);
}

function normalizeProductImage($image) {
    $image = trim((string)$image);
    if ($image === '') return '';
    if (preg_match('/^https?:\/\/[^\s<>"\']+\.(png|jpe?g|gif|webp)(\?[^\s<>"\']*)?$/i', $image)) {
        return $image;
    }
    if (preg_match('/^\/uploads\/products\/[a-zA-Z0-9_.-]+\.(png|jpe?g|gif|webp)$/i', $image)) {
        return $image;
    }
    return '';
}

function completeProductPurchase($product, $buyer, $quantity, $payMethod = 'balance', $pickupPassword = '') {
    global $db;
    $levels = $db->getMembershipLevels();
    $seller = $db->getUserById($product['seller_id']);
    $sellerLevelName = $seller ? ($seller['membership_level'] ?? 'Free') : 'Free';
    $sellerLevel = $levels[$sellerLevelName] ?? $levels['Free'];
    $feeRate = floatval($sellerLevel['fee_rate'] ?? 0);
    $price = floatval($product['price']) * $quantity;
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

    if (count($deliveryList) < $quantity) {
        return ['success' => false, 'message' => '该商品暂无足够可用账户'];
    }

    $pickupPasswordEnabled = !empty($product['pickup_password_enabled']);
    if ($pickupPasswordEnabled && trim((string)$pickupPassword) === '') {
        return ['success' => false, 'message' => '该商品需要买家设置取卡密码'];
    }

    $deferredPublishFee = deferredPublishFeeForDelivery($deliveryList);
    $sellerAmount = max(0, $price - $fee - $deferredPublishFee);
    $buyerLabel = sanitizeString($buyer['username'] ?? '游客');
    foreach ($deliveryList as $delivery) {
        $idx = $delivery['account_index'];
        $product['account_list'][$idx]['sold'] = true;
        $product['account_list'][$idx]['buyer_name'] = $buyerLabel;
        $product['account_list'][$idx]['buyer_id'] = (string)($buyer['id'] ?? '');
        $product['account_list'][$idx]['publish_fee_pending'] = false;
        $product['account_list'][$idx]['publish_fee_charged_at'] = time();
    }
    $product['stock'] -= $quantity;
    $product['sales'] += $quantity;
    $db->updateProduct($product);

    if ($seller) {
        $db->updateUser($seller['id'], ['balance' => $seller['balance'] + $sellerAmount]);
    }

    $order = [
        'id' => genId(),
        'buyer_id' => $buyer['id'],
        'buyer_name' => sanitizeString($buyer['username']),
        'seller_id' => $product['seller_id'],
        'seller_name' => sanitizeString($product['seller_name']),
        'product_id' => $product['id'],
        'product_title' => sanitizeString($product['title']),
        'price' => $price,
        'unit_price' => $product['price'],
        'quantity' => $quantity,
        'fee' => $fee + $deferredPublishFee,
        'seller_amount' => $sellerAmount,
        'pay_method' => $payMethod,
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
            'locked' => false,
            'password_required' => false,
            'pickup_password_enabled' => $pickupPasswordEnabled,
            'pickup_password_hash' => $pickupPasswordEnabled ? password_hash(trim((string)$pickupPassword), PASSWORD_DEFAULT) : ''
        ]
    ];
    $db->addOrder($order);

    if ($seller) {
        $db->createPaymentOrder([
            'trade_no' => 'INC' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $seller['id'],
            'payment_config_id' => 'balance',
            'pay_type' => 'balance_income',
            'amount' => $sellerAmount,
            'actual_amount' => $sellerAmount,
            'fee' => $fee + $deferredPublishFee,
            'status' => 'paid',
            'type' => 'product_sale_income',
            'title' => '商品销售收入',
            'description' => '售出商品：' . $product['title'] . ' × ' . $quantity . ($deferredPublishFee > 0 ? '，已扣售出发布费 ¥' . number_format($deferredPublishFee, 2, '.', '') : ''),
            'related_id' => $order['id'],
            'paid_at' => time()
        ]);
    }

    return ['success' => true, 'order' => $order, 'price' => $price, 'fee' => $fee + $deferredPublishFee];
}

switch ($action) {
    case 'upload_image':
        requireAuth();
        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            jsonResponse(['success' => false, 'message' => '请选择要上传的图片'], 400);
        }
        $file = $_FILES['image'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            jsonResponse(['success' => false, 'message' => '图片上传失败'], 400);
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > 2 * 1024 * 1024) {
            jsonResponse(['success' => false, 'message' => '图片大小不能超过2MB'], 400);
        }
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';
        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($extMap[$mime])) {
            jsonResponse(['success' => false, 'message' => '仅支持 JPG、PNG、GIF、WEBP 图片'], 400);
        }
        $siteRoot = is_dir(dirname(__DIR__) . '/public') ? dirname(__DIR__) . '/public' : dirname(__DIR__);
        $uploadDir = $siteRoot . '/uploads/products';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            jsonResponse(['success' => false, 'message' => '上传目录创建失败：' . $uploadDir], 500);
        }
        if (!is_writable($uploadDir)) {
            jsonResponse(['success' => false, 'message' => '上传目录不可写，请检查服务器目录权限：' . $uploadDir], 500);
        }
        $filename = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extMap[$mime];
        $target = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            jsonResponse(['success' => false, 'message' => '保存图片失败，请检查上传目录权限或磁盘空间'], 500);
        }
        @chmod($target, 0644);
        jsonResponse(['success' => true, 'url' => '/uploads/products/' . $filename, 'message' => '图片上传成功']);

    case 'list':
        $filters = [
            'stock_min' => isset($_GET['stock_min']) ? max(0, (int)$_GET['stock_min']) : 1,
            'category' => sanitizeString($_GET['category'] ?? 'all'),
            'search' => sanitizeString($_GET['search'] ?? '')
        ];
        if (!applySubdomainProductScope($db, $filters)) {
            $meta = subdomainProductListMeta($db);
            jsonResponse([
                'success' => true,
                'products' => [],
                'subdomain_state' => $meta,
            ]);
        }
        $sellerId = trim((string)($_GET['seller_id'] ?? ''));
        if (empty($filters['seller_id']) && $sellerId !== '' && validateId($sellerId)) {
            $filters['seller_id'] = $sellerId;
        }
        $products = $db->getProducts($filters);
        $levels = $db->getMembershipLevels();
        $config = $db->getSystemConfig();
        $sellerCache = [];
        foreach ($products as &$p) {
            $sellerId = $p['seller_id'] ?? '';
            if ($sellerId !== '' && !array_key_exists($sellerId, $sellerCache)) {
                $sellerCache[$sellerId] = $db->getUserById($sellerId);
            }
            $seller = $sellerCache[$sellerId] ?? null;
            attachSellerBadgeMeta($p, $seller, $levels, $config);
            $stats = productRatingStats($db->getComments($p['id']));
            $p['rating_good'] = $stats['good'];
            $p['rating_bad'] = $stats['bad'];
            $p['rating_total'] = $stats['total'];
            unset($p['account_list'], $p['pickup_password']);
        }
        unset($p);
        usort($products, function($a, $b) {
            $priorityDiff = intval($b['seller_membership_priority'] ?? 0) <=> intval($a['seller_membership_priority'] ?? 0);
            if ($priorityDiff !== 0) return $priorityDiff;
            $timeDiff = intval($b['updated_at'] ?? $b['created_at'] ?? 0) <=> intval($a['updated_at'] ?? $a['created_at'] ?? 0);
            if ($timeDiff !== 0) return $timeDiff;
            return intval($b['sales'] ?? 0) <=> intval($a['sales'] ?? 0);
        });
        jsonResponse(['success' => true, 'products' => $products]);

    case 'get':
        $id = $_GET['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product || !productAllowedOnCurrentSubdomain($db, $product)) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        unset($product['account_list'], $product['pickup_password']);
        jsonResponse(['success' => true, 'product' => $product]);

    case 'detail':
        $id = $_GET['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product || !productAllowedOnCurrentSubdomain($db, $product)) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        $safe = $product;
        $levels = $db->getMembershipLevels();
        $config = $db->getSystemConfig();
        $seller = $db->getUserById($safe['seller_id'] ?? '');
        attachSellerBadgeMeta($safe, $seller, $levels, $config);
        unset($safe['account_list'], $safe['pickup_password']);
        $comments = $db->getComments($id);
        $safe['rating_stats'] = productRatingStats($comments);
        jsonResponse(['success' => true, 'product' => $safe, 'comments' => $comments]);

    case 'publish':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        if (!$user) {
            productPublishFail('登录状态异常，请退出后重新登录');
        }
        if (!userHasMerchantCertification($user)) {
            jsonResponse([
                'success' => false,
                'message' => merchantCertificationMessage($user),
                'code' => 'merchant_certification_required'
            ], 403);
        }
        $levels = $db->getMembershipLevels();
        $userLevel = $levels[$user['membership_level'] ?? 'Free'] ?? $levels['Free'];

        $existingProducts = $db->getProducts(['seller_id' => $userId]);
        $maxProducts = intval($userLevel['max_products'] ?? 0);
        if ($maxProducts > 0 && count($existingProducts) >= $maxProducts) {
            productPublishFail("您当前会员等级最多只能发布{$maxProducts}个商品，当前已发布" . count($existingProducts) . "个", [
                'membership_level' => $user['membership_level'] ?? 'Free',
                'existing_products' => count($existingProducts),
                'max_products' => $maxProducts
            ]);
        }

        $title = sanitizeString($_POST['title'] ?? '');
        $category = sanitizeString($_POST['category'] ?? '其他');
        $price = floatval($_POST['price'] ?? 0);
        $description = sanitizeMarkdown($_POST['description'] ?? '');
        $accountListText = trim($_POST['account_list'] ?? '');
        $customImage = normalizeProductImage($_POST['image'] ?? '');
        $pickupPasswordEnabled = ($_POST['pickup_password_enabled'] ?? '0') === '1';

        if (empty($title) || strlen($title) > 100) {
            productPublishFail('请填写标题（最多100字符）');
        }
        if ($price <= 0 || $price > 999999) {
            productPublishFail('请填写有效价格（最高999999）');
        }
        if (empty($accountListText)) {
            productPublishFail('请填写账户列表');
        }
        $accountLines = preg_split('/\r\n|\r|\n/', $accountListText);
        $accountList = [];
        foreach ($accountLines as $line) {
            $account = parseAccountLine($line);
            if ($account) {
                $accountList[] = $account;
            }
        }

        if (empty($accountList)) {
            productPublishFail('账户列表格式不正确，请至少填写一行账号信息');
        }

        $maxAccountsPerProduct = intval($userLevel['max_accounts_per_product'] ?? 0);
        if ($maxAccountsPerProduct > 0 && count($accountList) > $maxAccountsPerProduct) {
            productPublishFail("您当前会员等级单个商品最多添加{$maxAccountsPerProduct}个账户，当前填写" . count($accountList) . "个", [
                'membership_level' => $user['membership_level'] ?? 'Free',
                'account_count' => count($accountList),
                'max_accounts_per_product' => $maxAccountsPerProduct
            ]);
        }

        $publishFeePerItem = floatval($userLevel['publish_fee_per_account'] ?? 0);
        markDeferredPublishFee($accountList, $publishFeePerItem);

        $images = ['🎮', '📺', '🎨', '🎵', '🤖', '📦', '🔑', '💎', '🌟', '🚀'];

        $product = [
            'id' => genId(),
            'seller_id' => $userId,
            'seller_name' => sanitizeString($user['username']),
            'title' => $title,
            'category' => $category,
            'price' => $price,
            'stock' => count($accountList),
            'description' => $description,
            'account_list' => $accountList,
            'pickup_password_enabled' => $pickupPasswordEnabled,
            'pickup_password' => '',
            'sales' => 0,
            'created_at' => time(),
            'image' => $customImage !== '' ? $customImage : $images[array_rand($images)]
        ];

        $db->addProduct($product);
        unset($product['account_list'], $product['pickup_password']);
        jsonResponse(['success' => true, 'message' => '发布成功', 'product' => $product]);

    case 'update':
        $userId = requireAuth();
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的商品ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (($product['seller_id'] ?? '') !== $userId && ($_SESSION['user_role'] ?? '') !== 'admin') {
            jsonResponse(['success' => false, 'message' => '无权修改该商品'], 403);
        }

        $title = sanitizeString($_POST['title'] ?? '');
        $category = sanitizeString($_POST['category'] ?? '其他');
        $price = floatval($_POST['price'] ?? 0);
        $description = sanitizeMarkdown($_POST['description'] ?? '');
        $customImage = normalizeProductImage($_POST['image'] ?? '');
        $pickupPasswordEnabled = ($_POST['pickup_password_enabled'] ?? '0') === '1';

        if ($title === '' || strlen($title) > 100) {
            jsonResponse(['success' => false, 'message' => '请填写标题（最多100字符）'], 400);
        }
        if ($price <= 0 || $price > 999999) {
            jsonResponse(['success' => false, 'message' => '请填写有效价格（最高999999）'], 400);
        }
        $product['title'] = $title;
        $product['category'] = $category;
        $product['price'] = $price;
        $product['description'] = $description;
        if ($customImage !== '') {
            $product['image'] = $customImage;
        }
        $product['pickup_password_enabled'] = $pickupPasswordEnabled;
        $product['pickup_password'] = '';
        $product['updated_at'] = time();
        $db->updateProduct($product);
        $safe = $product;
        unset($safe['account_list'], $safe['pickup_password']);
        jsonResponse(['success' => true, 'message' => '商品已更新', 'product' => $safe]);

    case 'add_stock':
        $userId = requireAuth();
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的商品ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (($product['seller_id'] ?? '') !== $userId && ($_SESSION['user_role'] ?? '') !== 'admin') {
            jsonResponse(['success' => false, 'message' => '无权修改该商品'], 403);
        }
        $user = getCurrentUser();
        $levels = $db->getMembershipLevels();
        $userLevel = $levels[$user['membership_level'] ?? 'Free'] ?? $levels['Free'];
        $accountListText = trim((string)($_POST['account_list'] ?? ''));
        if ($accountListText === '') {
            jsonResponse(['success' => false, 'message' => '请填写要添加的库存账号'], 400);
        }
        $accountLines = preg_split('/\r\n|\r|\n/', $accountListText);
        $newAccounts = [];
        foreach ($accountLines as $line) {
            $account = parseAccountLine($line);
            if ($account) {
                $newAccounts[] = $account;
            }
        }
        if (empty($newAccounts)) {
            jsonResponse(['success' => false, 'message' => '库存账号格式不正确，请至少填写一行账号信息'], 400);
        }
        $oldAccounts = is_array($product['account_list'] ?? null) ? $product['account_list'] : [];
        $maxAccountsPerProduct = intval($userLevel['max_accounts_per_product'] ?? 0);
        if ($maxAccountsPerProduct > 0 && count($oldAccounts) + count($newAccounts) > $maxAccountsPerProduct) {
            jsonResponse(['success' => false, 'message' => "您当前会员等级单个商品最多{$maxAccountsPerProduct}个账户，当前已有" . count($oldAccounts) . '个，本次添加' . count($newAccounts) . '个'], 400);
        }
        $publishFeePerItem = floatval($userLevel['publish_fee_per_account'] ?? 0);
        markDeferredPublishFee($newAccounts, $publishFeePerItem);
        $product['account_list'] = array_merge($oldAccounts, $newAccounts);
        $product['stock'] = count(array_filter($product['account_list'], fn($item) => empty($item['sold'])));
        $product['updated_at'] = time();
        $db->updateProduct($product);
        $safe = $product;
        unset($safe['account_list'], $safe['pickup_password']);
        jsonResponse(['success' => true, 'message' => '库存已添加 +' . count($newAccounts), 'product' => $safe]);

    case 'stock':
        $userId = requireAuth();
        $id = $_GET['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的商品ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (($product['seller_id'] ?? '') !== $userId && ($_SESSION['user_role'] ?? '') !== 'admin') {
            jsonResponse(['success' => false, 'message' => '无权查看该商品库存'], 403);
        }
        $stock = [];
        $accountList = is_array($product['account_list'] ?? null) ? $product['account_list'] : [];
        foreach ($accountList as $index => $item) {
            if (is_array($item)) {
                $item['password'] = decryptSensitive($item['password'] ?? '');
                $item['fresh_token'] = decryptSensitive($item['fresh_token'] ?? '');
                $item['content'] = trim((string)($item['content'] ?? ''));
                if ($item['content'] === '') {
                    $values = array_filter([
                        trim((string)($item['email'] ?? '')),
                        trim((string)($item['password'] ?? '')),
                        trim((string)($item['client_id'] ?? '')),
                        trim((string)($item['fresh_token'] ?? '')),
                    ], fn($value) => $value !== '' && $value !== 'N/A');
                    $item['content'] = $values ? implode(' | ', $values) : '库存内容为空';
                }
            } else {
                $item = ['content' => trim((string)$item)];
            }
            $item['index'] = $index;
            $item['sold'] = !empty($item['sold']);
            if (!empty($item['sold'])) {
                $item['buyer_name'] = trim((string)($item['buyer_name'] ?? ''));
            } else {
                unset($item['buyer_name'], $item['buyer_id']);
            }
            $stock[] = $item;
        }
        enrichSoldStockBuyers($id, $stock);
        $safe = $product;
        unset($safe['account_list'], $safe['pickup_password']);
        jsonResponse([
            'success' => true,
            'product' => $safe,
            'stock' => $stock,
            'unsold_count' => count(array_filter($stock, fn($item) => empty($item['sold']))),
            'sold_count' => count(array_filter($stock, fn($item) => !empty($item['sold']))),
        ]);

    case 'delete_stock':
        $userId = requireAuth();
        $id = $_POST['id'] ?? '';
        $stockIndex = filter_var($_POST['stock_index'] ?? null, FILTER_VALIDATE_INT);
        if (!validateId($id) || $stockIndex === false || $stockIndex < 0) {
            jsonResponse(['success' => false, 'message' => '库存参数无效'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (($product['seller_id'] ?? '') !== $userId && ($_SESSION['user_role'] ?? '') !== 'admin') {
            jsonResponse(['success' => false, 'message' => '无权删除该商品库存'], 403);
        }
        $accountList = is_array($product['account_list'] ?? null) ? array_values($product['account_list']) : [];
        if (!isset($accountList[$stockIndex])) {
            jsonResponse(['success' => false, 'message' => '库存不存在或已删除'], 404);
        }
        $deletedItem = $accountList[$stockIndex];
        array_splice($accountList, $stockIndex, 1);
        $refundUserId = (string)($product['seller_id'] ?? $userId);
        $refundAmount = refundDeletedUnsoldStock($refundUserId, $product['title'] ?? '', [$deletedItem]);
        $product['account_list'] = array_values($accountList);
        $product['stock'] = count(array_filter($product['account_list'], fn($item) => empty($item['sold'])));
        $product['updated_at'] = time();
        $db->updateProduct($product);
        $safe = $product;
        unset($safe['account_list'], $safe['pickup_password']);
        $message = '库存已删除';
        if ($refundAmount > 0) {
            $message .= '，已退还 ¥' . number_format($refundAmount, 2, '.', '');
        }
        jsonResponse(['success' => true, 'message' => $message, 'product' => $safe, 'refund_amount' => $refundAmount]);

    case 'delete_stock_batch':
        $userId = requireAuth();
        $id = $_POST['id'] ?? '';
        $mode = $_POST['mode'] ?? '';
        if (!validateId($id) || !in_array($mode, ['all', 'selected', 'unsold', 'sold'], true)) {
            jsonResponse(['success' => false, 'message' => '批量删除参数无效'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (($product['seller_id'] ?? '') !== $userId && ($_SESSION['user_role'] ?? '') !== 'admin') {
            jsonResponse(['success' => false, 'message' => '无权删除该商品库存'], 403);
        }
        $accountList = is_array($product['account_list'] ?? null) ? array_values($product['account_list']) : [];
        $deleteIndexes = [];
        if ($mode === 'selected') {
            $rawIndexes = json_decode((string)($_POST['stock_indexes'] ?? '[]'), true);
            if (!is_array($rawIndexes)) {
                jsonResponse(['success' => false, 'message' => '请选择要删除的库存'], 400);
            }
            $deleteIndexes = array_values(array_unique(array_filter(array_map('intval', $rawIndexes), fn($index) => $index >= 0)));
        } else {
            foreach ($accountList as $index => $item) {
                $isSold = !empty($item['sold']);
                if ($mode === 'all' || ($mode === 'unsold' && !$isSold) || ($mode === 'sold' && $isSold)) {
                    $deleteIndexes[] = $index;
                }
            }
        }
        if (empty($deleteIndexes)) {
            jsonResponse(['success' => false, 'message' => '没有符合条件的库存可删除'], 400);
        }
        $deleteMap = array_flip($deleteIndexes);
        $newAccountList = [];
        $deletedCount = 0;
        $deletedUnsoldCount = 0;
        $deletedItems = [];
        foreach ($accountList as $index => $item) {
            if (isset($deleteMap[$index])) {
                $deletedCount++;
                $deletedItems[] = $item;
                if (empty($item['sold'])) {
                    $deletedUnsoldCount++;
                }
                continue;
            }
            $newAccountList[] = $item;
        }
        if ($deletedCount <= 0) {
            jsonResponse(['success' => false, 'message' => '库存不存在或已删除'], 404);
        }
        $refundUserId = (string)($product['seller_id'] ?? $userId);
        $refundAmount = refundDeletedUnsoldStock($refundUserId, $product['title'] ?? '', $deletedItems);
        $product['account_list'] = array_values($newAccountList);
        $product['stock'] = count(array_filter($product['account_list'], fn($item) => empty($item['sold'])));
        $product['updated_at'] = time();
        $db->updateProduct($product);
        $safe = $product;
        unset($safe['account_list'], $safe['pickup_password']);
        $message = '已删除库存 ' . $deletedCount . ' 条';
        if ($refundAmount > 0) {
            $message .= '，已退还 ¥' . number_format($refundAmount, 2, '.', '');
        }
        jsonResponse(['success' => true, 'message' => $message, 'product' => $safe, 'deleted_count' => $deletedCount, 'deleted_unsold_count' => $deletedUnsoldCount, 'refund_amount' => $refundAmount]);

    case 'clear_stock':
        $userId = requireAuth();
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的商品ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (($product['seller_id'] ?? '') !== $userId && ($_SESSION['user_role'] ?? '') !== 'admin') {
            jsonResponse(['success' => false, 'message' => '无权清空该商品库存'], 403);
        }
        $accountList = is_array($product['account_list'] ?? null) ? array_values($product['account_list']) : [];
        $soldAccounts = array_values(array_filter($accountList, fn($item) => !empty($item['sold'])));
        $deletedCount = count($accountList) - count($soldAccounts);
        if ($deletedCount <= 0) {
            jsonResponse(['success' => false, 'message' => '没有可清空的未售库存'], 400);
        }
        $deletedItems = array_values(array_filter($accountList, fn($item) => empty($item['sold'])));
        $refundUserId = (string)($product['seller_id'] ?? $userId);
        $refundAmount = refundDeletedUnsoldStock($refundUserId, $product['title'] ?? '', $deletedItems);
        $product['account_list'] = $soldAccounts;
        $product['stock'] = 0;
        $product['updated_at'] = time();
        $db->updateProduct($product);
        $safe = $product;
        unset($safe['account_list'], $safe['pickup_password']);
        $message = '已清空未售库存 ' . $deletedCount . ' 条';
        if ($refundAmount > 0) {
            $message .= '，已退还 ¥' . number_format($refundAmount, 2, '.', '');
        }
        jsonResponse(['success' => true, 'message' => $message, 'product' => $safe, 'deleted_count' => $deletedCount, 'refund_amount' => $refundAmount]);

    case 'delete':
        $userId = requireAuth();
        global $db;
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $product = $db->getProductById($id);

        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if ($product['seller_id'] !== $userId && $_SESSION['user_role'] !== 'admin') {
            jsonResponse(['success' => false, 'message' => '无权删除'], 403);
        }

        $db->deleteProduct($id);
        jsonResponse(['success' => true, 'message' => '删除成功']);

    case 'my_products':
        $userId = requireAuth();
        $products = $db->getProducts(['seller_id' => $userId]);
        foreach ($products as &$p) {
            unset($p['account_list'], $p['pickup_password']);
        }
        jsonResponse(['success' => true, 'products' => $products]);

    case 'buy':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        $id = $_POST['id'] ?? '';
        $quantity = max(1, min(100, intval($_POST['quantity'] ?? 1)));
        $pickupPassword = trim((string)($_POST['pickup_password'] ?? ''));
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $product = $db->getProductById($id);

        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if ($product['stock'] < $quantity) {
            jsonResponse(['success' => false, 'message' => '库存不足'], 400);
        }
        if ($product['seller_id'] === $userId) {
            jsonResponse(['success' => false, 'message' => '不能购买自己的商品'], 400);
        }

        if (!empty($product['pickup_password_enabled']) && $pickupPassword === '') {
            jsonResponse(['success' => false, 'message' => '请填写取卡密码，后续查看发货需要使用'], 400);
        }
        if (mb_strlen($pickupPassword) > 100) {
            jsonResponse(['success' => false, 'message' => '取卡密码最多100字符'], 400);
        }

        $price = $product['price'] * $quantity;
        if (floatval($user['balance'] ?? 0) < 0) {
            jsonResponse(['success' => false, 'message' => '当前余额为负数，请先补足欠款后再购买'], 400);
        }
        if ($user['balance'] < $price) {
            jsonResponse(['success' => false, 'message' => '余额不足'], 400);
        }

        $db->updateUser($userId, ['balance' => $user['balance'] - $price]);
        $purchaseResult = completeProductPurchase($product, $user, $quantity, 'balance', $pickupPassword);
        if (empty($purchaseResult['success'])) {
            $db->updateUser($userId, ['balance' => $user['balance']]);
            jsonResponse(['success' => false, 'message' => $purchaseResult['message'] ?? '购买失败'], 400);
        }
        $order = $purchaseResult['order'];
        $fee = $purchaseResult['fee'];
        $db->createPaymentOrder([
            'trade_no' => 'BAL' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $userId,
            'payment_config_id' => 'balance',
            'pay_type' => 'balance',
            'amount' => -$price,
            'actual_amount' => -$price,
            'fee' => $fee,
            'status' => 'paid',
            'type' => 'product_purchase',
            'title' => '余额支付商品订单',
            'description' => '购买商品：' . $product['title'] . ' × ' . $quantity,
            'product_id' => $product['id'],
            'quantity' => $quantity,
            'related_id' => $order['id'],
            'paid_at' => time()
        ]);

        jsonResponse(['success' => true, 'message' => '购买成功', 'order' => $order]);

    case 'comment':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();

        $productId = $_POST['product_id'] ?? '';
        $orderId = $_POST['order_id'] ?? '';
        $rating = intval($_POST['rating'] ?? 0);
        $content = sanitizeString($_POST['content'] ?? '');

        if (!validateId($productId) || !validateId($orderId)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        if ($rating < 1 || $rating > 5) {
            jsonResponse(['success' => false, 'message' => '评分需在1-5之间'], 400);
        }
        if (strlen($content) > 500) {
            jsonResponse(['success' => false, 'message' => '评论内容最多500字符'], 400);
        }
        if ($db->hasComment($userId, $productId, $orderId)) {
            jsonResponse(['success' => false, 'message' => '您已评价过此订单'], 400);
        }
        $order = $db->getOrderById($orderId);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在，无法评价'], 404);
        }
        if (($order['buyer_id'] ?? '') !== $userId) {
            jsonResponse(['success' => false, 'message' => '只能评价自己的购买订单'], 403);
        }
        if (($order['product_id'] ?? '') !== $productId) {
            jsonResponse(['success' => false, 'message' => '订单与商品不匹配'], 400);
        }

        $comment = [
            'id' => genId(),
            'product_id' => $productId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'username' => sanitizeString($user['username']),
            'rating' => $rating,
            'content' => $content,
            'created_at' => time()
        ];

        $db->addComment($comment);
        jsonResponse(['success' => true, 'message' => '评价成功']);

    case 'reviews':
        $userId = requireAuth();
        $productId = $_GET['product_id'] ?? '';
        $comments = $productId && validateId($productId) ? $db->getComments($productId) : $db->getComments();
        $visible = [];
        foreach ($comments as $comment) {
            $product = $db->getProductById($comment['product_id'] ?? '');
            if (!$product) continue;
            $order = $db->getOrderById($comment['order_id'] ?? '');
            if (($product['seller_id'] ?? '') !== $userId && ($comment['user_id'] ?? '') !== $userId && ($_SESSION['user_role'] ?? '') !== 'admin') {
                continue;
            }
            $comment['product_title'] = $product['title'] ?? '';
            $comment['seller_id'] = $product['seller_id'] ?? '';
            $comment['buyer_name'] = $comment['username'] ?? '';
            $comment['order_price'] = $order['price'] ?? 0;
            $visible[] = $comment;
        }
        usort($visible, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        jsonResponse(['success' => true, 'comments' => $visible]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
