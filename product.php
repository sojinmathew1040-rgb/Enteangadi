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

} catch (PDOException $e) {
    $product = null;
}

require_once 'includes/header.php';
?>

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
                                <?= $product['type'] == 'buy' ? 'Wanted' : 'For Sale' ?>
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
                                <span class="price-label"><?= $product['type'] == 'buy' ? 'Budget' : 'Price' ?></span>
                                <h2 class="price-value">₹ <?= number_format($product['price'], 0) ?></h2>
                            </div>
                            <div class="info-actions">
                                <button class="action-circle-btn wishlist <?= $is_wishlisted ? 'active' : '' ?>"
                                    onclick="toggleWishlist(event, <?= $product['id'] ?>)">
                                    <i class="fa<?= $is_wishlisted ? 's' : 'r' ?> fa-heart"></i>
                                </button>
                                <button class="action-circle-btn share" onclick="shareProduct()">
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
                                            <a href="https://wa.me/<?= preg_replace('/\D/', '', $product['product_whatsapp']) ?>"
                                                target="_blank" class="btn-whatsapp">
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

<style>
    .product-page-premium {
        background: var(--background);
        padding: 30px 0 100px;
        min-height: 100vh;
    }

    .premium-breadcrumbs {
        margin-bottom: 24px;
        font-size: 13px;
        color: var(--text-muted);
    }

    .premium-breadcrumbs a {
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 500;
    }

    .premium-breadcrumbs i {
        font-size: 10px;
        margin: 0 8px;
        opacity: 0.5;
    }

    .product-main-layout {
        display: grid;
        grid-template-columns: 1.6fr 1fr;
        gap: 40px;
        align-items: start;
    }

    /* Media Section */
    .main-gallery-wrapper {
        position: relative;
        background: white;
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--shadow-md);
        aspect-ratio: 4/3;
    }

    .image-carousel-premium {
        display: flex;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        height: 100%;
        scrollbar-width: none;
    }

    .image-carousel-premium::-webkit-scrollbar {
        display: none;
    }

    .carousel-item-premium {
        flex: 0 0 100%;
        height: 100%;
        scroll-snap-align: start;
    }

    .carousel-item-premium img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        cursor: zoom-in;
        background: #f8fafc;
    }

    .badge-type {
        position: absolute;
        top: 20px;
        left: 20px;
        padding: 8px 20px;
        border-radius: 12px;
        color: white;
        font-weight: 800;
        font-size: 12px;
        z-index: 10;
        box-shadow: var(--shadow-md);
    }

    .badge-type.status-buy {
        background: linear-gradient(135deg, #FFB300 0%, #FF8F00 100%);
    }

    .badge-type.status-sell {
        background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
    }

    .carousel-counter-premium {
        position: absolute;
        bottom: 20px;
        right: 20px;
        background: rgba(15, 23, 42, 0.7);
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        backdrop-filter: blur(4px);
    }

    .carousel-nav-btns {
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
        display: flex;
        justify-content: space-between;
        padding: 0 15px;
        pointer-events: none;
    }

    .carousel-nav-btns button {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: white;
        border: none;
        box-shadow: var(--shadow-md);
        cursor: pointer;
        pointer-events: auto;
        transition: all 0.2s;
    }

    .carousel-nav-btns button:hover {
        transform: scale(1.1);
        background: var(--primary-green);
        color: white;
    }

    .thumbnail-grid-premium {
        display: flex;
        gap: 12px;
        margin-top: 15px;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .thumb-item {
        width: 80px;
        height: 80px;
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid transparent;
        flex-shrink: 0;
        transition: all 0.2s;
    }

    .thumb-item:hover {
        border-color: var(--primary-green);
    }

    .thumb-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .description-card-premium {
        background: white;
        border-radius: var(--border-radius);
        padding: 32px;
        margin-top: 40px;
        box-shadow: var(--shadow-sm);
    }

    .description-card-premium h3 {
        font-size: 20px;
        font-weight: 800;
        margin-bottom: 20px;
        color: var(--text-dark);
    }

    .description-content {
        color: var(--text-muted);
        line-height: 1.8;
        font-size: 16px;
    }

    /* Sticky Info Card */
    .sticky-info-card {
        position: sticky;
        top: 100px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 32px;
        padding: 40px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
    }

    .price-header-premium {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
    }

    .price-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 2px;
        margin-bottom: 4px;
        display: block;
    }

    .price-value {
        font-size: 42px;
        font-weight: 900;
        color: var(--primary-green-dark);
        margin: 0;
        letter-spacing: -1.5px;
    }

    .info-actions {
        display: flex;
        gap: 12px;
    }

    .action-circle-btn {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 1px solid var(--border-color);
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .action-circle-btn.wishlist.active {
        color: var(--primary-green);
        border-color: var(--primary-green);
        background: #f0fdf4;
    }

    .action-circle-btn:hover {
        transform: scale(1.1);
        box-shadow: var(--shadow-sm);
    }

    .product-title-premium {
        font-size: 24px;
        font-weight: 800;
        color: var(--text-dark);
        margin-bottom: 16px;
        line-height: 1.4;
    }

    .meta-tags-premium {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 30px;
    }

    .meta-tag {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        background: #f1f5f9;
        padding: 8px 14px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        transition: all 0.2s;
    }

    .meta-tag:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }

    .meta-tag i {
        color: var(--primary-green);
        margin-right: 6px;
    }

    .verified-banner-premium {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        padding: 16px;
        border-radius: 16px;
        display: flex;
        gap: 16px;
        margin-bottom: 30px;
    }

    .v-icon {
        font-size: 24px;
        color: #1d4ed8;
    }

    .v-text strong {
        display: block;
        font-size: 15px;
        color: #1e3a8a;
    }

    .v-text span {
        font-size: 13px;
        color: #3b82f6;
    }

    /* Seller Card */
    .seller-card-premium {
        background: #f8fafc;
        border-radius: 20px;
        padding: 24px;
        margin-bottom: 30px;
    }

    .seller-info-top {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
    }

    .seller-avatar,
    .seller-avatar-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        background: var(--primary-green);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 800;
    }

    .seller-names h4 {
        font-size: 18px;
        font-weight: 800;
        margin: 0;
    }

    .seller-names p {
        font-size: 13px;
        color: var(--text-muted);
        margin: 4px 0 0 0;
    }

    .contact-btns-grid {
        display: flex;
        gap: 10px;
    }

    .btn-chat-primary {
        flex: 1;
        background: var(--primary-green);
        color: white !important;
        text-decoration: none;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-weight: 700;
        gap: 10px;
        transition: all 0.3s;
    }

    .btn-chat-primary:hover {
        background: var(--primary-green-dark);
        transform: translateY(-3px);
    }

    .btn-edit-ad {
        width: 100%;
        background: #f1f5f9;
        color: var(--text-dark) !important;
        text-decoration: none;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-weight: 800;
        gap: 10px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s;
    }

    .btn-edit-ad:hover {
        background: #e2e8f0;
        transform: translateY(-2px);
    }

    .btn-whatsapp,
    .btn-phone {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white !important;
        text-decoration: none;
    }

    .btn-whatsapp {
        background: #25D366;
    }

    .btn-phone {
        background: #0F172A;
    }

    .safety-tips-premium {
        border-top: 1px solid #f1f5f9;
        padding-top: 24px;
    }

    .safety-tips-card {
        background: #fff9f0;
        border: 1px solid #ffedd5;
        border-radius: 16px;
        padding: 20px;
    }

    .safety-tips-card h4 {
        font-size: 14px;
        font-weight: 800;
        margin-bottom: 12px;
        color: #9a3412;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .safety-tips-card ul {
        list-style: none;
        padding: 0;
        margin: 0 0 16px 0;
    }

    .safety-tips-card li {
        font-size: 13px;
        color: #c2410c;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .safety-tips-card li i {
        font-size: 12px;
        opacity: 0.7;
    }

    .btn-report-link {
        background: none;
        border: none;
        color: #ef4444;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
    }

    .description-section-fullwidth {
        grid-column: 1 / -1;
    }

    @media (max-width: 992px) {
        .product-main-layout {
            grid-template-columns: 1fr;
        }

        .sticky-info-card {
            position: relative;
            top: 0;
        }

        .description-section-fullwidth {
            order: 3;
        }

        .product-media-section {
            order: 1;
        }

        .product-info-section {
            order: 2;
        }
    }

    @media (max-width: 768px) {
        .main-gallery-wrapper {
            border-radius: 0;
            margin-left: -20px;
            margin-right: -20px;
            width: calc(100% + 40px);
        }

        .product-page-premium {
            padding-top: 0;
        }

        .premium-breadcrumbs {
            display: none;
        }

        .price-value {
            font-size: 28px;
        }

        .description-card-premium {
            padding: 24px;
            margin-top: 20px;
        }
    }

    /* Lightbox Premium */
    .lightbox-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.98);
        z-index: 20000;
        display: none;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
    }

    .lightbox-content {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .lightbox-gallery {
        display: flex;
        width: 100%;
        height: 100%;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
    }

    .lightbox-gallery::-webkit-scrollbar {
        display: none;
    }

    .lightbox-item {
        flex: 0 0 100%;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        scroll-snap-align: start;
        padding: 40px;
    }

    .lightbox-item img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    }

    .lightbox-close {
        position: absolute;
        top: 30px;
        right: 30px;
        color: white;
        font-size: 32px;
        cursor: pointer;
        z-index: 20002;
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s;
    }

    .lightbox-close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: rotate(90deg);
    }

    .lightbox-nav {
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        transform: translateY(-50%);
        display: flex;
        justify-content: space-between;
        padding: 0 30px;
        pointer-events: none;
        z-index: 20001;
    }

    .lightbox-nav button {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        font-size: 20px;
        cursor: pointer;
        pointer-events: auto;
        transition: all 0.3s;
        backdrop-filter: blur(5px);
    }

    .lightbox-nav button:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
    }

    .lightbox-counter {
        position: absolute;
        bottom: 40px;
        left: 50%;
        transform: translateX(-50%);
        color: white;
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 600;
        backdrop-filter: blur(5px);
        font-size: 14px;
        z-index: 20001;
    }

    .status-warning-banner-premium {
        background: #fef2f2;
        border: 1px solid #fee2e2;
        border-radius: 20px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
        animation: fadeInDown 0.5s ease;
    }

    .status-warning-banner-premium .banner-icon {
        width: 50px;
        height: 50px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .status-warning-banner-premium .banner-text h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: #991b1b;
    }

    .status-warning-banner-premium .banner-text p {
        margin: 4px 0 0 0;
        color: #b91c1c;
        font-weight: 500;
        font-size: 14px;
    }

    .btn-banner-action {
        margin-left: auto;
        padding: 10px 20px;
        background: white;
        border: 1px solid #fee2e2;
        border-radius: 12px;
        text-decoration: none;
        color: #991b1b;
        font-weight: 700;
        font-size: 13px;
        transition: all 0.3s;
    }

    .btn-banner-action:hover {
        background: #ef4444;
        color: white;
        border-color: #ef4444;
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<script>
    let currentIdx = 0;
    const imgCount = <?= count($images) ?>;
    const images = <?= json_encode($images) ?>;

    function updateSliderCounter(el) {
        currentIdx = Math.round(el.scrollLeft / el.clientWidth);
        document.getElementById('currentImg').innerText = currentIdx + 1;
    }

    function scrollGallery(dir) {
        const slider = document.getElementById('mainCarousel');
        currentIdx = Math.max(0, Math.min(imgCount - 1, currentIdx + dir));
        slider.scrollTo({ left: currentIdx * slider.clientWidth, behavior: 'smooth' });
    }

    function jumpToImage(idx) {
        const slider = document.getElementById('mainCarousel');
        currentIdx = idx;
        slider.scrollTo({ left: idx * slider.clientWidth, behavior: 'smooth' });
    }

    function openLightbox() {
        const modal = document.getElementById('lightbox');
        const gallery = document.getElementById('lightboxGallery');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Native Fullscreen API
        if (modal.requestFullscreen) {
            modal.requestFullscreen();
        } else if (modal.webkitRequestFullscreen) {
            modal.webkitRequestFullscreen();
        } else if (modal.msRequestFullscreen) {
            modal.msRequestFullscreen();
        }

        // Jump to the current image in the lightbox
        setTimeout(() => {
            gallery.scrollTo({ left: currentIdx * gallery.clientWidth, behavior: 'instant' });
            document.getElementById('lbCurrent').innerText = currentIdx + 1;
        }, 10);
    }

    function closeLightbox() {
        const modal = document.getElementById('lightbox');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';

        // Exit Fullscreen
        if (document.fullscreenElement) {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
    }

    function updateLightboxCounter(el) {
        const idx = Math.round(el.scrollLeft / el.clientWidth);
        document.getElementById('lbCurrent').innerText = idx + 1;
    }

    function scrollLightbox(dir) {
        const gallery = document.getElementById('lightboxGallery');
        const idx = Math.round(gallery.scrollLeft / gallery.clientWidth);
        const nextIdx = Math.max(0, Math.min(imgCount - 1, idx + dir));
        gallery.scrollTo({ left: nextIdx * gallery.clientWidth, behavior: 'smooth' });
    }

    async function toggleWishlist(event, productId) {
        event.preventDefault();
        <?php if (!isset($_SESSION['user_id'])): ?>
            window.location.href = 'login.php';
            return;
        <?php endif; ?>

        const btn = event.currentTarget;
        const icon = btn.querySelector('i');

        try {
            const formData = new FormData();
            formData.append('product_id', productId);
            const resp = await fetch('user/toggle_wishlist.php', { method: 'POST', body: formData });
            const data = await resp.json();

            if (data.status === 'success') {
                if (data.action === 'added') {
                    icon.classList.replace('far', 'fas');
                    btn.classList.add('active');
                } else {
                    icon.classList.replace('fas', 'far');
                    btn.classList.remove('active');
                }
            }
        } catch (e) { console.error(e); }
    }

    function shareProduct() {
        if (navigator.share) {
            navigator.share({
                title: document.title,
                url: window.location.href
            });
        } else {
            const dummy = document.createElement('input');
            document.body.appendChild(dummy);
            dummy.value = window.location.href;
            dummy.select();
            document.execCommand('copy');
            document.body.removeChild(dummy);
            alert('Link copied to clipboard!');
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>