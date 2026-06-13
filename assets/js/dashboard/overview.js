/**
 * Dashboard - 概览模块
 */
(function() {
    'use strict';

    /**
     * 渲染概览 Tab
     */
    window.render_overview_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var result = await API.getOverview();
        if (!result.success) {
            area.innerHTML = '<div class="empty-state"><p>加载失败</p></div>';
            return;
        }

        var o = result.overview;
        area.innerHTML = `
            <h5 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2 text-primary"></i>控制台概览</h5>
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="stat-card primary">
                        <i class="bi bi-cart-check"></i>
                        <div class="stat-value">${o.total_orders}</div>
                        <div class="stat-label">总购买</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card success">
                        <i class="bi bi-graph-up"></i>
                        <div class="stat-value">${o.total_sales}</div>
                        <div class="stat-label">总售出</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card warning">
                        <i class="bi bi-wallet2"></i>
                        <div class="stat-value">¥${o.total_spent.toFixed(2)}</div>
                        <div class="stat-label">消费</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card info">
                        <i class="bi bi-box-seam"></i>
                        <div class="stat-value">${o.active_products}</div>
                        <div class="stat-label">在售商品</div>
                    </div>
                </div>
            </div>
            <h6 class="fw-bold mb-3">最近购买记录</h6>
            ${o.recent_orders.length === 0 ?
                '<p class="text-muted">暂无购买记录</p>' :
                `<div class="bg-light rounded-3 p-3">
                    ${o.recent_orders.map(function(order) {
                        return `
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <button type="button" class="btn btn-link p-0 text-start fw-semibold text-decoration-none" onclick="window.openOrderProductDetail('${Security.escapeAttr(order.product_id)}')">${Utils.truncate(order.product_title, 25)}</button>
                                <span class="text-danger fw-semibold">-¥${order.price.toFixed(2)}</span>
                                <span class="text-muted small">${Utils.formatDate(order.purchase_date)}</span>
                            </div>
                        `;
                    }).join('')}
                </div>`
            }
        `;
    };

})();
