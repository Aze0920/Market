/**
 * Dashboard - 自定义标签模块
 */
(function() {
    'use strict';

    /**
     * 渲染自定义标签 Tab
     */
    window.render_customlabel_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var App = deps.App;
        var Security = deps.Security;

        var userResult = await API.getCurrentUser();
        if (userResult.success && userResult.logged_in && userResult.user) {
            App.setUser(userResult.user);
        }

        var user = App.currentUser || {};
        var levelsResult = await API.getMembershipLevels();
        var levelInfo = (levelsResult.success ? (levelsResult.levels || {}) : {})[user.membership_level || 'Free'] || {};
        var canUseCustomLabel = user.role !== 'admin' && !!(levelInfo.custom_label_enabled || user.can_use_custom_label);

        if (user.role === 'admin') {
            area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-tags me-2 text-primary"></i>自定义标签</h5><div class="profile-card-soft"><div class="alert alert-info mb-0">管理员账号显示<strong>专属标识</strong>，不使用会员自定义标签。<br>请到后台 <strong>会员等级</strong> 页面顶部配置「管理员专属标识」的文字、图标和渐变颜色。</div></div>';
            return;
        }

        if (!canUseCustomLabel) {
            area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-tags me-2 text-primary"></i>自定义标签</h5><div class="profile-card-soft"><div class="alert alert-warning mb-3">你当前的会员等级 <strong>' + Security.escapeHtml(user.membership_level || 'Free') + '</strong> 尚未开通自定义标签。</div><div class="text-muted small">请让管理员到后台 <strong>会员等级</strong> → 编辑对应等级 → 勾选 <strong>「允许自定义标签」</strong> → 保存配置后，重新进入本页面即可设置。</div></div>';
            return;
        }

        var previewGradient = user.custom_label_gradient || levelInfo.gradient || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-tags me-2 text-primary"></i>自定义标签</h5><div class="profile-card-soft"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><div class="text-muted small">设置后会显示在商品卡片「卖家名称」后面，文字 1-10 个字符，可自定义 bi-xx 图标和渐变背景。</div></div><div id="customLabelPreview">' + (typeof window.renderGradientBadge === 'function' ? window.renderGradientBadge(user.custom_label_text || '预览', user.custom_label_icon || 'bi-tag', previewGradient, 'custom-label-badge') : '') + '</div></div><div class="row g-3"><div class="col-md-4"><label class="form-label">标签文字</label><input class="form-control" id="customLabelText" maxlength="10" value="' + Security.escapeAttr(user.custom_label_text || '') + '" placeholder="1-10 个字符" oninput="window.updateCustomLabelPreview()"></div><div class="col-md-4"><label class="form-label">图标 class</label><input class="form-control" id="customLabelIcon" value="' + Security.escapeAttr(user.custom_label_icon || 'bi-tag') + '" placeholder="例如 bi-star-fill" oninput="window.updateCustomLabelPreview()"><div class="form-text">填写 Bootstrap Icons 名称，如 bi-star-fill</div></div><div class="col-md-4"><label class="form-label">背景渐变 CSS</label><input class="form-control" id="customLabelGradient" value="' + Security.escapeAttr(user.custom_label_gradient || levelInfo.gradient || '') + '" placeholder="linear-gradient(135deg, #6366f1, #8b5cf6)" oninput="window.updateCustomLabelPreview()"></div><div class="col-12"><button type="button" class="btn btn-primary" onclick="window.saveCustomLabel()"><i class="bi bi-check2-circle me-1"></i>保存自定义标签</button></div></div></div>';
    };

    /**
     * 更新自定义标签预览
     */
    window.updateCustomLabelPreview = function() {
        var preview = document.getElementById('customLabelPreview');
        if (!preview || typeof window.renderGradientBadge !== 'function') return;
        var text = document.getElementById('customLabelText') && document.getElementById('customLabelText').value.trim() || '预览';
        var icon = document.getElementById('customLabelIcon') && document.getElementById('customLabelIcon').value.trim() || 'bi-tag';
        var gradient = document.getElementById('customLabelGradient') && document.getElementById('customLabelGradient').value.trim() || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)';
        preview.innerHTML = window.renderGradientBadge(text, icon, gradient, 'custom-label-badge');
    };

    /**
     * 保存自定义标签
     */
    window.saveCustomLabel = async function() {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        var App = window.App;
        var text = document.getElementById('customLabelText') && document.getElementById('customLabelText').value.trim() || '';
        var icon = document.getElementById('customLabelIcon') && document.getElementById('customLabelIcon').value.trim() || 'bi-tag';
        var gradient = document.getElementById('customLabelGradient') && document.getElementById('customLabelGradient').value.trim() || '';
        if (!text) {
            Toast.warning('请填写 1-10 个字符的标签文字');
            return;
        }
        if (text.length > 10) {
            Toast.warning('标签文字不能超过 10 个字符');
            return;
        }
        var result = await API.saveCustomLabel(text, icon, gradient);
        if (!result.success) {
            Toast.error(result.message || '保存失败');
            return;
        }
        Toast.success(result.message || '自定义标签已保存');
        if (result.user) App.setUser(result.user);
        window.updateCustomLabelPreview && window.updateCustomLabelPreview();
    };

})();
