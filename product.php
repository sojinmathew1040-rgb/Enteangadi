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


<?php
// End of file logic
?>

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