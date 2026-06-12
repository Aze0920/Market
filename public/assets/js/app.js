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
        return /^[\p{L}\p{N}_\u4e00-\u9fa5]{2,30}$/u.test(username || '');
    },
    
    validateEmail: function(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    validatePassword: function(password) {
        return password && password.length >= 6;
    }
};

function userAvatarUrl(user = {}) {
    const value = String(user.avatar || '').trim();
    if (/^\/uploads\/avatars\/[a-zA-Z0-9_.-]+\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(value)) {
        return value;
    }
    return '';
}

function avatarHtml(user = {}, className = '') {
    const initial = Security.escapeHtml(String(user.username || 'U').charAt(0).toUpperCase());
    const url = userAvatarUrl(user);
    const classes = `avatar ${className}`.trim();
    if (url) {
        return `<div class="${Security.escapeAttr(classes)} has-image"><img src="${Security.escapeAttr(url)}" alt="头像" onerror="this.remove(); this.parentElement.classList.remove('has-image'); this.parentElement.textContent='${initial}';"></div>`;
    }
    return `<div class="${Security.escapeAttr(classes)}">${initial}</div>`;
}

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
            if (guestArea) guestArea.classList.add('hidden');
            const guestOrderLink = document.getElementById('navGuestOrderLink');
            if (guestOrderLink) guestOrderLink.classList.add('hidden');
            if (userArea) userArea.classList.remove('hidden');
            if (dashboardLink) dashboardLink.classList.remove('hidden');
            if (publishLink) publishLink.classList.remove('hidden');

            document.getElementById('navUsername').textContent = Security.escapeHtml(this.currentUser.username);
            const navAvatar = document.getElementById('navAvatar');
            const navProfileBtn = document.getElementById('navProfileBtn');
            if (navAvatar) {
                const avatarUrl = userAvatarUrl(this.currentUser);
                navAvatar.classList.toggle('has-image', !!avatarUrl);
                navAvatar.innerHTML = avatarUrl
                    ? `<img src="${Security.escapeAttr(avatarUrl)}" alt="头像" onerror="this.remove(); this.parentElement.classList.remove('has-image'); this.parentElement.textContent='${Security.escapeHtml(this.currentUser.username.charAt(0).toUpperCase())}';">`
                    : Security.escapeHtml(this.currentUser.username.charAt(0).toUpperCase());
                navAvatar.onclick = () => showDashboard('profile');
                navAvatar.title = '个人中心';
                navAvatar.setAttribute('role', 'button');
            }
            if (navProfileBtn) navProfileBtn.classList.add('hidden');
            document.getElementById('navBalance').textContent = '¥ ' + this.currentUser.balance.toFixed(2) + (this.currentUser.frozen_balance > 0 ? '（冻结 ¥' + Number(this.currentUser.frozen_balance).toFixed(2) + '）' : '');
            const navAdminBtn = document.getElementById('navAdminBtn');
            if (navAdminBtn) {
                navAdminBtn.classList.toggle('hidden', this.currentUser.role !== 'admin');
            }
        } else {
            if (guestArea) guestArea.classList.remove('hidden');
            const guestOrderLink = document.getElementById('navGuestOrderLink');
            if (guestOrderLink) guestOrderLink.classList.remove('hidden');
            if (userArea) userArea.classList.add('hidden');
            if (dashboardLink) dashboardLink.classList.add('hidden');
            if (publishLink) publishLink.classList.add('hidden');
            const navAdminBtn = document.getElementById('navAdminBtn');
            if (navAdminBtn) {
                navAdminBtn.classList.add('hidden');
            }
        }
    },

    async updateUnreadBadge() {
        const badge = document.getElementById('unreadBadge');
        if (!badge) return;
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
        const value = text == null ? '' : String(text);
        navigator.clipboard.writeText(value).then(function() {
            window.Toast.success('已复制到剪贴板');
        }).catch(function() {
            window.Toast.error('复制失败');
        });
    }
};

document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    e.preventDefault();
    Utils.copyText(btn.getAttribute('data-copy') || '');
});

function cleanupBootstrapModalArtifacts() {
    const visibleModals = Array.from(document.querySelectorAll('.modal.show'));
    const backdrops = Array.from(document.querySelectorAll('.modal-backdrop'));
    if (visibleModals.length === 0) {
        backdrops.forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
        return;
    }
    if (backdrops.length > visibleModals.length) {
        backdrops.slice(0, backdrops.length - visibleModals.length).forEach(el => el.remove());
    }
}

function hideModalSafely(id) {
    const el = typeof id === 'string' ? document.getElementById(id) : id;
    if (!el || typeof bootstrap === 'undefined') return;
    bootstrap.Modal.getInstance(el)?.hide();
}

function showModalSafely(id, options = {}) {
    const el = typeof id === 'string' ? document.getElementById(id) : id;
    if (!el || typeof bootstrap === 'undefined') return null;
    (options.hide || []).forEach(hideModalSafely);
    setTimeout(cleanupBootstrapModalArtifacts, 180);
    const modal = bootstrap.Modal.getOrCreateInstance(el);
    modal.show();
    return modal;
}

document.addEventListener('hidden.bs.modal', () => {
    setTimeout(cleanupBootstrapModalArtifacts, 80);
});

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

const FRONT_DASHBOARD_TABS = ['overview', 'orders', 'sales', 'myproducts', 'balance', 'membership', 'subdomain', 'cardmanage', 'paymentmanage', 'profile', 'customlabel', 'messages', 'reviews', 'complaints'];

function isSubdomainFeatureEnabled() {
    const c = window.KeyNestSystemConfig || {};
    const v = c.subdomain_enabled;
    return v === true || v === '1' || v === 1;
}

window.SellerStore = {
    active: false,
    sellerId: null,
    sellerName: '',
    prefix: '',
    fullDomain: '',
    expired: false,
    pending: false,
    disabled: false,
    message: ''
};

async function initSellerStoreContext() {
    const res = await API.resolveSubdomain(window.location.hostname);
    if (!res.success) return;
    if (!res.seller_id && !res.prefix) {
        window.SellerStore = { active: false, sellerId: null, sellerName: '', prefix: '', fullDomain: '', expired: false, pending: false, disabled: false, message: '', reason: res.reason || '' };
        return;
    }
    window.SellerStore = {
        active: !!res.active,
        sellerId: res.seller_id || null,
        sellerName: res.seller_name || '',
        prefix: res.prefix || '',
        fullDomain: res.full_domain || '',
        expired: !!res.expired,
        pending: !!res.pending,
        disabled: !!res.disabled,
        message: res.message || '',
        reason: res.reason || '',
        status: res.status || ''
    };
    updateSellerStoreBanner();
}

function updateSellerStoreBanner() {
    const banner = document.getElementById('sellerStoreBanner');
    if (!banner) return;
    const store = window.SellerStore || {};
    const isUnavailableSubdomain = !!store.prefix && !store.active;
    if (!store.sellerId && !isUnavailableSubdomain) {
        banner.classList.add('hidden');
        banner.innerHTML = '';
        return;
    }
    banner.classList.remove('hidden');
    if (store.active && store.sellerId) {
        banner.className = 'alert alert-info py-2 px-3 mb-3';
        banner.innerHTML = `<i class="bi bi-shop me-1"></i>当前正在浏览 <strong>${Security.escapeHtml(store.sellerName || store.prefix)}</strong> 的专属店铺（${Security.escapeHtml(store.fullDomain || '')}）`;
        return;
    }
    const toneMap = {
        not_found: 'alert-secondary',
        pending: 'alert-warning',
        expired: 'alert-warning',
        disabled: 'alert-danger',
        rejected: 'alert-danger',
        inactive: 'alert-warning',
        feature_disabled: 'alert-warning',
        base_domain_missing: 'alert-warning'
    };
    banner.className = 'alert py-2 px-3 mb-3 ' + (toneMap[store.reason] || 'alert-warning');
    const fallback = store.reason === 'feature_disabled'
        ? '二级域名功能未开启，请先在后台系统设置中开启'
        : (store.reason === 'base_domain_missing'
            ? '后台尚未配置二级域名主域名'
            : (store.reason === 'not_found'
                ? '当前域名未分配，请联系管理员开通后再访问'
                : '当前二级域名暂不可用'));
    banner.innerHTML = `<i class="bi bi-globe2 me-1"></i>${Security.escapeHtml(store.message || fallback)}`;
}

function goMarketHome() {
    showHome({ resetFilters: true });
}

function getInitialFrontendState() {
    const hash = normalizeFrontendHash();
    const page = hash.get('page') || localStorage.getItem('keynest_front_page') || 'home';
    const tab = hash.get('tab') || localStorage.getItem('keynest_front_tab') || 'overview';
    return {
        page: ['home', 'dashboard'].includes(page) ? page : 'home',
        tab: FRONT_DASHBOARD_TABS.includes(tab) ? tab : 'overview'
    };
}

function resetMarketFilters() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    if (searchInput) {
        searchInput.value = '';
        searchInput.defaultValue = '';
    }
    if (categoryFilter) categoryFilter.value = 'all';
}

function showHome(options = {}) {
    const opts = typeof options === 'object' && options !== null ? options : {};
    App.currentPage = 'home';
    persistFrontendState();
    document.getElementById('homePage').classList.remove('hidden');
    document.getElementById('dashboardPage').classList.add('hidden');

    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    const navLinks = Array.from(document.querySelectorAll('.nav-link'));
    const marketLink = navLinks.find(link => link.textContent.includes('市场'));
    if (marketLink) marketLink.classList.add('active');

    const store = window.SellerStore || {};
    const marketTitle = document.getElementById('marketTitle');
    const marketDescription = document.getElementById('marketDescription');
    const isUnavailableSubdomain = !!store.prefix && !store.active;
    if (store.sellerId && marketTitle) {
        marketTitle.textContent = (store.sellerName || store.prefix || '卖家') + ' 的店铺';
    } else if (isUnavailableSubdomain && marketTitle) {
        marketTitle.textContent = '域名未开通';
    }
    if (store.sellerId && marketDescription) {
        marketDescription.textContent = store.active ? '仅展示该卖家的全部商品' : (store.message || '店铺暂不可用');
    } else if (isUnavailableSubdomain && marketDescription) {
        marketDescription.textContent = store.message || '当前域名未分配，请联系管理员';
    }

    if (opts.resetFilters !== false) resetMarketFilters();
    updateSellerStoreBanner();
    loadProducts({ forceAll: opts.resetFilters !== false });
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
        case 'subdomain':
            if (!isSubdomainFeatureEnabled()) {
                loadOverviewTab(contentArea);
                break;
            }
            loadSubdomainTab(contentArea);
            break;
        case 'cardmanage':
            loadCardManageTab(contentArea);
            break;
        case 'paymentmanage':
            loadPaymentManageTab(contentArea);
            break;
        case 'profile':
            loadProfileTab(contentArea);
            break;
        case 'customlabel':
            loadCustomLabelTab(contentArea);
            break;
        case 'messages':
            loadMessagesTab(contentArea);
            break;
        case 'reviews':
            loadReviewsTab(contentArea);
            break;
        case 'complaints':
            loadComplaintsTab(contentArea);
            break;
    }
}

function renderDashboard(tabName = null) {
    if (!App.currentUser) return;

    document.getElementById('dashUsername').textContent = App.currentUser.username;
    const dashAvatar = document.getElementById('dashAvatar');
    if (dashAvatar) {
        const avatarUrl = userAvatarUrl(App.currentUser);
        dashAvatar.classList.toggle('has-image', !!avatarUrl);
        dashAvatar.innerHTML = avatarUrl
            ? `<img src="${Security.escapeAttr(avatarUrl)}" alt="头像" onerror="this.remove(); this.parentElement.classList.remove('has-image'); this.parentElement.textContent='${Security.escapeHtml(App.currentUser.username.charAt(0).toUpperCase())}';">`
            : Security.escapeHtml(App.currentUser.username.charAt(0).toUpperCase());
    }
    document.getElementById('dashBalance').textContent = '¥ ' + App.currentUser.balance.toFixed(2) + (App.currentUser.frozen_balance > 0 ? '（冻结 ¥' + Number(App.currentUser.frozen_balance).toFixed(2) + '）' : '');

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
        ${isSubdomainFeatureEnabled() ? `
        <div class="sidebar-nav-item" data-tab="subdomain">
            <i class="bi bi-globe2"></i><span>二级域名</span>
        </div>` : ''}
    `;
    if (App.currentUser.role === 'admin') {
        sidebarHtml += `
            <div class="sidebar-nav-item" data-tab="cardmanage">
                <i class="bi bi-credit-card-2-front"></i><span>卡密管理</span>
            </div>
            <div class="sidebar-nav-item" data-tab="paymentmanage">
                <i class="bi bi-cash-stack"></i><span>支付接口</span>
            </div>
        `;
    }
    sidebarHtml += `
        <div class="sidebar-nav-item" data-tab="profile">
            <i class="bi bi-person-circle"></i><span>个人中心</span>
        </div>
        <div class="sidebar-nav-item" data-tab="customlabel">
            <i class="bi bi-tags"></i><span>自定义标签</span>
        </div>
        <div class="sidebar-nav-item" data-tab="messages">
            <i class="bi bi-chat-dots"></i><span>私信</span>
        </div>
        <div class="sidebar-nav-item" data-tab="reviews">
            <i class="bi bi-star-half"></i><span>评价管理</span>
        </div>
        <div class="sidebar-nav-item" data-tab="complaints">
            <i class="bi bi-exclamation-octagon"></i><span>投诉管理</span>
        </div>
    `;
    document.getElementById('dashSidebar').innerHTML = sidebarHtml;

    document.querySelectorAll('#dashSidebar .sidebar-nav-item[data-tab]').forEach(item => {
        item.onclick = function() {
            renderDashboardTab(this.dataset.tab);
        };
    });

    let activeTab = tabName || App.currentTab || 'overview';
    if (activeTab === 'subdomain' && !isSubdomainFeatureEnabled()) {
        activeTab = 'overview';
        App.currentTab = 'overview';
    }
    const hasActiveTab = !!document.querySelector(`#dashSidebar .sidebar-nav-item[data-tab="${activeTab}"]`);
    renderDashboardTab(hasActiveTab ? activeTab : 'overview');
}
window.__keynestFullRenderDashboard = renderDashboard;

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
                        <button type="button" class="btn btn-link p-0 text-start fw-semibold text-decoration-none" onclick="openOrderProductDetail('${Security.escapeAttr(o.product_id)}')">${Utils.truncate(o.product_title, 25)}</button>
                        <span class="text-danger fw-semibold">-¥${o.price.toFixed(2)}</span>
                        <span class="text-muted small">${Utils.formatDate(o.purchase_date)}</span>
                    </div>
                `).join('')}
            </div>`
        }
    `;
}

async function openOrderProductDetail(productId) {
    if (!productId) return Toast.warning('商品信息不存在');
    if (typeof openProductDetail !== 'function') {
        return Toast.error('商品详情组件未加载，请刷新页面后重试');
    }
    const activeModal = document.getElementById('purchaseConfirmModal');
    if (activeModal?.classList.contains('show')) {
        hideModalSafely(activeModal);
        setTimeout(cleanupBootstrapModalArtifacts, 120);
    }
    await openProductDetail(productId, { readonly: true, allowSoldOut: true });
}

function orderComplaintBadge(order) {
    const status = order?.complaint?.status || '';
    const map = {
        open: '<span class="badge badge-warning">投诉中</span>',
        processing: '<span class="badge badge-info">处理中</span>',
        withdrawn: '<span class="badge badge-secondary">已撤诉</span>',
        resolved: '<span class="badge badge-success">卖家胜</span>',
        rejected: '<span class="badge badge-danger">买家胜</span>'
    };
    return map[status] ? `<div>${map[status]}</div>` : '';
}

function orderComplaintActions(order) {
    const status = order?.complaint?.status || '';
    const orderId = Security.escapeAttr(order?.id || '');
    if (!status) {
        return `<button class="btn btn-sm btn-danger" onclick="openComplaintModal('${orderId}')">投诉</button>`;
    }
    if (status === 'open' || status === 'processing') {
        return `<button class="btn btn-sm btn-warning" onclick="openWithdrawComplaintModal('${orderId}')">撤诉</button>`;
    }
    const text = status === 'withdrawn' ? '已撤诉' : (status === 'resolved' ? '卖家胜' : (status === 'rejected' ? '买家胜' : '已结束'));
    return `<span class="badge badge-secondary">${text}</span>`;
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
                        <th>订单号</th>
                        <th>卖家昵称</th>
                        <th>价格</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.orders.map(o => `
                        <tr>
                            <td>
                                <button type="button" class="btn btn-link p-0 text-start fw-semibold text-decoration-none order-product-link" onclick="openOrderProductDetail('${Security.escapeAttr(o.product_id)}')" title="查看商品详情">
                                    ${Utils.truncate(o.product_title, 20)}
                                </button>
                                ${orderComplaintBadge(o)}
                            </td>
                            <td><code class="small">${Security.escapeHtml(o.payment_trade_no || o.id || '-')}</code></td>
                            <td>${Security.escapeHtml(o.seller_name || '-')}</td>
                            <td class="text-danger fw-semibold">¥${o.price.toFixed(2)}</td>
                            <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="viewDeliveryInfo('${o.id}')">查看发货</button>
                                ${o.has_comment ? '<span class="badge badge-success ms-1">已评价</span>' : `<button class="btn btn-sm btn-primary keynest-review-btn" data-product-id="${Security.escapeAttr(o.product_id)}" data-order-id="${Security.escapeAttr(o.id)}" onclick="openReviewDialog('${o.product_id}', '${o.id}')">评价</button>`}
                                ${orderComplaintActions(o)}
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
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    ${result.orders.map(o => `
                        <tr>
                            <td>
                                ${Utils.truncate(o.product_title, 20)}
                                ${orderComplaintBadge(o)}
                            </td>
                            <td>${o.guest_order ? '<span class="badge badge-secondary">游客买家</span><div class="small text-muted">已隐藏信息</div>' : Security.escapeHtml(o.buyer_name || '-')}</td>
                            <td class="text-success fw-semibold">+¥${o.seller_amount ? o.seller_amount.toFixed(2) : o.price.toFixed(2)}</td>
                            <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="openSellerOrderInfoModal('${Security.escapeAttr(o.id)}')">订单信息</button>
                                ${o.complaint && ['open', 'processing'].includes(o.complaint.status) ? `<button class="btn btn-sm btn-warning" onclick="openSellerComplaintModal('${Security.escapeAttr(o.id)}')">查看投诉</button><button class="btn btn-sm btn-danger" onclick="submitSellerComplaintRefund('${Security.escapeAttr(o.id)}')">同意退款</button>` : ''}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function openSellerOrderInfoModal(orderId) {
    const result = await API.getOrder(orderId);
    if (!result.success) {
        Toast.error(result.message || '订单不存在');
        return;
    }
    const order = result.order || {};
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-1"></i>售出订单信息</h6>
        <div class="bg-light rounded-3 p-3 small">
            <div><strong>订单号：</strong><code>${Security.escapeHtml(order.id || '-')}</code></div>
            <div><strong>商品：</strong>${Security.escapeHtml(order.product_title || '-')}</div>
            <div><strong>买家：</strong>${order.guest_order ? '<span class="badge badge-secondary">游客买家</span> <span class="text-muted">信息已隐藏</span>' : Security.escapeHtml(order.buyer_name || '-')}</div>
            <div><strong>数量：</strong>${Security.escapeHtml(order.quantity || 1)}</div>
            <div><strong>收入：</strong><span class="text-success fw-semibold">¥${Number(order.seller_amount || order.price || 0).toFixed(2)}</span></div>
            <div><strong>时间：</strong>${Utils.formatDate(order.purchase_date)}</div>
        </div>
        ${order.guest_order ? '<div class="alert alert-warning small mt-3 mb-0">这是游客订单，卖家端不会展示游客身份标识、联系方式或游客查询密钥。</div>' : ''}
    `;
    document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>';
    modal.show();
}

async function openComplaintModal(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle me-1"></i>投诉订单</h6>
        <div class="alert alert-warning small">提交投诉后，该订单对应的卖家收入会被冻结。系统会把 8 位撤诉密码发送到你的邮箱，撤诉时必须输入该密码。</div>
        <div class="mb-3">
            <label class="form-label">接收撤诉密码的邮箱</label>
            <input type="email" class="form-control" id="complaintEmail" placeholder="your@email.com">
        </div>
        <div class="mb-3">
            <label class="form-label">投诉原因</label>
            <textarea class="form-control" id="complaintReason" rows="4" maxlength="500" placeholder="请说明问题，例如账号无法使用、商品与描述不符等"></textarea>
            <small class="text-muted">最多 500 字</small>
        </div>
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">取消</button>
        <button class="btn btn-danger" onclick="submitComplaint('${Security.escapeAttr(orderId)}')">提交投诉</button>
    `;
    modal.show();
}

async function submitComplaint(orderId) {
    const email = document.getElementById('complaintEmail')?.value?.trim() || '';
    const reason = document.getElementById('complaintReason')?.value?.trim() || '';
    const result = await API.complainOrder(orderId, email, reason);
    if (result.success) {
        Toast.success(result.message || '投诉已提交');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
        renderDashboardTab('orders');
    } else {
        Toast.error(result.message || '投诉提交失败');
    }
}

function openWithdrawComplaintModal(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-arrow-counterclockwise me-1"></i>撤诉</h6>
        <div class="alert alert-info small">请输入投诉时邮件收到的 8 位数字撤诉密码。撤诉后冻结金额会解冻给卖家。</div>
        <div class="mb-3">
            <label class="form-label">撤诉密码</label>
            <input type="text" class="form-control" id="withdrawComplaintPassword" maxlength="8" placeholder="8位数字密码">
        </div>
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">取消</button>
        <button class="btn btn-warning" onclick="submitWithdrawComplaint('${Security.escapeAttr(orderId)}')">确认撤诉</button>
    `;
    modal.show();
}

async function submitWithdrawComplaint(orderId) {
    const password = document.getElementById('withdrawComplaintPassword')?.value?.trim() || '';
    const result = await API.withdrawComplaint(orderId, password);
    if (result.success) {
        Toast.success(result.message || '已撤诉');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
        renderDashboardTab('orders');
    } else {
        Toast.error(result.message || '撤诉失败');
    }
}

async function openSellerComplaintModal(orderId) {
    const result = await API.getOrder(orderId);
    if (!result.success) {
        Toast.error(result.message || '订单不存在');
        return;
    }
    const order = result.order;
    const complaint = order.complaint || {};
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    const messages = Array.isArray(complaint.messages) && complaint.messages.length
        ? complaint.messages
        : [
            complaint.reason ? { role: 'buyer', username: complaint.buyer_name || order.buyer_name || '买家', content: complaint.reason, created_at: complaint.created_at } : null,
            complaint.seller_reply ? { role: 'seller', username: order.seller_name || '卖家', content: complaint.seller_reply, created_at: complaint.seller_replied_at || complaint.updated_at } : null
        ].filter(Boolean);
    const sellerStatusInfo = (() => {
        const map = {
            open: ['warning', '待处理'],
            processing: ['primary', '处理中'],
            following: ['info', '跟进中'],
            resolved: ['success', '卖家胜'],
            rejected: ['danger', '买家胜'],
            withdrawn: ['secondary', '已撤诉']
        };
        return map[complaint.status || 'open'] || ['info', complaint.status || '已记录'];
    })();
    const sellerComplaintActive = !['resolved', 'rejected', 'withdrawn'].includes(complaint.status || 'open');
    const sellerAdminProgressHtml = (complaint.admin_reply || complaint.admin_status_by || complaint.admin_replied_by) ? `
        <div class="alert alert-info py-2 small mb-3">
            <div class="d-flex justify-content-between gap-2 mb-1">
                <strong><i class="bi bi-headset me-1"></i>平台处理状态：${Security.escapeHtml(sellerStatusInfo[1])}</strong>
                <span class="text-muted">${Utils.formatDate(complaint.admin_status_at || complaint.admin_replied_at || complaint.updated_at)}</span>
            </div>
            ${complaint.admin_reply ? `<div><strong>平台回复：</strong>${Security.escapeHtml(complaint.admin_reply)}</div>` : '<div class="text-muted">平台已更新处理状态，请留意后续处理结果。</div>'}
        </div>
    ` : '';
    const messagesHtml = messages.map(msg => `
        <div class="complaint-thread-item ${msg.role === 'seller' ? 'seller' : 'buyer'}">
            <div class="d-flex justify-content-between gap-2 mb-1">
                <strong>${msg.role === 'seller' ? '卖家' : '买家'}：${Security.escapeHtml(msg.username || '')}</strong>
                <small class="text-muted">${Utils.formatDate(msg.created_at)}</small>
            </div>
            <div>${Security.escapeHtml(msg.content || '')}</div>
        </div>
    `).join('');
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-circle me-1"></i>订单投诉</h6>
        <div class="bg-light rounded-3 p-3 mb-3 small">
            <div><strong>商品：</strong>${Security.escapeHtml(order.product_title || '-')}</div>
            <div><strong>买家：</strong>${Security.escapeHtml(order.buyer_name || '-')}</div>
            <div><strong>冻结金额：</strong>¥${Number(order.frozen_amount || 0).toFixed(2)}</div>
            <div><strong>投诉时间：</strong>${Utils.formatDate(complaint.created_at)}</div>
            <div><strong>当前状态：</strong><span class="badge badge-${sellerStatusInfo[0]}">${Security.escapeHtml(sellerStatusInfo[1])}</span></div>
            <div><strong>最近更新：</strong>${Utils.formatDate(complaint.updated_at || complaint.created_at)}</div>
        </div>
        ${sellerAdminProgressHtml}
        <div class="mb-3">
            <label class="form-label">投诉沟通记录</label>
            <div class="complaint-thread-list">${messagesHtml || '<div class="text-muted small">暂无沟通记录</div>'}</div>
        </div>
        ${sellerComplaintActive ? `
        <div class="mb-3">
            <label class="form-label">继续回复</label>
            <textarea class="form-control" id="sellerComplaintReply" rows="4" maxlength="500" placeholder="继续沟通处理进度、解决方案或说明"></textarea>
            <small class="text-muted">最多 500 字</small>
        </div>
        ` : '<div class="alert alert-secondary py-2 small mb-0">该投诉已结束，不能继续回复。</div>'}
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>
        ${sellerComplaintActive ? `<button class="btn btn-danger" onclick="submitSellerComplaintRefund('${Security.escapeAttr(orderId)}')">同意退款</button><button class="btn btn-primary" onclick="submitComplaintReply('${Security.escapeAttr(orderId)}', 'sales')">提交回复</button>` : ''}
    `;
    modal.show();
}

async function submitComplaintReply(orderId, refreshTab = 'complaints') {
    const reply = document.getElementById('sellerComplaintReply')?.value?.trim() || document.getElementById('complaintReplyContent')?.value?.trim() || '';
    const result = await API.replyComplaint(orderId, reply);
    if (result.success) {
        Toast.success(result.message || '回复已提交');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
        renderDashboardTab(refreshTab);
    } else {
        Toast.error(result.message || '回复失败');
    }
}

async function submitSellerComplaintRefund(orderId, note = '') {
    if (!confirm('确认同意退款吗？冻结金额将退还给买家余额，投诉将结束。')) return;
    const result = await API.sellerRefundComplaint(orderId, note);
    if (result.success) {
        Toast.success(result.message || '退款成功');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
        renderDashboardTab(App.currentTab || 'complaints');
    } else {
        Toast.error(result.message || '退款失败');
    }
}

async function submitSellerComplaintReply(orderId) {
    return submitComplaintReply(orderId, 'sales');
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
        <div class="row g-3 seller-product-grid">
            ${result.products.map(p => `
                <div class="col-md-6 col-xl-4">
                    <div class="card seller-product-card h-100" onclick="openSellerProductManage('${Security.escapeAttr(p.id)}')" role="button" title="点击编辑商品">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="d-flex align-items-center gap-2 mb-2 seller-product-actions">
                                        <span class="badge badge-primary">${Security.escapeHtml(p.category || '其他')}</span>
                                        <button class="btn btn-sm btn-outline-primary seller-stock-action" onclick="event.stopPropagation(); openAddStockModal('${Security.escapeAttr(p.id)}')" title="添加库存">
                                            <i class="bi bi-plus-circle me-1"></i>添加库存
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary seller-stock-action" onclick="event.stopPropagation(); openStockManageModal('${Security.escapeAttr(p.id)}')" title="库存管理">
                                            <i class="bi bi-archive me-1"></i>库存管理
                                        </button>
                                    </div>
                                    <h6 class="fw-bold seller-product-title mb-2">${Security.escapeHtml(p.title || '-')}</h6>
                                    <div class="seller-product-meta">
                                        <span><i class="bi bi-box"></i> 库存 ${Security.escapeHtml(p.stock)}</span>
                                        <span><i class="bi bi-graph-up"></i> 已售 ${Security.escapeHtml(p.sales)}</span>
                                        <span class="text-danger fw-semibold">¥${Number(p.price || 0).toFixed(2)}</span>
                                    </div>
                                </div>
                                <button class="btn btn-danger btn-sm seller-product-delete" onclick="event.stopPropagation(); deleteProduct('${Security.escapeAttr(p.id)}')" title="删除商品">
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

function paymentOrderTitle(order = {}) {
    const type = String(order.type || '').trim();
    const amount = Number(order.amount || 0);
    const absAmount = Math.abs(amount).toFixed(2);
    const titleMap = {
        recharge: '在线充值',
        membership_upgrade: '开通会员',
        product_online_purchase: '购买商品',
        product_purchase: '购买商品',
        product_purchase_refund: '购买失败退款',
        product_sale_income: '商品销售收入',
        card_recharge: '卡密充值',
        membership_card: '会员卡密兑换',
        publish_fee: '发布商品扣费',
        publish_fee_refund: '删除库存退费',
        admin_balance_adjust: amount >= 0 ? '后台加款' : '后台扣款'
    };
    const label = titleMap[type] || (amount < 0 ? '消费支出' : '余额收入');
    const sign = amount < 0 ? '-' : '';
    return `${label} ${sign}¥${absAmount}`;
}

function paymentOrderStatusText(order = {}) {
    const status = String(order.status || 'pending');
    const type = String(order.type || '');
    if ((order.delivery_status || '') === 'failed') {
        return order.refund_applied ? '库存不够已退款' : '库存不够';
    }
    if (type === 'product_purchase_refund') return '已退款';
    if (status === 'paid') {
        if (['product_purchase', 'product_online_purchase', 'publish_fee', 'membership_upgrade', 'membership_card'].includes(type) || Number(order.amount || 0) < 0) {
            return '已完成';
        }
        return '已到账';
    }
    if (status === 'pending') return '处理中';
    if (status === 'unpaid') return '未支付';
    return '失败';
}

function paymentOrderStatusClass(order = {}) {
    const status = String(order.status || 'pending');
    if ((order.delivery_status || '') === 'failed') return 'danger';
    if (String(order.type || '') === 'product_purchase_refund') return 'success';
    if (status === 'paid') return 'success';
    if (status === 'pending') return 'warning';
    return 'danger';
}

function getGuestOrderToken() {
    let token = localStorage.getItem('keynest_guest_order_token') || '';
    if (!/^[a-f0-9]{32,64}$/i.test(token)) {
        const bytes = new Uint8Array(24);
        if (window.crypto?.getRandomValues) {
            window.crypto.getRandomValues(bytes);
            token = Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
        } else {
            token = (Date.now().toString(16) + Math.random().toString(16).slice(2)).padEnd(32, '0');
        }
        localStorage.setItem('keynest_guest_order_token', token);
    }
    return token;
}

function guestOrders() {
    try {
        const list = JSON.parse(localStorage.getItem('keynest_guest_orders') || '[]');
        return Array.isArray(list) ? list.filter(o => o && o.id) : [];
    } catch (e) {
        return [];
    }
}

function saveGuestOrder(order) {
    if (!order || !order.id) return;
    const token = order.guest_token || getGuestOrderToken();
    const list = guestOrders().filter(item => item.id !== order.id);
    list.unshift({ ...order, guest_token: token, saved_at: Date.now() });
    localStorage.setItem('keynest_guest_orders', JSON.stringify(list.slice(0, 30)));
}

function findGuestOrder(orderId) {
    return guestOrders().find(item => String(item.id || '') === String(orderId || '') || String(item.trade_no || '') === String(orderId || '')) || null;
}

function guestOrderStatusText(order = {}) {
    if ((order.delivery_status || '') === 'failed') return order.delivery_error || '库存不够，购买失败';
    const status = String(order.status || 'pending');
    if (status === 'paid') return order.related_id ? '已支付，已发货' : '已支付，等待发货';
    if (status === 'pending') return '待支付';
    if (status === 'unpaid') return '未支付';
    return '订单失败';
}

async function refreshGuestPaymentOrder(orderId, token) {
    const result = await API.getPaymentOrderStatus(orderId, token || getGuestOrderToken());
    if (result.success && result.order) {
        const cached = findGuestOrder(orderId) || {};
        saveGuestOrder({ ...cached, ...result.order, guest_token: token || cached.guest_token || getGuestOrderToken() });
    }
    return result;
}

async function openGuestOrdersModal(orderId = '') {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('purchaseConfirmModal'));
    const orders = guestOrders();
    const inputValue = orderId || orders[0]?.id || '';
    document.getElementById('purchaseBody').innerHTML = `
        <h5 class="fw-bold mb-3"><i class="bi bi-search me-1"></i>查询订单</h5>
        <div class="alert alert-warning small" style="border-left:4px solid #f97316;"><strong>换设备查询：</strong>请输入购买时填写的真实邮箱和邮件里的 8 位查询码，即可直接从数据库查询卡密。</div>
        <div class="row g-2 mb-3">
            <div class="col-md-5"><input type="email" class="form-control" id="guestOrderEmailInput" placeholder="购买时填写的邮箱"></div>
            <div class="col-md-4"><input class="form-control text-uppercase" id="guestOrderCodeInput" maxlength="8" placeholder="8位查询码"></div>
            <div class="col-md-3"><button class="btn btn-primary w-100" onclick="queryGuestOrderByEmailCode()">查询卡密</button></div>
        </div>
        <div class="alert alert-info small">该入口也会读取当前电脑缓存的游客订单；如果只在当前电脑查询，也可以输入订单号刷新状态。</div>
        <div class="input-group mb-3">
            <input class="form-control" id="guestOrderQueryInput" value="${Security.escapeAttr(inputValue)}" placeholder="请输入订单号">
            <button class="btn btn-primary" onclick="queryGuestOrder()">查询</button>
        </div>
        <div id="guestOrderResultBox">
            ${orders.length ? renderGuestOrdersList(orders) : '<div class="text-muted text-center py-4">当前电脑暂无缓存订单</div>'}
        </div>
    `;
    document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>';
    modal.show();
}

function renderGuestOrdersList(orders) {
    return `<div class="guest-order-list">${orders.map(order => `
        <div class="guest-order-card border rounded-3 p-3 mb-2">
            <div class="d-flex justify-content-between gap-2 flex-wrap">
                <div>
                    <div class="fw-bold">${Security.escapeHtml(order.product_title || order.title || '游客订单')}</div>
                    <div class="small text-muted">订单号：<code>${Security.escapeHtml(order.id)}</code></div>
                    ${order.trade_no ? `<div class="small text-muted">交易号：${Security.escapeHtml(order.trade_no)}</div>` : ''}
                </div>
                <span class="badge badge-${paymentOrderStatusClass(order)} align-self-start">${Security.escapeHtml(guestOrderStatusText(order))}</span>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <button class="btn btn-sm btn-outline-primary" onclick="queryGuestOrder('${Security.escapeAttr(order.id)}')">刷新状态</button>
                ${order.related_id ? `<button class="btn btn-sm btn-primary" onclick="viewGuestDeliveryInfo('${Security.escapeAttr(order.related_id)}', '${Security.escapeAttr(order.guest_token || getGuestOrderToken())}')">查看发货</button>` : ''}
                <button class="btn btn-sm btn-outline" data-copy="${Security.escapeAttr(order.id)}">复制订单号</button>
            </div>
        </div>
    `).join('')}</div>`;
}

async function queryGuestOrder(orderId = '') {
    const id = orderId || document.getElementById('guestOrderQueryInput')?.value?.trim() || '';
    const box = document.getElementById('guestOrderResultBox');
    if (!id) return Toast.warning('请输入订单号');
    if (box) box.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    const cached = findGuestOrder(id) || {};
    const token = cached.guest_token || getGuestOrderToken();
    const result = await refreshGuestPaymentOrder(id, token);
    if (!result.success) {
        if (box) box.innerHTML = `<div class="alert alert-danger">${Security.escapeHtml(result.message || '订单不存在或无权查看')}</div>`;
        return;
    }
    const order = findGuestOrder(id) || result.order;
    if (box) box.innerHTML = renderGuestOrdersList([order]);
}

let guestDeliveryContext = { orderId: '', guestToken: '', guestEmail: '', guestQueryCode: '' };

async function queryGuestOrderByEmailCode(pickupPassword = '') {
    const email = document.getElementById('guestOrderEmailInput')?.value?.trim().toLowerCase() || '';
    const code = document.getElementById('guestOrderCodeInput')?.value?.trim().toUpperCase() || '';
    const box = document.getElementById('guestOrderResultBox');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Toast.warning('请输入购买时填写的真实邮箱');
    if (!/^[A-Z0-9]{8,12}$/.test(code)) return Toast.warning('请输入邮件中的8-12位查询码');
    if (box) box.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
    const result = await API.queryGuestOrderByCode(email, code, pickupPassword);
    if (!result.success) {
        if (box) box.innerHTML = `<div class="alert alert-danger">${Security.escapeHtml(result.message || '查询失败')}</div>`;
        return;
    }
    const order = result.order || {};
    saveGuestOrder({
        id: order.id || '',
        guest_email: email,
        guest_query_code: code,
        product_title: order.product_title || '游客订单',
        quantity: order.quantity || 1,
        amount: order.price || 0,
        created_at: order.purchase_date || Math.floor(Date.now() / 1000),
        related_id: order.id || '',
        status: 'paid',
        pickup_password_enabled: !!order.pickup_password_required || !!order.delivery_info?.pickup_password_enabled
    });
    if (order.pickup_password_required) {
        guestDeliveryContext = { orderId: order.id || '', guestToken: '', guestEmail: email, guestQueryCode: code };
        await renderGuestDeliveryModal('', order);
        return;
    }
    await viewGuestDeliveryInfoByCode(order.id || '', email, code, pickupPassword, order);
}

async function viewGuestDeliveryInfoByCode(orderId, guestEmail, guestQueryCode, pickupPassword = '', prefetchedOrder = null) {
    guestDeliveryContext = {
        orderId: orderId || '',
        guestToken: '',
        guestEmail: guestEmail || '',
        guestQueryCode: guestQueryCode || ''
    };
    await renderGuestDeliveryModal(pickupPassword, prefetchedOrder);
}

async function submitGuestPickupPassword() {
    const password = document.getElementById('guestPickupPasswordInput')?.value?.trim() || '';
    if (!password) {
        Toast.warning('请输入取卡密码');
        document.getElementById('guestPickupPasswordInput')?.focus();
        return;
    }
    await renderGuestDeliveryModal(password);
}

async function viewGuestDeliveryInfo(orderId, guestToken = '', pickupPassword = '') {
    guestDeliveryContext = {
        orderId: orderId || '',
        guestToken: guestToken || getGuestOrderToken(),
        guestEmail: '',
        guestQueryCode: ''
    };
    await renderGuestDeliveryModal(pickupPassword);
}

async function renderGuestDeliveryModal(pickupPassword = '', prefetchedOrder = null) {
    const { orderId, guestToken, guestEmail, guestQueryCode } = guestDeliveryContext;
    if (!orderId) return;

    const result = prefetchedOrder ? { success: true, order: prefetchedOrder } : (guestEmail && guestQueryCode ? await API.getOrder(orderId, pickupPassword, '', guestEmail, guestQueryCode) : await API.getOrder(orderId, pickupPassword, guestToken));
    if (!result.success) {
        Toast.error(result.message || '订单不存在');
        return;
    }

    const order = result.order;
    const needsPassword = !!order.pickup_password_required;
    if (needsPassword && pickupPassword) {
        Toast.warning('取卡密码错误，请重试');
    } else if (needsPassword && !pickupPassword) {
        // 首次进入，仅展示取卡密码表单
    }

    const d = order.delivery_info;
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-box-seam me-1"></i>游客订单发货信息</h6>
        <div class="alert alert-warning small">您尚未登录，请保存好订单号：<code>${Security.escapeHtml(order.id)}</code>${guestEmail && guestQueryCode ? `；当前使用邮箱 <strong>${Security.escapeHtml(guestEmail)}</strong> + 查询码查询` : ''}</div>
        ${needsPassword ? `
            <div class="alert alert-info small">该订单设置了取卡密码，请输入购买时填写的取卡密码。</div>
            <div class="input-group mb-3">
                <input type="password" class="form-control" id="guestPickupPasswordInput" placeholder="请输入取卡密码" autocomplete="off">
                <button type="button" class="btn btn-primary" onclick="submitGuestPickupPassword()">确认取卡</button>
            </div>
        ` : ''}
        ${needsPassword ? '' : `<div class="delivery-card"><div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-1"></i>卡密信息</h6>${deliveryInfoExportText(d) ? `<button class="btn btn-sm btn-outline-primary" onclick="exportDeliveryInfoTxt('${Security.escapeAttr(order.id)}', ${JSON.stringify(d).replace(/"/g, '&quot;')})"><i class="bi bi-download me-1"></i>导出TXT</button>` : ''}</div><div class="small">${deliveryInfoHtml(d)}</div></div>`}
    `;
    document.getElementById('purchaseFooter').innerHTML = '<button type="button" class="btn btn-outline" onclick="openGuestOrdersModal()">返回订单</button><button type="button" class="btn btn-primary" data-bs-dismiss="modal">关闭</button>';

    const modalEl = document.getElementById('purchaseConfirmModal');
    if (!modalEl.classList.contains('show')) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
        setTimeout(cleanupBootstrapModalArtifacts, 80);
    }

    if (needsPassword) {
        const input = document.getElementById('guestPickupPasswordInput');
        input?.focus();
        if (input && !input.dataset.boundEnter) {
            input.dataset.boundEnter = '1';
            input.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitGuestPickupPassword();
                }
            });
        }
    }
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
        paymentOrdersHtml = '<p class="text-muted mt-3">暂无余额流水记录</p>';
    } else {
        const sortedPaymentOrders = [...paymentResult.orders].sort((a, b) => Number(b.created_at || 0) - Number(a.created_at || 0));
        paymentOrdersHtml = `
            <div class="mt-3">
                ${sortedPaymentOrders.map(o => `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>${Security.escapeHtml(paymentOrderTitle(o))}</span>
                        <span class="badge badge-${paymentOrderStatusClass(o)}">
                            ${Security.escapeHtml(paymentOrderStatusText(o))}
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
                <div class="d-flex justify-content-center gap-4 flex-wrap mb-2">
                    <div>
                        <div class="text-muted small">可用余额</div>
                        <h2 class="fw-bold text-primary mb-0">¥ ${App.currentUser.balance.toFixed(2)}</h2>
                    </div>
                    <div>
                        <div class="text-muted small">冻结余额</div>
                        <h2 class="fw-bold text-warning mb-0">¥ ${Number(App.currentUser.frozen_balance || 0).toFixed(2)}</h2>
                    </div>
                </div>
                <p class="text-muted mb-3">冻结余额来自处理中投诉或待处理资金，不可提现</p>
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
                <h6 class="fw-bold">余额流水记录</h6>
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
    const [levelsResult, myLevelResult, configResult] = await Promise.all([
        API.getMembershipLevels(),
        API.getMyMembership(),
        API.getSystemConfig()
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
    const systemConfig = configResult.success ? (configResult.config || {}) : {};
    const showMembershipCardActivation = systemConfig.enable_membership_card_activation !== false && systemConfig.enable_membership_card_activation !== '0';

    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-gem me-2"></i>会员中心</h5>
        <div class="membership-cards">
            ${showMembershipCardActivation ? renderMembershipCardActivationCard() : ''}
            ${levelList.map(level => {
                const levelName = level.name || '';
                const levelPriority = Number(level.priority || 0);
                const isCurrentLevel = myLevelName === levelName;
                const isLowerLevel = !isCurrentLevel && myLevelName !== 'Free' && levelPriority < currentPriority;
                const cost = Number(level.cost || 0);
                const canAfford = !App.currentUser || Number(App.currentUser.balance || 0) >= cost;
                const levelGradient = level.gradient || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6)';
                const levelIcon = level.icon || 'bi-gem';
                const maxAccountsText = Number(level.max_accounts_per_product || 0) === 0 ? '不限制' : `${level.max_accounts_per_product} 账号`;
                const maxProductsText = Number(level.max_products || 0) === 0 ? '不限制' : `${level.max_products} 个商品`;
                const levelPrivileges = [
                    `单商品最大 ${maxAccountsText}`,
                    `最多商品 ${maxProductsText}`,
                    `手续费 ${(Number(level.fee_rate || 0) * 100).toFixed(2).replace(/\.00$/, '')}%`,
                    Number(level.publish_fee_per_account || 0) === 0 ? '售出不扣发布费' : `售出扣费 ¥${level.publish_fee_per_account}/账号`
                ];
                const footerHtml = isCurrentLevel
                    ? '<span class="membership-status-text">当前会员</span>'
                    : isLowerLevel
                        ? '<span class="membership-status-text text-muted">当前会员比此会员等级高，禁止升级</span>'
                        : level.can_upgrade === false
                            ? '<span class="membership-status-text text-muted">暂不支持开通</span>'
                            : `<button class="btn btn-primary w-100" onclick="upgradeMembership('${Security.escapeAttr(levelName)}')">${cost === 0 ? '免费开通' : '立即开通'}</button>`;

                return `
                    <div class="membership-card ${isCurrentLevel ? 'current' : ''}" style="--card-gradient: ${Security.escapeAttr(levelGradient)};">
                        <div class="card-header">
                            <i class="bi ${Security.escapeAttr(levelIcon)}"></i>
                            <h5>${Security.escapeHtml(levelName)}</h5>
                            <small>${Security.escapeHtml(level.description || levelName + '会员')}</small>
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

function renderMembershipCardActivationCard() {
    return `
        <div class="membership-card membership-activation-card" style="--card-gradient: linear-gradient(135deg, #111827 0%, #4f46e5 55%, #06b6d4 100%);">
            <div class="card-header">
                <i class="bi bi-credit-card-2-front"></i>
                <h5>卡密激活会员</h5>
                <small>使用会员卡密快速开通权益</small>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <span class="badge bg-primary-light text-primary fs-5"><i class="bi bi-key"></i> 输入卡密</span>
                </div>
                <ul class="privilege-list">
                    <li><i class="bi bi-check"></i> 支持后台生成的会员卡密</li>
                    <li><i class="bi bi-check"></i> 兑换成功后自动刷新会员等级</li>
                    <li><i class="bi bi-check"></i> 独立激活入口，不占用会员等级配置</li>
                    <li><i class="bi bi-check"></i> Free 为默认会员，不支持生成激活卡</li>
                </ul>
            </div>
            <div class="card-footer">
                <button class="btn btn-primary w-100" onclick="openCardRechargeModal('membership')">
                    <i class="bi bi-lightning-charge me-1"></i>立即激活
                </button>
            </div>
        </div>
    `;
}

async function loadCardManageTab(area) {
    const [cardResult, levelResult] = await Promise.all([API.getCards(false), API.getMembershipLevels()]);
    if (!cardResult.success) {
        area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
        return;
    }

    const cards = cardResult.cards || [];
    const levels = levelResult.success ? Object.values(levelResult.levels || {}) : [];
    const membershipLevels = levels.filter(level => level && level.name && String(level.name).toLowerCase() !== 'free');
    const cardValueText = c => (c.card_type === 'membership')
        ? `会员：${Security.escapeHtml(c.target_level || '-')}`
        : `¥${Number(c.amount || 0).toFixed(2)}`;
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-credit-card-2-front me-2"></i>卡密管理</h5>
        <div class="card bg-light mb-4">
            <div class="card-body">
                <h6 class="fw-bold mb-3">生成新卡密</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">卡密类型</label>
                        <select id="cardType" class="form-select" onchange="toggleCardCreateType()">
                            <option value="balance">余额卡密</option>
                            <option value="membership">会员卡密</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="cardAmountWrap">
                        <label class="form-label">余额金额</label>
                        <input type="number" id="cardAmount" class="form-control" placeholder="金额" min="1" step="0.01">
                    </div>
                    <div class="col-md-3 d-none" id="cardMembershipWrap">
                        <label class="form-label">会员权益</label>
                        <select id="cardTargetLevel" class="form-select" ${membershipLevels.length === 0 ? 'disabled' : ''}>
                            ${membershipLevels.map(level => `<option value="${Security.escapeAttr(level.name)}">${Security.escapeHtml(level.name)} - ${Security.escapeHtml(level.description || '会员权益')}</option>`).join('') || '<option value="">暂无可生成会员</option>'}
                        </select>
                        <small class="text-muted">Free 是默认会员，不可生成卡密。</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">数量</label>
                        <input type="number" id="cardCount" class="form-control" placeholder="1-100" min="1" max="100" value="1">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
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
                            <th>类型</th>
                            <th>权益</th>
                            <th>状态</th>
                            <th>生成时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${cards.map(c => `
                            <tr>
                                <td><code>${Security.escapeHtml(c.code)}</code></td>
                                <td>${c.card_type === 'membership' ? '<span class="badge badge-primary">会员卡</span>' : '<span class="badge badge-info">余额卡</span>'}</td>
                                <td>${cardValueText(c)}</td>
                                <td>
                                    <span class="badge badge-${c.used ? 'secondary' : 'success'}">
                                        ${c.used ? '已使用' : '未使用'}
                                    </span>
                                </td>
                                <td class="text-muted small">${Utils.formatDate(c.created_at)}</td>
                                <td>
                                    ${!c.used ? `
                                        <button class="btn btn-sm btn-outline" data-copy="${Security.escapeAttr(c.code)}">复制</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteCard('${Security.escapeAttr(c.id)}')">删除</button>
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
                                        ${o.status === 'paid' ? '已支付' : o.status === 'pending' ? '待支付' : o.status === 'unpaid' ? '未支付' : '失败'}
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

async function openSellerProductManage(productId) {
    const productResult = await API.getProduct(productId);
    const reviewsResult = await API.getProductReviews(productId);
    if (!productResult.success) {
        Toast.error(productResult.message || '商品不存在');
        return;
    }
    const product = productResult.product;
    const comments = reviewsResult.success ? reviewsResult.comments : [];
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <h5 class="fw-bold mb-3"><i class="bi bi-pencil-square me-1"></i>编辑商品</h5>
        <div class="row g-2 mb-3">
            <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><strong>${Security.escapeHtml(product.stock)}</strong><br><small class="text-muted">库存</small></div></div>
            <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><strong>${Security.escapeHtml(product.sales)}</strong><br><small class="text-muted">已售</small></div></div>
            <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><strong>${comments.length}</strong><br><small class="text-muted">评价</small></div></div>
        </div>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label">商品标题</label>
                <input class="form-control" id="editProductTitle" maxlength="100" value="${Security.escapeAttr(product.title || '')}">
            </div>
            <div class="col-md-4">
                <label class="form-label">价格 (¥)</label>
                <input type="number" class="form-control" id="editProductPrice" min="0.01" step="0.01" value="${Security.escapeAttr(product.price || 0)}">
            </div>
            <div class="col-md-6">
                <label class="form-label">分类</label>
                <select class="form-select" id="editProductCategory">
                    ${['游戏账号', '流媒体', '软件许可', '其他'].map(cat => `<option value="${Security.escapeAttr(cat)}" ${cat === product.category ? 'selected' : ''}>${Security.escapeHtml(cat)}</option>`).join('')}
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">商品图片链接</label>
                <input class="form-control" id="editProductImage" value="${Security.escapeAttr(product.image || '')}" placeholder="图片链接或上传后的地址" oninput="updateEditProductImagePreview(this.value)">
            </div>
            <div class="col-12">
                <div class="product-image-uploader" id="editProductImageDropZone" onclick="document.getElementById('editProductImageFile').click()">
                    <input type="file" id="editProductImageFile" accept="image/png,image/jpeg,image/gif,image/webp" class="hidden" onchange="handleEditProductImageFile(this.files[0])">
                    <div id="editProductImagePreview" class="product-image-preview-placeholder"></div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">商品描述（支持 Markdown）</label>
                <textarea class="form-control" id="editProductDesc" rows="4">${Security.escapeHtml(product.description || '')}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="editPickupPasswordEnabled" ${product.pickup_password_enabled ? 'checked' : ''}>
                    <label class="form-check-label" for="editPickupPasswordEnabled">开启买家取卡密码</label>
                </div>
                <small class="text-muted">开启后，买家购买时自行设置取卡密码；卖家不需要填写密码。</small>
            </div>
        </div>
        <hr>
        <h6 class="fw-bold">评价</h6>
        ${comments.length === 0 ? '<p class="text-muted small">暂无评价</p>' : comments.slice(0, 5).map(c => `
            <div class="border-bottom py-2 small">
                <strong>${Security.escapeHtml(c.buyer_name || c.username || '-')}</strong>
                <span class="text-warning ms-2">${'★'.repeat(Number(c.rating || 0))}</span>
                <span class="text-muted ms-2">${Utils.formatDate(c.created_at)}</span>
                <div>${Security.escapeHtml(c.content || '未填写评价内容')}</div>
            </div>
        `).join('')}
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>
        <button class="btn btn-danger" onclick="deleteProduct('${Security.escapeAttr(product.id)}')" data-bs-dismiss="modal">下架删除</button>
        <button class="btn btn-primary" onclick="saveSellerProduct('${Security.escapeAttr(product.id)}')">保存修改</button>
    `;
    modal.show();
    updateEditProductImagePreview(product.image || '');
    initEditProductImageDropZone();
}

let currentStockManageState = { productId: '', page: 1, pageSize: 10, filter: 'all' };

function stockItemDisplayContent(item = {}) {
    const content = String(item.content || '').trim();
    if (content && content !== 'N/A') return content;
    const values = [item.email, item.password, item.client_id, item.fresh_token]
        .map(v => String(v || '').trim())
        .filter(v => v && v !== 'N/A');
    return values.length ? values.join(' | ') : '库存内容为空';
}

function stockPageSizeOptions(selected = 10) {
    return [10, 20, 50, 100, 200, 500, 1000].map(size => `<option value="${size}" ${Number(selected) === size ? 'selected' : ''}>每页 ${size} 个</option>`).join('');
}

async function renderStockManageContent(productId, page = currentStockManageState.page || 1, pageSize = currentStockManageState.pageSize || 10, filter = currentStockManageState.filter || 'all') {
    pageSize = Math.max(10, Math.min(1000, Number(pageSize) || 10));
    filter = ['all', 'unsold', 'sold'].includes(filter) ? filter : 'all';
    currentStockManageState = { productId, page: Math.max(1, Number(page) || 1), pageSize, filter };
    const result = await API.getProductStock(productId);
    if (!result.success) {
        Toast.error(result.message || '库存读取失败');
        return false;
    }
    const product = result.product || {};
    const stock = Array.isArray(result.stock) ? result.stock : [];
    const unsoldCount = Number(result.unsold_count || stock.filter(item => !item.sold).length || 0);
    const soldCount = Number(result.sold_count || stock.filter(item => item.sold).length || 0);
    const filteredStock = filter === 'unsold' ? stock.filter(item => !item.sold) : (filter === 'sold' ? stock.filter(item => item.sold) : stock);
    const totalPages = Math.max(1, Math.ceil(filteredStock.length / pageSize));
    const safePage = Math.min(currentStockManageState.page, totalPages);
    currentStockManageState.page = safePage;
    const pageStock = filteredStock.slice((safePage - 1) * pageSize, safePage * pageSize);
    const rowsHtml = pageStock.length ? pageStock.map(item => {
        const content = stockItemDisplayContent(item);
        return `
            <div class="seller-stock-row ${item.sold ? 'sold' : ''}">
                <div class="seller-stock-main">
                    <input class="form-check-input seller-stock-select" type="radio" name="sellerStockSelected" value="${Number(item.index)}" aria-label="选择库存 #${Number(item.index) + 1}">
                    <div class="seller-stock-index">#${Number(item.index) + 1}</div>
                    <div class="seller-stock-content" title="${Security.escapeAttr(content)}">${Security.escapeHtml(content)}</div>
                    <span class="badge ${item.sold ? 'badge-secondary' : 'badge-success'}">${item.sold ? '已售' : '未售'}</span>
                    ${item.sold && item.buyer_name ? `<span class="seller-stock-buyer text-muted small">买家：${Security.escapeHtml(item.buyer_name)}</span>` : ''}
                </div>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteSellerStockItem('${Security.escapeAttr(productId)}', ${Number(item.index)})">
                    <i class="bi bi-trash me-1"></i>删除
                </button>
            </div>
        `;
    }).join('') : `<div class="text-muted text-center py-4">${filter === 'unsold' ? '暂无未售库存' : (filter === 'sold' ? '暂无已售库存' : '暂无库存')}</div>`;
    document.getElementById('purchaseBody').innerHTML = `
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-archive me-1"></i>库存管理</h5>
                <div class="text-muted small">${Security.escapeHtml(product.title || '-')}</div>
            </div>
            <div class="d-flex gap-2 flex-wrap stock-manage-actions">
                <button class="btn btn-sm btn-outline-danger" ${stock.length <= 0 ? 'disabled' : ''} onclick="deleteSellerStockBatch('${Security.escapeAttr(productId)}', 'all')"><i class="bi bi-trash3 me-1"></i>删除全部库存</button>
                <button class="btn btn-sm btn-outline-danger" ${stock.length <= 0 ? 'disabled' : ''} onclick="deleteSellerStockSelected('${Security.escapeAttr(productId)}')"><i class="bi bi-check2-square me-1"></i>删除选中库存</button>
                <button class="btn btn-sm btn-outline-danger" ${unsoldCount <= 0 ? 'disabled' : ''} onclick="deleteSellerStockBatch('${Security.escapeAttr(productId)}', 'unsold')"><i class="bi bi-box-seam me-1"></i>删除未出售</button>
                <button class="btn btn-sm btn-outline-danger" ${soldCount <= 0 ? 'disabled' : ''} onclick="deleteSellerStockBatch('${Security.escapeAttr(productId)}', 'sold')"><i class="bi bi-bag-check me-1"></i>删除已出售</button>
                <button class="btn btn-sm btn-outline-primary" onclick="openAddStockModal('${Security.escapeAttr(productId)}')"><i class="bi bi-plus-circle me-1"></i>添加库存</button>
            </div>
        </div>
        <div class="seller-stock-summary mb-3">
            <button type="button" class="seller-stock-stat ${filter === 'all' ? 'active' : ''}" onclick="switchStockManageFilter('${Security.escapeAttr(productId)}', 'all')"><strong>${Security.escapeHtml(stock.length)}</strong><span>总库存</span></button>
            <button type="button" class="seller-stock-stat ${filter === 'unsold' ? 'active' : ''}" onclick="switchStockManageFilter('${Security.escapeAttr(productId)}', 'unsold')"><strong>${Security.escapeHtml(unsoldCount)}</strong><span>未售</span></button>
            <button type="button" class="seller-stock-stat ${filter === 'sold' ? 'active' : ''}" onclick="switchStockManageFilter('${Security.escapeAttr(productId)}', 'sold')"><strong>${Security.escapeHtml(soldCount)}</strong><span>已售</span></button>
        </div>
        <div class="seller-stock-toolbar mb-3">
            <select class="form-select form-select-sm" onchange="refreshStockManageModal('${Security.escapeAttr(productId)}', 1, this.value, '${Security.escapeAttr(filter)}')">${stockPageSizeOptions(pageSize)}</select>
            <div class="seller-stock-page-actions">
                <button class="btn btn-sm btn-outline" ${safePage <= 1 ? 'disabled' : ''} onclick="refreshStockManageModal('${Security.escapeAttr(productId)}', ${safePage - 1}, ${pageSize}, '${Security.escapeAttr(filter)}')">上一页</button>
                <span class="small text-muted">第 ${safePage} / ${totalPages} 页，共 ${filteredStock.length} 条</span>
                <button class="btn btn-sm btn-outline" ${safePage >= totalPages ? 'disabled' : ''} onclick="refreshStockManageModal('${Security.escapeAttr(productId)}', ${safePage + 1}, ${pageSize}, '${Security.escapeAttr(filter)}')">下一页</button>
            </div>
        </div>
        <div class="seller-stock-list">${rowsHtml}</div>
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>
    `;
    return true;
}

async function refreshStockManageModal(productId = currentStockManageState.productId, page = currentStockManageState.page, pageSize = currentStockManageState.pageSize, filter = currentStockManageState.filter) {
    await renderStockManageContent(productId, page, pageSize, filter);
}

async function switchStockManageFilter(productId, filter) {
    await refreshStockManageModal(productId, 1, currentStockManageState.pageSize, filter);
}

async function openStockManageModal(productId, page = currentStockManageState.page || 1, pageSize = currentStockManageState.pageSize || 10, filter = currentStockManageState.filter || 'all') {
    const ok = await renderStockManageContent(productId, page, pageSize, filter);
    if (!ok) return;
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('purchaseConfirmModal'));
    modal.show();
}

async function deleteSellerStockItem(productId, stockIndex) {
    if (!confirm('确定删除这条库存吗？删除后不可恢复。')) return;
    const result = await API.deleteProductStock(productId, stockIndex);
    if (!result.success) return Toast.error(result.message || '删除库存失败');
    Toast.success(result.message || '库存已删除');
    await refreshUserData();
    await refreshStockManageModal(productId, currentStockManageState.page, currentStockManageState.pageSize, currentStockManageState.filter);
    renderDashboardTab('myproducts');
    if (typeof loadProducts === 'function') loadProducts();
}

async function deleteSellerStockSelected(productId) {
    const checked = Array.from(document.querySelectorAll('input[name="sellerStockSelected"]:checked'));
    const indexes = checked.map(input => Number(input.value)).filter(Number.isInteger);
    if (!indexes.length) return Toast.warning('请先选择要删除的库存');
    if (!confirm('确定删除选中的库存吗？删除后不可恢复。')) return;
    const result = await API.deleteProductStockBatch(productId, 'selected', indexes);
    if (!result.success) return Toast.error(result.message || '删除选中库存失败');
    Toast.success(result.message || '库存已删除');
    await refreshUserData();
    await refreshStockManageModal(productId, currentStockManageState.page, currentStockManageState.pageSize, currentStockManageState.filter);
    renderDashboardTab('myproducts');
    if (typeof loadProducts === 'function') loadProducts();
}

async function deleteSellerStockBatch(productId, mode) {
    const labels = { all: '全部库存', unsold: '未出售库存', sold: '已出售库存' };
    const label = labels[mode] || '库存';
    if (!confirm(`确定删除${label}吗？删除后不可恢复。`)) return;
    const result = await API.deleteProductStockBatch(productId, mode);
    if (!result.success) return Toast.error(result.message || `删除${label}失败`);
    Toast.success(result.message || '库存已删除');
    await refreshUserData();
    await refreshStockManageModal(productId, 1, currentStockManageState.pageSize, currentStockManageState.filter);
    renderDashboardTab('myproducts');
    if (typeof loadProducts === 'function') loadProducts();
}

async function clearSellerUnsoldStock(productId) {
    if (!confirm('确定清空该商品所有未售库存吗？已售库存不会删除，此操作不可恢复。')) return;
    const result = await API.clearProductStock(productId);
    if (!result.success) return Toast.error(result.message || '清空库存失败');
    Toast.success(result.message || '未售库存已清空');
    await refreshUserData();
    await refreshStockManageModal(productId, 1, currentStockManageState.pageSize, 'unsold');
    renderDashboardTab('myproducts');
    if (typeof loadProducts === 'function') loadProducts();
}

async function openAddStockModal(productId) {
    const productResult = await API.getProduct(productId);
    if (!productResult.success) {
        Toast.error(productResult.message || '商品不存在');
        return;
    }
    const product = productResult.product || {};
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle me-1"></i>添加库存</h5>
        <div class="alert alert-light border small mb-3">
            商品：<strong>${Security.escapeHtml(product.title || '-')}</strong><br>
            当前库存：<strong>${Security.escapeHtml(product.stock || 0)}</strong>
        </div>
        <div class="mb-3">
            <label class="form-label">新增库存账号</label>
            <textarea class="form-control" id="addStockAccountList" rows="8" placeholder="每行一个账号，格式与发布商品时一致"></textarea>
            <small class="text-muted">只会追加新增库存，不会覆盖原库存。</small>
        </div>
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">取消</button>
        <button class="btn btn-primary" onclick="submitAddStock('${Security.escapeAttr(productId)}')">确认添加</button>
    `;
    modal.show();
}

async function submitAddStock(productId) {
    const accountList = document.getElementById('addStockAccountList')?.value?.trim() || '';
    if (!accountList) return Toast.warning('请填写要添加的库存账号');
    const result = await API.addProductStock(productId, accountList);
    if (!result.success) return Toast.error(result.message || '添加库存失败');
    Toast.success(result.message || '库存已添加');
    bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
    renderDashboardTab('myproducts');
    if (typeof loadProducts === 'function') loadProducts();
}

async function saveSellerProduct(productId) {
    const title = document.getElementById('editProductTitle')?.value?.trim() || '';
    const category = document.getElementById('editProductCategory')?.value || '其他';
    const price = parseFloat(document.getElementById('editProductPrice')?.value || '0');
    const description = document.getElementById('editProductDesc')?.value?.trim() || '';
    const image = document.getElementById('editProductImage')?.value?.trim() || '';
    const pickupPasswordEnabled = document.getElementById('editPickupPasswordEnabled')?.checked ? '1' : '0';
    if (!title || !price || price <= 0) {
        Toast.warning('请填写标题和有效价格');
        return;
    }
    const result = await API.updateProduct(productId, {
        title,
        category,
        price,
        description,
        image,
        pickup_password_enabled: pickupPasswordEnabled
    });
    if (!result.success) {
        Toast.error(result.message || '保存失败');
        return;
    }
    Toast.success(result.message || '商品已更新');
    bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
    renderDashboardTab('myproducts');
    if (typeof loadProducts === 'function') loadProducts();
}

function toggleEditPickupPasswordInput() {
    return;
}

function updateEditProductImagePreview(value) {
    const preview = document.getElementById('editProductImagePreview');
    if (!preview) return;
    const url = String(value || '').trim();
    if (/^(https?:\/\/|\/uploads\/products\/).+\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(url)) {
        preview.innerHTML = `<img src="${Security.escapeAttr(url)}" alt="商品图片预览">`;
    } else {
        preview.innerHTML = '<i class="bi bi-cloud-arrow-up"></i><span>点击选择或拖拽上传新图片</span><small>不上传则保留当前随机图标或图片</small>';
    }
}

async function handleEditProductImageFile(file) {
    if (!file) return;
    if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
        Toast.warning('仅支持 JPG、PNG、GIF、WEBP 图片');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        Toast.warning('图片大小不能超过2MB');
        return;
    }
    const preview = document.getElementById('editProductImagePreview');
    if (preview) preview.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div><span>上传中...</span>';
    const result = await API.uploadProductImage(file);
    if (!result.success) {
        Toast.error(result.message || '图片上传失败');
        updateEditProductImagePreview(document.getElementById('editProductImage')?.value || '');
        return;
    }
    const input = document.getElementById('editProductImage');
    if (input) input.value = result.url;
    updateEditProductImagePreview(result.url);
    Toast.success('图片上传成功');
}

function initEditProductImageDropZone() {
    const zone = document.getElementById('editProductImageDropZone');
    if (!zone || zone.dataset.bound === '1') return;
    zone.dataset.bound = '1';
    ['dragenter', 'dragover'].forEach(eventName => {
        zone.addEventListener(eventName, event => {
            event.preventDefault();
            zone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(eventName => {
        zone.addEventListener(eventName, event => {
            event.preventDefault();
            zone.classList.remove('dragover');
        });
    });
    zone.addEventListener('drop', event => {
        const file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
        handleEditProductImageFile(file);
    });
}

async function loadReviewsTab(area) {
    const result = await API.getProductReviews();
    const comments = result.success ? result.comments : [];
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-star-half me-2"></i>评价管理</h5>
        ${comments.length === 0 ? `
            <div class="empty-state">
                <i class="bi bi-chat-square-heart"></i>
                <h5>暂无评价</h5>
                <p>买家评价后会显示在这里</p>
            </div>
        ` : `
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>商品</th>
                            <th>买家</th>
                            <th>评分</th>
                            <th>内容</th>
                            <th>时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${comments.map(c => `
                            <tr>
                                <td>${Security.escapeHtml(Utils.truncate(c.product_title || '-', 24))}</td>
                                <td>${Security.escapeHtml(c.buyer_name || c.username || '-')}</td>
                                <td><span class="text-warning">${'★'.repeat(Number(c.rating || 0))}</span><span class="text-muted">${'☆'.repeat(5 - Number(c.rating || 0))}</span></td>
                                <td>${Security.escapeHtml(c.content || '未填写评价内容')}</td>
                                <td class="text-muted small">${Utils.formatDate(c.created_at)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `}
    `;
}

function getUserPaymentMethods(user = App.currentUser || {}) {
    const methods = user.payment_methods && typeof user.payment_methods === 'object' ? user.payment_methods : {};
    return {
        alipay: { label: '支付宝', account: methods.alipay?.account || '', qrcode: methods.alipay?.qrcode || '' },
        wechat: { label: '微信', account: methods.wechat?.account || '', qrcode: methods.wechat?.qrcode || '' }
    };
}

function paymentMethodIcon(key) {
    return key === 'wechat' ? 'bi-wechat' : 'bi-alipay';
}

function hasConfiguredPaymentMethods(user = App.currentUser || {}) {
    const methods = getUserPaymentMethods(user);
    return Object.values(methods).some(item => !!(item.account && item.qrcode));
}

function merchantStatusInfo(user = App.currentUser || {}) {
    const status = user.merchant_status || 'none';
    if (!user.qq_bound) {
        return { ok: false, label: '未完成', badge: 'warning', desc: '请先绑定 QQ，绑定后才可申请开通商家' };
    }
    if (user.merchant_verified === true || user.merchant_verified === '1') {
        return { ok: true, label: '已完成', badge: 'success', desc: '商家已开通，可正常发布商品和收款' };
    }
    if (status === 'pending') {
        return { ok: false, label: '待审核', badge: 'warning', desc: '重新开通申请已提交，请等待管理员审核' };
    }
    if (status === 'rejected') {
        return { ok: false, label: '未通过', badge: 'danger', desc: '审核未通过，请修改认证资料后重新提交' };
    }
    return { ok: false, label: '未完成', badge: 'warning', desc: '请完善收款方式，并阅读同意商家守则声明' };
}

function scrollToMerchantCertification() {
    const target = document.getElementById('merchantCertificationBox') || document.querySelector('.payment-receive-card') || document.getElementById('savePaymentMethodsBtn');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    Toast.info(App.currentUser?.qq_bound ? '请完成收款方式，并阅读同意商家守则声明后开通商家' : '请先绑定 QQ，绑定后才可申请开通商家');
}

function paymentMethodNeedsEmailCode(key) {
    return false;
}

function paymentMethodVerifyHtml(key, item) {
    return '';
}

function renderPaymentMethodUploadCard(key, item) {
    const hasQr = !!item.qrcode;
    const imageUrl = hasQr ? `${item.qrcode}${item.qrcode.includes('?') ? '&' : '?'}v=${Date.now()}` : '';
    const uploadAttrs = `for="paymentQrInput_${Security.escapeAttr(key)}" ondragover="handlePaymentQrDragOver(event)" ondragleave="handlePaymentQrDragLeave(event)" ondrop="handlePaymentQrDrop(event, '${Security.escapeAttr(key)}')"`;
    return `
        <div class="payment-receive-card" data-method="${Security.escapeAttr(key)}">
            <div class="payment-receive-head">
                <div class="payment-receive-title"><i class="bi ${paymentMethodIcon(key)}"></i>${Security.escapeHtml(item.label)}</div>
                <span class="badge ${hasQr && item.account ? 'badge-success' : 'badge-warning'}">${hasQr && item.account ? '已配置' : '待完善'}</span>
            </div>
            <label class="form-label mt-3">收款账号</label>
            <div class="payment-account-lock-wrap">
                <input class="form-control" id="paymentAccount_${Security.escapeAttr(key)}" value="${Security.escapeAttr(item.account || '')}" placeholder="填写${Security.escapeAttr(item.label)}账号/昵称">
            </div>
            <label class="payment-upload-zone mt-3" ${uploadAttrs}>
                <input type="file" id="paymentQrInput_${Security.escapeAttr(key)}" accept="image/*" class="hidden" onchange="handlePaymentQrSelect(event, '${Security.escapeAttr(key)}')">
                <input type="hidden" id="paymentQr_${Security.escapeAttr(key)}" value="${Security.escapeAttr(item.qrcode || '')}">
                <div class="payment-upload-preview" id="paymentQrPreview_${Security.escapeAttr(key)}">
                    ${hasQr ? `<img src="${Security.escapeAttr(imageUrl)}" alt="${Security.escapeAttr(item.label)}收款码" onerror="handlePaymentQrImageError(this, '${Security.escapeAttr(item.label)}')"><div class="payment-upload-lock"><i class="bi bi-unlock-fill"></i>可重新上传</div>` : renderPaymentQrPlaceholder()}
                </div>
            </label>
        </div>
    `;
}

function handlePaymentQrImageError(img, label = '收款码') {
    const preview = img?.closest('.payment-upload-preview');
    if (!preview) return;
    preview.innerHTML = `
        <div class="payment-upload-error">
            <i class="bi bi-image-alt"></i>
            <strong>${Security.escapeHtml(label)}图片不存在</strong>
            <small>原收款码文件未找到，请验证后重新上传</small>
        </div>
    `;
}

function getSubdomainBaseDomains(config = {}) {
    const plans = Array.isArray(config.domain_plans) ? config.domain_plans : [];
    if (plans.length) return plans.map(plan => plan.domain).filter(Boolean);
    const domains = Array.isArray(config.base_domains) ? config.base_domains.filter(Boolean) : [];
    if (domains.length) return domains;
    return config.base_domain ? [config.base_domain] : [];
}

function getSubdomainPlanForDomain(config = {}, domain = '') {
    const plans = Array.isArray(config.domain_plans) ? config.domain_plans : [];
    const normalized = String(domain || '').replace(/^\*\./, '');
    const plan = plans.find(item => String(item.domain || '').replace(/^\*\./, '') === normalized);
    if (plan) return plan;
    return {
        domain: normalized,
        monthly_price: config.monthly_price || 10,
        description: ''
    };
}

function getSubdomainMonthlyPrice(config = {}, domain = '') {
    return Number(getSubdomainPlanForDomain(config, domain).monthly_price || config.monthly_price || 10);
}

function updateSubdomainPricingHints(config = {}) {
    const purchaseDomain = document.getElementById('subdomainBaseDomainInput')?.value
        || document.querySelector('.subdomain-base-select')?.value
        || getSubdomainBaseDomains(config)[0]
        || '';
    const purchasePlan = getSubdomainPlanForDomain(config, purchaseDomain);
    const purchasePrice = Number(purchasePlan.monthly_price || 10).toFixed(2);
    const purchaseHint = document.getElementById('subdomainPurchaseHint');
    if (purchaseHint) {
        purchaseHint.innerHTML = `价格：<strong>¥${purchasePrice}</strong> / 月，购买后需等待管理员审核。${purchasePlan.description ? `<div class="mt-1">${Security.escapeHtml(purchasePlan.description)}</div>` : ''}`;
    }
    const renewDomain = document.getElementById('subdomainRenewBaseDomain')?.value || purchaseDomain;
    const renewPlan = getSubdomainPlanForDomain(config, renewDomain);
    const renewPrice = Number(renewPlan.monthly_price || 10).toFixed(2);
    const renewHint = document.getElementById('subdomainRenewPriceHint');
    if (renewHint) {
        renewHint.innerHTML = `续费价格：<strong>¥${renewPrice}</strong> / 月，提交后需等待管理员审核。${renewPlan.description ? `<div class="mt-1">${Security.escapeHtml(renewPlan.description)}</div>` : ''}`;
    }
}

function renderSubdomainBaseDomainField(config, selected = '', inputId = 'subdomainBaseDomainInput', suffixClass = 'subdomain-base-suffix') {
    const domains = getSubdomainBaseDomains(config);
    if (domains.length <= 1) {
        const domain = domains[0] || 'yourdomain.com';
        return `<input type="hidden" id="${Security.escapeAttr(inputId)}" value="${Security.escapeAttr(selected || domain)}"><span class="input-group-text ${Security.escapeAttr(suffixClass)}">.${Security.escapeHtml(domain)}</span>`;
    }
    const options = domains.map(domain => `<option value="${Security.escapeAttr(domain)}" ${domain === selected ? 'selected' : ''}>*.${Security.escapeHtml(domain)}</option>`).join('');
    return `<select id="${Security.escapeAttr(inputId)}" class="form-select subdomain-base-select" data-suffix-class="${Security.escapeAttr(suffixClass)}" style="max-width:180px" onchange="updateSubdomainBaseSuffix(this)">${options}</select>`;
}

function updateSubdomainBaseSuffix(selectEl) {
    const baseDomain = selectEl?.value || document.getElementById('subdomainBaseDomainInput')?.value || '';
    const suffixClass = selectEl?.dataset?.suffixClass || 'subdomain-base-suffix';
    document.querySelectorAll('.' + suffixClass).forEach(el => {
        el.textContent = baseDomain ? '.' + baseDomain : '';
    });
    if (window.__subdomainTabConfig) {
        updateSubdomainPricingHints(window.__subdomainTabConfig);
    }
}

async function loadSubdomainTab(area) {
    const result = await API.getMySubdomain();
    if (!result.success) {
        area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
        return;
    }
    const config = result.config || {};
    window.__subdomainTabConfig = config;
    const subdomain = result.subdomain;
    const merchantApproved = (result.merchant_status || 'none') === 'approved';
    const baseDomains = getSubdomainBaseDomains(config);
    const selectedBaseDomain = subdomain?.base_domain || baseDomains[0] || '';
    const selectedPlan = getSubdomainPlanForDomain(config, selectedBaseDomain);
    const monthlyPrice = Number(selectedPlan.monthly_price || config.monthly_price || 10).toFixed(2);
    const canPurchase = !subdomain || subdomain.status === 'rejected';
    const canRenew = !!subdomain?.can_renew;
    const renewalPending = !!subdomain?.renewal_pending;
    const wildcard = Security.escapeHtml(baseDomains.length ? baseDomains.map(d => '*.' + d).join(' / ') : (config.wildcard_domain || config.base_domain || '未配置'));
    const baseDomainField = renderSubdomainBaseDomainField(config, selectedBaseDomain);
    const baseDomainSuffix = baseDomains.length <= 1 ? '' : `<span class="input-group-text subdomain-base-suffix">.${Security.escapeHtml(selectedBaseDomain)}</span>`;
    let statusHtml = '<span class="badge bg-secondary">未开通</span>';
    if (subdomain) {
        if (renewalPending) statusHtml = '<span class="badge bg-warning text-dark">续费待审核</span>';
        else if (subdomain.status === 'pending') statusHtml = '<span class="badge bg-warning text-dark">待审核</span>';
        else if (subdomain.is_active) statusHtml = '<span class="badge bg-success">生效中</span>';
        else if (subdomain.is_expired) statusHtml = '<span class="badge bg-danger">已过期</span>';
        else if (subdomain.status === 'rejected') statusHtml = '<span class="badge bg-danger">已拒绝</span>';
        else if (subdomain.disabled || subdomain.status === 'disabled') statusHtml = '<span class="badge bg-danger">已禁用</span>';
        else statusHtml = '<span class="badge bg-info text-dark">' + Security.escapeHtml(subdomain.status || '-') + '</span>';
    }
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-globe2 me-2 text-primary"></i>二级域名店铺</h5>
        ${!config.enabled ? '<div class="alert alert-warning">管理员尚未开启二级域名功能。</div>' : ''}
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="profile-card-soft border p-4 h-100">
                    <div class="small text-muted mb-2">平台主域名</div>
                    <div class="fw-bold mb-3"><code>${wildcard}</code></div>
                    <div class="small text-muted mb-1">当前状态</div>
                    <div class="mb-3">${statusHtml}</div>
                    ${subdomain ? `
                        <div class="small text-muted mb-1">我的二级域名</div>
                        <div class="fw-bold mb-3"><code>${Security.escapeHtml(subdomain.full_domain || subdomain.prefix || '-')}</code></div>
                        <div class="small text-muted mb-1">到期时间</div>
                        <div class="mb-0">${subdomain.expires_at ? new Date(subdomain.expires_at * 1000).toLocaleString() : '审核通过后生效'}</div>
                        ${canRenew ? `
                            <div class="mt-4 pt-3 border-top">
                                <div class="small text-muted mb-2">续费月数</div>
                                <input type="hidden" id="subdomainRenewBaseDomain" value="${Security.escapeAttr(selectedBaseDomain)}">
                                <div class="input-group mb-2">
                                    <input id="subdomainRenewMonthsInput" class="form-control" type="number" min="1" max="36" value="1">
                                    <button class="btn btn-primary" onclick="renewSellerSubdomain()" ${!config.enabled || !merchantApproved ? 'disabled' : ''}>续费</button>
                                </div>
                                <div class="small text-muted" id="subdomainRenewPriceHint">续费价格：¥${monthlyPrice} / 月，提交后需等待管理员审核。</div>
                            </div>
                        ` : ''}
                        ${renewalPending ? '<div class="small text-warning mt-3">续费申请审核中，通过后自动延长到期时间，店铺可继续访问。</div>' : ''}
                    ` : '<div class="text-muted small">购买或兑换后，访问您的二级域名将只展示您的全部商品。</div>'}
                </div>
            </div>
            <div class="col-lg-7">
                ${canPurchase ? `
                <div class="profile-card-soft border p-4 mb-4">
                    <h6 class="fw-bold mb-3">余额购买</h6>
                    ${!merchantApproved ? '<div class="alert alert-warning small">请先完成商家认证后再申请二级域名。</div>' : ''}
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">域名前缀</label>
                            <div class="input-group">
                                <input id="subdomainPrefixInput" class="form-control" placeholder="例如：roxy" value="${Security.escapeAttr(subdomain?.prefix || '')}">
                                ${baseDomains.length > 1 ? baseDomainField + baseDomainSuffix : baseDomainField}
                            </div>
                            <div id="subdomainPrefixHint" class="form-text"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">购买月数</label>
                            <input id="subdomainMonthsInput" class="form-control" type="number" min="1" max="36" value="1">
                        </div>
                        <div class="col-12">
                            <div class="small text-muted mb-2" id="subdomainPurchaseHint">价格：¥${monthlyPrice} / 月，购买后需等待管理员审核。${selectedPlan.description ? `<div class="mt-1">${Security.escapeHtml(selectedPlan.description)}</div>` : ''}</div>
                            <button class="btn btn-primary" onclick="purchaseSellerSubdomain()" ${!config.enabled || !merchantApproved ? 'disabled' : ''}>立即购买</button>
                        </div>
                    </div>
                </div>
                ` : ''}
                ${canPurchase ? `
                <div class="profile-card-soft border p-4">
                    <h6 class="fw-bold mb-3">卡密兑换</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">域名前缀</label>
                            <div class="input-group">
                                <input id="subdomainCardPrefixInput" class="form-control" placeholder="例如：roxy" value="${Security.escapeAttr(subdomain?.prefix || '')}">
                                ${renderSubdomainBaseDomainField(config, selectedBaseDomain, 'subdomainCardBaseDomainInput', 'subdomain-card-base-suffix')}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">二级域名卡密</label>
                            <input id="subdomainCardCodeInput" class="form-control" placeholder="输入卡密代码">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-outline-primary" onclick="redeemSubdomainCard()" ${!config.enabled || !merchantApproved ? 'disabled' : ''}>兑换并提交审核</button>
                        </div>
                    </div>
                </div>
                ` : `
                <div class="profile-card-soft border p-4">
                    <h6 class="fw-bold mb-3">店铺说明</h6>
                    <div class="text-muted small mb-0">${selectedPlan.description ? Security.escapeHtml(selectedPlan.description) : '您已开通二级域名店铺。如需延长使用期限，请在左侧使用续费功能。'}</div>
                </div>
                `}
            </div>
        </div>`;
    updateSubdomainPricingHints(config);
    const prefixInput = document.getElementById('subdomainPrefixInput');
    if (prefixInput && !prefixInput.readOnly) {
        prefixInput.addEventListener('input', () => {
            clearTimeout(window.__subdomainPrefixTimer);
            window.__subdomainPrefixTimer = setTimeout(async () => {
                const prefix = prefixInput.value.trim();
                const hint = document.getElementById('subdomainPrefixHint');
                if (!prefix || !hint) return;
                const check = await API.checkSubdomainPrefix(prefix);
                hint.textContent = check.message || '';
                hint.className = 'form-text ' + (check.success && check.available ? 'text-success' : 'text-danger');
            }, 300);
        });
    }
}

async function purchaseSellerSubdomain() {
    const prefix = document.getElementById('subdomainPrefixInput')?.value?.trim() || '';
    const months = document.getElementById('subdomainMonthsInput')?.value || '1';
    const baseDomain = document.getElementById('subdomainBaseDomainInput')?.value || '';
    if (!prefix) return Toast.warning('请输入域名前缀');
    const result = await API.purchaseSubdomain(prefix, months, baseDomain);
    if (!result.success) return Toast.error(result.message || '购买失败');
    Toast.success(result.message || '购买成功');
    await refreshUserData();
    renderDashboardTab('subdomain');
}

async function renewSellerSubdomain() {
    const months = document.getElementById('subdomainRenewMonthsInput')?.value || '1';
    const baseDomain = document.getElementById('subdomainRenewBaseDomain')?.value || '';
    const result = await API.renewSubdomain(months, baseDomain);
    if (!result.success) return Toast.error(result.message || '续费失败');
    Toast.success(result.message || '续费申请已提交');
    await refreshUserData();
    renderDashboardTab('subdomain');
}

async function redeemSubdomainCard() {
    const prefix = document.getElementById('subdomainCardPrefixInput')?.value?.trim() || '';
    const code = document.getElementById('subdomainCardCodeInput')?.value?.trim() || '';
    if (!prefix) return Toast.warning('请输入域名前缀');
    if (!code) return Toast.warning('请输入卡密');
    const baseDomain = document.getElementById('subdomainCardBaseDomainInput')?.value || document.getElementById('subdomainBaseDomainInput')?.value || '';
    const result = await API.useCard(code, { subdomain_prefix: prefix, subdomain_base_domain: baseDomain });
    if (!result.success) return Toast.error(result.message || '兑换失败');
    if ((result.card_type || '') !== 'subdomain') {
        return Toast.error('该卡密不是二级域名卡，请到余额管理或会员中心使用对应卡密');
    }
    Toast.success(result.message || '兑换成功，请等待管理员审核');
    renderDashboardTab('subdomain');
}

async function loadProfileTab(area) {
    const user = App.currentUser || {};
    const maskedEmail = user.email ? user.email.replace(/^(.{2}).*(@.*)$/, '$1****$2') : '未绑定邮箱';
    const qqBound = !!user.qq_bound;
    const merchant = merchantStatusInfo(user);
    const merchantVerified = merchant.ok;
    const paymentMethods = getUserPaymentMethods(user);
    const isAdmin = user.role === 'admin';
    let adminConfigHtml = '';
    profileSecurityUnlocked = false;
    profileEmailVerifyPending = false;
    profilePaymentInitiallyConfigured = Object.values(paymentMethods).some(item => !!(item.account || item.qrcode));
    if (isAdmin) {
        const configResult = await API.getSystemConfig();
        const config = configResult.success ? (configResult.config || {}) : {};
        adminConfigHtml = `
            <div class="col-12">
                <div class="profile-card-soft">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-1"><i class="bi bi-sliders me-2 text-primary"></i>提现与收款设置</h6>
                            <div class="text-muted small">嵌入式管理，不再使用弹窗</div>
                        </div>
                        <span class="badge badge-primary">管理员</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="configEnableWithdraw" ${config.enable_withdraw !== false && config.enable_withdraw !== '0' ? 'checked' : ''}>
                                <label class="form-check-label" for="configEnableWithdraw">启用提现功能</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">最低提现金额</label>
                            <input type="number" class="form-control" id="configMinWithdraw" step="0.01" min="1" value="${Security.escapeAttr(config.min_withdraw_amount || 10)}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">提现手续费率(%)</label>
                            <input type="number" class="form-control" id="configWithdrawFee" step="0.1" min="0" max="100" value="${Security.escapeAttr(Number(config.withdraw_fee_rate ?? 0.01) * 100)}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">管理员微信收款码 URL</label>
                            <input type="text" class="form-control" id="configWechatQrcode" value="${Security.escapeAttr(config.admin_wechat_qrcode || '')}" placeholder="https://example.com/wechat.png">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">管理员支付宝收款码 URL</label>
                            <input type="text" class="form-control" id="configAlipayQrcode" value="${Security.escapeAttr(config.admin_alipay_qrcode || '')}" placeholder="https://example.com/alipay.png">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="saveSystemConfig({ embedded: true })"><i class="bi bi-check2-circle me-1"></i>保存设置</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-person-circle me-2 text-primary"></i>个人中心</h5>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="profile-card-soft h-100">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="profile-avatar-wrap">
                            <label class="profile-avatar-upload" for="profileAvatarInput" title="点击上传头像">
                                ${avatarHtml(user, 'profile-main-avatar')}
                                <span class="profile-avatar-camera"><i class="bi bi-camera-fill"></i></span>
                            </label>
                            <input type="file" id="profileAvatarInput" accept="image/*" class="hidden" onchange="handleAvatarSelect(event)">
                            <button class="btn btn-sm btn-outline-primary mt-2" onclick="document.getElementById('profileAvatarInput')?.click()"><i class="bi bi-upload me-1"></i>上传头像</button>
                            <small class="text-muted d-block mt-1">不上传则使用默认头像</small>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-1">${Security.escapeHtml(user.username || '-')}</h5>
                            <div class="text-muted small">${Security.escapeHtml(maskedEmail)}</div>
                        </div>
                    </div>
                    <div class="profile-info-row"><span>会员等级</span><strong>${Security.escapeHtml(user.membership_level || 'Free')}</strong></div>
                    <div class="profile-info-row align-items-center gap-2 flex-wrap">
                        <span>自定义标签</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="renderDashboardTab('customlabel')">去设置</button>
                    </div>
                    <div class="profile-info-row"><span>账户余额</span><strong>¥ ${Number(user.balance || 0).toFixed(2)}</strong></div>
                    <div class="profile-info-row"><span>QQ 绑定</span><strong class="${qqBound ? 'text-success' : 'text-muted'}">${qqBound ? Security.escapeHtml(user.qq_nickname || '已绑定') : '未绑定'}</strong></div>
                    <div class="profile-info-row align-items-center gap-2 flex-wrap">
                        <span>商家认证</span>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <strong class="text-${merchant.badge === 'success' ? 'success' : merchant.badge === 'danger' ? 'danger' : 'warning'}">${Security.escapeHtml(merchant.label)}</strong>
                            <button class="btn btn-sm ${merchantVerified ? 'btn-outline-primary' : 'btn-primary'}" onclick="scrollToMerchantCertification()"><i class="bi ${merchantVerified ? 'bi-eye' : 'bi-shield-check'} me-1"></i>${merchantVerified ? '查看认证' : '去认证'}</button>
                        </div>
                    </div>
                    <div class="mt-4 d-grid gap-2">
                        ${qqBound ? `<button class="btn btn-outline-danger" onclick="unbindQQAccount()"><i class="bi bi-link-45deg me-1"></i>解绑第三方账号</button>` : `<button class="btn btn-primary" onclick="bindQQAccount()"><i class="bi bi-tencent-qq me-1"></i>绑定第三方账号</button>`}
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="profile-card-soft h-100">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i>个人资料</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">用户名</label>
                            <input class="form-control" id="profileUsername" value="${Security.escapeAttr(user.username || '')}" placeholder="2-30个字符，支持中文">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">邮箱</label>
                            <input class="form-control" id="profileEmail" type="email" value="${Security.escapeAttr(user.email || '')}" placeholder="your@email.com">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="saveProfileInfo()"><i class="bi bi-check2-circle me-1"></i>保存个人资料</button>
                        </div>
                    </div>
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>账号安全验证</h6>
                    <div class="alert alert-light border small mb-3">发送一次验证码即可在有效期内用于修改登录密码：<strong>${Security.escapeHtml(maskedEmail)}</strong></div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">邮箱验证码</label>
                            <input class="form-control" id="profileEmailCode" maxlength="6" inputmode="numeric" placeholder="请输入 6 位验证码" oninput="handleProfileEmailCodeInput()">
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <button class="btn btn-outline-primary w-100" id="sendProfileEmailCodeBtn" onclick="sendProfileEmailCode()">发送验证码</button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">新密码</label>
                            <input class="form-control locked" id="profileNewPassword" type="password" placeholder="验证邮箱验证码后可输入新密码" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">确认新密码</label>
                            <input class="form-control locked" id="profileConfirmPassword" type="password" placeholder="验证邮箱验证码后可确认新密码" disabled>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" id="changeProfilePasswordBtn" onclick="changeProfilePassword()" disabled><i class="bi bi-check2-circle me-1"></i>确认修改密码</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="profile-card-soft">
                    <div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap">
                        <div>
                            <h6 class="fw-bold mb-1"><i class="bi bi-wallet2 me-2 text-primary"></i>收款方式</h6>
                            <div class="text-muted small">提现会直接使用这里配置的微信或支付宝收款信息</div>
                        </div>
                        <button class="btn btn-primary" id="savePaymentMethodsBtn" onclick="savePaymentMethods()"><i class="bi bi-check2-circle me-1"></i>保存收款方式</button>
                    </div>
                    <div id="paymentMethodsNotice" class="payment-methods-notice hidden mb-3"></div>
                    <div class="payment-receive-grid">
                        ${Object.entries(paymentMethods).map(([key, item]) => renderPaymentMethodUploadCard(key, item)).join('')}
                    </div>
                    <div class="mt-4" id="merchantCertificationBox">
                        ${renderMerchantCertificationBox(user)}
                    </div>
                </div>
            </div>
            ${adminConfigHtml}
        </div>
    `;
    setTimeout(startMerchantReadTimer, 50);
}

async function loadCustomLabelTab(area) {
    const userResult = await API.getCurrentUser();
    if (userResult.success && userResult.logged_in && userResult.user) {
        App.setUser(userResult.user);
    }
    const user = App.currentUser || {};
    const levelsResult = await API.getMembershipLevels();
    const levelInfo = (levelsResult.success ? (levelsResult.levels || {}) : {})[user.membership_level || 'Free'] || {};
    const canUseCustomLabel = user.role !== 'admin' && !!(levelInfo.custom_label_enabled || user.can_use_custom_label);

    if (user.role === 'admin') {
        area.innerHTML = `
            <h5 class="fw-bold mb-4"><i class="bi bi-tags me-2 text-primary"></i>自定义标签</h5>
            <div class="profile-card-soft">
                <div class="alert alert-info mb-0">
                    管理员账号显示<strong>专属标识</strong>，不使用会员自定义标签。<br>
                    请到后台 <strong>会员等级</strong> 页面顶部配置「管理员专属标识」的文字、图标和渐变颜色。
                </div>
            </div>`;
        return;
    }

    if (!canUseCustomLabel) {
        area.innerHTML = `
            <h5 class="fw-bold mb-4"><i class="bi bi-tags me-2 text-primary"></i>自定义标签</h5>
            <div class="profile-card-soft">
                <div class="alert alert-warning mb-3">
                    你当前的会员等级 <strong>${Security.escapeHtml(user.membership_level || 'Free')}</strong> 尚未开通自定义标签。
                </div>
                <div class="text-muted small">
                    请让管理员到后台 <strong>会员等级</strong> → 编辑对应等级 → 勾选 <strong>「允许自定义标签」</strong> → 保存配置后，重新进入本页面即可设置。
                </div>
            </div>`;
        return;
    }

    const previewGradient = user.custom_label_gradient || levelInfo.gradient || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
    area.innerHTML = `
        <h5 class="fw-bold mb-4"><i class="bi bi-tags me-2 text-primary"></i>自定义标签</h5>
        <div class="profile-card-soft">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                    <div class="text-muted small">设置后会显示在商品卡片「卖家名称」后面，文字 1-10 个字符，可自定义 bi-xx 图标和渐变背景。</div>
                </div>
                <div id="customLabelPreview">${typeof renderGradientBadge === 'function' ? renderGradientBadge(user.custom_label_text || '预览', user.custom_label_icon || 'bi-tag', previewGradient, 'custom-label-badge') : ''}</div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">标签文字</label>
                    <input class="form-control" id="customLabelText" maxlength="10" value="${Security.escapeAttr(user.custom_label_text || '')}" placeholder="1-10 个字符" oninput="updateCustomLabelPreview()">
                </div>
                <div class="col-md-4">
                    <label class="form-label">图标 class</label>
                    <input class="form-control" id="customLabelIcon" value="${Security.escapeAttr(user.custom_label_icon || 'bi-tag')}" placeholder="例如 bi-star-fill" oninput="updateCustomLabelPreview()">
                    <div class="form-text">填写 Bootstrap Icons 名称，如 bi-star-fill</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">背景渐变 CSS</label>
                    <input class="form-control" id="customLabelGradient" value="${Security.escapeAttr(user.custom_label_gradient || levelInfo.gradient || '')}" placeholder="linear-gradient(135deg, #6366f1, #8b5cf6)" oninput="updateCustomLabelPreview()">
                </div>
                <div class="col-12">
                    <button type="button" class="btn btn-primary" onclick="saveCustomLabel()"><i class="bi bi-check2-circle me-1"></i>保存自定义标签</button>
                </div>
            </div>
        </div>`;
}

function updateCustomLabelPreview() {
    const preview = document.getElementById('customLabelPreview');
    if (!preview || typeof renderGradientBadge !== 'function') return;
    const text = document.getElementById('customLabelText')?.value.trim() || '预览';
    const icon = document.getElementById('customLabelIcon')?.value.trim() || 'bi-tag';
    const gradient = document.getElementById('customLabelGradient')?.value.trim() || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
    preview.innerHTML = renderGradientBadge(text, icon, gradient, 'custom-label-badge');
}

async function saveCustomLabel() {
    const text = document.getElementById('customLabelText')?.value.trim() || '';
    const icon = document.getElementById('customLabelIcon')?.value.trim() || 'bi-tag';
    const gradient = document.getElementById('customLabelGradient')?.value.trim() || '';
    if (!text) return Toast.warning('请填写 1-10 个字符的标签文字');
    if (text.length > 10) return Toast.warning('标签文字不能超过 10 个字符');
    const result = await API.saveCustomLabel(text, icon, gradient);
    if (!result.success) return Toast.error(result.message || '保存失败');
    Toast.success(result.message || '自定义标签已保存');
    if (result.user) App.setUser(result.user);
    updateCustomLabelPreview();
}

async function handleAvatarSelect(event) {
    const input = event.target;
    const file = input?.files?.[0];
    if (!file) return;
    if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
        input.value = '';
        return Toast.warning('头像仅支持 JPG、PNG、GIF、WEBP 图片');
    }
    if (file.size > 2 * 1024 * 1024) {
        input.value = '';
        return Toast.warning('头像大小不能超过2MB');
    }
    const btn = document.querySelector('.profile-avatar-wrap .btn');
    const oldHtml = btn?.innerHTML;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>上传中';
    }
    const result = await API.uploadAvatar(file);
    input.value = '';
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
    }
    if (!result.success) return Toast.error(result.message || '头像上传失败');
    if (result.user) App.setUser(result.user);
    Toast.success(result.message || '头像上传成功');
    renderDashboardTab('profile');
}

function merchantAgreementDefaultText() {
    return `商家守则、免责声明与商家质保

一、商家守则
1. 商家应保证发布的商品信息真实、完整、合法，不得发布违法违规、侵权、欺诈、虚假宣传或无法交付的商品。
2. 商家应及时处理订单、发货、售后和用户咨询，不得恶意拖延、诱导站外交易或逃避平台规则。
3. 商家应妥善保管收款账号和收款码，因资料错误导致的收款异常由商家自行承担。

二、免责声明
1. 商家确认已充分了解虚拟商品交易风险，并承诺自行承担因商品来源、授权、交付、售后等产生的责任。
2. 因商家商品描述不清、违规发布、无法交付、售后拒绝处理等造成的纠纷、退款、赔付或法律责任，由商家自行承担。
3. 平台可根据投诉、风控或监管要求对商品、订单、资金和商家资格采取限制、冻结、下架或关闭等必要措施。

三、商家质保
1. 商家承诺对所售商品提供明确、有效的质量保障和售后说明，并按承诺处理补发、换货、退款或技术支持。
2. 如商品存在不可用、与描述不符、重复销售、失效等问题，商家应优先保障买家权益并积极配合平台处理。
3. 商家连续出现高投诉、拒不售后或严重违规时，平台有权取消商家资格，后续重新开通需人工审核。

四、开通确认
本人确认已阅读并同意以上商家守则、免责声明与商家质保，自愿申请开通商家功能，并承诺遵守平台全部规则。`;
}

function renderMerchantCertificationBox(user = App.currentUser || {}) {
    const merchant = merchantStatusInfo(user);
    const agreementText = merchantAgreementDefaultText();
    const openedOnce = user.merchant_opened_once === true || user.merchant_opened_once === '1';
    const qqBound = !!user.qq_bound;
    const saveText = merchant.ok ? '更新认证资料' : (openedOnce ? '提交重新开通审核' : '同意并开通商家');
    const blockHtml = qqBound ? '' : `
        <div class="alert alert-warning small mb-3">
            <i class="bi bi-tencent-qq me-1"></i>开通商家前必须先绑定 QQ，用于身份确认和后续风控。请先点击左侧个人信息中的“绑定第三方账号”。
        </div>
    `;
    return `
        <div class="profile-card-soft border" style="box-shadow:none;" data-merchant-status="${Security.escapeAttr(user.merchant_status || 'none')}">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                <div>
                    <h6 class="fw-bold mb-1"><i class="bi bi-shield-check me-2 text-primary"></i>商家开通认证</h6>
                    <div class="text-muted small">首次开通免审核；后续被取消后重新开通需后台审核。</div>
                </div>
                <span class="badge badge-${merchant.badge}">${Security.escapeHtml(merchant.label)}</span>
            </div>
            <div class="alert alert-light border small mb-3">${Security.escapeHtml(merchant.desc)}</div>
            ${blockHtml}
            <label class="form-label fw-semibold">商家守则、免责声明与商家质保</label>
            <textarea class="form-control" id="merchantAgreementText" rows="9" readonly onscroll="handleMerchantAgreementScroll()">${Security.escapeHtml(agreementText)}</textarea>
            <div class="form-text" id="merchantReadTimerText">请至少阅读 5 秒后再勾选同意。</div>
            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" id="merchantRulesAccepted" ${user.merchant_rules_accepted ? 'checked' : ''} ${user.merchant_rules_accepted ? '' : 'disabled'}>
                <label class="form-check-label" for="merchantRulesAccepted">我已阅读并同意商家守则、免责声明与商家质保，申请开通商家功能</label>
            </div>
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <button class="btn btn-primary" id="merchantCertificationSaveHintBtn" onclick="savePaymentMethods()" ${qqBound ? '' : 'disabled title="请先绑定 QQ 后再开通商家"'}><i class="bi bi-check2-circle me-1"></i>${saveText}</button>
            </div>
        </div>
    `;
}

function startMerchantReadTimer() {
    merchantAgreementReadReady = !!(App.currentUser || {}).merchant_rules_accepted;
    if (merchantAgreementTimer) clearInterval(merchantAgreementTimer);
    let remaining = merchantAgreementReadReady ? 0 : 5;
    const text = document.getElementById('merchantReadTimerText');
    const checkbox = document.getElementById('merchantRulesAccepted');
    if (!text || !checkbox || merchantAgreementReadReady) return;
    text.textContent = `请至少阅读 ${remaining} 秒后再勾选同意。`;
    merchantAgreementTimer = setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
            merchantAgreementReadReady = true;
            checkbox.disabled = false;
            text.textContent = '已满足阅读时间，请勾选同意后提交。';
            clearInterval(merchantAgreementTimer);
            return;
        }
        text.textContent = `请至少阅读 ${remaining} 秒后再勾选同意。`;
    }, 1000);
}

function handleMerchantAgreementScroll() {}

let profileSecurityUnlocked = false;
let profileEmailVerifyPending = false;
let profilePaymentInitiallyConfigured = false;
let merchantAgreementTimer = null;
let merchantAgreementReadReady = false;
function setProfileSecurityUnlocked(unlocked) {
    profileSecurityUnlocked = !!unlocked;
    ['profileNewPassword', 'profileConfirmPassword'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.disabled = !profileSecurityUnlocked;
            input.classList.toggle('locked', !profileSecurityUnlocked);
        }
    });
    const passwordBtn = document.getElementById('changeProfilePasswordBtn');
    if (passwordBtn) passwordBtn.disabled = !profileSecurityUnlocked;
}

function handleProfileEmailCodeInput() {
    const input = document.getElementById('profileEmailCode');
    const code = (input?.value || '').replace(/\D/g, '').slice(0, 6);
    if (input && input.value !== code) input.value = code;
    if (code.length < 6) {
        if (profileSecurityUnlocked) setProfileSecurityUnlocked(false);
        return;
    }
    Toast.info('正在验证邮箱验证码...');
    verifyProfileEmailCodeAndUnlock(code);
}

async function verifyProfileEmailCodeAndUnlock(code) {
    if (profileEmailVerifyPending || profileSecurityUnlocked) return;
    profileEmailVerifyPending = true;
    const input = document.getElementById('profileEmailCode');
    if (input) input.classList.add('is-validating');
    const result = await API.verifyProfileEmailCode(code);
    profileEmailVerifyPending = false;
    if (input) input.classList.remove('is-validating');
    if (!result.success) {
        setProfileSecurityUnlocked(false);
        Toast.error(result.message || '验证码校验失败，请重新输入');
        return;
    }
    setProfileSecurityUnlocked(true);
    Toast.success(result.message || '验证通过，已解锁修改密码');
}

let profileEmailCountdown = 0;
let profileEmailTimer = null;
function updateProfileEmailButton() {
    const btn = document.getElementById('sendProfileEmailCodeBtn');
    if (!btn) return;
    if (profileEmailCountdown > 0) {
        btn.disabled = true;
        btn.textContent = profileEmailCountdown + '秒后重发';
    } else {
        btn.disabled = false;
        btn.textContent = '发送验证码';
    }
}
async function sendProfileEmailCode() {
    if (profileEmailCountdown > 0) return;
    if (window.API && typeof API.captchaDebug === 'function') API.captchaDebug('profile_send_click').catch(() => {});
    const btn = document.getElementById('sendProfileEmailCodeBtn');
    const oldText = btn?.textContent || '发送验证码';
    if (btn) {
        btn.disabled = true;
        btn.textContent = '验证中...';
    }
    let result;
    try {
        if (typeof window.runCaptcha !== 'function') {
            throw new Error('人机验证脚本未加载，请强制刷新页面后重试');
        }
        const captchaToken = await window.runCaptcha('email_code', true);
        if (btn) btn.textContent = '发送中...';
        result = await API.sendProfileEmailCode(captchaToken);
    } catch (error) {
        if (btn) {
            btn.disabled = false;
            btn.textContent = oldText;
        }
        if (error && error.message === 'captcha_cancelled') {
            Toast.warning('已取消人机验证');
        } else {
            Toast.error(error?.message || '人机验证失败，请重试');
        }
        return;
    }
    if (!result.success) {
        if (btn) {
            btn.disabled = false;
            btn.textContent = oldText;
        }
        return Toast.error(result.message || '验证码发送失败');
    }
    Toast.success(result.message || '验证码已发送');
    profileEmailCountdown = 60;
    updateProfileEmailButton();
    clearInterval(profileEmailTimer);
    profileEmailTimer = setInterval(() => {
        profileEmailCountdown -= 1;
        if (profileEmailCountdown <= 0) clearInterval(profileEmailTimer);
        updateProfileEmailButton();
    }, 1000);
}
window.sendProfileEmailCode = sendProfileEmailCode;
async function saveProfileInfo() {
    const username = document.getElementById('profileUsername')?.value.trim() || '';
    const email = document.getElementById('profileEmail')?.value.trim() || '';
    if (!username || !email) return Toast.warning('请填写用户名和邮箱');
    const result = await API.updateProfile(username, email);
    if (!result.success) return Toast.error(result.message || '保存失败');
    Toast.success(result.message || '个人资料已保存');
    if (result.user) App.setUser(result.user);
    renderDashboardTab('profile');
}

function handlePaymentQrDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('dragover');
}

function handlePaymentQrDragLeave(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');
}

function handlePaymentQrDrop(event, method) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');
    if (paymentMethodNeedsEmailCode(method)) return Toast.info('收款码已锁定，不能重复上传');
    const accountValue = document.getElementById('paymentAccount_' + method)?.value.trim() || '';
    if (!accountValue) return warnPaymentMethods('请先填写收款账号/昵称，再上传收款码图片');
    const file = event.dataTransfer?.files?.[0];
    if (file) uploadPaymentQrFile(method, file);
}

function handlePaymentQrSelect(event, method) {
    if (paymentMethodNeedsEmailCode(method)) {
        if (event.target) event.target.value = '';
        return Toast.info('收款码已锁定，不能重复上传');
    }
    const accountValue = document.getElementById('paymentAccount_' + method)?.value.trim() || '';
    if (!accountValue) {
        if (event.target) event.target.value = '';
        return warnPaymentMethods('请先填写收款账号/昵称，再上传收款码图片');
    }
    const file = event.target?.files?.[0];
    if (file) uploadPaymentQrFile(method, file);
}

function renderPaymentQrPlaceholder() {
    return '<i class="bi bi-cloud-arrow-up"></i><span>点击或拖拽上传收款码</span>';
}

function renderPaymentQrError(message, previousHtml = '') {
    const retry = previousHtml ? '<div class="small text-muted mt-2">可重新选择图片再试</div>' : '<div class="small text-muted mt-2">请检查图片格式、大小或稍后重试</div>';
    return `<div class="payment-upload-error"><i class="bi bi-exclamation-triangle"></i><strong>上传失败</strong><span>${Security.escapeHtml(message || '未知错误')}</span>${retry}</div>`;
}

async function uploadPaymentQrFile(method, file) {
    if (!file.type.startsWith('image/')) return Toast.warning('请选择图片文件');
    if (file.size > 2 * 1024 * 1024) return Toast.warning('图片大小不能超过2MB');
    const accountValue = document.getElementById('paymentAccount_' + method)?.value.trim() || '';
    if (!accountValue) return warnPaymentMethods('请先填写收款账号/昵称，再上传收款码图片');
    const preview = document.getElementById('paymentQrPreview_' + method);
    const previousHtml = preview?.innerHTML || renderPaymentQrPlaceholder();
    if (preview) preview.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><span>上传中...</span>';
    let result;
    try {
        result = await API.uploadPaymentQrcode(file, method, '', accountValue);
    } catch (error) {
        const message = error?.message || '收款码上传异常，请重试';
        Toast.error(message);
        showPaymentMethodsNotice(message, 'error');
        if (preview) preview.innerHTML = renderPaymentQrError(message, previousHtml);
        return;
    }
    document.getElementById('paymentQrInput_' + method).value = '';
    if (!result.success) {
        const message = result.message || '上传失败';
        Toast.error(message);
        showPaymentMethodsNotice(message, 'error');
        if (preview) preview.innerHTML = renderPaymentQrError(message, previousHtml);
        return;
    }
    const input = document.getElementById('paymentQr_' + method);
    if (input) input.value = result.url || '';
    if (preview) preview.innerHTML = `<img src="${Security.escapeAttr((result.url || '') + '?v=' + Date.now())}" alt="收款码">`;
    if (result.user) App.setUser(result.user);
    Toast.success(result.message || '上传成功');
}

function showPaymentMethodsNotice(message, type = 'info') {
    const box = document.getElementById('paymentMethodsNotice');
    if (!box) return;
    const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill';
    box.className = `payment-methods-notice ${type} mb-3`;
    box.innerHTML = `<i class="bi ${icon}"></i><span>${Security.escapeHtml(message)}</span>`;
}

function warnPaymentMethods(message) {
    showPaymentMethodsNotice(message, 'error');
    Toast.warning(message);
}

async function savePaymentMethods() {
    if (!App.currentUser?.qq_bound) {
        return warnPaymentMethods('请先绑定 QQ 后再申请开通商家');
    }
    const methods = getUserPaymentMethods();
    Object.keys(methods).forEach(key => {
        methods[key].account = document.getElementById('paymentAccount_' + key)?.value.trim() || '';
        methods[key].qrcode = document.getElementById('paymentQr_' + key)?.value.trim() || '';
    });
    for (const [key, item] of Object.entries(methods)) {
        if (!item.account || !item.qrcode) {
            return warnPaymentMethods(`${item.label || key}需要同时填写收款账号并上传收款码图片后才能保存`);
        }
    }
    const emailCode = document.getElementById('profileEmailCode')?.value.trim() || '';
    const merchantRulesAccepted = !!document.getElementById('merchantRulesAccepted')?.checked;
    if (!merchantRulesAccepted) {
        return warnPaymentMethods('请先阅读商家守则满5秒，并勾选同意开通商家');
    }
    const btn = document.getElementById('savePaymentMethodsBtn');
    const oldHtml = btn?.innerHTML;
    if (btn) {
        btn.classList.add('disabled');
        btn.setAttribute('aria-disabled', 'true');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>保存中...';
    }
    showPaymentMethodsNotice('正在保存收款方式...', 'info');
    const result = await API.savePaymentMethods(methods, emailCode, merchantRulesAccepted);
    if (btn) {
        btn.classList.remove('disabled');
        btn.setAttribute('aria-disabled', 'false');
        btn.innerHTML = oldHtml;
    }
    if (!result.success) {
        showPaymentMethodsNotice(result.message || '保存失败', 'error');
        return Toast.error(result.message || '保存失败');
    }
    showPaymentMethodsNotice(result.message || '收款方式已保存', 'success');
    Toast.success(result.message || '收款方式已保存');
    if (result.user) App.setUser(result.user);
    setTimeout(() => renderDashboardTab('profile'), 700);
}
async function changeProfilePassword() {
    const code = document.getElementById('profileEmailCode')?.value.trim() || '';
    const pwd = document.getElementById('profileNewPassword')?.value || '';
    const confirm = document.getElementById('profileConfirmPassword')?.value || '';
    if (!code) return Toast.warning('请先输入6位邮箱验证码完成解锁');
    if (!profileSecurityUnlocked) return Toast.warning('验证码验证通过后才能修改密码');
    if (!pwd || !confirm) return Toast.warning('请填写新密码和确认密码');
    const result = await API.changePassword(code, pwd, confirm);
    if (!result.success) return Toast.error(result.message || '修改失败');
    Toast.success(result.message || '密码修改成功');
    document.getElementById('profileEmailCode').value = '';
    document.getElementById('profileNewPassword').value = '';
    document.getElementById('profileConfirmPassword').value = '';
    setProfileSecurityUnlocked(false);
}
function bindQQAccount() {
    startOAuthLogin('qq', 'bind');
}
async function unbindQQAccount() {
    if (!confirm('确定要解绑 QQ 吗？解绑后不能使用该 QQ 一键登录此账号。')) return;
    const result = await API.unbindQQ();
    if (!result.success) return Toast.error(result.message || '解绑失败');
    Toast.success(result.message || 'QQ 已解绑');
    if (result.user) App.setUser(result.user);
    renderDashboardTab('profile');
}

async function loadMessagesTab(area) {
    const result = await API.getContacts();
    const contacts = result.success ? result.contacts : [];
    const selectedPartner = App.currentChatPartner;

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
                                <div class="sidebar-nav-item ${App.currentChatPartner === c.username ? 'active' : ''}" data-username="${Security.escapeAttr(c.username)}" onclick="selectContactTab('${Security.escapeAttr(c.username)}')">
                                    <span>${Security.escapeHtml(c.username)}</span>
                                    ${c.unread > 0 ? `<span class="badge badge-danger ms-auto">${Security.escapeHtml(c.unread)}</span>` : ''}
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
    if (selectedPartner) {
        selectContactTab(selectedPartner, { skipReadRefresh: true });
    }
}

async function selectContactTab(username, options = {}) {
    App.currentChatPartner = username;

    document.querySelectorAll('#contactListTab .sidebar-nav-item').forEach(item => {
        const name = item.dataset.username || item.textContent.trim();
        item.classList.toggle('active', name === username);
    });

    const result = await API.getConversation(username);
    const messages = result.success ? result.messages : [];

    const chatArea = document.getElementById('tabChatArea');
    chatArea.innerHTML = `
        <div class="d-flex flex-column h-100">
            <div class="p-2 border-bottom bg-light d-flex justify-content-between align-items-center">
                <strong>${Security.escapeHtml(username)}</strong>
                <button class="btn btn-sm btn-outline" onclick="refreshCurrentConversation()"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
            <div class="chat-container flex-grow-1" id="tabChatMessages">
                ${messages.map(m => `
                    <div class="chat-bubble ${m.from === App.currentUser.username ? 'sent' : 'received'}">
                        ${Security.escapeHtml(m.content)}
                        <span class="chat-time">${Utils.formatDate(m.timestamp)}</span>
                    </div>
                `).join('') || '<div class="empty-state py-4"><p>暂无消息，开始聊天吧</p></div>'}
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

    if (!options.skipReadRefresh) {
        await API.getConversation(username);
        App.updateUnreadBadge();
        refreshContactListTab({ keepSelection: true });
    }
}

async function refreshContactListTab(options = {}) {
    const result = await API.getContacts();
    const contacts = result.success ? result.contacts : [];
    const list = document.getElementById('contactListTab');
    if (!list) return;
    list.innerHTML = `
        <p class="text-muted small px-2">联系人</p>
        ${contacts.length === 0 ? '<p class="text-muted small px-2">暂无联系人</p>' : contacts.map(c => `
            <div class="sidebar-nav-item ${App.currentChatPartner === c.username ? 'active' : ''}" data-username="${Security.escapeAttr(c.username)}" onclick="selectContactTab('${Security.escapeAttr(c.username)}')">
                <span>${Security.escapeHtml(c.username)}</span>
                ${c.unread > 0 ? `<span class="badge badge-danger ms-auto">${Security.escapeHtml(c.unread)}</span>` : ''}
            </div>
        `).join('')}
    `;
    if (options.keepSelection && App.currentChatPartner) {
        const active = Array.from(list.querySelectorAll('.sidebar-nav-item')).find(item => item.dataset.username === App.currentChatPartner);
        if (active) active.classList.add('active');
    }
}

async function refreshCurrentConversation() {
    if (!App.currentChatPartner) return;
    await selectContactTab(App.currentChatPartner, { skipReadRefresh: true });
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
                   onclick="selectContactTab('${Security.escapeAttr(u.username)}');document.getElementById('tabSearchUser').value='';document.getElementById('tabUserSearchResults').innerHTML='';">${Security.escapeHtml(u.username)} <i class="bi bi-chat-dots"></i></span>`
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
let selectedWithdrawPaymentMethod = '';

function getConfiguredWithdrawMethods() {
    const methods = getUserPaymentMethods();
    return Object.entries(methods)
        .map(([key, item]) => ({ key, ...item }))
        .filter(item => item.account && item.qrcode);
}

function renderWithdrawPaymentOptions() {
    const box = document.getElementById('withdrawPaymentOptions');
    const help = document.getElementById('withdrawPaymentHelp');
    if (!box) return;
    const methods = getConfiguredWithdrawMethods();
    if (!methods.length) {
        box.innerHTML = `
            <div class="withdraw-payment-empty">
                <i class="bi bi-exclamation-circle"></i>
                <span>未上传收款方式，请在个人中心上传收款方式</span>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="bootstrap.Modal.getInstance(document.getElementById('withdrawModal'))?.hide(); showDashboard('profile')">去上传</button>
            </div>
        `;
        selectedWithdrawPaymentMethod = '';
        if (help) help.textContent = '请先在个人中心上传微信或支付宝收款方式';
        return;
    }
    if (!methods.some(item => item.key === selectedWithdrawPaymentMethod)) {
        selectedWithdrawPaymentMethod = methods[0].key;
    }
    box.innerHTML = methods.map(item => `
        <button type="button" class="withdraw-payment-option ${item.key === selectedWithdrawPaymentMethod ? 'active' : ''}" onclick="selectWithdrawPaymentMethod('${Security.escapeAttr(item.key)}')">
            <i class="bi ${paymentMethodIcon(item.key)}"></i>
            <span>${Security.escapeHtml(item.label)}</span>
            <small>${Security.escapeHtml(item.account)}</small>
        </button>
    `).join('');
    applyWithdrawPaymentMethod();
}

function selectWithdrawPaymentMethod(method) {
    selectedWithdrawPaymentMethod = method;
    renderWithdrawPaymentOptions();
}

function applyWithdrawPaymentMethod() {
    const methods = getUserPaymentMethods();
    const item = methods[selectedWithdrawPaymentMethod];
    const methodInput = document.getElementById('withdrawPaymentMethod');
    const accountInput = document.getElementById('withdrawAccount');
    const qrcodeInput = document.getElementById('withdrawQrcode');
    const help = document.getElementById('withdrawPaymentHelp');
    if (methodInput) methodInput.value = item?.label || '';
    if (accountInput) accountInput.value = item?.account || '';
    if (qrcodeInput) qrcodeInput.value = item?.qrcode || '';
    if (help && item) help.textContent = `将使用个人中心已上传的${item.label}收款方式`;
    applyWithdrawButtonState();
}

async function openWithdrawModal() {
    const userResult = await API.getCurrentUser();
    if (userResult.success && userResult.user) {
        App.setUser(userResult.user);
    }
    const sysConfigResult = await API.getSystemConfig();
    if (sysConfigResult.success) {
        minWithdrawAmount = sysConfigResult.config.min_withdraw_amount || 10;
        withdrawFeeRate = sysConfigResult.config.withdraw_fee_rate || 0.01;
    }
    
    document.getElementById('withdrawAmount').value = '';
    document.getElementById('withdrawPaymentMethod').value = '';
    document.getElementById('withdrawAccount').value = '';
    document.getElementById('withdrawQrcode').value = '';
    document.getElementById('withdrawFeeNote').textContent = `最低 ¥${Number(minWithdrawAmount).toFixed(2)}，手续费 ${(withdrawFeeRate * 100).toFixed(1)}%`;
    const amountInput = document.getElementById('withdrawAmount');
    const amountError = document.getElementById('withdrawAmountError');
    amountInput?.classList.remove('is-invalid');
    if (amountError) {
        amountError.textContent = '';
        amountError.style.setProperty('display', 'none', 'important');
    }
    selectedWithdrawPaymentMethod = '';
    renderWithdrawPaymentOptions();
    applyWithdrawButtonState();
    
    new bootstrap.Modal(document.getElementById('withdrawModal')).show();
}

function applyWithdrawButtonState() {
    const btn = document.getElementById('submitWithdrawBtn');
    if (!btn) return;
    const amount = parseFloat(document.getElementById('withdrawAmount')?.value || '0') || 0;
    const hasMethod = !!document.getElementById('withdrawPaymentMethod')?.value;
    const hasAccount = !!document.getElementById('withdrawAccount')?.value?.trim();
    const hasQrcode = !!document.getElementById('withdrawQrcode')?.value?.trim();
    btn.disabled = !amount || amount < minWithdrawAmount || amount > Number(App.currentUser?.balance || 0) || !hasMethod || !hasAccount || !hasQrcode;
}

function validateWithdrawAmount(showToast = false) {
    const input = document.getElementById('withdrawAmount');
    const error = document.getElementById('withdrawAmountError');
    const amount = parseFloat(input?.value || '0') || 0;
    let message = '';
    if (input && input.value.trim() !== '' && amount < minWithdrawAmount) {
        message = `最低提现金额为 ¥${Number(minWithdrawAmount).toFixed(2)}`;
    } else if (amount > Number(App.currentUser?.balance || 0)) {
        message = '提现金额不能超过当前可用余额';
    }
    if (input) input.classList.toggle('is-invalid', !!message);
    if (error) {
        error.textContent = message;
        error.style.setProperty('display', message ? 'block' : 'none', 'important');
    }
    if (message && showToast) Toast.warning(message);
    applyWithdrawButtonState();
    return !message;
}

function updateWithdrawInfo() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value) || 0;
    const feeNote = document.getElementById('withdrawFeeNote');
    
    if (amount > 0) {
        const fee = amount * withdrawFeeRate;
        const actualAmount = Math.max(0, amount - fee);
        feeNote.textContent = amount < minWithdrawAmount
            ? `最低提现 ¥${Number(minWithdrawAmount).toFixed(2)}，当前输入金额不足`
            : `手续费 ¥${fee.toFixed(2)}，实到 ¥${actualAmount.toFixed(2)}`;
    } else {
        feeNote.textContent = `最低 ¥${Number(minWithdrawAmount).toFixed(2)}，手续费 ${(withdrawFeeRate * 100).toFixed(1)}%`;
    }
    validateWithdrawAmount(false);
}

function fillAllWithdrawAmount() {
    const input = document.getElementById('withdrawAmount');
    if (!input) return;
    const balance = Math.max(0, Number(App.currentUser?.balance || 0));
    if (balance <= 0) {
        Toast.warning('当前没有可提现余额');
        return;
    }
    input.value = balance.toFixed(2);
    updateWithdrawInfo();
}

async function submitWithdraw() {
    const amount = parseFloat(document.getElementById('withdrawAmount').value);
    const paymentMethod = document.getElementById('withdrawPaymentMethod').value;
    const paymentAccount = document.getElementById('withdrawAccount').value.trim();
    const qrcodeUrl = document.getElementById('withdrawQrcode').value.trim();
    const btn = document.getElementById('submitWithdrawBtn');
    
    if (!validateWithdrawAmount(true) || !amount || amount < minWithdrawAmount) {
        return;
    }
    
    if (!paymentMethod || !paymentAccount || !qrcodeUrl) {
        Toast.warning('请先在个人中心完整上传收款账号和收款码');
        return;
    }
    
    if (amount > App.currentUser.balance) {
        Toast.warning('余额不足');
        return;
    }
    
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>提交中...';
    }
    try {
        const result = await API.requestWithdraw(amount, paymentMethod, paymentAccount, qrcodeUrl);
        if (result.success) {
            Toast.success(result.message || '提现申请已提交');
            bootstrap.Modal.getInstance(document.getElementById('withdrawModal'))?.hide();
            if (result.request) {
                App.currentUser.balance = Math.max(0, Number(App.currentUser.balance || 0) - amount);
            }
            App.updateNavUI();
            renderDashboardTab('balance');
        } else {
            Toast.error(result.message || '提现申请提交失败');
        }
    } catch (e) {
        Toast.error('提现申请提交失败，请检查网络或稍后重试');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '提交申请';
        }
    }
}

async function openSystemConfigModal() {
    if (!App.currentUser || App.currentUser.role !== 'admin') {
        Toast.warning('需要管理员权限');
        return;
    }
    const modalEl = document.getElementById('systemConfigModal');
    const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
    if (modal) modal.hide();
    App.currentPage = 'dashboard';
    App.currentTab = 'profile';
    persistFrontendState();
    document.getElementById('homePage')?.classList.add('hidden');
    document.getElementById('dashboardPage')?.classList.remove('hidden');
    if (document.getElementById('dashContentArea')) {
        renderDashboardTab('profile');
    } else {
        showDashboard('profile');
    }
}

async function saveSystemConfig(options = {}) {
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
        if (options && options.embedded) {
            renderDashboardTab('profile');
        } else {
            bootstrap.Modal.getInstance(document.getElementById('systemConfigModal'))?.hide();
            renderDashboardTab('profile');
        }
    } else {
        Toast.error(result.message);
    }
}

let selectedPaymentConfig = null;
let rechargePaymentOptions = [];
let currentPaymentLink = '';
let paymentPollingTimer = null;
let paymentPollingOrderId = '';

function paymentQrImageUrl(paymentResultOrUrl) {
    if (paymentResultOrUrl && typeof paymentResultOrUrl === 'object') {
        const qrContent = String(paymentResultOrUrl.qrcode_content || '').trim();
        if (qrContent) {
            return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' + encodeURIComponent(qrContent);
        }
        const directQr = String(paymentResultOrUrl.qrcode_url || '').trim();
        if (/^https?:\/\//i.test(directQr) || directQr.startsWith('/')) {
            return directQr;
        }
        return '';
    }
    return '';
}

function paymentHasDirectQr(paymentResult = {}) {
    return !!(String(paymentResult.qrcode_content || '').trim() || String(paymentResult.qrcode_url || '').trim());
}

function showPaymentFallback(paymentUrl, message = '当前接口没有返回可直接显示的支付二维码') {
    const imageEl = document.getElementById('qrPaymentImage');
    const iframeWrap = document.getElementById('qrPaymentIframeWrap');
    const iframe = document.getElementById('qrPaymentIframe');
    const fallbackEl = document.getElementById('qrPaymentFallback');
    if (imageEl) {
        imageEl.classList.add('hidden');
        imageEl.removeAttribute('src');
        imageEl.onerror = null;
    }
    if (iframeWrap) iframeWrap.classList.add('hidden');
    if (iframe) iframe.removeAttribute('src');
    if (fallbackEl) {
        fallbackEl.classList.remove('hidden');
        fallbackEl.innerHTML = `
            <div class="qr-payment-fallback-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="fw-bold mb-1">二维码未返回</div>
            <div class="small text-muted mb-3">${Security.escapeHtml(message)}</div>
            <button type="button" class="btn btn-sm btn-primary" onclick="openPaymentPageFallback()">打开支付页面</button>
        `;
    }
    return false;
}

function showEmbeddedPaymentPage(paymentUrl) {
    const imageEl = document.getElementById('qrPaymentImage');
    const iframeWrap = document.getElementById('qrPaymentIframeWrap');
    const iframe = document.getElementById('qrPaymentIframe');
    const fallbackEl = document.getElementById('qrPaymentFallback');
    if (!iframeWrap || !iframe || !paymentUrl) return showPaymentFallback(paymentUrl);
    if (imageEl) {
        imageEl.classList.add('hidden');
        imageEl.removeAttribute('src');
        imageEl.onerror = null;
    }
    if (fallbackEl) fallbackEl.classList.add('hidden');
    iframeWrap.classList.remove('hidden');
    iframe.src = paymentUrl;
    window.setTimeout(() => {
        if (!iframeWrap.classList.contains('hidden')) {
            showPaymentFallback(paymentUrl, '支付页面可能禁止嵌入，已为你保留打开支付页面入口');
        }
    }, 2500);
    return true;
}

function openCurrentPaymentLink() {
    openPaymentPageFallback();
}

function openPaymentPageFallback() {
    if (!currentPaymentLink) {
        Toast.error('支付链接不存在');
        return;
    }
    window.open(currentPaymentLink, '_blank', 'noopener');
}

function stopPaymentPolling() {
    if (paymentPollingTimer) clearInterval(paymentPollingTimer);
    paymentPollingTimer = null;
    paymentPollingOrderId = '';
}

function showQrPaymentModal(paymentResult, options = {}) {
    const order = paymentResult.order || {};
    const paymentUrl = paymentResult.payment_url || '';
    if (!paymentUrl || !order.id) {
        Toast.error('支付链接生成失败');
        return;
    }

    stopPaymentPolling();
    currentPaymentLink = paymentUrl;
    paymentPollingOrderId = order.id;

    const payType = options.payType || order.pay_type || '';
    const amount = Number(order.actual_amount || order.amount || 0);
    const methodEl = document.getElementById('qrPaymentMethod');
    const amountEl = document.getElementById('qrPaymentAmount');
    const imageEl = document.getElementById('qrPaymentImage');
    const iframeWrap = document.getElementById('qrPaymentIframeWrap');
    const iframe = document.getElementById('qrPaymentIframe');
    const fallbackEl = document.getElementById('qrPaymentFallback');
    const statusEl = document.getElementById('qrPaymentStatus');

    if (methodEl) methodEl.textContent = options.methodLabel || payMethodLabel(payType);
    if (amountEl) amountEl.textContent = '¥ ' + amount.toFixed(2);
    const qrImageUrl = paymentQrImageUrl(paymentResult);
    if (iframeWrap) iframeWrap.classList.add('hidden');
    if (iframe) iframe.removeAttribute('src');
    if (fallbackEl) fallbackEl.classList.add('hidden');
    if (imageEl) {
        imageEl.onerror = () => {
            imageEl.onerror = null;
            showPaymentFallback(paymentUrl, '支付接口返回了二维码地址，但图片加载失败');
        };
        if (qrImageUrl && paymentHasDirectQr(paymentResult)) {
            imageEl.classList.remove('hidden');
            imageEl.src = qrImageUrl;
        } else {
            showPaymentFallback(paymentUrl);
        }
    } else {
        showPaymentFallback(paymentUrl);
    }
    if (statusEl) statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>等待扫码支付，支付成功后会自动刷新';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('qrPaymentModal')).show();
    startPaymentPolling(order.id, options);
}

function startPaymentPolling(orderId, options = {}) {
    let attempts = 0;
    paymentPollingTimer = setInterval(async () => {
        attempts += 1;
        const result = await API.getPaymentOrderStatus(orderId, options.guestToken || '');
        if (!result.success || !result.order) return;
        const status = result.order.status;
        const statusEl = document.getElementById('qrPaymentStatus');
        if (status === 'paid') {
            stopPaymentPolling();
            hideModalSafely('qrPaymentModal');
            setTimeout(cleanupBootstrapModalArtifacts, 120);
            if (options.guestOrder) {
                const latestOrder = { ...(findGuestOrder(orderId) || {}), ...result.order, guest_token: options.guestToken || getGuestOrderToken() };
                saveGuestOrder(latestOrder);
                Toast.success(options.successMessage || '支付成功，请保存好订单号');
                setTimeout(() => openGuestOrdersModal(orderId), 280);
                return;
            }
            Toast.success(options.successMessage || '支付成功');
            setTimeout(() => window.location.reload(), 600);
            return;
        }
        if (['failed', 'cancelled', 'unpaid'].includes(status)) {
            stopPaymentPolling();
            if (statusEl) statusEl.innerHTML = '<i class="bi bi-x-circle-fill text-danger me-1"></i>订单已失效，请重新发起支付';
            Toast.error('订单已失效，请重新发起支付');
            return;
        }
        if (attempts >= 120) {
            stopPaymentPolling();
            if (statusEl) statusEl.innerHTML = '长时间未检测到支付，可稍后刷新页面查看结果';
        }
    }, 3000);
}

async function openOnlineRechargeModal() {
    const result = await API.getPaymentConfigs();
    if (!result.success) {
        Toast.error('加载支付方式失败');
        return;
    }

    rechargePaymentOptions = buildRechargePaymentOptions(result.configs || []);
    selectedPaymentConfig = null;

    const amountInput = document.getElementById('rechargeAmount');
    if (amountInput) amountInput.value = '';

    renderRechargePaymentSelect();
    handleRechargePaymentChange();

    bootstrap.Modal.getOrCreateInstance(document.getElementById('onlineRechargeModal')).show();
}

function payMethodLabel(method) {
    return ({ alipay: '支付宝', wxpay: '微信支付', qqpay: 'QQ钱包', cashier: '易支付收银台' })[method] || method;
}

function buildRechargePaymentOptions(configs) {
    return configs.flatMap(config => {
        const methods = config.pay_methods || ['alipay', 'wxpay'];
        return methods.map(method => ({
            value: `${config.id}::${method}`,
            config,
            method,
            label: payMethodLabel(method),
            feeRate: Number(config.fee_rate || 0)
        }));
    });
}

function renderRechargePaymentSelect() {
    const optionsBox = document.getElementById('rechargePayTypeOptions');
    const hiddenInput = document.getElementById('rechargePayType');
    const help = document.getElementById('rechargePayTypeHelp');
    if (!optionsBox || !hiddenInput) return;

    if (!rechargePaymentOptions.length) {
        optionsBox.innerHTML = '<div class="text-muted small">暂无可使用的支付方式</div>';
        hiddenInput.value = '';
        if (help) help.textContent = '请先在后台添加并启用支付接口';
        return;
    }

    hiddenInput.value = rechargePaymentOptions[0].value;
    optionsBox.innerHTML = rechargePaymentOptions.map((option, index) => `
        <button type="button"
                class="recharge-payment-option ${index === 0 ? 'active' : ''}"
                data-value="${Security.escapeAttr(option.value)}"
                onclick="selectRechargePaymentOption('${Security.escapeAttr(option.value)}')">
            <span>${Security.escapeHtml(option.label)}</span>
            <i class="bi bi-check-circle-fill"></i>
        </button>
    `).join('');
}

function selectRechargePaymentOption(value) {
    const hiddenInput = document.getElementById('rechargePayType');
    if (hiddenInput) hiddenInput.value = value;
    document.querySelectorAll('.recharge-payment-option').forEach(option => {
        option.classList.toggle('active', option.dataset.value === value);
    });
    handleRechargePaymentChange();
}

function handleRechargePaymentChange() {
    const hiddenInput = document.getElementById('rechargePayType');
    const help = document.getElementById('rechargePayTypeHelp');
    const selectedValue = hiddenInput?.value || '';
    const option = rechargePaymentOptions.find(item => item.value === selectedValue) || null;

    selectedPaymentConfig = option ? option.config : null;
    updateFeeInfo(option ? option.feeRate : 0);

    if (help) {
        help.textContent = option
            ? `当前选择：${option.label}${option.feeRate > 0 ? `，手续费 ${(option.feeRate * 100).toFixed(1)}%` : ''}`
            : '请选择支付方式';
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
    const selectedValue = document.getElementById('rechargePayType')?.value || '';
    const selectedOption = rechargePaymentOptions.find(item => item.value === selectedValue) || null;
    
    if (!amount || amount <= 0) {
        Toast.warning('请输入有效金额');
        return;
    }
    if (!selectedOption || !selectedOption.config) {
        Toast.warning('请选择支付方式');
        return;
    }
    
    const result = await API.createPaymentOrder(selectedOption.config.id, amount, selectedOption.method);
    if (result.success) {
        bootstrap.Modal.getInstance(document.getElementById('onlineRechargeModal'))?.hide();
        showQrPaymentModal(result, { payType: selectedOption.method, type: 'recharge' });
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
                            · ${c.api_mode === 'mapi_qr' ? '二维码直连' : '跳转页'}
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
    document.getElementById('paymentConfigApiMode').value = 'submit_page';
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
    document.getElementById('paymentConfigApiMode').value = config.api_mode || 'submit_page';
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
    const apiMode = document.getElementById('paymentConfigApiMode')?.value || 'submit_page';
    const enabled = document.getElementById('paymentConfigEnabled').checked;
    
    if (!name || !apiUrl || !partnerId || !key) {
        Toast.warning('请填写完整信息');
        return;
    }
    
    let result;
    if (editingPaymentConfigId) {
        result = await API.updatePaymentConfig(editingPaymentConfigId, {
            name, type, api_url: apiUrl, partner_id: partnerId, key, fee_rate: feeRate, api_mode: apiMode, enabled
        });
    } else {
        result = await API.addPaymentConfig({
            name, type, api_url: apiUrl, partner_id: partnerId, key, fee_rate: feeRate, api_mode: apiMode, enabled
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

let selectedMembershipPaymentConfig = null;
let selectedMembershipPayType = '';
let membershipPaymentConfigs = [];

function selectMembershipPayButton(configId, payType) {
    selectedMembershipPaymentConfig = membershipPaymentConfigs.find(c => c.id === configId) || null;
    selectedMembershipPayType = payType;
    document.querySelectorAll('.membership-payment-select-card').forEach(card => {
        card.classList.remove('border-primary');
        const checkIcon = card.querySelector('.bi-check-circle');
        if (checkIcon) checkIcon.remove();
    });
    const selectedCard = Array.from(document.querySelectorAll('.membership-payment-select-card')).find(card => card.dataset.configId === configId && card.dataset.payType === payType);
    if (selectedCard) {
        selectedCard.classList.add('border-primary');
        const icon = document.createElement('i');
        icon.className = 'bi bi-check-circle text-primary';
        icon.style.fontSize = '1.5rem';
        selectedCard.querySelector('.card-body .d-flex').appendChild(icon);
    }
}

async function upgradeMembership(levelName) {
    const [levelsResult, myLevelResult, configsResult] = await Promise.all([
        API.getMembershipLevels(),
        API.getMyMembership(),
        API.getPaymentConfigs()
    ]);

    if (!levelsResult.success) {
        Toast.error('加载会员信息失败');
        return;
    }

    const level = (levelsResult.levels || {})[levelName];
    if (!level) {
        Toast.error('会员等级不存在');
        return;
    }

    const cost = Number(level.cost || 0);
    const balance = Number(App.currentUser?.balance || 0);
    const canUseBalance = balance >= cost;
    membershipPaymentConfigs = configsResult.success ? (configsResult.configs || []) : [];
    const firstPaymentConfig = membershipPaymentConfigs[0] || null;
    selectedMembershipPaymentConfig = firstPaymentConfig;
    selectedMembershipPayType = firstPaymentConfig ? (firstPaymentConfig.pay_methods || ['alipay', 'wxpay'])[0] : '';
    const paymentButtonItems = [];
    membershipPaymentConfigs.forEach(c => {
        (c.pay_methods || ['alipay', 'wxpay']).forEach(method => {
            paymentButtonItems.push({ config: c, method });
        });
    });
    const paymentOptionsHtml = paymentButtonItems.length === 0
        ? '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i>后台暂未配置可用在线支付接口</div>'
        : `<div class="row g-2 mb-0">
            ${paymentButtonItems.map(item => {
                const c = item.config;
                const method = item.method;
                const selected = selectedMembershipPaymentConfig?.id === c.id && selectedMembershipPayType === method;
                return `<div class="col-6">
                    <div class="card membership-payment-select-card ${selected ? 'border-primary' : ''}" data-config-id="${Security.escapeAttr(c.id)}" data-pay-type="${Security.escapeAttr(method)}" onclick="selectMembershipPayButton('${Security.escapeAttr(c.id)}', '${Security.escapeAttr(method)}')" style="cursor: pointer;">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold mb-1">${payMethodLabel(method)}</h6>
                                    <small class="text-muted">${Number(c.fee_rate || 0) > 0 ? `手续费 ${(Number(c.fee_rate || 0) * 100).toFixed(1)}%` : '在线支付'}</small>
                                </div>
                                ${selected ? '<i class="bi bi-check-circle text-primary" style="font-size: 1.5rem;"></i>' : ''}
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('')}
        </div>`;

    const confirmed = await new Promise(resolve => {
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        document.getElementById('confirmModalTitle').textContent = '开通 ' + levelName + ' 会员';
        document.getElementById('confirmModalBody').innerHTML = `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>重要提醒：</strong>
                <ul class="mb-0 mt-2">
                    <li>升级到 ${Security.escapeHtml(levelName)} 后无法降级</li>
                    <li>升级到 ${Security.escapeHtml(levelName)} 后无法更换为更低等级</li>
                    <li>此操作不可撤销</li>
                </ul>
            </div>
            <div class="card bg-light mb-3">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between"><span>应付金额</span><strong>¥${cost.toFixed(2)}</strong></div>
                    <div class="d-flex justify-content-between text-muted small mt-1"><span>当前余额</span><span>¥${balance.toFixed(2)}</span></div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">选择支付方式</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="membershipPayMethod" id="membershipPayBalance" value="balance" ${canUseBalance ? 'checked' : 'disabled'}>
                    <label class="form-check-label" for="membershipPayBalance">余额支付</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="membershipPayMethod" id="membershipPayOnline" value="online" ${canUseBalance ? '' : 'checked'} ${membershipPaymentConfigs.length === 0 ? 'disabled' : ''}>
                    <label class="form-check-label" for="membershipPayOnline">在线支付</label>
                </div>
            </div>
            <div id="membershipOnlinePaymentBox">${paymentOptionsHtml}</div>
        `;
        const updateOnlineBox = () => {
            const selected = document.querySelector('input[name="membershipPayMethod"]:checked')?.value;
            const box = document.getElementById('membershipOnlinePaymentBox');
            if (box) box.style.display = selected === 'online' ? 'block' : 'none';
        };
        document.querySelectorAll('input[name="membershipPayMethod"]').forEach(input => input.addEventListener('change', updateOnlineBox));
        document.getElementById('confirmModalBtn').textContent = cost === 0 ? '确认开通' : '确认支付';
        document.getElementById('confirmModalBtn').onclick = () => {
            const payMethod = document.querySelector('input[name="membershipPayMethod"]:checked')?.value || 'balance';
            modal.hide();
            resolve(payMethod);
        };
        document.getElementById('confirmModalCancelBtn').onclick = () => {
            modal.hide();
            resolve(false);
        };
        modal.show();
        updateOnlineBox();
    });

    if (!confirmed) return;

    if (confirmed === 'online') {
        if (!selectedMembershipPaymentConfig) {
            Toast.warning('请选择支付接口');
            return;
        }
        const payType = selectedMembershipPayType || (selectedMembershipPaymentConfig.pay_methods || ['alipay'])[0];
        const result = await API.createMembershipPaymentOrder(selectedMembershipPaymentConfig.id, levelName, payType);
        if (result.success) {
            showQrPaymentModal(result, { payType, type: 'membership' });
        } else {
            Toast.error(result.message);
        }
        return;
    }

    const result = await API.request('membership.php?action=upgrade', 'POST', { 
        level: levelName,
        confirmed: 1,
        pay_method: 'balance'
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

function toggleCardCreateType() {
    const type = document.getElementById('cardType')?.value || 'balance';
    document.getElementById('cardAmountWrap')?.classList.toggle('d-none', type !== 'balance');
    document.getElementById('cardMembershipWrap')?.classList.toggle('d-none', type !== 'membership');
}

async function generateCards() {
    const cardType = document.getElementById('cardType')?.value || 'balance';
    const amount = parseFloat(document.getElementById('cardAmount')?.value || '0');
    const targetLevel = document.getElementById('cardTargetLevel')?.value || '';
    const count = parseInt(document.getElementById('cardCount').value);

    if (cardType === 'balance' && (!amount || amount <= 0)) {
        Toast.warning('请输入有效金额');
        return;
    }
    if (cardType === 'membership' && (!targetLevel || targetLevel === 'Free')) {
        Toast.warning('请选择要生成的会员权益，Free 不可生成卡密');
        return;
    }
    if (!count || count < 1 || count > 100) {
        Toast.warning('数量应在1-100之间');
        return;
    }

    const result = await API.createCards(cardType === 'balance' ? amount : 0, count, cardType, targetLevel);
    if (!result.success) {
        Toast.error(result.message);
        return;
    }

    Toast.success(result.message);
    
    const newCardsSection = document.getElementById('newCardsSection');
    const newCardsList = document.getElementById('newCardsList');
    newCardsSection.style.display = 'block';
    newCardsList.innerHTML = result.cards.map(c => {
        const valueText = c.card_type === 'membership' ? `会员：${Security.escapeHtml(c.target_level || '-')}` : `¥${Number(c.amount || 0).toFixed(2)}`;
        return `<div class="d-flex justify-content-between align-items-center py-1">
            <code>${Security.escapeHtml(c.code)}</code>
            <span>${valueText}</span>
            <button class="btn btn-sm btn-outline" data-copy="${Security.escapeAttr(c.code)}">复制</button>
        </div>`;
    }).join('');
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

function openReviewDialog(productId, orderId) {
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <div class="review-modal-head text-center mb-4">
            <div class="review-modal-icon"><i class="bi bi-star-fill"></i></div>
            <h5 class="fw-bold mb-1">评价商品</h5>
            <p class="text-muted small mb-0">请选择评分，评价内容可以不填写</p>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold">商品评分</label>
            <div class="rating-radio-group rating-radio-beauty">
                ${[1,2,3,4,5].map(n => `
                    <label class="rating-radio">
                        <input type="radio" name="reviewRating" value="${n}" ${n === 5 ? 'checked' : ''}>
                        <span><b>${n}星</b><small>${'★'.repeat(n)}</small></span>
                    </label>
                `).join('')}
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label fw-semibold">评价内容</label>
            <textarea class="form-control review-textarea" id="reviewContent" rows="5" maxlength="500" placeholder="可以写，也可以留空，例如：发货很快、账号正常、描述一致"></textarea>
            <div class="d-flex justify-content-between mt-2">
                <small class="text-muted">内容可写可不写</small>
                <small class="text-muted">最多 500 字</small>
            </div>
        </div>
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">取消</button>
        <button class="btn btn-primary" onclick="submitReviewDialog('${Security.escapeAttr(productId)}', '${Security.escapeAttr(orderId)}')">
            <i class="bi bi-send me-1"></i>提交评价
        </button>
    `;
    modal.show();
}

async function submitReviewDialog(productId, orderId) {
    const rating = parseInt(document.querySelector('input[name="reviewRating"]:checked')?.value || '5', 10);
    const content = document.getElementById('reviewContent')?.value?.trim() || '';
    const result = await API.addComment(productId, orderId, rating, content);
    if (result.success) {
        Toast.success('评价成功');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
        if (App.currentPage === 'dashboard') renderDashboardTab('orders');
    } else {
        Toast.error(result.message || '评价失败');
    }
}

function openCommentModal(productId, orderId) {
    openReviewDialog(productId, orderId);
}

async function submitComment(productId, orderId) {
    return submitReviewDialog(productId, orderId);
}

function orderTradeNo(order) {
    return order?.payment_trade_no || order?.id || '-';
}
async function loadComplaintsTab(area) {
    const [ordersResult, salesResult] = await Promise.all([API.getMyOrders(), API.getMySales()]);
    const buyerComplaints = ordersResult.success ? ordersResult.orders.filter(o => o.complaint) : [];
    const sellerComplaints = salesResult.success ? salesResult.orders.filter(o => o.complaint) : [];
    const complaintStatusInfo = complaint => {
        const status = complaint?.status || 'open';
        const map = {
            open: ['warning', '待处理'],
            processing: ['primary', '处理中'],
            following: ['info', '跟进中'],
            resolved: ['success', '卖家胜'],
            rejected: ['danger', '买家胜'],
            withdrawn: ['secondary', '已撤诉']
        };
        return map[status] || ['info', status || '已记录'];
    };
    const isComplaintActive = complaint => complaint && !['resolved', 'rejected', 'withdrawn'].includes(complaint.status || 'open');
    const renderStatus = complaint => {
        if (!complaint) return '<span class="badge badge-secondary">无投诉</span>';
        const [type, text] = complaintStatusInfo(complaint);
        return `<span class="badge badge-${type}">${text}</span>`;
    };
    const renderAdminProgress = complaint => {
        if (!complaint) return '';
        const [type, text] = complaintStatusInfo(complaint);
        const adminReply = complaint.admin_reply || '';
        const statusAt = complaint.admin_status_at || complaint.admin_replied_at || complaint.updated_at;
        if (!adminReply && !complaint.admin_status_by && !complaint.admin_replied_by) return '';
        return `
            <div class="alert alert-${type === 'danger' ? 'danger' : type === 'success' ? 'success' : 'info'} py-2 small mb-3">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <strong><i class="bi bi-headset me-1"></i>平台处理状态：${Security.escapeHtml(text)}</strong>
                    <span class="text-muted">${Utils.formatDate(statusAt)}</span>
                </div>
                ${adminReply ? `<div><strong>平台回复：</strong>${Security.escapeHtml(adminReply)}</div>` : `<div class="text-muted">平台已更新处理状态，请留意后续处理结果。</div>`}
            </div>
        `;
    };
    const renderComplaintMessages = (order) => {
        const complaint = order.complaint || {};
        const messages = Array.isArray(complaint.messages) && complaint.messages.length
            ? complaint.messages
            : [
                complaint.reason ? { role: 'buyer', username: complaint.buyer_name || order.buyer_name || '买家', content: complaint.reason, created_at: complaint.created_at } : null,
                complaint.seller_reply ? { role: 'seller', username: order.seller_name || '卖家', content: complaint.seller_reply, created_at: complaint.seller_replied_at || complaint.updated_at } : null
            ].filter(Boolean);
        if (!messages.length) return '';
        return `<div class="complaint-thread-list compact mb-3">${messages.map(msg => `
            <div class="complaint-thread-item ${msg.role === 'seller' ? 'seller' : 'buyer'}">
                <div class="d-flex justify-content-between gap-2 mb-1">
                    <strong>${msg.role === 'seller' ? '卖家' : '买家'}${msg.username ? '：' + Security.escapeHtml(msg.username) : ''}</strong>
                    <small class="text-muted">${Utils.formatDate(msg.created_at)}</small>
                </div>
                <div>${Security.escapeHtml(msg.content || '')}</div>
            </div>
        `).join('')}</div>`;
    };
    const renderComplaintCard = (order, role) => `
        <div class="complaint-manage-card">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                <div>
                    <div class="fw-bold">${Security.escapeHtml(order.product_title || '-')}</div>
                    <div class="text-muted small">${role === 'buyer' ? '我是买家' : '我是卖家'} · 交易号 ${Security.escapeHtml(orderTradeNo(order))} · 冻结 ¥${Number(order.frozen_amount || 0).toFixed(2)} · ${Utils.formatDate(order.complaint?.created_at || order.purchase_date)}</div>
                </div>
                ${renderStatus(order.complaint)}
            </div>
            ${renderAdminProgress(order.complaint)}
            ${renderComplaintMessages(order)}
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                ${role === 'buyer' && isComplaintActive(order.complaint) ? `<button class="btn btn-sm btn-outline-primary" onclick="openComplaintThreadModal('${Security.escapeAttr(order.id)}', 'buyer')">查看实时情况/继续沟通</button><button class="btn btn-sm btn-warning" onclick="openWithdrawComplaintModal('${Security.escapeAttr(order.id)}')">撤诉</button>` : ''}
                ${role === 'seller' && isComplaintActive(order.complaint) ? `<button class="btn btn-sm btn-primary" onclick="openSellerComplaintModal('${Security.escapeAttr(order.id)}')">查看实时情况/回复</button><button class="btn btn-sm btn-danger" onclick="submitSellerComplaintRefund('${Security.escapeAttr(order.id)}')">同意退款</button>` : ''}
            </div>
        </div>
    `;
    area.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-octagon me-2 text-warning"></i>投诉管理</h5>
            <span class="badge badge-warning">${buyerComplaints.length + sellerComplaints.length} 条</span>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-cart-check me-1"></i>我的投诉</h6>
                    ${buyerComplaints.length === 0 ? '<p class="text-muted small mb-0">暂无你发起的投诉</p>' : buyerComplaints.map(o => renderComplaintCard(o, 'buyer')).join('')}
                </div></div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100"><div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shop me-1"></i>收到的投诉</h6>
                    ${sellerComplaints.length === 0 ? '<p class="text-muted small mb-0">暂无买家投诉</p>' : sellerComplaints.map(o => renderComplaintCard(o, 'seller')).join('')}
                </div></div>
            </div>
        </div>
    `;
}

async function openComplaintThreadModal(orderId, role = 'buyer') {
    const result = await API.getOrder(orderId);
    if (!result.success) {
        Toast.error(result.message || '订单不存在');
        return;
    }
    const order = result.order;
    const complaint = order.complaint || {};
    const messages = Array.isArray(complaint.messages) && complaint.messages.length
        ? complaint.messages
        : [
            complaint.reason ? { role: 'buyer', username: complaint.buyer_name || order.buyer_name || '买家', content: complaint.reason, created_at: complaint.created_at } : null,
            complaint.seller_reply ? { role: 'seller', username: order.seller_name || '卖家', content: complaint.seller_reply, created_at: complaint.seller_replied_at || complaint.updated_at } : null
        ].filter(Boolean);
    const statusInfo = (() => {
        const map = {
            open: ['warning', '待处理'],
            processing: ['primary', '处理中'],
            following: ['info', '跟进中'],
            resolved: ['success', '卖家胜'],
            rejected: ['danger', '买家胜'],
            withdrawn: ['secondary', '已撤诉']
        };
        return map[complaint.status || 'open'] || ['info', complaint.status || '已记录'];
    })();
    const activeComplaint = !['resolved', 'rejected', 'withdrawn'].includes(complaint.status || 'open');
    const adminProgressHtml = (complaint.admin_reply || complaint.admin_status_by || complaint.admin_replied_by) ? `
        <div class="alert alert-info py-2 small mb-3">
            <div class="d-flex justify-content-between gap-2 mb-1">
                <strong><i class="bi bi-headset me-1"></i>平台处理状态：${Security.escapeHtml(statusInfo[1])}</strong>
                <span class="text-muted">${Utils.formatDate(complaint.admin_status_at || complaint.admin_replied_at || complaint.updated_at)}</span>
            </div>
            ${complaint.admin_reply ? `<div><strong>平台回复：</strong>${Security.escapeHtml(complaint.admin_reply)}</div>` : '<div class="text-muted">平台已更新处理状态，请留意后续处理结果。</div>'}
        </div>
    ` : '';
    const messagesHtml = messages.map(msg => `
        <div class="complaint-thread-item ${msg.role === 'seller' ? 'seller' : 'buyer'}">
            <div class="d-flex justify-content-between gap-2 mb-1">
                <strong>${msg.role === 'seller' ? '卖家' : '买家'}${msg.username ? '：' + Security.escapeHtml(msg.username) : ''}</strong>
                <small class="text-muted">${Utils.formatDate(msg.created_at)}</small>
            </div>
            <div>${Security.escapeHtml(msg.content || '')}</div>
        </div>
    `).join('');
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-chat-dots me-1"></i>投诉实时情况</h6>
        <div class="bg-light rounded-3 p-3 mb-3 small">
            <div><strong>商品：</strong>${Security.escapeHtml(order.product_title || '-')}</div>
            <div><strong>冻结金额：</strong>¥${Number(order.frozen_amount || 0).toFixed(2)}</div>
            <div><strong>当前状态：</strong><span class="badge badge-${statusInfo[0]}">${Security.escapeHtml(statusInfo[1])}</span></div>
            <div><strong>最近更新：</strong>${Utils.formatDate(complaint.updated_at || complaint.created_at)}</div>
        </div>
        ${adminProgressHtml}
        <div class="complaint-thread-list mb-3">${messagesHtml || '<div class="text-muted small">暂无沟通记录</div>'}</div>
        ${activeComplaint ? `
            <div class="mb-3">
                <label class="form-label">继续回复</label>
                <textarea class="form-control" id="complaintReplyContent" rows="4" maxlength="500" placeholder="请输入要补充说明的内容"></textarea>
                <small class="text-muted">最多 500 字</small>
            </div>
        ` : '<div class="alert alert-secondary py-2 small mb-0">该投诉已结束，不能继续回复。</div>'}
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>
        ${activeComplaint ? `<button class="btn btn-primary" onclick="submitComplaintReply('${Security.escapeAttr(orderId)}', 'complaints')">提交回复</button>` : ''}
    `;
    modal.show();
}

function openCommentModal(productId, orderId) {
    const modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
    document.getElementById('purchaseBody').innerHTML = `
        <div class="review-modal-head text-center mb-4">
            <div class="review-modal-icon"><i class="bi bi-star-fill"></i></div>
            <h5 class="fw-bold mb-1">评价商品</h5>
            <p class="text-muted small mb-0">请选择评分，评价内容可以不填写</p>
        </div>
        <div class="mb-4">
            <label class="form-label fw-semibold">商品评分</label>
            <div class="rating-radio-group rating-radio-beauty">
                ${[1,2,3,4,5].map(n => `
                    <label class="rating-radio">
                        <input type="radio" name="commentRating" value="${n}" ${n === 5 ? 'checked' : ''}>
                        <span><b>${n}星</b><small>${'★'.repeat(n)}</small></span>
                    </label>
                `).join('')}
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label fw-semibold">评价内容</label>
            <textarea class="form-control review-textarea" id="commentContent" rows="5" maxlength="500" placeholder="可以写，也可以留空，例如：发货很快、账号正常、描述一致"></textarea>
            <div class="d-flex justify-content-between mt-2">
                <small class="text-muted">内容可写可不写</small>
                <small class="text-muted">最多 500 字</small>
            </div>
        </div>
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">取消</button>
        <button class="btn btn-primary" onclick="submitComment('${Security.escapeAttr(productId)}', '${Security.escapeAttr(orderId)}')">
            <i class="bi bi-send me-1"></i>提交评价
        </button>
    `;
    modal.show();
}

async function submitComment(productId, orderId) {
    const rating = parseInt(document.querySelector('input[name="commentRating"]:checked')?.value || '5', 10);
    const content = document.getElementById('commentContent')?.value?.trim() || '';
    const result = await API.addComment(productId, orderId, rating, content);
    if (result.success) {
        Toast.success('评价成功');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
        if (App.currentPage === 'dashboard') renderDashboardTab('orders');
    } else {
        Toast.error(result.message || '评价失败');
    }
}

function openCardRechargeModal(mode = 'balance') {
    const modal = new bootstrap.Modal(document.getElementById('cardRechargeModal'));
    const title = document.getElementById('cardRechargeTitle');
    const submitBtn = document.getElementById('cardRechargeSubmitBtn');
    const label = document.getElementById('cardRechargeLabel');
    const input = document.getElementById('cardRechargeInput');
    if (title) title.innerHTML = mode === 'membership' ? '<i class="bi bi-credit-card-2-front me-2"></i>会员卡密激活' : '<i class="bi bi-credit-card-2-front me-2"></i>卡密充值';
    if (submitBtn) submitBtn.textContent = mode === 'membership' ? '立即激活' : '充值';
    if (label) label.textContent = mode === 'membership' ? '请输入会员卡密' : '请输入卡密';
    if (input) {
        input.value = '';
        input.placeholder = mode === 'membership' ? '输入会员卡密代码' : '输入卡密代码';
    }
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
            renderDashboardTab(result.card_type === 'membership' ? 'membership' : 'balance');
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
