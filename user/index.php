<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    $redirect_url = '../guest/index.php';
    if (!empty($query_string)) {
        $redirect_url .= '?' . $query_string;
    }
    header("Location: " . $redirect_url);
    exit;
}

function renderProductCard($product, $baseUrl, $isGuest = false) {
    $detailLink = $isGuest ? "product.php?id=" . $product['id'] : "../product.php?id=" . $product['id'];
    $imageSrc = $product['main_image'] ? "../" . htmlspecialchars($product['main_image']) : null;
    
    $typeBadge = '';
    if ($product['type'] == 'buy') {
        $typeBadge = '<div class="badge-wanted">Wanted</div>';
    } elseif ($product['type'] == 'rent') {
        $typeBadge = '<div class="badge-rent">For Rent</div>';
    } else {
        $typeBadge = '<div class="badge-selling">For Sale</div>';
    }
    
    $imgHtml = '';
    if ($imageSrc) {
        $imgHtml = '<img src="' . $imageSrc . '" loading="lazy" alt="' . htmlspecialchars($product['title']) . '" class="product-card-image">';
    } else {
        $imgHtml = '<div class="product-card-image" style="display: flex; align-items: center; justify-content: center; background: #e0e0e0;"><i class="fa fa-image" style="font-size: 40px; color: #999;"></i></div>';
    }
    
    $priceVal = number_format($product['price'], (fmod($product['price'], 1) == 0) ? 0 : 2);
    $titleHtml = htmlspecialchars($product['title']);
    $catName = htmlspecialchars($product['category_name'] ?? 'Uncategorized');
    $dateStr = date('M d', strtotime($product['created_at']));
    
    return '
        <a href="' . $detailLink . '" class="product-card">
            ' . $typeBadge . '
            ' . $imgHtml . '
            <div class="product-card-content">
                <div class="product-card-price">₹' . $priceVal . '</div>
                <div class="product-card-title">' . $titleHtml . '</div>
                <div class="product-card-meta">
                    <span>' . $catName . '</span>
                    <span>' . $dateStr . '</span>
                </div>
            </div>
        </a>
    ';
}

// Fetch recently viewed products from cookies (limit 6 for index horizontal scroll)
$recently_viewed_products = [];
if (isset($_COOKIE['recently_viewed'])) {
    $rv_ids = json_decode($_COOKIE['recently_viewed'], true);
    if (is_array($rv_ids) && !empty($rv_ids)) {
        // Limit to show max 6 in the scroll list
        $rv_ids_scroll = array_slice($rv_ids, 0, 6);
        $placeholders = implode(',', array_fill(0, count($rv_ids_scroll), '?'));
        try {
            $rv_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN (
                    SELECT product_id, MIN(id) as min_img_id 
                    FROM product_images 
                    GROUP BY product_id
                ) pim ON p.id = pim.product_id
                LEFT JOIN product_images pi ON pim.min_img_id = pi.id
                WHERE p.id IN ($placeholders) AND p.status = 'active'");
            $rv_stmt->execute($rv_ids_scroll);
            $rv_fetched = $rv_stmt->fetchAll();
            
            // Sort to match cookie order (most recent first)
            $rv_map = [];
            foreach ($rv_fetched as $p) {
                $rv_map[$p['id']] = $p;
            }
            foreach ($rv_ids_scroll as $id) {
                if (isset($rv_map[$id])) {
                    $recently_viewed_products[] = $rv_map[$id];
                }
            }
        } catch (PDOException $e) {
            $recently_viewed_products = [];
        }
    }
}

$category_filter = $_GET['category_id'] ?? null;
$where_clause = "WHERE p.status = 'active'";
$params = [];

if ($category_filter) {
    $where_clause .= " AND (p.category_id = ? OR c.parent_id = ?)";
    $params[] = $category_filter;
    $params[] = $category_filter;
}

$user_location = $_SESSION['user_location'] ?? null;
$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : null;
if ($radius && $user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = floatval($user_location['lat']);
    $lng = floatval($user_location['lng']);
    $where_clause .= " AND (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) <= ?";
    $params[] = $radius;
}

$post_type = $_GET['post_type'] ?? null;
$products = [];
$sell_products = [];
$rent_products = [];
$buy_products = [];
$fresh_products = [];

// Fetch products
try {
    if ($post_type && in_array($post_type, ['sell', 'buy', 'rent'])) {
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause AND p.type = ?
            ORDER BY p.created_at DESC");
        $stmt->execute(array_merge($params, [$post_type]));
        $products = $stmt->fetchAll();
    } else {
        // Fetch Sell items (limit 4)
        $stmt_sell = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause AND p.type = 'sell'
            ORDER BY p.created_at DESC LIMIT 4");
        $stmt_sell->execute($params);
        $sell_products = $stmt_sell->fetchAll();

        // Fetch Rent items (limit 4)
        $stmt_rent = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause AND p.type = 'rent'
            ORDER BY p.created_at DESC LIMIT 4");
        $stmt_rent->execute($params);
        $rent_products = $stmt_rent->fetchAll();

        // Fetch Buy (Looking for) items (limit 4)
        $stmt_buy = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause AND p.type = 'buy'
            ORDER BY p.created_at DESC LIMIT 4");
        $stmt_buy->execute($params);
        $buy_products = $stmt_buy->fetchAll();

        // Fetch Fresh Recommendations items (limit 8)
        $stmt_fresh = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause 
            ORDER BY p.created_at DESC LIMIT 8");
        $stmt_fresh->execute($params);
        $fresh_products = $stmt_fresh->fetchAll();
    }
} catch (PDOException $e) {
    // Keep empty lists
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
            $parent_id = $category_filter; // L1 selected
        } else {
            $parent_id = $cat_info['parent_id']; // L2 selected
        }
        $child_stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
        $child_stmt->execute([$parent_id]);
        $l2_categories = $child_stmt->fetchAll();
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <!-- Browse Categories section -->
    <div style="margin-bottom: 32px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: var(--text-dark); font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">Browse categories</h2>
            <?php if ($category_filter): ?>
                <a href="index.php<?= $post_type ? '?post_type=' . $post_type : '' ?>"
                    style="font-size: 14px; color: var(--primary-green); text-decoration: none; font-weight: 700; background: #f0fdf4; padding: 6px 14px; border-radius: 12px; transition: all 0.2s ease;">Clear Filter</a>
            <?php endif; ?>
        </div>
        <div
            style="display: flex; gap: 16px; overflow-x: auto; padding: 4px 4px 12px 4px; scrollbar-width: none; -ms-overflow-style: none;">
            <?php foreach ($l1_categories as $cat): 
                $isActive = ($parent_id == $cat['id'] || $category_filter == $cat['id']);
                $targetUrl = "index.php?category_id=" . $cat['id'] . ($post_type ? "&post_type=" . $post_type : "");
            ?>
                <a href="<?= $targetUrl ?>" class="category-card <?= $isActive ? 'active' : '' ?>">
                    <div class="category-icon-wrapper">
                        <?php if ($cat['photo_path']): ?>
                            <img src="<?= $base_url ?>/<?= htmlspecialchars($cat['photo_path']) ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fa fa-th-large" style="font-size: 20px; color: var(--primary-green);"></i>
                        <?php endif; ?>
                    </div>
                    <span class="category-name-text"><?= htmlspecialchars($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($l2_categories)): ?>
            <div
                style="display: flex; gap: 12px; overflow-x: auto; padding-top: 16px; padding-bottom: 8px; scrollbar-width: none; -ms-overflow-style: none; border-top: 1.5px solid #f1f5f9; margin-top: 8px;">
                <?php foreach ($l2_categories as $cat): 
                    $isActive = ($category_filter == $cat['id']);
                    $targetUrl = "index.php?category_id=" . $cat['id'] . ($post_type ? "&post_type=" . $post_type : "");
                ?>
                    <a href="<?= $targetUrl ?>" class="subcategory-chip <?= $isActive ? 'active' : '' ?>">
                        <?php if ($cat['photo_path']): ?>
                            <img src="<?= $base_url ?>/<?= htmlspecialchars($cat['photo_path']) ?>">
                        <?php endif; ?>
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <style>
            div::-webkit-scrollbar {
                display: none;
            }
        </style>
    </div>

    <?php if (!empty($recently_viewed_products)): ?>
        <!-- Recently Viewed Items Horizontal Scroll Section -->
        <div style="margin-bottom: 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h2 style="color: var(--text-dark, #1e293b); font-size: 20px; font-weight: 700; margin: 0;">Recently viewed items</h2>
                <a href="recently_viewed.php" style="font-size: 14px; color: var(--primary-green, #1B5E20); text-decoration: none; font-weight: 700;">View All</a>
            </div>
            <div class="recently-viewed-scroll-container" style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; scrollbar-width: none; -ms-overflow-style: none; scroll-behavior: smooth;">
                <?php foreach ($recently_viewed_products as $rv_p): ?>
                    <a href="../product.php?id=<?= $rv_p['id'] ?>" class="recently-viewed-card" style="text-decoration: none; flex: 0 0 auto; width: 150px; background: var(--white, #fff); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color, #e2e8f0); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; transition: all 0.3s ease;">
                        <!-- Image Area -->
                        <div style="position: relative; width: 100%; height: 110px; background: #f1f5f9;">
                            <?php if (!empty($rv_p['is_verified'])): ?>
                                <div style="position: absolute; top: 6px; left: 6px; background: var(--primary-green, #1B5E20); color: #fff; font-size: 7px; font-weight: 800; padding: 2px 6px; border-radius: 3px; text-transform: uppercase; z-index: 2; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 2px;"><i class="fa fa-check-double" style="font-size: 6px;"></i> Verified</div>
                            <?php endif; ?>
                            <?php if ($rv_p['main_image']): ?>
                                <img src="../<?= htmlspecialchars($rv_p['main_image']) ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa fa-image" style="font-size: 24px; color: #cbd5e1;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Info Area -->
                        <div style="padding: 10px; display: flex; flex-direction: column; flex: 1; min-width: 0;">
                            <div style="font-size: 14px; font-weight: 800; color: var(--primary-green-dark, #1b5e20); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;">
                                ₹<?= number_format($rv_p['price'], (fmod($rv_p['price'], 1) == 0) ? 0 : 2) ?>
                            </div>
                            <div style="font-size: 12px; color: var(--text-dark, #1e293b); font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 32px; line-height: 1.3;">
                                <?= htmlspecialchars($rv_p['title']) ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
        .recently-viewed-scroll-container::-webkit-scrollbar {
            display: none;
        }
        .recently-viewed-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md, 0 10px 15px -3px rgba(0, 0, 0, 0.1));
            border-color: var(--primary-green-light, #a5d6a7) !important;
        }
        </style>
    <?php endif; ?>



    <!-- Premium Proximity Slider -->
    <?php if ($user_location && isset($user_location['lat'])): ?>
        <div class="glass-card" style="padding: 20px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white); margin-bottom: 32px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-map-marker-alt" style="color: var(--primary-green);"></i> Distance Range
                </h3>
                <span style="font-size: 13px; font-weight: 600; color: var(--primary-green-dark);" id="radius-val-label">
                    <?= $radius ? $radius . ' km' : 'All Kerala' ?>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <input type="range" id="proximity-slider" min="0" max="100" step="5" value="<?= $radius ?? 0 ?>" style="flex: 1; accent-color: var(--primary-green); height: 6px; cursor: pointer;">
                <button onclick="applyRadiusFilter()" class="btn-primary" style="padding: 8px 18px; font-size: 13px; border-radius: 16px; font-weight: 700;">Apply</button>
            </div>
            <p style="margin: 8px 0 0 0; font-size: 11px; color: var(--text-muted);">
                Centred on <strong><?= htmlspecialchars($user_location['name'] ?? 'Your Location') ?></strong>
            </p>
        </div>
        <script>
            const slider = document.getElementById('proximity-slider');
            const label = document.getElementById('radius-val-label');
            slider.addEventListener('input', function() {
                const val = parseInt(this.value);
                label.innerText = val === 0 ? 'All Kerala' : val + ' km';
            });
            function applyRadiusFilter() {
                const val = parseInt(slider.value);
                const url = new URL(window.location.href);
                if (val === 0) {
                    url.searchParams.delete('radius');
                } else {
                    url.searchParams.set('radius', val);
                }
                window.location.href = url.toString();
            }
        </script>
    <?php endif; ?>

    <?php if ($post_type): ?>
        <!-- Full listing of a specific type (Filtered View) -->
        <div class="section-header-premium" style="margin-top: 16px;">
            <h2 class="section-title-premium">
                <?php 
                    if ($post_type === 'sell') echo "I Want to Sell - Recommendations";
                    elseif ($post_type === 'rent') echo "I Want to Rent - Recommendations";
                    else echo "I am Looking For - Recommendations";
                ?>
            </h2>
            <a href="index.php<?= $category_filter ? '?category_id=' . $category_filter : '' ?>" class="section-view-all-btn" style="background: #f1f5f9; color: #475569;"><i class="fa fa-arrow-left"></i> Back to Home</a>
        </div>
        
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 40px 20px; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
                <i class="fa fa-box-open" style="font-size: 40px; color: var(--text-muted); margin-bottom: 12px;"></i>
                <h3 style="color: var(--text-dark); margin-bottom: 6px;">No products found</h3>
                <p style="color: var(--text-muted); margin: 0;">Be the first one to post in this category!</p>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product) {
                    echo renderProductCard($product, $base_url, false);
                } ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Default Sectioned Homepage Recommendations -->
        
        <?php if (empty($sell_products) && empty($rent_products) && empty($buy_products) && empty($fresh_products)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color); margin-top: 32px;">
                <i class="fa fa-box-open" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;"></i>
                <h3 style="color: var(--text-dark); margin-bottom: 8px;">No products found</h3>
                <p style="color: var(--text-muted); margin-bottom: 16px;">Try adjusting your distance range or clearing the filter.</p>
                <a href="index.php" class="btn-primary" style="display: inline-block;">Clear Filters</a>
            </div>
        <?php else: ?>
            <!-- 1. Fresh Recommendations Section -->
            <?php if (!empty($fresh_products)): ?>
                <div class="section-header-premium">
                    <h2 class="section-title-premium">Fresh Recommendations</h2>
                </div>
                <div class="product-grid">
                    <?php foreach ($fresh_products as $product) {
                        echo renderProductCard($product, $base_url, false);
                    } ?>
                </div>
            <?php endif; ?>

            <!-- 2. I Want to Sell Section -->
            <?php if (!empty($sell_products)): ?>
                <div class="section-header-premium">
                    <h2 class="section-title-premium">I Want to Sell</h2>
                    <a href="index.php?post_type=sell<?= $category_filter ? '&category_id=' . $category_filter : '' ?>" class="section-view-all-btn">View All <i class="fa fa-chevron-right" style="font-size: 10px;"></i></a>
                </div>
                <div class="product-grid">
                    <?php foreach ($sell_products as $product) {
                        echo renderProductCard($product, $base_url, false);
                    } ?>
                </div>
            <?php endif; ?>

            <!-- 3. I Want to Rent Section -->
            <?php if (!empty($rent_products)): ?>
                <div class="section-header-premium">
                    <h2 class="section-title-premium">I Want to Rent</h2>
                    <a href="index.php?post_type=rent<?= $category_filter ? '&category_id=' . $category_filter : '' ?>" class="section-view-all-btn">View All <i class="fa fa-chevron-right" style="font-size: 10px;"></i></a>
                </div>
                <div class="product-grid">
                    <?php foreach ($rent_products as $product) {
                        echo renderProductCard($product, $base_url, false);
                    } ?>
                </div>
            <?php endif; ?>

            <!-- 4. I am Looking For Section -->
            <?php if (!empty($buy_products)): ?>
                <div class="section-header-premium">
                    <h2 class="section-title-premium">I am Looking For</h2>
                    <a href="index.php?post_type=buy<?= $category_filter ? '&category_id=' . $category_filter : '' ?>" class="section-view-all-btn">View All <i class="fa fa-chevron-right" style="font-size: 10px;"></i></a>
                </div>
                <div class="product-grid">
                    <?php foreach ($buy_products as $product) {
                        echo renderProductCard($product, $base_url, false);
                    } ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>