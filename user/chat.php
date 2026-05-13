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



<?php require_once '../includes/footer.php'; ?>