<?php
/**
 * KeyNest 虚拟商品交易平台
 * API入口文件
 */

// 安全配置
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('expose_php', 0);

// 安全头
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Robots-Tag: noindex, nofollow');

// CORS配置 — 从 config/security.php 读取白名单，自动补上当前 HTTP_HOST
$securityConfigFile = __DIR__ . '/../config/security.php';
$securityConfig = is_file($securityConfigFile) ? require $securityConfigFile : [];
$corsConfig = $securityConfig['cors'] ?? [];
$corsEnabled = $corsConfig['enabled'] ?? true;
if ($corsEnabled) {
    $allowedOrigins = $corsConfig['allowed_origins'] ?? [];
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($currentHost !== '') {
        $allowedOrigins[] = 'http://' . $currentHost;
        $allowedOrigins[] = 'https://' . $currentHost;
    }
    $allowedOrigins = array_unique($allowedOrigins);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    $allowedMethods = $corsConfig['allowed_methods'] ?? ['GET', 'POST'];
    header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods) . ', OPTIONS');
    $allowedHeaders = $corsConfig['allowed_headers'] ?? ['Content-Type', 'X-Requested-With'];
    header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
    header('Access-Control-Max-Age: 86400');
}

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 会话安全配置
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.gc_maxlifetime', 86400);
    session_start();
}

require_once __DIR__ . '/../config/install.php';
require_once __DIR__ . '/../core/SecurityLogger.php';
require_once __DIR__ . '/../core/SecurityValidator.php';
keynest_require_installed(true);

$GLOBALS['api_logger'] = new SecurityLogger();
$GLOBALS['security_validator'] = new SecurityValidator();
$GLOBALS['api_request_started_at'] = microtime(true);
$GLOBALS['api_request_logged'] = false;

function apiRequestContext($extra = []) {
    $post = $_POST;
    $get = $_GET;
    $logger = $GLOBALS['api_logger'] ?? null;
    if ($logger instanceof SecurityLogger) {
        $post = $logger->sanitizeContext($post);
        $get = $logger->sanitizeContext($get);
    }

    return array_merge([
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'script' => basename($_SERVER['SCRIPT_NAME'] ?? ''),
        'action' => $_REQUEST['action'] ?? '',
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        'get' => $get,
        'post' => $post
    ], $extra);
}

function apiLogRequest($level, $extra = []) {
    if (!isset($GLOBALS['api_logger']) || !($GLOBALS['api_logger'] instanceof SecurityLogger)) return;
    $durationMs = isset($GLOBALS['api_request_started_at']) ? round((microtime(true) - $GLOBALS['api_request_started_at']) * 1000, 2) : null;
    $GLOBALS['api_logger']->logApiRequest($level, apiRequestContext(array_merge(['duration_ms' => $durationMs], $extra)));
    $GLOBALS['api_request_logged'] = true;
}

apiLogRequest('info', ['event' => 'start']);

// 检测可疑输入
function detectSuspiciousRequestInput() {
    if (!isset($GLOBALS['api_logger']) || !($GLOBALS['api_logger'] instanceof SecurityLogger)) return;
    $logger = $GLOBALS['api_logger'];
    
    // 检查GET参数
    foreach ($_GET as $key => $value) {
        if (is_string($value) && strlen($value) > 0) {
            $result = $logger->detectSuspiciousInput($value, 'get_' . $key);
            if ($result['is_suspicious']) {
                apiLogRequest('warning', ['event' => 'suspicious_get_input', 'key' => $key, 'type' => $result['type']]);
            }
        }
    }
    
    // 检查POST参数
    foreach ($_POST as $key => $value) {
        if (is_string($value) && strlen($value) > 0) {
            $result = $logger->detectSuspiciousInput($value, 'post_' . $key);
            if ($result['is_suspicious']) {
                apiLogRequest('warning', ['event' => 'suspicious_post_input', 'key' => $key, 'type' => $result['type']]);
            }
        }
    }
}
detectSuspiciousRequestInput();

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    if (isset($GLOBALS['api_logger']) && $GLOBALS['api_logger'] instanceof SecurityLogger) {
        $GLOBALS['api_logger']->logPhpError(apiRequestContext([
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line
        ]));
    }
    return false;
});

set_exception_handler(function($exception) {
    if (isset($GLOBALS['api_logger']) && $GLOBALS['api_logger'] instanceof SecurityLogger) {
        $GLOBALS['api_logger']->logPhpError(apiRequestContext([
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]));
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'message' => '服务器内部错误，请查看日志'], JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if ($error && in_array($error['type'], $fatalTypes, true)) {
        if (isset($GLOBALS['api_logger']) && $GLOBALS['api_logger'] instanceof SecurityLogger) {
            $GLOBALS['api_logger']->logPhpError(apiRequestContext([
                'fatal' => true,
                'type' => $error['type'],
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]));
        }
        return;
    }

    if (empty($GLOBALS['api_request_logged']) || ($GLOBALS['api_request_logged'] === true && http_response_code() >= 400)) {
        apiLogRequest(http_response_code() >= 400 ? 'warning' : 'info', [
            'event' => 'finish',
            'status_code' => http_response_code()
        ]);
    }
});

// 生成CSRF token（如果还没有）
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// CSRF Token验证：对所有 POST 请求强制校验（含游客），避免跨站请求伪造
// 豁免列表：登录、注册、验证码获取、支付回调等无状态或跨站预期的接口
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $actionName = (string)($_REQUEST['action'] ?? '');
    $sentToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $csrfExemptActions = [
        'login',
        'admin_login',
        'register',
        'send_email_code',
        'captcha_debug',
        'geetest_register',
        'notify'
    ];
    // 包括游客在内的所有会话都进行 CSRF 检查
    $requiresCsrf = $scriptName !== 'oauth.php'
        && !in_array($actionName, $csrfExemptActions, true);

    if ($requiresCsrf && ($sessionToken === '' || $sentToken === '' || !hash_equals($sessionToken, $sentToken))) {
        apiLogRequest('warning', ['event' => 'csrf_token_invalid']);
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '请求已过期，请刷新页面后重试', 'csrf_token' => $sessionToken], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!empty($sessionToken) && empty($sentToken)) {
        apiLogRequest('warning', ['event' => 'csrf_token_missing']);
    }
}

// 安全辅助函数
function isAdmin() {
    return isset($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin';
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function checkCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 安全常量
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 300);
define('SESSION_TIMEOUT', 86400);
define('MIN_PASSWORD_LENGTH', 6);
define('MAX_USERNAME_LENGTH', 20);
define('MIN_USERNAME_LENGTH', 3);
