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

// Fetch trust metrics
require_once '../includes/helpers.php';
$metrics = getUserTrustMetrics($view_user_id);

// Fetch reviews
$reviews_stmt = $pdo->prepare("
    SELECT r.*, u.username as reviewer_name, u.profile_picture as reviewer_pic
    FROM user_ratings r
    JOIN users u ON r.reviewer_id = u.id
    WHERE r.reviewee_id = ?
    ORDER BY r.created_at DESC
");
$reviews_stmt->execute([$view_user_id]);
$reviews = $reviews_stmt->fetchAll();

// Check review eligibility
$can_review = false;
$existing_rating = null;
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $view_user_id) {
    $can_review = true;
    $check_r = $pdo->prepare("SELECT rating, comment FROM user_ratings WHERE reviewer_id = ? AND reviewee_id = ?");
    $check_r->execute([$_SESSION['user_id'], $view_user_id]);
    $existing_rating = $check_r->fetch();
}

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
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <h1 style="margin: 0; font-size: 32px; font-weight: 800;"><?= htmlspecialchars($view_user['username']) ?></h1>
                        <?php if ($metrics['phone_verified'] || $metrics['email_verified']): ?>
                            <div class="verified-badge-pill" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: white;">
                                <i class="fa fa-check-circle" style="color: #4ade80;"></i> Verified
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <p class="member-since" style="margin: 6px 0 0 0;"><i class="fa fa-calendar-alt"></i> Member since <?= htmlspecialchars($metrics['member_since']) ?></p>
                    
                    <!-- Star Rating Row -->
                    <div class="profile-rating-stars-row" style="margin-top: 10px; display: flex; align-items: center; gap: 8px;">
                        <div style="color: #facc15; font-size: 15px;">
                            <?php
                            $stars = round($metrics['avg_rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $stars) {
                                    echo '<i class="fa fa-star"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            }
                            ?>
                        </div>
                        <span style="font-size: 13px; font-weight: 600; color: rgba(255,255,255,0.95);">
                            <?= number_format($metrics['avg_rating'], 1) ?> (<?= $metrics['review_count'] ?> <?= $metrics['review_count'] == 1 ? 'review' : 'reviews' ?>)
                        </span>
                    </div>

                    <!-- Responsiveness Row -->
                    <div style="margin-top: 8px; font-size: 13px; color: rgba(255,255,255,0.9); font-weight: 500; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-comments" style="font-size: 12px; opacity: 0.85;"></i> <?= htmlspecialchars($metrics['response_time']) ?>
                    </div>
                    
                    <div class="profile-stats-row-view" style="margin-top: 20px;">
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

    <!-- Reviews and Ratings Section -->
    <div class="container active-listings-container" style="margin-top: 50px; border-top: 1px solid var(--border-color); padding-top: 40px; margin-bottom: 40px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <div class="section-header-premium" style="margin: 0;">
                <h2>Seller Reviews</h2>
                <p>Read opinions and feedback from other buyers</p>
            </div>
            <?php if ($can_review): ?>
                <button onclick="document.getElementById('review-modal').style.display='flex'" class="btn-primary" style="padding: 10px 20px; font-size: 14px; border-radius: 24px;">
                    <i class="fa fa-pen"></i> Write a Review
                </button>
            <?php endif; ?>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="empty-state-view-profile" style="padding: 40px 20px;">
                <div class="empty-illustration">
                    <i class="fa fa-star-half-alt"></i>
                </div>
                <h3>No Reviews Yet</h3>
                <p>This seller hasn't received any reviews yet. Be the first to leave feedback!</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($reviews as $rev): ?>
                    <div style="background: var(--white); padding: 20px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; gap: 16px; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; background: #e2e8f0; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1.5px solid var(--border-color);">
                            <?php if (!empty($rev['reviewer_pic'])): ?>
                                <img src="<?= $base_url . '/' . htmlspecialchars($rev['reviewer_pic']) ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <span style="font-weight: 700; color: #475569; font-size: 18px;"><?= strtoupper(substr($rev['reviewer_name'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <h4 style="margin: 0; font-size: 15px; color: var(--text-dark); font-weight: 700;"><?= htmlspecialchars($rev['reviewer_name']) ?></h4>
                                <span style="font-size: 11px; color: var(--text-muted);"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                            </div>
                            <div style="color: #facc15; font-size: 12px; margin-bottom: 8px;">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rev['rating'] ? '<i class="fa fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <p style="margin: 0; font-size: 13.5px; color: var(--text-dark); line-height: 1.5;"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Review Submission Modal -->
    <?php if ($can_review): ?>
        <div id="review-modal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; z-index: 9999;">
            <div class="modal-content" style="max-width: 500px; width: 90%; border-radius: 24px; padding: 24px; box-shadow: var(--shadow-lg); background: var(--white);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin:0; font-weight:800; color: var(--text-dark);">Rate & Review Seller</h3>
                    <button class="close-modal" onclick="document.getElementById('review-modal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer; color: var(--text-dark);">&times;</button>
                </div>
                <form id="review-form" method="POST">
                    <input type="hidden" name="reviewee_id" value="<?= $view_user_id ?>">
                    
                    <div style="margin-bottom: 20px; text-align: center;">
                        <label style="display:block; font-weight:700; margin-bottom: 8px; color: var(--text-dark);">Your Rating</label>
                        <div class="star-rating-select" style="display: inline-flex; flex-direction: row-reverse; gap: 8px; font-size: 32px;">
                            <input type="radio" id="star5" name="rating" value="5" <?= ($existing_rating && $existing_rating['rating'] == 5) ? 'checked' : '' ?> style="display:none;" />
                            <label for="star5" class="fa fa-star" style="color: #cbd5e1; cursor:pointer; transition:color 0.2s;"></label>
                            <input type="radio" id="star4" name="rating" value="4" <?= ($existing_rating && $existing_rating['rating'] == 4) ? 'checked' : '' ?> style="display:none;" />
                            <label for="star4" class="fa fa-star" style="color: #cbd5e1; cursor:pointer; transition:color 0.2s;"></label>
                            <input type="radio" id="star3" name="rating" value="3" <?= ($existing_rating && $existing_rating['rating'] == 3) ? 'checked' : '' ?> style="display:none;" />
                            <label for="star3" class="fa fa-star" style="color: #cbd5e1; cursor:pointer; transition:color 0.2s;"></label>
                            <input type="radio" id="star2" name="rating" value="2" <?= ($existing_rating && $existing_rating['rating'] == 2) ? 'checked' : '' ?> style="display:none;" />
                            <label for="star2" class="fa fa-star" style="color: #cbd5e1; cursor:pointer; transition:color 0.2s;"></label>
                            <input type="radio" id="star1" name="rating" value="1" <?= ($existing_rating && $existing_rating['rating'] == 1) ? 'checked' : '' ?> style="display:none;" />
                            <label for="star1" class="fa fa-star" style="color: #cbd5e1; cursor:pointer; transition:color 0.2s;"></label>
                        </div>
                        <style>
                            .star-rating-select input:checked ~ label,
                            .star-rating-select label:hover,
                            .star-rating-select label:hover ~ label {
                                color: #facc15 !important;
                            }
                        </style>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display:block; font-weight:700; margin-bottom: 6px; color: var(--text-dark);">Write a Comment (Optional)</label>
                        <textarea name="comment" rows="4" class="form-control" placeholder="Share your experience trading with this seller..." style="resize:none; padding:12px; border-radius:12px; font-size:14px; width:100%; box-sizing:border-box; background: var(--background); color: var(--text-dark); border: 1px solid var(--border-color);"><?= $existing_rating ? htmlspecialchars($existing_rating['comment']) : '' ?></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; border-radius: 14px; font-weight: 700;">Submit Feedback</button>
                </form>
            </div>
        </div>
        
        <script>
            document.getElementById('review-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                try {
                    const response = await fetch('api_add_review.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Error submitting review');
                    }
                } catch(err) {
                    alert('Submission failed. Please try again.');
                }
            });
        </script>
    <?php endif; ?>
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
