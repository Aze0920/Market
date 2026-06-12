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
            if (data && data.csrf_token) {
                this.csrfToken = data.csrf_token;
            }
        } catch (error) {
            console.error('Failed to initialize CSRF:', error);
        }
    },

    // 认证
    login(username, password, captcha_token = '') {
        return this.request('auth.php?action=login', 'POST', { username, password, captcha_token });
    },

    register(username, email, password, password_confirm, email_code = '', captcha_token = '', agreement_accepted = false) {
        return this.request('auth.php?action=register', 'POST', {
            username, email, password, password_confirm, email_code, captcha_token, agreement_accepted: agreement_accepted ? '1' : '0'
        });
    },

    sendEmailCode(email, captcha_token = '') {
        return this.request('auth.php?action=send_email_code', 'POST', { email, captcha_token });
    },

    sendPasswordResetCode(email, captcha_token = '') {
        return this.request('auth.php?action=send_password_reset_code', 'POST', { email, captcha_token });
    },

    resetPassword(email, email_code, new_password, confirm_password) {
        return this.request('auth.php?action=reset_password', 'POST', { email, email_code, new_password, confirm_password });
    },

    getCaptchaConfig() {
        return this.request('auth.php?action=captcha_config');
    },

    getGeetestRegister() {
        return this.request('auth.php?action=geetest_register&_=' + Date.now());
    },

    captchaDebug(step, provider = '', message = '') {
        return this.request('auth.php?action=captcha_debug', 'POST', {
            step,
            provider,
            message,
            href: location.href,
            ua: navigator.userAgent
        });
    },

    logout() {
        return this.request('auth.php?action=logout', 'POST');
    },

    getCurrentUser() {
        return this.request('auth.php?action=get_current_user');
    },

    updateProfile(username, email) {
        return this.request('auth.php?action=update_profile', 'POST', { username, email });
    },

    async uploadAvatar(file) {
        const options = {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData()
        };
        if (this.csrfToken) {
            options.headers['X-CSRF-Token'] = this.csrfToken;
        }
        options.body.append('image', file);
        try {
            const response = await fetch(this.baseUrl + 'auth.php?action=upload_avatar', options);
            const text = await response.text();
            let result = {};
            try {
                result = text ? JSON.parse(text) : {};
            } catch (parseError) {
                const preview = text ? text.slice(0, 300) : '空响应';
                return { success: false, message: `服务器返回异常（HTTP ${response.status}）：${preview}` };
            }
            return response.ok ? result : { success: false, message: result.message || `上传失败（HTTP ${response.status}）`, ...result };
        } catch (error) {
            console.error('Avatar Upload Error:', error);
            return { success: false, message: '头像上传失败，请检查网络或服务器状态：' + (error.message || error) };
        }
    },

    savePaymentMethods(methods, emailCode = '', merchantRulesAccepted = false) {
        return this.request('auth.php?action=save_payment_methods', 'POST', {
            payment_methods: JSON.stringify(methods || {}),
            email_code: emailCode,
            merchant_rules_accepted: merchantRulesAccepted ? '1' : '0'
        });
    },

    saveCustomLabel(text, icon, gradient) {
        return this.request('auth.php?action=save_custom_label', 'POST', { text, icon, gradient });
    },

    async uploadPaymentQrcode(file, method = '', emailCode = '', account = '') {
        const options = {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData()
        };
        if (this.csrfToken) {
            options.headers['X-CSRF-Token'] = this.csrfToken;
        }
        options.body.append('image', file);
        options.body.append('method', method);
        options.body.append('email_code', emailCode);
        options.body.append('account', account);
        try {
            const response = await fetch(this.baseUrl + 'auth.php?action=upload_payment_qrcode', options);
            const text = await response.text();
            let result = {};
            try {
                result = text ? JSON.parse(text) : {};
            } catch (parseError) {
                const preview = text ? text.slice(0, 300) : '空响应';
                return { success: false, message: `服务器返回异常（HTTP ${response.status}）：${preview}` };
            }
            return response.ok ? result : { success: false, message: result.message || `上传失败（HTTP ${response.status}）`, ...result };
        } catch (error) {
            console.error('Payment QR Upload Error:', error);
            return { success: false, message: '收款码上传失败，请检查网络或服务器状态：' + (error.message || error) };
        }
    },

    sendProfileEmailCode(captcha_token = '') {
        return this.request('auth.php?action=send_profile_email_code', 'POST', { captcha_token });
    },

    verifyProfileEmailCode(emailCode) {
        return this.request('auth.php?action=verify_profile_email_code', 'POST', { email_code: emailCode });
    },

    changePassword(emailCode, newPassword, confirmPassword) {
        return this.request('auth.php?action=change_password', 'POST', {
            email_code: emailCode,
            new_password: newPassword,
            confirm_password: confirmPassword
        });
    },

    unbindQQ() {
        return this.request('auth.php?action=unbind_qq', 'POST');
    },

    searchUsers(query) {
        return this.request('auth.php?action=search_users&query=' + encodeURIComponent(query));
    },

    async uploadProductImage(file) {
        const options = {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData()
        };
        if (this.csrfToken) {
            options.headers['X-CSRF-Token'] = this.csrfToken;
        }
        options.body.append('image', file);
        try {
            const response = await fetch(this.baseUrl + 'product.php?action=upload_image', options);
            const text = await response.text();
            const result = text ? JSON.parse(text) : {};
            return response.ok ? result : { success: false, message: result.message || '上传失败' };
        } catch (error) {
            console.error('Upload Error:', error);
            return { success: false, message: '图片上传失败，请稍后重试' };
        }
    },

    // 商品
    getProducts(filters = {}) {
        const params = new URLSearchParams(filters).toString();
        return this.request('product.php?action=list&' + params);
    },

    getProductReviews(productId = '') {
        return this.request('product.php?action=reviews' + (productId ? '&product_id=' + encodeURIComponent(productId) : ''));
    },

    getProduct(id) {
        return this.request('product.php?action=detail&id=' + id);
    },

    publishProduct(productData) {
        return this.request('product.php?action=publish', 'POST', productData);
    },

    updateProduct(id, productData) {
        return this.request('product.php?action=update', 'POST', { id, ...productData });
    },

    addProductStock(id, accountList) {
        return this.request('product.php?action=add_stock', 'POST', { id, account_list: accountList });
    },

    getProductStock(id) {
        return this.request('product.php?action=stock&id=' + encodeURIComponent(id));
    },

    deleteProductStock(id, stockIndex) {
        return this.request('product.php?action=delete_stock', 'POST', { id, stock_index: stockIndex });
    },

    deleteProductStockBatch(id, mode, stockIndexes = []) {
        return this.request('product.php?action=delete_stock_batch', 'POST', { id, mode, stock_indexes: JSON.stringify(stockIndexes) });
    },

    clearProductStock(id) {
        return this.request('product.php?action=clear_stock', 'POST', { id });
    },

    deleteProduct(id) {
        return this.request('product.php?action=delete', 'POST', { id });
    },

    getMyProducts() {
        return this.request('product.php?action=my_products');
    },

    buyProduct(id, quantity = 1, pickupPassword = '') {
        return this.request('product.php?action=buy', 'POST', { id, quantity, pickup_password: pickupPassword });
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

    getOrder(id, pickupPassword = '', guestToken = '', guestEmail = '', guestQueryCode = '') {
        return this.request('order.php?action=get&id=' + encodeURIComponent(id) + (pickupPassword ? '&pickup_password=' + encodeURIComponent(pickupPassword) : '') + (guestToken ? '&guest_token=' + encodeURIComponent(guestToken) : '') + (guestEmail ? '&guest_email=' + encodeURIComponent(guestEmail) : '') + (guestQueryCode ? '&guest_query_code=' + encodeURIComponent(guestQueryCode) : ''));
    },

    queryGuestOrderByCode(email, queryCode, pickupPassword = '') {
        return this.request('order.php?action=guest_query', 'POST', { email, query_code: queryCode, pickup_password: pickupPassword });
    },

    complainOrder(orderId, email, reason) {
        return this.request('order.php?action=complain', 'POST', { order_id: orderId, email, reason });
    },

    withdrawComplaint(orderId, password) {
        return this.request('order.php?action=withdraw_complaint', 'POST', { order_id: orderId, password });
    },

    replyComplaint(orderId, reply) {
        return this.request('order.php?action=reply_complaint', 'POST', { order_id: orderId, reply });
    },

    sellerRefundComplaint(orderId, note = '') {
        return this.request('order.php?action=seller_refund_complaint', 'POST', { order_id: orderId, note });
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

    requestWithdraw(amount, paymentMethod = '', paymentAccount = '', qrcodeUrl = '') {
        return this.request('finance.php?action=withdraw', 'POST', {
            amount,
            payment_method: paymentMethod,
            payment_account: paymentAccount,
            qrcode_url: qrcodeUrl
        });
    },

    getMyRequests() {
        return this.request('finance.php?action=my_requests');
    },

    getAllRequests() {
        return this.request('finance.php?action=all_requests');
    },

    approveRequest(id, adminNote = '') {
        return this.request('finance.php?action=approve', 'POST', { id, admin_note: adminNote });
    },

    rejectRequest(id, adminNote = '') {
        return this.request('finance.php?action=reject', 'POST', { id, admin_note: adminNote });
    },

    // 卡密
    useCard(code, extra = {}) {
        return this.request('card.php?action=use', 'POST', { code, ...extra });
    },

    resolveSubdomain(host = window.location.hostname) {
        return this.request('subdomain.php?action=resolve&host=' + encodeURIComponent(host));
    },

    getMySubdomain() {
        return this.request('subdomain.php?action=my');
    },

    checkSubdomainPrefix(prefix) {
        return this.request('subdomain.php?action=check_prefix&prefix=' + encodeURIComponent(prefix));
    },

    purchaseSubdomain(prefix, months = 1, baseDomain = '') {
        return this.request('subdomain.php?action=purchase', 'POST', { prefix, months, base_domain: baseDomain });
    },

    renewSubdomain(months = 1, baseDomain = '') {
        return this.request('subdomain.php?action=renew', 'POST', { months, base_domain: baseDomain });
    },

    getCards(onlyUnused = false) {
        const param = onlyUnused ? '?only_unused=1' : '';
        return this.request('card.php?action=list' + param);
    },

    createCards(amount, count, cardType = 'balance', targetLevel = '', baseDomain = '') {
        const payload = { amount, count, card_type: cardType, target_level: targetLevel };
        if (baseDomain) payload.base_domain = baseDomain;
        return this.request('card.php?action=create', 'POST', payload);
    },

    peekCard(code) {
        return this.request('card.php?action=peek', 'POST', { code });
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

    createProductPaymentOrder(paymentConfigId, productId, quantity, payType, pickupPassword = '', guestToken = '', guestEmail = '') {
        return this.request('payment.php?action=create_product_order', 'POST', {
            payment_config_id: paymentConfigId,
            product_id: productId,
            quantity,
            pay_type: payType,
            pickup_password: pickupPassword,
            guest_token: guestToken,
            guest_email: guestEmail
        });
    },

    getPaymentOrderStatus(id, guestToken = '') {
        return this.request('payment.php?action=get_order_status&id=' + encodeURIComponent(id) + (guestToken ? '&guest_token=' + encodeURIComponent(guestToken) : ''));
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
