<?php
/**
 * 私信相关API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function genId() {
    return 'id_' . time() . '_' . bin2hex(random_bytes(6));
}

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

function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

switch ($action) {
    case 'contacts':
        $username = getCurrentUsername();
        if (!$username) {
            jsonResponse(['success' => false, 'message' => '请先登录'], 401);
        }
        $contacts = $db->getContacts($username);
        $result = [];
        foreach ($contacts as $contact) {
            $unread = 0;
            $msgs = $db->getMessages($username, $contact);
            foreach ($msgs as $m) {
                if ($m['from'] === $contact && $m['to'] === $username && !$m['read']) {
                    $unread++;
                }
            }
            $result[] = ['username' => sanitizeString($contact), 'unread' => $unread];
        }
        jsonResponse(['success' => true, 'contacts' => $result]);

    case 'conversation':
        $username = getCurrentUsername();
        if (!$username) {
            jsonResponse(['success' => false, 'message' => '请先登录'], 401);
        }
        $partner = $_GET['partner'] ?? '';
        if (empty($partner) || !validateUsername($partner)) {
            jsonResponse(['success' => false, 'message' => '无效的联系人'], 400);
        }
        $msgs = $db->getMessages($username, $partner);
        $db->markMessagesRead($username, $partner);
        jsonResponse(['success' => true, 'messages' => $msgs]);

    case 'send':
        $username = getCurrentUsername();
        if (!$username) {
            jsonResponse(['success' => false, 'message' => '请先登录'], 401);
        }
        $to = $_POST['to'] ?? '';
        $content = trim($_POST['content'] ?? '');

        if (empty($to) || !validateUsername($to)) {
            jsonResponse(['success' => false, 'message' => '无效的收件人'], 400);
        }
        if (empty($content)) {
            jsonResponse(['success' => false, 'message' => '请填写内容'], 400);
        }
        if (strlen($content) > 1000) {
            jsonResponse(['success' => false, 'message' => '内容最多1000字符'], 400);
        }

        $message = [
            'id' => genId(),
            'from' => $username,
            'to' => $to,
            'content' => sanitizeString($content),
            'timestamp' => time(),
            'read' => false
        ];

        $db->addMessage($message);
        jsonResponse(['success' => true, 'message' => $message]);

    case 'unread_count':
        $username = getCurrentUsername();
        if (!$username) {
            jsonResponse(['success' => false, 'unread' => 0], 401);
        }
        $count = $db->getUnreadCount($username);
        jsonResponse(['success' => true, 'unread' => $count]);

    default:
        jsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
