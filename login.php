<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: user/index.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone_number = $_POST['phone_number'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($phone_number) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            // Handle session token for multi-device security
            $token = $user['session_token'];
            if (empty($token)) {
                $token = bin2hex(random_bytes(32));
                $upd = $pdo->prepare("UPDATE users SET session_token = ?, last_activity = NOW() WHERE id = ?");
                $upd->execute([$token, $user['id']]);
            } else {
                $upd = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
                $upd->execute([$user['id']]);
            }
            $_SESSION['session_token'] = $token;

            // Set 30-day persistent cookies
            enteangadi_set_cookie('enteangadi_remember_user', $user['id'], time() + 30 * 24 * 60 * 60);
            enteangadi_set_cookie('enteangadi_remember_token', $token, time() + 30 * 24 * 60 * 60);

            if ($user['role'] === 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: user/index.php"); // Redirect to user index
            }
            exit;
        } else {
            $error = "Invalid phone number or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2 style="text-align: center; margin-bottom: 24px; color: var(--primary-green-dark);">Welcome Back</h2>

        <?php if ($error): ?>
            <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" class="form-control" required
                    placeholder="Enter your phone number">
            </div>

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label for="password" style="margin-bottom: 0;">Password</label>
                    <a href="forgot_password.php"
                        style="font-size: 13px; color: var(--primary-green); text-decoration: none;">Forgot
                        Password?</a>
                </div>
                <div class="password-field-container" style="margin-top: 8px;">
                    <input type="password" id="password" name="password" class="form-control" required
                        placeholder="Enter your password">
                    <i class="fa fa-eye password-toggle" onclick="togglePasswordVisibility('password')"></i>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Login</button>
        </form>

        <p style="text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-muted);">
            Don't have an account? <a href="register.php"
                style="color: var(--primary-green); font-weight: 600; text-decoration: none;">Sign up here</a>
        </p>

        <div style="text-align: center; margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 16px;">
            <span style="font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 12px;">Or log in instantly via WhatsApp</span>
            <a href="whatsapp_verify.php" class="btn-primary" style="background: #25d366; border-color: #25d366; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; text-decoration: none;">
                <i class="fab fa-whatsapp" style="font-size: 18px;"></i> Login via WhatsApp OTP
            </a>
        </div>
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