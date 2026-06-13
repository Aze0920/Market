// 安全的 Markdown 渲染：先转义 HTML，再支持常用 Markdown 语法
function renderMarkdown(markdown) {
    let html = Security.escapeHtml(markdown || '');
    html = html.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
    html = html.replace(/^### (.*)$/gm, '<h3>$1</h3>').replace(/^## (.*)$/gm, '<h2>$1</h2>').replace(/^# (.*)$/gm, '<h1>$1</h1>');
    html = html.replace(/^> (.*)$/gm, '<blockquote>$1</blockquote>').replace(/^---$/gm, '<hr>');
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\*(.*?)\*/g, '<em>$1</em>').replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<img src="$2" alt="$1">').replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
    html = html.replace(/^(?:- |\* )(.*)$/gm, '<li>$1</li>').replace(/(<li>[\s\S]*?<\/li>)/g, '<ul>$1</ul>').replace(/<\/ul>\s*<ul>/g, '');
    return html.split(/\n{2,}/).map(part => /<\/?(h\d|ul|li|pre|blockquote|hr|img)/.test(part) ? part.replace(/\n/g, '<br>') : `<p>${part.replace(/\n/g, '<br>')}</p>`).join('');
}

/**
 * 检测二级商铺用户并设置阻止标志（用于发布商品等受限功能）
 * 直接调用API检测，不依赖其他模块
 */
window.checkAndBlockSubShopUser = async function() {
    // 如果已经检测过，直接返回缓存结果
    if (window.__subShopCheckDone === true) {
        return;
    }
    try {
        var result = await window.API.request('subdomain.php?action=my', 'GET', {});
        if (result.success && result.subdomain && result.subdomain.prefix) {
            window.__subShopBlocked = true;
        }
    } catch (e) {
        console.warn('Sub-shop check failed:', e);
    }
    window.__subShopCheckDone = true;
};

function plainTextSummary(markdown, maxLength = 80) {
    const text = (markdown || '').replace(/```[\s\S]*?```/g, ' ').replace(/!\[[^\]]*\]\([^)]*\)/g, ' ').replace(/\[([^\]]+)\]\([^)]*\)/g, '$1').replace(/[#>*_`\-]/g, ' ').replace(/\s+/g, ' ').trim();
    return text.length > maxLength ? text.slice(0, maxLength) + '...' : text;
}

function deliveryItemLineText(item) {
    if (!item || typeof item !== 'object') return '';
    if (item.content) return String(item.content).trim();
    return [item.email, item.password, item.client_id, item.fresh_token]
        .filter(value => value && value !== 'N/A')
        .map(value => String(value).trim())
        .join('----');
}

function deliveryInfoExportText(d) {
    const items = Array.isArray(d?.items) ? d.items : (d ? [d] : []);
    return items.map(deliveryItemLineText).filter(Boolean).join('\n');
}

function safeTxtFileName(name) {
    const safe = String(name || '订单').replace(/[\\/:*?"<>|]/g, '_').trim();
    return (safe || '订单') + '.txt';
}

function downloadTextFile(fileName, text) {
    if (!text) return Toast.warning('暂无可导出的卡密');
    const blob = new Blob(['\ufeff' + text], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = safeTxtFileName(fileName);
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
    Toast.success('已导出TXT文件');
}

function exportDeliveryInfoTxt(orderId, d) {
    downloadTextFile(orderId, deliveryInfoExportText(d));
}

function deliveryInfoHtml(d) {
    const text = deliveryInfoExportText(d);
    if (!text) return '<div class="text-muted small">暂无发货信息</div>';
    const rows = text.split('\n').length;
    return `
        <textarea class="form-control delivery-plain" readonly rows="${Math.min(Math.max(rows, 6), 16)}" style="resize:vertical;white-space:pre;word-break:normal;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">${Security.escapeHtml(text)}</textarea>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-sm btn-outline-primary" data-copy="${Security.escapeAttr(text)}"><i class="bi bi-clipboard me-1"></i>复制全部卡密</button>
        </div>
    `;
}

function resolveProductImageUrl(image) {
    const value = String(image || '').trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value)) return value;
    if (/^\/uploads\/products\/[a-zA-Z0-9_.-]+\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(value)) {
        return value;
    }
    return '';
}

function productImageHtml(image, className = '') {
    const url = resolveProductImageUrl(image);
    if (url) {
        return `<img class="product-custom-img ${className}" src="${Security.escapeAttr(url)}" alt="商品图片" loading="lazy" onerror="this.remove(); this.parentElement.classList.add('image-error'); this.parentElement.innerHTML='<i class=&quot;bi bi-image&quot;></i><span>商品图片暂时无法访问</span>';">`;
    }
    return Security.escapeHtml(String(image || '').trim() || '📦');
}

function normalizeBiIconClass(icon, fallback = 'bi-person') {
    const value = String(icon || '').trim();
    if (!value) return fallback;
    const normalized = value.startsWith('bi-') ? value : `bi-${value.replace(/^bi\s+/, '')}`;
    return /^bi(-[a-z0-9-]+)+$/.test(normalized) ? normalized : fallback;
}

function renderGradientBadge(text, icon, gradient, extraClass = '') {
    const safeGradient = String(gradient || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)').replace(/"/g, '');
    const safeIcon = normalizeBiIconClass(icon);
    const safeText = Security.escapeHtml(text || '');
    const safeClass = Security.escapeAttr(extraClass || '');
    return `<span class="seller-level-badge ${safeClass}" style="background:${safeGradient};"><i class="bi ${Security.escapeAttr(safeIcon)}"></i>${safeText}</span>`;
}

function sellerMembershipBadge(product = {}) {
    const isAdmin = product.seller_role === 'admin' || product.seller_is_admin === true || product.seller_is_admin === 1 || product.seller_is_admin === '1';
    const badges = [];
    badges.push(renderGradientBadge(
        product.seller_badge_text || (isAdmin ? '管理员' : (product.seller_membership_level || 'Free')),
        product.seller_badge_icon || (isAdmin ? 'bi-shield-fill-check' : 'bi-person'),
        product.seller_badge_gradient || (isAdmin ? 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)' : 'linear-gradient(135deg, #6c757d 0%, #495057 100%)'),
        isAdmin ? 'admin-badge' : ''
    ));
    const custom = product.seller_custom_label;
    if (custom && custom.text) {
        badges.push(renderGradientBadge(custom.text, custom.icon || 'bi-tag', custom.gradient || product.seller_badge_gradient, 'custom-label-badge'));
    }
    return badges.join('');
}

const SUBDOMAIN_UNAVAILABLE_META = {
    not_found: { title: '域名未分配', icon: 'bi-globe2', tone: 'info' },
    pending: { title: '域名审核中', icon: 'bi-hourglass-split', tone: 'warning' },
    expired: { title: '域名已过期', icon: 'bi-clock-history', tone: 'warning' },
    disabled: { title: '域名已禁用', icon: 'bi-slash-circle', tone: 'danger' },
    rejected: { title: '域名未通过', icon: 'bi-x-octagon', tone: 'danger' },
    inactive: { title: '域名不可用', icon: 'bi-exclamation-triangle', tone: 'warning' }
};

function getSubdomainUnavailableState(result) {
    const apiState = result?.subdomain_state;
    if (apiState?.blocked) {
        return apiState;
    }
    const store = window.SellerStore || {};
    if (store.prefix && !store.active) {
        return {
            blocked: true,
            prefix: store.prefix,
            full_domain: store.fullDomain || window.location.hostname,
            reason: store.reason || (store.pending ? 'pending' : (store.expired ? 'expired' : (store.disabled ? 'disabled' : 'inactive'))),
            message: store.message || ''
        };
    }
    return null;
}

function getSubdomainDisplayMessage(state) {
    const reason = state?.reason || 'not_found';
    const messages = {
        not_found: '当前域名未分配，请联系管理员开通后再访问。',
        pending: '该店铺域名正在审核中，请稍后再访问。',
        expired: '该店铺域名已过期，请联系卖家或管理员续费。',
        disabled: '该店铺域名已被禁用，暂时无法访问。',
        rejected: '该店铺域名申请未通过，请联系管理员处理。',
        inactive: '当前域名暂不可用，请联系管理员。'
    };
    return messages[reason] || messages.inactive;
}

function setSubdomainUnavailablePageMode(enabled) {
    document.body.classList.toggle('subdomain-unavailable-page', !!enabled);
}

function renderSubdomainUnavailableState(state) {
    const reason = state?.reason || 'not_found';
    const meta = SUBDOMAIN_UNAVAILABLE_META[reason] || SUBDOMAIN_UNAVAILABLE_META.inactive;
    const domain = state?.full_domain || window.location.hostname;
    const message = getSubdomainDisplayMessage(state);
    return `
        <div class="subdomain-unavailable-wrap">
            <div class="subdomain-unavailable-card tone-${Security.escapeHtml(meta.tone)}">
                <div class="subdomain-unavailable-icon">
                    <i class="bi ${Security.escapeHtml(meta.icon)}"></i>
                </div>
                <div class="subdomain-unavailable-badge">${Security.escapeHtml(meta.title)}</div>
                <div class="subdomain-unavailable-domain">${Security.escapeHtml(domain)}</div>
                <p class="subdomain-unavailable-message">${Security.escapeHtml(message)}</p>
            </div>
        </div>
    `;
}

function showSubdomainUnavailableState(state) {
    const grid = document.getElementById('productGrid');
    const emptyState = document.getElementById('emptyProductState');
    if (!grid || !emptyState || !state) return false;
    grid.innerHTML = '';
    emptyState.classList.remove('hidden');
    emptyState.innerHTML = renderSubdomainUnavailableState(state);
    setSubdomainUnavailablePageMode(true);
    return true;
}

async function loadProducts(options = {}) {
    const grid = document.getElementById('productGrid');
    const emptyState = document.getElementById('emptyProductState');
    if (!grid || !emptyState) return;

    const forceAll = !!options.forceAll;
    const search = forceAll ? '' : (document.getElementById('searchInput')?.value?.trim() || '');
    const category = forceAll ? 'all' : (document.getElementById('categoryFilter')?.value || 'all');
    const sort = forceAll ? 'default' : (document.getElementById('sortFilter')?.value || 'default');

    grid.innerHTML = '<div class="col-12"><div class="loading"><div class="spinner"></div></div></div>';
    emptyState.classList.add('hidden');

    const filters = { search, category, sort };
    const store = window.SellerStore || {};
    if (store.active && store.sellerId) {
        filters.seller_id = store.sellerId;
    }
    const result = await API.getProducts(filters);

    const unavailableState = getSubdomainUnavailableState(result);
    if (unavailableState && showSubdomainUnavailableState(unavailableState)) {
        return;
    }
    setSubdomainUnavailablePageMode(false);

    if (!result.success || result.products.length === 0) {
        grid.innerHTML = '';
        emptyState.classList.remove('hidden');
        if (window.SellerStore?.active) {
            emptyState.innerHTML = '<div class="text-center py-5"><p class="mb-0">该卖家暂无在售商品</p></div>';
        }
        return;
    }

    emptyState.classList.add('hidden');
    grid.innerHTML = result.products.map(p => `
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="product-card position-relative" onclick="openProductDetail('${Security.escapeHtml(p.id)}')">
                ${p.stock > 3 ?
                    `<span class="stock-badge in-stock">库存: ${Security.escapeHtml(p.stock)}</span>` :
                    `<span class="stock-badge low-stock">库存: ${Security.escapeHtml(p.stock)}</span>`
                }
                <div class="product-image">${productImageHtml(p.image)}</div>
                <div class="product-body">
                    <span class="category-tag">${Security.escapeHtml(p.category)}</span>
                    <h6 class="fw-bold">${Security.escapeHtml(p.title)}</h6>
                    <p class="text-muted small">${Security.escapeHtml(plainTextSummary(p.description || ''))}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="price">¥${Security.escapeHtml(p.price.toFixed(2))}</span>
                        <span class="text-muted small">已售 ${Security.escapeHtml(p.sales)}</span>
                    </div>
                    <div class="product-rating-line mt-2">
                        <span class="text-success small">好评 ${Security.escapeHtml(p.rating_good || 0)}</span>
                        <span class="text-danger small ms-2">差评 ${Security.escapeHtml(p.rating_bad || 0)}</span>
                    </div>
                    <div class="seller-line mt-2">
                        <span class="text-muted small">卖家: ${Security.escapeHtml(p.seller_name)}</span>
                        ${sellerMembershipBadge(p)}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function filterProducts() {
    loadProducts({ forceAll: false });
}

async function openProductDetail(id, options = {}) {
    const result = await API.getProduct(id);
    if (!result.success) {
        Toast.error('商品不存在');
        return;
    }

    const product = result.product;
    const comments = result.comments || [];
    const stats = product.rating_stats || { good: 0, bad: 0, total: comments.length };

    App.currentDetailProduct = product;

    const modal = new bootstrap.Modal(document.getElementById('productDetailModal'));
    document.getElementById('detailTitle').textContent = Security.escapeHtml(product.title);

    document.getElementById('detailBody').innerHTML = `
        <div class="row">
            <div class="col-md-5 text-center">
                <div class="product-detail-image">
                    ${productImageHtml(product.image, 'detail')}
                </div>
            </div>
            <div class="col-md-7">
                <span class="category-tag mb-2">${Security.escapeHtml(product.category)}</span>
                <h4 class="fw-bold">${Security.escapeHtml(product.title)}</h4>
                <div class="product-description markdown-content mb-3">${renderMarkdown(product.description || '暂无描述')}</div>
                <h3 class="text-danger fw-bold">¥${Security.escapeHtml(product.price.toFixed(2))}</h3>
                <p><small>库存: <strong>${Security.escapeHtml(product.stock)}</strong> | 已售: <strong>${Security.escapeHtml(product.sales)}</strong> | 卖家: <strong>${Security.escapeHtml(product.seller_name)}</strong></small></p>
                <div class="mb-2">${sellerMembershipBadge(product)}</div>
                <p class="small mb-2"><span class="text-success">好评 ${Security.escapeHtml(stats.good || 0)}</span><span class="text-danger ms-3">差评 ${Security.escapeHtml(stats.bad || 0)}</span></p>
                ${(!options.readonly && Number(product.stock || 0) > 0 && (!App.currentUser || App.currentUser.id !== product.seller_id)) ? `
                    <div class="purchase-quantity-box mb-3">
                        <label class="form-label">购买数量</label>
                        <input type="number" id="buyQuantity" class="form-control" min="1" max="${Security.escapeAttr(product.stock)}" value="1" oninput="updatePurchaseQuantityTotal()">
                        <small class="text-muted" id="buyQuantityTotal">合计：¥${Security.escapeHtml(product.price.toFixed(2))}</small>
                    </div>
                ` : ''}
                <hr>
                <h6 class="fw-bold">💬 买家评价</h6>
                <div style="max-height: 150px; overflow-y: auto;">
                    ${comments.length === 0 ?
                        '<p class="text-muted small">暂无评论</p>' :
                        comments.map(c => `
                            <div class="border-bottom pb-2 mb-2 small">
                                <strong>${Security.escapeHtml(c.username)}</strong>
                                <span class="text-warning">${'⭐'.repeat(Security.escapeHtml(c.rating))}</span>
                                <span class="text-muted ms-2">${Utils.formatDate(c.created_at)}</span>
                                <p class="mb-0 mt-1">${Security.escapeHtml(c.content)}</p>
                            </div>`
                        ).join('')
                    }
                </div>
            </div>
        </div>
    `;

    // 控制购买按钮显示
    const buyBtn = document.getElementById('btnBuyNow');
    if (options.readonly || Number(product.stock || 0) <= 0 || (App.currentUser && App.currentUser.id === product.seller_id)) {
        buyBtn.classList.add('hidden');
    } else {
        buyBtn.classList.remove('hidden');
        buyBtn.disabled = false;
        buyBtn.innerHTML = '<i class="bi bi-cart-plus me-1"></i>立即购买';
        if (!App.currentUser) {
            const sysConfigResult = await API.getSystemConfig();
            const allowGuestPurchase = sysConfigResult.success ? (sysConfigResult.config?.allow_guest_purchase !== false && sysConfigResult.config?.allow_guest_purchase !== '0') : true;
            if (!allowGuestPurchase) {
                buyBtn.disabled = true;
                buyBtn.classList.add('disabled');
                buyBtn.innerHTML = '<i class="bi bi-lock me-1"></i>请先登录购买';
                buyBtn.title = '后台已关闭游客购买，请登录账号后购买';
            } else {
                buyBtn.classList.remove('disabled');
                buyBtn.removeAttribute('title');
            }
        } else {
            buyBtn.classList.remove('disabled');
            buyBtn.removeAttribute('title');
        }
    }

    modal.show();
}

function updatePurchaseQuantityTotal() {
    const input = document.getElementById('buyQuantity');
    const total = document.getElementById('buyQuantityTotal');
    if (!input || !total || !App.currentDetailProduct) return;
    const max = Math.max(1, Number(App.currentDetailProduct.stock || 1));
    let quantity = Math.max(1, Math.min(max, parseInt(input.value || '1', 10)));
    input.value = quantity;
    total.textContent = `合计：¥${(Number(App.currentDetailProduct.price || 0) * quantity).toFixed(2)}`;
}

let selectedPurchasePaymentValue = 'balance';
let purchasePaymentOptions = [];
let buyNowOpening = false;
let confirmPurchasePending = false;

function purchaseMethodLabel(type) {
    const labels = { balance: '余额', alipay: '支付宝', wxpay: '微信', qqpay: 'QQ钱包', cashier: '聚合收银台' };
    return labels[type] || type;
}

function purchaseMethodIcon(type) {
    const icons = { balance: 'bi-wallet2', alipay: 'bi-alipay', wxpay: 'bi-wechat', qqpay: 'bi-chat-dots', cashier: 'bi-credit-card-2-front' };
    return icons[type] || 'bi-credit-card';
}

function selectPurchasePaymentOption(value) {
    const option = purchasePaymentOptions.find(item => item.value === value);
    if (!option || option.disabled) return;
    selectedPurchasePaymentValue = value;
    document.querySelectorAll('.purchase-payment-option').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.value === value);
    });
}

async function handleBuyNow() {
    if (buyNowOpening) return;
    buyNowOpening = true;
    const buyBtn = document.getElementById('btnBuyNow');
    const oldBuyBtnHtml = buyBtn?.innerHTML;
    if (buyBtn) {
        buyBtn.disabled = true;
        buyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>处理中...';
    }
    try {
        if (!App.currentDetailProduct) return;

        if (App.currentDetailProduct.stock <= 0) {
            Toast.error('商品已售罄');
            return;
        }

        if (App.currentUser && App.currentUser.id === App.currentDetailProduct.seller_id) {
            Toast.warning('不能购买自己的商品');
            return;
        }

        if (!App.currentUser) {
            const sysConfigResult = await API.getSystemConfig();
            const allowGuestPurchase = sysConfigResult.success ? (sysConfigResult.config?.allow_guest_purchase !== false && sysConfigResult.config?.allow_guest_purchase !== '0') : true;
            if (!allowGuestPurchase) {
                Toast.warning('当前已关闭游客购买，请登录账号后再购买');
                return;
            }
        }

        const quantityInput = document.getElementById('buyQuantity');
        const quantity = Math.max(1, Math.min(App.currentDetailProduct.stock, parseInt(quantityInput?.value || '1', 10)));
        const totalPrice = App.currentDetailProduct.price * quantity;

        const balanceEnough = App.currentUser && Number(App.currentUser.balance || 0) >= totalPrice;
        const configsResult = await API.getPaymentConfigs();
        const onlineOptions = [];
        if (configsResult.success && Array.isArray(configsResult.configs)) {
            configsResult.configs
                .filter(config => config.enabled)
                .forEach(config => {
                    (config.pay_methods || ['alipay', 'wxpay']).forEach(method => {
                        const value = `${config.id}|${method}`;
                        if (!onlineOptions.some(item => item.value === value)) {
                            onlineOptions.push({
                                value,
                                type: method,
                                configId: config.id,
                                payType: method,
                                label: purchaseMethodLabel(method),
                                desc: config.name || '在线支付',
                                disabled: false
                            });
                        }
                    });
                });
        }
        purchasePaymentOptions = [
            ...(App.currentUser ? [{
                value: 'balance',
                type: 'balance',
                label: '余额',
                desc: balanceEnough ? `可用 ¥${Number(App.currentUser.balance || 0).toFixed(2)}` : `余额不足 ¥${Number(App.currentUser.balance || 0).toFixed(2)}`,
                disabled: !balanceEnough
            }] : []),
            ...onlineOptions
        ];
        selectedPurchasePaymentValue = purchasePaymentOptions.find(item => !item.disabled)?.value || '';

        document.getElementById('purchaseBody').innerHTML = `
        <div class="text-center mb-3">
            <i class="bi bi-cart-check-fill text-success" style="font-size: 3rem;"></i>
            <h5 class="fw-bold mt-2">确认购买</h5>
        </div>
        <div class="card bg-light">
            <div class="card-body">
                <h6 class="fw-bold">${Security.escapeHtml(App.currentDetailProduct.title)}</h6>
                <p class="text-muted small mb-1">单价：¥${Security.escapeHtml(App.currentDetailProduct.price.toFixed(2))} × ${Security.escapeHtml(quantity)}</p>
                <p class="text-danger fs-5 fw-bold mb-1">¥${Security.escapeHtml(totalPrice.toFixed(2))}</p>
                ${App.currentUser ? `<p class="text-muted small mb-0">当前余额: ¥${Security.escapeHtml(App.currentUser.balance.toFixed(2))}</p>` : `
                    <div class="text-danger small fw-semibold">
                        <p class="mb-1">游客购买仅支持在线支付，支付后请保存订单号且无法维权</p>
                        <p class="mb-1">游客购买仅支持在线支付，支付后请保存订单号且无法维权</p>
                        <p class="mb-0">游客购买仅支持在线支付，支付后请保存订单号且无法维权；提交支付前还会再次确认三次</p>
                    </div>
                `}
            </div>
        </div>
        ${App.currentDetailProduct.pickup_password_enabled ? `
            <div class="mt-3">
                <label class="form-label fw-semibold">设置取卡密码</label>
                <input type="password" class="form-control" id="buyerPickupPassword" maxlength="100" placeholder="请输入取卡密码，后续查看发货需要使用">
                <small class="text-muted">这个密码由买家自己设置，购买成功后会立即显示卡密；之后在购买记录查看发货需要输入此密码。</small>
            </div>
        ` : ''}
        ${!App.currentUser ? `
            <div class="mt-3 p-3 rounded-4" style="border:2px solid #f97316;background:linear-gradient(135deg,#fff7ed,#ffffff);box-shadow:0 10px 24px rgba(249,115,22,.12);">
                <label class="form-label fw-bold text-danger"><i class="bi bi-envelope-exclamation me-1"></i>游客购买必须填写真实邮箱</label>
                <input type="email" class="form-control" id="guestPurchaseEmail" maxlength="190" placeholder="请输入你能正常接收邮件的邮箱">
                <div class="small mt-2" style="color:#9a3412;font-weight:700;line-height:1.7;">
                    查询码会发送到该邮箱。换设备、清缓存后只能通过“邮箱 + 查询码”找回卡密；邮箱填错将无法找回订单卡密。
                </div>
            </div>
        ` : ''}
        <div class="mt-3">
            <div class="form-label fw-semibold mb-2">选择支付方式</div>
            <div class="purchase-payment-options">
                ${purchasePaymentOptions.length ? purchasePaymentOptions.map(option => `
                    <button type="button" class="purchase-payment-option ${option.value === selectedPurchasePaymentValue ? 'active' : ''} ${option.disabled ? 'disabled' : ''}" data-value="${Security.escapeAttr(option.value)}" onclick="selectPurchasePaymentOption('${Security.escapeAttr(option.value)}')" ${option.disabled ? 'disabled' : ''}>
                        <i class="bi ${purchaseMethodIcon(option.type)}"></i>
                        <span>${Security.escapeHtml(option.label)}</span>
                        <small>${Security.escapeHtml(option.desc || '')}</small>
                    </button>
                `).join('') : '<div class="alert alert-warning small mb-0">暂无可用支付方式</div>'}
            </div>
        </div>
    `;

        document.getElementById('purchaseFooter').innerHTML = `
            <button class="btn btn-outline" data-bs-dismiss="modal">取消</button>
            <button class="btn btn-primary" id="btnConfirmPurchase" onclick="confirmPurchase(${quantity})" ${selectedPurchasePaymentValue ? '' : 'disabled'}>确认购买</button>
        `;

        showModalSafely('purchaseConfirmModal', { hide: ['productDetailModal'] });
    } finally {
        buyNowOpening = false;
        if (buyBtn) {
            buyBtn.disabled = false;
            buyBtn.innerHTML = oldBuyBtnHtml;
        }
        setTimeout(cleanupBootstrapModalArtifacts, 260);
    }
}

async function confirmGuestPurchaseRisk() {
    if (App.currentUser) return true;
    const message = '游客购买仅支持在线支付，支付后请保存订单号且无法维权，是否继续';
    const total = 3;

    return new Promise(resolve => {
        let step = 1;
        const overlay = document.createElement('div');
        overlay.className = 'guest-risk-confirm-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:3000;background:rgba(15,23,42,.56);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:18px;';

        const render = () => {
            overlay.innerHTML = `
                <div class="guest-risk-confirm-card" style="width:min(440px,100%);background:#fff;border-radius:24px;box-shadow:0 24px 80px rgba(15,23,42,.28);overflow:hidden;border:1px solid rgba(148,163,184,.25);animation:guestRiskPop .18s ease-out;">
                    <div style="padding:24px 24px 18px;background:linear-gradient(135deg,#fff7ed 0%,#fff 55%,#eef2ff 100%);">
                        <div style="display:flex;gap:14px;align-items:flex-start;">
                            <div style="width:48px;height:48px;border-radius:16px;background:linear-gradient(135deg,#f97316,#ef4444);color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 12px 28px rgba(239,68,68,.28);flex:none;">
                                <i class="bi bi-shield-exclamation" style="font-size:24px;"></i>
                            </div>
                            <div style="min-width:0;">
                                <div style="font-size:18px;font-weight:800;color:#0f172a;line-height:1.25;">游客购买风险确认</div>
                                <div style="font-size:13px;color:#64748b;margin-top:5px;">请仔细阅读，第 ${step} / ${total} 次确认</div>
                            </div>
                        </div>
                        <div style="height:8px;background:#e2e8f0;border-radius:999px;margin-top:20px;overflow:hidden;">
                            <div style="height:100%;width:${(step / total) * 100}%;background:linear-gradient(90deg,#f97316,#ef4444);border-radius:999px;transition:width .2s ease;"></div>
                        </div>
                    </div>
                    <div style="padding:0 24px 22px;">
                        <div style="border:1px solid #fed7aa;background:#fff7ed;color:#9a3412;border-radius:16px;padding:14px 16px;font-size:14px;line-height:1.7;font-weight:650;">
                            ${Security.escapeHtml(message)}
                        </div>
                        <div style="margin-top:12px;color:#64748b;font-size:13px;line-height:1.6;">
                            游客订单无法在账号中心维权，请务必在支付完成后保存订单号，后续只能通过“查询订单”入口查看。
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;padding:16px 24px 22px;border-top:1px solid #f1f5f9;background:#fff;">
                        <button type="button" class="btn btn-outline" id="guestRiskCancelBtn">取消购买</button>
                        <button type="button" class="btn btn-primary" id="guestRiskContinueBtn">${step >= total ? '我已确认，继续支付' : '继续确认'}</button>
                    </div>
                </div>
            `;
            const cancel = overlay.querySelector('#guestRiskCancelBtn');
            const next = overlay.querySelector('#guestRiskContinueBtn');
            cancel.onclick = () => {
                overlay.remove();
                Toast.info('已取消游客购买');
                resolve(false);
            };
            next.onclick = () => {
                if (step >= total) {
                    overlay.remove();
                    resolve(true);
                    return;
                }
                step += 1;
                render();
            };
        };

        if (!document.getElementById('guestRiskConfirmStyle')) {
            const style = document.createElement('style');
            style.id = 'guestRiskConfirmStyle';
            style.textContent = '@keyframes guestRiskPop{from{opacity:0;transform:translateY(12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}';
            document.head.appendChild(style);
        }
        render();
        document.body.appendChild(overlay);
    });
}

async function confirmPurchase(quantity = 1) {
    if (confirmPurchasePending) return;
    if (!App.currentDetailProduct) return;
    confirmPurchasePending = true;
    const confirmBtn = document.getElementById('btnConfirmPurchase');
    const oldConfirmHtml = confirmBtn?.innerHTML;
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>处理中...';
    }

    try {
        const selectedOption = purchasePaymentOptions.find(item => item.value === selectedPurchasePaymentValue);
        const pickupPassword = document.getElementById('buyerPickupPassword')?.value?.trim() || '';
        if (App.currentDetailProduct.pickup_password_enabled && !pickupPassword) {
            Toast.warning('请设置取卡密码，后续查看发货需要使用');
            document.getElementById('buyerPickupPassword')?.focus();
            return;
        }
        if (pickupPassword.length > 100) {
            Toast.warning('取卡密码最多100字符');
            return;
        }
        if (!selectedOption || selectedOption.disabled) {
            Toast.warning('请选择可用的支付方式');
            return;
        }
        let guestEmail = '';
        if (!App.currentUser) {
            guestEmail = document.getElementById('guestPurchaseEmail')?.value?.trim().toLowerCase() || '';
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(guestEmail)) {
                Toast.warning('游客购买必须填写真实有效的邮箱，用于接收查询码');
                document.getElementById('guestPurchaseEmail')?.focus();
                return;
            }
        }

        if (!App.currentUser) {
            const guestConfirmed = await confirmGuestPurchaseRisk();
            if (!guestConfirmed) return;
        }

        if (selectedOption.value !== 'balance') {
            const guestToken = App.currentUser ? '' : getGuestOrderToken();
            const result = await API.createProductPaymentOrder(
                selectedOption.configId,
                App.currentDetailProduct.id,
                quantity,
                selectedOption.payType,
                pickupPassword,
                guestToken,
                guestEmail
            );
            if (!result.success) {
                Toast.error(result.message || '创建支付订单失败');
                return;
            }
            if (!App.currentUser) {
                saveGuestOrder({
                    id: result.order?.id || '',
                    trade_no: result.order?.trade_no || '',
                    guest_token: guestToken,
                    guest_email: guestEmail,
                    product_title: App.currentDetailProduct.title || '',
                    quantity,
                    amount: result.order?.actual_amount || result.order?.amount || 0,
                    created_at: result.order?.created_at || Math.floor(Date.now() / 1000),
                    pickup_password_enabled: !!App.currentDetailProduct.pickup_password_enabled
                });
            }
            hideModalSafely('purchaseConfirmModal');
            showQrPaymentModal(result, {
                methodLabel: selectedOption.label,
                guestToken,
                guestOrder: !App.currentUser,
                successMessage: App.currentUser ? '支付成功，商品已发货，页面即将刷新' : '支付成功，商品已发货，查询码已发送到邮箱，请保存好订单号和查询码'
            });
            return;
        }

        const result = await API.buyProduct(App.currentDetailProduct.id, quantity, pickupPassword);

        if (!result.success) {
            Toast.error(result.message);
            return;
        }

        const order = result.order;
        const d = order.delivery_info;
        window.currentSuccessDeliveryInfoForExport = d;
        const pickupWasEnabled = !!App.currentDetailProduct.pickup_password_enabled;

        document.getElementById('purchaseBody').innerHTML = `
        <div class="text-center mb-3">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            <h5 class="fw-bold mt-2">购买成功！</h5>
            <p class="text-muted">商品已自动发货，请保存好以下信息${pickupWasEnabled ? '；后续在购买记录查看发货需要输入你刚设置的取卡密码' : ''}</p>
        </div>
        <div class="delivery-card">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-box-seam me-1"></i>发货信息</h6>
                ${deliveryInfoExportText(d) ? `<button class="btn btn-sm btn-outline-primary" onclick="exportDeliveryInfoTxt('${Security.escapeAttr(order.id)}', window.currentSuccessDeliveryInfoForExport)"><i class="bi bi-download me-1"></i>导出TXT</button>` : ''}
            </div>
            <div class="small">
                ${deliveryInfoHtml(d)}
            </div>
        </div>
        <p class="text-muted small text-center mt-2">信息已保存至「购买记录」</p>
    `;

        document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-primary w-100" data-bs-dismiss="modal" onclick="afterPurchase()">完成</button>
    `;

        App.currentDetailProduct = null;
        Toast.success('购买成功！');
        await refreshUserData();
    } finally {
        confirmPurchasePending = false;
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = oldConfirmHtml;
        }
        setTimeout(cleanupBootstrapModalArtifacts, 180);
    }
}

async function afterPurchase() {
    if (App.currentPage === 'home') {
        loadProducts();
    } else if (App.currentPage === 'dashboard') {
        renderDashboard();
    }
}

async function viewDeliveryInfo(orderId, pickupPassword = '') {
    const password = pickupPassword || document.getElementById('pickupPasswordInput')?.value?.trim() || '';
    const result = await API.getOrder(orderId, password);
    if (!result.success) {
        Toast.error(result.message || '订单不存在');
        return;
    }

    const order = result.order;
    const needsPassword = !!order.pickup_password_required;
    if (needsPassword && password) {
        Toast.warning('取卡密码错误，请重试');
    }

    const d = order.delivery_info;
    window.currentDeliveryInfoForExport = d;
    const modalEl = document.getElementById('purchaseConfirmModal');
    document.getElementById('purchaseBody').innerHTML = `
        <h6 class="fw-bold mb-3"><i class="bi bi-box-seam me-1"></i>发货信息</h6>
        ${needsPassword ? `
            <div class="alert alert-warning small">该订单设置了取卡密码，请输入你购买时填写的取卡密码后查看发货信息。</div>
            <div class="input-group mb-3">
                <input type="password" class="form-control" id="pickupPasswordInput" placeholder="请输入取卡密码" autocomplete="off">
                <button type="button" class="btn btn-primary" onclick="viewDeliveryInfo('${Security.escapeAttr(orderId)}', document.getElementById('pickupPasswordInput').value)">确认取卡</button>
            </div>
        ` : ''}
        ${needsPassword ? '' : `
        <div class="delivery-card">
            ${deliveryInfoExportText(d) ? `<div class="d-flex justify-content-end mb-3"><button class="btn btn-sm btn-outline-primary" onclick="exportDeliveryInfoTxt('${Security.escapeAttr(order.id || orderId)}', window.currentDeliveryInfoForExport)"><i class="bi bi-download me-1"></i>导出TXT</button></div>` : ''}
            <div class="small">
                ${deliveryInfoHtml(d)}
            </div>
        </div>`}
    `;

    document.getElementById('purchaseFooter').innerHTML = `
        <button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>
    `;

    if (!modalEl.classList.contains('show')) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
        setTimeout(cleanupBootstrapModalArtifacts, 80);
    }

    if (needsPassword) {
        document.getElementById('pickupPasswordInput')?.focus();
    }
}

async function openCommentModal(productId, orderId) {
    if (typeof openReviewDialog === 'function') {
        openReviewDialog(productId, orderId);
        return;
    }
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

async function deleteProduct(id) {
    if (!confirm('确定要下架删除此商品吗？')) return;

    const result = await API.deleteProduct(id);
    if (result.success) {
        Toast.success('商品已删除');
        renderDashboardTab('myproducts');
    } else {
        Toast.error(result.message);
    }
}

function togglePickupPasswordInput() {
    return;
}

function updatePublishImagePreview(value) {
    const preview = document.getElementById('pubImagePreview');
    if (!preview) return;
    const url = resolveProductImageUrl(value);
    if (url) {
        preview.innerHTML = `<img src="${Security.escapeAttr(url)}" alt="商品图片预览" onerror="this.remove(); this.parentElement.classList.add('image-error'); this.parentElement.innerHTML='<i class=&quot;bi bi-image&quot;></i><span>图片暂时无法访问，请重新上传</span>';">`;
    } else {
        preview.innerHTML = '<i class="bi bi-cloud-arrow-up"></i><span>点击选择或拖拽上传图片</span><small>支持 JPG / PNG / GIF / WEBP，最大 2MB</small>';
    }
}

async function handlePublishImageFile(file) {
    if (!file) return;
    if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
        Toast.warning('仅支持 JPG、PNG、GIF、WEBP 图片');
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        Toast.warning('图片大小不能超过2MB');
        return;
    }
    const preview = document.getElementById('pubImagePreview');
    if (preview) preview.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div><span>上传中...</span>';
    const result = await API.uploadProductImage(file);
    if (!result.success) {
        Toast.error(result.message || '图片上传失败');
        updatePublishImagePreview(document.getElementById('pubImageUrl')?.value || '');
        return;
    }
    const input = document.getElementById('pubImageUrl');
    if (input) input.value = result.url;
    updatePublishImagePreview(result.url);
    Toast.success('图片上传成功');
}

function initPublishImageDropZone() {
    const zone = document.getElementById('pubImageDropZone');
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
        handlePublishImageFile(file);
    });
}

function isMerchantVerifiedForPublish() {
    const user = App.currentUser || {};
    return user.merchant_verified === true || user.merchant_verified === '1';
}

function merchantPublishBlockMessage() {
    const user = App.currentUser || {};
    if (user.merchant_status === 'pending') return '您的商家重新开通申请正在审核中，请等待管理员审核';
    if (user.merchant_status === 'rejected') return '您的商家重新开通申请未通过，请修改认证资料后重新提交';
    if (!user.merchant_rules_accepted) return '请先阅读并同意商家守则、免责声明与商家质保';
    return '您还未完成商家认证，请先到控制台完成商家开通';
}

function redirectToMerchantCertification() {
    Toast.warning(merchantPublishBlockMessage());
    const modal = bootstrap.Modal.getInstance(document.getElementById('publishModal'));
    if (modal) modal.hide();
    showDashboard('profile');
    setTimeout(() => {
        (document.getElementById('merchantCertificationBox') || document.querySelector('.payment-receive-card'))?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 350);
}

function openPublishModal() {
    if (!App.currentUser) {
        Toast.warning('请先登录');
        openLoginModal();
        return;
    }
    // 二级商铺用户不允许发布商品（直接检测）
    checkAndBlockSubShopUser();
    if (window.__subShopBlocked === true) {
        Toast.warning('二级商铺用户暂时无法发布商品，请通过主站入口访问');
        return;
    }
    if (!isMerchantVerifiedForPublish()) {
        redirectToMerchantCertification();
        return;
    }

    // 重置表单
    document.getElementById('pubTitle').value = '';
    document.getElementById('pubCategory').value = '游戏账号';
    document.getElementById('pubPrice').value = '';
    document.getElementById('pubDesc').value = '';
    document.getElementById('pubAccounts').value = '';
    const imageInput = document.getElementById('pubImageUrl');
    const fileInput = document.getElementById('pubImageFile');
    if (imageInput) imageInput.value = '';
    if (fileInput) fileInput.value = '';
    updatePublishImagePreview('');
    const pickupEnabled = document.getElementById('pubPickupPasswordEnabled');
    if (pickupEnabled) pickupEnabled.checked = false;

    const modal = new bootstrap.Modal(document.getElementById('publishModal'));
    modal.show();
    initPublishImageDropZone();
}

async function handlePublish(event) {
    if (event && typeof event.preventDefault === 'function') event.preventDefault();
    const submitBtn = document.getElementById('btnPublishProduct');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    const showPublishError = (message) => {
        const finalMessage = message || '发布失败，请检查填写内容';
        let errorBox = document.getElementById('publishErrorBox');
        const modalBody = document.querySelector('#publishModal .modal-body');
        if (!errorBox && modalBody) {
            errorBox = document.createElement('div');
            errorBox.id = 'publishErrorBox';
            errorBox.className = 'alert alert-danger py-2 small mb-3';
            modalBody.prepend(errorBox);
        }
        if (errorBox) {
            errorBox.textContent = finalMessage;
            errorBox.classList.remove('hidden');
        }
        if (typeof Toast !== 'undefined' && Toast.error) {
            Toast.error(finalMessage);
        } else {
            alert(finalMessage);
        }
    };
    const clearPublishError = () => {
        const errorBox = document.getElementById('publishErrorBox');
        if (errorBox) {
            errorBox.textContent = '';
            errorBox.classList.add('hidden');
        }
    };
    try {
        clearPublishError();
        if (typeof Toast === 'undefined') {
            alert('页面脚本未完全加载，请强制刷新浏览器缓存后重试');
            return;
        }
        if (typeof API === 'undefined' || typeof API.publishProduct !== 'function') {
            showPublishError('发布接口脚本未加载，请刷新页面后重试');
            return;
        }
        if (!isMerchantVerifiedForPublish()) {
            redirectToMerchantCertification();
            return;
        }

        const titleEl = document.getElementById('pubTitle');
        const categoryEl = document.getElementById('pubCategory');
        const priceEl = document.getElementById('pubPrice');
        const descEl = document.getElementById('pubDesc');
        const accountsEl = document.getElementById('pubAccounts');
        const imageEl = document.getElementById('pubImageUrl');
        const pickupEnabledEl = document.getElementById('pubPickupPasswordEnabled');

        if (!titleEl || !categoryEl || !priceEl || !descEl || !accountsEl) {
            showPublishError('发布表单加载不完整，请刷新页面后重试');
            return;
        }

        const title = titleEl.value.trim();
        const category = categoryEl.value;
        const price = parseFloat(priceEl.value);
        const description = descEl.value.trim();
        const accountListText = accountsEl.value.trim();
        const image = imageEl ? imageEl.value.trim() : '';
        const pickupPasswordEnabled = pickupEnabledEl && pickupEnabledEl.checked;

        if (!title || !price || price <= 0) {
            showPublishError('请填写标题和有效价格');
            return;
        }
        if (!accountListText) {
            showPublishError('请填写账户列表');
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>发布中...';
        }

        const result = await API.publishProduct({
            title, category, price, description, account_list: accountListText,
            image,
            pickup_password_enabled: pickupPasswordEnabled ? '1' : '0'
        });

        if (result && result.success) {
            Toast.success(result.message || '商品发布成功');
            bootstrap.Modal.getInstance(document.getElementById('publishModal'))?.hide();
            if (App.currentPage === 'dashboard') {
                renderDashboardTab('myproducts');
            }
            await loadProducts();
            return;
        }

        showPublishError((result && result.message) || '发布失败，请检查填写内容');
    } catch (error) {
        console.error('Publish product failed:', error);
        showPublishError(error && error.message ? '发布失败：' + error.message : '发布失败，请刷新页面后重试');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText || '发布商品';
        }
    }
}

window.handlePublish = handlePublish;

document.addEventListener('DOMContentLoaded', function() {
    const publishBtn = document.getElementById('btnPublishProduct');
    if (publishBtn) {
        publishBtn.addEventListener('click', handlePublish);
    }
});
