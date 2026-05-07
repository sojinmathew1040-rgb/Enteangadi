<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user = $stmt->fetch();

            if (password_verify($current_password, $user['password'])) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update->execute([$hashed, $_SESSION['admin_id']]);
                $success = "Password updated successfully.";
            } else {
                $error = "Current password is incorrect.";
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

require_once 'includes/header.php';
?>

<div style="max-width: 600px;">
    <h2 style="margin-bottom: 24px; color: var(--primary-green-dark);">Account Settings</h2>

    <div
        style="background: var(--white); padding: 32px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
        <h3
            style="margin-bottom: 24px; font-size: 18px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
            Change Password</h3>

        <?php if ($error): ?>
            <div
                style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 8px;">Update Password</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>