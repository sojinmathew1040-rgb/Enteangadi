<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Ad Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ad_id'])) {
    $delete_id = $_POST['delete_ad_id'];

    // Fetch images to delete them from server
    $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $img_stmt->execute([$delete_id]);
    $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$delete_id, $user_id])) {
        foreach ($images as $img) {
            if (file_exists('../' . $img)) {
                unlink('../' . $img);
            }
        }
        $success = "Advertisement permanently deleted.";
    } else {
        $error = "Failed to delete advertisement.";
    }
}

// Fetch user's ads
$ad_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, 
    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.user_id = ? AND p.status = 'active'
    ORDER BY p.created_at DESC");
$ad_stmt->execute([$user_id]);
$my_ads = $ad_stmt->fetchAll();

$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/Enteangadi";
require_once '../includes/header.php';
?>

<div class="container" style="max-width: 800px; padding-top: 40px; padding-bottom: 40px;">
    <h2 style="color: var(--primary-green-dark); margin-bottom: 24px;">My Listings</h2>

    <?php if ($success): ?>
        <div
            style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (empty($my_ads)): ?>
        <div
            style="text-align: center; padding: 60px 20px; background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
            <i class="fa fa-box-open" style="font-size: 48px; color: #eee; margin-bottom: 16px;"></i>
            <h3 style="color: var(--text-dark); margin-bottom: 8px;">No active listings</h3>
            <p style="color: var(--text-muted);">You haven't posted any ads yet. Start selling today!</p>
            <a href="post_ad.php" class="btn-primary"
                style="display: inline-block; margin-top: 24px; padding: 12px 32px;">Post an Ad</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
            <?php foreach ($my_ads as $ad): ?>
                <div class="product-card"
                    style="position: relative; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
                    <a href="../product.php?id=<?= $ad['id'] ?>" style="text-decoration: none; color: inherit; display: block;">
                        <?php if ($ad['type'] == 'buy'): ?>
                            <div class="badge-wanted">Wanted</div>
                        <?php else: ?>
                            <div class="badge-selling">For Sale</div>
                        <?php endif; ?>
                        <?php if ($ad['main_image']): ?>
                            <img src="../<?= htmlspecialchars($ad['main_image']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>"
                                style="width: 100%; aspect-ratio: 4/3; object-fit: cover;">
                        <?php else: ?>
                            <div
                                style="width: 100%; aspect-ratio: 4/3; display: flex; align-items: center; justify-content: center; background: #f0f0f0; color: #999;">
                                <i class="fa fa-image" style="font-size: 40px;"></i>
                            </div>
                        <?php endif; ?>

                        <div style="padding: 16px;">
                            <div
                                style="font-size: 20px; font-weight: 700; color: var(--primary-green-dark); margin-bottom: 4px;">
                                ₹ <?= number_format($ad['price'], (fmod($ad['price'], 1) == 0) ? 0 : 2) ?></div>
                            <div
                                style="font-size: 15px; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 8px;">
                                <?= htmlspecialchars($ad['title']) ?>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted);">
                                <span><?= htmlspecialchars($ad['category_name'] ?? 'Uncategorized') ?></span>
                                <span><?= date('M d', strtotime($ad['created_at'])) ?></span>
                            </div>
                        </div>
                    </a>

                    <div
                        style="padding: 12px; border-top: 1px solid var(--border-color); background: #fafafa; display: flex; gap: 8px;">
                        <a href="edit_ad.php?id=<?= $ad['id'] ?>" class="btn-secondary"
                            style="flex: 1; padding: 8px; font-size: 13px; text-decoration: none; text-align: center; border: 1px solid var(--text-muted); color: var(--text-dark) !important; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 4px;">
                            <i class="fa fa-edit"></i> Edit
                        </a>
                        <form method="POST" action="my_ads.php" style="flex: 1;"
                            onsubmit="return confirm('Are you sure you want to delete this ad?');">
                            <input type="hidden" name="delete_ad_id" value="<?= $ad['id'] ?>">
                            <button type="submit" class="btn-danger"
                                style="width: 100%; padding: 8px; font-size: 13px; background: transparent; color: var(--danger) !important; border: 1px solid var(--danger); border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px;">
                                <i class="fa fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>