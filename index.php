<?php
require_once 'config.php';
require_once 'includes/header.php';
echo "<!-- DEBUG: Poster Path in DB: " . ($app_settings['announcement_poster'] ?? 'NOT_SET') . " -->";
if (isset($_GET['debug_poster']))
    echo "POSTER PATH: " . ($app_settings['announcement_poster'] ?? 'NOT SET');

$category_filter = $_GET['category_id'] ?? null;
$search_query = $_GET['search'] ?? null;
$user_location = $_SESSION['user_location'] ?? null;

$where_clause = "WHERE p.status = 'active'";
$params = [];

if ($category_filter) {
    $where_clause .= " AND (p.category_id = ? OR c.parent_id = ?)";
    $params[] = $category_filter;
    $params[] = $category_filter;
}

if ($search_query) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$distance_select = "";
$order_by = "p.created_at DESC";

if ($user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = $user_location['lat'];
    $lng = $user_location['lng'];
    // Haversine formula for distance in KM
    $distance_select = ", (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) AS distance";
    $order_by = "p.created_at DESC, distance ASC";
}

// Fetch products
try {
    $sql = "SELECT p.*, c.name as category_name, 
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image 
            $distance_select
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            $where_clause 
            ORDER BY $order_by";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Category Display Logic
$stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
$l1_categories = $stmt->fetchAll();

$l2_categories = [];
$parent_id = null;

if ($category_filter) {
    $cat_stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_filter]);
    $cat_info = $cat_stmt->fetch();

    if ($cat_info) {
        if ($cat_info['parent_id'] === null) {
            $parent_id = $category_filter;
        } else {
            $parent_id = $cat_info['parent_id'];
        }
        $child_stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
        $child_stmt->execute([$parent_id]);
        $l2_categories = $child_stmt->fetchAll();
    }
}

// Fetch user's wishlist
$user_wishlist = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<div class="container">
    <!-- Browse Categories section -->
    <div class="category-section">
        <div class="category-header">
            <h2 class="section-title-premium"><?= __('browse_categories') ?></h2>
            <?php if ($category_filter): ?>
                <a href="index.php" class="btn-clear-filter">Clear Filter</a>
            <?php endif; ?>
        </div>
        <div class="category-scroll-container">
            <?php foreach ($l1_categories as $cat): ?>
                <a href="index.php?category_id=<?= $cat['id'] ?>" class="category-item">
                    <?php if ($cat['photo_path']): ?>
                        <img src="<?= htmlspecialchars($cat['photo_path']) ?>"
                            class="category-item-img <?= ($parent_id == $cat['id'] || $category_filter == $cat['id']) ? 'active' : '' ?>">
                    <?php else: ?>
                        <div
                            class="category-item-placeholder <?= ($parent_id == $cat['id'] || $category_filter == $cat['id']) ? 'active' : '' ?>">
                            <i class="fa fa-th-large category-placeholder-icon"></i>
                        </div>
                    <?php endif; ?>
                    <span
                        class="category-item-text <?= ($parent_id == $cat['id'] || $category_filter == $cat['id']) ? 'active' : '' ?>"><?= htmlspecialchars($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($l2_categories)): ?>
            <div class="category-l2-container">
                <?php foreach ($l2_categories as $cat): ?>
                    <a href="index.php?category_id=<?= $cat['id'] ?>"
                        class="category-l2-item <?= ($category_filter == $cat['id']) ? 'active' : '' ?>">
                        <?php if ($cat['photo_path']): ?>
                            <img src="<?= htmlspecialchars($cat['photo_path']) ?>" class="category-l2-img">
                        <?php endif; ?>
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="category-header">
        <h2 class="section-title-premium"><?= __('fresh_recommendations') ?></h2>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state-card">
            <i class="fa fa-box-open empty-state-icon"></i>
            <h3 class="empty-state-title"><?= __('no_products') ?></h3>
            <p class="empty-state-subtitle"><?= __('be_the_first') ?></p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user/post_ad.php" class="btn-primary mt-16"><?= __('post_an_ad') ?></a>
            <?php else: ?>
                <a href="login.php" class="btn-primary mt-16"><?= __('login_to_post') ?></a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <a href="product.php?id=<?= $product['id'] ?>" class="product-card">
                    <?php if ($product['type'] == 'buy'): ?>
                        <div class="badge-wanted"><?= __('wanted') ?></div>
                    <?php else: ?>
                        <div class="badge-selling"><?= __('for_sale') ?></div>
                        <?php if (!empty($product['expiry_date'])): ?>
                            <?php
                            $expiry_date = strtotime($product['expiry_date']);
                            $today = strtotime(date('Y-m-d'));
                            $days_left = round(($expiry_date - $today) / (60 * 60 * 24));

                            if ($days_left >= 0 && $days_left <= 2):
                                ?>
                                <div class="badge-expiring">
                                    <i class="fa fa-clock"></i>
                                    <?= ($days_left == 0) ? __('expires_today') : __('expiring_soon') ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($product['main_image']): ?>
                        <img src="<?= htmlspecialchars($product['main_image']) ?>" class="product-card-image">
                    <?php else: ?>
                        <div class="product-card-image placeholder-bg">
                            <i class="fa fa-image placeholder-icon"></i>
                        </div>
                    <?php endif; ?>

                    <div class="wishlist-icon-btn" onclick="toggleWishlist(event, <?= $product['id'] ?>)">
                        <i
                            class="fa<?= in_array($product['id'], $user_wishlist) ? 's' : 'r' ?> fa-heart <?= in_array($product['id'], $user_wishlist) ? 'active' : '' ?>"></i>
                    </div>

                    <div class="product-card-content">
                        <div class="product-card-price">
                            ₹<?= number_format($product['price'], (fmod($product['price'], 1) == 0) ? 0 : 2) ?></div>
                        <div class="product-card-title">
                            <?= htmlspecialchars($product['title']) ?>
                            <?php if ($product['is_verified']): ?>
                                <span class="verified-badge" title="Verified Listing"><i class="fas fa-check-circle"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-meta">
                            <span><i class="fa fa-map-marker-alt meta-icon-sm"></i>
                                <?= htmlspecialchars($product['location_name'] ?? 'Unknown') ?></span>
                            <?php if (isset($product['distance'])): ?>
                                <span class="distance-badge"><?= round($product['distance'], 1) ?>
                                    <?= __('distance_away') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Infinite Scroll Sentinel -->
        <div id="infinite-scroll-sentinel" style="height: 20px; margin-bottom: 40px;"></div>
    <?php endif; ?>
</div>

<script>
    const sessionUserId = <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>;

    // Initialize Infinite Scroll
    document.addEventListener('DOMContentLoaded', () => {
        new EnteangadiInfiniteScroll({
            containerSelector: '.product-grid',
            loaderSelector: '#infinite-scroll-sentinel',
            apiUrl: 'api/products.php',
            params: {
                category_id: '<?= $category_filter ?>',
                search: '<?= $search_query ?>'
            },
            translations: {
                distance_away: '<?= __('distance_away') ?>',
                wanted: '<?= __('wanted') ?>',
                for_sale: '<?= __('for_sale') ?>'
            }
        });
    });
</script>
<script src="assets/js/index.js"></script>
<script src="assets/js/infinite-scroll.js"></script>


<!-- Premium Full-Screen Announcement Modal -->
<?php
$poster_path = $app_settings['announcement_poster'] ?? '';
if (!empty($poster_path)):
    ?>
    <script>console.log('Announcement Modal Code Included in Page');</script>
    <div id="announcement-modal" style="display: none;">
        <div class="announcement-wrapper">
            <!-- Close Trigger -->
            <button class="announcement-close-btn" onclick="closeAnnouncement()">
                <i class="fa fa-times" style="font-size: 14px;"></i> Close
            </button>

            <!-- Media Container -->
            <div class="announcement-media-container">
                <img id="announcement-poster-img"
                    src="<?= !empty($base_url) ? ($base_url . '/') : '' ?><?= htmlspecialchars($poster_path) ?>"
                    alt="Daily Announcement" onerror="console.error('FAILED TO LOAD POSTER IMAGE: ' + this.src)">
            </div>

            <!-- Sleek Action Continue Button at the bottom -->
            <button onclick="closeAnnouncement()" class="announcement-cta-button">
                Continue to <?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?> <i class="fa fa-arrow-right"
                    style="margin-left: 6px; font-size: 11px;"></i>
            </button>
        </div>
    </div>

    <script>
        function closeAnnouncement() {
            const modal = document.getElementById('announcement-modal');
            if (modal) {
                modal.classList.remove('active');
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }, 400);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('announcement-modal');
            if (!modal) {
                console.log('Announcement Modal element not found');
                return;
            }

            // Check if announcement was already shown in this session
            if (sessionStorage.getItem('announcement_shown')) {
                console.log('Announcement Modal already shown in this session');
                return;
            }

            console.log('Announcement Poster Path: <?= $base_url ?>/<?= $app_settings['announcement_poster'] ?? "EMPTY" ?>');

            // Aspect ratio check for dynamic layout adjustment
            const imgEl = document.getElementById('announcement-poster-img');
            const wrapper = document.querySelector('.announcement-wrapper');
            if (imgEl && wrapper) {
                imgEl.addEventListener('load', () => {
                    if (imgEl.naturalWidth < imgEl.naturalHeight) {
                        wrapper.classList.add('portrait-mode');
                    }
                });
                if (imgEl.complete) {
                    if (imgEl.naturalWidth < imgEl.naturalHeight) {
                        wrapper.classList.add('portrait-mode');
                    }
                }
            }

            // Show and set session flag so it won't repeat
            setTimeout(() => {
                modal.style.display = 'flex';
                modal.offsetHeight; // Force reflow
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                sessionStorage.setItem('announcement_shown', 'true');
                console.log('Announcement Modal shown');
            }, 800);
        });
    </script>

    <style>
        #announcement-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            height: -webkit-fill-available;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        #announcement-modal.active {
            opacity: 1;
        }

        .announcement-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            max-width: 1000px;
            max-width: 90vw;
            max-height: 650px;
            max-height: 80vh;
            background: #000;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: modalPopUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            transition: max-width 0.4s cubic-bezier(0.25, 0.8, 0.25, 1), max-height 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .announcement-wrapper.portrait-mode {
            max-width: 520px;
            max-height: 85vh;
        }

        .announcement-media-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .announcement-media-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .announcement-close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 10px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .announcement-close-btn:hover {
            transform: scale(1.05);
            background: #ffffff;
            color: #0f172a;
            border-color: #ffffff;
        }

        .announcement-cta-button {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-green);
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.4);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
        }

        .announcement-cta-button:hover {
            background: #15803d;
            transform: translateX(-50%) scale(1.05);
            box-shadow: 0 12px 30px -5px rgba(22, 163, 74, 0.6);
            color: #ffffff;
        }

        .verified-badge {
            color: #007bff !important;
            font-size: 16px !important;
            margin-left: 5px;
            vertical-align: middle;
            display: inline-flex;
            align-items: center;
        }

        .verified-badge i {
            font-weight: 900 !important;
            text-shadow: 0px 0px 2px rgba(0, 123, 255, 0.4);
        }

        @keyframes modalPopUp {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @media (max-width: 768px) {
            #announcement-modal {
                padding: 0;
            }

            .announcement-wrapper {
                max-width: 100vw;
                max-height: 100vh;
                border-radius: 0;
                border: none;
            }

            .announcement-close-btn {
                top: env(safe-area-inset-top, 24px);
                right: 16px;
            }
        }
    </style>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>