<?php
/**
 * 卖家二级域名相关 API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/SubdomainHelper.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return $db->getUserById($_SESSION['user_id']);
}

switch ($action) {
    case 'resolve':
        $host = strtolower(trim((string)($_GET['host'] ?? $_SERVER['HTTP_HOST'] ?? '')));
        $config = $db->getSystemConfig();

        if (!SubdomainHelper::configEnabled($config)) {
            jsonResponse([
                'success' => true,
                'active' => false,
                'seller_id' => null,
                'prefix' => null,
                'reason' => 'feature_disabled',
                'message' => '二级域名功能未开启，请先在后台系统设置中开启',
            ]);
        }

        $baseDomain = SubdomainHelper::normalizeBaseDomain($config['subdomain_base_domain'] ?? '');
        if ($baseDomain === '') {
            jsonResponse([
                'success' => true,
                'active' => false,
                'seller_id' => null,
                'prefix' => null,
                'reason' => 'base_domain_missing',
                'message' => '后台尚未配置二级域名主域名',
            ]);
        }

        $prefix = SubdomainHelper::extractPrefixFromHost($host, $baseDomain);
        if ($prefix === null) {
            jsonResponse([
                'success' => true,
                'active' => false,
                'seller_id' => null,
                'prefix' => null,
            ]);
        }

        $subdomain = $db->getSellerSubdomainByPrefix($prefix);
        $fullDomain = SubdomainHelper::fullHost($prefix, $baseDomain);
        if (!$subdomain) {
            jsonResponse([
                'success' => true,
                'active' => false,
                'seller_id' => null,
                'prefix' => $prefix,
                'full_domain' => $fullDomain,
                'reason' => 'not_found',
                'message' => '该二级域名尚未开通或未通过审核',
            ]);
        }

        $seller = $db->getUserById($subdomain['user_id'] ?? '');
        $status = (string)($subdomain['status'] ?? '');
        $isExpired = SubdomainHelper::isExpired($subdomain);
        $isActive = SubdomainHelper::isActive($subdomain);
        $isDisabled = !empty($subdomain['disabled']) || $status === 'disabled';

        $response = [
            'success' => true,
            'seller_id' => $subdomain['user_id'] ?? null,
            'seller_name' => $seller['username'] ?? '',
            'prefix' => $prefix,
            'full_domain' => $fullDomain,
            'status' => $status,
            'active' => $isActive,
            'expired' => $isExpired,
            'pending' => $status === 'pending',
            'disabled' => $isDisabled,
            'message' => '',
            'reason' => '',
        ];

        if ($status === 'pending') {
            $response['message'] = '该店铺二级域名正在审核中，请等待管理员审核通过';
            $response['reason'] = 'pending';
        } elseif ($isExpired) {
            $response['message'] = '当前二级域名已过期请联系客服进行处理';
            $response['reason'] = 'expired';
        } elseif ($isDisabled) {
            $response['message'] = '当前二级域名已被禁用';
            $response['reason'] = 'disabled';
        } elseif ($status === 'rejected') {
            $response['message'] = '该二级域名申请已被拒绝';
            $response['reason'] = 'rejected';
        } elseif (!$isActive) {
            $response['message'] = '当前二级域名暂不可用';
            $response['reason'] = 'inactive';
        }

        jsonResponse($response);

    case 'my':
        $userId = requireAuth();
        $user = getCurrentUser();
        $config = $db->getSystemConfig();
        $baseDomain = SubdomainHelper::normalizeBaseDomain($config['subdomain_base_domain'] ?? '');
        $subdomain = $db->getSellerSubdomainByUserId($userId);
        if ($subdomain) {
            $subdomain = SubdomainHelper::decorateSubdomainRecord($subdomain, $baseDomain);
        }
        jsonResponse([
            'success' => true,
            'subdomain' => $subdomain,
            'config' => SubdomainHelper::subdomainPublicConfig($config),
            'merchant_status' => $user['merchant_status'] ?? 'none',
        ]);

    case 'check_prefix':
        $prefix = strtolower(trim((string)($_GET['prefix'] ?? $_POST['prefix'] ?? '')));
        $error = SubdomainHelper::validatePrefix($prefix);
        if ($error) {
            jsonResponse(['success' => true, 'available' => false, 'message' => $error]);
        }
        if ($db->getSellerSubdomainByPrefix($prefix)) {
            jsonResponse(['success' => true, 'available' => false, 'message' => '该前缀已被占用']);
        }
        jsonResponse(['success' => true, 'available' => true, 'message' => '前缀可用']);

    case 'purchase':
        $userId = requireAuth();
        $user = getCurrentUser();
        if (!$user) {
            jsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }

        $config = $db->getSystemConfig();
        if (!SubdomainHelper::configEnabled($config)) {
            jsonResponse(['success' => false, 'message' => '二级域名功能未开启'], 400);
        }
        if (($user['merchant_status'] ?? 'none') !== 'approved') {
            jsonResponse(['success' => false, 'message' => '请先完成商家认证后再申请二级域名'], 403);
        }

        $prefix = strtolower(trim((string)($_POST['prefix'] ?? '')));
        $months = max(1, min(36, intval($_POST['months'] ?? 1)));
        $error = SubdomainHelper::validatePrefix($prefix);
        if ($error) {
            jsonResponse(['success' => false, 'message' => $error], 400);
        }

        $existing = $db->getSellerSubdomainByUserId($userId);
        if ($existing && !in_array($existing['status'] ?? '', ['rejected'], true)) {
            jsonResponse(['success' => false, 'message' => '您已有二级域名记录，无法重复购买'], 400);
        }
        if ($db->getSellerSubdomainByPrefix($prefix)) {
            jsonResponse(['success' => false, 'message' => '该前缀已被占用'], 400);
        }

        $monthlyPrice = max(0.01, floatval($config['subdomain_monthly_price'] ?? 10));
        $totalPrice = round($monthlyPrice * $months, 2);
        if (floatval($user['balance'] ?? 0) < $totalPrice) {
            jsonResponse(['success' => false, 'message' => '余额不足'], 400);
        }

        $db->updateUser($userId, ['balance' => floatval($user['balance']) - $totalPrice]);
        $now = time();
        $subdomain = [
            'user_id' => $userId,
            'prefix' => $prefix,
            'status' => 'pending',
            'pending_months' => $months,
            'expires_at' => 0,
            'last_price_paid' => $totalPrice,
            'disabled' => false,
            'created_at' => $now,
        ];
        if ($existing && ($existing['status'] ?? '') === 'rejected') {
            $subdomain['id'] = $existing['id'];
        }
        if (!$db->saveSellerSubdomain($subdomain)) {
            $db->updateUser($userId, ['balance' => floatval($user['balance'])]);
            jsonResponse(['success' => false, 'message' => '购买失败，请稍后重试'], 500);
        }

        $db->createPaymentOrder([
            'trade_no' => 'SUB' . date('YmdHis') . rand(1000, 9999),
            'user_id' => $userId,
            'payment_config_id' => 'balance',
            'pay_type' => 'balance',
            'amount' => -$totalPrice,
            'actual_amount' => -$totalPrice,
            'fee' => 0,
            'status' => 'paid',
            'type' => 'subdomain_purchase',
            'title' => '二级域名购买',
            'description' => '购买二级域名 ' . $prefix . ' × ' . $months . ' 个月（待审核）',
            'paid_at' => $now,
        ]);

        jsonResponse([
            'success' => true,
            'message' => '购买成功，请等待管理员审核通过后生效',
        ]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
