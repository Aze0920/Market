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

switch ($action) {
    case 'my_orders':
        $userId = requireAuth();
        $orders = $db->getOrders($userId, 'buyer');
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
