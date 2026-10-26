<?php
require_once 'config.php';
require_once 'includes/mail_helper.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Self-healing database check: Auto-create password_resets table if missing
    try {
        $pdo->query("SELECT 1 FROM password_resets LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            method ENUM('email', 'whatsapp') NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    }

    $email = trim($_POST['email'] ?? $_POST['identifier'] ?? '');

    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Find user by email
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, method, expires_at) VALUES (?, ?, 'email', ?)");
            $stmt->execute([$user['id'], $token, $expires]);

            $reset_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";

            if (!empty($user['email']) && sendResetEmail($user['email'], $user['username'], $reset_link)) {
                $success = "A reset link has been sent to your email address.";
            } else {
                // Fallback for Localhost/Testing: Show the link if email fails
                $success = "<strong>Email server not configured (Localhost).</strong><br>For testing purposes, here is your link: <a href='$reset_link' style='font-weight:bold; color:var(--primary-green);'>Reset Password Now</a>";
            }
        } else {
            // Generic message for security
            $success = "If that account exists, a reset link has been sent to your email address.";
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2 style="text-align: center; margin-bottom: 24px; color: var(--primary-green-dark);">Reset Password</h2>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 24px; font-size: 14px;">
            Enter your registered Email Address and we'll send you a link to reset your password.
        </p>

        <?php if ($error): ?>
            <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= $success ?>
            </div>
        <?php else: ?>
            <form method="POST" action="forgot_password.php">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required
                        placeholder="e.g. john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <p style="text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-muted);">
            Remembered your password? <a href="login.php"
                style="color: var(--primary-green); font-weight: 600; text-decoration: none;">Back to Login</a>
        </p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>