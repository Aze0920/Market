<?php
require_once dirname(__DIR__) . '/config/install.php';

error_reporting(0);
ini_set('display_errors', 0);

$installed = keynest_is_installed();
$message = '';
$messageType = 'danger';
$success = false;

function installer_value($key, $default = '') {
    return htmlspecialchars($_POST[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}

function installer_write_config($config) {
    $content = "<?php\n/**\n * 数据库配置\n * 本文件由安装程序生成。\n */\nreturn " . var_export($config, true) . ";\n";
    return file_put_contents(dirname(__DIR__) . '/config/database.php', $content, LOCK_EX) !== false;
}

function installer_create_lock() {
    $dataPath = dirname(__DIR__) . '/data';
    if (!is_dir($dataPath)) {
        mkdir($dataPath, 0755, true);
    }
    return file_put_contents($dataPath . '/install.lock', 'installed_at=' . date('c') . PHP_EOL, LOCK_EX) !== false;
}

function installer_init_database($config, $adminUser, $adminPassword, $adminEmail) {
    $charset = $config['charset'];
    $dsnWithoutDb = "mysql:host={$config['host']};port={$config['port']};charset={$charset}";
    $pdo = new PDO($dsnWithoutDb, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $dbName = str_replace('`', '``', $config['database']);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kn_records` (
        `table_name` varchar(64) NOT NULL,
        `record_id` varchar(80) NOT NULL,
        `username` varchar(80) DEFAULT NULL,
        `data` longtext NOT NULL,
        `created_at` int unsigned NOT NULL DEFAULT 0,
        `updated_at` int unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (`table_name`, `record_id`),
        KEY `idx_table_username` (`table_name`, `username`),
        KEY `idx_table_created` (`table_name`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM kn_records WHERE table_name = ?');
    $stmt->execute(['users']);
    $hasUsers = (int)$stmt->fetch()['total'] > 0;

    if (!$hasUsers) {
        $now = time();
        $admin = [
            'id' => 'admin_' . $now,
            'username' => $adminUser,
            'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
            'email' => $adminEmail,
            'balance' => 0,
            'role' => 'admin',
            'membership_level' => 'Infinite',
            'created_at' => $now,
            'last_login' => $now
        ];
        $insert = $pdo->prepare('INSERT INTO kn_records (table_name, record_id, username, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute(['users', $admin['id'], $admin['username'], json_encode($admin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now, $now]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $host = trim($_POST['host'] ?? '127.0.0.1');
    $port = (int)($_POST['port'] ?? 3306);
    $database = trim($_POST['database'] ?? 'keynest');
    $username = trim($_POST['username'] ?? 'root');
    $password = (string)($_POST['password'] ?? '');
    $adminUser = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['admin_user'] ?? 'admin');
    $adminPassword = (string)($_POST['admin_password'] ?? '');
    $adminEmail = filter_var(trim($_POST['admin_email'] ?? 'admin@keynest.local'), FILTER_SANITIZE_EMAIL);

    if ($host === '' || $database === '' || $username === '' || $port <= 0) {
        $message = '请填写完整的数据库连接信息。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
        $message = '数据库名只能包含字母、数字和下划线。';
    } elseif (strlen($adminUser) < 3 || strlen($adminUser) > 20) {
        $message = '管理员用户名需为 3-20 位字母、数字或下划线。';
    } elseif (strlen($adminPassword) < 8) {
        $message = '管理员密码至少 8 位。';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $message = '请填写正确的管理员邮箱。';
    } else {
        $config = [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
        ];

        try {
            installer_init_database($config, $adminUser, $adminPassword, $adminEmail);
            if (!installer_write_config($config)) {
                throw new RuntimeException('无法写入 config/database.php，请检查目录权限。');
            }
            if (!installer_create_lock()) {
                throw new RuntimeException('无法写入 data/install.lock，请检查目录权限。');
            }
            $success = true;
            $messageType = 'success';
            $message = '安装完成！现在可以进入网站并使用管理员账号登录。';
            $installed = true;
            // 安装完成后自动删除安装脚本，防止被重复访问
            $selfFile = __FILE__;
            if (is_writable($selfFile)) {
                @unlink($selfFile);
            }
        } catch (Throwable $e) {
            $message = '安装失败：' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KeyNest 安装向导</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
            --dark: #111827;
            --muted: #6b7280;
            --glass: rgba(255, 255, 255, .82);
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--dark);
            font-family: Inter, "Segoe UI", "Microsoft YaHei", system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(circle at 10% 10%, rgba(99,102,241,.30), transparent 28%),
                radial-gradient(circle at 90% 20%, rgba(14,165,233,.24), transparent 30%),
                radial-gradient(circle at 50% 100%, rgba(139,92,246,.28), transparent 32%),
                linear-gradient(135deg, #eef2ff 0%, #f8fafc 45%, #f5f3ff 100%);
            overflow-x: hidden;
        }
        .page-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 42px 16px;
            position: relative;
        }
        .orb {
            position: absolute;
            border-radius: 999px;
            filter: blur(6px);
            opacity: .55;
            animation: float 7s ease-in-out infinite;
        }
        .orb.one { width: 160px; height: 160px; left: 6%; top: 12%; background: #a5b4fc; }
        .orb.two { width: 120px; height: 120px; right: 8%; bottom: 12%; background: #c4b5fd; animation-delay: -2s; }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-18px); } }
        .install-card {
            width: min(1120px, 100%);
            display: grid;
            grid-template-columns: .88fr 1.12fr;
            background: var(--glass);
            border: 1px solid rgba(255,255,255,.72);
            border-radius: 32px;
            box-shadow: 0 28px 80px rgba(31,41,55,.18);
            overflow: hidden;
            backdrop-filter: blur(18px);
            position: relative;
            z-index: 1;
        }
        .hero-panel {
            padding: 48px;
            background: linear-gradient(145deg, rgba(79,70,229,.95), rgba(124,58,237,.92));
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .hero-panel::after {
            content: "";
            position: absolute;
            inset: auto -80px -100px auto;
            width: 260px;
            height: 260px;
            border-radius: 999px;
            background: rgba(255,255,255,.13);
        }
        .brand-badge {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.24);
            font-size: 28px;
            margin-bottom: 28px;
        }
        .hero-panel h1 { font-weight: 800; letter-spacing: -.04em; margin-bottom: 16px; }
        .hero-panel p { color: rgba(255,255,255,.82); font-size: 1.05rem; line-height: 1.8; }
        .feature-list { margin-top: 34px; display: grid; gap: 16px; }
        .feature-item { display: flex; gap: 12px; align-items: center; color: rgba(255,255,255,.9); }
        .feature-item i { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 12px; background: rgba(255,255,255,.14); }
        .form-panel { padding: 42px; }
        .section-title { font-weight: 800; letter-spacing: -.03em; margin-bottom: 6px; }
        .section-subtitle { color: var(--muted); margin-bottom: 28px; }
        .form-label { font-weight: 700; color: #374151; }
        .form-control, .input-group-text {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px 14px;
            background: rgba(255,255,255,.88);
        }
        .input-group .input-group-text { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .input-group .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .22rem rgba(99,102,241,.14);
        }
        .step-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            margin: 26px 0 16px;
        }
        .step-title span {
            width: 30px;
            height: 30px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            font-size: .9rem;
        }
        .btn-install {
            border: 0;
            width: 100%;
            padding: 14px 18px;
            border-radius: 16px;
            color: #fff;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 16px 34px rgba(99,102,241,.28);
        }
        .btn-install:hover { color: #fff; transform: translateY(-1px); }
        .done-box {
            min-height: 100%;
            display: grid;
            place-items: center;
            text-align: center;
            padding: 52px 24px;
        }
        .done-icon {
            width: 92px;
            height: 92px;
            border-radius: 28px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #22c55e, #10b981);
            color: #fff;
            font-size: 46px;
            margin: 0 auto 24px;
            box-shadow: 0 18px 42px rgba(16,185,129,.28);
        }
        .tips {
            background: #f8fafc;
            border: 1px solid #eef2f7;
            border-radius: 18px;
            padding: 16px;
            color: #64748b;
            font-size: .92rem;
        }
        @media (max-width: 900px) {
            .install-card { grid-template-columns: 1fr; }
            .hero-panel, .form-panel { padding: 32px 24px; }
        }
    </style>
</head>
<body>
<div class="page-shell">
    <div class="orb one"></div>
    <div class="orb two"></div>
    <main class="install-card">
        <section class="hero-panel">
            <div class="brand-badge"><i class="bi bi-key-fill"></i></div>
            <h1>KeyNest 安装向导</h1>
            <p>只需填写数据库和管理员信息，系统会自动创建数据表并初始化后台账号。完成后即可开始使用虚拟商品交易平台。</p>
            <div class="feature-list">
                <div class="feature-item"><i class="bi bi-database-check"></i><span>自动创建 MySQL 数据库表</span></div>
                <div class="feature-item"><i class="bi bi-shield-lock"></i><span>管理员密码 bcrypt 安全加密</span></div>
                <div class="feature-item"><i class="bi bi-lightning-charge"></i><span>安装完成后立即进入网站</span></div>
            </div>
        </section>
        <section class="form-panel">
            <?php if ($installed): ?>
                <div class="done-box">
                    <div>
                        <div class="done-icon"><i class="bi bi-check2"></i></div>
                        <h2 class="section-title">系统已安装</h2>
                        <p class="section-subtitle"><?php echo $success ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : '检测到安装锁，当前系统已经完成安装。'; ?></p>
                        <a class="btn btn-install" href="/">进入网站</a>
                        <div class="tips mt-4">如果需要重新安装，请先手动删除 <code>data/install.lock</code>，并确认已备份数据库。</div>
                    </div>
                </div>
            <?php else: ?>
                <h2 class="section-title">开始安装</h2>
                <p class="section-subtitle">请填写 MySQL/MariaDB 连接信息和管理员账号。</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> rounded-4 border-0 shadow-sm"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <form method="post" autocomplete="off">
                    <div class="step-title"><span>1</span>数据库设置</div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">数据库主机</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                                <input class="form-control" name="host" value="<?php echo installer_value('host', '127.0.0.1'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">端口</label>
                            <input class="form-control" name="port" type="number" value="<?php echo installer_value('port', '3306'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">数据库名</label>
                            <input class="form-control" name="database" value="<?php echo installer_value('database', 'keynest'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">数据库用户名</label>
                            <input class="form-control" name="username" value="<?php echo installer_value('username', 'root'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">数据库密码</label>
                            <input class="form-control" name="password" type="password" value="<?php echo installer_value('password'); ?>" placeholder="没有密码可留空">
                        </div>
                    </div>

                    <div class="step-title"><span>2</span>管理员账号</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">管理员用户名</label>
                            <input class="form-control" name="admin_user" value="<?php echo installer_value('admin_user', 'admin'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">管理员邮箱</label>
                            <input class="form-control" name="admin_email" type="email" value="<?php echo installer_value('admin_email', 'admin@keynest.local'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">管理员密码</label>
                            <input class="form-control" name="admin_password" type="password" placeholder="至少 6 位，建议使用强密码" required>
                        </div>
                    </div>

                    <div class="tips mt-4">
                        安装程序会自动创建数据库和 <code>kn_records</code> 数据表。如果数据库用户没有创建数据库权限，请先在面板里手动创建数据库。
                    </div>

                    <button class="btn btn-install mt-4" type="submit">
                        <i class="bi bi-rocket-takeoff me-2"></i>立即安装
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
