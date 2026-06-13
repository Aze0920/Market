/**
 * Dashboard - 管理模块（卡密管理、支付接口管理 - 仅管理员）
 */
(function() {
    'use strict';

    /**
     * 渲染卡密管理 Tab
     */
    window.render_cardmanage_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var cardResult = await API.getCards(false);
        var levelResult = await API.getMembershipLevels();

        if (!cardResult.success) {
            area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
            return;
        }

        var cards = cardResult.cards || [];
        var levels = levelResult.success ? Object.values(levelResult.levels || {}) : [];
        var membershipLevels = levels.filter(function(level) {
            return level && level.name && String(level.name).toLowerCase() !== 'free';
        });

        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-credit-card-2-front me-2"></i>卡密管理</h5><div class="card bg-light mb-4"><div class="card-body"><h6 class="fw-bold mb-3">生成新卡密</h6><div class="row g-3"><div class="col-md-3"><label class="form-label">卡密类型</label><select id="cardType" class="form-select" onchange="window.toggleCardCreateType()"><option value="balance">余额卡密</option><option value="membership">会员卡密</option></select></div><div class="col-md-3" id="cardAmountWrap"><label class="form-label">余额金额</label><input type="number" id="cardAmount" class="form-control" placeholder="金额" min="1" step="0.01"></div><div class="col-md-3 d-none" id="cardMembershipWrap"><label class="form-label">会员权益</label><select id="cardTargetLevel" class="form-select"' + (membershipLevels.length === 0 ? ' disabled' : '') + '>' + (membershipLevels.length === 0 ? '<option value="">暂无可生成会员</option>' : membershipLevels.map(function(level) {
            return '<option value="' + Security.escapeAttr(level.name) + '">' + Security.escapeHtml(level.name) + ' - ' + Security.escapeHtml(level.description || '会员权益') + '</option>';
        }).join('')) + '</select><small class="text-muted">Free 是默认会员，不可生成卡密。</small></div><div class="col-md-2"><label class="form-label">数量</label><input type="number" id="cardCount" class="form-control" placeholder="1-100" min="1" max="100" value="1"></div><div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary w-100" onclick="window.generateCards()">生成</button></div></div></div></div><div id="newCardsSection" class="mb-4" style="display: none;"><div class="card bg-success-light"><div class="card-body"><h6 class="fw-bold mb-2 text-success">新生成的卡密</h6><div id="newCardsList"></div></div></div></div><h6 class="fw-bold mb-3">卡密列表</h6>' + (cards.length === 0 ? '<div class="empty-state"><p>暂无卡密</p></div>' : '<div class="table-responsive"><table class="table"><thead><tr><th>卡密</th><th>类型</th><th>权益</th><th>状态</th><th>生成时间</th><th>操作</th></tr></thead><tbody>' + cards.map(function(c) {
            return '<tr><td><code>' + Security.escapeHtml(c.code) + '</code></td><td>' + (c.card_type === 'membership' ? '<span class="badge badge-primary">会员卡</span>' : '<span class="badge badge-info">余额卡</span>') + '</td><td>' + (c.card_type === 'membership' ? '会员：' + Security.escapeHtml(c.target_level || '-') : '¥' + Number(c.amount || 0).toFixed(2)) + '</td><td><span class="badge badge-' + (c.used ? 'secondary' : 'success') + '">' + (c.used ? '已使用' : '未使用') + '</span></td><td class="text-muted small">' + Utils.formatDate(c.created_at) + '</td><td>' + (!c.used ? "<button class=\"btn btn-sm btn-outline\" onclick=\"window.Utils.copyText(\'" + Security.escapeAttr(c.code) + "\')\">复制</button><button class=\"btn btn-sm btn-danger\" onclick=\"window.deleteCard(\'" + Security.escapeAttr(c.id) + "')\">删除</button>" : '') + '</td></tr>';
        }).join('') + '</tbody></table></div>');
    };

    /**
     * 渲染支付接口管理 Tab
     */
    window.render_paymentmanage_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var configResult = await API.getPaymentConfigs();
        var ordersResult = await API.getPaymentOrders();

        var configs = configResult.success ? configResult.configs : [];
        var orders = ordersResult.success ? ordersResult.orders : [];

        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-cash-stack me-2"></i>支付接口管理</h5><div class="card bg-light mb-4"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="fw-bold mb-2">支付接口配置</h6><p class="text-muted small mb-0">管理平台的支付接口，支持易支付等接口</p></div><button class="btn btn-primary" onclick="window.openPaymentConfigModal()"><i class="bi bi-gear me-1"></i>管理接口</button></div></div></div><h6 class="fw-bold mb-3">充值订单记录</h6>' + (orders.length === 0 ? '<p class="text-muted">暂无充值订单</p>' : '<div class="table-responsive"><table class="table"><thead><tr><th>订单号</th><th>用户</th><th>金额</th><th>实付</th><th>状态</th><th>时间</th></tr></thead><tbody>' + orders.map(function(o) {
            return '<tr><td><code class="small">' + Security.escapeHtml(o.trade_no) + '</code></td><td>' + Security.escapeHtml(o.user_id) + '</td><td>¥' + o.amount.toFixed(2) + '</td><td>¥' + o.actual_amount.toFixed(2) + '</td><td><span class="badge badge-' + (o.status === 'paid' ? 'success' : o.status === 'pending' ? 'warning' : 'danger') + '">' + (o.status === 'paid' ? '已支付' : o.status === 'pending' ? '待支付' : o.status === 'unpaid' ? '未支付' : '失败') + '</span></td><td class="text-muted small">' + Utils.formatDate(o.created_at) + '</td></tr>';
        }).join('') + '</tbody></table></div>');
    };

    /**
     * 切换卡密创建类型
     */
    window.toggleCardCreateType = function() {
        var cardType = document.getElementById('cardType') && document.getElementById('cardType').value;
        var amountWrap = document.getElementById('cardAmountWrap');
        var membershipWrap = document.getElementById('cardMembershipWrap');
        if (cardType === 'membership') {
            if (amountWrap) amountWrap.classList.add('d-none');
            if (membershipWrap) membershipWrap.classList.remove('d-none');
        } else {
            if (amountWrap) amountWrap.classList.remove('d-none');
            if (membershipWrap) membershipWrap.classList.add('d-none');
        }
    };

    /**
     * 生成卡密
     */
    window.generateCards = async function() {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        var cardType = document.getElementById('cardType') && document.getElementById('cardType').value || 'balance';
        var cardCount = parseInt((document.getElementById('cardCount') && document.getElementById('cardCount').value) || '1') || 1;
        var cardAmount = parseFloat((document.getElementById('cardAmount') && document.getElementById('cardAmount').value) || '0') || 0;
        var cardTargetLevel = document.getElementById('cardTargetLevel') && document.getElementById('cardTargetLevel').value || '';

        if (cardCount < 1 || cardCount > 100) {
            Toast.warning('数量需在 1-100 之间');
            return;
        }
        if (cardType === 'balance' && (!cardAmount || cardAmount <= 0)) {
            Toast.warning('请输入有效的余额金额');
            return;
        }
        if (cardType === 'membership' && !cardTargetLevel) {
            Toast.warning('请选择会员权益');
            return;
        }

        var params = {
            type: cardType,
            count: cardCount
        };
        if (cardType === 'balance') {
            params.amount = cardAmount;
        } else {
            params.target_level = cardTargetLevel;
        }

        var result = await API.createCards(params);
        if (!result.success) {
            Toast.error(result.message || '生成失败');
            return;
        }

        var cards = result.cards || [];
        var section = document.getElementById('newCardsSection');
        var list = document.getElementById('newCardsList');
        if (section) section.style.display = 'block';
        if (list) {
            list.innerHTML = cards.map(function(c) {
                return '<div class="d-flex justify-content-between align-items-center py-1"><code>' + c + '</code><button class="btn btn-sm btn-outline" onclick="window.Utils.copyText(\'' + c.replace(/'/g, '\\\'') + '\')">复制</button></div>';
            }).join('');
        }
        Toast.success('已生成 ' + cards.length + ' 个卡密');
        window.renderDashboardTab && window.renderDashboardTab('cardmanage');
    };

    /**
     * 删除卡密
     */
    window.deleteCard = async function(cardId) {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        if (!confirm('确定要删除这张卡密吗？')) return;

        var result = await API.deleteCard(cardId);
        if (!result.success) {
            Toast.error(result.message || '删除失败');
            return;
        }
        Toast.success(result.message || '卡密已删除');
        window.renderDashboardTab && window.renderDashboardTab('cardmanage');
    };

    /**
     * 打开支付配置弹窗
     */
    window.openPaymentConfigModal = async function() {
        var API = window.API;
        if (!API) return;

        var bootstrap = window.bootstrap;
        var Security = window.Security;
        var Toast = window.Toast;

        var result = await API.getPaymentConfigs();
        var configs = result.success ? result.configs : [];
        window.__paymentConfigs = configs;

        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = '<h6 class="fw-bold mb-3"><i class="bi bi-gear me-1"></i>支付接口配置</h6><div id="paymentConfigList">' + (configs.length === 0 ? '<div class="alert alert-info">暂未配置支付接口</div>' : configs.map(function(c) {
            return '<div class="card mb-2"><div class="card-body py-2"><div class="d-flex justify-content-between align-items-center"><div><strong>' + Security.escapeHtml(c.name || '接口') + '</strong><br><small class="text-muted">' + (Number(c.fee_rate || 0) > 0 ? '手续费 ' + (Number(c.fee_rate || 0) * 100).toFixed(1) + '%' : '无手续费') + '</small></div><div><button class="btn btn-sm btn-outline" onclick="window.editPaymentConfig(\'' + Security.escapeAttr(c.id) + '\')">编辑</button></div></div></div>';
        }).join('')) + '</div>';
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>';
        modal.show();
    };

})();
