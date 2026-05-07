<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header("Location: index.php");
    exit;
}

// Fetch product data
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
$stmt->execute([$product_id, $_SESSION['user_id']]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: index.php");
    exit;
}

// Fetch existing images
$img_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY id ASC");
$img_stmt->execute([$product_id]);
$existing_images = $img_stmt->fetchAll();

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

// Find parent category for pre-filling
$current_l2 = $product['category_id'];
$current_l1 = null;
foreach ($all_cats as $cat) {
    if ($cat['id'] == $current_l2) {
        $current_l1 = $cat['parent_id'] ?? $cat['id'];
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

    $location_name = $_POST['location_name'] ?? '';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    if (!empty($title) && !empty($category_id) && !empty($price)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, type = ?, title = ?, description = ?, price = ?, whatsapp_number = ?, phone_number = ?, location_name = ?, latitude = ?, longitude = ? WHERE id = ?");
            $stmt->execute([$category_id, $type, $title, $description, $price, $whatsapp_number, $phone_number, $location_name, $latitude, $longitude, $product_id]);

            // Handle image deletions
            if (isset($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $del_img_id) {
                    $sel_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND product_id = ?");
                    $sel_stmt->execute([$del_img_id, $product_id]);
                    $path = $sel_stmt->fetchColumn();
                    if ($path && file_exists('../' . $path))
                        unlink('../' . $path);

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
            header("Location: edit_ad.php?id=$product_id&success=1");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error updating ad. Please try again.";
        }
    } else {
        $error = "Please fill out all required fields.";
    }
}

if (isset($_GET['success'])) {
    $success = "Your ad has been updated successfully!";
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
    }

    .image-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 12px;
        margin-top: 16px;
    }

    .image-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid var(--border-color);
    }

    .image-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .delete-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 0, 0, 0.4);
        display: none;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
    }

    .image-item.to-delete .delete-overlay {
        display: flex;
    }
</style>

<div class="container">
    <div
        style="max-width: 600px; margin: 0 auto; background: var(--white); padding: 40px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
        <h2 style="margin-bottom: 24px; color: var(--primary-green-dark);">Edit Ad</h2>

        <?php if ($error): ?>
            <div style="background: #ffebee; color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div
                style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
                <?= $success ?> <a href="profile.php" style="font-weight: bold;">Back to Profile</a>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Ad Type Selection -->
            <div class="form-group" style="margin-bottom: 24px;">
                <div style="display: flex; gap: 12px;">
                    <label style="flex: 1; cursor: pointer;">
                        <input type="radio" name="type" value="sell" <?= $product['type'] == 'sell' ? 'checked' : '' ?>
                            style="display: none;" onchange="updateTypeUI(this.value)">
                        <div class="type-btn <?= $product['type'] == 'sell' ? 'active' : '' ?>" id="btn-sell"
                            style="padding: 14px; border-radius: 12px; border: 2px solid <?= $product['type'] == 'sell' ? 'var(--primary-green)' : '#eee' ?>; text-align: center; background: <?= $product['type'] == 'sell' ? '#e8f5e9' : '#f8f9fa' ?>; color: <?= $product['type'] == 'sell' ? 'var(--primary-green)' : 'var(--text-muted)' ?>; font-weight: 700;">
                            <i class="fa fa-tag" style="margin-right: 8px;"></i> Sell
                        </div>
                    </label>
                    <label style="flex: 1; cursor: pointer;">
                        <input type="radio" name="type" value="buy" <?= $product['type'] == 'buy' ? 'checked' : '' ?>
                            style="display: none;" onchange="updateTypeUI(this.value)">
                        <div class="type-btn <?= $product['type'] == 'buy' ? 'active' : '' ?>" id="btn-buy"
                            style="padding: 14px; border-radius: 12px; border: 2px solid <?= $product['type'] == 'buy' ? '#FFB300' : '#eee' ?>; text-align: center; background: <?= $product['type'] == 'buy' ? '#fff8e1' : '#f8f9fa' ?>; color: <?= $product['type'] == 'buy' ? '#FFB300' : 'var(--text-muted)' ?>; font-weight: 700;">
                            <i class="fa fa-shopping-basket" style="margin-right: 8px;"></i> Wanted
                        </div>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label for="title">Ad Title *</label>
                <input type="text" id="title" name="title" class="form-control" required
                    value="<?= htmlspecialchars($product['title']) ?>">
            </div>

            <div class="form-group">
                <label for="l1_category">Main Category *</label>
                <select id="l1_category" class="form-control" required onchange="updateL2Categories()">
                    <?php foreach ($l1_categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $current_l1 == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="l2_category_group">
                <label for="category_id">Sub-Category *</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <!-- Populated by JS -->
                </select>
            </div>

            <div class="form-group">
                <label for="price" id="price_label"><?= $product['type'] == 'buy' ? 'Budget (₹)' : 'Price (₹)' ?>
                    *</label>
                <input type="number" id="price" name="price" step="0.01" class="form-control" required
                    value="<?= (float) $product['price'] ?>">
            </div>

            <div class="form-group">
                <label>Contact Options *</label>
                <div style="display: flex; gap: 12px; margin-bottom: 15px;">
                    <input type="checkbox" id="contact_whatsapp_chk" name="contact_whatsapp" value="1"
                        style="display: none;" onchange="toggleContact('whatsapp')"
                        <?= !empty($product['whatsapp_number']) ? 'checked' : '' ?>>
                    <label for="contact_whatsapp_chk" class="toggle-btn">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </label>

                    <input type="checkbox" id="contact_phone_chk" name="contact_phone" value="1" style="display: none;"
                        onchange="toggleContact('phone')" <?= !empty($product['phone_number']) ? 'checked' : '' ?>>
                    <label for="contact_phone_chk" class="toggle-btn">
                        <i class="fa fa-phone"></i> Call
                    </label>
                </div>

                <div id="whatsapp_group"
                    style="display: <?= !empty($product['whatsapp_number']) ? 'block' : 'none' ?>; margin-bottom: 15px;">
                    <input type="tel" id="whatsapp_number" name="whatsapp_number" class="form-control"
                        value="<?= htmlspecialchars($product['whatsapp_number']) ?>" placeholder="WhatsApp Number">
                </div>
                <div id="phone_group"
                    style="display: <?= !empty($product['phone_number']) ? 'block' : 'none' ?>; margin-bottom: 15px;">
                    <input type="tel" id="phone_number" name="phone_number" class="form-control"
                        value="<?= htmlspecialchars($product['phone_number']) ?>" placeholder="Phone Number">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control"
                    rows="5"><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label for="location_name">Location *</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="location_name" name="location_name" class="form-control" required
                        value="<?= htmlspecialchars($product['location_name']) ?>">
                    <button type="button" onclick="detectPostLocation()" class="btn-secondary">Detect</button>
                </div>
                <input type="hidden" id="latitude" name="latitude" value="<?= $product['latitude'] ?>">
                <input type="hidden" id="longitude" name="longitude" value="<?= $product['longitude'] ?>">
            </div>

            <div class="form-group">
                <label>Existing Photos (Click to mark for deletion)</label>
                <div class="image-grid">
                    <?php foreach ($existing_images as $img): ?>
                        <div class="image-item" onclick="toggleDelete(<?= $img['id'] ?>, this)">
                            <img src="../<?= htmlspecialchars($img['image_path']) ?>">
                            <div class="delete-overlay"><i class="fa fa-trash"></i></div>
                            <input type="checkbox" name="delete_images[]" value="<?= $img['id'] ?>"
                                id="del-<?= $img['id'] ?>" style="display: none;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Add More Photos</label>
                <input type="file" name="images[]" class="form-control" multiple accept="image/*">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; margin-top: 16px;">Update Ad</button>
        </form>
    </div>
</div>

<script>
    const l2Categories = <?= $l2_json ?>;
    const currentL2 = <?= $product['category_id'] ?>;

    function updateTypeUI(type) {
        const btnSell = document.getElementById('btn-sell');
        const btnBuy = document.getElementById('btn-buy');
        const priceLabel = document.getElementById('price_label');

        if (type === 'sell') {
            btnSell.style.borderColor = 'var(--primary-green)';
            btnSell.style.background = '#e8f5e9';
            btnSell.style.color = 'var(--primary-green)';
            btnSell.classList.add('active');

            btnBuy.style.borderColor = '#eee';
            btnBuy.style.background = '#f8f9fa';
            btnBuy.style.color = 'var(--text-muted)';
            btnBuy.classList.remove('active');

            priceLabel.innerText = 'Price (₹) *';
        } else {
            btnBuy.style.borderColor = '#FFB300';
            btnBuy.style.background = '#fff8e1';
            btnBuy.style.color = '#FFB300';
            btnBuy.classList.add('active');

            btnSell.style.borderColor = '#eee';
            btnSell.style.background = '#f8f9fa';
            btnSell.style.color = 'var(--text-muted)';
            btnSell.classList.remove('active');

            priceLabel.innerText = 'Budget (₹) *';
        }
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
        } else {
            l2Group.style.display = 'none';
        }
    }

    function toggleContact(type) {
        const chk = document.getElementById('contact_' + type + '_chk');
        const group = document.getElementById(type + '_group');
        const input = document.getElementById(type + '_number');
        group.style.display = chk.checked ? 'block' : 'none';
        input.required = chk.checked;
    }

    function toggleDelete(id, el) {
        const chk = document.getElementById('del-' + id);
        chk.checked = !chk.checked;
        el.classList.toggle('to-delete', chk.checked);
    }

    async function detectPostLocation() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition(async (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            try {
                const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`);
                const data = await resp.json();
                document.getElementById('location_name').value = data.address.city || data.address.town || 'Current Location';
            } catch (e) { }
        });
    }

    window.onload = updateL2Categories;
</script>

<?php require_once '../includes/footer.php'; ?>