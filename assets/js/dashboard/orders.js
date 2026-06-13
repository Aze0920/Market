/**
 * Dashboard - 订单模块（购买记录）
 */
(function() {
    'use strict';

    /**
     * 渲染订单 Tab
     */
    window.render_orders_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Utils = deps.Utils;
        var Security = deps.Security;
        var Toast = deps.Toast;

        var result = await API.getMyOrders();
        if (!result.success || result.orders.length === 0) {
            area.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-receipt"></i>
                    <h5>暂无购买记录</h5>
                    <p>快去市场选购商品吧</p>
                </div>
            `;
            return;
        }

        area.innerHTML = `
            <h5 class="fw-bold mb-4"><i class="bi bi-receipt me-2"></i>购买记录</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>商品</th>
                            <th>订单号</th>
                            <th>卖家昵称</th>
                            <th>价格</th>
                            <th>时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${result.orders.map(function(o) {
                            return `
                                <tr>
                                    <td>
                                        <button type="button" class="btn btn-link p-0 text-start fw-semibold text-decoration-none order-product-link" onclick="window.openOrderProductDetail('${Security.escapeAttr(o.product_id)}')" title="查看商品详情">
                                            ${Utils.truncate(o.product_title, 20)}
                                        </button>
                                        ${orderComplaintBadge(o)}
                                    </td>
                                    <td><code class="small">${Security.escapeHtml(o.payment_trade_no || o.id || '-')}</code></td>
                                    <td>${Security.escapeHtml(o.seller_name || '-')}</td>
                                    <td class="text-danger fw-semibold">¥${o.price.toFixed(2)}</td>
                                    <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline" onclick="window.viewDeliveryInfo('${o.id}')">查看发货</button>
                                        ${o.has_comment ? '<span class="badge badge-success ms-1">已评价</span>' : `<button class="btn btn-sm btn-primary keynest-review-btn" data-product-id="${Security.escapeAttr(o.product_id)}" data-order-id="${Security.escapeAttr(o.id)}" onclick="window.openReviewDialog('${o.product_id}', '${o.id}')">评价</button>`}
                                        ${orderComplaintActions(o)}
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    };

    function orderComplaintBadge(order) {
        var status = order && order.complaint && order.complaint.status || '';
        var map = {
            open: '<span class="badge badge-warning">投诉中</span>',
            processing: '<span class="badge badge-info">处理中</span>',
            withdrawn: '<span class="badge badge-secondary">已撤诉</span>',
            resolved: '<span class="badge badge-success">卖家胜</span>',
            rejected: '<span class="badge badge-danger">买家胜</span>'
        };
        return map[status] ? '<div>' + map[status] + '</div>' : '';
    }

    function orderComplaintActions(order) {
        var status = order && order.complaint && order.complaint.status || '';
        var orderId = order && order.id ? order.id : '';
        var escapedId = orderId ? orderId.replace(/'/g, '\\\'') : '';

        if (!status) {
            return "<button class=\"btn btn-sm btn-danger\" onclick=\"window.openComplaintModal('" + escapedId + "')\">投诉</button>";
        }
        if (status === 'open' || status === 'processing') {
            return "<button class=\"btn btn-sm btn-warning\" onclick=\"window.openWithdrawComplaintModal('" + escapedId + "')\">撤诉</button>";
        }
        var text = status === 'withdrawn' ? '已撤诉' : (status === 'resolved' ? '卖家胜' : (status === 'rejected' ? '买家胜' : '已结束'));
        return '<span class="badge badge-secondary">' + text + '</span>';
    }

})();
