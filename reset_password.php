<?php
require_once 'config.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit;
}

// Verify token
$stmt = $pdo->prepare("SELECT pr.*, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ? AND pr.expires_at > NOW()");
$stmt->execute([$token]);
$reset_request = $stmt->fetch();

if (!$reset_request) {
    $error = "This reset link is invalid or has expired. Please request a new one.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset_request) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($password)) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $reset_request['user_id']]);

            // Delete token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);

            $success = "Your password has been reset successfully! You can now <a href='login.php' style='font-weight:bold; color:var(--primary-green-dark);'>Login</a>.";
        }
    } else {
        $error = "Please enter a new password.";
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2 style="text-align: center; margin-bottom: 24px; color: var(--primary-green-dark);">Set New Password</h2>

        <?php if ($reset_request): ?>
            <p style="text-align: center; color: var(--text-muted); margin-bottom: 24px; font-size: 14px;">
                Hi <strong><?= htmlspecialchars($reset_request['username']) ?></strong>, please enter your new password
                below.
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= $success ?>
            </div>
        <?php elseif ($reset_request): ?>
            <form method="POST" action="reset_password.php">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="password-field-container">
                        <input type="password" id="password" name="password" class="form-control" required
                            placeholder="Minimum 6 characters">
                        <i class="fa fa-eye password-toggle" onclick="togglePasswordVisibility('password')"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-field-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                            placeholder="Confirm your new password">
                        <i class="fa fa-eye password-toggle" onclick="togglePasswordVisibility('confirm_password')"></i>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    function togglePasswordVisibility(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const toggleIcon = passwordField.nextElementSibling;

        if (passwordField.type === "password") {
            passwordField.type = "text";
            toggleIcon.classList.remove("fa-eye");
            toggleIcon.classList.add("fa-eye-slash");
        } else {
            passwordField.type = "password";
            toggleIcon.classList.remove("fa-eye-slash");
            toggleIcon.classList.add("fa-eye");
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>