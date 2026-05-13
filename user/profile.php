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
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch statistics
$count_active = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'active'");
$count_active->execute([$user_id]);
$active_ads = $count_active->fetchColumn();

$count_sold = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'sold'");
$count_sold->execute([$user_id]);
$sold_ads = $count_sold->fetchColumn();

$count_wish = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$count_wish->execute([$user_id]);
$wishlist_count = $count_wish->fetchColumn();

$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/Enteangadi";
require_once '../includes/header.php';
?>

<div class="profile-page-wrapper">
    <!-- Premium Header Section -->
    <div class="profile-header-premium">
        <div class="profile-header-bg"></div>
        <div class="container" style="position: relative; z-index: 2; padding-top: 60px; padding-bottom: 40px;">
            <div class="profile-hero-content">
                <div class="profile-avatar-wrapper" onclick="document.getElementById('profile_picture_input').click()">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= $base_url . '/' . htmlspecialchars($user['profile_picture']) ?>" alt="Profile"
                            class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar-placeholder">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="avatar-edit-badge">
                        <i class="fa fa-camera"></i>
                    </div>
                </div>

                <div class="profile-titles">
                    <h1><?= htmlspecialchars($user['username']) ?></h1>
                    <p><i class="fa fa-phone"></i> <?= htmlspecialchars($user['phone_number']) ?></p>
                    <?php if (!empty($user['email'])): ?>
                        <p style="margin-top: 4px;"><i class="fa fa-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <form id="profile_pic_form" action="profile.php" method="POST" enctype="multipart/form-data"
                    style="display: none;">
                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*"
                        onchange="document.getElementById('profile_pic_form').submit()">
                </form>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -30px; position: relative; z-index: 10;">
        <?php if ($success): ?>
            <div class="alert-success-premium"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-danger-premium"><?= $error ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="profile-stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e8f5e9; color: #2e7d32;"><i class="fa fa-rocket"></i></div>
                <div class="stat-info">
                    <h3><?= $active_ads ?></h3>
                    <p>Live Ads</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff3e0; color: #ef6c00;"><i class="fa fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $sold_ads ?></h3>
                    <p>Sold Items</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fce4ec; color: #c2185b;"><i class="fa fa-heart"></i></div>
                <div class="stat-info">
                    <h3><?= $wishlist_count ?></h3>
                    <p>Wishlist</p>
                </div>
            </div>
        </div>

        <!-- Menu Section -->
        <div class="profile-content-layout">
            <div class="profile-menu-premium">
                <h2 class="menu-title">Account Management</h2>

                <a href="my_ads.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-th-large"></i></div>
                        <span>My Dashboard & Ads</span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="wishlist.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-heart"></i></div>
                        <span>Saved Favorites</span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="inbox.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-comment-alt"></i></div>
                        <span>Messages</span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="settings.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-user-cog"></i></div>
                        <span>Account Settings</span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="help_support.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-question-circle"></i></div>
                        <span>Help & Support</span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="../logout.php" class="menu-link logout-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-sign-out-alt"></i></div>
                        <span>Logout</span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .profile-page-wrapper {
        background-color: var(--background);
        min-height: 100vh;
    }

    .profile-header-premium {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
        color: white;
    }

    .profile-header-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
        opacity: 0.1;
        z-index: 1;
    }

    .profile-hero-content {
        display: flex;
        align-items: center;
        gap: 32px;
    }

    .profile-avatar-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.2);
        cursor: pointer;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .profile-avatar-wrapper:hover {
        transform: scale(1.05);
    }

    .profile-avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .profile-avatar-placeholder {
        width: 100%;
        height: 100%;
        background: var(--white);
        color: var(--primary-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        font-weight: 800;
        border-radius: 50%;
    }

    .avatar-edit-badge {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: var(--accent-gold);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .profile-titles h1 {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 4px;
        letter-spacing: -0.5px;
    }

    .profile-titles p {
        font-size: 16px;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Stats Grid */
    .profile-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: white;
        padding: 24px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-info h3 {
        font-size: 20px;
        font-weight: 800;
        color: var(--text-dark);
    }

    .stat-info p {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* Menu Premium */
    .profile-menu-premium {
        background: white;
        border-radius: var(--border-radius);
        padding: 24px;
        box-shadow: var(--shadow-sm);
    }

    .menu-title {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--text-muted);
        margin-bottom: 20px;
        font-weight: 700;
    }

    .menu-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        text-decoration: none;
        color: var(--text-dark);
        border-radius: 12px;
        transition: all 0.2s;
        margin-bottom: 8px;
    }

    .menu-link:hover {
        background: #f8fafc;
        transform: translateX(5px);
    }

    .menu-link-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .menu-icon {
        width: 40px;
        height: 40px;
        background: #f1f5f9;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 16px;
        transition: all 0.2s;
    }

    .menu-link:hover .menu-icon {
        background: var(--primary-green);
        color: white;
    }

    .menu-link span {
        font-weight: 600;
        font-size: 15px;
    }

    .menu-link i.fa-chevron-right {
        font-size: 12px;
        color: #cbd5e1;
    }

    .logout-link {
        color: var(--danger);
        margin-top: 16px;
        border-top: 1px solid #f1f5f9;
        padding-top: 24px;
    }

    .logout-link .menu-icon {
        background: #fef2f2;
        color: var(--danger);
    }

    .logout-link:hover .menu-icon {
        background: var(--danger);
        color: white;
    }

    .alert-success-premium {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-weight: 600;
        border-left: 5px solid #2e7d32;
    }

    @media (max-width: 768px) {
        .profile-hero-content {
            flex-direction: column;
            text-align: center;
            gap: 16px;
        }

        .profile-stats-grid {
            grid-template-columns: 1fr;
        }

        .profile-titles h1 {
            font-size: 24px;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>