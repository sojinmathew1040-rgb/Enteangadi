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
    SELECT p.title, p.price, p.status, p.updated_at, p.created_at, p.category_id,
    c.parent_id as parent_category_id,
    (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as thumbnail 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ?
");
$stmt2->execute([$product_id]);
$product = $stmt2->fetch();

if (!$other_user || !$product) {
    header("Location: inbox.php");
    exit;
}

$category_id = $product ? (int)$product['category_id'] : 0;
$parent_id = $product ? (int)$product['parent_category_id'] : 0;

$quick_replies = [
    "Is this still available?",
    "I am interested. What is your final price?",
    "Where is your location to inspect this?"
];

// Context/Category specific quick replies suggestions
if ($category_id == 25 || $parent_id == 25 || $category_id == 27 || $parent_id == 27 || $category_id == 61 || $parent_id == 61) {
    // Vehicles
    $quick_replies = [
        "Is the insurance still active?",
        "Are the registration documents (RC) clear?",
        "How many kilometers has it run?",
        "Can I come for a test drive?"
    ];
} elseif ($category_id == 32 || $parent_id == 32) {
    // Properties
    $quick_replies = [
        "What is the security deposit amount?",
        "Is there water and electricity backup?",
        "Are bachelor tenants allowed?",
        "When can I visit the property?"
    ];
} elseif ($category_id == 39 || $parent_id == 39) {
    // Jobs
    $quick_replies = [
        "What are the working hours?",
        "Is this a full-time or part-time position?",
        "Where is the office located?",
        "What are the salary and benefits?"
    ];
} elseif ($category_id == 11 || $parent_id == 11 || $category_id == 52 || $parent_id == 52) {
    // Mobiles, Electronics
    $quick_replies = [
        "Is the warranty still valid?",
        "Does it include the original bill and box?",
        "Are there any scratches or functional defects?",
        "What is the battery health percentage?"
    ];
} elseif ($category_id == 80 || $parent_id == 80) {
    // Pets
    $quick_replies = [
        "Are the vaccinations up to date?",
        "What is the age and breed?",
        "Is the price negotiable?",
        "Can you send more photos/videos?"
    ];
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

<!-- PeerJS WebRTC Library -->
<script src="https://unpkg.com/peerjs@1.4.7/dist/peerjs.min.js"></script>

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
                    <a href="view_profile.php?id=<?= $other_user_id ?>" class="user-info-chat" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 12px;">
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
                    </a>
                </div>
                <div class="header-actions" style="position: relative; display: flex; align-items: center; gap: 8px;">
                    <button onclick="startVoiceCall()" class="btn-chat-action" id="chatVoiceCallBtn" title="Voice Call" style="background: transparent; border: none; font-size: 18px; color: var(--primary-green); cursor: pointer; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s;">
                        <i class="fa fa-phone"></i>
                    </button>
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
                    <!-- Quick Replies Container -->
                    <div class="quick-replies-container" style="display: flex; gap: 8px; overflow-x: auto; padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid var(--border-color); -webkit-overflow-scrolling: touch; scrollbar-width: none; width: 100%; box-sizing: border-box;">
                        <?php foreach ($quick_replies as $reply): ?>
                            <button onclick="sendQuickReply(<?= htmlspecialchars(json_encode($reply)) ?>)" class="quick-reply-chip" style="flex-shrink: 0; background: var(--white); border: 1px solid var(--border-color); padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; color: var(--text-dark); cursor: pointer; transition: all 0.2s; box-shadow: var(--shadow-sm); outline: none;">
                                <?= htmlspecialchars($reply) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <style>
                        .quick-replies-container::-webkit-scrollbar {
                            display: none;
                        }
                        .quick-reply-chip:hover {
                            border-color: var(--primary-green);
                            color: var(--primary-green);
                            background: #f0fdf4;
                            transform: translateY(-1px);
                        }
                    </style>
                    <script>
                        function sendQuickReply(text) {
                            const input = document.getElementById('message-input');
                            if (input) {
                                input.value = text;
                                sendMessage();
                            }
                        }
                    </script>

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

<!-- Message Actions Modal (Delete for me / Delete for everyone) -->
<div id="messageActionsModal" class="modal-overlay" style="display: none; z-index: 999999; justify-content: center; align-items: center; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
    <div class="modal-content" style="max-width: 320px; padding: 24px; border-radius: 20px; background: var(--white, #fff); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); width: 90%; box-sizing: border-box; text-align: center; border: 1px solid var(--border-color, #e2e8f0); animation: popIn 0.2s ease-out;">
        <h3 style="margin: 0 0 16px; font-size: 18px; font-weight: 800; color: var(--text-dark, #1f2937);">Message Options</h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <button id="deleteForMeBtn" class="btn-secondary" style="width: 100%; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; border: 1px solid var(--border-color, #cbd5e1); background: var(--white, #fff); color: #ef4444; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-trash-alt"></i> Delete for me
            </button>
            <button id="deleteForEveryoneBtn" class="btn-primary" style="width: 100%; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; background: #ef4444; border: 1px solid #ef4444; color: #fff; cursor: pointer; transition: all 0.2s; display: none; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-trash"></i> Delete for everyone
            </button>
            <button onclick="closeMessageActionsModal()" class="btn-secondary" style="width: 100%; padding: 12px; border-radius: 12px; font-weight: 700; font-size: 14px; border: 1px solid var(--border-color, #cbd5e1); background: var(--white, #fff); color: var(--text-dark, #1e293b); cursor: pointer; transition: all 0.2s;">
                Cancel
            </button>
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

<!-- Voice Call Modal Overlay -->
<div id="voiceCallModal" class="modal-overlay" style="display: none; z-index: 999999; justify-content: center; align-items: center; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); position: fixed; top: 0; left: 0; right: 0; bottom: 0; font-family: 'Inter', sans-serif;">
    <div class="modal-content" style="max-width: 320px; padding: 40px 24px; border-radius: 28px; background: var(--white, #fff); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 90%; box-sizing: border-box; text-align: center; border: 1px solid var(--border-color, #e2e8f0); display: flex; flex-direction: column; align-items: center; gap: 20px; animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);">
        
        <!-- Pulsing Avatar wrapper -->
        <div style="position: relative; width: 100px; height: 100px; margin-bottom: 8px;">
            <div id="callPulseRing" style="position: absolute; width: 100%; height: 100%; background: rgba(27, 94, 32, 0.2); border-radius: 50%; animation: voicePulse 2s infinite;"></div>
            <div id="callPulseRing2" style="position: absolute; width: 100%; height: 100%; background: rgba(27, 94, 32, 0.15); border-radius: 50%; animation: voicePulse 2s infinite 0.6s;"></div>
            <div style="position: absolute; width: 80px; height: 80px; top: 10px; left: 10px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
                <?php if (!empty($other_user['profile_picture'])): ?>
                    <img src="<?= $base_url ?>/<?= htmlspecialchars($other_user['profile_picture']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 28px; font-weight: 800; color: var(--primary-green);"><?= strtoupper(substr($other_user['username'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 4px;">
            <h3 id="callUserTitle" style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-dark);"><?= htmlspecialchars($other_user['username']) ?></h3>
            <span id="callStatusLabel" style="font-size: 13px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Ringing...</span>
            <span id="callDuration" style="font-size: 14px; color: var(--primary-green); font-weight: 700; display: none;">00:00</span>
        </div>

        <!-- Audio element to play remote stream -->
        <audio id="remoteAudioStream" autoplay></audio>

        <!-- Call Actions Container -->
        <div style="display: flex; gap: 16px; width: 100%; justify-content: center; margin-top: 10px;">
            <!-- Incoming call controls -->
            <button id="declineCallBtn" onclick="declineIncomingCall()" class="btn-secondary" style="display: none; flex: 1; padding: 14px; border-radius: 16px; font-weight: 700; font-size: 14px; border: 1px solid #fecaca; background: #fee2e2; color: #ef4444; cursor: pointer; transition: all 0.2s; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-phone-slash"></i> Decline
            </button>
            <button id="acceptCallBtn" onclick="acceptIncomingCall()" class="btn-primary" style="display: none; flex: 1.5; padding: 14px; border-radius: 16px; font-weight: 700; font-size: 14px; background: var(--primary-green); border: 1px solid var(--primary-green); color: #fff; cursor: pointer; transition: all 0.2s; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-phone"></i> Accept
            </button>

            <!-- Ongoing call controls -->
            <button id="muteCallBtn" onclick="toggleMuteCall()" class="btn-secondary" style="display: none; flex: 1; padding: 14px; border-radius: 16px; font-weight: 700; font-size: 14px; border: 1px solid var(--border-color); background: #f1f5f9; color: #475569; cursor: pointer; transition: all 0.2s; align-items: center; justify-content: center;">
                <i class="fa fa-microphone"></i>
            </button>
            <button id="endCallBtn" onclick="endActiveCall()" class="btn-primary" style="display: none; flex: 1.5; padding: 14px; border-radius: 16px; font-weight: 700; font-size: 14px; background: #ef4444; border: 1px solid #ef4444; color: #fff; cursor: pointer; transition: all 0.2s; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-phone-slash"></i> Hang Up
            </button>
        </div>
    </div>
</div>

<script>
    let localAudioStream = null;
    let activeCallInstance = null;
    let callTimerInterval = null;
    let isMuted = false;
    let peerConnection = null;

    // Call Signal Control States
    window.lastIncomingCallId = null;
    window.callDeclinedId = null;
    window.callAcceptedId = null;
    window.activeIncomingCallId = null;
    window.callerActiveCallId = null;
    window.callConnectingStarted = false;
    window.callWatchdogTimer = null;

    function sendSignalMessage(text) {
        if (typeof otherId === 'undefined' || typeof productId === 'undefined') return Promise.resolve();
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('receiver_id', otherId);
        formData.append('product_id', productId);
        formData.append('message', text);
        return fetch('api_chat.php', { method: 'POST', body: formData })
            .then(res => res.json());
    }

    // JS Signal Handler Hooks triggered by chat.js renderer
    window.handleCallRequestSignal = function(callId, isMe, createdAt) {
        const msgTime = new Date(createdAt).getTime();
        const isRecent = (Date.now() - msgTime) < 20000;
        if (!isRecent) return;
        
        if (!isMe && !activeCallInstance && window.lastIncomingCallId !== callId && window.callDeclinedId !== callId && window.callAcceptedId !== callId) {
            window.lastIncomingCallId = callId;
            window.activeIncomingCallId = callId;
            
            // Show Incoming Call Modal
            const modal = document.getElementById('voiceCallModal');
            const statusLabel = document.getElementById('callStatusLabel');
            const userTitle = document.getElementById('callUserTitle');
            
            userTitle.innerText = "Incoming Call";
            statusLabel.innerText = "Calling...";
            modal.style.display = 'flex';

            // Show accept/decline buttons
            document.getElementById('declineCallBtn').style.display = 'flex';
            document.getElementById('acceptCallBtn').style.display = 'flex';
            document.getElementById('muteCallBtn').style.display = 'none';
            document.getElementById('endCallBtn').style.display = 'none';
        }
    };

    window.handleCallAcceptSignal = function(callId, isMe, createdAt) {
        const msgTime = new Date(createdAt).getTime();
        const isRecent = (Date.now() - msgTime) < 20000;
        if (!isRecent) return;

        if (!isMe && window.callerActiveCallId === callId && !window.callConnectingStarted) {
            window.callConnectingStarted = true;
            if (window.callWatchdogTimer) {
                clearTimeout(window.callWatchdogTimer);
                window.callWatchdogTimer = null;
            }
            console.log("Call accepted by other party. Initiating WebRTC streaming...");
            establishPeerJSCall();
        }
    };

    window.handleCallDeclineSignal = function(callId, isMe, createdAt) {
        const msgTime = new Date(createdAt).getTime();
        const isRecent = (Date.now() - msgTime) < 20000;
        if (!isRecent) return;

        if (!isMe && window.callerActiveCallId === callId) {
            if (window.callWatchdogTimer) {
                clearTimeout(window.callWatchdogTimer);
                window.callWatchdogTimer = null;
            }
            showCallStatusText('Call Declined', 'Failed');
            setTimeout(cleanupCallState, 2000);
        }
    };

    // Initialize PeerJS
    function initVoiceCallPeer() {
        const peerId = `enteangadi-user-${myId}`;
        peerConnection = new Peer(peerId, {
            debug: 1
        });

        peerConnection.on('open', (id) => {
            console.log('PeerJS server connection established with ID:', id);
        });

        peerConnection.on('error', (err) => {
            console.error('PeerJS error:', err);
            if (err.type === 'peer-unavailable') {
                showCallStatusText('User is offline', 'Failed to connect');
                setTimeout(cleanupCallState, 3000);
            } else {
                cleanupCallState();
            }
        });

        // Listen for incoming WebRTC call triggers
        peerConnection.on('call', (call) => {
            console.log('Incoming WebRTC call received...');
            activeCallInstance = call;
            
            // Automatically answer if we have already clicked accept
            if (window.callAcceptedId === window.activeIncomingCallId) {
                if (localAudioStream) {
                    call.answer(localAudioStream);
                    setupCallStreamHandlers(call);
                } else {
                    navigator.mediaDevices.getUserMedia({ audio: true }).then(stream => {
                        localAudioStream = stream;
                        call.answer(localAudioStream);
                        setupCallStreamHandlers(call);
                    }).catch(err => {
                        console.error("Delayed mic capture failed:", err);
                        call.close();
                        cleanupCallState();
                    });
                }
            } else {
                // Showing visual incoming call fallback overlay
                const modal = document.getElementById('voiceCallModal');
                const statusLabel = document.getElementById('callStatusLabel');
                const userTitle = document.getElementById('callUserTitle');
                
                userTitle.innerText = "Incoming Voice Call";
                statusLabel.innerText = "Calling...";
                modal.style.display = 'flex';

                document.getElementById('declineCallBtn').style.display = 'flex';
                document.getElementById('acceptCallBtn').style.display = 'flex';
                document.getElementById('muteCallBtn').style.display = 'none';
                document.getElementById('endCallBtn').style.display = 'none';
            }
        });
    }

    function showCallStatusText(status, label) {
        const statusLabel = document.getElementById('callStatusLabel');
        if (statusLabel) {
            statusLabel.innerText = status;
        }
    }

    async function startVoiceCall() {
        if (isBlocked) {
            alert("Cannot call. Chat is blocked.");
            return;
        }

        const callId = `enteangadi-call-${myId}-${otherId}-${Date.now()}`;
        window.callerActiveCallId = callId;
        window.callConnectingStarted = false;

        const modal = document.getElementById('voiceCallModal');
        const statusLabel = document.getElementById('callStatusLabel');
        const userTitle = document.getElementById('callUserTitle');

        userTitle.innerText = "<?= htmlspecialchars($other_user['username']) ?>";
        statusLabel.innerText = "Sending request...";
        modal.style.display = 'flex';

        // Outgoing call only shows End Call while dialing
        document.getElementById('declineCallBtn').style.display = 'none';
        document.getElementById('acceptCallBtn').style.display = 'none';
        document.getElementById('muteCallBtn').style.display = 'none';
        document.getElementById('endCallBtn').style.display = 'flex';

        try {
            await sendSignalMessage(`[CALL_REQUEST]:${callId}`);
            statusLabel.innerText = "Ringing...";

            // Watchdog: cancel call after 25 seconds if unanswered
            window.callWatchdogTimer = setTimeout(() => {
                if (!window.callConnectingStarted) {
                    statusLabel.innerText = "No Answer";
                    setTimeout(cleanupCallState, 2000);
                }
            }, 25000);

        } catch (err) {
            console.error("Outgoing call signal failed:", err);
            statusLabel.innerText = "Call Request Failed";
            setTimeout(cleanupCallState, 2000);
        }
    }

    async function establishPeerJSCall() {
        const statusLabel = document.getElementById('callStatusLabel');
        try {
            localAudioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const receiverPeerId = `enteangadi-user-${otherId}`;
            console.log("PeerJS dialing connection to:", receiverPeerId);
            
            const call = peerConnection.call(receiverPeerId, localAudioStream);
            activeCallInstance = call;
            
            document.getElementById('muteCallBtn').style.display = 'flex';
            document.getElementById('endCallBtn').style.display = 'flex';
            
            setupCallStreamHandlers(call);
        } catch (err) {
            console.error("Microphone capture failed on outgoing call connection:", err);
            statusLabel.innerText = "Mic Permission Denied";
            sendSignalMessage(`[CALL_DECLINE]:${window.callerActiveCallId}`);
            setTimeout(cleanupCallState, 2500);
        }
    }

    async function acceptIncomingCall() {
        const callId = window.activeIncomingCallId;
        window.callAcceptedId = callId;
        
        await sendSignalMessage(`[CALL_ACCEPT]:${callId}`);
        
        const statusLabel = document.getElementById('callStatusLabel');
        statusLabel.innerText = "Connecting...";
        
        document.getElementById('declineCallBtn').style.display = 'none';
        document.getElementById('acceptCallBtn').style.display = 'none';
        
        try {
            localAudioStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            document.getElementById('muteCallBtn').style.display = 'flex';
            document.getElementById('endCallBtn').style.display = 'flex';
            
            // Wait for WebRTC incoming streams...
        } catch (err) {
            console.error("Microphone capture failed on accepting call:", err);
            statusLabel.innerText = "Mic Permission Denied";
            await sendSignalMessage(`[CALL_DECLINE]:${callId}`);
            setTimeout(cleanupCallState, 2500);
        }
    }

    async function declineIncomingCall() {
        const callId = window.activeIncomingCallId;
        window.callDeclinedId = callId;
        await sendSignalMessage(`[CALL_DECLINE]:${callId}`);
        cleanupCallState();
    }

    function setupCallStreamHandlers(call) {
        const statusLabel = document.getElementById('callStatusLabel');
        const durationLabel = document.getElementById('callDuration');

        call.on('stream', (remoteStream) => {
            console.log("Voice stream connected. Playing audio...");
            const remoteAudio = document.getElementById('remoteAudioStream');
            if (remoteAudio) {
                remoteAudio.srcObject = remoteStream;
            }
            
            statusLabel.innerText = "Connected";
            durationLabel.style.display = 'block';
            startCallTimer();
        });

        call.on('close', () => {
            console.log("Call closed by remote peer.");
            cleanupCallState();
        });

        call.on('error', (err) => {
            console.error("Call stream error:", err);
            cleanupCallState();
        });
    }

    function toggleMuteCall() {
        if (localAudioStream) {
            isMuted = !isMuted;
            localAudioStream.getAudioTracks().forEach(track => {
                track.enabled = !isMuted;
            });
            const muteBtn = document.getElementById('muteCallBtn');
            if (muteBtn) {
                muteBtn.innerHTML = isMuted 
                    ? '<i class="fa fa-microphone-slash" style="color: #ef4444;"></i>' 
                    : '<i class="fa fa-microphone"></i>';
            }
        }
    }

    function startCallTimer() {
        let seconds = 0;
        const durationLabel = document.getElementById('callDuration');
        callTimerInterval = setInterval(() => {
            seconds++;
            const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
            const secs = String(seconds % 60).padStart(2, '0');
            durationLabel.innerText = `${mins}:${secs}`;
        }, 1000);
    }

    function endActiveCall() {
        if (activeCallInstance) {
            activeCallInstance.close();
        }
        cleanupCallState();
    }

    function cleanupCallState() {
        if (window.callWatchdogTimer) {
            clearTimeout(window.callWatchdogTimer);
            window.callWatchdogTimer = null;
        }

        // Clear timer
        if (callTimerInterval) {
            clearInterval(callTimerInterval);
            callTimerInterval = null;
        }
        const durationLabel = document.getElementById('callDuration');
        if (durationLabel) {
            durationLabel.style.display = 'none';
            durationLabel.innerText = '00:00';
        }

        // Stop microphone tracks
        if (localAudioStream) {
            localAudioStream.getTracks().forEach(track => track.stop());
            localAudioStream = null;
        }

        // Reset mute status
        isMuted = false;
        const muteBtn = document.getElementById('muteCallBtn');
        if (muteBtn) {
            muteBtn.innerHTML = '<i class="fa fa-microphone"></i>';
        }

        // Close modal
        const modal = document.getElementById('voiceCallModal');
        if (modal) {
            modal.style.display = 'none';
        }

        activeCallInstance = null;
        window.callConnectingStarted = false;
        console.log("WebRTC voice call state cleaned up.");
    }

    // Initialize Peer connection on page load
    window.addEventListener('load', () => {
        initVoiceCallPeer();
    });
</script>

<script src="../assets/js/chat.js?v=1.3"></script>

<?php require_once '../includes/footer.php'; ?>