<?php
/**
 * 会员等级相关API
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

function validateLevel($level) {
    global $db;
    $levels = $db->getMembershipLevels();
    return isset($levels[$level]) && !empty($levels[$level]['enabled']) && !empty($levels[$level]['can_upgrade']);
}

function levelOrderMap($levels) {
    $orders = [];
    foreach ($levels as $name => $level) {
        $orders[$name] = intval($level['priority'] ?? 0);
    }
    return $orders;
}

switch ($action) {
    case 'levels':
        $levels = $db->getMembershipLevels();
        if (!isset($_SESSION['user_id'])) {
            // 未登录用户隐藏价格与费率等商业敏感字段，仅保留展示所需信息
            $sensitiveLevelKeys = ['cost', 'fee_rate', 'publish_fee_per_account'];
            foreach ($levels as $name => &$level) {
                if (!is_array($level)) {
                    continue;
                }
                foreach ($sensitiveLevelKeys as $key) {
                    unset($level[$key]);
                }
            }
            unset($level);
        }
        jsonResponse(['success' => true, 'levels' => $levels]);

    case 'upgrade':
        $userId = requireAuth();
        global $db;
        $user = $db->getUserById($userId);
        $targetLevel = trim($_POST['level'] ?? '');
        $confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === '1';

        if (!validateLevel($targetLevel)) {
            jsonResponse(['success' => false, 'message' => '无效的会员等级'], 400);
        }

        $levels = $db->getMembershipLevels();
        $levelOrder = levelOrderMap($levels);
        $currentLevel = $user['membership_level'];
        $currentOrder = $levelOrder[$currentLevel] ?? 0;
        $targetOrder = $levelOrder[$targetLevel] ?? 0;

        if ($targetOrder <= $currentOrder) {
            jsonResponse(['success' => false, 'message' => '只能升级到更高级别']);
        }

        if (!$confirmed) {
            jsonResponse([
                'success' => false,
                'message' => '升级后无法降级或更换等级，请确认是否升级到' . $targetLevel,
                'require_confirm' => true
            ], 200);
        }

        $levelInfo = $levels[$targetLevel];
        $cost = floatval($levelInfo['cost'] ?? 0);
        $payMethod = $_POST['pay_method'] ?? 'balance';

        if ($payMethod !== 'balance') {
            jsonResponse(['success' => false, 'message' => '请使用支付接口创建在线支付订单'], 400);
        }

        if (floatval($user['balance'] ?? 0) < 0) {
            jsonResponse(['success' => false, 'message' => '当前余额为负数，请先补足欠款后再使用余额升级会员']);
        }
        if ($cost > 0 && $user['balance'] < $cost) {
            jsonResponse(['success' => false, 'message' => '余额不足，请选择在线支付或先充值']);
        }

        $updates = ['membership_level' => $targetLevel];
        $newBalance = $user['balance'];
        if ($cost > 0) {
            $newBalance = $user['balance'] - $cost;
            $updates['balance'] = $newBalance;
        }
        $db->updateUser($userId, $updates);
        if ($cost > 0) {
            $db->createPaymentOrder([
                'trade_no' => 'BAL' . date('YmdHis') . rand(1000, 9999),
                'user_id' => $userId,
                'payment_config_id' => 'balance',
                'pay_type' => 'balance',
                'amount' => -$cost,
                'actual_amount' => -$cost,
                'fee' => 0,
                'status' => 'paid',
                'type' => 'membership_upgrade_balance',
                'title' => '余额支付会员升级',
                'description' => '升级到 ' . $targetLevel . ' 会员',
                'target_level' => $targetLevel,
                'paid_at' => time()
            ]);
        }

        jsonResponse([
            'success' => true,
            'message' => "升级到{$targetLevel}会员成功",
            'new_balance' => $newBalance,
            'new_level' => $targetLevel
        ]);

    case 'my_level':
        if (!isset($_SESSION['user_id'])) {
            jsonResponse(['success' => false, 'logged_in' => false]);
        }

        $user = getCurrentUser();
        $levels = $db->getMembershipLevels();

        jsonResponse([
            'success' => true,
            'logged_in' => true,
            'level' => $user['membership_level'],
            'level_info' => $levels[$user['membership_level']] ?? null
        ]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
