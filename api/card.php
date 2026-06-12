<?php
/**
 * 卡密充值相关API
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

function genCardCode() {
    return strtoupper(substr(bin2hex(random_bytes(16)), 0, 16));
}

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

function normalizeCardType($type) {
    $type = strtolower(trim((string)$type));
    return in_array($type, ['membership', 'subdomain'], true) ? $type : 'balance';
}

function checkCardUseRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'card_use_attempts_' . md5($ip);
    $attempts = $_SESSION[$key] ?? [];
    $now = time();
    $attempts = array_filter($attempts, fn($t) => ($now - $t) < 900);
    if (count($attempts) >= 20) {
        return false;
    }
    $attempts[] = $now;
    $_SESSION[$key] = $attempts;
    return true;
}

switch ($action) {
    case 'use':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        $code = trim($_POST['code'] ?? '');

        if (!checkCardUseRateLimit()) {
            jsonResponse(['success' => false, 'message' => '尝试过于频繁，请15分钟后再试'], 429);
        }
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

        $cardType = normalizeCardType($card['card_type'] ?? 'balance');

        if ($cardType === 'subdomain') {
            if (!SubdomainHelper::configEnabled($db->getSystemConfig())) {
                jsonResponse(['success' => false, 'message' => '二级域名功能未开启'], 400);
            }
            $prefix = strtolower(trim((string)($_POST['subdomain_prefix'] ?? '')));
            $months = max(1, min(36, intval($card['target_level'] ?? 1)));
            $baseDomain = trim((string)($_POST['subdomain_base_domain'] ?? ''));
            $result = SubdomainHelper::submitApplication($db, $userId, $prefix, $months, [
                'base_domain' => $baseDomain,
                'price_paid' => 0,
            ]);
            if (empty($result['success'])) {
                jsonResponse(['success' => false, 'message' => $result['message'] ?? '兑换失败'], 400);
            }
            $db->useCardCode($code, $userId);
            $db->createPaymentOrder([
                'trade_no' => 'CARD' . date('YmdHis') . rand(1000, 9999),
                'user_id' => $userId,
                'payment_config_id' => 'card',
                'pay_type' => 'card_code',
                'amount' => 0,
                'actual_amount' => 0,
                'fee' => 0,
                'status' => 'paid',
                'type' => 'subdomain_card',
                'title' => '二级域名卡密兑换',
                'description' => '使用卡密申请二级域名 ' . $prefix . ' × ' . $months . ' 个月（待审核）',
                'related_id' => $card['id'] ?? '',
                'paid_at' => time()
            ]);
            jsonResponse([
                'success' => true,
                'message' => '二级域名卡密兑换成功，请等待管理员审核通过后生效',
                'card_type' => 'subdomain',
                'months' => $months,
            ]);
        }

        if ($cardType === 'membership') {
            $levels = $db->getMembershipLevels();
            $targetLevel = trim((string)($card['target_level'] ?? ''));
            if ($targetLevel === '' || strcasecmp($targetLevel, 'Free') === 0 || !isset($levels[$targetLevel])) {
                jsonResponse(['success' => false, 'message' => '无效的会员卡密'], 400);
            }
            $db->useCardCode($code, $userId);
            $db->updateUser($userId, ['membership_level' => $targetLevel]);
            $db->createPaymentOrder([
                'trade_no' => 'CARD' . date('YmdHis') . rand(1000, 9999),
                'user_id' => $userId,
                'payment_config_id' => 'card',
                'pay_type' => 'card_code',
                'amount' => 0,
                'actual_amount' => 0,
                'fee' => 0,
                'status' => 'paid',
                'type' => 'membership_card',
                'title' => '会员卡密兑换',
                'description' => '使用卡密开通 ' . $targetLevel . ' 会员',
                'target_level' => $targetLevel,
                'related_id' => $card['id'] ?? '',
                'paid_at' => time()
            ]);
            jsonResponse([
                'success' => true,
                'message' => '会员兑换成功，已开通 ' . $targetLevel . ' 会员',
                'card_type' => 'membership',
                'target_level' => $targetLevel
            ]);
        }

        $rechargeAmount = floatval($card['amount'] ?? 0);
        if ($rechargeAmount <= 0) {
            jsonResponse(['success' => false, 'message' => '该卡密无效，请联系管理员重新生成'], 400);
        }
        $db->useCardCode($code, $userId);
        $db->updateUser($userId, ['balance' => floatval($user['balance'] ?? 0) + $rechargeAmount]);
        $db->createPaymentOrder([
            'trade_no' => 'CARD' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $userId,
            'payment_config_id' => 'card',
            'pay_type' => 'card_code',
            'amount' => $rechargeAmount,
            'actual_amount' => $rechargeAmount,
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
            'card_type' => 'balance',
            'amount' => $card['amount'],
            'new_balance' => $user['balance'] + $card['amount']
        ]);

    case 'list':
        requireAdmin();
        $onlyUnused = isset($_GET['only_unused']) && $_GET['only_unused'] === '1';
        $cards = $db->getCardCodes($onlyUnused);
        $cards = array_map(function($card) use ($db) {
            $usedUserId = $card['used_by'] ?? '';
            $usedUser = $usedUserId ? $db->getUserById($usedUserId) : null;
            $card['used_user_id'] = $usedUserId ?: '';
            $card['used_user_email'] = $usedUser['email'] ?? '';
            return $card;
        }, $cards);
        jsonResponse(['success' => true, 'cards' => $cards]);

    case 'create':
        requireAdmin();
        $cardType = normalizeCardType($_POST['card_type'] ?? 'balance');
        $amount = floatval($_POST['amount'] ?? 0);
        $targetLevel = trim((string)($_POST['target_level'] ?? ''));
        $count = intval($_POST['count'] ?? 1);

        if ($cardType === 'balance' && ($amount <= 0 || $amount > 1000000)) {
            jsonResponse(['success' => false, 'message' => $amount <= 0 ? '无效的卡密类型或金额' : '无效的金额'], 400);
        }
        if ($cardType === 'membership') {
            $levels = $db->getMembershipLevels();
            if ($targetLevel === '' || strcasecmp($targetLevel, 'Free') === 0 || !isset($levels[$targetLevel])) {
                jsonResponse(['success' => false, 'message' => '请选择有效的会员等级，Free 会员不可生成卡密'], 400);
            }
            $amount = 0;
        }
        if ($cardType === 'subdomain') {
            if (!SubdomainHelper::configEnabled($db->getSystemConfig())) {
                jsonResponse(['success' => false, 'message' => '二级域名功能未开启，请先在系统设置中开启'], 400);
            }
            $months = max(1, min(36, intval($targetLevel !== '' ? $targetLevel : ($_POST['target_level'] ?? 1))));
            $targetLevel = (string)$months;
            $amount = 0;
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
                'card_type' => $cardType,
                'target_level' => in_array($cardType, ['membership', 'subdomain'], true) ? $targetLevel : '',
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

    case 'delete_batch':
        requireAdmin();
        $idsJson = $_POST['ids'] ?? '[]';
        $ids = json_decode($idsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($ids)) {
            jsonResponse(['success' => false, 'message' => '卡密ID列表格式错误'], 400);
        }
        $ids = array_values(array_unique(array_filter(array_map('trim', $ids), fn($id) => validateId($id))));
        if (empty($ids)) {
            jsonResponse(['success' => false, 'message' => '请选择要删除的卡密'], 400);
        }
        $deleted = 0;
        foreach ($ids as $id) {
            if ($db->deleteCardCode($id)) {
                $deleted++;
            }
        }
        if ($deleted === 0) {
            jsonResponse(['success' => false, 'message' => '所选卡密不存在或已被删除'], 400);
        }
        jsonResponse(['success' => true, 'message' => '已删除 ' . $deleted . ' 张卡密', 'deleted' => $deleted]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
