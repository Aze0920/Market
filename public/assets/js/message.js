/**
 * 私信模块
 */

function openMessagesModal() {
    if (!App.currentUser) {
        Toast.warning('请先登录');
        openLoginModal();
        return;
    }

    App.currentChatPartner = null;
    document.getElementById('chatMessages').innerHTML = `
        <div class="empty-state">
            <i class="bi bi-chat-dots"></i>
            <p>选择一个联系人或搜索用户开始对话</p>
        </div>
    `;
    document.getElementById('chatInputArea').classList.add('hidden');
    document.getElementById('newChatArea').classList.remove('hidden');
    document.getElementById('searchUserInput').value = '';
    document.getElementById('userSearchResults').innerHTML = '';

    renderContactList();

    const modal = new bootstrap.Modal(document.getElementById('messagesModal'));
    modal.show();
}

async function renderContactList() {
    const result = await API.getContacts();
    const contacts = result.success ? result.contacts : [];

    const listEl = document.getElementById('contactList');
    listEl.innerHTML = `
        <p class="text-muted small px-2 mb-2">联系人</p>
        ${contacts.length === 0 ?
            '<p class="text-muted small px-2">暂无联系人</p>' :
            contacts.map(c => `
                <div class="sidebar-nav-item" onclick="selectContact('${c.username}')">
                    <span>${c.username}</span>
                    ${c.unread > 0 ? `<span class="badge badge-danger ms-auto">${c.unread}</span>` : ''}
                </div>
            `).join('')
        }
    `;
}

async function selectContact(username) {
    App.currentChatPartner = username;

    const result = await API.getConversation(username);
    const messages = result.success ? result.messages : [];

    document.getElementById('chatMessages').innerHTML = messages.map(m => `
        <div class="chat-bubble ${m.from === App.currentUser.username ? 'sent' : 'received'}">
            ${m.content}
            <span class="chat-time">${Utils.formatDate(m.timestamp)}</span>
        </div>
    `).join('');

    document.getElementById('chatInputArea').classList.remove('hidden');
    document.getElementById('newChatArea').classList.add('hidden');
    document.getElementById('chatMessageInput').value = '';

    const chatContainer = document.getElementById('chatMessages');
    chatContainer.scrollTop = chatContainer.scrollHeight;

    await API.getConversation(username); // 标记已读
    App.updateUnreadBadge();
    renderContactList();
}

async function sendMessage() {
    const input = document.getElementById('chatMessageInput');
    const content = input.value.trim();

    if (!content || !App.currentChatPartner) return;

    const result = await API.sendMessage(App.currentChatPartner, content);

    if (result.success) {
        input.value = '';
        await selectContact(App.currentChatPartner);
    } else {
        Toast.error(result.message);
    }
}

function searchUserForChat() {
    const query = document.getElementById('searchUserInput').value.trim();
    const resultsDiv = document.getElementById('userSearchResults');

    if (!query) {
        resultsDiv.innerHTML = '';
        return;
    }

    API.searchUsers(query).then(result => {
        if (!result.success || result.users.length === 0) {
            resultsDiv.innerHTML = '<span class="text-muted small">未找到用户</span>';
            return;
        }
        resultsDiv.innerHTML = result.users.map(u =>
            `<span class="badge badge-primary me-1 mb-1" style="cursor:pointer; padding: 0.4rem 0.6rem;"
                   onclick="selectContact('${u.username}');document.getElementById('searchUserInput').value='';document.getElementById('userSearchResults').innerHTML='';">
                ${u.username} <i class="bi bi-chat-dots"></i>
            </span>`
        ).join('');
    });
}
