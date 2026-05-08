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

    $identifier = trim($_POST['identifier'] ?? ''); // Email or Phone

    if (!empty($identifier)) {
        // Find user by email or phone
        $stmt = $pdo->prepare("SELECT id, username, email, phone_number FROM users WHERE email = ? OR phone_number = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, method, expires_at) VALUES (?, ?, ?, ?)");

            // Determine method based on input or user record
            $method = (filter_var($identifier, FILTER_VALIDATE_EMAIL)) ? 'email' : 'whatsapp';
            $stmt->execute([$user['id'], $token, $method, $expires]);

            $reset_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=$token";

            if ($method === 'email' && !empty($user['email'])) {
                if (sendResetEmail($user['email'], $user['username'], $reset_link)) {
                    $success = "A reset link has been sent to your email address.";
                } else {
                    // Fallback for Localhost/Testing: Show the link if email fails
                    $success = "<strong>Email server not configured (Localhost).</strong><br>For testing purposes, here is your link: <a href='$reset_link' style='font-weight:bold; color:var(--primary-green);'>Reset Password Now</a>";
                }
            } else {
                // Automated WhatsApp attempt
                if (sendWhatsAppReset($user['phone_number'], $user['username'], $reset_link)) {
                    $success = "A reset link has been sent to your WhatsApp number.";
                } else {
                    // Fallback: Manual WhatsApp Link
                    $wa_message = "Hi " . $user['username'] . ", click here to reset your Enteangadi password: " . $reset_link;
                    $wa_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $user['phone_number']) . "?text=" . urlencode($wa_message);

                    $success = "We've generated a reset link for your WhatsApp. <a href='$wa_url' target='_blank' style='font-weight:bold; color:var(--primary-green);'>Click here to send it</a>.";
                }
            }
        } else {
            // Generic message for security
            $success = "If that account exists, a reset link has been sent.";
        }
    } else {
        $error = "Please enter your email or phone number.";
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2 style="text-align: center; margin-bottom: 24px; color: var(--primary-green-dark);">Reset Password</h2>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 24px; font-size: 14px;">
            Enter your registered Email or Phone Number and we'll send you a link to reset your password.
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
                    <label for="identifier">Email or Phone Number</label>
                    <input type="text" id="identifier" name="identifier" class="form-control" required
                        placeholder="e.g. john@example.com or 9876543210">
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