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
        // 1. Server-side text profanity and NSFW moderation
        if (isTextInappropriate($title) || isTextInappropriate($description)) {
            $error = "Your ad title or description contains restricted keywords.";
        }
        // 2. Server-side video file exclusion
        elseif (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['type'] as $type) {
                if (strpos($type, 'video/') === 0) {
                    $error = "Videos are not allowed on ad postings.";
                    break;
                }
            }
            if (empty($error)) {
                // 3. Server-side image NSFW classification via Sightengine (with local fallback if empty API keys)
                foreach ($_FILES['images']['tmp_name'] as $tmp_name) {
                    if (!empty($tmp_name) && isImageNSFW($tmp_name)) {
                        $error = "Inappropriate content detected: one or more of your uploaded images violate our safety guidelines.";
                        break;
                    }
                }
            }
        }

        if (!empty($error)) {
            // Keep error message
        } else {
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
                            @chmod($target_file, 0644);
                            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
                            $stmt->execute([$product_id, 'uploads/products/' . $file_name]);
                        } else {
                            $file_name = time() . '_' . $key . '_' . basename($_FILES['images']['name'][$key]);
                            $target_file = $upload_dir . $file_name;
                            if (move_uploaded_file($tmp_name, $target_file)) {
                                @chmod($target_file, 0644);
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
    return $result;
}

require_once '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/post_ad.css?v=1.3">

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
                                        <input type="radio" name="type" value="rent" <?= $product['type'] == 'rent' ? 'checked' : '' ?> onchange="updateTypeUI(this.value)">
                                        <span class="btn-content">
                                            <i class="fa fa-key"></i> Rent Item
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

<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loader-box">
        <div class="premium-spinner"></div>
        <h3 id="loaderTitle">Saving Changes</h3>
        <p id="loaderDesc">Please wait while we update your ad...</p>
    </div>
</div>

<script>
    const l2Categories = <?= $l2_json ?>;
    const catDetails = <?= $details_json ?>;
    const currentL2 = <?= $product['category_id'] ?>;
</script>
<script src="../assets/js/edit_ad.js"></script>

<?php require_once '../includes/footer.php'; ?>