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