<?php
require_once '../config.php';

// Redirect if logged in
if (isset($_SESSION['user_id'])) {
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    $redirect_url = '../user/index.php';
    if (!empty($query_string)) {
        $redirect_url .= '?' . $query_string;
    }
    header("Location: " . $redirect_url);
    exit;
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

require_once '../includes/header.php';

$category_filter = $_GET['category_id'] ?? null;
$where_clause = "WHERE p.status = 'active'";
$params = [];

if ($category_filter) {
    $where_clause .= " AND (p.category_id = ? OR c.parent_id = ?)";
    $params[] = $category_filter;
    $params[] = $category_filter;
}

// Fetch products
try {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN (
            SELECT product_id, MIN(id) as min_img_id 
            FROM product_images 
            GROUP BY product_id
        ) pim ON p.id = pim.product_id
        LEFT JOIN product_images pi ON pim.min_img_id = pi.id
        $where_clause 
        ORDER BY p.created_at DESC");
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
            $parent_id = $category_filter; // L1 selected
        } else {
            $parent_id = $cat_info['parent_id']; // L2 selected
        }
        $child_stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
        $child_stmt->execute([$parent_id]);
        $l2_categories = $child_stmt->fetchAll();
    }
}
?>

<div class="container">
    <!-- Browse Categories section -->
    <div style="margin-bottom: 32px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="color: var(--text-dark); font-size: 24px; font-weight: 700;">Browse categories</h2>
            <?php if ($category_filter): ?>
                <a href="index.php"
                    style="font-size: 14px; color: var(--primary-green); text-decoration: none; font-weight: 600;">Clear
                    Filter</a>
            <?php endif; ?>
        </div>
        <div
            style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: none; -ms-overflow-style: none;">
            <?php foreach ($l1_categories as $cat): ?>
                <a href="index.php?category_id=<?= $cat['id'] ?>"
                    style="text-decoration: none; flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; width: 100px;">
                    <?php if ($cat['photo_path']): ?>
                        <img src="../<?= htmlspecialchars($cat['photo_path']) ?>" loading="lazy"
                            style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; margin-bottom: 8px; border: 2px solid <?= ($parent_id == $cat['id'] || $category_filter == $cat['id']) ? 'var(--primary-green)' : 'transparent' ?>; padding: 2px;">
                    <?php else: ?>
                        <div
                            style="width: 64px; height: 64px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; border: 2px solid <?= ($parent_id == $cat['id'] || $category_filter == $cat['id']) ? 'var(--primary-green)' : 'transparent' ?>;">
                            <i class="fa fa-th-large" style="font-size: 24px; color: #999;"></i>
                        </div>
                    <?php endif; ?>
                    <span
                        style="font-size: 13px; color: var(--text-dark); text-align: center; font-weight: <?= ($parent_id == $cat['id'] || $category_filter == $cat['id']) ? '700' : '500' ?>; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($l2_categories)): ?>
            <div
                style="display: flex; gap: 12px; overflow-x: auto; padding-top: 16px; padding-bottom: 8px; scrollbar-width: none; -ms-overflow-style: none; border-top: 1px solid #eaeaea; margin-top: 8px;">
                <?php foreach ($l2_categories as $cat): ?>
                    <a href="index.php?category_id=<?= $cat['id'] ?>"
                        style="text-decoration: none; flex: 0 0 auto; display: flex; align-items: center; padding: 6px 12px; border-radius: 20px; background: <?= ($category_filter == $cat['id']) ? 'var(--primary-green)' : '#f0f0f0' ?>; color: <?= ($category_filter == $cat['id']) ? 'white' : 'var(--text-dark)' ?>; font-size: 13px; font-weight: 500;">
                        <?php if ($cat['photo_path']): ?>
                            <img src="../<?= htmlspecialchars($cat['photo_path']) ?>"
                                style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover; margin-right: 6px;">
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
                <a href="../user/recently_viewed.php" style="font-size: 14px; color: var(--primary-green, #1B5E20); text-decoration: none; font-weight: 700;">View All</a>
            </div>
            <div class="recently-viewed-scroll-container" style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; scrollbar-width: none; -ms-overflow-style: none; scroll-behavior: smooth;">
                <?php foreach ($recently_viewed_products as $rv_p): ?>
                    <a href="product.php?id=<?= $rv_p['id'] ?>" class="recently-viewed-card" style="text-decoration: none; flex: 0 0 auto; width: 150px; background: var(--white, #fff); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color, #e2e8f0); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; transition: all 0.3s ease;">
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

    <div style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="color: var(--primary-green-dark); font-size: 28px; font-weight: 700;">Fresh Recommendations</h2>
    </div>

    <?php if (empty($products)): ?>
        <div
            style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
            <i class="fa fa-box-open" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;"></i>
            <h3 style="color: var(--text-dark); margin-bottom: 8px;">No products found</h3>
            <p style="color: var(--text-muted);">Be the first one to post an ad on Enteangadi!</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="../user/post_ad.php" class="btn-primary" style="display: inline-block; margin-top: 16px;">Post an
                    Ad</a>
            <?php else: ?>
                <a href="login.php" class="btn-primary" style="display: inline-block; margin-top: 16px;">Login to Post</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <a href="product.php?id=<?= $product['id'] ?>" class="product-card">
                    <?php if ($product['type'] == 'buy'): ?>
                        <div class="badge-wanted">Wanted</div>
                    <?php else: ?>
                        <div class="badge-selling">For Sale</div>
                    <?php endif; ?>
                    <?php if ($product['main_image']): ?>
                        <img src="../<?= htmlspecialchars($product['main_image']) ?>" loading="lazy"
                            alt="<?= htmlspecialchars($product['title']) ?>" class="product-card-image">
                    <?php else: ?>
                        <!-- Fallback image block -->
                        <div class="product-card-image"
                            style="display: flex; align-items: center; justify-content: center; background: #e0e0e0;">
                            <i class="fa fa-image" style="font-size: 40px; color: #999;"></i>
                        </div>
                    <?php endif; ?>

                    <div class="product-card-content">
                        <div class="product-card-price">₹ <?= number_format($product['price'], 2) ?></div>
                        <div class="product-card-title"><?= htmlspecialchars($product['title']) ?></div>
                        <div class="product-card-meta">
                            <span><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></span>
                            <span><?= date('M d', strtotime($product['created_at'])) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>