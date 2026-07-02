<?php
require_once 'config.php';

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.*, p.phone_number as product_phone, p.whatsapp_number as product_whatsapp, u.username, u.phone_number as user_phone, u.role, u.profile_picture, c.name as category_name 
        FROM products p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: index.php");
        exit;
    }

    // Save Rating Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        
        $rating = intval($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $reviewer_id = $_SESSION['user_id'];
        $reviewee_id = $product['user_id'];
        
        if ($reviewer_id == $reviewee_id) {
            $rating_error = "You cannot rate yourself.";
        } elseif ($rating < 1 || $rating > 5) {
            $rating_error = "Please select a rating between 1 and 5 stars.";
        } else {
            try {
                $ins_stmt = $pdo->prepare("INSERT INTO user_ratings (reviewer_id, reviewee_id, rating, comment) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE rating = ?, comment = ?");
                $ins_stmt->execute([$reviewer_id, $reviewee_id, $rating, $comment, $rating, $comment]);
                header("Location: product.php?id=" . $product_id . "&rating_success=1");
                exit;
            } catch (Exception $e) {
                $rating_error = "An error occurred while saving your rating.";
            }
        }
    }

    // Fetch Reviews
    $reviews = [];
    try {
        $rev_stmt = $pdo->prepare("SELECT r.*, u.username, u.profile_picture 
            FROM user_ratings r 
            JOIN users u ON r.reviewer_id = u.id 
            WHERE r.reviewee_id = ? 
            ORDER BY r.created_at DESC");
        $rev_stmt->execute([$product['user_id']]);
        $reviews = $rev_stmt->fetchAll();
    } catch (Exception $e) {}

    // [AD ANALYTICS] Increment View Count
    $update_views = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $update_views->execute([$product_id]);

    // Increment daily analytics view count
    try {
        $stmt_an = $pdo->prepare("INSERT INTO analytics_clicks (product_id, click_type, click_date, click_count) 
                                 VALUES (?, 'view', CURRENT_DATE, 1) 
                                 ON DUPLICATE KEY UPDATE click_count = click_count + 1");
        $stmt_an->execute([$product_id]);
    } catch (Exception $e) {}

    // Track recently viewed products in cookies
    $recently_viewed = [];
    if (isset($_COOKIE['recently_viewed'])) {
        $recently_viewed = json_decode($_COOKIE['recently_viewed'], true);
        if (!is_array($recently_viewed)) {
            $recently_viewed = [];
        }
    }
    if (($key = array_search($product_id, $recently_viewed)) !== false) {
        unset($recently_viewed[$key]);
    }
    array_unshift($recently_viewed, $product_id);
    $recently_viewed = array_slice($recently_viewed, 0, 15);
    enteangadi_set_cookie('recently_viewed', json_encode($recently_viewed), time() + (86400 * 30));

    // Security: Only allow active ads OR let owner/admin view pending/sold/expired
    $is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $product['user_id']);
    $is_admin = (isset($_SESSION['admin_id']));

    if ($product['status'] !== 'active' && !$is_owner && !$is_admin) {
        header("Location: index.php?error=unauthorized_view");
        exit;
    }

    $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $img_stmt->execute([$product_id]);
    $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Wishlist check
    $is_wishlisted = false;
    if (isset($_SESSION['user_id'])) {
        $wish_stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $wish_stmt->execute([$_SESSION['user_id'], $product_id]);
        $is_wishlisted = (bool) $wish_stmt->fetch();
    }

    if ($product) {
        $og_title = $product['title'];
        $og_desc = substr(strip_tags($product['description']), 0, 150);
        if (strlen(strip_tags($product['description'])) > 150) {
            $og_desc .= '...';
        }
        $og_image = '';
        if (!empty($images)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $og_image = $protocol . $host . ($base_url ? $base_url : '') . '/' . $images[0];
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $og_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        // Query Suggested / Related Products
        $suggested_products = [];
        try {
            $sug_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN (
                    SELECT product_id, MIN(id) as min_img_id 
                    FROM product_images 
                    GROUP BY product_id
                ) pim ON p.id = pim.product_id
                LEFT JOIN product_images pi ON pim.min_img_id = pi.id
                WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
                ORDER BY p.created_at DESC LIMIT 8");
            $sug_stmt->execute([$product['category_id'], $product_id]);
            $suggested_products = $sug_stmt->fetchAll();
            
            if (count($suggested_products) < 4) {
                $needed = 8 - count($suggested_products);
                $exclude_ids = array_merge([$product['id']], array_column($suggested_products, 'id'));
                $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
                
                $fallback_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
                    FROM products p 
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN (
                        SELECT product_id, MIN(id) as min_img_id 
                        FROM product_images 
                        GROUP BY product_id
                    ) pim ON p.id = pim.product_id
                    LEFT JOIN product_images pi ON pim.min_img_id = pi.id
                    WHERE p.status = 'active' AND p.id NOT IN ($placeholders)
                    ORDER BY p.created_at DESC LIMIT " . (int)$needed);
                
                $fallback_stmt->execute($exclude_ids);
                $suggested_products = array_merge($suggested_products, $fallback_stmt->fetchAll());
            }
        } catch (Exception $ex) {
            $suggested_products = [];
        }
    }

} catch (PDOException $e) {
    $product = null;
}

if ($product) {
    require_once 'includes/helpers.php';
    $metrics = getUserTrustMetrics($product['user_id']);
}

require_once 'includes/header.php';
?>
<link rel="stylesheet" href="assets/css/product.css?v=1.2">

<div class="product-page-premium">
    <?php if ($product): ?>
        <div class="container">
            <?php if ($product['status'] !== 'active'): ?>
                <div class="status-warning-banner-premium">
                    <div class="banner-icon">
                        <i class="fa <?= ($product['status'] == 'deleted' ? 'fa-trash-alt' : 'fa-info-circle') ?>"></i>
                    </div>
                    <div class="banner-text">
                        <h3>Listing <?= ucfirst($product['status']) ?></h3>
                        <p>This advertisement is no longer live. Only you can see this page.</p>
                    </div>
                    <?php if ($is_owner): ?>
                        <a href="user/my_ads.php" class="btn-banner-action">Back to Dashboard</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Breadcrumbs -->
            <div class="premium-breadcrumbs">
                <a href="index.php">Home</a> <i class="fa fa-chevron-right"></i>
                <a
                    href="category.php?id=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name'] ?? 'General') ?></a>
                <i class="fa fa-chevron-right"></i>
                <span><?= htmlspecialchars($product['title']) ?></span>
            </div>

            <div class="product-main-layout">
                <!-- Left: Media Section -->
                <div class="product-media-section">
                    <div class="main-gallery-wrapper">
                        <?php if (!empty($images)): ?>
                            <div class="badge-type status-<?= $product['type'] ?>">
                                <?= $product['type'] == 'buy' ? 'Wanted' : ($product['type'] == 'rent' ? 'For Rent' : 'For Sale') ?>
                            </div>
                            <div class="image-carousel-premium" id="mainCarousel" onscroll="updateSliderCounter(this)">
                                <?php foreach ($images as $img): ?>
                                    <div class="carousel-item-premium">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['title']) ?>"
                                            onclick="openLightbox()">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($images) > 1): ?>
                                <div class="carousel-counter-premium">
                                    <span id="currentImg">1</span> / <?= count($images) ?>
                                </div>
                                <div class="carousel-nav-btns">
                                    <button onclick="scrollGallery(-1)"><i class="fa fa-chevron-left"></i></button>
                                    <button onclick="scrollGallery(1)"><i class="fa fa-chevron-right"></i></button>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-image-placeholder">
                                <i class="fa fa-image"></i>
                                <p>No images available</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Thumbnails -->
                    <?php if (count($images) > 1): ?>
                        <div class="thumbnail-grid-premium">
                            <?php foreach ($images as $index => $img): ?>
                                <div class="thumb-item" onclick="jumpToImage(<?= $index ?>)">
                                    <img src="<?= htmlspecialchars($img) ?>" alt="Thumb">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Right: Information Section -->
                <div class="product-info-section">
                    <div class="sticky-info-card">
                        <div class="price-header-premium">
                            <div class="price-box">
                                <span class="price-label"><?= $product['type'] == 'buy' ? 'Budget' : ($product['type'] == 'rent' ? 'Rent Price' : 'Price') ?></span>
                                <h2 class="price-value">₹ <?= number_format($product['price'], 0) ?></h2>
                            </div>
                            <div class="info-actions">
                                <button class="action-circle-btn wishlist <?= $is_wishlisted ? 'active' : '' ?>"
                                    onclick="toggleWishlist(event, <?= $product['id'] ?>)">
                                    <i class="fa<?= $is_wishlisted ? 's' : 'r' ?> fa-heart"></i>
                                </button>
                                <button class="action-circle-btn share" onclick="shareProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['title'], ENT_QUOTES) ?>')">
                                    <i class="fa fa-share-alt"></i>
                                </button>
                            </div>
                        </div>

                        <h1 class="product-title-premium"><?= htmlspecialchars($product['title']) ?></h1>

                        <div class="meta-tags-premium">
                            <?php
                            $maps_url = 'https://www.google.com/maps/search/?api=1&query=';
                            if (!empty($product['latitude']) && !empty($product['longitude'])) {
                                $maps_url .= urlencode($product['latitude'] . ',' . $product['longitude']);
                            } else {
                                $maps_url .= urlencode($product['location_name'] ?? 'Kerala');
                            }
                            ?>
                            <a href="<?= $maps_url ?>" target="_blank" class="meta-tag location-interactive" style="text-decoration: none; cursor: pointer; color: var(--text-muted);" title="Open in Google Maps">
                                <i class="fa fa-map-marker-alt"></i>
                                <?= htmlspecialchars($product['location_name'] ?? 'Kerala') ?>
                            </a>
                            <span class="meta-tag"><i class="fa fa-calendar-alt"></i>
                                <?= date('M d, Y', strtotime($product['created_at'])) ?></span>
                            <span class="meta-tag"><i class="fa fa-eye"></i> <?= number_format($product['views']) ?>
                                views</span>
                        </div>

                        <?php if ($product['is_verified']): ?>
                            <div class="verified-banner-premium">
                                <div class="v-icon"><i class="fa fa-check-double"></i></div>
                                <div class="v-text">
                                    <strong>Verified Listing</strong>
                                    <span>This ad has been verified by our team.</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Seller Section -->
                        <div class="seller-card-premium">
                            <a href="user/view_profile.php?id=<?= $product['user_id'] ?>" class="seller-info-top" style="text-decoration: none; display: flex; align-items: center; gap: 16px; color: inherit;">
                                <?php if (!empty($product['profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($product['profile_picture']) ?>" alt="Seller"
                                        class="seller-avatar">
                                <?php else: ?>
                                    <div class="seller-avatar-placeholder"><?= strtoupper(substr($product['username'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="seller-names" style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <h4 style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text-dark);"><?= htmlspecialchars($product['username']) ?></h4>
                                        <?php if ($metrics['phone_verified'] || $metrics['email_verified']): ?>
                                            <i class="fa fa-check-circle" style="color: #22c55e; font-size: 14px;" title="Verified Seller"></i>
                                        <?php endif; ?>
                                    </div>
                                    <p style="margin: 3px 0 0 0; font-size: 12px; color: var(--text-muted);">Member since <?= htmlspecialchars(date('Y', strtotime($product['created_at']))) ?></p>
                                    
                                    <!-- Star Ratings -->
                                    <div style="display: flex; align-items: center; gap: 4px; margin-top: 4px; color: #facc15; font-size: 11px;">
                                        <span>
                                            <?php
                                            $stars = round($metrics['avg_rating']);
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo $i <= $stars ? '<i class="fa fa-star"></i>' : '<i class="far fa-star"></i>';
                                            }
                                            ?>
                                        </span>
                                        <span style="color: var(--text-muted); font-size: 11px; font-weight: 600;">
                                            (<?= $metrics['review_count'] ?>)
                                        </span>
                                    </div>

                                    <!-- Responsiveness indicator -->
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($metrics['response_time']) ?>">
                                        <i class="fa fa-reply" style="font-size: 10px;"></i> <?= htmlspecialchars($metrics['response_time']) ?>
                                    </div>
                                </div>
                            </a>

                            <?php if (isset($_SESSION['user_id'])): ?>
                                <?php if ($_SESSION['user_id'] != $product['user_id']): ?>
                                    <div class="contact-btns-grid">
                                        <a href="user/chat.php?user_id=<?= $product['user_id'] ?>&product_id=<?= $product['id'] ?>"
                                            class="btn-chat-primary">
                                            <i class="fa fa-comment-dots"></i> Chat Now
                                        </a>
                                        <?php if (!empty($product['product_whatsapp'])): ?>
                                            <?php
                                            $first_img_url = '';
                                            if (!empty($images)) {
                                                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                                                $host = $_SERVER['HTTP_HOST'];
                                                $first_img_url = $protocol . $host . ($base_url ? $base_url : '') . '/' . $images[0];
                                            }

                                            $whatsapp_text = "Hello! I saw your listing on " . ($app_settings['app_name'] ?? 'Enteangadi') . ".\n\n"
                                                           . "Title: " . $product['title'] . "\n"
                                                           . "Price: ₹ " . number_format($product['price'], 0) . "\n"
                                                           . "Location: " . ($product['location_name'] ?? 'Kerala') . "\n";

                                            if ($first_img_url) {
                                                $whatsapp_text .= "Image: " . $first_img_url . "\n";
                                            }

                                            $whatsapp_text .= "\nIs it still available? I would love to make an offer.";
                                            $whatsapp_msg = urlencode($whatsapp_text);
                                            ?>
                                            <a href="https://wa.me/<?= preg_replace('/\D/', '', $product['product_whatsapp']) ?>?text=<?= $whatsapp_msg ?>"
                                                target="_blank" class="btn-whatsapp" title="Chat on WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($product['product_phone'])): ?>
                                            <a href="tel:<?= $product['product_phone'] ?>" class="btn-phone">
                                                <i class="fa fa-phone"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <a href="user/edit_ad.php?id=<?= $product['id'] ?>" class="btn-edit-ad">
                                        <i class="fa fa-edit"></i> Edit Your Ad
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="login-to-contact">
                                    <p>Login to contact the seller</p>
                                    <a href="login.php" class="btn-login-small">Login / Register</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Safety Section -->
                        <div class="safety-tips-premium">
                            <div class="safety-tips-card">
                                <h4><i class="fa fa-shield-alt"></i> Safety Tips</h4>
                                <ul>
                                    <li><i class="fa fa-check-circle"></i> Meet in a public place</li>
                                    <li><i class="fa fa-check-circle"></i> Check item before paying</li>
                                    <li><i class="fa fa-check-circle"></i> Don't pay in advance</li>
                                </ul>
                                <button onclick="reportProduct(<?= $product['id'] ?>)" class="btn-report-link">Report this
                                    ad</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description Section (Moved for better mobile flow) -->
                <div class="description-section-fullwidth">
                    <div class="description-card-premium">
                        <h3>Description</h3>
                        <div class="description-content">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </div>
                    </div>

                    <!-- Seller Reviews & Ratings -->
                    <div class="seller-reviews-card-premium" style="margin-top: 24px; padding: 24px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white); box-shadow: var(--shadow-sm);">
                        <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-star" style="color: #facc15;"></i> Seller Ratings & Reviews
                        </h3>
                        
                        <?php if (empty($reviews)): ?>
                            <p style="color: var(--text-muted); font-size: 13px; margin: 0 0 20px 0;">No reviews yet for this seller. Be the first one to rate them!</p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px;">
                                <?php foreach ($reviews as $rev): ?>
                                    <div style="display: flex; gap: 12px; align-items: flex-start; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
                                        <?php if (!empty($rev['profile_picture'])): ?>
                                            <img src="<?= htmlspecialchars($rev['profile_picture']) ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; color: #64748b;">
                                                <?= strtoupper(substr($rev['username'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                                <strong style="font-size: 13px; color: var(--text-dark);"><?= htmlspecialchars($rev['username']) ?></strong>
                                                <span style="font-size: 11px; color: var(--text-muted);"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                                            </div>
                                            <div style="color: #facc15; font-size: 11px; margin: 2px 0;">
                                                <?php for ($i = 1; $i <= 5; $i++) {
                                                    echo $i <= $rev['rating'] ? '<i class="fa fa-star"></i>' : '<i class="far fa-star"></i>';
                                                } ?>
                                            </div>
                                            <p style="margin: 4px 0 0 0; font-size: 13px; color: var(--text-dark); line-height: 1.4;"><?= htmlspecialchars($rev['comment']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Review Submission Form -->
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['user_id'] != $product['user_id']): ?>
                                <form action="" method="POST" style="border-top: 1.5px solid #f1f5f9; padding-top: 20px;">
                                    <input type="hidden" name="action" value="submit_rating">
                                    <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: var(--text-dark);">Rate this Seller</h4>
                                    <?php if (isset($rating_error)): ?>
                                        <div style="color: #ef4444; font-size: 12px; margin-bottom: 10px; font-weight: 600;"><?= $rating_error ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($_GET['rating_success'])): ?>
                                        <div style="color: #22c55e; font-size: 12px; margin-bottom: 10px; font-weight: 600;">Rating submitted successfully!</div>
                                    <?php endif; ?>
                                    <div style="display: flex; gap: 8px; font-size: 20px; color: #cbd5e1; cursor: pointer; margin-bottom: 12px;" id="star-rating-selector">
                                        <i class="far fa-star" data-value="1" onclick="setFormRating(1)"></i>
                                        <i class="far fa-star" data-value="2" onclick="setFormRating(2)"></i>
                                        <i class="far fa-star" data-value="3" onclick="setFormRating(3)"></i>
                                        <i class="far fa-star" data-value="4" onclick="setFormRating(4)"></i>
                                        <i class="far fa-star" data-value="5" onclick="setFormRating(5)"></i>
                                    </div>
                                    <input type="hidden" name="rating" id="selected-rating" value="0">
                                    <textarea name="comment" placeholder="Write a review about your experience with this seller..." style="width: 100%; height: 85px; padding: 12px; border-radius: 12px; border: 1px solid var(--border-color); outline: none; font-size: 13px; font-family: inherit; box-sizing: border-box; resize: none; margin-bottom: 12px;" required></textarea>
                                    <button type="submit" class="btn-primary" style="padding: 10px 20px; font-size: 13px; font-weight: 700; border-radius: 12px;">Submit Review</button>
                                </form>
                                <script>
                                    function setFormRating(val) {
                                        document.getElementById('selected-rating').value = val;
                                        const stars = document.querySelectorAll('#star-rating-selector i');
                                        stars.forEach((star, index) => {
                                            if (index < val) {
                                                star.className = 'fas fa-star';
                                                star.style.color = '#facc15';
                                            } else {
                                                star.className = 'far fa-star';
                                                star.style.color = '#cbd5e1';
                                            }
                                        });
                                    }
                                </script>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="background: #f8fafc; padding: 16px; border-radius: 12px; text-align: center; border: 1px solid #e2e8f0; margin-top: 16px;">
                                <span style="font-size: 13px; color: var(--text-muted); display: block; margin-bottom: 8px;">Please login to write a review for this seller.</span>
                                <a href="login.php" class="btn-primary" style="display: inline-block; text-decoration: none; padding: 6px 16px; font-size: 12px; font-weight: 700; border-radius: 8px;">Login</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Suggested Products Section -->
            <?php if (!empty($suggested_products)): ?>
                <div style="margin-top: 40px; margin-bottom: 32px;">
                    <h3 style="color: var(--text-dark, #1e293b); font-size: 20px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-sparkles" style="color: var(--primary-green, #1B5E20);"></i> Suggested Products
                    </h3>
                    <div class="suggested-scroll-container" style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; scrollbar-width: none; -ms-overflow-style: none; scroll-behavior: smooth;">
                        <?php foreach ($suggested_products as $sug_p): 
                            $detailUrl = "product.php?id=" . $sug_p['id'];
                            $imgUrl = $sug_p['main_image'] ? htmlspecialchars($sug_p['main_image']) : null;
                        ?>
                            <a href="<?= $detailUrl ?>" class="suggested-product-card" style="text-decoration: none; flex: 0 0 auto; width: 160px; background: var(--white, #fff); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color, #e2e8f0); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; transition: all 0.3s ease;">
                                <!-- Image Area -->
                                <div style="position: relative; width: 100%; height: 110px; background: #f1f5f9;">
                                    <?php if ($sug_p['type'] == 'buy'): ?>
                                        <div class="badge-wanted" style="font-size: 7px; top: 6px; left: 6px; padding: 2px 6px;">Wanted</div>
                                    <?php elseif ($sug_p['type'] == 'rent'): ?>
                                        <div class="badge-rent" style="font-size: 7px; top: 6px; left: 6px; padding: 2px 6px;">Rent</div>
                                    <?php else: ?>
                                        <div class="badge-selling" style="font-size: 7px; top: 6px; left: 6px; padding: 2px 6px;">Sale</div>
                                    <?php endif; ?>
                                    <?php if ($imgUrl): ?>
                                        <img src="<?= $imgUrl ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa fa-image" style="font-size: 24px; color: #cbd5e1;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Info Area -->
                                <div style="padding: 10px; display: flex; flex-direction: column; flex: 1; min-width: 0;">
                                    <div style="font-size: 14px; font-weight: 800; color: var(--primary-green-dark, #1b5e20); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;">
                                        ₹<?= number_format($sug_p['price'], (fmod($sug_p['price'], 1) == 0) ? 0 : 2) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-dark, #1e293b); font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 32px; line-height: 1.3;">
                                        <?= htmlspecialchars($sug_p['title']) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <style>
                .suggested-scroll-container::-webkit-scrollbar {
                    display: none;
                }
                .suggested-product-card:hover {
                    transform: translateY(-2px);
                    box-shadow: var(--shadow-md, 0 10px 15px -3px rgba(0, 0, 0, 0.1));
                    border-color: var(--primary-green, #1B5E20) !important;
                }
                </style>
            <?php endif; ?>
        </div>

        <!-- Lightbox -->
        <div id="lightbox" class="lightbox-overlay" onclick="closeLightbox()">
            <span class="lightbox-close">&times;</span>
            <div class="lightbox-content" onclick="event.stopPropagation()">
                <div class="lightbox-gallery" id="lightboxGallery" onscroll="updateLightboxCounter(this)">
                    <?php foreach ($images as $img): ?>
                        <div class="lightbox-item">
                            <img src="<?= htmlspecialchars($img) ?>" alt="Gallery Image">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="lightbox-counter">
                        <span id="lbCurrent">1</span> / <?= count($images) ?>
                    </div>
                    <div class="lightbox-nav">
                        <button onclick="scrollLightbox(-1)"><i class="fa fa-chevron-left"></i></button>
                        <button onclick="scrollLightbox(1)"><i class="fa fa-chevron-right"></i></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="container empty-state-premium">
            <i class="fa fa-search"></i>
            <h2>Product not found</h2>
            <a href="index.php">Back to Home</a>
        </div>
    <?php endif; ?>
</div>


<?php
// End of file logic
?>

<!-- Fallback Premium Custom Share Modal -->
<div id="customShareModal" class="custom-share-backdrop" onclick="closeCustomShare()">
    <div class="custom-share-card" onclick="event.stopPropagation()">
        <div class="custom-share-header">
            <h3>Share link</h3>
            <button class="custom-share-close" onclick="closeCustomShare()">&times;</button>
        </div>
        
        <!-- Product Mini Card Preview -->
        <div class="custom-share-preview-card">
            <img src="<?= !empty($images) ? htmlspecialchars($images[0], ENT_QUOTES) : 'assets/images/placeholder.jpg' ?>" alt="Product Image">
            <div class="custom-share-preview-info">
                <h4 class="custom-share-preview-title"><?= htmlspecialchars($product['title'], ENT_QUOTES) ?></h4>
                <p class="custom-share-preview-price">₹ <?= number_format($product['price'], 0) ?></p>
            </div>
        </div>

        <div class="custom-share-section-title">Share using</div>
        <div class="custom-share-grid">
            <a href="#" id="share-whatsapp" target="_blank" class="custom-share-item whatsapp">
                <div class="custom-share-icon"><i class="fab fa-whatsapp"></i></div>
                <span>WhatsApp</span>
            </a>
            <a href="#" id="share-telegram" target="_blank" class="custom-share-item telegram">
                <div class="custom-share-icon"><i class="fab fa-telegram-plane"></i></div>
                <span>Telegram</span>
            </a>
            <a href="#" id="share-facebook" target="_blank" class="custom-share-item facebook">
                <div class="custom-share-icon"><i class="fab fa-facebook-f"></i></div>
                <span>Facebook</span>
            </a>
            <a href="#" id="share-twitter" target="_blank" class="custom-share-item twitter">
                <div class="custom-share-icon"><i class="fab fa-x-twitter"></i></div>
                <span>Twitter</span>
            </a>
            <a href="#" id="share-linkedin" target="_blank" class="custom-share-item linkedin">
                <div class="custom-share-icon"><i class="fab fa-linkedin-in"></i></div>
                <span>LinkedIn</span>
            </a>
            <a href="#" id="share-email" target="_blank" class="custom-share-item email">
                <div class="custom-share-icon"><i class="fas fa-envelope"></i></div>
                <span>Email</span>
            </a>
            <button onclick="copyShareLink()" class="custom-share-item copy-link">
                <div class="custom-share-icon"><i class="fas fa-link"></i></div>
                <span>Copy Link</span>
            </button>
        </div>
    </div>
</div>

<!-- App Redirect Modal for Android users landing from Mobile App Shares -->
<div id="appRedirectModal" class="app-redirect-backdrop">
    <div class="app-redirect-card">
        <div class="app-redirect-logo">
            <?php if (!empty($app_settings['app_logo'])): ?>
                <img src="<?= htmlspecialchars($app_settings['app_logo'], ENT_QUOTES) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 16px;">
            <?php else: ?>
                <i class="fa fa-shopping-bag"></i>
            <?php endif; ?>
        </div>
        <h3>Open in <?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?></h3>
        <p>View this product inside the <?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?> mobile application for a better experience!</p>
        <div class="app-redirect-btns">
            <a href="#" id="btn-redirect-install" class="btn-app-install" target="_blank">
                <i class="fab fa-google-play"></i> Install App
            </a>
            <button class="btn-app-continue" onclick="closeRedirectModal()">
                Continue on website
            </button>
        </div>
    </div>
</div>

<script>
    const imgCount = <?= count($images) ?>;
    const sessionUserId = <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>;
    const playStoreUrl = '<?= !empty($app_settings['play_store_url']) ? htmlspecialchars($app_settings['play_store_url'], ENT_QUOTES) : 'https://play.google.com/store/apps/details?id=com.enteangadi.app' ?>';
    const appStoreUrl = '<?= !empty($app_settings['app_store_url']) ? htmlspecialchars($app_settings['app_store_url'], ENT_QUOTES) : 'https://apps.apple.com/app/enteangadi' ?>';
</script>
<script src="assets/js/product.js?v=<?= time() ?>"></script>

<?php require_once 'includes/footer.php'; ?>