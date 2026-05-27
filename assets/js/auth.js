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

function openLoginModal() {
    document.getElementById('loginUsername').value = '';
    document.getElementById('loginPassword').value = '';
    setLoginError('');
    const modal = new bootstrap.Modal(document.getElementById('loginModal'));
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

function openRegisterModal() {
    document.getElementById('regUsername').value = '';
    document.getElementById('regEmail').value = '';
    document.getElementById('regPassword').value = '';
    document.getElementById('regPasswordConfirm').value = '';
    const codeInput = document.getElementById('regEmailCode');
    if (codeInput) codeInput.value = '';
    const group = document.getElementById('regEmailCodeGroup');
    if (group) group.classList.add('hidden');
    setRegisterError('');
    refreshRegisterEmailVerifyState();
    const modal = new bootstrap.Modal(document.getElementById('registerModal'));
    modal.show();
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
        btn.textContent = '发送中...';
    }
    const result = await API.sendEmailCode(email);
    if (!result.success) {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '发送验证码';
        }
        Toast.error(result.message || '验证码发送失败');
        return;
    }
    Toast.success(result.message || '验证码已发送');
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

function switchToRegister() {
    bootstrap.Modal.getInstance(document.getElementById('loginModal')).hide();
    setTimeout(() => openRegisterModal(), 200);
}

function switchToLogin() {
    bootstrap.Modal.getInstance(document.getElementById('registerModal')).hide();
    setTimeout(() => openLoginModal(), 200);
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
        const result = await API.login(username, password);

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
        const message = error && error.message ? error.message : '登录请求失败，请稍后重试';
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
        const result = await API.register(username, email, password, passwordConfirm, emailCode);

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
            bootstrap.Modal.getInstance(document.getElementById('registerModal'))?.hide();
            showHome();
            App.updateUnreadBadge();
        }, 350);
    } catch (error) {
        const message = error && error.message ? error.message : '注册请求失败，请稍后重试';
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
