<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Helpers are now in includes/helpers.php

// Handle Ad Actions (Sold, Deactivate, Activate, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ad_action'])) {
    $ad_id = $_POST['target_ad_id'];
    $action = $_POST['ad_action'];

    $status_map = [
        'mark_sold' => 'sold',
        'deactivate' => 'inactive',
        'activate' => 'active',
        'delete' => 'deleted'
    ];

    if (isset($status_map[$action])) {
        $new_status = $status_map[$action];
        $reason = $_POST['status_reason'] ?? null;
        if ($reason === 'Other') {
            $reason = $_POST['custom_reason'] ?? 'Other';
        }

        $stmt = $pdo->prepare("UPDATE products SET status = ?, status_reason = ? WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$new_status, $reason, $ad_id, $user_id])) {

            // Re-compress images for Sold/Delete/Inactive to save space
            if ($new_status !== 'active') {
                $img_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
                $img_stmt->execute([$ad_id]);
                $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($images as $img) {
                    $fullPath = '../' . $img;
                    recompressTo50kb($fullPath);
                }
            }

            $success = "Ad " . str_replace('_', ' ', strtoupper($action)) . " successfully.";
        } else {
            $error = "Failed to update ad status.";
        }
    }
}

// Fetch user's ads (Active first, then Sold/Expired)
$ad_stmt = $pdo->prepare("SELECT p.*, c.name as category_name, 
    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image,
    (SELECT COUNT(*) FROM wishlist w WHERE w.product_id = p.id) as like_count
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.user_id = ? AND p.status != 'permanently_deleted'
    ORDER BY (p.status = 'active') DESC, p.created_at DESC");
$ad_stmt->execute([$user_id]);
$all_ads = $ad_stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container" style="max-width: 900px; padding: 40px 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
        <h2 style="color: var(--primary-green-dark); margin: 0; font-size: 28px; font-weight: 800;">My Dashboard</h2>
        <a href="post_ad.php" class="btn-primary"
            style="text-decoration: none; display: flex; align-items: center; gap: 8px; padding: 10px 20px;">
            <i class="fa fa-plus-circle"></i> Post New Ad
        </a>
    </div>

    <?php
    // Stylish Approval Notification Logic (Shows ONLY ONCE per approval)
    $newly_approved_stmt = $pdo->prepare("SELECT id FROM products WHERE user_id = ? AND status = 'active' AND is_notified = 0");
    $newly_approved_stmt->execute([$user_id]);
    $newly_approved_ads = $newly_approved_stmt->fetchAll(PDO::FETCH_COLUMN);
    $has_newly_approved = count($newly_approved_ads);

    if ($has_newly_approved > 0 && !isset($_GET['delete_ad_id'])):
        // Mark these as notified immediately so it won't show on next refresh
        $placeholders = implode(',', array_fill(0, $has_newly_approved, '?'));
        $update_notif = $pdo->prepare("UPDATE products SET is_notified = 1 WHERE id IN ($placeholders)");
        $update_notif->execute($newly_approved_ads);
        ?>
        <div
            style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #bbf7d0; padding: 24px; border-radius: 20px; margin-bottom: 32px; display: flex; align-items: center; gap: 20px; animation: slideDown 0.5s ease-out; position: relative;">
            <div
                style="width: 56px; height: 56px; background: var(--primary-green); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);">
                <i class="fa fa-rocket"></i>
            </div>
            <div>
                <h3 style="margin: 0 0 4px 0; color: var(--primary-green-dark); font-size: 18px; font-weight: 800;">Great
                    News! Your Ad is Live</h3>
                <p style="margin: 0; color: #166534; font-size: 14px; font-weight: 500;">Your advertisement has been
                    approved and is now visible to thousands of potential buyers on Enteangadi.</p>
            </div>
            <button onclick="this.parentElement.style.display='none'"
                style="position: absolute; top: 16px; right: 16px; background: none; border: none; color: #166534; cursor: pointer; font-size: 18px; opacity: 0.5;"><i
                    class="fa fa-times"></i></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success-light" style="margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-danger-light" style="margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if (empty($all_ads)): ?>
        <div
            style="text-align: center; padding: 80px 20px; background: var(--white); border-radius: 20px; box-shadow: var(--shadow-sm);">
            <div
                style="width: 80px; height: 80px; background: #f8f9fa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fa fa-box-open" style="font-size: 32px; color: #cbd5e1;"></i>
            </div>
            <h3 style="color: var(--text-dark); margin-bottom: 8px;">No listings found</h3>
            <p style="color: var(--text-muted); max-width: 300px; margin: 0 auto 24px;">You haven't posted any
                advertisements yet. Start selling on Enteangadi today!</p>
            <a href="post_ad.php" class="btn-primary"
                style="display: inline-block; padding: 12px 32px; text-decoration: none;">Post your first Ad</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
            <?php foreach ($all_ads as $ad):
                $is_active = ($ad['status'] === 'active');
                $is_sold = ($ad['status'] === 'sold');
                $is_expired = ($ad['status'] === 'expired');
                $is_pending = ($ad['status'] === 'pending');
                $is_inactive = ($ad['status'] === 'inactive');
                ?>
                <div class="product-card"
                    style="position: relative; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid <?= $is_active ? 'var(--border-color)' : '#eee' ?>; <?= (!$is_active && !$is_pending) ? 'filter: grayscale(0.9); opacity: 0.7;' : '' ?> transition: all 0.3s;">

                    <!-- Status Badge -->
                    <?php if ($is_sold): ?>
                        <div
                            style="position: absolute; top: 12px; left: 12px; background: #000; color: #fff; padding: 4px 12px; border-radius: 6px; font-size: 10px; font-weight: 800; z-index: 20; text-transform: uppercase; letter-spacing: 1px;">
                            SOLD</div>
                    <?php elseif ($is_expired): ?>
                        <div
                            style="position: absolute; top: 12px; left: 12px; background: #ef4444; color: #fff; padding: 4px 12px; border-radius: 6px; font-size: 10px; font-weight: 800; z-index: 20; text-transform: uppercase; letter-spacing: 1px;">
                            EXPIRED</div>
                        <div
                            style="position: absolute; top: 12px; left: 12px; background: #f59e0b; color: #fff; padding: 4px 12px; border-radius: 6px; font-size: 10px; font-weight: 800; z-index: 20; text-transform: uppercase; letter-spacing: 1px;">
                            PENDING REVIEW</div>
                    <?php elseif ($is_inactive): ?>
                        <div
                            style="position: absolute; top: 12px; left: 12px; background: #64748b; color: #fff; padding: 4px 12px; border-radius: 6px; font-size: 10px; font-weight: 800; z-index: 20; text-transform: uppercase; letter-spacing: 1px;">
                            DEACTIVATED</div>
                    <?php else: ?>
                        <div class="badge-selling" style="top: 12px; left: 12px;">ACTIVE</div>
                        <?php if (!empty($ad['expiry_date'])): ?>
                            <?php
                            $expiry_ts = strtotime($ad['expiry_date']);
                            $today_ts = strtotime(date('Y-m-d'));
                            if ($expiry_ts == $today_ts):
                                ?>
                                <div
                                    style="position: absolute; top: 38px; left: 12px; background: #f59e0b; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 800; z-index: 20; text-transform: uppercase;">
                                    EXPIRES TODAY</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <a href="<?= $is_active ? '../product.php?id=' . $ad['id'] : '#' ?>"
                        style="text-decoration: none; color: inherit; display: block; <?= !$is_active ? 'cursor: default;' : '' ?>">
                        <?php if ($ad['main_image']): ?>
                            <img src="../<?= htmlspecialchars($ad['main_image']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>"
                                style="width: 100%; aspect-ratio: 1; object-fit: cover;">
                        <?php else: ?>
                            <div
                                style="width: 100%; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; background: #f8f9fa; color: #cbd5e1;">
                                <i class="fa fa-image" style="font-size: 40px;"></i>
                            </div>
                        <?php endif; ?>

                        <div style="padding: 16px;">
                            <div
                                style="font-size: 18px; font-weight: 800; color: <?= $is_active ? 'var(--primary-green-dark)' : '#64748b' ?>; margin-bottom: 4px;">
                                ₹ <?= number_format($ad['price'], (fmod($ad['price'], 1) == 0) ? 0 : 2) ?>
                            </div>
                            <div
                                style="font-size: 14px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                                <?= htmlspecialchars($ad['title']) ?>
                                <?php if (!empty($ad['is_verified'])): ?>
                                    <span class="verified-badge" title="Verified Listing"><i class="fa fa-check"></i></span>
                                <?php endif; ?>
                            </div>
                            <div
                                style="display: flex; align-items: center; gap: 8px; font-size: 11px; color: var(--text-muted); margin-top: 8px;">
                                <span
                                    style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; display: flex; align-items: center; gap: 4px;">
                                    <i class="fa fa-eye"></i> <?= number_format($ad['views']) ?>
                                </span>
                                <span
                                    style="background: #fff1f2; padding: 2px 8px; border-radius: 4px; color: #be123c; display: flex; align-items: center; gap: 4px;">
                                    <i class="fa fa-heart"></i> <?= number_format($ad['like_count']) ?>
                                </span>
                                <span>&bull;</span>
                                <span><?= date('M d, Y', strtotime($ad['created_at'])) ?></span>
                            </div>
                        </div>
                    </a>

                    <div
                        style="padding: 12px; background: #fafafa; border-top: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php if ($is_active): ?>
                            <a href="edit_ad.php?id=<?= $ad['id'] ?>"
                                style="flex: 1; padding: 8px; font-size: 11px; text-decoration: none; text-align: center; background: white; border: 1px solid #cbd5e1; color: var(--text-dark) !important; border-radius: 6px; font-weight: 600; transition: all 0.2s;">
                                <i class="fa fa-edit"></i> Edit
                            </a>
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="target_ad_id" value="<?= $ad['id'] ?>">
                                <input type="hidden" name="ad_action" value="deactivate">
                                <button type="submit"
                                    style="width: 100%; padding: 8px; font-size: 11px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                    <i class="fa fa-eye-slash"></i> Hide
                                </button>
                            </form>
                            <div style="flex: 1; display: flex; gap: 8px;">
                                <button type="button" onclick="openFeedbackModal(<?= $ad['id'] ?>, 'mark_sold')"
                                    style="width: 100%; padding: 8px; font-size: 11px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                    <i class="fa fa-check"></i> Sold
                                </button>
                            </div>
                        <?php elseif ($is_pending): ?>
                            <div
                                style="flex: 1; text-align: center; color: #f59e0b; font-size: 11px; font-weight: 600; padding: 8px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px;">
                                <i class="fa fa-hourglass-half"></i> Under Review
                            </div>
                        <?php else: ?>
                            <!-- Re-activate button for Inactive/Sold/Expired -->
                            <form method="POST" style="flex: 1;">
                                <input type="hidden" name="target_ad_id" value="<?= $ad['id'] ?>">
                                <input type="hidden" name="ad_action" value="activate">
                                <button type="submit"
                                    style="width: 100%; padding: 8px; font-size: 11px; background: #0284c7; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                    <i class="fa fa-bolt"></i> Activate
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Delete option always available (except pending maybe) -->
                        <?php if (!$is_pending): ?>
                            <div style="flex: 1; display: flex; gap: 8px;">
                                <button type="button" onclick="openFeedbackModal(<?= $ad['id'] ?>, 'delete')"
                                    style="width: 100%; padding: 8px; font-size: 11px; background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }
</style>

<?php require_once '../includes/footer.php'; ?>

<!-- Feedback Modal -->
<div id="feedbackModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div
        style="background: white; padding: 32px; border-radius: 24px; width: 90%; max-width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
        <h3 id="feedbackTitle" style="margin-bottom: 8px; font-size: 20px; font-weight: 800; color: var(--text-dark);">
            Feedback</h3>
        <p id="feedbackSub" style="color: var(--text-muted); font-size: 14px; margin-bottom: 24px;">Please tell us why
            you are closing this listing.</p>

        <form method="POST" id="feedbackForm">
            <input type="hidden" name="target_ad_id" id="modal_ad_id">
            <input type="hidden" name="ad_action" id="modal_ad_action">

            <div class="form-group" style="margin-bottom: 20px;">
                <label
                    style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin-bottom: 12px; display: block;">SELECT
                    A REASON</label>
                <div id="reasonOptions" style="display: flex; flex-direction: column; gap: 10px;">
                    <!-- Options injected by JS -->
                </div>
            </div>

            <div id="customReasonWrapper" style="display: none; margin-bottom: 20px;">
                <label
                    style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; display: block;">PLEASE
                    SPECIFY</label>
                <textarea name="custom_reason" class="form-control" rows="3"
                    placeholder="Type your reason here..."></textarea>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 32px;">
                <button type="button" onclick="closeFeedbackModal()" class="btn-secondary"
                    style="flex: 1; padding: 12px; border-radius: 12px; font-weight: 600;">Cancel</button>
                <button type="submit" class="btn-primary" id="confirmBtn"
                    style="flex: 2; padding: 12px; border-radius: 12px; font-weight: 700; background: var(--primary-green);">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
    const reasons = {
        'mark_sold': ['Sold on Enteangadi', 'Sold elsewhere', 'Decided not to sell', 'Other'],
        'delete': ['Item no longer available', 'Mistake in listing', 'Privacy concerns', 'Other']
    };

    function openFeedbackModal(adId, action) {
        const modal = document.getElementById('feedbackModal');
        const optionsDiv = document.getElementById('reasonOptions');
        const title = document.getElementById('feedbackTitle');
        const confirmBtn = document.getElementById('confirmBtn');

        document.getElementById('modal_ad_id').value = adId;
        document.getElementById('modal_ad_action').value = action;

        title.innerText = action === 'mark_sold' ? 'Listing Sold' : 'Delete Listing';
        confirmBtn.style.background = action === 'mark_sold' ? 'var(--primary-green)' : '#ef4444';
        confirmBtn.style.borderColor = action === 'mark_sold' ? 'var(--primary-green)' : '#ef4444';

        optionsDiv.innerHTML = '';
        reasons[action].forEach((r, i) => {
            const label = document.createElement('label');
            label.style = "display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s;";
            label.innerHTML = `<input type="radio" name="status_reason" value="${r}" ${i === 0 ? 'checked' : ''} onchange="toggleCustomReason(this.value)"> <span style="font-size: 14px; font-weight: 600; color: #334155;">${r}</span>`;
            optionsDiv.appendChild(label);
        });

        document.getElementById('customReasonWrapper').style.display = 'none';
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function toggleCustomReason(val) {
        document.getElementById('customReasonWrapper').style.display = (val === 'Other') ? 'block' : 'none';
    }

    function closeFeedbackModal() {
        document.getElementById('feedbackModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close on click outside
    window.onclick = function (event) {
        const modal = document.getElementById('feedbackModal');
        if (event.target == modal) closeFeedbackModal();
    }
</script>