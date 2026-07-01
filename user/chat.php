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
    SELECT p.title, p.price, p.status, p.updated_at, p.created_at, 
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

$days_left = 15;
$is_chat_expired = false;
if ($product['status'] === 'sold' || $product['status'] === 'deleted') {
    $is_chat_expired = true;
    $updated_time = strtotime($product['updated_at'] ?? $product['created_at']);
    $diff_seconds = time() - $updated_time;
    $diff_days = (int) floor($diff_seconds / 86400);
    $days_left = 15 - $diff_days;
    if ($days_left < 0) {
        $days_left = 0;
    }
}

// Check block status
$stmt_block = $pdo->prepare("SELECT blocker_id FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
$stmt_block->execute([$my_id, $other_user_id, $other_user_id, $my_id]);
$block_record = $stmt_block->fetch();

$is_blocked = !empty($block_record);
$blocked_by_me = $is_blocked && ($block_record['blocker_id'] == $my_id);

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
                <div class="header-actions" style="position: relative;">
                    <button onclick="toggleChatMenu(event)" class="btn-chat-action" id="chatMenuBtn" title="Chat Actions" style="background: transparent; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                        <i class="fa fa-ellipsis-v"></i>
                    </button>
                    <div id="chatMenuDropdown" class="chat-menu-dropdown">
                        <a href="javascript:void(0)" onclick="clearChatMessages()"><i class="fa fa-broom"></i> Clear Chat</a>
                        <a href="javascript:void(0)" onclick="openReportModal()"><i class="fa fa-flag"></i> Report</a>
                        <?php if ($is_blocked): ?>
                            <?php if ($blocked_by_me): ?>
                                <a href="javascript:void(0)" onclick="toggleBlockUser()" id="blockMenuAction"><i class="fa fa-ban"></i> Unblock User</a>
                            <?php else: ?>
                                <a href="javascript:void(0)" id="blockMenuAction" class="disabled" style="opacity: 0.5; cursor: not-allowed;"><i class="fa fa-ban"></i> Blocked</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="javascript:void(0)" onclick="toggleBlockUser()" id="blockMenuAction" class="menu-danger"><i class="fa fa-ban"></i> Block User</a>
                        <?php endif; ?>
                    </div>
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
                <?php if ($is_chat_expired): ?>
                    <div class="chat-disabled-banner" style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 16px; border-radius: 16px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; font-weight: 700; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.03); text-align: center;">
                        <div>
                            <i class="fa fa-exclamation-triangle" style="font-size: 18px; margin-right: 6px;"></i>
                            <span>This product has been marked as <?= htmlspecialchars($product['status']) ?>. Chat is disabled.</span>
                        </div>
                        <span style="font-size: 13px; font-weight: 500; color: #b91c1c;">This chat and all shared media will be permanently deleted in <?= $days_left ?> days.</span>
                    </div>
                <?php elseif ($is_blocked): ?>
                    <div class="chat-disabled-banner" style="background: #f1f5f9; border: 1px solid #e2e8f0; color: #64748b; padding: 16px; border-radius: 16px; display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 700; width: 100%; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                        <i class="fa fa-ban" style="font-size: 18px;"></i>
                        <span><?= $blocked_by_me ? 'You have blocked this user. Unblock to send messages.' : 'You cannot send messages to this user.' ?></span>
                    </div>
                <?php else: ?>
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
                        
                        <button type="button" id="imgBtn" class="btn-img-chat" title="Send Image" onclick="document.getElementById('chat-image-input').click()" style="background: transparent; border: none; font-size: 16px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; transition: all 0.3s;">
                            <i class="fa fa-camera"></i>
                        </button>
                        <input type="file" id="chat-image-input" accept="image/*" multiple style="display: none;" onchange="(async () => { if (this.files.length) { for (let i = 0; i < this.files.length; i++) { await uploadChatImage(this.files[i]); } this.value = ''; } })()">
                        
                        <button onclick="sendMessage()" id="sendBtn" class="btn-send-chat">
                            <i class="fa fa-paper-plane"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Report User/Ad Modal -->
<div id="reportChatModal" class="modal-overlay" style="display: none; z-index: 99999; justify-content: center; align-items: center; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
    <div class="modal-content" style="max-width: 400px; padding: 24px; border-radius: 16px; background: var(--white, #fff); box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 90%; box-sizing: border-box;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: var(--text-dark);">Report User / Ad</h3>
            <button onclick="closeReportModal()" class="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div class="modal-body">
            <form id="reportChatForm" onsubmit="submitChatReport(event)">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 14px; font-weight: 600; color: var(--text-dark);">Reason for reporting</label>
                    <select id="reportReasonSelect" class="form-control" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 14px; margin-bottom: 12px; box-sizing: border-box; background: var(--background, #fff); color: var(--text-dark);">
                        <option value="spam">Spam / Scam</option>
                        <option value="harassment">Harassment / Abusive behavior</option>
                        <option value="fraud">Fraudulent listing / fake items</option>
                        <option value="inappropriate">Inappropriate language / content</option>
                        <option value="other">Other reason</option>
                    </select>
                    <textarea id="reportReasonText" class="form-control" placeholder="Provide more details..." style="width: 100%; min-height: 80px; padding: 12px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 14px; box-sizing: border-box; display: none; background: var(--background, #fff); color: var(--text-dark); resize: vertical;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="closeReportModal()" class="btn-secondary" style="padding: 10px 16px; border-radius: 8px;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding: 10px 16px; border-radius: 8px; background: #ef4444; border-color: #ef4444; color: #fff;">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Custom Themed Alert & Confirm Dialog Modal -->
<div id="customConfirmModal" class="modal-overlay" style="display: none; z-index: 999999; justify-content: center; align-items: center; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
    <div class="modal-content" style="max-width: 400px; padding: 28px; border-radius: 20px; background: var(--white, #fff); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); width: 90%; box-sizing: border-box; text-align: center; border: 1px solid var(--border-color, #e2e8f0); animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
        <div class="dialog-icon-wrapper" id="customConfirmIconWrapper" style="width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; background: rgba(27, 94, 32, 0.1); color: var(--primary-green, #1B5E20); font-size: 24px;">
            <i class="fa fa-info-circle" id="customConfirmIcon"></i>
        </div>
        <h3 id="customConfirmTitle" style="margin: 0 0 10px; font-size: 20px; font-weight: 800; color: var(--text-dark, #1f2937);">Confirm Action</h3>
        <p id="customConfirmMessage" style="margin: 0 0 28px; font-size: 14px; color: var(--text-muted, #4b5563); line-height: 1.6; font-weight: 500;">Are you sure you want to proceed?</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button id="customConfirmCancelBtn" class="btn-secondary" style="flex: 1; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; border: 1px solid var(--border-color, #cbd5e1); background: var(--white, #fff); color: var(--text-dark, #1e293b); cursor: pointer; transition: all 0.2s;">Cancel</button>
            <button id="customConfirmProceedBtn" class="btn-primary" style="flex: 1.5; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; background: var(--primary-green, #1B5E20); border: 1px solid var(--primary-green, #1B5E20); color: #fff; cursor: pointer; transition: all 0.2s;">Proceed</button>
        </div>
    </div>
</div>

<style>
@keyframes popIn {
    0% { transform: scale(0.9); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<script>
    const myId = <?= $my_id ?>;
    const otherId = <?= $other_user_id ?>;
    const productId = <?= $product_id ?>;
    const isBlocked = <?= $is_blocked ? 'true' : 'false' ?>;
    const blockedByMe = <?= $blocked_by_me ? 'true' : 'false' ?>;
</script>
<script src="../assets/js/chat.js?v=1.2"></script>



<?php require_once '../includes/footer.php'; ?>