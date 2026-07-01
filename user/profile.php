<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete_picture') {
        // Fetch old profile picture to delete it
        $old_pic_stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $old_pic_stmt->execute([$user_id]);
        $old_pic = $old_pic_stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            if ($old_pic && file_exists('../' . $old_pic)) {
                unlink('../' . $old_pic);
            }
            $success = "Profile picture deleted successfully!";
        } else {
            $error = "Failed to delete profile picture.";
        }
    } else if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_info = pathinfo($_FILES['profile_picture']['name']);
        $ext = strtolower($file_info['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed)) {
            require_once '../includes/helpers.php';
            if (isImageNSFW($_FILES['profile_picture']['tmp_name'])) {
                $error = "Inappropriate profile picture content detected.";
            } else {
                $new_name = uniqid() . '.' . $ext;
                $dest = $upload_dir . $new_name;

                if (compressAndResizeImage($_FILES['profile_picture']['tmp_name'], $dest, 400, 80) || move_uploaded_file($_FILES['profile_picture']['tmp_name'], $dest)) {
                    @chmod($dest, 0644);
                    $db_path = 'uploads/profiles/' . $new_name;

                    // Fetch old profile picture to delete it
                    $old_pic_stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $old_pic_stmt->execute([$user_id]);
                    $old_pic = $old_pic_stmt->fetchColumn();

                    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    if ($stmt->execute([$db_path, $user_id])) {
                        if ($old_pic && file_exists('../' . $old_pic)) {
                            unlink('../' . $old_pic);
                        }
                        $success = "Profile picture updated successfully!";
                    } else {
                        $error = "Failed to update profile picture in database.";
                    }
                } else {
                    $error = "Failed to upload image.";
                }
            }
        } else {
            $error = "Invalid file format. Only JPG, PNG, and WEBP are allowed.";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch statistics
$count_active = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'active'");
$count_active->execute([$user_id]);
$active_ads = $count_active->fetchColumn();

$count_sold = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'sold'");
$count_sold->execute([$user_id]);
$sold_ads = $count_sold->fetchColumn();

$count_wish = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$count_wish->execute([$user_id]);
$wishlist_count = $count_wish->fetchColumn();

require_once '../includes/header.php';
?>

<div class="profile-page-wrapper">
    <!-- Premium Header Section -->
    <div class="profile-header-premium">
        <div class="profile-header-bg"></div>
        <div class="container" style="position: relative; z-index: 2; padding-top: 60px; padding-bottom: 40px;">
            <div class="profile-hero-content">
                <div class="profile-avatar-wrapper" onclick="document.getElementById('profile_picture_input').click()">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= $base_url . '/' . htmlspecialchars($user['profile_picture']) ?>" alt="Profile"
                            class="profile-avatar-img" id="profile-avatar-img" onerror="this.style.display='none'; document.getElementById('profile-avatar-fallback').style.display='flex';">
                    <?php endif; ?>
                    <div id="profile-avatar-fallback" class="profile-avatar-placeholder" style="<?= !empty($user['profile_picture']) ? 'display: none;' : '' ?>">
                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                    </div>
                    <div class="avatar-edit-badge">
                        <i class="fa fa-camera"></i>
                    </div>
                </div>

                <div class="profile-titles">
                    <h1><?= htmlspecialchars($user['username']) ?></h1>
                    <p><i class="fa fa-phone"></i> <?= htmlspecialchars($user['phone_number']) ?></p>
                    <?php if (!empty($user['email'])): ?>
                        <p style="margin-top: 4px;"><i class="fa fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                    <?php endif; ?>
                </div>

                <form id="profile_pic_form" action="profile.php" method="POST" enctype="multipart/form-data"
                    style="display: none;">
                    <input type="file" id="profile_picture_input" name="profile_picture" accept="image/*"
                        onchange="handleProfileFileSelect(this)">
                </form>

                <form id="delete_pic_form" action="profile.php" method="POST" style="display: none;">
                    <input type="hidden" name="action" value="delete_picture">
                </form>

                <script>
                function deleteProfilePicture() {
                    if (confirm("Are you sure you want to delete your profile picture?")) {
                        document.getElementById('delete_pic_form').submit();
                    }
                }
                </script>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -30px; position: relative; z-index: 10;">
        <?php if ($success): ?>
            <div class="alert-success-premium"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-danger-premium"><?= $error ?></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="profile-stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e8f5e9; color: #2e7d32;"><i class="fa fa-rocket"></i></div>
                <div class="stat-info">
                    <h3><?= $active_ads ?></h3>
                    <p><?= __('live_ads') ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff3e0; color: #ef6c00;"><i class="fa fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $sold_ads ?></h3>
                    <p><?= __('sold_items') ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fce4ec; color: #c2185b;"><i class="fa fa-heart"></i></div>
                <div class="stat-info">
                    <h3><?= $wishlist_count ?></h3>
                    <p><?= __('wishlist') ?></p>
                </div>
            </div>
        </div>

        <!-- Menu Section -->
        <div class="profile-content-layout">
            <div class="profile-menu-premium">
                <h2 class="menu-title"><?= __('account_management') ?></h2>

                <a href="my_ads.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-th-large"></i></div>
                        <span><?= __('my_dashboard') ?></span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="wishlist.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-heart"></i></div>
                        <span><?= __('saved_favorites') ?></span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="inbox.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-comment-alt"></i></div>
                        <span><?= __('messages') ?></span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="settings.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-user-cog"></i></div>
                        <span><?= __('account_settings') ?></span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="help_support.php" class="menu-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-question-circle"></i></div>
                        <span><?= __('help_support') ?></span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>

                <a href="../logout.php" class="menu-link logout-link">
                    <div class="menu-link-content">
                        <div class="menu-icon"><i class="fa fa-sign-out-alt"></i></div>
                        <span><?= __('logout') ?></span>
                    </div>
                    <i class="fa fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .profile-page-wrapper {
        background-color: var(--background);
        min-height: 100vh;
    }

    .profile-header-premium {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 100%);
        color: white;
    }

    .profile-header-bg {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: url('https://www.transparenttextures.com/patterns/carbon-fibre.png');
        opacity: 0.1;
        z-index: 1;
    }

    .profile-hero-content {
        display: flex;
        align-items: center;
        gap: 32px;
    }

    .profile-avatar-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.2);
        cursor: pointer;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .profile-avatar-wrapper:hover {
        transform: scale(1.05);
    }

    .profile-avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .profile-avatar-placeholder {
        width: 100%;
        height: 100%;
        background: var(--white);
        color: var(--primary-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        font-weight: 800;
        border-radius: 50%;
    }

    .avatar-edit-badge {
        position: absolute;
        bottom: 5px;
        right: 5px;
        background: var(--accent-gold);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        border: 2px solid white;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .profile-titles h1 {
        font-size: 32px;
        font-weight: 800;
        margin-bottom: 4px;
        letter-spacing: -0.5px;
    }

    .profile-titles p {
        font-size: 16px;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Stats Grid */
    .profile-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: var(--white);
        padding: 24px;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-info h3 {
        font-size: 20px;
        font-weight: 800;
        color: var(--text-dark);
    }

    .stat-info p {
        font-size: 13px;
        color: var(--text-muted);
        font-weight: 500;
    }

    /* Menu Premium */
    .profile-menu-premium {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 24px;
        box-shadow: var(--shadow-sm);
    }

    .menu-title {
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: var(--text-muted);
        margin-bottom: 20px;
        font-weight: 700;
    }

    .menu-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        text-decoration: none;
        color: var(--text-dark);
        border-radius: 12px;
        transition: all 0.2s;
        margin-bottom: 8px;
    }

    .menu-link:hover {
        background: var(--background);
        transform: translateX(5px);
    }

    .menu-link-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .menu-icon {
        width: 40px;
        height: 40px;
        background: var(--background);
        color: var(--text-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 16px;
        transition: all 0.2s;
    }

    .menu-link:hover .menu-icon {
        background: var(--primary-green);
        color: white;
    }

    .menu-link span {
        font-weight: 600;
        font-size: 15px;
    }

    .menu-link i.fa-chevron-right {
        font-size: 12px;
        color: var(--border-color);
    }

    .logout-link {
        color: var(--danger);
        margin-top: 16px;
        border-top: 1px solid var(--border-color);
        padding-top: 24px;
    }

    .logout-link .menu-icon {
        background: #fef2f2;
        color: var(--danger);
    }

    [data-theme="dark"] .logout-link .menu-icon {
        background: rgba(239, 68, 68, 0.1);
    }

    .logout-link:hover .menu-icon {
        background: var(--danger);
        color: white;
    }

    .alert-success-premium {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 24px;
        font-weight: 600;
        border-left: 5px solid #2e7d32;
    }

    @media (max-width: 768px) {
        .profile-hero-content {
            flex-direction: column;
            text-align: center;
            gap: 16px;
        }

        .profile-stats-grid {
            grid-template-columns: 1fr;
        }

        .profile-titles h1 {
            font-size: 24px;
        }
    }

    .btn-delete-avatar-premium {
        margin-top: 12px;
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.25);
        color: #ef4444;
        padding: 8px 18px;
        border-radius: 24px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }
    .btn-delete-avatar-premium:hover {
        background: #ef4444;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        border-color: #ef4444;
    }
    
    /* Cropper styling */
    #crop-modal {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 100000;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    #crop-modal.active {
        opacity: 1;
        display: flex;
    }
    .crop-container-card {
        background: var(--white, #ffffff);
        max-width: 420px;
        width: 100%;
        border-radius: 28px;
        padding: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        text-align: center;
        box-sizing: border-box;
        transform: translateY(20px);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    #crop-modal.active .crop-container-card {
        transform: translateY(0);
    }
    .crop-viewport-wrapper {
        position: relative;
        width: 100%;
        height: 300px;
        background: #0f172a;
        border-radius: 20px;
        overflow: hidden;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .crop-mask {
        position: absolute;
        top: 50%; left: 50%;
        width: 200px; height: 200px;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        box-shadow: 0 0 0 999px rgba(15, 23, 42, 0.7);
        border: 2px dashed rgba(255, 255, 255, 0.8);
        pointer-events: none;
        z-index: 10;
    }
    .crop-slider-container {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
    }
    .crop-slider {
        flex: 1;
        height: 6px;
        border-radius: 3px;
        background: var(--border-color, #e2e8f0);
        outline: none;
        accent-color: var(--primary-green, #2e7d32);
    }
    .crop-btn-row {
        display: flex;
        gap: 16px;
    }
    .crop-btn {
        flex: 1;
        padding: 14px;
        border-radius: 16px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: all 0.2s ease;
    }
    .crop-btn.cancel {
        background: var(--background, #f8fafc);
        color: var(--text-dark, #1e293b);
        border: 1px solid var(--border-color, #e2e8f0);
    }
    .crop-btn.save {
        background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
    }
    .crop-btn:active {
        transform: scale(0.97);
    }
</style>

<!-- Crop Profile Picture Modal -->
<div id="crop-modal">
    <div class="crop-container-card">
        <h3 style="margin-top: 0; margin-bottom: 16px; color: var(--text-dark); font-weight: 800; font-size: 18px;">Crop Profile Picture</h3>
        
        <div class="crop-viewport-wrapper">
            <!-- Scrollable / draggable container -->
            <div id="cropper-container" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; user-select: none; touch-action: none;">
                <img id="cropper-image" style="position: absolute; max-width: none; max-height: none; transform-origin: center center; cursor: move; will-change: transform;" />
            </div>
            <!-- Circle crop mask visual overlay -->
            <div class="crop-mask"></div>
        </div>
        
        <!-- Zoom Slider Control -->
        <div class="crop-slider-container">
            <i class="fa fa-search-minus" style="color: var(--text-muted); font-size: 14px;"></i>
            <input type="range" id="crop-zoom" class="crop-slider" min="0.1" max="3" step="0.01" value="1">
            <i class="fa fa-search-plus" style="color: var(--text-muted); font-size: 14px;"></i>
        </div>
        
        <!-- Action Buttons -->
        <div class="crop-btn-row">
            <button type="button" class="crop-btn cancel" id="crop-cancel-btn">Cancel</button>
            <button type="button" class="crop-btn save" id="crop-save-btn">Upload</button>
        </div>
    </div>
</div>

<script>
(function() {
    let image = document.getElementById('cropper-image');
    let zoomSlider = document.getElementById('crop-zoom');
    let modal = document.getElementById('crop-modal');
    
    let posX = 0, posY = 0;
    let scale = 1;
    let isDragging = false;
    let startX, startY;
    let displayWidth = 0, displayHeight = 0;

    function updateTransform() {
        image.style.transform = `translate(${posX}px, ${posY}px) scale(${scale})`;
    }

    // Dragging - mouse events
    image.addEventListener('mousedown', (e) => {
        isDragging = true;
        startX = e.clientX - posX;
        startY = e.clientY - posY;
        image.style.cursor = 'grabbing';
    });

    window.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        posX = e.clientX - startX;
        posY = e.clientY - startY;
        updateTransform();
    });

    window.addEventListener('mouseup', () => {
        isDragging = false;
        image.style.cursor = 'move';
    });

    // Dragging - touch events for mobile/tablet WebViews
    image.addEventListener('touchstart', (e) => {
        if (e.touches.length === 1) {
            isDragging = true;
            startX = e.touches[0].clientX - posX;
            startY = e.touches[0].clientY - posY;
        }
    });

    image.addEventListener('touchmove', (e) => {
        if (!isDragging || e.touches.length !== 1) return;
        e.preventDefault();
        posX = e.touches[0].clientX - startX;
        posY = e.touches[0].clientY - startY;
        updateTransform();
    });

    image.addEventListener('touchend', () => {
        isDragging = false;
    });

    // Scale / zoom input control
    zoomSlider.addEventListener('input', (e) => {
        scale = parseFloat(e.target.value);
        updateTransform();
    });

    // Cancel Button
    document.getElementById('crop-cancel-btn').onclick = function() {
        modal.classList.remove('active');
        document.getElementById('profile_picture_input').value = '';
    };

    // Global entry point to launch cropper
    window.startProfileCropper = function(dataUrl) {
        image.src = dataUrl;
        zoomSlider.value = 1;
        scale = 1;
        posX = 0;
        posY = 0;
        
        image.onload = function() {
            const containerSize = 300; // matching .crop-viewport-wrapper dimensions
            const imgRatio = image.naturalWidth / image.naturalHeight;
            if (imgRatio > 1) {
                // Landscape
                displayHeight = containerSize;
                displayWidth = containerSize * imgRatio;
            } else {
                // Portrait
                displayWidth = containerSize;
                displayHeight = containerSize / imgRatio;
            }
            image.style.width = displayWidth + 'px';
            image.style.height = displayHeight + 'px';
            updateTransform();
        };
        
        modal.classList.add('active');
    };

    // Handle standard browser file selection
    window.handleProfileFileSelect = function(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                window.startProfileCropper(e.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    // Crop Canvas and Upload
    document.getElementById('crop-save-btn').onclick = function() {
        const canvas = document.createElement('canvas');
        canvas.width = 400;
        canvas.height = 400;
        const ctx = canvas.getContext('2d');
        
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        
        // Translate to canvas center (200, 200)
        ctx.translate(200, 200);
        
        // Scale ratio from 200px (circle viewport in container space) to 400px (output resolution)
        ctx.scale(2.0, 2.0);
        
        // Apply the translation
        ctx.translate(posX, posY);
        
        // Apply zoom scale
        ctx.scale(scale, scale);
        
        // Draw centered display image
        ctx.drawImage(image, -displayWidth / 2, -displayHeight / 2, displayWidth, displayHeight);
        
        const saveBtn = document.getElementById('crop-save-btn');
        const originalText = saveBtn.innerText;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading...';
        
        canvas.toBlob((blob) => {
            if (!blob) {
                alert("Failed to render cropped image.");
                saveBtn.disabled = false;
                saveBtn.innerText = originalText;
                return;
            }
            
            const formData = new FormData();
            formData.append('profile_picture', blob, 'profile_picture.jpg');
            
            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                window.location.reload();
            })
            .catch(err => {
                console.error("Upload error:", err);
                alert("Failed to upload profile picture.");
                saveBtn.disabled = false;
                saveBtn.innerText = originalText;
            });
        }, 'image/jpeg', 0.85);
    };
})();
</script>

<?php require_once '../includes/footer.php'; ?>