<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($product_id && $action) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE products SET status = 'active', is_verified = 1 WHERE id = ?");
            if ($stmt->execute([$product_id])) {
                $success = "Product approved successfully.";
            }
        } elseif ($action === 'reject') {
            // We mark as deleted for now
            $stmt = $pdo->prepare("UPDATE products SET status = 'deleted' WHERE id = ?");
            if ($stmt->execute([$product_id])) {
                $success = "Product rejected and hidden.";
            }
        }
    }
}

// Fetch pending products
$stmt = $pdo->query("
    SELECT p.*, u.username, u.phone_number as user_phone, c.name as category_name,
    (SELECT image_path FROM product_images WHERE product_id = p.id LIMIT 1) as main_image,
    (SELECT GROUP_CONCAT(image_path) FROM product_images WHERE product_id = p.id) as all_images
    FROM products p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");
$pending_products = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <h2 style="margin: 0; font-weight: 800; letter-spacing: -0.5px;">Pending Approvals</h2>
    <div
        style="background: #e8f5e9; color: var(--primary-green-dark); padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 14px;">
        <?= count($pending_products) ?> Ads Waiting
    </div>
</div>

<?php if ($success): ?>
    <div
        style="background: #f0fdf4; color: #16a34a; padding: 12px; border-radius: 12px; margin-bottom: 20px; border-left: 5px solid #16a34a;">
        <i class="fa fa-check-circle"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div
    style="background: var(--white); border-radius: 20px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                <th style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted);">Product
                </th>
                <th style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted);">Details
                </th>
                <th style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted);">Seller Info
                </th>
                <th
                    style="padding: 16px 24px; font-weight: 600; font-size: 13px; color: var(--text-muted); text-align: right;">
                    Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pending_products)): ?>
                <tr>
                    <td colspan="4" style="padding: 48px; text-align: center; color: var(--text-muted);">
                        <i class="fa fa-mug-hot"
                            style="font-size: 32px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                        All caught up! No pending ads.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pending_products as $p): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                        <td style="padding: 20px 24px;">
                            <div style="display: flex; gap: 16px; align-items: center;">
                                <div
                                    style="width: 64px; height: 64px; border-radius: 12px; overflow: hidden; background: #eee; flex-shrink: 0;">
                                    <?php if ($p['main_image']): ?>
                                        <img src="../<?= htmlspecialchars($p['main_image']) ?>"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div
                                            style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ccc;">
                                            <i class="fa fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: var(--text-dark); margin-bottom: 4px;">
                                        <?= htmlspecialchars($p['title']) ?>
                                    </div>
                                    <div style="font-size: 12px; font-weight: 700; color: var(--primary-green);">
                                        #<?= htmlspecialchars($p['unique_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 20px 24px;">
                            <div style="font-size: 13px; color: var(--text-dark); margin-bottom: 4px;">
                                <i class="fa fa-tag" style="width: 16px; color: #94a3b8;"></i>
                                <?= htmlspecialchars($p['category_name']) ?>
                            </div>
                            <div style="font-weight: 700; color: #1e293b;">₹<?= number_format($p['price'], 2) ?></div>
                            <div style="font-size: 11px; color: var(--text-muted);"><i class="fa fa-clock"></i>
                                <?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                        </td>
                        <td style="padding: 20px 24px;">
                            <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($p['username']) ?></div>
                            <div style="font-size: 13px; color: var(--text-muted);"><?= htmlspecialchars($p['user_phone']) ?>
                            </div>
                        </td>
                        <td style="padding: 20px 24px; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <button type="button" class="btn-secondary"
                                    style="padding: 8px 16px; font-size: 13px; border-radius: 8px;" onclick='viewAdDetails(<?= json_encode([
                                        "title" => $p["title"],
                                        "unique_id" => $p["unique_id"],
                                        "description" => $p["description"],
                                        "price" => number_format($p["price"], 2),
                                        "location" => $p["location_name"],
                                        "category" => $p["category_name"],
                                        "images" => explode(",", $p["all_images"])
                                    ]) ?>)'>
                                    <i class="fa fa-eye"></i> Details
                                </button>
                                <form method="POST" style="margin: 0;"
                                    onsubmit="return confirm('Approve this ad? It will go live immediately.');">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-primary"
                                        style="padding: 8px 16px; font-size: 13px; border-radius: 8px; background: #16a34a; border-color: #16a34a;">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="margin: 0;"
                                    onsubmit="return confirm('Reject this ad? It will be hidden from the user.');">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-secondary"
                                        style="padding: 8px 16px; font-size: 13px; border-radius: 8px; color: #dc2626; border-color: #fecaca; background: #fef2f2;">
                                        <i class="fa fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Ad Details Modal -->
<div id="adDetailsModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div
        style="background: white; width: 90%; max-width: 600px; max-height: 85vh; border-radius: 24px; overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--shadow-lg);">
        <div
            style="padding: 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 id="modalTitle" style="margin: 0; font-size: 18px; font-weight: 800;"></h3>
                <div id="modalId"
                    style="font-size: 12px; color: var(--primary-green); font-weight: 700; margin-top: 2px;"></div>
            </div>
            <button onclick="closeModal()"
                style="background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center;"><i
                    class="fa fa-times"></i></button>
        </div>

        <div style="padding: 24px; overflow-y: auto; flex: 1;">
            <div id="modalImages"
                style="display: flex; gap: 10px; overflow-x: auto; padding-bottom: 15px; margin-bottom: 20px; scrollbar-width: thin;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                <div>
                    <label
                        style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Price</label>
                    <div id="modalPrice" style="font-weight: 700; color: var(--text-dark); font-size: 18px;"></div>
                </div>
                <div>
                    <label
                        style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 4px;">Location</label>
                    <div id="modalLocation" style="font-weight: 600; color: var(--text-dark);"><i
                            class="fa fa-map-marker-alt" style="color: #ef4444; margin-right: 4px;"></i> <span></span>
                    </div>
                </div>
            </div>

            <label
                style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 8px;">Description</label>
            <div id="modalDescription"
                style="background: #f8fafc; padding: 16px; border-radius: 12px; font-size: 14px; line-height: 1.6; color: #334155; white-space: pre-wrap;">
            </div>
        </div>

        <div style="padding: 20px 24px; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: right;">
            <button onclick="closeModal()" class="btn-secondary"
                style="padding: 10px 24px; border-radius: 10px; font-weight: 600;">Close Preview</button>
        </div>
    </div>
</div>

<script>
    function viewAdDetails(data) {
        document.getElementById('modalTitle').innerText = data.title;
        document.getElementById('modalId').innerText = '#' + data.unique_id;
        document.getElementById('modalPrice').innerText = '₹ ' + data.price;
        document.getElementById('modalLocation').querySelector('span').innerText = data.location;
        document.getElementById('modalDescription').innerText = data.description;

        const imagesContainer = document.getElementById('modalImages');
        imagesContainer.innerHTML = '';
        data.images.forEach(img => {
            if (img) {
                const div = document.createElement('div');
                div.style.minWidth = '120px';
                div.style.height = '120px';
                div.style.borderRadius = '12px';
                div.style.overflow = 'hidden';
                div.style.border = '1px solid #e2e8f0';
                div.innerHTML = `<img src="../${img}" style="width: 100%; height: 100%; object-fit: cover;">`;
                imagesContainer.appendChild(div);
            }
        });

        document.getElementById('adDetailsModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('adDetailsModal').style.display = 'none';
    }

    // Close on click outside
    window.onclick = function (event) {
        const modal = document.getElementById('adDetailsModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>