/**
 * Dashboard - 财务中心模块（合并余额管理和会员中心）
 */
(function() {
    'use strict';

    /**
     * 渲染财务中心 Tab
     */
    window.render_balance_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var App = deps.App;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var result = await API.getMyRequests();
        var paymentResult = await API.getMyPaymentOrders();
        var configsResult = await API.getPaymentConfigs();
        var sysConfigResult = await API.getSystemConfig();
        var myMembershipResult = await API.getMyMembership();
        var levelsResult = await API.getMembershipLevels();
        var isAdmin = App.currentUser && App.currentUser.role === 'admin';

        var sysConfig = sysConfigResult.success ? sysConfigResult.config : {};
        var enableWithdraw = sysConfig.enable_withdraw !== false && sysConfig.enable_withdraw !== '0';
        var minWithdrawAmount = sysConfig.min_withdraw_amount || 10;
        var withdrawFeeRate = sysConfig.withdraw_fee_rate || 0.01;

        // 会员信息
        var myLevel = myMembershipResult.level || 'Free';
        var levels = levelsResult.levels || {};
        var levelInfo = levels[myLevel] || {};
        var levelPriority = Number(levelInfo.priority || 0);

        var requestsHtml = '';
        if (!result.success || !result.requests || result.requests.length === 0) {
            requestsHtml = '<p class="text-muted mt-3">暂无申请记录</p>';
        } else {
            requestsHtml = '<div class="mt-3">' + result.requests.map(function(r) {
                return '<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><span>' + (r.type === 'deposit' ? '充值' : (r.payment_method ? '提现-' + r.payment_method : '提现')) + ' ¥' + r.amount.toFixed(2) + '</span>' + (r.payment_account ? '<br><small class="text-muted">收款: ' + Security.escapeHtml(r.payment_account) + '</small>' : '') + '</div><div class="text-end"><span class="badge badge-' + ((r.status === 'approved' || r.status === 'paid') ? 'success' : r.status === 'rejected' ? 'danger' : 'warning') + '">' + ((r.status === 'approved' || r.status === 'paid') ? '已通过' : r.status === 'rejected' ? '已拒绝' : '待处理') + '</span>' + (r.admin_note ? '<br><small class="text-muted">' + Security.escapeHtml(r.admin_note) + '</small>' : '') + '</div><span class="text-muted small">' + Utils.formatDate(r.created_at) + '</span></div>';
            }).join('') + '</div>';
        }

        var paymentOrdersHtml = '';
        if (!paymentResult.success || !paymentResult.orders || paymentResult.orders.length === 0) {
            paymentOrdersHtml = '<p class="text-muted mt-3">暂无余额流水记录</p>';
        } else {
            var sortedPaymentOrders = paymentResult.orders.slice().sort(function(a, b) {
                return Number(b.created_at || 0) - Number(a.created_at || 0);
            });
            paymentOrdersHtml = '<div class="mt-3">' + sortedPaymentOrders.map(function(o) {
                return '<div class="d-flex justify-content-between align-items-center py-2 border-bottom"><span>' + Security.escapeHtml(paymentOrderTitle(o)) + '</span><span class="badge badge-' + paymentOrderStatusClass(o) + '">' + Security.escapeHtml(paymentOrderStatusText(o)) + '</span><span class="text-muted small">' + Utils.formatDate(o.created_at) + '</span></div>';
            }).join('') + '</div>';
        }

        var paymentConfigsHtml = '';
        if (!configsResult.success || !configsResult.configs || configsResult.configs.length === 0) {
            paymentConfigsHtml = '<p class="text-muted text-center py-3">暂无可使用的支付方式</p>';
        } else {
            paymentConfigsHtml = '<div class="row g-2">' + configsResult.configs.map(function(c) {
                return '<div class="col-12"><div class="card payment-method-card" style="cursor: pointer;"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><h6 class="fw-bold mb-1">' + Security.escapeHtml(c.name) + '</h6>' + (c.fee_rate > 0 ? '<small class="text-muted">手续费: ' + (c.fee_rate * 100).toFixed(1) + '%</small>' : '') + '</div><i class="bi bi-credit-card-2-back" style="font-size: 1.5rem; color: var(--primary-accent);"></i></div></div></div></div>';
            }).join('') + '</div>';
        }

        // 会员等级卡片
        var membershipCardHtml = '<div class="card mb-4" style="background: ' + Security.escapeAttr(levelInfo.gradient || 'linear-gradient(135deg, #6c757d 0%, #495057 100%)') + '; color: white;">' +
            '<div class="card-body">' +
                '<div class="d-flex justify-content-between align-items-center">' +
                    '<div>' +
                        '<h5 class="fw-bold mb-1"><i class="bi ' + Security.escapeAttr(levelInfo.icon || 'bi-person') + ' me-2"></i>' + Security.escapeHtml(myLevel) + '</h5>' +
                        '<small>手续费: ' + (Number(levelInfo.fee_rate || 0) * 100).toFixed(1) + '%</small>' +
                    '</div>' +
                    '<div class="text-end">' +
                        '<button class="btn btn-sm btn-light" onclick="window.location.hash = \'#membership\'; window.renderDashboardTab && window.renderDashboardTab(\'membership\');">' +
                            '<i class="bi bi-gem me-1"></i>升级会员' +
                        '</div>' +
            '</div>' +
        '</div>';

        // 管理员功能已移至后台，前端控制台不再显示

        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-wallet2 me-2"></i>财务中心</h5>' +
            membershipCardHtml +
            '<div class="card bg-light mb-4"><div class="card-body text-center py-4">' +
                '<div class="d-flex justify-content-center gap-4 flex-wrap mb-2">' +
                    '<div><div class="text-muted small">可用余额</div><h2 class="fw-bold text-primary mb-0">¥ ' + (App.currentUser ? App.currentUser.balance.toFixed(2) : '0.00') + '</h2></div>' +
                    '<div><div class="text-muted small">冻结余额</div><h2 class="fw-bold text-warning mb-0">¥ ' + Number(App.currentUser ? (App.currentUser.frozen_balance || 0) : 0).toFixed(2) + '</h2></div>' +
                '</div>' +
                '<p class="text-muted mb-3">冻结余额来自处理中投诉或待处理资金，不可提现</p>' +
                '<div class="d-flex gap-2 justify-content-center flex-wrap">' +
                    '<button class="btn btn-primary" onclick="window.openOnlineRechargeModal()"><i class="bi bi-cash-stack me-1"></i>在线充值</button>' +
                    '<button class="btn btn-success" onclick="window.openCardRechargeModal()"><i class="bi bi-credit-card-2-front me-1"></i>卡密充值</button>' +
                    (enableWithdraw ? '<button class="btn btn-warning" onclick="window.openWithdrawModal()"><i class="bi bi-box-arrow-up me-1"></i>申请提现</button>' : '') +
                '</div>' +
            '</div></div>' +
            '<h6 class="fw-bold mb-3"><i class="bi bi-list-ul me-1"></i>充值 / 提现记录</h6>' + requestsHtml +
            '<h6 class="fw-bold mb-3 mt-4"><i class="bi bi-clock-history me-1"></i>余额流水</h6>' + paymentOrdersHtml +
            '<h6 class="fw-bold mb-3 mt-4"><i class="bi bi-credit-card me-1"></i>可用支付方式</h6>' + paymentConfigsHtml;
    };

    function paymentOrderTitle(order) {
        var type = String(order.type || '').trim();
        var amount = Number(order.amount || 0);
        var absAmount = Math.abs(amount).toFixed(2);
        var titleMap = {
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
        var label = titleMap[type] || (amount < 0 ? '消费支出' : '余额收入');
        var sign = amount < 0 ? '-' : '';
        return label + ' ' + sign + '¥' + absAmount;
    }

    function paymentOrderStatusText(order) {
        var status = String(order.status || 'pending');
        var type = String(order.type || '');
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

    function paymentOrderStatusClass(order) {
        var status = String(order.status || 'pending');
        if ((order.delivery_status || '') === 'failed') return 'danger';
        if (String(order.type || '') === 'product_purchase_refund') return 'success';
        if (status === 'paid') return 'success';
        if (status === 'pending') return 'warning';
        return 'danger';
    }

    /**
     * 批准请求（管理员）
     */
    window.approveRequest = async function(id) {
        var API = window.API;
        if (!API) return;

        var note = prompt('请输入处理备注（可选）:', '');
        if (note === null) return;

        var result = await API.approveRequest(id, note);
        if (result.success) {
            window.Toast && window.Toast.success('已通过');
            window.renderDashboardTab && window.renderDashboardTab('balance');
        } else {
            window.Toast && window.Toast.error(result.message);
        }
    };

    /**
     * 拒绝请求（管理员）
     */
    window.rejectRequest = async function(id) {
        var API = window.API;
        if (!API) return;

        var note = prompt('请输入拒绝原因:', '');
        if (note === null) return;

        var result = await API.rejectRequest(id, note);
        if (result.success) {
            window.Toast && window.Toast.success('已拒绝');
            window.renderDashboardTab && window.renderDashboardTab('balance');
        } else {
            window.Toast && window.Toast.error(result.message);
        }
    };

})();
