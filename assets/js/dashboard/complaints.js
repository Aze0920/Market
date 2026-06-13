/**
 * Dashboard - 投诉管理模块
 */
(function() {
    'use strict';

    /**
     * 渲染投诉 Tab
     */
    window.render_complaints_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var ordersResult = await API.getMyOrders();
        var salesResult = await API.getMySales();
        var buyerComplaints = ordersResult.success ? ordersResult.orders.filter(function(o) {
            return o.complaint;
        }) : [];
        var sellerComplaints = salesResult.success ? salesResult.orders.filter(function(o) {
            return o.complaint;
        }) : [];

        area.innerHTML = '<div class="d-flex justify-content-between align-items-center mb-4"><h5 class="fw-bold mb-0"><i class="bi bi-exclamation-octagon me-2 text-warning"></i>投诉管理</h5><span class="badge badge-warning">' + (buyerComplaints.length + sellerComplaints.length) + ' 条</span></div><div class="row g-3"><div class="col-lg-6"><div class="card h-100"><div class="card-body"><h6 class="fw-bold mb-3"><i class="bi bi-cart-check me-1"></i>我的投诉</h6>' + (buyerComplaints.length === 0 ? '<p class="text-muted small mb-0">暂无你发起的投诉</p>' : buyerComplaints.map(function(o) {
            return renderComplaintCard(o, 'buyer');
        }).join('')) + '</div></div></div><div class="col-lg-6"><div class="card h-100"><div class="card-body"><h6 class="fw-bold mb-3"><i class="bi bi-shop me-1"></i>收到的投诉</h6>' + (sellerComplaints.length === 0 ? '<p class="text-muted small mb-0">暂无买家投诉</p>' : sellerComplaints.map(function(o) {
            return renderComplaintCard(o, 'seller');
        }).join('')) + '</div></div></div></div>';
    };

    function complaintStatusInfo(complaint) {
        var status = complaint && complaint.status || 'open';
        var map = {
            open: ['warning', '待处理'],
            processing: ['primary', '处理中'],
            following: ['info', '跟进中'],
            resolved: ['success', '卖家胜'],
            rejected: ['danger', '买家胜'],
            withdrawn: ['secondary', '已撤诉']
        };
        return map[status] || ['info', status || '已记录'];
    }

    function isComplaintActive(complaint) {
        return complaint && !['resolved', 'rejected', 'withdrawn'].includes(complaint.status || 'open');
    }

    function renderStatus(complaint) {
        if (!complaint) return '<span class="badge badge-secondary">无投诉</span>';
        var info = complaintStatusInfo(complaint);
        return '<span class="badge badge-' + info[0] + '">' + info[1] + '</span>';
    }

    function renderAdminProgress(complaint) {
        if (!complaint) return '';
        var info = complaintStatusInfo(complaint);
        var adminReply = complaint.admin_reply || '';
        var statusAt = complaint.admin_status_at || complaint.admin_replied_at || complaint.updated_at;
        if (!adminReply && !complaint.admin_status_by && !complaint.admin_replied_by) return '';
        return '<div class="alert alert-' + (info[0] === 'danger' ? 'danger' : info[0] === 'success' ? 'success' : 'info') + ' py-2 small mb-3"><div class="d-flex justify-content-between gap-2 mb-1"><strong><i class="bi bi-headset me-1"></i>平台处理状态：' + Security.escapeHtml(info[1]) + '</strong><span class="text-muted">' + Utils.formatDate(statusAt) + '</span></div>' + (adminReply ? '<div><strong>平台回复：</strong>' + Security.escapeHtml(adminReply) + '</div>' : '<div class="text-muted">平台已更新处理状态，请留意后续处理结果。</div>') + '</div>';
    }

    function renderComplaintMessages(order) {
        var complaint = order.complaint || {};
        var messages = Array.isArray(complaint.messages) && complaint.messages.length
            ? complaint.messages
            : [
                complaint.reason ? { role: 'buyer', username: complaint.buyer_name || order.buyer_name || '买家', content: complaint.reason, created_at: complaint.created_at } : null,
                complaint.seller_reply ? { role: 'seller', username: order.seller_name || '卖家', content: complaint.seller_reply, created_at: complaint.seller_replied_at || complaint.updated_at } : null
            ].filter(Boolean);
        if (!messages.length) return '';
        return '<div class="complaint-thread-list compact mb-3">' + messages.map(function(msg) {
            return '<div class="complaint-thread-item ' + (msg.role === 'seller' ? 'seller' : 'buyer') + '"><div class="d-flex justify-content-between gap-2 mb-1"><strong>' + (msg.role === 'seller' ? '卖家' : '买家') + (msg.username ? '：' + Security.escapeHtml(msg.username) : '') + '</strong><small class="text-muted">' + Utils.formatDate(msg.created_at) + '</small></div><div>' + Security.escapeHtml(msg.content || '') + '</div></div>';
        }).join('') + '</div>';
    }

    function renderComplaintCard(order, role) {
        var complaint = order.complaint;
        var active = isComplaintActive(complaint);
        var escapedId = order.id ? order.id.replace(/'/g, '\\\'') : '';
        return '<div class="complaint-manage-card"><div class="d-flex justify-content-between align-items-start gap-3 mb-2"><div><div class="fw-bold">' + Security.escapeHtml(order.product_title || '-') + '</div><div class="text-muted small">' + (role === 'buyer' ? '我是买家' : '我是卖家') + ' · 订单 ' + Security.escapeHtml(order.id || '-') + ' · 冻结 ¥' + Number(order.frozen_amount || 0).toFixed(2) + ' · ' + Utils.formatDate(complaint && complaint.created_at || order.purchase_date) + '</div></div>' + renderStatus(complaint) + '</div>' + renderAdminProgress(complaint) + renderComplaintMessages(order) + '<div class="d-flex flex-wrap gap-2 justify-content-end">' + (role === 'buyer' && active ? "<button class=\"btn btn-sm btn-outline-primary\" onclick=\"window.openComplaintThreadModal('" + escapedId + "', 'buyer')\">查看实时情况/继续沟通</button><button class=\"btn btn-sm btn-warning\" onclick=\"window.openWithdrawComplaintModal('" + escapedId + "')\">撤诉</button>" : '') + (role === 'seller' && active ? "<button class=\"btn btn-sm btn-primary\" onclick=\"window.openSellerComplaintModal('" + escapedId + "')\">查看实时情况/回复</button>" : '') + '</div></div>';
    }

    /**
     * 打开投诉详情弹窗
     */
    window.openComplaintThreadModal = async function(orderId, role) {
        var API = window.API;
        if (!API) return;

        var bootstrap = window.bootstrap;
        var Utils = window.Utils;
        var Security = window.Security;
        var Toast = window.Toast;

        var result = await API.getOrder(orderId);
        if (!result.success) {
            Toast.error(result.message || '订单不存在');
            return;
        }
        var order = result.order;
        var complaint = order.complaint || {};
        var messages = Array.isArray(complaint.messages) && complaint.messages.length
            ? complaint.messages
            : [
                complaint.reason ? { role: 'buyer', username: complaint.buyer_name || order.buyer_name || '买家', content: complaint.reason, created_at: complaint.created_at } : null,
                complaint.seller_reply ? { role: 'seller', username: order.seller_name || '卖家', content: complaint.seller_reply, created_at: complaint.seller_replied_at || complaint.updated_at } : null
            ].filter(Boolean);
        var statusInfo = complaintStatusInfo(complaint);
        var activeComplaint = isComplaintActive(complaint);
        var adminProgressHtml = (complaint.admin_reply || complaint.admin_status_by || complaint.admin_replied_by) ? '<div class="alert alert-info py-2 small mb-3"><div class="d-flex justify-content-between gap-2 mb-1"><strong><i class="bi bi-headset me-1"></i>平台处理状态：' + Security.escapeHtml(statusInfo[1]) + '</strong><span class="text-muted">' + Utils.formatDate(complaint.admin_status_at || complaint.admin_replied_at || complaint.updated_at) + '</span></div>' + (complaint.admin_reply ? '<div><strong>平台回复：</strong>' + Security.escapeHtml(complaint.admin_reply) + '</div>' : '<div class="text-muted">平台已更新处理状态，请留意后续处理结果。</div>') + '</div>' : '';
        var messagesHtml = messages.map(function(msg) {
            return '<div class="complaint-thread-item ' + (msg.role === 'seller' ? 'seller' : 'buyer') + '"><div class="d-flex justify-content-between gap-2 mb-1"><strong>' + (msg.role === 'seller' ? '卖家' : '买家') + (msg.username ? '：' + Security.escapeHtml(msg.username) : '') + '</strong><small class="text-muted">' + Utils.formatDate(msg.created_at) + '</small></div><div>' + Security.escapeHtml(msg.content || '') + '</div></div>';
        }).join('');
        var escapedId = orderId ? orderId.replace(/'/g, '\\\'') : '';
        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = '<h6 class="fw-bold mb-3"><i class="bi bi-chat-dots me-1"></i>投诉实时情况</h6><div class="bg-light rounded-3 p-3 mb-3 small"><div><strong>商品：</strong>' + Security.escapeHtml(order.product_title || '-') + '</div><div><strong>冻结金额：</strong>¥' + Number(order.frozen_amount || 0).toFixed(2) + '</div><div><strong>当前状态：</strong><span class="badge badge-' + Security.escapeHtml(statusInfo[0]) + '">' + Security.escapeHtml(statusInfo[1]) + '</span></div><div><strong>最近更新：</strong>' + Utils.formatDate(complaint.updated_at || complaint.created_at) + '</div></div>' + adminProgressHtml + '<div class="complaint-thread-list mb-3">' + (messagesHtml || '<div class="text-muted small">暂无沟通记录</div>') + '</div>' + (activeComplaint ? '<div class="mb-3"><label class="form-label">继续回复</label><textarea class="form-control" id="complaintReplyContent" rows="4" maxlength="500" placeholder="请输入要补充说明的内容"></textarea><small class="text-muted">最多 500 字</small></div>' : '<div class="alert alert-secondary py-2 small mb-0">该投诉已结束，不能继续回复。</div>');
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">关闭</button>' + (activeComplaint ? "<button class=\"btn btn-primary\" onclick=\"window.submitComplaintReply('" + escapedId + "', 'complaints')\">提交回复</button>" : '');
        modal.show();
    };

    /**
     * 提交投诉回复
     */
    window.submitComplaintReply = async function(orderId, refreshTab) {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        var reply = document.getElementById('sellerComplaintReply') && document.getElementById('sellerComplaintReply').value || document.getElementById('complaintReplyContent') && document.getElementById('complaintReplyContent').value || '';
        var result = await API.replyComplaint(orderId, reply);
        if (result.success) {
            Toast.success(result.message || '回复已提交');
            bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal')).hide();
            window.renderDashboardTab && window.renderDashboardTab(refreshTab || 'complaints');
        } else {
            Toast.error(result.message || '回复失败');
        }
    };

    /**
     * 打开投诉弹窗（买家）
     */
    window.openComplaintModal = async function(orderId) {
        var API = window.API;
        if (!API) return;

        var bootstrap = window.bootstrap;
        var Security = window.Security;

        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = '<h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle me-1"></i>投诉订单</h6><div class="alert alert-warning small">提交投诉后，该订单对应的卖家收入会被冻结。系统会把 8 位撤诉密码发送到你的邮箱，撤诉时必须输入该密码。</div><div class="mb-3"><label class="form-label">接收撤诉密码的邮箱</label><input type="email" class="form-control" id="complaintEmail" placeholder="your@email.com"></div><div class="mb-3"><label class="form-label">投诉原因</label><textarea class="form-control" id="complaintReason" rows="4" maxlength="500" placeholder="请说明问题，例如账号无法使用、商品与描述不符等"></textarea><small class="text-muted">最多 500 字</small></div>';
        var escapedId = orderId ? orderId.replace(/'/g, '\\\'') : '';
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">取消</button><button class="btn btn-danger" onclick="window.submitComplaint(\'' + escapedId + "')'>提交投诉</button>";
        modal.show();
    };

    /**
     * 提交投诉
     */
    window.submitComplaint = async function(orderId) {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        var email = document.getElementById('complaintEmail') && document.getElementById('complaintEmail').value || '';
        var reason = document.getElementById('complaintReason') && document.getElementById('complaintReason').value || '';
        var result = await API.complainOrder(orderId, email, reason);
        if (result.success) {
            Toast.success(result.message || '投诉已提交');
            bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal')).hide();
            window.renderDashboardTab && window.renderDashboardTab('orders');
        } else {
            Toast.error(result.message || '投诉提交失败');
        }
    };

    /**
     * 打开撤诉弹窗
     */
    window.openWithdrawComplaintModal = function(orderId) {
        var bootstrap = window.bootstrap;
        var Security = window.Security;
        var escapedId = orderId ? orderId.replace(/'/g, '\\\'') : '';
        var modal = new bootstrap.Modal(document.getElementById('purchaseConfirmModal'));
        document.getElementById('purchaseBody').innerHTML = '<h6 class="fw-bold mb-3"><i class="bi bi-arrow-counterclockwise me-1"></i>撤诉</h6><div class="alert alert-info small">请输入投诉时邮件收到的 8 位数字撤诉密码。撤诉后冻结金额会解冻给卖家。</div><div class="mb-3"><label class="form-label">撤诉密码</label><input type="text" class="form-control" id="withdrawComplaintPassword" maxlength="8" placeholder="8位数字密码"></div>';
        document.getElementById('purchaseFooter').innerHTML = '<button class="btn btn-outline" data-bs-dismiss="modal">取消</button><button class="btn btn-warning" onclick="window.submitWithdrawComplaint(\'' + escapedId + "')'>确认撤诉</button>";
        modal.show();
    };

    /**
     * 提交撤诉
     */
    window.submitWithdrawComplaint = async function(orderId) {
        var API = window.API;
        if (!API) return;

        var Toast = window.Toast;
        var password = document.getElementById('withdrawComplaintPassword') && document.getElementById('withdrawComplaintPassword').value || '';
        var result = await API.withdrawComplaint(orderId, password);
        if (result.success) {
            Toast.success(result.message || '已撤诉');
            bootstrap.Modal.getInstance(document.getElementById('purchaseConfirmModal')).hide();
            window.renderDashboardTab && window.renderDashboardTab('orders');
        } else {
            Toast.error(result.message || '撤诉失败');
        }
    };

})();
