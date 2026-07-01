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

    // [AD ANALYTICS] Increment View Count
    $update_views = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $update_views->execute([$product_id]);

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
    }

} catch (PDOException $e) {
    $product = null;
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
                            <span class="meta-tag"><i class="fa fa-map-marker-alt"></i>
                                <?= htmlspecialchars($product['location_name'] ?? 'Kerala') ?></span>
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
                            <div class="seller-info-top">
                                <?php if (!empty($product['profile_picture'])): ?>
                                    <img src="<?= htmlspecialchars($product['profile_picture']) ?>" alt="Seller"
                                        class="seller-avatar">
                                <?php else: ?>
                                    <div class="seller-avatar-placeholder"><?= strtoupper(substr($product['username'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="seller-names">
                                    <h4><?= htmlspecialchars($product['username']) ?></h4>
                                    <p>Member since <?= date('Y', strtotime($product['created_at'])) ?></p>
                                </div>
                            </div>

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
                </div>
            </div>
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
<script src="assets/js/product.js"></script>

<?php require_once 'includes/footer.php'; ?>