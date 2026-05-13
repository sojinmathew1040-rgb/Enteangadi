/**
 * Enteangadi - Real-time Chat Logic
 * Manages message fetching, rendering, and auto-polling.
 */

let isFirstLoad = true;
let lastMessageCount = 0;

function fetchMessages() {
    // otherId and productId must be defined globally in the PHP file
    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;

    fetch(`api_chat.php?action=fetch&other_id=${otherId}&product_id=${productId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderMessages(data.messages);
            }
        });
}

function renderMessages(messages) {
    const chatBox = document.getElementById('chat-box');
    if (!chatBox) return;

    if (messages.length === 0 && isFirstLoad) {
        chatBox.innerHTML = `
            <div class="empty-chat-state">
                <div class="empty-chat-icon"><i class="fa fa-comments"></i></div>
                <h3>Start the conversation</h3>
                <p>Be the first to send a message about this listing.</p>
            </div>
        `;
        isFirstLoad = false;
        return;
    }

    lastMessageCount = messages.length;

    let html = '';
    messages.forEach((msg) => {
        // myId must be defined globally
        const isMe = typeof myId !== 'undefined' && msg.sender_id == myId;
        const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        html += `
            <div class="message-row ${isMe ? 'msg-me' : 'msg-other'}">
                <div class="message-bubble">
                    <div class="message-text">${escapeHtml(msg.message_text)}</div>
                    <div class="message-meta">
                        <span class="msg-time">${time}</span>
                        ${isMe ? '<i class="fa fa-check-double msg-status"></i>' : ''}
                    </div>
                </div>
            </div>
        `;
    });

    const isAtBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 100;

    if (chatBox.innerHTML !== html || isFirstLoad) {
        chatBox.innerHTML = html;
        if (isAtBottom || isFirstLoad) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
        isFirstLoad = false;
    }
}

function sendMessage() {
    const messageInput = document.getElementById('message-input');
    const chatBox = document.getElementById('chat-box');
    if (!messageInput || !chatBox) return;

    const text = messageInput.value.trim();
    if (!text) return;

    messageInput.value = '';
    messageInput.focus();

    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver_id', otherId);
    formData.append('product_id', productId);
    formData.append('message', text);

    fetch('api_chat.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                fetchMessages();
                setTimeout(() => chatBox.scrollTop = chatBox.scrollHeight, 100);
            }
        });
}

function escapeHtml(unsafe) {
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function deleteChat() {
    if (typeof otherId === 'undefined' || typeof productId === 'undefined') return;

    if (confirm("Permanently delete this conversation?")) {
        const formData = new FormData();
        formData.append('action', 'delete_chat');
        formData.append('other_id', otherId);
        formData.append('product_id', productId);
        fetch('api_chat.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = "inbox.php";
                }
            });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const messageInput = document.getElementById('message-input');
    if (messageInput) {
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    }

    fetchMessages();
    setInterval(fetchMessages, 3000);
});
