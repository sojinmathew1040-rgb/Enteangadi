<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Check current user verification status
$stmt_u = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
$stmt_u->execute([$user_id]);
$is_verified = (bool)$stmt_u->fetchColumn();

// Fetch the latest verification request
$stmt_req = $pdo->prepare("SELECT * FROM verification_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt_req->execute([$user_id]);
$latest_request = $stmt_req->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_verified) {
    // If a request is already pending, don't allow duplicate submissions
    if ($latest_request && $latest_request['status'] === 'pending') {
        $error = "You already have a pending verification request.";
    } else {
        $id_type = trim($_POST['id_type'] ?? '');
        
        if (empty($id_type)) {
            $error = "Please select a valid ID type.";
        } elseif (!isset($_FILES['id_photo']) || $_FILES['id_photo']['error'] !== UPLOAD_ERR_OK) {
            $error = "Please upload a clear photo of your ID document.";
        } else {
            // Validate image upload
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $file_info = getimagesize($_FILES['id_photo']['tmp_name']);
            
            if (!$file_info || !in_array($file_info['mime'], $allowed_types)) {
                $error = "Invalid image file type. Only JPG, PNG, and WebP are allowed.";
            } else {
                $upload_dir = '../uploads/verifications/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION);
                if (empty($ext)) $ext = 'jpg';
                $file_name = 'ver_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['id_photo']['tmp_name'], $target_path)) {
                    @chmod($target_path, 0644);
                    $db_path = 'uploads/verifications/' . $file_name;
                    
                    // Insert pending verification request
                    $stmt_ins = $pdo->prepare("INSERT INTO verification_requests (user_id, id_type, id_photo, status) VALUES (?, ?, ?, 'pending')");
                    if ($stmt_ins->execute([$user_id, $id_type, $db_path])) {
                        $success = "Verification request submitted successfully. Admin will review it soon.";
                        
                        // Refresh request status
                        $stmt_req->execute([$user_id]);
                        $latest_request = $stmt_req->fetch();
                    } else {
                        $error = "Database error. Failed to submit request.";
                    }
                } else {
                    $error = "Failed to save uploaded image document.";
                }
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container" style="padding-top: 32px; padding-bottom: 60px;">
    <!-- Navigation header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <a href="profile.php" style="text-decoration: none; color: var(--text-dark); font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa fa-arrow-left"></i> Back to Account
        </a>
        <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--primary-green-dark);">Verify Profile</h2>
    </div>

    <div style="max-width: 600px; margin: 0 auto;">
        
        <?php if ($success): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">
                <i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #ffe5e5; color: #c62828; padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 600;">
                <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($is_verified): ?>
            <!-- Verified Status Block -->
            <div class="glass-card" style="padding: 40px 24px; text-align: center; border-radius: 24px; border: 1px solid var(--border-color); background: var(--white); box-shadow: var(--shadow-sm);">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: #f0fdf4; color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px;">
                    <i class="fa fa-check-circle"></i>
                </div>
                <h3 style="color: var(--text-dark); margin: 0 0 8px 0; font-weight: 800; font-size: 22px;">Profile Verified</h3>
                <p style="color: var(--text-muted); margin: 0; font-size: 14px; line-height: 1.5;">
                    Your identity verification is approved! A verified seller checkmark badge is visible on your profile and listings page.
                </p>
            </div>

        <?php elseif ($latest_request && $latest_request['status'] === 'pending'): ?>
            <!-- Pending Status Block -->
            <div class="glass-card" style="padding: 40px 24px; text-align: center; border-radius: 24px; border: 1px solid var(--border-color); background: var(--white); box-shadow: var(--shadow-sm);">
                <div style="width: 80px; height: 80px; border-radius: 50%; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 20px;">
                    <i class="fa fa-clock"></i>
                </div>
                <h3 style="color: var(--text-dark); margin: 0 0 8px 0; font-weight: 800; font-size: 22px;">Verification Pending</h3>
                <p style="color: var(--text-muted); margin: 0 0 16px 0; font-size: 14px; line-height: 1.5;">
                    Your verification request is currently under review by the administrators.
                </p>
                <div style="display: inline-block; padding: 6px 12px; background: #f1f5f9; border-radius: 8px; font-size: 12px; font-weight: 700; color: #475569;">
                    Submitted ID: <?= htmlspecialchars($latest_request['id_type']) ?>
                </div>
            </div>

        <?php else: ?>
            <!-- Upload Form -->
            <div class="glass-card" style="padding: 32px 24px; border-radius: 24px; border: 1px solid var(--border-color); background: var(--white); box-shadow: var(--shadow-sm);">
                
                <?php if ($latest_request && $latest_request['status'] === 'rejected'): ?>
                    <div style="background: #fff3f3; border: 1px solid #fecaca; padding: 16px; border-radius: 12px; margin-bottom: 24px; color: #991b1b; font-size: 13px;">
                        <h4 style="margin: 0 0 4px 0; font-weight: 800;">Previous Request Rejected</h4>
                        <p style="margin: 0;"><strong>Reason:</strong> <?= htmlspecialchars($latest_request['rejection_reason'] ?: 'Document not clear.') ?></p>
                    </div>
                <?php endif; ?>

                <h3 style="color: var(--text-dark); margin: 0 0 6px 0; font-weight: 800; font-size: 20px;">Identity Verification</h3>
                <p style="color: var(--text-muted); margin: 0 0 24px 0; font-size: 13px;">
                    Submit a photo of a government-issued ID to claim your **Verified Seller** badge.
                </p>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 14px; color: var(--text-dark);">Document Type</label>
                        <select name="id_type" required style="width: 100%; height: 48px; border: 1px solid var(--border-color); border-radius: 12px; padding: 0 16px; font-size: 14px; background: #fff; outline: none; cursor: pointer;">
                            <option value="">-- Select Government ID --</option>
                            <option value="Aadhaar Card">Aadhaar Card (India)</option>
                            <option value="PAN Card">PAN Card (India)</option>
                            <option value="Passport">Passport</option>
                            <option value="Driving License">Driving License</option>
                            <option value="Voter ID Card">Voter ID Card</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 700; font-size: 14px; color: var(--text-dark);">Upload ID Photo</label>
                        <div style="border: 2px dashed var(--border-color); border-radius: 16px; padding: 24px; text-align: center; background: #f8fafc; cursor: pointer; position: relative;">
                            <input type="file" name="id_photo" accept="image/*" required style="position: absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor:pointer;" onchange="previewFile(this)">
                            <div id="upload-placeholder">
                                <i class="fa fa-cloud-upload-alt" style="font-size: 32px; color: var(--text-muted); margin-bottom: 12px;"></i>
                                <p style="margin: 0; font-size: 13px; font-weight: 600; color: var(--text-dark);">Click to upload file</p>
                                <span style="font-size: 11px; color: var(--text-muted);">PNG, JPG, WebP (Max 5MB)</span>
                            </div>
                            <div id="upload-preview" style="display: none;">
                                <img id="preview-img" style="max-height: 150px; border-radius: 8px; margin-bottom: 8px;">
                                <p id="preview-filename" style="margin: 0; font-size: 12px; font-weight: 700; color: var(--primary-green-dark);"></p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; height: 50px; border-radius: 12px; font-weight: 800; font-size: 15px;">Submit Request</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function previewFile(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('upload-placeholder').style.display = 'none';
            document.getElementById('upload-preview').style.display = 'block';
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('preview-filename').innerText = file.name;
        }
        reader.readAsDataURL(file);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
