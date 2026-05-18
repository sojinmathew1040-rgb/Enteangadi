<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

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

        // Restriction: Cannot activate a 'pending' ad manually
        if ($action === 'activate') {
            $check_stmt = $pdo->prepare("SELECT status FROM products WHERE id = ? AND user_id = ?");
            $check_stmt->execute([$ad_id, $user_id]);
            $current_status = $check_stmt->fetchColumn();
            if ($current_status === 'pending') {
                $error = "This ad is under review and cannot be activated yet.";
                goto end_post;
            }

            // Check if manual approval is required
            $set_stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'ad_approval_mode'");
            $set_stmt->execute();
            $approval_mode = $set_stmt->fetchColumn() ?: 'auto';

            if ($approval_mode !== 'auto') {
                $new_status = 'pending';
            }
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
                    if (function_exists('recompressTo50kb')) {
                        recompressTo50kb($fullPath);
                    }
                }
            }

            if ($action === 'activate' && $new_status === 'pending') {
                $success = "Ad sent for approval pending.";
            } else {
                $success = "Ad " . str_replace('_', ' ', strtoupper($action)) . " successfully.";
            }
        } else {
            $error = "Failed to update ad status.";
        }
    }
    end_post:
    ;
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

// Fetch approval mode for UI
$set_stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'ad_approval_mode'");
$set_stmt->execute();
$approval_mode = $set_stmt->fetchColumn() ?: 'auto';

require_once '../includes/header.php';
?>

<div class="my-ads-wrapper">
    <div class="container">
        <div class="dashboard-header-premium">
            <div class="header-left">
                <h1>My Dashboard</h1>
                <p>Manage your listings and track performance</p>
            </div>
        </div>

        <?php
        // Newly Approved Notification
        $newly_approved_stmt = $pdo->prepare("SELECT id FROM products WHERE user_id = ? AND status = 'active' AND is_notified = 0");
        $newly_approved_stmt->execute([$user_id]);
        $newly_approved_ads = $newly_approved_stmt->fetchAll(PDO::FETCH_COLUMN);
        $has_newly_approved = count($newly_approved_ads);

        if ($has_newly_approved > 0):
            $placeholders = implode(',', array_fill(0, $has_newly_approved, '?'));
            $update_notif = $pdo->prepare("UPDATE products SET is_notified = 1 WHERE id IN ($placeholders)");
            $update_notif->execute($newly_approved_ads);
            ?>
            <div class="approval-toast">
                <div class="toast-icon"><i class="fa fa-rocket"></i></div>
                <div class="toast-content">
                    <h3>Listing Approved!</h3>
                    <p>Your ad is now live and visible to buyers.</p>
                </div>
                <button onclick="this.parentElement.style.display='none'" class="toast-close"><i
                        class="fa fa-times"></i></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="status-toast success">
                <div class="toast-indicator"></div>
                <div class="toast-body">
                    <i class="fa fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
                <button onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="status-toast error">
                <div class="toast-indicator"></div>
                <div class="toast-body">
                    <i class="fa fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
                <button onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (empty($all_ads)): ?>
            <div class="empty-state-premium">
                <div class="empty-illustration">
                    <i class="fa fa-layer-group"></i>
                </div>
                <h3>Your Dashboard is Waiting</h3>
                <p>Start your journey by posting your first advertisement. It's quick, easy, and reaches thousands of
                    buyers.</p>
                <a href="post_ad.php" class="btn-premium-cta">
                    <i class="fa fa-plus-circle"></i> Create New Listing
                </a>
            </div>
        <?php else: ?>
            <div class="my-ads-grid">
                <?php foreach ($all_ads as $ad):
                    $is_active = ($ad['status'] === 'active');
                    $is_sold = ($ad['status'] === 'sold');
                    $is_expired = ($ad['status'] === 'expired');
                    $is_pending = ($ad['status'] === 'pending');
                    $is_inactive = ($ad['status'] === 'inactive');
                    $is_deleted = ($ad['status'] === 'deleted');
                    ?>
                    <div class="my-ad-card <?= !$is_active ? 'ad-card-dimmed' : '' ?>">
                        <div class="ad-card-image-box">
                            <?php if ($ad['main_image']): ?>
                                <img src="../<?= htmlspecialchars($ad['main_image']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>"
                                    class="ad-image">
                            <?php else: ?>
                                <div class="ad-image-placeholder"><i class="fa fa-image"></i></div>
                            <?php endif; ?>

                            <!-- Premium Status Badge -->
                            <div class="ad-status-badge status-<?= $ad['status'] ?>">
                                <?= strtoupper($ad['status']) ?>
                            </div>
                        </div>

                        <div class="ad-card-details">
                            <div class="ad-price">₹ <?= number_format($ad['price'], 0) ?></div>
                            <h3 class="ad-title"><?= htmlspecialchars($ad['title']) ?></h3>

                            <div class="ad-stats-mini">
                                <span><i class="fa fa-eye"></i> <?= number_format($ad['views']) ?></span>
                                <span><i class="fa fa-heart"></i> <?= number_format($ad['like_count']) ?></span>
                                <span><i class="fa fa-calendar"></i> <?= date('M d', strtotime($ad['created_at'])) ?></span>
                            </div>

                            <div class="ad-card-actions-premium">
                                <!-- Primary Action: View -->
                                <a href="../product.php?id=<?= $ad['id'] ?>" class="btn-view-listing" title="View Listing">
                                    <i class="fa fa-external-link-alt"></i> View Listing
                                </a>

                                <div class="action-tools-grid">
                                    <?php if ($is_active): ?>
                                        <a href="edit_ad.php?id=<?= $ad['id'] ?>" class="tool-btn edit" title="Edit Listing">
                                            <i class="fa fa-pencil-alt"></i>
                                        </a>
                                        <button onclick="openFeedbackModal(<?= $ad['id'] ?>, 'mark_sold')" class="tool-btn sold"
                                            title="Mark as Sold">
                                            <i class="fa fa-check"></i>
                                        </button>
                                        <form method="POST" style="display:contents;">
                                            <input type="hidden" name="target_ad_id" value="<?= $ad['id'] ?>">
                                            <input type="hidden" name="ad_action" value="deactivate">
                                            <button type="submit" class="tool-btn deactivate" title="Deactivate">
                                                <i class="fa fa-eye-slash"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($is_deleted): ?>
                                        <div class="tool-btn deleted-status-mini">
                                            <i class="fa fa-trash-alt"></i>
                                            <span>Deleted</span>
                                        </div>
                                    <?php elseif (!$is_pending): ?>
                                        <form method="POST" style="display:contents;">
                                            <input type="hidden" name="target_ad_id" value="<?= $ad['id'] ?>">
                                            <input type="hidden" name="ad_action" value="activate">
                                            <button type="submit" class="tool-btn activate-request"
                                                title="<?= ($approval_mode === 'auto') ? 'Activate' : 'Request Activation' ?>">
                                                <i class="fa <?= ($approval_mode === 'auto') ? 'fa-bolt' : 'fa-clock' ?>"></i>
                                                <span><?= ($approval_mode === 'auto') ? 'Activate' : 'Request' ?></span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="approval-pending-card-mini">
                                            <div class="pending-shimmer"></div>
                                            <i class="fa fa-hourglass-half"></i>
                                            <span>Under Review</span>
                                        </div>
                                    <?php endif; ?>

                                    <button onclick="openFeedbackModal(<?= $ad['id'] ?>, 'delete')" class="tool-btn delete"
                                        title="Delete">
                                        <i class="fa fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="modal-overlay-premium" style="display:none;">
    <div class="modal-content-premium">
        <h3 id="feedbackTitle">Feedback</h3>
        <p id="feedbackSub">Please tell us why you are closing this listing.</p>

        <form method="POST" id="feedbackForm">
            <input type="hidden" name="target_ad_id" id="modal_ad_id">
            <input type="hidden" name="ad_action" id="modal_ad_action">

            <div class="reason-list" id="reasonOptions"></div>

            <div id="customReasonWrapper" style="display: none; margin-top: 15px;">
                <textarea name="custom_reason" class="form-control" rows="3"
                    placeholder="Type your reason here..."></textarea>
            </div>

            <div class="modal-footer-btns">
                <button type="button" onclick="closeFeedbackModal()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-confirm" id="confirmBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<style>
    .my-ads-wrapper {
        background: var(--background);
        min-height: 100vh;
        padding-top: 20px;
    }

    .dashboard-header-premium {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
    }

    .dashboard-header-premium h1 {
        font-size: 32px;
        font-weight: 800;
        color: var(--text-dark);
    }

    .dashboard-header-premium p {
        color: var(--text-muted);
        font-weight: 500;
    }

    .btn-premium-action {
        background: var(--primary-green);
        color: white !important;
        padding: 14px 28px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 10px 20px rgba(46, 125, 50, 0.2);
        transition: all 0.3s;
    }

    .btn-premium-action:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(46, 125, 50, 0.3);
    }

    /* Ads Grid Layout */
    .my-ads-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
        margin-top: 20px;
    }

    @media (max-width: 991px) {
        .my-ads-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
    }

    @media (max-width: 768px) {
        .my-ads-grid {
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 15px;
        }
    }

    /* Ad Card Styling */
    .my-ad-card {
        background: var(--white);
        border-radius: var(--border-radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: all 0.3s ease;
        border: 1px solid var(--border-color);
    }

    .my-ad-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-lg);
    }

    .ad-card-dimmed {
        filter: grayscale(0.8);
        opacity: 0.8;
    }

    .ad-card-image-box {
        position: relative;
        width: 100%;
        aspect-ratio: 1;
    }

    .ad-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .ad-image-placeholder {
        width: 100%;
        height: 100%;
        background: var(--skeleton-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        color: var(--text-muted);
    }

    .ad-status-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 800;
        color: white;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .status-active {
        background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
    }

    .status-sold {
        background: #0F172A;
    }

    .status-expired {
        background: #EF4444;
    }

    .status-pending {
        background: #F59E0B;
    }

    .status-inactive {
        background: #64748B;
    }

    .status-deleted {
        background: #ef4444;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .ad-card-details {
        padding: 20px;
    }

    .ad-price {
        font-size: 20px;
        font-weight: 800;
        color: var(--primary-green-dark);
        margin-bottom: 8px;
    }

    .ad-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-dark);
        margin-bottom: 12px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ad-stats-mini {
        display: flex;
        gap: 12px;
        font-size: 11px;
        color: var(--text-muted);
        font-weight: 600;
        margin-bottom: 20px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
    }

    .ad-card-actions-premium {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 15px;
    }

    .btn-view-listing {
        width: 100%;
        height: 44px;
        background: var(--background);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: var(--text-dark);
        font-weight: 700;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.3s;
    }

    .btn-view-listing:hover {
        background: var(--primary-green);
        color: white !important;
        border-color: var(--primary-green);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
    }

    .action-tools-grid {
        display: flex;
        gap: 8px;
    }

    .tool-btn {
        flex: 1;
        height: 44px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--background);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--text-muted);
        font-size: 14px;
    }

    .tool-btn:hover {
        background: var(--white);
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .tool-btn.edit:hover {
        color: #0284c7;
        border-color: #0284c7;
    }

    .tool-btn.sold:hover {
        color: #166534;
        border-color: #166534;
        background: #f0fdf4;
    }

    .tool-btn.deactivate:hover {
        color: #f59e0b;
        border-color: #f59e0b;
        background: #fffbeb;
    }

    .tool-btn.delete:hover {
        color: #ef4444;
        border-color: #ef4444;
        background: #fef2f2;
    }

    .tool-btn.deleted-status-mini {
        flex: 3;
        background: #fee2e2;
        color: #ef4444;
        border: 1px solid #fecaca;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 10px;
        gap: 6px;
        cursor: default;
    }

    .tool-btn.deleted-status-mini:hover {
        transform: none;
        box-shadow: none;
    }

    .tool-btn.activate-request {
        flex: 3;
        background: var(--primary-green);
        color: white;
        border: none;
        font-weight: 700;
        gap: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 11px;
    }

    .tool-btn.activate-request:hover {
        background: var(--primary-green-dark);
        box-shadow: 0 6px 15px rgba(34, 197, 94, 0.3);
    }

    .approval-pending-card-mini {
        flex: 3;
        position: relative;
        background: #fffbeb;
        border: 1px solid #fef3c7;
        color: #d97706;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        overflow: hidden;
    }

    /* Status Toasts */
    .status-toast {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--white);
        padding: 16px 20px;
        border-radius: 16px;
        margin-bottom: 24px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        border: 1px solid var(--border-color);
        position: relative;
        overflow: hidden;
        animation: slideInUp 0.4s ease;
    }

    .toast-indicator {
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
    }

    .status-toast.success .toast-indicator {
        background: var(--primary-green);
    }

    .status-toast.error .toast-indicator {
        background: #ef4444;
    }

    .toast-body {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
        color: var(--text-dark);
    }

    .status-toast.success i {
        color: var(--primary-green);
    }

    .status-toast.error i {
        color: #ef4444;
    }

    .status-toast button {
        background: none;
        border: none;
        font-size: 20px;
        color: #94a3b8;
        cursor: pointer;
        padding: 0;
        line-height: 1;
    }

    /* Pending Card Style */
    .approval-pending-card {
        position: relative;
        background: #fffbeb;
        border: 1px solid #fef3c7;
        color: #d97706;
        padding: 10px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        overflow: hidden;
    }

    .pending-shimmer {
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.6), transparent);
        animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
        to {
            left: 100%;
        }
    }

    @keyframes slideInUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Approval Toast */
    .approval-toast {
        background: var(--white);
        border-radius: 20px;
        padding: 24px;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: var(--shadow-md);
        border-left: 6px solid var(--primary-green);
        position: relative;
    }

    .toast-icon {
        width: 50px;
        height: 50px;
        background: #e8f5e9;
        color: var(--primary-green);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .toast-content h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 800;
        color: var(--text-dark);
    }

    .toast-content p {
        margin: 4px 0 0 0;
        color: var(--text-muted);
        font-size: 14px;
    }

    /* Modal Premium */
    .modal-overlay-premium {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content-premium {
        background: var(--white);
        width: 90%;
        max-width: 400px;
        padding: 32px;
        border-radius: 24px;
        box-shadow: var(--shadow-lg);
        animation: zoomIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        color: var(--text-dark);
    }

    @keyframes zoomIn {
        from {
            transform: scale(0.9);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .reason-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 20px;
    }

    .reason-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        background: var(--background);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        color: var(--text-dark);
    }

    .modal-footer-btns {
        display: flex;
        gap: 12px;
        margin-top: 30px;
    }

    .btn-cancel {
        flex: 1;
        padding: 14px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: var(--white);
        font-weight: 700;
        cursor: pointer;
        color: var(--text-dark);
    }

    .btn-confirm {
        flex: 2;
        padding: 14px;
        border-radius: 12px;
        border: none;
        background: var(--primary-green);
        color: white;
        font-weight: 700;
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .dashboard-header-premium {
            flex-direction: column;
            align-items: flex-start;
            gap: 20px;
        }

        .btn-premium-action {
            width: 100%;
            justify-content: center;
        }
    }
</style>

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

        optionsDiv.innerHTML = '';
        reasons[action].forEach((r, i) => {
            const label = document.createElement('label');
            label.className = 'reason-item';
            label.innerHTML = `<input type="radio" name="status_reason" value="${r}" ${i === 0 ? 'checked' : ''} onchange="toggleCustomReason(this.value)"> <span>${r}</span>`;
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

    window.onclick = (e) => { if (e.target.id === 'feedbackModal') closeFeedbackModal(); }
</script>

<?php require_once '../includes/footer.php'; ?>