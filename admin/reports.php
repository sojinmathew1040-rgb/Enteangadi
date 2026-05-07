<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_product_id'])) {
        $delete_id = $_POST['delete_product_id'];
        $stmt = $pdo->prepare("UPDATE products SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$delete_id]);

        // Mark reports for this product as resolved
        $stmt2 = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE product_id = ?");
        $stmt2->execute([$delete_id]);

        $success = "Product deleted and reports resolved.";
    } elseif (isset($_POST['resolve_report_id'])) {
        $resolve_id = $_POST['resolve_report_id'];
        $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$resolve_id]);
        $success = "Report marked as resolved.";
    }
}

// Fetch reports
$stmt = $pdo->query("SELECT r.*, p.title as product_title, p.status as product_status, u1.username as reporter_name, u2.username as owner_name, u2.phone_number as owner_phone
    FROM reports r 
    JOIN products p ON r.product_id = p.id
    JOIN users u1 ON r.reported_by_user_id = u1.id
    JOIN users u2 ON p.user_id = u2.id
    ORDER BY r.status ASC, r.created_at DESC");
$reports = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<h2 style="margin-bottom: 24px; color: var(--primary-green-dark);">Reported Ads</h2>

<?php if (isset($success)): ?>
    <div
        style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
        <?= $success ?>
    </div>
<?php endif; ?>

<div
    style="background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 1px solid var(--border-color); text-align: left;">
                <th style="padding: 16px;">Product</th>
                <th style="padding: 16px;">Reported By</th>
                <th style="padding: 16px;">Reason</th>
                <th style="padding: 16px;">Owner Actions</th>
                <th style="padding: 16px;">Status</th>
                <th style="padding: 16px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
                <tr
                    style="border-bottom: 1px solid var(--border-color); background: <?= $report['status'] === 'pending' ? '#fff9e6' : 'transparent' ?>;">
                    <td style="padding: 16px;">
                        <a href="../product.php?id=<?= $report['product_id'] ?>"
                            style="color: var(--primary-green); font-weight: 500; text-decoration: none;">
                            <?= htmlspecialchars($report['product_title']) ?>
                        </a>
                        <?php if ($report['product_status'] === 'deleted'): ?>
                            <span style="color: var(--danger); font-size: 12px; display: block;">(Deleted)</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 16px;"><?= htmlspecialchars($report['reporter_name']) ?></td>
                    <td style="padding: 16px; max-width: 200px;"><?= htmlspecialchars($report['reason']) ?></td>
                    <td style="padding: 16px;">
                        <div style="font-weight: 500; margin-bottom: 4px;"><?= htmlspecialchars($report['owner_name']) ?>
                        </div>
                        <?php
                        $wa_number = preg_replace('/\D/', '', $report['owner_phone']);
                        $wa_message = urlencode("Admin from Enteangadi: Your ad '{$report['product_title']}' has been reported.");
                        ?>
                        <a href="https://wa.me/<?= $wa_number ?>?text=<?= $wa_message ?>" target="_blank"
                            class="btn-secondary" style="padding: 4px 8px; font-size: 12px; display: inline-block;">
                            <i class="fab fa-whatsapp"></i> Contact Owner
                        </a>
                    </td>
                    <td style="padding: 16px;">
                        <span
                            style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: <?= $report['status'] === 'pending' ? '#fff3e0' : '#e8f5e9' ?>; color: <?= $report['status'] === 'pending' ? '#e65100' : '#2e7d32' ?>;">
                            <?= ucfirst($report['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 16px;">
                        <?php if ($report['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 8px; flex-direction: column;">
                                <?php if ($report['product_status'] !== 'deleted'): ?>
                                    <form method="POST" onsubmit="return confirm('Delete this product permanently?');">
                                        <input type="hidden" name="delete_product_id" value="<?= $report['product_id'] ?>">
                                        <button type="submit" class="btn-danger" style="width: 100%; font-size: 12px;"><i
                                                class="fa fa-trash"></i> Delete Ad</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST">
                                    <input type="hidden" name="resolve_report_id" value="<?= $report['id'] ?>">
                                    <button type="submit" class="btn-primary"
                                        style="width: 100%; font-size: 12px; padding: 6px 12px;">Mark Resolved</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>