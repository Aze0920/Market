<?php
/**
 * 卖家二级域名 API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/SubdomainHelper.php';
require_once __DIR__ . '/../core/SubdomainPurchase.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

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

function subdomainConfigPayload($config) {
    $baseDomain = SubdomainHelper::normalizeBaseDomain($config['subdomain_base_domain'] ?? '');
    return [
        'enabled' => !empty($config['subdomain_enabled']),
        'base_domain' => $baseDomain,
        'wildcard_domain' => $baseDomain !== '' ? '*.' . $baseDomain : '',
        'monthly_price' => floatval($config['subdomain_monthly_price'] ?? 10),
    ];
}

function formatSubdomainForClient($subdomain, $config) {
    if (!$subdomain) return null;
    $baseDomain = SubdomainHelper::normalizeBaseDomain($config['subdomain_base_domain'] ?? '');
    $payload = $subdomain;
    $payload['full_domain'] = $baseDomain !== '' ? SubdomainHelper::fullHost($subdomain['prefix'], $baseDomain) : '';
    $payload['is_expired'] = SubdomainHelper::isExpired($subdomain);
    $payload['is_active'] = SubdomainHelper::isActive($subdomain);
    return $payload;
}

function requireMerchantApproved($user) {
    if (($user['merchant_status'] ?? 'none') !== 'approved') {
        jsonResponse(['success' => false, 'message' => '请先完成商家认证后再申请二级域名'], 400);
    }
}

switch ($action) {
    case 'config':
        $config = $db->getSystemConfig();
        jsonResponse(['success' => true, 'config' => subdomainConfigPayload($config)]);

    case 'resolve':
        $config = $db->getSystemConfig();
        $settings = subdomainConfigPayload($config);
        if (!$settings['enabled'] || $settings['base_domain'] === '') {
            jsonResponse(['success' => true, 'active' => false]);
        }
        $host = trim((string)($_GET['host'] ?? $_SERVER['HTTP_HOST'] ?? ''));
        $host = strtolower(preg_replace('/:\d+$/', '', $host));
        $prefix = SubdomainHelper::extractPrefixFromHost($host, $settings['base_domain']);
        if ($prefix === null) {
            jsonResponse(['success' => true, 'active' => false]);
        }
        $subdomain = $db->getSellerSubdomainByPrefix($prefix);
        if (!$subdomain) {
            jsonResponse(['success' => true, 'active' => false, 'prefix' => $prefix, 'reason' => 'not_found']);
        }
        $seller = $db->getUserById($subdomain['user_id'] ?? '');
        if (!$seller) {
            jsonResponse(['success' => true, 'active' => false, 'prefix' => $prefix, 'reason' => 'seller_missing']);
        }
        $expired = SubdomainHelper::isExpired($subdomain);
        $active = SubdomainHelper::isActive($subdomain);
        jsonResponse([
            'success' => true,
            'active' => $active,
            'expired' => $expired,
            'pending' => ($subdomain['status'] ?? '') === 'pending',
            'disabled' => !empty($subdomain['disabled']) || ($subdomain['status'] ?? '') === 'disabled',
            'prefix' => $prefix,
            'full_domain' => SubdomainHelper::fullHost($prefix, $settings['base_domain']),
            'seller_id' => $seller['id'],
            'seller_name' => $seller['username'] ?? '',
            'expires_at' => intval($subdomain['expires_at'] ?? 0),
            'status' => $subdomain['status'] ?? 'none',
            'message' => $expired ? '当前二级域名已过期请联系客服进行处理' : (($subdomain['status'] ?? '') === 'pending' ? '该店铺二级域名正在审核中' : ''),
        ]);

    case 'my':
        $userId = requireAuth();
        $user = getCurrentUser();
        $config = $db->getSystemConfig();
        $settings = subdomainConfigPayload($config);
        $subdomain = $db->getSellerSubdomainByUserId($userId);
        jsonResponse([
            'success' => true,
            'config' => $settings,
            'subdomain' => formatSubdomainForClient($subdomain, $config),
            'merchant_status' => $user['merchant_status'] ?? 'none',
        ]);

    case 'check_prefix':
        requireAuth();
        $config = $db->getSystemConfig();
        $settings = subdomainConfigPayload($config);
        if (!$settings['enabled']) {
            jsonResponse(['success' => false, 'message' => '二级域名功能未开启'], 400);
        }
        $prefix = strtolower(trim((string)($_GET['prefix'] ?? $_POST['prefix'] ?? '')));
        $error = SubdomainHelper::validatePrefix($prefix);
        if ($error) {
            jsonResponse(['success' => false, 'message' => $error], 400);
        }
        $existing = $db->getSellerSubdomainByPrefix($prefix);
        $userId = $_SESSION['user_id'];
        $available = !$existing || ($existing['user_id'] ?? '') === $userId;
        jsonResponse([
            'success' => true,
            'available' => $available,
            'full_domain' => $settings['base_domain'] !== '' ? SubdomainHelper::fullHost($prefix, $settings['base_domain']) : '',
            'message' => $available ? '该前缀可以使用' : '该前缀已被占用',
        ]);

    case 'purchase':
        $userId = requireAuth();
        $user = getCurrentUser();
        $config = $db->getSystemConfig();
        $settings = subdomainConfigPayload($config);
        if (!$settings['enabled']) {
            jsonResponse(['success' => false, 'message' => '二级域名功能未开启'], 400);
        }
        if ($settings['base_domain'] === '') {
            jsonResponse(['success' => false, 'message' => '管理员尚未配置二级域名主域名'], 400);
        }
        requireMerchantApproved($user);

        $prefix = strtolower(trim((string)($_POST['prefix'] ?? '')));
        $months = max(1, min(36, intval($_POST['months'] ?? 1)));
        $error = SubdomainHelper::validatePrefix($prefix);
        if ($error) {
            jsonResponse(['success' => false, 'message' => $error], 400);
        }

        $monthlyPrice = max(0.01, floatval($settings['monthly_price']));
        $totalPrice = round($monthlyPrice * $months, 2);
        if (floatval($user['balance'] ?? 0) < $totalPrice) {
            jsonResponse(['success' => false, 'message' => '余额不足，请先充值'], 400);
        }

        $existing = $db->getSellerSubdomainByUserId($userId);
        if ($existing && ($existing['status'] ?? '') === 'pending') {
            jsonResponse(['success' => false, 'message' => '您已有待审核的二级域名申请，请等待审核完成'], 400);
        }

        if (!$db->updateUser($userId, ['balance' => floatval($user['balance']) - $totalPrice])) {
            jsonResponse(['success' => false, 'message' => '扣款失败'], 500);
        }

        $result = SubdomainPurchase::apply($db, $user, $prefix, $months, $totalPrice, 'balance');
        if (!$result['success']) {
            $db->updateUser($userId, ['balance' => floatval($user['balance'])]);
            jsonResponse($result, 400);
        }
        jsonResponse([
            'success' => true,
            'message' => '购买成功，已提交审核，请等待管理员审核通过后生效',
            'subdomain' => formatSubdomainForClient($db->getSellerSubdomainByUserId($userId), $config),
            'paid_amount' => $totalPrice,
        ]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
