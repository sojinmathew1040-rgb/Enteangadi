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

            case 'delete_account':
                // Fetch all product images first to delete them
                $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id IN (SELECT id FROM products WHERE user_id = ?)");
                $img_stmt->execute([$user_id]);
                $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

                // Fetch profile picture
                $user_stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $user_stmt->execute([$user_id]);
                $profile_pic = $user_stmt->fetchColumn();

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
        <h2 style="color: var(--primary-green-dark); margin: 0;">Settings</h2>
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
                style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: none; border: none; border-bottom: 1px solid #eee; cursor: pointer; text-align: left; transition: background 0.2s;">
                <i class="fa fa-sign-out-alt" style="color: var(--text-muted); font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--text-dark);">Logout</span>
                <i class="fa fa-chevron-right" style="color: #ccc; font-size: 12px;"></i>
            </button>
        </form>

        <!-- Logout All Devices -->
        <form method="POST" style="margin: 0;"
            onsubmit="return confirm('This will logout all other sessions except this one. Continue?');">
            <input type="hidden" name="action" value="logout_all">
            <button type="submit"
                style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: none; border: none; border-bottom: 1px solid #eee; cursor: pointer; text-align: left; transition: background 0.2s;">
                <i class="fa fa-devices" style="color: var(--primary-green); font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--text-dark);">Logout from all devices</span>
                <i class="fa fa-chevron-right" style="color: #ccc; font-size: 12px;"></i>
            </button>
        </form>

        <!-- Delete Account -->
        <form method="POST" style="margin: 0;"
            onsubmit="return confirm('WARNING: This will permanently delete your account and all your listings. This action cannot be undone. Are you sure?');">
            <input type="hidden" name="action" value="delete_account">
            <button type="submit"
                style="width: 100%; display: flex; align-items: center; gap: 12px; padding: 16px 20px; background: none; border: none; cursor: pointer; text-align: left; transition: background 0.2s;">
                <i class="fa fa-user-slash" style="color: var(--danger); font-size: 18px; width: 24px;"></i>
                <span style="font-weight: 500; flex: 1; color: var(--danger);">Delete Account</span>
                <i class="fa fa-chevron-right" style="color: #fca5a5; font-size: 12px;"></i>
            </button>
        </form>
    </div>

    <p style="text-align: center; color: var(--text-muted); font-size: 12px; margin-top: 32px;">
        Enteangadi Version 1.2.0
    </p>
</div>

<?php require_once '../includes/footer.php'; ?>