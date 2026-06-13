<?php
/**
 * 管理员后台专用 API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/../core/SubdomainHelper.php';

header('Content-Type: application/json; charset=utf-8');

$db = Database::getInstance();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function adminJsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function adminRequireAdmin() {
    global $db;
    if (!isset($_SESSION['user_id'])) {
        adminJsonResponse(['success' => false, 'message' => '请先登录'], 401);
    }
    $user = $db->getUserById($_SESSION['user_id']);
    if (!$user || ($user['role'] ?? '') !== 'admin') {
        adminJsonResponse(['success' => false, 'message' => '需要管理员权限'], 403);
    }
    return $user;
}

function adminSafeUser($user) {
    unset($user['password']);
    return $user;
}

function adminBaseUrl() {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function adminSafeProduct($product) {
    if (!is_array($product)) {
        return null;
    }
    $accounts = is_array($product['account_list'] ?? null) ? $product['account_list'] : [];
    unset($product['account_list'], $product['pickup_password']);
    $product['stock'] = count(array_filter($accounts, fn($item) => is_array($item) && empty($item['sold'])));
    return $product;
}

function adminFinanceRequests() {
    global $db;
    $requests = array_merge($db->getDepositRequests(), $db->getWithdrawRequests());
    usort($requests, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
    foreach ($requests as &$request) {
        $user = $db->getUserById($request['user_id'] ?? '');
        $request['user_email'] = $user['email'] ?? '';
    }
    unset($request);
    return array_values($requests);
}

function adminCardItems() {
    global $db;
    $cards = $db->getCardCodes(false);
    foreach ($cards as &$card) {
        $usedUserId = $card['used_by'] ?? '';
        $usedUser = $usedUserId ? $db->getUserById($usedUserId) : null;
        $card['used_user_id'] = $usedUserId ?: '';
        $card['used_user_email'] = $usedUser['email'] ?? '';
    }
    unset($card);
    return array_values($cards);
}

function adminUserPayload() {
    $id = trim($_POST['id'] ?? '');
    if ($id === '') {
        adminJsonResponse(['success' => false, 'message' => '缺少用户ID'], 400);
    }
    $payload = [
        'id' => $id,
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user',
        'membership_level' => trim($_POST['membership_level'] ?? 'Free'),
        'balance' => floatval($_POST['balance'] ?? 0),
    ];
    $newPassword = trim((string)($_POST['new_password'] ?? ''));
    if ($newPassword !== '') {
        if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            adminJsonResponse(['success' => false, 'message' => '新密码长度需为 8-72 位'], 400);
        }
        $payload['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    return $payload;
}

function adminLogFilePath($type, $date = '') {
    $allowed = ['api', 'php_error', 'security'];
    if (!in_array($type, $allowed, true)) {
        return null;
    }
    $date = $date ?: date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return null;
    }
    return dirname(__DIR__) . '/logs/' . $type . '_' . $date . '.log';
}

function adminReadLastLines($file, $maxLines = 300) {
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return '';
    }
    $lines = array_slice($lines, -max(1, min(1000, (int)$maxLines)));
    return implode("\n", $lines);
}

function adminListLogDates($type) {
    $allowed = ['api', 'php_error', 'security'];
    if (!in_array($type, $allowed, true)) {
        return [];
    }
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        return [];
    }
    $dates = [];
    foreach (glob($dir . '/' . $type . '_*.log') ?: [] as $file) {
        if (preg_match('/_' . '(\d{4}-\d{2}-\d{2})\.log$/', basename($file), $m)) {
            $dates[] = $m[1];
        }
    }
    rsort($dates);
    return array_values(array_unique($dates));
}

function adminClearAllLogs() {
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        return 0;
    }
    $count = 0;
    foreach (glob($dir . '/*.log') ?: [] as $file) {
        if (is_file($file) && is_writable($file)) {
            file_put_contents($file, '');
            $count++;
        }
    }
    return $count;
}

function adminMembershipPayload() {
    $levelsJson = $_POST['levels'] ?? '';
    $levels = json_decode($levelsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($levels)) {
        adminJsonResponse(['success' => false, 'message' => '会员等级数据格式错误'], 400);
    }
    return $levels;
}

function adminUpdaterConfig() {
    return [
        'repo_url' => 'https://github.com/Aze0920/Market.git',
        'branch' => 'main',
        'work_dir' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_repo' . DIRECTORY_SEPARATOR . 'Market',
        'site_dir' => dirname(__DIR__),
        'token_file' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'github-token.txt',
        'github_token' => getenv('KEYNEST_GITHUB_TOKEN') ?: trim((string)@file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'github-token.txt')),
        'version_file' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_version.json',
        'git_bin' => adminFindGitBinary(),
    ];
}

function adminFindGitBinary($withDebug = false) {
    $disabled = array_filter(array_map('trim', explode(',', ini_get('disable_functions') ?: '')));
    $requiredFunctions = ['proc_open'];
    $blockedFunctions = array_values(array_intersect($requiredFunctions, $disabled));
    $canRunCommand = function_exists('proc_open') && empty($blockedFunctions);
    $diagnostics = [
        'php_os' => PHP_OS,
        'disabled_functions' => ini_get('disable_functions') ?: '',
        'proc_open_available' => $canRunCommand,
        'blocked_functions' => $blockedFunctions,
        'path' => getenv('PATH') ?: '',
        'candidates' => [],
    ];
    $candidates = stripos(PHP_OS, 'WIN') === 0 ? [
        'git',
        'C:\\Program Files\\Git\\cmd\\git.exe',
        'C:\\Program Files\\Git\\bin\\git.exe',
        'C:\\Program Files (x86)\\Git\\cmd\\git.exe',
        'C:\\Program Files (x86)\\Git\\bin\\git.exe',
        getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '\\Programs\\Git\\cmd\\git.exe' : '',
    ] : [
        'git',
        '/usr/bin/git',
        '/usr/local/bin/git',
        '/bin/git',
        '/www/server/git/bin/git',
    ];
    foreach ($candidates as $git) {
        if (!$git) continue;
        $item = ['path' => $git, 'exists' => $git === 'git' ? null : is_file($git), 'code' => null, 'output' => ''];
        if (!$canRunCommand) {
            $item['output'] = 'PHP 禁用了 proc_open，无法执行命令';
            $diagnostics['candidates'][] = $item;
            continue;
        }
        if ($git !== 'git' && !$item['exists']) {
            $diagnostics['candidates'][] = $item;
            continue;
        }
        $cmd = ($git === 'git' ? 'git' : escapeshellarg($git)) . ' --version';
        $res = adminRunCommandRaw($cmd);
        $item['code'] = $res['code'];
        $item['output'] = $res['output'];
        $diagnostics['candidates'][] = $item;
        if ($res['code'] === 0) {
            return $withDebug ? ['git_bin' => $git, 'diagnostics' => $diagnostics] : $git;
        }
    }
    return $withDebug ? ['git_bin' => '', 'diagnostics' => $diagnostics] : '';
}

function adminGitCommand($args, $config = null) {
    $config = $config ?: adminUpdaterConfig();
    if (empty($config['git_bin'])) {
        return '';
    }
    return ($config['git_bin'] === 'git' ? 'git' : escapeshellarg($config['git_bin'])) . ' ' . $args;
}

function adminRunCommandRaw($command, $cwd = null) {
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptorSpec, $pipes, $cwd ?: null);
    if (!is_resource($process)) {
        return ['code' => 1, 'output' => '无法执行命令'];
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return ['code' => $code, 'output' => trim($output)];
}

function adminRunCommand($command, $cwd = null) {
    return adminRunCommandRaw($command, $cwd);
}

function adminAppVersion() {
    return 'V1.2.6';
}

function adminUpdaterVersion($config) {
    if (!is_file($config['version_file'])) {
        return [];
    }
    $data = json_decode(file_get_contents($config['version_file']), true);
    return is_array($data) ? $data : [];
}

function adminSaveUpdaterVersion($config, $commit) {
    $dir = dirname($config['version_file']);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $payload = [
        'version' => adminAppVersion(),
        'commit' => $commit,
        'branch' => $config['branch'],
        'repo_url' => $config['repo_url'],
        'updated_at' => time(),
        'updated_at_text' => date('Y-m-d H:i:s'),
    ];
    file_put_contents($config['version_file'], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function adminGitRemoteUrl($config) {
    $token = trim((string)($config['github_token'] ?? ''));
    if ($token === '') {
        return $config['repo_url'];
    }
    return 'https://Aze0920:' . rawurlencode($token) . '@github.com/Aze0920/Market.git';
}

function adminEnsureUpdateRepo($config) {
    $parent = dirname($config['work_dir']);
    if (!is_dir($parent)) {
        mkdir($parent, 0755, true);
    }
    if (!is_dir($config['work_dir'] . DIRECTORY_SEPARATOR . '.git')) {
        if (is_dir($config['work_dir'])) {
            adminDeleteDirectory($config['work_dir']);
        }
        $clone = adminRunCommand(adminGitCommand('clone --branch ' . escapeshellarg($config['branch']) . ' ' . escapeshellarg(adminGitRemoteUrl($config)) . ' ' . escapeshellarg($config['work_dir']), $config), $parent);
        if ($clone['code'] !== 0) {
            adminJsonResponse(['success' => false, 'message' => '克隆 GitHub 仓库失败', 'output' => $clone['output']], 500);
        }
    }
    adminRunCommand(adminGitCommand('remote set-url origin ' . escapeshellarg(adminGitRemoteUrl($config)), $config), $config['work_dir']);
}

function adminUpdateStatus() {
    $gitCheck = adminFindGitBinary(true);
    $config = adminUpdaterConfig();
    $config['git_bin'] = $gitCheck['git_bin'];
    $version = adminUpdaterVersion($config);
    $status = [
        'repo_url' => $config['repo_url'],
        'branch' => $config['branch'],
        'work_dir' => $config['work_dir'],
        'site_dir' => $config['site_dir'],
        'version_file' => $config['version_file'],
        'site_version' => $version['version'] ?? adminAppVersion(),
        'site_commit' => $version['commit'] ?? '',
        'site_updated_at' => $version['updated_at_text'] ?? '',
        'work_repo_exists' => is_dir($config['work_dir'] . DIRECTORY_SEPARATOR . '.git'),
        'git_available' => !empty($config['git_bin']),
        'git_bin' => $config['git_bin'],
        'git_diagnostics' => $gitCheck['diagnostics'],
    ];
    if (!$status['git_available']) {
        $status['local_commit'] = $status['site_commit'];
        $status['work_commit'] = '';
        $status['remote_commit'] = '';
        $status['has_update'] = false;
        return $status;
    }
    if ($status['work_repo_exists']) {
        $local = adminRunCommand(adminGitCommand('rev-parse HEAD', $config), $config['work_dir']);
        $remote = adminRunCommand(adminGitCommand('ls-remote origin refs/heads/' . escapeshellarg($config['branch']), $config), $config['work_dir']);
        $status['work_commit'] = $local['code'] === 0 ? trim($local['output']) : '';
        $status['local_commit'] = $status['site_commit'] ?: $status['work_commit'];
        $status['remote_commit'] = $remote['code'] === 0 ? trim(strtok($remote['output'], "\t")) : '';
        $status['has_update'] = $status['remote_commit'] && $status['local_commit'] && $status['remote_commit'] !== $status['local_commit'];
    } else {
        $remote = adminRunCommand(adminGitCommand('ls-remote ' . escapeshellarg($config['repo_url']) . ' refs/heads/' . escapeshellarg($config['branch']), $config));
        $status['local_commit'] = $status['site_commit'];
        $status['work_commit'] = '';
        $status['remote_commit'] = $remote['code'] === 0 ? trim(strtok($remote['output'], "\t")) : '';
        $status['has_update'] = !empty($status['remote_commit']) && $status['remote_commit'] !== $status['local_commit'];
    }
    return $status;
}

function adminDeleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

function adminUpdaterDataAllowUpdate() {
    return ['data/.htaccess', 'data/index.php'];
}

function adminPathIsPreserved($relative) {
    $relative = str_replace('\\', '/', trim($relative, '/'));
    if ($relative === '') {
            return true;
        }
    if (in_array($relative, adminUpdaterDataAllowUpdate(), true)) {
        return false;
    }
    if (preg_match('#^data/.+\.json$#i', $relative)) {
        return true;
    }
    $exact = ['.git', 'config/database.php', 'data/install.lock', 'data/update_version.json'];
    foreach ($exact as $skip) {
        if ($relative === $skip) {
            return true;
        }
    }
    foreach (['logs', 'data/update_repo'] as $prefix) {
        if ($relative === $prefix || str_starts_with($relative, $prefix . '/')) {
            return true;
        }
    }
    if ($relative === 'data' || str_starts_with($relative, 'data/')) {
        return true;
    }
    return false;
}

function adminCopyDirectory($source, $target, $rootSource = null) {
    $rootSource = $rootSource ?: $source;
    if (!is_dir($target)) {
        mkdir($target, 0755, true);
    }
    foreach (scandir($source) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $target . DIRECTORY_SEPARATOR . $item;
        $relative = str_replace('\\', '/', ltrim(substr($src, strlen($rootSource)), DIRECTORY_SEPARATOR));
        if (adminPathIsPreserved($relative)) {
            continue;
        }
        if (is_dir($src)) {
            if (!is_dir($dst)) {
                mkdir($dst, 0755, true);
            }
            adminCopyDirectory($src, $dst, $rootSource);
        } else {
            copy($src, $dst);
        }
    }
}

function adminCopyFileFromRepo($config, $relative) {
    $relative = str_replace('\\', '/', trim($relative, '/'));
    if ($relative === '' || str_contains($relative, '..')) {
        return ['ok' => false, 'reason' => '非法路径'];
    }
    $src = $config['work_dir'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $dst = $config['site_dir'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($src)) {
        return ['ok' => false, 'reason' => 'Git 仓库中不存在该文件'];
    }
    $dir = dirname($dst);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'reason' => '无法创建目标目录'];
    }
    if (is_file($dst) && !is_writable($dst)) {
        return ['ok' => false, 'reason' => '目标文件不可写，请把所有者改为 www'];
    }
    if (!is_file($dst) && !is_writable($dir)) {
        return ['ok' => false, 'reason' => '目标目录不可写，请把所有者改为 www'];
    }
    if (!@copy($src, $dst)) {
        return ['ok' => false, 'reason' => '复制失败'];
    }
    return ['ok' => true];
}

function adminDeleteSiteFile($config, $relative) {
    $relative = str_replace('\\', '/', trim($relative, '/'));
    if ($relative === '' || str_contains($relative, '..') || adminPathIsPreserved($relative)) {
        return false;
    }
    $path = $config['site_dir'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_file($path)) {
        return unlink($path);
    }
    return false;
}

function adminChangedFiles($config, $fromCommit, $toCommit) {
    if ($fromCommit === '') {
        $cmd = adminGitCommand('diff-tree --no-commit-id --name-status -r ' . escapeshellarg($toCommit), $config);
    } else {
        $cmd = adminGitCommand('diff --name-status ' . escapeshellarg($fromCommit) . ' ' . escapeshellarg($toCommit), $config);
    }
    $res = adminRunCommand($cmd, $config['work_dir']);
    if ($res['code'] !== 0) {
        return ['success' => false, 'output' => $res['output'], 'files' => []];
    }
    $files = [];
    foreach (preg_split('/\r?\n/', trim($res['output'])) as $line) {
        if ($line === '') continue;
        $parts = preg_split('/\s+/', $line);
        $status = $parts[0] ?? '';
        $path = end($parts) ?: '';
        if ($path !== '') {
            $files[] = ['status' => $status, 'path' => str_replace('\\', '/', $path)];
        }
    }
    return ['success' => true, 'output' => $res['output'], 'files' => $files];
}

function adminApplyChangedFiles($config, $files) {
    $changed = [];
    $skipped = [];
    $failed = [];
    foreach ($files as $file) {
        $status = strtoupper($file['status'] ?? '');
        $path = $file['path'] ?? '';
        if ($path === '') continue;
        if (adminPathIsPreserved($path)) {
            $skipped[] = '跳过（保留本地） ' . $path;
            continue;
        }
        if (str_starts_with($status, 'D')) {
            if (adminDeleteSiteFile($config, $path)) {
                $changed[] = '删除 ' . $path;
            } else {
                $skipped[] = '跳过删除 ' . $path;
            }
        } else {
            $result = adminCopyFileFromRepo($config, $path);
            if (!empty($result['ok'])) {
                $changed[] = '更新 ' . $path;
            } else {
                $failed[] = '更新失败 ' . $path . '（' . ($result['reason'] ?? '未知错误') . '）';
            }
        }
    }
    return ['changed' => $changed, 'skipped' => $skipped, 'failed' => $failed];
}

function adminRequireDatabaseConfig($config) {
    $databaseConfig = $config['site_dir'] . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
    if (!is_file($databaseConfig)) {
        adminJsonResponse([
            'success' => false,
            'message' => '当前服务器缺少 config/database.php。请先从备份或本地重新上传数据库配置文件，否则系统会进入安装页。'
        ], 500);
    }
}

function adminEnsureInstallLock($config) {
    $databaseConfig = $config['site_dir'] . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
    $lockFile = $config['site_dir'] . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'install.lock';
    if (!is_file($databaseConfig) || is_file($lockFile)) {
        return;
    }
    $lockDir = dirname($lockFile);
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0755, true);
    }
    file_put_contents($lockFile, 'installed_at=' . date('c') . PHP_EOL . 'created_by=updater' . PHP_EOL, LOCK_EX);
}

function adminApplyUpdate() {
    $config = adminUpdaterConfig();
    if (empty($config['git_bin'])) {
        adminJsonResponse(['success' => false, 'message' => '服务器 PHP 环境无法执行 Git 命令。请在宝塔 PHP 设置里删除 proc_open 的禁用项，并确认服务器已安装 git。', 'status' => adminUpdateStatus()], 500);
    }
    adminRequireDatabaseConfig($config);
    adminEnsureUpdateRepo($config);
    $fetch = adminRunCommand(adminGitCommand('fetch origin ' . escapeshellarg($config['branch']), $config), $config['work_dir']);
    if ($fetch['code'] !== 0) {
        adminJsonResponse(['success' => false, 'message' => '拉取远程更新失败', 'output' => $fetch['output']], 500);
    }
    $targetCommit = adminRunCommand(adminGitCommand('rev-parse FETCH_HEAD', $config), $config['work_dir']);
    if ($targetCommit['code'] !== 0 || trim($targetCommit['output']) === '') {
        adminJsonResponse(['success' => false, 'message' => '读取远程版本失败', 'output' => $targetCommit['output']], 500);
    }
    $targetCommitHash = trim($targetCommit['output']);
    $reset = adminRunCommand(adminGitCommand('reset --hard ' . escapeshellarg($targetCommitHash), $config), $config['work_dir']);
    if ($reset['code'] !== 0) {
        adminJsonResponse(['success' => false, 'message' => '更新工作目录失败', 'output' => $reset['output']], 500);
    }
    $version = adminUpdaterVersion($config);
    $currentCommit = trim($version['commit'] ?? '');
    $diff = adminChangedFiles($config, $currentCommit, $targetCommitHash);
    if (!$diff['success']) {
        adminJsonResponse(['success' => false, 'message' => '计算变更文件失败', 'output' => $diff['output']], 500);
    }
    $applied = adminApplyChangedFiles($config, $diff['files']);
    if (!empty($applied['failed'])) {
        $output = implode("\n", array_filter([
            $fetch['output'],
            $reset['output'],
            '变更文件：' . count($diff['files']) . ' 个',
            implode("\n", $applied['changed']),
            implode("\n", $applied['skipped']),
            implode("\n", $applied['failed']),
        ]));
        adminJsonResponse([
            'success' => false,
            'message' => '更新未完成：' . count($applied['failed']) . ' 个文件未能写入。请查看下方日志中的具体原因；若为不可写，请在宝塔把网站目录所有者改为 www 后重试。',
            'output' => $output,
            'status' => adminUpdateStatus()
        ], 500);
    }
    adminEnsureInstallLock($config);
    adminSaveUpdaterVersion($config, $targetCommitHash);
    $output = implode("\n", array_filter([
        $fetch['output'],
        $reset['output'],
        '变更文件：' . count($diff['files']) . ' 个',
        '已记录版本：' . adminAppVersion(),
        implode("\n", $applied['changed']),
        implode("\n", $applied['skipped']),
        implode("\n", $applied['failed'] ?? []),
    ]));
    return ['success' => true, 'message' => '更新完成', 'status' => adminUpdateStatus(), 'output' => $output];
}

function adminEmailForUserId($userId) {
    global $db;
    $userId = trim((string)$userId);
    if ($userId === '') return '';
    $user = $db->getUserById($userId);
    return $user['email'] ?? '';
}

function adminAttachUserEmails($record) {
    if (!is_array($record)) return $record;
    foreach (['user_id', 'buyer_id', 'seller_id', 'used_by'] as $field) {
        if (!empty($record[$field])) {
            $email = adminEmailForUserId($record[$field]);
            if ($email !== '') {
                $record[$field . '_email'] = $email;
            }
        }
    }
    return $record;
}

function adminSafeComplaintOrder($order) {
    $order = adminAttachUserEmails($order);
    if (isset($order['complaint']) && is_array($order['complaint'])) {
        unset($order['complaint']['password_hash']);
        unset($order['complaint']['email']);
    }
    return $order;
}

function adminComplaintOrders() {
    global $db;
    $orders = $db->getOrders();
    $items = [];
    foreach ($orders as $order) {
        if (!empty($order['complaint']) && is_array($order['complaint'])) {
            $items[] = adminSafeComplaintOrder($order);
        }
    }
    usort($items, fn($a, $b) => (($b['complaint']['updated_at'] ?? $b['complaint']['created_at'] ?? 0) - ($a['complaint']['updated_at'] ?? $a['complaint']['created_at'] ?? 0)));
    return array_values($items);
}

function adminCommentItems() {
    global $db;
    $items = [];
    foreach ($db->getComments() as $comment) {
        if (!is_array($comment)) continue;
        $product = $db->getProductById($comment['product_id'] ?? '');
        $order = $db->getOrderById($comment['order_id'] ?? '');
        $comment = adminAttachUserEmails($comment);
        $comment['product_title'] = $product['title'] ?? '';
        $comment['seller_id'] = $product['seller_id'] ?? ($order['seller_id'] ?? '');
        $comment['seller_name'] = $product['seller_name'] ?? ($order['seller_name'] ?? '');
        $comment['seller_id_email'] = adminEmailForUserId($comment['seller_id'] ?? '');
        $comment['order_price'] = $order['price'] ?? 0;
        $comment['order_quantity'] = $order['quantity'] ?? 1;
        $items[] = $comment;
    }
    usort($items, fn($a, $b) => intval($b['created_at'] ?? 0) - intval($a['created_at'] ?? 0));
    return array_values($items);
}

function adminListParams() {
    $page = max(1, intval($_GET['page'] ?? $_POST['page'] ?? 1));
    $pageSize = max(10, min(200, intval($_GET['page_size'] ?? $_POST['page_size'] ?? 20)));
    $keyword = strtolower(trim((string)($_GET['keyword'] ?? $_POST['keyword'] ?? '')));
    $status = trim((string)($_GET['status'] ?? $_POST['status'] ?? ''));
    if ($status === 'all') {
        $status = '';
    }
    $merchantStatus = trim((string)($_GET['merchant_status'] ?? $_POST['merchant_status'] ?? ''));
    return compact('page', 'pageSize', 'keyword', 'status', 'merchantStatus');
}

function adminPaginateArray(array $items, $page, $pageSize) {
    $total = count($items);
    $offset = max(0, ($page - 1) * $pageSize);
    return [
        'items' => array_slice($items, $offset, $pageSize),
        'total' => $total,
        'page' => $page,
        'pageSize' => $pageSize,
    ];
}

function adminFilterUsers(array $users, array $params) {
    if ($params['merchantStatus'] !== '') {
        $users = array_values(array_filter($users, fn($user) => ($user['merchant_status'] ?? 'none') === $params['merchantStatus']));
    }
    if ($params['keyword'] !== '') {
        $keyword = $params['keyword'];
        $users = array_values(array_filter($users, function($user) use ($keyword) {
            $haystack = strtolower(implode(' ', [
                (string)($user['username'] ?? ''),
                (string)($user['email'] ?? ''),
                (string)($user['id'] ?? ''),
            ]));
            return strpos($haystack, $keyword) !== false;
        }));
    }
    return $users;
}

function adminFilterProducts(array $products, array $params) {
    if ($params['keyword'] !== '') {
        $keyword = $params['keyword'];
        $products = array_values(array_filter($products, function($product) use ($keyword) {
            $haystack = strtolower(implode(' ', [
                (string)($product['title'] ?? ''),
                (string)($product['seller_name'] ?? ''),
                (string)($product['seller_id'] ?? ''),
                (string)($product['category'] ?? ''),
                (string)($product['id'] ?? ''),
            ]));
            return strpos($haystack, $keyword) !== false;
        }));
    }
    return $products;
}

function adminFilterComments(array $comments, array $params) {
    if ($params['keyword'] !== '') {
        $keyword = $params['keyword'];
        $comments = array_values(array_filter($comments, function($comment) use ($keyword) {
            $haystack = strtolower(implode(' ', [
                (string)($comment['username'] ?? ''),
                (string)($comment['user_id_email'] ?? ''),
                (string)($comment['product_title'] ?? ''),
                (string)($comment['seller_name'] ?? ''),
                (string)($comment['seller_id_email'] ?? ''),
                (string)($comment['order_id'] ?? ''),
                (string)($comment['content'] ?? ''),
            ]));
            return strpos($haystack, $keyword) !== false;
        }));
    }
    return $comments;
}

function adminFilterComplaints(array $complaints, array $params) {
    if ($params['status'] !== '') {
        $status = $params['status'];
        $complaints = array_values(array_filter($complaints, fn($order) => ($order['complaint']['status'] ?? '') === $status));
    }
    if ($params['keyword'] !== '') {
        $keyword = $params['keyword'];
        $complaints = array_values(array_filter($complaints, function($order) use ($keyword) {
            $complaint = $order['complaint'] ?? [];
            $haystack = strtolower(implode(' ', [
                (string)($order['id'] ?? ''),
                (string)($order['payment_trade_no'] ?? ''),
                (string)($order['product_title'] ?? ''),
                (string)($order['buyer_name'] ?? ''),
                (string)($order['seller_name'] ?? ''),
                (string)($order['buyer_id_email'] ?? ''),
                (string)($order['seller_id_email'] ?? ''),
                (string)($complaint['reason'] ?? ''),
                (string)($complaint['content'] ?? ''),
            ]));
            return strpos($haystack, $keyword) !== false;
        }));
    }
    return $complaints;
}

function adminComplaintSummary(array $complaints) {
    $open = 0;
    foreach ($complaints as $order) {
        $status = $order['complaint']['status'] ?? '';
        if (in_array($status, ['open', 'processing'], true)) {
            $open++;
        }
    }
    return ['all' => count($complaints), 'open' => $open];
}

function adminDashboardData() {
    global $db;
    $todayStart = strtotime('today');
    $users = array_map('adminSafeUser', $db->getTable('users'));
    $products = array_values(array_filter(array_map('adminSafeProduct', $db->getTable('products'))));
    $paymentOrders = $db->getPaymentOrders();
    $todayReceipt = 0.0;
    $todayProfit = 0.0;
    foreach ($paymentOrders as $order) {
        if (($order['status'] ?? '') !== 'paid') {
            continue;
        }
        $paidAt = intval($order['paid_at'] ?? 0);
        if ($paidAt >= $todayStart) {
            $todayReceipt += floatval($order['actual_amount'] ?? $order['amount'] ?? 0);
            $type = (string)($order['type'] ?? '');
            if ($type === 'membership_upgrade') {
                // 在线开通会员：平台收入为支付手续费部分
                $todayProfit += floatval($order['fee'] ?? 0);
            } elseif ($type === 'membership_upgrade_balance') {
                // 余额开通会员：全额归平台
                $todayProfit += abs(floatval($order['amount'] ?? 0));
            } elseif ($type === 'subdomain_purchase' || $type === 'subdomain_renew') {
                // 二级域名购买/续费：全额归平台
                $todayProfit += abs(floatval($order['amount'] ?? 0));
            }
        }
    }
    // 商品成交手续费（含发布费，已合并在订单 fee 字段）
    foreach ($db->getTable('orders') as $order) {
        if (intval($order['purchase_date'] ?? 0) >= $todayStart) {
            $todayProfit += floatval($order['fee'] ?? 0);
        }
    }
    $complaints = adminComplaintOrders();
    $complaintSummary = adminComplaintSummary($complaints);
    $pendingRequests = array_values(array_filter(adminFinanceRequests(), fn($request) => ($request['status'] ?? '') === 'pending'));
    $pendingSubdomains = 0;
    $subPage = 1;
    do {
        $subResult = $db->listSellerSubdomains($subPage, 200, '', '');
        foreach ($subResult['items'] as $subdomain) {
            $status = $subdomain['status'] ?? '';
            if ($status === 'pending' || ($status === 'approved' && intval($subdomain['pending_months'] ?? 0) > 0)) {
                $pendingSubdomains++;
            }
        }
        $subPage++;
    } while (($subPage - 1) * 200 < intval($subResult['total'] ?? 0));

    usort($users, fn($a, $b) => intval($b['created_at'] ?? 0) - intval($a['created_at'] ?? 0));
    return [
        'stats' => [
            'user_count' => count($users),
            'product_count' => count($products),
            'pay_order_count' => count($paymentOrders),
            'open_complaints' => $complaintSummary['open'],
            'pending_requests' => count($pendingRequests),
            'today_receipt' => round($todayReceipt, 2),
            'today_profit' => round($todayProfit, 2),
            'pending_subdomains' => $pendingSubdomains,
        ],
        'recent_users' => array_slice($users, 0, 8),
        'pending_requests' => array_slice($pendingRequests, 0, 8),
    ];
}

function adminBalanceLedgerLabel($type, $amount = 0) {
    $map = [
        'recharge' => '在线充值',
        'card_recharge' => '卡密充值',
        'membership_upgrade_balance' => '余额升级会员',
        'product_purchase' => '余额购买商品',
        'product_purchase_refund' => '购买失败退款',
        'product_sale_income' => '商品销售收入',
        'publish_fee' => '发布商品扣费',
        'publish_fee_refund' => '删除库存退费',
        'admin_balance_adjust' => $amount >= 0 ? '后台加款' : '后台扣款',
        'complaint_freeze' => '投诉冻结',
        'complaint_withdraw_release' => '撤诉解冻',
        'complaint_seller_win' => '投诉放款',
        'complaint_buyer_win' => '投诉退款',
        'complaint_rejudge_buyer_win' => '改判买家胜',
        'complaint_rejudge_seller_win' => '改判卖家胜'
    ];
    return $map[$type] ?? ($amount >= 0 ? '余额收入' : '余额支出');
}

function adminAddBalanceLedgerEntry(&$entries, $data) {
    $balanceDelta = round(floatval($data['balance_delta'] ?? 0), 2);
    $frozenDelta = round(floatval($data['frozen_delta'] ?? 0), 2);
    if (abs($balanceDelta) < 0.01 && abs($frozenDelta) < 0.01) return;
    $type = (string)($data['type'] ?? '');
    $time = intval($data['time'] ?? 0);
    $entries[] = [
        'trade_no' => (string)($data['trade_no'] ?? ''),
        'type' => $type,
        'type_label' => adminBalanceLedgerLabel($type, $balanceDelta),
        'balance_delta' => $balanceDelta,
        'frozen_delta' => $frozenDelta,
        'description' => (string)($data['description'] ?? ''),
        'related_id' => (string)($data['related_id'] ?? ''),
        'time' => $time,
    ];
}

function adminUserBalanceDetails($userId) {
    global $db;
    $user = $db->getUserById($userId);
    if (!$user) {
        return null;
    }
    $entries = [];
    $balanceTypes = ['recharge', 'card_recharge', 'membership_upgrade_balance', 'product_purchase', 'product_purchase_refund', 'product_sale_income', 'publish_fee', 'publish_fee_refund', 'admin_balance_adjust'];
    $paymentOrders = $db->getPaymentOrders($userId);
    foreach ($paymentOrders as $order) {
        $type = (string)($order['type'] ?? '');
        $payType = (string)($order['pay_type'] ?? '');
        $amount = floatval($order['amount'] ?? 0);
        if (($order['status'] ?? '') === 'paid' && abs($amount) >= 0.01 && (in_array($type, $balanceTypes, true) || strpos($payType, 'balance') !== false || $payType === 'admin_adjust' || $payType === 'card_code')) {
            adminAddBalanceLedgerEntry($entries, [
                'trade_no' => $order['trade_no'] ?? $order['id'] ?? '',
                'type' => $type,
                'balance_delta' => $amount,
                'frozen_delta' => 0,
                'description' => $order['description'] ?? $order['title'] ?? '',
                'related_id' => $order['related_id'] ?? '',
                'time' => intval($order['paid_at'] ?? $order['created_at'] ?? 0),
            ]);
        }
    }

    foreach ($paymentOrders as $order) {
        $refundAmount = floatval($order['refunded_amount'] ?? 0);
        if (empty($order['refund_applied']) || $refundAmount <= 0) continue;
        $originId = (string)($order['id'] ?? '');
        $hasRefundRecord = false;
        foreach ($entries as $entry) {
            if (($entry['type'] ?? '') === 'product_purchase_refund' && (string)($entry['related_id'] ?? '') === $originId) {
                $hasRefundRecord = true;
                break;
            }
        }
        if (!$hasRefundRecord) {
            adminAddBalanceLedgerEntry($entries, [
                'trade_no' => ($order['trade_no'] ?? $originId) . '-退款',
                'type' => 'product_purchase_refund',
                'balance_delta' => $refundAmount,
                'description' => $order['delivery_error'] ?? $order['description'] ?? '订单退款到余额',
                'time' => intval($order['refunded_at'] ?? $order['paid_at'] ?? $order['created_at'] ?? 0),
            ]);
        }
    }

    foreach ($db->getOrders() as $order) {
        if (empty($order['complaint']) || !is_array($order['complaint'])) continue;
        $complaint = $order['complaint'];
        $amount = max(0, floatval($complaint['funds_amount'] ?? $order['frozen_amount'] ?? 0));
        if ($amount <= 0) continue;
        $orderNo = $order['id'] ?? '';
        $title = $order['product_title'] ?? '订单';
        $createdAt = intval($complaint['created_at'] ?? $order['frozen_at'] ?? 0);
        $settledAt = intval($complaint['funds_settled_at'] ?? $complaint['updated_at'] ?? 0);
        $fundsAction = (string)($complaint['funds_action'] ?? '');
        $history = is_array($complaint['funds_history'] ?? null) ? $complaint['funds_history'] : [];
        $hasHistory = count($history) > 0;
        if (($order['seller_id'] ?? '') === $userId) {
            adminAddBalanceLedgerEntry($entries, [
                'trade_no' => $orderNo,
                'type' => 'complaint_freeze',
                'balance_delta' => -$amount,
                'frozen_delta' => $amount,
                'description' => '订单投诉冻结：' . $title,
                'time' => $createdAt,
            ]);
            if (!$hasHistory && $fundsAction === 'released_to_seller_by_withdrawal') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_withdraw_release',
                    'balance_delta' => $amount,
                    'frozen_delta' => -$amount,
                    'description' => '买家撤诉，冻结金额解冻：' . $title,
                    'time' => $settledAt,
                ]);
            } elseif (!$hasHistory && $fundsAction === 'release_to_seller') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_seller_win',
                    'balance_delta' => $amount,
                    'frozen_delta' => -$amount,
                    'description' => '投诉判定卖家胜，冻结金额放款：' . $title,
                    'time' => $settledAt,
                ]);
            } elseif (!$hasHistory && $fundsAction === 'refund_to_buyer') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_buyer_win',
                    'balance_delta' => 0,
                    'frozen_delta' => -$amount,
                    'description' => '投诉判定买家胜，冻结金额退给买家：' . $title,
                    'time' => $settledAt,
                ]);
            }
        }
        if (($order['buyer_id'] ?? '') === $userId && !$hasHistory && $fundsAction === 'refund_to_buyer') {
            adminAddBalanceLedgerEntry($entries, [
                'trade_no' => $orderNo,
                'type' => 'complaint_buyer_win',
                'balance_delta' => $amount,
                'frozen_delta' => 0,
                'description' => '投诉判定买家胜，退款入余额：' . $title,
                'time' => $settledAt,
            ]);
        }
        foreach ($history as $item) {
            $from = (string)($item['from'] ?? '');
            $to = (string)($item['to'] ?? '');
            $historyAmount = max(0, floatval($item['amount'] ?? 0));
            if ($historyAmount <= 0) continue;
            $historyTime = intval($item['created_at'] ?? $settledAt);
            if (($order['seller_id'] ?? '') === $userId && ($from === 'frozen' || $from === '') && $to === 'release_to_seller') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_seller_win',
                    'balance_delta' => $historyAmount,
                    'frozen_delta' => -$historyAmount,
                    'description' => '投诉判定卖家胜，冻结金额放款：' . $title,
                    'time' => $historyTime,
                ]);
            }
            if (($order['seller_id'] ?? '') === $userId && ($from === 'frozen' || $from === '') && $to === 'refund_to_buyer') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_buyer_win',
                    'balance_delta' => 0,
                    'frozen_delta' => -$historyAmount,
                    'description' => '投诉判定买家胜，冻结金额退给买家：' . $title,
                    'time' => $historyTime,
                ]);
            }
            if (($order['buyer_id'] ?? '') === $userId && ($from === 'frozen' || $from === '') && $to === 'refund_to_buyer') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_buyer_win',
                    'balance_delta' => $historyAmount,
                    'description' => '投诉判定买家胜，退款入余额：' . $title,
                    'time' => $historyTime,
                ]);
            }
            if (($order['seller_id'] ?? '') === $userId && $from === 'release_to_seller' && $to === 'refund_to_buyer') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_rejudge_buyer_win',
                    'balance_delta' => -$historyAmount,
                    'description' => '改判买家胜，从卖家余额扣回：' . $title,
                    'time' => $historyTime,
                ]);
            }
            if (($order['buyer_id'] ?? '') === $userId && $from === 'refund_to_buyer' && $to === 'release_to_seller') {
                adminAddBalanceLedgerEntry($entries, [
                    'trade_no' => $orderNo,
                    'type' => 'complaint_rejudge_seller_win',
                    'balance_delta' => -$historyAmount,
                    'description' => '改判卖家胜，从买家余额扣回：' . $title,
                    'time' => $historyTime,
                ]);
            }
        }
    }

    usort($entries, fn($a, $b) => intval($b['time'] ?? 0) - intval($a['time'] ?? 0));
    return [
        'user' => adminSafeUser($user),
        'entries' => array_values($entries),
        'income' => array_reduce($entries, fn($sum, $entry) => $sum + max(0, floatval($entry['balance_delta'] ?? 0)), 0),
        'expense' => array_reduce($entries, fn($sum, $entry) => $sum + abs(min(0, floatval($entry['balance_delta'] ?? 0))), 0),
    ];
}

function adminResolveComplaintFunds(&$order, $status) {
    global $db;
    if (!in_array($status, ['resolved', 'rejected'], true)) return [true, ''];

    $amount = max(0, floatval($order['complaint']['funds_amount'] ?? $order['frozen_amount'] ?? 0));
    if ($amount <= 0) {
        $order['complaint']['funds_settled'] = true;
        $order['complaint']['funds_settled_at'] = time();
        $order['complaint']['funds_action'] = 'none';
        $order['complaint']['funds_amount'] = 0;
        return [true, '该订单没有可处理冻结金额，仅更新判定状态'];
    }

    $seller = $db->getUserById($order['seller_id'] ?? '');
    if (!$seller) return [false, '卖家不存在，无法处理冻结金额'];
    $buyer = $db->getUserById($order['buyer_id'] ?? '');
    if (!$buyer) return [false, '买家不存在，无法处理冻结金额'];

    $targetAction = $status === 'resolved' ? 'release_to_seller' : 'refund_to_buyer';
    $currentAction = (string)($order['complaint']['funds_action'] ?? '');
    $message = '';

    if ($currentAction === $targetAction && !empty($order['complaint']['funds_settled'])) {
        return [true, $status === 'resolved' ? '当前已是卖家胜，资金已归卖家' : '当前已是买家胜，资金已归买家'];
    }

    if (!empty($order['balance_frozen'])) {
        $sellerFrozen = floatval($seller['frozen_balance'] ?? 0);
        if ($sellerFrozen + 0.00001 < $amount) {
            return [false, '卖家冻结余额不足，资金状态异常，已停止自动裁决'];
        }
        if ($targetAction === 'release_to_seller') {
            $db->updateUser($seller['id'], [
                'balance' => floatval($seller['balance'] ?? 0) + $amount,
                'frozen_balance' => max(0, $sellerFrozen - $amount)
            ]);
            $message = '已判定卖家胜，冻结金额已放款给卖家';
        } else {
            $db->updateUser($seller['id'], [
                'frozen_balance' => max(0, $sellerFrozen - $amount)
            ]);
            $db->updateUser($buyer['id'], [
                'balance' => floatval($buyer['balance'] ?? 0) + $amount
            ]);
            $message = '已判定买家胜，冻结金额已退还给买家';
        }
        $order['balance_frozen'] = false;
        $order['frozen_released_at'] = time();
    } elseif ($currentAction === 'release_to_seller' && $targetAction === 'refund_to_buyer') {
        $db->updateUser($seller['id'], [
            'balance' => floatval($seller['balance'] ?? 0) - $amount
        ]);
        $db->updateUser($buyer['id'], [
            'balance' => floatval($buyer['balance'] ?? 0) + $amount
        ]);
        $message = '已改判买家胜，金额已从卖家转给买家；卖家余额不足时会记为负数';
    } elseif ($currentAction === 'refund_to_buyer' && $targetAction === 'release_to_seller') {
        $buyerBalance = floatval($buyer['balance'] ?? 0);
        if ($buyerBalance + 0.00001 < $amount) {
            return [false, '买家余额不足，无法改判卖家胜；请先让买家补足余额或人工处理'];
        }
        $db->updateUser($buyer['id'], [
            'balance' => $buyerBalance - $amount
        ]);
        $db->updateUser($seller['id'], [
            'balance' => floatval($seller['balance'] ?? 0) + $amount
        ]);
        $message = '已改判卖家胜，金额已从买家转给卖家';
    } else {
        return [false, '当前资金状态异常，无法自动改判'];
    }

    if (!isset($order['complaint']['funds_history']) || !is_array($order['complaint']['funds_history'])) {
        $order['complaint']['funds_history'] = [];
    }
    $order['complaint']['funds_history'][] = [
        'from' => $currentAction ?: 'frozen',
        'to' => $targetAction,
        'amount' => $amount,
        'created_at' => time()
    ];
    $order['complaint']['funds_action'] = $targetAction;
    $order['complaint']['funds_settled'] = true;
    $order['complaint']['funds_settled_at'] = time();
    $order['complaint']['funds_amount'] = $amount;
    return [true, $message];
}

function adminTestEmailPayload() {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        adminJsonResponse(['success' => false, 'message' => '请输入有效的测试收件邮箱'], 400);
    }
    return $email;
}

adminRequireAdmin();

switch ($action) {
    case 'dashboard':
        adminJsonResponse(['success' => true, 'dashboard' => adminDashboardData()]);

    case 'users':
        $params = adminListParams();
        $users = array_map('adminSafeUser', $db->getTable('users'));
        usort($users, fn($a, $b) => intval($b['created_at'] ?? 0) - intval($a['created_at'] ?? 0));
        $users = adminFilterUsers($users, $params);
        $result = adminPaginateArray($users, $params['page'], $params['pageSize']);
        adminJsonResponse([
            'success' => true,
            'users' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['pageSize'],
            'levels' => $db->getMembershipLevels(),
        ]);

    case 'products':
        $params = adminListParams();
        $products = array_values(array_filter(array_map('adminSafeProduct', $db->getTable('products'))));
        usort($products, fn($a, $b) => intval($b['created_at'] ?? 0) - intval($a['created_at'] ?? 0));
        $products = adminFilterProducts($products, $params);
        $result = adminPaginateArray($products, $params['page'], $params['pageSize']);
        adminJsonResponse([
            'success' => true,
            'products' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['pageSize'],
        ]);

    case 'finance_requests':
        adminJsonResponse(['success' => true, 'requests' => adminFinanceRequests()]);

    case 'cards':
        adminJsonResponse(['success' => true, 'cards' => adminCardItems()]);

    case 'payment_configs':
        adminJsonResponse([
            'success' => true,
            'configs' => array_values($db->getPaymentConfigs()),
            'notify_url' => adminBaseUrl() . '/api/payment.php?action=notify',
            'return_url' => adminBaseUrl() . '/',
        ]);

    case 'system_config':
        $config = $db->getSystemConfig();
        $config = KeyNestMailer::stripProfileSecrets($config);
        unset($config['smtp_password'], $config['resend_api_key'], $config['captcha_secret_key'], $config['oauth_qq_app_key'], $config['oauth_wechat_app_secret'], $config['oauth_caihong_key']);
        adminJsonResponse(['success' => true, 'config' => $config]);

    case 'user_balance_details':
        $id = trim($_GET['id'] ?? $_POST['id'] ?? '');
        if ($id === '') {
            adminJsonResponse(['success' => false, 'message' => '缺少用户ID'], 400);
        }
        $details = adminUserBalanceDetails($id);
        if (!$details) {
            adminJsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        adminJsonResponse(['success' => true, 'details' => $details]);

    case 'update_user':
        $payload = adminUserPayload();
        $target = $db->getUserById($payload['id']);
        if (!$target) {
            adminJsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        if (($target['username'] ?? '') === 'admin') {
            $payload['role'] = 'admin';
            $payload['username'] = 'admin';
        }
        $updates = $payload;
        unset($updates['id']);
        if (!$db->updateUser($payload['id'], $updates)) {
            adminJsonResponse(['success' => false, 'message' => '保存失败：用户名可能重复或字段格式错误'], 400);
        }
        $oldBalance = floatval($target['balance'] ?? 0);
        $newBalance = floatval($updates['balance'] ?? $oldBalance);
        $diff = $newBalance - $oldBalance;
        if (abs($diff) >= 0.01) {
            $adminUser = $db->getUserById($_SESSION['user_id'] ?? '');
            $db->createPaymentOrder([
                'trade_no' => 'ADJ' . date('YmdHis') . rand(1000, 9999),
                'user_id' => $payload['id'],
                'payment_config_id' => 'admin',
                'pay_type' => 'admin_adjust',
                'amount' => $diff,
                'actual_amount' => $diff,
                'fee' => 0,
                'status' => 'paid',
                'type' => 'admin_balance_adjust',
                'title' => '后台余额调整',
                'description' => '管理员 ' . ($adminUser['username'] ?? 'admin') . ' 将余额从 ¥' . number_format($oldBalance, 2) . ' 调整为 ¥' . number_format($newBalance, 2),
                'paid_at' => time()
            ]);
        }
        adminJsonResponse(['success' => true, 'message' => isset($updates['password']) ? '用户信息已更新，登录密码已重置' : '用户信息已更新']);

    case 'reset_user_payment_methods':
        $id = trim($_POST['id'] ?? '');
        $target = $db->getUserById($id);
        if (!$target) {
            adminJsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        if (($target['role'] ?? '') === 'admin') {
            adminJsonResponse(['success' => false, 'message' => '管理员账号不允许重置收款方式'], 400);
        }
        if (!$db->updateUser($id, ['payment_methods' => [], 'merchant_status' => 'none', 'merchant_rules_accepted' => false, 'merchant_rules_accepted_at' => 0])) {
            adminJsonResponse(['success' => false, 'message' => '重置收款方式失败'], 500);
        }
        adminJsonResponse(['success' => true, 'message' => '已清空该用户收款方式，用户可重新配置']);

    case 'delete_user':
        $id = trim($_POST['id'] ?? '');
        $target = $db->getUserById($id);
        if (!$target) {
            adminJsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        if (($target['username'] ?? '') === 'admin' || ($target['role'] ?? '') === 'admin') {
            adminJsonResponse(['success' => false, 'message' => 'admin 管理员禁止删除'], 400);
        }
        if (!$db->deleteUser($id)) {
            adminJsonResponse(['success' => false, 'message' => '删除失败'], 400);
        }
        adminJsonResponse(['success' => true, 'message' => '用户已删除']);

    case 'review_merchant':
        $id = trim($_POST['id'] ?? '');
        $decision = trim($_POST['decision'] ?? '');
        $target = $db->getUserById($id);
        if (!$target) {
            adminJsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        if (($target['merchant_status'] ?? 'none') !== 'pending') {
            adminJsonResponse(['success' => false, 'message' => '该用户没有待审核的商家重新开通申请'], 400);
        }
        $updates = $decision === 'approve'
            ? ['merchant_status' => 'approved', 'merchant_approved_at' => time(), 'merchant_opened_once' => true]
            : ['merchant_status' => 'rejected'];
        if (!$db->updateUser($id, $updates)) {
            adminJsonResponse(['success' => false, 'message' => '商家审核处理失败'], 500);
        }
        adminJsonResponse(['success' => true, 'message' => $decision === 'approve' ? '已通过商家重新开通申请' : '已拒绝商家重新开通申请']);

    case 'subdomains':
        adminRequireAdmin();
        $page = max(1, intval($_GET['page'] ?? $_POST['page'] ?? 1));
        $pageSize = max(10, min(200, intval($_GET['page_size'] ?? $_POST['page_size'] ?? 20)));
        $keyword = trim((string)($_GET['keyword'] ?? $_POST['keyword'] ?? ''));
        $status = trim((string)($_GET['status'] ?? $_POST['status'] ?? ''));
        if ($status === 'all') {
            $status = '';
        }
        $result = $db->listSellerSubdomains($page, $pageSize, $keyword, $status);
        $config = $db->getSystemConfig();
        $items = array_map(function($item) use ($config) {
            $baseDomain = SubdomainHelper::resolveBaseDomainChoice($config, $item['base_domain'] ?? '');
            $item['full_domain'] = $baseDomain !== '' ? SubdomainHelper::fullHost($item['prefix'] ?? '', $baseDomain) : '';
            $item['is_expired'] = SubdomainHelper::isExpired($item);
            $item['is_active'] = SubdomainHelper::isActive($item);
            return $item;
        }, $result['items']);
        adminJsonResponse([
            'success' => true,
            'subdomains' => $items,
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['pageSize'],
        ]);

    case 'create_subdomain':
        $admin = adminRequireAdmin();
        $userId = trim($_POST['user_id'] ?? '');
        $prefix = strtolower(trim($_POST['prefix'] ?? ''));
        $months = max(1, min(36, intval($_POST['months'] ?? 1)));
        $autoApprove = !isset($_POST['auto_approve']) || filter_var($_POST['auto_approve'], FILTER_VALIDATE_BOOLEAN);
        $user = $db->getUserById($userId);
        if (!$user) {
            adminJsonResponse(['success' => false, 'message' => '用户不存在'], 404);
        }
        $error = SubdomainHelper::validatePrefix($prefix);
        if ($error) {
            adminJsonResponse(['success' => false, 'message' => $error], 400);
        }
        if ($db->getSellerSubdomainByPrefix($prefix)) {
            adminJsonResponse(['success' => false, 'message' => '该前缀已被占用'], 400);
        }
        if ($db->getSellerSubdomainByUserId($userId)) {
            adminJsonResponse(['success' => false, 'message' => '该用户已有二级域名记录'], 400);
        }
        $now = time();
        $config = $db->getSystemConfig();
        $baseDomain = SubdomainHelper::resolveBaseDomainChoice($config, $_POST['base_domain'] ?? '');
        $subdomain = [
            'user_id' => $userId,
            'prefix' => $prefix,
            'base_domain' => $baseDomain,
            'status' => $autoApprove ? 'approved' : 'pending',
            'pending_months' => $autoApprove ? 0 : $months,
            'expires_at' => $autoApprove ? ($now + SubdomainHelper::monthSeconds($months)) : 0,
            'approved_at' => $autoApprove ? $now : 0,
            'reviewed_at' => $autoApprove ? $now : 0,
            'reviewed_by' => $autoApprove ? ($admin['id'] ?? '') : '',
            'last_price_paid' => 0,
            'disabled' => false,
            'created_at' => $now,
        ];
        if (!$db->saveSellerSubdomain($subdomain)) {
            adminJsonResponse(['success' => false, 'message' => '创建失败'], 500);
        }
        adminJsonResponse(['success' => true, 'message' => $autoApprove ? '二级域名已创建并生效' : '二级域名已创建，待审核']);

    case 'review_subdomain':
        $admin = adminRequireAdmin();
        $id = trim($_POST['id'] ?? '');
        $decision = trim($_POST['decision'] ?? '');
        $subdomain = $db->getSellerSubdomainById($id);
        if (!$subdomain) {
            adminJsonResponse(['success' => false, 'message' => '二级域名记录不存在'], 404);
        }
        $isNewPending = ($subdomain['status'] ?? '') === 'pending';
        $isRenewalPending = SubdomainHelper::hasRenewalPending($subdomain);
        if (!$isNewPending && !$isRenewalPending) {
            adminJsonResponse(['success' => false, 'message' => '该记录不在待审核状态'], 400);
        }
        if ($decision === 'approve') {
            $months = max(1, intval($subdomain['pending_months'] ?? 1));
            $baseExpires = max(intval($subdomain['expires_at'] ?? 0), time());
            $subdomain['expires_at'] = $baseExpires + SubdomainHelper::monthSeconds($months);
            $subdomain['status'] = 'approved';
            $subdomain['pending_months'] = 0;
            $subdomain['approved_at'] = time();
            $subdomain['disabled'] = false;
        } elseif ($isRenewalPending) {
            $subdomain['pending_months'] = 0;
        } else {
            $subdomain['status'] = 'rejected';
            $subdomain['pending_months'] = 0;
        }
        $subdomain['reviewed_at'] = time();
        $subdomain['reviewed_by'] = $admin['id'] ?? '';
        if (!$db->saveSellerSubdomain($subdomain)) {
            adminJsonResponse(['success' => false, 'message' => '审核处理失败'], 500);
        }
        if ($decision === 'approve') {
            $message = $isRenewalPending ? '已通过二级域名续费审核' : '已通过二级域名审核';
        } elseif ($isRenewalPending) {
            $message = '已拒绝二级域名续费申请';
        } else {
            $message = '已拒绝二级域名申请';
        }
        adminJsonResponse(['success' => true, 'message' => $message]);

    case 'update_subdomain':
        $admin = adminRequireAdmin();
        $id = trim($_POST['id'] ?? '');
        $subdomain = $db->getSellerSubdomainById($id);
        if (!$subdomain) {
            adminJsonResponse(['success' => false, 'message' => '二级域名记录不存在'], 404);
        }
        if (isset($_POST['prefix'])) {
            $prefix = strtolower(trim((string)$_POST['prefix']));
            $error = SubdomainHelper::validatePrefix($prefix);
            if ($error) {
                adminJsonResponse(['success' => false, 'message' => $error], 400);
            }
            $other = $db->getSellerSubdomainByPrefix($prefix);
            if ($other && ($other['id'] ?? '') !== $id) {
                adminJsonResponse(['success' => false, 'message' => '该前缀已被其他卖家占用'], 400);
            }
            $subdomain['prefix'] = $prefix;
        }
        if (isset($_POST['expires_at'])) {
            $subdomain['expires_at'] = max(0, intval($_POST['expires_at']));
            if ($subdomain['expires_at'] > 0 && ($subdomain['status'] ?? '') === 'pending') {
                $subdomain['status'] = 'approved';
                $subdomain['pending_months'] = 0;
                $subdomain['approved_at'] = time();
                $subdomain['disabled'] = false;
            }
        }
        if (isset($_POST['disabled'])) {
            $subdomain['disabled'] = filter_var($_POST['disabled'], FILTER_VALIDATE_BOOLEAN);
            if ($subdomain['disabled']) {
                $subdomain['status'] = 'disabled';
            } elseif (($subdomain['status'] ?? '') === 'disabled') {
                $subdomain['status'] = 'approved';
            }
        }
        if (isset($_POST['status'])) {
            $status = trim((string)$_POST['status']);
            if (in_array($status, ['pending', 'approved', 'rejected', 'disabled'], true)) {
                $subdomain['status'] = $status;
                $subdomain['disabled'] = $status === 'disabled';
            }
        }
        $subdomain['reviewed_at'] = time();
        $subdomain['reviewed_by'] = $admin['id'] ?? '';
        if (!$db->saveSellerSubdomain($subdomain)) {
            adminJsonResponse(['success' => false, 'message' => '更新失败'], 500);
        }
        adminJsonResponse(['success' => true, 'message' => '二级域名信息已更新']);

    case 'delete_subdomain':
        adminRequireAdmin();
        $id = trim($_POST['id'] ?? '');
        $subdomain = $db->getSellerSubdomainById($id);
        if (!$subdomain) {
            adminJsonResponse(['success' => false, 'message' => '二级域名记录不存在'], 404);
        }
        if (!$db->deleteSellerSubdomain($id)) {
            adminJsonResponse(['success' => false, 'message' => '删除失败'], 500);
        }
        adminJsonResponse(['success' => true, 'message' => '二级域名已删除']);

    case 'product_stock':
        $id = trim($_GET['id'] ?? $_POST['id'] ?? '');
        if ($id === '') {
            adminJsonResponse(['success' => false, 'message' => '缺少商品ID'], 400);
        }
        $product = $db->getProductById($id);
        if (!$product) {
            adminJsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        $accounts = is_array($product['account_list'] ?? null) ? $product['account_list'] : [];
        $stockItems = [];
        foreach ($accounts as $index => $account) {
            if (!is_array($account)) continue;
            $stockItems[] = [
                'index' => $index,
                'email' => $account['email'] ?? '',
                'password' => $account['password'] ?? '',
                'client_id' => $account['client_id'] ?? '',
                'fresh_token' => $account['fresh_token'] ?? '',
                'content' => $account['content'] ?? '',
                'format' => $account['format'] ?? '',
                'sold' => !empty($account['sold'])
            ];
        }
        adminJsonResponse([
            'success' => true,
            'product' => [
                'id' => $product['id'] ?? '',
                'title' => $product['title'] ?? '',
                'stock' => intval($product['stock'] ?? 0),
                'sales' => intval($product['sales'] ?? 0)
            ],
            'items' => $stockItems
        ]);

    case 'delete_product_stock':
        $id = trim($_POST['id'] ?? '');
        $index = $_POST['index'] ?? null;
        if ($id === '' || $index === null || !is_numeric($index)) {
            adminJsonResponse(['success' => false, 'message' => '缺少商品ID或库存序号'], 400);
        }
        $index = intval($index);
        $product = $db->getProductById($id);
        if (!$product) {
            adminJsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        $accounts = is_array($product['account_list'] ?? null) ? $product['account_list'] : [];
        if (!array_key_exists($index, $accounts) || !is_array($accounts[$index])) {
            adminJsonResponse(['success' => false, 'message' => '库存不存在或已被删除'], 404);
        }
        if (!empty($accounts[$index]['sold'])) {
            adminJsonResponse(['success' => false, 'message' => '已售库存不能删除，避免影响已成交订单'], 400);
        }
        array_splice($accounts, $index, 1);
        $product['account_list'] = array_values($accounts);
        $product['stock'] = count(array_filter($product['account_list'], fn($item) => is_array($item) && empty($item['sold'])));
        $product['updated_at'] = time();
        if (!$db->updateProduct($product)) {
            adminJsonResponse(['success' => false, 'message' => '删除库存失败'], 500);
        }
        adminJsonResponse(['success' => true, 'message' => '库存已删除', 'stock' => $product['stock']]);

    case 'delete_product':
        $id = trim($_POST['id'] ?? '');
        if ($id === '') {
            adminJsonResponse(['success' => false, 'message' => '缺少商品ID'], 400);
        }
        if (!$db->getProductById($id)) {
            adminJsonResponse(['success' => false, 'message' => '商品不存在'], 404);
        }
        if (!$db->deleteProduct($id)) {
            adminJsonResponse(['success' => false, 'message' => '删除失败'], 400);
        }
        adminJsonResponse(['success' => true, 'message' => '商品已删除']);

    case 'delete_products':
        $idsJson = $_POST['ids'] ?? '[]';
        $ids = json_decode($idsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($ids)) {
            adminJsonResponse(['success' => false, 'message' => '商品ID列表格式错误'], 400);
        }
        $ids = array_values(array_unique(array_filter(array_map('trim', $ids), fn($id) => $id !== '')));
        if (empty($ids)) {
            adminJsonResponse(['success' => false, 'message' => '请选择要删除的商品'], 400);
        }
        $deleted = 0;
        $missing = 0;
        foreach ($ids as $id) {
            if (!$db->getProductById($id)) {
                $missing++;
                continue;
            }
            if ($db->deleteProduct($id)) {
                $deleted++;
            }
        }
        if ($deleted === 0) {
            adminJsonResponse(['success' => false, 'message' => $missing > 0 ? '所选商品不存在或已被删除' : '删除失败'], 400);
        }
        adminJsonResponse(['success' => true, 'message' => '已删除 ' . $deleted . ' 个商品', 'deleted' => $deleted, 'missing' => $missing]);

    case 'comments':
        $params = adminListParams();
        $comments = adminFilterComments(adminCommentItems(), $params);
        $result = adminPaginateArray($comments, $params['page'], $params['pageSize']);
        adminJsonResponse([
            'success' => true,
            'comments' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['pageSize'],
        ]);

    case 'delete_comment':
        $id = trim($_POST['id'] ?? '');
        if ($id === '') {
            adminJsonResponse(['success' => false, 'message' => '缺少评价ID'], 400);
        }
        $exists = false;
        foreach ($db->getComments() as $comment) {
            if (($comment['id'] ?? '') === $id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            adminJsonResponse(['success' => false, 'message' => '评价不存在或已被删除'], 404);
        }
        if (!$db->deleteComment($id)) {
            adminJsonResponse(['success' => false, 'message' => '删除评价失败'], 500);
        }
        adminJsonResponse(['success' => true, 'message' => '评价已删除']);

    case 'complaints':
        $params = adminListParams();
        $allComplaints = adminComplaintOrders();
        $summary = adminComplaintSummary($allComplaints);
        $complaints = adminFilterComplaints($allComplaints, $params);
        $result = adminPaginateArray($complaints, $params['page'], $params['pageSize']);
        adminJsonResponse([
            'success' => true,
            'complaints' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['pageSize'],
            'summary' => $summary,
        ]);

    case 'get_complaint':
        $id = trim($_GET['order_id'] ?? $_POST['order_id'] ?? '');
        $order = $db->getOrderById($id);
        if (!$order || empty($order['complaint'])) {
            adminJsonResponse(['success' => false, 'message' => '投诉不存在'], 404);
        }
        adminJsonResponse(['success' => true, 'order' => adminSafeComplaintOrder($order)]);

    case 'reply_complaint':
        $id = trim($_POST['order_id'] ?? '');
        $reply = trim((string)($_POST['reply'] ?? ''));
        $order = $db->getOrderById($id);
        if (!$order || empty($order['complaint'])) {
            adminJsonResponse(['success' => false, 'message' => '投诉不存在'], 404);
        }
        if ($reply === '' || mb_strlen($reply) > 800) {
            adminJsonResponse(['success' => false, 'message' => '请填写回复内容，最多800字'], 400);
        }
        $adminUser = $db->getUserById($_SESSION['user_id'] ?? '');
        $safeReply = htmlspecialchars($reply, ENT_QUOTES, 'UTF-8');
        $replyItem = [
            'id' => bin2hex(random_bytes(8)),
            'content' => $safeReply,
            'username' => $adminUser['username'] ?? 'admin',
            'created_at' => time(),
        ];
        if (!isset($order['complaint']['admin_replies']) || !is_array($order['complaint']['admin_replies'])) {
            $order['complaint']['admin_replies'] = [];
            if (!empty($order['complaint']['admin_reply'])) {
                $order['complaint']['admin_replies'][] = [
                    'id' => bin2hex(random_bytes(8)),
                    'content' => (string)$order['complaint']['admin_reply'],
                    'username' => $order['complaint']['admin_replied_by'] ?? 'admin',
                    'created_at' => intval($order['complaint']['admin_replied_at'] ?? $order['complaint']['updated_at'] ?? time()),
                ];
            }
        }
        $order['complaint']['admin_replies'][] = $replyItem;
        $order['complaint']['admin_reply'] = $safeReply;
        $order['complaint']['admin_replied_by'] = $adminUser['username'] ?? 'admin';
        $order['complaint']['admin_replied_at'] = $replyItem['created_at'];
        $order['complaint']['updated_at'] = time();
        $db->updateOrder($order);
        adminJsonResponse(['success' => true, 'message' => '管理员回复已保存']);

    case 'update_complaint_status':
        $id = trim($_POST['order_id'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $allowed = ['open', 'processing', 'resolved', 'rejected'];
        $order = $db->getOrderById($id);
        if (!$order || empty($order['complaint'])) {
            adminJsonResponse(['success' => false, 'message' => '投诉不存在'], 404);
        }
        if (!in_array($status, $allowed, true)) {
            adminJsonResponse(['success' => false, 'message' => '投诉状态无效，后台不能操作撤诉'], 400);
        }
        if (($order['complaint']['status'] ?? '') === 'withdrawn') {
            adminJsonResponse(['success' => false, 'message' => '该投诉已撤诉，不能修改状态'], 400);
        }
        $adminUser = $db->getUserById($_SESSION['user_id'] ?? '');
        [$fundsOk, $fundsMessage] = adminResolveComplaintFunds($order, $status);
        if (!$fundsOk) {
            adminJsonResponse(['success' => false, 'message' => $fundsMessage ?: '冻结金额处理失败'], 500);
        }
        $order['complaint']['status'] = $status;
        $order['complaint']['admin_status_by'] = $adminUser['username'] ?? 'admin';
        $order['complaint']['admin_status_at'] = time();
        $order['complaint']['updated_at'] = time();
        $db->updateOrder($order);
        adminJsonResponse(['success' => true, 'message' => $fundsMessage ?: '投诉状态已更新']);

    case 'stats':
        $users = $db->getTable('users');
        $products = $db->getTable('products');
        $orders = $db->getTable('orders');
        $paymentOrders = $db->getTable('payment_orders');
        $depositRequests = $db->getTable('deposit_requests');
        $withdrawRequests = $db->getTable('withdraw_requests');
        $pendingRequests = array_filter(array_merge($depositRequests, $withdrawRequests), fn($r) => ($r['status'] ?? '') === 'pending');

        adminJsonResponse([
            'success' => true,
            'stats' => [
                'users' => count($users),
                'products' => count($products),
                'orders' => count($orders),
                'payment_orders' => count($paymentOrders),
                'pending_requests' => count($pendingRequests),
            ]
        ]);

    case 'logs':
        $type = $_GET['type'] ?? 'api';
        $date = $_GET['date'] ?? date('Y-m-d');
        $lines = max(50, min(1000, intval($_GET['lines'] ?? 300)));
        $file = adminLogFilePath($type, $date);
        if (!$file) {
            adminJsonResponse(['success' => false, 'message' => '日志类型或日期无效'], 400);
        }
        adminJsonResponse([
            'success' => true,
            'type' => $type,
            'date' => $date,
            'dates' => adminListLogDates($type),
            'exists' => is_file($file),
            'size' => is_file($file) ? filesize($file) : 0,
            'content' => adminReadLastLines($file, $lines)
        ]);

    case 'clear_logs':
        $count = adminClearAllLogs();
        adminJsonResponse(['success' => true, 'message' => '已清空全部日志文件，共 ' . $count . ' 个', 'count' => $count]);

    case 'membership_levels':
        adminJsonResponse(['success' => true, 'levels' => $db->getMembershipLevels()]);

    case 'save_membership_levels':
        $levels = adminMembershipPayload();
        if (!$db->updateMembershipLevels($levels)) {
            adminJsonResponse(['success' => false, 'message' => '保存会员等级失败，请至少保留一个有效等级'], 400);
        }
        adminJsonResponse(['success' => true, 'message' => '会员等级已保存', 'levels' => $db->getMembershipLevels()]);

    case 'delete_membership_level':
        $name = trim($_POST['name'] ?? '');
        if (!$db->deleteMembershipLevel($name)) {
            adminJsonResponse(['success' => false, 'message' => '删除失败：不能删除 Free 或已有用户正在使用的等级'], 400);
        }
        adminJsonResponse(['success' => true, 'message' => '会员等级已删除', 'levels' => $db->getMembershipLevels()]);

    case 'update_status':
        try {
            adminJsonResponse(['success' => true, 'status' => adminUpdateStatus()]);
        } catch (Throwable $e) {
            adminJsonResponse(['success' => false, 'message' => '更新检测失败：' . $e->getMessage(), 'output' => $e->getFile() . ':' . $e->getLine()], 500);
        }

    case 'run_update':
        try {
            adminJsonResponse(adminApplyUpdate());
        } catch (Throwable $e) {
            adminJsonResponse(['success' => false, 'message' => '自动更新失败：' . $e->getMessage(), 'output' => $e->getFile() . ':' . $e->getLine()], 500);
        }

    case 'test_email':
        $to = adminTestEmailPayload();
        $config = $db->getSystemConfig();
        $siteName = $config['site_name'] ?? 'KeyNest';
        $code = (string)random_int(100000, 999999);
        $subject = $siteName . ' 邮箱验证码测试';
        $html = KeyNestMailer::renderTemplate($config, [
            'site_name' => $siteName,
            'title' => '邮箱验证码测试',
            'message' => '如果你收到这封邮件，说明邮箱发送配置已经成功。',
            'code' => $code,
            'ttl' => max(1, min(60, intval($config['email_code_ttl'] ?? 10))),
            'footer' => '这是一封后台测试邮件，不会用于真实注册验证。',
            'time' => date('Y-m-d H:i:s')
        ]);
        $result = KeyNestMailer::sendAndLog($to, $subject, $html, $config, [
            'profile_id' => trim((string)($_POST['profile_id'] ?? '')),
        ]);
        if (!empty($result['success'])) {
            $result['message'] = ($result['message'] ?? '测试邮件已发送') . '；测试验证码：' . $code;
            $result['test_code'] = $code;
        }
        adminJsonResponse($result);

    default:
        adminJsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
