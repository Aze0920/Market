<?php
/**
 * 订单相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
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

function validateId($id) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $id);
}

function genId() {
    return 'id_' . time() . '_' . bin2hex(random_bytes(6));
}

function genComplaintPassword() {
    return str_pad((string)random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
}

function maskDeliveryInfo($deliveryInfo) {
    if (!is_array($deliveryInfo)) return $deliveryInfo;
    $deliveryInfo['items'] = array_map(function($item) {
        return [
            'format' => $item['format'] ?? 'line',
            'content' => '该商品开启了取卡密码，请输入正确密码后查看',
            'email' => '******',
            'password' => '******',
            'client_id' => '******',
            'fresh_token' => '******'
        ];
    }, $deliveryInfo['items'] ?? [$deliveryInfo]);
    $deliveryInfo['locked'] = true;
    return $deliveryInfo;
}

function safeOrderForResponse($order) {
    if (isset($order['complaint']) && is_array($order['complaint'])) {
        unset($order['complaint']['password_hash']);
        unset($order['complaint']['email']);
    }
    return $order;
}

function freezeSellerOrderBalance(&$order) {
    global $db;
    if (!empty($order['balance_frozen'])) return true;
    $seller = $db->getUserById($order['seller_id'] ?? '');
    if (!$seller) return false;
    $amount = max(0, floatval($order['seller_amount'] ?? $order['price'] ?? 0));
    $currentBalance = floatval($seller['balance'] ?? 0);
    $currentFrozen = floatval($seller['frozen_balance'] ?? 0);
    $freezeAmount = min($amount, $currentBalance);
    $db->updateUser($seller['id'], [
        'balance' => $currentBalance - $freezeAmount,
        'frozen_balance' => $currentFrozen + $freezeAmount
    ]);
    $order['balance_frozen'] = true;
    $order['frozen_amount'] = $freezeAmount;
    $order['frozen_at'] = time();
    return true;
}

function releaseSellerOrderBalance(&$order) {
    global $db;
    if (empty($order['balance_frozen'])) return true;
    $seller = $db->getUserById($order['seller_id'] ?? '');
    if (!$seller) return false;
    $amount = max(0, floatval($order['frozen_amount'] ?? 0));
    $currentBalance = floatval($seller['balance'] ?? 0);
    $currentFrozen = floatval($seller['frozen_balance'] ?? 0);
    $db->updateUser($seller['id'], [
        'balance' => $currentBalance + $amount,
        'frozen_balance' => max(0, $currentFrozen - $amount)
    ]);
    $order['balance_frozen'] = false;
    $order['frozen_released_at'] = time();
    return true;
}

switch ($action) {
    case 'my_orders':
        $userId = requireAuth();
        $orders = $db->getOrders($userId, 'buyer');
        foreach ($orders as &$order) {
            $order['has_comment'] = $db->hasComment($userId, $order['product_id'] ?? '', $order['id'] ?? '');
            if (!empty($order['delivery_info']['password_required'])) {
                $order['delivery_info'] = maskDeliveryInfo($order['delivery_info']);
                $order['pickup_password_required'] = true;
            }
            $order = safeOrderForResponse($order);
        }
        usort($orders, fn($a, $b) => $b['purchase_date'] - $a['purchase_date']);
        jsonResponse(['success' => true, 'orders' => $orders]);

    case 'my_sales':
        $userId = requireAuth();
        $orders = $db->getOrders($userId, 'seller');
        foreach ($orders as &$order) {
            $order = safeOrderForResponse($order);
        }
        usort($orders, fn($a, $b) => $b['purchase_date'] - $a['purchase_date']);
        jsonResponse(['success' => true, 'orders' => $orders]);

    case 'get':
        $userId = requireAuth();
        $id = $_GET['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $order = $db->getOrderById($id);

        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        if ($order['buyer_id'] !== $userId && $order['seller_id'] !== $userId) {
            jsonResponse(['success' => false, 'message' => '无权查看'], 403);
        }

        $product = $db->getProductById($order['product_id'] ?? '');
        $pickupPassword = trim((string)($_GET['pickup_password'] ?? $_POST['pickup_password'] ?? ''));
        if ($product && !empty($product['pickup_password_enabled']) && $order['buyer_id'] === $userId) {
            $hash = (string)($product['pickup_password'] ?? '');
            if ($hash === '' || $pickupPassword === '' || !password_verify($pickupPassword, $hash)) {
                $order['delivery_info'] = maskDeliveryInfo($order['delivery_info'] ?? []);
                $order['pickup_password_required'] = true;
            } else {
                if (isset($order['delivery_info']['locked'])) $order['delivery_info']['locked'] = false;
                $order['pickup_password_required'] = false;
            }
        }

        jsonResponse(['success' => true, 'order' => safeOrderForResponse($order)]);

    case 'complain':
        $userId = requireAuth();
        $user = getCurrentUser();
        $id = $_POST['order_id'] ?? '';
        $email = trim((string)($_POST['email'] ?? ''));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的订单ID'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => '请输入有效邮箱，用于接收撤诉密码'], 400);
        }
        if ($reason === '' || mb_strlen($reason) > 500) {
            jsonResponse(['success' => false, 'message' => '请填写投诉原因，最多500字'], 400);
        }
        $order = $db->getOrderById($id);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        if (($order['buyer_id'] ?? '') !== $userId) {
            jsonResponse(['success' => false, 'message' => '只能投诉自己的购买订单'], 403);
        }
        if (!empty($order['complaint']) && ($order['complaint']['status'] ?? '') === 'open') {
            jsonResponse(['success' => false, 'message' => '该订单已被投诉，请勿重复提交'], 400);
        }

        $password = genComplaintPassword();
        $config = $db->getSystemConfig();
        $mailResult = KeyNestMailer::send(
            $email,
            'KeyNest 订单投诉撤诉密码',
            '<p>您正在投诉订单：<strong>' . htmlspecialchars($order['product_title'] ?? $id, ENT_QUOTES, 'UTF-8') . '</strong></p><p>撤诉密码为：</p><h2 style="letter-spacing:4px;">' . $password . '</h2><p>请妥善保存，撤诉时需要输入该密码。</p>',
            $config
        );
        if (empty($mailResult['success'])) {
            jsonResponse(['success' => false, 'message' => '投诉密码邮件发送失败：' . ($mailResult['message'] ?? '请检查邮箱配置')], 400);
        }

        freezeSellerOrderBalance($order);
        $order['complaint'] = [
            'id' => genId(),
            'status' => 'open',
            'buyer_id' => $userId,
            'buyer_name' => $user['username'] ?? '',
            'email' => $email,
            'reason' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'seller_reply' => '',
            'created_at' => time(),
            'updated_at' => time()
        ];
        $db->updateOrder($order);
        jsonResponse(['success' => true, 'message' => '投诉已提交，撤诉密码已发送到邮箱，订单金额已冻结']);

    case 'withdraw_complaint':
        $userId = requireAuth();
        $id = $_POST['order_id'] ?? '';
        $password = trim((string)($_POST['password'] ?? ''));
        if (!validateId($id) || !preg_match('/^\d{8}$/', $password)) {
            jsonResponse(['success' => false, 'message' => '订单ID或撤诉密码无效'], 400);
        }
        $order = $db->getOrderById($id);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        if (($order['buyer_id'] ?? '') !== $userId) {
            jsonResponse(['success' => false, 'message' => '只能撤诉自己的订单'], 403);
        }
        if (empty($order['complaint']) || ($order['complaint']['status'] ?? '') !== 'open') {
            jsonResponse(['success' => false, 'message' => '该订单没有进行中的投诉'], 400);
        }
        if (!password_verify($password, $order['complaint']['password_hash'] ?? '')) {
            jsonResponse(['success' => false, 'message' => '撤诉密码错误'], 400);
        }
        releaseSellerOrderBalance($order);
        $order['complaint']['status'] = 'withdrawn';
        $order['complaint']['withdrawn_at'] = time();
        $order['complaint']['updated_at'] = time();
        $db->updateOrder($order);
        jsonResponse(['success' => true, 'message' => '已撤诉，冻结金额已解冻']);

    case 'reply_complaint':
        $userId = requireAuth();
        $id = $_POST['order_id'] ?? '';
        $reply = trim((string)($_POST['reply'] ?? ''));
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的订单ID'], 400);
        }
        if ($reply === '' || mb_strlen($reply) > 500) {
            jsonResponse(['success' => false, 'message' => '请填写回复内容，最多500字'], 400);
        }
        $order = $db->getOrderById($id);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        if (($order['seller_id'] ?? '') !== $userId) {
            jsonResponse(['success' => false, 'message' => '只能回复自己售出订单的投诉'], 403);
        }
        if (empty($order['complaint']) || ($order['complaint']['status'] ?? '') !== 'open') {
            jsonResponse(['success' => false, 'message' => '该订单没有进行中的投诉'], 400);
        }
        $order['complaint']['seller_reply'] = htmlspecialchars($reply, ENT_QUOTES, 'UTF-8');
        $order['complaint']['seller_replied_at'] = time();
        $order['complaint']['updated_at'] = time();
        $db->updateOrder($order);
        jsonResponse(['success' => true, 'message' => '回复已提交']);

    case 'overview':
        $userId = requireAuth();
        
        $myOrders = $db->getOrders($userId, 'buyer');
        $mySales = $db->getOrders($userId, 'seller');
        $myProducts = $db->getProducts(['seller_id' => $userId]);

        $totalSpent = array_sum(array_column($myOrders, 'price'));
        $totalEarned = array_sum(array_column($mySales, 'price'));
        $activeProducts = count(array_filter($myProducts, fn($p) => $p['stock'] > 0));

        usort($myOrders, fn($a, $b) => $b['purchase_date'] - $a['purchase_date']);
        $recentOrders = array_slice($myOrders, 0, 5);

        jsonResponse([
            'success' => true,
            'overview' => [
                'total_orders' => count($myOrders),
                'total_sales' => count($mySales),
                'total_spent' => $totalSpent,
                'total_earned' => $totalEarned,
                'active_products' => $activeProducts,
                'recent_orders' => $recentOrders
            ]
        ]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
