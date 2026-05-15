<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'logout':
                header("Location: ../logout.php");
                exit;

            case 'logout_all':
                $new_token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
                if ($stmt->execute([$new_token, $user_id])) {
                    $_SESSION['session_token'] = $new_token;
                    $success = "Logged out from all other devices.";
                } else {
                    $error = "Failed to logout from all devices.";
                }
                break;

            case 'change_password':
                $current_pw = $_POST['current_password'] ?? '';
                $new_pw = $_POST['new_password'] ?? '';
                $confirm_pw = $_POST['confirm_password'] ?? '';

                if (!empty($current_pw) && !empty($new_pw) && !empty($confirm_pw)) {
                    if ($new_pw !== $confirm_pw) {
                        $error = "New passwords do not match.";
                    } else {
                        // Verify current password
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user_pw = $stmt->fetchColumn();

                        if (password_verify($current_pw, $user_pw)) {
                            $hashed_pw = password_hash($new_pw, PASSWORD_DEFAULT);
                            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            if ($upd->execute([$hashed_pw, $user_id])) {
                                $success = "Password updated successfully.";
                            } else {
                                $error = "Failed to update password.";
                            }
                        } else {
                            $error = "Incorrect current password.";
                        }
                    }
                } else {
                    $error = "Please fill in all password fields.";
                }
                break;

            case 'delete_account':
                // Fetch all product images first to delete them
                $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id IN (SELECT id FROM products WHERE user_id = ?)");
                $img_stmt->execute([$user_id]);
                $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

                // Fetch profile picture and details for logging
                $user_stmt = $pdo->prepare("SELECT username, email, profile_picture FROM users WHERE id = ?");
                $user_stmt->execute([$user_id]);
                $user_data = $user_stmt->fetch();
                $profile_pic = $user_data['profile_picture'] ?? null;

                $reason = $_POST['closure_reason'] ?? 'Not specified';
                if ($reason === 'Other') {
                    $reason = $_POST['custom_reason'] ?? 'Other';
                }

                // Log the closure feedback
                $log_stmt = $pdo->prepare("INSERT INTO account_closures (username, email, reason) VALUES (?, ?, ?)");
                $log_stmt->execute([$user_data['username'], $user_data['email'], $reason]);

                // Delete user (cascades to products, images, etc. in DB)
                $del_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($del_stmt->execute([$user_id])) {
                    // Delete files from folders
                    foreach ($images as $img) {
                        if ($img && file_exists('../' . $img)) {
                            unlink('../' . $img);
                        }
                    }
                    if ($profile_pic && file_exists('../' . $profile_pic)) {
                        unlink('../' . $profile_pic);
                    }

                    session_destroy();
                    header("Location: ../index.php?msg=account_deleted");
                    exit;
                } else {
                    $error = "Failed to delete account.";
                }
                break;
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container" style="max-width: 600px; padding-top: 40px; padding-bottom: 40px;">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
        <a href="profile.php" style="color: var(--text-dark); font-size: 20px;"><i class="fa fa-arrow-left"></i></a>
        <h2 style="color: var(--primary-green-dark); margin: 0;"><?= __('settings') ?></h2>
    </div>

    <?php if ($success): ?>
        <div
            style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div
        style="background: var(--white); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">

        <!-- Logout Current -->
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="logout">
            <button type="submit"
                style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: none; border: none; border-bottom: 1px solid var(--border-color); cursor: pointer; text-align: left; transition: background 0.2s;">
                <i class="fa fa-sign-out-alt" style="color: var(--text-muted); font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--text-dark);"><?= __('logout') ?></span>
                <i class="fa fa-chevron-right" style="color: var(--border-color); font-size: 12px;"></i>
            </button>
        </form>

        <!-- Change Password -->
        <div style="border-bottom: 1px solid var(--border-color);">
            <button type="button"
                onclick="document.getElementById('change-pw-form').style.display = (document.getElementById('change-pw-form').style.display === 'none' ? 'block' : 'none')"
                style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: none; border: none; cursor: pointer; text-align: left; transition: background 0.2s;">
                <i class="fa fa-key" style="color: #FBC02D; font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--text-dark);"><?= __('change_password') ?></span>
                <i class="fa fa-chevron-down" style="color: var(--border-color); font-size: 12px;"></i>
            </button>
            <div id="change-pw-form" style="display: none; padding: 0 20px 20px 56px;">
                <form method="POST" action="settings.php">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 12px;"><?= __('current_password') ?></label>
                        <input type="password" name="current_password" class="form-control" style="padding: 8px 12px;"
                            required>
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label style="font-size: 12px;"><?= __('new_password') ?></label>
                        <input type="password" name="new_password" class="form-control" style="padding: 8px 12px;"
                            required>
                    </div>
                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-size: 12px;"><?= __('confirm_password') ?></label>
                        <input type="password" name="confirm_password" class="form-control" style="padding: 8px 12px;"
                            required>
                    </div>
                    <button type="submit" class="btn-primary"
                        style="padding: 8px 16px; font-size: 14px; border-radius: 8px;"><?= __('update_password') ?></button>
                </form>
            </div>
        </div>

        <!-- Language Switcher -->
        <div style="border-bottom: 1px solid var(--border-color);">
            <div style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px;">
                <i class="fa fa-globe" style="color: #4285F4; font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--text-dark);"><?= __('language') ?></span>
                <div class="toggle-switch-wrapper"
                    style="display: flex; background: var(--background); padding: 4px; border-radius: 10px; border: 1px solid var(--border-color);">
                    <a href="?lang=en"
                        style="padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; transition: 0.3s; background: <?= ($_SESSION['lang'] ?? 'en') == 'en' ? 'var(--primary-green)' : 'transparent' ?>; color: <?= ($_SESSION['lang'] ?? 'en') == 'en' ? 'white' : 'var(--text-muted)' ?>;">EN</a>
                    <a href="?lang=ml"
                        style="padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; transition: 0.3s; background: <?= ($_SESSION['lang'] ?? 'en') == 'ml' ? 'var(--primary-green)' : 'transparent' ?>; color: <?= ($_SESSION['lang'] ?? 'en') == 'ml' ? 'white' : 'var(--text-muted)' ?>;">മല</a>
                </div>
            </div>
        </div>

        <!-- Dark Mode Toggle -->
        <div style="border-bottom: 1px solid var(--border-color);">
            <div style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; cursor: pointer;"
                onclick="toggleTheme(); updateSettingsThemeIcon()">
                <i id="settings-theme-icon" class="fa fa-moon"
                    style="color: #6366f1; font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--text-dark);">Dark Mode</span>
                <div id="theme-toggle-indicator"
                    style="width: 40px; height: 20px; background: #cbd5e1; border-radius: 20px; position: relative; transition: 0.3s;">
                    <div id="theme-toggle-dot"
                        style="width: 16px; height: 16px; background: white; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: 0.3s;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Logout All Devices -->
        <form method="POST" style="margin: 0;"
            onsubmit="return confirm('This will logout all other sessions except this one. Continue?');">
            <input type="hidden" name="action" value="logout_all">
            <button type="submit"
                style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: none; border: none; border-bottom: 1px solid var(--border-color); cursor: pointer; text-align: left; transition: background 0.2s;">
                <i class="fa fa-devices" style="color: var(--primary-green); font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--text-dark);"><?= __('logout_all_devices') ?></span>
                <i class="fa fa-chevron-right" style="color: var(--border-color); font-size: 12px;"></i>
            </button>
        </form>

        <!-- Delete Account -->
        <button type="button" onclick="openClosureModal()"
            style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: none; border: none; cursor: pointer; text-align: left; transition: background 0.2s;">
            <i class="fa fa-user-slash" style="color: var(--danger); font-size: 18px; width: 24px;"></i>
            <span style="font-weight: 500; flex: 1; color: var(--danger);"><?= __('delete_account') ?></span>
            <i class="fa fa-chevron-right" style="color: #fca5a5; font-size: 12px;"></i>
        </button>
    </div>

    <p style="text-align: center; color: var(--text-muted); font-size: 12px; margin-top: 32px;">
        Enteangadi Version 1.2.0
    </p>
</div>

<!-- Account Closure Modal -->
<div id="closureModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div
        style="background: white; padding: 32px; border-radius: 24px; width: 90%; max-width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
        <h3 style="margin-bottom: 8px; font-size: 20px; font-weight: 800; color: var(--text-dark);">Close Account?</h3>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px;">We're sorry to see you go. Please
            tell us why you are leaving.</p>

        <form method="POST" id="closureForm">
            <input type="hidden" name="action" value="delete_account">

            <div class="form-group" style="margin-bottom: 20px;">
                <label
                    style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 12px; display: block; text-transform: uppercase;">Select
                    a reason</label>
                <div id="closureReasons" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Options injected by JS -->
                </div>
            </div>

            <div id="customClosureWrapper" style="display: none; margin-bottom: 20px;">
                <label
                    style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block; text-transform: uppercase;">Please
                    specify</label>
                <textarea name="custom_reason" class="form-control" rows="3" placeholder="Tell us more..."
                    style="font-size: 14px; border-radius: 12px;"></textarea>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 32px;">
                <button type="button" onclick="closeClosureModal()" class="btn-secondary"
                    style="flex: 1; padding: 12px; border-radius: 12px; font-weight: 600;">Cancel</button>
                <button type="submit" class="btn-primary"
                    style="flex: 2; padding: 12px; border-radius: 12px; font-weight: 700; background: #ef4444; border-color: #ef4444;">Delete
                    Permanently</button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateSettingsThemeIcon() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const icon = document.getElementById('settings-theme-icon');
        const indicator = document.getElementById('theme-toggle-indicator');
        const dot = document.getElementById('theme-toggle-dot');

        if (isDark) {
            icon.className = 'fa fa-sun';
            icon.style.color = '#FBC02D';
            indicator.style.background = 'var(--primary-green)';
            dot.style.left = '22px';
        } else {
            icon.className = 'fa fa-moon';
            icon.style.color = '#6366f1';
            indicator.style.background = '#cbd5e1';
            dot.style.left = '2px';
        }
    }

    // Initialize icons on load
    document.addEventListener('DOMContentLoaded', updateSettingsThemeIcon);

    const closureReasonsListFinal = [
        'Found a better platform',
        'Not enough buyers/sellers',
        'Privacy/Safety concerns',
        'App is difficult to use',
        'No longer need an account',
        'Other'
    ];

    function openClosureModal() {
        const modal = document.getElementById('closureModal');
        const optionsDiv = document.getElementById('closureReasons');

        optionsDiv.innerHTML = '';
        closureReasonsListFinal.forEach((r, i) => {
            const label = document.createElement('label');
            label.style = "display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s;";
            label.innerHTML = `<input type="radio" name="closure_reason" value="${r}" ${i === 0 ? 'checked' : ''} onchange="toggleCustomClosure(this.value)"> <span style="font-size: 14px; font-weight: 600; color: #334155;">${r}</span>`;
            optionsDiv.appendChild(label);
        });

        document.getElementById('customClosureWrapper').style.display = 'none';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function toggleCustomClosure(val) {
        document.getElementById('customClosureWrapper').style.display = (val === 'Other') ? 'block' : 'none';
    }

    function closeClosureModal() {
        document.getElementById('closureModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    document.getElementById('closureForm').onsubmit = function () {
        return confirm('Are you absolutely sure? This will delete all your listings and profile data forever.');
    };

    window.onclick = function (event) {
        if (event.target == document.getElementById('closureModal')) closeClosureModal();
    }
</script>

<?php require_once '../includes/footer.php'; ?>