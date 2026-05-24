<?php
/**
 * 商品相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

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

function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function sanitizeMarkdown($str) {
    return trim((string)$str);
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
            'password' => $parts[1],
            'client_id' => sanitizeString($parts[2] ?? 'N/A'),
            'fresh_token' => $parts[3] ?? 'N/A',
            'content' => $line,
            'format' => 'pipe',
            'sold' => false
        ];
    }

    return [
        'email' => 'N/A',
        'password' => 'N/A',
        'client_id' => 'N/A',
        'fresh_token' => 'N/A',
        'content' => $line,
        'format' => 'line',
        'sold' => false
    ];
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

switch ($action) {
    case 'list':
        $filters = [
            'stock_min' => isset($_GET['stock_min']) ? max(0, (int)$_GET['stock_min']) : 1,
            'category' => sanitizeString($_GET['category'] ?? 'all'),
            'search' => sanitizeString($_GET['search'] ?? '')
        ];
        $products = $db->getProducts($filters);
        foreach ($products as &$p) {
            unset($p['account_list']);
        }
        jsonResponse(['success' => true, 'products' => $products]);

    case 'get':
        $id = $_GET['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        unset($product['account_list']);
        jsonResponse(['success' => true, 'product' => $product]);

    case 'detail':
        $id = $_GET['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        $safe = $product;
        unset($safe['account_list']);
        $comments = $db->getComments($id);
        jsonResponse(['success' => true, 'product' => $safe, 'comments' => $comments]);

    case 'publish':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        if (!$user) {
            productPublishFail('登录状态异常，请退出后重新登录');
        }
        $levels = $db->getMembershipLevels();
        $userLevel = $levels[$user['membership_level'] ?? 'Free'] ?? $levels['Free'];

        $existingProducts = $db->getProducts(['seller_id' => $userId]);
        if (count($existingProducts) >= $userLevel['max_products']) {
            productPublishFail("您当前会员等级最多只能发布{$userLevel['max_products']}个商品，当前已发布" . count($existingProducts) . "个", [
                'membership_level' => $user['membership_level'] ?? 'Free',
                'existing_products' => count($existingProducts),
                'max_products' => $userLevel['max_products']
            ]);
        }

        $title = sanitizeString($_POST['title'] ?? '');
        $category = sanitizeString($_POST['category'] ?? '其他');
        $price = floatval($_POST['price'] ?? 0);
        $description = sanitizeMarkdown($_POST['description'] ?? '');
        $accountListText = trim($_POST['account_list'] ?? '');

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

        if (count($accountList) > $userLevel['max_accounts_per_product']) {
            productPublishFail("您当前会员等级单个商品最多添加{$userLevel['max_accounts_per_product']}个账户，当前填写" . count($accountList) . "个", [
                'membership_level' => $user['membership_level'] ?? 'Free',
                'account_count' => count($accountList),
                'max_accounts_per_product' => $userLevel['max_accounts_per_product']
            ]);
        }

        $publishFee = $userLevel['publish_fee_per_account'] * count($accountList);
        if ($user['balance'] < $publishFee) {
            productPublishFail("发布费用不足，需要{$publishFee}元", [
                'balance' => $user['balance'],
                'publish_fee' => $publishFee
            ]);
        }

        if ($publishFee > 0) {
            $db->updateUser($userId, ['balance' => $user['balance'] - $publishFee]);
        }

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
            'sales' => 0,
            'created_at' => time(),
            'image' => $images[array_rand($images)]
        ];

        $db->addProduct($product);
        unset($product['account_list']);
        jsonResponse(['success' => true, 'message' => '发布成功', 'product' => $product]);

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
            unset($p['account_list']);
        }
        jsonResponse(['success' => true, 'products' => $products]);

    case 'buy':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        $id = $_POST['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $product = $db->getProductById($id);

        if (!$product) {
            jsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if ($product['stock'] <= 0) {
            jsonResponse(['success' => false, 'message' => '商品已售罄'], 400);
        }
        if ($product['seller_id'] === $userId) {
            jsonResponse(['success' => false, 'message' => '不能购买自己的商品'], 400);
        }
        if ($user['balance'] < $product['price']) {
            jsonResponse(['success' => false, 'message' => '余额不足'], 400);
        }

        $levels = $db->getMembershipLevels();
        $seller = $db->getUserById($product['seller_id']);
        $sellerLevel = $levels[$seller['membership_level']] ?? $levels['Free'];
        $feeRate = $sellerLevel['fee_rate'];

        $price = $product['price'];
        $sellerAmount = $price * (1 - $feeRate);
        $fee = $price * $feeRate;

        $db->updateUser($userId, ['balance' => $user['balance'] - $price]);

        $deliveryInfo = null;
        $accountIndex = -1;
        foreach ($product['account_list'] as $idx => $acc) {
            if (!$acc['sold']) {
                $deliveryInfo = $acc;
                $accountIndex = $idx;
                break;
            }
        }

        if (!$deliveryInfo) {
            jsonResponse(['success' => false, 'message' => '该商品暂无可用账户'], 400);
        }

        $product['account_list'][$accountIndex]['sold'] = true;
        $product['stock'] -= 1;
        $product['sales'] += 1;
        $db->updateProduct($product);

        if ($seller) {
            $db->updateUser($seller['id'], ['balance' => $seller['balance'] + $sellerAmount]);
        }

        $order = [
            'id' => genId(),
            'buyer_id' => $userId,
            'buyer_name' => sanitizeString($user['username']),
            'seller_id' => $product['seller_id'],
            'seller_name' => sanitizeString($product['seller_name']),
            'product_id' => $product['id'],
            'product_title' => sanitizeString($product['title']),
            'price' => $price,
            'fee' => $fee,
            'seller_amount' => $sellerAmount,
            'purchase_date' => time(),
            'delivery_info' => [
                'email' => $deliveryInfo['email'] ?? '',
                'password' => $deliveryInfo['password'] ?? '',
                'client_id' => $deliveryInfo['client_id'] ?? 'N/A',
                'fresh_token' => $deliveryInfo['fresh_token'] ?? 'N/A',
                'content' => $deliveryInfo['content'] ?? '',
                'format' => $deliveryInfo['format'] ?? 'pipe'
            ]
        ];
        $db->addOrder($order);

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

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
