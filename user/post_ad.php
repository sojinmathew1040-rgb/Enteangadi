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

// Fetch all categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$all_cats = $stmt->fetchAll();

$l1_categories = [];
$l2_categories = [];
foreach ($all_cats as $cat) {
    if (empty($cat['parent_id'])) {
        $l1_categories[] = $cat;
    } else {
        $l2_categories[$cat['parent_id']][] = $cat;
    }
}
$l2_json = json_encode($l2_categories);

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

    $location_name = $_POST['location_name'] ?? '';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (!empty($title) && !empty($category_id) && !empty($price)) {
        if (($contact_whatsapp && empty($whatsapp_number)) || ($contact_phone && empty($phone_number))) {
            $error = "Please provide the required contact numbers for the selected options.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO products (user_id, category_id, type, title, description, price, whatsapp_number, phone_number, location_name, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $category_id, $type, $title, $description, $price, $whatsapp_number, $phone_number, $location_name, $latitude, $longitude]);
                $product_id = $pdo->lastInsertId();

                // Handle image upload with compression
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../uploads/products/';
                    if (!is_dir($upload_dir))
                        mkdir($upload_dir, 0777, true);

                    $cover_idx = (int) ($_POST['cover_index'] ?? 0);
                    $indices = array_keys($_FILES['images']['tmp_name']);

                    // Move cover index to front of processing
                    if (in_array($cover_idx, $indices)) {
                        $indices = array_diff($indices, [$cover_idx]);
                        array_unshift($indices, $cover_idx);
                    }

                    foreach ($indices as $key) {
                        $tmp_name = $_FILES['images']['tmp_name'][$key];
                        if (empty($tmp_name))
                            continue;

                        $file_ext = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
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
                $success = "Your ad has been posted successfully!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error posting ad. Please try again.";
            }
        }
    } else {
        $error = "Please fill out all required fields.";
    }
}

/**
 * Compress and standardize image
 */
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

    // Get original dimensions
    list($width, $height) = getimagesize($source);

    // Calculate new dimensions
    $ratio = $width / $height;
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    } else {
        $new_width = $width;
        $new_height = $height;
    }

    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);

    // Handle transparency for PNG/GIF
    if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Save as JPEG for better compression
    $result = imagejpeg($new_image, $destination, $quality);

    imagedestroy($image);
    imagedestroy($new_image);

    return $result;
}

require_once '../includes/header.php';
?>

<style>
    .toggle-btn {
        flex: 1;
        padding: 14px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: #fff;
        color: var(--text-muted);
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    input[type="checkbox"]:checked+.toggle-btn {
        background: var(--primary-green);
        color: white;
        border-color: var(--primary-green);
        box-shadow: 0 4px 12px rgba(46, 125, 50, 0.2);
    }

    .preview-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid var(--border-color);
    }

    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .preview-item.is-cover {
        border-color: var(--primary-green);
    }

    .preview-item .cover-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        background: var(--primary-green);
        color: white;
        padding: 2px 6px;
        font-size: 10px;
        border-radius: 4px;
        display: none;
    }

    .preview-item.is-cover .cover-badge {
        display: block;
    }

    .preview-item .order-num {
        position: absolute;
        top: 4px;
        right: 4px;
        background: rgba(0, 0, 0, 0.5);
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
    }
</style>

<div class="container">
    <div
        style="max-width: 600px; margin: 0 auto; background: var(--white); padding: 40px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
        <h2 style="margin-bottom: 24px; color: var(--primary-green-dark);">Post an Ad</h2>

        <?php if ($error): ?>
            <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= $success ?> <a href="index.php" style="font-weight: bold;">Go to Dashboard</a>
            </div>
        <?php else: ?>
            <form method="POST" action="post_ad.php" enctype="multipart/form-data">
                <!-- Ad Type Selection -->
                <div class="form-group" style="margin-bottom: 24px;">

                    <div style="display: flex; gap: 12px;">
                        <label style="flex: 1; cursor: pointer;">
                            <input type="radio" name="type" value="sell" checked style="display: none;"
                                onchange="updateTypeUI(this.value)">
                            <div class="type-btn active" id="btn-sell"
                                style="padding: 14px; border-radius: 12px; border: 2px solid var(--primary-green); text-align: center; background: #e8f5e9; color: var(--primary-green); font-weight: 700;">
                                <i class="fa fa-tag" style="margin-right: 8px;"></i> Sell
                            </div>
                        </label>
                        <label style="flex: 1; cursor: pointer;">
                            <input type="radio" name="type" value="buy" style="display: none;"
                                onchange="updateTypeUI(this.value)">
                            <div class="type-btn" id="btn-buy"
                                style="padding: 14px; border-radius: 12px; border: 2px solid #eee; text-align: center; background: #f8f9fa; color: var(--text-muted); font-weight: 700;">
                                <i class="fa fa-shopping-basket" style="margin-right: 8px;"></i> Wanted
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="title">Ad Title *</label>
                    <input type="text" id="title" name="title" class="form-control" required
                        placeholder="e.g. iPhone 13 Pro Max">
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
                    <select id="category_id" name="category_id" class="form-control" required>
                        <option value="">Select Sub-Category</option>
                    </select>
                </div>

                <script>
                    const l2Categories = <?= $l2_json ?>;
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
                                l2Select.appendChild(option);
                            });
                        } else {
                            l2Group.style.display = 'none';
                            if (selectedL1) {
                                // If L1 has no subcategories, assign the L1 ID as the category
                                const option = document.createElement('option');
                                option.value = selectedL1;
                                option.textContent = "Default";
                                l2Select.appendChild(option);
                                l2Select.value = selectedL1;
                            }
                        }
                    }
                </script>

                <div class="form-group">
                    <label for="price" id="priceLabel">Price (₹) *</label>
                    <input type="number" id="price" name="price" step="0.01" class="form-control" required
                        placeholder="Enter amount">
                </div>

                <div class="form-group">
                    <label>Contact Options *</label>
                    <div style="display: flex; gap: 12px; margin-bottom: 15px;">
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
                            placeholder="WhatsApp Number (e.g. 9876543210)">
                    </div>
                    <div id="phone_group" style="display: none; margin-bottom: 15px;">
                        <input type="tel" id="phone_number" name="phone_number" class="form-control"
                            placeholder="Phone Number for Calls">
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
                        placeholder="Describe what you are selling..."></textarea>
                </div>

                <div class="form-group">
                    <label for="location_name">Location *</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="location_name" name="location_name" class="form-control" required
                            placeholder="City, Area"
                            value="<?= htmlspecialchars($_SESSION['user_location']['name'] ?? '') ?>">
                        <button type="button" onclick="detectPostLocation()" class="btn-secondary"
                            style="padding: 8px 12px; white-space: nowrap;">
                            <i class="fa fa-crosshairs"></i> Detect
                        </button>
                    </div>
                    <input type="hidden" id="latitude" name="latitude"
                        value="<?= $_SESSION['user_location']['lat'] ?? '' ?>">
                    <input type="hidden" id="longitude" name="longitude"
                        value="<?= $_SESSION['user_location']['lng'] ?? '' ?>">
                </div>

                <script>
                    async function detectPostLocation() {
                        if (!navigator.geolocation) {
                            alert('Geolocation is not supported');
                            return;
                        }

                        const btn = event.currentTarget;
                        const originalHTML = btn.innerHTML;
                        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
                        btn.disabled = true;

                        navigator.geolocation.getCurrentPosition(async (position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;

                            document.getElementById('latitude').value = lat;
                            document.getElementById('longitude').value = lng;

                            try {
                                const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`);
                                const data = await resp.json();
                                const city = data.address.city || data.address.town || data.address.village || 'Current Location';
                                document.getElementById('location_name').value = city;
                            } catch (e) {
                                document.getElementById('location_name').value = 'Current Location';
                            }

                            btn.innerHTML = originalHTML;
                            btn.disabled = false;
                        }, (err) => {
                            alert('Error: ' + err.message);
                            btn.innerHTML = originalHTML;
                            btn.disabled = false;
                        }, { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 });
                    }
                </script>

                <div class="form-group">
                    <label>Photos (Select multiple) *</label>
                    <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/*"
                        style="display: none;" onchange="previewImages()">
                    <div onclick="document.getElementById('images').click()"
                        style="border: 2px dashed var(--border-color); padding: 32px; border-radius: 12px; text-align: center; cursor: pointer; background: #fafafa; transition: all 0.3s;"
                        onmouseover="this.style.borderColor='var(--primary-green)'"
                        onmouseout="this.style.borderColor='var(--border-color)'">
                        <i class="fa fa-camera"
                            style="font-size: 32px; color: var(--primary-green); margin-bottom: 12px;"></i>
                        <p style="color: var(--text-dark); font-weight: 500;">Add Photos</p>
                        <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Click any photo after
                            uploading to set as Cover</p>
                    </div>
                    <div id="image_preview_container"
                        style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 12px; margin-top: 16px;">
                    </div>
                    <input type="hidden" id="cover_index" name="cover_index" value="0">
                </div>

                <script>
                    function previewImages() {
                        const container = document.getElementById('image_preview_container');
                        const files = document.getElementById('images').files;
                        container.innerHTML = '';

                        for (let i = 0; i < files.length; i++) {
                            const reader = new FileReader();
                            reader.onload = function (e) {
                                const div = document.createElement('div');
                                div.className = 'preview-item' + (i === 0 ? ' is-cover' : '');
                                div.id = 'preview-' + i;
                                div.onclick = () => setAsCover(i);
                                div.innerHTML = `
                                    <img src="${e.target.result}">
                                    <div class="cover-badge">Main</div>
                                    <div class="order-num">${i + 1}</div>
                                `;
                                container.appendChild(div);
                            }
                            reader.readAsDataURL(files[i]);
                        }
                        document.getElementById('cover_index').value = 0;
                    }

                    function setAsCover(index) {
                        document.querySelectorAll('.preview-item').forEach(el => el.classList.remove('is-cover'));
                        document.getElementById('preview-' + index).classList.add('is-cover');
                        document.getElementById('cover_index').value = index;
                    }
                </script>

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
            btnSell.classList.add('active');
            btnSell.style.background = '#e8f5e9';
            btnSell.style.borderColor = 'var(--primary-green)';
            btnSell.style.color = 'var(--primary-green)';

            btnBuy.classList.remove('active');
            btnBuy.style.background = '#f8f9fa';
            btnBuy.style.borderColor = '#eee';
            btnBuy.style.color = 'var(--text-muted)';

            priceLabel.innerText = "Price (₹)";
        } else {
            btnBuy.classList.add('active');
            btnBuy.style.background = '#fff8e1';
            btnBuy.style.borderColor = '#ffb300';
            btnBuy.style.color = '#ff8f00';

            btnSell.classList.remove('active');
            btnSell.style.background = '#f8f9fa';
            btnSell.style.borderColor = '#eee';
            btnSell.style.color = 'var(--text-muted)';

            priceLabel.innerText = "Budget (₹)";
        }
    }
</script>

<?php require_once '../includes/footer.php'; ?>