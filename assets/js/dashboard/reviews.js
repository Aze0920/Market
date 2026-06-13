/**
 * Dashboard - 评价模块
 */
(function() {
    'use strict';

    /**
     * 渲染评价 Tab
     */
    window.render_reviews_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var result = await API.getProductReviews();
        var comments = result.success ? result.comments : [];

        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-star-half me-2"></i>评价管理</h5>' + (comments.length === 0 ? '<div class="empty-state"><i class="bi bi-chat-square-heart"></i><h5>暂无评价</h5><p>买家评价后会显示在这里</p></div>' : '<div class="table-responsive"><table class="table"><thead><tr><th>商品</th><th>买家</th><th>评分</th><th>内容</th><th>时间</th></tr></thead><tbody>' + comments.map(function(c) {
            var rating = Number(c.rating || 0);
            var starsFilled = '★'.repeat(rating);
            var starsEmpty = '☆'.repeat(5 - rating);
            return '<tr><td>' + Security.escapeHtml(Utils.truncate(c.product_title || '-', 24)) + '</td><td>' + Security.escapeHtml(c.buyer_name || c.username || '-') + '</td><td><span class="text-warning">' + starsFilled + '</span><span class="text-muted">' + starsEmpty + '</span></td><td>' + Security.escapeHtml(c.content || '未填写评价内容') + '</td><td class="text-muted small">' + Utils.formatDate(c.created_at) + '</td></tr>';
        }).join('') + '</tbody></table></div>');
    };

})();
