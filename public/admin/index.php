<?php
require_once dirname(__DIR__, 2) . '/config/install.php';
keynest_require_installed(false);
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
        .admin-shell { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
        .sidebar { background: #0f172a; color: #fff; padding: 26px 20px; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
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
        .user-pill { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff; border: 1px solid var(--border); border-radius: 999px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
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
        .order-status-editor-row td { padding-top: 0; border-top: 0; }
        .order-status-editor { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin: 0 0 8px; padding: 14px; border: 1px dashed #cbd5e1; border-radius: 18px; background: #f8fafc; }
        .order-status-option { border: 1px solid var(--border); background: #fff; border-radius: 999px; padding: 8px 13px; font-weight: 800; color: #475569; }
        .order-status-option.active { border-color: var(--primary); color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary2)); }
        .order-status-option:not(.active):hover { border-color: #c7d2fe; background: #eef2ff; color: #3730a3; }
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
        .complaint-card-admin { border: 1px solid var(--border); border-radius: 18px; padding: 16px; background: #fff; box-shadow: 0 10px 28px rgba(15,23,42,.05); margin-bottom: 14px; }
        .complaint-reason-admin { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 14px; padding: 12px; white-space: pre-wrap; word-break: break-word; color: #334155; }
        .complaint-grid-admin { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .complaint-meta-admin { background: #f8fafc; border-radius: 14px; padding: 12px; }
        @media (max-width: 1100px) { .complaint-grid-admin { grid-template-columns: 1fr; } }
        .logs-panel { margin-bottom: 28px; }
        .log-meta { color: var(--muted); font-size: .86rem; }
        @media (max-width: 980px) { .admin-shell { grid-template-columns: 1fr; } .sidebar { position: relative; height: auto; } .content { padding: 20px; } .topbar { align-items: flex-start; flex-direction: column; } .log-viewer.logs-page-viewer { height: 420px; max-height: 55vh; } }
    </style>
</head>
<body>
<div id="toastBox" class="toast-box"></div>

<section id="loginView" class="login-wrap hidden">
    <div class="login-card">
        <div class="login-logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h2 class="fw-bold mb-1">管理员登录</h2>
        <p class="text-muted mb-4">请输入管理员账号密码进入 KeyNest 后台。</p>
        <div id="adminLoginError" class="alert alert-danger py-2 small hidden"></div>
        <div class="mb-3">
            <label class="form-label fw-semibold">用户名</label>
            <input id="adminUsername" class="form-control form-control-lg" placeholder="admin" autocomplete="username">
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
        <button class="side-link" data-page="orders" onclick="switchAdminPage('orders')"><i class="bi bi-receipt-cutoff"></i>订单记录</button>
        <button class="side-link" data-page="complaints" onclick="switchAdminPage('complaints')"><i class="bi bi-exclamation-octagon-fill"></i>投诉管理</button>
        <button class="side-link" data-page="finance" onclick="switchAdminPage('finance')"><i class="bi bi-wallet2"></i>充值提现</button>
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
            <div class="user-pill">
                <div class="avatar" id="adminAvatar">A</div>
                <div><div class="fw-bold" id="adminName">admin</div><div class="small text-muted">超级管理员</div></div>
            </div>
        </div>
        <div id="adminContent"></div>
    </main>
</section>

<script>
const Admin = { user: null, page: 'overview', settingsTab: 'basic', cache: {} };
const apiBase = '/api/';

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
    if (data) options.body = new URLSearchParams(data).toString();
    try {
        const res = await fetch(apiBase + endpoint, options);
        const text = await res.text();
        let json = {};
        try {
            json = text ? JSON.parse(text) : {};
        } catch (e) {
            const preview = text ? text.slice(0, 1000) : '空响应';
            return { success: false, message: `服务器返回异常内容（HTTP ${res.status}）：${preview}` };
        }
        if (!res.ok) {
            const detail = json.output ? '\n' + json.output : '';
            return { success: false, message: (json.message || ('请求失败：' + res.status)) + detail, status: res.status, ...json };
        }
        return json;
    } catch (e) {
        return { success: false, message: '网络错误，请检查服务器是否正常：' + (e.message || e) };
    }
}
async function bootstrapAdmin() {
    const result = await request('auth.php?action=get_current_user');
    if (!result.success || !result.logged_in) return showLogin(result.message || '请先登录管理员账号');
    if (!result.user || result.user.role !== 'admin') return showLogin('当前账号不是管理员，请使用管理员账号登录。');
    Admin.user = result.user;
    restoreAdminState();
    showAdmin();
    await loadAdminData();
}
function showLogin(message = '') {
    document.getElementById('adminView').classList.add('hidden');
    document.getElementById('loginView').classList.remove('hidden');
    const box = document.getElementById('adminLoginError');
    if (message) { box.textContent = message; box.classList.remove('hidden'); } else { box.classList.add('hidden'); }
}
function showAdmin() {
    document.getElementById('loginView').classList.add('hidden');
    document.getElementById('adminView').classList.remove('hidden');
    document.getElementById('adminName').textContent = Admin.user.username;
    document.getElementById('adminAvatar').textContent = Admin.user.username.charAt(0).toUpperCase();
}
function restoreAdminState() {
    const validPages = ['overview', 'users', 'products', 'orders', 'complaints', 'finance', 'cards', 'settings', 'membership', 'updates', 'logs'];
    const validSettingsTabs = ['basic', 'payment', 'login', 'email', 'captcha', 'announcement'];
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
        errorBox.textContent = '请填写用户名和密码';
        errorBox.classList.remove('hidden');
        return;
    }
    const btn = document.getElementById('adminLoginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>登录中...';
    const result = await request('auth.php?action=login', 'POST', { username, password });
    btn.disabled = false;
    btn.textContent = '登录后台';
    if (!result.success) {
        errorBox.textContent = result.message || '登录失败，请检查用户名和密码';
        errorBox.classList.remove('hidden');
        showToast(errorBox.textContent, 'error');
        return;
    }
    if (!result.user || result.user.role !== 'admin') {
        await request('auth.php?action=logout', 'POST');
        errorBox.textContent = '该账号不是管理员，无法进入后台。';
        errorBox.classList.remove('hidden');
        showToast('该账号不是管理员', 'error');
        return;
    }
    Admin.user = result.user;
    showToast('登录成功', 'success');
    showAdmin();
    await loadAdminData();
}
async function adminLogout() {
    await request('auth.php?action=logout', 'POST');
    Admin.user = null;
    showLogin('已退出登录');
}
async function loadAdminData() {
    const [users, products, payOrders, requests, cards, payConfigs, sysConfig, complaints] = await Promise.all([
        request('admin.php?action=users'),
        request('product.php?action=list&stock_min=0'),
        request('payment.php?action=get_orders'),
        request('finance.php?action=all_requests'),
        request('card.php?action=list'),
        request('payment.php?action=get_configs'),
        request('finance.php?action=get_system_config'),
        request('admin.php?action=complaints')
    ]);
    Admin.cache = {
        users: users.users || [],
        products: products.products || [],
        payOrders: payOrders.orders || [],
        requests: requests.requests || [],
        cards: cards.cards || [],
        payConfigs: payConfigs.configs || [],
        complaints: complaints.complaints || [],
        membershipLevels: {},
        sysConfig: sysConfig.config || {}
    };
    renderPage();
}
function switchAdminPage(page, settingsTab = null) {
    Admin.page = page;
    if (settingsTab) Admin.settingsTab = settingsTab;
    saveAdminState();
    updateAdminNavActive(settingsTab);
    renderPage();
}
function setTitle(title) { document.getElementById('pageTitle').textContent = title; }
function renderPage() {
    const renderers = { overview: renderOverview, users: renderUsers, products: renderProducts, orders: renderOrders, complaints: renderComplaints, finance: renderFinance, cards: renderCards, payments: renderPayments, settings: renderSettings, membership: renderMembershipAdmin, updates: renderUpdates, logs: renderLogs     };
    updateAdminNavActive(Admin.page === 'settings' && Admin.settingsTab === 'payment' ? 'payment' : null);
    (renderers[Admin.page] || renderOverview)();
}
function renderOverview() {
    setTitle('后台总览');
    const users = Admin.cache.users || [], products = Admin.cache.products || [], orders = Admin.cache.payOrders || [], requests = Admin.cache.requests || [], cards = Admin.cache.cards || [], complaints = Admin.cache.complaints || [];
    const pending = requests.filter(r => r.status === 'pending').length;
    document.getElementById('adminContent').innerHTML = `
        <div class="row g-3 mb-4">
            ${stat('bi-people-fill', '#dbeafe', '#1d4ed8', users.length, '用户总数')}
            ${stat('bi-box-seam-fill', '#ede9fe', '#6d28d9', products.length, '商品总数')}
            ${stat('bi-cash-stack', '#dcfce7', '#15803d', orders.length, '支付订单')}
            ${stat('bi-exclamation-octagon-fill', '#fee2e2', '#b91c1c', complaints.filter(o => (o.complaint?.status || '') === 'open').length, '进行中投诉')}
            ${stat('bi-hourglass-split', '#fef3c7', '#b45309', pending, '待处理申请')}
        </div>
        <div class="row g-4">
            <div class="col-lg-7"><div class="panel"><div class="panel-title"><h5>最新用户</h5><button class="btn btn-sm btn-outline-primary" onclick="switchAdminPage('users')">查看全部</button></div>${userTable(users.slice(-6).reverse())}</div></div>
            <div class="col-lg-5"><div class="panel"><div class="panel-title"><h5>待处理申请</h5><button class="btn btn-sm btn-outline-primary" onclick="switchAdminPage('finance')">处理</button></div>${requestList(requests.filter(r => r.status === 'pending').slice(0, 6))}</div></div>
        </div>`;
}
function stat(icon, bg, color, value, label) { return `<div class="col-md-6 col-xl-3"><div class="stat-card"><div class="stat-icon" style="background:${bg};color:${color}"><i class="bi ${icon}"></i></div><div class="stat-value">${value}</div><div class="stat-label">${label}</div></div></div>`; }
function renderUsers() {
    setTitle('用户管理');
    const keyword = (document.getElementById('userSearchInput')?.value || '').trim().toLowerCase();
    const users = Admin.cache.users || [];
    const filteredUsers = keyword ? users.filter(u =>
        String(u.username || '').toLowerCase().includes(keyword) ||
        String(u.email || '').toLowerCase().includes(keyword)
    ) : users;
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div>
                    <h5>全部用户</h5>
                    <div class="small text-muted mt-1">${keyword ? '已筛选 ' + filteredUsers.length + ' / ' + users.length + ' 个用户' : '共 ' + users.length + ' 个用户'}</div>
                </div>
                <button class="btn btn-sm btn-primary" onclick="loadAdminData()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-7 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input id="userSearchInput" class="form-control" placeholder="搜索用户名或邮箱" value="${escapeHtml(keyword)}" oninput="renderUsers()" autocomplete="off">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-secondary" onclick="clearUserSearch()" ${keyword ? '' : 'disabled'}>清空</button>
                </div>
            </div>
            ${userTable(filteredUsers, true)}
        </div>`;
    if (keyword) {
        const input = document.getElementById('userSearchInput');
        input?.focus();
        input?.setSelectionRange(input.value.length, input.value.length);
    }
}
function clearUserSearch() {
    const input = document.getElementById('userSearchInput');
    if (input) input.value = '';
    renderUsers();
}
function userTable(users, withActions = false) {
    if (!users.length) return '<div class="text-muted py-4 text-center">暂无用户</div>';
    const actionHead = withActions ? '<th>操作</th>' : '';
    const actionCol = u => withActions ? `<td><button class="btn btn-sm btn-outline-primary me-1" onclick="openUserEditor('${escapeHtml(u.id)}')">编辑</button><button class="btn btn-sm btn-outline-danger" onclick="deleteUserAdmin('${escapeHtml(u.id)}')" ${u.username === 'admin' ? 'disabled title="admin 禁止删除"' : ''}>删除</button></td>` : '';
    return `<div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>邮箱</th><th>角色</th><th>会员</th><th>余额</th><th>注册时间</th>${actionHead}</tr></thead><tbody>${users.map(u => `<tr><td><strong>${escapeHtml(u.username)}</strong></td><td>${escapeHtml(u.email || '-')}</td><td>${u.role === 'admin' ? '<span class="badge-soft info">管理员</span>' : '<span class="badge-soft success">用户</span>'}</td><td>${escapeHtml(u.membership_level || 'Free')}</td><td>${money(u.balance)}</td><td>${dateText(u.created_at)}</td>${actionCol(u)}</tr>`).join('')}</tbody></table></div>`;
}
function membershipOptionsForUser(selected) {
    const levels = Object.values(Admin.cache.membershipLevels || {});
    const list = levels.length ? levels : [{ name: 'Free' }, { name: 'VIP' }, { name: 'PRO' }, { name: 'Infinite' }];
    return list.map(level => `<option value="${escapeHtml(level.name)}" ${level.name === selected ? 'selected' : ''}>${escapeHtml(level.name)}</option>`).join('');
}
function openUserEditor(id) {
    const user = (Admin.cache.users || []).find(u => u.id === id);
    if (!user) return showToast('用户不存在', 'error');
    const modalId = 'userEditorModal';
    document.getElementById(modalId)?.remove();
    const isAdminRoot = user.username === 'admin';
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
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="saveUserAdmin()">保存</button></div>
            </div>
        </div>`;
    document.body.appendChild(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
}
async function saveUserAdmin() {
    const roleEl = document.getElementById('editRole');
    const payload = {
        id: document.getElementById('editUserId').value,
        username: document.getElementById('editUsername').value.trim(),
        email: document.getElementById('editEmail').value.trim(),
        role: roleEl?.value || 'admin',
        membership_level: document.getElementById('editMembership').value,
        balance: document.getElementById('editBalance').value
    };
    const res = await request('admin.php?action=update_user', 'POST', payload);
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    showToast('用户信息已保存', 'success');
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
                                <td><strong>${escapeHtml(p.title)}</strong><div class="small text-muted"><code>${escapeHtml(p.id)}</code></div></td>
                                <td>${escapeHtml(p.seller_name || '-')}</td>
                                <td>${escapeHtml(p.category || '-')}</td>
                                <td>${money(p.price)}</td>
                                <td>${p.stock || 0}</td>
                                <td>${p.sales || 0}</td>
                                <td class="text-end">
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
        product_sale_income: '商品销售收入',
        publish_fee: '发布扣费',
        admin_balance_adjust: '后台调整'
    };
    return map[type] || payType || type || '-';
}
function complaintStatusBadge(status) {
    const map = { open: ['warning', '处理中'], processing: ['info', '跟进中'], resolved: ['success', '已解决'], rejected: ['danger', '已驳回'], withdrawn: ['info', '已撤诉'] };
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
        complaints = complaints.filter(o => [o.id, o.product_title, o.buyer_name, o.seller_name, o.complaint?.reason].some(v => String(v || '').toLowerCase().includes(keyword)));
    }
    const openCount = (Admin.cache.complaints || []).filter(o => (o.complaint?.status || '') === 'open').length;
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <div><h5>投诉管理</h5><div class="small text-muted mt-1">共 ${Admin.cache.complaints?.length || 0} 条，进行中 ${openCount} 条</div></div>
                <button class="btn btn-sm btn-primary" onclick="loadAdminData()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4"><input id="complaintSearchInput" class="form-control" placeholder="搜索订单/商品/买家/卖家/原因" value="${escapeHtml(keyword)}" oninput="renderComplaints()"></div>
                <div class="col-md-3">
                    <select id="complaintStatusFilter" class="form-select" onchange="renderComplaints()">
                        ${[['all','全部状态'],['open','处理中'],['processing','跟进中'],['resolved','已解决'],['rejected','已驳回'],['withdrawn','已撤诉']].map(([v,t]) => `<option value="${v}" ${status === v ? 'selected' : ''}>${t}</option>`).join('')}
                    </select>
                </div>
            </div>
            ${complaints.length ? complaints.map(renderComplaintAdminCard).join('') : '<div class="text-muted text-center py-5">暂无投诉记录</div>'}
        </div>`;
}
function renderComplaintAdminCard(order) {
    const complaint = order.complaint || {};
    return `
        <div class="complaint-card-admin">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <div class="fw-bold fs-6">${escapeHtml(order.product_title || '-')}</div>
                    <div class="text-muted small">订单ID：${escapeHtml(order.id || '-')} · 投诉时间：${dateText(complaint.created_at)}</div>
                </div>
                ${complaintStatusBadge(complaint.status)}
            </div>
            <div class="complaint-grid-admin mb-3">
                <div class="complaint-meta-admin"><div class="small text-muted">买家</div><strong>${escapeHtml(order.buyer_name || '-')}</strong><div class="small text-muted">${escapeHtml(order.buyer_id || '')}</div></div>
                <div class="complaint-meta-admin"><div class="small text-muted">卖家</div><strong>${escapeHtml(order.seller_name || '-')}</strong><div class="small text-muted">${escapeHtml(order.seller_id || '')}</div></div>
                <div class="complaint-meta-admin"><div class="small text-muted">订单金额 / 冻结</div><strong>${money(order.price)}</strong><div class="small text-danger">冻结 ${money(order.frozen_amount || 0)}</div></div>
            </div>
            <div class="mb-3"><div class="small text-muted mb-1">投诉原因</div><div class="complaint-reason-admin">${escapeHtml(complaint.reason || '-')}</div></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><div class="small text-muted mb-1">卖家回复</div><div class="complaint-reason-admin">${escapeHtml(complaint.seller_reply || '暂无')}</div></div>
                <div class="col-md-6"><div class="small text-muted mb-1">管理员回复</div><textarea id="adminComplaintReply-${escapeHtml(order.id)}" class="form-control" rows="4" maxlength="800" placeholder="填写管理员处理意见">${escapeHtml(complaint.admin_reply || '')}</textarea></div>
            </div>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <button class="btn btn-sm btn-outline-primary" onclick="saveAdminComplaintReply('${escapeHtml(order.id)}')">保存回复</button>
                ${['open','processing','resolved','rejected','withdrawn'].map(s => `<button class="btn btn-sm ${complaint.status === s ? 'btn-primary' : 'btn-outline-secondary'}" onclick="updateAdminComplaintStatus('${escapeHtml(order.id)}','${s}')">${complaintStatusText(s)}</button>`).join('')}
            </div>
        </div>`;
}
function complaintStatusText(status) { return ({ open: '处理中', processing: '跟进中', resolved: '已解决', rejected: '已驳回', withdrawn: '已撤诉' })[status] || status; }
async function saveAdminComplaintReply(orderId) {
    const reply = document.getElementById('adminComplaintReply-' + orderId)?.value?.trim() || '';
    const res = await request('admin.php?action=reply_complaint', 'POST', { order_id: orderId, reply });
    if (!res.success) return showToast(res.message || '保存失败', 'error');
    showToast('管理员回复已保存', 'success');
    await loadAdminData();
    renderComplaints();
}
async function updateAdminComplaintStatus(orderId, status) {
    const res = await request('admin.php?action=update_complaint_status', 'POST', { order_id: orderId, status });
    if (!res.success) return showToast(res.message || '状态更新失败', 'error');
    showToast('投诉状态已更新', 'success');
    await loadAdminData();
    renderComplaints();
}

function renderOrders() {
    setTitle('订单记录');
    const orders = Admin.cache.payOrders || [];
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title">
                <h5>支付订单</h5>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUnpaidOrdersAdmin()">删除所有未支付</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteAllOrdersAdmin()">删除全部订单</button>
                    <button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>交易号</th><th>用户ID</th><th>类型</th><th>说明</th><th>金额</th><th>实付</th><th>状态</th><th>创建时间</th><th class="text-end">操作</th></tr>
                    </thead>
                    <tbody>
                        ${orders.map(o => `
                            <tr>
                                <td><code>${escapeHtml(o.trade_no || o.id)}</code></td>
                                <td>${escapeHtml(o.user_id || '-')}</td>
                                <td>${escapeHtml(orderTypeLabel(o.type, o.pay_type))}</td>
                                <td><div class="fw-semibold">${escapeHtml(o.title || '-')}</div><div class="small text-muted">${escapeHtml(o.description || '')}</div></td>
                                <td>${money(o.amount)}</td>
                                <td>${money(o.actual_amount)}</td>
                                <td>${orderStatusPill(o)}</td>
                                <td>${dateText(o.created_at)}</td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="deletePaymentOrderAdmin('${escapeHtml(o.id)}')">删除</button></td>
                            </tr>
                            <tr id="orderStatusEditor-${escapeHtml(o.id)}" class="order-status-editor-row hidden">
                                <td colspan="9">${orderStatusEditor(o)}</td>
                            </tr>
                        `).join('') || '<tr><td colspan="9" class="text-center text-muted py-4">暂无订单</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>`;
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
    if (!row) return;
    const shouldOpen = forceOpen === null ? row.classList.contains('hidden') : forceOpen;
    document.querySelectorAll('.order-status-editor-row').forEach(el => el.classList.add('hidden'));
    row.classList.toggle('hidden', !shouldOpen);
}
async function updatePaymentOrderStatus(id, status) { const res = await request('payment.php?action=update_order_status', 'POST', { id, status }); if (!res.success) { showToast(res.message || '状态更新失败', 'error'); await loadAdminData(); renderOrders(); return; } showToast('订单状态已更新', 'success'); await loadAdminData(); }
async function deletePaymentOrderAdmin(id) { if (!confirm('确定删除这条订单吗？')) return; const res = await request('payment.php?action=delete_order', 'POST', { id }); if (!res.success) return showToast(res.message || '删除失败', 'error'); showToast('订单已删除', 'success'); await loadAdminData(); renderOrders(); }
async function deleteUnpaidOrdersAdmin() { if (!confirm('确定删除所有未支付订单吗？包含待处理、失败、已取消订单。')) return; const res = await request('payment.php?action=delete_unpaid_orders', 'POST'); if (!res.success) return showToast(res.message || '删除失败', 'error'); showToast(res.message || '已删除未支付订单', 'success'); await loadAdminData(); renderOrders(); }
async function deleteAllOrdersAdmin() { if (!confirm('确定删除全部支付订单吗？此操作不可恢复。')) return; const res = await request('payment.php?action=delete_all_orders', 'POST'); if (!res.success) return showToast(res.message || '删除失败', 'error'); showToast(res.message || '已删除全部订单', 'success'); await loadAdminData(); renderOrders(); }
function renderFinance() { setTitle('充值提现'); document.getElementById('adminContent').innerHTML = `<div class="panel"><div class="panel-title"><h5>申请列表</h5><button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button></div>${requestTable(Admin.cache.requests || [])}</div>`; }
function requestList(list) { if (!list.length) return '<div class="text-muted py-4 text-center">暂无待处理申请</div>'; return list.map(r => `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><strong>${escapeHtml(r.username || r.user_id)}</strong><div class="small text-muted">${money(r.amount)} · ${dateText(r.created_at)}</div></div>${statusBadge(r.status)}</div>`).join(''); }
function requestTable(list) { return `<div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>类型</th><th>金额</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody>${list.map(r => `<tr><td>${escapeHtml(r.username || r.user_id)}</td><td>${r.id && r.id.startsWith('wd_') ? '提现' : '充值'}</td><td>${money(r.amount)}</td><td>${statusBadge(r.status)}</td><td>${dateText(r.created_at)}</td><td>${r.status === 'pending' ? `<button class="btn btn-sm btn-success me-1" onclick="handleRequest('${r.id}','approve')">通过</button><button class="btn btn-sm btn-outline-danger" onclick="handleRequest('${r.id}','reject')">拒绝</button>` : '-'}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">暂无申请</td></tr>'}</tbody></table></div>`; }
async function handleRequest(id, action) { const res = await request(`finance.php?action=${action}`, 'POST', { id }); if (!res.success) return showToast(res.message || '操作失败', 'error'); showToast(res.message || '操作成功', 'success'); await loadAdminData(); }
function renderCards() {
    setTitle('卡密管理');
    const cards = Admin.cache.cards || [];
    document.getElementById('adminContent').innerHTML = `
        <div class="panel mb-4">
            <div class="panel-title"><h5>生成卡密</h5></div>
            <div class="row g-3">
                <div class="col-md-5"><input id="cardAmount" class="form-control" type="number" placeholder="金额"></div>
                <div class="col-md-5"><input id="cardCount" class="form-control" type="number" value="1" placeholder="数量"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" onclick="createCards()">生成</button></div>
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
                            <th>卡密</th><th>金额</th><th>状态</th><th>使用者</th><th>创建时间</th><th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${cards.map(c => `
                            <tr>
                                <td><input class="form-check-input card-select" type="checkbox" value="${escapeHtml(c.id)}" onchange="updateCardBatchToolbar()"></td>
                                <td><code>${escapeHtml(c.code)}</code></td>
                                <td>${money(c.amount)}</td>
                                <td>${c.used ? '<span class="badge-soft danger">已使用</span>' : '<span class="badge-soft success">未使用</span>'}</td>
                                <td>${escapeHtml(c.used_by || '-')}</td>
                                <td>${dateText(c.created_at)}</td>
                                <td class="text-end"><button class="btn btn-sm btn-outline-danger" onclick="deleteCardAdmin('${escapeHtml(c.id)}')">删除</button></td>
                            </tr>
                        `).join('') || '<tr><td colspan="7" class="text-center text-muted py-4">暂无卡密</td></tr>'}
                    </tbody>
                </table>
            </div>
        </div>`;
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
    if (!confirm('确定删除卡密 ' + card.code + ' 吗？')) return;
    const res = await request('card.php?action=delete', 'POST', { id });
    if (!res.success) return showToast(res.message || '删除失败', 'error');
    showToast('卡密已删除', 'success');
    await loadAdminData();
    renderCards();
}
async function deleteSelectedCardsAdmin() {
    const ids = selectedCardIds();
    if (!ids.length) return showToast('请先选择要删除的卡密', 'error');
    if (!confirm('确定删除选中的 ' + ids.length + ' 张卡密吗？此操作不可恢复。')) return;
    const res = await request('card.php?action=delete_batch', 'POST', { ids: JSON.stringify(ids) });
    if (!res.success) return showToast(res.message || '批量删除失败', 'error');
    showToast(res.message || ('已删除 ' + ids.length + ' 张卡密'), 'success');
    await loadAdminData();
    renderCards();
}
async function createCards() {
    const amount = document.getElementById('cardAmount').value;
    const count = document.getElementById('cardCount').value;
    const res = await request('card.php?action=create', 'POST', { amount, count });
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
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title"><h5>会员等级配置</h5><div><button class="btn btn-sm btn-outline-primary me-2" onclick="addMembershipLevelRow()">新增等级</button><button class="btn btn-sm btn-primary" onclick="saveMembershipLevels()">保存配置</button></div></div>
            <div class="config-help mb-3">点击卡片即可编辑等级。后台启用几个，前台会员中心就显示几个；卡片样式与前台保持一致。</div>
            <div id="membershipAdminList" class="membership-admin-grid">加载中...</div>
        </div>`;
    loadMembershipLevelsAdmin();
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
    const maxProducts = Number(level.max_products || 0) >= 9999 ? '无限商品' : `${Number(level.max_products || 0)} 个商品`;
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
                <input type="hidden" class="ml-max-accounts" value="${Number(level.max_accounts_per_product || 1)}">
                <input type="hidden" class="ml-max-products" value="${Number(level.max_products || 1)}">
                <input type="hidden" class="ml-fee-rate" value="${feeRate}">
                <input type="hidden" class="ml-publish-fee" value="${Number(level.publish_fee_per_account || 0)}">
                <input type="hidden" class="ml-gradient" value="${gradient}">
                <input type="checkbox" class="ml-enabled d-none" ${level.enabled !== false ? 'checked' : ''}>
                <input type="checkbox" class="ml-can-upgrade d-none" ${level.can_upgrade !== false ? 'checked' : ''}>
                <div class="membership-admin-price">${Number(level.cost || 0) === 0 ? '<i class="bi bi-gift"></i> 免费' : '¥ ' + Number(level.cost || 0).toFixed(2)}</div>
                <ul class="membership-admin-list">
                    <li><i class="bi bi-check"></i> 单商品最大 ${Number(level.max_accounts_per_product || 0)} 账号</li>
                    <li><i class="bi bi-check"></i> ${maxProducts}</li>
                    <li><i class="bi bi-check"></i> 手续费 ${feeRate}%</li>
                    <li><i class="bi bi-check"></i> ${Number(level.publish_fee_per_account || 0) === 0 ? '发布免费' : '发布费 ¥' + Number(level.publish_fee_per_account || 0) + '/账号'}</li>
                </ul>
                <div class="d-flex justify-content-between align-items-center mt-3 small text-muted">
                    <span>${level.enabled !== false ? '已启用' : '已隐藏'}</span>
                    <span>${level.can_upgrade !== false ? '允许升级' : '禁止升级'}</span>
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
                        <div class="col-md-3"><label class="form-label">单商品账号数</label><input id="editLevelMaxAccounts" type="number" class="form-control" value="${escapeHtml(value('.ml-max-accounts'))}"></div>
                        <div class="col-md-3"><label class="form-label">最多商品数</label><input id="editLevelMaxProducts" type="number" class="form-control" value="${escapeHtml(value('.ml-max-products'))}"></div>
                        <div class="col-md-3"><label class="form-label">交易手续费 %</label><input id="editLevelFeeRate" type="number" step="0.01" class="form-control" value="${escapeHtml(value('.ml-fee-rate'))}"></div>
                        <div class="col-md-3"><label class="form-label">发布费/账号</label><input id="editLevelPublishFee" type="number" step="0.01" class="form-control" value="${escapeHtml(value('.ml-publish-fee'))}"></div>
                        <div class="col-md-12"><label class="form-label">卡片渐变 CSS</label><input id="editLevelGradient" class="form-control" value="${escapeHtml(value('.ml-gradient'))}"></div>
                        <div class="col-md-6"><div class="form-check"><input id="editLevelEnabled" class="form-check-input" type="checkbox" ${checked('.ml-enabled') ? 'checked' : ''}><label class="form-check-label">启用显示</label></div></div>
                        <div class="col-md-6"><div class="form-check"><input id="editLevelCanUpgrade" class="form-check-input" type="checkbox" ${checked('.ml-can-upgrade') ? 'checked' : ''}><label class="form-check-label">允许前台升级</label></div></div>
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
function collectMembershipLevels() {
    return Array.from(document.querySelectorAll('.membership-level-row')).map(row => ({
        name: row.querySelector('.ml-name').value.trim(),
        description: row.querySelector('.ml-description').value.trim(),
        priority: parseInt(row.querySelector('.ml-priority').value || '0', 10),
        cost: parseFloat(row.querySelector('.ml-cost').value || '0'),
        icon: row.querySelector('.ml-icon').value.trim(),
        max_accounts_per_product: parseInt(row.querySelector('.ml-max-accounts').value || '1', 10),
        max_products: parseInt(row.querySelector('.ml-max-products').value || '1', 10),
        fee_rate: (parseFloat(row.querySelector('.ml-fee-rate').value || '0') / 100),
        publish_fee_per_account: parseFloat(row.querySelector('.ml-publish-fee').value || '0'),
        gradient: row.querySelector('.ml-gradient').value.trim(),
        enabled: row.querySelector('.ml-enabled').checked,
        can_upgrade: row.querySelector('.ml-can-upgrade').checked
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
    const siteVersionText = status.local_commit ? escapeHtml((status.local_commit || '').slice(0, 12)) : '未记录';
    const siteUpdatedText = status.site_updated_at ? `<div class="small text-muted mt-1">更新时间：${escapeHtml(status.site_updated_at)}</div>` : '<div class="small text-muted mt-1">首次使用后台更新后会自动记录</div>';
    return `
        <div class="row g-3">
            <div class="col-md-6"><div class="border rounded-4 p-3"><div class="text-muted small">远程提交</div><code>${escapeHtml((status.remote_commit || '').slice(0, 12) || '-')}</code></div></div>
            <div class="col-md-6"><div class="border rounded-4 p-3"><div class="text-muted small">当前网站版本记录</div><code>${siteVersionText}</code>${siteUpdatedText}</div></div>
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
function switchSettingsTab(tab) { Admin.settingsTab = tab; saveAdminState(); renderSettings(); }
function renderSettingsContent() {
    const map = { basic: renderBasicSettings, payment: renderPaymentSettingsOnly, login: renderReservedLoginSettings, email: renderReservedEmailSettings, captcha: renderReservedCaptchaSettings, announcement: renderReservedAnnouncementSettings };
    (map[Admin.settingsTab] || renderBasicSettings)('settingsContent');
}
function renderBasicSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>基础设置</h5></div><div class="row g-3"><div class="col-md-6"><label class="form-label">站点名称</label><input id="setSiteName" class="form-control" value="${escapeHtml(c.site_name || 'KeyNest')}"></div><div class="col-md-6"><label class="form-label">站点描述</label><input id="setSiteDescription" class="form-control" value="${escapeHtml(c.site_description || '')}"></div><div class="col-md-6"><label class="form-label">最低提现金额</label><input id="setMinWithdraw" class="form-control" type="number" value="${escapeHtml(c.min_withdraw_amount || 10)}"></div><div class="col-md-6"><label class="form-label">提现手续费比例</label><input id="setWithdrawFee" class="form-control" type="number" step="0.001" value="${escapeHtml(c.withdraw_fee_rate || 0.01)}"></div><div class="col-12"><button class="btn btn-primary" onclick="saveSettings()">保存基础设置</button></div></div></div>`;
}
async function saveSettings() { const res = await request('finance.php?action=update_system_config', 'POST', { site_name: document.getElementById('setSiteName').value, site_description: document.getElementById('setSiteDescription').value, min_withdraw_amount: document.getElementById('setMinWithdraw').value, withdraw_fee_rate: document.getElementById('setWithdrawFee').value }); if (!res.success) return showToast(res.message || '保存失败', 'error'); showToast('保存成功', 'success'); await loadAdminData(); }
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
function renderReservedLoginSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>登录注册</h5><button class="btn btn-sm btn-primary" onclick="saveLoginSettings()">保存登录设置</button></div><div class="config-help mb-3">这里控制第三方登录入口是否显示。勾选后还需要填写对应平台应用参数；未接入真实回调前建议保持关闭。</div><div class="row g-3"><div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="oauthQqEnabled" ${c.oauth_qq_enabled ? 'checked' : ''}><label class="form-check-label" for="oauthQqEnabled"><strong>QQ 官方登录</strong><span>需要 App ID / App Key / 回调地址</span></label></div></div><div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="oauthWechatEnabled" ${c.oauth_wechat_enabled ? 'checked' : ''}><label class="form-check-label" for="oauthWechatEnabled"><strong>微信官方登录</strong><span>需要 App ID / App Secret / 回调地址</span></label></div></div><div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" id="oauthCaihongEnabled" ${c.oauth_caihong_enabled ? 'checked' : ''}><label class="form-check-label" for="oauthCaihongEnabled"><strong>彩虹聚合登录</strong><span>需要聚合登录网关、商户 ID 和 Key</span></label></div></div></div></div>`;
}
async function saveLoginSettings() { await saveSystemConfigFields({ oauth_qq_enabled: checkedValue('oauthQqEnabled'), oauth_wechat_enabled: checkedValue('oauthWechatEnabled'), oauth_caihong_enabled: checkedValue('oauthCaihongEnabled') }, '登录设置已保存'); }
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
    const provider = c.email_provider || 'smtp';
    const template = c.email_template_html || defaultEmailTemplateHtml();
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>邮箱验证</h5><button class="btn btn-sm btn-primary" onclick="saveEmailSettings()">保存邮箱设置</button></div><div class="config-help mb-3">支持 Resend 和通用 SMTP。Resend API 模式只需要 API Key 和已验证发件域名，不需要填写用户名；如果使用 QQ/163/Gmail 等 SMTP，请使用邮箱账号和授权码。下面可以直接修改验证码邮件卡片 HTML，并实时预览效果。</div><div class="row g-3"><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="emailVerifyEnabled" ${c.register_email_verify_enabled ? 'checked' : ''}><label class="form-check-label" for="emailVerifyEnabled">注册时启用邮箱验证码</label></div></div><div class="col-md-4"><label class="form-label">发信方式</label><select id="emailProvider" class="form-select" onchange="toggleEmailProviderFields()"><option value="smtp" ${provider === 'smtp' ? 'selected' : ''}>SMTP（QQ/163/Gmail/企业邮箱）</option><option value="resend" ${provider === 'resend' ? 'selected' : ''}>Resend</option></select></div><div class="col-md-4"><label class="form-label">发件人邮箱 From</label><input id="resendFromEmail" class="form-control" value="${escapeHtml(c.resend_from_email || '')}" placeholder="noreply@example.com"></div><div class="col-md-4"><label class="form-label">发件人名称</label><input id="resendFromName" class="form-control" value="${escapeHtml(c.resend_from_name || 'KeyNest')}" placeholder="KeyNest"></div><div class="col-md-4"><label class="form-label">验证码有效期（分钟）</label><input id="emailCodeTtl" class="form-control" type="number" min="1" max="60" value="${escapeHtml(c.email_code_ttl || 10)}"></div><div class="col-md-8 resend-email-field"><label class="form-label">Resend API Key</label><input id="resendApiKey" class="form-control" type="password" placeholder="re_xxxxxxxxx；留空表示不修改"></div><div class="col-md-4 smtp-email-field"><label class="form-label">SMTP 主机</label><input id="smtpHost" class="form-control" value="${escapeHtml(c.smtp_host || '')}" placeholder="smtp.qq.com"></div><div class="col-md-2 smtp-email-field"><label class="form-label">端口</label><input id="smtpPort" class="form-control" type="number" value="${escapeHtml(c.smtp_port || 465)}" placeholder="465"></div><div class="col-md-2 smtp-email-field"><label class="form-label">加密</label><select id="smtpSecure" class="form-select"><option value="ssl" ${c.smtp_secure === 'ssl' ? 'selected' : ''}>SSL</option><option value="tls" ${c.smtp_secure === 'tls' ? 'selected' : ''}>TLS</option><option value="none" ${c.smtp_secure === 'none' ? 'selected' : ''}>无</option></select></div><div class="col-md-4 smtp-email-field"><label class="form-label">SMTP 账号</label><input id="smtpUsername" class="form-control" value="${escapeHtml(c.smtp_username || '')}" placeholder="你的邮箱地址"></div><div class="col-md-6 smtp-email-field"><label class="form-label">SMTP 密码 / 授权码</label><input id="smtpPassword" class="form-control" type="password" placeholder="留空表示不修改；QQ 邮箱填授权码"></div><div class="col-md-6"><label class="form-label">测试收件邮箱</label><div class="input-group"><input id="testEmailTo" class="form-control" placeholder="输入你的邮箱测试发送"><button class="btn btn-outline-primary" type="button" onclick="testEmailSettings()">测试发送</button></div></div><div class="col-lg-6"><div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label mb-0">邮件卡片 HTML 模板</label><button class="btn btn-sm btn-outline-secondary" type="button" onclick="resetEmailTemplateHtml()">恢复默认卡片</button></div><textarea id="emailTemplateHtml" class="form-control" rows="16" oninput="updateEmailTemplatePreview()">${escapeHtml(template)}</textarea><div class="config-help mt-2">可用变量：<code>{{site_name}}</code> <code>{{title}}</code> <code>{{message}}</code> <code>{{code}}</code> <code>{{ttl}}</code> <code>{{footer}}</code> <code>{{time}}</code></div></div><div class="col-lg-6"><label class="form-label">模板预览</label><div id="emailTemplatePreview" style="background:#eef2f7;border:1px solid #e5e7eb;border-radius:18px;padding:18px;min-height:360px;overflow:auto"></div></div></div></div>`;
    toggleEmailProviderFields();
    updateEmailTemplatePreview();
}
function toggleEmailProviderFields() { const provider = fieldValue('emailProvider') || 'smtp'; document.querySelectorAll('.resend-email-field').forEach(el => el.style.display = provider === 'resend' ? '' : 'none'); document.querySelectorAll('.smtp-email-field').forEach(el => el.style.display = provider === 'smtp' ? '' : 'none'); }
async function saveEmailSettings() { await saveSystemConfigFields({ register_email_verify_enabled: checkedValue('emailVerifyEnabled'), email_provider: fieldValue('emailProvider'), resend_api_key: fieldValue('resendApiKey'), resend_from_email: fieldValue('resendFromEmail'), resend_from_name: fieldValue('resendFromName'), email_code_ttl: fieldValue('emailCodeTtl'), email_template_html: fieldValue('emailTemplateHtml'), smtp_host: fieldValue('smtpHost'), smtp_port: fieldValue('smtpPort'), smtp_username: fieldValue('smtpUsername'), smtp_password: fieldValue('smtpPassword'), smtp_secure: fieldValue('smtpSecure') }, '邮箱设置已保存'); await loadAdminData(); }
async function testEmailSettings() { const email = fieldValue('testEmailTo'); if (!email) return showToast('请输入测试收件邮箱', 'warning'); const saveRes = await request('finance.php?action=update_system_config', 'POST', { register_email_verify_enabled: checkedValue('emailVerifyEnabled'), email_provider: fieldValue('emailProvider'), resend_api_key: fieldValue('resendApiKey'), resend_from_email: fieldValue('resendFromEmail'), resend_from_name: fieldValue('resendFromName'), email_code_ttl: fieldValue('emailCodeTtl'), email_template_html: fieldValue('emailTemplateHtml'), smtp_host: fieldValue('smtpHost'), smtp_port: fieldValue('smtpPort'), smtp_username: fieldValue('smtpUsername'), smtp_password: fieldValue('smtpPassword'), smtp_secure: fieldValue('smtpSecure') }); if (!saveRes.success) return showToast(saveRes.message || '保存邮箱设置失败', 'error'); const res = await request('admin.php?action=test_email', 'POST', { email }); if (!res.success) return showToast(res.message || '测试发送失败', 'error'); showToast(res.message || '测试验证码邮件已发送', 'success'); }
function renderReservedCaptchaSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>人机验证</h5><button class="btn btn-sm btn-primary" onclick="saveCaptchaSettings()">保存验证设置</button></div><div class="config-help mb-3">选择你要用的验证码服务商，并填写前端 Site Key 和后端 Secret Key。不同服务商的前端组件接入方式不同，这里先保存参数，后续登录/注册页可按 provider 加载对应组件。</div><div class="row g-3"><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="captchaEnabled" ${c.captcha_enabled ? 'checked' : ''}><label class="form-check-label" for="captchaEnabled">启用人机验证</label></div></div><div class="col-md-4"><label class="form-label">服务商</label><select id="captchaProvider" class="form-select" onchange="updateCaptchaProviderLink()"><option value="turnstile" ${c.captcha_provider === 'turnstile' ? 'selected' : ''}>Cloudflare Turnstile</option><option value="recaptcha_v3" ${c.captcha_provider === 'recaptcha_v3' ? 'selected' : ''}>Google reCAPTCHA v3</option><option value="geetest_v3" ${c.captcha_provider === 'geetest_v3' || c.captcha_provider === 'behavior_v3' ? 'selected' : ''}>极验行为验证 v3</option><option value="aliyun" ${c.captcha_provider === 'aliyun' ? 'selected' : ''}>阿里云验证码</option><option value="tencent" ${c.captcha_provider === 'tencent' ? 'selected' : ''}>腾讯验证码</option></select></div><div class="col-md-8"><label class="form-label">服务商官网</label><div id="captchaProviderLink" class="config-help py-2"></div></div><div class="col-md-4"><label class="form-label">Site Key / Captcha ID</label><input id="captchaSiteKey" class="form-control" value="${escapeHtml(c.captcha_site_key || '')}" placeholder="前端公开 key"></div><div class="col-md-4"><label class="form-label">Secret Key</label><input id="captchaSecretKey" class="form-control" type="password" placeholder="留空表示不修改"></div><div class="col-12"><label class="form-label">校验接口/额外配置（可选）</label><textarea id="captchaExtraConfig" class="form-control" rows="3" placeholder='例如 {"endpoint":"https://..."}'>${escapeHtml(c.captcha_extra_config || '')}</textarea></div></div></div>`;
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
async function saveAnnouncementSettings() { await saveSystemConfigFields({ announcement_enabled: checkedValue('announcementEnabled'), announcement_title: fieldValue('announcementTitle'), announcement_position: fieldValue('announcementPosition'), announcement_content: fieldValue('announcementContent') }, '公告设置已保存'); }

document.addEventListener('keydown', e => { if (e.key === 'Enter' && !document.getElementById('loginView').classList.contains('hidden')) adminLogin(); });
bootstrapAdmin();
</script>
</body>
</html>
