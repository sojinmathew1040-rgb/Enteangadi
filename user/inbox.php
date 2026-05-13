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



<?php require_once '../includes/footer.php'; ?>