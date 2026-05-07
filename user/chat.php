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
    echo "<div class='container' style='padding: 40px; text-align: center;'><h3 style='color: var(--danger);'>You cannot chat with yourself.</h3><a href='../product.php?id=$product_id' class='btn-primary' style='display: inline-block; margin-top: 16px;'>Go Back</a></div>";
    exit;
}

// Fetch details for the header
$stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
$stmt->execute([$other_user_id]);
$other_user = $stmt->fetch();

$stmt2 = $pdo->prepare("SELECT title, price FROM products WHERE id = ?");
$stmt2->execute([$product_id]);
$product = $stmt2->fetch();

if (!$other_user || !$product) {
    echo "Invalid user or product.";
    exit;
}

require_once '../includes/header.php';
?>

<div class="container" style="padding: 10px 16px;">
    <div
        style="max-width: 600px; margin: 0 auto; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; height: 75vh; overflow: hidden;">

        <!-- Chat Header -->
        <div
            style="padding: 16px; background: var(--primary-green); color: white; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <a href="inbox.php" style="color: white; text-decoration: none;"><i class="fa fa-arrow-left"
                        style="font-size: 18px;"></i></a>
                <?php if (!empty($other_user['profile_picture'])): ?>
                    <img src="<?= $base_url ?>/<?= htmlspecialchars($other_user['profile_picture']) ?>"
                        style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
                <?php else: ?>
                    <div
                        style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.2); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">
                        <?= strtoupper(substr($other_user['username'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div style="font-weight: 600; font-size: 16px;"><?= htmlspecialchars($other_user['username']) ?>
                    </div>
                    <div
                        style="font-size: 12px; opacity: 0.9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;">
                        <?= htmlspecialchars($product['title']) ?> (₹<?= number_format($product['price']) ?>)
                    </div>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="deleteChat()" style="background: rgba(255,0,0,0.2); color: white; border: 1px solid rgba(255,255,255,0.5); font-size: 14px; text-decoration: none; padding: 6px 12px; border-radius: 16px; cursor: pointer;">
                    <i class="fa fa-trash"></i> Delete
                </button>
                <a href="../product.php?id=<?= $product_id ?>"
                    style="color: white; font-size: 14px; text-decoration: none; padding: 6px 12px; border: 1px solid rgba(255,255,255,0.5); border-radius: 16px; background: rgba(0,0,0,0.1);">View
                    Ad</a>
            </div>
        </div>

        <!-- Messages Area -->
        <div id="chat-box"
            style="flex: 1; padding: 16px; overflow-y: auto; background: #f0f2f5; display: flex; flex-direction: column; gap: 12px;">
            <!-- Messages will be loaded here -->
            <div style="text-align: center; color: #888; font-size: 14px; margin-top: auto; margin-bottom: auto;"
                id="loading-msg">Loading chat...</div>
        </div>

        <!-- Input Area -->
        <div
            style="padding: 12px 16px; background: white; border-top: 1px solid #e0e0e0; display: flex; gap: 8px; align-items: center;">
            <input type="text" id="message-input" placeholder="Type your message..."
                style="flex: 1; padding: 12px 16px; border: 1px solid #ddd; border-radius: 24px; outline: none; font-size: 15px; background: #f8f9fa;">
            <button onclick="sendMessage()"
                style="background: var(--primary-green); color: white; border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s;">
                <i class="fa fa-paper-plane" style="margin-right: 2px;"></i>
            </button>
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
            chatBox.innerHTML = '<div style="text-align: center; color: #888; font-size: 14px; margin-top: auto; margin-bottom: auto;">No messages yet. Send a message to start the conversation!</div>';
            isFirstLoad = false;
            return;
        }

        if (messages.length > lastMessageCount && lastMessageCount > 0) {
            const newMessages = messages.slice(lastMessageCount);
            const hasNewReceived = newMessages.some(m => m.sender_id == otherId);
            if (hasNewReceived && typeof playBeep === 'function') {
                playBeep();
            }
        }
        lastMessageCount = messages.length;

        let html = '';
        messages.forEach(msg => {
            const isMe = msg.sender_id == myId;
            const align = isMe ? 'flex-end' : 'flex-start';
            const bg = isMe ? 'var(--primary-green)' : '#ffffff';
            const color = isMe ? 'white' : '#333';
            const radius = isMe ? '18px 18px 4px 18px' : '18px 18px 18px 4px';
            const border = isMe ? 'none' : '1px solid #e0e0e0';
            const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            let avatarHtml = '';
            if (!isMe) {
                if (otherUserPic) {
                    avatarHtml = `<img src="${otherUserPic}" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; margin-right: 8px; align-self: flex-end; margin-bottom: 2px;">`;
                } else {
                    avatarHtml = `<div style="width: 28px; height: 28px; border-radius: 50%; background: #ccc; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; margin-right: 8px; align-self: flex-end; margin-bottom: 2px;">${otherUserInitial}</div>`;
                }
            }

            html += `
                <div style="display: flex; flex-direction: column; align-items: ${align}; width: 100%;">
                    <div style="display: flex; max-width: 85%;">
                        ${avatarHtml}
                        <div style="background: ${bg}; color: ${color}; border: ${border}; padding: 8px 12px; border-radius: ${radius}; font-size: 15px; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,0.05); display: flex; flex-direction: column; min-width: 80px;">
                            <div style="word-wrap: break-word; white-space: pre-wrap;">${escapeHtml(msg.message_text)}</div>
                            <div style="font-size: 10px; opacity: 0.7; text-align: right; margin-top: 4px; display: flex; justify-content: flex-end; gap: 4px; align-items: center;">
                                ${time}
                                ${isMe ? '<i class="fa fa-check" style="font-size: 10px;"></i>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        // Auto-scroll if content changed
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

        messageInput.value = ''; // clear input
        messageInput.focus();

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('receiver_id', otherId);
        formData.append('product_id', productId);
        formData.append('message', text);

        // Optimistic UI update could be done here, but fetch is fast enough for now

        fetch('api_chat.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    fetchMessages();
                    setTimeout(() => chatBox.scrollTop = chatBox.scrollHeight, 100);
                } else {
                    alert("Error sending message.");
                }
            });
    }

    messageInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Initial fetch and poll
    fetchMessages();
    let pollInterval = setInterval(fetchMessages, 3000); // poll every 3 seconds

    function deleteChat() {
        if (confirm("Are you sure you want to permanently delete this conversation?")) {
            const formData = new FormData();
            formData.append('action', 'delete_chat');
            formData.append('other_id', otherId);
            formData.append('product_id', productId);

            fetch('api_chat.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    clearInterval(pollInterval);
                    alert("Chat deleted.");
                    window.location.href = "inbox.php";
                } else {
                    alert("Failed to delete chat.");
                }
            });
        }
    }

</script>

<?php require_once '../includes/footer.php'; ?>