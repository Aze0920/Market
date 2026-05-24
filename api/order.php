<?php
/**
 * 订单相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

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
        }
        usort($orders, fn($a, $b) => $b['purchase_date'] - $a['purchase_date']);
        jsonResponse(['success' => true, 'orders' => $orders]);

    case 'my_sales':
        $userId = requireAuth();
        $orders = $db->getOrders($userId, 'seller');
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

        jsonResponse(['success' => true, 'order' => $order]);

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
