/**
 * Dashboard - 我的商品模块
 */
(function() {
    'use strict';

    /**
     * 渲染我的商品 Tab
     */
    window.render_myproducts_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Security = deps.Security;
        var Utils = deps.Utils;

        var result = await API.getMyProducts();
        if (!result.success || result.products.length === 0) {
            area.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-box-seam"></i>
                    <h5>暂无发布商品</h5>
                    <button class="btn btn-primary mt-2" onclick="window.openPublishModal()">
                        <i class="bi bi-plus-circle me-1"></i>发布商品
                    </button>
                </div>
            `;
            return;
        }

        area.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>我的商品 (${result.products.length})</h5>
                <button class="btn btn-primary btn-sm" onclick="window.openPublishModal()">
                    <i class="bi bi-plus-circle me-1"></i>发布新商品
                </button>
            </div>
            <div class="row g-3 seller-product-grid">
                ${result.products.map(function(p) {
                    return `
                        <div class="col-md-6 col-xl-4">
                            <div class="card seller-product-card h-100" onclick="window.openSellerProductManage('${Security.escapeAttr(p.id)}')" role="button" title="点击编辑商品">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start gap-3">
                                        <div class="flex-grow-1 min-width-0">
                                            <div class="d-flex align-items-center gap-2 mb-2 seller-product-actions">
                                                <span class="badge badge-primary">${Security.escapeHtml(p.category || '其他')}</span>
                                                <button class="btn btn-sm btn-outline-primary seller-stock-action" onclick="event.stopPropagation(); window.openAddStockModal('${Security.escapeAttr(p.id)}')" title="添加库存">
                                                    <i class="bi bi-plus-circle me-1"></i>添加库存
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary seller-stock-action" onclick="event.stopPropagation(); window.openStockManageModal('${Security.escapeAttr(p.id)}')" title="库存管理">
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
                                        <button class="btn btn-danger btn-sm seller-product-delete" onclick="event.stopPropagation(); window.deleteProduct('${Security.escapeAttr(p.id)}')" title="删除商品">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    };

    /**
     * 打开卖家商品管理弹窗
     */
    window.openSellerProductManage = async function(productId) {
        var API = window.API;
        if (!API) return;

        var bootstrap = window.bootstrap;
        var Security = window.Security;
        var Utils = window.Utils;
        var Toast = window.Toast;

        var productResult = await API.getProduct(productId);
        var reviewsResult = await API.getProductReviews(productId);
        if (!productResult.success) {
            Toast.error(productResult.message || '商品不存在');
            return;
        }
        var product = productResult.product;
        var comments = reviewsResult.success ? reviewsResult.comments : [];
        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = '<h5 class="fw-bold mb-3"><i class="bi bi-pencil-square me-1"></i>编辑商品</h5><div class="row g-2 mb-3"><div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><strong>' + Security.escapeHtml(product.stock) + '</strong><br><small class="text-muted">库存</small></div></div><div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><strong>' + Security.escapeHtml(product.sales) + '</strong><br><small class="text-muted">已售</small></div></div><div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><strong>' + comments.length + '</strong><br><small class="text-muted">评价</small></div></div></div><div class="row g-3"><div class="col-md-8"><label class="form-label">商品标题</label><input class="form-control" id="editProductTitle" maxlength="100" value="' + Security.escapeAttr(product.title || '') + '"></div><div class="col-md-4"><label class="form-label">价格 (¥)</label><input type="number" class="form-control" id="editProductPrice" min="0.01" step="0.01" value="' + Security.escapeAttr(product.price || 0) + '"></div><div class="col-md-6"><label class="form-label">分类</label><select class="form-select" id="editProductCategory">' + ['游戏账号', '流媒体', '软件许可', '其他'].map(function(cat) {
                        return '<option value="' + Security.escapeAttr(cat) + '"' + (cat === product.category ? ' selected' : '') + '>' + Security.escapeHtml(cat) + '</option>';
                    }).join('') + '</select></div><div class="col-md-6"><label class="form-label">商品图片链接</label><input class="form-control" id="editProductImage" value="' + Security.escapeAttr(product.image || '') + '" placeholder="图片链接或上传后的地址" oninput="window.updateEditProductImagePreview(this.value)"></div><div class="col-12"><label class="form-label">商品描述（支持 Markdown）</label><textarea class="form-control" id="editProductDesc" rows="4" placeholder="描述你的商品，支持 Markdown 格式">' + Security.escapeHtml(product.description || '') + '</textarea></div><div class="col-12"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="editPickupPasswordEnabled"' + (product.pickup_password_enabled === '1' || product.pickup_password_enabled === true ? ' checked' : '') + '><label class="form-check-label" for="editPickupPasswordEnabled">开启买家取卡密码</label></div></div></div>';
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="window.saveSellerProduct(\'' + Security.escapeAttr(productId) + '\')">保存</button>';
        modal.show();
        setTimeout(function() {
            var dropZone = document.getElementById('editProductImageDropZone');
            if (dropZone && !dropZone.dataset.bound) {
                window.initEditProductImageDropZone && window.initEditProductImageDropZone();
            }
        }, 100);
    };

    /**
     * 保存卖家商品
     */
    window.saveSellerProduct = async function(productId) {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        var title = document.getElementById('editProductTitle') && document.getElementById('editProductTitle').value || '';
        var category = document.getElementById('editProductCategory') && document.getElementById('editProductCategory').value || '其他';
        var price = parseFloat(document.getElementById('editProductPrice') && document.getElementById('editProductPrice').value || '0');
        var description = document.getElementById('editProductDesc') && document.getElementById('editProductDesc').value || '';
        var image = document.getElementById('editProductImage') && document.getElementById('editProductImage').value || '';
        var pickupPasswordEnabled = document.getElementById('editPickupPasswordEnabled') && document.getElementById('editPickupPasswordEnabled').checked ? '1' : '0';

        if (!title || !price || price <= 0) {
            Toast.warning('请填写标题和有效价格');
            return;
        }

        var result = await API.updateProduct(productId, {
            title: title,
            category: category,
            price: price,
            description: description,
            image: image,
            pickup_password_enabled: pickupPasswordEnabled
        });

        if (!result.success) {
            Toast.error(result.message || '保存失败');
            return;
        }

        Toast.success(result.message || '商品已更新');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal')).hide();
        window.renderDashboardTab && window.renderDashboardTab('myproducts');
        if (typeof window.loadProducts === 'function') window.loadProducts();
    };

    /**
     * 更新编辑商品图片预览
     */
    window.updateEditProductImagePreview = function(value) {
        var preview = document.getElementById('editProductImagePreview');
        if (!preview) return;
        var url = String(value || '').trim();
        if (/^(https?:\/\/|\/uploads\/products\/).+\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(url)) {
            preview.innerHTML = '<img src="' + Security.escapeAttr(url) + '" alt="商品图片预览">';
        } else {
            preview.innerHTML = '<i class="bi bi-cloud-arrow-up"></i><span>点击选择或拖拽上传新图片</span><small>不上传则保留当前随机图标或图片</small>';
        }
    };

    /**
     * 处理编辑商品图片文件
     */
    window.handleEditProductImageFile = async function(file) {
        var API = window.API;
        var Toast = window.Toast;
        if (!file) return;
        if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
            Toast.warning('仅支持 JPG、PNG、GIF、WEBP 图片');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            Toast.warning('图片大小不能超过2MB');
            return;
        }
        var preview = document.getElementById('editProductImagePreview');
        if (preview) preview.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div><span>上传中...</span>';

        var result = await API.uploadProductImage(file);
        if (!result.success) {
            Toast.error(result.message || '图片上传失败');
            var imgInput = document.getElementById('editProductImage');
            window.updateEditProductImagePreview(imgInput && imgInput.value || '');
            return;
        }
        var input = document.getElementById('editProductImage');
        if (input) input.value = result.url;
        window.updateEditProductImagePreview(result.url);
        Toast.success('图片上传成功');
    };

    /**
     * 初始化编辑商品图片拖放区
     */
    window.initEditProductImageDropZone = function() {
        var zone = document.getElementById('editProductImageDropZone');
        if (!zone || zone.dataset.bound === '1') return;
        zone.dataset.bound = '1';
        ['dragenter', 'dragover'].forEach(function(eventName) {
            zone.addEventListener(eventName, function(e) {
                e.preventDefault();
                zone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function(eventName) {
            zone.addEventListener(eventName, function(e) {
                e.preventDefault();
                zone.classList.remove('dragover');
            });
        });
        zone.addEventListener('drop', function(e) {
            var file = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
            window.handleEditProductImageFile && window.handleEditProductImageFile(file);
        });
    };

    /**
     * 添加库存弹窗
     */
    window.openAddStockModal = async function(productId) {
        var API = window.API;
        if (!API) return;

        var bootstrap = window.bootstrap;
        var Security = window.Security;
        var Toast = window.Toast;

        var productResult = await API.getProduct(productId);
        if (!productResult.success) {
            Toast.error(productResult.message || '商品不存在');
            return;
        }
        var product = productResult.product;
        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = '<h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-1"></i>添加库存 - ' + Security.escapeHtml(product.title || '') + '</h6><div class="alert alert-info small">当前库存：' + Security.escapeHtml(product.stock) + ' 个</div><div class="mb-3"><label class="form-label">账号列表（一行一个，格式：账号----密码----备注 或 邮箱|密码|ClientID|FreshToken）</label><textarea class="form-control" id="addStockAccounts" rows="8" placeholder="账号1----密码1----备注1&#10;账号2----密码2----备注2&#10;&#10;或兼容格式：&#10;user1@email.com|password1|clientid1|token1"></textarea></div>';
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">取消</button><button class="btn btn-primary" onclick="window.submitAddStock(\'' + Security.escapeAttr(productId) + '\')">添加</button>';
        modal.show();
    };

    /**
     * 提交添加库存
     */
    window.submitAddStock = async function(productId) {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        var accounts = document.getElementById('addStockAccounts') && document.getElementById('addStockAccounts').value || '';
        if (!accounts.trim()) {
            Toast.warning('请输入账号信息');
            return;
        }
        var result = await API.addStock(productId, accounts);
        if (!result.success) {
            Toast.error(result.message || '添加库存失败');
            return;
        }
        Toast.success(result.message || '库存已添加');
        bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal')).hide();
        window.renderDashboardTab && window.renderDashboardTab('myproducts');
        if (typeof window.loadProducts === 'function') window.loadProducts();
    };

})();
