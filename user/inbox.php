<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$my_id = $_SESSION['user_id'];

// Fetch all distinct conversations
try {
    $query = "
    SELECT 
        m.product_id,
        CASE 
            WHEN m.sender_id = :my_id THEN m.receiver_id 
            ELSE m.sender_id 
        END AS other_user_id,
        u.username as other_username,
        u.profile_picture as other_profile_picture,
        p.title as product_title,
        p.price as product_price,
        MAX(m.created_at) as last_message_time,
        SUM(CASE WHEN m.receiver_id = :my_id AND m.is_read = 0 THEN 1 ELSE 0 END) as unread_count
    FROM messages m
    JOIN users u ON u.id = (CASE WHEN m.sender_id = :my_id THEN m.receiver_id ELSE m.sender_id END)
    JOIN products p ON p.id = m.product_id
    WHERE m.sender_id = :my_id OR m.receiver_id = :my_id
    GROUP BY m.product_id, other_user_id, u.username, u.profile_picture, p.title, p.price
    ORDER BY last_message_time DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute(['my_id' => $my_id]);
    $conversations = $stmt->fetchAll();

    // Fetch the exact last message for each conversation to avoid complex subqueries in MySQL 5.7/8.0 group by issues
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

<div class="container">
    <div
        style="max-width: 800px; margin: 0 auto; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); padding: 24px;">
        <h2 style="margin-bottom: 24px; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
            <i class="fa fa-inbox" style="color: var(--primary-green);"></i> Inbox
        </h2>

        <?php if (!empty($error)): ?>
            <p style="color: var(--danger);"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (empty($conversations)): ?>
            <div style="text-align: center; padding: 48px 0; color: var(--text-muted);">
                <i class="fa fa-comments" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                <p>No messages yet.</p>
                <p style="font-size: 14px; margin-top: 8px;">Start chatting with sellers to see your conversations here.</p>
                <a href="../index.php" class="btn-primary" style="display: inline-block; margin-top: 16px;">Browse Ads</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($conversations as $conv): ?>
                    <a href="chat.php?user_id=<?= $conv['other_user_id'] ?>&product_id=<?= $conv['product_id'] ?>"
                        style="text-decoration: none; display: block; border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; transition: all 0.2s; position: relative; background: <?= $conv['unread_count'] > 0 ? '#f0fdf4' : 'white' ?>;"
                        onmouseover="this.style.borderColor='var(--primary-green)'; this.style.transform='translateY(-2px)';"
                        onmouseout="this.style.borderColor='var(--border-color)'; this.style.transform='none';">

                        <div style="display: flex; gap: 16px; align-items: center;">
                            <!-- Profile Picture -->
                            <?php if (!empty($conv['other_profile_picture'])): ?>
                                <img src="<?= $base_url ?>/<?= htmlspecialchars($conv['other_profile_picture']) ?>"
                                    style="width: 56px; height: 56px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid #e0e0e0;">
                            <?php else: ?>
                                <div
                                    style="width: 56px; height: 56px; border-radius: 50%; background: var(--primary-green); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 24px; flex-shrink: 0;">
                                    <?= strtoupper(substr($conv['other_username'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Info -->
                            <div style="flex: 1; min-width: 0;">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                                    <h3
                                        style="margin: 0; font-size: 16px; color: var(--text-dark); font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding-right: 8px;">
                                        <?= htmlspecialchars($conv['other_username']) ?>
                                    </h3>
                                    <span style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                        <?= date('M d, H:i', strtotime($conv['last_message_time'])) ?>
                                    </span>
                                </div>
                                <div
                                    style="font-size: 13px; color: var(--primary-green-dark); font-weight: 500; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($conv['product_title']) ?>
                                </div>
                                <div
                                    style="font-size: 14px; color: <?= $conv['unread_count'] > 0 ? 'var(--text-dark)' : 'var(--text-muted)' ?>; font-weight: <?= $conv['unread_count'] > 0 ? '600' : 'normal' ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php if ($conv['last_sender_id'] == $my_id): ?>
                                        <span style="opacity: 0.7;">You:</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($conv['last_message']) ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($conv['unread_count'] > 0): ?>
                            <div
                                style="position: absolute; top: 16px; right: 16px; background: var(--danger); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">
                                <?= $conv['unread_count'] > 99 ? '99+' : $conv['unread_count'] ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>