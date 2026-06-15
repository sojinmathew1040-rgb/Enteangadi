<?php
require_once '../config.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch recently viewed products from cookie
$recently_viewed_products = [];
if (isset($_COOKIE['recently_viewed'])) {
    $rv_ids = json_decode($_COOKIE['recently_viewed'], true);
    if (is_array($rv_ids) && !empty($rv_ids)) {
        // Limit to last 15
        $rv_ids = array_slice($rv_ids, 0, 15);
        $placeholders = implode(',', array_fill(0, count($rv_ids), '?'));
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
            $rv_stmt->execute($rv_ids);
            $rv_fetched = $rv_stmt->fetchAll();
            
            // Sort to match cookie order (most recent first)
            $rv_map = [];
            foreach ($rv_fetched as $p) {
                $rv_map[$p['id']] = $p;
            }
            foreach ($rv_ids as $id) {
                if (isset($rv_map[$id])) {
                    $recently_viewed_products[] = $rv_map[$id];
                }
            }
        } catch (PDOException $e) {
            $recently_viewed_products = [];
        }
    }
}

// Fetch user's wishlist IDs
$wishlist_ids = [];
if (isset($_SESSION['user_id'])) {
    $wish_stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wish_stmt->execute([$user_id]);
    $wishlist_ids = $wish_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<div class="container" style="padding-top: 24px; padding-bottom: 40px;">
    <!-- Header with Back Button -->
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
        <a href="index.php" style="color: var(--text-dark, #1e293b); font-size: 20px; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: var(--background, #f8fafc); border: 1px solid var(--border-color, #e2e8f0); transition: all 0.2s;"><i class="fa fa-arrow-left"></i></a>
        <h2 style="color: var(--primary-green-dark, #1b5e20); margin: 0; font-size: 22px; font-weight: 800;">Recently viewed items</h2>
    </div>

    <?php if (empty($recently_viewed_products)): ?>
        <div style="text-align: center; padding: 60px 20px; background: var(--white, #fff); border-radius: var(--border-radius, 16px); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color, #e2e8f0); max-width: 600px; margin: 0 auto;">
            <i class="fa fa-history" style="font-size: 48px; color: var(--text-muted, #94a3b8); margin-bottom: 16px;"></i>
            <h3 style="color: var(--text-dark, #1e293b); margin-bottom: 8px;">No recently viewed items</h3>
            <p style="color: var(--text-muted, #64748b); font-size: 14px;">Products you view will show up here to help you find them quickly.</p>
            <a href="index.php" class="btn-primary" style="display: inline-block; margin-top: 16px;">Browse Products</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($recently_viewed_products as $rv_p): 
                $is_wishlisted = in_array($rv_p['id'], $wishlist_ids);
            ?>
                <div class="product-card-wrapper" id="product-card-<?= $rv_p['id'] ?>" style="position: relative;">
                    <a href="../product.php?id=<?= $rv_p['id'] ?>" class="product-card">
                        <?php if ($rv_p['type'] == 'buy'): ?>
                            <div class="badge-wanted">Wanted</div>
                        <?php else: ?>
                            <div class="badge-selling">For Sale</div>
                        <?php endif; ?>
                        
                        <?php if (!empty($rv_p['is_verified'])): ?>
                            <div style="position: absolute; top: 10px; left: 10px; background: var(--primary-green, #1B5E20); color: #fff; font-size: 8px; font-weight: 800; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; z-index: 2; letter-spacing: 0.5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 2px;"><i class="fa fa-check-double" style="font-size: 6px;"></i> Verified</div>
                        <?php endif; ?>

                        <?php if ($rv_p['main_image']): ?>
                            <img src="../<?= htmlspecialchars($rv_p['main_image']) ?>"
                                alt="<?= htmlspecialchars($rv_p['title']) ?>" class="product-card-image">
                        <?php else: ?>
                            <div class="product-card-image"
                                style="display: flex; align-items: center; justify-content: center; background: #e0e0e0;">
                                <i class="fa fa-image" style="font-size: 40px; color: #999;"></i>
                            </div>
                        <?php endif; ?>

                        <div class="product-card-content">
                            <div class="product-card-price">
                                ₹<?= number_format($rv_p['price'], (fmod($rv_p['price'], 1) == 0) ? 0 : 2) ?></div>
                            <div class="product-card-title"><?= htmlspecialchars($rv_p['title']) ?></div>
                            <div class="product-card-meta">
                                <span><i class="fa fa-map-marker-alt" style="font-size: 10px; color: var(--primary-green, #1B5E20);"></i>
                                    <?= htmlspecialchars($rv_p['location_name'] ?? 'Unknown') ?></span>
                                <span><?= date('d M', strtotime($rv_p['created_at'])) ?></span>
                            </div>
                        </div>
                    </a>

                    <!-- Wishlist Heart Button -->
                    <div class="wishlist-btn" onclick="toggleRVWishlist(event, <?= $rv_p['id'] ?>)" id="rv-wish-<?= $rv_p['id'] ?>"
                        style="position: absolute; top: 10px; right: 10px; z-index: 2; background: rgba(255,255,255,0.9); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.1); color: <?= $is_wishlisted ? '#ef4444' : '#64748b' ?>;">
                        <i class="fa<?= $is_wishlisted ? 's' : 'r' ?> fa-heart" style="font-size: 15px;"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.product-card-wrapper button:hover {
    transform: scale(1.1);
}
</style>

<script>
async function toggleRVWishlist(event, productId) {
    event.preventDefault();
    event.stopPropagation();
    
    const btn = document.getElementById('rv-wish-' + productId);
    if (!btn) return;
    
    const icon = btn.querySelector('i');
    
    try {
        const formData = new FormData();
        formData.append('product_id', productId);
        
        const resp = await fetch('toggle_wishlist.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await resp.json();
        if (data.status === 'success') {
            if (data.action === 'added') {
                btn.style.color = '#ef4444';
                icon.className = 'fas fa-heart';
            } else {
                btn.style.color = '#64748b';
                icon.className = 'far fa-heart';
            }
        } else {
            alert(data.message || "Failed to update wishlist");
        }
    } catch(e) {
        console.error(e);
        alert("Connection error");
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
