/**
 * 认证模块
 * 此文件依赖于 app.js 中定义的全局对象
 * 备用定义仅在需要时使用
 */

// 备用定义（如果app.js中的定义不存在）
(function() {
    // 确保 Security 对象存在
    if (typeof window.Security === 'undefined') {
        window.Security = {
            escapeHtml: function(str) {
                if (str === null || str === undefined) return '';
                var div = document.createElement('div');
                div.textContent = String(str);
                return div.innerHTML;
            },
            escapeAttr: function(attr) {
                if (attr === null || attr === undefined) return '';
                return String(attr).replace(/"/g, '&quot;').replace(/'/g, '&#x27;');
            }
        };
    }
    
    // 确保 Utils 对象存在
    if (typeof window.Utils === 'undefined') {
        window.Utils = {
            formatDate: function(timestamp) {
                var date = new Date(timestamp * 1000);
                return date.getFullYear() + '-' + 
                       String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                       String(date.getDate()).padStart(2, '0') + ' ' + 
                       String(date.getHours()).padStart(2, '0') + ':' + 
                       String(date.getMinutes()).padStart(2, '0');
            },
            copyText: function(text) {
                navigator.clipboard.writeText(text).then(function() {
                    if (window.Toast) window.Toast.success('已复制');
                }).catch(function() {});
            }
        };
    }
    
    // 确保 Toast 对象存在
    if (typeof window.Toast === 'undefined') {
        window.Toast = {
            container: null,
            initialized: false,
            init: function() {
                if (this.initialized) return;
                var container = document.getElementById('toastContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toastContainer';
                    container.className = 'toast-container';
                    document.body.appendChild(container);
                }
                this.container = container;
                this.initialized = true;
            },
            show: function(message, type) {
                type = type || 'info';
                this.init();
                var icons = {
                    success: 'bi-check-circle-fill',
                    error: 'bi-x-circle-fill',
                    warning: 'bi-exclamation-triangle-fill',
                    info: 'bi-info-circle-fill'
                };
                var toast = document.createElement('div');
                toast.className = 'toast toast-' + type;
                toast.innerHTML = '<i class="bi ' + icons[type] + ' toast-icon"></i><span>' + (window.Security ? window.Security.escapeHtml(message) : message) + '</span>';
                this.container.appendChild(toast);
                setTimeout(function() {
                    toast.style.animation = 'slideOut 0.3s ease forwards';
                    setTimeout(function() { toast.remove(); }, 300);
                }, 3000);
            },
            success: function(message) { this.show(message, 'success'); },
            error: function(message) { this.show(message, 'error'); },
            warning: function(message) { this.show(message, 'warning'); },
            info: function(message) { this.show(message, 'info'); }
        };
    }
    
    // 确保 App 对象存在
    if (typeof window.App === 'undefined') {
        window.App = {
            currentUser: null,
            currentPage: 'home',
            currentTab: 'overview',
            currentChatPartner: null,
            currentDetailProduct: null,
            products: [],
            setUser: function(user) { this.currentUser = user; this.updateNavUI(); },
            clearUser: function() { this.currentUser = null; this.updateNavUI(); },
            updateNavUI: function() {
                var guestArea = document.getElementById('navGuestArea');
                var userArea = document.getElementById('navUserArea');
                var dashboardLink = document.getElementById('navDashboardLink');
                var publishLink = document.getElementById('navSellLink');
                if (this.currentUser) {
                    if (guestArea) guestArea.classList.add('hidden');
                    if (userArea) userArea.classList.remove('hidden');
                    if (dashboardLink) dashboardLink.classList.remove('hidden');
                    if (publishLink) publishLink.classList.remove('hidden');
                    var navUsername = document.getElementById('navUsername');
                    var navAvatar = document.getElementById('navAvatar');
                    var navBalance = document.getElementById('navBalance');
                    if (navUsername) navUsername.textContent = (window.Security ? window.Security.escapeHtml(this.currentUser.username) : this.currentUser.username);
                    if (navAvatar) navAvatar.textContent = this.currentUser.username.charAt(0).toUpperCase();
                    if (navBalance) navBalance.textContent = '¥ ' + this.currentUser.balance.toFixed(2);
                } else {
                    if (guestArea) guestArea.classList.remove('hidden');
                    if (userArea) userArea.classList.add('hidden');
                    if (dashboardLink) dashboardLink.classList.add('hidden');
                    if (publishLink) publishLink.classList.add('hidden');
                }
            },
            updateUnreadBadge: async function() {
                var badge = document.getElementById('unreadBadge');
                if (!this.currentUser) {
                    if (badge) badge.classList.add('hidden');
                    return;
                }
                var result = await API.getUnreadCount();
                if (result.success && result.unread > 0) {
                    if (badge) {
                        badge.textContent = result.unread;
                        badge.classList.remove('hidden');
                    }
                } else {
                    if (badge) badge.classList.add('hidden');
                }
            }
        };
    }
})();

let keynestCaptchaConfig = null;
let keynestCaptchaScriptLoading = null;
let keynestCaptchaScriptProvider = '';

function captchaDebugLog(step, provider = '', message = '') {
    try {
        console.info('[KeyNest Captcha]', step, provider, message || '');
        if (window.API && typeof API.captchaDebug === 'function') {
            API.captchaDebug(step, provider, message || '').catch(() => {});
        }
    } catch (error) {}
}

async function getKeynestCaptchaConfig() {
    if (keynestCaptchaConfig) return keynestCaptchaConfig;
    captchaDebugLog('config_request_start');
    const result = await API.getCaptchaConfig();
    keynestCaptchaConfig = result.success ? (result.captcha || {}) : {};
    captchaDebugLog('config_request_finish', keynestCaptchaConfig.provider || '', JSON.stringify({ success: !!result.success, enabled: !!keynestCaptchaConfig.enabled, has_site_key: !!keynestCaptchaConfig.site_key }));
    return keynestCaptchaConfig;
}

function loadTurnstileScript() {
    if (window.turnstile) return Promise.resolve();
    if (keynestCaptchaScriptLoading && keynestCaptchaScriptProvider === 'turnstile') return keynestCaptchaScriptLoading;
    keynestCaptchaScriptProvider = 'turnstile';
    keynestCaptchaScriptLoading = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        script.onload = resolve;
        script.onerror = () => reject(new Error('人机验证组件加载失败，请检查网络或浏览器拦截'));
        document.head.appendChild(script);
    });
    return keynestCaptchaScriptLoading;
}

function loadGeetestScript() {
    if (window.initGeetest) {
        captchaDebugLog('geetest_script_already_loaded', 'geetest_v3');
        return Promise.resolve();
    }
    if (keynestCaptchaScriptLoading && keynestCaptchaScriptProvider === 'geetest_v3') return keynestCaptchaScriptLoading;
    keynestCaptchaScriptProvider = 'geetest_v3';
    captchaDebugLog('geetest_script_load_start', 'geetest_v3');
    keynestCaptchaScriptLoading = new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-keynest-captcha="geetest_v3"]');
        if (existing) existing.remove();
        const script = document.createElement('script');
        script.src = 'https://static.geetest.com/static/tools/gt.js';
        script.async = true;
        script.dataset.keynestCaptcha = 'geetest_v3';
        const timer = window.setTimeout(() => {
            captchaDebugLog('geetest_script_timeout', 'geetest_v3');
            reject(new Error('极验组件加载超时，请检查网络或浏览器拦截'));
        }, 10000);
        script.onload = () => {
            window.clearTimeout(timer);
            captchaDebugLog('geetest_script_load_success', 'geetest_v3');
            resolve();
        };
        script.onerror = () => {
            window.clearTimeout(timer);
            captchaDebugLog('geetest_script_load_error', 'geetest_v3');
            reject(new Error('极验组件加载失败，请检查网络或浏览器拦截'));
        };
        document.head.appendChild(script);
    });
    return keynestCaptchaScriptLoading;
}

function captchaOverlayHtml(widgetHtml = '<div id="keynestCaptchaWidget" class="captcha-widget"></div>') {
    return `
        <div class="captcha-card">
            <button type="button" class="captcha-close" aria-label="关闭">&times;</button>
            <div class="captcha-icon"><i class="bi bi-shield-check"></i></div>
            <h5>请先完成人机验证</h5>
            <p>验证通过后会继续当前操作。</p>
            ${widgetHtml}
        </div>
    `;
}

function createCaptchaOverlay(widgetHtml) {
    document.getElementById('keynestCaptchaOverlay')?.remove();
    const overlay = document.createElement('div');
    overlay.id = 'keynestCaptchaOverlay';
    overlay.className = 'captcha-overlay';
    overlay.innerHTML = captchaOverlayHtml(widgetHtml);
    document.body.appendChild(overlay);
    return overlay;
}

async function runTurnstileCaptcha(config) {
    await loadTurnstileScript();
    return new Promise((resolve, reject) => {
        const overlay = createCaptchaOverlay('<div id="keynestCaptchaWidget" class="captcha-widget"></div>');
        const close = (error) => {
            overlay.remove();
            if (error) reject(error);
        };
        overlay.querySelector('.captcha-close').onclick = () => close(new Error('captcha_cancelled'));
        try {
            window.turnstile.render('#keynestCaptchaWidget', {
                sitekey: config.site_key,
                callback: token => {
                    overlay.remove();
                    resolve(token);
                },
                'error-callback': () => close(new Error('人机验证失败，请重新验证')),
                'expired-callback': () => Toast.warning('人机验证已过期，请重新验证')
            });
        } catch (error) {
            close(error);
        }
    });
}

async function runGeetestCaptcha(config) {
    captchaDebugLog('geetest_overlay_create', 'geetest_v3');
    const overlay = createCaptchaOverlay('<div id="keynestCaptchaWidget" class="captcha-widget"><div class="text-muted small py-3"><span class="spinner-border spinner-border-sm me-2"></span>正在加载极验验证...</div></div>');
    const closeOverlay = (error, reject) => {
        overlay.remove();
        if (error && reject) reject(error);
    };
    return new Promise(async (resolve, reject) => {
        overlay.querySelector('.captcha-close').onclick = () => closeOverlay(new Error('captcha_cancelled'), reject);
        try {
            await loadGeetestScript();
        } catch (error) {
            closeOverlay(error, reject);
            return;
        }
        if (typeof window.initGeetest !== 'function') {
            captchaDebugLog('geetest_init_function_missing', 'geetest_v3');
            closeOverlay(new Error('极验组件未正确加载，请刷新页面重试'), reject);
            return;
        }
        captchaDebugLog('geetest_init_start', 'geetest_v3', JSON.stringify({ has_site_key: !!config.site_key }));
        const initConfig = {
            gt: config.site_key,
            challenge: String(Date.now()),
            offline: false,
            new_captcha: true,
            product: 'bind',
            width: '100%',
            lang: 'zh-cn',
            https: location.protocol === 'https:'
        };
        try {
            const extra = config.extra_config ? JSON.parse(config.extra_config) : {};
            Object.assign(initConfig, extra || {});
        } catch (error) {
            closeOverlay(new Error('极验扩展配置不是有效 JSON'), reject);
            return;
        }
        try {
            window.initGeetest(initConfig, captchaObj => {
                let ready = false;
                const readyTimer = window.setTimeout(() => {
                    if (!ready) {
                        captchaDebugLog('geetest_ready_timeout', 'geetest_v3');
                        closeOverlay(new Error('极验初始化超时，请检查 Captcha ID 是否正确'), reject);
                    }
                }, 10000);
                captchaObj.onReady(() => {
                    ready = true;
                    window.clearTimeout(readyTimer);
                    captchaDebugLog('geetest_ready', 'geetest_v3');
                    const widget = document.getElementById('keynestCaptchaWidget');
                    if (widget) widget.innerHTML = '<div class="text-muted small py-2">请在弹出的极验窗口中完成验证</div>';
                    captchaDebugLog('geetest_verify_call', 'geetest_v3');
                    captchaObj.verify();
                });
                captchaObj.onSuccess(() => {
                    window.clearTimeout(readyTimer);
                    captchaDebugLog('geetest_success', 'geetest_v3');
                    const result = captchaObj.getValidate();
                    if (!result) {
                        closeOverlay(new Error('极验验证结果为空，请重试'), reject);
                        return;
                    }
                    overlay.remove();
                    resolve(JSON.stringify({
                        geetest_challenge: result.geetest_challenge || '',
                        geetest_validate: result.geetest_validate || '',
                        geetest_seccode: result.geetest_seccode || ''
                    }));
                });
                captchaObj.onError(error => {
                    window.clearTimeout(readyTimer);
                    captchaDebugLog('geetest_error', 'geetest_v3', JSON.stringify(error || {}));
                    closeOverlay(new Error((error && (error.msg || error.error_code)) || '极验加载失败，请重试'), reject);
                });
                captchaObj.onClose(() => {
                    captchaDebugLog('geetest_close', 'geetest_v3');
                    closeOverlay(new Error('captcha_cancelled'), reject);
                });
            });
        } catch (error) {
            closeOverlay(error, reject);
        }
    });
}

async function runCaptcha(context = 'default', force = false) {
    const config = await getKeynestCaptchaConfig();
    const shouldRun = force || (context === 'login' && config.login_enabled) || (context === 'register' && config.register_enabled) || (context === 'email_code' && config.email_code_enabled);
    if (!shouldRun) return '';
    if (!config.enabled || !config.site_key) {
        Toast.error('人机验证未配置完整，请联系管理员');
        throw new Error('人机验证未配置完整，请联系管理员');
    }
    const provider = config.provider || 'turnstile';
    captchaDebugLog('run_captcha_provider', provider, JSON.stringify({ context, force, shouldRun }));
    if (provider === 'turnstile') return runTurnstileCaptcha(config);
    if (provider === 'geetest_v3') return runGeetestCaptcha(config);
    Toast.error('当前人机验证服务商暂未接入：' + provider);
    throw new Error('当前人机验证服务商暂未接入：' + provider);
}
window.runCaptcha = runCaptcha;
window.getKeynestCaptchaConfig = getKeynestCaptchaConfig;

function setAuthMode(mode) {
    const content = document.getElementById('authModalContent');
    if (!content) return;
    content.classList.toggle('is-register', mode === 'register');
}

function resetLoginForm() {
    document.getElementById('loginUsername').value = '';
    document.getElementById('loginPassword').value = '';
    document.getElementById('loginCaptchaBox')?.replaceChildren();
    setLoginError('');
}

function resetRegisterForm() {
    document.getElementById('regUsername').value = '';
    document.getElementById('regEmail').value = '';
    document.getElementById('regPassword').value = '';
    document.getElementById('regPasswordConfirm').value = '';
    const codeInput = document.getElementById('regEmailCode');
    if (codeInput) codeInput.value = '';
    const group = document.getElementById('regEmailCodeGroup');
    if (group) group.classList.add('hidden');
    document.getElementById('registerCaptchaBox')?.replaceChildren();
    setRegisterError('');
}

function openLoginModal() {
    resetLoginForm();
    setAuthMode('login');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginModal'));
    modal.show();
}

function openRegisterModal() {
    resetRegisterForm();
    setAuthMode('register');
    refreshRegisterEmailVerifyState();
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('loginModal'));
    modal.show();
}

function setLoginError(message) {
    const errorBox = document.getElementById('loginErrorBox');
    if (!errorBox) return;
    if (!message) {
        errorBox.classList.add('hidden');
        errorBox.textContent = '';
        return;
    }
    errorBox.textContent = message;
    errorBox.classList.remove('hidden');
}

async function refreshRegisterEmailVerifyState() {
    const group = document.getElementById('regEmailCodeGroup');
    if (!group) return;
    group.classList.add('hidden');
    const result = await API.getSystemConfig();
    const enabled = !!(result.success && result.config && result.config.register_email_verify_enabled);
    group.classList.toggle('hidden', !enabled);
}

let registerEmailCodeCountdown = 0;
let registerEmailCodeTimer = null;
async function sendRegisterEmailCode() {
    const email = document.getElementById('regEmail').value.trim();
    if (!Security.validateEmail(email)) {
        Toast.warning('请输入有效的邮箱地址');
        return;
    }
    const btn = document.getElementById('sendRegEmailCodeBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '验证中...';
    }
    try {
        const captchaToken = await window.runCaptcha('email_code', true);
        if (btn) btn.textContent = '发送中...';
        const result = await API.sendEmailCode(email, captchaToken);
        if (!result.success) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = '发送验证码';
            }
            Toast.error(result.message || '验证码发送失败');
            return;
        }
        Toast.success(result.message || '验证码已发送');
    } catch (error) {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '发送验证码';
        }
        if (error && error.message === 'captcha_cancelled') {
            Toast.warning('已取消人机验证');
        } else {
            Toast.error(error?.message || '人机验证失败，请重试');
        }
        return;
    }
    registerEmailCodeCountdown = 60;
    clearInterval(registerEmailCodeTimer);
    registerEmailCodeTimer = setInterval(() => {
        registerEmailCodeCountdown -= 1;
        if (!btn) return;
        if (registerEmailCodeCountdown <= 0) {
            clearInterval(registerEmailCodeTimer);
            btn.disabled = false;
            btn.textContent = '发送验证码';
        } else {
            btn.disabled = true;
            btn.textContent = registerEmailCodeCountdown + '秒后重发';
        }
    }, 1000);
}
window.sendRegisterEmailCode = sendRegisterEmailCode;

function switchToRegister() {
    resetRegisterForm();
    setAuthMode('register');
    refreshRegisterEmailVerifyState();
}

function switchToLogin() {
    resetLoginForm();
    setAuthMode('login');
}

function startOAuthLogin(provider = 'qq', mode = '') {
    const url = 'api/oauth.php?provider=' + encodeURIComponent(provider) + (mode ? '&mode=' + encodeURIComponent(mode) : '');
    window.location.href = url;
}

async function handleLogin() {
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();

    if (!username || !password) {
        setLoginError('请填写用户名和密码');
        Toast.warning('请填写用户名和密码');
        return;
    }

    const submitBtn = document.getElementById('loginSubmitBtn');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>登录中...';
    }

    try {
        setLoginError('');
        const captchaToken = await runCaptcha('login');
        const result = await API.login(username, password, captchaToken);

        if (!result.success) {
            const message = result.message || '登录失败，请检查用户名和密码';
            setLoginError(message);
            Toast.error(message);
            return;
        }

        App.setUser(result.user);
        bootstrap.Modal.getInstance(document.getElementById('loginModal'))?.hide();
        Toast.success(`欢迎回来，${result.user.username}！`);
        showHome();
        App.updateUnreadBadge();
    } catch (error) {
        const message = error && error.message === 'captcha_cancelled' ? '已取消人机验证' : (error && error.message ? error.message : '登录请求失败，请稍后重试');
        setLoginError(message);
        Toast.error(message);
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

function setRegisterError(message, type = 'danger') {
    const box = document.getElementById('registerResultBox');
    if (!box) return;
    if (!message) {
        box.className = 'alert py-2 small hidden';
        box.textContent = '';
        return;
    }
    box.className = 'alert alert-' + type + ' py-2 small';
    box.textContent = message;
}

function showRegisterError(message) {
    setRegisterError(message || '注册失败，请检查填写内容', 'danger');
}

async function handleRegister() {
    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value.trim();
    const passwordConfirm = document.getElementById('regPasswordConfirm').value.trim();
    const emailCode = document.getElementById('regEmailCode')?.value.trim() || '';

    // 客户端验证
    if (!username || !email || !password) {
        showRegisterError('请填写所有字段');
        Toast.warning('请填写所有字段');
        return;
    }
    
    // 使用Security工具进行验证
    if (!Security.validateLength(username, 2, 30)) {
        showRegisterError('用户名需2-30个字符');
        Toast.warning('用户名需2-30个字符');
        return;
    }
    
    if (!Security.validateUsername(username)) {
        showRegisterError('用户名只能包含中文、字母、数字和下划线');
        Toast.warning('用户名只能包含中文、字母、数字和下划线');
        return;
    }
    
    if (!Security.validateEmail(email)) {
        showRegisterError('请输入有效的邮箱地址');
        Toast.warning('请输入有效的邮箱地址');
        return;
    }
    
    if (!Security.validatePassword(password)) {
        showRegisterError('密码至少6位');
        Toast.warning('密码至少6位');
        return;
    }
    
    if (password !== passwordConfirm) {
        showRegisterError('两次密码不一致');
        Toast.warning('两次密码不一致');
        return;
    }

    // 显示加载状态
    const submitBtn = document.getElementById('registerSubmitBtn');
    const originalText = submitBtn ? submitBtn.innerHTML : '注册';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>注册中...';
    }

    try {
        const captchaToken = await runCaptcha('register');
        const result = await API.register(username, email, password, passwordConfirm, emailCode, captchaToken);

        if (!result.success) {
            const message = result.message || '注册失败，请检查验证码、用户名或邮箱';
            showRegisterError(message);
            Toast.error(message);
            return;
        }

        App.setUser(result.user);
        setRegisterError('注册成功，正在进入市场...', 'success');
        Toast.success('注册成功，欢迎加入！');
        setTimeout(() => {
            bootstrap.Modal.getInstance(document.getElementById('loginModal'))?.hide();
            showHome();
            App.updateUnreadBadge();
        }, 350);
    } catch (error) {
        const message = error && error.message === 'captcha_cancelled' ? '已取消人机验证' : (error && error.message ? error.message : '注册请求失败，请稍后重试');
        showRegisterError(message);
        Toast.error(message);
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

async function logout() {
    await API.logout();
    App.clearUser();
    App.currentChatPartner = null;
    Toast.info('已退出登录');
    showHome();
}

async function refreshUserData() {
    const result = await API.getCurrentUser();
    if (result.success && result.logged_in) {
        App.setUser(result.user);
    } else {
        App.clearUser();
    }
}
