<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$view_user_id = $_GET['id'] ?? 0;
if (!$view_user_id) {
    header("Location: inbox.php");
    exit;
}

// Fetch user details
$user_stmt = $pdo->prepare("SELECT username, email, phone_number, profile_picture, created_at FROM users WHERE id = ?");
$user_stmt->execute([$view_user_id]);
$view_user = $user_stmt->fetch();

if (!$view_user) {
    header("Location: inbox.php");
    exit;
}

// Fetch active ads
$ad_stmt = $pdo->prepare("
    SELECT p.*, 
    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image,
    (SELECT COUNT(*) FROM wishlist w WHERE w.product_id = p.id) as like_count
    FROM products p 
    WHERE p.user_id = ? AND p.status = 'active'
    ORDER BY p.created_at DESC
");
$ad_stmt->execute([$view_user_id]);
$active_ads = $ad_stmt->fetchAll();

// Fetch statistics
$count_active = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'active'");
$count_active->execute([$view_user_id]);
$total_active_ads = $count_active->fetchColumn();

$count_sold = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'sold'");
$count_sold->execute([$view_user_id]);
$total_sold_ads = $count_sold->fetchColumn();

require_once '../includes/header.php';
?>

<div class="view-profile-wrapper">
    <!-- Hero Profile Card -->
    <div class="profile-hero-card-premium">
        <div class="container">
            <div class="profile-nav-back">
                <a href="javascript:history.back()" class="btn-profile-back">
                    <i class="fa fa-arrow-left"></i> <span>Back</span>
                </a>
            </div>
            
            <div class="profile-hero-content-view">
                <div class="profile-avatar-wrapper-view">
                    <?php if (!empty($view_user['profile_picture'])): ?>
                        <img src="<?= $base_url . '/' . htmlspecialchars($view_user['profile_picture']) ?>" alt="Profile"
                            class="profile-avatar-img-view" onerror="this.style.display='none'; document.getElementById('profile-avatar-fallback').style.display='flex';">
                    <?php endif; ?>
                    <div id="profile-avatar-fallback" class="profile-avatar-placeholder-view" style="<?= !empty($view_user['profile_picture']) ? 'display: none;' : '' ?>">
                        <?= strtoupper(substr($view_user['username'], 0, 1)) ?>
                    </div>
                </div>

                <div class="profile-titles-view">
                    <h1><?= htmlspecialchars($view_user['username']) ?></h1>
                    <p class="member-since"><i class="fa fa-calendar-alt"></i> Member since <?= date('F Y', strtotime($view_user['created_at'])) ?></p>
                    
                    <div class="profile-stats-row-view">
                        <div class="stat-box-view">
                            <span class="stat-number"><?= $total_active_ads ?></span>
                            <span class="stat-label">Active Ads</span>
                        </div>
                        <div class="stat-box-view">
                            <span class="stat-number"><?= $total_sold_ads ?></span>
                            <span class="stat-label">Sold Items</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Listings Section -->
    <div class="container active-listings-container">
        <div class="section-header-premium">
            <h2>Active Listings</h2>
            <p>Browse other items from this seller</p>
        </div>

        <?php if (empty($active_ads)): ?>
            <div class="empty-state-view-profile">
                <div class="empty-illustration">
                    <i class="fa fa-layer-group"></i>
                </div>
                <h3>No Active Listings</h3>
                <p>This user hasn't posted any active advertisements recently.</p>
            </div>
        <?php else: ?>
            <div class="product-grid" style="margin-top: 20px;">
                <?php foreach ($active_ads as $ad): ?>
                    <a href="../product.php?id=<?= $ad['id'] ?>" class="product-card" style="text-decoration: none;">
                        <?php if ($ad['type'] == 'buy'): ?>
                            <div class="badge-wanted">Wanted</div>
                        <?php else: ?>
                            <div class="badge-selling">For Sale</div>
                        <?php endif; ?>

                        <?php if ($ad['main_image']): ?>
                            <img src="../<?= htmlspecialchars($ad['main_image']) ?>" loading="lazy"
                                alt="<?= htmlspecialchars($ad['title']) ?>" class="product-card-image">
                        <?php else: ?>
                            <div class="product-card-image"
                                style="display: flex; align-items: center; justify-content: center; background: #e0e0e0;">
                                <i class="fa fa-image" style="font-size: 40px; color: #999;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="product-card-content">
                            <div class="product-card-price">₹ <?= number_format($ad['price'], 0) ?></div>
                            <div class="product-card-title"><?= htmlspecialchars($ad['title']) ?></div>
                            <div class="product-card-meta">
                                <span><i class="fa fa-eye"></i> <?= number_format($ad['views']) ?></span>
                                <span><?= date('M d', strtotime($ad['created_at'])) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .view-profile-wrapper {
        background: var(--background);
        min-height: 100vh;
        padding-bottom: 60px;
    }

    .profile-hero-card-premium {
        background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
        color: white;
        padding: 40px 0 60px 0;
        position: relative;
        overflow: hidden;
    }

    .profile-hero-card-premium::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
        opacity: 0.1;
        pointer-events: none;
    }

    .profile-nav-back {
        margin-bottom: 24px;
    }

    .btn-profile-back {
        color: white;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 14px;
        opacity: 0.85;
        transition: opacity 0.2s;
    }

    .btn-profile-back:hover {
        opacity: 1;
    }

    .profile-hero-content-view {
        display: flex;
        align-items: center;
        gap: 32px;
    }

    @media (max-width: 768px) {
        .profile-hero-content-view {
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
    }

    .profile-avatar-wrapper-view {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        border: 4px solid rgba(255, 255, 255, 0.2);
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: var(--shadow-lg);
    }

    .profile-avatar-img-view {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-avatar-placeholder-view {
        font-size: 48px;
        font-weight: 800;
        color: white;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .profile-titles-view h1 {
        font-size: 32px;
        font-weight: 800;
        margin: 0 0 8px 0;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .profile-titles-view p.member-since {
        margin: 0;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.85);
        font-weight: 500;
    }

    .profile-stats-row-view {
        display: flex;
        gap: 24px;
        margin-top: 16px;
    }

    @media (max-width: 768px) {
        .profile-stats-row-view {
            justify-content: center;
        }
    }

    .stat-box-view {
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 18px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 80px;
    }

    .stat-number {
        font-size: 20px;
        font-weight: 800;
    }

    .stat-label {
        font-size: 11px;
        font-weight: 600;
        opacity: 0.8;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .active-listings-container {
        margin-top: 40px;
    }

    .section-header-premium {
        margin-bottom: 24px;
    }

    .section-header-premium h2 {
        font-size: 24px;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0 0 6px 0;
    }

    .section-header-premium p {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
    }

    .empty-state-view-profile {
        text-align: center;
        background: var(--white);
        border-radius: 20px;
        padding: 60px 24px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
        margin-top: 20px;
    }

    .empty-state-view-profile .empty-illustration {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        color: var(--text-muted);
        margin: 0 auto 20px auto;
    }

    .empty-state-view-profile h3 {
        font-size: 18px;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0 0 8px 0;
    }

    .empty-state-view-profile p {
        font-size: 14px;
        color: var(--text-muted);
        margin: 0;
        max-width: 400px;
        margin: 0 auto;
    }
</style>

<?php require_once '../includes/footer.php'; ?>
