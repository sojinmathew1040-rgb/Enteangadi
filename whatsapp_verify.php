<?php
require_once 'config.php';

$error = '';
$step = isset($_POST['step']) ? intval($_POST['step']) : 1;
$phone_number = $_POST['phone_number'] ?? '';
$otp = $_POST['otp'] ?? '';
$generated_otp = $_POST['generated_otp'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        if (empty($phone_number)) {
            $error = 'Please enter your phone number.';
        } else {
            $generated_otp = strval(rand(100000, 999999));
            $step = 2;
        }
    } elseif ($step === 2) {
        if ($otp === $generated_otp || $otp === '123456') {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ?");
                $stmt->execute([$phone_number]);
                $user = $stmt->fetch();

                if (!$user) {
                    $username = 'WA_User_' . substr($phone_number, -4);
                    $email = 'wa_' . time() . '@enteangadi.local';
                    $password_hashed = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                    
                    $ins = $pdo->prepare("INSERT INTO users (username, phone_number, email, password, is_verified, role) VALUES (?, ?, ?, ?, 1, 'user')");
                    $ins->execute([$username, $phone_number, $email, $password_hashed]);
                    
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ?");
                    $stmt->execute([$phone_number]);
                    $user = $stmt->fetch();
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];

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

                enteangadi_set_cookie('enteangadi_remember_user', $user['id'], time() + 30 * 24 * 60 * 60);
                enteangadi_set_cookie('enteangadi_remember_token', $token, time() + 30 * 24 * 60 * 60);

                header("Location: user/index.php");
                exit;
            } catch (Exception $e) {
                $error = 'Authentication failed: ' . $e->getMessage();
                $step = 1;
            }
        } else {
            $error = 'Invalid OTP code. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container" style="max-width: 420px; margin: 40px auto; padding: 32px; border-radius: var(--border-radius); box-shadow: var(--shadow-md); border: 1px solid var(--border-color); background: var(--white);">
        
        <div style="text-align: center; margin-bottom: 24px;">
            <i class="fab fa-whatsapp" style="font-size: 48px; color: #25d366; margin-bottom: 12px;"></i>
            <h2 style="color: var(--text-dark); margin: 0; font-size: 20px; font-weight: 800;">WhatsApp Quick Verification</h2>
            <p style="color: var(--text-muted); font-size: 13px; margin: 6px 0 0 0;">Verify instantly without password keys</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: 600;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" action="">
                <input type="hidden" name="step" value="1">
                <div class="form-group">
                    <label style="font-weight: 700; font-size: 13px;">WhatsApp Number</label>
                    <input type="tel" name="phone_number" class="form-control" placeholder="e.g. +919876543210" value="<?= htmlspecialchars($phone_number) ?>" required style="margin-top: 8px;">
                </div>
                <button type="submit" class="btn-primary" style="background: #25d366; border-color: #25d366; width: 100%; margin-top: 16px; font-weight: 700;">Send Verification OTP</button>
            </form>
        <?php else: ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 12px; line-height: 1.5;">
                <i class="fab fa-whatsapp" style="margin-right: 4px; font-size: 14px;"></i> Simulated OTP code sent to your WhatsApp: <strong><?= $generated_otp ?></strong> (Master bypass: <strong>123456</strong>)
            </div>

            <form method="POST" action="">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="phone_number" value="<?= htmlspecialchars($phone_number) ?>">
                <input type="hidden" name="generated_otp" value="<?= htmlspecialchars($generated_otp) ?>">
                
                <div class="form-group">
                    <label style="font-weight: 700; font-size: 13px;">Enter 6-digit Code</label>
                    <input type="text" name="otp" class="form-control" placeholder="Enter OTP code" maxlength="6" required style="margin-top: 8px; text-align: center; letter-spacing: 4px; font-size: 18px; font-weight: bold;">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 16px; font-weight: 700;">Confirm & Login</button>
            </form>
            <div style="text-align: center; margin-top: 16px;">
                <a href="whatsapp_verify.php" style="font-size: 13px; color: var(--text-muted); text-decoration: none;"><i class="fa fa-arrow-left"></i> Change Number</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
