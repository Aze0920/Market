/**
 * Dashboard - 个人中心模块
 */
(function() {
    'use strict';

    // 模块级状态
    var profileSecurityUnlocked = false;
    var profileEmailVerifyPending = false;
    var profilePaymentInitiallyConfigured = false;
    var merchantAgreementTimer = null;
    var merchantAgreementReadReady = false;

    /**
     * 渲染个人中心 Tab
     */
    window.render_profile_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var App = deps.App;
        var Security = deps.Security;
        var Utils = deps.Utils;

        // 重置模块状态
        profileSecurityUnlocked = false;
        profileEmailVerifyPending = false;
        profilePaymentInitiallyConfigured = false;

        var user = App.currentUser || {};
        var maskedEmail = user.email ? user.email.replace(/^(.{2}).*(@.*)$/, '$1****$2') : '未绑定邮箱';
        var qqBound = !!user.qq_bound;
        var merchant = merchantStatusInfo(user);
        var merchantVerified = merchant.ok;
        var paymentMethods = getUserPaymentMethods(user);

        // 管理员配置已移至后台，前端控制台不再显示

        // 商铺设置快捷入口
        var shopSettingsHtml = '<div class="col-12"><div class="profile-card-soft"><div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="fw-bold mb-1"><i class="bi bi-shop me-2 text-primary"></i>商铺设置</h6><div class="text-muted small">自定义商铺名称、公告和样式</div></div><button class="btn btn-sm btn-outline-primary" onclick="window.renderDashboardTab && window.renderDashboardTab(\'shop\')">详细设置</button></div><div class="row g-3"><div class="col-md-6"><div class="form-label">商铺名称</div><div class="fw-semibold">' + Security.escapeHtml(user.shop_name || '未设置') + '</div></div><div class="col-md-6"><div class="form-label">商铺公告</div><div class="fw-semibold">' + (user.shop_announcement ? Security.escapeHtml(user.shop_announcement.substring(0, 50)) + (user.shop_announcement.length > 50 ? '...' : '') : '未设置') + '</div></div></div></div></div>';

        // 自定义标签快捷入口
        var customLabelHtml = '<div class="col-12"><div class="profile-card-soft"><div class="d-flex justify-content-between align-items-center mb-3"><div><h6 class="fw-bold mb-1"><i class="bi bi-tags me-2 text-primary"></i>自定义标签</h6><div class="text-muted small">设置个性化用户标签</div></div><button class="btn btn-sm btn-outline-primary" onclick="window.renderDashboardTab && window.renderDashboardTab(\'customlabel\')">详细设置</button></div><div class="row g-3"><div class="col-md-4"><div class="form-label">标签文字</div><div class="fw-semibold">' + Security.escapeHtml(user.custom_label_text || '未设置') + '</div></div><div class="col-md-4"><div class="form-label">标签图标</div><div class="fw-semibold"><i class="bi ' + Security.escapeAttr(user.custom_label_icon || 'bi-tag') + '"></i> ' + Security.escapeHtml(user.custom_label_icon || '未设置') + '</div></div><div class="col-md-4"><div class="form-label">标签渐变</div><div class="fw-semibold">' + (user.custom_label_gradient ? '已设置' : '未设置') + '</div></div></div></div></div>';

        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-person-circle me-2 text-primary"></i>个人中心</h5><div class="row g-4"><div class="col-lg-5"><div class="profile-card-soft h-100"><div class="d-flex align-items-center gap-3 mb-4"><div class="profile-avatar-wrap"><label class="profile-avatar-upload" for="profileAvatarInput" title="点击上传头像">' + avatarHtml(user, 'profile-main-avatar') + '<span class="profile-avatar-camera"><i class="bi bi-camera-fill"></i></span></label><input type="file" id="profileAvatarInput" accept="image/*" class="hidden" onchange="window.handleAvatarSelect(event)"><button class="btn btn-sm btn-outline-primary mt-2" onclick="document.getElementById(\'profileAvatarInput\').click()"><i class="bi bi-upload me-1"></i>上传头像</button><small class="text-muted d-block mt-1">不上传则使用默认头像</small></div><div><h5 class="fw-bold mb-1">' + Security.escapeHtml(user.username || '-') + '</h5><div class="text-muted small">' + Security.escapeHtml(maskedEmail) + '</div></div></div><div class="profile-info-row"><span>会员等级</span><strong>' + Security.escapeHtml(user.membership_level || 'Free') + '</strong></div><div class="profile-info-row"><span>账户余额</span><strong>¥ ' + Number(user.balance || 0).toFixed(2) + '</strong></div><div class="profile-info-row"><span>QQ 绑定</span><strong class="' + (qqBound ? 'text-success' : 'text-muted') + '">' + (qqBound ? Security.escapeHtml(user.qq_nickname || '已绑定') : '未绑定') + '</strong></div><div class="profile-info-row align-items-center gap-2 flex-wrap"><span>商家认证</span><div class="d-flex align-items-center gap-2 flex-wrap"><strong class="text-' + (merchant.badge === 'success' ? 'success' : merchant.badge === 'danger' ? 'danger' : 'warning') + '">' + Security.escapeHtml(merchant.label) + '</strong><button class="btn btn-sm ' + (merchantVerified ? 'btn-outline-primary' : 'btn-primary') + '" onclick="window.scrollToMerchantCertification()"><i class="bi ' + (merchantVerified ? 'bi-eye' : 'bi-shield-check') + ' me-1"></i>' + (merchantVerified ? '查看认证' : '去认证') + '</button></div></div><div class="mt-4 d-grid gap-2">' + (qqBound ? '<button class="btn btn-outline-danger" onclick="window.unbindQQAccount()"><i class="bi bi-link-45deg me-1"></i>解绑第三方账号</button>' : '<button class="btn btn-primary" onclick="window.bindQQAccount()"><i class="bi bi-tencent-qq me-1"></i>绑定第三方账号</button>') + '</div></div></div><div class="col-lg-7"><div class="profile-card-soft h-100"><h6 class="fw-bold mb-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i>个人资料</h6><div class="row g-3"><div class="col-md-6"><label class="form-label">用户名</label><input class="form-control" id="profileUsername" value="' + Security.escapeAttr(user.username || '') + '" placeholder="2-30个字符，支持中文"></div><div class="col-md-6"><label class="form-label">邮箱</label><input class="form-control" id="profileEmail" type="email" value="' + Security.escapeAttr(user.email || '') + '" placeholder="your@email.com"></div><div class="col-12"><button class="btn btn-primary" onclick="window.saveProfileInfo()"><i class="bi bi-check2-circle me-1"></i>保存个人资料</button></div></div><hr class="my-4"><h6 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-primary"></i>账号安全验证</h6><div class="alert alert-light border small mb-3">发送一次验证码即可在有效期内用于修改登录密码：<strong>' + Security.escapeHtml(maskedEmail) + '</strong></div><div class="row g-3"><div class="col-md-7"><label class="form-label">邮箱验证码</label><input class="form-control" id="profileEmailCode" maxlength="6" inputmode="numeric" placeholder="请输入 6 位验证码" oninput="window.handleProfileEmailCodeInput()"></div><div class="col-md-5 d-flex align-items-end"><button class="btn btn-outline-primary w-100" id="sendProfileEmailCodeBtn" onclick="window.sendProfileEmailCode()">发送验证码</button></div><div class="col-md-6"><label class="form-label">新密码</label><input class="form-control locked" id="profileNewPassword" type="password" placeholder="验证邮箱验证码后可输入新密码" disabled></div><div class="col-md-6"><label class="form-label">确认新密码</label><input class="form-control locked" id="profileConfirmPassword" type="password" placeholder="验证邮箱验证码后可确认新密码" disabled></div><div class="col-12"><button class="btn btn-primary" id="changeProfilePasswordBtn" onclick="window.changeProfilePassword()" disabled><i class="bi bi-check2-circle me-1"></i>确认修改密码</button></div></div></div></div>' + shopSettingsHtml + customLabelHtml + '<div class="col-12"><div class="profile-card-soft"><div class="d-flex justify-content-between align-items-center mb-3 gap-3 flex-wrap"><div><h6 class="fw-bold mb-1"><i class="bi bi-wallet2 me-2 text-primary"></i>收款方式</h6><div class="text-muted small">提现会直接使用这里配置的微信或支付宝收款信息</div></div><button class="btn btn-primary" id="savePaymentMethodsBtn" onclick="window.savePaymentMethods()"><i class="bi bi-check2-circle me-1"></i>保存收款方式</button></div><div id="paymentMethodsNotice" class="payment-methods-notice hidden mb-3"></div><div class="payment-receive-grid">' + Object.entries(paymentMethods).map(function(entry) {
            var key = entry[0];
            var item = entry[1];
            return renderPaymentMethodUploadCard(key, item);
        }).join('') + '</div><div class="mt-4" id="merchantCertificationBox">' + renderMerchantCertificationBox(user) + '</div></div></div></div>';

        setTimeout(function() {
            if (typeof window.startMerchantReadTimer === 'function') window.startMerchantReadTimer();
        }, 50);
    };

    function avatarHtml(user, className) {
        var value = String(user.avatar || '').trim();
        var initial = Security.escapeHtml(String(user.username || 'U').charAt(0).toUpperCase());
        var classes = 'avatar ' + (className || '').trim();
        if (/^\/uploads\/avatars\/[a-zA-Z0-9_.-]+\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(value)) {
            return '<div class="' + Security.escapeAttr(classes) + ' has-image"><img src="' + Security.escapeAttr(value) + '" alt="头像" onerror="this.remove(); this.parentElement.classList.remove(\'has-image\'); this.parentElement.textContent=\'' + initial + '\';"></div>';
        }
        return '<div class="' + Security.escapeAttr(classes) + '">' + initial + '</div>';
    }

    function merchantStatusInfo(user) {
        user = user || {};
        var status = user.merchant_status || 'none';
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

    function getUserPaymentMethods(user) {
        user = user || {};
        var methods = user.payment_methods && typeof user.payment_methods === 'object' ? user.payment_methods : {};
        return {
            alipay: { label: '支付宝', account: methods.alipay && methods.alipay.account || '', qrcode: methods.alipay && methods.alipay.qrcode || '' },
            wechat: { label: '微信', account: methods.wechat && methods.wechat.account || '', qrcode: methods.wechat && methods.wechat.qrcode || '' }
        };
    }

    function paymentMethodIcon(key) {
        return key === 'wechat' ? 'bi-wechat' : 'bi-alipay';
    }

    function renderPaymentMethodUploadCard(key, item) {
        var hasQr = !!item.qrcode;
        var imageUrl = hasQr ? (item.qrcode + (item.qrcode.includes('?') ? '&' : '?') + 'v=' + Date.now()) : '';
        return '<div class="payment-receive-card" data-method="' + Security.escapeAttr(key) + '"><div class="payment-receive-head"><div class="payment-receive-title"><i class="bi ' + paymentMethodIcon(key) + '"></i>' + Security.escapeHtml(item.label) + '</div><span class="badge ' + (hasQr && item.account ? 'badge-success' : 'badge-warning') + '">' + (hasQr && item.account ? '已配置' : '待完善') + '</span></div><label class="form-label mt-3">收款账号</label><div class="payment-account-lock-wrap"><input class="form-control" id="paymentAccount_' + Security.escapeAttr(key) + '" value="' + Security.escapeAttr(item.account || '') + '" placeholder="填写' + Security.escapeAttr(item.label) + '账号/昵称"></div><label class="payment-upload-zone mt-3" for="paymentQrInput_' + Security.escapeAttr(key) + '" ondragover="window.handlePaymentQrDragOver(event)" ondragleave="window.handlePaymentQrDragLeave(event)" ondrop="window.handlePaymentQrDrop(event, \'' + Security.escapeAttr(key) + '\')"><input type="file" id="paymentQrInput_' + Security.escapeAttr(key) + '" accept="image/*" class="hidden" onchange="window.handlePaymentQrSelect(event, \'' + Security.escapeAttr(key) + '\')"><input type="hidden" id="paymentQr_' + Security.escapeAttr(key) + '" value="' + Security.escapeAttr(item.qrcode || '') + '"><div class="payment-upload-preview" id="paymentQrPreview_' + Security.escapeAttr(key) + '">' + (hasQr ? '<img src="' + Security.escapeAttr(imageUrl) + '" alt="' + Security.escapeAttr(item.label) + '收款码" onerror="window.handlePaymentQrImageError(this, \'' + Security.escapeAttr(item.label) + '\')"><div class="payment-upload-lock"><i class="bi bi-unlock-fill"></i>可重新上传</div>' : '<i class="bi bi-cloud-arrow-up"></i><span>点击或拖拽上传收款码</span>') + '</div></label></div>';
    }

    function renderMerchantCertificationBox(user) {
        user = user || {};
        var merchant = merchantStatusInfo(user);
        var openedOnce = user.merchant_opened_once === true || user.merchant_opened_once === '1';
        var qqBound = !!user.qq_bound;
        var saveText = merchant.ok ? '更新认证资料' : (openedOnce ? '提交重新开通审核' : '同意并开通商家');
        var blockHtml = qqBound ? '' : '<div class="alert alert-warning small mb-3"><i class="bi bi-tencent-qq me-1"></i>开通商家前必须先绑定 QQ，用于身份确认和后续风控。请先点击左侧个人信息中的"绑定第三方账号"。</div>';
        return '<div class="profile-card-soft border" style="box-shadow:none;" data-merchant-status="' + Security.escapeAttr(user.merchant_status || 'none') + '"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><h6 class="fw-bold mb-1"><i class="bi bi-shield-check me-2 text-primary"></i>商家开通认证</h6><div class="text-muted small">首次开通免审核；后续被取消后重新开通需后台审核。</div></div><span class="badge badge-' + merchant.badge + '">' + Security.escapeHtml(merchant.label) + '</span></div><div class="alert alert-light border small mb-3">' + Security.escapeHtml(merchant.desc) + '</div>' + blockHtml + '<label class="form-label fw-semibold">商家守则、免责声明与商家质保</label><textarea class="form-control" id="merchantAgreementText" rows="9" readonly onscroll="window.handleMerchantAgreementScroll()">一、商家守则\n1. 商家应保证发布的商品信息真实、完整、合法，不得发布违法违规、侵权、欺诈、虚假宣传或无法交付的商品。\n2. 商家应及时处理订单、发货、售后和用户咨询，不得恶意拖延、诱导站外交易或逃避平台规则。\n3. 商家应妥善保管收款账号和收款码，因资料错误导致的收款异常由商家自行承担。\n\n二、免责声明\n1. 商家确认已充分了解虚拟商品交易风险，并承诺自行承担因商品来源、授权、交付、售后等产生的责任。\n2. 因商家商品描述不清、违规发布、无法交付、售后拒绝处理等造成的纠纷、退款、赔付或法律责任，由商家自行承担。\n3. 平台可根据投诉、风控或监管要求对商品、订单、资金和商家资格采取限制、冻结、下架或关闭等必要措施。\n\n三、商家质保\n1. 商家承诺对所售商品提供明确、有效的质量保障和售后说明，并按承诺处理补发、换货、退款或技术支持。\n2. 如商品存在不可用、与描述不符、重复销售、失效等问题，商家应优先保障买家权益并积极配合平台处理。\n3. 商家连续出现高投诉、拒不售后或严重违规时，平台有权取消商家资格，后续重新开通需人工审核。\n\n四、开通确认\n本人确认已阅读并同意以上商家守则、免责声明与商家质保，自愿申请开通商家功能，并承诺遵守平台全部规则。</textarea><div class="form-text" id="merchantReadTimerText">请至少阅读 5 秒后再勾选同意。</div><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="merchantRulesAccepted"' + (user.merchant_rules_accepted ? ' checked' : '') + (user.merchant_rules_accepted ? '' : ' disabled') + '><label class="form-check-label" for="merchantRulesAccepted">我已阅读并同意商家守则、免责声明与商家质保，申请开通商家功能</label></div><div class="mt-3 d-flex gap-2 flex-wrap"><button class="btn btn-primary" id="merchantCertificationSaveHintBtn" onclick="window.savePaymentMethods()"' + (qqBound ? '' : ' disabled title="请先绑定 QQ 后再开通商家"') + '><i class="bi bi-check2-circle me-1"></i>' + saveText + '</button></div></div>';
    }

    /**
     * 开始商家协议阅读计时器
     */
    window.startMerchantReadTimer = function() {
        var user = window.App && window.App.currentUser || {};
        merchantAgreementReadReady = !!(user.merchant_rules_accepted);
        if (merchantAgreementTimer) clearInterval(merchantAgreementTimer);
        var remaining = merchantAgreementReadReady ? 0 : 5;
        var text = document.getElementById('merchantReadTimerText');
        var checkbox = document.getElementById('merchantRulesAccepted');
        if (!text || !checkbox || merchantAgreementReadReady) return;
        text.textContent = '请至少阅读 ' + remaining + ' 秒后再勾选同意。';
        merchantAgreementTimer = setInterval(function() {
            remaining -= 1;
            if (remaining <= 0) {
                merchantAgreementReadReady = true;
                checkbox.disabled = false;
                text.textContent = '已满足阅读时间，请勾选同意后提交。';
                clearInterval(merchantAgreementTimer);
                return;
            }
            text.textContent = '请至少阅读 ' + remaining + ' 秒后再勾选同意。';
        }, 1000);
    };

    window.handleMerchantAgreementScroll = function() {};

    // ===== Profile Security Functions =====

    function setProfileSecurityUnlocked(unlocked) {
        profileSecurityUnlocked = !!unlocked;
        ['profileNewPassword', 'profileConfirmPassword'].forEach(function(id) {
            var input = document.getElementById(id);
            if (input) {
                input.disabled = !profileSecurityUnlocked;
                input.classList.toggle('locked', !profileSecurityUnlocked);
            }
        });
        var passwordBtn = document.getElementById('changeProfilePasswordBtn');
        if (passwordBtn) passwordBtn.disabled = !profileSecurityUnlocked;
    }

    window.handleProfileEmailCodeInput = function() {
        var input = document.getElementById('profileEmailCode');
        var code = (input && input.value || '').replace(/\D/g, '').slice(0, 6);
        if (input && input.value !== code) input.value = code;
        if (code.length < 6) {
            if (profileSecurityUnlocked) setProfileSecurityUnlocked(false);
            return;
        }
        window.Toast && window.Toast.info('正在验证邮箱验证码...');
        window.verifyProfileEmailCodeAndUnlock(code);
    };

    window.verifyProfileEmailCodeAndUnlock = async function(code) {
        var API = window.API;
        if (!API || profileEmailVerifyPending || profileSecurityUnlocked) return;

        profileEmailVerifyPending = true;
        var input = document.getElementById('profileEmailCode');
        if (input) input.classList.add('is-validating');
        var result = await API.verifyProfileEmailCode(code);
        profileEmailVerifyPending = false;
        if (input) input.classList.remove('is-validating');
        if (!result.success) {
            setProfileSecurityUnlocked(false);
            window.Toast && window.Toast.error(result.message || '验证码校验失败，请重新输入');
            return;
        }
        setProfileSecurityUnlocked(true);
        window.Toast && window.Toast.success(result.message || '验证通过，已解锁修改密码');
    };

    window.sendProfileEmailCode = async function() {
        var API = window.API;
        if (!API) return;

        var profileEmailCountdown = 0;
        var profileEmailTimer = null;
        var btn = document.getElementById('sendProfileEmailCodeBtn');
        var oldText = btn && btn.textContent || '发送验证码';

        if (profileEmailCountdown > 0) return;
        if (window.API && typeof window.API.captchaDebug === 'function') window.API.captchaDebug('profile_send_click').catch(function() {});

        if (btn) {
            btn.disabled = true;
            btn.textContent = '验证中...';
        }
        var result;
        try {
            if (typeof window.runCaptcha !== 'function') {
                throw new Error('人机验证脚本未加载，请强制刷新页面后重试');
            }
            var captchaToken = await window.runCaptcha('email_code', true);
            if (btn) btn.textContent = '发送中...';
            result = await API.sendProfileEmailCode(captchaToken);
        } catch (error) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = oldText;
            }
            if (error && error.message === 'captcha_cancelled') {
                window.Toast && window.Toast.warning('已取消人机验证');
            } else {
                window.Toast && window.Toast.error(error && error.message || '人机验证失败，请重试');
            }
            return;
        }

        if (!result.success) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = oldText;
            }
            window.Toast && window.Toast.error(result.message || '验证码发送失败');
            return;
        }

        window.Toast && window.Toast.success(result.message || '验证码已发送');
        profileEmailCountdown = 60;
        if (btn) {
            btn.disabled = true;
            var updateBtn = function() {
                if (profileEmailCountdown > 0) {
                    btn.textContent = profileEmailCountdown + '秒后重发';
                    profileEmailCountdown -= 1;
                } else {
                    btn.disabled = false;
                    btn.textContent = '发送验证码';
                    clearInterval(profileEmailTimer);
                }
            };
            updateBtn();
            profileEmailTimer = setInterval(updateBtn, 1000);
        }
    };

    window.saveProfileInfo = async function() {
        var API = window.API;
        var App = window.App;
        var Toast = window.Toast;
        if (!API) return;

        var username = document.getElementById('profileUsername') && document.getElementById('profileUsername').value.trim() || '';
        var email = document.getElementById('profileEmail') && document.getElementById('profileEmail').value.trim() || '';
        if (!username || !email) {
            Toast.warning('请填写用户名和邮箱');
            return;
        }
        var result = await API.updateProfile(username, email);
        if (!result.success) {
            Toast.error(result.message || '保存失败');
            return;
        }
        Toast.success(result.message || '个人资料已保存');
        if (result.user) App.setUser(result.user);
        window.renderDashboardTab && window.renderDashboardTab('profile');
    };

    window.changeProfilePassword = async function() {
        var API = window.API;
        var Toast = window.Toast;
        if (!API) return;

        var code = document.getElementById('profileEmailCode') && document.getElementById('profileEmailCode').value.trim() || '';
        var pwd = document.getElementById('profileNewPassword') && document.getElementById('profileNewPassword').value || '';
        var confirm = document.getElementById('profileConfirmPassword') && document.getElementById('profileConfirmPassword').value || '';
        if (!code) {
            Toast.warning('请先输入6位邮箱验证码完成解锁');
            return;
        }
        if (!profileSecurityUnlocked) {
            Toast.warning('验证码验证通过后才能修改密码');
            return;
        }
        if (!pwd || !confirm) {
            Toast.warning('请填写新密码和确认密码');
            return;
        }
        var result = await API.changePassword(code, pwd, confirm);
        if (!result.success) {
            Toast.error(result.message || '修改失败');
            return;
        }
        Toast.success(result.message || '密码修改成功');
        var emailInput = document.getElementById('profileEmailCode');
        var pwdInput = document.getElementById('profileNewPassword');
        var confirmInput = document.getElementById('profileConfirmPassword');
        if (emailInput) emailInput.value = '';
        if (pwdInput) pwdInput.value = '';
        if (confirmInput) confirmInput.value = '';
        setProfileSecurityUnlocked(false);
    };

    window.bindQQAccount = function() {
        if (typeof window.startOAuthLogin === 'function') {
            window.startOAuthLogin('qq', 'bind');
        }
    };

    window.unbindQQAccount = async function() {
        var API = window.API;
        var App = window.App;
        var Toast = window.Toast;
        if (!API) return;

        if (!confirm('确定要解绑 QQ 吗？解绑后不能使用该 QQ 一键登录此账号。')) return;
        var result = await API.unbindQQ();
        if (!result.success) {
            Toast.error(result.message || '解绑失败');
            return;
        }
        Toast.success(result.message || 'QQ 已解绑');
        if (result.user) App.setUser(result.user);
        window.renderDashboardTab && window.renderDashboardTab('profile');
    };

    window.scrollToMerchantCertification = function() {
        var target = document.getElementById('merchantCertificationBox') || document.querySelector('.payment-receive-card') || document.getElementById('savePaymentMethodsBtn');
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        window.Toast && window.Toast.info(window.App && window.App.currentUser && window.App.currentUser.qq_bound ? '请完成收款方式，并阅读同意商家守则声明后开通商家' : '请先绑定 QQ，绑定后才可申请开通商家');
    };

    // ===== Payment Method Functions =====

    window.handlePaymentQrDragOver = function(event) {
        event.preventDefault();
        event.currentTarget.classList.add('dragover');
    };

    window.handlePaymentQrDragLeave = function(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('dragover');
    };

    window.handlePaymentQrDrop = function(event, method) {
        event.preventDefault();
        event.currentTarget.classList.remove('dragover');
        var file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
        if (file) window.uploadPaymentQrFile(method, file);
    };

    window.handlePaymentQrSelect = function(event, method) {
        var file = event.target && event.target.files && event.target.files[0];
        if (file) window.uploadPaymentQrFile(method, file);
    };

    window.handlePaymentQrImageError = function(img, label) {
        label = label || '收款码';
        var preview = img && img.closest('.payment-upload-preview');
        if (!preview) return;
        preview.innerHTML = '<div class="payment-upload-error"><i class="bi bi-image-alt"></i><strong>' + Security.escapeHtml(label) + '图片不存在</strong><small>原收款码文件未找到，请验证后重新上传</small></div>';
    };

    window.uploadPaymentQrFile = async function(method, file) {
        var API = window.API;
        var Toast = window.Toast;
        if (!API) return;

        if (!file.type.startsWith('image/')) {
            Toast.warning('请选择图片文件');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            Toast.warning('图片大小不能超过2MB');
            return;
        }
        var accountValue = document.getElementById('paymentAccount_' + method) && document.getElementById('paymentAccount_' + method).value.trim() || '';
        if (!accountValue) {
            window.warnPaymentMethods && window.warnPaymentMethods('请先填写收款账号/昵称，再上传收款码图片');
            return;
        }
        var preview = document.getElementById('paymentQrPreview_' + method);
        var previousHtml = preview && preview.innerHTML || '<i class="bi bi-cloud-arrow-up"></i><span>点击或拖拽上传收款码</span>';
        if (preview) preview.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><span>上传中...</span>';

        var result;
        try {
            result = await API.uploadPaymentQrcode(file, method, '', accountValue);
        } catch (error) {
            var message = (error && error.message) || '收款码上传异常，请重试';
            Toast.error(message);
            window.showPaymentMethodsNotice && window.showPaymentMethodsNotice(message, 'error');
            if (preview) preview.innerHTML = '<div class="payment-upload-error"><i class="bi bi-exclamation-triangle"></i><strong>上传失败</strong><span>' + Security.escapeHtml(message) + '</span><div class="small text-muted mt-2">可重新选择图片再试</div></div>';
            return;
        }

        var qrInput = document.getElementById('paymentQrInput_' + method);
        if (qrInput) qrInput.value = '';
        if (!result.success) {
            var msg = result.message || '上传失败';
            Toast.error(msg);
            window.showPaymentMethodsNotice && window.showPaymentMethodsNotice(msg, 'error');
            if (preview) preview.innerHTML = '<div class="payment-upload-error"><i class="bi bi-exclamation-triangle"></i><strong>上传失败</strong><span>' + Security.escapeHtml(msg) + '</span><div class="small text-muted mt-2">可重新选择图片再试</div></div>';
            return;
        }
        var qrHidden = document.getElementById('paymentQr_' + method);
        if (qrHidden) qrHidden.value = result.url || '';
        if (preview) preview.innerHTML = '<img src="' + Security.escapeAttr((result.url || '') + '?v=' + Date.now()) + '" alt="收款码">';
        if (result.user) window.App.setUser(result.user);
        Toast.success(result.message || '上传成功');
    };

    window.showPaymentMethodsNotice = function(message, type) {
        type = type || 'info';
        var box = document.getElementById('paymentMethodsNotice');
        if (!box) return;
        var icon = type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill';
        box.className = 'payment-methods-notice ' + type + ' mb-3';
        box.innerHTML = '<i class="bi ' + icon + '"></i><span>' + Security.escapeHtml(message) + '</span>';
    };

    window.warnPaymentMethods = function(message) {
        window.showPaymentMethodsNotice && window.showPaymentMethodsNotice(message, 'error');
        window.Toast && window.Toast.warning(message);
    };

    window.savePaymentMethods = async function() {
        var API = window.API;
        var App = window.App;
        var Toast = window.Toast;
        if (!API) return;

        if (!App.currentUser || !App.currentUser.qq_bound) {
            window.warnPaymentMethods && window.warnPaymentMethods('请先绑定 QQ 后再申请开通商家');
            return;
        }
        var methods = getUserPaymentMethods();
        Object.keys(methods).forEach(function(key) {
            var accInput = document.getElementById('paymentAccount_' + key);
            var qrInput = document.getElementById('paymentQr_' + key);
            methods[key].account = accInput && accInput.value.trim() || '';
            methods[key].qrcode = qrInput && qrInput.value.trim() || '';
        });
        for (var key in methods) {
            var item = methods[key];
            if (!item.account || !item.qrcode) {
                window.warnPaymentMethods && window.warnPaymentMethods((item.label || key) + '需要同时填写收款账号并上传收款码图片后才能保存');
                return;
            }
        }
        var emailCode = document.getElementById('profileEmailCode') && document.getElementById('profileEmailCode').value.trim() || '';
        var merchantRulesAccepted = !!(document.getElementById('merchantRulesAccepted') && document.getElementById('merchantRulesAccepted').checked);
        if (!merchantRulesAccepted) {
            window.warnPaymentMethods && window.warnPaymentMethods('请先阅读商家守则满5秒，并勾选同意开通商家');
            return;
        }
        var btn = document.getElementById('savePaymentMethodsBtn');
        var oldHtml = btn && btn.innerHTML;
        if (btn) {
            btn.classList.add('disabled');
            btn.setAttribute('aria-disabled', 'true');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>保存中...';
        }
        window.showPaymentMethodsNotice && window.showPaymentMethodsNotice('正在保存收款方式...', 'info');
        var result = await API.savePaymentMethods(methods, emailCode, merchantRulesAccepted);
        if (btn) {
            btn.classList.remove('disabled');
            btn.setAttribute('aria-disabled', 'false');
            btn.innerHTML = oldHtml;
        }
        if (!result.success) {
            window.showPaymentMethodsNotice && window.showPaymentMethodsNotice(result.message || '保存失败', 'error');
            Toast.error(result.message || '保存失败');
            return;
        }
        window.showPaymentMethodsNotice && window.showPaymentMethodsNotice(result.message || '收款方式已保存', 'success');
        Toast.success(result.message || '收款方式已保存');
        if (result.user) App.setUser(result.user);
        setTimeout(function() {
            window.renderDashboardTab && window.renderDashboardTab('profile');
        }, 700);
    };

    window.handleAvatarSelect = async function(event) {
        var API = window.API;
        var App = window.App;
        var Toast = window.Toast;
        if (!API) return;

        var input = event.target;
        var file = input && input.files && input.files[0];
        if (!file) return;
        if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
            if (input) input.value = '';
            Toast.warning('头像仅支持 JPG、PNG、GIF、WEBP 图片');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            if (input) input.value = '';
            Toast.warning('头像大小不能超过2MB');
            return;
        }
        var btn = document.querySelector('.profile-avatar-wrap .btn');
        var oldHtml = btn && btn.innerHTML;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>上传中';
        }
        var result = await API.uploadAvatar(file);
        if (input) input.value = '';
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
        if (!result.success) {
            Toast.error(result.message || '头像上传失败');
            return;
        }
        if (result.user) App.setUser(result.user);
        Toast.success(result.message || '头像上传成功');
        window.renderDashboardTab && window.renderDashboardTab('profile');
    };

    window.saveSystemConfig = async function(options) {
        var API = window.API;
        var Toast = window.Toast;
        if (!API) return;

        options = options || {};
        var config = {};
        if (options.embedded) {
            config.enable_withdraw = !!(document.getElementById('configEnableWithdraw') && document.getElementById('configEnableWithdraw').checked);
            var minWithdraw = parseFloat((document.getElementById('configMinWithdraw') && document.getElementById('configMinWithdraw').value) || '10');
            var withdrawFee = parseFloat((document.getElementById('configWithdrawFee') && document.getElementById('configWithdrawFee').value) || '0');
            config.min_withdraw_amount = minWithdraw;
            config.withdraw_fee_rate = withdrawFee / 100;
            config.admin_wechat_qrcode = (document.getElementById('configWechatQrcode') && document.getElementById('configWechatQrcode').value) || '';
            config.admin_alipay_qrcode = (document.getElementById('configAlipayQrcode') && document.getElementById('configAlipayQrcode').value) || '';
        }
        var result = await API.updateSystemConfig(config);
        if (!result.success) {
            Toast.error(result.message || '保存失败');
            return;
        }
        Toast.success(result.message || '设置已保存');
    };

})();
