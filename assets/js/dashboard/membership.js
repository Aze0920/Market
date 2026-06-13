/**
 * Dashboard - 会员中心模块
 */
(function() {
    'use strict';

    /**
     * 渲染会员 Tab
     */
    window.render_membership_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var App = deps.App;
        var Security = deps.Security;

        var levelsResult = await API.getMembershipLevels();
        var myLevelResult = await API.getMyMembership();
        var configResult = await API.getSystemConfig();

        if (!levelsResult.success) {
            area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
            return;
        }

        var levels = levelsResult.levels || {};
        var levelList = Object.values(levels).filter(function(level) {
            return level.enabled !== false;
        }).sort(function(a, b) {
            return Number(a.priority || 0) - Number(b.priority || 0);
        });

        var myLevelName = myLevelResult.level || 'Free';
        var myLevel = myLevelResult.level_info || levels[myLevelName] || {};
        var currentPriority = Number(myLevel.priority || 0);
        var systemConfig = configResult.success ? (configResult.config || {}) : {};
        var showMembershipCardActivation = systemConfig.enable_membership_card_activation !== false && systemConfig.enable_membership_card_activation !== '0';

        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-gem me-2"></i>会员中心</h5><div class="membership-cards">' + (showMembershipCardActivation ? renderMembershipCardActivationCard() : '') + levelList.map(function(level) {
            var levelName = level.name || '';
            var levelPriority = Number(level.priority || 0);
            var isCurrentLevel = myLevelName === levelName;
            var isLowerLevel = !isCurrentLevel && myLevelName !== 'Free' && levelPriority < currentPriority;
            var cost = Number(level.cost || 0);
            var canAfford = !App.currentUser || Number(App.currentUser.balance || 0) >= cost;
            var levelGradient = level.gradient || 'linear-gradient(135deg, #6366f1 0%, #8b5cf6)';
            var levelIcon = level.icon || 'bi-gem';
            var maxAccountsText = Number(level.max_accounts_per_product || 0) === 0 ? '不限制' : level.max_accounts_per_product + ' 账号';
            var maxProductsText = Number(level.max_products || 0) === 0 ? '不限制' : level.max_products + ' 个商品';
            var levelPrivileges = [
                '单商品最大 ' + maxAccountsText,
                '最多商品 ' + maxProductsText,
                '手续费 ' + (Number(level.fee_rate || 0) * 100).toFixed(2).replace(/\.00$/, '') + '%',
                Number(level.publish_fee_per_account || 0) === 0 ? '售出不扣发布费' : '售出扣费 ¥' + level.publish_fee_per_account + '/账号'
            ];
            var footerHtml = isCurrentLevel
                ? '<span class="membership-status-text">当前会员</span>'
                : isLowerLevel
                    ? '<span class="membership-status-text text-muted">当前会员比此会员等级高，禁止升级</span>'
                    : level.can_upgrade === false
                        ? '<span class="membership-status-text text-muted">暂不支持开通</span>'
                        : '<button class="btn btn-primary w-100" onclick="window.upgradeMembership(\'' + Security.escapeAttr(levelName) + '\')">' + (cost === 0 ? '免费开通' : '立即开通') + '</button>';

            return '<div class="membership-card' + (isCurrentLevel ? ' current' : '') + '" style="--card-gradient: ' + Security.escapeAttr(levelGradient) + ';"><div class="card-header"><i class="bi ' + Security.escapeAttr(levelIcon) + '"></i><h5>' + Security.escapeHtml(levelName) + '</h5><small>' + Security.escapeHtml(level.description || levelName + '会员') + '</small>' + (isCurrentLevel ? '<span class="current-badge">当前会员</span>' : '') + '</div><div class="card-body"><div class="text-center mb-3">' + (cost === 0 ? '<span class="badge bg-success-light text-success fs-5"><i class="bi bi-gift"></i> 免费</span>' : '<span class="badge bg-primary-light text-primary fs-5"><i class="bi bi-cash"></i> ¥' + cost.toFixed(2) + '</span>') + '</div><ul class="privilege-list">' + levelPrivileges.map(function(p) {
                return '<li><i class="bi bi-check"></i> ' + Security.escapeHtml(p) + '</li>';
            }).join('') + '</ul></div><div class="card-footer">' + footerHtml + '</div></div>';
        }).join('') || '<div class="text-muted py-4">暂无会员等级</div></div>';
    };

    function renderMembershipCardActivationCard() {
        return '<div class="membership-card membership-activation-card" style="--card-gradient: linear-gradient(135deg, #111827 0%, #4f46e5 55%, #06b6d4 100%);"><div class="card-header"><i class="bi bi-credit-card-2-front"></i><h5>卡密激活会员</h5><small>使用会员卡密快速开通权益</small></div><div class="card-body"><div class="text-center mb-3"><span class="badge bg-primary-light text-primary fs-5"><i class="bi bi-key"></i> 输入卡密</span></div><ul class="privilege-list"><li><i class="bi bi-check"></i> 支持后台生成的会员卡密</li><li><i class="bi bi-check"></i> 兑换成功后自动刷新会员等级</li><li><i class="bi bi-check"></i> 独立激活入口，不占用会员等级配置</li><li><i class="bi bi-check"></i> Free 为默认会员，不支持生成激活卡</li></ul></div><div class="card-footer"><button class="btn btn-primary w-100" onclick="window.openCardRechargeModal(\'membership\')"><i class="bi bi-lightning-charge me-1"></i>立即激活</button></div></div>';
    }

    /**
     * 升级会员
     */
    window.upgradeMembership = async function(levelName) {
        var API = window.API;
        var App = window.App;
        var Toast = window.Toast;
        if (!API) return;

        var levelsResult = await API.getMembershipLevels();
        var myLevelResult = await API.getMyMembership();
        var configsResult = await API.getPaymentConfigs();
        var userResult = await API.getCurrentUser();

        if (!levelsResult.success) {
            Toast.error('加载会员信息失败');
            return;
        }

        if (userResult.success && userResult.logged_in) {
            App.setUser(userResult.user);
        }

        var level = (levelsResult.levels || {})[levelName];
        if (!level) {
            Toast.error('会员等级不存在');
            return;
        }

        var cost = Number(level.cost || 0);
        var balance = Number(App.currentUser ? App.currentUser.balance : 0) || 0;
        var canUseBalance = balance >= cost;
        var membershipPaymentConfigs = configsResult.success ? (configsResult.configs || []) : [];
        var firstPaymentConfig = membershipPaymentConfigs[0] || null;
        window.selectedMembershipPaymentConfig = firstPaymentConfig;
        window.selectedMembershipPayType = firstPaymentConfig ? (firstPaymentConfig.pay_methods || ['alipay', 'wxpay'])[0] : '';
        var paymentButtonItems = [];
        membershipPaymentConfigs.forEach(function(c) {
            (c.pay_methods || ['alipay', 'wxpay']).forEach(function(method) {
                paymentButtonItems.push({ config: c, method: method });
            });
        });
        var paymentOptionsHtml = paymentButtonItems.length === 0
            ? '<div class="alert alert-warning mb-0"><i class="bi bi-exclamation-triangle me-1"></i>后台暂未配置可用在线支付接口</div>'
            : '<div class="row g-2 mb-0">' + paymentButtonItems.map(function(item) {
                var c = item.config;
                var method = item.method;
                var selected = window.selectedMembershipPaymentConfig && window.selectedMembershipPaymentConfig.id === c.id && window.selectedMembershipPayType === method;
                return '<div class="col-6"><div class="card membership-payment-select-card' + (selected ? ' border-primary' : '') + '" data-config-id="' + Security.escapeAttr(c.id) + '" data-pay-type="' + Security.escapeAttr(method) + '" onclick="window.selectMembershipPayButton(\'' + Security.escapeAttr(c.id) + '\', \'' + Security.escapeAttr(method) + '\')" style="cursor: pointer;"><div class="card-body py-3"><div class="d-flex justify-content-between align-items-center"><div><h6 class="fw-bold mb-1">' + membershipPayLabel(method) + '</h6><small class="text-muted">' + (Number(c.fee_rate || 0) > 0 ? '手续费 ' + (Number(c.fee_rate || 0) * 100).toFixed(1) + '%' : '在线支付') + '</small></div>' + (selected ? '<i class="bi bi-check-circle text-primary" style="font-size: 1.5rem;"></i>' : '') + '</div></div></div></div>';
            }).join('') + '</div>';

        var selectedPayMethod = await new Promise(function(resolve) {
            var modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            document.getElementById('confirmModalTitle').textContent = '开通 ' + levelName + ' 会员';
            document.getElementById('confirmModalBody').innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>重要提醒：</strong><ul class="mb-0 mt-2"><li>升级到 ' + Security.escapeHtml(levelName) + ' 后无法降级</li><li>升级到 ' + Security.escapeHtml(levelName) + ' 后无法更换为更低等级</li><li>此操作不可撤销</li></ul></div><div class="card bg-light mb-3"><div class="card-body py-3"><div class="d-flex justify-content-between"><span>应付金额</span><strong>¥' + cost.toFixed(2) + '</strong></div><div class="d-flex justify-content-between text-muted small mt-1"><span>当前余额</span><span>¥' + balance.toFixed(2) + '</span></div></div></div><div class="mb-3"><label class="form-label fw-bold">请选择支付方式</label><div class="form-check mb-2"><input class="form-check-input" type="radio" name="membershipPayMethod" id="membershipPayBalance" value="balance"' + (canUseBalance ? ' checked' : '') + (canUseBalance ? '' : ' disabled') + '><label class="form-check-label" for="membershipPayBalance">余额支付</label></div><div class="form-check"><input class="form-check-input" type="radio" name="membershipPayMethod" id="membershipPayOnline" value="online"' + (canUseBalance ? '' : ' checked') + (membershipPaymentConfigs.length === 0 ? ' disabled' : '') + '><label class="form-check-label" for="membershipPayOnline">在线支付</label></div></div><div id="membershipOnlinePaymentBox">' + paymentOptionsHtml + '</div>';

            var updateOnlineBox = function() {
                var selected = document.querySelector('input[name="membershipPayMethod"]:checked');
                var box = document.getElementById('membershipOnlinePaymentBox');
                if (box) box.style.display = selected && selected.value === 'online' ? 'block' : 'none';
            };
            document.querySelectorAll('input[name="membershipPayMethod"]').forEach(function(input) {
                input.addEventListener('change', updateOnlineBox);
            });
            var confirmBtn = document.getElementById('confirmModalBtn');
            confirmBtn.textContent = cost === 0 ? '确认开通' : '确认支付';
            confirmBtn.onclick = function() {
                var payMethodInput = document.querySelector('input[name="membershipPayMethod"]:checked');
                var payMethod = payMethodInput ? payMethodInput.value : '';
                if (!payMethod) {
                    Toast.warning('请选择支付方式');
                    return;
                }
                modal.hide();
                resolve(payMethod);
            };
            document.getElementById('confirmModalCancelBtn').onclick = function() {
                modal.hide();
                resolve(false);
            };
            modal.show();
            updateOnlineBox();
        });

        if (!selectedPayMethod) return;

        if (selectedPayMethod === 'online') {
            if (!window.selectedMembershipPaymentConfig) {
                Toast.warning('请选择支付接口');
                return;
            }
            var payType = window.selectedMembershipPayType || (window.selectedMembershipPaymentConfig.pay_methods || ['alipay'])[0];
            var result = await API.request('payment.php?action=create_membership_order', 'POST', {
                payment_config_id: window.selectedMembershipPaymentConfig.id,
                level: levelName,
                pay_type: payType
            });
            if (result.success) {
                window.showQrPaymentModal && window.showQrPaymentModal(result, {
                    payType: payType,
                    type: 'membership',
                    methodLabel: membershipPayLabel(payType),
                    successMessage: '支付成功，会员已开通，页面即将刷新'
                });
            } else {
                Toast.error(result.message || '创建支付订单失败');
            }
            return;
        }

        var result = await API.request('membership.php?action=upgrade', 'POST', {
            level: levelName,
            confirmed: 1,
            pay_method: 'balance'
        });
        if (result.success) {
            Toast.success(result.message);
            window.refreshUserData && window.refreshUserData();
            window.renderDashboardTab && window.renderDashboardTab('membership');
        } else {
            Toast.error(result.message || '余额支付失败');
        }
    };

    function membershipPayLabel(method) {
        var map = { alipay: '支付宝', wxpay: '微信支付', qqpay: 'QQ钱包', cashier: '易支付收银台' };
        return map[method] || method;
    }

    window.selectMembershipPayButton = function(configId, payType) {
        var membershipPaymentConfigs = window.membershipPaymentConfigs || [];
        window.selectedMembershipPaymentConfig = membershipPaymentConfigs.find(function(c) {
            return c.id === configId;
        }) || null;
        window.selectedMembershipPayType = payType;
        document.querySelectorAll('.membership-payment-select-card').forEach(function(card) {
            card.classList.remove('border-primary');
            var checkIcon = card.querySelector('.bi-check-circle');
            if (checkIcon) checkIcon.remove();
        });
        var selectedCard = Array.from(document.querySelectorAll('.membership-payment-select-card')).find(function(card) {
            return card.dataset.configId === configId && card.dataset.payType === payType;
        });
        if (selectedCard) {
            selectedCard.classList.add('border-primary');
            var icon = document.createElement('i');
            icon.className = 'bi bi-check-circle text-primary';
            icon.style.fontSize = '1.5rem';
            var bodyDiv = selectedCard.querySelector('.card-body .d-flex');
            if (bodyDiv) bodyDiv.appendChild(icon);
        }
    };

})();
