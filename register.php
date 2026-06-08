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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Self-healing database check: Auto-add email column if missing
    try {
        $pdo->query("SELECT email FROM users LIMIT 1");
    } catch (Exception $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(155) UNIQUE AFTER phone_number");
    }

    $username = trim($_POST['username'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($username) && !empty($phone_number) && !empty($email) && !empty($password)) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Check if phone number already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
            $stmt->execute([$phone_number]);
            if ($stmt->fetch()) {
                $error = "An account with this phone number already exists.";
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = "An account with this email already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, phone_number, email, password) VALUES (?, ?, ?, ?)");

                    try {
                        $stmt->execute([$username, $phone_number, $email, $hashed_password]);
                        $new_user_id = $pdo->lastInsertId();

                        // Auto-login logic
                        $token = bin2hex(random_bytes(32));
                        $upd = $pdo->prepare("UPDATE users SET session_token = ?, last_activity = NOW() WHERE id = ?");
                        $upd->execute([$token, $new_user_id]);

                        $_SESSION['user_id'] = $new_user_id;
                        $_SESSION['user_role'] = 'user';
                        $_SESSION['username'] = $username;
                        $_SESSION['session_token'] = $token;

                        // Set 30-day persistent cookies
                        enteangadi_set_cookie('enteangadi_remember_user', $new_user_id, time() + 30 * 24 * 60 * 60);
                        enteangadi_set_cookie('enteangadi_remember_token', $token, time() + 30 * 24 * 60 * 60);

                        header("Location: user/index.php");
                        exit;
                    } catch (PDOException $e) {
                        $error = "Error creating account. Please try again.";
                    }
                }
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h2 style="text-align: center; margin-bottom: 24px; color: var(--primary-green-dark);">Create an Account</h2>

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
            <form method="POST" action="register.php">
                <div class="form-group">
                    <label for="username">Full Name / Username</label>
                    <input type="text" id="username" name="username" class="form-control" required placeholder="John Doe"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" class="form-control" required
                        placeholder="Enter WhatsApp number" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required placeholder="john@example.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field-container">
                        <input type="password" id="password" name="password" class="form-control" required
                            placeholder="Create a password">
                        <i class="fa fa-eye password-toggle" onclick="togglePasswordVisibility('password')"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-field-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                            placeholder="Confirm your password">
                        <i class="fa fa-eye password-toggle" onclick="togglePasswordVisibility('confirm_password')"></i>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Sign Up</button>
            </form>
        <?php endif; ?>

        <p style="text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-muted);">
            Already have an account? <a href="login.php"
                style="color: var(--primary-green); font-weight: 600; text-decoration: none;">Login here</a>
        </p>
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