<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_id = $_POST['delete_user_id'];
    $admin_password = $_POST['admin_password'] ?? '';

    // Privilege check: Must have manage_users permission
    if (!has_permission('manage_users')) {
        $error = "Permission denied: You do not have privileges to delete users.";
    } else {
        // Verify currently logged in user's password (Root Admin or Privileged Community Member)
        $stmt_admin = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt_admin->execute([$_SESSION['admin_id'] ?? 0]);
        $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);

        if (!$admin_user || empty($admin_password) || !password_verify($admin_password, $admin_user['password'])) {
            $error = "Authentication failed: Incorrect account password. User deletion aborted.";
        } else {
        // Fetch user profile picture and product images before deleting from DB
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $profile_pic = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT pi.image_path FROM product_images pi 
                              JOIN products p ON pi.product_id = p.id 
                              WHERE p.user_id = ?");
        $stmt->execute([$delete_id]);
        $product_images = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        if ($stmt->execute([$delete_id])) {
            // Delete physical files
            if (!empty($profile_pic) && file_exists('../' . $profile_pic)) {
                unlink('../' . $profile_pic);
            }
            foreach ($product_images as $img) {
                if (!empty($img) && file_exists('../' . $img)) {
                    unlink('../' . $img);
                }
            }
            $success = "User and all associated media deleted successfully.";
        } else {
            $error = "Failed to delete user.";
        }
    }
}
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_id'])) {
    $user_id = $_POST['reset_password_id'];
    $new_password = $_POST['new_password'];
    
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashed_password, $user_id])) {
            $success = "Password reset successfully.";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<h2 style="margin-bottom: 24px; color: var(--primary-green-dark);">Manage Users</h2>

<?php if (isset($success)): ?>
    <div
        style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div
        style="background: #fee2e2; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div
    style="background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9fa; border-bottom: 1px solid var(--border-color); text-align: left;">
                <th style="padding: 16px;">ID</th>
                <th style="padding: 16px;">Username</th>
                <th style="padding: 16px;">Phone</th>
                <th style="padding: 16px;">Role</th>
                <th style="padding: 16px;">Joined</th>
                <th style="padding: 16px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <td style="padding: 16px;"><?= $user['id'] ?></td>
                    <td style="padding: 16px; font-weight: 500;"><?= htmlspecialchars($user['username']) ?></td>
                    <td style="padding: 16px;"><?= htmlspecialchars($user['phone_number']) ?></td>
                    <td style="padding: 16px;">
                        <span
                            style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; background: <?= $user['role'] === 'admin' ? '#e3f2fd' : '#e8f5e9' ?>; color: <?= $user['role'] === 'admin' ? '#1976d2' : '#2e7d32' ?>;">
                            <?= ucfirst($user['role']) ?>
                        </span>
                    </td>
                    <td style="padding: 16px;"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                    <td style="padding: 16px;">
                        <div style="display: flex; gap: 8px;">
                            <button type="button" onclick="openResetModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')" class="btn-secondary" style="font-size: 12px; padding: 6px 10px;"><i class="fa fa-key"></i> Reset</button>
                            <?php if ($user['role'] !== 'admin' && has_permission('manage_users')): ?>
                                <button type="button" onclick="openDeleteUserModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>')" class="btn-danger" style="font-size: 12px; padding: 6px 10px;"><i class="fa fa-trash"></i> Delete</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: var(--border-radius); width: 100%; max-width: 400px;">
        <h3 style="margin-bottom: 8px;">Reset Password</h3>
        <p id="resetUsername" style="color: var(--text-muted); margin-bottom: 20px; font-size: 14px;"></p>
        <form method="POST">
            <input type="hidden" id="reset_user_id" name="reset_password_id">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" onclick="closeResetModal()" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal (Requires Admin Password) -->
<div id="deleteUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; padding: 32px; border-radius: 20px; width: 100%; max-width: 420px; box-shadow: var(--shadow-md);">
        <div style="width: 52px; height: 52px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px;">
            <i class="fa fa-exclamation-triangle"></i>
        </div>
        <h3 style="margin-bottom: 8px; color: #1e293b; font-size: 20px;">Confirm User Deletion</h3>
        <p id="deleteUsernamePrompt" style="color: #64748b; margin-bottom: 20px; font-size: 13.5px; line-height: 1.5;"></p>
        <form method="POST">
            <input type="hidden" id="delete_user_id" name="delete_user_id">
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 6px; text-transform: uppercase;">Your Account Password Required</label>
                <input type="password" name="admin_password" class="form-control" placeholder="Enter your password" required autocomplete="current-password" style="font-size: 14px; border-radius: 10px; padding: 12px;">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeDeleteUserModal()" class="btn-secondary" style="padding: 10px 16px; border-radius: 10px;">Cancel</button>
                <button type="submit" class="btn-danger" style="padding: 10px 18px; border-radius: 10px; font-weight: 700;"><i class="fa fa-trash"></i> Permanently Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openResetModal(id, username) {
        document.getElementById('reset_user_id').value = id;
        document.getElementById('resetUsername').innerText = "For user: " + username;
        document.getElementById('resetModal').style.display = 'flex';
    }
    function closeResetModal() {
        document.getElementById('resetModal').style.display = 'none';
    }

    function openDeleteUserModal(id, username) {
        document.getElementById('delete_user_id').value = id;
        document.getElementById('deleteUsernamePrompt').innerHTML = "Are you sure you want to delete user <b>" + username + "</b>? All their ads, inquiries, and media will be permanently removed.<br><br>Please confirm with your <b>Account Password</b>:";
        document.getElementById('deleteUserModal').style.display = 'flex';
    }
    function closeDeleteUserModal() {
        document.getElementById('deleteUserModal').style.display = 'none';
    }
</script>

<?php require_once 'includes/footer.php'; ?>