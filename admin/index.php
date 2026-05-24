<?php
require_once dirname(__DIR__) . '/config/install.php';
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
        try { json = text ? JSON.parse(text) : {}; } catch (e) { return { success: false, message: '服务器返回异常内容' }; }
        if (!res.ok) return { success: false, message: json.message || ('请求失败：' + res.status), status: res.status, ...json };
        return json;
    } catch (e) {
        return { success: false, message: '网络错误，请检查服务器是否正常' };
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
    const validPages = ['overview', 'users', 'products', 'orders', 'finance', 'cards', 'settings', 'membership', 'updates', 'logs'];
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
    const [users, products, payOrders, requests, cards, payConfigs, sysConfig] = await Promise.all([
        request('admin.php?action=users'),
        request('product.php?action=list&stock_min=0'),
        request('payment.php?action=get_orders'),
        request('finance.php?action=all_requests'),
        request('card.php?action=list'),
        request('payment.php?action=get_configs'),
        request('finance.php?action=get_system_config')
    ]);
    Admin.cache = {
        users: users.users || [],
        products: products.products || [],
        payOrders: payOrders.orders || [],
        requests: requests.requests || [],
        cards: cards.cards || [],
        payConfigs: payConfigs.configs || [],
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
    const renderers = { overview: renderOverview, users: renderUsers, products: renderProducts, orders: renderOrders, finance: renderFinance, cards: renderCards, payments: renderPayments, settings: renderSettings, membership: renderMembershipAdmin, updates: renderUpdates, logs: renderLogs     };
    updateAdminNavActive(Admin.page === 'settings' && Admin.settingsTab === 'payment' ? 'payment' : null);
    (renderers[Admin.page] || renderOverview)();
}
function renderOverview() {
    setTitle('后台总览');
    const users = Admin.cache.users || [], products = Admin.cache.products || [], orders = Admin.cache.payOrders || [], requests = Admin.cache.requests || [], cards = Admin.cache.cards || [];
    const pending = requests.filter(r => r.status === 'pending').length;
    document.getElementById('adminContent').innerHTML = `
        <div class="row g-3 mb-4">
            ${stat('bi-people-fill', '#dbeafe', '#1d4ed8', users.length, '用户总数')}
            ${stat('bi-box-seam-fill', '#ede9fe', '#6d28d9', products.length, '商品总数')}
            ${stat('bi-cash-stack', '#dcfce7', '#15803d', orders.length, '支付订单')}
            ${stat('bi-hourglass-split', '#fef3c7', '#b45309', pending, '待处理申请')}
        </div>
        <div class="row g-4">
            <div class="col-lg-7"><div class="panel"><div class="panel-title"><h5>最新用户</h5><button class="btn btn-sm btn-outline-primary" onclick="switchAdminPage('users')">查看全部</button></div>${userTable(users.slice(-6).reverse())}</div></div>
            <div class="col-lg-5"><div class="panel"><div class="panel-title"><h5>待处理申请</h5><button class="btn btn-sm btn-outline-primary" onclick="switchAdminPage('finance')">处理</button></div>${requestList(requests.filter(r => r.status === 'pending').slice(0, 6))}</div></div>
        </div>`;
}
function stat(icon, bg, color, value, label) { return `<div class="col-md-6 col-xl-3"><div class="stat-card"><div class="stat-icon" style="background:${bg};color:${color}"><i class="bi ${icon}"></i></div><div class="stat-value">${value}</div><div class="stat-label">${label}</div></div></div>`; }
function renderUsers() { setTitle('用户管理'); document.getElementById('adminContent').innerHTML = `<div class="panel"><div class="panel-title"><h5>全部用户</h5><button class="btn btn-sm btn-primary" onclick="loadAdminData()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button></div>${userTable(Admin.cache.users || [])}</div>`; }
function userTable(users) {
    if (!users.length) return '<div class="text-muted py-4 text-center">暂无用户</div>';
    return `<div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>邮箱</th><th>角色</th><th>会员</th><th>余额</th><th>注册时间</th></tr></thead><tbody>${users.map(u => `<tr><td><strong>${escapeHtml(u.username)}</strong></td><td>${escapeHtml(u.email || '-')}</td><td>${u.role === 'admin' ? '<span class="badge-soft info">管理员</span>' : '<span class="badge-soft success">用户</span>'}</td><td>${escapeHtml(u.membership_level || 'Free')}</td><td>${money(u.balance)}</td><td>${dateText(u.created_at)}</td></tr>`).join('')}</tbody></table></div>`;
}
function renderProducts() { setTitle('商品管理'); const products = Admin.cache.products || []; document.getElementById('adminContent').innerHTML = `<div class="panel"><div class="panel-title"><h5>全部商品</h5><button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button></div><div class="table-responsive"><table class="table"><thead><tr><th>标题</th><th>卖家</th><th>分类</th><th>价格</th><th>库存</th><th>销量</th></tr></thead><tbody>${products.map(p => `<tr><td><strong>${escapeHtml(p.title)}</strong></td><td>${escapeHtml(p.seller_name || '-')}</td><td>${escapeHtml(p.category || '-')}</td><td>${money(p.price)}</td><td>${p.stock || 0}</td><td>${p.sales || 0}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">暂无商品</td></tr>'}</tbody></table></div></div>`; }
function renderOrders() { setTitle('订单记录'); const orders = Admin.cache.payOrders || []; document.getElementById('adminContent').innerHTML = `<div class="panel"><div class="panel-title"><h5>支付订单</h5><button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button></div><div class="table-responsive"><table class="table"><thead><tr><th>交易号</th><th>用户ID</th><th>金额</th><th>实付</th><th>状态</th><th>创建时间</th></tr></thead><tbody>${orders.map(o => `<tr><td><code>${escapeHtml(o.trade_no || o.id)}</code></td><td>${escapeHtml(o.user_id || '-')}</td><td>${money(o.amount)}</td><td>${money(o.actual_amount)}</td><td>${statusBadge(o.status)}</td><td>${dateText(o.created_at)}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">暂无订单</td></tr>'}</tbody></table></div></div>`; }
function renderFinance() { setTitle('充值提现'); document.getElementById('adminContent').innerHTML = `<div class="panel"><div class="panel-title"><h5>申请列表</h5><button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button></div>${requestTable(Admin.cache.requests || [])}</div>`; }
function requestList(list) { if (!list.length) return '<div class="text-muted py-4 text-center">暂无待处理申请</div>'; return list.map(r => `<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><strong>${escapeHtml(r.username || r.user_id)}</strong><div class="small text-muted">${money(r.amount)} · ${dateText(r.created_at)}</div></div>${statusBadge(r.status)}</div>`).join(''); }
function requestTable(list) { return `<div class="table-responsive"><table class="table"><thead><tr><th>用户</th><th>类型</th><th>金额</th><th>状态</th><th>时间</th><th>操作</th></tr></thead><tbody>${list.map(r => `<tr><td>${escapeHtml(r.username || r.user_id)}</td><td>${r.id && r.id.startsWith('wd_') ? '提现' : '充值'}</td><td>${money(r.amount)}</td><td>${statusBadge(r.status)}</td><td>${dateText(r.created_at)}</td><td>${r.status === 'pending' ? `<button class="btn btn-sm btn-success me-1" onclick="handleRequest('${r.id}','approve')">通过</button><button class="btn btn-sm btn-outline-danger" onclick="handleRequest('${r.id}','reject')">拒绝</button>` : '-'}</td></tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">暂无申请</td></tr>'}</tbody></table></div>`; }
async function handleRequest(id, action) { const res = await request(`finance.php?action=${action}`, 'POST', { id }); if (!res.success) return showToast(res.message || '操作失败', 'error'); showToast(res.message || '操作成功', 'success'); await loadAdminData(); }
function renderCards() { setTitle('卡密管理'); const cards = Admin.cache.cards || []; document.getElementById('adminContent').innerHTML = `<div class="panel mb-4"><div class="panel-title"><h5>生成卡密</h5></div><div class="row g-3"><div class="col-md-5"><input id="cardAmount" class="form-control" type="number" placeholder="金额"></div><div class="col-md-5"><input id="cardCount" class="form-control" type="number" value="1" placeholder="数量"></div><div class="col-md-2"><button class="btn btn-primary w-100" onclick="createCards()">生成</button></div></div></div><div class="panel"><div class="panel-title"><h5>卡密列表</h5><button class="btn btn-sm btn-primary" onclick="loadAdminData()">刷新</button></div><div class="table-responsive"><table class="table"><thead><tr><th>卡密</th><th>金额</th><th>状态</th><th>使用者</th><th>创建时间</th></tr></thead><tbody>${cards.map(c => `<tr><td><code>${escapeHtml(c.code)}</code></td><td>${money(c.amount)}</td><td>${c.used ? '<span class="badge-soft danger">已使用</span>' : '<span class="badge-soft success">未使用</span>'}</td><td>${escapeHtml(c.used_by || '-')}</td><td>${dateText(c.created_at)}</td></tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted py-4">暂无卡密</td></tr>'}</tbody></table></div></div>`; }
async function createCards() { const amount = document.getElementById('cardAmount').value; const count = document.getElementById('cardCount').value; const res = await request('card.php?action=create', 'POST', { amount, count }); if (!res.success) return showToast(res.message || '生成失败', 'error'); showToast(res.message || '生成成功', 'success'); await loadAdminData(); }
function renderMembershipAdmin() {
    setTitle('会员等级');
    document.getElementById('adminContent').innerHTML = `
        <div class="panel">
            <div class="panel-title"><h5>会员等级配置</h5><div><button class="btn btn-sm btn-outline-primary me-2" onclick="addMembershipLevelRow()">新增等级</button><button class="btn btn-sm btn-primary" onclick="saveMembershipLevels()">保存配置</button></div></div>
            <div class="config-help mb-3">这里控制前台会员中心显示的等级、升级价格、发布数量、发布费和手续费。你配置几个，前台就显示几个；默认会初始化 Free、VIP、PRO、Infinite。</div>
            <div id="membershipAdminList" class="d-grid gap-3">加载中...</div>
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
    list.innerHTML = levels.map((level, index) => membershipLevelRow(level, index)).join('') || '<div class="text-muted text-center py-4">暂无等级，请新增</div>';
}
function membershipLevelRow(level = {}, index = 0) {
    const name = escapeHtml(level.name || '');
    return `
        <div class="border rounded-4 p-3 membership-level-row" data-index="${index}">
            <div class="row g-3">
                <div class="col-md-2"><label class="form-label">等级名称</label><input class="form-control ml-name" value="${name}" ${level.name === 'Free' ? 'readonly' : ''} placeholder="VIP"></div>
                <div class="col-md-3"><label class="form-label">描述</label><input class="form-control ml-description" value="${escapeHtml(level.description || '')}" placeholder="会员描述"></div>
                <div class="col-md-2"><label class="form-label">排序权重</label><input type="number" class="form-control ml-priority" value="${Number(level.priority || index)}"></div>
                <div class="col-md-2"><label class="form-label">开通价格</label><input type="number" step="0.01" class="form-control ml-cost" value="${Number(level.cost || 0)}"></div>
                <div class="col-md-3"><label class="form-label">图标 class</label><input class="form-control ml-icon" value="${escapeHtml(level.icon || 'bi-gem')}" placeholder="bi-gem"></div>
                <div class="col-md-3"><label class="form-label">单商品账号数</label><input type="number" class="form-control ml-max-accounts" value="${Number(level.max_accounts_per_product || 1)}"></div>
                <div class="col-md-3"><label class="form-label">最多商品数</label><input type="number" class="form-control ml-max-products" value="${Number(level.max_products || 1)}"></div>
                <div class="col-md-3"><label class="form-label">交易手续费 %</label><input type="number" step="0.01" class="form-control ml-fee-rate" value="${Number(level.fee_rate || 0) * 100}"></div>
                <div class="col-md-3"><label class="form-label">发布费/账号</label><input type="number" step="0.01" class="form-control ml-publish-fee" value="${Number(level.publish_fee_per_account || 0)}"></div>
                <div class="col-md-6"><label class="form-label">卡片渐变 CSS</label><input class="form-control ml-gradient" value="${escapeHtml(level.gradient || '')}" placeholder="linear-gradient(...)"></div>
                <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input ml-enabled" type="checkbox" ${level.enabled !== false ? 'checked' : ''}><label class="form-check-label">启用显示</label></div></div>
                <div class="col-md-3 d-flex align-items-end"><div class="form-check"><input class="form-check-input ml-can-upgrade" type="checkbox" ${level.can_upgrade !== false ? 'checked' : ''}><label class="form-check-label">允许前台升级</label></div></div>
                <div class="col-12 d-flex justify-content-end"><button class="btn btn-sm btn-outline-danger" onclick="deleteMembershipLevelRow(this, '${name}')" ${level.name === 'Free' ? 'disabled' : ''}>删除</button></div>
            </div>
        </div>`;
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
async function deleteMembershipLevelRow(btn, name) {
    if (!name || name.startsWith('NewLevel')) { btn.closest('.membership-level-row')?.remove(); return; }
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
            <div class="panel-title"><h5>系统日志</h5><button class="btn btn-sm btn-primary" onclick="loadAdminLog()"><i class="bi bi-arrow-clockwise me-1"></i>刷新</button></div>
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
function renderReservedEmailSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>Resend 邮箱验证</h5><button class="btn btn-sm btn-primary" onclick="saveEmailSettings()">保存邮箱设置</button></div><div class="config-help mb-3">已按 Resend 适配。你只需要在 Resend 后台创建 API Key，并验证发信域名。From 邮箱建议使用 <code>noreply@你的域名</code>。</div><div class="row g-3"><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="emailVerifyEnabled" ${c.register_email_verify_enabled ? 'checked' : ''}><label class="form-check-label" for="emailVerifyEnabled">注册时启用邮箱验证码</label></div></div><div class="col-md-6"><label class="form-label">Resend API Key</label><input id="resendApiKey" class="form-control" type="password" placeholder="re_xxxxxxxxx；留空表示不修改"></div><div class="col-md-6"><label class="form-label">发件人邮箱 From</label><input id="resendFromEmail" class="form-control" value="${escapeHtml(c.resend_from_email || '')}" placeholder="KeyNest <noreply@example.com>"></div><div class="col-md-6"><label class="form-label">发件人名称</label><input id="resendFromName" class="form-control" value="${escapeHtml(c.resend_from_name || 'KeyNest')}" placeholder="KeyNest"></div><div class="col-md-6"><label class="form-label">验证码有效期（分钟）</label><input id="emailCodeTtl" class="form-control" type="number" min="1" max="60" value="${escapeHtml(c.email_code_ttl || 10)}"></div></div></div>`;
}
async function saveEmailSettings() { await saveSystemConfigFields({ register_email_verify_enabled: checkedValue('emailVerifyEnabled'), email_provider: 'resend', resend_api_key: fieldValue('resendApiKey'), resend_from_email: fieldValue('resendFromEmail'), resend_from_name: fieldValue('resendFromName'), email_code_ttl: fieldValue('emailCodeTtl') }, 'Resend 邮箱设置已保存'); }
function renderReservedCaptchaSettings(targetId = 'settingsContent') {
    const c = Admin.cache.sysConfig || {};
    document.getElementById(targetId).innerHTML = `<div class="panel"><div class="panel-title"><h5>人机验证</h5><button class="btn btn-sm btn-primary" onclick="saveCaptchaSettings()">保存验证设置</button></div><div class="config-help mb-3">选择你要用的验证码服务商，并填写前端 Site Key 和后端 Secret Key。不同服务商的前端组件接入方式不同，这里先保存参数，后续登录/注册页可按 provider 加载对应组件。</div><div class="row g-3"><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="captchaEnabled" ${c.captcha_enabled ? 'checked' : ''}><label class="form-check-label" for="captchaEnabled">启用人机验证</label></div></div><div class="col-md-4"><label class="form-label">服务商</label><select id="captchaProvider" class="form-select"><option value="turnstile" ${c.captcha_provider === 'turnstile' ? 'selected' : ''}>Cloudflare Turnstile</option><option value="recaptcha_v3" ${c.captcha_provider === 'recaptcha_v3' ? 'selected' : ''}>Google reCAPTCHA v3</option><option value="geetest_v3" ${c.captcha_provider === 'geetest_v3' || c.captcha_provider === 'behavior_v3' ? 'selected' : ''}>极验行为验证 v3</option><option value="aliyun" ${c.captcha_provider === 'aliyun' ? 'selected' : ''}>阿里云验证码</option><option value="tencent" ${c.captcha_provider === 'tencent' ? 'selected' : ''}>腾讯验证码</option></select></div><div class="col-md-4"><label class="form-label">Site Key / Captcha ID</label><input id="captchaSiteKey" class="form-control" value="${escapeHtml(c.captcha_site_key || '')}" placeholder="前端公开 key"></div><div class="col-md-4"><label class="form-label">Secret Key</label><input id="captchaSecretKey" class="form-control" type="password" placeholder="留空表示不修改"></div><div class="col-12"><label class="form-label">校验接口/额外配置（可选）</label><textarea id="captchaExtraConfig" class="form-control" rows="3" placeholder='例如 {"endpoint":"https://..."}'>${escapeHtml(c.captcha_extra_config || '')}</textarea></div></div></div>`;
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
