<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($phone_number) && !empty($password)) {
        // Check if phone number already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
        $stmt->execute([$phone_number]);
        if ($stmt->fetch()) {
            $error = "An account with this phone number already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, phone_number, password) VALUES (?, ?, ?)");

            try {
                $stmt->execute([$username, $phone_number, $hashed_password]);
                $success = "Account created successfully! You can now <a href='login.php'>Login</a>.";
            } catch (PDOException $e) {
                $error = "Error creating account. Please try again.";
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
                    <input type="text" id="username" name="username" class="form-control" required placeholder="John Doe">
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" class="form-control" required
                        placeholder="Enter WhatsApp number">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required
                        placeholder="Create a password">
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

<?php require_once 'includes/footer.php'; ?>