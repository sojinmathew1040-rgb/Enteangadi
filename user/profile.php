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
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_info = pathinfo($_FILES['profile_picture']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            $new_name = uniqid() . '.' . $ext;
            $dest = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $dest)) {
                $db_path = 'uploads/profiles/' . $new_name;

                // Fetch old profile picture to delete it
                $old_pic_stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $old_pic_stmt->execute([$user_id]);
                $old_pic = $old_pic_stmt->fetchColumn();

                $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                if ($stmt->execute([$db_path, $user_id])) {
                    if ($old_pic && file_exists('../' . $old_pic)) {
                        unlink('../' . $old_pic);
                    }
                    $success = "Profile picture updated successfully!";
                } else {
                    $error = "Failed to update profile picture in database.";
                }
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file format. Only JPG, PNG, and WEBP are allowed.";
        }
    }

    // Handle Ad Deletion
    if (isset($_POST['delete_ad_id'])) {
        $delete_id = $_POST['delete_ad_id'];

        // Fetch images to delete them from server
        $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
        $img_stmt->execute([$delete_id]);
        $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$delete_id, $user_id])) {
            foreach ($images as $img) {
                if (file_exists('../' . $img)) {
                    unlink('../' . $img);
                }
            }
            $success = "Advertisement permanently deleted.";
        } else {
            $error = "Failed to delete advertisement.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch user's ads
$ad_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, 
    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.user_id = ? AND p.status = 'active'
    ORDER BY p.created_at DESC");
$ad_stmt->execute([$user_id]);
$my_ads = $ad_stmt->fetchAll();

$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/Enteangadi";
require_once '../includes/header.php';
?>

<div class="profile-container">
    <h2 style="color: var(--primary-green-dark); margin-bottom: 24px;">My Profile</h2>

    <?php if ($success): ?>
        <div class="alert-success-light">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-danger-light">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="profile-card">
        <!-- Left Side: Profile Info -->
        <div class="profile-info-side">
            <?php
            $seller_initial = strtoupper(substr($user['username'], 0, 1));
            $is_admin = ($user['role'] === 'admin');
            ?>
            <div class="profile-avatar" onclick="document.getElementById('profile_picture_input').click()">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= $base_url . '/' . htmlspecialchars($user['profile_picture']) ?>" alt="Profile" class="profile-avatar-img">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <?= $is_admin ? 'E' : $seller_initial ?>
                    </div>
                <?php endif; ?>
                <div class="profile-avatar-badge">
                    <i class="fa fa-camera" style="font-size: 14px;"></i>
                </div>
            </div>

            <form id="profile_pic_form" action="profile.php" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*" onchange="document.getElementById('profile_pic_form').submit()">
            </form>

            <h3 style="margin-bottom: 4px;"><?= htmlspecialchars($user['username']) ?></h3>
            <p style="color: var(--text-muted); font-size: 14px;">
                <i class="fa fa-phone" style="font-size: 12px; margin-right: 4px;"></i>
                <?= htmlspecialchars($user['phone_number']) ?>
            </p>
        </div>

        <div class="profile-actions-side">
            <h4 style="margin-bottom: 20px; color: var(--text-muted); font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700;">Account Settings</h4>
            <div class="profile-menu">
                <a href="wishlist.php" class="profile-menu-item">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="profile-menu-icon">
                            <i class="fa fa-heart"></i>
                        </div>
                        <span style="font-weight: 600;">My Wishlist</span>
                    </div>
                    <i class="fa fa-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
                </a>

                <a href="my_ads.php" class="profile-menu-item">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="profile-menu-icon">
                            <i class="fa fa-list-alt"></i>
                        </div>
                        <span style="font-weight: 600;">My Ads</span>
                    </div>
                    <i class="fa fa-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
                </a>

                <a href="settings.php" class="profile-menu-item">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="profile-menu-icon">
                            <i class="fa fa-cog"></i>
                        </div>
                        <span style="font-weight: 600;">Settings</span>
                    </div>
                    <i class="fa fa-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
                </a>

                <a href="help_support.php" class="profile-menu-item">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="profile-menu-icon">
                            <i class="fa fa-headset"></i>
                        </div>
                        <span style="font-weight: 600;">Help & Support</span>
                    </div>
                    <i class="fa fa-chevron-right" style="color: #cbd5e1; font-size: 12px;"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>