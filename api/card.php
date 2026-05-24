<?php
/**
 * 卡密充值相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function genId() {
    return 'id_' . time() . '_' . bin2hex(random_bytes(6));
}

function genCardCode() {
    return strtoupper(substr(bin2hex(random_bytes(16)), 0, 16));
}

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

function requireAdmin() {
    global $db;
    $userId = requireAuth();
    $user = $db->getUserById($userId);
    if (!$user || $user['role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => '需要管理员权限'], 403);
    }
    return $userId;
}

function validateId($id) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $id);
}

switch ($action) {
    case 'use':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        $code = trim($_POST['code'] ?? '');

        if (empty($code) || strlen($code) > 50) {
            jsonResponse(['success' => false, 'message' => '无效的卡密'], 400);
        }

        $card = $db->getCardCode($code);
        if (!$card) {
            jsonResponse(['success' => false, 'message' => '无效的卡密'], 400);
        }

        if ($card['used']) {
            jsonResponse(['success' => false, 'message' => '该卡密已被使用'], 400);
        }

        $db->useCardCode($code, $userId);
        $db->updateUser($userId, ['balance' => $user['balance'] + $card['amount']]);
        $db->createPaymentOrder([
            'trade_no' => 'CARD' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $userId,
            'payment_config_id' => 'card',
            'pay_type' => 'card_code',
            'amount' => $card['amount'],
            'actual_amount' => $card['amount'],
            'fee' => 0,
            'status' => 'paid',
            'type' => 'card_recharge',
            'title' => '卡密充值',
            'description' => '使用卡密充值余额',
            'related_id' => $card['id'] ?? '',
            'paid_at' => time()
        ]);

        jsonResponse([
            'success' => true,
            'message' => '充值成功',
            'amount' => $card['amount'],
            'new_balance' => $user['balance'] + $card['amount']
        ]);

    case 'list':
        requireAdmin();
        $onlyUnused = isset($_GET['only_unused']) && $_GET['only_unused'] === '1';
        $cards = $db->getCardCodes($onlyUnused);
        jsonResponse(['success' => true, 'cards' => $cards]);

    case 'create':
        requireAdmin();
        $amount = floatval($_POST['amount'] ?? 0);
        $count = intval($_POST['count'] ?? 1);

        if ($amount <= 0 || $amount > 1000000) {
            jsonResponse(['success' => false, 'message' => '无效的金额'], 400);
        }
        if ($count < 1 || $count > 100) {
            jsonResponse(['success' => false, 'message' => '单次最多生成100张卡密'], 400);
        }

        $createdCards = [];
        for ($i = 0; $i < $count; $i++) {
            $card = [
                'id' => genId(),
                'code' => genCardCode(),
                'amount' => $amount,
                'used' => false,
                'used_by' => null,
                'used_at' => null,
                'created_at' => time(),
                'created_by' => $_SESSION['user_id']
            ];
            $db->addCardCode($card);
            $createdCards[] = $card;
        }

        jsonResponse([
            'success' => true,
            'message' => "成功创建{$count}张卡密",
            'cards' => $createdCards
        ]);

    case 'delete':
        requireAdmin();
        $id = $_POST['id'] ?? '';

        if (empty($id) || !validateId($id)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }

        $db->deleteCardCode($id);
        jsonResponse(['success' => true, 'message' => '卡密已删除']);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
