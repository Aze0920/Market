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
            if (userArea) userArea.classList.remove('hidden');
            if (dashboardLink) dashboardLink.classList.remove('hidden');
            if (publishLink) publishLink.classList.remove('hidden');

            document.getElementById('navUsername').textContent = Security.escapeHtml(this.currentUser.username);
            document.getElementById('navAvatar').textContent = Security.escapeHtml(this.currentUser.username.charAt(0).toUpperCase());
            document.getElementById('navBalance').textContent = '¥ ' + this.currentUser.balance.toFixed(2) + (this.currentUser.frozen_balance > 0 ? '（冻结 ¥' + Number(this.currentUser.frozen_balance).toFixed(2) + '）' : '');
            const navAdminBtn = document.getElementById('navAdminBtn');
            if (navAdminBtn) {
                navAdminBtn.classList.toggle('hidden', this.currentUser.role !== 'admin');
            }
        } else {
            if (guestArea) guestArea.classList.remove('hidden');
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
        tab: ['overview', 'orders', 'sales', 'myproducts', 'balance', 'membership', 'cardmanage', 'paymentmanage', 'profile', 'messages', 'reviews', 'complaints'].includes(tab) ? tab : 'overview'
    };
}

function resetMarketFilters() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    if (searchInput) searchInput.value = '';
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

    if (opts.resetFilters !== false) resetMarketFilters();
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
        case 'cardmanage':
            loadCardManageTab(contentArea);
            break;
        case 'paymentmanage':
            loadPaymentManageTab(contentArea);
            break;
        case 'profile':
            loadProfileTab(contentArea);
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
    document.getElementById('dashAvatar').textContent = App.currentUser.username.charAt(0).toUpperCase();
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

    const activeTab = tabName || App.currentTab || 'overview';
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
                            <td>
                                ${Utils.truncate(o.product_title, 20)}
                                ${o.complaint && o.complaint.status === 'open' ? '<div><span class="badge badge-warning">投诉中</span></div>' : ''}
                                ${o.complaint && o.complaint.status === 'withdrawn' ? '<div><span class="badge badge-secondary">已撤诉</span></div>' : ''}
                            </td>
                            <td class="text-danger fw-semibold">¥${o.price.toFixed(2)}</td>
                            <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="viewDeliveryInfo('${o.id}')">查看发货</button>
                                ${o.has_comment ? '<span class="badge badge-success ms-1">已评价</span>' : `<button class="btn btn-sm btn-primary keynest-review-btn" data-product-id="${Security.escapeAttr(o.product_id)}" data-order-id="${Security.escapeAttr(o.id)}" onclick="openReviewDialog('${o.product_id}', '${o.id}')">评价</button>`}
                                ${o.complaint && o.complaint.status === 'open' ? `<button class="btn btn-sm btn-warning" onclick="openWithdrawComplaintModal('${o.id}')">撤诉</button>` : `<button class="btn btn-sm btn-danger" onclick="openComplaintModal('${o.id}')">投诉</button>`}
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
                                ${o.complaint && o.complaint.status === 'open' ? '<div><span class="badge badge-warning">投诉中</span></div>' : ''}
                            </td>
                            <td>${o.buyer_name}</td>
                            <td class="text-success fw-semibold">+¥${o.seller_amount ? o.seller_amount.toFixed(2) : o.price.toFixed(2)}</td>
                            <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                            <td>
                                ${o.complaint && o.complaint.status === 'open' ? `<button class="btn btn-sm btn-warning" onclick="openSellerComplaintModal('${o.id}')">查看投诉</button>` : '<span class="text-muted small">-</span>'}
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
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
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-circle me-1"></i>订单投诉</h6>
        <div class="bg-light rounded-3 p-3 mb-3 small">
            <div><strong>商品：</strong>${Security.escapeHtml(order.product_title || '-')}</div>
            <div><strong>买家：</strong>${Security.escapeHtml(order.buyer_name || '-')}</div>
            <div><strong>冻结金额：</strong>¥${Number(order.frozen_amount || 0).toFixed(2)}</div>
            <div><strong>投诉时间：</strong>${Utils.formatDate(complaint.created_at)}</div>
        </div>
        <div class="mb-3">
            <label class="form-label">买家投诉内容</label>
            <div class="complaint-reason-box">${Security.escapeHtml(complaint.reason || '-')}</div>
        </div>
        <div class="mb-3">
            <label class="form-label">卖家回复</label>
            <textarea class="form-control" id="sellerComplaintReply" rows="4" maxlength="500" placeholder="请填写处理说明或解决方案">${Security.escapeHtml(complaint.seller_reply || '')}</textarea>
            <small class="text-muted">最多 500 字</small>
        </div>
    `;
    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>
        <button class="btn btn-primary" onclick="submitSellerComplaintReply('${Security.escapeAttr(orderId)}')">提交回复</button>
    `;
    modal.show();
}

async function submitSellerComplaintReply(orderId) {
    const reply = document.getElementById('sellerComplaintReply')?.value?.trim() || '';
    const result = await API.replyComplaint(orderId, reply);
    if (result.success) {
        Toast.success(result.message || '回复已提交');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal'))?.hide();
        renderDashboardTab('sales');
    } else {
        Toast.error(result.message || '回复失败');
    }
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
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge badge-primary">${Security.escapeHtml(p.category || '其他')}</span>
                                        <span class="text-muted small"><i class="bi bi-pencil-square me-1"></i>点击编辑</span>
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
                            ${o.status === 'paid' ? '已到账' : o.status === 'pending' ? '处理中' : o.status === 'unpaid' ? '未支付' : '失败'}
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
                ${App.currentUser.frozen_balance > 0 ? `<div class="text-warning fw-semibold mb-1">冻结余额：¥ ${Number(App.currentUser.frozen_balance).toFixed(2)}</div>` : ''}
                <p class="text-muted mb-3">当前可用余额</p>
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
                    <input class="form-check-input" type="checkbox" id="editPickupPasswordEnabled" ${product.pickup_password_enabled ? 'checked' : ''} onchange="toggleEditPickupPasswordInput()">
                    <label class="form-check-label" for="editPickupPasswordEnabled">开启取卡密码</label>
                </div>
                <div id="editPickupPasswordWrap" class="${product.pickup_password_enabled ? '' : 'hidden'}">
                    <label class="form-label">新取卡密码</label>
                    <input type="text" class="form-control" id="editPickupPassword" maxlength="100" placeholder="留空则保留原密码；首次开启必须填写">
                </div>
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

async function saveSellerProduct(productId) {
    const title = document.getElementById('editProductTitle')?.value?.trim() || '';
    const category = document.getElementById('editProductCategory')?.value || '其他';
    const price = parseFloat(document.getElementById('editProductPrice')?.value || '0');
    const description = document.getElementById('editProductDesc')?.value?.trim() || '';
    const image = document.getElementById('editProductImage')?.value?.trim() || '';
    const pickupPasswordEnabled = document.getElementById('editPickupPasswordEnabled')?.checked ? '1' : '0';
    const pickupPassword = document.getElementById('editPickupPassword')?.value?.trim() || '';
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
        pickup_password_enabled: pickupPasswordEnabled,
        pickup_password: pickupPassword
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
    const enabled = document.getElementById('editPickupPasswordEnabled')?.checked;
    const wrap = document.getElementById('editPickupPasswordWrap');
    if (wrap) wrap.classList.toggle('hidden', !enabled);
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

async function loadProfileTab(area) {
    const user = App.currentUser || {};
    const maskedEmail = user.email ? user.email.replace(/^(.{2}).*(@.*)$/, '$1****$2') : '未绑定邮箱';
    const qqBound = !!user.qq_openid;
    const isAdmin = user.role === 'admin';
    let adminConfigHtml = '';
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
                        <div class="avatar" style="width:58px;height:58px;font-size:1.35rem;">${Security.escapeHtml((user.username || 'U').charAt(0).toUpperCase())}</div>
                        <div>
                            <h5 class="fw-bold mb-1">${Security.escapeHtml(user.username || '-')}</h5>
                            <div class="text-muted small">${Security.escapeHtml(maskedEmail)}</div>
                        </div>
                    </div>
                    <div class="profile-info-row"><span>会员等级</span><strong>${Security.escapeHtml(user.membership_level || 'Free')}</strong></div>
                    <div class="profile-info-row"><span>账户余额</span><strong>¥ ${Number(user.balance || 0).toFixed(2)}</strong></div>
                    <div class="profile-info-row"><span>QQ 绑定</span><strong class="${qqBound ? 'text-success' : 'text-muted'}">${qqBound ? Security.escapeHtml(user.qq_nickname || '已绑定') : '未绑定'}</strong></div>
                    <div class="mt-4 d-grid gap-2">
                        ${qqBound ? `<button class="btn btn-outline-danger" onclick="unbindQQAccount()"><i class="bi bi-link-45deg me-1"></i>解绑第三方账号</button>` : `<button class="btn btn-primary" onclick="bindQQAccount()"><i class="bi bi-tencent-qq me-1"></i>绑定第三方账号</button>`}
                        <button class="btn btn-outline-primary" onclick="startOAuthLogin('qq')"><i class="bi bi-tencent-qq me-1"></i>QQ 一键登录测试</button>
                    </div>
                    <div class="text-muted small mt-3">QQ 一键登录需要先绑定当前账号，未绑定的 QQ 会提示先绑定。</div>
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
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>修改密码</h6>
                    <div class="alert alert-light border small mb-3">验证码会发送到当前账号邮箱：<strong>${Security.escapeHtml(maskedEmail)}</strong></div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">邮箱验证码</label>
                            <input class="form-control" id="profileEmailCode" maxlength="6" placeholder="请输入 6 位验证码">
                        </div>
                        <div class="col-md-5 d-flex align-items-end">
                            <button class="btn btn-outline-primary w-100" id="sendProfileEmailCodeBtn" onclick="sendProfileEmailCode()">发送验证码</button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">新密码</label>
                            <input class="form-control" id="profileNewPassword" type="password" placeholder="至少6位，包含字母和数字">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">确认新密码</label>
                            <input class="form-control" id="profileConfirmPassword" type="password" placeholder="再次输入新密码">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="changeProfilePassword()"><i class="bi bi-check2-circle me-1"></i>确认修改密码</button>
                        </div>
                    </div>
                </div>
            </div>
            ${adminConfigHtml}
        </div>
    `;
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
    const result = await API.sendProfileEmailCode();
    if (!result.success) return Toast.error(result.message || '验证码发送失败');
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
async function changeProfilePassword() {
    const code = document.getElementById('profileEmailCode')?.value.trim() || '';
    const pwd = document.getElementById('profileNewPassword')?.value || '';
    const confirm = document.getElementById('profileConfirmPassword')?.value || '';
    if (!code || !pwd || !confirm) return Toast.warning('请填写验证码和新密码');
    const result = await API.changePassword(code, pwd, confirm);
    if (!result.success) return Toast.error(result.message || '修改失败');
    Toast.success(result.message || '密码修改成功');
    document.getElementById('profileEmailCode').value = '';
    document.getElementById('profileNewPassword').value = '';
    document.getElementById('profileConfirmPassword').value = '';
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

function paymentQrImageUrl(paymentUrl) {
    return 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=10&data=' + encodeURIComponent(paymentUrl);
}

function openCurrentPaymentLink() {
    if (currentPaymentLink) window.location.href = currentPaymentLink;
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
    const statusEl = document.getElementById('qrPaymentStatus');

    if (methodEl) methodEl.textContent = options.methodLabel || payMethodLabel(payType);
    if (amountEl) amountEl.textContent = '¥ ' + amount.toFixed(2);
    if (imageEl) imageEl.src = paymentQrImageUrl(paymentUrl);
    if (statusEl) statusEl.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>等待扫码支付，支付成功后会自动刷新';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('qrPaymentModal')).show();
    startPaymentPolling(order.id, options);
}

function startPaymentPolling(orderId, options = {}) {
    let attempts = 0;
    paymentPollingTimer = setInterval(async () => {
        attempts += 1;
        const result = await API.getPaymentOrderStatus(orderId);
        if (!result.success || !result.order) return;
        const status = result.order.status;
        const statusEl = document.getElementById('qrPaymentStatus');
        if (status === 'paid') {
            stopPaymentPolling();
            if (statusEl) statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>' + (options.successMessage || '支付成功，正在刷新...');
            Toast.success(options.successMessage || '支付成功');
            setTimeout(() => window.location.reload(), 800);
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

async function loadComplaintsTab(area) {
    const [ordersResult, salesResult] = await Promise.all([API.getMyOrders(), API.getMySales()]);
    const buyerComplaints = ordersResult.success ? ordersResult.orders.filter(o => o.complaint) : [];
    const sellerComplaints = salesResult.success ? salesResult.orders.filter(o => o.complaint) : [];
    const renderStatus = complaint => {
        if (!complaint) return '<span class="badge badge-secondary">无投诉</span>';
        if (complaint.status === 'open') return '<span class="badge badge-warning">处理中</span>';
        if (complaint.status === 'withdrawn') return '<span class="badge badge-secondary">已撤诉</span>';
        return '<span class="badge badge-info">已记录</span>';
    };
    const renderComplaintCard = (order, role) => `
        <div class="complaint-manage-card">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                <div>
                    <div class="fw-bold">${Security.escapeHtml(order.product_title || '-')}</div>
                    <div class="text-muted small">${role === 'buyer' ? '我是买家' : '我是卖家'} · 订单 ${Security.escapeHtml(order.id || '-')} · ${Utils.formatDate(order.complaint?.created_at || order.purchase_date)}</div>
                </div>
                ${renderStatus(order.complaint)}
            </div>
            <div class="complaint-reason-box mb-3">${Security.escapeHtml(order.complaint?.reason || '未填写投诉原因')}</div>
            ${order.complaint?.seller_reply ? `<div class="alert alert-info py-2 small mb-3"><strong>卖家回复：</strong>${Security.escapeHtml(order.complaint.seller_reply)}</div>` : ''}
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                ${role === 'buyer' && order.complaint?.status === 'open' ? `<button class="btn btn-sm btn-warning" onclick="openWithdrawComplaintModal('${Security.escapeAttr(order.id)}')">撤诉</button>` : ''}
                ${role === 'seller' && order.complaint?.status === 'open' ? `<button class="btn btn-sm btn-primary" onclick="openSellerComplaintModal('${Security.escapeAttr(order.id)}')">查看并回复</button>` : ''}
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
