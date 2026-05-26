<?php
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();
$provider = strtolower(trim($_GET['provider'] ?? $_POST['provider'] ?? 'qq'));
$config = $db->getSystemConfig();

function oauthJson($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function oauthRedirect($message, $success = false) {
    $flag = $success ? 'success' : 'error';
    $url = '../#page=dashboard&tab=profile&oauth_' . $flag . '=' . rawurlencode($message);
    header('Location: ' . $url);
    exit;
}

function oauthRequireQqEnabled($config) {
    if (empty($config['oauth_qq_enabled'])) {
        oauthRedirect('QQ 登录未启用');
    }
    foreach (['oauth_qq_app_id', 'oauth_qq_app_key'] as $field) {
        if (trim((string)($config[$field] ?? '')) === '') {
            oauthRedirect('QQ 登录参数未配置完整');
        }
    }
}

function oauthQqRedirectUri($config) {
    if (!empty($config['oauth_qq_redirect_uri'])) {
        return $config['oauth_qq_redirect_uri'];
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/api/oauth.php?provider=qq';
}

function oauthHttpGet($url) {
    $context = stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_SSL_VERIFYPEER => true]);
        $body = curl_exec($ch);
        curl_close($ch);
    }
    return (string)$body;
}

function oauthExtractJson($body) {
    $body = trim((string)$body);
    if (preg_match('/callback\((.*)\);?/s', $body, $m)) {
        $body = trim($m[1]);
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

if ($provider !== 'qq') {
    oauthJson(['success' => false, 'message' => '暂仅支持 QQ 登录'], 400);
}

oauthRequireQqEnabled($config);
$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';
$redirectUri = oauthQqRedirectUri($config);

if (empty($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_qq_state'] = $state;
    $_SESSION['oauth_qq_mode'] = $mode === 'bind' ? 'bind' : 'login';
    $params = http_build_query([
        'response_type' => 'code',
        'client_id' => $config['oauth_qq_app_id'],
        'redirect_uri' => $redirectUri,
        'state' => $state,
        'scope' => 'get_user_info'
    ]);
    header('Location: https://graph.qq.com/oauth2.0/authorize?' . $params);
    exit;
}

$state = $_GET['state'] ?? '';
if (!$state || empty($_SESSION['oauth_qq_state']) || !hash_equals($_SESSION['oauth_qq_state'], $state)) {
    oauthRedirect('QQ 登录状态校验失败，请重试');
}
$mode = $_SESSION['oauth_qq_mode'] ?? 'login';
unset($_SESSION['oauth_qq_state'], $_SESSION['oauth_qq_mode']);

$tokenUrl = 'https://graph.qq.com/oauth2.0/token?' . http_build_query([
    'grant_type' => 'authorization_code',
    'client_id' => $config['oauth_qq_app_id'],
    'client_secret' => $config['oauth_qq_app_key'],
    'code' => $_GET['code'],
    'redirect_uri' => $redirectUri
]);
$tokenBody = oauthHttpGet($tokenUrl);
parse_str($tokenBody, $tokenData);
$accessToken = $tokenData['access_token'] ?? '';
if (!$accessToken) {
    oauthRedirect('获取 QQ 授权失败');
}

$openidBody = oauthHttpGet('https://graph.qq.com/oauth2.0/me?' . http_build_query(['access_token' => $accessToken]));
$openidData = oauthExtractJson($openidBody);
$openid = $openidData['openid'] ?? '';
if (!$openid) {
    oauthRedirect('获取 QQ OpenID 失败');
}

$userInfo = oauthExtractJson(oauthHttpGet('https://graph.qq.com/user/get_user_info?' . http_build_query([
    'access_token' => $accessToken,
    'oauth_consumer_key' => $config['oauth_qq_app_id'],
    'openid' => $openid
])));
$nickname = trim((string)($userInfo['nickname'] ?? 'QQ用户'));
$users = $db->getTable('users');
$boundUser = null;
foreach ($users as $u) {
    if (($u['qq_openid'] ?? '') === $openid) {
        $boundUser = $u;
        break;
    }
}

if ($mode === 'bind') {
    if (empty($_SESSION['user_id'])) {
        oauthRedirect('请先登录后再绑定 QQ');
    }
    if ($boundUser && ($boundUser['id'] ?? '') !== $_SESSION['user_id']) {
        oauthRedirect('该 QQ 已绑定其他账号');
    }
    $db->updateUser($_SESSION['user_id'], ['qq_openid' => $openid, 'qq_nickname' => $nickname, 'qq_bound_at' => time()]);
    oauthRedirect('QQ 绑定成功', true);
}

if (!$boundUser) {
    oauthRedirect('该 QQ 还没有绑定账号，请先登录账号后到个人中心绑定 QQ');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $boundUser['id'];
$_SESSION['username'] = $boundUser['username'];
$_SESSION['user_role'] = $boundUser['role'] ?? 'user';
$_SESSION['login_time'] = time();
$_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
$db->updateUser($boundUser['id'], ['last_login' => time()]);
oauthRedirect('QQ 登录成功', true);
