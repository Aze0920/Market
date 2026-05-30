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

function plainTextSummary(markdown, maxLength = 80) {
    const text = (markdown || '').replace(/```[\s\S]*?```/g, ' ').replace(/!\[[^\]]*\]\([^)]*\)/g, ' ').replace(/\[([^\]]+)\]\([^)]*\)/g, '$1').replace(/[#>*_`\-]/g, ' ').replace(/\s+/g, ' ').trim();
    return text.length > maxLength ? text.slice(0, maxLength) + '...' : text;
}

function deliveryInfoHtml(d) {
    const items = Array.isArray(d?.items) ? d.items : [d];
    return items.map((item, index) => {
        if (item && item.format === 'line' && item.content) {
            return `<div class="mb-3"><strong>账号信息 ${items.length > 1 ? '#' + (index + 1) : ''}:</strong><pre class="delivery-plain mt-2 mb-2">${Security.escapeHtml(item.content)}</pre><button class="btn btn-sm btn-outline-primary" onclick="Utils.copyText('${Security.escapeAttr(item.content)}')"><i class="bi bi-clipboard me-1"></i>复制账号信息</button></div>`;
        }
        return `
            <div class="mb-3 pb-2 border-bottom">
                <p class="mb-2"><strong>📧 邮箱:</strong> ${Security.escapeHtml(item?.email || '')}<i class="bi bi-clipboard copy-btn" onclick="Utils.copyText('${Security.escapeAttr(item?.email || '')}')"></i></p>
                <p class="mb-2"><strong>🔑 密码:</strong> ${Security.escapeHtml(item?.password || '')}<i class="bi bi-clipboard copy-btn" onclick="Utils.copyText('${Security.escapeAttr(item?.password || '')}')"></i></p>
                <p class="mb-2"><strong>🆔 Client ID:</strong> ${Security.escapeHtml(item?.client_id || 'N/A')}<i class="bi bi-clipboard copy-btn" onclick="Utils.copyText('${Security.escapeAttr(item?.client_id || 'N/A')}')"></i></p>
                <p class="mb-0"><strong>🔄 Fresh Token:</strong> ${Security.escapeHtml(item?.fresh_token || 'N/A')}<i class="bi bi-clipboard copy-btn" onclick="Utils.copyText('${Security.escapeAttr(item?.fresh_token || 'N/A')}')"></i></p>
            </div>
        `;
    }).join('');
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

function sellerMembershipBadge(product = {}) {
    const level = String(product.seller_membership_level || 'Free').trim() || 'Free';
    const priority = Number(product.seller_membership_priority || 0);
    if (priority <= 0 && level.toLowerCase() === 'free') {
        return '<span class="seller-level-badge free"><i class="bi bi-person"></i>Free</span>';
    }
    return `<span class="seller-level-badge vip"><i class="bi bi-gem"></i>${Security.escapeHtml(level)}</span>`;
}

async function loadProducts(options = {}) {
    const grid = document.getElementById('productGrid');
    const emptyState = document.getElementById('emptyProductState');
    if (!grid || !emptyState) return;

    const forceAll = !!options.forceAll;
    const search = forceAll ? '' : (document.getElementById('searchInput')?.value?.trim() || '');
    const category = forceAll ? 'all' : (document.getElementById('categoryFilter')?.value || 'all');

    grid.innerHTML = '<div class="col-12"><div class="loading"><div class="spinner"></div></div></div>';
    emptyState.classList.add('hidden');

    const result = await API.getProducts({ search, category });

    if (!result.success || result.products.length === 0) {
        grid.innerHTML = '';
        emptyState.classList.remove('hidden');
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

async function openProductDetail(id) {
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
                ${(!App.currentUser || App.currentUser.id !== product.seller_id) ? `
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
    if (App.currentUser && App.currentUser.id === product.seller_id) {
        buyBtn.classList.add('hidden');
    } else {
        buyBtn.classList.remove('hidden');
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
                        <p class="mb-0">游客购买仅支持在线支付，支付后请保存订单号且无法维权</p>
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

        if (selectedOption.value !== 'balance') {
            const guestToken = App.currentUser ? '' : getGuestOrderToken();
            const result = await API.createProductPaymentOrder(
                selectedOption.configId,
                App.currentDetailProduct.id,
                quantity,
                selectedOption.payType,
                pickupPassword,
                guestToken
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
                successMessage: App.currentUser ? '支付成功，商品已发货，页面即将刷新' : '支付成功，商品已发货，请保存好订单号'
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
        const pickupWasEnabled = !!App.currentDetailProduct.pickup_password_enabled;

        document.getElementById('purchaseBody').innerHTML = `
        <div class="text-center mb-3">
            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
            <h5 class="fw-bold mt-2">购买成功！</h5>
            <p class="text-muted">商品已自动发货，请保存好以下信息${pickupWasEnabled ? '；后续在购买记录查看发货需要输入你刚设置的取卡密码' : ''}</p>
        </div>
        <div class="delivery-card">
            <h6 class="fw-bold mb-3"><i class="bi bi-box-seam me-1"></i>发货信息</h6>
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
