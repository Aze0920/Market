/**
 * API 请求模块
 */
const API = {
    baseUrl: 'api/',
    csrfToken: null,

    async request(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // 添加CSRF令牌到请求头
        if (this.csrfToken) {
            options.headers['X-CSRF-Token'] = this.csrfToken;
        }

        if (data) {
            options.body = new URLSearchParams(data).toString();
        }

        try {
            const response = await fetch(this.baseUrl + endpoint, options);
            const text = await response.text();
            let result = null;

            try {
                result = text ? JSON.parse(text) : {};
            } catch (jsonError) {
                console.error('JSON Parse Error:', jsonError);
                console.error('Response text:', text.substring(0, 500));
                return { success: false, message: response.ok ? '服务器返回了无效数据，请联系管理员' : '服务器错误，状态码: ' + response.status };
            }

            if (result.csrf_token) {
                this.csrfToken = result.csrf_token;
            }

            if (!response.ok) {
                return {
                    success: false,
                    message: result.message || ('请求失败，状态码: ' + response.status),
                    status: response.status,
                    ...result
                };
            }

            return result;
        } catch (error) {
            console.error('API Error:', error);
            return { success: false, message: '网络错误，请稍后重试' };
        }
    },

    // 初始化CSRF令牌
    async initCSRF() {
        try {
            const response = await fetch(this.baseUrl + 'auth.php?action=get_current_user');
            const data = await response.json();
            // CSRF token由后端在init.php中设置
        } catch (error) {
            console.error('Failed to initialize CSRF:', error);
        }
    },

    // 认证
    login(username, password) {
        return this.request('auth.php?action=login', 'POST', { username, password });
    },

    register(username, email, password, password_confirm, email_code = '') {
        return this.request('auth.php?action=register', 'POST', {
            username, email, password, password_confirm, email_code
        });
    },

    sendEmailCode(email) {
        return this.request('auth.php?action=send_email_code', 'POST', { email });
    },

    logout() {
        return this.request('auth.php?action=logout', 'POST');
    },

    getCurrentUser() {
        return this.request('auth.php?action=get_current_user');
    },

    searchUsers(query) {
        return this.request('auth.php?action=search_users&query=' + encodeURIComponent(query));
    },

    // 商品
    getProducts(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        return this.request('product.php?action=list&' + params);
    },

    getProduct(id) {
        return this.request('product.php?action=detail&id=' + id);
    },

    publishProduct(productData) {
        return this.request('product.php?action=publish', 'POST', productData);
    },

    deleteProduct(id) {
        return this.request('product.php?action=delete', 'POST', { id });
    },

    getMyProducts() {
        return this.request('product.php?action=my_products');
    },

    buyProduct(id) {
        return this.request('product.php?action=buy', 'POST', { id });
    },

    addComment(productId, orderId, rating, content) {
        return this.request('product.php?action=comment', 'POST', {
            product_id: productId, order_id: orderId, rating, content
        });
    },

    // 订单
    getMyOrders() {
        return this.request('order.php?action=my_orders');
    },

    getMySales() {
        return this.request('order.php?action=my_sales');
    },

    getOrder(id) {
        return this.request('order.php?action=get&id=' + id);
    },

    getOverview() {
        return this.request('order.php?action=overview');
    },

    // 私信
    getContacts() {
        return this.request('message.php?action=contacts');
    },

    getConversation(partner) {
        return this.request('message.php?action=conversation&partner=' + encodeURIComponent(partner));
    },

    sendMessage(to, content) {
        return this.request('message.php?action=send', 'POST', { to, content });
    },

    getUnreadCount() {
        return this.request('message.php?action=unread_count');
    },

    // 财务
    getBalance() {
        return this.request('finance.php?action=balance');
    },

    requestDeposit(amount) {
        return this.request('finance.php?action=deposit', 'POST', { amount });
    },

    requestWithdraw(amount) {
        return this.request('finance.php?action=withdraw', 'POST', { amount });
    },

    getMyRequests() {
        return this.request('finance.php?action=my_requests');
    },

    getAllRequests() {
        return this.request('finance.php?action=all_requests');
    },

    approveRequest(id) {
        return this.request('finance.php?action=approve', 'POST', { id });
    },

    rejectRequest(id) {
        return this.request('finance.php?action=reject', 'POST', { id });
    },

    // 卡密
    useCard(code) {
        return this.request('card.php?action=use', 'POST', { code });
    },

    getCards(onlyUnused = false) {
        const param = onlyUnused ? '?only_unused=1' : '';
        return this.request('card.php?action=list' + param);
    },

    createCards(amount, count) {
        return this.request('card.php?action=create', 'POST', { amount, count });
    },

    deleteCard(id) {
        return this.request('card.php?action=delete', 'POST', { id });
    },

    // 会员
    getMembershipLevels() {
        return this.request('membership.php?action=levels');
    },

    upgradeMembership(level) {
        return this.request('membership.php?action=upgrade', 'POST', { level });
    },

    getMyMembership() {
        return this.request('membership.php?action=my_level');
    },

    // 支付
    getPaymentConfigs() {
        return this.request('payment.php?action=get_configs');
    },

    addPaymentConfig(config) {
        return this.request('payment.php?action=add_config', 'POST', config);
    },

    updatePaymentConfig(id, update) {
        update.id = id;
        return this.request('payment.php?action=update_config', 'POST', update);
    },

    deletePaymentConfig(id) {
        return this.request('payment.php?action=delete_config', 'POST', { id });
    },

    createPaymentOrder(paymentConfigId, amount, payType) {
        return this.request('payment.php?action=create_order', 'POST', {
            payment_config_id: paymentConfigId,
            amount,
            pay_type: payType
        });
    },

    createMembershipPaymentOrder(paymentConfigId, level, payType) {
        return this.request('payment.php?action=create_membership_order', 'POST', {
            payment_config_id: paymentConfigId,
            level,
            pay_type: payType
        });
    },

    getPaymentOrders() {
        return this.request('payment.php?action=get_orders');
    },

    getMyPaymentOrders() {
        return this.request('payment.php?action=get_my_orders');
    },

    // 系统配置
    getSystemConfig() {
        return this.request('finance.php?action=get_system_config');
    },

    updateSystemConfig(config) {
        return this.request('finance.php?action=update_system_config', 'POST', config);
    },

    // 提现
    requestWithdraw(amount, paymentMethod, paymentAccount, qrcodeUrl) {
        return this.request('finance.php?action=withdraw', 'POST', {
            amount,
            payment_method: paymentMethod,
            payment_account: paymentAccount,
            qrcode_url: qrcodeUrl
        });
    },

    getWithdrawRequests() {
        return this.request('finance.php?action=get_withdraw_requests');
    }
};
