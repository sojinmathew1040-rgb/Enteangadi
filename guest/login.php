<?php
require_once '../config.php';

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

            if ($user['role'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../user/index.php");
            }
            exit;
        } else {
            $error = "Invalid phone number or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

require_once '../includes/header.php';
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
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required
                    placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Login</button>
        </form>

        <p style="text-align: center; margin-top: 24px; font-size: 14px; color: var(--text-muted);">
            Don't have an account? <a href="register.php"
                style="color: var(--primary-green); font-weight: 600; text-decoration: none;">Sign up here</a>
        </p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>