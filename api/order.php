<?php
/**
 * 订单相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/../core/OrderTradeNo.php';
require_once __DIR__ . '/../core/NotifyMail.php';

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

function genGuestQueryCode() {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function safeGuestOrderForResponse($order) {
    unset($order['guest_token']);
    unset($order['guest_query_code']);
    return safeOrderForResponse($order);
}

function findGuestOrderByEmailCode($email, $code) {
    global $db;
    $email = strtolower(trim((string)$email));
    $code = strtoupper(trim((string)$code));
    foreach ($db->getOrders() as $order) {
        if (empty($order['guest_order'])) continue;
        if (strtolower((string)($order['guest_email'] ?? '')) === $email && strtoupper((string)($order['guest_query_code'] ?? '')) === $code) {
            return $order;
        }
    }
    return null;
}

function maskDeliveryInfo($deliveryInfo) {
    if (!is_array($deliveryInfo)) return $deliveryInfo;
    if (isset($deliveryInfo['pickup_password_hash'])) unset($deliveryInfo['pickup_password_hash']);
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

function anonymizeGuestBuyerForSeller($order) {
    if (empty($order['guest_order'])) return $order;
    $order['buyer_id'] = '';
    $order['buyer_name'] = '游客买家';
    unset($order['guest_token']);
    return $order;
}

function safeOrderForResponse($order) {
    if (isset($order['delivery_info']) && is_array($order['delivery_info'])) {
        unset($order['delivery_info']['pickup_password_hash']);
        if (isset($order['delivery_info']['items']) && is_array($order['delivery_info']['items'])) {
            foreach ($order['delivery_info']['items'] as &$item) {
                $item['password'] = decryptSensitive($item['password'] ?? '');
                $item['fresh_token'] = decryptSensitive($item['fresh_token'] ?? '');
            }
            unset($item);
        }
    }
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
    $db->updateUser($seller['id'], [
        'balance' => $currentBalance - $amount,
        'frozen_balance' => $currentFrozen + $amount
    ]);
    $order['balance_frozen'] = $amount > 0;
    $order['frozen_amount'] = $amount;
    $order['frozen_at'] = time();
    return true;
}

function attachPaymentTradeNoToOrder($order) {
    global $db;
    return OrderTradeNo::attachToOrder($order, $db);
}

function attachPaymentTradeNoToOrders(array $orders) {
    global $db;
    return OrderTradeNo::attachToOrders($orders, $db);
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

function resolveComplaintRefundToBuyer(&$order, $operatorUsername = '') {
    global $db;
    if (empty($order['complaint']) || !is_array($order['complaint'])) {
        return [false, '该订单没有投诉记录'];
    }
    if (!empty($order['complaint']['funds_settled'])) {
        return [false, '该投诉资金已处理，不能重复退款'];
    }
    $amount = max(0, floatval($order['complaint']['funds_amount'] ?? $order['frozen_amount'] ?? 0));
    $buyer = $db->getUserById($order['buyer_id'] ?? '');
    if (!$buyer) {
        return [false, '买家账号不存在，无法退款到余额，请联系管理员处理'];
    }
    $seller = $db->getUserById($order['seller_id'] ?? '');
    if (!$seller) {
        return [false, '卖家不存在'];
    }
    if ($amount <= 0) {
        $order['complaint']['funds_settled'] = true;
        $order['complaint']['funds_settled_at'] = time();
        $order['complaint']['funds_action'] = 'none';
        $order['complaint']['funds_amount'] = 0;
        $order['complaint']['status'] = 'rejected';
        $order['complaint']['updated_at'] = time();
        return [true, '投诉已关闭'];
    }
    if (empty($order['balance_frozen'])) {
        return [false, '订单冻结状态异常，请联系管理员处理'];
    }
    $sellerFrozen = floatval($seller['frozen_balance'] ?? 0);
    if ($sellerFrozen + 0.00001 < $amount) {
        return [false, '冻结余额不足，无法退款'];
    }
    $currentAction = (string)($order['complaint']['funds_action'] ?? 'frozen');
    $db->updateUser($seller['id'], [
        'frozen_balance' => max(0, $sellerFrozen - $amount)
    ]);
    $db->updateUser($buyer['id'], [
        'balance' => floatval($buyer['balance'] ?? 0) + $amount
    ]);
    $order['balance_frozen'] = false;
    $order['frozen_released_at'] = time();
    if (!isset($order['complaint']['funds_history']) || !is_array($order['complaint']['funds_history'])) {
        $order['complaint']['funds_history'] = [];
    }
    $order['complaint']['funds_history'][] = [
        'from' => $currentAction !== '' ? $currentAction : 'frozen',
        'to' => 'refund_to_buyer',
        'amount' => $amount,
        'created_at' => time(),
        'by' => $operatorUsername
    ];
    $order['complaint']['funds_action'] = 'refund_to_buyer';
    $order['complaint']['funds_settled'] = true;
    $order['complaint']['funds_settled_at'] = time();
    $order['complaint']['funds_amount'] = $amount;
    $order['complaint']['status'] = 'rejected';
    $order['complaint']['updated_at'] = time();
    return [true, '已退款 ¥' . number_format($amount, 2, '.', '') . ' 到买家余额'];
}

switch ($action) {
    case 'my_orders':
        $userId = requireAuth();
        $orders = $db->getOrders($userId, 'buyer');
        attachPaymentTradeNoToOrders($orders);
        foreach ($orders as &$order) {
            $order['has_comment'] = $db->hasComment($userId, $order['product_id'] ?? '', $order['id'] ?? '');
            if (!empty($order['delivery_info']['pickup_password_enabled'])) {
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
        attachPaymentTradeNoToOrders($orders);
        foreach ($orders as &$order) {
            $order = anonymizeGuestBuyerForSeller(safeOrderForResponse($order));
        }
        usort($orders, fn($a, $b) => $b['purchase_date'] - $a['purchase_date']);
        jsonResponse(['success' => true, 'orders' => $orders]);

    case 'get':
        $sessionUserId = $_SESSION['user_id'] ?? '';
        $guestToken = trim((string)($_GET['guest_token'] ?? $_POST['guest_token'] ?? ''));
        $guestEmail = strtolower(trim((string)($_GET['guest_email'] ?? $_POST['guest_email'] ?? '')));
        $guestQueryCode = strtoupper(trim((string)($_GET['guest_query_code'] ?? $_POST['guest_query_code'] ?? '')));
        $userId = $sessionUserId;
        $id = $_GET['id'] ?? '';
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        $order = $db->getOrderById($id);

        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        $guestAllowedByToken = !empty($order['guest_order']) && $guestToken !== '' && hash_equals((string)($order['guest_token'] ?? ''), hash('sha256', $guestToken));
        $guestAllowedByCode = !empty($order['guest_order']) && filter_var($guestEmail, FILTER_VALIDATE_EMAIL) && preg_match('/^[A-Z0-9]{8,12}$/', $guestQueryCode) && strtolower((string)($order['guest_email'] ?? '')) === $guestEmail && hash_equals(strtoupper((string)($order['guest_query_code'] ?? '')), $guestQueryCode);
        $guestAllowed = $guestAllowedByToken || $guestAllowedByCode;
        if (($sessionUserId === '' || ($order['buyer_id'] !== $userId && $order['seller_id'] !== $userId)) && !$guestAllowed) {
            jsonResponse(['success' => false, 'message' => '无权查看'], 403);
        }

        $pickupPassword = trim((string)($_GET['pickup_password'] ?? $_POST['pickup_password'] ?? ''));
        if (!empty($order['delivery_info']['pickup_password_enabled']) && ($order['buyer_id'] === $userId || $guestAllowed)) {
            $hash = (string)($order['delivery_info']['pickup_password_hash'] ?? '');
            if ($hash === '' || $pickupPassword === '' || !password_verify($pickupPassword, $hash)) {
                $order['delivery_info'] = maskDeliveryInfo($order['delivery_info'] ?? []);
                $order['pickup_password_required'] = true;
            } else {
                if (isset($order['delivery_info']['locked'])) $order['delivery_info']['locked'] = false;
                $order['delivery_info']['password_required'] = false;
                $order['pickup_password_required'] = false;
            }
        }

        if (($order['seller_id'] ?? '') === $sessionUserId && !empty($order['guest_order'])) {
            $order = anonymizeGuestBuyerForSeller($order);
        }

        $wrapped = [$order];
        attachPaymentTradeNoToOrders($wrapped);
        $order = $wrapped[0];

        jsonResponse(['success' => true, 'order' => $guestAllowed ? safeGuestOrderForResponse($order) : safeOrderForResponse($order)]);

    case 'guest_query':
        $email = strtolower(trim((string)($_POST['email'] ?? $_GET['email'] ?? '')));
        $code = strtoupper(trim((string)($_POST['query_code'] ?? $_GET['query_code'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => '请输入购买时填写的真实邮箱'], 400);
        }
        if (!preg_match('/^[A-Z0-9]{8,12}$/', $code)) {
            jsonResponse(['success' => false, 'message' => '请输入8-12位查询码'], 400);
        }
        $order = findGuestOrderByEmailCode($email, $code);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '未找到订单，请确认邮箱和查询码是否正确'], 404);
        }
        $pickupPassword = trim((string)($_POST['pickup_password'] ?? $_GET['pickup_password'] ?? ''));
        if (!empty($order['delivery_info']['pickup_password_enabled'])) {
            $hash = (string)($order['delivery_info']['pickup_password_hash'] ?? '');
            if ($hash === '' || $pickupPassword === '' || !password_verify($pickupPassword, $hash)) {
                $order['delivery_info'] = maskDeliveryInfo($order['delivery_info'] ?? []);
                $order['pickup_password_required'] = true;
            } else {
                if (isset($order['delivery_info']['locked'])) $order['delivery_info']['locked'] = false;
                $order['delivery_info']['password_required'] = false;
                $order['pickup_password_required'] = false;
            }
        }
        jsonResponse(['success' => true, 'order' => safeGuestOrderForResponse($order)]);

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
        if (!empty($order['complaint']) || intval($order['complaint_withdrawn_at'] ?? 0) > 0) {
            jsonResponse(['success' => false, 'message' => '该订单已提交过投诉，不能重复投诉'], 400);
        }

        $password = genComplaintPassword();
        $config = $db->getSystemConfig();

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
            'messages' => [[
                'role' => 'buyer',
                'user_id' => $userId,
                'username' => $user['username'] ?? '',
                'content' => htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'),
                'created_at' => time()
            ]],
            'funds_amount' => floatval($order['frozen_amount'] ?? 0),
            'funds_action' => 'frozen',
            'funds_settled' => false,
            'created_at' => time(),
            'updated_at' => time()
        ];

        $order = attachPaymentTradeNoToOrder($order);
        $mailResult = NotifyMail::buyerComplaintEmail($order, $password, $config);
        if (empty($mailResult['success'])) {
            releaseSellerOrderBalance($order);
            unset($order['complaint']);
            jsonResponse(['success' => false, 'message' => '投诉密码邮件发送失败：' . ($mailResult['message'] ?? '请检查邮箱配置')], 400);
        }
        NotifyMail::sellerComplaintReceived($order, $user, $config);
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
        if (empty($order['complaint']) || in_array(($order['complaint']['status'] ?? ''), ['resolved', 'rejected', 'withdrawn'], true)) {
            jsonResponse(['success' => false, 'message' => '该订单没有可撤诉的进行中投诉'], 400);
        }
        if (!password_verify($password, $order['complaint']['password_hash'] ?? '')) {
            jsonResponse(['success' => false, 'message' => '撤诉密码错误'], 400);
        }
        releaseSellerOrderBalance($order);
        $order['complaint']['status'] = 'withdrawn';
        $order['complaint']['withdrawn_at'] = time();
        $order['complaint']['updated_at'] = time();
        $order['complaint']['funds_action'] = 'released_to_seller_by_withdrawal';
        $order['complaint']['funds_settled'] = true;
        $order['complaint']['funds_settled_at'] = time();
        $order['complaint_withdrawn_at'] = time();
        $config = $db->getSystemConfig();
        $db->updateOrder($order);
        $notifyOrder = attachPaymentTradeNoToOrder($order);
        NotifyMail::sellerComplaintWithdrawn($notifyOrder, $config);
        NotifyMail::buyerComplaintWithdrawn($notifyOrder, $config);
        jsonResponse(['success' => true, 'message' => '已撤诉，冻结金额已解冻，投诉记录已保留']);

    case 'reply_complaint':
        $userId = requireAuth();
        $user = getCurrentUser();
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
        $isBuyer = (($order['buyer_id'] ?? '') === $userId);
        $isSeller = (($order['seller_id'] ?? '') === $userId);
        if (!$isBuyer && !$isSeller) {
            jsonResponse(['success' => false, 'message' => '无权回复该投诉'], 403);
        }
        if (empty($order['complaint']) || in_array(($order['complaint']['status'] ?? ''), ['resolved', 'rejected', 'withdrawn'], true)) {
            jsonResponse(['success' => false, 'message' => '该投诉已结束，不能继续回复'], 400);
        }
        if (!isset($order['complaint']['messages']) || !is_array($order['complaint']['messages'])) {
            $order['complaint']['messages'] = [];
            if (!empty($order['complaint']['reason'])) {
                $order['complaint']['messages'][] = [
                    'role' => 'buyer',
                    'user_id' => $order['buyer_id'] ?? '',
                    'username' => $order['buyer_name'] ?? '买家',
                    'content' => $order['complaint']['reason'],
                    'created_at' => $order['complaint']['created_at'] ?? time()
                ];
            }
            if (!empty($order['complaint']['seller_reply'])) {
                $order['complaint']['messages'][] = [
                    'role' => 'seller',
                    'user_id' => $order['seller_id'] ?? '',
                    'username' => $order['seller_name'] ?? '卖家',
                    'content' => $order['complaint']['seller_reply'],
                    'created_at' => $order['complaint']['seller_replied_at'] ?? ($order['complaint']['updated_at'] ?? time())
                ];
            }
        }
        $role = $isSeller ? 'seller' : 'buyer';
        $order['complaint']['messages'][] = [
            'role' => $role,
            'user_id' => $userId,
            'username' => $user['username'] ?? ($role === 'seller' ? '卖家' : '买家'),
            'content' => htmlspecialchars($reply, ENT_QUOTES, 'UTF-8'),
            'created_at' => time()
        ];
        if ($isSeller) {
            $order['complaint']['seller_reply'] = htmlspecialchars($reply, ENT_QUOTES, 'UTF-8');
            $order['complaint']['seller_replied_at'] = time();
        }
        $order['complaint']['updated_at'] = time();
        $config = $db->getSystemConfig();
        $db->updateOrder($order);
        $notifyOrder = attachPaymentTradeNoToOrder($order);
        if ($isSeller) {
            NotifyMail::buyerSellerReply($notifyOrder, htmlspecialchars($reply, ENT_QUOTES, 'UTF-8'), $config);
        } elseif ($isBuyer) {
            NotifyMail::sellerBuyerReply($notifyOrder, htmlspecialchars($reply, ENT_QUOTES, 'UTF-8'), $config);
        }
        jsonResponse(['success' => true, 'message' => '回复已提交']);

    case 'seller_refund_complaint':
        $userId = requireAuth();
        $user = getCurrentUser();
        $id = $_POST['order_id'] ?? '';
        $note = trim((string)($_POST['note'] ?? ''));
        if (!validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的订单ID'], 400);
        }
        if ($note !== '' && mb_strlen($note) > 500) {
            jsonResponse(['success' => false, 'message' => '退款说明最多500字'], 400);
        }
        $order = $db->getOrderById($id);
        if (!$order) {
            jsonResponse(['success' => false, 'message' => '订单不存在'], 404);
        }
        if (($order['seller_id'] ?? '') !== $userId) {
            jsonResponse(['success' => false, 'message' => '只有卖家可以操作退款'], 403);
        }
        if (empty($order['complaint']) || in_array(($order['complaint']['status'] ?? ''), ['resolved', 'rejected', 'withdrawn'], true)) {
            jsonResponse(['success' => false, 'message' => '该投诉已结束，无法退款'], 400);
        }
        [$ok, $message] = resolveComplaintRefundToBuyer($order, $user['username'] ?? 'seller');
        if (!$ok) {
            jsonResponse(['success' => false, 'message' => $message], 400);
        }
        $refundNote = $note !== '' ? htmlspecialchars($note, ENT_QUOTES, 'UTF-8') : '卖家同意退款，冻结金额已退还给买家';
        if (!isset($order['complaint']['messages']) || !is_array($order['complaint']['messages'])) {
            $order['complaint']['messages'] = [];
        }
        $order['complaint']['messages'][] = [
            'role' => 'seller',
            'user_id' => $userId,
            'username' => $user['username'] ?? '卖家',
            'content' => $refundNote,
            'created_at' => time()
        ];
        $order['complaint']['seller_reply'] = $refundNote;
        $order['complaint']['seller_replied_at'] = time();
        $order['complaint']['seller_refunded_at'] = time();
        $order['complaint']['seller_refunded_by'] = $user['username'] ?? 'seller';
        $refundAmount = max(0, floatval($order['complaint']['funds_amount'] ?? $order['frozen_amount'] ?? 0));
        $config = $db->getSystemConfig();
        $db->updateOrder($order);
        $notifyOrder = attachPaymentTradeNoToOrder($order);
        NotifyMail::buyerSellerRefund($notifyOrder, $refundAmount, $refundNote, $config);
        NotifyMail::sellerComplaintRefundDone($notifyOrder, $refundAmount, $refundNote, $config);
        jsonResponse(['success' => true, 'message' => $message]);

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
