window.Security = {
    escapeHtml: function(str) {
        if (str === null || str === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    },
    
    escapeAttr: function(attr) {
        if (attr === null || attr === undefined) return '';
        return String(attr).replace(/"/g, '&quot;').replace(/'/g, '&#x27;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    },
    
    escapeUrl: function(url) {
        if (url === null || url === undefined) return '';
        try {
            return encodeURIComponent(String(url));
        } catch (e) {
            return '';
        }
    },
    
    validateLength: function(str, min, max) {
        if (typeof str !== 'string') return false;
        var len = str.length;
        if (min !== undefined && len < min) return false;
        if (max !== undefined && len > max) return false;
        return true;
    },
    
    validateUsername: function(username) {
        return /^[a-zA-Z0-9_]{3,20}$/.test(username);
    },
    
    validateEmail: function(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    validatePassword: function(password) {
        return password && password.length >= 6;
    }
};

window.App = {
    currentUser: null,
    currentPage: 'home',
    currentTab: 'overview',
    currentChatPartner: null,
    currentDetailProduct: null,
    products: [],

    setUser: function(user) {
        this.currentUser = user;
        this.updateNavUI();
    },

    clearUser: function() {
        this.currentUser = null;
        this.updateNavUI();
    },

    updateNavUI() {
        const guestArea = document.getElementById('navGuestArea');
        const userArea = document.getElementById('navUserArea');
        const dashboardLink = document.getElementById('navDashboardLink');
        const publishLink = document.getElementById('navSellLink');

        if (this.currentUser) {
            guestArea.classList.add('hidden');
            userArea.classList.remove('hidden');
            dashboardLink.classList.remove('hidden');
            publishLink.classList.remove('hidden');

            document.getElementById('navUsername').textContent = Security.escapeHtml(this.currentUser.username);
            document.getElementById('navAvatar').textContent = Security.escapeHtml(this.currentUser.username.charAt(0).toUpperCase());
            document.getElementById('navBalance').textContent = '¥ ' + this.currentUser.balance.toFixed(2);
            const navAdminBtn = document.getElementById('navAdminBtn');
            if (navAdminBtn) {
                navAdminBtn.classList.toggle('hidden', this.currentUser.role !== 'admin');
            }
        } else {
            guestArea.classList.remove('hidden');
            userArea.classList.add('hidden');
            dashboardLink.classList.add('hidden');
            publishLink.classList.add('hidden');
            const navAdminBtn = document.getElementById('navAdminBtn');
            if (navAdminBtn) {
                navAdminBtn.classList.add('hidden');
            }
        }
    },

    async updateUnreadBadge() {
        const badge = document.getElementById('unreadBadge');
        if (!this.currentUser) {
            badge.classList.add('hidden');
            return;
        }

        const result = await API.getUnreadCount();
        if (result.success && result.unread > 0) {
            badge.textContent = result.unread;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
};

window.Toast = {
    container: null,
    initialized: false,

    init() {
        if (this.initialized) return;
        
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        this.container = container;
        this.initialized = true;
    },

    ensureInit() {
        if (!this.initialized) {
            this.init();
        }
    },

    show(message, type = 'info') {
        this.ensureInit();
        
        const icons = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = '<i class="bi ' + icons[type] + ' toast-icon"></i><span>' + Security.escapeHtml(message) + '</span>';

        this.container.appendChild(toast);

        setTimeout(function() {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    },

    success(message) { this.show(message, 'success'); },
    error(message) { this.show(message, 'error'); },
    warning(message) { this.show(message, 'warning'); },
    info(message) { this.show(message, 'info'); }
};

const slideStyle = document.createElement('style');
slideStyle.textContent = '@keyframes slideOut{to{transform:translateX(100%);opacity:0}}';
document.head.appendChild(slideStyle);

window.Utils = {
    formatDate(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
    },
    truncate: function(str, len) {
        if (!str) return '';
        return str.length > len ? str.substring(0, len) + '...' : str;
    },
    copyText: function(text) {
        navigator.clipboard.writeText(text).then(function() {
            window.Toast.success('已复制到剪贴板');
        }).catch(function() {
            window.Toast.error('复制失败');
        });
    }
};

function persistFrontendState() {
    if (!App.currentPage) return;
    localStorage.setItem('keynest_front_page', App.currentPage);
    localStorage.setItem('keynest_front_tab', App.currentTab || 'overview');
    const params = new URLSearchParams({ page: App.currentPage });
    if (App.currentPage === 'dashboard') params.set('tab', App.currentTab || 'overview');
    history.replaceState(null, '', '#' + params.toString());
}

function normalizeFrontendHash() {
    const raw = (window.location.hash || '').replace(/^#/, '');
    if (!raw) return new URLSearchParams();
    if (raw.includes('=')) return new URLSearchParams(raw);
    if (raw === 'market' || raw === 'home') return new URLSearchParams({ page: 'home' });
    return new URLSearchParams({ page: 'dashboard', tab: raw });
}

function getInitialFrontendState() {
    const hash = normalizeFrontendHash();
    const page = hash.get('page') || localStorage.getItem('keynest_front_page') || 'home';
    const tab = hash.get('tab') || localStorage.getItem('keynest_front_tab') || 'overview';
    return {
        page: ['home', 'dashboard'].includes(page) ? page : 'home',
        tab: ['overview', 'orders', 'sales', 'myproducts', 'balance', 'membership', 'cardmanage', 'paymentmanage', 'messages'].includes(tab) ? tab : 'overview'
    };
}

function showHome() {
    App.currentPage = 'home';
    persistFrontendState();
    document.getElementById('homePage').classList.remove('hidden');
    document.getElementById('dashboardPage').classList.add('hidden');

    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    const marketLink = document.querySelector('.nav-link[href="#market"]');
    if (marketLink) marketLink.classList.add('active');

    loadProducts();
}

function showDashboard(tabName = null) {
    if (!App.currentUser) {
        Toast.warning('请先登录');
        openLoginModal();
        return;
    }

    App.currentPage = 'dashboard';
    App.currentTab = tabName || App.currentTab || 'overview';
    persistFrontendState();

    document.getElementById('homePage').classList.add('hidden');
    document.getElementById('dashboardPage').classList.remove('hidden');

    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));

    renderDashboard(App.currentTab);
}

function renderDashboardTab(tabName) {
    App.currentTab = tabName;
    if (App.currentPage === 'dashboard') persistFrontendState();

    document.querySelectorAll('#dashSidebar .sidebar-nav-item').forEach(item => {
        item.classList.toggle('active', item.dataset.tab === tabName);
    });

    const contentArea = document.getElementById('dashContentArea');
    contentArea.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

    switch (tabName) {
        case 'overview':
            loadOverviewTab(contentArea);
            break;
        case 'orders':
            loadOrdersTab(contentArea);
            break;
        case 'sales':
            loadSalesTab(contentArea);
            break;
        case 'myproducts':
            loadMyProductsTab(contentArea);
            break;
        case 'balance':
            loadBalanceTab(contentArea);
            break;
        case 'membership':
            loadMembershipTab(contentArea);
            break;
        case 'cardmanage':
            loadCardManageTab(contentArea);
            break;
        case 'paymentmanage':
            loadPaymentManageTab(contentArea);
            break;
        case 'messages':
            loadMessagesTab(contentArea);
            break;
    }
}

function renderDashboard(tabName = null) {
    if (!App.currentUser) return;

    document.getElementById('dashUsername').textContent = App.currentUser.username;
    document.getElementById('dashAvatar').textContent = App.currentUser.username.charAt(0).toUpperCase();
    document.getElementById('dashBalance').textContent = '¥ ' + App.currentUser.balance.toFixed(2);

    let sidebarHtml = `
        <div class="sidebar-nav-item active" data-tab="overview">
            <i class="bi bi-house"></i><span>概览</span>
        </div>
        <div class="sidebar-nav-item" data-tab="orders">
            <i class="bi bi-receipt"></i><span>购买记录</span>
        </div>
        <div class="sidebar-nav-item" data-tab="sales">
            <i class="bi bi-graph-up"></i><span>售出记录</span>
        </div>
        <div class="sidebar-nav-item" data-tab="myproducts">
            <i class="bi bi-box-seam"></i><span>我的商品</span>
        </div>
        <div class="sidebar-nav-item" data-tab="balance">
            <i class="bi bi-wallet2"></i><span>余额管理</span>
        </div>
        <div class="sidebar-nav-item" data-tab="membership">
            <i class="bi bi-gem"></i><span>会员中心</span>
        </div>
    `;
    if (App.currentUser.role === 'admin') {
        sidebarHtml += `
            <div class="sidebar-nav-item" data-tab="cardmanage">
                <i class="bi bi-credit-card-2-front"></i><span>卡密管理</span>
            </div>
            <div class="sidebar-nav-item" data-tab="paymentmanage">
                <i class="bi bi-cash-stack"></i><span>支付接口</span>
            </div>
            <div class="sidebar-nav-item" onclick="openSystemConfigModal()">
                <i class="bi bi-gear"></i><span>系统设置</span>
            </div>
        `;
    }
    sidebarHtml += `
        <div class="sidebar-nav-item" data-tab="messages">
            <i class="bi bi-chat-dots"></i><span>私信</span>
        </div>
    `;
    document.getElementById('dashSidebar').innerHTML = sidebarHtml;

    document.querySelectorAll('#dashSidebar .sidebar-nav-item[data-tab]').forEach(item => {
        item.onclick = function() {
            renderDashboardTab(this.dataset.tab);
        };
    });

    const activeTab = tabName || App.currentTab || 'overview';
    const hasActiveTab = !!document.querySelector(`#dashSidebar .sidebar-nav-item[data-tab="${activeTab}"]`);
    renderDashboardTab(hasActiveTab ? activeTab : 'overview');
}

async function loadOverviewTab(area) {
    const result = await API.getOverview();
    if (!result.success) {
        area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
        return;
    }

    const o = result.overview;
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2 text-primary"></i>控制台概览</h5>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card primary">
                    <i class="bi bi-cart-check"></i>
                    <div class="stat-value">${o.total_orders}</div>
                    <div class="stat-label">总购买</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card success">
                    <i class="bi bi-graph-up"></i>
                    <div class="stat-value">${o.total_sales}</div>
                    <div class="stat-label">总售出</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card warning">
                    <i class="bi bi-wallet2"></i>
                    <div class="stat-value">¥${o.total_spent.toFixed(2)}</div>
                    <div class="stat-label">消费</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card info">
                    <i class="bi bi-box-seam"></i>
                    <div class="stat-value">${o.active_products}</div>
                    <div class="stat-label">在售商品</div>
                </div>
            </div>
        </div>
        <h6 class="fw-bold mb-3">最近购买记录</h6>
        ${o.recent_orders.length === 0 ?
            '<p class="text-muted">暂无购买记录</p>' :
            `<div class="bg-light rounded-3 p-3">
                ${o.recent_orders.map(o => `
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>${Utils.truncate(o.product_title, 25)}</span>
                        <span class="text-danger fw-semibold">-¥${o.price.toFixed(2)}</span>
                        <span class="text-muted small">${Utils.formatDate(o.purchase_date)}</span>
                    </div>
                `).join('')}
            </div>`
        }
    `;
}

async function loadOrdersTab(area) {
    const result = await API.getMyOrders();
    if (!result.success || result.orders.length === 0) {
        area.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-receipt"></i>
                <h5>暂无购买记录</h5>
                <p>快去市场选购商品吧</p>
            </div>
        `;
        return;
    }

    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-receipt me-2"></i>购买记录</h5>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>商品</th>
                        <th>价格</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.orders.map(o => `
                        <tr>
                            <td>${Utils.truncate(o.product_title, 20)}</td>
                            <td class="text-danger fw-semibold">¥${o.price.toFixed(2)}</td>
                            <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="viewDeliveryInfo('${o.id}')">查看发货</button>
                                <button class="btn btn-sm btn-primary" onclick="openCommentModal('${o.product_id}', '${o.id}')">评价</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function loadSalesTab(area) {
    const result = await API.getMySales();
    if (!result.success || result.orders.length === 0) {
        area.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-graph-up"></i>
                <h5>暂无售出记录</h5>
                <p>发布商品开始销售吧</p>
            </div>
        `;
        return;
    }

    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-graph-up me-2"></i>售出记录</h5>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>商品</th>
                        <th>买家</th>
                        <th>价格</th>
                        <th>时间</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.orders.map(o => `
                        <tr>
                            <td>${Utils.truncate(o.product_title, 20)}</td>
                            <td>${o.buyer_name}</td>
                            <td class="text-success fw-semibold">+¥${o.seller_amount ? o.seller_amount.toFixed(2) : o.price.toFixed(2)}</td>
                            <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function loadMyProductsTab(area) {
    const result = await API.getMyProducts();
    if (!result.success || result.products.length === 0) {
        area.innerHTML = `
            <div class="empty-state">
                <i class="bi bi-box-seam"></i>
                <h5>暂无发布商品</h5>
                <button class="btn btn-primary mt-2" onclick="openPublishModal()">
                    <i class="bi bi-plus-circle me-1"></i>发布商品
                </button>
            </div>
        `;
        return;
    }

    area.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>我的商品 (${result.products.length})</h5>
            <button class="btn btn-primary btn-sm" onclick="openPublishModal()">
                <i class="bi bi-plus-circle me-1"></i>发布新商品
            </button>
        </div>
        <div class="row g-3">
            ${result.products.map(p => `
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge badge-primary mb-1">${p.category}</span>
                                    <h6 class="fw-bold">${p.title}</h6>
                                    <p class="text-muted small mb-1">
                                        库存: ${p.stock} | 已售: ${p.sales} | ¥${p.price.toFixed(2)}
                                    </p>
                                </div>
                                <button class="btn btn-danger btn-sm" onclick="deleteProduct('${p.id}')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

async function loadBalanceTab(area) {
    const result = await API.getMyRequests();
    const paymentResult = await API.getMyPaymentOrders();
    const configsResult = await API.getPaymentConfigs();
    const sysConfigResult = await API.getSystemConfig();
    const isAdmin = App.currentUser && App.currentUser.role === 'admin';

    const sysConfig = sysConfigResult.success ? sysConfigResult.config : {};
    const enableWithdraw = sysConfig.enable_withdraw ?? true;
    const minWithdrawAmount = sysConfig.min_withdraw_amount ?? 10;
    const withdrawFeeRate = sysConfig.withdraw_fee_rate ?? 0.01;

    let requestsHtml = '';
    if (!result.success || result.requests.length === 0) {
        requestsHtml = '<p class="text-muted mt-3">暂无申请记录</p>';
    } else {
        requestsHtml = `
            <div class="mt-3">
                ${result.requests.map(r => `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <span>${r.type === 'deposit' ? '充值' : (r.payment_method ? '提现-' + r.payment_method : '提现')} ¥${r.amount.toFixed(2)}</span>
                            ${r.payment_account ? `<br><small class="text-muted">收款: ${r.payment_account}</small>` : ''}
                        </div>
                        <div class="text-end">
                            <span class="badge badge-${r.status === 'approved' || r.status === 'paid' ? 'success' : r.status === 'rejected' || r.status === 'rejected' ? 'danger' : 'warning'}">
                                ${r.status === 'approved' || r.status === 'paid' ? '已通过' : r.status === 'rejected' ? '已拒绝' : '待处理'}
                            </span>
                            ${r.admin_note ? `<br><small class="text-muted">${r.admin_note}</small>` : ''}
                        </div>
                        <span class="text-muted small">${Utils.formatDate(r.created_at)}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    let paymentOrdersHtml = '';
    if (!paymentResult.success || paymentResult.orders.length === 0) {
        paymentOrdersHtml = '<p class="text-muted mt-3">暂无在线充值记录</p>';
    } else {
        paymentOrdersHtml = `
            <div class="mt-3">
                ${paymentResult.orders.map(o => `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>在线充值 ¥${o.amount.toFixed(2)}</span>
                        <span class="badge badge-${o.status === 'paid' ? 'success' : o.status === 'pending' ? 'warning' : 'danger'}">
                            ${o.status === 'paid' ? '已到账' : o.status === 'pending' ? '处理中' : '失败'}
                        </span>
                        <span class="text-muted small">${Utils.formatDate(o.created_at)}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    let paymentConfigsHtml = '';
    if (!configsResult.success || configsResult.configs.length === 0) {
        paymentConfigsHtml = '<p class="text-muted text-center py-3">暂无可使用的支付方式</p>';
    } else {
        paymentConfigsHtml = `
            <div class="row g-2">
                ${configsResult.configs.map(c => `
                    <div class="col-12">
                        <div class="card payment-method-card" style="cursor: pointer;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="fw-bold mb-1">${c.name}</h6>
                                        ${c.fee_rate > 0 ? `<small class="text-muted">手续费: ${(c.fee_rate * 100).toFixed(1)}%</small>` : ''}
                                    </div>
                                    <i class="bi bi-credit-card-2-back" style="font-size: 1.5rem; color: var(--primary-accent);"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    let adminSection = '';
    if (isAdmin) {
        const allResult = await API.getAllRequests();
        if (allResult.success && allResult.requests.length > 0) {
            adminSection = `
                <div class="mt-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-check me-1"></i>所有用户请求</h6>
                    <div class="bg-light rounded-3 p-3">
                        ${allResult.requests.map(r => `
                            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <div>
                                    <strong>${r.username}</strong>
                                    <span class="text-muted ms-2">${r.type === 'deposit' ? '充值' : '提现'} ¥${r.amount.toFixed(2)}</span>
                                    ${r.payment_account ? `<br><small class="text-muted">收款方式: ${r.payment_method} - ${r.payment_account}</small>` : ''}
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="text-muted small">${Utils.formatDate(r.created_at)}</span>
                                    ${r.status === 'pending' ? `
                                        <button class="btn btn-success btn-sm" onclick="approveRequest('${r.id}')">通过</button>
                                        <button class="btn btn-danger btn-sm" onclick="rejectRequest('${r.id}')">拒绝</button>
                                    ` : `
                                        <span class="badge badge-${r.status === 'approved' || r.status === 'paid' ? 'success' : 'danger'}">
                                            ${r.status === 'approved' || r.status === 'paid' ? '已通过' : '已拒绝'}
                                        </span>
                                    `}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
    }

    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-wallet2 me-2"></i>余额管理</h5>
        <div class="card bg-light mb-4">
            <div class="card-body text-center py-4">
                <h2 class="fw-bold text-primary mb-1">¥ ${App.currentUser.balance.toFixed(2)}</h2>
                <p class="text-muted mb-3">当前余额</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <button class="btn btn-primary" onclick="openOnlineRechargeModal()">
                        <i class="bi bi-cash-stack me-1"></i>在线充值
                    </button>
                    <button class="btn btn-success" onclick="openCardRechargeModal()">
                        <i class="bi bi-credit-card-2-front me-1"></i>卡密充值
                    </button>
                    ${enableWithdraw ? `
                        <button class="btn btn-warning" onclick="openWithdrawModal()">
                            <i class="bi bi-box-arrow-up me-1"></i>申请提现
                        </button>
                    ` : ''}
                </div>
                ${enableWithdraw ? `
                    <p class="text-muted small mt-2 mb-0">
                        提现说明：最低 ¥${minWithdrawAmount}，手续费 ${(withdrawFeeRate * 100).toFixed(1)}%，7个工作日内处理
                    </p>
                ` : ''}
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h6 class="fw-bold">在线充值记录</h6>
                ${paymentOrdersHtml}
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold">充值/提现记录</h6>
                ${requestsHtml}
            </div>
        </div>
        ${adminSection}
    `;
}

async function loadMembershipTab(area) {
    const [levelsResult, myLevelResult] = await Promise.all([
        API.getMembershipLevels(),
        API.getMyMembership()
    ]);

    if (!levelsResult.success) {
        area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
        return;
    }

    const levels = levelsResult.levels || {};
    const levelList = Object.values(levels).filter(level => level.enabled !== false).sort((a, b) => Number(a.priority || 0) - Number(b.priority || 0));
    const myLevelName = myLevelResult.level || 'Free';
    const myLevel = myLevelResult.level_info || levels[myLevelName] || {};
    const currentPriority = Number(myLevel.priority || 0);

    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-gem me-2"></i>会员中心</h5>
        <div class="membership-cards">
            ${levelList.map(level => {
                const levelName = level.name || '';
                const levelPriority = Number(level.priority || 0);
                const isCurrentLevel = myLevelName === levelName;
                const isLowerLevel = !isCurrentLevel && myLevelName !== 'Free' && levelPriority < currentPriority;
                const cost = Number(level.cost || 0);
                const canAfford = !App.currentUser || Number(App.currentUser.balance || 0) >= cost;
                const levelGradient = level.gradient || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6)';
                const levelIcon = level.icon || 'bi-gem';
                const levelPrivileges = [
                    `单商品最大 ${level.max_accounts_per_product || 0} 账号`,
                    Number(level.max_products || 0) >= 9999 ? '无限商品' : `${level.max_products || 0} 个商品`,
                    `手续费 ${(Number(level.fee_rate || 0) * 100).toFixed(2).replace(/\.00$/, '')}%`,
                    Number(level.publish_fee_per_account || 0) === 0 ? '发布免费' : `发布费 ¥${level.publish_fee_per_account}/账号`
                ];
                const footerHtml = isCurrentLevel
                    ? '<span class="btn btn-outline-secondary w-100 disabled"><i class="bi bi-check-circle"></i> 当前会员</span>'
                    : isLowerLevel
                        ? '<span class="btn btn-outline-secondary w-100 disabled"><i class="bi bi-lock"></i> 当前会员比此会员等级高，禁止升级</span>'
                        : level.can_upgrade === false
                            ? '<span class="btn btn-outline-secondary w-100 disabled"><i class="bi bi-lock"></i> 暂不支持开通</span>'
                            : `<button class="btn btn-primary w-100" onclick="upgradeMembership('${Security.escapeAttr(levelName)}')" ${!canAfford ? 'disabled' : ''}>
                                <i class="bi bi-rocket-takeoff"></i> ${cost === 0 ? '免费开通' : '立即开通'}
                            </button>`;

                return `
                    <div class="membership-card ${isCurrentLevel ? 'current' : ''}" style="--card-gradient: ${Security.escapeAttr(levelGradient)};">
                        <div class="card-header">
                            <i class="bi ${Security.escapeAttr(levelIcon)}"></i>
                            <h5>${Security.escapeHtml(levelName)}</h5>
                            ${isCurrentLevel ? '<span class="current-badge">当前会员</span>' : ''}
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                ${cost === 0 ? '<span class="badge bg-success-light text-success fs-5"><i class="bi bi-gift"></i> 免费</span>' : `<span class="badge bg-primary-light text-primary fs-5"><i class="bi bi-cash"></i> ¥${cost.toFixed(2)}</span>`}
                            </div>
                            <ul class="privilege-list">
                                ${levelPrivileges.map(p => `<li><i class="bi bi-check"></i> ${Security.escapeHtml(p)}</li>`).join('')}
                            </ul>
                        </div>
                        <div class="card-footer">${footerHtml}</div>
                    </div>
                `;
            }).join('') || '<div class="text-muted py-4">暂无会员等级</div>'}
        </div>
    `;
}

async function loadCardManageTab(area) {
    const result = await API.getCards(false);
    if (!result.success) {
        area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
        return;
    }

    const cards = result.cards || [];
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-credit-card-2-front me-2"></i>卡密管理</h5>
        <div class="card bg-light mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">生成新卡密</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">面值</label>
                        <input type="number" id="cardAmount" class="form-control" placeholder="金额" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">数量</label>
                        <input type="number" id="cardCount" class="form-control" placeholder="1-100" min="1" max="100" value="1">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="generateCards()">生成</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="newCardsSection" class="mb-4" style="display: none;">
            <div class="card bg-success-light">
                <div class="card-body">
                    <h6 class="fw-bold mb-2 text-success">新生成的卡密</h6>
                    <div id="newCardsList"></div>
                </div>
            </div>
        </div>
        <h6 class="fw-bold mb-3">卡密列表</h6>
        ${cards.length === 0 ?
            '<div class="empty-state"><p>暂无卡密</p></div>' :
            `<div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>卡密</th>
                            <th>面值</th>
                            <th>状态</th>
                            <th>生成时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${cards.map(c => `
                            <tr>
                                <td><code>${c.code}</code></td>
                                <td>¥${c.amount.toFixed(2)}</td>
                                <td>
                                    <span class="badge badge-${c.used ? 'secondary' : 'success'}">
                                        ${c.used ? '已使用' : '未使用'}
                                    </span>
                                </td>
                                <td class="text-muted small">${Utils.formatDate(c.created_at)}</td>
                                <td>
                                    ${!c.used ? `
                                        <button class="btn btn-sm btn-outline" onclick="Utils.copyText('${c.code}')">复制</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteCard('${c.id}')">删除</button>
                                    ` : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>`
        }
    `;
}

async function loadPaymentManageTab(area) {
    const configResult = await API.getPaymentConfigs();
    const ordersResult = await API.getPaymentOrders();
    
    const configs = configResult.success ? configResult.configs : [];
    const orders = ordersResult.success ? ordersResult.orders : [];
    
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-cash-stack me-2"></i>支付接口管理</h5>
        
        <div class="card bg-light mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-2">支付接口配置</h6>
                        <p class="text-muted small mb-0">管理平台的支付接口，支持易支付等接口</p>
                    </div>
                    <button class="btn btn-primary" onclick="openPaymentConfigModal()">
                        <i class="bi bi-gear me-1"></i>管理接口
                    </button>
                </div>
            </div>
        </div>
        
        <h6 class="fw-bold mb-3">充值订单记录</h6>
        ${orders.length === 0 ? 
            '<p class="text-muted">暂无充值订单</p>' :
            `<div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>用户</th>
                            <th>金额</th>
                            <th>实付</th>
                            <th>状态</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${orders.map(o => `
                            <tr>
                                <td><code class="small">${o.trade_no}</code></td>
                                <td>${o.user_id}</td>
                                <td>¥${o.amount.toFixed(2)}</td>
                                <td>¥${o.actual_amount.toFixed(2)}</td>
                                <td>
                                    <span class="badge badge-${o.status === 'paid' ? 'success' : o.status === 'pending' ? 'warning' : 'danger'}">
                                        ${o.status === 'paid' ? '已支付' : o.status === 'pending' ? '待支付' : '失败'}
                                    </span>
                                </td>
                                <td class="text-muted small">${Utils.formatDate(o.created_at)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>`
        }
    `;
}

async function loadMessagesTab(area) {
    const result = await API.getContacts();
    const contacts = result.success ? result.contacts : [];

    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2"></i>私信</h5>
        <div class="row">
            <div class="col-md-4 border-end pe-0" style="max-height: 400px; overflow-y: auto;">
                <div class="p-2">
                    <input type="text" class="form-control form-control-sm mb-2" 
                           id="tabSearchUser" placeholder="搜索用户..."
                           onkeypress="if(event.key==='Enter')searchUserForChatTab()">
                    <div id="tabUserSearchResults" class="mb-2 small"></div>
                    <div id="contactListTab">
                        <p class="text-muted small px-2">联系人</p>
                        ${contacts.length === 0 ?
                            '<p class="text-muted small px-2">暂无联系人</p>' :
                            contacts.map(c => `
                                <div class="sidebar-nav-item" onclick="selectContactTab('${c.username}')">
                                    <span>${c.username}</span>
                                    ${c.unread > 0 ? `<span class="badge badge-danger ms-auto">${c.unread}</span>` : ''}
                                </div>
                            `).join('')
                        }
                    </div>
                </div>
            </div>
            <div class="col-md-8 ps-0" id="tabChatArea">
                <div class="empty-state">
                    <i class="bi bi-chat-dots"></i>
                    <p>选择联系人开始对话</p>
                </div>
            </div>
        </div>
    `;
}

async function selectContactTab(username) {
    App.currentChatPartner = username;

    const result = await API.getConversation(username);
    const messages = result.success ? result.messages : [];

    const chatArea = document.getElementById('tabChatArea');
    chatArea.innerHTML = `
        <div class="d-flex flex-column h-100">
            <div class="p-2 border-bottom bg-light">
                <strong>${username}</strong>
            </div>
            <div class="chat-container flex-grow-1" id="tabChatMessages">
                ${messages.map(m => `
                    <div class="chat-bubble ${m.from === App.currentUser.username ? 'sent' : 'received'}">
                        ${m.content}
                        <span class="chat-time">${Utils.formatDate(m.timestamp)}</span>
                    </div>
                `).join('')}
            </div>
            <div class="chat-input-area">
                <input type="text" class="form-control" id="tabChatInput" placeholder="输入消息..."
                       onkeypress="if(event.key==='Enter')sendMessageTab()">
                <button class="btn btn-primary" onclick="sendMessageTab()">
                    <i class="bi bi-send"></i>
                </button>
            </div>
        </div>
    `;

    const chatContainer = document.getElementById('tabChatMessages');
    chatContainer.scrollTop = chatContainer.scrollHeight;

    await API.getConversation(username);
    App.updateUnreadBadge();
    renderDashboardTab('messages');
}

async function sendMessageTab() {
    const input = document.getElementById('tabChatInput');
    const content = input.value.trim();
    if (!content || !App.currentChatPartner) return;

    await API.sendMessage(App.currentChatPartner, content);
    input.value = '';
    await selectContactTab(App.currentChatPartner);
}

function searchUserForChatTab() {
    const query = document.getElementById('tabSearchUser').value.trim();
    const resultsDiv = document.getElementById('tabUserSearchResults');
    if (!query) {
        resultsDiv.innerHTML = '';
        return;
    }

    API.searchUsers(query).then(result => {
        if (!result.success || result.users.length === 0) {
            resultsDiv.innerHTML = '<span class="text-muted">未找到用户</span>';
            return;
        }
        resultsDiv.innerHTML = result.users.map(u =>
            `<span class="badge badge-primary me-1" style="cursor:pointer;" 
                   onclick="selectContactTab('${u.username}')">${u.username} <i class="bi bi-chat-dots"></i></span>`
        ).join('');
    });
}

async function requestWithdraw() {
    if (App.currentUser.balance <= 0) {
        Toast.warning('余额不足');
        return;
    }
    const amount = parseFloat(prompt(`当前余额 ¥${App.currentUser.balance.toFixed(2)}，请输入提现金额：`));
    if (!amount || amount <= 0) {
        Toast.warning('请输入有效金额');
        return;
    }
    if (amount > App.currentUser.balance) {
        Toast.warning('超过可提现余额');
        return;
    }
    const result = await API.requestWithdraw(amount);
    if (result.success) {
        Toast.success(result.message);
        renderDashboardTab('balance');
    } else {
        Toast.error(result.message);
    }
}

async function approveRequest(id) {
    const note = prompt('请输入处理备注（可选）:', '');
    if (note === null) return;
    
    const result = await API.approveRequest(id, note);
    if (result.success) {
        Toast.success('已通过');
        renderDashboardTab('balance');
    } else {
        Toast.error(result.message);
    }
}

async function rejectRequest(id) {
    const note = prompt('请输入拒绝原因:', '');
    if (note === null) return;
    
    const result = await API.rejectRequest(id, note);
    if (result.success) {
        Toast.success('已拒绝');
        renderDashboardTab('balance');
    } else {
        Toast.error(result.message);
    }
}

let withdrawFeeRate = 0.01;
let minWithdrawAmount = 10;

async function openWithdrawModal() {
    const sysConfigResult = await API.getSystemConfig();
    if (sysConfigResult.success) {
        minWithdrawAmount = sysConfigResult.config.min_withdraw_amount || 10;
        withdrawFeeRate = sysConfigResult.config.withdraw_fee_rate || 0.01;
    }
    
    document.getElementById('withdrawAmount').value = '';
    document.getElementById('withdrawPaymentMethod').value = '';
    document.getElementById('withdrawAccount').value = '';
    document.getElementById('withdrawQrcode').value = '';
    document.getElementById('withdrawFeeNote').textContent = '';
    
    new bootstrap.Modal(document.getElementById('withdrawModal')).show();
}

function updateWithdrawInfo() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value) || 0;
    const feeNote = document.getElementById('withdrawFeeNote');
    
    if (amount > 0) {
        const fee = amount * withdrawFeeRate;
        const actualAmount = amount - fee;
        feeNote.textContent = `手续费 ¥${fee.toFixed(2)}，实到 ¥${actualAmount.toFixed(2)}`;
    } else {
        feeNote.textContent = `最低 ${minWithdrawAmount}元，手续费 ${(withdrawFeeRate * 100).toFixed(1)}%`;
    }
}

async function submitWithdraw() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value);
    const paymentMethod = document.getElementById('withdrawPaymentMethod').value;
    const paymentAccount = document.getElementById('withdrawAccount').value.trim();
    const qrcodeUrl = document.getElementById('withdrawQrcode').value.trim();
    
    if (!amount || amount < minWithdrawAmount) {
        Toast.warning('提现金额不能低于 ¥' + minWithdrawAmount);
        return;
    }
    
    if (!paymentMethod) {
        Toast.warning('请选择收款方式');
        return;
    }
    
    if (!paymentAccount) {
        Toast.warning('请填写收款账号');
        return;
    }
    
    if (amount > App.currentUser.balance) {
        Toast.warning('余额不足');
        return;
    }
    
    const result = await API.requestWithdraw(amount, paymentMethod, paymentAccount, qrcodeUrl);
    if (result.success) {
        Toast.success(result.message);
        bootstrap.Modal.getInstance(document.getElementById('withdrawModal'))?.hide();
        App.currentUser.balance -= amount;
        App.updateNavUI();
        renderDashboardTab('balance');
    } else {
        Toast.error(result.message);
    }
}

async function openSystemConfigModal() {
    if (App.currentUser.role !== 'admin') {
        Toast.warning('需要管理员权限');
        return;
    }
    
    const result = await API.getSystemConfig();
    if (result.success) {
        const config = result.config;
        document.getElementById('configEnableWithdraw').checked = config.enable_withdraw ?? true;
        document.getElementById('configMinWithdraw').value = config.min_withdraw_amount || 10;
        document.getElementById('configWithdrawFee').value = (config.withdraw_fee_rate || 0.01) * 100;
        document.getElementById('configWechatQrcode').value = config.admin_wechat_qrcode || '';
        document.getElementById('configAlipayQrcode').value = config.admin_alipay_qrcode || '';
    }
    
    new bootstrap.Modal(document.getElementById('systemConfigModal')).show();
}

async function saveSystemConfig() {
    const enableWithdraw = document.getElementById('configEnableWithdraw').checked;
    const minWithdraw = parseFloat(document.getElementById('configMinWithdraw').value) || 10;
    const withdrawFee = parseFloat(document.getElementById('configWithdrawFee').value) / 100 || 0.01;
    const wechatQrcode = document.getElementById('configWechatQrcode').value.trim();
    const alipayQrcode = document.getElementById('configAlipayQrcode').value.trim();
    
    const result = await API.updateSystemConfig({
        enable_withdraw: enableWithdraw,
        min_withdraw_amount: minWithdraw,
        withdraw_fee_rate: withdrawFee,
        admin_wechat_qrcode: wechatQrcode,
        admin_alipay_qrcode: alipayQrcode
    });
    
    if (result.success) {
        Toast.success('设置已保存');
        bootstrap.Modal.getInstance(document.getElementById('systemConfigModal'))?.hide();
        renderDashboardTab('balance');
    } else {
        Toast.error(result.message);
    }
}

let selectedPaymentConfig = null;

async function openOnlineRechargeModal() {
    const result = await API.getPaymentConfigs();
    if (!result.success) {
        Toast.error('加载支付方式失败');
        return;
    }
    
    const methodsDiv = document.getElementById('rechargePaymentMethods');
    if (!result.configs || result.configs.length === 0) {
        methodsDiv.innerHTML = '<p class="text-muted">暂无可使用的支付方式</p>';
    } else {
        methodsDiv.innerHTML = result.configs.map(c => {
            const methods = (c.pay_methods || ['alipay', 'wxpay']).map(payMethodLabel).join(' / ');
            return `
            <div class="col-12">
                <div class="card payment-select-card ${selectedPaymentConfig?.id === c.id ? 'border-primary' : ''}" 
                     onclick="selectPaymentConfig('${c.id}')" style="cursor: pointer;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-1">${Security.escapeHtml(c.name)}</h6>
                                <small class="text-muted">
                                    ${methods}
                                    ${c.fee_rate > 0 ? ` · 手续费: ${(c.fee_rate * 100).toFixed(1)}%` : ''}
                                </small>
                            </div>
                            ${selectedPaymentConfig?.id === c.id ? '<i class="bi bi-check-circle text-primary" style="font-size: 1.5rem;"></i>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        }).join('');
        
        if (result.configs.length > 0) {
            selectPaymentConfig(result.configs[0].id);
        }
    }
    
    new bootstrap.Modal(document.getElementById('onlineRechargeModal')).show();
}

function payMethodLabel(method) {
    return ({ alipay: '支付宝', wxpay: '微信支付', qqpay: 'QQ钱包', cashier: '易支付收银台' })[method] || method;
}

function selectPaymentConfig(configId) {
    API.getPaymentConfigs().then(r => {
        if (r.success) {
            selectedPaymentConfig = r.configs.find(c => c.id === configId);
            updateRechargePayTypeOptions();
            updateFeeInfo(selectedPaymentConfig?.fee_rate || 0);
        }
    });
    
    document.querySelectorAll('.payment-select-card').forEach(card => {
        card.classList.remove('border-primary');
        const checkIcon = card.querySelector('.bi-check-circle');
        if (checkIcon) checkIcon.remove();
    });
    
    const selectedCard = Array.from(document.querySelectorAll('.payment-select-card')).find(card => 
        card.getAttribute('onclick')?.includes(`'${configId}'`)
    );
    if (selectedCard) {
        selectedCard.classList.add('border-primary');
        const icon = document.createElement('i');
        icon.className = 'bi bi-check-circle text-primary';
        icon.style.fontSize = '1.5rem';
        selectedCard.querySelector('.card-body').querySelector('.d-flex').appendChild(icon);
    }
}

function updateRechargePayTypeOptions() {
    const select = document.getElementById('rechargePayType');
    const help = document.getElementById('rechargePayTypeHelp');
    if (!select || !selectedPaymentConfig) return;
    const methods = selectedPaymentConfig.pay_methods || ['alipay', 'wxpay'];
    select.innerHTML = methods.map(method => `<option value="${method}">${payMethodLabel(method)}</option>`).join('');
    if (help) {
        help.textContent = `当前接口支持：${methods.map(payMethodLabel).join('、')}`;
    }
}

function updateFeeInfo(feeRate) {
    const amount = parseFloat(document.getElementById('rechargeAmount')?.value) || 0;
    const feeInfoDiv = document.getElementById('rechargeFeeInfo');
    const feeTextSpan = document.getElementById('rechargeFeeText');
    
    if (feeRate > 0 && amount > 0) {
        const fee = amount * feeRate;
        const total = amount + fee;
        feeInfoDiv.style.display = 'block';
        feeTextSpan.textContent = `充值 ¥${amount.toFixed(2)}，手续费 ¥${fee.toFixed(2)}，合计 ¥${total.toFixed(2)}`;
    } else if (feeRate > 0) {
        feeInfoDiv.style.display = 'block';
        feeTextSpan.textContent = `手续费率 ${(feeRate * 100).toFixed(1)}%`;
    } else {
        feeInfoDiv.style.display = 'none';
    }
}

async function submitOnlineRecharge() {
    const amount = parseFloat(document.getElementById('rechargeAmount')?.value);
    const payType = document.getElementById('rechargePayType')?.value;
    
    if (!amount || amount <= 0) {
        Toast.warning('请输入有效金额');
        return;
    }
    if (!selectedPaymentConfig) {
        Toast.warning('请选择支付方式');
        return;
    }
    const methods = selectedPaymentConfig.pay_methods || ['alipay', 'wxpay'];
    if (!methods.includes(payType)) {
        Toast.warning('当前接口不支持所选支付类型');
        return;
    }
    
    const result = await API.createPaymentOrder(selectedPaymentConfig.id, amount, payType);
    if (result.success) {
        window.open(result.payment_url, '_blank');
        Toast.success('请在新窗口完成支付');
        bootstrap.Modal.getInstance(document.getElementById('onlineRechargeModal'))?.hide();
    } else {
        Toast.error(result.message);
    }
}

async function openPaymentConfigModal() {
    await loadPaymentConfigs();
    new bootstrap.Modal(document.getElementById('paymentConfigModal')).show();
}

async function loadPaymentConfigs() {
    const result = await API.getPaymentConfigs();
    const listDiv = document.getElementById('paymentConfigList');
    
    if (!result.success || result.configs.length === 0) {
        listDiv.innerHTML = '<p class="text-muted text-center py-3">暂无支付接口配置</p>';
        return;
    }
    
    listDiv.innerHTML = result.configs.map(c => `
        <div class="card mb-2">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-1">${c.name}</h6>
                        <small class="text-muted">
                            ${c.enabled ? '<span class="text-success">已启用</span>' : '<span class="text-danger">已禁用</span>'}
                            ${c.fee_rate > 0 ? ` · 手续费: ${(c.fee_rate * 100).toFixed(1)}%` : ''}
                        </small>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline" onclick="editPaymentConfig('${c.id}')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deletePaymentConfig('${c.id}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

let editingPaymentConfigId = null;

function toggleAddPaymentConfig() {
    const formDiv = document.getElementById('addPaymentConfigForm');
    const saveBtn = document.getElementById('savePaymentConfigBtn');
    
    if (formDiv.style.display === 'none') {
        formDiv.style.display = 'block';
        saveBtn.style.display = 'block';
        editingPaymentConfigId = null;
        clearPaymentConfigForm();
    } else {
        formDiv.style.display = 'none';
        saveBtn.style.display = 'none';
        editingPaymentConfigId = null;
    }
}

function clearPaymentConfigForm() {
    document.getElementById('paymentConfigName').value = '';
    document.getElementById('paymentConfigType').value = 'yipay';
    document.getElementById('paymentConfigApiUrl').value = '';
    document.getElementById('paymentConfigPartnerId').value = '';
    document.getElementById('paymentConfigKey').value = '';
    document.getElementById('paymentConfigFeeRate').value = '';
    document.getElementById('paymentConfigEnabled').checked = true;
}

async function editPaymentConfig(id) {
    const result = await API.getPaymentConfigs();
    if (!result.success) return;
    
    const config = result.configs.find(c => c.id === id);
    if (!config) return;
    
    editingPaymentConfigId = id;
    document.getElementById('paymentConfigName').value = config.name;
    document.getElementById('paymentConfigType').value = config.type;
    document.getElementById('paymentConfigApiUrl').value = config.api_url;
    document.getElementById('paymentConfigPartnerId').value = config.partner_id;
    document.getElementById('paymentConfigKey').value = config.key;
    document.getElementById('paymentConfigFeeRate').value = config.fee_rate * 100;
    document.getElementById('paymentConfigEnabled').checked = config.enabled;
    
    document.getElementById('addPaymentConfigForm').style.display = 'block';
    document.getElementById('savePaymentConfigBtn').style.display = 'block';
}

async function savePaymentConfig() {
    const name = document.getElementById('paymentConfigName').value.trim();
    const type = document.getElementById('paymentConfigType').value;
    const apiUrl = document.getElementById('paymentConfigApiUrl').value.trim();
    const partnerId = document.getElementById('paymentConfigPartnerId').value.trim();
    const key = document.getElementById('paymentConfigKey').value.trim();
    const feeRate = parseFloat(document.getElementById('paymentConfigFeeRate')?.value) / 100;
    const enabled = document.getElementById('paymentConfigEnabled').checked;
    
    if (!name || !apiUrl || !partnerId || !key) {
        Toast.warning('请填写完整信息');
        return;
    }
    
    let result;
    if (editingPaymentConfigId) {
        result = await API.updatePaymentConfig(editingPaymentConfigId, {
            name, type, api_url: apiUrl, partner_id: partnerId, key, fee_rate: feeRate, enabled
        });
    } else {
        result = await API.addPaymentConfig({
            name, type, api_url: apiUrl, partner_id: partnerId, key, fee_rate: feeRate, enabled
        });
    }
    
    if (result.success) {
        Toast.success('保存成功');
        loadPaymentConfigs();
        document.getElementById('addPaymentConfigForm').style.display = 'none';
        document.getElementById('savePaymentConfigBtn').style.display = 'none';
        editingPaymentConfigId = null;
    } else {
        Toast.error(result.message);
    }
}

async function deletePaymentConfig(id) {
    if (!confirm('确定要删除这个支付接口吗？')) return;
    
    const result = await API.deletePaymentConfig(id);
    if (result.success) {
        Toast.success('已删除');
        loadPaymentConfigs();
    } else {
        Toast.error(result.message);
    }
}

async function upgradeMembership(levelName) {
    const confirmed = await new Promise(resolve => {
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        document.getElementById('confirmModalTitle').textContent = '确认升级到 ' + levelName;
        document.getElementById('confirmModalBody').innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>重要提醒：</strong>
                <ul class="mb-0 mt-2">
                    <li>升级到 ${levelName} 后无法降级</li>
                    <li>升级到 ${levelName} 后无法更换为其他等级</li>
                    <li>此操作不可撤销</li>
                </ul>
            </div>
            <p class="text-center text-muted mt-3 mb-0">确定要继续吗？</p>
        `;
        document.getElementById('confirmModalBtn').onclick = () => {
            modal.hide();
            resolve(true);
        };
        document.getElementById('confirmModalCancelBtn').onclick = () => {
            modal.hide();
            resolve(false);
        };
        modal.show();
    });

    if (!confirmed) return;
    
    const result = await API.request('membership.php?action=upgrade', 'POST', { 
        level: levelName,
        confirmed: 1 
    });
    
    if (result.success) {
        Toast.success(result.message);
        await refreshUserData();
        renderDashboardTab('membership');
    } else {
        Toast.error(result.message);
    }
}

function getLowerLevels(levelName) {
    const levels = ['Free', 'VIP', 'PRO', 'Infinite'];
    const currentIndex = levels.indexOf(levelName);
    if (currentIndex <= 0) return '无';
    return levels.slice(0, currentIndex).join('、');
}

async function generateCards() {
    const amount = parseFloat(document.getElementById('cardAmount').value);
    const count = parseInt(document.getElementById('cardCount').value);

    if (!amount || amount <= 0) {
        Toast.warning('请输入有效金额');
        return;
    }
    if (!count || count < 1 || count > 100) {
        Toast.warning('数量应在1-100之间');
        return;
    }

    const result = await API.createCards(amount, count);
    if (!result.success) {
        Toast.error(result.message);
        return;
    }

    Toast.success(result.message);
    
    const newCardsSection = document.getElementById('newCardsSection');
    const newCardsList = document.getElementById('newCardsList');
    newCardsSection.style.display = 'block';
    newCardsList.innerHTML = result.cards.map(c =>
        `<div class="d-flex justify-content-between align-items-center py-1">
            <code>${c.code}</code>
            <span>¥${c.amount.toFixed(2)}</span>
            <button class="btn btn-sm btn-outline" onclick="Utils.copyText('${c.code}')">复制</button>
        </div>`
    ).join('');

    loadCardManageTab(document.getElementById('dashContentArea'));
}

async function deleteCard(id) {
    if (!confirm('确定要删除此卡密吗？')) return;

    const result = await API.deleteCard(id);
    if (result.success) {
        Toast.success('已删除');
        loadCardManageTab(document.getElementById('dashContentArea'));
    } else {
        Toast.error(result.message);
    }
}

function openCardRechargeModal() {
    const modal = new bootstrap.Modal(document.getElementById('cardRechargeModal'));
    document.getElementById('cardRechargeInput').value = '';
    modal.show();
}

async function useCardRecharge() {
    const code = document.getElementById('cardRechargeInput').value.trim();
    if (!code) {
        Toast.warning('请输入卡密');
        return;
    }

    const result = await API.useCard(code);
    if (result.success) {
        Toast.success(result.message);
        bootstrap.Modal.getInstance(document.getElementById('cardRechargeModal')).hide();
        await refreshUserData();
        if (App.currentPage === 'dashboard') {
            renderDashboardTab('balance');
        }
    } else {
        Toast.error(result.message);
    }
}

async function refreshUserData() {
    const result = await API.getCurrentUser();
    if (result.success && result.logged_in) {
        App.setUser(result.user);
    }
}
