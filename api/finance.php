<?php
/**
 * 余额/充值相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/NotifyMail.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
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
    if (is_array($str) || is_object($str)) return '';
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function validateId($id) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $id);
}

function attachFinanceUserEmails($records) {
    global $db;
    return array_map(function($record) use ($db) {
        if (!empty($record['user_id'])) {
            $user = $db->getUserById($record['user_id']);
            if ($user && !empty($user['email'])) {
                $record['user_id_email'] = $user['email'];
            }
        }
        return $record;
    }, $records);
}

function encryptSmtpPasswordValue($plainPassword) {
    $plainPassword = (string)$plainPassword;
    if ($plainPassword === '') {
        return '';
    }
    $key = getenv('KEYNEST_ENCRYPTION_KEY') ?: 'KeyNestDefaultEncKey2024!';
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plainPassword, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $encrypted !== false ? base64_encode($iv . $encrypted) : $plainPassword;
}

function normalizeStoredEmailProfiles(array $config) {
    $profiles = $config['email_profiles'] ?? [];
    if (is_string($profiles)) {
        $decoded = json_decode($profiles, true);
        $profiles = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($profiles) || empty($profiles)) {
        return [];
    }
    $normalized = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile)) {
            continue;
        }
        $id = trim((string)($profile['id'] ?? ''));
        if ($id === '') {
            $id = 'email_' . time() . '_' . bin2hex(random_bytes(3));
        }
        $normalized[$id] = $profile;
    }
    return $normalized;
}

function parseEmailProfilesInput($rawProfiles, array $currentConfig) {
    if (is_string($rawProfiles)) {
        $profiles = json_decode($rawProfiles, true);
    } else {
        $profiles = $rawProfiles;
    }
    if (!is_array($profiles)) {
        return ['success' => false, 'message' => '发信配置格式错误'];
    }
    $existing = normalizeStoredEmailProfiles($currentConfig);
    $normalized = [];
    foreach ($profiles as $profile) {
        if (!is_array($profile)) {
            continue;
        }
        $id = sanitizeString($profile['id'] ?? ('email_' . time() . '_' . bin2hex(random_bytes(3))));
        $old = $existing[$id] ?? null;
        $provider = strtolower(trim((string)($profile['provider'] ?? 'smtp')));
        if (!in_array($provider, ['smtp', 'resend'], true)) {
            $provider = 'smtp';
        }
        $secure = strtolower(trim((string)($profile['smtp_secure'] ?? 'ssl')));
        if (!in_array($secure, ['ssl', 'tls', 'none'], true)) {
            $secure = 'ssl';
        }
        $item = [
            'id' => $id,
            'name' => sanitizeString($profile['name'] ?? ''),
            'enabled' => filter_var($profile['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'provider' => $provider,
            'resend_from_email' => sanitizeString($profile['resend_from_email'] ?? ''),
            'resend_from_name' => sanitizeString($profile['resend_from_name'] ?? 'KeyNest'),
            'smtp_host' => sanitizeString($profile['smtp_host'] ?? ''),
            'smtp_port' => max(1, min(65535, intval($profile['smtp_port'] ?? 465))),
            'smtp_username' => sanitizeString($profile['smtp_username'] ?? ''),
            'smtp_secure' => $secure,
            'resend_api_key' => '',
            'smtp_password' => '',
            'send_count' => max(0, intval(is_array($old) ? ($old['send_count'] ?? 0) : 0)),
        ];
        $newApiKey = trim((string)($profile['resend_api_key'] ?? ''));
        if ($newApiKey !== '') {
            $item['resend_api_key'] = sanitizeString($newApiKey);
        } elseif (is_array($old)) {
            $item['resend_api_key'] = (string)($old['resend_api_key'] ?? '');
        }
        if ($item['resend_api_key'] === '' && $item['provider'] === 'resend' && ($currentConfig['resend_from_email'] ?? '') === $item['resend_from_email']) {
            $item['resend_api_key'] = (string)($currentConfig['resend_api_key'] ?? '');
        }
        $newPassword = (string)($profile['smtp_password'] ?? '');
        if ($newPassword !== '') {
            $item['smtp_password'] = encryptSmtpPasswordValue($newPassword);
        } elseif (is_array($old)) {
            $item['smtp_password'] = (string)($old['smtp_password'] ?? '');
        }
        if ($item['smtp_password'] === '' && $item['smtp_username'] !== '' && ($currentConfig['smtp_username'] ?? '') === $item['smtp_username']) {
            $item['smtp_password'] = (string)($currentConfig['smtp_password'] ?? '');
        }
        $normalized[] = $item;
    }
    if (empty($normalized)) {
        return ['success' => false, 'message' => '请至少保留一个发信方式'];
    }
    return ['success' => true, 'profiles' => $normalized];
}

function syncLegacyEmailConfigFromProfiles(array $profiles) {
    $selected = null;
    foreach ($profiles as $profile) {
        if (!empty($profile['enabled'])) {
            $selected = $profile;
            break;
        }
    }
    if (!$selected) {
        $selected = $profiles[0];
    }
    return [
        'email_provider' => ($selected['provider'] ?? '') === 'resend' ? 'resend' : 'smtp',
        'resend_from_email' => $selected['resend_from_email'] ?? '',
        'resend_from_name' => $selected['resend_from_name'] ?? 'KeyNest',
        'resend_api_key' => $selected['resend_api_key'] ?? '',
        'smtp_host' => $selected['smtp_host'] ?? '',
        'smtp_port' => intval($selected['smtp_port'] ?? 465),
        'smtp_username' => $selected['smtp_username'] ?? '',
        'smtp_password' => $selected['smtp_password'] ?? '',
        'smtp_secure' => $selected['smtp_secure'] ?? 'ssl',
    ];
}

function appendMailNotifyHint($message, array $mailResult) {
    if (!empty($mailResult['success'])) {
        return $message;
    }
    $hint = trim((string)($mailResult['message'] ?? '邮件通知发送失败'));
    if ($hint === '') {
        return $message;
    }
    return $message . '（邮件通知发送失败：' . $hint . '）';
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
        
        if (floatval($user['balance'] ?? 0) < 0) {
            jsonResponse(['success' => false, 'message' => '当前余额为负数，请先补足欠款后再提现'], 400);
        }
        if ($user['balance'] < $amount) {
            jsonResponse(['success' => false, 'message' => '余额不足'], 400);
        }
        
        $paymentMethodMap = ['支付宝' => 'alipay', '微信' => 'wechat', '银行卡' => 'bank'];
        $paymentMethod = $paymentMethodMap[$paymentMethod] ?? $paymentMethod;
        if (empty($paymentMethod) || empty($paymentAccount)) {
            jsonResponse(['success' => false, 'message' => '请选择收款方式并填写收款账号'], 400);
        }

        if (!in_array($paymentMethod, ['alipay', 'wechat', 'bank'], true)) {
            jsonResponse(['success' => false, 'message' => '收款方式不正确，请重新选择'], 400);
        }

        if (empty($qrcodeUrl)) {
            jsonResponse(['success' => false, 'message' => '请先上传收款码后再申请提现'], 400);
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
        if (!$result || empty($result['id'])) {
            if (function_exists('apiLogRequest')) {
                apiLogRequest('withdraw_create_failed', [
                    'user_id' => $userId,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'has_account' => $paymentAccount !== '',
                    'has_qrcode' => $qrcodeUrl !== ''
                ], 'ERROR');
            }
            jsonResponse(['success' => false, 'message' => '提现申请创建失败，请检查收款方式配置'], 500);
        }
        
        $balanceUpdated = $db->updateUser($userId, [
            'balance' => $user['balance'] - $amount
        ]);
        if (!$balanceUpdated) {
            $db->updateWithdrawRequest($result['id'], [
                'status' => 'rejected',
                'admin_note' => '系统自动回滚：余额扣减失败',
                'processed_by' => 'system',
                'processed_at' => time()
            ]);
            if (function_exists('apiLogRequest')) {
                apiLogRequest('withdraw_balance_update_failed', [
                    'request_id' => $result['id'],
                    'user_id' => $userId,
                    'amount' => $amount
                ], 'ERROR');
            }
            jsonResponse(['success' => false, 'message' => '提现申请失败：余额扣减失败，请稍后重试'], 500);
        }

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
        
        $allRequests = attachFinanceUserEmails($allRequests);
        jsonResponse(['success' => true, 'requests' => $allRequests]);

    case 'all_requests':
        requireAdmin();
        
        $depositRequests = $db->getDepositRequests();
        $withdrawRequests = $db->getWithdrawRequests();
        
        $allRequests = array_merge($depositRequests, $withdrawRequests);
        usort($allRequests, fn($a, $b) => $b['created_at'] - $a['created_at']);
        
        $allRequests = attachFinanceUserEmails($allRequests);
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
            $processedAt = time();
            $db->updateWithdrawRequest($requestId, [
                'status' => 'paid',
                'admin_note' => $adminNote,
                'processed_by' => sanitizeString($admin['username']),
                'processed_at' => $processedAt
            ]);

            $user = $db->getUserById($withdrawRequest['user_id'] ?? '');
            $updatedRequest = $db->getWithdrawRequest($requestId) ?: array_merge($withdrawRequest, [
                'status' => 'paid',
                'admin_note' => $adminNote,
                'processed_by' => sanitizeString($admin['username']),
                'processed_at' => $processedAt,
            ]);
            $config = $db->getSystemConfig();
            $mailResult = $user ? NotifyMail::withdrawApproved($user, $updatedRequest, $config) : ['success' => false, 'message' => '用户不存在'];

            jsonResponse(['success' => true, 'message' => appendMailNotifyHint('已标记为已支付', $mailResult)]);
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

        $config = $db->getSystemConfig();
        $mailResult = ($targetUser && ($target['type'] ?? '') === 'deposit')
            ? NotifyMail::depositApproved($targetUser, $target, $config)
            : ['success' => false, 'message' => '无需发送邮件'];

        jsonResponse(['success' => true, 'message' => appendMailNotifyHint('已批准', $mailResult)]);

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
            $processedAt = time();
            
            if ($user) {
                $db->updateUser($user['id'], [
                    'balance' => $user['balance'] + $withdrawRequest['amount']
                ]);
            }
            
            $db->updateWithdrawRequest($requestId, [
                'status' => 'rejected',
                'admin_note' => $adminNote,
                'processed_by' => sanitizeString($admin['username']),
                'processed_at' => $processedAt
            ]);

            $updatedRequest = $db->getWithdrawRequest($requestId) ?: array_merge($withdrawRequest, [
                'status' => 'rejected',
                'admin_note' => $adminNote,
                'processed_by' => sanitizeString($admin['username']),
                'processed_at' => $processedAt,
            ]);
            $config = $db->getSystemConfig();
            $mailResult = $user ? NotifyMail::withdrawRejected($user, $updatedRequest, $config) : ['success' => false, 'message' => '用户不存在'];
            
            jsonResponse(['success' => true, 'message' => appendMailNotifyHint('已拒绝，余额已退还', $mailResult)]);
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

    case 'delete_request':
        requireAdmin();
        $requestId = $_POST['id'] ?? '';
        if (!validateId($requestId) && !preg_match('/^wd_/', $requestId) && !preg_match('/^dep_/', $requestId)) {
            jsonResponse(['success' => false, 'message' => '无效的ID'], 400);
        }
        if (preg_match('/^wd_/', $requestId)) {
            $deleted = $db->deleteWithdrawRequest($requestId);
        } else {
            $deleted = $db->deleteDepositRequest($requestId);
        }
        if (!$deleted) {
            jsonResponse(['success' => false, 'message' => '记录不存在或已删除'], 404);
        }
        jsonResponse(['success' => true, 'message' => '记录已删除']);

    case 'delete_requests':
        requireAdmin();
        $rawIds = $_POST['ids'] ?? '[]';
        $ids = json_decode($rawIds, true);
        if (!is_array($ids)) {
            jsonResponse(['success' => false, 'message' => '请选择要删除的记录'], 400);
        }
        $withdrawIds = [];
        $depositIds = [];
        foreach ($ids as $id) {
            $id = (string)$id;
            if (!validateId($id) && !preg_match('/^wd_/', $id) && !preg_match('/^dep_/', $id)) {
                continue;
            }
            if (preg_match('/^wd_/', $id)) {
                $withdrawIds[] = $id;
            } else {
                $depositIds[] = $id;
            }
        }
        $count = $db->deleteWithdrawRequests($withdrawIds) + $db->deleteDepositRequests($depositIds);
        jsonResponse(['success' => true, 'message' => '已删除 ' . $count . ' 条记录', 'count' => $count]);

    case 'get_withdraw_requests':
        requireAdmin();
        $requests = $db->getWithdrawRequests();
        usort($requests, fn($a, $b) => $b['created_at'] - $a['created_at']);
        $requests = attachFinanceUserEmails($requests);
        jsonResponse(['success' => true, 'requests' => $requests]);

    case 'get_system_config':
        require_once __DIR__ . '/../core/Mailer.php';
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
        $config = KeyNestMailer::stripProfileSecrets($config);
        unset($config['smtp_password'], $config['resend_api_key']);
        
        jsonResponse(['success' => true, 'config' => $config]);

    case 'update_system_config':
        requireAdmin();
        
        $config = [];
        $currentConfig = $db->getSystemConfig();
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
            'enable_withdraw',
            'allow_guest_purchase',
            'enable_membership_card_activation',
            'oauth_qq_enabled',
            'oauth_wechat_enabled',
            'oauth_caihong_enabled',
            'register_email_verify_enabled',
            'captcha_enabled',
            'captcha_login_enabled',
            'captcha_register_enabled',
            'announcement_enabled',
            'announcement_popup_enabled',
            'subdomain_enabled'
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
            'email_template_html',
            'captcha_provider',
            'captcha_site_key',
            'captcha_extra_config',
            'announcement_title',
            'announcement_content',
            'announcement_position',
            'announcement_items',
            'user_agreement_title',
            'user_agreement_content',
            'merchant_agreement_title',
            'merchant_agreement_content',
            'admin_badge_icon',
            'admin_badge_gradient',
            'admin_badge_text',
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
                $config[$field] = in_array($field, ['announcement_content', 'captcha_extra_config', 'announcement_items', 'email_template_html', 'user_agreement_content', 'merchant_agreement_content'], true)
                    ? $value
                    : sanitizeString($value);
            }
        }

        if (isset($_POST['smtp_password']) && $_POST['smtp_password'] !== '') {
            $config['smtp_password'] = encryptSmtpPasswordValue(sanitizeString($_POST['smtp_password']));
        }
        if (isset($_POST['resend_api_key']) && $_POST['resend_api_key'] !== '') {
            $config['resend_api_key'] = sanitizeString($_POST['resend_api_key']);
        }
        if (isset($_POST['captcha_secret_key']) && $_POST['captcha_secret_key'] !== '') {
            $config['captcha_secret_key'] = sanitizeString($_POST['captcha_secret_key']);
        }
        $oauthChecks = [
            'oauth_qq' => ['enabled' => 'oauth_qq_enabled', 'required' => ['oauth_qq_app_id', 'oauth_qq_app_key', 'oauth_qq_redirect_uri'], 'label' => 'QQ 登录'],
            'oauth_wechat' => ['enabled' => 'oauth_wechat_enabled', 'required' => ['oauth_wechat_app_id', 'oauth_wechat_app_secret', 'oauth_wechat_redirect_uri'], 'label' => '微信登录'],
            'oauth_caihong' => ['enabled' => 'oauth_caihong_enabled', 'required' => ['oauth_caihong_api_url', 'oauth_caihong_app_id', 'oauth_caihong_key', 'oauth_caihong_redirect_uri'], 'label' => '彩虹聚合登录']
        ];
        foreach ($oauthChecks as $check) {
            if (!array_key_exists($check['enabled'], $config) || !$config[$check['enabled']]) continue;
            foreach ($check['required'] as $field) {
                $value = $config[$field] ?? $currentConfig[$field] ?? '';
                if (trim((string)$value) === '') {
                    jsonResponse(['success' => false, 'message' => $check['label'] . '参数不完整，请填写后再启用'], 400);
                }
            }
        }
        if (isset($_POST['smtp_port'])) {
            $config['smtp_port'] = max(1, min(65535, intval($_POST['smtp_port'])));
        }
        if (isset($_POST['email_code_ttl'])) {
            $config['email_code_ttl'] = max(1, min(60, intval($_POST['email_code_ttl'])));
        }
        if (isset($_POST['subdomain_monthly_price'])) {
            $config['subdomain_monthly_price'] = max(0.01, floatval($_POST['subdomain_monthly_price']));
        }
        if (isset($_POST['subdomain_main_site_hosts'])) {
            require_once __DIR__ . '/../core/SubdomainHelper.php';
            $config['subdomain_main_site_hosts'] = SubdomainHelper::parseMainSiteHostsInput($_POST['subdomain_main_site_hosts']);
        }
        if (isset($_POST['subdomain_domain_plans'])) {
            require_once __DIR__ . '/../core/SubdomainHelper.php';
            $plans = SubdomainHelper::parseDomainPlansInput($_POST['subdomain_domain_plans']);
            if (empty($plans)) {
                jsonResponse(['success' => false, 'message' => '请至少配置一个主域名'], 400);
            }
            $config['subdomain_domain_plans'] = $plans;
            $domains = array_map(fn($plan) => $plan['domain'], $plans);
            $config['subdomain_base_domains'] = $domains;
            $config['subdomain_base_domain'] = $domains[0] ?? '';
            $config['subdomain_monthly_price'] = floatval($plans[0]['monthly_price'] ?? ($config['subdomain_monthly_price'] ?? 10));
        } elseif (isset($_POST['subdomain_base_domains'])) {
            require_once __DIR__ . '/../core/SubdomainHelper.php';
            $domains = SubdomainHelper::parseBaseDomainsInput($_POST['subdomain_base_domains']);
            $config['subdomain_base_domains'] = $domains;
            $config['subdomain_base_domain'] = $domains[0] ?? '';
        } elseif (isset($_POST['subdomain_base_domain'])) {
            require_once __DIR__ . '/../core/SubdomainHelper.php';
            $domain = SubdomainHelper::normalizeBaseDomain($_POST['subdomain_base_domain']);
            $config['subdomain_base_domain'] = $domain;
            $config['subdomain_base_domains'] = $domain !== '' ? [$domain] : [];
        }
        if (isset($_POST['email_profiles'])) {
            $parsed = parseEmailProfilesInput($_POST['email_profiles'], $currentConfig);
            if (empty($parsed['success'])) {
                jsonResponse(['success' => false, 'message' => $parsed['message'] ?? '发信配置保存失败'], 400);
            }
            $profiles = $parsed['profiles'];
            $config['email_profiles'] = $profiles;
            $config = array_merge($config, syncLegacyEmailConfigFromProfiles($profiles));
        }
        
        $db->updateSystemConfig($config);
        jsonResponse(['success' => true, 'message' => '配置已更新']);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
