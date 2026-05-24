<?php
/**
 * 用户认证相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

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
    return preg_replace('/[^a-zA-Z0-9_]/', '', $username);
}

function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
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

        unset($user['password']);
        jsonResponse(['success' => true, 'message' => '登录成功', 'user' => $user]);

    case 'register':
        $username = sanitizeUsername($_POST['username'] ?? '');
        $email = sanitizeEmail($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            jsonResponse(['success' => false, 'message' => '请填写所有字段'], 400);
        }
        if (strlen($username) < 3 || strlen($username) > 20) {
            jsonResponse(['success' => false, 'message' => '用户名需3-20个字符'], 400);
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            jsonResponse(['success' => false, 'message' => '用户名只能包含字母、数字和下划线'], 400);
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
        
        unset($user['password']);
        jsonResponse(['success' => true, 'logged_in' => true, 'user' => $user]);

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
