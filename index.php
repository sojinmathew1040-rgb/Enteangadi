<?php
require_once 'config.php';
require_once 'includes/header.php';
echo "<!-- DEBUG: Poster Path in DB: " . ($app_settings['announcement_poster'] ?? 'NOT_SET') . " -->";
if (isset($_GET['debug_poster']))
    echo "POSTER PATH: " . ($app_settings['announcement_poster'] ?? 'NOT SET');

$category_filter = $_GET['category_id'] ?? null;
$search_query = $_GET['search'] ?? null;
$user_location = $_SESSION['user_location'] ?? null;

$min_price = $_GET['min_price'] ?? null;
$max_price = $_GET['max_price'] ?? null;
$ad_type = $_GET['ad_type'] ?? null;
$sort_by = $_GET['sort_by'] ?? 'newest';

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

if ($min_price !== null && $min_price !== '') {
    $where_clause .= " AND p.price >= ?";
    $params[] = floatval($min_price);
}

if ($max_price !== null && $max_price !== '') {
    $where_clause .= " AND p.price <= ?";
    $params[] = floatval($max_price);
}

if ($ad_type && in_array($ad_type, ['buy', 'sell', 'rent'])) {
    $where_clause .= " AND p.type = ?";
    $params[] = $ad_type;
}

$distance_select = "";
$order_by = "p.created_at DESC";

if ($user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = $user_location['lat'];
    $lng = $user_location['lng'];
    // Haversine formula for distance in KM
    $distance_select = ", (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) AS distance";
}

if ($sort_by === 'price_asc') {
    $order_by = "p.price ASC";
} elseif ($sort_by === 'price_desc') {
    $order_by = "p.price DESC";
} elseif ($sort_by === 'distance' && !empty($distance_select)) {
    $order_by = "distance ASC";
} else {
    if (!empty($distance_select)) {
        $order_by = "p.created_at DESC, distance ASC";
    } else {
        $order_by = "p.created_at DESC";
    }
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

    <div class="category-header"
        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
        <h2 class="section-title-premium" style="margin: 0;"><?= __('fresh_recommendations') ?></h2>

        <div style="display: flex; gap: 12px; align-items: center;">
            <button class="btn-filter-toggle" onclick="toggleFiltersDrawer()"
                style="display: flex; align-items: center; gap: 8px; background: var(--white); border: 1px solid var(--border-color); padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; color: var(--text-dark); cursor: pointer; transition: all 0.3s ease; box-shadow: var(--shadow-sm);">
                <i class="fa fa-sliders-h" style="color: var(--primary-green);"></i> Filters
                <?php
                $active_filter_count = 0;
                if ($min_price !== null && $min_price !== '')
                    $active_filter_count++;
                if ($max_price !== null && $max_price !== '')
                    $active_filter_count++;
                if ($ad_type && $ad_type !== '')
                    $active_filter_count++;
                if ($sort_by && $sort_by !== 'newest')
                    $active_filter_count++;
                if ($active_filter_count > 0):
                    ?>
                    <span
                        style="background: var(--primary-green); color: white; border-radius: 50%; font-size: 10px; width: 18px; height: 18px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; margin-left: 4px;"><?= $active_filter_count ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- Collapsible Advanced Filters Drawer -->
    <div id="advanced-filters-drawer"
        style="max-height: 0px; overflow: hidden; transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1); margin-bottom: 24px;">
        <div class="glass-card"
            style="padding: 24px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: var(--white); box-shadow: var(--shadow-sm);">
            <form action="index.php" method="GET" id="filtersForm">
                <?php if ($category_filter): ?>
                    <input type="hidden" name="category_id" value="<?= htmlspecialchars($category_filter) ?>">
                <?php endif; ?>
                <?php if ($search_query): ?>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                <?php endif; ?>

                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px; align-items: end;">
                    <!-- Filter: Ad Type -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label
                            style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Listing
                            Type</label>
                        <div
                            style="display: flex; border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; background: var(--background);">
                            <button type="button" class="type-btn <?= (!$ad_type) ? 'active' : '' ?>"
                                onclick="setAdType('')"
                                style="flex: 1; padding: 10px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; background: transparent; border-radius: 10px; margin: 2px;">All</button>
                            <button type="button" class="type-btn <?= ($ad_type === 'sell') ? 'active' : '' ?>"
                                onclick="setAdType('sell')"
                                style="flex: 1; padding: 10px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; background: transparent; border-radius: 10px; margin: 2px;">Selling</button>
                            <button type="button" class="type-btn <?= ($ad_type === 'rent') ? 'active' : '' ?>"
                                onclick="setAdType('rent')"
                                style="flex: 1; padding: 10px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; background: transparent; border-radius: 10px; margin: 2px;">Rent</button>
                            <button type="button" class="type-btn <?= ($ad_type === 'buy') ? 'active' : '' ?>"
                                onclick="setAdType('buy')"
                                style="flex: 1; padding: 10px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; background: transparent; border-radius: 10px; margin: 2px;">Wanted</button>
                        </div>
                        <input type="hidden" name="ad_type" id="ad_type_input"
                            value="<?= htmlspecialchars($ad_type ?? '') ?>">
                    </div>

                    <!-- Filter: Price Range -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label
                            style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Price
                            Range (₹)</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="number" name="min_price" placeholder="Min" class="form-control"
                                value="<?= htmlspecialchars($min_price ?? '') ?>"
                                style="padding: 10px; border-radius: 12px; border: 1px solid var(--border-color); font-size: 13px; flex: 1; min-width: 0; background: var(--white); color: var(--text-dark);">
                            <span style="color: var(--text-muted); font-size: 13px; font-weight: 600;">to</span>
                            <input type="number" name="max_price" placeholder="Max" class="form-control"
                                value="<?= htmlspecialchars($max_price ?? '') ?>"
                                style="padding: 10px; border-radius: 12px; border: 1px solid var(--border-color); font-size: 13px; flex: 1; min-width: 0; background: var(--white); color: var(--text-dark);">
                        </div>
                    </div>

                    <!-- Filter: Sorting -->
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label
                            style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px;">Sort
                            By</label>
                        <select name="sort_by" class="form-control"
                            style="padding: 10px; border-radius: 12px; border: 1px solid var(--border-color); font-size: 13px; font-weight: 600; background: var(--white); color: var(--text-dark); width: 100%; cursor: pointer;">
                            <option value="newest" <?= ($sort_by === 'newest') ? 'selected' : '' ?>>Newest First</option>
                            <option value="price_asc" <?= ($sort_by === 'price_asc') ? 'selected' : '' ?>>Price: Low to
                                High</option>
                            <option value="price_desc" <?= ($sort_by === 'price_desc') ? 'selected' : '' ?>>Price: High to
                                Low</option>
                            <?php if ($user_location && isset($user_location['lat'])): ?>
                                <option value="distance" <?= ($sort_by === 'distance') ? 'selected' : '' ?>>Nearest First
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Filter Actions -->
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn-primary"
                            style="flex: 2; padding: 12px; font-size: 13px; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer; font-weight: 600;"><i
                                class="fa fa-filter"></i> Apply</button>
                        <a href="index.php?<?= $category_filter ? 'category_id=' . htmlspecialchars($category_filter) : '' ?><?= $search_query ? ($category_filter ? '&' : '') . 'search=' . htmlspecialchars($search_query) : '' ?>"
                            class="btn-secondary"
                            style="flex: 1; padding: 12px; font-size: 13px; border-radius: 12px; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; font-weight: 600;"><i
                                class="fa fa-undo"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
        .type-btn {
            background: transparent;
            color: var(--text-muted);
            border-radius: 10px;
            margin: 2px;
        }

        .type-btn.active {
            background: var(--white) !important;
            color: var(--primary-green) !important;
            box-shadow: var(--shadow-sm);
        }

        [data-theme="dark"] .type-btn.active {
            background: var(--glass-bg) !important;
        }

        .btn-filter-toggle:hover {
            border-color: var(--primary-green) !important;
            transform: translateY(-1px);
        }
    </style>

    <script>
        function toggleFiltersDrawer() {
            const drawer = document.getElementById('advanced-filters-drawer');
            if (drawer.style.maxHeight === '0px' || drawer.style.maxHeight === '0' || drawer.style.maxHeight === '') {
                drawer.style.maxHeight = '500px';
            } else {
                drawer.style.maxHeight = '0px';
            }
        }

        function setAdType(type) {
            document.getElementById('ad_type_input').value = type;
            const buttons = document.querySelectorAll('.type-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }

        // Keep drawer open if active filters are present on load
        document.addEventListener('DOMContentLoaded', () => {
            const activeFilters = <?= $active_filter_count ?>;
            if (activeFilters > 0) {
                document.getElementById('advanced-filters-drawer').style.maxHeight = '500px';
            }
        });
    </script>

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
                    <?php elseif ($product['type'] == 'rent'): ?>
                        <div class="badge-rent" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: white; position: absolute; top: 12px; left: 12px; padding: 6px 12px; border-radius: 8px; font-size: 10px; font-weight: 800; text-transform: uppercase; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); z-index: 2;">For Rent</div>
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
                search: '<?= $search_query ?>',
                min_price: '<?= htmlspecialchars($min_price ?? "") ?>',
                max_price: '<?= htmlspecialchars($max_price ?? "") ?>',
                ad_type: '<?= htmlspecialchars($ad_type ?? "") ?>',
                sort_by: '<?= htmlspecialchars($sort_by ?? "") ?>'
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