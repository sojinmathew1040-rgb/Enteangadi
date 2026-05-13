<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$other_user_id = $_GET['user_id'] ?? 0;
$product_id = $_GET['product_id'] ?? 0;
$my_id = $_SESSION['user_id'];

if (!$other_user_id || !$product_id) {
    header("Location: inbox.php");
    exit;
}

if ($other_user_id == $my_id) {
    header("Location: ../product.php?id=$product_id");
    exit;
}

// Fetch details for the header
$stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->execute([$other_user_id]);
$other_user = $stmt->fetch();

$stmt2 = $pdo->prepare("
    SELECT p.title, p.price, 
    (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as thumbnail 
    FROM products p 
    WHERE p.id = ?
");
$stmt2->execute([$product_id]);
$product = $stmt2->fetch();

if (!$other_user || !$product) {
    header("Location: inbox.php");
    exit;
}

require_once '../includes/header.php';
?>

<div class="chat-page-wrapper">
    <div class="container chat-main-container">
        <div class="chat-window-premium">
            <!-- Chat Header -->
            <div class="chat-header-premium">
                <div class="header-left">
                    <a href="inbox.php" class="back-btn"><i class="fa fa-chevron-left"></i></a>
                    <div class="user-info-chat">
                        <?php if (!empty($other_user['profile_picture'])): ?>
                            <img src="<?= $base_url ?>/<?= htmlspecialchars($other_user['profile_picture']) ?>"
                                class="header-avatar">
                        <?php else: ?>
                            <div class="header-avatar-initials"><?= strtoupper(substr($other_user['username'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="user-meta">
                            <span class="user-name"><?= htmlspecialchars($other_user['username']) ?></span>
                            <span class="online-status">Online</span>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="deleteChat()" class="btn-chat-action delete" title="Delete Conversation">
                        <i class="fa fa-trash-alt"></i>
                    </button>
                </div>
            </div>

            <!-- Product Context Bar -->
            <div class="product-context-bar">
                <div class="product-mini-info">
                    <div class="product-mini-thumb">
                        <?php if (!empty($product['thumbnail'])): ?>
                            <img src="<?= $base_url ?>/<?= htmlspecialchars($product['thumbnail']) ?>">
                        <?php else: ?>
                            <div class="no-thumb-mini"><i class="fa fa-image"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="product-mini-text">
                        <span class="mini-title"><?= htmlspecialchars($product['title']) ?></span>
                        <span class="mini-price">₹<?= number_format($product['price']) ?></span>
                    </div>
                </div>
                <a href="../product.php?id=<?= $product_id ?>" class="btn-view-ad-chat">View Ad</a>
            </div>

            <!-- Messages Area -->
            <div id="chat-box" class="messages-area">
                <div class="loading-messages">
                    <div class="chat-spinner"></div>
                    <span>Fetching conversation...</span>
                </div>
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                <div class="input-wrapper-premium">
                    <input type="text" id="message-input" placeholder="Type a message..." autocomplete="off">
                    <button onclick="sendMessage()" id="sendBtn" class="btn-send-chat">
                        <i class="fa fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const myId = <?= $my_id ?>;
    const otherId = <?= $other_user_id ?>;
    const productId = <?= $product_id ?>;
    const chatBox = document.getElementById('chat-box');
    const messageInput = document.getElementById('message-input');
    const otherUserPic = <?= json_encode(!empty($other_user['profile_picture']) ? $base_url . '/' . $other_user['profile_picture'] : null) ?>;
    const otherUserInitial = <?= json_encode(strtoupper(substr($other_user['username'], 0, 1))) ?>;
    let isFirstLoad = true;
    let lastMessageCount = 0;

    function fetchMessages() {
        fetch(`api_chat.php?action=fetch&other_id=${otherId}&product_id=${productId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderMessages(data.messages);
                }
            });
    }

    function renderMessages(messages) {
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
        messages.forEach((msg, index) => {
            const isMe = msg.sender_id == myId;
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
        const text = messageInput.value.trim();
        if (!text) return;

        messageInput.value = '';
        messageInput.focus();

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

    messageInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    function escapeHtml(unsafe) {
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    fetchMessages();
    let pollInterval = setInterval(fetchMessages, 3000);

    function deleteChat() {
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
</script>

<style>
    .chat-page-wrapper {
        background: #f8fafc;
        height: calc(100vh - 70px);
        padding: 20px 0;
    }

    .chat-main-container {
        max-width: 800px;
        margin: 0 auto;
        height: 100%;
    }

    .chat-window-premium {
        background: white;
        border-radius: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.03);
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #f1f5f9;
    }

    /* Header Styling */
    .chat-header-premium {
        padding: 16px 24px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: white;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .back-btn {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: #f8fafc;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: 0.3s;
    }

    .back-btn:hover {
        background: #f1f5f9;
        color: var(--primary-green);
    }

    .user-info-chat {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .header-avatar,
    .header-avatar-initials {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        object-fit: cover;
    }

    .header-avatar-initials {
        background: var(--primary-green);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
    }

    .user-meta {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-weight: 800;
        color: var(--text-dark);
        font-size: 16px;
    }

    .online-status {
        font-size: 12px;
        color: var(--primary-green);
        font-weight: 700;
    }

    .btn-chat-action.delete {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        border: none;
        background: #fff1f2;
        color: #e11d48;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-chat-action.delete:hover {
        background: #ffe4e6;
        transform: scale(1.05);
    }

    /* Product Bar Styling */
    .product-context-bar {
        padding: 12px 24px;
        background: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .product-mini-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .product-mini-thumb {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        overflow: hidden;
        background: white;
    }

    .product-mini-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-mini-text {
        display: flex;
        flex-direction: column;
    }

    .mini-title {
        font-weight: 700;
        font-size: 14px;
        color: var(--text-dark);
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mini-price {
        font-weight: 800;
        font-size: 13px;
        color: var(--primary-green);
    }

    .btn-view-ad-chat {
        padding: 8px 16px;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        color: var(--text-dark);
        transition: 0.3s;
    }

    .btn-view-ad-chat:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }

    /* Messages Styling */
    .messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        background: #ffffff;
        background-image: radial-gradient(#f1f5f9 1px, transparent 1px);
        background-size: 20px 20px;
    }

    .message-row {
        display: flex;
        width: 100%;
    }

    .msg-me {
        justify-content: flex-end;
    }

    .msg-other {
        justify-content: flex-start;
    }

    .message-bubble {
        max-width: 75%;
        padding: 12px 16px;
        border-radius: 20px;
        position: relative;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
    }

    .msg-me .message-bubble {
        background: var(--primary-green);
        color: white;
        border-bottom-right-radius: 4px;
    }

    .msg-other .message-bubble {
        background: #f1f5f9;
        color: var(--text-dark);
        border-bottom-left-radius: 4px;
    }

    .message-text {
        font-size: 15px;
        line-height: 1.5;
        font-weight: 500;
        word-break: break-word;
    }

    .message-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 4px;
        margin-top: 4px;
        opacity: 0.7;
    }

    .msg-time {
        font-size: 10px;
        font-weight: 600;
    }

    .msg-status {
        font-size: 10px;
    }

    /* Input Area Styling */
    .chat-input-area {
        padding: 20px 24px;
        background: white;
        border-top: 1px solid #f1f5f9;
    }

    .input-wrapper-premium {
        background: #f8fafc;
        border-radius: 20px;
        padding: 6px 6px 6px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid #f1f5f9;
        transition: 0.3s;
    }

    .input-wrapper-premium:focus-within {
        background: white;
        border-color: var(--primary-green);
        box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.1);
    }

    .input-wrapper-premium input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 12px 0;
        font-size: 15px;
        font-weight: 600;
        color: var(--text-dark);
        outline: none;
    }

    .btn-send-chat {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        background: var(--primary-green);
        color: white;
        border: none;
        cursor: pointer;
        transition: 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .btn-send-chat:hover {
        transform: scale(1.05) rotate(-5deg);
        box-shadow: 0 5px 15px rgba(34, 197, 94, 0.3);
    }

    /* Loading & Empty States */
    .loading-messages,
    .empty-chat-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
    }

    .chat-spinner {
        width: 30px;
        height: 30px;
        border: 3px solid #f1f5f9;
        border-top: 3px solid var(--primary-green);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 12px;
    }

    .empty-chat-icon {
        font-size: 48px;
        color: #f1f5f9;
        margin-bottom: 16px;
    }

    .empty-chat-state h3 {
        color: var(--text-dark);
        font-weight: 800;
        margin-bottom: 8px;
    }

    .empty-chat-state p {
        font-weight: 600;
        font-size: 14px;
    }

    @keyframes spin {
        100% {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 600px) {
        .chat-page-wrapper {
            padding: 0;
            height: calc(100vh - 60px);
        }

        .chat-window-premium {
            border-radius: 0;
            border: none;
        }

        .message-bubble {
            max-width: 85%;
        }

        .mini-title {
            max-width: 120px;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>