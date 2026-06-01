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

<script>
    document.body.classList.add('chat-page-body');
</script>

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
                    <div id="recording-status" class="recording-status-premium" style="display: none;">
                        <span class="recording-dot animate-pulse"></span>
                        <span class="recording-timer">00:00</span>
                        <span class="recording-label">Recording Voice...</span>
                        <button type="button" id="cancelRecBtn" class="btn-cancel-rec" title="Discard">
                            <i class="fa fa-trash-alt"></i>
                        </button>
                    </div>
                    
                    <input type="text" id="message-input" placeholder="Type a message..." autocomplete="off">
                    
                    <button type="button" id="micBtn" class="btn-mic-chat" title="Record Voice Note">
                        <i class="fa fa-microphone"></i>
                    </button>
                    
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
</script>
<script src="../assets/js/chat.js"></script>



<?php require_once '../includes/footer.php'; ?>