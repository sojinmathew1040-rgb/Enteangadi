<?php
require_once 'config.php';

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.*, p.phone_number as product_phone, p.whatsapp_number as product_whatsapp, u.username, u.phone_number as user_phone, u.role, u.profile_picture, c.name as category_name 
        FROM products p 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        header("Location: index.php");
        exit;
    }

    // [AD ANALYTICS] Increment View Count
    $update_views = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $update_views->execute([$product_id]);

    // Security: Only allow active ads OR let owner/admin view pending/sold/expired
    $is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $product['user_id']);
    $is_admin = (isset($_SESSION['admin_id']));

    if ($product['status'] !== 'active' && !$is_owner && !$is_admin) {
        header("Location: index.php?error=unauthorized_view");
        exit;
    }

    $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $img_stmt->execute([$product_id]);
    $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Wishlist check
    $is_wishlisted = false;
    if (isset($_SESSION['user_id'])) {
        $wish_stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $wish_stmt->execute([$_SESSION['user_id'], $product_id]);
        $is_wishlisted = (bool) $wish_stmt->fetch();
    }

    // Admin Action: Toggle Verification
    if ($is_admin && isset($_POST['admin_action'])) {
        $new_status = ($_POST['admin_action'] === 'verify') ? 1 : 0;
        $v_stmt = $pdo->prepare("UPDATE products SET is_verified = ? WHERE id = ?");
        $v_stmt->execute([$new_status, $product_id]);
        header("Location: product.php?id=" . $product_id . "&success=status_updated");
        exit;
    }

} catch (PDOException $e) {
    $product = null;
}

require_once 'includes/header.php';
?>

<div class="container">
    <?php if ($product): ?>
        <div class="product-detail-container">
            <div class="product-detail-image">
                <?php if (!empty($images)): ?>
                    <div
                        style="position: relative; border-radius: var(--border-radius); overflow: hidden; background: #f0f0f0;">
                        <?php if ($product['type'] == 'buy'): ?>
                            <div class="badge-wanted"
                                style="top: 16px; left: 16px; font-size: 12px; padding: 6px 14px; background: linear-gradient(135deg, #FFB300 0%, #FF8F00 100%); color: white; border-radius: 6px; font-weight: 800; position: absolute; z-index: 10;">
                                Wanted</div>
                        <?php else: ?>
                            <div class="badge-selling"
                                style="top: 16px; left: 16px; font-size: 12px; padding: 6px 14px; background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%); color: white; border-radius: 6px; font-weight: 800; position: absolute; z-index: 10;">
                                For Sale</div>
                        <?php endif; ?>
                        <div class="image-carousel"
                            style="display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -ms-overflow-style: none; cursor: pointer;"
                            onscroll="updateImageCounter(this)" onclick="openLightbox()">
                            <?php foreach ($images as $index => $img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['title']) ?>"
                                    style="flex: 0 0 100%; width: 100%; scroll-snap-align: start; object-fit: contain; aspect-ratio: 4/3; border-radius: 0; cursor: zoom-in;">
                            <?php endforeach; ?>
                        </div>
                        <style>
                            .image-carousel::-webkit-scrollbar {
                                display: none;
                            }
                        </style>
                        <?php if (count($images) > 1): ?>
                            <div
                                style="position: absolute; bottom: 16px; right: 16px; background: rgba(0,0,0,0.6); color: white; padding: 4px 12px; border-radius: 16px; font-size: 14px; font-weight: 500; pointer-events: none;">
                                <span id="current-image-index">1</span> / <?= count($images) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Lightbox Modal -->
                    <div id="lightbox"
                        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.98); z-index: 9999; flex-direction: column;">

                        <!-- Close Button -->
                        <span
                            style="position: absolute; top: 20px; right: 25px; color: #fff; font-size: 35px; font-weight: 300; cursor: pointer; z-index: 10002; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.1); border-radius: 50%;"
                            onclick="closeLightbox()">&times;</span>

                        <!-- Scrollable Image Container -->
                        <div id="lightbox-scroll-container"
                            style="width: 100%; height: 100%; display: flex; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; -ms-overflow-style: none;"
                            onscroll="syncLightboxCounter(this)">
                            <?php foreach ($images as $img): ?>
                                <div
                                    style="flex: 0 0 100%; height: 100%; display: flex; align-items: center; justify-content: center; scroll-snap-align: center;">
                                    <img src="<?= htmlspecialchars($img) ?>"
                                        style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Navigation Buttons (Hidden on mobile) -->
                        <button onclick="scrollLightbox(-1)" class="lightbox-nav"
                            style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.1); color: white; border: none; width: 50px; height: 50px; cursor: pointer; font-size: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10001;">&#10094;</button>
                        <button onclick="scrollLightbox(1)" class="lightbox-nav"
                            style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.1); color: white; border: none; width: 50px; height: 50px; cursor: pointer; font-size: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10001;">&#10095;</button>

                        <!-- Counter -->
                        <div id="lightbox-counter-label"
                            style="position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); color: white; font-size: 16px; font-weight: 500; background: rgba(0,0,0,0.5); padding: 6px 16px; border-radius: 20px;">
                        </div>

                        <style>
                            #lightbox-scroll-container::-webkit-scrollbar {
                                display: none;
                            }

                            @media (max-width: 768px) {
                                .lightbox-nav {
                                    display: none !important;
                                }
                            }
                        </style>
                    </div>

                    <script>
                        let currentLightboxIndex = 0;
                        const productImagesCount = <?= count($images) ?>;

                        function updateImageCounter(element) {
                            const scrollLeft = element.scrollLeft;
                            const clientWidth = element.clientWidth;
                            currentLightboxIndex = Math.round(scrollLeft / clientWidth);
                            const counterElement = document.getElementById('current-image-index');
                            if (counterElement) {
                                counterElement.innerText = currentLightboxIndex + 1;
                            }
                        }

                        function syncLightboxCounter(element) {
                            const scrollLeft = element.scrollLeft;
                            const clientWidth = element.clientWidth;
                            const index = Math.round(scrollLeft / clientWidth);
                            document.getElementById('lightbox-counter-label').innerText = `${index + 1} / ${productImagesCount}`;
                        }

                        function openLightbox() {
                            const lightbox = document.getElementById('lightbox');
                            lightbox.style.display = 'flex';
                            document.body.style.overflow = 'hidden';

                            const container = document.getElementById('lightbox-scroll-container');
                            const targetScroll = currentLightboxIndex * container.clientWidth;
                            container.scrollLeft = targetScroll;
                            syncLightboxCounter(container);
                        }

                        function closeLightbox() {
                            document.getElementById('lightbox').style.display = 'none';
                            document.body.style.overflow = 'auto';
                        }

                        function scrollLightbox(direction) {
                            const container = document.getElementById('lightbox-scroll-container');
                            const newIndex = Math.max(0, Math.min(productImagesCount - 1, Math.round(container.scrollLeft / container.clientWidth) + direction));
                            container.scrollTo({
                                left: newIndex * container.clientWidth,
                                behavior: 'smooth'
                            });
                        }

                        function scrollToIndex(index) {
                            const container = document.getElementById('lightbox-scroll-container');
                            container.scrollTo({
                                left: index * container.clientWidth,
                                behavior: 'smooth'
                            });
                        }

                        // Close on Escape key and Arrow keys
                        document.addEventListener('keydown', function (e) {
                            if (document.getElementById('lightbox').style.display === 'flex') {
                                if (e.key === "Escape") closeLightbox();
                                if (e.key === "ArrowRight") scrollLightbox(1);
                                if (e.key === "ArrowLeft") scrollLightbox(-1);
                            }
                        });
                    </script>
                <?php else: ?>
                    <div
                        style="width: 100%; aspect-ratio: 4/3; background: #e0e0e0; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-image" style="font-size: 64px; color: #999;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-detail-info">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <h1 style="font-size: 32px; color: var(--text-dark); margin-bottom: 8px; font-weight: 700; flex: 1;">
                        <?= htmlspecialchars($product['title']) ?>
                    </h1>
                    <div style="display: flex; gap: 8px; align-items: center; margin-left: 16px;">
                        <div class="wishlist-btn" onclick="toggleWishlist(event, <?= $product['id'] ?>)"
                            style="background: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                            <i class="fa<?= $is_wishlisted ? 's' : 'r' ?> fa-heart"
                                style="color: <?= $is_wishlisted ? 'var(--primary-green)' : '#999' ?>; font-size: 20px;"></i>
                        </div>
                        <div class="share-btn" onclick="shareProduct()"
                            style="background: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                            <i class="fa fa-share-alt" style="color: #6366f1; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>

                <div
                    style="font-size: 13px; font-weight: 700; color: var(--primary-green); margin-bottom: 12px; letter-spacing: 0.5px; display: flex; align-items: center; gap: 10px;">
                    <span>ID: <?= htmlspecialchars($product['unique_id']) ?></span>
                    <?php if ($product['is_verified']): ?>
                        <span class="verified-tag-premium">
                            <i class="fa fa-check-circle"></i> Verified Listing
                        </span>
                    <?php endif; ?>
                </div>

                <div
                    style="margin-bottom: 8px; font-size: 14px; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">
                    <?= $product['type'] == 'buy' ? 'Budget' : 'Price' ?>
                </div>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary-green-dark); margin-bottom: 16px;">
                    ₹ <?= number_format($product['price'], (fmod($product['price'], 1) == 0) ? 0 : 2) ?>
                </div>

                <?php if (!empty($product['expiry_date'])): ?>
                    <?php
                    $expiry = strtotime($product['expiry_date']);
                    $today = strtotime(date('Y-m-d'));
                    $is_expired = ($expiry < $today);
                    $is_today = ($expiry == $today);
                    $date_str = date('d M, Y', $expiry);
                    ?>
                    <div
                        style="margin-top: 12px; margin-bottom: 24px; padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 12px; background: <?= $is_expired ? '#ffebee' : ($is_today ? '#fff3e0' : '#fff8e1') ?>; color: <?= $is_expired ? '#c62828' : ($is_today ? '#e65100' : '#f57f17') ?>; border: 1px solid <?= $is_expired ? '#ffcdd2' : ($is_today ? '#ffe0b2' : '#ffecb3') ?>;">
                        <i class="fa <?= $is_expired ? 'fa-calendar-times' : 'fa-clock' ?>" style="font-size: 20px;"></i>
                        <div>
                            <span
                                style="display: block; font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.8; letter-spacing: 0.5px;">
                                <?= $is_expired ? 'Expired' : ($is_today ? 'Expires Today' : 'Best Before') ?>
                            </span>
                            <span style="font-size: 16px; font-weight: 700;"><?= $date_str ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <div
                    style="display: flex; justify-content: space-between; color: var(--text-muted); font-size: 14px; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color);">
                    <span><i class="fa fa-tag"></i>
                        <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></span>
                    <span>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= $product['latitude'] ?>,<?= $product['longitude'] ?>"
                            target="_blank" style="text-decoration: none; color: inherit;">
                            <i class="fa fa-map-marker-alt"></i>
                            <?= htmlspecialchars($product['location_name'] ?? 'Unknown Location') ?>
                        </a>
                    </span>
                    <span><i class="fa fa-calendar"></i> <?= date('M d, Y', strtotime($product['created_at'])) ?></span>
                </div>

                <h3 style="font-size: 18px; margin-bottom: 12px; font-weight: 600;">Description</h3>
                <p
                    style="color: var(--text-muted); line-height: 1.6; margin-bottom: 32px; white-space: pre-wrap; text-align: left;">
                    <?= htmlspecialchars($product['description']) ?>
                </p>

                <div
                    style="background: var(--background); padding: 24px; border-radius: var(--border-radius); text-align: center;">
                    <h3 style="font-size: 16px; margin-bottom: 16px;"><?= $product['type'] == 'buy' ? 'Buyer' : 'Seller' ?>
                        Details</h3>
                    <div
                        style="display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 24px;">
                        <?php
                        $seller_initial = strtoupper(substr($product['username'], 0, 1));
                        $is_admin = ($product['role'] === 'admin');
                        ?>
                        <?php if (!empty($product['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($product['profile_picture']) ?>" alt="Seller"
                                style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-green);">
                        <?php else: ?>
                            <div
                                style="width: 64px; height: 64px; border-radius: 50%; background: var(--primary-green); color: white; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold;">
                                <?= $is_admin ? 'E' : $seller_initial ?>
                            </div>
                        <?php endif; ?>

                        <div style="text-align: left;">
                            <p style="font-size: 20px; font-weight: 600; color: var(--text-dark); margin: 0;">
                                <?php if ($is_admin): ?>
                                    Enteangadi Official <i class="fa fa-check-circle"
                                        style="color: var(--primary-green); font-size: 16px;" title="Verified Admin"></i>
                                <?php else: ?>
                                    <?= htmlspecialchars($product['username']) ?>
                                <?php endif; ?>
                            </p>
                            <p style="font-size: 14px; color: var(--text-muted); margin: 4px 0 0 0;">Member since
                                <?= date('Y') ?>
                            </p>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['user_id'] == $product['user_id']): ?>
                            <div
                                style="padding: 16px; background: #e8f5e9; border-radius: 8px; color: var(--primary-green-dark); font-size: 14px; font-weight: 500;">
                                <i class="fa fa-info-circle"></i> This is your advertisement.
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px;">
                                <!-- In-app chat (default) -->
                                <a href="user/chat.php?user_id=<?= $product['user_id'] ?>&product_id=<?= $product['id'] ?>"
                                    class="btn-primary"
                                    style="text-decoration: none; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                    <i class="fa fa-comments" style="font-size: 20px;"></i> Chat in App
                                </a>

                                <!-- WhatsApp Chat (Optional) -->
                                <?php if (!empty($product['product_whatsapp'])): ?>
                                    <?php
                                    $wa_number = preg_replace('/\D/', '', $product['product_whatsapp']);
                                    $wa_message = urlencode("Hi, I'm interested in your product: " . $product['title']);
                                    $wa_url = "https://wa.me/{$wa_number}?text={$wa_message}";
                                    ?>
                                    <a href="<?= $wa_url ?>" target="_blank" class="whatsapp-btn"
                                        style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                                        <i class="fab fa-whatsapp" style="font-size: 20px;"></i> Chat on WhatsApp
                                    </a>
                                <?php endif; ?>

                                <!-- Phone Call (Optional) -->
                                <?php if (!empty($product['product_phone'])): ?>
                                    <a href="tel:<?= htmlspecialchars($product['product_phone']) ?>" class="btn-secondary"
                                        style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; background: #f0f0f0; color: #333;">
                                        <i class="fa fa-phone" style="font-size: 20px;"></i> Call
                                        <?= $product['type'] == 'buy' ? 'Buyer' : 'Seller' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <button onclick="reportProduct(<?= $product['id'] ?>)" class="btn-danger"
                                style="width: 100%; margin-top: 12px; background: transparent; color: var(--danger) !important; border: 1px solid var(--danger);">
                                <i class="fa fa-flag"></i> Report Ad
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="padding: 16px; background: #fff3e0; border-radius: 8px; color: #e65100; font-size: 14px;">
                            <i class="fa fa-lock"></i> Please <a href="login.php"
                                style="color: #e65100; font-weight: bold; text-decoration: underline;">Login</a> to contact the
                            seller.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Admin Verification Controls (Visible only to Admin) -->
                <?php if ($is_admin): ?>
                    <div
                        style="margin-top: 24px; padding: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; text-align: left;">
                        <h4
                            style="font-size: 15px; margin-bottom: 12px; color: var(--text-dark); display: flex; align-items: center; gap: 8px; font-weight: 700;">
                            <i class="fa fa-user-shield" style="color: #0284c7;"></i> Admin Management
                        </h4>
                        <form method="POST">
                            <?php if (!$product['is_verified']): ?>
                                <input type="hidden" name="admin_action" value="verify">
                                <button type="submit" class="btn-primary"
                                    style="width: 100%; background: #0284c7; border-color: #0284c7; font-size: 14px; padding: 12px; border-radius: 10px;">
                                    <i class="fa fa-check-circle"></i> Verify & Add Blue Tick
                                </button>
                                <p style="margin: 10px 0 0 0; font-size: 11px; color: var(--text-muted); text-align: center;">This
                                    listing was auto-approved. Click to verify it manually.</p>
                            <?php else: ?>
                                <input type="hidden" name="admin_action" value="unverify">
                                <button type="submit" class="btn-secondary"
                                    style="width: 100%; color: #dc2626; border-color: #fecaca; background: #fef2f2; font-size: 14px; padding: 12px; border-radius: 10px;">
                                    <i class="fa fa-times-circle"></i> Remove Verification
                                </button>
                                <p style="margin: 10px 0 0 0; font-size: 11px; color: #ef4444; text-align: center;">This ad is
                                    verified. Click to remove the trust badge.</p>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Safety Tips Section -->
                <div
                    style="margin-top: 24px; padding: 20px; background: #fffde7; border-radius: var(--border-radius); border-left: 4px solid #fbc02d;">
                    <h4
                        style="font-size: 15px; font-weight: 700; color: #f57f17; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-shield-alt"></i> Safety Tips for
                        <?= $product['type'] == 'buy' ? 'Sellers' : 'Buyers' ?>
                    </h4>
                    <ul style="padding-left: 20px; font-size: 13px; color: #5d4037; line-height: 1.6; text-align: left;">
                        <li>Meet the <?= $product['type'] == 'buy' ? 'buyer' : 'seller' ?> in a public place.</li>
                        <li>Inspect the item thoroughly before
                            <?= $product['type'] == 'buy' ? 'completing the sale' : 'paying' ?>.
                        </li>
                        <li>Avoid sharing personal information or paying in advance.</li>
                        <li>Use the Enteangadi chat for initial communication.</li>
                    </ul>
                </div>
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

        fetch('user/api_report.php', {
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

<script>
    async function toggleWishlist(event, productId) {
        event.preventDefault();

        <?php if (!isset($_SESSION['user_id'])): ?>
            window.location.href = 'guest/login.php';
            return;
        <?php endif; ?>

        const btn = event.currentTarget;
        const icon = btn.querySelector('i');

        try {
            const formData = new FormData();
            formData.append('product_id', productId);

            const resp = await fetch('user/toggle_wishlist.php', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();
            if (data.status === 'success') {
                if (data.action === 'added') {
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                    icon.style.color = 'var(--primary-green)';
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    icon.style.color = '#999';
                }
            } else {
                alert(data.message);
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function shareProduct() {
        const shareData = {
            title: '<?= addslashes($product['title']) ?> | Enteangadi',
            text: 'Check out this listing on Enteangadi: <?= addslashes($product['title']) ?>',
            url: window.location.href
        };

        if (navigator.share) {
            try {
                await navigator.share(shareData);
            } catch (err) {
                console.log('Share failed:', err);
            }
        } else {
            // Fallback for browsers without Web Share API
            const dummy = document.createElement('input');
            document.body.appendChild(dummy);
            dummy.value = window.location.href;
            dummy.select();
            document.execCommand('copy');
            document.body.removeChild(dummy);
            alert('Link copied to clipboard! You can now share it.');
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>