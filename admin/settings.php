<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'branding') {
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Ensure default keys exist
        $pdo->exec("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES 
            ('app_logo', ''),
            ('app_name', 'Enteangadi'),
            ('app_tagline', 'Your Local Marketplace')");

        $tagline = $_POST['app_tagline'] ?? '';

        // Update tagline
        $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'app_tagline'");
        $stmt->execute([$tagline]);

        // Handle logo deletion
        if (isset($_POST['delete_logo']) && $_POST['delete_logo'] === '1') {
            $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'app_logo'");
            $stmt->execute();
            $current_logo = $stmt->fetchColumn();

            if (!empty($current_logo)) {
                $file_to_delete = '../' . $current_logo;
                if (file_exists($file_to_delete)) {
                    unlink($file_to_delete);
                }
            }
            $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = '' WHERE setting_key = 'app_logo'");
            $stmt->execute();
        }

        // Handle logo upload
        if (isset($_FILES['app_logo']) && $_FILES['app_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/logo/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['app_logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];

            if (in_array($file_ext, $allowed)) {
                $file_name = 'logo_' . time() . '.' . $file_ext;
                $target_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['app_logo']['tmp_name'], $target_path)) {
                    $db_path = 'uploads/logo/' . $file_name;
                    $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'app_logo'");
                    $stmt->execute([$db_path]);
                }
            }
        }
        $success = "Visual identity updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'general') {
        $app_name = $_POST['app_name'] ?? 'Enteangadi';
        $stmt = $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'app_name'");
        $stmt->execute([$app_name]);
        $success = "General settings updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'contact') {
        $fields = [
            'support_email',
            'support_phone',
            'whatsapp_number',
            'facebook_url',
            'instagram_url',
            'twitter_url'
        ];

        foreach ($fields as $field) {
            $value = $_POST[$field] ?? '';
            // Upsert logic
            $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) 
                                 VALUES (?, ?) 
                                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$field, $value]);
        }
        $success = "Contact and social settings updated successfully.";
    } elseif (isset($_POST['action']) && $_POST['action'] === 'password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!empty($current_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } else {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $user = $stmt->fetch();

                if (password_verify($current_password, $user['password'])) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update->execute([$hashed, $_SESSION['admin_id']]);
                    $success = "Password updated successfully.";
                } else {
                    $error = "Current password is incorrect.";
                }
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }
}

// Fetch current app settings
try {
    $stmt = $pdo->query("SELECT * FROM app_settings");
    $settings_raw = $stmt->fetchAll();
    $app_settings = [];
    foreach ($settings_raw as $s) {
        $app_settings[$s['setting_key']] = $s['setting_value'];
    }
} catch (PDOException $e) {
    $app_settings = [
        'app_logo' => '',
        'app_name' => 'Enteangadi',
        'app_tagline' => 'Your Local Marketplace'
    ];
}

require_once 'includes/header.php';
?>

<style>
    .settings-container {
        max-width: 1000px;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
        margin-top: 24px;
    }

    .settings-card {
        background: var(--white);
        border-radius: 16px;
        padding: 24px;
        text-align: center;
        text-decoration: none;
        color: var(--text-dark);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid var(--border-color);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;
        box-shadow: var(--shadow-sm);
    }

    .settings-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
        border-color: var(--primary-green);
    }

    .settings-card i {
        font-size: 24px;
        color: var(--primary-green);
        background: #f0fdf4;
        width: 56px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .settings-card:hover i {
        background: var(--primary-green);
        color: var(--white);
    }

    .settings-card h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
    }

    .settings-card p {
        margin: 0;
        color: var(--text-muted);
        font-size: 11px;
    }

    .settings-section {
        display: none;
        animation: slideUp 0.4s ease-out;
    }

    .settings-section.active {
        display: block;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: var(--text-muted);
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 24px;
        transition: all 0.2s;
        cursor: pointer;
        background: var(--white);
        border: 1px solid var(--border-color);
        padding: 8px 16px;
        border-radius: 12px;
        font-size: 14px;
    }

    .back-btn:hover {
        color: var(--primary-green-dark);
        border-color: var(--primary-green);
        background: #f0fdf4;
    }

    .settings-form-wrapper {
        background: var(--white);
        padding: 40px;
        border-radius: 24px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
    }

    .section-header {
        margin-bottom: 32px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .section-header h2 {
        margin: 0;
        font-size: 22px;
        color: var(--text-dark);
    }

    .logo-preview-box {
        margin-bottom: 24px;
        background: #f8fafc;
        padding: 24px;
        border-radius: 16px;
        border: 2px dashed var(--border-color);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }

    .form-group-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .clear-field {
        font-size: 11px;
        color: #ef4444;
        cursor: pointer;
        font-weight: 600;
        text-transform: uppercase;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 4px;
        opacity: 0.7;
    }

    .clear-field:hover {
        opacity: 1;
        text-decoration: underline;
    }

    .form-divider {
        margin: 32px 0;
        border-top: 1px solid var(--border-color);
        position: relative;
        text-align: center;
    }

    .form-divider span {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        background: white;
        padding: 0 16px;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Premium Alert Box */
    .alert-box {
        padding: 16px 20px;
        border-radius: 16px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="settings-container">

    <!-- Messages -->
    <?php if ($error): ?>
        <div class="alert-box" style="background: #fee2e2; color: #dc2626; border-left: 5px solid #dc2626;">
            <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-box" style="background: #f0fdf4; color: #16a34a; border-left: 5px solid #16a34a;">
            <i class="fa fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Grid View -->
    <div id="grid-view"
        class="settings-section <?= empty($error) && empty($success) && !isset($_POST['action']) ? 'active' : '' ?>">
        <h1 style="margin-bottom: 8px; font-weight: 800; letter-spacing: -0.5px;">Platform Settings</h1>
        <p style="color: var(--text-muted); margin-bottom: 32px;">Configure your application's identity and security
            protocols.</p>

        <div class="settings-grid">
            <div class="settings-card" onclick="showSection('branding')">
                <i class="fa fa-palette"></i>
                <div>
                    <h3>Logo & Branding</h3>
                    <p>Customize visuals and assets</p>
                </div>
            </div>

            <div class="settings-card" onclick="showSection('security')">
                <i class="fa fa-key"></i>
                <div>
                    <h3>Security Settings</h3>
                    <p>Manage admin access</p>
                </div>
            </div>

            <div class="settings-card" onclick="showSection('general')">
                <i class="fa fa-sliders"></i>
                <div>
                    <h3>General Config</h3>
                    <p>Site name and global info</p>
                </div>
            </div>

            <div class="settings-card" onclick="showSection('contact')">
                <i class="fa fa-address-book"></i>
                <div>
                    <h3>Contact & Social</h3>
                    <p>Support info and social links</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Branding Section -->
    <div id="section-branding"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'branding') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Visual Identity</h2>
            </div>

            <form method="POST" action="settings.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="branding">

                <div class="form-group">
                    <label>Platform Logo</label>
                    <div class="logo-preview-box">
                        <?php if (!empty($app_settings['app_logo'])): ?>
                            <img src="../<?= htmlspecialchars($app_settings['app_logo']) ?>" alt="Current Logo"
                                style="max-height: 80px; object-fit: contain;">
                            <label
                                style="display: flex; align-items: center; gap: 8px; color: #dc2626; cursor: pointer; font-size: 13px; font-weight: 600; margin-top: 12px;">
                                <input type="checkbox" name="delete_logo" value="1"> Remove current logo
                            </label>
                        <?php else: ?>
                            <i class="fa fa-image" style="font-size: 40px; color: #cbd5e1;"></i>
                            <span style="color: var(--text-muted); font-size: 13px;">No logo set</span>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="app_logo" class="form-control" accept="image/*">
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <div class="form-group-header">
                        <label>Tagline / Motto</label>
                        <span class="clear-field" onclick="clearField('app_tagline')"><i class="fa fa-times-circle"></i>
                            Clear</span>
                    </div>
                    <input type="text" id="app_tagline" name="app_tagline" class="form-control"
                        value="<?= htmlspecialchars($app_settings['app_tagline'] ?? '') ?>"
                        placeholder="e.g. Your Local Marketplace">
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save
                    Visual Changes</button>
            </form>
        </div>
    </div>

    <!-- Security Section -->
    <div id="section-security"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'password') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Account Security</h2>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label>Current Admin Password</label>
                    <input type="password" name="current_password" class="form-control"
                        placeholder="Required for verification" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="At least 8 characters"
                        required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                        placeholder="Repeat new password" required>
                </div>
                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Update
                    Security Credentials</button>
            </form>
        </div>
    </div>

    <!-- General Section -->
    <div id="section-general"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'general') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>General Configuration</h2>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="general">
                <div class="form-group">
                    <label>Platform Name</label>
                    <input type="text" name="app_name" class="form-control"
                        value="<?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?>"
                        placeholder="e.g. Enteangadi">
                    <small style="color: var(--text-muted); display: block; margin-top: 8px;">This is the name that
                        appears in the site header and browser tab.</small>
                </div>

                <div class="form-group" style="margin-top: 24px;">
                    <label>Site Status</label>
                    <select class="form-control" disabled style="background: #f8fafc; cursor: not-allowed;">
                        <option>Active / Online</option>
                    </select>
                    <small style="color: var(--text-muted); display: block; margin-top: 8px;">Maintenance mode options
                        coming soon.</small>
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save
                    General Settings</button>
            </form>
        </div>
    </div>

    <!-- Contact Section -->
    <div id="section-contact"
        class="settings-section <?= (isset($_POST['action']) && $_POST['action'] === 'contact') ? 'active' : '' ?>">
        <button class="back-btn" onclick="showSection('grid')">
            <i class="fa fa-arrow-left"></i> Back to Dashboard
        </button>

        <div class="settings-form-wrapper">
            <div class="section-header">
                <h2>Contact & Social Info</h2>
            </div>

            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="contact">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <div class="form-group-header">
                            <label>Support Email</label>
                            <span class="clear-field" onclick="clearField('support_email')"><i
                                    class="fa fa-times-circle"></i> Clear</span>
                        </div>
                        <input type="email" id="support_email" name="support_email" class="form-control"
                            value="<?= htmlspecialchars($app_settings['support_email'] ?? '') ?>"
                            placeholder="support@example.com">
                    </div>
                    <div class="form-group">
                        <div class="form-group-header">
                            <label>Support Phone</label>
                            <span class="clear-field" onclick="clearField('support_phone')"><i
                                    class="fa fa-times-circle"></i> Clear</span>
                        </div>
                        <input type="text" id="support_phone" name="support_phone" class="form-control"
                            value="<?= htmlspecialchars($app_settings['support_phone'] ?? '') ?>"
                            placeholder="+91 9876543210">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <div class="form-group-header">
                        <label>WhatsApp Number</label>
                        <span class="clear-field" onclick="clearField('whatsapp_number')"><i
                                class="fa fa-times-circle"></i> Clear</span>
                    </div>
                    <input type="text" id="whatsapp_number" name="whatsapp_number" class="form-control"
                        value="<?= htmlspecialchars($app_settings['whatsapp_number'] ?? '') ?>"
                        placeholder="WhatsApp number with country code">
                </div>

                <div class="form-divider"><span>SOCIAL MEDIA LINKS</span></div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div class="form-group">
                        <div class="form-group-header">
                            <label><i class="fab fa-facebook" style="color: #1877F2;"></i> Facebook URL</label>
                            <span class="clear-field" onclick="clearField('facebook_url')"><i
                                    class="fa fa-times-circle"></i> Clear</span>
                        </div>
                        <input type="url" id="facebook_url" name="facebook_url" class="form-control"
                            value="<?= htmlspecialchars($app_settings['facebook_url'] ?? '') ?>"
                            placeholder="https://facebook.com/yourpage">
                    </div>
                    <div class="form-group">
                        <div class="form-group-header">
                            <label><i class="fab fa-instagram" style="color: #E4405F;"></i> Instagram URL</label>
                            <span class="clear-field" onclick="clearField('instagram_url')"><i
                                    class="fa fa-times-circle"></i> Clear</span>
                        </div>
                        <input type="url" id="instagram_url" name="instagram_url" class="form-control"
                            value="<?= htmlspecialchars($app_settings['instagram_url'] ?? '') ?>"
                            placeholder="https://instagram.com/yourpage">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <div class="form-group-header">
                        <label><i class="fab fa-twitter" style="color: #1DA1F2;"></i> Twitter URL</label>
                        <span class="clear-field" onclick="clearField('twitter_url')"><i class="fa fa-times-circle"></i>
                            Clear</span>
                    </div>
                    <input type="url" id="twitter_url" name="twitter_url" class="form-control"
                        value="<?= htmlspecialchars($app_settings['twitter_url'] ?? '') ?>"
                        placeholder="https://twitter.com/yourpage">
                </div>

                <button type="submit" class="btn-primary" style="margin-top: 32px; width: 100%; padding: 14px;">Save
                    Contact Details</button>
            </form>
        </div>
    </div>

</div>

<script>
    function clearField(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
            field.focus();
        }
    }

    function showSection(sectionId) {
        // Hide all sections
        document.querySelectorAll('.settings-section').forEach(section => {
            section.classList.remove('active');
        });

        // Show target section
        if (sectionId === 'grid') {
            document.getElementById('grid-view').classList.add('active');
            window.location.hash = '';
        } else {
            const section = document.getElementById('section-' + sectionId);
            if (section) {
                section.classList.add('active');
                window.location.hash = sectionId;
            } else if (sectionId === 'security') { // Map legacy ID
                document.getElementById('section-security').classList.add('active');
                window.location.hash = 'security';
            }
        }
    }

    // Support for deep linking via hash
    window.addEventListener('DOMContentLoaded', () => {
        const hash = window.location.hash.substring(1);
        if (hash === 'branding' || hash === 'password' || hash === 'general' || hash === 'contact') {
            showSection(hash);
        }
    });

    // Support for browser back button
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.substring(1);
        if (hash) {
            showSection(hash);
        } else {
            showSection('grid');
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>