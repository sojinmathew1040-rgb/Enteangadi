<?php
/**
 * Unified High-Speed Home & Category Feed (shared by user/index.php and guest/index.php)
 * Expects $pdo, $base_url, and $is_guest_feed (bool) to be defined by caller.
 */

if (!function_exists('renderProductCard')) {
    function renderProductCard(array $product, bool $eager = false): string
    {
        $pid = (int)$product['id'];
        $detailLink = "../product.php?id=" . $pid;
        $mainImg = $product['main_image'] ?? '';
        $imgVersion = !empty($product['updated_at']) ? strtotime($product['updated_at']) : (!empty($product['created_at']) ? strtotime($product['created_at']) : 1);
        $imageSrc = $mainImg !== '' ? "../" . htmlspecialchars($mainImg) . "?v=" . $imgVersion : null;

        if ($product['type'] === 'buy') {
            $typeBadge = '<div class="badge-wanted">' . htmlspecialchars(__('wanted')) . '</div>';
        } elseif ($product['type'] === 'rent') {
            $typeBadge = '<div class="badge-rent">' . htmlspecialchars(__('for_rent')) . '</div>';
        } else {
            $typeBadge = '<div class="badge-selling">' . htmlspecialchars(__('for_sale')) . '</div>';
        }

        $adminBadge = !empty($product['is_admin_posted'])
            ? '<div class="badge-admin-posted" style="position:absolute;top:10px;right:10px;background:linear-gradient(135deg,#059669 0%,#10b981 100%);color:#fff;padding:4px 9px;border-radius:8px;font-size:9px;font-weight:800;text-transform:uppercase;z-index:10;display:flex;align-items:center;gap:4px;box-shadow:0 4px 10px rgba(5,150,105,.4);letter-spacing:.5px;"><i class="fa fa-shield-alt" style="font-size:8px;"></i> Posted by Admin</div>'
            : '';

        $loadAttr = $eager ? 'fetchpriority="high" decoding="async"' : 'loading="lazy" decoding="async"';
        $imgHtml = $imageSrc
            ? '<img src="' . $imageSrc . '" ' . $loadAttr . ' alt="' . htmlspecialchars($product['title']) . '" class="product-card-image" onerror="window.handleBrokenProductImg && window.handleBrokenProductImg(this, ' . $pid . ')">'
            : '<div class="product-card-image" style="display:flex;align-items:center;justify-content:center;background:#e0e0e0;"><i class="fa fa-image" style="font-size:40px;color:#999;"></i></div>';

        $priceVal = number_format((float)$product['price'], (fmod((float)$product['price'], 1) == 0) ? 0 : 2);
        $titleHtml = htmlspecialchars($product['title']);
        $catName = htmlspecialchars($product['category_name'] ?? 'Uncategorized');
        $dateStr = date('M d', strtotime($product['created_at']));

        $distanceHtml = '';
        if (isset($product['distance']) && $product['distance'] !== null) {
            $dist = (float)$product['distance'];
            $distText = $dist < 1 ? round($dist * 1000) . ' m' : number_format($dist, 1) . ' km';
            $distanceHtml = '<span style="font-size:11px;font-weight:700;color:var(--primary-green);margin-left:auto;">🚴 ' . $distText . '</span>';
        }

        return '<a href="' . $detailLink . '" class="product-card" style="position:relative;">'
            . $typeBadge . $adminBadge . $imgHtml
            . '<div class="product-card-content">'
            . '<div class="product-card-price">₹' . $priceVal . '</div>'
            . '<div class="product-card-title">' . $titleHtml . '</div>'
            . '<div class="product-card-meta" style="display:flex;align-items:center;width:100%;">'
            . '<span>' . $catName . '</span><span style="margin-left:8px;">' . $dateStr . '</span>' . $distanceHtml
            . '</div></div></a>';
    }
}

// Recently viewed products (fast correlated primary image lookup)
$recently_viewed_products = [];
if (!empty($_COOKIE['recently_viewed'])) {
    $rv_ids = json_decode($_COOKIE['recently_viewed'], true);
    if (is_array($rv_ids) && !empty($rv_ids)) {
        $rv_ids_scroll = array_values(array_filter(array_map('intval', array_slice($rv_ids, 0, 6))));
        if (!empty($rv_ids_scroll)) {
            $placeholders = implode(',', array_fill(0, count($rv_ids_scroll), '?'));
            try {
                $rv_stmt = $pdo->prepare("SELECT p.*, c.name as category_name,
                    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) as main_image
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.id IN ($placeholders) AND p.status = 'active'");
                $rv_stmt->execute($rv_ids_scroll);
                $rv_map = [];
                foreach ($rv_stmt->fetchAll() as $p) {
                    $rv_map[(int)$p['id']] = $p;
                }
                foreach ($rv_ids_scroll as $id) {
                    if (isset($rv_map[$id])) {
                        $recently_viewed_products[] = $rv_map[$id];
                    }
                }
            } catch (PDOException $e) {
            }
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

$known_body_types = [
    'Hatchback', 'Sedan', 'SUV', 'Compact SUV', 'MUV', 'Luxury',
    'Motorcycle', 'Scooter', 'Cruiser', 'Sports Bike', 'Electric Scooter', 'Bicycle',
    'Auto Rickshaw', 'Pickup & Mini Truck', 'Tipper & Truck', 'Tractor', 'Bus & Traveller', 'JCB & Cranes',
    'House', 'Villa', 'Apartment', 'Plot / Land', 'Plantation', 'Commercial Shop', 'Office',
    'Fresh Milk', 'Country Eggs', 'Spices & Pepper', 'Coconuts & Oil', 'Fruits & Vegetables', 'Pure Honey', 'Plant Saplings', 'Manure',
    'Milking', 'Kid / Young', 'Pair', 'Pregnant', 'Breeding'
];
if (!$body_type_filter && $active_spec && in_array($active_spec, $known_body_types, true)) $body_type_filter = $active_spec;
if (!$fuel_filter && $active_spec && in_array($active_spec, ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'], true)) $fuel_filter = $active_spec;
if (!$transmission_filter && $active_spec && in_array($active_spec, ['Manual', 'Automatic'], true)) $transmission_filter = $active_spec;

$where_clause = "WHERE p.status = 'active'";
$params = [];
$parent_id = null;
$selected_category_name = '';

if ($category_filter) {
    $cat_stmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id = ?");
    $cat_stmt->execute([$category_filter]);
    $cat_info = $cat_stmt->fetch();
    if ($cat_info) {
        $selected_category_name = $cat_info['name'];
        if ($cat_info['parent_id'] !== null) {
            $parent_id = $cat_info['parent_id'];
            $where_clause .= " AND p.category_id = ?";
            $params[] = $category_filter;
        } else {
            $parent_id = $category_filter;
            $where_clause .= " AND (p.category_id = ? OR c.parent_id = ?)";
            array_push($params, $category_filter, $category_filter);
        }
    }
}

if ($body_type_filter) {
    if ($body_type_filter === 'Luxury') {
        $where_clause .= " AND (p.body_type = 'Luxury' OR p.brand IN ('Mercedes-Benz', 'BMW', 'Audi', 'Land Rover') OR p.title LIKE '%Luxury%' OR p.search_keywords LIKE '%Luxury%')";
    } elseif ($body_type_filter === 'SUV') {
        $where_clause .= " AND (p.body_type IN ('SUV', 'Compact SUV') OR p.title LIKE '%SUV%' OR p.description LIKE '%SUV%' OR p.search_keywords LIKE '%SUV%' OR c.name LIKE '%SUV%')";
    } else {
        $clean_btype = '%' . trim(preg_replace('/\(.*?\)/', '', $body_type_filter)) . '%';
        $where_clause .= " AND (p.body_type = ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ? OR c.name LIKE ?)";
        array_push($params, $body_type_filter, $clean_btype, $clean_btype, $clean_btype, $clean_btype);
    }
}

foreach ([$fuel_filter, $transmission_filter] as $ft) {
    if ($ft) {
        $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
        array_push($params, "%$ft%", "%$ft%", "%$ft%");
    }
}
if ($brand_filter) {
    $where_clause .= " AND (p.brand = ? OR p.title LIKE ? OR p.search_keywords LIKE ?)";
    array_push($params, $brand_filter, "%$brand_filter%", "%$brand_filter%");
}
if ($model_filter) {
    $where_clause .= " AND (p.model = ? OR p.title LIKE ? OR p.search_keywords LIKE ?)";
    array_push($params, $model_filter, "%$model_filter%", "%$model_filter%");
}
if ($variant_filter) {
    $where_clause .= " AND (p.variant = ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    array_push($params, $variant_filter, "%$variant_filter%", "%$variant_filter%", "%$variant_filter%");
}
if (!empty($search_query)) {
    $terms = array_filter(array_map('trim', explode(' ', $search_query)));
    if (!empty($terms)) {
        $term_clauses = [];
        foreach ($terms as $term) {
            $term_clauses[] = "(p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ? OR c.name LIKE ? OR parent_c.name LIKE ? OR p.location_name LIKE ?)";
            $w = "%$term%";
            array_push($params, $w, $w, $w, $w, $w, $w);
        }
        $where_clause .= " AND (" . implode(" AND ", $term_clauses) . ")";
    }
}
if ($active_spec && $active_spec !== $body_type_filter && $active_spec !== $fuel_filter && $active_spec !== $transmission_filter) {
    $clean_spec = trim(preg_replace('/\(.*?\)/', '', $active_spec));
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    array_push($params, "%$active_spec%", "%$active_spec%", "%$active_spec%", "%$clean_spec%", "%$clean_spec%", "%$clean_spec%");
}
if ($post_type && in_array($post_type, ['sell', 'buy', 'rent'], true)) {
    $where_clause .= " AND p.type = ?";
    $params[] = $post_type;
}

// Use $parent_id already resolved above to check root category in O(1) without 7 extra SQL queries!
$root_cat_id = (int)($parent_id ?: $category_filter ?: 0);
$is_cars = ($root_cat_id === 25);
$is_bikes = ($root_cat_id === 27);
$is_pets = ($root_cat_id === 80);
$is_farm = ($root_cat_id === 9);
$is_commercial = ($root_cat_id === 61);
$is_mobiles = ($root_cat_id === 11);
$is_properties = ($root_cat_id === 32);

$buildFilterUrl = function ($overrides = []) use ($is_guest_feed) {
    $p = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') unset($p[$k]);
        else $p[$k] = $v;
    }
    if (empty($p) && !empty($is_guest_feed)) {
        $p['_g'] = '1';
    }
    return 'index.php' . (!empty($p) ? '?' . http_build_query($p) : '');
};

$user_location = $_SESSION['user_location'] ?? null;
$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : null;
$sort_by = $_GET['sort_by'] ?? null;
$distance_select = "";
$order_by = "p.created_at DESC";

if ($user_location && isset($user_location['lat'], $user_location['lng'])) {
    $lat = floatval($user_location['lat']);
    $lng = floatval($user_location['lng']);
    $distExpr = "(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude))))))";
    $distance_select = ", $distExpr AS distance";
    if ($radius) {
        $where_clause .= " AND p.latitude IS NOT NULL AND p.longitude IS NOT NULL AND $distExpr <= ?";
        $params[] = $radius;
    }
}
if ($sort_by === 'price_asc') $order_by = "p.price ASC";
elseif ($sort_by === 'price_desc') $order_by = "p.price DESC";
elseif ($sort_by === 'distance' && $distance_select !== '') $order_by = "distance IS NULL ASC, distance ASC";

$page_size = 100;
$products = [];
$total_matching_ads = 0;

try {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN categories parent_c ON c.parent_id = parent_c.id $where_clause");
    $cntStmt->execute($params);
    $total_matching_ads = (int)$cntStmt->fetchColumn();

    if ($total_matching_ads > 0) {
        $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, parent_c.name as parent_category_name,
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) as main_image
            $distance_select
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN categories parent_c ON c.parent_id = parent_c.id
            $where_clause
            ORDER BY $order_by
            LIMIT $page_size");
        $stmt->execute($params);
        $products = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $products = [];
}

$l1_categories = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();
$l2_categories = [];
if ($parent_id) {
    $child_stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
    $child_stmt->execute([$parent_id]);
    $l2_categories = $child_stmt->fetchAll();
}

// Hierarchical Brand -> Model -> Variant Catalog
$available_brands = [];
$available_models = [];
$available_variants = [];
$selected_model_body_type = null;

if ($parent_id) {
    if ($model_filter) {
        $mb_stmt = $pdo->prepare("SELECT body_type FROM product_models WHERE name = ? LIMIT 1");
        $mb_stmt->execute([$model_filter]);
        $selected_model_body_type = $mb_stmt->fetchColumn() ?: null;
        if ($selected_model_body_type && !$body_type_filter) $body_type_filter = $selected_model_body_type;
    }
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
    }
    if (empty($available_brands)) {
        $b_stmt = $pdo->prepare("SELECT id, name FROM product_brands WHERE category_id = ? ORDER BY sort_order, name");
        $b_stmt->execute([$parent_id]);
        $available_brands = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($brand_filter) {
        $brand_row = $pdo->prepare("SELECT id FROM product_brands WHERE category_id = ? AND name = ?");
        $brand_row->execute([$parent_id, $brand_filter]);
        $active_b_id = $brand_row->fetchColumn();
        if ($active_b_id) {
            if ($body_type_filter && $body_type_filter !== 'Luxury') {
                if ($body_type_filter === 'SUV') {
                    $m_stmt = $pdo->prepare("SELECT id, name, body_type FROM product_models WHERE brand_id = ? AND body_type IN ('SUV', 'Compact SUV') ORDER BY sort_order, name");
                    $m_stmt->execute([$active_b_id]);
                } else {
                    $m_stmt = $pdo->prepare("SELECT id, name, body_type FROM product_models WHERE brand_id = ? AND body_type = ? ORDER BY sort_order, name");
                    $m_stmt->execute([$active_b_id, $body_type_filter]);
                }
                $available_models = $m_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            if (empty($available_models)) {
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

require_once __DIR__ . '/header.php';
?>

<div class="container">
    <!-- Browse Categories -->
    <div style="margin-bottom:32px;" class="category-slider-wrapper" id="categorySliderWrapper">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
            <h2 style="color:var(--text-dark);font-size:22px;font-weight:800;margin:0;letter-spacing:-0.5px;">Browse categories</h2>
            <div style="display:flex;align-items:center;gap:10px;">
                <?php if ($category_filter || $post_type || $search_query || $active_spec): ?>
                    <a href="index.php<?= !empty($is_guest_feed) ? '?_g=1' : '' ?>" style="font-size:13px;color:var(--primary-green);text-decoration:none;font-weight:700;background:#f0fdf4;padding:6px 14px;border-radius:12px;">Clear Filter</a>
                <?php endif; ?>
                <div class="category-nav-controls">
                    <button type="button" class="cat-nav-btn cat-prev-btn" onclick="scrollCategorySlider(-1)" title="Scroll Left" aria-label="Previous categories"><i class="fa fa-chevron-left"></i></button>
                    <button type="button" class="cat-nav-btn cat-next-btn" onclick="scrollCategorySlider(1)" title="Scroll Right" aria-label="Next categories"><i class="fa fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div class="category-track" id="categoryScrollTrack">
            <?php
            $infinite_categories = !empty($l1_categories) ? array_merge($l1_categories, $l1_categories) : [];
            foreach ($infinite_categories as $idx => $cat):
                $isActive = ($parent_id == $cat['id'] || $category_filter == $cat['id']);
                $targetUrl = "index.php?category_id=" . $cat['id'] . ($post_type ? "&post_type=" . urlencode($post_type) : "");
            ?>
                <a href="<?= $targetUrl ?>" class="category-card <?= $isActive ? 'active' : '' ?>">
                    <div class="category-icon-wrapper">
                        <?php if (!empty($cat['photo_path'])): ?>
                            <img src="<?= $base_url ?>/<?= htmlspecialchars($cat['photo_path']) ?>" width="50" height="50" <?= $idx < 8 ? 'decoding="async"' : 'loading="lazy" decoding="async"' ?> style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <i class="fa fa-th-large" style="font-size:20px;color:var(--primary-green);"></i>
                        <?php endif; ?>
                    </div>
                    <span class="category-name-text"><?= htmlspecialchars($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($l2_categories)): ?>
            <div style="display:flex;gap:12px;overflow-x:auto;padding-top:14px;padding-bottom:8px;scrollbar-width:none;-ms-overflow-style:none;border-top:1.5px solid #f1f5f9;margin-top:4px;">
                <?php foreach ($l2_categories as $cat):
                    $isActive = ($category_filter == $cat['id']);
                    $targetUrl = "index.php?category_id=" . $cat['id'] . ($post_type ? "&post_type=" . urlencode($post_type) : "");
                ?>
                    <a href="<?= $targetUrl ?>" class="subcategory-chip <?= $isActive ? 'active' : '' ?>">
                        <?php if (!empty($cat['photo_path'])): ?>
                            <img src="<?= $base_url ?>/<?= htmlspecialchars($cat['photo_path']) ?>" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <script>
            (function () {
                const track = document.getElementById('categoryScrollTrack');
                const wrapper = document.getElementById('categorySliderWrapper');
                if (!track) return;
                let isPaused = false, isOffscreen = false, manualResumeTimer = null, rafId = null;
                const scrollSpeed = 0.75;

                function step() {
                    if (!isPaused && !isOffscreen && !document.hidden && track) {
                        const half = track.scrollWidth / 2;
                        if (half > 50) {
                            track.scrollLeft += scrollSpeed;
                            if (track.scrollLeft >= half) track.scrollLeft -= half;
                        }
                    }
                    if (!isOffscreen && !document.hidden) rafId = requestAnimationFrame(step);
                    else rafId = null;
                }
                function ensureRunning() {
                    if (!rafId && !isOffscreen && !document.hidden) rafId = requestAnimationFrame(step);
                }

                if ('IntersectionObserver' in window && wrapper) {
                    new IntersectionObserver((entries) => {
                        isOffscreen = !entries[0].isIntersecting;
                        ensureRunning();
                    }).observe(wrapper);
                }
                document.addEventListener('visibilitychange', ensureRunning);

                window.scrollCategorySlider = function (direction) {
                    isPaused = true;
                    const half = track.scrollWidth / 2;
                    const dist = 240 * direction;
                    if (track.scrollLeft + dist < 0 && half > 0) track.scrollLeft += half;
                    else if (track.scrollLeft + dist >= half * 2 && half > 0) track.scrollLeft -= half;
                    track.scrollBy({ left: dist, behavior: 'smooth' });
                    clearTimeout(manualResumeTimer);
                    manualResumeTimer = setTimeout(() => { isPaused = false; }, 2500);
                };

                if (wrapper) {
                    wrapper.addEventListener('mouseenter', () => { isPaused = true; });
                    wrapper.addEventListener('mouseleave', () => { clearTimeout(manualResumeTimer); manualResumeTimer = setTimeout(() => { isPaused = false; }, 300); });
                    wrapper.addEventListener('touchstart', () => { isPaused = true; }, { passive: true });
                    wrapper.addEventListener('touchend', () => { clearTimeout(manualResumeTimer); manualResumeTimer = setTimeout(() => { isPaused = false; }, 2000); }, { passive: true });
                }

                let isDown = false, startX = 0, scrollLeftStart = 0;
                track.addEventListener('mousedown', (e) => { isDown = true; isPaused = true; startX = e.pageX - track.offsetLeft; scrollLeftStart = track.scrollLeft; track.style.cursor = 'grabbing'; });
                window.addEventListener('mouseup', () => {
                    if (isDown) { isDown = false; track.style.cursor = 'pointer'; clearTimeout(manualResumeTimer); manualResumeTimer = setTimeout(() => { isPaused = false; }, 2000); }
                });
                track.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    track.scrollLeft = scrollLeftStart - (e.pageX - track.offsetLeft - startX) * 1.5;
                });

                ensureRunning();
            })();
        </script>
    </div>

    <?php if (!empty($recently_viewed_products) && !$category_filter && !$search_query): ?>
        <div style="margin-bottom:32px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h2 style="color:var(--text-dark,#1e293b);font-size:20px;font-weight:700;margin:0;"><?= htmlspecialchars(__('recently_viewed')) ?></h2>
                <a href="<?= !empty($is_guest_feed) ? '../user/recently_viewed.php' : 'recently_viewed.php' ?>" style="font-size:14px;color:var(--primary-green,#1B5E20);text-decoration:none;font-weight:700;">View All</a>
            </div>
            <div class="recently-viewed-scroll-container" style="display:flex;gap:16px;overflow-x:auto;padding-bottom:12px;scrollbar-width:none;-ms-overflow-style:none;scroll-behavior:smooth;">
                <?php foreach ($recently_viewed_products as $rv_p): ?>
                    <a href="../product.php?id=<?= (int)$rv_p['id'] ?>" class="recently-viewed-card" style="text-decoration:none;flex:0 0 auto;width:150px;background:var(--white,#fff);border-radius:16px;overflow:hidden;border:1px solid var(--border-color,#e2e8f0);box-shadow:var(--shadow-sm);display:flex;flex-direction:column;transition:all 0.25s ease;">
                        <div style="position:relative;width:100%;height:110px;background:#f1f5f9;">
                            <?php if (!empty($rv_p['is_verified'])): ?>
                                <div style="position:absolute;top:6px;left:6px;background:var(--primary-green,#1B5E20);color:#fff;font-size:7px;font-weight:800;padding:2px 6px;border-radius:3px;text-transform:uppercase;z-index:2;display:flex;align-items:center;gap:2px;"><i class="fa fa-check-double" style="font-size:6px;"></i> Verified</div>
                            <?php endif; ?>
                            <?php if (!empty($rv_p['main_image'])): ?>
                                <img src="../<?= htmlspecialchars($rv_p['main_image']) ?>" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover;" onerror="window.handleBrokenProductImg && window.handleBrokenProductImg(this, <?= (int)$rv_p['id'] ?>)">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><i class="fa fa-image" style="font-size:24px;color:#cbd5e1;"></i></div>
                            <?php endif; ?>
                        </div>
                        <div style="padding:10px;display:flex;flex-direction:column;flex:1;min-width:0;">
                            <div style="font-size:14px;font-weight:800;color:var(--primary-green-dark,#1b5e20);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;">₹<?= number_format((float)$rv_p['price'], (fmod((float)$rv_p['price'], 1) == 0) ? 0 : 2) ?></div>
                            <div style="font-size:12px;color:var(--text-dark,#1e293b);font-weight:600;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;height:32px;line-height:1.3;"><?= htmlspecialchars($rv_p['title']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Proximity Slider -->
    <?php if ($user_location && isset($user_location['lat'])): ?>
        <div class="glass-card" style="padding:20px;border-radius:20px;border:1px solid var(--border-color);background:var(--white);margin-bottom:32px;box-shadow:var(--shadow-sm);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
                <h3 style="margin:0;font-size:16px;font-weight:800;color:var(--text-dark);display:flex;align-items:center;gap:6px;"><i class="fa fa-map-marker-alt" style="color:var(--primary-green);"></i> Distance Range</h3>
                <span style="font-size:13px;font-weight:600;color:var(--primary-green-dark);" id="radius-val-label"><?= $radius ? $radius . ' km' : 'All Kerala' ?></span>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <input type="range" id="proximity-slider" min="0" max="100" step="5" value="<?= $radius ?? 0 ?>" style="flex:1;accent-color:var(--primary-green);height:6px;cursor:pointer;">
                <button onclick="applyRadiusFilter()" class="btn-primary" style="padding:8px 18px;font-size:13px;border-radius:16px;font-weight:700;">Apply</button>
            </div>
            <div style="display:flex;gap:16px;margin-top:16px;align-items:center;border-top:1px solid var(--border-color);padding-top:16px;">
                <div style="display:flex;flex-direction:column;flex:1;">
                    <label style="font-size:12px;font-weight:800;color:var(--text-dark);margin-bottom:6px;">Sort By</label>
                    <select id="sort-by-select" style="padding:10px 14px;font-size:13px;border-radius:12px;border:1px solid var(--border-color);color:var(--text-dark);font-weight:600;cursor:pointer;outline:none;" onchange="applySortBy(this.value)">
                        <option value="" <?= empty($sort_by) ? 'selected' : '' ?>>Newest Listings</option>
                        <option value="price_asc" <?= $sort_by === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort_by === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="distance" <?= $sort_by === 'distance' ? 'selected' : '' ?>>Nearest First</option>
                    </select>
                </div>
            </div>
            <p style="margin:8px 0 0 0;font-size:11px;color:var(--text-muted);">Centred on <strong><?= htmlspecialchars($user_location['name'] ?? 'Your Location') ?></strong></p>
        </div>
        <script>
            (function () {
                const slider = document.getElementById('proximity-slider'), label = document.getElementById('radius-val-label');
                if (slider && label) slider.addEventListener('input', function () { const v = parseInt(this.value); label.innerText = v === 0 ? 'All Kerala' : v + ' km'; });
                window.applyRadiusFilter = function () {
                    const v = parseInt(slider.value), u = new URL(window.location.href);
                    if (v === 0) u.searchParams.delete('radius'); else u.searchParams.set('radius', v);
                    window.location.href = u.toString();
                };
                window.applySortBy = function (v) {
                    const u = new URL(window.location.href);
                    if (!v) u.searchParams.delete('sort_by'); else u.searchParams.set('sort_by', v);
                    window.location.href = u.toString();
                };
            })();
        </script>
    <?php endif; ?>

    <?php
    $primary_type_title = '';
    $primary_type_icon = '';
    $primary_type_opts = [];
    $secondary_filters = [];

    if ($is_cars) {
        $primary_type_title = 'Body Type (ബോഡി ടൈപ്പ്)';
        $primary_type_icon = 'fa-car-side';
        $primary_type_opts = ['Hatchback', 'Sedan', 'SUV', 'Compact SUV', 'MUV', 'Luxury'];
        $secondary_filters = ['Fuel Types' => ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'], 'Transmission' => ['Manual', 'Automatic']];
    } elseif ($is_bikes) {
        $primary_type_title = 'Vehicle Type (വാഹന ഇനം)';
        $primary_type_icon = 'fa-motorcycle';
        $primary_type_opts = ['Motorcycle', 'Scooter', 'Cruiser', 'Sports Bike', 'Electric Scooter', 'Bicycle'];
    } elseif ($is_commercial) {
        $primary_type_title = 'Vehicle Type (വാഹന ഇനം)';
        $primary_type_icon = 'fa-truck';
        $primary_type_opts = ['Auto Rickshaw', 'Pickup & Mini Truck', 'Tipper & Truck', 'Tractor', 'Bus & Traveller', 'JCB & Cranes'];
        $secondary_filters = ['Fuel Type' => ['Diesel', 'Petrol', 'CNG', 'Electric']];
    } elseif ($is_properties) {
        $primary_type_title = 'Property Type (വസ്തു ഇനം)';
        $primary_type_icon = 'fa-home';
        $primary_type_opts = ['House', 'Villa', 'Apartment', 'Plot / Land', 'Plantation', 'Commercial Shop', 'Office'];
        $secondary_filters = ['Layout' => ['1 BHK', '2 BHK', '3 BHK', '4 BHK', '5+ BHK']];
    } elseif ($is_farm) {
        $primary_type_title = 'Product Type (ഉൽപ്പന്ന ഇനം)';
        $primary_type_icon = 'fa-seedling';
        $primary_type_opts = ['Fresh Milk', 'Country Eggs', 'Spices & Pepper', 'Coconuts & Oil', 'Fruits & Vegetables', 'Pure Honey', 'Plant Saplings', 'Manure'];
        $secondary_filters = ['Quality' => ['Organic', 'Farm Fresh', 'Homemade']];
    } elseif ($is_pets) {
        $primary_type_title = 'Livestock & Pet Types (ഇനം)';
        $primary_type_icon = 'fa-paw';
        $primary_type_opts = ['Milking', 'Kid / Young', 'Pair', 'Pregnant', 'Breeding'];
    } elseif ($is_mobiles) {
        $secondary_filters = ['Storage Capacity' => ['64 GB', '128 GB', '256 GB', '512 GB', '1 TB']];
    }

    $has_catalog_filter = (!empty($available_brands) || !empty($brand_filter) || !empty($model_filter) || !empty($variant_filter) || !empty($primary_type_opts));
    $show_filters_panel = ($has_catalog_filter || $is_cars || $is_bikes || $is_pets || $is_farm || $is_commercial || $is_mobiles || $is_properties || !empty($active_spec) || !empty($body_type_filter));
    $extra_spec = ($active_spec && $active_spec !== $body_type_filter && $active_spec !== $fuel_filter && $active_spec !== $transmission_filter) ? $active_spec : null;
    $active_pills = array_filter([
        $body_type_filter ? ['label' => 'Type: ' . $body_type_filter, 'reset' => ['body_type' => null, 'spec' => null], 'bg' => '#dcfce7', 'fg' => '#15803d', 'bd' => '#bbf7d0'] : null,
        $brand_filter ? ['label' => 'Brand: ' . $brand_filter, 'reset' => ['brand' => null, 'model' => null, 'variant' => null], 'bg' => '#e0f2fe', 'fg' => '#0284c7', 'bd' => '#bae6fd'] : null,
        $model_filter ? ['label' => 'Model: ' . $model_filter, 'reset' => ['model' => null, 'variant' => null], 'bg' => '#ede9fe', 'fg' => '#6d28d9', 'bd' => '#ddd6fe'] : null,
        $variant_filter ? ['label' => 'Variant: ' . $variant_filter, 'reset' => ['variant' => null], 'bg' => '#fef3c7', 'fg' => '#b45309', 'bd' => '#fde68a'] : null,
        $fuel_filter ? ['label' => 'Fuel: ' . $fuel_filter, 'reset' => ['fuel' => null], 'bg' => '#fee2e2', 'fg' => '#b91c1c', 'bd' => '#fca5a5'] : null,
        $transmission_filter ? ['label' => 'Gear: ' . $transmission_filter, 'reset' => ['transmission' => null], 'bg' => '#f1f5f9', 'fg' => '#334155', 'bd' => '#cbd5e1'] : null,
        $extra_spec ? ['label' => $extra_spec, 'reset' => ['spec' => null], 'bg' => '#f0fdf4', 'fg' => '#166534', 'bd' => '#bbf7d0'] : null,
    ]);
    $active_filter_count = count($active_pills);
    $clear_all_url = $buildFilterUrl(['body_type' => null, 'brand' => null, 'model' => null, 'variant' => null, 'fuel' => null, 'transmission' => null, 'spec' => null]);
    ?>

    <?php if ($show_filters_panel): ?>
        <style>
            .catalog-drawer-modal { position:fixed; z-index:10001; background:#fff; box-shadow:0 -10px 40px rgba(15,23,42,.25); display:flex; flex-direction:column; transition:transform .3s cubic-bezier(.16,1,.3,1), opacity .25s ease; }
            @media (max-width:768px) { .catalog-drawer-modal { bottom:0; left:0; right:0; max-height:85vh; height:85vh; border-radius:24px 24px 0 0; transform:translateY(105%); } .catalog-drawer-modal.open { transform:translateY(0); } }
            @media (min-width:769px) { .catalog-drawer-modal { top:50%; left:50%; width:680px; max-height:85vh; border-radius:24px; transform:translate(-50%,-50%) scale(.92); opacity:0; pointer-events:none; } .catalog-drawer-modal.open { transform:translate(-50%,-50%) scale(1); opacity:1; pointer-events:auto; } }
        </style>

        <div id="catalog-filter-bar-container" style="margin-bottom:20px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <button type="button" onclick="openCatalogDrawer()" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:<?= $active_filter_count > 0 ? 'var(--primary-green)' : '#fff' ?>;color:<?= $active_filter_count > 0 ? '#fff' : 'var(--text-dark)' ?>;border:1.5px solid <?= $active_filter_count > 0 ? 'var(--primary-green)' : 'var(--border-color)' ?>;border-radius:14px;font-size:13px;font-weight:800;cursor:pointer;box-shadow:<?= $active_filter_count > 0 ? '0 4px 12px rgba(16,185,129,.25)' : 'var(--shadow-sm)' ?>;">
                    <i class="fa fa-sliders-h" style="color:<?= $active_filter_count > 0 ? '#fff' : 'var(--primary-green)' ?>;"></i>
                    <span>Filter & Catalog</span>
                    <?php if ($active_filter_count > 0): ?><span style="background:rgba(255,255,255,.3);color:#fff;font-size:11px;padding:2px 7px;border-radius:20px;font-weight:800;"><?= $active_filter_count ?></span><?php endif; ?>
                    <i class="fa fa-chevron-down" style="font-size:10px;opacity:.7;margin-left:2px;"></i>
                </button>
                <div id="active-pills-bar" style="display:flex;align-items:center;gap:8px;flex:1;overflow-x:auto;padding-bottom:2px;scrollbar-width:none;">
                    <?php foreach ($active_pills as $pill): ?>
                        <a href="<?= $buildFilterUrl($pill['reset']) ?>" onclick="applyFilterAjax(event, this.href)" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:12px;font-weight:700;border-radius:20px;background:<?= $pill['bg'] ?>;color:<?= $pill['fg'] ?>;text-decoration:none;border:1px solid <?= $pill['bd'] ?>;white-space:nowrap;">
                            <span><?= htmlspecialchars($pill['label']) ?></span><i class="fa fa-times" style="font-size:10px;"></i>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($active_filter_count > 0): ?>
                        <a href="<?= $clear_all_url ?>" onclick="applyFilterAjax(event, this.href)" style="font-size:12px;font-weight:700;color:#ef4444;text-decoration:none;white-space:nowrap;padding:4px 8px;">Clear All</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="catalog-drawer-backdrop" onclick="closeCatalogDrawer()" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:10000;opacity:0;transition:opacity .25s ease;"></div>
        <div id="catalog-drawer" class="catalog-drawer-modal" role="dialog" aria-modal="true" aria-labelledby="catalog-drawer-title">
            <div style="padding:18px 24px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;background:#fff;border-radius:24px 24px 0 0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:#f0fdf4;color:var(--primary-green);display:flex;align-items:center;justify-content:center;font-size:16px;"><i class="fa fa-sliders-h"></i></div>
                    <div>
                        <h3 id="catalog-drawer-title" style="margin:0;font-size:17px;font-weight:800;color:var(--text-dark);">Detailed Filter & Catalog</h3>
                        <div id="drawer-ads-counter" style="font-size:11px;color:var(--text-muted);font-weight:600;margin-top:2px;"><?= $total_matching_ads ?> <?= $total_matching_ads === 1 ? 'ad' : 'ads' ?> match your criteria</div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <?php if ($active_filter_count > 0): ?>
                        <a href="<?= $clear_all_url ?>" onclick="applyFilterAjax(event, this.href)" style="font-size:12px;font-weight:700;color:#ef4444;text-decoration:none;background:#fef2f2;padding:6px 12px;border-radius:10px;border:1px solid #fecaca;display:inline-flex;align-items:center;gap:4px;"><i class="fa fa-times-circle"></i> Clear All</a>
                    <?php endif; ?>
                    <button type="button" onclick="closeCatalogDrawer()" style="background:#f1f5f9;border:none;width:36px;height:36px;border-radius:50%;font-size:16px;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fa fa-times"></i></button>
                </div>
            </div>

            <div id="catalog-drawer-content" style="padding:20px 24px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:20px;">
                <?php if (!empty($primary_type_opts)): ?>
                    <div style="background:#f0fdf4;border-radius:16px;padding:16px;border:1.5px solid #bbf7d0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="font-size:12px;font-weight:800;color:#166534;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;"><i class="fa <?= $primary_type_icon ?: 'fa-th-large' ?>" style="color:var(--primary-green);"></i> 1. <?= $primary_type_title ?></span>
                            <?php if ($body_type_filter): ?>
                                <a href="<?= $buildFilterUrl(['body_type' => null, 'spec' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="font-size:11px;font-weight:700;color:#ef4444;text-decoration:none;">Reset</a>
                            <?php else: ?>
                                <span style="font-size:11px;color:#15803d;font-weight:600;"><?= count($primary_type_opts) ?> styles</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <?php foreach ($primary_type_opts as $opt):
                                $isSelected = ($body_type_filter === $opt);
                                $isDisabled = $selected_model_body_type && !($selected_model_body_type === 'Compact SUV' && $opt === 'SUV') && ($selected_model_body_type !== $opt);
                                if ($isDisabled): ?>
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font-size:12px;font-weight:600;border-radius:12px;border:1.5px dashed #cbd5e1;background:#f8fafc;color:#94a3b8;cursor:not-allowed;opacity:.45;text-decoration:line-through;"><?= htmlspecialchars($opt) ?></span>
                                <?php else: ?>
                                    <a href="<?= $buildFilterUrl(['body_type' => ($isSelected ? null : $opt), 'spec' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font-size:12px;font-weight:700;border-radius:12px;border:1.5px solid <?= $isSelected ? '#059669' : '#86efac' ?>;background:<?= $isSelected ? '#059669' : '#fff' ?>;color:<?= $isSelected ? '#fff' : '#14532d' ?>;text-decoration:none;">
                                        <?php if ($isSelected): ?><i class="fa fa-check" style="font-size:10px;"></i><?php endif; ?><?= htmlspecialchars($opt) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($available_brands)): ?>
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="font-size:12px;font-weight:800;color:var(--text-dark);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;"><i class="fa fa-shield-alt" style="color:var(--primary-green);"></i> 2. Brand / Make (നിർമ്മാതാവ് / ബ്രാൻഡ്)</span>
                            <span style="font-size:11px;color:var(--text-muted);font-weight:600;"><?= count($available_brands) ?> available</span>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;max-height:180px;overflow-y:auto;padding:2px;">
                            <?php foreach ($available_brands as $b):
                                $isSelected = ($brand_filter === $b['name']); ?>
                                <a href="<?= $buildFilterUrl(['brand' => ($isSelected ? null : $b['name']), 'model' => null, 'variant' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font-size:12px;font-weight:700;border-radius:12px;border:1.5px solid <?= $isSelected ? 'var(--primary-green)' : 'var(--border-color)' ?>;background:<?= $isSelected ? 'var(--primary-green)' : 'var(--white)' ?>;color:<?= $isSelected ? '#fff' : 'var(--text-dark)' ?>;text-decoration:none;">
                                    <?php if ($isSelected): ?><i class="fa fa-check" style="font-size:10px;"></i><?php endif; ?><?= htmlspecialchars($b['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($available_models)): ?>
                    <div style="background:#f8fafc;border-radius:16px;padding:16px;border:1.5px solid #e2e8f0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="font-size:12px;font-weight:800;color:#1e293b;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;"><i class="fa fa-car" style="color:#0284c7;"></i> 3. Model / Variety for <?= htmlspecialchars($brand_filter) ?></span>
                            <span style="font-size:11px;color:#64748b;font-weight:600;"><?= count($available_models) ?> models</span>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;max-height:180px;overflow-y:auto;padding:2px;">
                            <?php foreach ($available_models as $m):
                                $isSelected = ($model_filter === $m['name']); ?>
                                <a href="<?= $buildFilterUrl(['model' => ($isSelected ? null : $m['name']), 'variant' => null]) ?>" onclick="applyFilterAjax(event, this.href)" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;font-size:12px;font-weight:700;border-radius:12px;border:1.5px solid <?= $isSelected ? '#0284c7' : '#cbd5e1' ?>;background:<?= $isSelected ? '#0284c7' : '#fff' ?>;color:<?= $isSelected ? '#fff' : '#1e293b' ?>;text-decoration:none;">
                                    <?php if ($isSelected): ?><i class="fa fa-check" style="font-size:10px;"></i><?php endif; ?><?= htmlspecialchars($m['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($available_variants)): ?>
                    <div style="background:#fffbeb;border-radius:16px;padding:16px;border:1.5px solid #fde68a;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                            <span style="font-size:12px;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px;"><i class="fa fa-tags" style="color:#d97706;"></i> 4. Variant / Trim for <?= htmlspecialchars($model_filter) ?></span>
                            <span style="font-size:11px;color:#b45309;font-weight:600;"><?= count($available_variants) ?> variants</span>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;max-height:160px;overflow-y:auto;padding:2px;">
                            <?php foreach ($available_variants as $v):
                                $isSelected = ($variant_filter === $v['name']); ?>
                                <a href="<?= $buildFilterUrl(['variant' => ($isSelected ? null : $v['name'])]) ?>" onclick="applyFilterAjax(event, this.href)" style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;font-size:11px;font-weight:700;border-radius:10px;border:1.5px solid <?= $isSelected ? '#d97706' : '#fcd34d' ?>;background:<?= $isSelected ? '#d97706' : '#fff' ?>;color:<?= $isSelected ? '#fff' : '#78350f' ?>;text-decoration:none;">
                                    <?php if ($isSelected): ?><i class="fa fa-check" style="font-size:9px;"></i><?php endif; ?><?= htmlspecialchars($v['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($secondary_filters as $group_title => $opts): ?>
                    <div>
                        <span style="font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;display:block;margin-bottom:8px;"><?= $group_title ?></span>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
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
                                } ?>
                                <a href="<?= $targetUrl ?>" onclick="applyFilterAjax(event, this.href)" style="display:inline-block;padding:6px 14px;font-size:12px;font-weight:600;border-radius:12px;border:1px solid <?= $isSelected ? 'var(--primary-green)' : 'var(--border-color)' ?>;background:<?= $isSelected ? '#f0fdf4' : 'var(--white)' ?>;color:<?= $isSelected ? 'var(--primary-green-dark)' : 'var(--text-dark)' ?>;text-decoration:none;"><?= htmlspecialchars($opt) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="padding:16px 24px calc(16px + env(safe-area-inset-bottom, 0px)) 24px;border-top:1px solid var(--border-color);background:#fff;display:flex;gap:12px;align-items:center;border-radius:0 0 24px 24px;">
                <button type="button" onclick="closeCatalogDrawer()" style="flex:1;padding:14px;background:var(--primary-green);color:#fff;border:none;border-radius:14px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(16,185,129,.3);display:flex;align-items:center;justify-content:center;gap:8px;">
                    <span>View Results</span>
                    <span id="btn-view-count" style="background:rgba(255,255,255,.25);padding:2px 8px;border-radius:12px;font-size:12px;"><?= $total_matching_ads ?> ads</span>
                </button>
            </div>
        </div>

        <script>
            function openCatalogDrawer() {
                const b = document.getElementById('catalog-drawer-backdrop'), d = document.getElementById('catalog-drawer');
                if (!d || !b) return;
                b.style.display = 'block';
                setTimeout(() => { b.style.opacity = '1'; d.classList.add('open'); }, 10);
                document.body.style.overflow = 'hidden';
            }
            function closeCatalogDrawer() {
                const b = document.getElementById('catalog-drawer-backdrop'), d = document.getElementById('catalog-drawer');
                if (!d || !b) return;
                d.classList.remove('open');
                b.style.opacity = '0';
                setTimeout(() => { b.style.display = 'none'; document.body.style.overflow = ''; }, 300);
            }
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCatalogDrawer(); });

            async function applyFilterAjax(e, url) {
                if (e) e.preventDefault();
                const listingsCont = document.getElementById('home-listings-container');
                const drawerContent = document.getElementById('catalog-drawer-content');
                if (listingsCont) { listingsCont.style.opacity = '0.5'; listingsCont.style.pointerEvents = 'none'; }
                if (drawerContent) drawerContent.style.opacity = '0.7';
                try {
                    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                    ['catalog-filter-bar-container', 'catalog-drawer-content', 'drawer-ads-counter', 'btn-view-count', 'ads-count-badge', 'home-listings-container'].forEach(id => {
                        const n = doc.getElementById(id), c = document.getElementById(id);
                        if (n && c) c.innerHTML = n.innerHTML;
                    });
                    window.history.pushState(null, '', url);
                    if (typeof window.initHomeFeedPager === 'function') window.initHomeFeedPager();
                } catch (err) {
                    window.location.href = url;
                } finally {
                    if (listingsCont) { listingsCont.style.opacity = '1'; listingsCont.style.pointerEvents = 'auto'; }
                    if (drawerContent) drawerContent.style.opacity = '1';
                }
            }
            window.addEventListener('popstate', () => applyFilterAjax(null, window.location.href));
        </script>
    <?php endif; ?>

    <!-- Section Title & Deal Type Filter Tabs -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;margin-bottom:16px;flex-wrap:wrap;gap:12px;width:100%;">
        <div>
            <h2 class="section-title-premium" style="margin:0;font-size:20px;font-weight:800;color:var(--text-dark);">
                <?php
                if ($search_query) echo 'Search Results for "' . htmlspecialchars($search_query) . '"';
                elseif ($category_filter && !empty($selected_category_name)) echo htmlspecialchars($selected_category_name);
                else echo htmlspecialchars(__('fresh_recommendations'));
                ?>
            </h2>
            <span id="ads-count-badge" style="font-size:12px;color:var(--text-muted);font-weight:600;">
                <?= $total_matching_ads ?> <?= $total_matching_ads === 1 ? 'ad' : 'ads' ?> available
            </span>
        </div>

        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <?php
            $extraParams = ($category_filter ? '&category_id=' . urlencode($category_filter) : '')
                . ($search_query ? '&search=' . urlencode($search_query) : '')
                . ($active_spec ? '&spec=' . urlencode($active_spec) : '')
                . ($radius ? '&radius=' . urlencode($radius) : '')
                . ($sort_by ? '&sort_by=' . urlencode($sort_by) : '');
            $dealTabs = ['' => 'All', 'sell' => 'For Sale', 'rent' => 'For Rent', 'buy' => 'Wanted'];
            ?>
            <div style="display:flex;background:#f1f5f9;padding:4px;border-radius:12px;gap:4px;font-size:12px;font-weight:700;">
                <?php foreach ($dealTabs as $tKey => $tLabel):
                    $isTab = ($tKey === '' ? empty($post_type) : ($post_type === $tKey));
                    $defaultReset = !empty($is_guest_feed) ? '?_g=1' : '';
                    $tabHref = 'index.php' . ($tKey !== '' ? '?post_type=' . $tKey . $extraParams : ($extraParams !== '' ? '?' . ltrim($extraParams, '&') : $defaultReset));
                ?>
                    <a href="<?= $tabHref ?>" style="padding:6px 12px;border-radius:8px;text-decoration:none;color:<?= $isTab ? '#fff' : '#64748b' ?>;background:<?= $isTab ? 'var(--primary-green)' : 'transparent' ?>;"><?= $tLabel ?></a>
                <?php endforeach; ?>
            </div>

            <button id="map-toggle-btn" class="section-view-all-btn" style="background:var(--primary-green);color:#fff;display:flex;align-items:center;gap:6px;cursor:pointer;border:none;font-size:13px;font-weight:700;border-radius:12px;padding:8px 16px;" onclick="toggleMapView()">
                <i class="fa fa-map"></i> Show Map
            </button>
            <?php if ($category_filter || $search_query || $post_type || $active_spec): ?>
                <a href="index.php<?= !empty($is_guest_feed) ? '?_g=1' : '' ?>" class="section-view-all-btn" style="background:#f1f5f9;color:#475569;"><i class="fa fa-arrow-left"></i> Back to Home</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Lazy-Loaded Map Explorer -->
    <div id="map-explorer-container" style="display:none;width:100%;height:500px;border-radius:20px;border:1px solid var(--border-color);overflow:hidden;margin-bottom:24px;box-shadow:var(--shadow-md);position:relative;z-index:10;">
        <div id="map-canvas" style="width:100%;height:100%;"></div>
    </div>

    <script>
        (function () {
            let explorerMap = null, markersLayer = null, mapViewActive = false;

            window.toggleMapView = async function () {
                const mapContainer = document.getElementById('map-explorer-container');
                const listingsContainer = document.getElementById('home-listings-container');
                const btn = document.getElementById('map-toggle-btn');
                mapViewActive = !mapViewActive;

                if (mapViewActive) {
                    mapContainer.style.display = 'block';
                    if (listingsContainer) listingsContainer.style.display = 'none';
                    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading Map...';
                    if (typeof window.ensureLeafletLoaded === 'function') {
                        await window.ensureLeafletLoaded();
                    }
                    btn.innerHTML = '<i class="fa fa-th"></i> Show List';
                    btn.style.background = '#475569';
                    initMapExplorer();
                } else {
                    mapContainer.style.display = 'none';
                    if (listingsContainer) listingsContainer.style.display = '';
                    btn.innerHTML = '<i class="fa fa-map"></i> Show Map';
                    btn.style.background = 'var(--primary-green)';
                }
            };

            function initMapExplorer() {
                if (typeof L === 'undefined') return;
                if (explorerMap) { explorerMap.invalidateSize(); return; }
                const lat = EnteangadiConfig.location ? parseFloat(EnteangadiConfig.location.lat) : 9.94;
                const lng = EnteangadiConfig.location ? parseFloat(EnteangadiConfig.location.lng) : 76.27;
                explorerMap = L.map('map-canvas').setView([lat, lng], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(explorerMap);
                markersLayer = L.layerGroup().addTo(explorerMap);
                explorerMap.on('moveend', loadMapMarkers);
                loadMapMarkers();
            }

            async function loadMapMarkers() {
                if (!explorerMap || !markersLayer) return;
                const b = explorerMap.getBounds();
                const up = new URLSearchParams(window.location.search);
                try {
                    const res = await fetch(`../api/map_listings.php?min_lat=${b.getSouth()}&max_lat=${b.getNorth()}&min_lng=${b.getWest()}&max_lng=${b.getEast()}&category_id=${up.get('category_id') || ''}&search=${encodeURIComponent(up.get('search') || '')}`);
                    const data = await res.json();
                    if (!data.success) return;
                    markersLayer.clearLayers();
                    (data.products || []).forEach(p => {
                        if (!p.latitude || !p.longitude) return;
                        const priceFmt = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(p.price);
                        const imgHtml = p.main_image
                            ? `<img src="../${p.main_image}" loading="lazy" style="width:100%;height:80px;object-fit:cover;border-radius:8px;margin-bottom:6px;">`
                            : `<div style="width:100%;height:80px;display:flex;align-items:center;justify-content:center;background:#e2e8f0;border-radius:8px;margin-bottom:6px;"><i class="fa fa-image" style="color:#94a3b8;"></i></div>`;
                        const icon = L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style="background:var(--primary-green);color:#fff;font-weight:800;font-size:10px;padding:4px 8px;border-radius:12px;box-shadow:0 2px 4px rgba(0,0,0,.2);white-space:nowrap;border:1.5px solid #fff;">₹${Math.round(p.price).toLocaleString('en-IN')}</div>`,
                            iconSize: [60, 20], iconAnchor: [30, 10]
                        });
                        L.marker([parseFloat(p.latitude), parseFloat(p.longitude)], { icon })
                            .bindPopup(`<div style="width:150px;font-family:sans-serif;">${imgHtml}<div style="font-weight:800;color:var(--primary-green-dark);margin-bottom:2px;">${priceFmt}</div><div style="font-size:11px;font-weight:bold;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px;">${p.title}</div><a href="../product.php?id=${p.id}" style="display:block;text-align:center;background:var(--primary-green);color:#fff;text-decoration:none;font-size:11px;font-weight:bold;padding:4px;border-radius:6px;">View Details</a></div>`)
                            .addTo(markersLayer);
                    });
                } catch (e) {}
            }
        })();
    </script>

    <!-- Paginated Listings Feed -->
    <div id="home-listings-container">
        <?php if (empty($products)): ?>
            <div style="text-align:center;padding:40px 20px;background:var(--white);border-radius:var(--border-radius);box-shadow:var(--shadow-sm);border:1px solid var(--border-color);">
                <i class="fa fa-box-open" style="font-size:40px;color:var(--text-muted);margin-bottom:12px;"></i>
                <h3 style="color:var(--text-dark);margin-bottom:6px;">No products found</h3>
                <p style="color:var(--text-muted);margin:0;"><?= $category_filter ? 'Be the first one to post in this category!' : 'Be the first one to post an ad!' ?></p>
            </div>
        <?php else: ?>
            <div class="product-grid" id="home-product-grid" data-total="<?= $total_matching_ads ?>" data-loaded="<?= count($products) ?>" style="margin-bottom:24px;">
                <?php foreach ($products as $idx => $product) {
                    echo renderProductCard($product, $idx < 4);
                } ?>
            </div>
            <?php if ($total_matching_ads > count($products)): ?>
                <div id="feed-load-more-sentinel" style="text-align:center;padding:18px 0 32px;">
                    <button type="button" id="feed-load-more-btn" class="btn-secondary" style="padding:10px 26px;border-radius:20px;font-weight:700;font-size:13px;">
                        Load More Ads
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    (function () {
        const i18nLabels = {
            wanted: <?= json_encode(__('wanted')) ?>,
            for_rent: <?= json_encode(__('for_rent')) ?>,
            for_sale: <?= json_encode(__('for_sale')) ?>
        };
        let observer = null;

        function buildCardHtml(p) {
            const badge = p.type === 'buy'
                ? `<div class="badge-wanted">${i18nLabels.wanted}</div>`
                : (p.type === 'rent' ? `<div class="badge-rent">${i18nLabels.for_rent}</div>` : `<div class="badge-selling">${i18nLabels.for_sale}</div>`);
            const adminBadge = Number(p.is_admin_posted) ? `<div class="badge-admin-posted" style="position:absolute;top:10px;right:10px;background:linear-gradient(135deg,#059669 0%,#10b981 100%);color:#fff;padding:4px 9px;border-radius:8px;font-size:9px;font-weight:800;text-transform:uppercase;z-index:10;display:flex;align-items:center;gap:4px;"><i class="fa fa-shield-alt" style="font-size:8px;"></i> Posted by Admin</div>` : '';
            const imgHtml = p.main_image
                ? `<img src="../${p.main_image}" loading="lazy" decoding="async" alt="" class="product-card-image" onerror="window.handleBrokenProductImg && window.handleBrokenProductImg(this, ${Number(p.id) || 0})">`
                : `<div class="product-card-image" style="display:flex;align-items:center;justify-content:center;background:#e0e0e0;"><i class="fa fa-image" style="font-size:40px;color:#999;"></i></div>`;
            const numPrice = Number(p.price || 0);
            const priceFmt = numPrice.toLocaleString('en-IN', { minimumFractionDigits: numPrice % 1 === 0 ? 0 : 2, maximumFractionDigits: 2 });
            const dt = p.created_at ? new Date(p.created_at.replace(' ', 'T')).toLocaleDateString('en-US', { month: 'short', day: '2-digit' }) : '';
            let distHtml = '';
            if (p.distance !== undefined && p.distance !== null) {
                const d = parseFloat(p.distance);
                distHtml = `<span style="font-size:11px;font-weight:700;color:var(--primary-green);margin-left:auto;">🚴 ${d < 1 ? Math.round(d * 1000) + ' m' : d.toFixed(1) + ' km'}</span>`;
            }
            const safeTitle = String(p.title || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const safeCat = String(p.category_name || 'Uncategorized').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            return `<a href="../product.php?id=${p.id}" class="product-card" style="position:relative;">${badge}${adminBadge}${imgHtml}<div class="product-card-content"><div class="product-card-price">₹${priceFmt}</div><div class="product-card-title">${safeTitle}</div><div class="product-card-meta" style="display:flex;align-items:center;width:100%;"><span>${safeCat}</span><span style="margin-left:8px;">${dt}</span>${distHtml}</div></div></a>`;
        }

        window.initHomeFeedPager = function () {
            if (observer) { observer.disconnect(); observer = null; }
            const grid = document.getElementById('home-product-grid');
            const sentinel = document.getElementById('feed-load-more-sentinel');
            const btn = document.getElementById('feed-load-more-btn');
            if (!grid || !sentinel) return;

            let offset = parseInt(grid.dataset.loaded || '100', 10);
            const total = parseInt(grid.dataset.total || '0', 10);
            let busy = false;

            async function fetchNextBatch() {
                if (busy || offset >= total) return;
                busy = true;
                if (btn) btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading...';
                try {
                    const q = new URLSearchParams(window.location.search);
                    q.set('limit', '48');
                    q.set('offset', String(offset));
                    q.set('_ts', String(Date.now()));
                    const res = await fetch(`../api/products.php?${q.toString()}`);
                    const data = await res.json();
                    if (data.success && Array.isArray(data.products) && data.products.length > 0) {
                        grid.insertAdjacentHTML('beforeend', data.products.map(buildCardHtml).join(''));
                        offset += data.products.length;
                        grid.dataset.loaded = String(offset);
                        if (window.EnteangadiPreloader && typeof window.EnteangadiPreloader.scanLinks === 'function') {
                            window.EnteangadiPreloader.scanLinks(grid);
                        }
                    }
                    if (!data.has_more || offset >= total) {
                        sentinel.remove();
                        if (observer) observer.disconnect();
                        return;
                    }
                    if (btn) btn.textContent = 'Load More Ads';
                } catch (e) {
                    if (btn) btn.textContent = 'Load More Ads';
                } finally {
                    busy = false;
                }
            }

            if (btn) btn.onclick = fetchNextBatch;
            if ('IntersectionObserver' in window) {
                observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting) fetchNextBatch();
                }, { rootMargin: '500px 0px' });
                observer.observe(sentinel);
            }
        };

        document.addEventListener('DOMContentLoaded', window.initHomeFeedPager);
    })();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>

