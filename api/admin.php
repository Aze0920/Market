<?php
/**
 * 管理员后台专用 API
 */
require_once __DIR__ . '/index.php';
require_once __DIR__ . '/../core/Database.php';

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
        'commit' => $commit,
        'branch' => $config['branch'],
        'repo_url' => $config['repo_url'],
        'updated_at' => time(),
        'updated_at_text' => date('Y-m-d H:i:s'),
    ];
    file_put_contents($config['version_file'], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function adminGitRemoteUrl($config) {
    if (!is_file($config['token_file'])) {
        return $config['repo_url'];
    }
    $token = trim(file_get_contents($config['token_file']));
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

function adminPathIsPreserved($relative, $preserve) {
    $relative = str_replace('\\', '/', trim($relative, '/'));
    foreach ($preserve as $skip) {
        if ($relative === $skip || str_starts_with($relative, $skip . '/')) {
            return true;
        }
    }
    return false;
}

function adminCopyDirectory($source, $target, $rootSource = null) {
    $rootSource = $rootSource ?: $source;
    $preserve = ['.git', 'config/database.php', 'data', 'logs', 'data/install.lock', 'data/update_version.json'];
    if (!is_dir($target)) {
        mkdir($target, 0755, true);
    }
    foreach (scandir($source) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $src = $source . DIRECTORY_SEPARATOR . $item;
        $dst = $target . DIRECTORY_SEPARATOR . $item;
        $relative = str_replace('\\', '/', ltrim(substr($src, strlen($rootSource)), DIRECTORY_SEPARATOR));
        if (adminPathIsPreserved($relative, $preserve)) {
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
    $reset = adminRunCommand(adminGitCommand('reset --hard origin/' . escapeshellarg($config['branch']), $config), $config['work_dir']);
    if ($reset['code'] !== 0) {
        adminJsonResponse(['success' => false, 'message' => '更新工作目录失败', 'output' => $reset['output']], 500);
    }
    adminCopyDirectory($config['work_dir'], $config['site_dir']);
    adminEnsureInstallLock($config);
    $updatedCommit = adminRunCommand(adminGitCommand('rev-parse HEAD', $config), $config['work_dir']);
    if ($updatedCommit['code'] === 0 && trim($updatedCommit['output']) !== '') {
        adminSaveUpdaterVersion($config, trim($updatedCommit['output']));
    }
    return ['success' => true, 'message' => '更新完成', 'status' => adminUpdateStatus(), 'output' => $fetch['output'] . "\n" . $reset['output']];
}

adminRequireAdmin();

switch ($action) {
    case 'users':
        $users = array_map('adminSafeUser', $db->getTable('users'));
        usort($users, fn($a, $b) => ($b['created_at'] ?? 0) - ($a['created_at'] ?? 0));
        adminJsonResponse(['success' => true, 'users' => array_values($users)]);

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
        adminJsonResponse(['success' => true, 'status' => adminUpdateStatus()]);

    case 'run_update':
        adminJsonResponse(adminApplyUpdate());

    default:
        adminJsonResponse(['success' => false, 'message' => '未知操作'], 400);
}
