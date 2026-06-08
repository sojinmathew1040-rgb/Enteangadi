<?php
require_once '../config.php';

if (isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'admin') {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'admin'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_permissions'] = $user['permissions'] ?? '';
            $_SESSION['is_admin_session'] = true;

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid admin credentials.";
        }
    } else {
        $error = "Please enter username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Enteangadi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: var(--primary-green-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        .admin-login-box {
            background: var(--white);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
        }

        .admin-brand {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-green-dark);
            margin-bottom: 8px;
        }

        .admin-subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 32px;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="admin-login-box">
        <div class="admin-brand">Enteangadi</div>
        <div class="admin-subtitle">Secure Admin Portal</div>

        <?php if ($error): ?>
            <div
                style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 14px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" class="form-control" required
                    placeholder="Enter username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required
                    placeholder="Enter password">
            </div>

            <button type="submit" class="btn-primary"
                style="width: 100%; margin-top: 16px; padding: 14px; font-size: 16px;">Login to Dashboard</button>
        </form>

        <div style="text-align: center; margin-top: 24px;">
            <a href="../guest/index.php"
                style="color: var(--text-muted); text-decoration: none; font-size: 14px;">&larr; Back to Main Site</a>
        </div>
    </div>

</body>

</html>