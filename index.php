<?php
require_once 'config.php';
require_once 'includes/header.php';

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
                        <img src="<?= htmlspecialchars($cat['photo_path']) ?>"
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
                            <img src="<?= htmlspecialchars($cat['photo_path']) ?>"
                                style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover; margin-right: 6px;">
                        <?php endif; ?>
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

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
                <a href="user/post_ad.php" class="btn-primary" style="display: inline-block; margin-top: 16px;">Post an Ad</a>
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
                        <img src="<?= htmlspecialchars($product['main_image']) ?>" class="product-card-image">
                    <?php else: ?>
                        <div class="product-card-image"
                            style="display: flex; align-items: center; justify-content: center; background: #e0e0e0;">
                            <i class="fa fa-image" style="font-size: 40px; color: #999;"></i>
                        </div>
                    <?php endif; ?>

                    <div class="wishlist-btn" onclick="toggleWishlist(event, <?= $product['id'] ?>)"
                        style="position: absolute; top: 10px; right: 10px; z-index: 2; background: rgba(255,255,255,0.9); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <i class="fa<?= in_array($product['id'], $user_wishlist) ? 's' : 'r' ?> fa-heart"
                            style="color: <?= in_array($product['id'], $user_wishlist) ? 'var(--primary-green)' : '#999' ?>;"></i>
                    </div>

                    <div class="product-card-content">
                        <div class="product-card-price">
                            ₹<?= number_format($product['price'], (fmod($product['price'], 1) == 0) ? 0 : 2) ?></div>
                        <div class="product-card-title"><?= htmlspecialchars($product['title']) ?></div>
                        <div class="product-card-meta">
                            <span><i class="fa fa-map-marker-alt" style="font-size: 10px;"></i>
                                <?= htmlspecialchars($product['location_name'] ?? 'Unknown') ?></span>
                            <?php if (isset($product['distance'])): ?>
                                <span style="color: var(--primary-green); font-weight: 600;"><?= round($product['distance'], 1) ?>
                                    km away</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    async function toggleWishlist(event, productId) {
        event.preventDefault();
        event.stopPropagation();

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
                    icon.classList.remove('far'); icon.classList.add('fas'); icon.style.color = 'var(--primary-green)';
                } else {
                    icon.classList.remove('fas'); icon.classList.add('far'); icon.style.color = '#999';
                }
            }
        } catch (e) { console.error(e); }
    }
</script>

<?php require_once 'includes/footer.php'; ?>