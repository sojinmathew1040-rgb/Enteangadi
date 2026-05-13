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
            <h2 class="section-title-premium">Browse categories</h2>
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
        <h2 class="section-title-premium">Fresh Recommendations</h2>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-state-card">
            <i class="fa fa-box-open empty-state-icon"></i>
            <h3 class="empty-state-title">No products found</h3>
            <p class="empty-state-subtitle">Be the first one to post an ad on Enteangadi!</p>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="user/post_ad.php" class="btn-primary mt-16">Post an Ad</a>
            <?php else: ?>
                <a href="login.php" class="btn-primary mt-16">Login to Post</a>
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
                        <?php if (!empty($product['expiry_date'])): ?>
                            <?php
                            $expiry_date = strtotime($product['expiry_date']);
                            $today = strtotime(date('Y-m-d'));
                            $days_left = round(($expiry_date - $today) / (60 * 60 * 24));

                            if ($days_left >= 0 && $days_left <= 2):
                                ?>
                                <div class="badge-expiring">
                                    <i class="fa fa-clock"></i>
                                    <?= ($days_left == 0) ? 'Expires Today' : 'Expiring Soon' ?>
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
                        <i class="fa<?= in_array($product['id'], $user_wishlist) ? 's' : 'r' ?> fa-heart <?= in_array($product['id'], $user_wishlist) ? 'active' : '' ?>"></i>
                    </div>

                    <div class="product-card-content">
                        <div class="product-card-price">
                            ₹<?= number_format($product['price'], (fmod($product['price'], 1) == 0) ? 0 : 2) ?></div>
                        <div class="product-card-title">
                            <?= htmlspecialchars($product['title']) ?>
                            <?php if ($product['is_verified']): ?>
                                <span class="verified-badge" title="Verified Listing"><i class="fa fa-check"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-card-meta">
                            <span><i class="fa fa-map-marker-alt meta-icon-sm"></i>
                                <?= htmlspecialchars($product['location_name'] ?? 'Unknown') ?></span>
                            <?php if (isset($product['distance'])): ?>
                                <span class="distance-badge"><?= round($product['distance'], 1) ?> km away</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    const sessionUserId = <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null' ?>;
</script>
<script src="assets/js/index.js"></script>

<?php require_once 'includes/footer.php'; ?>