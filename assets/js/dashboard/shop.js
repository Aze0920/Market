/**
 * Dashboard - 商铺设置模块
 * 支持自定义商铺名称、公告和 CSS 样式
 */
(function() {
    'use strict';

    /**
     * 渲染商铺设置 Tab
     */
    window.render_shop_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var App = deps.App;
        var Security = deps.Security;

        var user = App.currentUser || {};
        var shopName = user.shop_name || '';
        var shopAnnouncement = user.shop_announcement || '';
        var shopCustomCss = user.shop_custom_css || '';

        area.innerHTML = `
            <h5 class="fw-bold mb-4"><i class="bi bi-shop me-2 text-primary"></i>商铺设置</h5>
            <div class="profile-card-soft">
                <h6 class="fw-bold mb-3"><i class="bi bi-gear me-2 text-primary"></i>基础信息</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">商铺名称</label>
                        <input type="text" class="form-control" id="shopName" maxlength="50" value="${Security.escapeAttr(shopName)}" placeholder="设置你的商铺显示名称">
                        <small class="text-muted">1-50 个字符，不填则显示用户名</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">商铺公告</label>
                        <input type="text" class="form-control" id="shopAnnouncement" maxlength="500" value="${Security.escapeAttr(shopAnnouncement)}" placeholder="显示在商铺页面的公告信息">
                        <small class="text-muted">最多 500 个字符，支持纯文本</small>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold mb-3"><i class="bi bi-palette me-2 text-primary"></i>自定义样式</h6>
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>CSS 仅限样式</strong>：仅允许填写 CSS 样式规则（如背景色、字体颜色、边框等）。
                    <br>禁止包含 JavaScript、HTML 标签、事件处理器（onclick、onerror 等）或危险表达式。
                    <br>系统会自动过滤危险内容，不正确的 CSS 可能无法生效。
                </div>
                <div class="mb-3">
                    <label class="form-label">自定义 CSS</label>
                    <textarea class="form-control font-monospace" id="shopCustomCss" rows="8" maxlength="65535" placeholder="/* 示例：修改商铺背景色 */&#10;.shop-page { background: #f8f9fa; }&#10;&#10;/* 修改标题颜色 */&#10;.shop-title { color: #6366f1; }&#10;&#10;/* 修改商品卡片边框 */&#10;.product-card { border-radius: 12px; border: 1px solid #e5e7eb; }">${Security.escapeHtml(shopCustomCss)}</textarea>
                    <small class="text-muted d-block mt-1">最多 65535 字符。建议填写有效的 CSS 选择器和属性。</small>
                </div>

                <div class="mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="window.previewShopCss()">
                        <i class="bi bi-eye me-1"></i>预览样式
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="window.clearShopCss()">
                        <i class="bi bi-trash me-1"></i>清空 CSS
                    </button>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        商铺名称和公告会显示在你的商铺页面（<code>seller.{domain}</code>）顶部。
                        <br>自定义 CSS 会注入到商铺所有页面，仅影响商铺样式，不影响主市场。
                    </div>
                    <button type="button" class="btn btn-primary" onclick="window.saveShopSettings()">
                        <i class="bi bi-check2-circle me-1"></i>保存商铺设置
                    </button>
                </div>
            </div>
        `;
    };

    /**
     * 保存商铺设置
     */
    window.saveShopSettings = async function() {
        var API = window.API;
        var App = window.App;
        var Toast = window.Toast;
        if (!API) return;

        var shopName = document.getElementById('shopName') && document.getElementById('shopName').value || '';
        var shopAnnouncement = document.getElementById('shopAnnouncement') && document.getElementById('shopAnnouncement').value || '';
        var shopCustomCss = document.getElementById('shopCustomCss') && document.getElementById('shopCustomCss').value || '';

        var result = await API.updateShopSettings(shopName, shopAnnouncement, shopCustomCss);
        if (!result.success) {
            Toast.error(result.message || '保存失败');
            return;
        }

        Toast.success(result.message || '商铺设置已保存');
        if (result.user) {
            App.setUser(result.user);
        }
        window.renderDashboardTab && window.renderDashboardTab('shop');
    };

    /**
     * 预览 CSS 效果
     */
    window.previewShopCss = function() {
        var css = document.getElementById('shopCustomCss') && document.getElementById('shopCustomCss').value || '';
        var previewId = 'shop-css-preview-iframe';
        var existing = document.getElementById(previewId);

        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = '<h6 class="fw-bold mb-3"><i class="bi bi-eye me-1"></i>CSS 预览</h6><iframe id="' + previewId + '" style="width:100%;height:300px;border:1px solid #dee2e6;border-radius:8px;" sandbox="allow-same-origin"></iframe>';
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>';
        modal.show();

        var iframe = document.getElementById(previewId);
        if (iframe && iframe.contentWindow) {
            try {
                var safeCss = css.replace(/<\/?[^>]+>/gi, '').replace(/javascript:/gi, '').replace(/on\w+=/gi, '');
                iframe.contentWindow.document.open();
                iframe.contentWindow.document.write('<html><head><style>body{padding:16px;font-family:system-ui,sans-serif;}' + safeCss + '</style></head><body><h3>预览效果</h3><p>背景色: <span style="background:#6366f1;color:white;padding:2px 8px;border-radius:4px;">示例文字</span></p><div style="border:1px solid #ccc;padding:16px;margin:8px 0;border-radius:8px;">商品卡片示例</div></body></html>');
                iframe.contentWindow.document.close();
            } catch (e) {
                console.error('CSS preview error:', e);
            }
        }
    };

    /**
     * 清空 CSS
     */
    window.clearShopCss = function() {
        var cssInput = document.getElementById('shopCustomCss');
        if (cssInput && confirm('确定要清空所有自定义 CSS 吗？')) {
            cssInput.value = '';
        }
    };

})();
