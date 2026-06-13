/**
 * Dashboard 模块入口 - 负责懒加载各 Tab 模块
 * 懒加载策略：首次访问某个 Tab 时动态加载对应模块
 */
(function() {
    'use strict';

    // 已加载的模块缓存
    const loadedModules = {};

    // 模块定义：tabName -> { file: JS文件路径, init: 初始化函数 }
    const moduleRegistry = {
        overview: {
            file: 'assets/js/dashboard/overview.js',
            init: null
        },
        orders: {
            file: 'assets/js/dashboard/orders.js',
            init: null
        },
        sales: {
            file: 'assets/js/dashboard/sales.js',
            init: null
        },
        myproducts: {
            file: 'assets/js/dashboard/products.js',
            init: null
        },
        balance: {
            file: 'assets/js/dashboard/balance.js',
            init: null
        },
        profile: {
            file: 'assets/js/dashboard/profile.js',
            init: null
        },
        reviews: {
            file: 'assets/js/dashboard/reviews.js',
            init: null
        },
        complaints: {
            file: 'assets/js/dashboard/complaints.js',
            init: null
        },
        messages: {
            file: 'assets/js/dashboard/messages.js',
            init: null
        },
        cardmanage: {
            file: 'assets/js/dashboard/admin.js',
            init: null
        },
        paymentmanage: {
            file: 'assets/js/dashboard/admin.js',
            init: null
        },
        shop: {
            file: 'assets/js/dashboard/shop.js',
            init: null
        }
    };

    // 待注入的依赖（由 app.js 提供）
    let dependencyInjection = null;

    // 二级商铺状态缓存
    let isSubShopUser = false;
    let subShopCheckDone = false;

    /**
     * 检查是否为二级商铺用户
     */
    async function checkSubShopStatus() {
        if (subShopCheckDone) {
            return isSubShopUser;
        }
        try {
            var result = await window.API.request('subdomain.php?action=my', 'GET', {});
            if (result.success && result.subdomain && result.subdomain.prefix) {
                isSubShopUser = true;
            }
        } catch (e) {
            console.warn('Sub-shop check failed:', e);
        }
        subShopCheckDone = true;
        return isSubShopUser;
    }

    /**
     * 获取受限制的 Tab 列表（二级商铺用户不能访问）
     */
    function getRestrictedTabsForSubShop() {
        return ['myproducts', 'balance', 'cardmanage', 'paymentmanage'];
    }

    /**
     * 检查某个 Tab 是否对当前用户受限
     */
    function isTabRestricted(tabName) {
        // 仅对二级商铺用户限制
        if (!isSubShopUser) {
            return false;
        }
        return getRestrictedTabsForSubShop().indexOf(tabName) !== -1;
    }

    /**
     * 设置依赖注入（由 app.js 调用）
     */
    window.__dashboardSetDependencies = function(deps) {
        dependencyInjection = deps;
    };

    /**
     * 获取当前依赖
     */
    function getDeps() {
        if (dependencyInjection) {
            return dependencyInjection;
        }
        // 降级：如果依赖未注入，尝试从全局获取
        return {
            API: window.API,
            App: window.App,
            Toast: window.Toast,
            Utils: window.Utils,
            Security: window.Security,
            renderDashboardTab: window.renderDashboardTab,
            loadProducts: window.loadProducts,
            showDashboard: window.showDashboard,
            userAvatarUrl: window.userAvatarUrl,
            avatarHtml: window.avatarHtml,
            hideModalSafely: window.hideModalSafely,
            cleanupBootstrapModalArtifacts: window.cleanupBootstrapModalArtifacts,
            openProductDetail: window.openProductDetail,
            openPublishModal: window.openPublishModal,
            openOrderProductDetail: window.openOrderProductDetail,
            loadMembershipTab: window.loadMembershipTab,
            renderMembershipCardActivationCard: window.renderMembershipCardActivationCard,
            renderGradientBadge: window.renderGradientBadge,
            startMerchantReadTimer: window.startMerchantReadTimer,
            renderMerchantCertificationBox: window.renderMerchantCertificationBox,
            merchantAgreementDefaultText: window.merchantAgreementDefaultText,
            getUserPaymentMethods: window.getUserPaymentMethods,
            merchantStatusInfo: window.merchantStatusInfo,
            paymentMethodIcon: window.paymentMethodIcon,
            paymentMethodNeedsEmailCode: window.paymentMethodNeedsEmailCode,
            paymentMethodVerifyHtml: window.paymentMethodVerifyHtml,
            renderPaymentMethodUploadCard: window.renderPaymentMethodUploadCard,
            handlePaymentQrDragOver: window.handlePaymentQrDragOver,
            handlePaymentQrDragLeave: window.handlePaymentQrDragLeave,
            handlePaymentQrDrop: window.handlePaymentQrDrop,
            handlePaymentQrSelect: window.handlePaymentQrSelect,
            renderPaymentQrPlaceholder: window.renderPaymentQrPlaceholder,
            renderPaymentQrError: window.renderPaymentQrError,
            uploadPaymentQrFile: window.uploadPaymentQrFile,
            showPaymentMethodsNotice: window.showPaymentMethodsNotice,
            warnPaymentMethods: window.warnPaymentMethods,
            savePaymentMethods: window.savePaymentMethods,
            sendProfileEmailCode: window.sendProfileEmailCode,
            saveProfileInfo: window.saveProfileInfo,
            setProfileSecurityUnlocked: window.setProfileSecurityUnlocked,
            handleProfileEmailCodeInput: window.handleProfileEmailCodeInput,
            verifyProfileEmailCodeAndUnlock: window.verifyProfileEmailCodeAndUnlock,
            changeProfilePassword: window.changeProfilePassword,
            bindQQAccount: window.bindQQAccount,
            unbindQQAccount: window.unbindQQAccount,
            scrollToMerchantCertification: window.scrollToMerchantCertification,
            startOAuthLogin: window.startOAuthLogin
        };
    }

    /**
     * 懒加载并执行模块
     */
    function loadModule(tabName) {
        return new Promise(function(resolve, reject) {
            // 如果模块已加载，直接执行
            if (loadedModules[tabName]) {
                resolve(loadedModules[tabName]);
                return;
            }

            const moduleInfo = moduleRegistry[tabName];
            if (!moduleInfo) {
                reject(new Error('未知 Tab: ' + tabName));
                return;
            }

            // 如果多个 tab 共用一个文件，检查是否已加载
            const fileKey = moduleInfo.file;
            if (loadedModules[fileKey]) {
                loadedModules[tabName] = loadedModules[fileKey];
                resolve(loadedModules[tabName]);
                return;
            }

            // 创建 script 元素并加载
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.src = moduleInfo.file + '?v=V1.2.0';
            script.async = true;

            script.onload = function() {
                // 模块加载成功
                loadedModules[tabName] = true;
                loadedModules[fileKey] = true;
                resolve(true);
            };

            script.onerror = function(e) {
                console.error('模块加载失败:', moduleInfo.file, e);
                reject(new Error('模块加载失败: ' + moduleInfo.file));
            };

            document.head.appendChild(script);
        });
    }

    /**
     * 渲染 Dashboard Tab - 懒加载版本
     * 覆盖 window.renderDashboardTab
     */
    window.renderDashboardTab = async function(tabName) {
        var deps = getDeps();

        // 检查是否为二级商铺用户（首次检查）
        await checkSubShopStatus();

        // 二级商铺用户访问受限 Tab 时显示提示
        if (isTabRestricted(tabName)) {
            var restrictedContent = document.getElementById('dashContentArea');
            if (restrictedContent) {
                restrictedContent.innerHTML = '<div class="empty-state"><i class="bi bi-shield-lock" style="font-size:3rem;color:#9ca3af;"></i><h5 class="mt-3">功能受限</h5><p class="text-muted">二级商铺用户暂时无法使用此功能</p><p class="small text-muted">如需完整功能，请通过主站入口访问</p></div>';
            }
            // 更新侧边栏
            document.querySelectorAll('#dashSidebar .sidebar-nav-item').forEach(function(item) {
                item.classList.toggle('active', item.dataset.tab === tabName);
            });
            if (deps.Toast) {
                deps.Toast.warning('二级商铺用户暂时无法使用此功能，请通过主站入口访问');
            }
            return;
        }

        // 设置 App.currentTab
        if (deps.App) {
            deps.App.currentTab = tabName;
            if (deps.App.currentPage === 'dashboard') {
                deps.persistFrontendState && deps.persistFrontendState();
            }
        }

        // 更新侧边栏 active 状态
        document.querySelectorAll('#dashSidebar .sidebar-nav-item').forEach(function(item) {
            item.classList.toggle('active', item.dataset.tab === tabName);
        });

        // 显示加载状态
        var contentArea = document.getElementById('dashContentArea');
        if (contentArea) {
            contentArea.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
        }

        try {
            // 懒加载模块
            await loadModule(tabName);

            // 调用模块的 render 函数
            if (typeof window['render_' + tabName + '_tab'] === 'function') {
                window['render_' + tabName + '_tab'](contentArea, deps);
            } else if (typeof window['load' + capitalize(tabName) + 'Tab'] === 'function') {
                // 兼容旧命名
                await window['load' + capitalize(tabName) + 'Tab'](contentArea);
            } else {
                // 降级：如果模块没有导出 render 函数，尝试调用全局函数
                var tabLoadFn = window['load' + capitalize(tabName) + 'Tab'];
                if (typeof tabLoadFn === 'function') {
                    await tabLoadFn(contentArea);
                } else {
                    if (contentArea) {
                        contentArea.innerHTML = '<div class="empty-state"><p>该功能正在开发中...</p></div>';
                    }
                }
            }
        } catch (err) {
            console.error('渲染 Tab 失败:', tabName, err);
            if (contentArea) {
                contentArea.innerHTML = '<div class="empty-state"><p>加载失败，请刷新页面重试</p></div>';
            }
            if (deps.Toast && typeof deps.Toast.error === 'function') {
                deps.Toast.error('加载失败: ' + (err.message || '未知错误'));
            }
        }
    };

    /**
     * 首字母大写
     */
    function capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * 为二级商铺用户隐藏受限的侧边栏项
     */
    function hideRestrictedSidebarItems() {
        if (!isSubShopUser) return;
        var restrictedTabs = getRestrictedTabsForSubShop();
        restrictedTabs.forEach(function(tab) {
            var item = document.querySelector('#dashSidebar .sidebar-nav-item[data-tab="' + tab + '"]');
            if (item) {
                item.style.display = 'none';
            }
        });
    }

    /**
     * 初始化侧边栏访问控制（在侧边栏渲染后调用）
     */
    window.__dashboardInitAccessControl = async function() {
        await checkSubShopStatus();
        hideRestrictedSidebarItems();
    };

    /**
     * 导出模块加载器供外部使用
     */
    window.__dashboardModuleLoader = {
        load: loadModule,
        loaded: loadedModules,
        registry: moduleRegistry,
        isSubShopUser: function() { return isSubShopUser; },
        checkSubShopStatus: checkSubShopStatus
    };

})();
