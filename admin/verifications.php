<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

// Handle Verification Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $action = $_POST['action'] ?? '';
    
    if ($request_id && in_array($action, ['approve', 'reject'])) {
        // Fetch request details
        $stmt_r = $pdo->prepare("SELECT user_id, id_photo FROM verification_requests WHERE id = ?");
        $stmt_r->execute([$request_id]);
        $req_data = $stmt_r->fetch();
        
        if ($req_data) {
            $user_id = $req_data['user_id'];
            
            if ($action === 'approve') {
                try {
                    $pdo->beginTransaction();
                    
                    // Update request status
                    $stmt_up_req = $pdo->prepare("UPDATE verification_requests SET status = 'approved' WHERE id = ?");
                    $stmt_up_req->execute([$request_id]);
                    
                    // Update user verified flag
                    $stmt_up_usr = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                    $stmt_up_usr->execute([$user_id]);
                    
                    $pdo->commit();
                    $success = "Verification request approved successfully. User badge activated.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Failed to approve request: " . $e->getMessage();
                }
            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? 'Document is not clear or does not match profile details.');
                
                try {
                    $pdo->beginTransaction();
                    
                    // Update request status
                    $stmt_up_req = $pdo->prepare("UPDATE verification_requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                    $stmt_up_req->execute([$reason, $request_id]);
                    
                    // Reset user verified flag if previously verified
                    $stmt_up_usr = $pdo->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                    $stmt_up_usr->execute([$user_id]);
                    
                    $pdo->commit();
                    $success = "Verification request rejected and user notified.";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Failed to reject request: " . $e->getMessage();
                }
            }
        } else {
            $error = "Request details not found.";
        }
    }
}

// Fetch all requests
$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'approved', 'rejected'])) {
    $tab = 'pending';
}

$stmt_list = $pdo->prepare("
    SELECT vr.*, u.username, u.email, u.phone_number 
    FROM verification_requests vr
    JOIN users u ON vr.user_id = u.id
    WHERE vr.status = ?
    ORDER BY vr.created_at DESC
");
$stmt_list->execute([$tab]);
$requests = $stmt_list->fetchAll();

require_once 'includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <h2 style="margin: 0; color: var(--primary-green-dark);">Profile Verifications</h2>
</div>

<?php if ($success): ?>
    <div style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <i class="fa fa-check-circle"></i> <?= $success ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="background: #ffe5e5; color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
        <i class="fa fa-exclamation-circle"></i> <?= $error ?>
    </div>
<?php endif; ?>

<!-- Tabs navigation -->
<div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
    <a href="verifications.php?tab=pending" style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 700; background: <?= $tab === 'pending' ? 'var(--primary-green)' : 'transparent' ?>; color: <?= $tab === 'pending' ? '#fff' : 'var(--text-muted)' ?>;">
        Pending Claims
    </a>
    <a href="verifications.php?tab=approved" style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 700; background: <?= $tab === 'approved' ? 'var(--primary-green)' : 'transparent' ?>; color: <?= $tab === 'approved' ? '#fff' : 'var(--text-muted)' ?>;">
        Approved Claims
    </a>
    <a href="verifications.php?tab=rejected" style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 700; background: <?= $tab === 'rejected' ? 'var(--primary-green)' : 'transparent' ?>; color: <?= $tab === 'rejected' ? '#fff' : 'var(--text-muted)' ?>;">
        Rejected Claims
    </a>
</div>

<!-- List block -->
<div style="background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); overflow: hidden;">
    <?php if (empty($requests)): ?>
        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
            <i class="fa fa-id-card" style="font-size: 40px; margin-bottom: 12px;"></i>
            <p style="margin: 0; font-size: 15px; font-weight: 600;">No verification records in this tab.</p>
        </div>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 1px solid var(--border-color); text-align: left; font-size: 13px;">
                    <th style="padding: 16px;">User Details</th>
                    <th style="padding: 16px;">ID Type</th>
                    <th style="padding: 16px;">Uploaded ID Photo</th>
                    <th style="padding: 16px;">Submitted</th>
                    <?php if ($tab === 'rejected'): ?>
                        <th style="padding: 16px;">Rejection Reason</th>
                    <?php endif; ?>
                    <?php if ($tab === 'pending'): ?>
                        <th style="padding: 16px; text-align: right;">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $req): ?>
                    <tr style="border-bottom: 1px solid var(--border-color); font-size: 14px;">
                        <td style="padding: 16px;">
                            <strong style="color: var(--text-dark);"><?= htmlspecialchars($req['username']) ?></strong>
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                Email: <?= htmlspecialchars($req['email']) ?><br>
                                Phone: <?= htmlspecialchars($req['phone_number']) ?>
                            </div>
                        </td>
                        <td style="padding: 16px; font-weight: 600; color: var(--text-dark);">
                            <?= htmlspecialchars($req['id_type']) ?>
                        </td>
                        <td style="padding: 16px;">
                            <a href="../<?= htmlspecialchars($req['id_photo']) ?>" target="_blank" title="Click to view full screen">
                                <img src="../<?= htmlspecialchars($req['id_photo']) ?>" style="max-width: 100px; max-height: 60px; border-radius: 6px; border: 1px solid var(--border-color); object-fit: contain; cursor: pointer;">
                            </a>
                        </td>
                        <td style="padding: 16px; color: var(--text-muted); font-size: 12px;">
                            <?= date('M d, Y H:i', strtotime($req['created_at'])) ?>
                        </td>
                        <?php if ($tab === 'rejected'): ?>
                            <td style="padding: 16px; color: #991b1b; font-size: 12px; max-width: 200px;">
                                <?= htmlspecialchars($req['rejection_reason']) ?>
                            </td>
                        <?php endif; ?>
                        <?php if ($tab === 'pending'): ?>
                            <td style="padding: 16px; text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-primary" style="font-size: 12px; padding: 6px 12px;"><i class="fa fa-check"></i> Approve</button>
                                    </form>
                                    <button type="button" onclick="openRejectModal(<?= $req['id'] ?>)" class="btn-danger" style="font-size: 12px; padding: 6px 12px;"><i class="fa fa-times"></i> Reject</button>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Rejection Modal Overlay -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 28px; border-radius: 16px; width: 100%; max-width: 420px; box-shadow: var(--shadow-lg);">
        <h3 style="margin-top: 0; margin-bottom: 12px; font-weight: 800; color: #991b1b;">Reject Request</h3>
        <form method="POST">
            <input type="hidden" id="reject_req_id" name="request_id">
            <input type="hidden" name="action" value="reject">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 13px;">Reason for Rejection</label>
                <textarea name="rejection_reason" required placeholder="Describe why this ID document was rejected e.g., Photo is blurry, name does not match profile, etc." style="width: 100%; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px; font-size: 13px; min-height: 100px; resize: vertical; outline: none;"></textarea>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeRejectModal()" class="btn-secondary" style="font-size: 13px; padding: 8px 16px;">Cancel</button>
                <button type="submit" class="btn-danger" style="font-size: 13px; padding: 8px 16px;">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('reject_req_id').value = id;
    document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
