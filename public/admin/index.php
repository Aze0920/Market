<?php
$rootPath = dirname(__DIR__);
if (!is_file($rootPath . '/config/install.php')) {
    $rootPath = dirname(__DIR__, 2);
}
$installPath = $rootPath . '/config/install.php';
require_once $installPath;
keynest_require_installed(false);
require_once $rootPath . '/core/Database.php';
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    session_start();
}
$adminGateUser = null;
$adminGateMessage = '';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir === '' || $scriptDir === '.') {
    $scriptDir = '';
}
if (substr($scriptDir, -6) === '/admin') {
    $apiBasePath = substr($scriptDir, 0, -6) . '/api/';
} else {
    $apiBasePath = $scriptDir . '/api/';
}
$apiBasePath = preg_replace('#/+#', '/', $apiBasePath);
if ($apiBasePath === '') {
    $apiBasePath = '/api/';
}
if (isset($_SESSION['user_id'])) {
    $adminGateUser = Database::getInstance()->getUserById($_SESSION['user_id']);
    if (!$adminGateUser || ($adminGateUser['role'] ?? '') !== 'admin') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $adminGateUser = null;
        $adminGateMessage = '当前账号不是管理员，请使用管理员账号登录。';
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KeyNest 管理后台</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary: #4f46e5;
            --primary2: #7c3aed;
            --bg: #f5f7fb;
            --dark: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        body { margin: 0; min-height: 100vh; background: var(--bg); color: var(--dark); font-family: "Segoe UI", "Microsoft YaHei", system-ui, sans-serif; }
        .hidden { display: none !important; }
        .admin-shell { min-height: 100vh; }
        .sidebar { background: #0f172a; color: #fff; padding: 26px 20px; position: fixed; left: 0; top: 0; bottom: 0; width: 280px; height: 100vh; overflow-y: auto; z-index: 1000; }
        .content { margin-left: 280px; min-width: 0; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .brand-icon { width: 46px; height: 46px; display: grid; place-items: center; border-radius: 16px; background: linear-gradient(135deg, var(--primary), var(--primary2)); box-shadow: 0 12px 26px rgba(79,70,229,.38); }
        .brand strong { display: block; font-size: 1.08rem; }
        .brand span { color: #94a3b8; font-size: .82rem; }
        .nav-title { color: #64748b; font-size: .75rem; letter-spacing: .08em; margin: 24px 10px 10px; }
        .side-link { display: flex; align-items: center; gap: 12px; width: 100%; border: 0; border-radius: 14px; color: #cbd5e1; background: transparent; padding: 12px 14px; text-align: left; margin-bottom: 6px; transition: .18s ease; }
        .side-link:hover, .side-link.active { color: #fff; background: rgba(255,255,255,.10); }
        .side-link.active { box-shadow: inset 3px 0 0 #818cf8; }
        .content { padding: 28px; overflow-x: hidden; }
        .topbar { display: flex; justify-content: space-between; align-items: center; gap: 18px; margin-bottom: 24px; }
        .topbar h1 { font-weight: 800; letter-spacing: -.04em; margin: 0; }
        .admin-profile-wrap { position: relative; }
        .user-pill { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff; border: 1px solid var(--border); border-radius: 999px; box-shadow: 0 10px 30px rgba(15,23,42,.06); cursor: pointer; transition: .18s ease; }
        .user-pill:hover, .user-pill.active { border-color: #c7d2fe; box-shadow: 0 14px 34px rgba(79,70,229,.12); }
        .profile-dropdown { position: absolute; top: calc(100% + 12px); right: 0; width: min(420px, calc(100vw - 40px)); background: #fff; border: 1px solid var(--border); border-radius: 24px; padding: 18px; box-shadow: 0 28px 80px rgba(15,23,42,.18); z-index: 2000; }
        .profile-dropdown::before { content: ''; position: absolute; top: -7px; right: 28px; width: 14px; height: 14px; background: #fff; border-left: 1px solid var(--border); border-top: 1px solid var(--border); transform: rotate(45deg); }
        .profile-dropdown-head { display: flex; align-items: center; gap: 12px; padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid var(--border); }
        .profile-status-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 0; color: #475569; }
        .profile-status-row strong { color: #111827; }
        .avatar { width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary2)); font-weight: 800; }
        .stat-card { background: #fff; border: 1px solid var(--border); border-radius: 22px; padding: 22px; box-shadow: 0 16px 40px rgba(15,23,42,.06); height: 100%; }
        .stat-icon { width: 46px; height: 46px; border-radius: 16px; display: grid; place-items: center; margin-bottom: 14px; font-size: 1.25rem; }
        .stat-value { font-size: 2rem; font-weight: 850; letter-spacing: -.04em; }
        .stat-label { color: var(--muted); }
        .panel { background: #fff; border: 1px solid var(--border); border-radius: 24px; padding: 22px; box-shadow: 0 16px 40px rgba(15,23,42,.06); }
        .panel-title { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .panel-title h5 { margin: 0; font-weight: 800; }
        .table { vertical-align: middle; }
        .table thead th { color: #64748b; font-size: .82rem; border-bottom: 1px solid var(--border); }
        .balance-detail-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid #bfdbfe; border-radius: 999px; padding: 5px 10px; background: #eff6ff; color: #1d4ed8; font-weight: 800; line-height: 1; transition: .16s ease; }
        .balance-detail-btn small { font-size: .72rem; font-weight: 800; color: #2563eb; }
        .balance-detail-btn:hover { border-color: #60a5fa; background: #dbeafe; color: #1e40af; box-shadow: 0 8px 18px rgba(37,99,235,.14); transform: translateY(-1px); }
        .modal-balance-details { width: min(1560px, calc(100vw - 48px)); max-width: none; }
        .balance-ledger-table { min-width: 1180px; table-layout: auto; }
        .balance-ledger-table th, .balance-ledger-table td { white-space: nowrap; }
        .balance-ledger-table .ledger-desc { min-width: 360px; max-width: 620px; white-space: normal; word-break: break-word; line-height: 1.55; }
        .balance-ledger-table .ledger-code { max-width: 210px; display: inline-block; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }
        .badge-soft { border-radius: 999px; padding: 6px 10px; font-weight: 700; font-size: .76rem; }
        .badge-soft.success { background: #dcfce7; color: #166534; }
        .badge-soft.warning { background: #fef3c7; color: #92400e; }
        .badge-soft.danger { background: #fee2e2; color: #991b1b; }
        .badge-soft.info { background: #dbeafe; color: #1e40af; }
        .order-status-pill { display: inline-flex; align-items: center; gap: 7px; border: 0; border-radius: 999px; padding: 7px 12px; font-size: .82rem; font-weight: 800; line-height: 1; cursor: pointer; transition: .16s ease; }
        .order-status-pill:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(15,23,42,.12); }
        .order-status-pill::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .order-status-pill.pending { background: #fef3c7; color: #92400e; }
        .order-status-pill.paid { background: #dcfce7; color: #166534; }
        .order-status-pill.failed, .order-status-pill.cancelled, .order-status-pill.unpaid { background: #fee2e2; color: #991b1b; }
        .order-delivery-error { display: inline-flex; align-items: center; gap: 4px; max-width: 100%; border-radius: 10px; padding: 5px 8px; background: #fff1f2; color: #be123c; font-size: .76rem; font-weight: 700; line-height: 1.35; }
        .order-status-editor-row td { padding-top: 0; border-top: 0; }
        .order-status-editor { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 0 0 8px; padding: 14px; border: 1px dashed #cbd5e1; border-radius: 18px; background: #f8fafc; }
        .order-status-editor-card .order-status-editor { margin-top: 12px; }
        .order-status-option { border: 1px solid var(--border); background: #fff; border-radius: 999px; padding: 8px 13px; font-weight: 800; color: #475569; }
        .order-status-option.active { border-color: var(--primary); color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary2)); }
        .order-status-option:not(.active):hover { border-color: #c7d2fe; background: #eef2ff; color: #3730a3; }
        .admin-order-mobile-list { display: none; }
        .admin-order-card { border: 1px solid var(--border); border-radius: 18px; padding: 14px; background: #fff; box-shadow: 0 10px 28px rgba(15,23,42,.06); }
        .admin-order-card + .admin-order-card { margin-top: 12px; }
        .admin-order-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
        .admin-order-title { font-weight: 850; color: #111827; line-height: 1.35; word-break: break-word; }
        .admin-order-trade { color: var(--muted); font-family: Consolas, Monaco, "Courier New", monospace; font-size: .72rem; word-break: break-all; margin-top: 2px; }
        .admin-order-desc { color: #64748b; font-size: .82rem; line-height: 1.45; word-break: break-word; margin-bottom: 12px; }
        .admin-order-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .admin-order-grid > div { min-width: 0; border-radius: 12px; background: #f8fafc; padding: 9px 10px; }
        .admin-order-grid span { display: block; color: var(--muted); font-size: .72rem; margin-bottom: 3px; }
        .admin-order-grid strong { display: block; color: #111827; font-size: .9rem; line-height: 1.35; word-break: break-word; }
        .admin-order-time { grid-column: 1 / -1; }
        .admin-order-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .confirm-overlay { position: fixed; inset: 0; z-index: 9998; display: grid; place-items: center; padding: 20px; background: rgba(15,23,42,.42); backdrop-filter: blur(6px); }
        .admin-confirm { width: min(430px, 100%); background: #fff; border: 1px solid rgba(255,255,255,.75); border-radius: 24px; padding: 24px; box-shadow: 0 28px 90px rgba(15,23,42,.28); animation: confirmPop .16s ease-out; }
        .admin-confirm-icon { width: 50px; height: 50px; border-radius: 18px; display: grid; place-items: center; background: #fee2e2; color: var(--danger); font-size: 1.5rem; margin-bottom: 16px; }
        .admin-confirm-icon.primary { background: #eef2ff; color: var(--primary); }
        .admin-confirm h5 { margin: 0 0 8px; font-weight: 850; }
        .admin-confirm p { color: var(--muted); margin: 0; line-height: 1.7; }
        .admin-confirm-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }
        @keyframes confirmPop { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .login-wrap { min-height: 100vh; display: grid; place-items: center; padding: 20px; background: radial-gradient(circle at 12% 16%, rgba(79,70,229,.26), transparent 28%), radial-gradient(circle at 88% 18%, rgba(14,165,233,.20), transparent 30%), linear-gradient(135deg, #eef2ff, #f8fafc); }
        .login-card { width: min(430px, 100%); background: rgba(255,255,255,.88); border: 1px solid rgba(255,255,255,.7); border-radius: 28px; padding: 34px; box-shadow: 0 28px 80px rgba(15,23,42,.16); backdrop-filter: blur(18px); }
        .login-logo { width: 64px; height: 64px; border-radius: 22px; display: grid; place-items: center; color: #fff; font-size: 30px; background: linear-gradient(135deg, var(--primary), var(--primary2)); margin-bottom: 20px; }
        .toast-box { position: fixed; top: 22px; right: 22px; z-index: 9999; display: grid; gap: 10px; }
        .admin-toast { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 13px 16px; box-shadow: 0 18px 40px rgba(15,23,42,.14); min-width: 260px; display: flex; gap: 10px; align-items: center; }
        .admin-toast.error i { color: var(--danger); } .admin-toast.success i { color: var(--success); } .admin-toast.info i { color: var(--primary); }
        .settings-tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .settings-tab { border: 1px solid var(--border); background: #fff; color: #475569; border-radius: 999px; padding: 9px 14px; font-weight: 700; }
        .settings-tab.active { color: #fff; border-color: var(--primary); background: linear-gradient(135deg, var(--primary), var(--primary2)); }
        .admin-setting-card { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 14px; width: 100%; padding: 16px; border: 1px solid #e0e7ff; border-radius: 20px; background: linear-gradient(135deg, #f8fafc 0%, #ffffff 55%, #eef2ff 100%); box-shadow: 0 12px 32px rgba(79,70,229,.08); cursor: pointer; transition: .18s ease; }
        .admin-setting-card:hover { transform: translateY(-1px); border-color: #c7d2fe; box-shadow: 0 18px 42px rgba(79,70,229,.12); }
        .admin-setting-card.is-off { border-color: #fee2e2; background: linear-gradient(135deg, #fff7ed 0%, #ffffff 55%, #fff1f2 100%); }
        .admin-setting-icon { width: 46px; height: 46px; border-radius: 16px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary2)); box-shadow: 0 12px 24px rgba(79,70,229,.22); font-size: 1.25rem; }
        .admin-setting-card.is-off .admin-setting-icon { background: linear-gradient(135deg, #f97316, #ef4444); box-shadow: 0 12px 24px rgba(239,68,68,.18); }
        .admin-setting-copy { min-width: 0; display: grid; gap: 4px; }
        .admin-setting-title { color: #111827; font-weight: 850; }
        .admin-setting-desc { color: #64748b; font-size: .86rem; line-height: 1.6; }
        .admin-setting-state { color: #4f46e5; font-size: .78rem; font-weight: 800; }
        .admin-setting-card.is-off .admin-setting-state { color: #dc2626; }
        .admin-setting-switch { margin: 0; padding-left: 0; min-height: auto; }
        .admin-setting-switch .form-check-input { margin: 0; width: 3rem; height: 1.55rem; cursor: pointer; }
        .method-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 9px; background: #eef2ff; color: #3730a3; font-size: .78rem; font-weight: 700; margin: 2px; }
        .membership-admin-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .membership-admin-card { border: 1px solid var(--border); border-radius: 22px; background: #fff; overflow: hidden; box-shadow: 0 14px 34px rgba(15,23,42,.06); cursor: pointer; transition: .18s ease; }
        .membership-admin-card:hover { transform: translateY(-3px); box-shadow: 0 20px 48px rgba(15,23,42,.12); }
        .membership-admin-card.disabled { opacity: .58; filter: grayscale(.15); }
        .membership-admin-head { min-height: 118px; padding: 22px; color: #fff; text-align: center; background: var(--card-gradient, linear-gradient(135deg, var(--primary), var(--primary2))); }
        .membership-admin-head i { display: block; font-size: 2rem; margin-bottom: 8px; }
        .membership-admin-head h5 { margin: 0; font-weight: 850; }
        .membership-admin-body { padding: 18px; }
        .membership-admin-price { text-align: center; color: #10b981; font-weight: 850; margin-bottom: 12px; }
        .membership-admin-list { list-style: none; padding: 0; margin: 0; color: #334155; font-size: .9rem; }
        .membership-admin-list li { display: flex; gap: 8px; align-items: center; padding: 7px 0; border-bottom: 1px solid #f1f5f9; }
        .membership-admin-list li:last-child { border-bottom: 0; }
        .membership-admin-list i { color: #10b981; }
        @media (max-width: 1200px) { .membership-admin-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 720px) { .membership-admin-grid { grid-template-columns: 1fr; } }
        .config-help { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 18px; padding: 16px; color: #475569; }
        .markdown-preview { min-height: 180px; background: #f8fafc; border: 1px solid var(--border); border-radius: 16px; padding: 16px; color: #1f2937; }
        .markdown-preview h1, .markdown-preview h2, .markdown-preview h3 { font-weight: 800; margin-top: .6rem; }
        .markdown-preview p { margin-bottom: .75rem; }
        .markdown-preview code { background: #eef2ff; color: #3730a3; border-radius: 6px; padding: 2px 5px; }
        .markdown-preview pre { background: #0f172a; color: #e2e8f0; border-radius: 12px; padding: 12px; overflow-x: auto; }
        .markdown-preview blockquote { border-left: 4px solid var(--primary); padding-left: 12px; color: #475569; }
        .markdown-preview img { max-width: 100%; border-radius: 12px; }
        .form-check-label strong { display: block; }
        .form-check-label span { color: var(--muted); font-size: .82rem; }
        .log-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
        .log-viewer { background: #0f172a; color: #dbeafe; border-radius: 18px; padding: 16px; min-height: 220px; max-height: min(52vh, 520px); overflow: auto; white-space: pre-wrap; word-break: break-word; font-family: Consolas, Monaco, "Courier New", monospace; font-size: 12px; line-height: 1.65; }
        .log-viewer.logs-page-viewer { height: clamp(320px, calc(100vh - 420px), 520px); min-height: 320px; max-height: calc(100vh - 320px); }
        .complaint-row-toggle { cursor: pointer; transition: background .15s ease; }
        .complaint-row-toggle:hover, .complaint-row-toggle.expanded { background: #f8fafc; }
        .complaint-detail-row td { padding: 0 16px 16px !important; border-top: none; background: #f8fafc; }
        .complaint-detail-panel { padding: 4px 0 8px; }
        .complaint-reason-admin { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 12px; white-space: pre-wrap; word-break: break-word; color: #334155; }
        .complaint-grid-admin { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .complaint-meta-admin { background: #f8fafc; border-radius: 14px; padding: 12px; }
        @media (max-width: 1100px) { .complaint-grid-admin { grid-template-columns: 1fr; } }
        .logs-panel { margin-bottom: 28px; }
        .log-meta { color: var(--muted); font-size: .86rem; }
        @media (max-width: 980px) { .admin-shell { display: block; } .sidebar { position: relative; left: auto; top: auto; bottom: auto; width: auto; height: auto; } .content { margin-left: 0; padding: 20px; } .topbar { align-items: flex-start; flex-direction: column; } .log-viewer.logs-page-viewer { height: 420px; max-height: 55vh; } }
        html, body { overflow-x: hidden; }
        img, video, canvas, svg { max-width: 100%; }
        .table-responsive { -webkit-overflow-scrolling: touch; }
        @media (min-width: 981px) and (max-width: 1280px) {
            .sidebar { width: 240px; padding: 22px 14px; }
            .content { margin-left: 240px; padding: 24px; }
            .side-link { padding: 11px 12px; }
            .stat-card { padding: 18px; }
        }
        @media (max-width: 980px) {
            .sidebar { padding: 18px; overflow: visible; }
            .brand { margin-bottom: 16px; }
            .nav-title { margin: 16px 4px 8px; }
            .sidebar .side-link { display: inline-flex; width: auto; margin: 0 6px 8px 0; border-radius: 999px; padding: 10px 13px; white-space: nowrap; }
            .sidebar .side-link.active { box-shadow: inset 0 -3px 0 #818cf8; }
            .content { min-width: 0; }
            .topbar { gap: 12px; margin-bottom: 18px; }
            .topbar h1 { font-size: clamp(1.45rem, 5vw, 2rem); }
            .panel { padding: 18px; border-radius: 20px; }
            .panel-title { align-items: flex-start; flex-direction: column; gap: 10px; }
            .table-responsive { margin-inline: -8px; padding-inline: 8px; }
            .table { min-width: 760px; }
            .membership-admin-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 640px) {
            .login-wrap { padding: 14px; align-items: start; padding-top: min(12vh, 72px); }
            .login-card { padding: 24px 18px; border-radius: 22px; }
            .admin-shell { display: block; }
            .sidebar { position: sticky; top: 0; z-index: 100; border-radius: 0; padding: 14px 12px; max-height: 46vh; overflow-y: auto; }
            .brand-icon { width: 40px; height: 40px; border-radius: 14px; }
            .brand strong { font-size: 1rem; }
            .brand span, .nav-title { display: none; }
            .content { padding: 16px 12px 24px; }
            .topbar { flex-direction: column; align-items: stretch; }
            .user-pill { width: 100%; justify-content: space-between; padding: 9px 12px; }
            .profile-dropdown { position: fixed; left: 12px; right: 12px; top: auto; bottom: 12px; width: auto; max-height: calc(100dvh - 24px); overflow-y: auto; border-radius: 20px; }
            .profile-dropdown::before { display: none; }
            .profile-status-row { align-items: flex-start; flex-direction: column; gap: 3px; }
            .stat-card { padding: 16px; border-radius: 18px; }
            .stat-value { font-size: 1.55rem; }
            .panel { padding: 14px; border-radius: 18px; }
            .panel-title h5 { font-size: 1rem; }
            .panel-title .btn { flex: 1 1 calc(50% - 6px); }
            .panel-title .btn-primary { flex-basis: 100%; }
            .settings-tabs { flex-wrap: nowrap; overflow-x: auto; padding-bottom: 3px; }
            .settings-tab { flex: 0 0 auto; padding: 8px 12px; }
            .toast-box { left: 12px; right: 12px; top: auto; bottom: 12px; }
            .admin-toast { min-width: 0; width: 100%; }
            .admin-confirm { padding: 20px; border-radius: 20px; }
            .admin-confirm-actions { flex-direction: column-reverse; }
            .admin-confirm-actions .btn { width: 100%; justify-content: center; }
            .membership-admin-grid, .complaint-grid-admin { grid-template-columns: 1fr; }
            .order-status-editor { align-items: stretch; flex-direction: column; }
            .order-status-option { width: 100%; }
            .admin-order-table-wrap { display: none; }
            .admin-order-mobile-list { display: block; }
            .admin-order-card .order-status-pill { cursor: pointer; flex: 0 0 auto; }
            .admin-order-grid { grid-template-columns: 1fr; }
            .admin-order-actions { grid-template-columns: 1fr; }
            .log-toolbar { align-items: stretch; flex-direction: column; }
            .log-toolbar .form-control, .log-toolbar .form-select, .log-toolbar .btn { width: 100%; }
            .log-viewer, .log-viewer.logs-page-viewer { height: 360px; max-height: 55vh; font-size: 11px; }
            .modal-dialog { margin: .5rem; }
            .modal-content { border-radius: 18px; max-height: calc(100dvh - 1rem); overflow: hidden; }
            .modal-body { overflow-y: auto; }
        }
        @media (max-width: 420px) {
            .sidebar .side-link { width: 100%; justify-content: flex-start; }
            .table { min-width: 680px; }
            .btn { white-space: normal; }
        }
    </style>
</head>
<body>
<div id="toastBox" class="toast-box"></div>

<section id="loginView" class="login-wrap <?php echo $adminGateUser ? 'hidden' : ''; ?>">
    <div class="login-card">
        <div class="login-logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h2 class="fw-bold mb-1">管理员登录</h2>
        <p class="text-muted mb-4">请输入管理员用户名或邮箱和密码进入 KeyNest 后台。</p>
        <div id="adminLoginError" class="alert alert-danger py-2 small hidden"></div>
        <div class="mb-3">
            <label class="form-label fw-semibold">用户名或邮箱</label>
            <input id="adminUsername" class="form-control form-control-lg" placeholder="输入用户名或邮箱" autocomplete="username">
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold">密码</label>
            <input id="adminPassword" type="password" class="form-control form-control-lg" placeholder="请输入密码" autocomplete="current-password">
        </div>
        <button id="adminLoginBtn" class="btn btn-primary btn-lg w-100" onclick="adminLogin()">登录后台</button>
        <div class="d-flex justify-content-between mt-4 small">
            <a href="/" class="text-decoration-none">返回首页</a>
            <span class="text-muted">后台地址 /admin/</span>
        </div>
    </div>
</section>

<?php if ($adminGateUser): ?>
<section id="adminView" class="admin-shell hidden">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-icon"><i class="bi bi-key-fill"></i></div>
            <div><strong>KeyNest Admin</strong><span>管理控制台</span></div>
        </div>
        <div class="nav-title">主菜单</div>
        <button class="side-link active" data-page="overview" onclick="switchAdminPage('overview')"><i class="bi bi-grid-1x2-fill"></i>总览</button>
        <button class="side-link" data-page="users" onclick="switchAdminPage('users')"><i class="bi bi-people-fill"></i>用户管理</button>
        <button class="side-link" data-page="products" onclick="switchAdminPage('products')"><i class="bi bi-box-seam-fill"></i>商品管理</button>
        <button class="side-link" data-page="comments" onclick="switchAdminPage('comments')"><i class="bi bi-chat-square-text-fill"></i>评价管理</button>
        <button class="side-link" data-page="orders" onclick="switchAdminPage('orders')"><i class="bi bi-receipt-cutoff"></i>订单记录</button>
        <button class="side-link" data-page="complaints" onclick="switchAdminPage('complaints')"><i class="bi bi-exclamation-octagon-fill"></i>投诉管理</button>
        <button class="side-link" data-page="finance" onclick="switchAdminPage('finance')"><i class="bi bi-wallet2"></i>充值提现</button>
        <button class="side-link" data-page="merchant_review" onclick="switchAdminPage('merchant_review')"><i class="bi bi-shop-window"></i>商家审核</button>
        <button class="side-link" data-page="cards" onclick="switchAdminPage('cards')"><i class="bi bi-credit-card-2-front-fill"></i>卡密管理</button>
        <button class="side-link" data-page="membership" onclick="switchAdminPage('membership')"><i class="bi bi-gem"></i>会员等级</button>
        <button class="side-link" data-page="settings" onclick="switchAdminPage('settings')"><i class="bi bi-gear-fill"></i>系统设置</button>
        <button class="side-link" data-page="updates" onclick="switchAdminPage('updates')"><i class="bi bi-cloud-arrow-down-fill"></i>系统更新</button>
        <button class="side-link" data-page="logs" onclick="switchAdminPage('logs')"><i class="bi bi-journal-text"></i>系统日志</button>
        <div class="nav-title">快捷操作</div>
        <a class="side-link text-decoration-none" href="/"><i class="bi bi-house-door"></i>返回前台</a>
        <button class="side-link" onclick="adminLogout()"><i class="bi bi-box-arrow-right"></i>退出登录</button>
    </aside>
    <main class="content">
        <div class="topbar">
            <div>
                <h1 id="pageTitle">后台总览</h1>
                <div class="text-muted mt-1">集中管理用户、商品、订单、财务和系统配置。</div>
            </div>
            <div class="admin-profile-wrap">
                <button type="button" class="user-pill" id="adminProfileToggle" onclick="toggleAdminProfileDropdown()">
                <div class="avatar" id="adminAvatar">A</div>
                    <div><div class="fw-bold" id="adminName">admin</div><div class="small text-muted">个人中心</div></div>
                    <i class="bi bi-chevron-down text-muted"></i>
                </button>
                <div class="profile-dropdown hidden" id="adminProfileDropdown"></div>
            </div>
        </div>
        <div id="adminContent"></div>
    </main>
</section>
<?php endif; ?>

<script>
const Admin = { user: null, page: 'overview', settingsTab: 'basic', cache: {}, dataLoaded: {}, csrfToken: null, serverGateMessage: <?php echo json_encode($adminGateMessage, JSON_UNESCAPED_UNICODE); ?>, listState: { users: { page: 1, pageSize: 10 }, orders: { page: 1, pageSize: 10 }, complaints: { page: 1, pageSize: 10 }, comments: { page: 1, pageSize: 10 } } };
const ADMIN_DATA_LOADERS = {
    dashboard: () => request('admin.php?action=dashboard'),
    users: () => request('admin.php?action=users'),
    products: () => request('admin.php?action=products'),
    payOrders: () => request('payment.php?action=get_orders&lite=1'),
    requests: () => request('admin.php?action=finance_requests'),
    cards: () => request('admin.php?action=cards'),
    payConfigs: () => request('admin.php?action=payment_configs'),
    sysConfig: () => request('admin.php?action=system_config'),
    complaints: () => request('admin.php?action=complaints'),
    membershipLevels: () => request('admin.php?action=membership_levels'),
    comments: () => request('admin.php?action=comments')
};
const ADMIN_PAGE_KEYS = {
    overview: ['dashboard'],
    users: ['users', 'membershipLevels'],
    products: ['products'],
    comments: ['comments'],
    orders: ['payOrders'],
    complaints: ['complaints'],
    finance: ['requests'],
    merchant_review: ['users'],
    cards: ['cards', 'membershipLevels'],
    payments: ['payConfigs'],
    settings: ['sysConfig'],
    membership: ['membershipLevels', 'sysConfig'],
    updates: [],
    logs: []
};
const adminPageSizeOptions = [10, 20, 50, 100, 200, 500, 1000];
const apiBase = <?php echo json_encode($apiBasePath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}
function money(value) { return '¥ ' + Number(value || 0).toFixed(2); }
function dateText(ts) { return ts ? new Date(ts * 1000).toLocaleString('zh-CN') : '-'; }
function statusBadge(status) {
    const map = { pending: ['warning', '待处理'], approved: ['success', '已通过'], paid: ['success', '已支付'], rejected: ['danger', '已拒绝'] };
    const item = map[status] || ['info', status || '-'];
    return `<span class="badge-soft ${item[0]}">${item[1]}</span>`;
}
function adminPageSizeSelect(id, value, onChange) {
    return `<select id="${escapeHtml(id)}" class="form-select form-select-sm" style="width:auto;min-width:120px" onchange="${onChange}">${adminPageSizeOptions.map(size => `<option value="${size}" ${Number(value) === size ? 'selected' : ''}>每页 ${size} 条</option>`).join('')}</select>`;
}
function adminPaginationHtml(page, pageSize, total, onPage, label = '条') {
    const totalPages = Math.max(1, Math.ceil(total / pageSize));
    const safePage = Math.min(Math.max(1, Number(page) || 1), totalPages);
    return `
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
            <div class="small text-muted">第 ${safePage} / ${totalPages} 页，共 ${total} ${label}</div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary" ${safePage <= 1 ? 'disabled' : ''} onclick="${onPage}(${safePage - 1})">上一页</button>
                <button class="btn btn-sm btn-outline-secondary" ${safePage >= totalPages ? 'disabled' : ''} onclick="${onPage}(${safePage + 1})">下一页</button>
            </div>
        </div>`;
}
function showToast(message, type = 'info') {
    const box = document.getElementById('toastBox');
    const icons = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', info: 'bi-info-circle-fill' };
    const el = document.createElement('div');
    el.className = `admin-toast ${type}`;
    el.innerHTML = `<i class="bi ${icons[type] || icons.info}"></i><span>${escapeHtml(message)}</span>`;
    box.appendChild(el);
    setTimeout(() => el.remove(), 3200);
}
async function request(endpoint, method = 'GET', data = null) {
    const options = { method, headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' } };
    if (Admin.csrfToken) options.headers['X-CSRF-Token'] = Admin.csrfToken;
    if (data) options.body = new URLSearchParams(data).toString();
    try {
        const res = await fetch(apiBase + endpoint, options);
        const text = await res.text();
        let json = {};
        try {
            json = text ? JSON.parse(text) : {};
        } catch (e) {
            const looksHtml = /^\s*</.test(text || '');
            const preview = looksHtml ? '接口地址不存在或被服务器返回了 HTML 页面，请检查 API 路径：' + apiBase + endpoint : (text ? text.slice(0, 1000) : '空响应');
            return { success: false, message: `服务器返回异常内容（HTTP ${res.status}）：${preview}` };
        }
        if (json.csrf_token) Admin.csrfToken = json.csrf_token;
        if (!res.ok) {
            const detail = json.output ? '\n' + json.output : '';
            const error = { success: false, message: (json.message || ('请求失败：' + res.status)) + detail, status: res.status, ...json };
            if (res.status === 401 || res.status === 403) handleAdminAuthFailure(error.message);
            return error;
        }
        return json;
    } catch (e) {
        return { success: false, message: '网络错误，请检查服务器是否正常：' + (e.message || e) };
    }
}
async function bootstrapAdmin() {
    if (Admin.serverGateMessage) return showLogin(Admin.serverGateMessage);
    const result = await request('auth.php?action=get_current_user');
    if (!result.success || !result.logged_in) return showLogin(result.message || '请先登录管理员账号');
    if (!result.user || result.user.role !== 'admin') return handleAdminAuthFailure('当前账号不是管理员，请使用管理员账号登录。');
    Admin.user = result.user;
    restoreAdminState();
    showAdmin();
    renderPageLoading();
    await loadAdminPageData(Admin.page);
}
function handleAdminAuthFailure(message = '需要管理员权限，请重新登录。') {
    Admin.user = null;
    Admin.cache = {};
    Admin.dataLoaded = {};
    Admin.csrfToken = null;
    const content = document.getElementById('adminContent');
    if (content) content.innerHTML = '';
    showLogin(message);
}
function showLogin(message = '') {
    document.getElementById('adminView')?.classList.add('hidden');
    document.getElementById('loginView')?.classList.remove('hidden');
    const box = document.getElementById('adminLoginError');
    if (!box) return;
    if (message) { box.textContent = message; box.classList.remove('hidden'); } else { box.classList.add('hidden'); }
}
function showAdmin() {
    document.getElementById('loginView')?.classList.add('hidden');
    const adminView = document.getElementById('adminView');
    if (!adminView) {
        location.reload();
        return;
    }
    adminView.classList.remove('hidden');
    refreshAdminProfileUI();
}
function refreshAdminProfileUI() {
    if (!Admin.user) return;
    document.getElementById('adminName').textContent = Admin.user.username;
    document.getElementById('adminAvatar').textContent = Admin.user.username.charAt(0).toUpperCase();
    renderAdminProfileDropdown();
}
function restoreAdminState() {
    const validPages = ['overview', 'users', 'products', 'orders', 'complaints', 'finance', 'cards', 'settings', 'membership', 'updates', 'logs'];
    const validSettingsTabs = ['basic', 'payment', 'login', 'agreements', 'email', 'captcha', 'announcement'];
    const hash = new URLSearchParams((window.location.hash || '').replace(/^#/, ''));
    const storedPage = localStorage.getItem('keynest_admin_page');
    const storedTab = localStorage.getItem('keynest_admin_settings_tab');
    const page = hash.get('page') || storedPage || 'overview';
    const tab = hash.get('tab') || storedTab || 'basic';
    Admin.page = validPages.includes(page) ? page : 'overview';
    Admin.settingsTab = validSettingsTabs.includes(tab) ? tab : 'basic';
}
function saveAdminState() {
    localStorage.setItem('keynest_admin_page', Admin.page);
    localStorage.setItem('keynest_admin_settings_tab', Admin.settingsTab);
    const params = new URLSearchParams({ page: Admin.page });
    if (Admin.page === 'settings') params.set('tab', Admin.settingsTab);
    history.replaceState(null, '', '#' + params.toString());
}
function updateAdminNavActive(settingsTab = null) {
    document.querySelectorAll('.side-link[data-page]').forEach(btn => {
        const onclick = btn.getAttribute('onclick') || '';
        const isActive = settingsTab ? btn.dataset.page === Admin.page && onclick.includes(settingsTab) : btn.dataset.page === Admin.page && !onclick.includes("'payment'");
        btn.classList.toggle('active', isActive);
    });
}
async function adminLogin() {
    const username = document.getElementById('adminUsername').value.trim();
    const password = document.getElementById('adminPassword').value;
    const errorBox = document.getElementById('adminLoginError');
    if (!username || !password) {
        errorBox.textContent = '请填写用户名或邮箱和密码';
        errorBox.classList.remove('hidden');
        return;
    }
    const btn = document.getElementById('adminLoginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>登录中...';
    const result = await request('auth.php?action=admin_login', 'POST', { username, password });
    btn.disabled = false;
    btn.textContent = '登录后台';
    if (!result.success) {
        errorBox.textContent = result.message || '登录失败，请检查用户名或邮箱和密码';
        errorBox.classList.remove('hidden');
        showToast(errorBox.textContent, 'error');
        return;
    }
    if (!result.user || result.user.role !== 'admin') {
        errorBox.textContent = '该账号不是管理员，无法进入后台。';
        errorBox.classList.remove('hidden');
        showToast('该账号不是管理员', 'error');
        return;
    }
    Admin.user = result.user;
    showToast('登录成功', 'success');
    if (!document.getElementById('adminView')) {
        location.reload();
        return;
    }
    showAdmin();
    await loadAdminData();
}
async function adminLogout() {
    await request('auth.php?action=logout', 'POST');
    Admin.user = null;
    closeAdminProfileDropdown();
    showLogin('已退出登录');
}
function renderAdminProfileDropdown() {
    const box = document.getElementById('adminProfileDropdown');
    if (!box || !Admin.user) return;
    const qqBound = !!Admin.user.qq_bound;
    box.innerHTML = `
        <div class="profile-dropdown-head">
            <div class="avatar" style="width:48px;height:48px;font-size:1.2rem;">${escapeHtml((Admin.user.username || 'A').charAt(0).toUpperCase())}</div>
            <div class="flex-grow-1">
                <div class="fw-bold">${escapeHtml(Admin.user.username || '-')}</div>
                <div class="small text-muted">${escapeHtml(Admin.user.email || '未设置邮箱')}</div>
            </div>
            <span class="badge-soft info">管理员</span>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">用户名</label>
                <input id="adminProfileUsername" class="form-control" value="${escapeHtml(Admin.user.username || '')}" placeholder="2-30个字符，支持中文">
            </div>
            <div class="col-12">
                <label class="form-label">邮箱</label>
                <input id="adminProfileEmail" type="email" class="form-control" value="${escapeHtml(Admin.user.email || '')}" placeholder="your@email.com">
            </div>
            <div class="col-12">
                <button class="btn btn-primary w-100" onclick="saveAdminProfileInfo()"><i class="bi bi-check2-circle me-1"></i>保存个人资料</button>
            </div>
        </div>
        <div class="profile-status-row mt-2">
            <span><i class="bi bi-tencent-qq me-1"></i>QQ 绑定</span>
            <strong class="${qqBound ? 'text-success' : 'text-muted'}">${qqBound ? escapeHtml(Admin.user.qq_nickname || '已绑定') : '未绑定'}</strong>
        </div>
        <div class="d-grid gap-2">
            ${qqBound ? '<button class="btn btn-outline-danger" onclick="unbindAdminQQAccount()"><i class="bi bi-link-45deg me-1"></i>解绑 QQ</button>' : '<button class="btn btn-outline-primary" onclick="bindAdminQQAccount()"><i class="bi bi-tencent-qq me-1"></i>绑定 QQ，启用快捷登录</button>'}
            <button class="btn btn-outline-secondary" onclick="switchAdminPage('settings', 'login'); closeAdminProfileDropdown()"><i class="bi bi-sliders me-1"></i>配置 QQ 登录参数</button>
            <button class="btn btn-light" onclick="adminLogout()"><i class="bi bi-box-arrow-right me-1"></i>退出登录</button>
        </div>
        <div class="small text-muted mt-3">QQ 快捷登录必须先把当前账号绑定 QQ，否则无法识别登录到哪个账号。</div>
    `;
}
function toggleAdminProfileDropdown() {
    renderAdminProfileDropdown();
    const box = document.getElementById('adminProfileDropdown');
    const btn = document.getElementById('adminProfileToggle');
    if (!box) return;
    box.classList.toggle('hidden');
    btn?.classList.toggle('active', !box.classList.contains('hidden'));
}
function closeAdminProfileDropdown() {
    document.getElementById('adminProfileDropdown')?.classList.add('hidden');
    document.getElementById('adminProfileToggle')?.classList.remove('active');
}
async function saveAdminProfileInfo() {
    const username = document.getElementById('adminProfileUsername')?.value.trim() || '';
    const email = document.getElementById('adminProfileEmail')?.value.trim() || '';
    if (!username || !email) return showToast('请填写用户名和邮箱', 'error');
    const res = await request('auth.php?action=update_profile', 'POST', { username, email });
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    Admin.user = res.user || Admin.user;
    showToast(res.message || '个人资料已保存', 'success');
    refreshAdminProfileUI();
    await loadAdminData();
}
function bindAdminQQAccount() {
    location.href = '/api/oauth.php?provider=qq&mode=bind';
}
async function unbindAdminQQAccount() {
    if (!confirm('确定要解绑 QQ 吗？解绑后不能使用该 QQ 快捷登录此账号。')) return;
    const res = await request('auth.php?action=unbind_qq', 'POST');
    if (!res.success) return showToast(res.message || '解绑失败', 'error');
    Admin.user = res.user || Admin.user;
    showToast(res.message || 'QQ 已解绑', 'success');
    refreshAdminProfileUI();
}
document.addEventListener('click', e => {
    const wrap = document.querySelector('.admin-profile-wrap');
    if (wrap && !wrap.contains(e.target)) closeAdminProfileDropdown();
});
function applyAdminCacheKey(key, res) {
    switch (key) {
        case 'dashboard':
            Admin.cache.dashboard = res.dashboard || {};
            break;
        case 'users':
            Admin.cache.users = res.users || [];
            break;
        case 'products':
            Admin.cache.products = res.products || [];
            break;
        case 'payOrders':
            Admin.cache.payOrders = res.orders || [];
            break;
        case 'requests':
            Admin.cache.requests = res.requests || [];
            break;
        case 'cards':
            Admin.cache.cards = res.cards || [];
            break;
        case 'payConfigs':
            Admin.cache.payConfigs = res.configs || [];
            break;
        case 'sysConfig':
            Admin.cache.sysConfig = res.config || {};
            break;
        case 'complaints':
            Admin.cache.complaints = res.complaints || [];
            break;
        case 'membershipLevels':
            Admin.cache.membershipLevels = res.levels || {};
            break;
        case 'comments':
            Admin.cache.comments = res.comments || [];
            break;
    }
}
function renderPageLoading() {
    const area = document.getElementById('adminContent');
    if (!area) return;
    area.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary mb-3" role="status"></div><div>正在加载...</div></div>';
}
async function loadAdminKeys(keys, { force = false } = {}) {
    const uniqueKeys = [...new Set((keys || []).filter(Boolean))];
    const pendingKeys = uniqueKeys.filter(key => force || !Admin.dataLoaded[key]);
    if (!pendingKeys.length) return true;
    const results = await Promise.all(pendingKeys.map(async key => ({ key, res: await ADMIN_DATA_LOADERS[key]() })));
    const failed = results.find(item => !item.res || item.res.success === false);
    if (failed) {
        if (failed.res?.status === 401 || failed.res?.status === 403) return false;
        showToast(failed.res?.message || '后台数据加载失败', 'error');
        return false;
    }
    results.forEach(({ key, res }) => {
        applyAdminCacheKey(key, res);
        Admin.dataLoaded[key] = true;
    });
    return true;
}
function adminPageDataKeys(page = Admin.page) {
    const keys = [...(ADMIN_PAGE_KEYS[page] || ['dashboard'])];
    if (page === 'settings' && Admin.settingsTab === 'payment' && !keys.includes('payConfigs')) {
        keys.push('payConfigs');
    }
    return keys;
}
async function loadAdminPageData(page = Admin.page, { force = false } = {}) {
    const ok = await loadAdminKeys(adminPageDataKeys(page), { force });
    if (ok) renderPage();
    return ok;
}
async function loadAdminData() {
    return loadAdminPageData(Admin.page, { force: true });
}
function switchAdminPage(page, settingsTab = null) {
    Admin.page = page;
    if (settingsTab) Admin.settingsTab = settingsTab;
    saveAdminState();
    updateAdminNavActive(settingsTab);
    const keys = adminPageDataKeys(page);
    if (!keys.length || keys.every(key => Admin.dataLoaded[key])) {
        renderPage();
        return;
    }
    renderPageLoading();
    loadAdminPageData(page);
}
function setTitle(title) { document.getElementById('pageTitle').textContent = title; }
function renderPage() {
    const renderers = { overview: renderOverview, users: renderUsers, products: renderProducts, comments: renderComments, orders: renderOrders, complaints: renderComplaints, finance: renderFinance, merchant_review: renderMerchantReview, cards: renderCards, payments: renderPayments, settings: renderSettings, membership: renderMembershipAdmin, updates: renderUpdates, logs: renderLogs     };
    updateAdminNavActive(Admin.page === 'settings' && Admin.settingsTab === 'payment' ? 'payment' : null);
    (renderers[Admin.page] || renderOverview)();
}
function renderOverview() {
    setTitle('后台总览');
    const dashboard = Admin.cache.dashboard || {};
    const stats = dashboard.stats || {};
    const users = dashboard.recent_users || Admin.cache.users || [];
    const requests = dashboard.pending_requests || Admin.cache.requests || [];
    document.getElementById('adminContent').innerHTML = `
        <div class="row g-3 mb-4">
            ${stat('bi-people-fill', '#dbeafe', '#1d4ed8', stats.user_count ?? 0, '用户总数')}
            ${stat('bi-box-seam-fill', '#ede9fe', '#6d28d9', stats.product_count ?? 0, '商品总数')}
            ${stat('bi-cash-stack', '#dcfce7', '#15803d', stats.pay_order_count ?? 0, '支付订单')}
            ${stat('bi-exclamation-octagon-fill', '#fee2e2', '#b91c1c', stats.open_complaints ?? 0, '进行中投诉')}
            ${stat('bi-hourglass-split', '#fef3c7', '#b45309', stats.pending_requests ?? 0, '待处理申请')}
            ${stat('bi-wallet2', '#e0f2fe', '#0369a1', money(stats.today_receipt ?? 0), '今日收款')}
            ${stat('bi-graph-up-arrow', '#f0fdf4', '#16a34a', money(stats.today_profit ?? 0), '今日利润')}
        </div>
        <div class="row g-4">
            <div class="col-lg-7"><div class="panel"><div class="panel-title"><h5>最新用户</h5><button class="btn btn-sm btn-outline-primary" onclick="switchAdminPage('users')">查看全部</button></div>${userTable(users)}</div></div>
            <div class="col-lg-5"><div class="panel"><div class="panel-title"><h5>待处理申请</h5><button class="btn btn-sm btn-outline-primary" onclick="switchAdminPage('finance')">处理</button></div>${requestList(requests)}</div></div>
        </div>`;
}
function stat(icon, bg, color, value, label) { return `<div class="col-md-6 col-xl-3"><div class="stat-card"><div class="stat-icon" style="background:${bg};color:${color}"><i class="bi ${icon}"></i></div><div class="stat-value">${value}</div><div class="stat-label">${label}</div></div></div>`; }
function renderUsers() {
    setTitle('用户管理');
    const keyword = (document.getElementById('userSearchInput')?.value || '').trim().toLowerCase();
    const users = Admin.cache.users || [];
    const state = Admin.listState.users || (Admin.listState.users = { page: 1, pageSize: 10 });
    state.pageSize = Math.max(10, Math.min(1000, Number(document.getElementById('userPageSizeSelect')?.value || state.pageSize || 10)));
    const filteredUsers = keyword ? users.filter(u =>
        String(u.username || '').toLowerCase().includes(keyword) ||
        String(u.email || '').toLowerCase().includes(keyword)
    ) : users;
    const totalPages = Math.max(1, Math.ceil(filteredUsers.length / state.pageSize));
    state.page = Math.min(Math.max(1, Number(state.page) || 1), totalPages);
    const pageUsers = filteredUsers.slice((state.page - 1) * state.pageSize, state.page * state.pageSize);
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>全部用户</h5>
                    <div class="small text-muted mt-1">${keyword ? '已筛选 ' + filteredUsers.length + ' / ' + users.length + ' 个用户' : '共 ' + users.length + ' 个用户'}，当前显示 ${pageUsers.length} 个</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button id="batchDeleteUsersBtn" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedUsersAdmin()" disabled><i class="bi bi-trash3 me-1"></i>删除选中</button>
                    <button class="btn btn-sm btn-primary" onclick="loadAdminData()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button>
                </div>
            </div>
            <div class="row g-2 mb-3 align-items-center">
                <div class="col-md-7 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input id="userSearchInput" class="form-control" placeholder="搜索用户名或邮箱" value="${escapeHtml(keyword)}" oninput="Admin.listState.users.page=1;renderUsers()" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-secondary" onclick="clearUserSearch()" ${keyword ? '' : 'disabled'}>清空</button>
                </div>
                <div class="col-md-auto ms-md-auto">
                    ${adminPageSizeSelect('userPageSizeSelect', state.pageSize, 'setUsersPageSize(this.value)')}
                </div>
            </div>
            ${userTable(pageUsers, true, true)}
            ${adminPaginationHtml(state.page, state.pageSize, filteredUsers.length, 'setUsersPage', '个用户')}
        </div>`;
    updateUserBatchToolbar();
    if (keyword) {
        const input = document.getElementById('userSearchInput');
        input?.focus();
        input?.setSelectionRange(input.value.length, input.value.length);
    }
}
function setUsersPage(page) {
    Admin.listState.users.page = Number(page) || 1;
    renderUsers();
}
function setUsersPageSize(size) {
    Admin.listState.users.pageSize = Math.max(10, Math.min(1000, Number(size) || 10));
    Admin.listState.users.page = 1;
    renderUsers();
}
function clearUserSearch() {
    const input = document.getElementById('userSearchInput');
    if (input) input.value = '';
    Admin.listState.users.page = 1;
    renderUsers();
}
function adminModal({ title = '详情', body = '', footer = '', size = 'lg' } = {}) {
    const modalId = 'adminDynamicModal';
    document.getElementById(modalId)?.remove();
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = modalId;
    const sizeClass = size ? ` modal-${size}` : '';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered${sizeClass}">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header"><h5 class="modal-title">${title}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">${body}</div>
                ${footer ? `<div class="modal-footer">${footer}</div>` : ''}
            </div>
        </div>`;
    document.body.appendChild(modal);
    modal.addEventListener('hidden.bs.modal', () => modal.remove(), { once: true });
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
function userBalanceDetailOrders(userId) {
    const uid = String(userId || '');
    const balanceTypes = new Set([
        'recharge',
        'card_recharge',
        'membership_upgrade_balance',
        'product_purchase',
        'product_purchase_refund',
        'product_sale_income',
        'publish_fee',
        'publish_fee_refund',
        'admin_balance_adjust'
    ]);
    const allOrders = (Admin.cache.payOrders || []).filter(o => String(o.user_id || '') === uid);
    const orders = allOrders.filter(o => (o.status || '') === 'paid' && Math.abs(Number(o.amount || 0)) > 0 && (balanceTypes.has(String(o.type || '')) || String(o.pay_type || '').includes('balance')));
    allOrders.forEach(o => {
        const refundAmount = Number(o.refunded_amount || 0);
        if (!o.refund_applied || refundAmount <= 0) return;
        const hasRefundRecord = orders.some(item => String(item.type || '') === 'product_purchase_refund' && String(item.related_id || '') === String(o.id || ''));
        if (hasRefundRecord) return;
        orders.push({
            ...o,
            id: `${o.id || o.trade_no || 'refund'}-refund`,
            trade_no: o.trade_no ? `${o.trade_no}-退款` : `退款-${o.id || '-'}`,
            amount: refundAmount,
            actual_amount: refundAmount,
            status: 'paid',
            type: 'product_purchase_refund',
            title: '购买失败退款',
            description: o.delivery_error || o.description || o.title || '订单退款到余额',
            paid_at: o.refunded_at || o.paid_at || o.created_at || 0
        });
    });
    return orders.sort((a, b) => Number(b.paid_at || b.created_at || 0) - Number(a.paid_at || a.created_at || 0));
}
function adminPaymentOrderTitle(order = {}) {
    const type = String(order.type || '').trim();
    const amount = Number(order.amount || 0);
    const titleMap = {
        recharge: '在线充值',
        card_recharge: '卡密充值',
        membership_card: '会员卡密兑换',
        membership_upgrade: '会员开通',
        membership_upgrade_balance: '余额升级会员',
        product_purchase: '余额购买商品',
        product_online_purchase: '在线购买商品',
        product_purchase_refund: '购买失败退款',
        product_sale_income: '商品销售收入',
        publish_fee: '发布商品扣费',
        publish_fee_refund: '删除库存退费',
        admin_balance_adjust: amount >= 0 ? '后台加款' : '后台扣款'
    };
    return titleMap[type] || (amount >= 0 ? '余额收入' : '余额支出');
}
async function openUserBalanceDetails(userId) {
    const cachedUser = (Admin.cache.users || []).find(u => String(u.id || '') === String(userId || ''));
    if (!cachedUser) return showToast('用户不存在', 'error');
    const res = await request(`admin.php?action=user_balance_details&id=${encodeURIComponent(userId)}`);
    if (!res.success) return showToast(res.message || '加载余额明细失败', 'error');
    const details = res.details || {};
    const user = details.user || cachedUser;
    const entries = Array.isArray(details.entries) ? details.entries : [];
    const income = Number(details.income || 0);
    const expense = Number(details.expense || 0);
    const deltaText = value => {
        const amount = Number(value || 0);
        if (Math.abs(amount) < 0.01) return '<span class="text-muted">-</span>';
        return `<span class="fw-semibold ${amount >= 0 ? 'text-success' : 'text-danger'}">${amount >= 0 ? '+' : '-'}${money(Math.abs(amount))}</span>`;
    };
    adminModal({
        title: `${escapeHtml(user.username || '-')} 的余额明细`,
        size: 'balance-details',
        body: `
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="stat-card"><div class="stat-value">${money(user.balance)}</div><div class="stat-label">当前余额</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="stat-value text-warning">${money(user.frozen_balance)}</div><div class="stat-label">冻结余额</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="stat-value text-success">+${money(income)}</div><div class="stat-label">累计入账</div></div></div>
                <div class="col-md-3"><div class="stat-card"><div class="stat-value text-danger">-${money(expense)}</div><div class="stat-label">累计支出</div></div></div>
            </div>
            ${entries.length ? `<div class="table-responsive"><table class="table balance-ledger-table"><thead><tr><th>交易号/订单号</th><th>类型</th><th>余额变动</th><th>冻结变动</th><th>说明</th><th>时间</th></tr></thead><tbody>${entries.map(entry => `<tr>
                <td><code class="small ledger-code" title="${escapeHtml(entry.trade_no || '-')}">${escapeHtml(entry.trade_no || '-')}</code></td>
                <td>${escapeHtml(entry.type_label || '余额明细')}</td>
                <td>${deltaText(entry.balance_delta)}</td>
                <td>${deltaText(entry.frozen_delta)}</td>
                <td class="small text-muted ledger-desc">${escapeHtml(entry.description || '-')}</td>
                <td class="small text-muted">${dateText(entry.time)}</td>
            </tr>`).join('')}</tbody></table></div>` : '<div class="empty-state"><i class="bi bi-wallet2"></i><h5>暂无余额明细</h5><p>该用户还没有产生余额或冻结流水记录</p></div>'}
        `,
        footer: '<button class="btn btn-outline-secondary" data-bs-dismiss="modal">关闭</button>'
    });
}
function userTable(users, withActions = false, selectable = false) {
    if (!users.length) return '<div class="text-muted py-4 text-center">暂无用户</div>';
    const selectHead = selectable ? '<th style="width:44px"><input class="form-check-input" type="checkbox" id="userSelectAll" onchange="toggleAllUserSelection(this.checked)"></th>' : '';
    const selectCol = u => selectable ? `<td><input class="form-check-input user-select" type="checkbox" value="${escapeHtml(u.id)}" onchange="updateUserBatchToolbar()" ${u.username === 'admin' || u.role === 'admin' ? 'disabled title="管理员禁止删除"' : ''}></td>` : '';
    const actionHead = withActions ? '<th>操作</th>' : '';
    const actionCol = u => withActions ? `<td><button class="btn btn-sm btn-outline-primary me-1" onclick="openUserEditor('${escapeHtml(u.id)}')">编辑</button><button class="btn btn-sm btn-outline-danger" onclick="deleteUserAdmin('${escapeHtml(u.id)}')" ${u.username === 'admin' ? 'disabled title="admin 禁止删除"' : ''}>删除</button></td>` : '';
    return `<div class="table-responsive"><table class="table"><thead><tr>${selectHead}<th>用户</th><th>邮箱</th><th>角色</th><th>会员</th><th>余额</th><th>注册时间</th>${actionHead}</tr></thead><tbody>${users.map(u => `<tr>${selectCol(u)}<td><strong>${escapeHtml(u.username)}</strong></td><td>${escapeHtml(u.email || '-')}</td><td>${u.role === 'admin' ? '<span class="badge-soft info">管理员</span>' : '<span class="badge-soft success">用户</span>'}</td><td>${escapeHtml(u.membership_level || 'Free')}</td><td><button type="button" class="balance-detail-btn" onclick="openUserBalanceDetails('${escapeHtml(u.id)}')" title="查看余额明细"><span>${money(u.balance)}</span><small>明细</small></button></td><td>${dateText(u.created_at)}</td>${actionCol(u)}</tr>`).join('')}</tbody></table></div>`;
}
function selectedUserIds() {
    return Array.from(document.querySelectorAll('.user-select:checked')).map(input => input.value).filter(Boolean);
}
function updateUserBatchToolbar() {
    const checkboxes = Array.from(document.querySelectorAll('.user-select:not(:disabled)'));
    const selectedCount = checkboxes.filter(input => input.checked).length;
    const batchBtn = document.getElementById('batchDeleteUsersBtn');
    const selectAll = document.getElementById('userSelectAll');
    if (batchBtn) {
        batchBtn.disabled = selectedCount === 0;
        batchBtn.innerHTML = `<i class="bi bi-trash3 me-1"></i>${selectedCount ? '删除选中 (' + selectedCount + ')' : '删除选中'}`;
    }
    if (selectAll) {
        selectAll.disabled = checkboxes.length === 0;
        selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
}
function toggleAllUserSelection(checked) {
    document.querySelectorAll('.user-select:not(:disabled)').forEach(input => { input.checked = checked; });
    updateUserBatchToolbar();
}
function membershipOptionsForUser(selected) {
    const selectedName = selected || 'Free';
    const levels = Object.values(Admin.cache.membershipLevels || {})
        .filter(level => level && level.name)
        .sort((a, b) => Number(a.priority || 0) - Number(b.priority || 0));
    const selectedExists = levels.some(level => level.name === selectedName);
    const list = selectedExists ? levels : [{ name: selectedName, priority: -1 }, ...levels];
    return list.map(level => `<option value="${escapeHtml(level.name)}" ${level.name === selectedName ? 'selected' : ''}>${escapeHtml(level.name)}</option>`).join('');
}
function openUserEditor(id) {
    const user = (Admin.cache.users || []).find(u => u.id === id);
    if (!user) return showToast('用户不存在', 'error');
    const modalId = 'userEditorModal';
    document.getElementById(modalId)?.remove();
    const isAdminRoot = user.username === 'admin';
    const paymentMethods = user.payment_methods && typeof user.payment_methods === 'object' ? user.payment_methods : {};
    const hasPaymentMethods = Object.values(paymentMethods).some(item => item && (item.account || item.qrcode));
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = modalId;
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header"><h5 class="modal-title">编辑用户</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" id="editUserId" value="${escapeHtml(user.id)}">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">用户名</label><input id="editUsername" class="form-control" value="${escapeHtml(user.username)}" ${isAdminRoot ? 'readonly' : ''}></div>
                        <div class="col-md-6"><label class="form-label">邮箱</label><input id="editEmail" class="form-control" value="${escapeHtml(user.email || '')}"></div>
                        <div class="col-md-6"><label class="form-label">角色</label><select id="editRole" class="form-select" ${isAdminRoot ? 'disabled' : ''}><option value="user" ${user.role !== 'admin' ? 'selected' : ''}>用户</option><option value="admin" ${user.role === 'admin' ? 'selected' : ''}>管理员</option></select></div>
                        <div class="col-md-6"><label class="form-label">会员等级</label><select id="editMembership" class="form-select">${membershipOptionsForUser(user.membership_level || 'Free')}</select></div>
                        <div class="col-md-12"><label class="form-label">余额</label><input id="editBalance" type="number" step="0.01" min="0" class="form-control" value="${Number(user.balance || 0)}"></div>
                    </div>
                    ${isAdminRoot ? '<div class="alert alert-warning py-2 small mt-3 mb-0">admin 账号禁止删除，用户名和管理员角色已锁定。</div>' : ''}
                    <div class="alert alert-light border py-2 small mt-3 mb-0 d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <span><i class="bi bi-wallet2 me-1 text-primary"></i>收款方式：${hasPaymentMethods ? '<strong class="text-success">已配置</strong>' : '<span class="text-muted">未配置</span>'}</span>
                        <button class="btn btn-sm btn-outline-danger" onclick="resetUserPaymentMethodsAdmin('${escapeHtml(user.id)}')" ${isAdminRoot || !hasPaymentMethods ? 'disabled' : ''}>
                            <i class="bi bi-arrow-counterclockwise me-1"></i>重新配置收款方式
                        </button>
                    </div>
                    <div class="alert alert-light border py-2 small mt-3 mb-0">
                        <label class="form-label fw-semibold mb-1"><i class="bi bi-key me-1 text-primary"></i>重置登录密码</label>
                        <div class="input-group input-group-sm">
                            <input id="editNewPassword" type="password" class="form-control" autocomplete="new-password" placeholder="输入新密码，留空则不修改">
                            <button class="btn btn-outline-secondary" type="button" onclick="toggleEditUserPassword()"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="form-text">仅填写时才会重置，至少 6 位。</div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="saveUserAdmin()">保存</button></div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
async function saveUserAdmin() {
    const roleEl = document.getElementById('editRole');
    const newPassword = document.getElementById('editNewPassword')?.value || '';
    if (newPassword && newPassword.length < 6) {
        showToast('新密码至少 6 位', 'error');
        return;
    }
    const payload = {
        id: document.getElementById('editUserId').value,
        username: document.getElementById('editUsername').value.trim(),
        email: document.getElementById('editEmail').value.trim(),
        role: roleEl?.value || 'admin',
        membership_level: document.getElementById('editMembership').value,
        balance: document.getElementById('editBalance').value
    };
    if (newPassword) payload.new_password = newPassword;
    const res = await request('admin.php?action=update_user', 'POST', payload);
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    showToast(res.message || '用户信息已保存', 'success');
    bootstrap.Modal.getInstance(document.getElementById('userEditorModal'))?.hide();
    await loadAdminData();
    renderUsers();
}
function toggleEditUserPassword() {
    const input = document.getElementById('editNewPassword');
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}
async function resetUserPaymentMethodsAdmin(id) {
    const user = (Admin.cache.users || []).find(u => u.id === id);
    if (!user) return showToast('用户不存在', 'error');
    const confirmed = await adminConfirm({
        title: '重新配置收款方式？',
        message: '这会清空用户“' + (user.username || '-') + '”已保存的微信/支付宝收款账号和收款码。清空后用户需要自行重新上传。',
        confirmText: '确认清空',
        cancelText: '取消',
        danger: true
    });
    if (!confirmed) return;
    const res = await request('admin.php?action=reset_user_payment_methods', 'POST', { id });
    if (!res.success) return showToast(res.message || '重置失败', 'error');
    showToast(res.message || '已清空收款方式', 'success');
    bootstrap.Modal.getInstance(document.getElementById('userEditorModal'))?.hide();
    await loadAdminData();
    renderUsers();
}

async function deleteUserAdmin(id) {
    const user = (Admin.cache.users || []).find(u => u.id === id);
    if (!user) return showToast('用户不存在', 'error');
    if (user.username === 'admin') return showToast('admin 管理员禁止删除', 'error');
    if (!confirm('确定删除用户 ' + user.username + ' 吗？')) return;
    const res = await request('admin.php?action=delete_user', 'POST', { id });
    if (!res.success) return showToast(res.message || '删除失败', 'error');
    showToast('用户已删除', 'success');
    await loadAdminData();
    renderUsers();
}
async function deleteSelectedUsersAdmin() {
    const ids = selectedUserIds();
    if (!ids.length) return showToast('请先选择要删除的用户', 'error');
    if (!confirm('确定删除选中的 ' + ids.length + ' 个用户吗？此操作不可恢复。')) return;
    let successCount = 0;
    let failedMessage = '';
    for (const id of ids) {
        const res = await request('admin.php?action=delete_user', 'POST', { id });
        if (res.success) successCount++;
        else failedMessage = res.message || '部分用户删除失败';
    }
    if (successCount) showToast('已删除 ' + successCount + ' 个用户', 'success');
    if (failedMessage) showToast(failedMessage, 'error');
    await loadAdminData();
    renderUsers();
}
function renderMerchantReview() {
    setTitle('商家审核');
    const users = (Admin.cache.users || []).filter(u => u.merchant_status === 'pending');
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>重新开通审核</h5>
                    <div class="small text-muted mt-1">首次开通自动通过；这里仅处理后续重新开通的商家申请。</div>
                </div>
                <button class="btn btn-sm btn-primary" onclick="loadAdminData()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button>
            </div>
            ${users.length ? `<div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>收款方式</th><th>声明确认</th><th>申请时间</th><th>操作</th></tr></thead><tbody>${users.map(u => merchantReviewRow(u)).join('')}</tbody></table></div>` : '<div class="text-muted py-4 text-center">暂无待审核商家重新开通申请</div>'}
        </div>`;
}
function merchantReviewRow(u) {
    const methods = u.payment_methods && typeof u.payment_methods === 'object' ? u.payment_methods : {};
    const methodHtml = Object.entries(methods).filter(([, item]) => item && item.account && item.qrcode).map(([key, item]) => `<span class="method-chip"><i class="bi ${key === 'wechat' ? 'bi-wechat' : 'bi-alipay'}"></i>${escapeHtml(item.account)}</span>`).join('') || '<span class="text-muted">未配置</span>';
    const rulesAccepted = u.merchant_rules_accepted ? '<span class="badge bg-success">已同意</span>' : '<span class="badge bg-warning text-dark">未同意</span>';
    return `<tr><td><strong>${escapeHtml(u.username || '-')}</strong><div class="small text-muted">${escapeHtml(u.email || '-')}</div></td><td>${methodHtml}</td><td>${rulesAccepted}</td><td>${dateText(u.merchant_reapply_at || u.merchant_rules_accepted_at)}</td><td><button class="btn btn-sm btn-success me-1" onclick="reviewMerchant('${escapeHtml(u.id)}','approve')">通过</button><button class="btn btn-sm btn-outline-danger" onclick="reviewMerchant('${escapeHtml(u.id)}','reject')">拒绝</button></td></tr>`;
}
async function reviewMerchant(id, decision) {
    const user = (Admin.cache.users || []).find(u => u.id === id);
    if (!user) return showToast('用户不存在', 'error');
    const ok = await adminConfirm({
        title: decision === 'approve' ? '通过商家开通？' : '拒绝商家开通？',
        message: `确认${decision === 'approve' ? '通过' : '拒绝'}用户“${user.username || '-'}”的商家重新开通申请？`,
        confirmText: decision === 'approve' ? '确认通过' : '确认拒绝',
        cancelText: '取消',
        danger: decision !== 'approve'
    });
    if (!ok) return;
    const res = await request('admin.php?action=review_merchant', 'POST', { id, decision });
    if (!res.success) return showToast(res.message || '处理失败', 'error');
    showToast(res.message || '已处理', 'success');
    await loadAdminData();
    renderMerchantReview();
}

function renderProducts() {
    setTitle('商品管理');
    const products = Admin.cache.products || [];
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>全部商品</h5>
                    <div class="small text-muted mt-1">可单独删除，也可勾选多个商品后批量删除。</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button id="batchDeleteProductsBtn" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedProductsAdmin()" disabled>
                        <i class="bi bi-trash3 me-1"></i>批量删除
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="loadAdminData()">
                        <i class="bi bi-arrow-clockwise me-1"></i>刷新
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:44px"><input class="form-check-input" type="checkbox" id="productSelectAll" onchange="toggleAllProductSelection(this.checked)" ${products.length ? '' : 'disabled'}></th>
                            <th>标题</th>
                            <th>卖家</th>
                            <th>分类</th>
                            <th>价格</th>
                            <th>库存</th>
                            <th>销量</th>
                            <th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${products.map(p => `
                            <tr>
                                <td><input class="form-check-input product-select" type="checkbox" value="${escapeHtml(p.id)}" onchange="updateProductBatchToolbar()"></td>
                                <td><strong>${escapeHtml(p.title)}</strong><div class="small text-muted">${escapeHtml(userEmailByNameOrId(p.seller_name, p.seller_id) || '-')}</div></td>
                                <td>${escapeHtml(p.seller_name || '-')}</td>
                                <td>${escapeHtml(p.category || '-')}</td>
                                <td>${money(p.price)}</td>
                                <td>${p.stock || 0}</td>
                                <td>${p.sales || 0}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="openProductStockModal('${escapeHtml(p.id)}')">
                                        <i class="bi bi-list-ul me-1"></i>查看库存
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProductAdmin('${escapeHtml(p.id)}')">
                                        <i class="bi bi-trash me-1"></i>删除
                                    </button>
                                </td>
                            </tr>
                        `).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">暂无商品</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>`;
    updateProductBatchToolbar();
}
function renderComments() {
    setTitle('评价管理');
    const keyword = (document.getElementById('commentSearchInput')?.value || '').trim().toLowerCase();
    const comments = Admin.cache.comments || [];
    const filtered = keyword ? comments.filter(c =>
        String(c.username || '').toLowerCase().includes(keyword) ||
        String(c.user_id_email || '').toLowerCase().includes(keyword) ||
        String(c.product_title || '').toLowerCase().includes(keyword) ||
        String(c.content || '').toLowerCase().includes(keyword) ||
        String(c.order_id || '').toLowerCase().includes(keyword)
    ) : comments;
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>全部评价</h5>
                    <div class="small text-muted mt-1">${keyword ? '已筛选 ' + filtered.length + ' / ' + comments.length + ' 条评价' : '共 ' + comments.length + ' 条评价'}，支持查看详情和删除指定评价。</div>
                </div>
                <button class="btn btn-sm btn-primary" onclick="loadAdminData()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-7 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input id="commentSearchInput" class="form-control" placeholder="搜索用户、商品、订单号或评价内容" value="${escapeHtml(keyword)}" oninput="renderComments()" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-auto"><button class="btn btn-outline-secondary" onclick="clearCommentSearch()" ${keyword ? '' : 'disabled'}>清空</button></div>
            </div>
            ${commentTable(filtered)}
        </div>`;
    if (keyword) {
        const input = document.getElementById('commentSearchInput');
        input?.focus();
        input?.setSelectionRange(input.value.length, input.value.length);
    }
}
function clearCommentSearch() {
    const input = document.getElementById('commentSearchInput');
    if (input) input.value = '';
    renderComments();
}
function commentRatingBadge(rating) {
    const value = Number(rating || 0);
    const cls = value >= 4 ? 'success' : (value >= 3 ? 'warning' : 'danger');
    return `<span class="badge-soft ${cls}">${value || '-'} 星</span>`;
}
function commentTable(list) {
    if (!list.length) return '<div class="text-muted py-4 text-center">暂无评价</div>';
    return `<div class="table-responsive"><table class="table"><thead><tr><th>评价用户</th><th>商品</th><th>评分</th><th>评价内容</th><th>时间</th><th class="text-end">操作</th></tr></thead><tbody>${list.map(c => `<tr>
        <td><strong>${escapeHtml(c.username || '-')}</strong><div class="small text-muted">${escapeHtml(c.user_id_email || c.user_id || '-')}</div></td>
        <td><strong>${escapeHtml(c.product_title || c.product_id || '-')}</strong><div class="small text-muted">订单：${escapeHtml(c.order_id || '-')}</div></td>
        <td>${commentRatingBadge(c.rating)}</td>
        <td class="small text-muted" style="max-width:420px;white-space:normal;word-break:break-word;">${escapeHtml(c.content || '未填写文字评价')}</td>
        <td class="small text-muted">${dateText(c.created_at)}</td>
        <td class="text-end"><button class="btn btn-sm btn-outline-primary me-1" onclick="openCommentDetail('${escapeHtml(c.id)}')">查看</button><button class="btn btn-sm btn-outline-danger" onclick="deleteCommentAdmin('${escapeHtml(c.id)}')">删除</button></td>
    </tr>`).join('')}</tbody></table></div>`;
}
function openCommentDetail(id) {
    const c = (Admin.cache.comments || []).find(item => String(item.id || '') === String(id || ''));
    if (!c) return showToast('评价不存在，请刷新后重试', 'error');
    adminModal({
        title: '评价详情',
        size: 'lg',
        body: `
            <div class="row g-3 small mb-3">
                <div class="col-md-6"><div class="text-muted">评价用户</div><strong>${escapeHtml(c.username || '-')}</strong><div class="text-muted">${escapeHtml(c.user_id_email || c.user_id || '-')}</div></div>
                <div class="col-md-6"><div class="text-muted">评分</div>${commentRatingBadge(c.rating)}</div>
                <div class="col-md-6"><div class="text-muted">商品</div><strong>${escapeHtml(c.product_title || '-')}</strong><div class="text-muted">${escapeHtml(c.product_id || '-')}</div></div>
                <div class="col-md-6"><div class="text-muted">卖家</div><strong>${escapeHtml(c.seller_name || '-')}</strong><div class="text-muted">${escapeHtml(c.seller_id_email || c.seller_id || '-')}</div></div>
                <div class="col-md-6"><div class="text-muted">订单号</div><code>${escapeHtml(c.order_id || '-')}</code></div>
                <div class="col-md-6"><div class="text-muted">评价时间</div><strong>${dateText(c.created_at)}</strong></div>
            </div>
            <div class="border rounded-4 p-3 bg-light-subtle"><div class="text-muted small mb-2">评价内容</div><div style="white-space:pre-wrap;word-break:break-word;line-height:1.7;">${escapeHtml(c.content || '未填写文字评价')}</div></div>
        `,
        footer: `<button class="btn btn-outline-danger me-auto" onclick="deleteCommentAdmin('${escapeHtml(c.id)}')">删除评价</button><button class="btn btn-outline-secondary" data-bs-dismiss="modal">关闭</button>`
    });
}
async function deleteCommentAdmin(id) {
    const c = (Admin.cache.comments || []).find(item => String(item.id || '') === String(id || ''));
    const ok = await adminConfirm({
        title: '删除这条评价？',
        message: '确认删除“' + (c?.product_title || c?.content || id || '-') + '”这条评价吗？删除后不可恢复。',
        confirmText: '确认删除',
        cancelText: '取消',
        danger: true
    });
    if (!ok) return;
    const res = await request('admin.php?action=delete_comment', 'POST', { id });
    if (!res.success) return showToast(res.message || '删除评价失败', 'error');
    showToast(res.message || '评价已删除', 'success');
    document.getElementById('adminDynamicModal') && bootstrap.Modal.getInstance(document.getElementById('adminDynamicModal'))?.hide();
    await loadAdminData();
    renderComments();
}
function selectedProductIds() {
    return Array.from(document.querySelectorAll('.product-select:checked')).map(input => input.value).filter(Boolean);
}
function updateProductBatchToolbar() {
    const checkboxes = Array.from(document.querySelectorAll('.product-select'));
    const selectedCount = checkboxes.filter(input => input.checked).length;
    const batchBtn = document.getElementById('batchDeleteProductsBtn');
    const selectAll = document.getElementById('productSelectAll');
    if (batchBtn) {
        batchBtn.disabled = selectedCount === 0;
        batchBtn.innerHTML = `<i class="bi bi-trash3 me-1"></i>${selectedCount ? '批量删除 (' + selectedCount + ')' : '批量删除'}`;
    }
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
}
function toggleAllProductSelection(checked) {
    document.querySelectorAll('.product-select').forEach(input => { input.checked = checked; });
    updateProductBatchToolbar();
}
function stockDisplayText(item) {
    if (!item) return '-';
    if (item.format === 'line' && item.content) return item.content;
    const parts = [];
    if (item.email) parts.push('邮箱：' + item.email);
    if (item.password) parts.push('密码：' + item.password);
    if (item.client_id && item.client_id !== 'N/A') parts.push('Client ID：' + item.client_id);
    if (item.fresh_token && item.fresh_token !== 'N/A') parts.push('Fresh Token：' + item.fresh_token);
    return parts.join(' | ') || item.content || '-';
}
async function openProductStockModal(id) {
    const product = (Admin.cache.products || []).find(p => p.id === id);
    const modalId = 'productStockModal';
    document.getElementById(modalId)?.remove();
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = modalId;
    modal.innerHTML = `
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">查看库存</h5>
                        <div class="small text-muted">${escapeHtml(product?.title || id)}</div>
                    </div>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="productStockModalBody">
                    <div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>正在加载库存...</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    const instance = new bootstrap.Modal(modal);
    instance.show();
    await loadProductStockIntoModal(id);
}
function stockFilterButton(label, count, filter, activeFilter) {
    const active = filter === activeFilter;
    return `<button type="button" class="btn w-100 text-start border rounded-4 p-3 ${active ? 'btn-primary text-white' : 'btn-light'}" onclick="setProductStockFilter('${escapeHtml(filter)}')"><div class="small ${active ? 'text-white-50' : 'text-muted'}">${escapeHtml(label)}</div><strong>${count}</strong></button>`;
}
function setProductStockFilter(filter) {
    const productId = document.getElementById('productStockModalBody')?.dataset.productId || '';
    if (!productId) return;
    loadProductStockIntoModal(productId, filter);
}
async function loadProductStockIntoModal(id, filter = null) {
    const body = document.getElementById('productStockModalBody');
    if (!body) return;
    const currentFilter = filter || body.dataset.filter || 'all';
    body.dataset.productId = id;
    body.dataset.filter = currentFilter;
    const res = await request('admin.php?action=product_stock&id=' + encodeURIComponent(id));
    if (!res.success) {
        body.innerHTML = `<div class="alert alert-danger">${escapeHtml(res.message || '库存加载失败')}</div>`;
        return;
    }
    const items = Array.isArray(res.items) ? res.items : [];
    const unsold = items.filter(item => !item.sold).length;
    const sold = items.filter(item => item.sold).length;
    const visibleItems = currentFilter === 'sold' ? items.filter(item => item.sold) : (currentFilter === 'unsold' ? items.filter(item => !item.sold) : items);
    body.innerHTML = `
        <div class="row g-3 mb-3">
            <div class="col-md-4">${stockFilterButton('当前可售库存', unsold, 'unsold', currentFilter)}</div>
            <div class="col-md-4">${stockFilterButton('已售库存', sold, 'sold', currentFilter)}</div>
            <div class="col-md-4">${stockFilterButton('后台记录总数', items.length, 'all', currentFilter)}</div>
        </div>
        ${visibleItems.length ? `<div class="table-responsive"><table class="table"><thead><tr><th style="width:76px">序号</th><th>库存内容</th><th style="width:96px">状态</th><th style="width:110px" class="text-end">操作</th></tr></thead><tbody>${visibleItems.map(item => `
            <tr>
                <td>#${Number(item.index) + 1}</td>
                <td><pre class="mb-0 small" style="white-space:pre-wrap;word-break:break-word;max-width:720px;">${escapeHtml(stockDisplayText(item))}</pre></td>
                <td>${item.sold ? '<span class="badge-soft info">已售</span>' : '<span class="badge-soft success">可售</span>'}</td>
                <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="deleteProductStockItem('${escapeHtml(res.product.id)}', ${Number(item.index)})" ${item.sold ? 'disabled title="已售库存不能删除"' : ''}>删除</button></td>
            </tr>`).join('')}</tbody></table></div>` : '<div class="text-muted text-center py-4">暂无库存记录</div>'}
    `;
}
async function deleteProductStockItem(id, index) {
    const ok = await adminConfirm({
        title: '删除这条库存？',
        message: '删除后该库存将不再出售，此操作不可恢复。已售库存不会被允许删除。',
        confirmText: '确认删除',
        cancelText: '取消',
        danger: true
    });
    if (!ok) return;
    const res = await request('admin.php?action=delete_product_stock', 'POST', { id, index });
    if (!res.success) return showToast(res.message || '删除库存失败', 'error');
    showToast(res.message || '库存已删除', 'success');
    await loadAdminData();
    const currentFilter = document.getElementById('productStockModalBody')?.dataset.filter || 'all';
    await loadProductStockIntoModal(id, currentFilter);
}
async function deleteProductAdmin(id) {
    const product = (Admin.cache.products || []).find(p => p.id === id);
    if (!product) return showToast('商品不存在', 'error');
    if (!confirm('确定删除商品“' + product.title + '”吗？此操作不可恢复。')) return;
    const res = await request('admin.php?action=delete_product', 'POST', { id });
    if (!res.success) return showToast(res.message || '删除失败', 'error');
    showToast(res.message || '商品已删除', 'success');
    await loadAdminData();
    renderProducts();
}
async function deleteSelectedProductsAdmin() {
    const ids = selectedProductIds();
    if (!ids.length) return showToast('请先选择要删除的商品', 'error');
    if (!confirm('确定删除选中的 ' + ids.length + ' 个商品吗？此操作不可恢复。')) return;
    const res = await request('admin.php?action=delete_products', 'POST', { ids: JSON.stringify(ids) });
    if (!res.success) return showToast(res.message || '批量删除失败', 'error');
    showToast(res.message || ('已删除 ' + ids.length + ' 个商品'), 'success');
    await loadAdminData();
    renderProducts();
}
function orderTypeLabel(type, payType) {
    const map = {
        recharge: '在线充值',
        membership_upgrade: '在线会员升级',
        membership_upgrade_balance: '余额会员升级',
        product_purchase: '余额购买商品',
        product_online_purchase: '在线支付商品',
        product_purchase_refund: '购买失败退款',
        product_sale_income: '商品销售收入',
        publish_fee: '发布扣费',
        admin_balance_adjust: '后台调整'
    };
    return map[type] || payType || type || '-';
}
function orderDeliveryNotice(order) {
    if ((order.delivery_status || '') !== 'failed' && !order.delivery_error) return '';
    const refundText = order.refund_applied ? `，已退回余额 ${money(order.refunded_amount || order.actual_amount || order.amount || 0)}` : '';
    return `<div class="order-delivery-error mt-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>${escapeHtml(order.delivery_error || '发货失败')}${refundText}</div>`;
}
function orderStatusDisplay(order) {
    if ((order.delivery_status || '') === 'failed') {
        const refundText = order.refund_applied ? '已退款' : '待退款';
        return `<span class="order-status-pill failed" title="${escapeHtml(order.delivery_error || '发货失败')}">库存不够 / ${refundText}</span>`;
    }
    return orderStatusPill(order);
}
function deliveryItemsForOrder(order) {
    const purchaseOrder = order?.purchase_order || {};
    const deliveryInfo = purchaseOrder.delivery_info || {};
    return Array.isArray(deliveryInfo.items) ? deliveryInfo.items : [];
}
function hasPurchaseDeliveryData(order) {
    if (order?.has_purchase_delivery) return true;
    return deliveryItemsForOrder(order).length > 0;
}
function deliveryItemDisplayText(item) {
    if (!item || typeof item !== 'object') return '-';
    if ((item.format || '') === 'line' && item.content) return String(item.content);
    const parts = [];
    if (item.email) parts.push('邮箱：' + item.email);
    if (item.password) parts.push('密码：' + item.password);
    if (item.client_id && item.client_id !== 'N/A') parts.push('Client ID：' + item.client_id);
    if (item.fresh_token && item.fresh_token !== 'N/A') parts.push('Fresh Token：' + item.fresh_token);
    if (item.content) parts.push(String(item.content));
    return parts.join('\n') || '-';
}
function deliveryItemLineText(item) {
    if (!item || typeof item !== 'object') return '';
    if (item.content) return String(item.content).trim();
    return [item.email, item.password, item.client_id, item.fresh_token]
        .filter(value => value && value !== 'N/A')
        .map(value => String(value).trim())
        .join('----');
}
function deliveryItemsExportText(items) {
    return (items || []).map(deliveryItemLineText).filter(Boolean).join('\n');
}
function safeTxtFileName(name) {
    const safe = String(name || '订单').replace(/[\\/:*?"<>|]/g, '_').trim();
    return (safe || '订单') + '.txt';
}
function downloadTextFile(fileName, text) {
    if (!text) return showToast('暂无可导出的卡密', 'error');
    const blob = new Blob(['\ufeff' + text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = safeTxtFileName(fileName);
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    showToast('已导出TXT文件', 'success');
}
function findPaymentOrderById(id) {
    return (Admin.cache.payOrders || []).find(o => String(o.id || '') === String(id || '')) || null;
}
async function openPaymentOrderDataModal(id) {
    let order = findPaymentOrderById(id);
    if (!order) return showToast('订单不存在，请刷新后重试', 'error');
    if (!order.purchase_order && (order.related_id || order.has_purchase_delivery)) {
        const res = await request(`payment.php?action=get_order_detail&id=${encodeURIComponent(id)}`);
        if (!res.success) return showToast(res.message || '加载发货数据失败', 'error');
        order = { ...order, ...(res.detail?.order || {}) };
        const idx = (Admin.cache.payOrders || []).findIndex(o => String(o.id || '') === String(id || ''));
        if (idx >= 0) Admin.cache.payOrders[idx] = { ...Admin.cache.payOrders[idx], ...order };
    }
    const purchaseOrder = order.purchase_order || {};
    const items = deliveryItemsForOrder(order);
    const modalId = 'paymentOrderDataModal';
    document.getElementById(modalId)?.remove();
    const buyerText = purchaseOrder.guest_order
        ? '游客买家'
        : (purchaseOrder.buyer_id_email || purchaseOrder.buyer_name || recordUserEmail(order, 'user_id', order.user_id || '-'));
    const exportText = deliveryItemsExportText(items);
    const textareaRows = Math.min(Math.max(exportText ? exportText.split('\n').length : 6, 6), 18);
    const itemsHtml = exportText ? `
        <div class="border rounded-3 p-3 bg-light-subtle">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <strong>发货数据</strong>
                <span class="text-muted small">共 ${items.length} 条，一行一个卡密</span>
            </div>
            <textarea class="form-control" readonly rows="${textareaRows}" style="resize:vertical;white-space:pre;word-break:normal;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;line-height:1.55;">${escapeHtml(exportText)}</textarea>
        </div>
    ` : '<div class="text-muted text-center py-4">这条记录没有关联到已发货数据，可能不是商品订单或尚未发货。</div>';
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = modalId;
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam me-2 text-primary"></i>客户购买数据</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3 small">
                        <div class="col-md-6"><div class="text-muted">支付交易号</div><code>${escapeHtml(order.trade_no || order.id || '-')}</code></div>
                        <div class="col-md-6"><div class="text-muted">购买订单号</div><code>${escapeHtml(purchaseOrder.id || order.related_id || '-')}</code></div>
                        <div class="col-md-6"><div class="text-muted">商品</div><strong>${escapeHtml(purchaseOrder.product_title || order.title || '-')}</strong></div>
                        <div class="col-md-3"><div class="text-muted">数量</div><strong>${escapeHtml(purchaseOrder.quantity || order.quantity || 1)}</strong></div>
                        <div class="col-md-3"><div class="text-muted">金额</div><strong>${money(purchaseOrder.price || order.actual_amount || order.amount || 0)}</strong></div>
                        <div class="col-md-6"><div class="text-muted">买家</div><strong>${escapeHtml(buyerText)}</strong></div>
                        <div class="col-md-6"><div class="text-muted">卖家</div><strong>${escapeHtml(purchaseOrder.seller_id_email || purchaseOrder.seller_name || '-')}</strong></div>
                    </div>
                    ${purchaseOrder.guest_order ? '<div class="alert alert-warning small py-2">这是游客订单，后台仅展示订单与发货数据，不展示游客查询密钥。</div>' : ''}
                    ${itemsHtml}
                </div>
                <div class="modal-footer">
                    ${items.length ? `<button class="btn btn-outline-success" onclick="exportOrderDeliveryItems('${escapeHtml(id)}')"><i class="bi bi-download me-1"></i>导出TXT</button><button class="btn btn-outline-primary" onclick="copyAllOrderDeliveryItems('${escapeHtml(id)}')">复制全部数据</button>` : ''}
                    <button class="btn btn-primary" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    new bootstrap.Modal(modal).show();
}
function copyOrderDeliveryItem(id, index) {
    const order = findPaymentOrderById(id);
    const item = deliveryItemsForOrder(order)[index];
    if (!item) return showToast('数据不存在', 'error');
    navigator.clipboard?.writeText(deliveryItemDisplayText(item)).then(() => showToast('发货数据已复制', 'success')).catch(() => showToast('复制失败，请手动复制', 'error'));
}
function copyAllOrderDeliveryItems(id) {
    const order = findPaymentOrderById(id);
    const text = deliveryItemsExportText(deliveryItemsForOrder(order));
    if (!text) return showToast('没有可复制的数据', 'error');
    navigator.clipboard?.writeText(text).then(() => showToast('全部发货数据已复制', 'success')).catch(() => showToast('复制失败，请手动复制', 'error'));
}
function exportOrderDeliveryItems(id) {
    const order = findPaymentOrderById(id);
    const purchaseOrder = order?.purchase_order || {};
    const fileName = purchaseOrder.id || order?.related_id || order?.trade_no || id;
    downloadTextFile(fileName, deliveryItemsExportText(deliveryItemsForOrder(order)));
}
function findAdminUserById(id) {
    return (Admin.cache.users || []).find(u => String(u.id || '') === String(id || '')) || null;
}
function userEmailById(id, fallback = '-') {
    const user = findAdminUserById(id);
    return user?.email || fallback || id || '-';
}
function recordUserEmail(record, field = 'user_id', fallback = '-') {
    const emailKey = field + '_email';
    if (record?.[emailKey]) return record[emailKey];
    const userId = record?.[field] || '';
    const user = findAdminUserById(userId);
    if (user?.email) return user.email;
    if (record?.user_exists === false && userId) {
        return `${userId}（用户不存在）`;
    }
    if (record?.user_username) return record.user_username;
    if (user?.username) return user.username;
    return fallback || userId || '-';
}
function orderUserDisplayHtml(record, field = 'user_id') {
    const username = record?.user_username || findAdminUserById(record?.[field])?.username || '';
    const email = record?.[field + '_email'] || findAdminUserById(record?.[field])?.email || '';
    const uid = record?.[field] || '';
    if (username && email) {
        return `<div><strong>${escapeHtml(username)}</strong><div class="small text-muted">${escapeHtml(email)}</div></div>`;
    }
    if (email) return `<div>${escapeHtml(email)}</div>`;
    if (username) return `<div><strong>${escapeHtml(username)}</strong></div>`;
    return `<div>${escapeHtml(recordUserEmail(record, field, uid || '-'))}</div>`;
}
async function openPaymentOrderDetail(id) {
    const res = await request(`payment.php?action=get_order_detail&id=${encodeURIComponent(id)}`);
    if (!res.success) return showToast(res.message || '加载订单详情失败', 'error');
    renderPaymentOrderDetailModal(res.detail || {});
}
function renderPaymentOrderDetailModal(detail) {
    const order = detail.order || {};
    const linkedUser = detail.linked_user || null;
    adminModal({
        title: `订单详情 · ${escapeHtml(order.trade_no || order.id || '-')}`,
        size: 'lg',
        body: `
            <div class="row g-3 mb-3">
                <div class="col-md-6"><div class="small text-muted">交易号</div><code>${escapeHtml(order.trade_no || order.id || '-')}</code></div>
                <div class="col-md-6"><div class="small text-muted">订单ID</div><code>${escapeHtml(order.id || '-')}</code></div>
                <div class="col-md-6"><div class="small text-muted">类型</div><strong>${escapeHtml(orderTypeLabel(order.type, order.pay_type))}</strong></div>
                <div class="col-md-6"><div class="small text-muted">支付方式</div><strong>${escapeHtml(order.pay_type || '-')}</strong></div>
                <div class="col-md-6"><div class="small text-muted">支付状态</div>${orderStatusDisplay(order)}</div>
                <div class="col-md-6"><div class="small text-muted">金额</div><strong>${money(order.amount)}</strong></div>
                <div class="col-md-6"><div class="small text-muted">实付</div><strong>${money(order.actual_amount)}</strong></div>
                <div class="col-md-6"><div class="small text-muted">手续费</div><strong>${money(order.fee || 0)}</strong></div>
                <div class="col-md-6"><div class="small text-muted">创建时间</div>${dateText(order.created_at)}</div>
                <div class="col-md-6"><div class="small text-muted">支付时间</div>${order.paid_at ? dateText(order.paid_at) : '-'}</div>
                <div class="col-12"><div class="small text-muted">说明</div><div>${escapeHtml(order.title || '-')}<div class="small text-muted">${escapeHtml(order.description || '')}</div></div></div>
                ${order.delivery_error ? `<div class="col-12"><div class="small text-muted">备注</div><div class="small text-danger">${escapeHtml(order.delivery_error)}</div></div>` : ''}
            </div>
            <div class="border rounded-3 p-3 bg-light-subtle">
                <div class="fw-semibold mb-2">用户账号</div>
                <div class="row g-2 small">
                    <div class="col-md-4"><span class="text-muted">用户名</span><div><strong>${escapeHtml(order.user_username || linkedUser?.username || '-')}</strong></div></div>
                    <div class="col-md-4"><span class="text-muted">邮箱</span><div>${escapeHtml(order.user_id_email || order.guest_email || linkedUser?.email || '-')}</div></div>
                    <div class="col-md-4"><span class="text-muted">user_id</span><div><code>${escapeHtml(order.user_id || '-')}</code></div></div>
                    ${linkedUser ? `<div class="col-md-4"><span class="text-muted">当前余额</span><div>${money(linkedUser.balance)}</div></div><div class="col-md-4"><span class="text-muted">冻结余额</span><div>${money(linkedUser.frozen_balance)}</div></div>` : ''}
                </div>
            </div>
        `,
        footer: '<button class="btn btn-outline-secondary" data-bs-dismiss="modal">关闭</button>'
    });
}
function userEmailByNameOrId(name, id = '') {
    const users = Admin.cache.users || [];
    const byId = findAdminUserById(id);
    if (byId?.email) return byId.email;
    const byName = users.find(u => String(u.username || '') === String(name || ''));
    return byName?.email || name || id || '-';
}
function memberLimitText(value, unit) {
    const number = Number(value || 0);
    return number === 0 ? '无限制' : `${number} ${unit}`;
}
function complaintStatusBadge(status) {
    const map = { open: ['warning', '处理中'], processing: ['info', '跟进中'], resolved: ['success', '卖家胜'], rejected: ['danger', '买家胜'] };
    const item = map[status] || ['info', status || '-'];
    return `<span class="badge-soft ${item[0]}">${item[1]}</span>`;
}
function renderComplaints() {
    setTitle('投诉管理');
    const status = document.getElementById('complaintStatusFilter')?.value || 'all';
    const keyword = (document.getElementById('complaintSearchInput')?.value || '').trim().toLowerCase();
    let complaints = Admin.cache.complaints || [];
    if (status !== 'all') complaints = complaints.filter(o => (o.complaint?.status || '') === status);
    if (keyword) {
        complaints = complaints.filter(o => [o.id, o.payment_trade_no, o.product_title, o.buyer_name, o.seller_name, o.complaint?.reason].some(v => String(v || '').toLowerCase().includes(keyword)));
    }
    const allCount = Admin.cache.complaints?.length || 0;
    const openCount = (Admin.cache.complaints || []).filter(o => (o.complaint?.status || '') === 'open').length;
    const state = Admin.listState.complaints || (Admin.listState.complaints = { page: 1, pageSize: 10 });
    state.pageSize = Math.max(10, Math.min(1000, Number(document.getElementById('complaintPageSizeSelect')?.value || state.pageSize || 10)));
    const totalPages = Math.max(1, Math.ceil(complaints.length / state.pageSize));
    state.page = Math.min(Math.max(1, Number(state.page) || 1), totalPages);
    const pageComplaints = complaints.slice((state.page - 1) * state.pageSize, state.page * state.pageSize);
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>投诉管理</h5>
                    <div class="small text-muted mt-1">${keyword || status !== 'all' ? '已筛选 ' + complaints.length + ' / ' + allCount + ' 条' : '共 ' + allCount + ' 条'}，进行中 ${openCount} 条，当前显示 ${pageComplaints.length} 条</div>
                </div>
                <button class="btn btn-sm btn-primary" onclick="loadAdminData()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button>
            </div>
            <div class="row g-2 mb-3 align-items-center">
                <div class="col-md-5 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input id="complaintSearchInput" class="form-control" placeholder="搜索订单/商品/买家/卖家/原因" value="${escapeHtml(keyword)}" oninput="Admin.listState.complaints.page=1;renderComplaints()" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="complaintStatusFilter" class="form-select" onchange="Admin.listState.complaints.page=1;renderComplaints()">
                        ${[['all','全部状态'],['open','处理中'],['processing','跟进中'],['resolved','卖家胜'],['rejected','买家胜']].map(([v,t]) => `<option value="${v}" ${status === v ? 'selected' : ''}>${t}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-secondary" onclick="clearComplaintSearch()" ${keyword ? '' : 'disabled'}>清空</button>
                </div>
                <div class="col-md-auto ms-md-auto">
                    ${adminPageSizeSelect('complaintPageSizeSelect', state.pageSize, 'setComplaintsPageSize(this.value)')}
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>商品 / 订单</th><th>买家</th><th>卖家</th><th>金额 / 冻结</th><th>状态</th><th>投诉时间</th><th style="width:44px"></th></tr>
                    </thead>
                    <tbody>${complaintAdminTableRows(pageComplaints)}</tbody>
                </table>
            </div>
            ${adminPaginationHtml(state.page, state.pageSize, complaints.length, 'setComplaintsPage', '条投诉')}
        </div>`;
    if (keyword) {
        const input = document.getElementById('complaintSearchInput');
        input?.focus();
        input?.setSelectionRange(input.value.length, input.value.length);
    }
}
function adminOrderTradeNo(order) {
    return order?.payment_trade_no || order?.id || '-';
}
function complaintAdminTableRows(orders) {
    if (!orders.length) return '<tr><td colspan="7" class="text-center text-muted py-4">暂无投诉记录</td></tr>';
    return orders.map(order => {
        const complaint = order.complaint || {};
        const id = escapeHtml(order.id || '');
        const tradeNo = escapeHtml(adminOrderTradeNo(order));
        return `
            <tr class="complaint-row-toggle" data-complaint-id="${id}" onclick="toggleComplaintDetail('${id}')">
                <td><strong>${escapeHtml(order.product_title || '-')}</strong><div class="small text-muted">交易号 <code>${tradeNo}</code></div></td>
                <td>${escapeHtml(order.buyer_name || '-')}<div class="small text-muted">${escapeHtml(recordUserEmail(order, 'buyer_id', order.buyer_id || ''))}</div></td>
                <td>${escapeHtml(order.seller_name || '-')}<div class="small text-muted">${escapeHtml(recordUserEmail(order, 'seller_id', order.seller_id || ''))}</div></td>
                <td>${money(order.price)}<div class="small text-danger">冻结 ${money(order.frozen_amount || 0)}</div></td>
                <td>${complaintStatusBadge(complaint.status)}</td>
                <td>${dateText(complaint.created_at)}</td>
                <td class="text-end text-muted"><i class="bi bi-chevron-down"></i></td>
            </tr>
            <tr id="complaintDetail-${id}" class="complaint-detail-row hidden">
                <td colspan="7">${complaintAdminDetailHtml(order)}</td>
            </tr>`;
    }).join('');
}
function complaintAdminReplies(complaint = {}) {
    const replies = Array.isArray(complaint.admin_replies) ? complaint.admin_replies : [];
    if (replies.length) return replies;
    if (complaint.admin_reply) {
        return [{ content: complaint.admin_reply, username: complaint.admin_replied_by || 'admin', created_at: complaint.admin_replied_at || complaint.updated_at || 0 }];
    }
    return [];
}
function openAdminComplaintReplies(orderId) {
    const order = (Admin.cache.complaints || []).find(o => String(o.id || '') === String(orderId || ''));
    if (!order || !order.complaint) return showToast('投诉不存在，请刷新后重试', 'error');
    const replies = complaintAdminReplies(order.complaint);
    adminModal({
        title: '管理员回复',
        size: 'lg',
        body: replies.length ? `<div class="d-flex flex-column gap-2">${replies.map((reply, index) => `
            <div class="border rounded-3 p-3 bg-light-subtle">
                <div class="d-flex justify-content-between gap-2 mb-2 small text-muted">
                    <strong>#${index + 1} ${escapeHtml(reply.username || reply.by || 'admin')}</strong>
                    <span>${dateText(reply.created_at)}</span>
                </div>
                <div style="white-space:pre-wrap;word-break:break-word;">${escapeHtml(reply.content || reply.reply || '')}</div>
            </div>
        `).join('')}</div>` : '<div class="text-muted text-center py-4">暂无管理员回复</div>',
        footer: '<button class="btn btn-primary" data-bs-dismiss="modal">关闭</button>'
    });
}
function complaintAdminDetailHtml(order) {
    const complaint = order.complaint || {};
    const adminReplies = complaintAdminReplies(complaint);
    return `
        <div class="complaint-detail-panel" onclick="event.stopPropagation()">
            <div class="complaint-grid-admin mb-3">
                <div class="complaint-meta-admin"><div class="small text-muted">买家</div><strong>${escapeHtml(order.buyer_name || '-')}</strong><div class="small text-muted">${escapeHtml(recordUserEmail(order, 'buyer_id', order.buyer_id || ''))}</div></div>
                <div class="complaint-meta-admin"><div class="small text-muted">卖家</div><strong>${escapeHtml(order.seller_name || '-')}</strong><div class="small text-muted">${escapeHtml(recordUserEmail(order, 'seller_id', order.seller_id || ''))}</div></div>
                <div class="complaint-meta-admin"><div class="small text-muted">订单金额 / 冻结</div><strong>${money(order.price)}</strong><div class="small text-danger">冻结 ${money(order.frozen_amount || 0)}</div></div>
            </div>
            <div class="mb-3"><div class="small text-muted mb-1">投诉原因</div><div class="complaint-reason-admin">${escapeHtml(complaint.reason || '-')}</div></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><div class="small text-muted mb-1">卖家回复</div><div class="complaint-reason-admin">${escapeHtml(complaint.seller_reply || '暂无')}</div></div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                        <div class="small text-muted">管理员回复</div>
                        <button class="btn btn-sm btn-outline-primary" onclick="openAdminComplaintReplies('${escapeHtml(order.id)}')">查看回复${adminReplies.length ? '（' + adminReplies.length + '）' : ''}</button>
                    </div>
                    <textarea id="adminComplaintReply-${escapeHtml(order.id)}" class="form-control" rows="4" maxlength="800" placeholder="填写新的管理员回复，每次提交都会追加记录"></textarea>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <button class="btn btn-sm btn-outline-primary" onclick="saveAdminComplaintReply('${escapeHtml(order.id)}')">提交回复</button>
                ${['open','processing','resolved','rejected'].map(s => `<button class="btn btn-sm ${complaint.status === s ? 'btn-primary' : 'btn-outline-secondary'}" onclick="updateAdminComplaintStatus('${escapeHtml(order.id)}','${s}')">${complaintStatusText(s)}</button>`).join('')}
            </div>
        </div>`;
}
function toggleComplaintDetail(orderId, forceOpen = null) {
    const row = document.getElementById('complaintDetail-' + orderId);
    const summaryRow = document.querySelector(`tr[data-complaint-id="${orderId}"]`);
    const shouldOpen = forceOpen === null ? (row ? row.classList.contains('hidden') : true) : forceOpen;
    document.querySelectorAll('.complaint-detail-row').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.complaint-row-toggle').forEach(el => el.classList.remove('expanded'));
    if (row) row.classList.toggle('hidden', !shouldOpen);
    if (shouldOpen && summaryRow) summaryRow.classList.add('expanded');
}
function setComplaintsPage(page) {
    Admin.listState.complaints.page = Number(page) || 1;
    renderComplaints();
}
function setComplaintsPageSize(size) {
    Admin.listState.complaints.pageSize = Math.max(10, Math.min(1000, Number(size) || 10));
    Admin.listState.complaints.page = 1;
    renderComplaints();
}
function clearComplaintSearch() {
    const input = document.getElementById('complaintSearchInput');
    if (input) input.value = '';
    Admin.listState.complaints.page = 1;
    renderComplaints();
}
function complaintStatusText(status) { return ({ open: '处理中', processing: '跟进中', resolved: '卖家胜', rejected: '买家胜' })[status] || status; }
async function saveAdminComplaintReply(orderId) {
    const reply = document.getElementById('adminComplaintReply-' + orderId)?.value?.trim() || '';
    const res = await request('admin.php?action=reply_complaint', 'POST', { order_id: orderId, reply });
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    showToast('管理员回复已保存', 'success');
    await loadAdminData();
    renderComplaints();
}
async function updateAdminComplaintStatus(orderId, status) {
    const tipMap = {
        resolved: '确认判定卖家胜吗？资金会归卖家；如果之前已判买家胜，将自动从买家改转给卖家。',
        rejected: '确认判定买家胜吗？资金会归买家；如果之前已判卖家胜，将自动从卖家改转给买家。'
    };
    if (tipMap[status]) {
        const ok = await adminConfirm({
            title: complaintStatusText(status),
            message: tipMap[status],
            confirmText: '确认处理',
            cancelText: '取消',
            danger: status === 'rejected'
        });
        if (!ok) return;
    }
    const res = await request('admin.php?action=update_complaint_status', 'POST', { order_id: orderId, status });
    if (!res.success) return showToast(res.message || '状态更新失败', 'error');
    showToast(res.message || '投诉状态已更新', 'success');
    await loadAdminData();
    renderComplaints();
}

function paymentOrderAdminCard(o) {
    const id = escapeHtml(o.id || '');
    const tradeNo = escapeHtml(o.trade_no || o.id || '-');
    return `
        <div class="admin-order-card">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <input class="form-check-input order-select" type="checkbox" value="${id}" onchange="updateOrderBatchToolbar()">
                <div class="flex-grow-1 min-width-0">
                    <div class="admin-order-card-head">
                        <div class="min-width-0">
                            <div class="admin-order-title">${escapeHtml(o.title || orderTypeLabel(o.type, o.pay_type) || '支付订单')}</div>
                            <div class="admin-order-trade">${tradeNo}</div>
                        </div>
                        ${orderStatusDisplay(o)}
                    </div>
                </div>
            </div>
            <div class="admin-order-desc">${escapeHtml(o.description || '-')} ${orderDeliveryNotice(o)}</div>
            <div class="admin-order-grid">
                <div><span>用户账号</span>${orderUserDisplayHtml(o, 'user_id')}</div>
                <div><span>类型</span><strong>${escapeHtml(orderTypeLabel(o.type, o.pay_type))}</strong></div>
                <div><span>金额</span><strong class="${Number(o.amount || 0) < 0 ? 'text-danger' : 'text-success'}">${money(o.amount)}</strong></div>
                <div><span>实付</span><strong>${money(o.actual_amount)}</strong></div>
                <div class="admin-order-time"><span>创建时间</span><strong>${dateText(o.created_at)}</strong></div>
            </div>
            <div id="orderStatusEditorCard-${id}" class="order-status-editor-card hidden">${orderStatusEditor(o)}</div>
            <div class="admin-order-actions">
                <button class="btn btn-sm btn-outline-secondary" onclick="openPaymentOrderDetail('${id}')">详情</button>
                ${hasPurchaseDeliveryData(o) ? `<button class="btn btn-sm btn-outline-success" onclick="openPaymentOrderDataModal('${id}')">查看数据</button>` : ''}
                <button class="btn btn-sm btn-outline-primary" onclick="toggleOrderStatusEditor('${id}', true)">修改状态</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deletePaymentOrderAdmin('${id}')">删除</button>
            </div>
        </div>
    `;
}

function renderOrders() {
    setTitle('订单记录');
    const keyword = (document.getElementById('orderSearchInput')?.value || '').trim().toLowerCase();
    const allOrders = Admin.cache.payOrders || [];
    const state = Admin.listState.orders || (Admin.listState.orders = { page: 1, pageSize: 10 });
    state.pageSize = Math.max(10, Math.min(1000, Number(document.getElementById('orderPageSizeSelect')?.value || state.pageSize || 10)));
    const orders = keyword ? allOrders.filter(o => [
        o.id, o.trade_no, o.user_id, recordUserEmail(o, 'user_id', ''), o.pay_type, o.type, orderTypeLabel(o.type, o.pay_type), o.title, o.description, o.status, orderStatusMeta(o.status).label, o.delivery_status, o.delivery_error
    ].some(v => String(v || '').toLowerCase().includes(keyword))) : allOrders;
    const totalPages = Math.max(1, Math.ceil(orders.length / state.pageSize));
    state.page = Math.min(Math.max(1, Number(state.page) || 1), totalPages);
    const pageOrders = orders.slice((state.page - 1) * state.pageSize, state.page * state.pageSize);
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>支付订单</h5>
                    <div class="small text-muted mt-1">${keyword ? '已筛选 ' + orders.length + ' / ' + allOrders.length + ' 条订单' : '共 ' + allOrders.length + ' 条订单'}，当前显示 ${pageOrders.length} 条</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button id="batchDeleteOrdersBtn" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedPaymentOrdersAdmin()" disabled><i class="bi bi-trash3 me-1"></i>删除选中</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUnpaidOrdersAdmin()">删除所有未支付</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAllOrdersAdmin()">删除全部订单</button>
                    <button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button>
                </div>
            </div>
            <div class="row g-2 mb-3 align-items-center">
                <div class="col-md-7 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input id="orderSearchInput" class="form-control" placeholder="搜索交易号、邮箱、类型、说明、状态" value="${escapeHtml(keyword)}" oninput="Admin.listState.orders.page=1;renderOrders()" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-secondary" onclick="clearOrderSearch()" ${keyword ? '' : 'disabled'}>清空</button>
                </div>
                <div class="col-md-auto ms-md-auto">
                    ${adminPageSizeSelect('orderPageSizeSelect', state.pageSize, 'setOrdersPageSize(this.value)')}
                </div>
            </div>
            <div class="admin-order-mobile-list">
                ${pageOrders.map(paymentOrderAdminCard).join('') || '<div class="text-muted text-center py-4">暂无订单</div>'}
            </div>
            <div class="table-responsive admin-order-table-wrap">
                <table class="table">
                    <thead>
                        <tr><th style="width:44px"><input class="form-check-input" type="checkbox" id="orderSelectAll" onchange="toggleAllOrderSelection(this.checked)" ${pageOrders.length ? '' : 'disabled'}></th><th>交易号</th><th>用户账号</th><th>类型</th><th>说明</th><th>金额</th><th>实付</th><th>状态</th><th>创建时间</th><th class="text-end">操作</th></tr>
                    </thead>
                    <tbody>
                        ${pageOrders.map(o => `
                            <tr>
                                <td><input class="form-check-input order-select" type="checkbox" value="${escapeHtml(o.id)}" onchange="updateOrderBatchToolbar()"></td>
                                <td><code>${escapeHtml(o.trade_no || o.id)}</code></td>
                                <td>${orderUserDisplayHtml(o, 'user_id')}</td>
                                <td>${escapeHtml(orderTypeLabel(o.type, o.pay_type))}</td>
                                <td><div class="fw-semibold">${escapeHtml(o.title || '-')}</div><div class="small text-muted">${escapeHtml(o.description || '')}</div>${orderDeliveryNotice(o)}</td>
                                <td>${money(o.amount)}</td>
                                <td>${money(o.actual_amount)}</td>
                                <td>${orderStatusDisplay(o)}</td>
                                <td>${dateText(o.created_at)}</td>
                                <td class="text-end"><div class="d-inline-flex justify-content-end align-items-center gap-1 flex-nowrap"><button class="btn btn-sm btn-outline-secondary text-nowrap" onclick="openPaymentOrderDetail('${escapeHtml(o.id)}')">详情</button>${hasPurchaseDeliveryData(o) ? `<button class="btn btn-sm btn-outline-success text-nowrap" onclick="openPaymentOrderDataModal('${escapeHtml(o.id)}')">查看数据</button>` : ''}<button class="btn btn-sm btn-outline-danger text-nowrap" onclick="deletePaymentOrderAdmin('${escapeHtml(o.id)}')">删除</button></div></td>
                            </tr>
                            <tr id="orderStatusEditor-${escapeHtml(o.id)}" class="order-status-editor-row hidden">
                                <td colspan="10">${orderStatusEditor(o)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="10" class="text-center text-muted py-4">暂无订单</td></tr>'}
                    </tbody>
                </table>
            </div>
            ${adminPaginationHtml(state.page, state.pageSize, orders.length, 'setOrdersPage', '条订单')}
        </div>`;
    updateOrderBatchToolbar();
    if (keyword) {
        const input = document.getElementById('orderSearchInput');
        input?.focus();
        input?.setSelectionRange(input.value.length, input.value.length);
    }
}
function setOrdersPage(page) {
    Admin.listState.orders.page = Number(page) || 1;
    renderOrders();
}
function setOrdersPageSize(size) {
    Admin.listState.orders.pageSize = Math.max(10, Math.min(1000, Number(size) || 10));
    Admin.listState.orders.page = 1;
    renderOrders();
}
function clearOrderSearch() {
    const input = document.getElementById('orderSearchInput');
    if (input) input.value = '';
    Admin.listState.orders.page = 1;
    renderOrders();
}
function selectedPaymentOrderIds() {
    return Array.from(document.querySelectorAll('.order-select:checked')).map(input => input.value).filter(Boolean);
}
function updateOrderBatchToolbar() {
    const checkboxes = Array.from(document.querySelectorAll('.order-select'));
    const selectedCount = checkboxes.filter(input => input.checked).length;
    const batchBtn = document.getElementById('batchDeleteOrdersBtn');
    const selectAll = document.getElementById('orderSelectAll');
    if (batchBtn) {
        batchBtn.disabled = selectedCount === 0;
        batchBtn.innerHTML = `<i class="bi bi-trash3 me-1"></i>${selectedCount ? '删除选中 (' + selectedCount + ')' : '删除选中'}`;
    }
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
}
function toggleAllOrderSelection(checked) {
    document.querySelectorAll('.order-select').forEach(input => { input.checked = checked; });
    updateOrderBatchToolbar();
}
function orderStatusMeta(status) {
    const map = {
        pending: { label: '待处理', className: 'pending' },
        paid: { label: '已支付', className: 'paid' },
        failed: { label: '失败', className: 'failed' },
        cancelled: { label: '已取消', className: 'cancelled' },
        unpaid: { label: '未支付', className: 'unpaid' }
    };
    return map[status] || { label: status || '-', className: 'pending' };
}
function orderStatusPill(order) {
    const meta = orderStatusMeta(order.status);
    return `<button class="order-status-pill ${meta.className}" onclick="toggleOrderStatusEditor('${escapeHtml(order.id)}')" title="点击修改状态">${escapeHtml(meta.label)}</button>`;
}
function orderStatusEditor(order) {
    const options = [
        ['pending', '待处理'],
        ['paid', '已支付'],
        ['failed', '失败'],
        ['cancelled', '已取消'],
        ['unpaid', '未支付']
    ];
    return `
        <div class="order-status-editor">
            <span class="fw-bold me-1">修改状态</span>
            ${options.map(([value, label]) => `<button class="order-status-option ${order.status === value ? 'active' : ''}" onclick="updatePaymentOrderStatus('${escapeHtml(order.id)}', '${value}')">${label}</button>`).join('')}
            <button class="btn btn-sm btn-light ms-auto" onclick="toggleOrderStatusEditor('${escapeHtml(order.id)}', false)">收起</button>
        </div>`;
}
function toggleOrderStatusEditor(id, forceOpen = null) {
    const row = document.getElementById('orderStatusEditor-' + id);
    const card = document.getElementById('orderStatusEditorCard-' + id);
    const currentTarget = card && window.matchMedia('(max-width: 640px)').matches ? card : row;
    if (!row && !card) return;
    const shouldOpen = forceOpen === null ? (currentTarget ? currentTarget.classList.contains('hidden') : true) : forceOpen;
    document.querySelectorAll('.order-status-editor-row, .order-status-editor-card').forEach(el => el.classList.add('hidden'));
    if (row) row.classList.toggle('hidden', !shouldOpen);
    if (card) card.classList.toggle('hidden', !shouldOpen);
}
async function updatePaymentOrderStatus(id, status) { const res = await request('payment.php?action=update_order_status', 'POST', { id, status }); if (!res.success) { showToast(res.message || '状态更新失败', 'error'); await loadAdminData(); renderOrders(); return; } showToast('订单状态已更新', 'success'); await loadAdminData(); renderOrders(); }
async function deletePaymentOrderAdmin(id) { if (!confirm('确定删除这条订单吗？')) return; const res = await request('payment.php?action=delete_order', 'POST', { id }); if (!res.success) return showToast(res.message || '删除失败', 'error'); showToast('订单已删除', 'success'); await loadAdminData(); renderOrders(); }
async function deleteSelectedPaymentOrdersAdmin() { const ids = selectedPaymentOrderIds(); if (!ids.length) return showToast('请先选择要删除的订单', 'error'); if (!confirm('确定删除选中的 ' + ids.length + ' 条订单吗？此操作不可恢复。')) return; let successCount = 0; let failedMessage = ''; for (const id of ids) { const res = await request('payment.php?action=delete_order', 'POST', { id }); if (res.success) successCount++; else failedMessage = res.message || '部分订单删除失败'; } if (successCount) showToast('已删除 ' + successCount + ' 条订单', 'success'); if (failedMessage) showToast(failedMessage, 'error'); await loadAdminData(); renderOrders(); }
async function deleteUnpaidOrdersAdmin() { if (!confirm('确定删除所有未支付订单吗？包含待处理、失败、已取消订单。')) return; const res = await request('payment.php?action=delete_unpaid_orders', 'POST'); if (!res.success) return showToast(res.message || '删除失败', 'error'); showToast(res.message || '已删除未支付订单', 'success'); await loadAdminData(); renderOrders(); }
async function deleteAllOrdersAdmin() { if (!confirm('确定删除全部支付订单吗？此操作不可恢复。')) return; const res = await request('payment.php?action=delete_all_orders', 'POST'); if (!res.success) return showToast(res.message || '删除失败', 'error'); showToast(res.message || '已删除全部订单', 'success'); await loadAdminData(); renderOrders(); }
function withdrawMethodText(method) {
    const map = { alipay: '支付宝', wechat: '微信', bank: '银行卡' };
    return map[method] || method || '-';
}
function renderFinance() {
    setTitle('充值提现');
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>申请列表</h5>
                    <div class="small text-muted mt-1">点击“查看收款信息”核对客户账号和收款码后再转账。</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button id="batchDeleteFinanceBtn" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedFinanceRequests()" disabled>
                        <i class="bi bi-trash3 me-1"></i>批量删除
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button>
                </div>
            </div>
            ${requestTable(Admin.cache.requests || [])}
        </div>`;
    updateFinanceBatchToolbar();
}
function requestList(list) { if (!list.length) return '<div class="text-muted py-4 text-center">暂无待处理申请</div>'; return list.map(r => `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><strong>${escapeHtml(recordUserEmail(r, 'user_id', r.username || r.user_id))}</strong><div class="small text-muted">${r.id && r.id.startsWith('wd_') ? '提现' : '充值'} · ${money(r.amount)} · ${dateText(r.created_at)}</div></div>${statusBadge(r.status)}</div>`).join(''); }
function requestTable(list) { return `<div class="table-responsive"><table class="table"><thead><tr><th style="width:44px"><input class="form-check-input" type="checkbox" id="financeSelectAll" onchange="toggleAllFinanceSelection(this.checked)" ${list.length ? '' : 'disabled'}></th><th>用户邮箱</th><th>类型</th><th>金额</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody>${list.map(r => `<tr><td><input class="form-check-input finance-select" type="checkbox" value="${escapeHtml(r.id)}" onchange="updateFinanceBatchToolbar()"></td><td>${escapeHtml(recordUserEmail(r, 'user_id', r.username || r.user_id))}</td><td>${r.id && r.id.startsWith('wd_') ? '提现' : '充值'}</td><td>${money(r.amount)}${r.actual_amount ? `<div class="small text-muted">实到 ${money(r.actual_amount)}</div>` : ''}</td><td>${statusBadge(r.status)}</td><td>${dateText(r.created_at)}</td><td><div class="d-flex flex-wrap gap-1">${r.id && r.id.startsWith('wd_') ? `<button class="btn btn-sm btn-outline-primary" onclick="openWithdrawRequestDetail('${escapeHtml(r.id)}')">查看收款信息</button>` : ''}${r.status === 'pending' ? `<button class="btn btn-sm btn-outline-danger" onclick="handleRequest('${r.id}','reject')">拒绝</button>` : ''}<button class="btn btn-sm btn-outline-danger" onclick="deleteFinanceRequest('${escapeHtml(r.id)}')">删除</button></div></td></tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted py-4">暂无申请</td></tr>'}</tbody></table></div>`; }
function selectedFinanceRequestIds() {
    return Array.from(document.querySelectorAll('.finance-select:checked')).map(input => input.value).filter(Boolean);
}
function updateFinanceBatchToolbar() {
    const checkboxes = Array.from(document.querySelectorAll('.finance-select'));
    const selectedCount = checkboxes.filter(input => input.checked).length;
    const batchBtn = document.getElementById('batchDeleteFinanceBtn');
    const selectAll = document.getElementById('financeSelectAll');
    if (batchBtn) {
        batchBtn.disabled = selectedCount === 0;
        batchBtn.innerHTML = `<i class="bi bi-trash3 me-1"></i>${selectedCount ? '批量删除 (' + selectedCount + ')' : '批量删除'}`;
    }
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
}
function toggleAllFinanceSelection(checked) {
    document.querySelectorAll('.finance-select').forEach(input => { input.checked = checked; });
    updateFinanceBatchToolbar();
}
function openWithdrawRequestDetail(id) {
    const r = (Admin.cache.requests || []).find(item => item.id === id);
    if (!r) return showToast('提现申请不存在，请刷新后重试', 'error');
    const modalId = 'withdrawRequestDetailModal';
    document.getElementById(modalId)?.remove();
    const userEmail = recordUserEmail(r, 'user_id', r.username || r.user_id);
    const statusActions = r.status === 'pending' ? `
        <button class="btn btn-outline-danger" onclick="handleRequestFromDetail('${escapeHtml(r.id)}','reject')">拒绝</button>
        <button class="btn btn-success" onclick="handleRequestFromDetail('${escapeHtml(r.id)}','approve')">确认已转账并通过</button>
    ` : '';
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = modalId;
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-wallet2 me-2 text-primary"></i>提现收款信息</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><div class="small text-muted">用户邮箱</div><div class="fw-bold">${escapeHtml(userEmail)}</div></div>
                        <div class="col-md-3"><div class="small text-muted">提现金额</div><div class="fw-bold text-danger">${money(r.amount)}</div></div>
                        <div class="col-md-3"><div class="small text-muted">实到金额</div><div class="fw-bold text-success">${money(r.actual_amount || r.amount)}</div></div>
                        <div class="col-md-6"><div class="small text-muted">收款方式</div><div class="fw-bold">${escapeHtml(withdrawMethodText(r.payment_method))}</div></div>
                        <div class="col-md-6"><div class="small text-muted">手续费</div><div class="fw-bold">${money(r.fee || 0)}</div></div>
                        <div class="col-12">
                            <div class="small text-muted mb-1">收款账号</div>
                            <div class="input-group">
                                <input class="form-control" id="withdrawAccountCopyInput" value="${escapeHtml(r.payment_account || '')}" readonly>
                                <button class="btn btn-outline-primary" onclick="copyAdminWithdrawAccount()">复制账号</button>
                            </div>
                        </div>
                    </div>
                    <div class="text-center border rounded-4 p-3 bg-light">
                        <div class="fw-semibold mb-2">客户收款码</div>
                        ${r.qrcode_url ? `<img src="${escapeHtml(r.qrcode_url)}" alt="收款码" role="button" title="点击放大查看" onclick="openWithdrawQrPreview('${escapeHtml(r.qrcode_url)}')" style="cursor:zoom-in;max-width:260px;max-height:260px;border-radius:16px;background:#fff;padding:8px;box-shadow:0 8px 24px rgba(15,23,42,.12);" onerror="this.outerHTML='<div class=\'text-danger py-4\'>收款码加载失败</div>';">` : '<div class="text-danger py-4">客户没有提交收款码</div>'}
                        ${r.qrcode_url ? '<div class="small text-muted mt-2">点击图片可在当前页面放大查看</div>' : ''}
                    </div>
                    ${r.admin_note ? `<div class="alert alert-light border small mt-3 mb-0">处理备注：${escapeHtml(r.admin_note)}</div>` : ''}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">关闭</button>
                    ${statusActions}
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
async function copyAdminWithdrawAccount() {
    const input = document.getElementById('withdrawAccountCopyInput');
    if (!input || !input.value) return showToast('没有可复制的账号', 'error');
    try {
        await navigator.clipboard.writeText(input.value);
        showToast('收款账号已复制', 'success');
    } catch (e) {
        input.select();
        document.execCommand('copy');
        showToast('收款账号已复制', 'success');
    }
}
function openWithdrawQrPreview(url) {
    if (!url) return;
    const modalId = 'withdrawQrPreviewModal';
    document.getElementById(modalId)?.remove();
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = modalId;
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-qr-code me-2 text-primary"></i>收款码预览</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center bg-light">
                    <img src="${escapeHtml(url)}" alt="收款码预览" style="max-width:100%;max-height:70vh;border-radius:18px;background:#fff;padding:10px;box-shadow:0 12px 32px rgba(15,23,42,.16);">
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
async function handleRequestFromDetail(id, action) {
    await handleRequest(id, action);
    bootstrap.Modal.getInstance(document.getElementById('withdrawRequestDetailModal'))?.hide();
}
async function handleRequest(id, action) { const res = await request(`finance.php?action=${action}`, 'POST', { id }); if (!res.success) return showToast(res.message || '操作失败', 'error'); showToast(res.message || '操作成功', 'success'); await loadAdminData(); }
async function deleteFinanceRequest(id) {
    const item = (Admin.cache.requests || []).find(r => r.id === id);
    if (!item) return showToast('记录不存在', 'error');
    const confirmed = await adminConfirm({
        title: '删除申请记录？',
        message: '确定删除这条' + (id.startsWith('wd_') ? '提现' : '充值') + '记录吗？此操作不可恢复。',
        confirmText: '删除',
        cancelText: '取消',
        danger: true
    });
    if (!confirmed) return;
    const res = await request('finance.php?action=delete_request', 'POST', { id });
    if (!res.success) return showToast(res.message || '删除失败', 'error');
    showToast(res.message || '记录已删除', 'success');
    await loadAdminData();
}
async function deleteSelectedFinanceRequests() {
    const ids = selectedFinanceRequestIds();
    if (!ids.length) return showToast('请先选择要删除的记录', 'error');
    const confirmed = await adminConfirm({
        title: '批量删除申请记录？',
        message: '确定删除选中的 ' + ids.length + ' 条充值/提现记录吗？此操作不可恢复。',
        confirmText: '批量删除',
        cancelText: '取消',
        danger: true
    });
    if (!confirmed) return;
    const res = await request('finance.php?action=delete_requests', 'POST', { ids: JSON.stringify(ids) });
    if (!res.success) return showToast(res.message || '批量删除失败', 'error');
    showToast(res.message || '已删除选中记录', 'success');
    await loadAdminData();
}
function cardMembershipLevelsAdmin() {
    return Object.values(Admin.cache.membershipLevels || {})
        .filter(level => level && level.name && String(level.name).toLowerCase() !== 'free')
        .sort((a, b) => Number(a.priority || 0) - Number(b.priority || 0));
}
function cardMembershipOptionsAdmin() {
    const levels = cardMembershipLevelsAdmin();
    return levels.map(level => `<option value="${escapeHtml(level.name)}">${escapeHtml(level.name)}</option>`).join('') || '<option value="">暂无可生成的会员等级</option>';
}
function toggleAdminCardCreateType() {
    const type = document.getElementById('cardType')?.value || 'balance';
    document.getElementById('cardAmountWrap')?.classList.toggle('d-none', type !== 'balance');
    document.getElementById('cardMembershipWrap')?.classList.toggle('d-none', type !== 'membership');
}
function renderCards() {
    setTitle('卡密管理');
    const cards = Admin.cache.cards || [];
    const hasMembershipLevels = cardMembershipLevelsAdmin().length > 0;
    document.getElementById('adminContent').innerHTML = `
        <div class="panel mb-4">
            <div class="panel-title">
                <div>
                    <h5>生成卡密</h5>
                    <div class="small text-muted mt-1">余额卡用于充值余额；会员卡用于激活指定会员等级，Free 默认等级不可生成。</div>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-md-6 col-lg-3">
                    <label class="form-label">卡密类型</label>
                    <select id="cardType" class="form-select" onchange="toggleAdminCardCreateType()">
                        <option value="balance">余额卡</option>
                        <option value="membership">会员卡</option>
                    </select>
                </div>
                <div class="col-md-6 col-lg-3" id="cardAmountWrap">
                    <label class="form-label">充值金额</label>
                    <input id="cardAmount" class="form-control" type="number" min="0" step="0.01" placeholder="例如：100">
                </div>
                <div class="col-md-6 col-lg-4 d-none" id="cardMembershipWrap">
                    <label class="form-label">会员权益</label>
                    <select id="cardTargetLevel" class="form-select" ${hasMembershipLevels ? '' : 'disabled'}>${cardMembershipOptionsAdmin()}</select>
                </div>
                <div class="col-md-6 col-lg-2">
                    <label class="form-label">生成数量</label>
                    <input id="cardCount" class="form-control" type="number" min="1" value="1" placeholder="数量">
                </div>
                <div class="col-md-12 col-lg-2"><button class="btn btn-primary w-100" onclick="createCards()">生成</button></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>卡密列表</h5>
                    <div class="small text-muted mt-1">可勾选卡密后批量删除。</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button id="batchCopyCardsBtn" class="btn btn-sm btn-outline-primary" onclick="copySelectedCardsAdmin()" disabled>
                        <i class="bi bi-clipboard me-1"></i>批量复制
                    </button>
                    <button id="batchDeleteCardsBtn" class="btn btn-sm btn-outline-danger" onclick="deleteSelectedCardsAdmin()" disabled>
                        <i class="bi bi-trash3 me-1"></i>批量删除
                    </button>
                    <button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:44px"><input class="form-check-input" type="checkbox" id="cardSelectAll" onchange="toggleAllCardSelection(this.checked)" ${cards.length ? '' : 'disabled'}></th>
                            <th>卡密</th><th>类型</th><th>金额</th><th>权益</th><th>状态</th><th>用户ID</th><th>用户邮箱</th><th>创建时间</th><th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${cards.map(c => {
                            const type = (c.card_type || 'balance') === 'membership' ? 'membership' : 'balance';
                            return `
                            <tr>
                                <td><input class="form-check-input card-select" type="checkbox" value="${escapeHtml(c.id)}" onchange="updateCardBatchToolbar()"></td>
                                <td><code>${escapeHtml(c.code)}</code></td>
                                <td>${type === 'membership' ? '<span class="badge-soft primary">会员卡</span>' : '<span class="badge-soft success">余额卡</span>'}</td>
                                <td>${type === 'membership' ? '-' : money(c.amount)}</td>
                                <td>${type === 'membership' ? escapeHtml(c.target_level || '-') : '余额充值'}</td>
                                <td>${c.used ? '<span class="badge-soft danger">已使用</span>' : '<span class="badge-soft success">未使用</span>'}</td>
                                <td><code class="small">${escapeHtml(c.used_user_id || c.used_by || '-')}</code></td>
                                <td>${escapeHtml(c.used_user_email || '-')}</td>
                                <td>${dateText(c.created_at)}</td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="deleteCardAdmin('${escapeHtml(c.id)}')">删除</button></td>
                            </tr>`;
                        }).join('') || '<tr><td colspan="10" class="text-center text-muted py-4">暂无卡密</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>`;
    toggleAdminCardCreateType();
    updateCardBatchToolbar();
}
function selectedCardIds() {
    return Array.from(document.querySelectorAll('.card-select:checked')).map(input => input.value).filter(Boolean);
}
function updateCardBatchToolbar() {
    const checkboxes = Array.from(document.querySelectorAll('.card-select'));
    const selectedCount = checkboxes.filter(input => input.checked).length;
    const batchBtn = document.getElementById('batchDeleteCardsBtn');
    const copyBtn = document.getElementById('batchCopyCardsBtn');
    const selectAll = document.getElementById('cardSelectAll');
    if (copyBtn) {
        copyBtn.disabled = selectedCount === 0;
        copyBtn.innerHTML = `<i class="bi bi-clipboard me-1"></i>${selectedCount ? '批量复制 (' + selectedCount + ')' : '批量复制'}`;
    }
    if (batchBtn) {
        batchBtn.disabled = selectedCount === 0;
        batchBtn.innerHTML = `<i class="bi bi-trash3 me-1"></i>${selectedCount ? '批量删除 (' + selectedCount + ')' : '批量删除'}`;
    }
    if (selectAll) {
        selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
    }
}
function toggleAllCardSelection(checked) {
    document.querySelectorAll('.card-select').forEach(input => { input.checked = checked; });
    updateCardBatchToolbar();
}
async function copySelectedCardsAdmin() {
    const ids = selectedCardIds();
    if (!ids.length) return showToast('请先选择要复制的卡密', 'error');
    const codes = (Admin.cache.cards || [])
        .filter(card => ids.includes(card.id))
        .map(card => card.code)
        .filter(Boolean);
    if (!codes.length) return showToast('没有可复制的卡密', 'error');
    await copyTextToClipboard(codes.join('\n'));
}
async function deleteCardAdmin(id) {
    const card = (Admin.cache.cards || []).find(c => c.id === id);
    if (!card) return showToast('卡密不存在', 'error');
    const confirmed = await adminConfirm({
        title: '删除卡密？',
        message: '确定删除卡密 ' + card.code + ' 吗？此操作不可恢复。',
        confirmText: '删除',
        cancelText: '取消',
        danger: true
    });
    if (!confirmed) return;
    const res = await request('card.php?action=delete', 'POST', { id });
    if (!res.success) return showToast(res.message || '删除失败', 'error');
    showToast('卡密已删除', 'success');
    await loadAdminData();
    renderCards();
}
async function deleteSelectedCardsAdmin() {
    const ids = selectedCardIds();
    if (!ids.length) return showToast('请先选择要删除的卡密', 'error');
    const confirmed = await adminConfirm({
        title: '批量删除卡密？',
        message: '确定删除选中的 ' + ids.length + ' 张卡密吗？此操作不可恢复。',
        confirmText: '批量删除',
        cancelText: '取消',
        danger: true
    });
    if (!confirmed) return;
    const res = await request('card.php?action=delete_batch', 'POST', { ids: JSON.stringify(ids) });
    if (!res.success) return showToast(res.message || '批量删除失败', 'error');
    showToast(res.message || ('已删除 ' + ids.length + ' 张卡密'), 'success');
    await loadAdminData();
    renderCards();
}
async function createCards() {
    const cardType = document.getElementById('cardType')?.value || 'balance';
    const amount = document.getElementById('cardAmount')?.value || '';
    const count = document.getElementById('cardCount')?.value || '1';
    const targetLevel = document.getElementById('cardTargetLevel')?.value || '';
    if (cardType === 'balance' && Number(amount) <= 0) return showToast('请输入大于 0 的充值金额', 'error');
    if (cardType === 'membership' && !targetLevel) return showToast('请选择要生成的会员权益', 'error');
    const res = await request('card.php?action=create', 'POST', { amount, count, card_type: cardType, target_level: targetLevel });
    if (!res.success) return showToast(res.message || '生成失败', 'error');
    showToast(res.message || '生成成功', 'success');
    await loadAdminData();
    renderCards();
    const codes = (res.cards || []).map(card => card.code).filter(Boolean);
    if (codes.length) {
        const confirmed = await adminConfirm({
            title: '复制生成的卡密？',
            message: '本次成功生成 ' + codes.length + ' 张卡密，确认后会按一行一个复制到剪贴板。',
            confirmText: '复制卡密',
            cancelText: '暂不复制'
        });
        if (confirmed) await copyTextToClipboard(codes.join('\n'));
    }
}
async function copyTextToClipboard(text) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }
        showToast('卡密已复制到剪贴板，共 ' + text.split('\n').filter(Boolean).length + ' 条', 'success');
    } catch (e) {
        showToast('复制失败，请手动复制', 'error');
    }
}
function renderMembershipAdmin() {
    setTitle('会员等级');
    const config = Admin.cache.sysConfig || {};
    document.getElementById('adminContent').innerHTML = `
        <div class="panel mb-3">
            <div class="panel-title"><h5>管理员专属标识</h5><button class="btn btn-sm btn-primary" onclick="saveAdminBadgeStyle()">保存管理员标识</button></div>
            <div class="config-help mb-3">管理员账号在商品卡片上会显示专属标识，颜色和图标可单独配置，与普通会员等级无关。</div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">显示文字</label><input id="adminBadgeText" class="form-control" value="${escapeHtml(config.admin_badge_text || '管理员')}"></div>
                <div class="col-md-3"><label class="form-label">图标 class</label><input id="adminBadgeIcon" class="form-control" value="${escapeHtml(config.admin_badge_icon || 'bi-shield-fill-check')}"></div>
                <div class="col-md-6"><label class="form-label">背景渐变 CSS</label><input id="adminBadgeGradient" class="form-control" value="${escapeHtml(config.admin_badge_gradient || 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)')}"></div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-title"><h5>会员等级配置</h5><div><button class="btn btn-sm btn-outline-primary me-2" onclick="addMembershipLevelRow()">新增等级</button><button class="btn btn-sm btn-primary" onclick="saveMembershipLevels()">保存配置</button></div></div>
            <div class="config-help mb-3">点击卡片即可编辑等级。可为每个等级设置图标、渐变颜色；勾选“允许自定义标签”后，该等级用户可在个人中心设置 1-10 字个性化标签。</div>
            <div id="membershipAdminList" class="membership-admin-grid">加载中...</div>
        </div>`;
    loadMembershipLevelsAdmin();
}
async function saveAdminBadgeStyle() {
    const res = await request('finance.php?action=update_system_config', 'POST', {
        admin_badge_text: fieldValue('adminBadgeText'),
        admin_badge_icon: fieldValue('adminBadgeIcon'),
        admin_badge_gradient: fieldValue('adminBadgeGradient')
    });
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    showToast('管理员标识已保存', 'success');
    await loadAdminData();
    renderMembershipAdmin();
}
async function loadMembershipLevelsAdmin() {
    const res = await request('admin.php?action=membership_levels');
    if (!res.success) {
        document.getElementById('membershipAdminList').innerHTML = '<div class="text-danger">加载失败</div>';
        return;
    }
    Admin.cache.membershipLevels = res.levels || {};
    renderMembershipLevelRows(Object.values(Admin.cache.membershipLevels));
}
function renderMembershipLevelRows(levels) {
    const list = document.getElementById('membershipAdminList');
    if (!levels.length) levels = [];
    levels.sort((a, b) => Number(a.priority || 0) - Number(b.priority || 0));
    list.innerHTML = levels.map((level, index) => membershipLevelRow(level, index)).join('') || '<div class="text-muted text-center py-4">暂无等级，请新增</div>';
}
function membershipLevelRow(level = {}, index = 0) {
    const levelName = escapeHtml(level.name || '');
    const gradient = escapeHtml(level.gradient || 'linear-gradient(135deg, #6366f1, #8b5cf6)');
    const icon = escapeHtml(level.icon || 'bi-gem');
    const feeRate = (Number(level.fee_rate || 0) * 100).toFixed(2).replace(/\.00$/, '');
    const maxProducts = memberLimitText(level.max_products, '个商品');
    return `
        <div class="membership-admin-card membership-level-row ${level.enabled === false ? 'disabled' : ''}" data-index="${index}" onclick="openMembershipLevelEditor(${index})">
            <div class="membership-admin-head" style="--card-gradient: ${gradient};">
                <i class="bi ${icon}"></i>
                <h5>${levelName}</h5>
                <div class="small opacity-75 mt-1">${escapeHtml(level.description || '')}</div>
            </div>
            <div class="membership-admin-body">
                <input type="hidden" class="ml-name" value="${levelName}">
                <input type="hidden" class="ml-description" value="${escapeHtml(level.description || '')}">
                <input type="hidden" class="ml-priority" value="${Number(level.priority || index)}">
                <input type="hidden" class="ml-cost" value="${Number(level.cost || 0)}">
                <input type="hidden" class="ml-icon" value="${icon}">
                <input type="hidden" class="ml-max-accounts" value="${level.max_accounts_per_product === undefined || level.max_accounts_per_product === null || level.max_accounts_per_product === '' ? 1 : Number(level.max_accounts_per_product)}">
                <input type="hidden" class="ml-max-products" value="${level.max_products === undefined || level.max_products === null || level.max_products === '' ? 1 : Number(level.max_products)}">
                <input type="hidden" class="ml-fee-rate" value="${feeRate}">
                <input type="hidden" class="ml-publish-fee" value="${Number(level.publish_fee_per_account || 0)}">
                <input type="hidden" class="ml-gradient" value="${gradient}">
                <input type="checkbox" class="ml-enabled d-none" ${level.enabled !== false ? 'checked' : ''}>
                <input type="checkbox" class="ml-can-upgrade d-none" ${level.can_upgrade !== false ? 'checked' : ''}>
                <input type="checkbox" class="ml-custom-label-enabled d-none" ${level.custom_label_enabled ? 'checked' : ''}>
                <div class="membership-admin-price">${Number(level.cost || 0) === 0 ? '<i class="bi bi-gift"></i> 免费' : '¥ ' + Number(level.cost || 0).toFixed(2)}</div>
                <ul class="membership-admin-list">
                    <li><i class="bi bi-check"></i> 单商品最大 ${memberLimitText(level.max_accounts_per_product, '账号')}</li>
                    <li><i class="bi bi-check"></i> ${maxProducts}</li>
                    <li><i class="bi bi-check"></i> 手续费 ${feeRate}%</li>
                    <li><i class="bi bi-check"></i> ${Number(level.publish_fee_per_account || 0) === 0 ? '发布免费' : '发布费 ¥' + Number(level.publish_fee_per_account || 0) + '/账号'}</li>
                </ul>
                <div class="d-flex justify-content-between align-items-center mt-3 small text-muted flex-wrap gap-2">
                    <span>${level.enabled !== false ? '已启用' : '已隐藏'}</span>
                    <span>${level.can_upgrade !== false ? '允许升级' : '禁止升级'}</span>
                    <span>${level.custom_label_enabled ? '可自定义标签' : '无自定义标签'}</span>
                </div>
            </div>
        </div>`;
}
function openMembershipLevelEditor(index) {
    const row = document.querySelector(`.membership-level-row[data-index="${index}"]`);
    if (!row) return;
    const value = cls => row.querySelector(cls)?.value || '';
    const checked = cls => !!row.querySelector(cls)?.checked;
    const name = value('.ml-name');
    document.getElementById('membershipLevelEditorModal')?.remove();
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'membershipLevelEditorModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header"><h5 class="modal-title">编辑会员等级</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="hidden" id="editLevelIndex" value="${index}">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">等级名称</label><input id="editLevelName" class="form-control" value="${escapeHtml(name)}" ${name === 'Free' ? 'readonly' : ''}></div>
                        <div class="col-md-8"><label class="form-label">描述</label><input id="editLevelDescription" class="form-control" value="${escapeHtml(value('.ml-description'))}"></div>
                        <div class="col-md-4"><label class="form-label">排序权重</label><input id="editLevelPriority" type="number" class="form-control" value="${escapeHtml(value('.ml-priority'))}"></div>
                        <div class="col-md-4"><label class="form-label">开通价格</label><input id="editLevelCost" type="number" step="0.01" class="form-control" value="${escapeHtml(value('.ml-cost'))}"></div>
                        <div class="col-md-4"><label class="form-label">图标 class</label><input id="editLevelIcon" class="form-control" value="${escapeHtml(value('.ml-icon'))}"></div>
                        <div class="col-md-3"><label class="form-label">单商品账号数</label><input id="editLevelMaxAccounts" type="number" min="0" class="form-control" value="${escapeHtml(value('.ml-max-accounts'))}"><div class="form-text">填 0 表示无限制</div></div>
                        <div class="col-md-3"><label class="form-label">最多商品数</label><input id="editLevelMaxProducts" type="number" min="0" class="form-control" value="${escapeHtml(value('.ml-max-products'))}"><div class="form-text">填 0 表示无限制</div></div>
                        <div class="col-md-3"><label class="form-label">交易手续费 %</label><input id="editLevelFeeRate" type="number" step="0.01" class="form-control" value="${escapeHtml(value('.ml-fee-rate'))}"></div>
                        <div class="col-md-3"><label class="form-label">发布费/账号</label><input id="editLevelPublishFee" type="number" step="0.01" class="form-control" value="${escapeHtml(value('.ml-publish-fee'))}"></div>
                        <div class="col-md-12"><label class="form-label">卡片渐变 CSS</label><input id="editLevelGradient" class="form-control" value="${escapeHtml(value('.ml-gradient'))}"></div>
                        <div class="col-md-4"><div class="form-check"><input id="editLevelEnabled" class="form-check-input" type="checkbox" ${checked('.ml-enabled') ? 'checked' : ''}><label class="form-check-label">启用显示</label></div></div>
                        <div class="col-md-4"><div class="form-check"><input id="editLevelCanUpgrade" class="form-check-input" type="checkbox" ${checked('.ml-can-upgrade') ? 'checked' : ''}><label class="form-check-label">允许前台升级</label></div></div>
                        <div class="col-md-4"><div class="form-check"><input id="editLevelCustomLabelEnabled" class="form-check-input" type="checkbox" ${checked('.ml-custom-label-enabled') ? 'checked' : ''}><label class="form-check-label">允许自定义标签</label></div></div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button class="btn btn-outline-danger" onclick="deleteMembershipLevelByIndex(${index})" ${name === 'Free' ? 'disabled' : ''}>删除等级</button>
                    <div><button class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="applyMembershipLevelEditor()">应用</button></div>
                </div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
function applyMembershipLevelEditor() {
    const index = document.getElementById('editLevelIndex').value;
    const row = document.querySelector(`.membership-level-row[data-index="${index}"]`);
    if (!row) return;
    row.querySelector('.ml-name').value = document.getElementById('editLevelName').value.trim();
    row.querySelector('.ml-description').value = document.getElementById('editLevelDescription').value.trim();
    row.querySelector('.ml-priority').value = document.getElementById('editLevelPriority').value;
    row.querySelector('.ml-cost').value = document.getElementById('editLevelCost').value;
    row.querySelector('.ml-icon').value = document.getElementById('editLevelIcon').value.trim();
    row.querySelector('.ml-max-accounts').value = document.getElementById('editLevelMaxAccounts').value;
    row.querySelector('.ml-max-products').value = document.getElementById('editLevelMaxProducts').value;
    row.querySelector('.ml-fee-rate').value = document.getElementById('editLevelFeeRate').value;
    row.querySelector('.ml-publish-fee').value = document.getElementById('editLevelPublishFee').value;
    row.querySelector('.ml-gradient').value = document.getElementById('editLevelGradient').value.trim();
    row.querySelector('.ml-enabled').checked = document.getElementById('editLevelEnabled').checked;
    row.querySelector('.ml-can-upgrade').checked = document.getElementById('editLevelCanUpgrade').checked;
    row.querySelector('.ml-custom-label-enabled').checked = document.getElementById('editLevelCustomLabelEnabled').checked;
    bootstrap.Modal.getInstance(document.getElementById('membershipLevelEditorModal'))?.hide();
    renderMembershipLevelRows(collectMembershipLevels());
}
function deleteMembershipLevelByIndex(index) {
    const row = document.querySelector(`.membership-level-row[data-index="${index}"]`);
    if (!row) return;
    const name = row.querySelector('.ml-name')?.value || '';
    bootstrap.Modal.getInstance(document.getElementById('membershipLevelEditorModal'))?.hide();
    deleteMembershipLevelRow(row, name);
}
function toIntInputValue(value, fallback = 0) {
    const text = String(value ?? '').trim();
    if (text === '') return fallback;
    const number = parseInt(text, 10);
    return Number.isFinite(number) ? number : fallback;
}

function toFloatInputValue(value, fallback = 0) {
    const text = String(value ?? '').trim();
    if (text === '') return fallback;
    const number = parseFloat(text);
    return Number.isFinite(number) ? number : fallback;
}

function collectMembershipLevels() {
    return Array.from(document.querySelectorAll('.membership-level-row')).map(row => ({
        name: row.querySelector('.ml-name').value.trim(),
        description: row.querySelector('.ml-description').value.trim(),
        priority: toIntInputValue(row.querySelector('.ml-priority').value, 0),
        cost: toFloatInputValue(row.querySelector('.ml-cost').value, 0),
        icon: row.querySelector('.ml-icon').value.trim(),
        max_accounts_per_product: Math.max(0, toIntInputValue(row.querySelector('.ml-max-accounts').value, 1)),
        max_products: Math.max(0, toIntInputValue(row.querySelector('.ml-max-products').value, 1)),
        fee_rate: (toFloatInputValue(row.querySelector('.ml-fee-rate').value, 0) / 100),
        publish_fee_per_account: Math.max(0, toFloatInputValue(row.querySelector('.ml-publish-fee').value, 0)),
        gradient: row.querySelector('.ml-gradient').value.trim(),
        enabled: row.querySelector('.ml-enabled').checked,
        can_upgrade: row.querySelector('.ml-can-upgrade').checked,
        custom_label_enabled: row.querySelector('.ml-custom-label-enabled').checked
    })).filter(level => level.name);
}
function addMembershipLevelRow() {
    const levels = collectMembershipLevels();
    levels.push({ name: 'NewLevel' + (levels.length + 1), description: '自定义会员', priority: levels.length, cost: 0, icon: 'bi-gem', max_accounts_per_product: 10, max_products: 1, fee_rate: 0, publish_fee_per_account: 0, gradient: 'linear-gradient(135deg, #6366f1, #8b5cf6)', enabled: true, can_upgrade: true });
    renderMembershipLevelRows(levels);
}
async function saveMembershipLevels() {
    const levels = collectMembershipLevels();
    const res = await request('admin.php?action=save_membership_levels', 'POST', { levels: JSON.stringify(levels) });
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    showToast('会员等级已保存', 'success');
    Admin.cache.membershipLevels = res.levels || {};
    renderMembershipLevelRows(Object.values(Admin.cache.membershipLevels));
}
async function deleteMembershipLevelRow(target, name) {
    const row = target.closest ? target.closest('.membership-level-row') : target;
    if (!name || name.startsWith('NewLevel')) { row?.remove(); renderMembershipLevelRows(collectMembershipLevels()); return; }
    if (!confirm('确定删除会员等级 ' + name + ' 吗？已有用户使用的等级不能删除。')) return;
    const res = await request('admin.php?action=delete_membership_level', 'POST', { name });
    if (!res.success) return showToast(res.message || '删除失败', 'error');
    showToast('已删除', 'success');
    renderMembershipLevelRows(Object.values(res.levels || {}));
}
function renderUpdates() {
    setTitle('系统更新');
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title"><h5>GitHub 自动更新</h5><button class="btn btn-sm btn-primary" onclick="checkUpdateStatus()"><i class="bi bi-arrow-clockwise me-1"></i>检测更新</button></div>
            <div id="updateStatusBox" class="mb-3">正在检测...</div>
            <div class="d-flex gap-2">
                <button id="runUpdateBtn" class="btn btn-success" onclick="runSystemUpdate()"><i class="bi bi-cloud-arrow-down me-1"></i>立即更新</button>
                <button class="btn btn-outline-secondary" onclick="checkUpdateStatus()">重新检测</button>
            </div>
            <pre id="updateOutput" class="log-viewer mt-3" style="min-height:220px;max-height:360px;">暂无输出</pre>
        </div>`;
    checkUpdateStatus();
}
function updateStatusHtml(status) {
    const hasUpdate = !!status.has_update;
    const siteVersionText = status.site_version ? escapeHtml(status.site_version) : '未记录';
    const siteUpdatedText = status.site_updated_at ? `<div class="small text-muted mt-1">更新时间：${escapeHtml(status.site_updated_at)}</div>` : '<div class="small text-muted mt-1">首次使用后台更新后会自动记录</div>';
    return `
        <div class="row g-3">
            <div class="col-md-6"><div class="border rounded-4 p-3"><div class="text-muted small">远程提交</div><code>${escapeHtml((status.remote_commit || '').slice(0, 12) || '-')}</code></div></div>
            <div class="col-md-6"><div class="border rounded-4 p-3"><div class="text-muted small">当前网站版本号</div><code>${siteVersionText}</code>${siteUpdatedText}</div></div>
            <div class="col-md-6"><div class="border rounded-4 p-3"><div class="text-muted small">Git 状态</div>${status.git_available ? '<span class="badge-soft success">可用</span>' : '<span class="badge-soft danger">不可用</span>'}</div></div>
            <div class="col-md-6"><div class="border rounded-4 p-3"><div class="text-muted small">更新状态</div>${hasUpdate ? '<span class="badge-soft warning">发现更新</span>' : '<span class="badge-soft success">已是最新</span>'}</div></div>
        </div>`;
}
async function checkUpdateStatus() {
    const box = document.getElementById('updateStatusBox');
    if (box) box.innerHTML = '正在检测...';
    const res = await request('admin.php?action=update_status');
    if (!res.success) {
        if (box) box.innerHTML = `<div class="alert alert-danger">${escapeHtml(res.message || '检测失败')}</div>`;
        return;
    }
    if (box) box.innerHTML = updateStatusHtml(res.status || {});
}
async function confirmSystemUpdate() {
    return new Promise(resolve => {
        const existing = document.getElementById('updateConfirmOverlay');
        if (existing) existing.remove();
        const overlay = document.createElement('div');
        overlay.id = 'updateConfirmOverlay';
        overlay.className = 'modal fade show';
        overlay.style.display = 'block';
        overlay.style.background = 'rgba(15, 23, 42, .45)';
        overlay.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-body p-4">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width:42px;height:42px;flex:0 0 42px;"><i class="bi bi-cloud-arrow-down-fill"></i></div>
                            <div class="flex-grow-1">
                                <h5 class="fw-bold mb-2">确认立即更新？</h5>
                                <p class="text-muted mb-0">将从 GitHub 拉取最新代码并覆盖当前网站代码，系统会保留数据库配置、数据目录和日志。</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 px-4 pb-4">
                        <button type="button" class="btn btn-outline-secondary" data-action="cancel">取消</button>
                        <button type="button" class="btn btn-primary" data-action="ok">确认更新</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        const close = value => { overlay.remove(); resolve(value); };
        overlay.querySelector('[data-action="cancel"]')?.addEventListener('click', () => close(false));
        overlay.querySelector('[data-action="ok"]')?.addEventListener('click', () => close(true));
        overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
    });
}
async function runSystemUpdate() {
    if (!(await confirmSystemUpdate())) return;
    const btn = document.getElementById('runUpdateBtn');
    const out = document.getElementById('updateOutput');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>更新中...'; }
    if (out) out.textContent = '正在更新，请稍候...';
    const res = await request('admin.php?action=run_update', 'POST');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-arrow-down me-1"></i>立即更新'; }
    if (!res.success) {
        if (out) out.textContent = (res.message || '更新失败') + '\n' + (res.output || '');
        showToast(res.message || '更新失败', 'error');
        return;
    }
    if (out) out.textContent = res.output || '更新完成';
    showToast('更新完成，请强制刷新浏览器缓存', 'success');
    checkUpdateStatus();
}
function renderPayments() { Admin.settingsTab = 'payment'; renderSettings(); }
function renderLogs() {
    setTitle('系统日志');
    document.getElementById('adminContent').innerHTML = `
        <div class="panel logs-panel">
            <div class="panel-title"><h5>系统日志</h5><div class="d-flex flex-wrap gap-2"><button class="btn btn-sm btn-outline-danger" onclick="clearAllLogsAdmin()">删除全部日志</button><button class="btn btn-sm btn-primary" onclick="loadAdminLog()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button></div></div>
            <div class="config-help mb-3">日志文件位于服务器 <code>logs/</code> 目录。后台只显示最近指定行数，敏感字段已自动脱敏。</div>
            <div class="log-toolbar mb-3">
                <div><label class="form-label">日志类型</label><select id="logType" class="form-select" onchange="loadAdminLog(true)"><option value="api">API 请求日志</option><option value="php_error">PHP 错误日志</option><option value="security">安全日志</option></select></div>
                <div><label class="form-label">日期</label><select id="logDate" class="form-select"></select></div>
                <div><label class="form-label">显示行数</label><select id="logLines" class="form-select"><option value="100">最近 100 行</option><option value="300" selected>最近 300 行</option><option value="500">最近 500 行</option><option value="1000">最近 1000 行</option></select></div>
                <button class="btn btn-outline-primary" onclick="loadAdminLog()">查看</button>
                <button class="btn btn-outline-secondary" onclick="copyLogContent()">复制日志</button>
            </div>
            <div id="logMeta" class="log-meta mb-2">正在加载...</div>
            <pre id="logViewer" class="log-viewer logs-page-viewer">正在加载...</pre>
        </div>`;
    loadAdminLog(true);
}
async function loadAdminLog(refreshDates = false) {
    const typeEl = document.getElementById('logType');
    const dateEl = document.getElementById('logDate');
    const linesEl = document.getElementById('logLines');
    const type = typeEl?.value || 'api';
    const lines = linesEl?.value || '300';
    const selectedDate = refreshDates ? '' : (dateEl?.value || '');
    const query = new URLSearchParams({ type, lines });
    if (selectedDate) query.set('date', selectedDate);
    const res = await request('admin.php?action=logs&' + query.toString());
    if (!res.success) {
        document.getElementById('logMeta').textContent = res.message || '读取日志失败';
        document.getElementById('logViewer').textContent = '';
        return;
    }
    if (dateEl) {
        const dates = res.dates && res.dates.length ? res.dates : [res.date];
        dateEl.innerHTML = dates.map(d => `<option value="${escapeHtml(d)}" ${d === res.date ? 'selected' : ''}>${escapeHtml(d)}</option>`).join('');
    }
    document.getElementById('logMeta').textContent = res.exists ? `${res.type}_${res.date}.log · ${Number(res.size || 0).toLocaleString()} bytes · 显示最近 ${lines} 行` : `${res.type}_${res.date}.log 暂不存在`;
    document.getElementById('logViewer').textContent = res.content || '暂无日志内容';
}
function copyLogContent() { const text = document.getElementById('logViewer')?.textContent || ''; navigator.clipboard?.writeText(text).then(() => showToast('日志已复制', 'success')).catch(() => showToast('复制失败', 'error')); }
function adminConfirm({ title = '确认操作', message = '', confirmText = '确认', cancelText = '取消', danger = false } = {}) {
    return new Promise(resolve => {
        document.getElementById('adminConfirmOverlay')?.remove();
        const overlay = document.createElement('div');
        overlay.id = 'adminConfirmOverlay';
        overlay.className = 'confirm-overlay';
        overlay.innerHTML = `
            <div class="admin-confirm" role="dialog" aria-modal="true" aria-labelledby="adminConfirmTitle">
                <div class="admin-confirm-icon ${danger ? '' : 'primary'}"><i class="bi ${danger ? 'bi-exclamation-triangle-fill' : 'bi-clipboard-check-fill'}"></i></div>
                <h5 id="adminConfirmTitle">${escapeHtml(title)}</h5>
                <p>${escapeHtml(message)}</p>
                <div class="admin-confirm-actions">
                    <button class="btn btn-light" id="adminConfirmCancel">${escapeHtml(cancelText)}</button>
                    <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" id="adminConfirmOk">${escapeHtml(confirmText)}</button>
                </div>
            </div>`;
        const close = value => { overlay.remove(); resolve(value); };
        overlay.addEventListener('click', e => { if (e.target === overlay) close(false); });
        document.addEventListener('keydown', function onKeydown(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', onKeydown);
                close(false);
            }
        });
        document.body.appendChild(overlay);
        document.getElementById('adminConfirmCancel').onclick = () => close(false);
        document.getElementById('adminConfirmOk').onclick = () => close(true);
        document.getElementById('adminConfirmOk').focus();
    });
}
async function clearAllLogsAdmin() {
    const confirmed = await adminConfirm({
        title: '清空全部日志？',
        message: '此操作会清空 logs 目录下所有 .log 文件，清空后不可恢复。',
        confirmText: '确认清空',
        cancelText: '取消',
        danger: true
    });
    if (!confirmed) return;
    const res = await request('admin.php?action=clear_logs', 'POST');
    if (!res.success) return showToast(res.message || '清空日志失败', 'error');
    showToast(res.message || '日志已清空', 'success');
    await loadAdminLog(true);
}
function renderSettings() {
    setTitle('系统设置');
    const tabs = [
        ['basic', '基础设置'],
        ['payment', '支付设置'],
        ['login', '登录注册'],
        ['agreements', '协议管理'],
        ['email', '邮箱验证'],
        ['captcha', '人机验证'],
        ['announcement', '公告设置']
    ];
    document.getElementById('adminContent').innerHTML = `
        <div class="settings-tabs">
            ${tabs.map(([id, label]) => `<button class="settings-tab ${Admin.settingsTab === id ? 'active' : ''}" onclick="switchSettingsTab('${id}')">${label}</button>`).join('')}
        </div>
        <div id="settingsContent"></div>
    `;
    renderSettingsContent();
}
async function switchSettingsTab(tab) {
    Admin.settingsTab = tab;
    saveAdminState();
    if (tab === 'payment' && !Admin.dataLoaded.payConfigs) {
        await loadAdminKeys(['payConfigs']);
    }
    renderSettings();
}
function renderSettingsContent() {
    const map = { basic: renderBasicSettings, payment: renderPaymentSettingsOnly, login: renderReservedLoginSettings, agreements: renderAgreementSettings, email: renderReservedEmailSettings, captcha: renderReservedCaptchaSettings, announcement: renderReservedAnnouncementSettings };
    (map[Admin.settingsTab] || renderBasicSettings)('settingsContent');
}
function renderBasicSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    const allowGuestPurchase = c.allow_guest_purchase !== false && c.allow_guest_purchase !== '0';
    const enableMembershipCardActivation = c.enable_membership_card_activation !== false && c.enable_membership_card_activation !== '0';
    document.getElementById(targetId).innerHTML = `
        <div class="panel settings-basic-panel">
            <div class="panel-title">
                <div>
                    <h5 class="mb-1">基础设置</h5>
                    <div class="text-muted small">维护站点信息、提现规则和游客购买策略</div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">站点名称</label>
                    <input id="setSiteName" class="form-control" value="${escapeHtml(c.site_name || 'KeyNest')}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">站点描述</label>
                    <input id="setSiteDescription" class="form-control" value="${escapeHtml(c.site_description || '')}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">最低提现金额</label>
                    <input id="setMinWithdraw" class="form-control" type="number" value="${escapeHtml(c.min_withdraw_amount || 10)}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">提现手续费比例</label>
                    <input id="setWithdrawFee" class="form-control" type="number" step="0.001" value="${escapeHtml(c.withdraw_fee_rate || 0.01)}">
                </div>
                <div class="col-lg-6">
                    <label class="admin-setting-card ${allowGuestPurchase ? 'is-on' : 'is-off'} h-100" for="setAllowGuestPurchase">
                        <span class="admin-setting-icon"><i class="bi bi-person-check"></i></span>
                        <span class="admin-setting-copy">
                            <span class="admin-setting-title">允许游客购买</span>
                            <span class="admin-setting-desc">开启后未登录用户可使用在线支付购买；关闭后前台“立即购买”会直接置灰，需要登录后才能购买。</span>
                            <span class="admin-setting-state">当前状态：${allowGuestPurchase ? '已开启' : '已关闭'}</span>
                        </span>
                        <span class="form-check form-switch admin-setting-switch">
                            <input class="form-check-input" type="checkbox" id="setAllowGuestPurchase" ${allowGuestPurchase ? 'checked' : ''}>
                        </span>
                    </label>
                </div>
                <div class="col-lg-6">
                    <label class="admin-setting-card ${enableMembershipCardActivation ? 'is-on' : 'is-off'} h-100" for="setEnableMembershipCardActivation">
                        <span class="admin-setting-icon"><i class="bi bi-credit-card-2-front"></i></span>
                        <span class="admin-setting-copy">
                            <span class="admin-setting-title">开启卡密激活会员</span>
                            <span class="admin-setting-desc">开启后前台会员中心会显示独立的“卡密激活会员”卡片；关闭后该入口隐藏，但已生成的会员卡密数据不会被删除。</span>
                            <span class="admin-setting-state">当前状态：${enableMembershipCardActivation ? '已开启' : '已关闭'}</span>
                        </span>
                        <span class="form-check form-switch admin-setting-switch">
                            <input class="form-check-input" type="checkbox" id="setEnableMembershipCardActivation" ${enableMembershipCardActivation ? 'checked' : ''}>
                        </span>
                    </label>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" onclick="saveSettings()"><i class="bi bi-check2-circle me-1"></i>保存基础设置</button>
                </div>
            </div>
        </div>`;
}
async function saveSettings() { const res = await request('finance.php?action=update_system_config', 'POST', { site_name: document.getElementById('setSiteName').value, site_description: document.getElementById('setSiteDescription').value, min_withdraw_amount: document.getElementById('setMinWithdraw').value, withdraw_fee_rate: document.getElementById('setWithdrawFee').value, allow_guest_purchase: document.getElementById('setAllowGuestPurchase')?.checked ? '1' : '0', enable_membership_card_activation: document.getElementById('setEnableMembershipCardActivation')?.checked ? '1' : '0' }); if (!res.success) return showToast(res.message || '保存失败', 'error'); showToast('保存成功', 'success'); await loadAdminData(); }
async function saveSystemConfigFields(data, successMessage = '保存成功') { const res = await request('finance.php?action=update_system_config', 'POST', data); if (!res.success) return showToast(res.message || '保存失败', 'error'); showToast(successMessage, 'success'); await loadAdminData(); }
function checkedValue(id) { return document.getElementById(id)?.checked ? '1' : '0'; }
function fieldValue(id) { return document.getElementById(id)?.value || ''; }
function markdownToHtml(markdown) {
    let html = escapeHtml(markdown || '');
    html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
    html = html.replace(/^### (.*)$/gm, '<h3>$1</h3>').replace(/^## (.*)$/gm, '<h2>$1</h2>').replace(/^# (.*)$/gm, '<h1>$1</h1>');
    html = html.replace(/^> (.*)$/gm, '<blockquote>$1</blockquote>').replace(/^---$/gm, '<hr>');
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\*(.*?)\*/g, '<em>$1</em>').replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<img src="$2" alt="$1">').replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    html = html.replace(/^(?:- |\* )(.*)$/gm, '<li>$1</li>').replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>').replace(/<\/ul>\s*<ul>/g, '');
    return html.split(/\n{2,}/).map(part => /<\/?(h\d|ul|li|pre|blockquote|hr|img)/.test(part) ? part.replace(/\n/g, '<br>') : `<p>${part.replace(/\n/g, '<br>')}</p>`).join('');
}
function updateAnnouncementPreview() { const preview = document.getElementById('announcementPreview'); if (preview) preview.innerHTML = markdownToHtml(fieldValue('announcementContent')) || '<span class="text-muted">暂无内容</span>'; }
function payMethodLabel(method) { return ({ alipay: '支付宝', wxpay: '微信支付', qqpay: 'QQ钱包', cashier: '收银台' })[method] || method; }
function methodChips(methods) { return (methods || []).map(m => `<span class="method-chip">${payMethodLabel(m)}</span>`).join('') || '<span class="text-muted small">未配置</span>'; }
function renderPaymentSettingsOnly(targetId = 'adminContent') {
    const configs = Admin.cache.payConfigs || [];
    const notifyUrl = location.origin + '/api/payment.php?action=notify';
    const returnUrl = location.origin + '/';
    document.getElementById(targetId).innerHTML = `
        <div class="panel mb-4">
            <div class="panel-title"><h5>易支付接口说明</h5><button class="btn btn-sm btn-primary" onclick="openPaymentEditor()"><i class="bi bi-plus-lg me-1"></i>新增接口</button></div>
            <div class="config-help">
                <div><strong>异步回调地址：</strong><code>${escapeHtml(notifyUrl)}</code> <button class="btn btn-sm btn-outline-primary ms-2" onclick="copyText('${notifyUrl}')">复制</button></div>
                <div class="mt-2"><strong>同步跳转地址：</strong><code>${escapeHtml(returnUrl)}</code> <button class="btn btn-sm btn-outline-primary ms-2" onclick="copyText('${returnUrl}')">复制</button></div>
                <div class="small mt-2">易支付通常使用 <code>submit.php</code> 发起支付，签名方式为 MD5。部分平台要求回调地址不能带参数，当前系统已按订单号自动反查支付配置。</div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-title"><h5>支付接口列表</h5><button class="btn btn-sm btn-outline-primary" onclick="loadAdminData()">刷新</button></div>
            <div class="table-responsive"><table class="table"><thead><tr><th>名称</th><th>网关</th><th>商户ID</th><th>方式</th><th>手续费</th><th>排序</th><th>状态</th><th>操作</th></tr></thead><tbody>${configs.map(c => `<tr><td><strong>${escapeHtml(c.name)}</strong><div class="small text-muted">${escapeHtml(c.remark || '')}</div></td><td><code>${escapeHtml(c.api_url || '-')}</code></td><td>${escapeHtml(c.partner_id || '-')}</td><td>${methodChips(c.pay_methods)}</td><td>${Number((c.fee_rate || 0) * 100).toFixed(2)}%</td><td>${c.sort_order || 0}</td><td>${c.enabled ? '<span class="badge-soft success">启用</span>' : '<span class="badge-soft danger">关闭</span>'}</td><td><button class="btn btn-sm btn-outline-primary me-1" onclick="openPaymentEditor('${c.id}')">编辑</button><button class="btn btn-sm btn-outline-danger" onclick="deletePaymentConfigAdmin('${c.id}')">删除</button></td></tr>`).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">暂无支付接口</td></tr>'}</tbody></table></div>
        </div>
        ${paymentEditorModalHtml()}`;
}
function paymentEditorModalHtml() { return `<div class="modal fade" id="adminPaymentEditorModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="adminPaymentEditorTitle">支付接口</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="adminPayId"><div class="row g-3"><div class="col-md-6"><label class="form-label">接口名称</label><input id="adminPayName" class="form-control" placeholder="例如：主易支付"></div><div class="col-md-6"><label class="form-label">接口类型</label><select id="adminPayType" class="form-select"><option value="yipay">易支付</option></select></div><div class="col-12"><label class="form-label">API网关地址</label><input id="adminPayApiUrl" class="form-control" placeholder="https://pay.example.com/"></div><div class="col-md-6"><label class="form-label">商户ID / PID</label><input id="adminPayPartnerId" class="form-control"></div><div class="col-md-6"><label class="form-label">商户密钥</label><input id="adminPayKey" class="form-control" placeholder="编辑时留空表示不修改密钥"></div><div class="col-md-4"><label class="form-label">手续费率</label><input id="adminPayFeeRate" class="form-control" type="number" step="0.001" min="0" max="1" placeholder="0.01"></div><div class="col-md-4"><label class="form-label">提交方式</label><select id="adminPaySubmitMode" class="form-select"><option value="url_redirect">URL跳转</option><option value="form_post">POST表单</option></select></div><div class="col-md-4"><label class="form-label">排序</label><input id="adminPaySortOrder" class="form-control" type="number" value="0"></div><div class="col-12"><label class="form-label">支持支付方式</label><div class="d-flex flex-wrap gap-3"><label><input type="checkbox" class="admin-pay-method" value="alipay"> 支付宝</label><label><input type="checkbox" class="admin-pay-method" value="wxpay"> 微信支付</label><label><input type="checkbox" class="admin-pay-method" value="qqpay"> QQ钱包</label><label><input type="checkbox" class="admin-pay-method" value="cashier"> 收银台</label></div></div><div class="col-12"><label class="form-label">备注</label><input id="adminPayRemark" class="form-control" placeholder="仅后台可见"></div><div class="col-12"><label><input type="checkbox" id="adminPayEnabled" checked> 启用该接口</label></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="savePaymentConfigAdmin()">保存</button></div></div></div></div>`; }
function openPaymentEditor(id = '') { const configs = Admin.cache.payConfigs || []; const c = configs.find(item => item.id === id) || {}; document.getElementById('adminPayId').value = id; document.getElementById('adminPaymentEditorTitle').textContent = id ? '编辑支付接口' : '新增支付接口'; document.getElementById('adminPayName').value = c.name || ''; document.getElementById('adminPayType').value = c.type || 'yipay'; document.getElementById('adminPayApiUrl').value = c.api_url || ''; document.getElementById('adminPayPartnerId').value = c.partner_id || ''; document.getElementById('adminPayKey').value = ''; document.getElementById('adminPayFeeRate').value = c.fee_rate ?? 0; document.getElementById('adminPaySubmitMode').value = c.submit_mode || 'url_redirect'; document.getElementById('adminPaySortOrder').value = c.sort_order || 0; document.getElementById('adminPayRemark').value = c.remark || ''; document.getElementById('adminPayEnabled').checked = c.enabled !== false; const methods = c.pay_methods || ['alipay', 'wxpay']; document.querySelectorAll('.admin-pay-method').forEach(el => el.checked = methods.includes(el.value)); new bootstrap.Modal(document.getElementById('adminPaymentEditorModal')).show(); }
async function savePaymentConfigAdmin() { const methods = Array.from(document.querySelectorAll('.admin-pay-method:checked')).map(el => el.value); const id = document.getElementById('adminPayId').value; const data = { name: document.getElementById('adminPayName').value, type: document.getElementById('adminPayType').value, api_url: document.getElementById('adminPayApiUrl').value, partner_id: document.getElementById('adminPayPartnerId').value, key: document.getElementById('adminPayKey').value, fee_rate: document.getElementById('adminPayFeeRate').value, submit_mode: document.getElementById('adminPaySubmitMode').value, sort_order: document.getElementById('adminPaySortOrder').value, remark: document.getElementById('adminPayRemark').value, enabled: document.getElementById('adminPayEnabled').checked ? '1' : '0', pay_methods: JSON.stringify(methods) }; const res = id ? await request('payment.php?action=update_config', 'POST', { ...data, id }) : await request('payment.php?action=add_config', 'POST', data); if (!res.success) return showToast(res.message || '保存失败', 'error'); showToast(res.message || '保存成功', 'success'); bootstrap.Modal.getInstance(document.getElementById('adminPaymentEditorModal'))?.hide(); await loadAdminData(); }
async function deletePaymentConfigAdmin(id) { if (!confirm('确定删除该支付接口吗？')) return; const res = await request('payment.php?action=delete_config', 'POST', { id }); if (!res.success) return showToast(res.message || '删除失败', 'error'); showToast('删除成功', 'success'); await loadAdminData(); }
function copyText(text) { navigator.clipboard?.writeText(text).then(() => showToast('已复制', 'success')).catch(() => showToast('复制失败，请手动复制', 'error')); }
function oauthCallbackUrl(provider) { return `${location.origin}/api/oauth.php?provider=${provider}`; }
function setOauthFields(prefix, enabled) { document.querySelectorAll(`[data-oauth-fields="${prefix}"] input`).forEach(el => { if (el.type !== 'checkbox') el.disabled = !enabled; }); }
function toggleOauthConfig() { setOauthFields('qq', document.getElementById('oauthQqEnabled')?.checked); setOauthFields('wechat', document.getElementById('oauthWechatEnabled')?.checked); setOauthFields('caihong', document.getElementById('oauthCaihongEnabled')?.checked); }
function oauthCard({ id, title, desc, enabled, fields, help }) { return `<div class="col-lg-4"><div class="panel h-100" style="box-shadow:none;border:1px solid #e5e7eb"><div class="d-flex justify-content-between align-items-start mb-3"><div><h6 class="mb-1">${title}</h6><div class="text-muted small">${desc}</div></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="${id}Enabled" onchange="toggleOauthConfig()" ${enabled ? 'checked' : ''}></div></div>${help ? `<div class="config-help mb-3">${help}</div>` : ''}<div data-oauth-fields="${id}" class="row g-3">${fields.join('')}</div></div></div>`; }
function oauthField(id, label, value = '', placeholder = '', type = 'text') { return `<div class="col-12"><label class="form-label">${label}</label><input id="${id}" class="form-control" type="${type}" value="${escapeHtml(value || '')}" placeholder="${escapeHtml(placeholder || '')}"></div>`; }
function renderReservedLoginSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    const qqCallback = c.oauth_qq_redirect_uri || oauthCallbackUrl('qq');
    const wechatCallback = c.oauth_wechat_redirect_uri || oauthCallbackUrl('wechat');
    const caihongCallback = c.oauth_caihong_redirect_uri || oauthCallbackUrl('caihong');
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>登录注册</h5><button class="btn btn-sm btn-primary" onclick="saveLoginSettings()">保存登录设置</button></div><div class="config-help mb-3">这里控制第三方登录以及注册/登录是否需要人机验证。发送邮箱验证码始终会强制进行人机验证。</div><div class="row g-3 mb-3"><div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="captchaLoginEnabled" ${c.captcha_login_enabled ? 'checked' : ''}><label class="form-check-label" for="captchaLoginEnabled"><strong>登录时启用人机验证</strong><span>开启后用户登录前需要先通过验证。</span></label></div></div><div class="col-md-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="captchaRegisterEnabled" ${c.captcha_register_enabled ? 'checked' : ''}><label class="form-check-label" for="captchaRegisterEnabled"><strong>注册时启用人机验证</strong><span>开启后用户提交注册前需要先通过验证。</span></label></div></div></div><div class="row g-3">${oauthCard({ id: 'oauthQq', title: 'QQ 官方登录', desc: 'QQ 互联网站应用', enabled: c.oauth_qq_enabled, help: `回调地址：<code>${escapeHtml(qqCallback)}</code> <button class="btn btn-sm btn-outline-primary ms-2" type="button" onclick="copyText('${oauthCallbackUrl('qq')}')">复制默认</button>`, fields: [oauthField('oauthQqAppId', 'App ID', c.oauth_qq_app_id, '例如 1020xxxxx'), oauthField('oauthQqAppKey', 'App Key', '', c.oauth_qq_app_key ? '已配置，留空不修改' : '填写 QQ App Key', 'password'), oauthField('oauthQqRedirectUri', '回调地址', qqCallback)] })}${oauthCard({ id: 'oauthWechat', title: '微信官方登录', desc: '微信开放平台网站应用', enabled: c.oauth_wechat_enabled, help: `回调地址：<code>${escapeHtml(wechatCallback)}</code> <button class="btn btn-sm btn-outline-primary ms-2" type="button" onclick="copyText('${oauthCallbackUrl('wechat')}')">复制默认</button>`, fields: [oauthField('oauthWechatAppId', 'App ID', c.oauth_wechat_app_id, '例如 wx123456'), oauthField('oauthWechatAppSecret', 'App Secret', '', c.oauth_wechat_app_secret ? '已配置，留空不修改' : '填写 App Secret', 'password'), oauthField('oauthWechatRedirectUri', '回调地址', wechatCallback)] })}${oauthCard({ id: 'oauthCaihong', title: '彩虹聚合登录', desc: '聚合登录平台参数', enabled: c.oauth_caihong_enabled, help: `网关、商户 ID 和通信 Key 由聚合登录服务商提供。<br>当前前台域名：<code>${escapeHtml(location.hostname)}</code><br><strong>注意：</strong>如果前台是 <code>shop.uzip.cn</code>，聚合平台白名单也要填 <code>shop.uzip.cn</code>，只填 <code>uzip.cn</code> 可能会被判定为回调域名未授权。`, fields: [oauthField('oauthCaihongApiUrl', '聚合登录网关', c.oauth_caihong_api_url, 'https://login.example.com/'), oauthField('oauthCaihongAppId', '商户 / App ID', c.oauth_caihong_app_id), oauthField('oauthCaihongKey', '通信 Key', '', c.oauth_caihong_key ? '已配置，留空不修改' : '填写通信 Key', 'password'), oauthField('oauthCaihongRedirectUri', '回调地址', caihongCallback)] })}</div></div>`;
    toggleOauthConfig();
}
function renderAgreementSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><div><h5>协议管理</h5><div class="small text-muted mt-1">用户注册必须勾选同意协议后才能提交，前台展示内容从这里读取。</div></div><button class="btn btn-sm btn-primary" onclick="saveAgreementSettings()">保存协议</button></div><div class="config-help mb-3">支持 Markdown 格式。用户协议用于注册提示；商家协议用于商品发布、销售和结算规则说明。</div><div class="row g-3"><div class="col-lg-6"><label class="form-label">用户协议标题</label><input id="userAgreementTitle" class="form-control" value="${escapeHtml(c.user_agreement_title || '用户协议')}"><label class="form-label mt-3">用户协议内容</label><textarea id="userAgreementContent" class="form-control" rows="18" oninput="updateAgreementPreview('user')">${escapeHtml(c.user_agreement_content || '')}</textarea></div><div class="col-lg-6"><label class="form-label">用户协议预览</label><div id="userAgreementPreview" class="markdown-preview" style="min-height:440px;max-height:620px;overflow:auto"></div></div><div class="col-lg-6"><label class="form-label">商家协议标题</label><input id="merchantAgreementTitle" class="form-control" value="${escapeHtml(c.merchant_agreement_title || '商家协议')}"><label class="form-label mt-3">商家协议内容</label><textarea id="merchantAgreementContent" class="form-control" rows="18" oninput="updateAgreementPreview('merchant')">${escapeHtml(c.merchant_agreement_content || '')}</textarea></div><div class="col-lg-6"><label class="form-label">商家协议预览</label><div id="merchantAgreementPreview" class="markdown-preview" style="min-height:440px;max-height:620px;overflow:auto"></div></div><div class="col-12"><button class="btn btn-primary" onclick="saveAgreementSettings()"><i class="bi bi-check2-circle me-1"></i>保存协议内容</button></div></div></div>`;
    updateAgreementPreview('user');
    updateAgreementPreview('merchant');
}
function updateAgreementPreview(type) {
    const isMerchant = type === 'merchant';
    const sourceId = isMerchant ? 'merchantAgreementContent' : 'userAgreementContent';
    const previewId = isMerchant ? 'merchantAgreementPreview' : 'userAgreementPreview';
    const preview = document.getElementById(previewId);
    if (preview) preview.innerHTML = markdownToHtml(fieldValue(sourceId)) || '<span class="text-muted">暂无内容</span>';
}
async function saveAgreementSettings() {
    await saveSystemConfigFields({
        user_agreement_title: fieldValue('userAgreementTitle'),
        user_agreement_content: fieldValue('userAgreementContent'),
        merchant_agreement_title: fieldValue('merchantAgreementTitle'),
        merchant_agreement_content: fieldValue('merchantAgreementContent')
    }, '协议内容已保存');
}
async function saveLoginSettings() {
    const c = Admin.cache.sysConfig || {};
    const qqEnabled = document.getElementById('oauthQqEnabled')?.checked;
    const wechatEnabled = document.getElementById('oauthWechatEnabled')?.checked;
    const caihongEnabled = document.getElementById('oauthCaihongEnabled')?.checked;
    if (qqEnabled && (!fieldValue('oauthQqAppId') || (!fieldValue('oauthQqAppKey') && !c.oauth_qq_app_key) || !fieldValue('oauthQqRedirectUri'))) return showToast('请填写 QQ 登录的 App ID、App Key 和回调地址', 'warning');
    if (wechatEnabled && (!fieldValue('oauthWechatAppId') || (!fieldValue('oauthWechatAppSecret') && !c.oauth_wechat_app_secret) || !fieldValue('oauthWechatRedirectUri'))) return showToast('请填写微信登录的 App ID、App Secret 和回调地址', 'warning');
    if (caihongEnabled && (!fieldValue('oauthCaihongApiUrl') || !fieldValue('oauthCaihongAppId') || (!fieldValue('oauthCaihongKey') && !c.oauth_caihong_key) || !fieldValue('oauthCaihongRedirectUri'))) return showToast('请填写彩虹聚合登录的网关、商户 ID、Key 和回调地址', 'warning');
    const data = { oauth_qq_enabled: qqEnabled ? '1' : '0', oauth_wechat_enabled: wechatEnabled ? '1' : '0', oauth_caihong_enabled: caihongEnabled ? '1' : '0', captcha_login_enabled: checkedValue('captchaLoginEnabled'), captcha_register_enabled: checkedValue('captchaRegisterEnabled'), oauth_qq_app_id: fieldValue('oauthQqAppId'), oauth_qq_redirect_uri: fieldValue('oauthQqRedirectUri'), oauth_wechat_app_id: fieldValue('oauthWechatAppId'), oauth_wechat_redirect_uri: fieldValue('oauthWechatRedirectUri'), oauth_caihong_api_url: fieldValue('oauthCaihongApiUrl'), oauth_caihong_app_id: fieldValue('oauthCaihongAppId'), oauth_caihong_redirect_uri: fieldValue('oauthCaihongRedirectUri') };
    if (fieldValue('oauthQqAppKey')) data.oauth_qq_app_key = fieldValue('oauthQqAppKey');
    if (fieldValue('oauthWechatAppSecret')) data.oauth_wechat_app_secret = fieldValue('oauthWechatAppSecret');
    if (fieldValue('oauthCaihongKey')) data.oauth_caihong_key = fieldValue('oauthCaihongKey');
    await saveSystemConfigFields(data, '登录设置已保存');
}
function normalizeAdminEmailProfiles(config = {}) {
    let profiles = config.email_profiles;
    if (typeof profiles === 'string') {
        try { profiles = JSON.parse(profiles); } catch (e) { profiles = []; }
    }
    if (!Array.isArray(profiles) || !profiles.length) {
        const fromEmail = config.resend_from_email || config.smtp_username || '';
        const hasLegacy = fromEmail || config.resend_api_key || config.smtp_host;
        if (hasLegacy) {
            profiles = [{
                id: 'legacy',
                name: '默认发信',
                enabled: true,
                provider: config.email_provider || 'smtp',
                resend_from_email: config.resend_from_email || '',
                resend_from_name: config.resend_from_name || 'KeyNest',
                resend_api_key: '',
                smtp_host: config.smtp_host || '',
                smtp_port: config.smtp_port || 465,
                smtp_username: config.smtp_username || '',
                smtp_password: '',
                smtp_secure: config.smtp_secure || 'ssl'
            }];
        } else {
            profiles = [{
                id: 'email_' + Date.now(),
                name: '发信方式 1',
                enabled: true,
                provider: 'smtp',
                resend_from_email: '',
                resend_from_name: config.resend_from_name || 'KeyNest',
                resend_api_key: '',
                smtp_host: 'smtp.qq.com',
                smtp_port: 465,
                smtp_username: '',
                smtp_password: '',
                smtp_secure: 'ssl'
            }];
        }
    }
    return profiles;
}
function emailProfileSummary(profile = {}) {
    const name = profile.name || profile.resend_from_email || profile.smtp_username || '未命名发信';
    const provider = profile.provider === 'resend' ? 'Resend' : 'SMTP';
    return `${name} · ${provider}`;
}
const EMAIL_PROFILE_COLLAPSE_KEY = 'keynest_admin_email_profile_collapsed';
const EMAIL_PROFILE_TEST_TO_KEY = 'keynest_admin_email_test_to';
function getEmailProfileCollapseMap() {
    try {
        const raw = localStorage.getItem(EMAIL_PROFILE_COLLAPSE_KEY);
        const map = raw ? JSON.parse(raw) : {};
        return map && typeof map === 'object' ? map : {};
    } catch (e) {
        return {};
    }
}
function isEmailProfileCollapsed(profileId, index = 0) {
    const map = getEmailProfileCollapseMap();
    if (Object.prototype.hasOwnProperty.call(map, profileId)) {
        return !!map[profileId];
    }
    return index > 0;
}
function setEmailProfileCollapsed(profileId, collapsed) {
    const map = getEmailProfileCollapseMap();
    map[profileId] = !!collapsed;
    localStorage.setItem(EMAIL_PROFILE_COLLAPSE_KEY, JSON.stringify(map));
}
function removeEmailProfileCollapseState(profileId) {
    const map = getEmailProfileCollapseMap();
    delete map[profileId];
    localStorage.setItem(EMAIL_PROFILE_COLLAPSE_KEY, JSON.stringify(map));
}
function emailProfileCardHtml(profile, index, collapsed = true) {
    const id = escapeHtml(profile.id || ('email_' + index));
    const provider = profile.provider || 'smtp';
    const savedTestTo = localStorage.getItem(EMAIL_PROFILE_TEST_TO_KEY) || '';
    return `
        <div class="email-profile-card border rounded-3 mb-3" data-profile-id="${id}">
            <div class="email-profile-head d-flex justify-content-between align-items-center gap-2 p-3" onclick="toggleEmailProfileCard('${id}')" style="cursor:pointer">
                <div class="min-width-0">
                    <div class="fw-semibold">发信方式 ${index + 1}</div>
                    <div class="small text-muted text-truncate">${escapeHtml(emailProfileSummary(profile))}</div>
                </div>
                <div class="d-flex align-items-center gap-2" onclick="event.stopPropagation()">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input email-profile-enabled" type="checkbox" data-profile-id="${id}" ${profile.enabled !== false ? 'checked' : ''}>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeEmailProfile('${id}')">删除</button>
                    <i class="bi bi-chevron-${collapsed ? 'down' : 'up'} text-muted"></i>
                </div>
            </div>
            <div id="emailProfileBody-${id}" class="email-profile-body border-top p-3 ${collapsed ? 'hidden' : ''}">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">配置名称</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="name" value="${escapeHtml(profile.name || '')}" placeholder="例如：QQ邮箱1"></div>
                    <div class="col-md-4"><label class="form-label">发信方式</label><select class="form-select email-profile-field email-profile-provider" data-profile-id="${id}" data-field="provider" onchange="toggleEmailProfileProvider('${id}')"><option value="smtp" ${provider === 'smtp' ? 'selected' : ''}>SMTP</option><option value="resend" ${provider === 'resend' ? 'selected' : ''}>Resend</option></select></div>
                    <div class="col-md-4"><label class="form-label">发件人邮箱 From</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="resend_from_email" value="${escapeHtml(profile.resend_from_email || '')}" placeholder="noreply@example.com"></div>
                    <div class="col-md-4"><label class="form-label">发件人名称</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="resend_from_name" value="${escapeHtml(profile.resend_from_name || 'KeyNest')}"></div>
                    <div class="col-md-8 email-profile-resend-${id} ${provider === 'resend' ? '' : 'hidden'}"><label class="form-label">Resend API Key</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="resend_api_key" type="password" placeholder="re_xxxx；留空表示不修改"></div>
                    <div class="col-md-4 email-profile-smtp-${id} ${provider === 'smtp' ? '' : 'hidden'}"><label class="form-label">SMTP 主机</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="smtp_host" value="${escapeHtml(profile.smtp_host || '')}" placeholder="smtp.qq.com"></div>
                    <div class="col-md-2 email-profile-smtp-${id} ${provider === 'smtp' ? '' : 'hidden'}"><label class="form-label">端口</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="smtp_port" type="number" value="${escapeHtml(profile.smtp_port || 465)}"></div>
                    <div class="col-md-2 email-profile-smtp-${id} ${provider === 'smtp' ? '' : 'hidden'}"><label class="form-label">加密</label><select class="form-select email-profile-field" data-profile-id="${id}" data-field="smtp_secure"><option value="ssl" ${profile.smtp_secure === 'ssl' ? 'selected' : ''}>SSL</option><option value="tls" ${profile.smtp_secure === 'tls' ? 'selected' : ''}>TLS</option><option value="none" ${profile.smtp_secure === 'none' ? 'selected' : ''}>无</option></select></div>
                    <div class="col-md-4 email-profile-smtp-${id} ${provider === 'smtp' ? '' : 'hidden'}"><label class="form-label">SMTP 账号</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="smtp_username" value="${escapeHtml(profile.smtp_username || '')}"></div>
                    <div class="col-md-6 email-profile-smtp-${id} ${provider === 'smtp' ? '' : 'hidden'}"><label class="form-label">SMTP 密码 / 授权码</label><input class="form-control email-profile-field" data-profile-id="${id}" data-field="smtp_password" type="password" placeholder="留空表示不修改"></div>
                    <div class="col-12 mt-1 pt-3 border-top">
                        <label class="form-label mb-1">测试此发信配置</label>
                        <div class="input-group">
                            <input class="form-control email-profile-test-to" data-profile-id="${id}" value="${escapeHtml(savedTestTo)}" placeholder="输入收件邮箱，仅测试当前这一条" oninput="localStorage.setItem(EMAIL_PROFILE_TEST_TO_KEY, this.value)">
                            <button class="btn btn-outline-primary" type="button" onclick="testEmailProfile('${id}')">测试发送</button>
                        </div>
                        <div class="config-help mt-1">只使用本条配置发送测试邮件，不会轮番切换其他邮箱。</div>
                    </div>
                </div>
            </div>
        </div>`;
}
function toggleEmailProfileCard(id) {
    const body = document.getElementById('emailProfileBody-' + id);
    const card = document.querySelector(`.email-profile-card[data-profile-id="${id}"]`);
    if (!body || !card) return;
    body.classList.toggle('hidden');
    setEmailProfileCollapsed(id, body.classList.contains('hidden'));
    const icon = card.querySelector('.bi-chevron-down, .bi-chevron-up');
    if (icon) {
        icon.classList.toggle('bi-chevron-down', body.classList.contains('hidden'));
        icon.classList.toggle('bi-chevron-up', !body.classList.contains('hidden'));
    }
}
function toggleEmailProfileProvider(id) {
    const provider = document.querySelector(`.email-profile-provider[data-profile-id="${id}"]`)?.value || 'smtp';
    document.querySelectorAll(`.email-profile-smtp-${id}`).forEach(el => el.classList.toggle('hidden', provider !== 'smtp'));
    document.querySelectorAll(`.email-profile-resend-${id}`).forEach(el => el.classList.toggle('hidden', provider !== 'resend'));
}
function addEmailProfileRow() {
    const list = document.getElementById('emailProfilesList');
    if (!list) return;
    const count = list.querySelectorAll('.email-profile-card').length + 1;
    const profile = {
        id: 'email_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
        name: '发信方式 ' + count,
        enabled: true,
        provider: 'smtp',
        resend_from_email: '',
        resend_from_name: fieldValue('resendFromNameGlobal') || 'KeyNest',
        resend_api_key: '',
        smtp_host: 'smtp.qq.com',
        smtp_port: 465,
        smtp_username: '',
        smtp_password: '',
        smtp_secure: 'ssl'
    };
    list.insertAdjacentHTML('beforeend', emailProfileCardHtml(profile, count - 1, false));
    setEmailProfileCollapsed(profile.id, false);
}
function removeEmailProfile(id) {
    const card = document.querySelector(`.email-profile-card[data-profile-id="${id}"]`);
    if (!card) return;
    const list = document.getElementById('emailProfilesList');
    if (list && list.querySelectorAll('.email-profile-card').length <= 1) {
        return showToast('至少保留一个发信配置', 'warning');
    }
    removeEmailProfileCollapseState(id);
    card.remove();
}
function collectEmailProfilesFromForm() {
    const cards = Array.from(document.querySelectorAll('.email-profile-card'));
    return cards.map(card => {
        const id = card.dataset.profileId || '';
        const read = field => card.querySelector(`.email-profile-field[data-profile-id="${id}"][data-field="${field}"]`)?.value ?? '';
        return {
            id,
            name: read('name').trim(),
            enabled: card.querySelector(`.email-profile-enabled[data-profile-id="${id}"]`)?.checked !== false,
            provider: read('provider') || 'smtp',
            resend_from_email: read('resend_from_email').trim(),
            resend_from_name: read('resend_from_name').trim() || 'KeyNest',
            resend_api_key: read('resend_api_key').trim(),
            smtp_host: read('smtp_host').trim(),
            smtp_port: Number(read('smtp_port') || 465),
            smtp_username: read('smtp_username').trim(),
            smtp_password: read('smtp_password'),
            smtp_secure: read('smtp_secure') || 'ssl'
        };
    });
}
function defaultEmailTemplateHtml() {
    return `<div style="margin:0;padding:28px;background:#f3f6fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Arial,sans-serif;color:#1f2937"><div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:22px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,.12);border:1px solid #e5e7eb"><div style="padding:26px 30px;background:linear-gradient(135deg,#6d5dfc,#8b5cf6);color:#fff"><div style="font-size:14px;opacity:.9">{{site_name}}</div><div style="font-size:24px;font-weight:800;margin-top:6px">{{title}}</div></div><div style="padding:30px"><p style="margin:0 0 14px;font-size:15px;line-height:1.8;color:#4b5563">{{message}}</p><div style="margin:22px 0;padding:20px;border-radius:18px;background:#f8fafc;border:1px dashed #c7d2fe;text-align:center"><div style="font-size:13px;color:#64748b;margin-bottom:8px">验证码</div><div style="font-size:34px;letter-spacing:8px;font-weight:900;color:#4f46e5">{{code}}</div></div><p style="margin:0;font-size:13px;line-height:1.8;color:#94a3b8">{{footer}}</p></div></div></div>`;
}
function emailTemplateSampleHtml(template) {
    return (template || defaultEmailTemplateHtml()).replaceAll('{{site_name}}', escapeHtml(Admin.cache.sysConfig?.site_name || 'KeyNest')).replaceAll('{{title}}', '邮箱验证码测试').replaceAll('{{message}}', '如果你收到这封邮件，说明邮箱发送配置已经成功。').replaceAll('{{code}}', '123456').replaceAll('{{ttl}}', '10').replaceAll('{{footer}}', '验证码 10 分钟内有效。如果不是你本人操作，请忽略本邮件。').replaceAll('{{time}}', new Date().toLocaleString());
}
function updateEmailTemplatePreview() {
    const box = document.getElementById('emailTemplatePreview');
    if (box) box.innerHTML = emailTemplateSampleHtml(fieldValue('emailTemplateHtml'));
}
function resetEmailTemplateHtml() {
    const el = document.getElementById('emailTemplateHtml');
    if (!el) return;
    el.value = defaultEmailTemplateHtml();
    updateEmailTemplatePreview();
}
function renderReservedEmailSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    const profiles = normalizeAdminEmailProfiles(c);
    const template = c.email_template_html || defaultEmailTemplateHtml();
    const lastError = c.email_last_error || '';
    const lastErrorAt = c.email_last_error_at ? dateText(c.email_last_error_at) : '';
    document.getElementById(targetId).innerHTML = `
        <div class="panel">
            <div class="panel-title"><h5>邮箱验证</h5><button class="btn btn-sm btn-primary" onclick="saveEmailSettings()">保存邮箱设置</button></div>
            <div class="config-help mb-3">可配置多个发信邮箱，系统会按顺序轮番发送；某个邮箱失败会自动切换下一个，并在下方显示报错提示。每个配置可折叠管理。</div>
            ${lastError ? `<div class="alert alert-danger py-2 small mb-3"><strong>最近发信异常：</strong>${escapeHtml(lastError)}${lastErrorAt ? `<div class="mt-1 text-muted">时间：${escapeHtml(lastErrorAt)}</div>` : ''}</div>` : ''}
            <div class="row g-3 mb-3">
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="emailVerifyEnabled" ${c.register_email_verify_enabled ? 'checked' : ''}><label class="form-check-label" for="emailVerifyEnabled">注册时启用邮箱验证码</label></div></div>
                <div class="col-md-4"><label class="form-label">验证码有效期（分钟）</label><input id="emailCodeTtl" class="form-control" type="number" min="1" max="60" value="${escapeHtml(c.email_code_ttl || 10)}"></div>
                <div class="col-md-4"><label class="form-label">默认发件人名称</label><input id="resendFromNameGlobal" class="form-control" value="${escapeHtml(c.resend_from_name || 'KeyNest')}"></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">发信方式列表</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addEmailProfileRow()"><i class="bi bi-plus-circle me-1"></i>新增发信方式</button>
            </div>
            <div id="emailProfilesList">${profiles.map((p, i) => emailProfileCardHtml(p, i, isEmailProfileCollapsed(p.id || ('email_' + i), i))).join('')}</div>
            <div class="row g-3 align-items-stretch mt-3">
                <div class="col-lg-6">
                    <div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">验证码邮件卡片 HTML</label><button class="btn btn-sm btn-outline-secondary" type="button" onclick="resetEmailTemplateHtml()">恢复默认卡片</button></div>
                    <textarea id="emailTemplateHtml" class="form-control" rows="16" oninput="updateEmailTemplatePreview()">${escapeHtml(template)}</textarea>
                    <div class="config-help mt-2">可用变量：<code>{{site_name}}</code> <code>{{title}}</code> <code>{{message}}</code> <code>{{code}}</code> <code>{{ttl}}</code> <code>{{footer}}</code> <code>{{time}}</code></div>
                </div>
                <div class="col-lg-6"><label class="form-label">实时预览</label><div id="emailTemplatePreview" style="background:#eef2f7;border:1px solid #e5e7eb;border-radius:18px;padding:18px;min-height:430px;max-height:520px;overflow:auto"></div></div>
            </div>
        </div>`;
    updateEmailTemplatePreview();
}
async function saveEmailSettingsData() {
    return request('finance.php?action=update_system_config', 'POST', {
        register_email_verify_enabled: checkedValue('emailVerifyEnabled'),
        email_code_ttl: fieldValue('emailCodeTtl'),
        resend_from_name: fieldValue('resendFromNameGlobal'),
        email_template_html: fieldValue('emailTemplateHtml'),
        email_profiles: JSON.stringify(collectEmailProfilesFromForm())
    });
}
async function saveEmailSettings() {
    const res = await saveEmailSettingsData();
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    showToast('邮箱设置已保存', 'success');
    await loadAdminData();
    renderReservedEmailSettings();
}
async function testEmailProfile(profileId) {
    const to = document.querySelector(`.email-profile-test-to[data-profile-id="${profileId}"]`)?.value?.trim() || '';
    if (!to) return showToast('请输入测试收件邮箱', 'warning');
    localStorage.setItem(EMAIL_PROFILE_TEST_TO_KEY, to);
    const saveRes = await saveEmailSettingsData();
    if (!saveRes.success) return showToast(saveRes.message || '保存邮箱设置失败', 'error');
    const res = await request('admin.php?action=test_email', 'POST', { email: to, profile_id: profileId });
    if (!res.success) return showToast(res.message || '测试发送失败', 'error');
    showToast((res.message || '测试邮件已发送') + (res.used_profile ? `（${res.used_profile}）` : ''), 'success');
    await loadAdminData();
    renderReservedEmailSettings();
}
function toggleEmailProviderFields() {}
function renderReservedCaptchaSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>人机验证</h5><button class="btn btn-sm btn-primary" onclick="saveCaptchaSettings()">保存验证设置</button></div><div class="config-help mb-3">已接入 Cloudflare Turnstile 和极验行为验证 v3：发送邮箱验证码每次都会强制验证；登录/注册是否验证请到“登录注册”页签分别开启。极验请填写 Captcha ID 到 Site Key，Private Key 到 Secret Key；扩展配置可填 JSON，例如 {&quot;product&quot;:&quot;bind&quot;,&quot;lang&quot;:&quot;zh-cn&quot;}。</div><div class="row g-3"><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="captchaEnabled" ${c.captcha_enabled ? 'checked' : ''}><label class="form-check-label" for="captchaEnabled">启用全站人机验证能力</label></div></div><div class="col-md-4"><label class="form-label">服务商</label><select id="captchaProvider" class="form-select" onchange="updateCaptchaProviderLink()"><option value="turnstile" ${c.captcha_provider === 'turnstile' ? 'selected' : ''}>Cloudflare Turnstile</option><option value="recaptcha_v3" ${c.captcha_provider === 'recaptcha_v3' ? 'selected' : ''}>Google reCAPTCHA v3（仅保存参数）</option><option value="geetest_v3" ${c.captcha_provider === 'geetest_v3' || c.captcha_provider === 'behavior_v3' ? 'selected' : ''}>极验行为验证 v3</option><option value="aliyun" ${c.captcha_provider === 'aliyun' ? 'selected' : ''}>阿里云验证码（仅保存参数）</option><option value="tencent" ${c.captcha_provider === 'tencent' ? 'selected' : ''}>腾讯验证码（仅保存参数）</option></select></div><div class="col-md-8"><label class="form-label">服务商官网</label><div id="captchaProviderLink" class="config-help py-2"></div></div><div class="col-md-4"><label class="form-label">Site Key / Captcha ID</label><input id="captchaSiteKey" class="form-control" value="${escapeHtml(c.captcha_site_key || '')}" placeholder="前端公开 key"></div><div class="col-md-4"><label class="form-label">Secret Key</label><input id="captchaSecretKey" class="form-control" type="password" placeholder="留空表示不修改"></div><div class="col-12"><label class="form-label">校验接口/额外配置（可选）</label><textarea id="captchaExtraConfig" class="form-control" rows="3" placeholder='例如 {"endpoint":"https://..."}'>${escapeHtml(c.captcha_extra_config || '')}</textarea></div></div></div>`;
    updateCaptchaProviderLink();
}
function captchaProviderInfo(provider) {
    const map = {
        turnstile: { name: 'Cloudflare Turnstile', url: 'https://www.cloudflare.com/products/turnstile/' },
        recaptcha_v3: { name: 'Google reCAPTCHA v3', url: 'https://www.google.com/recaptcha/admin/create' },
        geetest_v3: { name: '极验行为验证', url: 'https://www.geetest.com/' },
        aliyun: { name: '阿里云验证码', url: 'https://www.aliyun.com/product/captcha' },
        tencent: { name: '腾讯验证码', url: 'https://cloud.tencent.com/product/captcha' }
    };
    return map[provider] || map.turnstile;
}
function updateCaptchaProviderLink() {
    const box = document.getElementById('captchaProviderLink');
    if (!box) return;
    const info = captchaProviderInfo(fieldValue('captchaProvider') || 'turnstile');
    box.innerHTML = `<span class="text-muted me-2">当前选择：</span><strong>${escapeHtml(info.name)}</strong><a class="btn btn-sm btn-outline-primary ms-3" href="${escapeHtml(info.url)}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1"></i>打开官网</a>`;
}
async function saveCaptchaSettings() { await saveSystemConfigFields({ captcha_enabled: checkedValue('captchaEnabled'), captcha_provider: fieldValue('captchaProvider'), captcha_site_key: fieldValue('captchaSiteKey'), captcha_secret_key: fieldValue('captchaSecretKey'), captcha_extra_config: fieldValue('captchaExtraConfig') }, '人机验证设置已保存'); }
function renderReservedAnnouncementSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>公告设置</h5><button class="btn btn-sm btn-primary" onclick="saveAnnouncementSettings()">保存公告</button></div><div class="config-help mb-3">公告支持 Markdown 格式。可用于首页公告、弹窗内容或后台通知，开启后前台即可读取配置展示。</div><div class="row g-3"><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="announcementEnabled" ${c.announcement_enabled ? 'checked' : ''}><label class="form-check-label" for="announcementEnabled">启用公告</label></div></div><div class="col-md-6"><label class="form-label">公告标题</label><input id="announcementTitle" class="form-control" value="${escapeHtml(c.announcement_title || '')}" placeholder="例如：平台维护通知"></div><div class="col-md-6"><label class="form-label">展示位置</label><select id="announcementPosition" class="form-select"><option value="home" ${c.announcement_position === 'home' ? 'selected' : ''}>首页公告</option><option value="modal" ${c.announcement_position === 'modal' ? 'selected' : ''}>弹窗公告</option><option value="both" ${c.announcement_position === 'both' ? 'selected' : ''}>首页 + 弹窗</option></select></div><div class="col-lg-6"><label class="form-label">Markdown 内容</label><textarea id="announcementContent" class="form-control" rows="12" oninput="updateAnnouncementPreview()" placeholder="# 公告标题&#10;&#10;- 支持列表&#10;- 支持 **加粗**、链接、代码块">${escapeHtml(c.announcement_content || '')}</textarea></div><div class="col-lg-6"><label class="form-label">实时预览</label><div id="announcementPreview" class="markdown-preview"></div></div></div></div>`;
    updateAnnouncementPreview();
}
async function saveAnnouncementSettings() { const position = fieldValue('announcementPosition'); await saveSystemConfigFields({ announcement_enabled: checkedValue('announcementEnabled'), announcement_popup_enabled: (position === 'modal' || position === 'both') ? '1' : '0', announcement_title: fieldValue('announcementTitle'), announcement_position: position, announcement_content: fieldValue('announcementContent') }, '公告设置已保存'); }

document.addEventListener('keydown', e => { if (e.key === 'Enter' && !document.getElementById('loginView').classList.contains('hidden')) adminLogin(); });
bootstrapAdmin();
</script>
</body>
</html>
