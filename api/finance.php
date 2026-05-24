<?php
/**
 * 余额/充值相关API
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

function requireAdmin() {
    global $db;
    $userId = requireAuth();
    $user = $db->getUserById($userId);
    if (!$user || $user['role'] !== 'admin') {
        jsonResponse(['success' => false, 'message' => '需要管理员权限'], 403);
    }
    return $user;
}

function getCurrentUser() {
    global $db;
    if (!isset($_SESSION['user_id'])) return null;
    return $db->getUserById($_SESSION['user_id']);
}

function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function validateId($id) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $id);
}

switch ($action) {
    case 'balance':
        $user = getCurrentUser();
        if (!$user) {
            jsonResponse(['success' => false, 'message' => '请先登录'], 401);
        }
        jsonResponse(['success' => true, 'balance' => $user['balance']]);

    case 'deposit':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        $amount = floatval($_POST['amount'] ?? 0);

        if ($amount <= 0 || $amount > 1000000) {
            jsonResponse(['success' => false, 'message' => '请输入有效金额'], 400);
        }

        $request = [
            'id' => 'dep_' . time() . '_' . bin2hex(random_bytes(6)),
            'user_id' => $userId,
            'username' => sanitizeString($user['username']),
            'amount' => $amount,
            'type' => 'deposit',
            'status' => 'pending',
            'created_at' => time()
        ];

        $db->addDepositRequest($request);
        jsonResponse(['success' => true, 'message' => '充值申请已提交，请联系管理员处理']);

    case 'withdraw':
        $userId = requireAuth();
        global $db;
        $user = getCurrentUser();
        $amount = floatval($_POST['amount'] ?? 0);
        $paymentMethod = sanitizeString($_POST['payment_method'] ?? '');
        $paymentAccount = sanitizeString($_POST['payment_account'] ?? '');
        $qrcodeUrl = sanitizeString($_POST['qrcode_url'] ?? '');

        $config = $db->getSystemConfig();
        
        if (!($config['enable_withdraw'] ?? false)) {
            jsonResponse(['success' => false, 'message' => '提现功能已关闭'], 400);
        }
        
        $minAmount = $config['min_withdraw_amount'] ?? 10;
        if ($amount < $minAmount) {
            jsonResponse(['success' => false, 'message' => '最低提现金额为 ¥' . $minAmount], 400);
        }

        if ($amount <= 0 || $amount > 1000000) {
            jsonResponse(['success' => false, 'message' => '请输入有效金额'], 400);
        }
        
        if ($user['balance'] < $amount) {
            jsonResponse(['success' => false, 'message' => '余额不足'], 400);
        }
        
        if (empty($paymentMethod) || empty($paymentAccount)) {
            jsonResponse(['success' => false, 'message' => '请选择收款方式并填写收款账号'], 400);
        }

        if (strlen($paymentAccount) > 100) {
            jsonResponse(['success' => false, 'message' => '收款账号过长'], 400);
        }

        $feeRate = $config['withdraw_fee_rate'] ?? 0.01;
        $fee = $amount * $feeRate;
        $actualAmount = $amount - $fee;

        $request = [
            'user_id' => $userId,
            'username' => sanitizeString($user['username']),
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'payment_account' => $paymentAccount,
            'qrcode_url' => $qrcodeUrl
        ];

        $result = $db->createWithdrawRequest($request);
        
        $db->updateUser($userId, [
            'balance' => $user['balance'] - $amount
        ]);

        jsonResponse([
            'success' => true, 
            'message' => '提现申请已提交，实到金额 ¥' . number_format($actualAmount, 2) . '（扣除手续费 ¥' . number_format($fee, 2) . '）',
            'request' => $result
        ]);

    case 'my_requests':
        $userId = requireAuth();
        $depositRequests = $db->getDepositRequests($userId);
        $withdrawRequests = $db->getWithdrawRequests($userId);
        
        $allRequests = array_merge($depositRequests, $withdrawRequests);
        usort($allRequests, fn($a, $b) => $b['created_at'] - $a['created_at']);
        
        jsonResponse(['success' => true, 'requests' => $allRequests]);

    case 'all_requests':
        requireAdmin();
        
        $depositRequests = $db->getDepositRequests();
        $withdrawRequests = $db->getWithdrawRequests();
        
        $allRequests = array_merge($depositRequests, $withdrawRequests);
        usort($allRequests, fn($a, $b) => $b['created_at'] - $a['created_at']);
        
        jsonResponse(['success' => true, 'requests' => $allRequests]);

    case 'approve':
        requireAdmin();
        
        $requestId = $_POST['id'] ?? '';
        $adminNote = sanitizeString($_POST['admin_note'] ?? '');

        if (!validateId($requestId) && !preg_match('/^wd_/', $requestId) && !preg_match('/^dep_/', $requestId)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }

        $withdrawRequest = $db->getWithdrawRequest($requestId);
        if ($withdrawRequest) {
            if ($withdrawRequest['status'] !== 'pending') {
                jsonResponse(['success' => false, 'message' => '请求已处理'], 400);
            }
            
            $admin = getCurrentUser();
            $db->updateWithdrawRequest($requestId, [
                'status' => 'paid',
                'admin_note' => $adminNote,
                'processed_by' => sanitizeString($admin['username']),
                'processed_at' => time()
            ]);
            
            jsonResponse(['success' => true, 'message' => '已标记为已支付']);
        }

        $requests = $db->getDepositRequests();
        $target = null;
        foreach ($requests as $r) {
            if ($r['id'] === $requestId) {
                $target = $r;
                break;
            }
        }

        if (!$target || $target['status'] !== 'pending') {
            jsonResponse(['success' => false, 'message' => '请求不存在或已处理'], 400);
        }

        $targetUser = $db->getUserById($target['user_id']);
        if ($targetUser && $target['type'] === 'deposit') {
            $db->updateUser($target['user_id'], ['balance' => $targetUser['balance'] + $target['amount']]);
        }

        $target['status'] = 'approved';
        $db->updateDepositRequest($target);

        jsonResponse(['success' => true, 'message' => '已批准']);

    case 'reject':
        requireAdmin();
        
        $requestId = $_POST['id'] ?? '';
        $adminNote = sanitizeString($_POST['admin_note'] ?? '');

        if (!validateId($requestId) && !preg_match('/^wd_/', $requestId) && !preg_match('/^dep_/', $requestId)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }

        $withdrawRequest = $db->getWithdrawRequest($requestId);
        if ($withdrawRequest) {
            if ($withdrawRequest['status'] !== 'pending') {
                jsonResponse(['success' => false, 'message' => '请求已处理'], 400);
            }
            
            $admin = getCurrentUser();
            $user = $db->getUserById($withdrawRequest['user_id']);
            
            if ($user) {
                $db->updateUser($user['id'], [
                    'balance' => $user['balance'] + $withdrawRequest['amount']
                ]);
            }
            
            $db->updateWithdrawRequest($requestId, [
                'status' => 'rejected',
                'admin_note' => $adminNote,
                'processed_by' => sanitizeString($admin['username']),
                'processed_at' => time()
            ]);
            
            jsonResponse(['success' => true, 'message' => '已拒绝，余额已退还']);
        }

        $requests = $db->getDepositRequests();
        $target = null;
        foreach ($requests as $r) {
            if ($r['id'] === $requestId) {
                $target = $r;
                break;
            }
        }

        if (!$target || $target['status'] !== 'pending') {
            jsonResponse(['success' => false, 'message' => '请求不存在或已处理'], 400);
        }

        $target['status'] = 'rejected';
        $db->updateDepositRequest($target);

        jsonResponse(['success' => true, 'message' => '已拒绝']);

    case 'get_withdraw_requests':
        requireAdmin();
        $requests = $db->getWithdrawRequests();
        usort($requests, fn($a, $b) => $b['created_at'] - $a['created_at']);
        jsonResponse(['success' => true, 'requests' => $requests]);

    case 'get_system_config':
        $user = getCurrentUser();
        $config = $db->getSystemConfig();
        
        if (!$user || $user['role'] !== 'admin') {
            unset($config['admin_wechat_qrcode']);
            unset($config['admin_alipay_qrcode']);
            unset($config['smtp_password']);
            unset($config['resend_api_key']);
            unset($config['captcha_secret_key']);
            unset($config['oauth_qq_app_key']);
            unset($config['oauth_wechat_app_secret']);
            unset($config['oauth_caihong_key']);
        }
        
        jsonResponse(['success' => true, 'config' => $config]);

    case 'update_system_config':
        requireAdmin();
        
        $config = [];
        if (isset($_POST['enable_withdraw'])) {
            $config['enable_withdraw'] = (bool)$_POST['enable_withdraw'];
        }
        if (isset($_POST['min_withdraw_amount'])) {
            $config['min_withdraw_amount'] = max(1, floatval($_POST['min_withdraw_amount']));
        }
        if (isset($_POST['withdraw_fee_rate'])) {
            $config['withdraw_fee_rate'] = min(1, max(0, floatval($_POST['withdraw_fee_rate'])));
        }
        if (isset($_POST['admin_wechat_qrcode'])) {
            $config['admin_wechat_qrcode'] = sanitizeString($_POST['admin_wechat_qrcode']);
        }
        if (isset($_POST['admin_alipay_qrcode'])) {
            $config['admin_alipay_qrcode'] = sanitizeString($_POST['admin_alipay_qrcode']);
        }
        if (isset($_POST['site_name'])) {
            $config['site_name'] = sanitizeString($_POST['site_name']);
        }
        if (isset($_POST['site_description'])) {
            $config['site_description'] = sanitizeString($_POST['site_description']);
        }

        $booleanFields = [
            'enable_recharge',
            'oauth_qq_enabled',
            'oauth_wechat_enabled',
            'oauth_caihong_enabled',
            'register_email_verify_enabled',
            'captcha_enabled',
            'announcement_enabled',
            'announcement_popup_enabled'
        ];
        foreach ($booleanFields as $field) {
            if (isset($_POST[$field])) {
                $config[$field] = filter_var($_POST[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $stringFields = [
            'smtp_host',
            'smtp_username',
            'smtp_secure',
            'email_provider',
            'resend_from_email',
            'resend_from_name',
            'captcha_provider',
            'captcha_site_key',
            'captcha_extra_config',
            'announcement_title',
            'announcement_content',
            'announcement_position',
            'announcement_items',
            'oauth_qq_app_id',
            'oauth_qq_app_key',
            'oauth_qq_redirect_uri',
            'oauth_wechat_app_id',
            'oauth_wechat_app_secret',
            'oauth_wechat_redirect_uri',
            'oauth_caihong_api_url',
            'oauth_caihong_app_id',
            'oauth_caihong_key',
            'oauth_caihong_redirect_uri'
        ];
        foreach ($stringFields as $field) {
            if (isset($_POST[$field])) {
                $value = trim((string)$_POST[$field]);
                if ($field === 'announcement_items') {
                    $items = json_decode($value, true);
                    if (!is_array($items)) {
                        jsonResponse(['success' => false, 'message' => '公告数据格式错误'], 400);
                    }
                    $normalizedItems = [];
                    foreach ($items as $item) {
                        if (!is_array($item)) continue;
                        $title = sanitizeString($item['title'] ?? '');
                        $content = trim((string)($item['content'] ?? ''));
                        if ($title === '' && $content === '') continue;
                        $normalizedItems[] = [
                            'id' => sanitizeString($item['id'] ?? ('ann_' . time() . '_' . bin2hex(random_bytes(3)))),
                            'title' => $title !== '' ? $title : '平台公告',
                            'content' => $content,
                            'enabled' => filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'popup_enabled' => filter_var($item['popup_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
                            'created_at' => intval($item['created_at'] ?? time()),
                        ];
                    }
                    $config[$field] = $normalizedItems;
                    continue;
                }
                $config[$field] = in_array($field, ['announcement_content', 'captcha_extra_config', 'announcement_items'], true)
                    ? $value
                    : sanitizeString($value);
            }
        }

        if (isset($_POST['smtp_password']) && $_POST['smtp_password'] !== '') {
            $config['smtp_password'] = sanitizeString($_POST['smtp_password']);
        }
        if (isset($_POST['resend_api_key']) && $_POST['resend_api_key'] !== '') {
            $config['resend_api_key'] = sanitizeString($_POST['resend_api_key']);
        }
        if (isset($_POST['captcha_secret_key']) && $_POST['captcha_secret_key'] !== '') {
            $config['captcha_secret_key'] = sanitizeString($_POST['captcha_secret_key']);
        }
        if (isset($_POST['smtp_port'])) {
            $config['smtp_port'] = max(1, min(65535, intval($_POST['smtp_port'])));
        }
        if (isset($_POST['email_code_ttl'])) {
            $config['email_code_ttl'] = max(1, min(60, intval($_POST['email_code_ttl'])));
        }
        
        $db->updateSystemConfig($config);
        jsonResponse(['success' => true, 'message' => '配置已更新']);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
