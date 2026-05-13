<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header("Location: my_ads.php");
    exit;
}

// Fetch product data
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
$stmt->execute([$product_id, $_SESSION['user_id']]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: my_ads.php");
    exit;
}

// Fetch existing images
$img_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id ASC");
$img_stmt->execute([$product_id]);
$existing_images = $img_stmt->fetchAll();

$error = '';
$success = '';

// Fetch all categories with is_perishable flag
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$all_cats = $stmt->fetchAll();

$l1_categories = [];
$l2_categories = [];
$cat_details = [];

foreach ($all_cats as $cat) {
    $cat_details[$cat['id']] = ['is_perishable' => $cat['is_perishable']];
    if (empty($cat['parent_id'])) {
        $l1_categories[] = $cat;
    } else {
        $l2_categories[$cat['parent_id']][] = $cat;
    }
}

$l2_json = json_encode($l2_categories);
$details_json = json_encode($cat_details);

// Find parent category for pre-filling
$current_l2 = $product['category_id'];
$current_l1 = null;
foreach ($all_cats as $cat) {
    if ($cat['id'] == $current_l2) {
        $current_l1 = $cat['parent_id'] ?: $cat['id'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'sell';
    $title = $_POST['title'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = isset($_POST['price']) ? preg_replace('/[^-0-9.]/', '', $_POST['price']) : '0';
    $contact_whatsapp = isset($_POST['contact_whatsapp']) ? 1 : 0;
    $contact_phone = isset($_POST['contact_phone']) ? 1 : 0;

    $whatsapp_number = $contact_whatsapp ? ($_POST['whatsapp_number'] ?? '') : '';
    $phone_number = $contact_phone ? ($_POST['phone_number'] ?? '') : '';
    $description = $_POST['description'] ?? '';
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    $location_name = $_POST['location_name'] ?? '';
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

    if (!empty($title) && !empty($category_id) && !empty($price)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, type = ?, title = ?, description = ?, price = ?, expiry_date = ?, whatsapp_number = ?, phone_number = ?, location_name = ?, latitude = ?, longitude = ?, status = 'pending' WHERE id = ?");
            $stmt->execute([$category_id, $type, $title, $description, $price, $expiry_date, $whatsapp_number, $phone_number, $location_name, $latitude, $longitude, $product_id]);

            // Handle image deletions
            if (isset($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $del_img_id) {
                    $sel_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
                    $sel_stmt->execute([$del_img_id, $product_id]);
                    $path = $sel_stmt->fetchColumn();
                    if ($path && file_exists('../' . $path))
                        @unlink('../' . $path);

                    $del_stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                    $del_stmt->execute([$del_img_id]);
                }
            }

            // Handle new image uploads
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $upload_dir = '../uploads/products/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if (empty($tmp_name))
                        continue;

                    $file_name = time() . '_' . $key . '.jpg';
                    $target_file = $upload_dir . $file_name;

                    if (function_exists('imagecreatefromjpeg') && compressProductImageLocal($tmp_name, $target_file, 800, 60)) {
                        $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                        $stmt->execute([$product_id, 'uploads/products/' . $file_name]);
                    } else {
                        $file_name = time() . '_' . $key . '_' . basename($_FILES['images']['name'][$key]);
                        $target_file = $upload_dir . $file_name;
                        if (move_uploaded_file($tmp_name, $target_file)) {
                            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                            $stmt->execute([$product_id, 'uploads/products/' . $file_name]);
                        }
                    }
                }
            }

            $pdo->commit();
            $success = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error updating ad. Please try again.";
        }
    } else {
        $error = "Please fill out all required fields.";
    }
}

function compressProductImageLocal($source, $destination, $max_width, $quality)
{
    if (!function_exists('imagecreatefromjpeg'))
        return false;
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg')
        $image = @imagecreatefromjpeg($source);
    elseif ($info['mime'] == 'image/gif')
        $image = @imagecreatefromgif($source);
    elseif ($info['mime'] == 'image/png')
        $image = @imagecreatefrompng($source);
    else
        return false;
    if (!$image)
        return false;

    list($width, $height) = getimagesize($source);
    $ratio = $width / $height;
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    $new_image = imagecreatetruecolor($new_width, $new_height);
    if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    $result = imagejpeg($new_image, $destination, $quality);
    imagedestroy($image);
    imagedestroy($new_image);
    return $result;
}

require_once '../includes/header.php';
?>

<div class="post-ad-page-premium">
    <div class="container">
        <?php if ($success): ?>
            <div class="post-success-card animate-success">
                <div class="success-icon-wrapper">
                    <div class="success-ring"></div>
                    <i class="fa fa-check-circle success-bounce"></i>
                </div>
                <h2 class="success-title">Ad Updated Successfully!</h2>
                <p class="success-subtitle">Your changes have been saved. Your ad is now under review and will be live
                    shortly.</p>
                <div class="success-actions">
                    <a href="my_ads.php" class="btn-manage-premium">
                        <i class="fa fa-th-list"></i>
                        Manage My Ads
                    </a>
                    <a href="../index.php" class="btn-home-premium">
                        <i class="fa fa-home"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="stepper-wrapper-premium">
                <div class="step active" id="step-dot-1">
                    <div class="dot">1</div>
                    <span>Info</span>
                </div>
                <div class="step-line"></div>
                <div class="step" id="step-dot-2">
                    <div class="dot">2</div>
                    <span>Media</span>
                </div>
                <div class="step-line"></div>
                <div class="step" id="step-dot-3">
                    <div class="dot">3</div>
                    <span>Final</span>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="editAdForm">
                <?php if ($error): ?>
                    <div class="alert-danger-premium"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- Step 1: Basic Info -->
                <div class="form-step active" id="step-1">
                    <div class="form-section-premium">
                        <div class="section-header">
                            <div class="section-num">01</div>
                            <h3>Basic Information</h3>
                        </div>
                        <div class="form-grid-premium">
                            <div class="form-group-premium full-width">
                                <label>Ad Type</label>
                                <div class="type-button-group">
                                    <label class="type-btn">
                                        <input type="radio" name="type" value="sell" <?= $product['type'] == 'sell' ? 'checked' : '' ?> onchange="updateTypeUI(this.value)">
                                        <span class="btn-content">
                                            <i class="fa fa-tag"></i> Sell Item
                                        </span>
                                    </label>
                                    <label class="type-btn">
                                        <input type="radio" name="type" value="buy" <?= $product['type'] == 'buy' ? 'checked' : '' ?> onchange="updateTypeUI(this.value)">
                                        <span class="btn-content">
                                            <i class="fa fa-shopping-basket"></i> Looking For
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group-premium full-width">
                                <label for="title">Ad Title *</label>
                                <input type="text" id="title" name="title" class="premium-input" required
                                    value="<?= htmlspecialchars($product['title']) ?>">
                            </div>
                            <div class="form-group-premium">
                                <label for="l1_category">Category *</label>
                                <select id="l1_category" class="premium-select" required onchange="updateL2Categories()">
                                    <?php foreach ($l1_categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $current_l1 == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group-premium" id="l2_category_group">
                                <label for="category_id">Sub-Category *</label>
                                <select id="category_id" name="category_id" class="premium-select" required
                                    onchange="checkPerishable(this.value)">
                                    <!-- Populated by JS -->
                                </select>
                            </div>
                            <div class="form-group-premium" id="price_group">
                                <label for="price"
                                    id="priceLabel"><?= $product['type'] == 'buy' ? 'Budget (₹)' : 'Price (₹)' ?> *</label>
                                <div class="price-input-wrapper">
                                    <span class="currency-symbol">₹</span>
                                    <input type="number" id="price" name="price" step="0.01"
                                        class="premium-input with-prefix" required value="<?= (float) $product['price'] ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions-premium">
                        <button type="button" class="btn-next-premium" onclick="goToStep(2)">
                            <span>Continue</span>
                            <i class="fa fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Media -->
                <div class="form-step" id="step-2">
                    <div class="form-section-premium">
                        <div class="section-header">
                            <div class="section-num">02</div>
                            <h3>Manage Photos</h3>
                        </div>

                        <div class="photo-management-side-wise">
                            <div class="add-photo-btn-card" onclick="document.getElementById('images').click()">
                                <i class="fa fa-camera-retro"></i>
                                <span>Add New</span>
                                <input type="file" id="images" name="images[]" multiple accept="image/*"
                                    style="display: none;" onchange="previewImages()">
                            </div>
                            <div id="image_preview_container" class="image-preview-side-container">
                                <?php foreach ($existing_images as $img): ?>
                                    <div class="preview-item" onclick="toggleExistingDelete(<?= $img['id'] ?>, this)">
                                        <img src="../<?= htmlspecialchars($img['image_path']) ?>">
                                        <div class="delete-overlay">
                                            <i class="fa fa-trash-alt"></i>
                                            <span>DELETE</span>
                                        </div>
                                        <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>"
                                            id="del-<?= $img['id'] ?>" style="display: none;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions-premium">
                        <button type="button" class="btn-back-premium" onclick="goToStep(1)">Back</button>
                        <button type="button" class="btn-next-premium" onclick="goToStep(3)">
                            <span>Continue</span>
                            <i class="fa fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Final Details -->
                <div class="form-step" id="step-3">
                    <div id="perishable_section" class="perishable-warning-premium" style="display: none;">
                        <div class="warning-icon"><i class="fa fa-leaf"></i></div>
                        <div class="warning-content">
                            <h4>Perishable Item</h4>
                            <div class="form-group-premium" style="margin-top: 10px;">
                                <label for="expiry_date">Expiry Date *</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="premium-input"
                                    min="<?= date('Y-m-d') ?>" value="<?= $product['expiry_date'] ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-section-premium">
                        <div class="section-header">
                            <div class="section-num">03</div>
                            <h3>Details & Contact</h3>
                        </div>
                        <div class="form-group-premium full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="premium-textarea"
                                rows="6"><?= htmlspecialchars($product['description']) ?></textarea>
                        </div>
                        <div class="form-grid-premium">
                            <div class="form-group-premium full-width">
                                <label>Contact Options *</label>
                                <div class="premium-contact-toggles">
                                    <label class="contact-toggle">
                                        <input type="checkbox" id="contact_whatsapp_chk" name="contact_whatsapp" value="1"
                                            onchange="toggleContact('whatsapp')" <?= !empty($product['whatsapp_number']) ? 'checked' : '' ?>>
                                        <div class="toggle-box whatsapp"><i
                                                class="fab fa-whatsapp"></i><span>WhatsApp</span></div>
                                    </label>
                                    <label class="contact-toggle">
                                        <input type="checkbox" id="contact_phone_chk" name="contact_phone" value="1"
                                            onchange="toggleContact('phone')" <?= !empty($product['phone_number']) ? 'checked' : '' ?>>
                                        <div class="toggle-box phone"><i class="fa fa-phone"></i><span>Call</span></div>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group-premium" id="whatsapp_group"
                                style="display: <?= !empty($product['whatsapp_number']) ? 'block' : 'none' ?>;">
                                <label>WhatsApp Number</label>
                                <input type="tel" id="whatsapp_number" name="whatsapp_number" class="premium-input"
                                    value="<?= htmlspecialchars($product['whatsapp_number']) ?>">
                            </div>
                            <div class="form-group-premium" id="phone_group"
                                style="display: <?= !empty($product['phone_number']) ? 'block' : 'none' ?>;">
                                <label>Phone Number</label>
                                <input type="tel" id="phone_number" name="phone_number" class="premium-input"
                                    value="<?= htmlspecialchars($product['phone_number']) ?>">
                            </div>
                            <div class="form-group-premium full-width">
                                <label>Location *</label>
                                <div class="premium-location-wrapper">
                                    <div class="location-input-box">
                                        <i class="fa fa-map-marker-alt"></i>
                                        <input type="text" id="location_name" name="location_name"
                                            class="premium-input with-icon" required
                                            value="<?= htmlspecialchars($product['location_name']) ?>">
                                    </div>
                                    <button type="button" onclick="detectPostLocation(event)"
                                        class="btn-detect-premium">Detect</button>
                                </div>
                                <input type="hidden" id="latitude" name="latitude" value="<?= $product['latitude'] ?>">
                                <input type="hidden" id="longitude" name="longitude" value="<?= $product['longitude'] ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-actions-premium">
                        <button type="button" class="btn-back-premium" onclick="goToStep(2)">Back</button>
                        <button type="submit" class="btn-post-premium" id="submitBtn">
                            <span>Save Changes</span>
                            <i class="fa fa-save"></i>
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Reuse styles from post_ad.php */
    .post-ad-page-premium {
        padding: 40px 0 100px;
        background: #F8FAFC;
        min-height: 100vh;
    }

    .stepper-wrapper-premium {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 50px;
    }

    .stepper-wrapper-premium .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        opacity: 0.4;
        transition: 0.3s;
    }

    .stepper-wrapper-premium .step.active {
        opacity: 1;
    }

    .stepper-wrapper-premium .dot {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #E2E8F0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: var(--text-dark);
    }

    .stepper-wrapper-premium .step.active .dot {
        background: var(--primary-green);
        color: white;
    }

    .stepper-wrapper-premium .step span {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .stepper-wrapper-premium .step-line {
        flex: 0 0 40px;
        height: 2px;
        background: #E2E8F0;
        margin-bottom: 24px;
    }

    .form-step {
        display: none;
    }

    .form-step.active {
        display: block;
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-section-premium {
        background: white;
        border-radius: 32px;
        padding: 40px;
        margin-bottom: 24px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.03);
        border: 1px solid rgba(0, 0, 0, 0.02);
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 32px;
    }

    .section-num {
        width: 28px;
        height: 28px;
        background: var(--primary-green);
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
    }

    .form-grid-premium {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .full-width {
        grid-column: span 2;
    }

    .premium-input,
    .premium-select,
    .premium-textarea {
        width: 100%;
        background: #F8FAFC;
        border: 2px solid #F1F5F9;
        border-radius: 16px;
        padding: 14px 20px;
        font-size: 15px;
        transition: 0.3s;
    }

    .premium-input:focus {
        border-color: var(--primary-green);
        background: white;
        outline: none;
    }

    .type-button-group {
        display: flex;
        background: #F1F5F9;
        padding: 6px;
        border-radius: 18px;
        gap: 6px;
    }

    .type-btn {
        flex: 1;
        cursor: pointer;
    }

    .type-btn input {
        display: none;
    }

    .type-btn .btn-content {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px 20px;
        border-radius: 14px;
        font-size: 14px;
        font-weight: 700;
        color: #64748B;
        transition: 0.3s;
    }

    .type-btn input:checked+.btn-content {
        background: white;
        color: var(--primary-green);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .form-actions-premium {
        display: flex;
        justify-content: center;
        gap: 16px;
        margin-top: 30px;
    }

    .btn-next-premium,
    .btn-post-premium {
        background: var(--primary-green);
        color: white;
        border: none;
        padding: 16px 40px;
        border-radius: 20px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-back-premium {
        background: #E2E8F0;
        color: var(--text-dark);
        border: none;
        padding: 16px 30px;
        border-radius: 20px;
        font-weight: 700;
        cursor: pointer;
    }

    .premium-contact-toggles {
        display: flex;
        gap: 12px;
    }

    .contact-toggle {
        flex: 1;
        cursor: pointer;
    }

    .contact-toggle input {
        display: none;
    }

    .toggle-box {
        padding: 14px;
        background: #F8FAFC;
        border: 2px solid #F1F5F9;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        font-weight: 700;
        color: #64748B;
        transition: 0.3s;
    }

    .contact-toggle input:checked+.toggle-box.whatsapp {
        border-color: #25D366;
        background: #F0FDF4;
        color: #166534;
    }

    .contact-toggle input:checked+.toggle-box.phone {
        border-color: var(--primary-green);
        background: #F0FDF4;
        color: var(--primary-green);
    }

    .upload-area-premium {
        border: 2px dashed #E2E8F0;
        border-radius: 24px;
        padding: 40px 20px;
        text-align: center;
        cursor: pointer;
        transition: 0.3s;
    }

    .upload-area-premium:hover {
        border-color: var(--primary-green);
        background: #F0FDF4;
    }

    /* Photo Management Side-Wise */
    .photo-management-side-wise {
        display: flex;
        gap: 16px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 24px;
        border: 2px dashed #e2e8f0;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        scroll-behavior: smooth;
    }

    .photo-management-side-wise::-webkit-scrollbar {
        display: none;
    }

    .add-photo-btn-card {
        min-width: 110px;
        height: 110px;
        background: white;
        border-radius: 18px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        border: 2px solid #f1f5f9;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        color: var(--text-muted);
        flex-shrink: 0;
    }

    .add-photo-btn-card:hover {
        border-color: var(--primary-green);
        color: var(--primary-green);
        background: #f0fdf4;
        transform: scale(0.98);
    }

    .add-photo-btn-card i {
        font-size: 24px;
    }

    .add-photo-btn-card span {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .image-preview-side-container {
        display: flex;
        gap: 16px;
    }

    .preview-item {
        width: 110px;
        height: 110px;
        border-radius: 18px;
        overflow: hidden;
        position: relative;
        cursor: pointer;
        background: white;
        border: 2px solid white;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        flex-shrink: 0;
    }

    @keyframes popIn {
        0% {
            transform: scale(0.5);
            opacity: 0;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .preview-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
        border-color: var(--primary-green);
    }

    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.4s;
    }

    .preview-item:hover img {
        transform: scale(1.1);
    }

    .delete-overlay {
        position: absolute;
        inset: 0;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
        font-weight: 800;
        backdrop-filter: blur(4px);
        background: rgba(239, 68, 68, 0.7);
        z-index: 5;
    }

    .delete-overlay i {
        font-size: 24px;
        margin-bottom: 4px;
    }

    .preview-item.marked-delete .delete-overlay {
        display: flex;
    }

    .preview-item.marked-delete {
        border-color: #EF4444;
        transform: scale(0.95);
        opacity: 0.8;
    }

    .premium-location-wrapper {
        display: flex;
        gap: 10px;
    }

    .location-input-box {
        flex: 1;
        position: relative;
    }

    .location-input-box i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary-green);
    }

    .premium-input.with-icon {
        padding-left: 45px;
    }

    .btn-detect-premium {
        padding: 0 20px;
        background: #F1F5F9;
        border: none;
        border-radius: 14px;
        font-weight: 700;
        color: var(--text-dark);
        cursor: pointer;
    }

    .post-success-card {
        background: white;
        border-radius: 40px;
        padding: 80px 40px;
        text-align: center;
        max-width: 650px;
        margin: 60px auto;
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(0, 0, 0, 0.02);
        position: relative;
        overflow: hidden;
    }

    .animate-success {
        animation: fadeInUp 0.8s cubic-bezier(0.2, 1, 0.3, 1);
    }

    .success-icon-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
        margin: 0 auto 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .success-ring {
        position: absolute;
        width: 100%;
        height: 100%;
        border: 4px solid #f0fdf4;
        border-radius: 50%;
        animation: pulseRing 2s infinite;
    }

    .success-bounce {
        font-size: 80px;
        color: var(--primary-green);
        z-index: 2;
        animation: iconBounce 1s cubic-bezier(0.17, 0.67, 0.83, 0.67);
    }

    .success-title {
        font-size: 32px;
        font-weight: 900;
        color: var(--text-dark);
        margin-bottom: 16px;
        letter-spacing: -0.5px;
    }

    .success-subtitle {
        font-size: 16px;
        color: #64748b;
        max-width: 450px;
        margin: 0 auto 40px;
        line-height: 1.6;
    }

    .success-actions {
        display: flex;
        gap: 20px;
        justify-content: center;
        flex-wrap: wrap;
    }

    .btn-manage-premium {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 36px;
        background: linear-gradient(135deg, var(--primary-green) 0%, #166534 100%);
        color: white;
        text-decoration: none;
        border-radius: 20px;
        font-weight: 800;
        font-size: 16px;
        box-shadow: 0 10px 25px rgba(22, 101, 52, 0.2);
        transition: all 0.3s cubic-bezier(0.2, 1, 0.3, 1);
    }

    .btn-manage-premium:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 15px 35px rgba(22, 101, 52, 0.3);
    }

    .btn-home-premium {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 36px;
        background: white;
        color: var(--text-dark);
        text-decoration: none;
        border-radius: 20px;
        font-weight: 800;
        font-size: 16px;
        border: 2px solid #f1f5f9;
        transition: all 0.3s ease;
    }

    .btn-home-premium:hover {
        background: #f8fafc;
        border-color: #e2e8f0;
        transform: translateY(-2px);
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(40px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes iconBounce {
        0% {
            transform: scale(0);
        }

        60% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
        }
    }

    @keyframes pulseRing {
        0% {
            transform: scale(0.8);
            opacity: 0.5;
        }

        100% {
            transform: scale(1.3);
            opacity: 0;
        }
    }

    .perishable-warning-premium {
        background: #FFF7ED;
        border: 2px solid #FFEDD5;
        border-radius: 24px;
        padding: 24px;
        display: flex;
        gap: 20px;
        margin-bottom: 24px;
        align-items: center;
    }

    .warning-icon {
        width: 48px;
        height: 48px;
        background: #FFEDD5;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: #9A3412;
    }
</style>

<script>
    const l2Categories = <?= $l2_json ?>;
    const catDetails = <?= $details_json ?>;
    const currentL2 = <?= $product['category_id'] ?>;

    function goToStep(n) {
        document.querySelectorAll('.form-step').forEach(step => step.classList.remove('active'));
        document.getElementById('step-' + n).classList.add('active');

        document.querySelectorAll('.stepper-wrapper-premium .step').forEach((step, idx) => {
            if (idx < n) step.classList.add('active');
            else step.classList.remove('active');
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateTypeUI(type) {
        const priceLabel = document.getElementById('priceLabel');
        priceLabel.innerText = (type === 'sell') ? 'Price (₹) *' : 'Budget (₹) *';
    }

    function updateL2Categories() {
        const l1Select = document.getElementById('l1_category');
        const l2Group = document.getElementById('l2_category_group');
        const l2Select = document.getElementById('category_id');
        const selectedL1 = l1Select.value;

        l2Select.innerHTML = '<option value="">Select Sub-Category</option>';

        if (selectedL1 && l2Categories[selectedL1]) {
            l2Group.style.display = 'block';
            l2Categories[selectedL1].forEach(cat => {
                const option = document.createElement('option');
                option.value = cat.id;
                option.textContent = cat.name;
                if (cat.id == currentL2) option.selected = true;
                l2Select.appendChild(option);
            });
            checkPerishable(l2Select.value || currentL2);
        } else {
            l2Group.style.display = 'none';
        }
    }

    function checkPerishable(catId) {
        const section = document.getElementById('perishable_section');
        const expiryInput = document.getElementById('expiry_date');
        if (catId && catDetails[catId] && catDetails[catId].is_perishable == 1) {
            section.style.display = 'flex';
            expiryInput.required = true;
        } else {
            section.style.display = 'none';
            expiryInput.required = false;
        }
    }

    function toggleContact(type) {
        const chk = document.getElementById('contact_' + type + '_chk');
        const group = document.getElementById(type + '_group');
        const input = document.getElementById(type + '_number');
        group.style.display = chk.checked ? 'block' : 'none';
        input.required = chk.checked;
    }

    function toggleExistingDelete(id, el) {
        const chk = document.getElementById('del-' + id);
        chk.checked = !chk.checked;
        el.classList.toggle('marked-delete', chk.checked);
    }

    function previewImages() {
        const container = document.getElementById('image_preview_container');
        const files = document.getElementById('images').files;
        container.innerHTML = '';

        for (let i = 0; i < files.length; i++) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `<img src="${e.target.result}">`;
                container.appendChild(div);
            };
            reader.readAsDataURL(files[i]);
        }
    }

    async function detectPostLocation(e) {
        e.preventDefault();
        if (!navigator.geolocation) return;
        const btn = e.currentTarget;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        navigator.geolocation.getCurrentPosition(async (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            try {
                const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`);
                const data = await resp.json();
                document.getElementById('location_name').value = data.address.city || data.address.town || data.display_name;
            } catch (e) { }
            btn.innerHTML = 'Detect';
        }, () => {
            btn.innerHTML = 'Detect';
        });
    }

    window.onload = () => {
        updateL2Categories();
        updateTypeUI(document.querySelector('input[name="type"]:checked').value);
    };
</script>

<?php require_once '../includes/footer.php'; ?>