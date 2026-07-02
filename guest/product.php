<?php
require_once '../config.php';

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.*, u.username, u.phone_number, c.name as category_name 
        FROM products p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: index.php");
        exit;
    }

    // Track recently viewed products in cookies
    $recently_viewed = [];
    if (isset($_COOKIE['recently_viewed'])) {
        $recently_viewed = json_decode($_COOKIE['recently_viewed'], true);
        if (!is_array($recently_viewed)) {
            $recently_viewed = [];
        }
    }
    if (($key = array_search($product_id, $recently_viewed)) !== false) {
        unset($recently_viewed[$key]);
    }
    array_unshift($recently_viewed, $product_id);
    $recently_viewed = array_slice($recently_viewed, 0, 15);
    enteangadi_set_cookie('recently_viewed', json_encode($recently_viewed), time() + (86400 * 30));

    $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $img_stmt->execute([$product_id]);
    $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Query Suggested / Related Products
    $suggested_products = [];
    try {
        $sug_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN (
                SELECT product_id, MIN(id) as min_img_id 
                FROM product_images 
                GROUP BY product_id
            ) pim ON p.id = pim.product_id
            LEFT JOIN product_images pi ON pim.min_img_id = pi.id
            WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
            ORDER BY p.created_at DESC LIMIT 8");
        $sug_stmt->execute([$product['category_id'], $product_id]);
        $suggested_products = $sug_stmt->fetchAll();
        
        if (count($suggested_products) < 4) {
            $needed = 8 - count($suggested_products);
            $exclude_ids = array_merge([$product['id']], array_column($suggested_products, 'id'));
            $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
            
            $fallback_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, pi.image_path as main_image 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN (
                    SELECT product_id, MIN(id) as min_img_id 
                    FROM product_images 
                    GROUP BY product_id
                ) pim ON p.id = pim.product_id
                LEFT JOIN product_images pi ON pim.min_img_id = pi.id
                WHERE p.status = 'active' AND p.id NOT IN ($placeholders)
                ORDER BY p.created_at DESC LIMIT " . (int)$needed);
            
            $fallback_stmt->execute($exclude_ids);
            $suggested_products = array_merge($suggested_products, $fallback_stmt->fetchAll());
        }
    } catch (Exception $ex) {
        $suggested_products = [];
    }

} catch (PDOException $e) {
    $product = null;
}

require_once '../includes/header.php';
?>

<div class="container">
    <?php if ($product): ?>
        <div class="product-detail-container">
            <div class="product-detail-image">
                <?php if (!empty($images)): ?>
                    <div style="position: relative;">
                        <?php if ($product['type'] == 'buy'): ?>
                            <div class="badge-wanted"
                                style="top: 16px; left: 16px; font-size: 12px; padding: 6px 14px; background: linear-gradient(135deg, #FFB300 0%, #FF8F00 100%); color: white; border-radius: 6px; font-weight: 800; position: absolute; z-index: 10;">
                                Wanted</div>
                        <?php else: ?>
                            <div class="badge-selling"
                                style="top: 16px; left: 16px; font-size: 12px; padding: 6px 14px; background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%); color: white; border-radius: 6px; font-weight: 800; position: absolute; z-index: 10;">
                                For Sale</div>
                        <?php endif; ?>
                        <img src="../<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($product['title']) ?>"
                            style="width: 100%; border-radius: var(--border-radius); aspect-ratio: 4/3; object-fit: contain; background: #f0f0f0;">
                    </div>

                    <?php if (count($images) > 1): ?>
                        <div style="display: flex; gap: 8px; margin-top: 8px; overflow-x: auto;">
                            <?php foreach ($images as $img): ?>
                                <img src="../<?= htmlspecialchars($img) ?>"
                                    style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover; cursor: pointer;"
                                    alt="thumbnail">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div
                        style="width: 100%; height: 400px; background: #e0e0e0; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-image" style="font-size: 64px; color: #999;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-detail-info">
                <h1 style="font-size: 32px; color: var(--text-dark); margin-bottom: 8px; font-weight: 700;">
                    <?= htmlspecialchars($product['title']) ?>
                </h1>

                <div
                    style="margin-bottom: 8px; font-size: 14px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">
                    <?= $product['type'] == 'buy' ? 'Budget' : 'Price' ?>
                </div>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary-green-dark); margin-bottom: 16px;">
                    ₹ <?= number_format($product['price'], (fmod($product['price'], 1) == 0) ? 0 : 2) ?>
                </div>

                <div
                    style="display: flex; justify-content: space-between; color: var(--text-muted); font-size: 14px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);">
                    <span><i class="fa fa-tag"></i>
                        <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></span>
                    <span><i class="fa fa-calendar"></i> <?= date('M d, Y', strtotime($product['created_at'])) ?></span>
                </div>

                <h3 style="font-size: 18px; margin-bottom: 12px; font-weight: 600;">Description</h3>
                <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 32px; white-space: pre-wrap;">
                    <?= htmlspecialchars($product['description']) ?>
                </p>

                <div
                    style="background: var(--background); padding: 24px; border-radius: var(--border-radius); text-align: center;">
                    <h3 style="font-size: 16px; margin-bottom: 8px;"><?= $product['type'] == 'buy' ? 'Buyer' : 'Seller' ?>
                        Details</h3>
                    <p style="font-size: 20px; font-weight: 600; color: var(--text-dark); margin-bottom: 16px;">
                        <?= htmlspecialchars($product['username']) ?>
                    </p>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php
                        $wa_number = preg_replace('/\D/', '', $product['phone_number']);
                        $wa_message = urlencode("Hi, I'm interested in your product: " . $product['title']);
                        $wa_url = "https://wa.me/{$wa_number}?text={$wa_message}";
                        ?>
                        <a href="<?= $wa_url ?>" target="_blank" class="whatsapp-btn">
                            <i class="fab fa-whatsapp" style="font-size: 24px;"></i> Chat on WhatsApp
                        </a>
                        <button onclick="reportProduct(<?= $product['id'] ?>)" class="btn-danger"
                            style="width: 100%; margin-top: 12px; background: transparent; color: var(--danger) !important; border: 1px solid var(--danger);">
                            <i class="fa fa-flag"></i> Report Ad
                        </button>
                    <?php else: ?>
                        <div style="padding: 16px; background: #fff3e0; border-radius: 8px; color: #e65100; font-size: 14px;">
                            <i class="fa fa-lock"></i> Please <a href="login.php"
                                style="color: #e65100; font-weight: bold; text-decoration: underline;">Login</a> to contact the
                            seller.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Suggested Products Section -->
            <?php if (!empty($suggested_products)): ?>
                <div style="margin-top: 40px; margin-bottom: 32px;">
                    <h3 style="color: var(--text-dark, #1e293b); font-size: 20px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-sparkles" style="color: var(--primary-green, #1B5E20);"></i> Suggested Products
                    </h3>
                    <div class="suggested-scroll-container" style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; scrollbar-width: none; -ms-overflow-style: none; scroll-behavior: smooth;">
                        <?php foreach ($suggested_products as $sug_p): 
                            $detailUrl = "product.php?id=" . $sug_p['id'];
                            $imgUrl = $sug_p['main_image'] ? "../" . htmlspecialchars($sug_p['main_image']) : null;
                        ?>
                            <a href="<?= $detailUrl ?>" class="suggested-product-card" style="text-decoration: none; flex: 0 0 auto; width: 160px; background: var(--white, #fff); border-radius: 16px; overflow: hidden; border: 1px solid var(--border-color, #e2e8f0); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; transition: all 0.3s ease;">
                                <!-- Image Area -->
                                <div style="position: relative; width: 100%; height: 110px; background: #f1f5f9;">
                                    <?php if ($sug_p['type'] == 'buy'): ?>
                                        <div class="badge-wanted" style="font-size: 7px; top: 6px; left: 6px; padding: 2px 6px;">Wanted</div>
                                    <?php elseif ($sug_p['type'] == 'rent'): ?>
                                        <div class="badge-rent" style="font-size: 7px; top: 6px; left: 6px; padding: 2px 6px;">Rent</div>
                                    <?php else: ?>
                                        <div class="badge-selling" style="font-size: 7px; top: 6px; left: 6px; padding: 2px 6px;">Sale</div>
                                    <?php endif; ?>
                                    <?php if ($imgUrl): ?>
                                        <img src="<?= $imgUrl ?>" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa fa-image" style="font-size: 24px; color: #cbd5e1;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Info Area -->
                                <div style="padding: 10px; display: flex; flex-direction: column; flex: 1; min-width: 0;">
                                    <div style="font-size: 14px; font-weight: 800; color: var(--primary-green-dark, #1b5e20); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px;">
                                        ₹<?= number_format($sug_p['price'], (fmod($sug_p['price'], 1) == 0) ? 0 : 2) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-dark, #1e293b); font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 32px; line-height: 1.3;">
                                        <?= htmlspecialchars($sug_p['title']) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <style>
                .suggested-scroll-container::-webkit-scrollbar {
                    display: none;
                }
                .suggested-product-card:hover {
                    transform: translateY(-2px);
                    box-shadow: var(--shadow-md, 0 10px 15px -3px rgba(0, 0, 0, 0.1));
                    border-color: var(--primary-green, #1B5E20) !important;
                }
                </style>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p>Product not found.</p>
    <?php endif; ?>
</div>

<!-- Report Modal -->
<div id="reportModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: var(--border-radius); width: 100%; max-width: 400px;">
        <h3 style="margin-bottom: 16px;">Report Product</h3>
        <form id="reportForm">
            <input type="hidden" id="report_product_id" name="product_id">
            <div class="form-group">
                <label>Reason</label>
                <textarea id="report_reason" name="reason" class="form-control" rows="4" required></textarea>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeReportModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" style="background-color: var(--danger);">Submit
                    Report</button>
            </div>
        </form>
    </div>
</div>

<script>
    function reportProduct(id) {
        document.getElementById('report_product_id').value = id;
        document.getElementById('reportModal').style.display = 'flex';
    }

    function closeReportModal() {
        document.getElementById('reportModal').style.display = 'none';
        document.getElementById('report_reason').value = '';
    }

    document.getElementById('reportForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const productId = document.getElementById('report_product_id').value;
        const reason = document.getElementById('report_reason').value;

        fetch('../user/api_report.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&reason=${encodeURIComponent(reason)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Report submitted successfully.');
                    closeReportModal();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred.');
            });
    });
</script>

<?php require_once '../includes/footer.php'; ?>