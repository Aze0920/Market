/**
 * Dashboard - 私信模块
 */
(function() {
    'use strict';

    /**
     * 渲染消息 Tab
     */
    window.render_messages_tab = async function(area, deps) {
        if (!area) return;
        var API = deps.API;
        var App = deps.App;
        var Utils = deps.Utils;
        var Security = deps.Security;

        var result = await API.getContacts();
        var contacts = result.success ? result.contacts : [];
        var selectedPartner = App.currentChatPartner;

        area.innerHTML = '<h5 class="fw-bold mb-4"><i class="bi bi-chat-dots me-2"></i>私信</h5><div class="row"><div class="col-md-4 border-end pe-0" style="max-height: 400px; overflow-y: auto;"><div class="p-2"><input type="text" class="form-control form-control-sm mb-2" id="tabSearchUser" placeholder="搜索用户..." onkeypress="if(event.key===\'Enter\')window.searchUserForChatTab()"><div id="tabUserSearchResults" class="mb-2 small"></div><div id="contactListTab"><p class="text-muted small px-2">联系人</p>' + (contacts.length === 0 ? '<p class="text-muted small px-2">暂无联系人</p>' : contacts.map(function(c) {
            return '<div class="sidebar-nav-item' + (App.currentChatPartner === c.username ? ' active' : '') + '" data-username="' + Security.escapeAttr(c.username) + '" onclick="window.selectContactTab(\'' + Security.escapeAttr(c.username) + '\')"><span>' + Security.escapeHtml(c.username) + '</span>' + (c.unread > 0 ? '<span class="badge badge-danger ms-auto">' + Security.escapeHtml(c.unread) + '</span>' : '') + '</div>';
        }).join('')) + '</div></div></div><div class="col-md-8 ps-0" id="tabChatArea"><div class="empty-state"><i class="bi bi-chat-dots"></i><p>选择联系人开始对话</p></div></div></div>';

        if (selectedPartner) {
            window.selectContactTab(selectedPartner, { skipReadRefresh: true });
        }
    };

    /**
     * 选择联系人 Tab
     */
    window.selectContactTab = async function(username, options) {
        options = options || {};
        var API = window.API;
        if (!API) return;

        var App = window.App;
        var Utils = window.Utils;
        var Security = window.Security;

        App.currentChatPartner = username;

        document.querySelectorAll('#contactListTab .sidebar-nav-item').forEach(function(item) {
            var name = item.dataset.username || item.textContent.trim();
            item.classList.toggle('active', name === username);
        });

        var result = await API.getConversation(username);
        var messages = result.success ? result.messages : [];

        var chatArea = document.getElementById('tabChatArea');
        chatArea.innerHTML = '<div class="d-flex flex-column h-100"><div class="p-2 border-bottom bg-light d-flex justify-content-between align-items-center"><strong>' + Security.escapeHtml(username) + '</strong><button class="btn btn-sm btn-outline" onclick="window.refreshCurrentConversation()"><i class="bi bi-arrow-clockwise"></i></button></div><div class="chat-container flex-grow-1" id="tabChatMessages">' + messages.map(function(m) {
            return '<div class="chat-bubble ' + (m.from === App.currentUser.username ? 'sent' : 'received') + '">' + Security.escapeHtml(m.content) + '<span class="chat-time">' + Utils.formatDate(m.timestamp) + '</span></div>';
        }).join('') || '<div class="empty-state py-4"><p>暂无消息，开始聊天吧</p></div>' + '</div><div class="chat-input-area"><input type="text" class="form-control" id="tabChatInput" placeholder="输入消息..." onkeypress="if(event.key===\'Enter\')window.sendMessageTab()"><button class="btn btn-primary" onclick="window.sendMessageTab()"><i class="bi bi-send"></i></button></div></div>';

        var chatContainer = document.getElementById('tabChatMessages');
        if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;

        if (!options.skipReadRefresh) {
            await API.getConversation(username);
            App.updateUnreadBadge && App.updateUnreadBadge();
            window.refreshContactListTab && window.refreshContactListTab({ keepSelection: true });
        }
    };

    /**
     * 刷新联系人列表
     */
    window.refreshContactListTab = async function(options) {
        options = options || {};
        var API = window.API;
        if (!API) return;

        var App = window.App;
        var Security = window.Security;

        var result = await API.getContacts();
        var contacts = result.success ? result.contacts : [];
        var list = document.getElementById('contactListTab');
        if (!list) return;

        list.innerHTML = '<p class="text-muted small px-2">联系人</p>' + (contacts.length === 0 ? '<p class="text-muted small px-2">暂无联系人</p>' : contacts.map(function(c) {
            return '<div class="sidebar-nav-item' + (App.currentChatPartner === c.username ? ' active' : '') + '" data-username="' + Security.escapeAttr(c.username) + '" onclick="window.selectContactTab(\'' + Security.escapeAttr(c.username) + '\')"><span>' + Security.escapeHtml(c.username) + '</span>' + (c.unread > 0 ? '<span class="badge badge-danger ms-auto">' + Security.escapeHtml(c.unread) + '</span>' : '') + '</div>';
        }).join(''));

        if (options.keepSelection && App.currentChatPartner) {
            var active = Array.from(list.querySelectorAll('.sidebar-nav-item')).find(function(item) {
                return item.dataset.username === App.currentChatPartner;
            });
            if (active) active.classList.add('active');
        }
    };

    /**
     * 刷新当前对话
     */
    window.refreshCurrentConversation = async function() {
        var App = window.App;
        if (App && App.currentChatPartner) {
            await window.selectContactTab(App.currentChatPartner, { skipReadRefresh: true });
        }
    };

    /**
     * 发送消息 Tab
     */
    window.sendMessageTab = async function() {
        var API = window.API;
        if (!API) return;

        var App = window.App;
        var input = document.getElementById('tabChatInput');
        var content = input && input.value.trim() || '';
        if (!content || !App.currentChatPartner) return;

        await API.sendMessage(App.currentChatPartner, content);
        if (input) input.value = '';
        await window.selectContactTab(App.currentChatPartner);
    };

    /**
     * 搜索用户
     */
    window.searchUserForChatTab = function() {
        var API = window.API;
        if (!API) return;

        var Security = window.Security;
        var query = document.getElementById('tabSearchUser') && document.getElementById('tabSearchUser').value.trim() || '';
        var resultsDiv = document.getElementById('tabUserSearchResults');
        if (!query) {
            if (resultsDiv) resultsDiv.innerHTML = '';
            return;
        }

        API.searchUsers(query).then(function(result) {
            if (!result.success || !result.users || result.users.length === 0) {
                if (resultsDiv) resultsDiv.innerHTML = '<span class="text-muted">未找到用户</span>';
                return;
            }
            if (resultsDiv) {
                resultsDiv.innerHTML = result.users.map(function(u) {
                    return '<span class="badge badge-primary me-1" style="cursor:pointer;" onclick="window.selectContactTab(\'' + Security.escapeAttr(u.username) + '\');if(document.getElementById(\'tabSearchUser\'))document.getElementById(\'tabSearchUser\').value=\'\';if(document.getElementById(\'tabUserSearchResults\'))document.getElementById(\'tabUserSearchResults\').innerHTML=\'\';">' + Security.escapeHtml(u.username) + ' <i class="bi bi-chat-dots"></i></span>';
                }).join('');
            }
        });
    };

})();
