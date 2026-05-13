<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$my_id = $_SESSION['user_id'];

// Fetch all distinct conversations with product thumbnails
try {
    $query = "
    SELECT 
        t.product_id,
        t.other_user_id,
        u.username as other_username,
        u.profile_picture as other_profile_picture,
        p.title as product_title,
        p.price as product_price,
        (SELECT image_path FROM product_images WHERE product_id = t.product_id ORDER BY id ASC LIMIT 1) as product_thumbnail,
        MAX(t.created_at) as last_message_time,
        SUM(t.unread_flag) as unread_count
    FROM (
        SELECT product_id, receiver_id as other_user_id, created_at, 0 as unread_flag
        FROM messages 
        WHERE sender_id = :my_id
        UNION ALL
        SELECT product_id, sender_id as other_user_id, created_at, (CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_flag
        FROM messages 
        WHERE receiver_id = :my_id
    ) t
    JOIN users u ON u.id = t.other_user_id
    JOIN products p ON p.id = t.product_id
    GROUP BY t.product_id, t.other_user_id, u.username, u.profile_picture, p.title, p.price
    ORDER BY last_message_time DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['my_id' => $my_id]);
    $conversations = $stmt->fetchAll();

    foreach ($conversations as &$conv) {
        $msg_stmt = $pdo->prepare("
            SELECT message_text, sender_id 
            FROM messages 
            WHERE product_id = ? 
            AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
            ORDER BY created_at DESC LIMIT 1
        ");
        $msg_stmt->execute([$conv['product_id'], $my_id, $conv['other_user_id'], $conv['other_user_id'], $my_id]);
        $last_msg = $msg_stmt->fetch();
        $conv['last_message'] = $last_msg ? $last_msg['message_text'] : '';
        $conv['last_sender_id'] = $last_msg ? $last_msg['sender_id'] : 0;
    }
} catch (PDOException $e) {
    $conversations = [];
    $error = "Failed to load messages.";
}

require_once '../includes/header.php';
?>

<div class="inbox-wrapper-premium">
    <div class="container inbox-container">
        <div class="inbox-header">
            <div class="header-left">
                <h1>Messages</h1>
                <p>Chat with buyers and sellers</p>
            </div>
            <div class="unread-badge-total">
                <?php
                $total_unread = array_sum(array_column($conversations, 'unread_count'));
                if ($total_unread > 0): ?>
                    <span class="pulse-badge"><?= $total_unread ?> Unread</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($conversations)): ?>
            <div class="empty-inbox-state">
                <div class="empty-illustration">
                    <i class="fa fa-comments"></i>
                </div>
                <h2>No messages yet</h2>
                <p>When you start a conversation about a product, it will appear here.</p>
                <a href="../index.php" class="btn-browse-premium">Browse Marketplace</a>
            </div>
        <?php else: ?>
            <div class="conversations-list">
                <?php foreach ($conversations as $conv): ?>
                    <a href="chat.php?user_id=<?= $conv['other_user_id'] ?>&product_id=<?= $conv['product_id'] ?>"
                        class="conv-row <?= $conv['unread_count'] > 0 ? 'unread' : '' ?>">

                        <div class="conv-avatar-group">
                            <?php if (!empty($conv['other_profile_picture'])): ?>
                                <img src="<?= $base_url ?>/<?= htmlspecialchars($conv['other_profile_picture']) ?>"
                                    class="user-avatar">
                            <?php else: ?>
                                <div class="user-avatar-initials">
                                    <?= strtoupper(substr($conv['other_username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                        </div>

                        <div class="conv-main-info">
                            <div class="conv-top-line">
                                <span class="username"><?= htmlspecialchars($conv['other_username']) ?></span>
                                <span class="time"><?= formatInboxTime($conv['last_message_time']) ?></span>
                            </div>
                            <div class="product-tag">
                                <i class="fa fa-shopping-bag"></i>
                                <?= htmlspecialchars($conv['product_title']) ?>
                            </div>
                            <div class="last-message-snippet">
                                <?php if ($conv['last_sender_id'] == $my_id): ?>
                                    <span class="you-prefix">You: </span>
                                <?php endif; ?>
                                <?= htmlspecialchars($conv['last_message']) ?>
                            </div>
                        </div>

                        <div class="conv-product-thumb">
                            <?php if (!empty($conv['product_thumbnail'])): ?>
                                <img src="<?= $base_url ?>/<?= htmlspecialchars($conv['product_thumbnail']) ?>">
                            <?php else: ?>
                                <div class="no-thumb"><i class="fa fa-image"></i></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
function formatInboxTime($timestamp)
{
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60)
        return "Just now";
    if ($diff < 3600)
        return floor($diff / 60) . "m ago";
    if ($diff < 86400)
        return floor($diff / 3600) . "h ago";
    if ($diff < 172800)
        return "Yesterday";
    return date('M d', $time);
}
?>

<style>
    .inbox-wrapper-premium {
        padding: 40px 0;
        background: #f8fafc;
        min-height: 100vh;
    }

    .inbox-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .inbox-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 30px;
    }

    .inbox-header h1 {
        font-size: 32px;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
    }

    .inbox-header p {
        color: #64748b;
        margin: 5px 0 0 0;
        font-weight: 600;
    }

    .pulse-badge {
        background: #fef2f2;
        color: #ef4444;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 800;
        font-size: 14px;
        border: 1px solid #fee2e2;
        animation: subtlePulse 2s infinite;
    }

    @keyframes subtlePulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }

        100% {
            transform: scale(1);
        }
    }

    .conversations-list {
        background: white;
        border-radius: 30px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.03);
        overflow: hidden;
        border: 1px solid #f1f5f9;
    }

    .conv-row {
        display: flex;
        align-items: center;
        padding: 20px 24px;
        text-decoration: none;
        color: inherit;
        gap: 20px;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.3s;
        position: relative;
    }

    .conv-row:last-child {
        border-bottom: none;
    }

    .conv-row:hover {
        background: #f8fafc;
    }

    .conv-row.unread {
        background: #f0fdf4;
    }

    .conv-row.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--primary-green);
    }

    .user-avatar,
    .user-avatar-initials {
        width: 60px;
        height: 60px;
        border-radius: 20px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .user-avatar-initials {
        background: var(--primary-green);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 800;
    }

    .conv-avatar-group {
        position: relative;
    }

    .unread-dot {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 14px;
        height: 14px;
        background: #ef4444;
        border: 3px solid white;
        border-radius: 50%;
    }

    .conv-main-info {
        flex: 1;
        min-width: 0;
    }

    .conv-top-line {
        display: flex;
        justify-content: space-between;
        margin-bottom: 4px;
    }

    .username {
        font-weight: 800;
        font-size: 16px;
        color: var(--text-dark);
    }

    .time {
        font-size: 12px;
        color: #94a3b8;
        font-weight: 600;
    }

    .product-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        font-weight: 700;
        color: var(--primary-green);
        background: #f0fdf4;
        padding: 4px 10px;
        border-radius: 8px;
        margin-bottom: 8px;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .last-message-snippet {
        font-size: 14px;
        color: #64748b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 500;
    }

    .unread .last-message-snippet {
        color: var(--text-dark);
        font-weight: 700;
    }

    .you-prefix {
        color: #94a3b8;
        font-weight: 700;
    }

    .conv-product-thumb {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        overflow: hidden;
        flex-shrink: 0;
        background: #f1f5f9;
        border: 1px solid #f1f5f9;
    }

    .conv-product-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .no-thumb {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #cbd5e1;
    }

    .empty-inbox-state {
        text-align: center;
        padding: 100px 40px;
        background: white;
        border-radius: 40px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.03);
    }

    .empty-illustration {
        width: 100px;
        height: 100px;
        background: #f0fdf4;
        color: var(--primary-green);
        border-radius: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        margin: 0 auto 24px;
    }

    .empty-inbox-state h2 {
        font-size: 24px;
        font-weight: 800;
        margin-bottom: 12px;
    }

    .empty-inbox-state p {
        color: #64748b;
        max-width: 300px;
        margin: 0 auto 30px;
        font-weight: 500;
    }

    .btn-browse-premium {
        display: inline-block;
        padding: 16px 32px;
        background: var(--primary-green);
        color: white;
        text-decoration: none;
        border-radius: 18px;
        font-weight: 800;
        box-shadow: 0 10px 20px rgba(34, 197, 94, 0.2);
        transition: 0.3s;
    }

    .btn-browse-premium:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(34, 197, 94, 0.3);
    }

    @media (max-width: 600px) {
        .conv-product-thumb {
            display: none;
        }

        .inbox-wrapper-premium {
            padding: 20px 0;
        }

        .conv-row {
            padding: 16px;
        }

        .user-avatar,
        .user-avatar-initials {
            width: 50px;
            height: 50px;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>