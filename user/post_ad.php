<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../guest/login.php");
    exit;
}

// Auto-add phone_number column if it doesn't exist
try {
    $pdo->exec("ALTER TABLE products ADD COLUMN phone_number VARCHAR(20) DEFAULT NULL");
} catch (PDOException $e) {
}

$error = '';
$success = '';

// Fetch all categories with is_perishable flag
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$all_cats = $stmt->fetchAll();

$l1_categories = [];
$l2_categories = [];
$cat_details = []; // Map for easy JS lookup

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
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (!empty($title) && !empty($category_id) && !empty($price)) {
        // Validate expiry if perishable
        $is_cat_perishable = $cat_details[$category_id]['is_perishable'] ?? 0;
        if ($is_cat_perishable && empty($expiry_date)) {
            $error = "Expiry date is mandatory for perishable / edible items.";
        } elseif (($contact_whatsapp && empty($whatsapp_number)) || ($contact_phone && empty($phone_number))) {
            $error = "Please provide the required contact numbers.";
        } else {
            try {
                $pdo->beginTransaction();

                // [AD APPROVAL SYSTEM] Generate Unique ID and Check Mode
                $unique_id = 'ENTAGD' . rand(1000, 9999);
                // Ensure uniqueness
                $check_stmt = $pdo->prepare("SELECT 1 FROM products WHERE unique_id = ?");
                $check_stmt->execute([$unique_id]);
                while ($check_stmt->fetch()) {
                    $unique_id = 'ENTAGD' . rand(1000, 9999);
                    $check_stmt->execute([$unique_id]);
                }

                // Get approval mode from settings
                $set_stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'ad_approval_mode'");
                $set_stmt->execute();
                $approval_mode = $set_stmt->fetchColumn() ?: 'auto';
                $status = ($approval_mode === 'manual') ? 'pending' : 'active';

                $stmt = $pdo->prepare("INSERT INTO products (user_id, unique_id, category_id, type, title, description, price, expiry_date, whatsapp_number, phone_number, location_name, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $unique_id, $category_id, $type, $title, $description, $price, $expiry_date, $whatsapp_number, $phone_number, $location_name, $latitude, $longitude, $status]);
                $product_id = $pdo->lastInsertId();

                // Handle image upload with compression
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../uploads/products/';
                    if (!is_dir($upload_dir))
                        mkdir($upload_dir, 0777, true);

                    $cover_idx = (int) ($_POST['cover_index'] ?? 0);
                    $indices = array_keys($_FILES['images']['tmp_name']);

                    if (in_array($cover_idx, $indices)) {
                        $indices = array_diff($indices, [$cover_idx]);
                        array_unshift($indices, $cover_idx);
                    }

                    foreach ($indices as $key) {
                        $tmp_name = $_FILES['images']['tmp_name'][$key];
                        if (empty($tmp_name))
                            continue;

                        $file_name = time() . '_' . $key . '.jpg';
                        $target_file = $upload_dir . $file_name;

                        if (function_exists('imagecreatefromjpeg') && compressImage($tmp_name, $target_file, 800, 60)) {
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

                if ($status === 'pending') {
                    $success = "
                    <div style='text-align:center; padding: 20px 0;'>
                        <div style='background: #fff8e1; color: #f57c00; border-radius: 50%; width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;'>
                            <i class='fa fa-clock' style='font-size: 32px;'></i>
                        </div>
                        <h3 style='margin-bottom: 8px;'>Ad Submitted for Review</h3>
                        <p style='color: var(--text-muted); font-size: 14px;'>Your unique ID is <strong>$unique_id</strong>. Our team will review and approve your ad shortly.</p>
                    </div>";
                } else {
                    $success = "
                    <div style='text-align:center; padding: 20px 0;'>
                        <div style='background: #e8f5e9; color: var(--primary-green); border-radius: 50%; width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;'>
                            <i class='fa fa-check-circle' style='font-size: 32px;'></i>
                        </div>
                        <h3 style='margin-bottom: 8px;'>Congratulations! Your Ad is Live</h3>
                        <p style='color: var(--text-muted); font-size: 14px;'>Your unique ID is <strong>$unique_id</strong>. Your ad is now visible to thousands of buyers.</p>
                    </div>";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error posting ad. Please try again.";
            }
        }
    } else {
        $error = "Please fill out all required fields.";
    }
}

function compressImage($source, $destination, $max_width, $quality)
{
    $info = getimagesize($source);
    if ($info['mime'] == 'image/jpeg')
        $image = imagecreatefromjpeg($source);
    elseif ($info['mime'] == 'image/gif')
        $image = imagecreatefromgif($source);
    elseif ($info['mime'] == 'image/png')
        $image = imagecreatefrompng($source);
    else
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

<div class="container">
    <div class="form-container-card">
        <h2 style="margin-bottom: 24px; color: var(--primary-green-dark);">Post an Ad</h2>

        <?php if ($error): ?>
            <div class="alert-danger-light">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-success-light">
                <?= $success ?> <a href="index.php" style="font-weight: bold; color: var(--primary-green-dark);">Go to
                    Dashboard</a>
            </div>
        <?php else: ?>
            <form method="POST" action="post_ad.php" enctype="multipart/form-data">
                <!-- Ad Type Selection -->
                <div class="form-group" style="margin-bottom: 24px;">
                    <div class="type-selector">
                        <label class="type-btn-label">
                            <input type="radio" name="type" value="sell" checked style="display: none;"
                                onchange="updateTypeUI(this.value)">
                            <div class="type-btn-box active-sell" id="btn-sell">
                                <i class="fa fa-tag" style="margin-right: 8px;"></i> Sell
                            </div>
                        </label>
                        <label class="type-btn-label">
                            <input type="radio" name="type" value="buy" style="display: none;"
                                onchange="updateTypeUI(this.value)">
                            <div class="type-btn-box" id="btn-buy">
                                <i class="fa fa-shopping-basket" style="margin-right: 8px;"></i> Wanted
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">Ad Title *</label>
                    <input type="text" id="title" name="title" class="form-control" required
                        placeholder="e.g. Home Made Biriyani">
                </div>

                <div class="form-group">
                    <label for="l1_category">Main Category *</label>
                    <select id="l1_category" class="form-control" required onchange="updateL2Categories()">
                        <option value="">Select Main Category</option>
                        <?php foreach ($l1_categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="l2_category_group" style="display: none;">
                    <label for="category_id">Sub-Category *</label>
                    <select id="category_id" name="category_id" class="form-control" required
                        onchange="checkPerishable(this.value)">
                        <option value="">Select Sub-Category</option>
                    </select>
                </div>

                <!-- Perishable Warning & Expiry Section -->
                <div id="perishable_section"
                    style="display: none; background: #fff8f1; border: 1px solid #ffccbc; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                        <i class="fa fa-exclamation-triangle" style="color: #e64a19; font-size: 20px;"></i>
                        <div>
                            <h4 style="color: #d84315; margin-bottom: 4px;">Perishable Item Policy</h4>
                            <p style="font-size: 13px; color: #5d4037; line-height: 1.5;">You are listing an edible or
                                perishable item. For safety:
                            <ul style="font-size: 13px; color: #5d4037; margin-top: 8px; padding-left: 20px;">
                                <li>Ensure the item is fresh and safe for consumption.</li>
                                <li>You must provide an accurate Best Before / Expiry date.</li>
                                <li>State any storage instructions in the description.</li>
                            </ul>
                            </p>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="expiry_date">Best Before / Expiry Date *</label>
                        <input type="date" id="expiry_date" name="expiry_date" class="form-control"
                            min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <script>
                    const l2Categories = <?= $l2_json ?>;
                    const catDetails = <?= $details_json ?>;

                    function updateL2Categories() {
                        const l1Select = document.getElementById('l1_category');
                        const l2Group = document.getElementById('l2_category_group');
                        const l2Select = document.getElementById('category_id');

                        const selectedL1 = l1Select.value;
                        l2Select.innerHTML = '<option value="">Select Sub-Category</option>';
                        hidePerishableSection();

                        if (selectedL1 && l2Categories[selectedL1]) {
                            l2Group.style.display = 'block';
                            l2Categories[selectedL1].forEach(cat => {
                                const option = document.createElement('option');
                                option.value = cat.id;
                                option.textContent = cat.name;
                                l2Select.appendChild(option);
                            });
                        } else {
                            l2Group.style.display = 'none';
                            if (selectedL1) {
                                checkPerishable(selectedL1);
                            }
                        }
                    }

                    function checkPerishable(catId) {
                        const section = document.getElementById('perishable_section');
                        const expiryInput = document.getElementById('expiry_date');

                        if (catId && catDetails[catId] && catDetails[catId].is_perishable == 1) {
                            section.style.display = 'block';
                            expiryInput.required = true;
                        } else {
                            hidePerishableSection();
                        }
                    }

                    function hidePerishableSection() {
                        const section = document.getElementById('perishable_section');
                        const expiryInput = document.getElementById('expiry_date');
                        section.style.display = 'none';
                        expiryInput.required = false;
                        expiryInput.value = '';
                    }
                </script>

                <div class="form-group">
                    <label for="price" id="priceLabel">Price (₹) *</label>
                    <input type="number" id="price" name="price" step="0.01" class="form-control" required
                        placeholder="Enter amount">
                </div>

                <div class="form-group">
                    <label>Contact Options *</label>
                    <div class="contact-options-grid">
                        <input type="checkbox" id="contact_whatsapp_chk" name="contact_whatsapp" value="1"
                            style="display: none;" onchange="toggleContact('whatsapp')">
                        <label for="contact_whatsapp_chk" id="label_whatsapp" class="toggle-btn">
                            <i class="fab fa-whatsapp" style="font-size: 18px;"></i> WhatsApp
                        </label>

                        <input type="checkbox" id="contact_phone_chk" name="contact_phone" value="1" style="display: none;"
                            onchange="toggleContact('phone')">
                        <label for="contact_phone_chk" id="label_phone" class="toggle-btn">
                            <i class="fa fa-phone" style="font-size: 16px;"></i> Call
                        </label>
                    </div>

                    <div id="whatsapp_group" style="display: none; margin-bottom: 15px;">
                        <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control"
                            placeholder="WhatsApp Number">
                    </div>
                    <div id="phone_group" style="display: none; margin-bottom: 15px;">
                        <input type="tel" id="phone_number" name="phone_number" class="form-control"
                            placeholder="Phone Number">
                    </div>
                </div>

                <script>
                    function toggleContact(type) {
                        const chk = document.getElementById('contact_' + type + '_chk');
                        const group = document.getElementById(type + '_group');
                        const input = document.getElementById(type + '_number');
                        if (chk.checked) {
                            group.style.display = 'block';
                            input.required = true;
                        } else {
                            group.style.display = 'none';
                            input.required = false;
                        }
                    }
                </script>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="5"
                        placeholder="Describe the item... Include storage instructions for food items."></textarea>
                </div>

                <div class="form-group">
                    <label for="location_name">Location *</label>
                    <div class="location-detect-wrapper">
                        <input type="text" id="location_name" name="location_name" class="form-control" required
                            placeholder="City, Area"
                            value="<?= htmlspecialchars($_SESSION['user_location']['name'] ?? '') ?>">
                        <button type="button" onclick="detectPostLocation(event)" class="btn-secondary"
                            style="padding: 8px 12px; white-space: nowrap;">
                            <i class="fa fa-crosshairs"></i> Detect
                        </button>
                    </div>
                    <input type="hidden" id="latitude" name="latitude"
                        value="<?= $_SESSION['user_location']['lat'] ?? '' ?>">
                    <input type="hidden" id="longitude" name="longitude"
                        value="<?= $_SESSION['user_location']['lng'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label>Photos (Select multiple) *</label>
                    <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/*"
                        style="display: none;" onchange="previewImages()">
                    <div class="upload-zone" onclick="document.getElementById('images').click()">
                        <i class="fa fa-camera upload-icon"></i>
                        <p class="upload-text">Add Photos</p>
                    </div>
                    <div id="image_preview_container" class="image-preview-grid"></div>
                    <input type="hidden" id="cover_index" name="cover_index" value="0">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 16px;">Post Ad Now</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateTypeUI(type) {
        const btnSell = document.getElementById('btn-sell');
        const btnBuy = document.getElementById('btn-buy');
        const priceLabel = document.getElementById('priceLabel');
        if (type === 'sell') {
            btnSell.classList.add('active-sell'); btnSell.classList.remove('active-buy');
            btnBuy.classList.remove('active-sell', 'active-buy');
            priceLabel.innerText = "Price (₹)";
        } else {
            btnBuy.classList.add('active-buy'); btnBuy.classList.remove('active-sell');
            btnSell.classList.remove('active-sell', 'active-buy');
            priceLabel.innerText = "Budget (₹)";
        }
    }

    async function detectPostLocation(event) {
        if (!navigator.geolocation) return;
        const btn = event.currentTarget;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition(async (pos) => {
            const lat = pos.coords.latitude; const lng = pos.coords.longitude;
            document.getElementById('latitude').value = lat; document.getElementById('longitude').value = lng;
            try {
                const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`);
                const d = await r.json();
                document.getElementById('location_name').value = d.address.city || d.address.town || 'Current Location';
            } catch (e) { document.getElementById('location_name').value = 'Current Location'; }
            btn.innerHTML = '<i class="fa fa-crosshairs"></i> Detect'; btn.disabled = false;
        }, () => { btn.innerHTML = '<i class="fa fa-crosshairs"></i> Detect'; btn.disabled = false; });
    }

    function previewImages() {
        const container = document.getElementById('image_preview_container');
        const files = document.getElementById('images').files;
        container.innerHTML = '';
        for (let i = 0; i < files.length; i++) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'preview-item' + (i === 0 ? ' is-cover' : '');
                div.id = 'preview-' + i;
                div.onclick = () => setAsCover(i);
                div.innerHTML = `<img src="${e.target.result}"><div class="cover-badge">Main</div><div class="order-num">${i + 1}</div>`;
                container.appendChild(div);
            }
            reader.readAsDataURL(files[i]);
        }
    }

    function setAsCover(i) {
        document.querySelectorAll('.preview-item').forEach(el => el.classList.remove('is-cover'));
        document.getElementById('preview-' + i).classList.add('is-cover');
        document.getElementById('cover_index').value = i;
    }
</script>

<?php require_once '../includes/footer.php'; ?>