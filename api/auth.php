<?php
/**
 * 用户认证相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Mailer.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 速率限制配置
define('LOGIN_RATE_LIMIT', 5); // 5次
define('LOGIN_RATE_WINDOW', 300); // 5分钟内

function genId() {
    return 'id_' . time() . '_' . bin2hex(random_bytes(6));
}

function checkLoginRateLimit($username) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'login_attempts_' . md5($username . '|' . $ip);
    
    $attempts = $_SESSION[$key] ?? [];
    $now = time();
    
    // 清理过期记录
    $attempts = array_filter($attempts, fn($t) => ($now - $t) < LOGIN_RATE_WINDOW);
    
    if (count($attempts) >= LOGIN_RATE_LIMIT) {
        return false;
    }
    
    $attempts[] = $now;
    $_SESSION[$key] = $attempts;
    return true;
}

function sanitizeUsername($username) {
    $username = trim((string)$username);
    $username = preg_replace('/[\x00-\x1F\x7F<>"\'`\\\\]/u', '', $username);
    return mb_substr($username, 0, 30, 'UTF-8');
}

function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function isEmailVerifyEnabled() {
    global $db;
    $config = $db->getSystemConfig();
    return !empty($config['register_email_verify_enabled']);
}

function verifyRegisterEmailCode($email, $code) {
    if (!isEmailVerifyEnabled()) return true;
    $email = strtolower(trim($email));
    $code = trim($code);
    $record = $_SESSION['register_email_code'][$email] ?? null;
    if (!$record || empty($record['code']) || empty($record['expires_at'])) {
        jsonResponse(['success' => false, 'message' => '请先获取邮箱验证码'], 400);
    }
    if (time() > $record['expires_at']) {
        unset($_SESSION['register_email_code'][$email]);
        jsonResponse(['success' => false, 'message' => '邮箱验证码已过期，请重新获取'], 400);
    }
    if (!hash_equals((string)$record['code'], $code)) {
        jsonResponse(['success' => false, 'message' => '邮箱验证码错误'], 400);
    }
    unset($_SESSION['register_email_code'][$email]);
    return true;
}

function sendRegisterEmailCode($email) {
    global $db;
    $config = $db->getSystemConfig();
    if (empty($config['register_email_verify_enabled'])) {
        jsonResponse(['success' => true, 'message' => '邮箱验证未启用']);
    }
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => '请输入有效的邮箱地址'], 400);
    }
    $lastSentKey = 'register_email_last_sent_' . md5($email);
    if (!empty($_SESSION[$lastSentKey]) && time() - $_SESSION[$lastSentKey] < 60) {
        jsonResponse(['success' => false, 'message' => '发送过于频繁，请稍后再试'], 429);
    }
    foreach ($db->getTable('users') as $u) {
        if (isset($u['email']) && strtolower($u['email']) === $email) {
            jsonResponse(['success' => false, 'message' => '该邮箱已被注册'], 400);
        }
    }
    $code = (string)random_int(100000, 999999);
    $ttl = max(1, min(60, intval($config['email_code_ttl'] ?? 10)));
    $_SESSION['register_email_code'][$email] = ['code' => $code, 'expires_at' => time() + $ttl * 60];
    $_SESSION[$lastSentKey] = time();
    $siteName = $config['site_name'] ?? 'KeyNest';
    $subject = $siteName . ' 注册邮箱验证码';
    $html = KeyNestMailer::renderTemplate($config, [
        'site_name' => $siteName,
        'title' => '注册邮箱验证码',
        'message' => '你正在注册 ' . $siteName . ' 账号，请在页面中输入下面的验证码。',
        'code' => $code,
        'ttl' => $ttl,
        'footer' => '验证码 ' . $ttl . ' 分钟内有效。如果不是你本人操作，请忽略本邮件。',
        'time' => date('Y-m-d H:i:s')
    ]);
    $result = KeyNestMailer::send($email, $subject, $html, $config);
    if (empty($result['success'])) {
        unset($_SESSION['register_email_code'][$email]);
        jsonResponse(['success' => false, 'message' => $result['message'] ?? '邮件发送失败'], 500);
    }
    jsonResponse(['success' => true, 'message' => '验证码已发送，请查收邮箱']);
}

function sendProfileEmailCode($user) {
    global $db;
    $config = $db->getSystemConfig();
    $email = strtolower(trim($user['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => '当前账号未绑定有效邮箱，无法发送验证码'], 400);
    }
    $lastSentKey = 'profile_email_last_sent_' . md5($user['id'] . '|' . $email);
    if (!empty($_SESSION[$lastSentKey]) && time() - $_SESSION[$lastSentKey] < 60) {
        jsonResponse(['success' => false, 'message' => '发送过于频繁，请稍后再试'], 429);
    }
    $code = (string)random_int(100000, 999999);
    $ttl = max(1, min(60, intval($config['email_code_ttl'] ?? 10)));
    $_SESSION['profile_email_code'][$user['id']] = ['code' => $code, 'expires_at' => time() + $ttl * 60, 'email' => $email];
    $_SESSION[$lastSentKey] = time();
    $siteName = $config['site_name'] ?? 'KeyNest';
    $subject = $siteName . ' 修改密码验证码';
    $html = KeyNestMailer::renderTemplate($config, [
        'site_name' => $siteName,
        'title' => '修改密码验证码',
        'message' => '你正在修改 ' . $siteName . ' 账号密码，请在页面中输入下面的验证码。',
        'code' => $code,
        'ttl' => $ttl,
        'footer' => '验证码 ' . $ttl . ' 分钟内有效。如果不是你本人操作，请立即检查账号安全。',
        'time' => date('Y-m-d H:i:s')
    ]);
    $result = KeyNestMailer::send($email, $subject, $html, $config);
    if (empty($result['success'])) {
        unset($_SESSION['profile_email_code'][$user['id']]);
        jsonResponse(['success' => false, 'message' => $result['message'] ?? '邮件发送失败'], 500);
    }
    jsonResponse(['success' => true, 'message' => '验证码已发送到当前绑定邮箱']);
}

function verifyProfileEmailCode($user, $code) {
    $record = $_SESSION['profile_email_code'][$user['id']] ?? null;
    $email = strtolower(trim($user['email'] ?? ''));
    if (!$record || empty($record['code']) || empty($record['expires_at'])) {
        jsonResponse(['success' => false, 'message' => '请先获取邮箱验证码'], 400);
    }
    if (($record['email'] ?? '') !== $email) {
        unset($_SESSION['profile_email_code'][$user['id']]);
        jsonResponse(['success' => false, 'message' => '邮箱已变化，请重新获取验证码'], 400);
    }
    if (time() > $record['expires_at']) {
        unset($_SESSION['profile_email_code'][$user['id']]);
        jsonResponse(['success' => false, 'message' => '邮箱验证码已过期，请重新获取'], 400);
    }
    if (!hash_equals((string)$record['code'], trim($code))) {
        jsonResponse(['success' => false, 'message' => '邮箱验证码错误'], 400);
    }
    unset($_SESSION['profile_email_code'][$user['id']]);
}

function safeUser($user) {
    unset($user['password']);
    return $user;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => '请先登录'], 401);
    }
    return $_SESSION['user_id'];
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    // 在响应中包含CSRF token
    if (isset($_SESSION['csrf_token'])) {
        $data['csrf_token'] = $_SESSION['csrf_token'];
    }
    echo json_encode($data);
    exit;
}

switch ($action) {
    case 'login':
        $username = sanitizeUsername($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            jsonResponse(['success' => false, 'message' => '请填写用户名和密码'], 400);
        }

        // 检查速率限制
        if (!checkLoginRateLimit($username)) {
            jsonResponse(['success' => false, 'message' => '登录尝试过于频繁，请稍后再试'], 429);
        }

        $user = $db->getUserByUsername($username);
        if (!$user) {
            jsonResponse(['success' => false, 'message' => '用户不存在，请检查用户名是否正确'], 401);
        }
        if (!password_verify($password, $user['password'])) {
            jsonResponse(['success' => false, 'message' => '密码错误，请重新输入'], 401);
        }

        // 登录成功，清除速率限制记录
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'login_attempts_' . md5($username . '|' . $ip);
        unset($_SESSION[$key]);

        // 生成新的会话ID防止会话固定攻击
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['login_time'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

        jsonResponse(['success' => true, 'message' => '登录成功', 'user' => safeUser($user)]);

    case 'send_email_code':
        $email = sanitizeEmail($_POST['email'] ?? '');
        sendRegisterEmailCode($email);

    case 'register':
        $username = sanitizeUsername($_POST['username'] ?? '');
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $emailCode = trim($_POST['email_code'] ?? '');

        if (empty($username) || empty($email) || empty($password)) {
            jsonResponse(['success' => false, 'message' => '请填写所有字段'], 400);
        }
        if (mb_strlen($username, 'UTF-8') < 2 || mb_strlen($username, 'UTF-8') > 30) {
            jsonResponse(['success' => false, 'message' => '用户名需2-30个字符'], 400);
        }
        if (!preg_match('/^[\p{L}\p{N}_\x{4e00}-\x{9fa5}]+$/u', $username)) {
            jsonResponse(['success' => false, 'message' => '用户名只能包含中文、字母、数字和下划线'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => false, 'message' => '请输入有效的邮箱地址'], 400);
        }
        if (strlen($password) < 6) {
            jsonResponse(['success' => false, 'message' => '密码至少6位'], 400);
        }
        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{6,}$/', $password)) {
            jsonResponse(['success' => false, 'message' => '密码需包含字母和数字'], 400);
        }
        if ($password !== $passwordConfirm) {
            jsonResponse(['success' => false, 'message' => '两次密码不一致'], 400);
        }
        verifyRegisterEmailCode($email, $emailCode);
        if ($db->getUserByUsername($username)) {
            jsonResponse(['success' => false, 'message' => '用户名已存在'], 400);
        }

        // 检查邮箱是否已被使用
        $allUsers = $db->getTable('users');
        foreach ($allUsers as $u) {
            if (isset($u['email']) && strtolower($u['email']) === strtolower($email)) {
                jsonResponse(['success' => false, 'message' => '该邮箱已被注册'], 400);
            }
        }

        $newUser = [
            'id' => genId(),
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'balance' => 0,
            'role' => 'user',
            'membership_level' => 'Free',
            'created_at' => time(),
            'last_login' => time()
        ];

        $db->addUser($newUser);

        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUser['id'];
        $_SESSION['username'] = $newUser['username'];
        $_SESSION['login_time'] = time();

        unset($newUser['password']);
        jsonResponse(['success' => true, 'message' => '注册成功', 'user' => $newUser]);

    case 'logout':
        $userId = $_SESSION['user_id'] ?? null;
        session_destroy();
        if ($userId) {
            // 更新用户最后登录时间
            $user = $db->getUserById($userId);
            if ($user) {
                $db->updateUser($userId, ['last_login' => time()]);
            }
        }
        jsonResponse(['success' => true, 'message' => '已退出']);

    case 'get_current_user':
        if (!isset($_SESSION['user_id'])) {
            jsonResponse(['success' => false, 'logged_in' => false]);
        }
        
        // 会话检查
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // IP变更检测（可选，宽松模式）
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== $currentIp) {
            // 记录IP变更但不立即销毁会话（宽松模式）
            error_log("IP changed for user {$_SESSION['user_id']}: {$_SESSION['ip']} -> {$currentIp}");
        }
        
        // User-Agent检查
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $userAgent) {
            // User-Agent变更，警告但不销毁会话
            error_log("User-Agent changed for user {$_SESSION['user_id']}");
        }
        
        $user = $db->getUserById($_SESSION['user_id']);
        if (!$user) {
            session_destroy();
            jsonResponse(['success' => false, 'logged_in' => false]);
        }
        
        // 检查会话是否过期（24小时）
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
            session_destroy();
            jsonResponse(['success' => false, 'logged_in' => false, 'message' => '会话已过期，请重新登录']);
        }
        
        // 更新最后活跃时间（每10分钟）
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 600) {
            $_SESSION['last_activity'] = time();
        } elseif (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }
        
        jsonResponse(['success' => true, 'logged_in' => true, 'user' => safeUser($user)]);

    case 'send_profile_email_code':
        $userId = requireAuth();
        $user = $db->getUserById($userId);
        if (!$user) {
            jsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        sendProfileEmailCode($user);

    case 'change_password':
        $userId = requireAuth();
        $user = $db->getUserById($userId);
        if (!$user) {
            jsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        $code = trim($_POST['email_code'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if (!$code || !$newPassword || !$confirmPassword) {
            jsonResponse(['success' => false, 'message' => '请填写验证码和新密码'], 400);
        }
        if ($newPassword !== $confirmPassword) {
            jsonResponse(['success' => false, 'message' => '两次密码不一致'], 400);
        }
        if (strlen($newPassword) < 6 || !preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*#?&]{6,}$/', $newPassword)) {
            jsonResponse(['success' => false, 'message' => '密码至少6位，且需包含字母和数字'], 400);
        }
        verifyProfileEmailCode($user, $code);
        $ok = $db->updateUser($userId, ['password' => password_hash($newPassword, PASSWORD_DEFAULT)]);
        if (!$ok) {
            jsonResponse(['success' => false, 'message' => '密码修改失败'], 500);
        }
        jsonResponse(['success' => true, 'message' => '密码修改成功，请牢记新密码']);

    case 'unbind_qq':
        $userId = requireAuth();
        $ok = $db->updateUser($userId, ['qq_openid' => '', 'qq_nickname' => '', 'qq_bound_at' => 0]);
        if (!$ok) {
            jsonResponse(['success' => false, 'message' => '解绑失败'], 500);
        }
        $user = $db->getUserById($userId);
        jsonResponse(['success' => true, 'message' => 'QQ 已解绑', 'user' => safeUser($user)]);

    case 'search_users':
        requireAuth();
        $query = sanitizeUsername($_GET['query'] ?? '');
        if (empty($query) || strlen($query) < 2) {
            jsonResponse(['success' => true, 'users' => []]);
        }
        $allUsers = $db->getTable('users');
        $results = array_filter($allUsers, function($u) use ($query) {
            return isset($u['username']) && 
                   stripos($u['username'], $query) !== false && 
                   $u['username'] !== $_SESSION['username'];
        });
        $results = array_map(function($u) {
            return ['id' => htmlspecialchars($u['id'], ENT_QUOTES, 'UTF-8'), 
                    'username' => htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8')];
        }, array_values($results));
        jsonResponse(['success' => true, 'users' => $results]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
