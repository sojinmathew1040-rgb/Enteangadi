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

if (!function_exists('renderProductCard')) {
    function renderProductCard($product, $baseUrl, $isGuest = false) {
        $detailLink = $isGuest ? "product.php?id=" . $product['id'] : "../product.php?id=" . $product['id'];
        $imgVersion = !empty($product['updated_at']) ? strtotime($product['updated_at']) : (!empty($product['created_at']) ? strtotime($product['created_at']) : time());
        $imageSrc = $product['main_image'] ? "../" . htmlspecialchars($product['main_image']) . "?v=" . $imgVersion : null;
        
        $typeBadge = '';
        if ($product['type'] == 'buy') {
            $typeBadge = '<div class="badge-wanted">' . htmlspecialchars(__('wanted')) . '</div>';
        } elseif ($product['type'] == 'rent') {
            $typeBadge = '<div class="badge-rent">' . htmlspecialchars(__('for_rent')) . '</div>';
        } else {
            $typeBadge = '<div class="badge-selling">' . htmlspecialchars(__('for_sale')) . '</div>';
        }

        $adminBadge = '';
        if (!empty($product['is_admin_posted'])) {
            $adminBadge = '<div class="badge-admin-posted" style="position: absolute; top: 10px; right: 10px; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; padding: 4px 9px; border-radius: 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; z-index: 10; display: flex; align-items: center; gap: 4px; box-shadow: 0 4px 10px rgba(5, 150, 105, 0.4); letter-spacing: 0.5px;"><i class="fa fa-shield-alt" style="font-size: 8px;"></i> Posted by Admin</div>';
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
            <a href="' . $detailLink . '" class="product-card" style="position: relative;">
                ' . $typeBadge . '
                ' . $adminBadge . '
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
$body_type_filter = $_GET['body_type'] ?? null;
$brand_filter = $_GET['brand'] ?? null;
$model_filter = $_GET['model'] ?? null;
$variant_filter = $_GET['variant'] ?? null;
$fuel_filter = $_GET['fuel'] ?? null;
$transmission_filter = $_GET['transmission'] ?? null;
$active_spec = $_GET['spec'] ?? null;
$post_type = $_GET['post_type'] ?? null;

// Backwards compatibility with 'spec' parameter
$known_body_types = [
    'Hatchback', 'Sedan', 'SUV', 'Compact SUV', 'MUV', 'Luxury',
    'Motorcycle', 'Scooter', 'Cruiser', 'Sports Bike', 'Electric Scooter', 'Bicycle',
    'Auto Rickshaw', 'Pickup & Mini Truck', 'Tipper & Truck', 'Tractor', 'Bus & Traveller', 'JCB & Cranes',
    'House', 'Villa', 'Apartment', 'Plot / Land', 'Plantation', 'Commercial Shop', 'Office',
    'Fresh Milk', 'Country Eggs', 'Spices & Pepper', 'Coconuts & Oil', 'Fruits & Vegetables', 'Pure Honey', 'Plant Saplings', 'Manure',
    'Milking', 'Kid / Young', 'Pair', 'Pregnant', 'Breeding'
];
$known_fuels = ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'];
$known_transmissions = ['Manual', 'Automatic'];

if (!$body_type_filter && $active_spec && in_array($active_spec, $known_body_types)) {
    $body_type_filter = $active_spec;
}
if (!$fuel_filter && $active_spec && in_array($active_spec, $known_fuels)) {
    $fuel_filter = $active_spec;
}
if (!$transmission_filter && $active_spec && in_array($active_spec, $known_transmissions)) {
    $transmission_filter = $active_spec;
}

$where_clause = "WHERE p.status = 'active'";
$params = [];

// Determine category filter
$parent_id = null;
$selected_category_name = '';
if ($category_filter) {
    $cat_stmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_filter]);
    $cat_info = $cat_stmt->fetch();
    if ($cat_info) {
        $selected_category_name = $cat_info['name'];
        if ($cat_info['parent_id'] !== null) {
            $parent_id = $cat_info['parent_id']; // Subcategory (L2) selected
            $where_clause .= " AND p.category_id = ?";
            $params[] = $category_filter;
        } else {
            $parent_id = $category_filter; // Parent (L1) category selected: match products in this category or any child category
            $where_clause .= " AND (p.category_id = ? OR c.parent_id = ?)";
            $params[] = $category_filter;
            $params[] = $category_filter;
        }
    }
}

// Body Type Filter
if ($body_type_filter) {
    if ($body_type_filter === 'Luxury') {
        $where_clause .= " AND (p.body_type = 'Luxury' OR p.brand IN ('Mercedes-Benz', 'BMW', 'Audi', 'Land Rover') OR p.title LIKE '%Luxury%' OR p.search_keywords LIKE '%Luxury%')";
    } elseif ($body_type_filter === 'SUV') {
        $where_clause .= " AND (p.body_type IN ('SUV', 'Compact SUV') OR p.title LIKE '%SUV%' OR p.description LIKE '%SUV%' OR p.search_keywords LIKE '%SUV%' OR c.name LIKE '%SUV%')";
    } else {
        $clean_btype = trim(preg_replace('/\(.*?\)/', '', $body_type_filter));
        $where_clause .= " AND (p.body_type = ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ? OR c.name LIKE ?)";
        $params[] = $body_type_filter;
        $params[] = '%' . $clean_btype . '%';
        $params[] = '%' . $clean_btype . '%';
        $params[] = '%' . $clean_btype . '%';
        $params[] = '%' . $clean_btype . '%';
    }
}

// Fuel Filter
if ($fuel_filter) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = '%' . $fuel_filter . '%';
    $params[] = '%' . $fuel_filter . '%';
    $params[] = '%' . $fuel_filter . '%';
}

// Transmission Filter
if ($transmission_filter) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = '%' . $transmission_filter . '%';
    $params[] = '%' . $transmission_filter . '%';
    $params[] = '%' . $transmission_filter . '%';
}

// Hierarchical Brand, Model, and Variant filters
if ($brand_filter) {
    $where_clause .= " AND (p.brand = ? OR p.title LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = $brand_filter;
    $params[] = '%' . $brand_filter . '%';
    $params[] = '%' . $brand_filter . '%';
}

if ($model_filter) {
    $where_clause .= " AND (p.model = ? OR p.title LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = $model_filter;
    $params[] = '%' . $model_filter . '%';
    $params[] = '%' . $model_filter . '%';
}

if ($variant_filter) {
    $where_clause .= " AND (p.variant = ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = $variant_filter;
    $params[] = '%' . $variant_filter . '%';
    $params[] = '%' . $variant_filter . '%';
    $params[] = '%' . $variant_filter . '%';
}

// Search across Title, Description, Multilingual Keywords, Subcategory, Parent Category, and Location
if (!empty($search_query)) {
    $terms = array_filter(array_map('trim', explode(' ', $search_query)));
    if (!empty($terms)) {
        $term_clauses = [];
        foreach ($terms as $term) {
            $term_clauses[] = "(p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ? OR c.name LIKE ? OR parent_c.name LIKE ? OR p.location_name LIKE ?)";
            $wildcard = '%' . $term . '%';
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
        }
        $where_clause .= " AND (" . implode(" AND ", $term_clauses) . ")";
    }
}

if ($active_spec && $active_spec !== $body_type_filter && $active_spec !== $fuel_filter && $active_spec !== $transmission_filter) {
    $clean_spec = trim(preg_replace('/\(.*?\)/', '', $active_spec));
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = '%' . $active_spec . '%';
    $params[] = '%' . $active_spec . '%';
    $params[] = '%' . $active_spec . '%';
    $params[] = '%' . $clean_spec . '%';
    $params[] = '%' . $clean_spec . '%';
    $params[] = '%' . $clean_spec . '%';
}

if ($post_type && in_array($post_type, ['sell', 'buy', 'rent'])) {
    $where_clause .= " AND p.type = ?";
    $params[] = $post_type;
}

if (!function_exists('isOrUnderCategory')) {
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
}

$is_cars = isOrUnderCategory($category_filter, 25, $pdo);
$is_bikes = isOrUnderCategory($category_filter, 27, $pdo);
$is_pets = isOrUnderCategory($category_filter, 80, $pdo);
$is_farm = isOrUnderCategory($category_filter, 9, $pdo);
$is_commercial = isOrUnderCategory($category_filter, 61, $pdo);
$is_mobiles = isOrUnderCategory($category_filter, 11, $pdo);
$is_properties = isOrUnderCategory($category_filter, 32, $pdo);

$buildFilterUrl = function($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return 'index.php?' . http_build_query($params);
};

$user_location = $_SESSION['user_location'] ?? null;
$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : null;
$sort_by = $_GET['sort_by'] ?? null;

$select_fields = "p.*, c.name as category_name, parent_c.name as parent_category_name, pi.image_path as main_image";
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

$is_filtered_view = (!empty($category_filter) || !empty($post_type) || !empty($search_query) || !empty($radius) || !empty($body_type_filter) || !empty($fuel_filter) || !empty($transmission_filter) || !empty($active_spec) || !empty($brand_filter) || !empty($model_filter) || !empty($variant_filter));
$products = [];

// Fetch products: On homepage displays all active ads; on category/search displays all matching ads
try {
    $stmt = $pdo->prepare("SELECT $select_fields $distance_select 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN categories parent_c ON c.parent_id = parent_c.id
        LEFT JOIN (
            SELECT product_id, MIN(id) as min_img_id 
            FROM product_images 
            GROUP BY product_id
        ) pim ON p.id = pim.product_id
        LEFT JOIN product_images pi ON pim.min_img_id = pi.id
        $where_clause
        ORDER BY $order_by");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Category Display Logic
$stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
$l1_categories = $stmt->fetchAll();

$l2_categories = [];
if ($parent_id) {
    $child_stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
    $child_stmt->execute([$parent_id]);
    $l2_categories = $child_stmt->fetchAll();
}

// Hierarchical Brand -> Model -> Variant Catalog Logic
$available_brands = [];
$available_models = [];
$available_variants = [];
$active_b_id = null;
$active_m_id = null;
$selected_model_body_type = null;

if ($parent_id) {
    // If a model is selected, get its body_type so we can disable non-applicable body types in UI
    if ($model_filter) {
        $mb_stmt = $pdo->prepare("SELECT body_type FROM product_models WHERE name = ? LIMIT 1");
        $mb_stmt->execute([$model_filter]);
        $selected_model_body_type = $mb_stmt->fetchColumn() ?: null;
        if ($selected_model_body_type && !$body_type_filter) {
            $body_type_filter = $selected_model_body_type;
        }
    }

    // Brands Query: Filter by body_type if selected!
    if ($body_type_filter) {
        if ($body_type_filter === 'Luxury') {
            $b_stmt = $pdo->prepare("SELECT DISTINCT b.id, b.name FROM product_brands b JOIN product_models m ON m.brand_id = b.id WHERE b.category_id = ? AND b.name IN ('Mercedes-Benz', 'BMW', 'Audi', 'Land Rover') ORDER BY b.sort_order, b.name");
            $b_stmt->execute([$parent_id]);
        } elseif ($body_type_filter === 'SUV') {
            $b_stmt = $pdo->prepare("SELECT DISTINCT b.id, b.name FROM product_brands b JOIN product_models m ON m.brand_id = b.id WHERE b.category_id = ? AND m.body_type IN ('SUV', 'Compact SUV') ORDER BY b.sort_order, b.name");
            $b_stmt->execute([$parent_id]);
        } else {
            $b_stmt = $pdo->prepare("SELECT DISTINCT b.id, b.name FROM product_brands b JOIN product_models m ON m.brand_id = b.id WHERE b.category_id = ? AND m.body_type = ? ORDER BY b.sort_order, b.name");
            $b_stmt->execute([$parent_id, $body_type_filter]);
        }
        $available_brands = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback to all brands if none match
        if (empty($available_brands)) {
            $b_stmt = $pdo->prepare("SELECT id, name FROM product_brands WHERE category_id = ? ORDER BY sort_order, name");
            $b_stmt->execute([$parent_id]);
            $available_brands = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $b_stmt = $pdo->prepare("SELECT id, name FROM product_brands WHERE category_id = ? ORDER BY sort_order, name");
        $b_stmt->execute([$parent_id]);
        $available_brands = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($brand_filter) {
        $brand_row = $pdo->prepare("SELECT id FROM product_brands WHERE category_id = ? AND name = ?");
        $brand_row->execute([$parent_id, $brand_filter]);
        $active_b_id = $brand_row->fetchColumn();
        if ($active_b_id) {
            // Models Query: Filter by body_type if selected!
            if ($body_type_filter && $body_type_filter !== 'Luxury') {
                if ($body_type_filter === 'SUV') {
                    $m_stmt = $pdo->prepare("SELECT id, name, body_type FROM product_models WHERE brand_id = ? AND body_type IN ('SUV', 'Compact SUV') ORDER BY sort_order, name");
                    $m_stmt->execute([$active_b_id]);
                } else {
                    $m_stmt = $pdo->prepare("SELECT id, name, body_type FROM product_models WHERE brand_id = ? AND body_type = ? ORDER BY sort_order, name");
                    $m_stmt->execute([$active_b_id, $body_type_filter]);
                }
                $available_models = $m_stmt->fetchAll(PDO::FETCH_ASSOC);
                if (empty($available_models)) {
                    $m_stmt = $pdo->prepare("SELECT id, name, body_type FROM product_models WHERE brand_id = ? ORDER BY sort_order, name");
                    $m_stmt->execute([$active_b_id]);
                    $available_models = $m_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $m_stmt = $pdo->prepare("SELECT id, name, body_type FROM product_models WHERE brand_id = ? ORDER BY sort_order, name");
                $m_stmt->execute([$active_b_id]);
                $available_models = $m_stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if ($model_filter) {
                $model_row = $pdo->prepare("SELECT id FROM product_models WHERE brand_id = ? AND name = ?");
                $model_row->execute([$active_b_id, $model_filter]);
                $active_m_id = $model_row->fetchColumn();
                if ($active_m_id) {
                    $v_stmt = $pdo->prepare("SELECT id, name FROM product_variants WHERE model_id = ? ORDER BY sort_order, name");
                    $v_stmt->execute([$active_m_id]);
                    $available_variants = $v_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <!-- Browse Categories section (Always accessible) -->
    <div style="margin-bottom: 32px;" class="category-slider-wrapper" id="categorySliderWrapper">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h2 style="color: var(--text-dark); font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">Browse categories</h2>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php if ($category_filter || $post_type || $search_query || $active_spec): ?>
                    <a href="index.php"
                        style="font-size: 13px; color: var(--primary-green); text-decoration: none; font-weight: 700; background: #f0fdf4; padding: 6px 14px; border-radius: 12px; transition: all 0.2s ease;">Clear Filter</a>
                <?php endif; ?>
                <!-- Manual Scroll Control Buttons -->
                <div class="category-nav-controls">
                    <button type="button" class="cat-nav-btn cat-prev-btn" onclick="scrollCategorySlider(-1)" title="Scroll Left" aria-label="Previous categories">
                        <i class="fa fa-chevron-left"></i>
                    </button>
                    <button type="button" class="cat-nav-btn cat-next-btn" onclick="scrollCategorySlider(1)" title="Scroll Right" aria-label="Next categories">
                        <i class="fa fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="category-track" id="categoryScrollTrack">
            <?php 
            // Duplicate the category list so it forms a continuous seamless infinite loop
            $infinite_categories = !empty($l1_categories) ? array_merge($l1_categories, $l1_categories) : [];
            foreach ($infinite_categories as $cat): 
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
            <div style="display: flex; gap: 12px; overflow-x: auto; padding-top: 14px; padding-bottom: 8px; scrollbar-width: none; -ms-overflow-style: none; border-top: 1.5px solid #f1f5f9; margin-top: 4px;">
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

        <script>
            (function() {
                const track = document.getElementById('categoryScrollTrack');
                const wrapper = document.getElementById('categorySliderWrapper');
                if (!track) return;

                let isPaused = false;
                let manualResumeTimer = null;
                const scrollSpeed = 0.75; // Smooth continuous pixels per frame

                function step() {
                    if (!isPaused && track) {
                        const halfWidth = track.scrollWidth / 2;
                        if (halfWidth > 50) {
                            track.scrollLeft += scrollSpeed;
                            if (track.scrollLeft >= halfWidth) {
                                track.scrollLeft -= halfWidth;
                            }
                        }
                    }
                    requestAnimationFrame(step);
                }

                // Manual Navigation Button controls (< and >)
                window.scrollCategorySlider = function(direction) {
                    isPaused = true;
                    const halfWidth = track.scrollWidth / 2;
                    const scrollDistance = 240 * direction;
                    
                    let target = track.scrollLeft + scrollDistance;
                    if (target < 0 && halfWidth > 0) {
                        track.scrollLeft += halfWidth;
                    } else if (target >= halfWidth * 2 && halfWidth > 0) {
                        track.scrollLeft -= halfWidth;
                    }

                    track.scrollBy({ left: scrollDistance, behavior: 'smooth' });

                    clearTimeout(manualResumeTimer);
                    manualResumeTimer = setTimeout(() => {
                        isPaused = false;
                    }, 2500);
                };

                // Pause on hover
                if (wrapper) {
                    wrapper.addEventListener('mouseenter', () => { isPaused = true; });
                    wrapper.addEventListener('mouseleave', () => { 
                        clearTimeout(manualResumeTimer);
                        manualResumeTimer = setTimeout(() => { isPaused = false; }, 300);
                    });
                    wrapper.addEventListener('touchstart', () => { isPaused = true; }, { passive: true });
                    wrapper.addEventListener('touchend', () => { 
                        clearTimeout(manualResumeTimer);
                        manualResumeTimer = setTimeout(() => { isPaused = false; }, 2000); 
                    }, { passive: true });
                }

                // Mouse Drag to scroll freely
                let isDown = false;
                let startX = 0;
                let scrollLeftStart = 0;

                track.addEventListener('mousedown', (e) => {
                    isDown = true;
                    isPaused = true;
                    startX = e.pageX - track.offsetLeft;
                    scrollLeftStart = track.scrollLeft;
                    track.style.cursor = 'grabbing';
                });

                window.addEventListener('mouseup', () => {
                    if (isDown) {
                        isDown = false;
                        if (track) track.style.cursor = 'pointer';
                        clearTimeout(manualResumeTimer);
                        manualResumeTimer = setTimeout(() => { isPaused = false; }, 2000);
                    }
                });

                track.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - track.offsetLeft;
                    const walk = (x - startX) * 1.5;
                    track.scrollLeft = scrollLeftStart - walk;

                    const halfWidth = track.scrollWidth / 2;
                    if (halfWidth > 0) {
                        if (track.scrollLeft >= halfWidth) {
                            track.scrollLeft -= halfWidth;
                            scrollLeftStart -= halfWidth;
                        } else if (track.scrollLeft < 0) {
                            track.scrollLeft += halfWidth;
                            scrollLeftStart += halfWidth;
                        }
                    }
                });

                requestAnimationFrame(step);
            })();
        </script>
    </div>

    <?php if (!empty($recently_viewed_products) && !$category_filter && !$search_query): ?>
    <!-- Recently Viewed Items Horizontal Scroll Section -->
    <div style="margin-bottom: 32px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="color: var(--text-dark, #1e293b); font-size: 20px; font-weight: 700; margin: 0;"><?= htmlspecialchars(__('recently_viewed')) ?></h2>
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

    <style>
        .catalog-drawer-modal {
            position: fixed;
            z-index: 10001;
            background: #ffffff;
            box-shadow: 0 -10px 40px rgba(15, 23, 42, 0.25);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
        }
        @media (max-width: 768px) {
            .catalog-drawer-modal {
                bottom: 0;
                left: 0;
                right: 0;
                max-height: 85vh;
                height: 85vh;
                border-radius: 24px 24px 0 0;
                transform: translateY(105%);
            }
            .catalog-drawer-modal.open {
                transform: translateY(0);
            }
        }
        @media (min-width: 769px) {
            .catalog-drawer-modal {
                top: 50%;
                left: 50%;
                width: 680px;
                max-height: 85vh;
                border-radius: 24px;
                transform: translate(-50%, -50%) scale(0.92);
                opacity: 0;
                pointer-events: none;
            }
            .catalog-drawer-modal.open {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>

    <?php 
    $primary_type_title = '';
    $primary_type_icon = '';
    $primary_type_opts = [];
    $secondary_filters = [];

    if ($is_cars) {
        $primary_type_title = 'Body Type (ബോഡി ടൈപ്പ്)';
        $primary_type_icon = 'fa-car-side';
        $primary_type_opts = ['Hatchback', 'Sedan', 'SUV', 'Compact SUV', 'MUV', 'Luxury'];
        $secondary_filters = [
            'Fuel Types' => ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'],
            'Transmission' => ['Manual', 'Automatic']
        ];
    } elseif ($is_bikes) {
        $primary_type_title = 'Vehicle Type (വാഹന ഇനം)';
        $primary_type_icon = 'fa-motorcycle';
        $primary_type_opts = ['Motorcycle', 'Scooter', 'Cruiser', 'Sports Bike', 'Electric Scooter', 'Bicycle'];
    } elseif ($is_commercial) {
        $primary_type_title = 'Vehicle Type (വാഹന ഇനം)';
        $primary_type_icon = 'fa-truck';
        $primary_type_opts = ['Auto Rickshaw', 'Pickup & Mini Truck', 'Tipper & Truck', 'Tractor', 'Bus & Traveller', 'JCB & Cranes'];
        $secondary_filters = [
            'Fuel Type' => ['Diesel', 'Petrol', 'CNG', 'Electric']
        ];
    } elseif ($is_properties) {
        $primary_type_title = 'Property Type (വസ്തു ഇനം)';
        $primary_type_icon = 'fa-home';
        $primary_type_opts = ['House', 'Villa', 'Apartment', 'Plot / Land', 'Plantation', 'Commercial Shop', 'Office'];
        $secondary_filters = [
            'Layout' => ['1 BHK', '2 BHK', '3 BHK', '4 BHK', '5+ BHK']
        ];
    } elseif ($is_farm) {
        $primary_type_title = 'Product Type (ഉൽപ്പന്ന ഇനം)';
        $primary_type_icon = 'fa-seedling';
        $primary_type_opts = ['Fresh Milk', 'Country Eggs', 'Spices & Pepper', 'Coconuts & Oil', 'Fruits & Vegetables', 'Pure Honey', 'Plant Saplings', 'Manure'];
        $secondary_filters = [
            'Quality' => ['Organic', 'Farm Fresh', 'Homemade']
        ];
    } elseif ($is_pets) {
        $primary_type_title = 'Livestock & Pet Types (ഇനം)';
        $primary_type_icon = 'fa-paw';
        $primary_type_opts = ['Milking', 'Kid / Young', 'Pair', 'Pregnant', 'Breeding'];
    } elseif ($is_mobiles) {
        $secondary_filters = [
            'Storage Capacity' => ['64 GB', '128 GB', '256 GB', '512 GB', '1 TB']
        ];
    }

    $has_catalog_filter = (!empty($available_brands) || !empty($brand_filter) || !empty($model_filter) || !empty($variant_filter) || !empty($primary_type_opts));
    $show_filters_panel = ($has_catalog_filter || $is_cars || $is_bikes || $is_pets || $is_farm || $is_commercial || $is_mobiles || $is_properties || !empty($active_spec) || !empty($body_type_filter));
    $active_filter_count = ($body_type_filter ? 1 : 0) + ($brand_filter ? 1 : 0) + ($model_filter ? 1 : 0) + ($variant_filter ? 1 : 0) + ($fuel_filter ? 1 : 0) + ($transmission_filter ? 1 : 0) + (($active_spec && $active_spec !== $body_type_filter && $active_spec !== $fuel_filter && $active_spec !== $transmission_filter) ? 1 : 0);
    ?>
    <?php if ($show_filters_panel): ?>
        <!-- Compact Filter Bar (Menu Trigger + Active Filter Chips Strip) -->
        <div id="catalog-filter-bar-container" style="margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- Filter Drawer Trigger Button -->
                <button type="button" onclick="openCatalogDrawer()" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; background: <?= $active_filter_count > 0 ? 'var(--primary-green)' : '#ffffff' ?>; color: <?= $active_filter_count > 0 ? '#ffffff' : 'var(--text-dark)' ?>; border: 1.5px solid <?= $active_filter_count > 0 ? 'var(--primary-green)' : 'var(--border-color)' ?>; border-radius: 14px; font-size: 13px; font-weight: 800; cursor: pointer; box-shadow: <?= $active_filter_count > 0 ? '0 4px 12px rgba(16, 185, 129, 0.25)' : 'var(--shadow-sm)' ?>; transition: all 0.2s ease;">
                    <i class="fa fa-sliders-h" style="color: <?= $active_filter_count > 0 ? '#ffffff' : 'var(--primary-green)' ?>;"></i>
                    <span>Filter & Catalog</span>
                    <?php if ($active_filter_count > 0): ?>
                        <span style="background: rgba(255,255,255,0.3); color: #fff; font-size: 11px; padding: 2px 7px; border-radius: 20px; font-weight: 800;"><?= $active_filter_count ?></span>
                    <?php endif; ?>
                    <i class="fa fa-chevron-down" style="font-size: 10px; opacity: 0.7; margin-left: 2px;"></i>
                </button>

                <!-- Active Filter Pills Strip (Single line scrollable) -->
                <div id="active-pills-bar" style="display: flex; align-items: center; gap: 8px; flex: 1; overflow-x: auto; padding-bottom: 2px; scrollbar-width: none;">
                    <?php if ($body_type_filter): ?>
                        <a href="<?= $buildFilterUrl(['body_type' => null, 'spec' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #dcfce7; color: #15803d; text-decoration: none; border: 1px solid #bbf7d0; white-space: nowrap;">
                            <span>Type: <?= htmlspecialchars($body_type_filter) ?></span>
                            <i class="fa fa-times" style="font-size: 10px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($brand_filter): ?>
                        <a href="<?= $buildFilterUrl(['brand' => null, 'model' => null, 'variant' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #e0f2fe; color: #0284c7; text-decoration: none; border: 1px solid #bae6fd; white-space: nowrap;">
                            <span>Brand: <?= htmlspecialchars($brand_filter) ?></span>
                            <i class="fa fa-times" style="font-size: 10px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($model_filter): ?>
                        <a href="<?= $buildFilterUrl(['model' => null, 'variant' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #ede9fe; color: #6d28d9; text-decoration: none; border: 1px solid #ddd6fe; white-space: nowrap;">
                            <span>Model: <?= htmlspecialchars($model_filter) ?></span>
                            <i class="fa fa-times" style="font-size: 10px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($variant_filter): ?>
                        <a href="<?= $buildFilterUrl(['variant' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #fef3c7; color: #b45309; text-decoration: none; border: 1px solid #fde68a; white-space: nowrap;">
                            <span>Variant: <?= htmlspecialchars($variant_filter) ?></span>
                            <i class="fa fa-times" style="font-size: 10px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($fuel_filter): ?>
                        <a href="<?= $buildFilterUrl(['fuel' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #fee2e2; color: #b91c1c; text-decoration: none; border: 1px solid #fca5a5; white-space: nowrap;">
                            <span>Fuel: <?= htmlspecialchars($fuel_filter) ?></span>
                            <i class="fa fa-times" style="font-size: 10px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($transmission_filter): ?>
                        <a href="<?= $buildFilterUrl(['transmission' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #f1f5f9; color: #334155; text-decoration: none; border: 1px solid #cbd5e1; white-space: nowrap;">
                            <span>Gear: <?= htmlspecialchars($transmission_filter) ?></span>
                            <i class="fa fa-times" style="font-size: 10px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($active_spec && $active_spec !== $body_type_filter && $active_spec !== $fuel_filter && $active_spec !== $transmission_filter): ?>
                        <a href="<?= $buildFilterUrl(['spec' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 12px; font-weight: 700; border-radius: 20px; background: #f0fdf4; color: #166534; text-decoration: none; border: 1px solid #bbf7d0; white-space: nowrap;">
                            <span><?= htmlspecialchars($active_spec) ?></span>
                            <i class="fa fa-times" style="font-size: 10px;"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($active_filter_count > 0): ?>
                        <a href="<?= $buildFilterUrl(['body_type' => null, 'brand' => null, 'model' => null, 'variant' => null, 'fuel' => null, 'transmission' => null, 'spec' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="font-size: 12px; font-weight: 700; color: #ef4444; text-decoration: none; white-space: nowrap; padding: 4px 8px;">
                            Clear All
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Filter Backdrop -->
        <div id="catalog-drawer-backdrop" onclick="closeCatalogDrawer()" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(4px); z-index: 10000; opacity: 0; transition: opacity 0.25s ease;"></div>

        <!-- Filter Slide-up Drawer / Modal Window -->
        <div id="catalog-drawer" class="catalog-drawer-modal" role="dialog" aria-modal="true" aria-labelledby="catalog-drawer-title">
            <!-- Drawer Header -->
            <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #ffffff; border-radius: 24px 24px 0 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: #f0fdf4; color: var(--primary-green); display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        <i class="fa fa-sliders-h"></i>
                    </div>
                    <div>
                        <h3 id="catalog-drawer-title" style="margin: 0; font-size: 17px; font-weight: 800; color: var(--text-dark);">
                            Detailed Filter & Catalog
                        </h3>
                        <div id="drawer-ads-counter" style="font-size: 11px; color: var(--text-muted); font-weight: 600; margin-top: 2px;">
                            <?= count($products) ?> <?= count($products) === 1 ? 'ad' : 'ads' ?> match your criteria
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php if ($active_filter_count > 0): ?>
                        <a href="<?= $buildFilterUrl(['body_type' => null, 'brand' => null, 'model' => null, 'variant' => null, 'fuel' => null, 'transmission' => null, 'spec' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="font-size: 12px; font-weight: 700; color: #ef4444; text-decoration: none; background: #fef2f2; padding: 6px 12px; border-radius: 10px; border: 1px solid #fecaca; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-times-circle"></i> Clear All
                        </a>
                    <?php endif; ?>
                    <button type="button" onclick="closeCatalogDrawer()" style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; font-size: 16px; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Drawer Scrollable Content -->
            <div id="catalog-drawer-content" style="padding: 20px 24px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 20px;">
                <!-- LEVEL 1: PRIMARY BODY TYPE / VEHICLE TYPE / PROPERTY TYPE -->
                <?php if (!empty($primary_type_opts)): ?>
                    <div style="background: #f0fdf4; border-radius: 16px; padding: 16px; border: 1.5px solid #bbf7d0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 12px; font-weight: 800; color: #166534; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa <?= $primary_type_icon ?: 'fa-th-large' ?>" style="color: var(--primary-green);"></i> 1. <?= $primary_type_title ?>
                            </span>
                            <?php if ($body_type_filter): ?>
                                <a href="<?= $buildFilterUrl(['body_type' => null, 'spec' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="font-size: 11px; font-weight: 700; color: #ef4444; text-decoration: none;">Reset</a>
                            <?php else: ?>
                                <span style="font-size: 11px; color: #15803d; font-weight: 600;"><?= count($primary_type_opts) ?> styles</span>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php foreach ($primary_type_opts as $opt): 
                                $isSelected = ($body_type_filter === $opt);
                                
                                // Disable incompatible body types if a specific model is chosen (e.g. Fronx is not Sedan)
                                $isDisabled = false;
                                if ($selected_model_body_type) {
                                    if ($selected_model_body_type === 'Compact SUV' && $opt === 'SUV') {
                                        $isDisabled = false; // Allow SUV broad parent group
                                    } elseif ($selected_model_body_type !== $opt) {
                                        $isDisabled = true;
                                    }
                                }

                                if ($isDisabled): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 12px; font-weight: 600; border-radius: 12px; border: 1.5px dashed #cbd5e1; background: #f8fafc; color: #94a3b8; cursor: not-allowed; opacity: 0.45; text-decoration: line-through;" title="Not available for <?= htmlspecialchars($model_filter) ?>">
                                        <?= htmlspecialchars($opt) ?>
                                    </span>
                                <?php else: 
                                    $targetUrl = $buildFilterUrl(['body_type' => ($isSelected ? null : $opt), 'spec' => null]);
                                ?>
                                    <a href="<?= $targetUrl ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 12px; border: 1.5px solid <?= $isSelected ? '#059669' : '#86efac' ?>; background: <?= $isSelected ? '#059669' : '#ffffff' ?>; color: <?= $isSelected ? '#ffffff' : '#14532d' ?>; text-decoration: none; transition: all 0.2s ease; box-shadow: <?= $isSelected ? '0 4px 12px rgba(5, 150, 105, 0.3)' : 'none' ?>;">
                                        <?php if ($isSelected): ?><i class="fa fa-check" style="font-size: 10px;"></i><?php endif; ?>
                                        <?= htmlspecialchars($opt) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- LEVEL 2: Brands / Makes -->
                <?php if (!empty($available_brands)): ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 12px; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-shield-alt" style="color: var(--primary-green);"></i> 2. Brand / Make (നിർമ്മാതാവ് / ബ്രാൻഡ്)
                            </span>
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;"><?= count($available_brands) ?> available</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; max-height: 180px; overflow-y: auto; padding: 2px;">
                            <?php foreach ($available_brands as $b): 
                                $isSelected = ($brand_filter === $b['name']);
                                $targetUrl = $buildFilterUrl(['brand' => ($isSelected ? null : $b['name']), 'model' => null, 'variant' => null]);
                            ?>
                                <a href="<?= $targetUrl ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 12px; border: 1.5px solid <?= $isSelected ? 'var(--primary-green)' : 'var(--border-color)' ?>; background: <?= $isSelected ? 'var(--primary-green)' : 'var(--white)' ?>; color: <?= $isSelected ? '#fff' : 'var(--text-dark)' ?>; text-decoration: none; transition: all 0.2s ease; box-shadow: <?= $isSelected ? '0 4px 12px rgba(16,185,129,0.3)' : 'none' ?>;">
                                    <?php if ($isSelected): ?><i class="fa fa-check" style="font-size: 10px;"></i><?php endif; ?>
                                    <?= htmlspecialchars($b['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- LEVEL 3: Models / Breeds (When Brand Selected) -->
                <?php if (!empty($available_models)): ?>
                    <div style="background: #f8fafc; border-radius: 16px; padding: 16px; border: 1.5px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-car" style="color: #0284c7;"></i> 3. Model / Variety for <?= htmlspecialchars($brand_filter) ?> (മോഡൽ / ഇനം)
                            </span>
                            <span style="font-size: 11px; color: #64748b; font-weight: 600;"><?= count($available_models) ?> models</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; max-height: 180px; overflow-y: auto; padding: 2px;">
                            <?php foreach ($available_models as $m): 
                                $isSelected = ($model_filter === $m['name']);
                                $targetUrl = $buildFilterUrl(['model' => ($isSelected ? null : $m['name']), 'variant' => null]);
                            ?>
                                <a href="<?= $targetUrl ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 12px; font-weight: 700; border-radius: 12px; border: 1.5px solid <?= $isSelected ? '#0284c7' : '#cbd5e1' ?>; background: <?= $isSelected ? '#0284c7' : '#fff' ?>; color: <?= $isSelected ? '#fff' : '#1e293b' ?>; text-decoration: none; transition: all 0.2s ease; box-shadow: <?= $isSelected ? '0 4px 12px rgba(2,132,199,0.25)' : 'none' ?>;">
                                    <?php if ($isSelected): ?><i class="fa fa-check" style="font-size: 10px;"></i><?php endif; ?>
                                    <?= htmlspecialchars($m['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- LEVEL 4: Variants / Trims (When Model Selected) -->
                <?php if (!empty($available_variants)): ?>
                    <div style="background: #fffbeb; border-radius: 16px; padding: 16px; border: 1.5px solid #fde68a;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 12px; font-weight: 800; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-tags" style="color: #d97706;"></i> 4. Variant / Trim / Spec for <?= htmlspecialchars($model_filter) ?> (വേരിയന്റ്)
                            </span>
                            <span style="font-size: 11px; color: #b45309; font-weight: 600;"><?= count($available_variants) ?> variants</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; max-height: 160px; overflow-y: auto; padding: 2px;">
                            <?php foreach ($available_variants as $v): 
                                $isSelected = ($variant_filter === $v['name']);
                                $targetUrl = $buildFilterUrl(['variant' => ($isSelected ? null : $v['name'])]);
                            ?>
                                <a href="<?= $targetUrl ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; font-size: 11px; font-weight: 700; border-radius: 10px; border: 1.5px solid <?= $isSelected ? '#d97706' : '#fcd34d' ?>; background: <?= $isSelected ? '#d97706' : '#fff' ?>; color: <?= $isSelected ? '#fff' : '#78350f' ?>; text-decoration: none; transition: all 0.2s ease; box-shadow: <?= $isSelected ? '0 4px 10px rgba(217,119,6,0.25)' : 'none' ?>;">
                                    <?php if ($isSelected): ?><i class="fa fa-check" style="font-size: 9px;"></i><?php endif; ?>
                                    <?= htmlspecialchars($v['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- LEVEL 5: Secondary Attribute Filter Groups (Fuel, Transmission, etc.) -->
                <?php if (!empty($secondary_filters)): ?>
                    <?php foreach ($secondary_filters as $group_title => $opts): ?>
                        <div>
                            <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;"><?= $group_title ?></span>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <?php foreach ($opts as $opt): 
                                    if ($group_title === 'Fuel Types' || $group_title === 'Fuel Type') {
                                        $isSelected = ($fuel_filter === $opt);
                                        $targetUrl = $buildFilterUrl(['fuel' => ($isSelected ? null : $opt)]);
                                    } elseif ($group_title === 'Transmission') {
                                        $isSelected = ($transmission_filter === $opt);
                                        $targetUrl = $buildFilterUrl(['transmission' => ($isSelected ? null : $opt)]);
                                    } else {
                                        $isSelected = ($active_spec === $opt);
                                        $targetUrl = $buildFilterUrl(['spec' => ($isSelected ? null : $opt)]);
                                    }
                                ?>
                                    <a href="<?= $targetUrl ?>" onclick="applyFilterAjax(event, this.href)" style="display: inline-block; padding: 6px 14px; font-size: 12px; font-weight: 600; border-radius: 12px; border: 1px solid <?= $isSelected ? 'var(--primary-green)' : 'var(--border-color)' ?>; background: <?= $isSelected ? '#f0fdf4' : 'var(--white)' ?>; color: <?= $isSelected ? 'var(--primary-green-dark)' : 'var(--text-dark)' ?>; text-decoration: none; transition: all 0.2s ease;">
                                        <?= htmlspecialchars($opt) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Drawer Sticky Footer Action Button -->
            <div style="padding: 16px 24px; border-top: 1px solid var(--border-color); background: #ffffff; display: flex; gap: 12px; align-items: center; border-radius: 0 0 24px 24px;">
                <button type="button" onclick="closeCatalogDrawer()" style="flex: 1; padding: 14px; background: var(--primary-green); color: white; border: none; border-radius: 14px; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3); display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>View Results</span>
                    <span id="btn-view-count" style="background: rgba(255,255,255,0.25); padding: 2px 8px; border-radius: 12px; font-size: 12px;"><?= count($products) ?> ads</span>
                </button>
            </div>
        </div>

        <script>
            function openCatalogDrawer() {
                const backdrop = document.getElementById('catalog-drawer-backdrop');
                const drawer = document.getElementById('catalog-drawer');
                if (!drawer || !backdrop) return;
                backdrop.style.display = 'block';
                setTimeout(() => {
                    backdrop.style.opacity = '1';
                    drawer.classList.add('open');
                }, 10);
                document.body.style.overflow = 'hidden';
            }

            function closeCatalogDrawer() {
                const backdrop = document.getElementById('catalog-drawer-backdrop');
                const drawer = document.getElementById('catalog-drawer');
                if (!drawer || !backdrop) return;
                drawer.classList.remove('open');
                backdrop.style.opacity = '0';
                setTimeout(() => {
                    backdrop.style.display = 'none';
                    document.body.style.overflow = '';
                }, 300);
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeCatalogDrawer();
            });

            async function applyFilterAjax(e, url) {
                if (e) e.preventDefault();

                const listingsCont = document.getElementById('home-listings-container');
                const drawerContent = document.getElementById('catalog-drawer-content');
                if (listingsCont) {
                    listingsCont.style.opacity = '0.5';
                    listingsCont.style.pointerEvents = 'none';
                }
                if (drawerContent) {
                    drawerContent.style.opacity = '0.7';
                }

                try {
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const html = await res.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');

                    // 1. Update filter bar active chips & count
                    const newBar = doc.getElementById('catalog-filter-bar-container');
                    const currBar = document.getElementById('catalog-filter-bar-container');
                    if (newBar && currBar) {
                        currBar.innerHTML = newBar.innerHTML;
                    }

                    // 2. Update drawer content (models, variants, pills)
                    const newContent = doc.getElementById('catalog-drawer-content');
                    const currContent = document.getElementById('catalog-drawer-content');
                    if (newContent && currContent) {
                        currContent.innerHTML = newContent.innerHTML;
                    }

                    // 3. Update drawer header counter & footer button
                    const newCounter = doc.getElementById('drawer-ads-counter');
                    const currCounter = document.getElementById('drawer-ads-counter');
                    if (newCounter && currCounter) {
                        currCounter.innerHTML = newCounter.innerHTML;
                    }

                    const newBtnView = doc.getElementById('btn-view-count');
                    const currBtnView = document.getElementById('btn-view-count');
                    if (newBtnView && currBtnView) {
                        currBtnView.innerHTML = newBtnView.innerHTML;
                    }

                    // 4. Update section title & ads counter
                    const newAdsBadge = doc.getElementById('ads-count-badge');
                    const currAdsBadge = document.getElementById('ads-count-badge');
                    if (newAdsBadge && currAdsBadge) {
                        currAdsBadge.innerHTML = newAdsBadge.innerHTML;
                    }

                    // 5. Update listings container
                    const newListings = doc.getElementById('home-listings-container');
                    if (newListings && listingsCont) {
                        listingsCont.innerHTML = newListings.innerHTML;
                    }

                    // 6. Update URL in address bar without scrolling or reloading
                    window.history.pushState(null, '', url);

                } catch (err) {
                    console.error('Filter AJAX error, falling back to full navigation:', err);
                    window.location.href = url;
                } finally {
                    if (listingsCont) {
                        listingsCont.style.opacity = '1';
                        listingsCont.style.pointerEvents = 'auto';
                    }
                    if (drawerContent) {
                        drawerContent.style.opacity = '1';
                    }
                }
            }

            window.addEventListener('popstate', () => {
                applyFilterAjax(null, window.location.href);
            });
        </script>
    <?php endif; ?>

    <!-- Global Header Controls, Section Title & Deal Type Filter Tabs -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; width: 100%;">
        <div>
            <h2 class="section-title-premium" style="margin: 0; font-size: 20px; font-weight: 800; color: var(--text-dark);">
                <?php
                if ($search_query) {
                    echo 'Search Results for "' . htmlspecialchars($search_query) . '"';
                } elseif ($category_filter && !empty($selected_category_name)) {
                    echo htmlspecialchars($selected_category_name);
                } else {
                    echo htmlspecialchars(__('fresh_recommendations'));
                }
                ?>
            </h2>
            <span id="ads-count-badge" style="font-size: 12px; color: var(--text-muted); font-weight: 600;">
                <?= count($products) ?> <?= count($products) === 1 ? 'ad' : 'ads' ?> available
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <!-- Deal Type Filter Pills -->
            <?php
            $catParam = $category_filter ? '&category_id=' . urlencode($category_filter) : '';
            $searchParam = $search_query ? '&search=' . urlencode($search_query) : '';
            $specParam = $active_spec ? '&spec=' . urlencode($active_spec) : '';
            $radiusParam = $radius ? '&radius=' . urlencode($radius) : '';
            $sortParam = $sort_by ? '&sort_by=' . urlencode($sort_by) : '';
            $extraParams = $catParam . $searchParam . $specParam . $radiusParam . $sortParam;
            ?>
            <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; gap: 4px; font-size: 12px; font-weight: 700;">
                <a href="index.php?<?= ltrim($extraParams, '&') ?>" style="padding: 6px 12px; border-radius: 8px; text-decoration: none; color: <?= empty($post_type) ? '#fff' : '#64748b' ?>; background: <?= empty($post_type) ? 'var(--primary-green)' : 'transparent' ?>;">All</a>
                <a href="index.php?post_type=sell<?= $extraParams ?>" style="padding: 6px 12px; border-radius: 8px; text-decoration: none; color: <?= $post_type === 'sell' ? '#fff' : '#64748b' ?>; background: <?= $post_type === 'sell' ? 'var(--primary-green)' : 'transparent' ?>;">For Sale</a>
                <a href="index.php?post_type=rent<?= $extraParams ?>" style="padding: 6px 12px; border-radius: 8px; text-decoration: none; color: <?= $post_type === 'rent' ? '#fff' : '#64748b' ?>; background: <?= $post_type === 'rent' ? 'var(--primary-green)' : 'transparent' ?>;">For Rent</a>
                <a href="index.php?post_type=buy<?= $extraParams ?>" style="padding: 6px 12px; border-radius: 8px; text-decoration: none; color: <?= $post_type === 'buy' ? '#fff' : '#64748b' ?>; background: <?= $post_type === 'buy' ? 'var(--primary-green)' : 'transparent' ?>;">Wanted</a>
            </div>

            <button id="map-toggle-btn" class="section-view-all-btn" style="background: var(--primary-green); color: white; display: flex; align-items: center; gap: 6px; cursor: pointer; border: none; font-size: 13px; font-weight: 700; border-radius: 12px; padding: 8px 16px;" onclick="toggleMapView()">
                <i class="fa fa-map"></i> Show Map
            </button>
            <?php if ($category_filter || $search_query || $post_type || $active_spec): ?>
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
                        const imgV = encodeURIComponent(p.updated_at || p.created_at || Date.now());
                        const imgHtml = p.main_image 
                            ? `<img src="../${p.main_image}?v=${imgV}" style="width: 100%; height: 80px; object-fit: cover; border-radius: 8px; margin-bottom: 6px;">`
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

    <!-- Content Feeds Wrapper: Displays all ads on homepage, and filtered ads when category/search is selected -->
    <div id="home-listings-container">
        <?php if (empty($products)): ?>
            <div style="text-align: center; padding: 40px 20px; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
                <i class="fa fa-box-open" style="font-size: 40px; color: var(--text-muted); margin-bottom: 12px;"></i>
                <h3 style="color: var(--text-dark); margin-bottom: 6px;">No products found</h3>
                <p style="color: var(--text-muted); margin: 0;">
                    <?= $category_filter ? 'Be the first one to post in this category!' : 'Be the first one to post an ad!' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="product-grid" style="margin-bottom: 24px;">
                <?php foreach ($products as $product) {
                    echo renderProductCard($product, $base_url, false);
                } ?>
            </div>
        <?php endif; ?>
    </div> <!-- Close home-listings-container -->
</div>

<?php require_once '../includes/footer.php'; ?>