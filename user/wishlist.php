<?php
require_once '../config.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../guest/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch wishlisted products
try {
    $sql = "SELECT p.*, c.name as category_name, 
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image 
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE w.user_id = ? AND p.status = 'active'
            ORDER BY w.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Fetch user's wishlist IDs for the heart icons
$user_wishlist = array_column($products, 'id');
?>

<div class="container">
    <div style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="color: var(--primary-green-dark); font-size: 28px; font-weight: 700;">My Wishlist</h2>
    </div>

    <?php if (empty($products)): ?>
        <div
            style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
            <i class="fa fa-heart" style="font-size: 48px; color: #eee; margin-bottom: 16px;"></i>
            <h3 style="color: var(--text-dark); margin-bottom: 8px;">Your wishlist is empty</h3>
            <p style="color: var(--text-muted);">Explore products and add them to your wishlist to see them here.</p>
            <a href="../index.php" class="btn-primary" style="display: inline-block; margin-top: 16px;">Browse Products</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card-wrapper" id="product-card-<?= $product['id'] ?>" style="position: relative;">
                    <a href="../product.php?id=<?= $product['id'] ?>" class="product-card">
                        <?php if ($product['main_image']): ?>
                            <img src="../<?= htmlspecialchars($product['main_image']) ?>"
                                alt="<?= htmlspecialchars($product['title']) ?>" class="product-card-image">
                        <?php else: ?>
                            <div class="product-card-image"
                                style="display: flex; align-items: center; justify-content: center; background: #e0e0e0;">
                                <i class="fa fa-image" style="font-size: 40px; color: #999;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="product-card-content">
                            <div class="product-card-price">
                                ₹<?= number_format($product['price'], (fmod($product['price'], 1) == 0) ? 0 : 2) ?></div>
                            <div class="product-card-title"><?= htmlspecialchars($product['title']) ?></div>
                            <div class="product-card-meta">
                                <span><i class="fa fa-map-marker-alt" style="font-size: 10px;"></i>
                                    <?= htmlspecialchars($product['location_name'] ?? 'Unknown') ?></span>
                            </div>
                        </div>
                    </a>

                    <div class="wishlist-btn" onclick="removeFromWishlist(event, <?= $product['id'] ?>)"
                        style="position: absolute; top: 10px; right: 10px; z-index: 2; background: rgba(255,255,255,0.9); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <i class="fas fa-heart" style="color: var(--primary-green); font-size: 16px;"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    async function removeFromWishlist(event, productId) {
        event.preventDefault();
        event.stopPropagation();

        if (!confirm('Remove this product from your wishlist?')) return;

        const card = document.getElementById('product-card-' + productId);

        try {
            const formData = new FormData();
            formData.append('product_id', productId);

            const resp = await fetch('toggle_wishlist.php', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();
            if (data.status === 'success' && data.action === 'removed') {
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    if (document.querySelectorAll('.product-card-wrapper').length === 0) {
                        location.reload();
                    }
                }, 300);
            }
        } catch (e) {
            console.error(e);
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>