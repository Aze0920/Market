/**
 * Dashboard - 售出记录模块
 */
(function() {
    'use strict';

    /**
     * 渲染售出 Tab
     */
    window.render_sales_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var result = await API.getMySales();
        if (!result.success || result.orders.length === 0) {
            area.innerHTML = `
                <div class="empty-state">
                    <i class="bi bi-graph-up"></i>
                    <h5>暂无售出记录</h5>
                    <p>发布商品开始销售吧</p>
                </div>
            `;
            return;
        }

        area.innerHTML = `
            <h5 class="fw-bold mb-4"><i class="bi bi-graph-up me-2"></i>售出记录</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>商品</th>
                            <th>买家</th>
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
                                        ${Utils.truncate(o.product_title, 20)}
                                        ${orderComplaintBadge(o)}
                                    </td>
                                    <td>${o.guest_order ? '<span class="badge badge-secondary">游客买家</span><div class="small text-muted">已隐藏信息</div>' : Security.escapeHtml(o.buyer_name || '-')}</td>
                                    <td class="text-success fw-semibold">+¥${o.seller_amount ? o.seller_amount.toFixed(2) : o.price.toFixed(2)}</td>
                                    <td class="text-muted small">${Utils.formatDate(o.purchase_date)}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline" onclick="window.openSellerOrderInfoModal('${Security.escapeAttr(o.id)}')">订单信息</button>
                                        ${o.complaint && ['open', 'processing'].includes(o.complaint.status) ? "<button class=\"btn btn-sm btn-warning\" onclick=\"window.openSellerComplaintModal('" + Security.escapeAttr(o.id) + "')\">查看投诉</button>" : ''}
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

    /**
     * 打开卖家订单信息弹窗
     */
    window.openSellerOrderInfoModal = async function(orderId) {
        var API = window.API || (window.__dashboardModuleLoader && window.__dashboardModuleLoader.deps && window.__dashboardModuleLoader.deps.API);
        if (!API) return;

        var bootstrap = window.bootstrap;
        var Utils = window.Utils;
        var Security = window.Security;

        var result = await API.getOrder(orderId);
        if (!result.success) {
            window.Toast && window.Toast.error(result.message || '订单不存在');
            return;
        }
        var order = result.order || {};
        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = `
            <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-1"></i>售出订单信息</h6>
            <div class="bg-light rounded-3 p-3 small">
                <div><strong>订单号：</strong><code>${Security.escapeHtml(order.id || '-')}</code></div>
                <div><strong>商品：</strong>${Security.escapeHtml(order.product_title || '-')}</div>
                <div><strong>买家：</strong>${order.guest_order ? '<span class="badge badge-secondary">游客买家</span> <span class="text-muted">信息已隐藏</span>' : Security.escapeHtml(order.buyer_name || '-')}</div>
                <div><strong>数量：</strong>${Security.escapeHtml(order.quantity || 1)}</div>
                <div><strong>收入：</strong><span class="text-success fw-semibold">¥${Number(order.seller_amount || order.price || 0).toFixed(2)}</span></div>
                <div><strong>时间：</strong>${Utils.formatDate(order.purchase_date)}</div>
            </div>
            ${order.guest_order ? '<div class="alert alert-warning small mt-3 mb-0">这是游客订单，卖家端不会展示游客身份标识、联系方式或游客查询密钥。</div>' : ''}
        `;
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>';
        modal.show();
    };

    /**
     * 打开卖家投诉弹窗
     */
    window.openSellerComplaintModal = async function(orderId) {
        var API = window.API || (window.__dashboardModuleLoader && window.__dashboardModuleLoader.deps && window.__dashboardModuleLoader.deps.API);
        if (!API) return;

        var bootstrap = window.bootstrap;
        var Utils = window.Utils;
        var Security = window.Security;

        var result = await API.getOrder(orderId);
        if (!result.success) {
            window.Toast && window.Toast.error(result.message || '订单不存在');
            return;
        }
        var order = result.order;
        var complaint = order.complaint || {};
        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        var messages = Array.isArray(complaint.messages) && complaint.messages.length
            ? complaint.messages
            : [
                complaint.reason ? { role: 'buyer', username: complaint.buyer_name || order.buyer_name || '买家', content: complaint.reason, created_at: complaint.created_at } : null,
                complaint.seller_reply ? { role: 'seller', username: order.seller_name || '卖家', content: complaint.seller_reply, created_at: complaint.seller_replied_at || complaint.updated_at } : null
            ].filter(Boolean);
        var sellerStatusInfo = (function() {
            var map = {
                open: ['warning', '待处理'],
                processing: ['primary', '处理中'],
                following: ['info', '跟进中'],
                resolved: ['success', '卖家胜'],
                rejected: ['danger', '买家胜'],
                withdrawn: ['secondary', '已撤诉']
            };
            return map[complaint.status || 'open'] || ['info', complaint.status || '已记录'];
        })();
        var sellerComplaintActive = !['resolved', 'rejected', 'withdrawn'].includes(complaint.status || 'open');
        var sellerAdminProgressHtml = (complaint.admin_reply || complaint.admin_status_by || complaint.admin_replied_by) ? '<div class="alert alert-info py-2 small mb-3"><div class="d-flex justify-content-between gap-2 mb-1"><strong><i class="bi bi-headset me-1"></i>平台处理状态：' + Security.escapeHtml(sellerStatusInfo[1]) + '</strong><span class="text-muted">' + Utils.formatDate(complaint.admin_status_at || complaint.admin_replied_at || complaint.updated_at) + '</span></div>' + (complaint.admin_reply ? '<div><strong>平台回复：</strong>' + Security.escapeHtml(complaint.admin_reply) + '</div>' : '<div class="text-muted">平台已更新处理状态，请留意后续处理结果。</div>') + '</div>' : '';
        var messagesHtml = messages.map(function(msg) {
            return '<div class="complaint-thread-item ' + (msg.role === 'seller' ? 'seller' : 'buyer') + '"><div class="d-flex justify-content-between gap-2 mb-1"><strong>' + (msg.role === 'seller' ? '卖家' : '买家') + '：' + Security.escapeHtml(msg.username || '') + '</strong><small class="text-muted">' + Utils.formatDate(msg.created_at) + '</small></div><div>' + Security.escapeHtml(msg.content || '') + '</div></div>';
        }).join('');
        document.getElementById('purchaseBody').innerHTML = '<h6 class="fw-bold mb-3"><i class="bi bi-exclamation-circle me-1"></i>订单投诉</h6><div class="bg-light rounded-3 p-3 mb-3 small"><div><strong>商品：</strong>' + Security.escapeHtml(order.product_title || '-') + '</div><div><strong>买家：</strong>' + Security.escapeHtml(order.buyer_name || '-') + '</div><div><strong>冻结金额：</strong>¥' + Number(order.frozen_amount || 0).toFixed(2) + '</div><div><strong>投诉时间：</strong>' + Utils.formatDate(complaint.created_at) + '</div><div><strong>当前状态：</strong><span class="badge badge-' + Security.escapeHtml(sellerStatusInfo[0]) + '">' + Security.escapeHtml(sellerStatusInfo[1]) + '</span></div><div><strong>最近更新：</strong>' + Utils.formatDate(complaint.updated_at || complaint.created_at) + '</div></div>' + sellerAdminProgressHtml + '<div class="mb-3"><label class="form-label">投诉沟通记录</label><div class="complaint-thread-list">' + (messagesHtml || '<div class="text-muted small">暂无沟通记录</div>') + '</div></div>' + (sellerComplaintActive ? '<div class="mb-3"><label class="form-label">继续回复</label><textarea class="form-control" id="sellerComplaintReply" rows="4" maxlength="500" placeholder="继续沟通处理进度、解决方案或说明"></textarea><small class="text-muted">最多 500 字</small></div>' : '<div class="alert alert-secondary py-2 small mb-0">该投诉已结束，不能继续回复。</div>');
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>' + (sellerComplaintActive ? "<button class=\"btn btn-primary\" onclick=\"window.submitSellerComplaintReply('" + Security.escapeAttr(orderId) + "')\">提交回复</button>" : '');
        modal.show();
    };

    /**
     * 提交卖家投诉回复
     */
    window.submitSellerComplaintReply = async function(orderId) {
        var API = window.API;
        var reply = document.getElementById('sellerComplaintReply') && document.getElementById('sellerComplaintReply').value || '';
        var result = await API.replyComplaint(orderId, reply);
        if (result.success) {
            window.Toast && window.Toast.success(result.message || '回复已提交');
            bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal')).hide();
            window.renderDashboardTab && window.renderDashboardTab('sales');
        } else {
            window.Toast && window.Toast.error(result.message || '回复失败');
        }
    };

})();
