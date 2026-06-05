<?php
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();
$provider = strtolower(trim($_GET['provider'] ?? $_POST['provider'] ?? $_GET['type'] ?? 'qq'));
$config = $db->getSystemConfig();

function oauthJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function oauthRedirect($message, $success = false, $extra = []) {
    $flag = $success ? 'success' : 'error';
    $return = $_SESSION['oauth_qq_return'] ?? 'front';
    unset($_SESSION['oauth_qq_return']);
    $extraQuery = '';
    if (!empty($extra)) {
        $extraQuery = '&' . http_build_query($extra);
    }
    if ($return === 'admin') {
        $url = '../admin/#page=overview&oauth_' . $flag . '=' . rawurlencode($message) . $extraQuery;
    } else {
        $url = '../#page=home&oauth_' . $flag . '=' . rawurlencode($message) . $extraQuery;
    }
    header('Location: ' . $url);
    exit;
}

function oauthRequireEnabled($config) {
    if (empty($config['oauth_caihong_enabled'])) {
        oauthRedirect('彩虹聚合登录未启用，请到后台 系统设置 → 登录注册 开启右侧“彩虹聚合登录”');
    }
    foreach (['oauth_caihong_app_id', 'oauth_caihong_key'] as $field) {
        if (trim((string)($config[$field] ?? '')) === '') {
            oauthRedirect('彩虹聚合登录 AppID/通信 Key 未配置完整');
        }
    }
}

function oauthCallbackUri($config) {
    if (!empty($config['oauth_caihong_redirect_uri'])) {
        return $config['oauth_caihong_redirect_uri'];
    }
    if (!empty($config['oauth_qq_redirect_uri'])) {
        return $config['oauth_qq_redirect_uri'];
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/api/oauth.php?provider=qq';
}

function oauthHttpGet($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 KeyNest OAuth Client'
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body !== false && $body !== '') {
            return (string)$body;
        }
        if ($error) {
            return '';
        }
    }
    $context = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $context);
    return $body === false ? '' : (string)$body;
}

function oauthDecodeJson($body) {
    $data = json_decode(trim((string)$body), true);
    return is_array($data) ? $data : [];
}

function oauthApiBase($config) {
    $apiUrl = trim((string)($config['oauth_caihong_api_url'] ?? ''));
    if ($apiUrl === '') {
        $apiUrl = trim((string)($config['oauth_api_url'] ?? ''));
    }
    if ($apiUrl === '') {
        $apiUrl = 'https://login.az0.cn/';
    }
    return rtrim($apiUrl, '/') . '/connect.php';
}

function oauthProviderKey($provider) {
    $allowed = ['qq', 'wx', 'alipay', 'sina', 'baidu', 'douyin', 'huawei', 'xiaomi', 'google', 'microsoft', 'dingtalk', 'feishu', 'gitee', 'github'];
    return in_array($provider, $allowed, true) ? $provider : 'qq';
}

$provider = oauthProviderKey($provider);
oauthRequireEnabled($config);
$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';
$callbackUri = oauthCallbackUri($config);
$apiBase = oauthApiBase($config);

if (empty($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_qq_state'] = $state;
    $_SESSION['oauth_qq_mode'] = $mode === 'bind' ? 'bind' : 'login';
    $_SESSION['oauth_qq_return'] = strpos($_SERVER['HTTP_REFERER'] ?? '', '/admin') !== false ? 'admin' : 'front';
    $_SESSION['oauth_qq_provider'] = $provider;

    $loginUrl = $apiBase . '?' . http_build_query([
        'act' => 'login',
        'appid' => $config['oauth_caihong_app_id'],
        'appkey' => $config['oauth_caihong_key'],
        'type' => $provider,
        'redirect_uri' => $callbackUri,
        'state' => $state
    ]);
    $loginData = oauthDecodeJson(oauthHttpGet($loginUrl));
    if (($loginData['code'] ?? null) !== 0 || empty($loginData['url'])) {
        $callbackHost = parse_url($callbackUri, PHP_URL_HOST) ?: $callbackUri;
        $msg = $loginData['msg'] ?? '接口无响应';
        if (mb_strpos($msg, '域名') !== false || mb_strpos($msg, '授权') !== false) {
            $msg .= '。当前回调域名：' . $callbackHost . '，请在聚合登录平台的“回调域名白名单”中添加这个完整域名（例如 shop.uzip.cn），不要只填主域名。';
        }
        oauthRedirect('获取聚合登录地址失败：' . $msg);
    }
    header('Location: ' . $loginData['url']);
    exit;
}

$state = $_GET['state'] ?? '';
if ($state && !empty($_SESSION['oauth_qq_state']) && !hash_equals($_SESSION['oauth_qq_state'], $state)) {
    oauthRedirect('登录状态校验失败，请重试');
}
$mode = $_SESSION['oauth_qq_mode'] ?? 'login';
$provider = oauthProviderKey($_GET['type'] ?? $_SESSION['oauth_qq_provider'] ?? $provider);
unset($_SESSION['oauth_qq_state'], $_SESSION['oauth_qq_mode'], $_SESSION['oauth_qq_provider']);

$callbackUrl = $apiBase . '?' . http_build_query([
    'act' => 'callback',
    'appid' => $config['oauth_caihong_app_id'],
    'appkey' => $config['oauth_caihong_key'],
    'type' => $provider,
    'code' => $_GET['code']
]);
$userInfo = oauthDecodeJson(oauthHttpGet($callbackUrl));
if (($userInfo['code'] ?? null) !== 0) {
    oauthRedirect('聚合登录失败：' . ($userInfo['msg'] ?? '获取用户信息失败'));
}

$socialUid = trim((string)($userInfo['social_uid'] ?? ''));
if ($socialUid === '') {
    oauthRedirect('聚合登录未返回用户唯一标识');
}
$openid = $provider . ':' . $socialUid;
$nickname = trim((string)($userInfo['nickname'] ?? '第三方用户'));

$users = $db->getTable('users');
$boundUser = null;
foreach ($users as $u) {
    if (($u['qq_openid'] ?? '') === $openid || ($u['qq_openid'] ?? '') === $socialUid) {
        $boundUser = $u;
        break;
    }
}

if ($mode === 'bind') {
    if (empty($_SESSION['user_id'])) {
        oauthRedirect('请先登录后再绑定第三方账号');
    }
    if ($boundUser && ($boundUser['id'] ?? '') !== $_SESSION['user_id']) {
        oauthRedirect('该第三方账号已绑定其他用户');
    }
    $db->updateUser($_SESSION['user_id'], [
        'qq_openid' => $openid,
        'qq_nickname' => $nickname,
        'qq_bound_at' => time()
    ]);
    oauthRedirect('第三方账号绑定成功', true);
}

if (!$boundUser) {
    oauthRedirect('该 QQ 还没有绑定平台账号。请先用账号密码登录，登录成功后到个人中心绑定该 QQ，之后就可以一键登录。', false, ['oauth_action' => 'bind_after_login']);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $boundUser['id'];
$_SESSION['username'] = $boundUser['username'];
$_SESSION['user_role'] = $boundUser['role'] ?? 'user';
$_SESSION['login_time'] = time();
$_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
$db->updateUser($boundUser['id'], ['last_login' => time()]);
oauthRedirect('登录成功', true);
