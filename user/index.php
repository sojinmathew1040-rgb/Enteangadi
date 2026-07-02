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
    
    $distanceHtml = '';
    if (isset($product['distance']) && $product['distance'] !== null) {
        $dist = floatval($product['distance']);
        $distText = $dist < 1 ? round($dist * 1000) . ' m' : number_format($dist, 1) . ' km';
        $distanceHtml = '<span style="font-size: 11px; font-weight: 700; color: var(--primary-green); margin-left: auto;">🚴 ' . $distText . '</span>';
    }
    
    return '
        <a href="' . $detailLink . '" class="product-card">
            ' . $typeBadge . '
            ' . $imgHtml . '
            <div class="product-card-content">
                <div class="product-card-price">₹' . $priceVal . '</div>
                <div class="product-card-title">' . $titleHtml . '</div>
                <div class="product-card-meta" style="display: flex; align-items: center; width: 100%;">
                    <span>' . $catName . '</span>
                    <span style="margin-left: 8px;">' . $dateStr . '</span>
                    ' . $distanceHtml . '
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
$search_query = $_GET['search'] ?? null;
$active_spec = $_GET['spec'] ?? null;
$where_clause = "WHERE p.status = 'active'";
$params = [];

// Determine if the category filter is an L2 category (subcategory)
$is_l2_filter = false;
$parent_id = null;
if ($category_filter) {
    $cat_stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_filter]);
    $cat_info = $cat_stmt->fetch();
    if ($cat_info) {
        if ($cat_info['parent_id'] !== null) {
            $is_l2_filter = true; // It has a parent, so it's a subcategory (L2)
            $parent_id = $cat_info['parent_id'];
        } else {
            $parent_id = $category_filter; // It is a parent category (L1)
        }
    }
}

if ($is_l2_filter) {
    $where_clause .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if ($search_query) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
}

if ($active_spec) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = '%' . $active_spec . '%';
    $params[] = '%' . $active_spec . '%';
}

function isOrUnderCategory($categoryId, $targetParentId, $pdo) {
    if (!$categoryId) return false;
    if ($categoryId == $targetParentId) return true;
    try {
        $stmt = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $cat = $stmt->fetch();
        return ($cat && $cat['parent_id'] == $targetParentId);
    } catch (Exception $e) {
        return false;
    }
}

$is_cars = isOrUnderCategory($category_filter, 25, $pdo);
$is_mobiles = isOrUnderCategory($category_filter, 11, $pdo);
$is_properties = isOrUnderCategory($category_filter, 32, $pdo);

$user_location = $_SESSION['user_location'] ?? null;
$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : null;
$sort_by = $_GET['sort_by'] ?? null;

$select_fields = "p.*, c.name as category_name, pi.image_path as main_image";
$distance_select = "";
$order_by = "p.created_at DESC";

if ($user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = floatval($user_location['lat']);
    $lng = floatval($user_location['lng']);
    $distance_select = ", (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) AS distance";
}

if ($sort_by === 'price_asc') {
    $order_by = "p.price ASC";
} elseif ($sort_by === 'price_desc') {
    $order_by = "p.price DESC";
} elseif ($sort_by === 'distance' && !empty($distance_select)) {
    $order_by = "distance ASC";
}

if ($radius && $user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = floatval($user_location['lat']);
    $lng = floatval($user_location['lng']);
    $where_clause .= " AND (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) <= ?";
    $params[] = $radius;
}

$post_type = $_GET['post_type'] ?? null;
$is_filtered_view = ($post_type || $search_query || $is_l2_filter || $radius || $active_spec);
$products = [];
$sell_products = [];
$rent_products = [];
$buy_products = [];
$fresh_products = [];

// Fetch products
try {
    if ($is_filtered_view) {
        $type_where = "";
        $type_params = [];
        if ($post_type && in_array($post_type, ['sell', 'buy', 'rent'])) {
            $type_where = " AND p.type = ?";
            $type_params = [$post_type];
        }

        $stmt = $pdo->prepare("SELECT $select_fields $distance_select 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause $type_where
            ORDER BY $order_by");
        $stmt->execute(array_merge($params, $type_params));
        $products = $stmt->fetchAll();
    } else {
        // Fetch Sell items (limit 4)
        $stmt_sell = $pdo->prepare("SELECT $select_fields $distance_select 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause AND p.type = 'sell'
            ORDER BY $order_by LIMIT 4");
        $stmt_sell->execute($params);
        $sell_products = $stmt_sell->fetchAll();

        // Fetch Rent items (limit 4)
        $stmt_rent = $pdo->prepare("SELECT $select_fields $distance_select 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause AND p.type = 'rent'
            ORDER BY $order_by LIMIT 4");
        $stmt_rent->execute($params);
        $rent_products = $stmt_rent->fetchAll();

        // Fetch Buy (Looking for) items (limit 4)
        $stmt_buy = $pdo->prepare("SELECT $select_fields $distance_select 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause AND p.type = 'buy'
            ORDER BY $order_by LIMIT 4");
        $stmt_buy->execute($params);
        $buy_products = $stmt_buy->fetchAll();

        // Fetch Fresh Recommendations items (limit 8)
        $stmt_fresh = $pdo->prepare("SELECT $select_fields $distance_select 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            $where_clause 
            ORDER BY $order_by LIMIT 8");
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
    <?php if (!$is_filtered_view): ?>
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
            <div style="display: flex; gap: 16px; margin-top: 16px; align-items: center; border-top: 1px solid var(--border-color); padding-top: 16px;">
                <div style="display: flex; flex-direction: column; flex: 1;">
                    <label style="font-size: 12px; font-weight: 800; color: var(--text-dark); margin-bottom: 6px;">Sort By</label>
                    <select id="sort-by-select" style="padding: 10px 14px; font-size: 13px; border-radius: 12px; border: 1px solid var(--border-color); color: var(--text-dark); font-weight: 600; cursor: pointer; outline: none;" onchange="applySortBy(this.value)">
                        <option value="" <?= empty($sort_by) ? 'selected' : '' ?>>Newest Listings</option>
                        <option value="price_asc" <?= $sort_by === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort_by === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="distance" <?= $sort_by === 'distance' ? 'selected' : '' ?>>Nearest First</option>
                    </select>
                </div>
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
            function applySortBy(val) {
                const url = new URL(window.location.href);
                if (!val) {
                    url.searchParams.delete('sort_by');
                } else {
                    url.searchParams.set('sort_by', val);
                }
                window.location.href = url.toString();
            }
        </script>
    <?php endif; ?>

    <?php if ($is_cars || $is_mobiles || $is_properties): ?>
        <div class="glass-card" style="padding: 20px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white); margin-bottom: 32px; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-sliders-h" style="color: var(--primary-green);"></i> Specific Filters
                </h3>
                <?php if ($active_spec): ?>
                    <a href="index.php?category_id=<?= $category_filter ?><?= $post_type ? '&post_type=' . $post_type : '' ?><?= $radius ? '&radius=' . $radius : '' ?>" style="font-size: 11px; font-weight: 700; color: var(--primary-green); text-decoration: none; background: #f0fdf4; padding: 4px 10px; border-radius: 8px;">Clear Filters</a>
                <?php endif; ?>
            </div>
            <div style="display: flex; flex-direction: column; gap: 16px;">
                <?php 
                $filter_groups = [];
                if ($is_cars) {
                    $filter_groups = [
                        'Popular Brands' => ['Maruti Suzuki', 'Hyundai', 'Honda', 'Toyota', 'Tata', 'Mahindra'],
                        'Body Types' => ['Sedan', 'SUV', 'Hatchback'],
                        'Fuel Types' => ['Petrol', 'Diesel', 'Electric', 'CNG']
                    ];
                } elseif ($is_mobiles) {
                    $filter_groups = [
                        'Brands' => ['Apple', 'Samsung', 'OnePlus', 'Xiaomi', 'Realme'],
                        'Storage Capacity' => ['64 GB', '128 GB', '256 GB', '512 GB']
                    ];
                } elseif ($is_properties) {
                    $filter_groups = [
                        'Properties Type' => ['House', 'Apartment', 'Villa', 'Plot'],
                        'Size / Layout' => ['1 BHK', '2 BHK', '3 BHK', '4 BHK']
                    ];
                }

                foreach ($filter_groups as $group_title => $opts): ?>
                    <div>
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;"><?= $group_title ?></span>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($opts as $opt): 
                                $isSelected = ($active_spec === $opt);
                                if ($isSelected) {
                                    $targetUrl = "index.php?category_id=" . $category_filter . ($post_type ? "&post_type=" . $post_type : "") . ($radius ? "&radius=" . $radius : "");
                                } else {
                                    $targetUrl = "index.php?category_id=" . $category_filter . ($post_type ? "&post_type=" . $post_type : "") . ($radius ? "&radius=" . $radius : "") . "&spec=" . urlencode($opt);
                                }
                            ?>
                                <a href="<?= $targetUrl ?>" style="display: inline-block; padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 12px; border: 1px solid <?= $isSelected ? 'var(--primary-green)' : 'var(--border-color)' ?>; background: <?= $isSelected ? '#f0fdf4' : 'var(--white)' ?>; color: <?= $isSelected ? 'var(--primary-green-dark)' : 'var(--text-dark)' ?>; text-decoration: none; transition: all 0.2s ease;">
                                    <?= htmlspecialchars($opt) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Global Map Explorer Controls -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; width: 100%;">
        <h2 class="section-title-premium" style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-dark);">
            <?= $is_filtered_view ? 'Search Results' : 'Local Recommendations' ?>
        </h2>
        <div style="display: flex; align-items: center; gap: 12px;">
            <button id="map-toggle-btn" class="section-view-all-btn" style="background: var(--primary-green); color: white; display: flex; align-items: center; gap: 6px; cursor: pointer; border: none; font-size: 13px; font-weight: 700; border-radius: 12px; padding: 8px 16px;" onclick="toggleMapView()">
                <i class="fa fa-map"></i> Show Map
            </button>
            <?php if ($is_filtered_view): ?>
                <a href="index.php" class="section-view-all-btn" style="background: #f1f5f9; color: #475569;"><i class="fa fa-arrow-left"></i> Back to Home</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toggleable Bounding-Box Map Explorer Container -->
    <div id="map-explorer-container" style="display: none; width: 100%; height: 500px; border-radius: 20px; border: 1px solid var(--border-color); overflow: hidden; margin-bottom: 24px; box-shadow: var(--shadow-md); position: relative; z-index: 10;">
        <div id="map-canvas" style="width: 100%; height: 100%;"></div>
    </div>

    <script>
        let explorerMap = null;
        let markersLayer = null;
        let mapViewActive = false;

        function toggleMapView() {
            const mapContainer = document.getElementById('map-explorer-container');
            const listingsContainer = document.getElementById('home-listings-container');
            const btn = document.getElementById('map-toggle-btn');

            mapViewActive = !mapViewActive;

            if (mapViewActive) {
                mapContainer.style.display = 'block';
                if (listingsContainer) listingsContainer.style.display = 'none';
                btn.innerHTML = '<i class="fa fa-th"></i> Show List';
                btn.style.background = '#475569';
                
                // Initialize Map if not done
                initMapExplorer();
            } else {
                mapContainer.style.display = 'none';
                if (listingsContainer) listingsContainer.style.display = '';
                btn.innerHTML = '<i class="fa fa-map"></i> Show Map';
                btn.style.background = 'var(--primary-green)';
            }
        }

        function initMapExplorer() {
            if (explorerMap) {
                explorerMap.invalidateSize();
                return;
            }

            // Default center to user location or Kochi coordinates
            const defaultLat = EnteangadiConfig.location ? parseFloat(EnteangadiConfig.location.lat) : 9.94;
            const defaultLng = EnteangadiConfig.location ? parseFloat(EnteangadiConfig.location.lng) : 76.27;

            explorerMap = L.map('map-canvas').setView([defaultLat, defaultLng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap contributors'
            }).addTo(explorerMap);

            markersLayer = L.layerGroup().addTo(explorerMap);

            // Load markers when map moves/zooms
            explorerMap.on('moveend', function() {
                loadMapMarkers();
            });

            // Load markers initially
            loadMapMarkers();
        }

        async function loadMapMarkers() {
            if (!explorerMap || !markersLayer) return;

            const bounds = explorerMap.getBounds();
            const minLat = bounds.getSouth();
            const maxLat = bounds.getNorth();
            const minLng = bounds.getWest();
            const maxLng = bounds.getEast();

            // Get other search params
            const urlParams = new URLSearchParams(window.location.search);
            const categoryId = urlParams.get('category_id') || '';
            const searchVal = urlParams.get('search') || '';

            try {
                const response = await fetch(`../api/map_listings.php?min_lat=${minLat}&max_lat=${maxLat}&min_lng=${minLng}&max_lng=${maxLng}&category_id=${categoryId}&search=${encodeURIComponent(searchVal)}`);
                const result = await response.json();
                
                if (result.success) {
                    markersLayer.clearLayers();
                    const listings = result.products || [];
                    
                    listings.forEach(p => {
                        if (!p.latitude || !p.longitude) return;
                        
                        const priceFormatted = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(p.price);
                        const imgHtml = p.main_image 
                            ? `<img src="../${p.main_image}" style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; margin-bottom: 6px;">`
                            : `<div style="width: 100%; height: 80px; display: flex; align-items: center; justify-content: center; background: #e2e8f0; border-radius: 8px; margin-bottom: 6px;"><i class="fa fa-image" style="color: #94a3b8;"></i></div>`;
                        
                        const popupContent = `
                            <div style="width: 150px; font-family: sans-serif;">
                                ${imgHtml}
                                <div style="font-weight: 800; color: var(--primary-green-dark); margin-bottom: 2px;">${priceFormatted}</div>
                                <div style="font-size: 11px; font-weight: bold; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 6px;">${p.title}</div>
                                <a href="../product.php?id=${p.id}" style="display: block; text-align: center; background: var(--primary-green); color: white; text-decoration: none; font-size: 11px; font-weight: bold; padding: 4px; border-radius: 6px;">View Details</a>
                            </div>
                        `;
                        
                        const customIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style="background: var(--primary-green); color: white; font-weight: 800; font-size: 10px; padding: 4px 8px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); white-space: nowrap; border: 1.5px solid white;">₹${Math.round(p.price).toLocaleString('en-IN')}</div>`,
                            iconSize: [60, 20],
                            iconAnchor: [30, 10]
                        });

                        const marker = L.marker([parseFloat(p.latitude), parseFloat(p.longitude)], { icon: customIcon })
                            .bindPopup(popupContent)
                            .addTo(markersLayer);
                    });
                }
            } catch (e) {
                console.error("Map markers fetch failed:", e);
            }
        }
    </script>

    <!-- Content Feeds Wrapper -->
    <div id="home-listings-container">
    <?php if ($is_filtered_view): ?>
        <!-- Full listing of a specific type (Filtered View) -->
        
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
    </div> <!-- Close home-listings-container -->
</div>

<?php require_once '../includes/footer.php'; ?>