<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = $_POST['name'] ?? '';
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
        $is_perishable = isset($_POST['is_perishable']) ? 1 : 0;

        if (!empty($name)) {
            $photo_path = null;
            if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
                $upload_dir = '../uploads/categories/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                $file_name = time() . '_' . basename($_FILES['photo']['name']);
                $target_file = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    $photo_path = 'uploads/categories/' . $file_name;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id, photo_path, is_perishable) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $parent_id, $photo_path, $is_perishable]);
            $success = "Category added successfully.";
        }
    } elseif (isset($_POST['edit_category'])) {
        $category_id = $_POST['category_id'];
        $name = $_POST['name'] ?? '';
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
        $is_perishable = isset($_POST['is_perishable']) ? 1 : 0;

        if (!empty($name)) {
            $update_sql = "UPDATE categories SET name = ?, parent_id = ?, is_perishable = ? WHERE id = ?";
            $params = [$name, $parent_id, $is_perishable, $category_id];

            if (isset($_FILES['photo']) && !empty($_FILES['photo']['name'])) {
                $upload_dir = '../uploads/categories/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                $file_name = time() . '_' . basename($_FILES['photo']['name']);
                $target_file = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                    // Delete old photo
                    $old_stmt = $pdo->prepare("SELECT photo_path FROM categories WHERE id = ?");
                    $old_stmt->execute([$category_id]);
                    $old_photo = $old_stmt->fetchColumn();
                    if ($old_photo && file_exists('../' . $old_photo)) {
                        unlink('../' . $old_photo);
                    }

                    $update_sql = "UPDATE categories SET name = ?, parent_id = ?, is_perishable = ?, photo_path = ? WHERE id = ?";
                    $params = [$name, $parent_id, $is_perishable, 'uploads/categories/' . $file_name, $category_id];
                }
            }

            $stmt = $pdo->prepare($update_sql);
            $stmt->execute($params);
            $success = "Category updated successfully.";
        }
    } elseif (isset($_POST['delete_category'])) {
        $category_id = $_POST['delete_category_id'] ?? null;
        if ($category_id) {
            // Function to recursively get all subcategory IDs
            if (!function_exists('getAllCategoryIds')) {
                function getAllCategoryIds($pdo, $parentId, &$ids)
                {
                    $ids[] = $parentId;
                    $stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ?");
                    $stmt->execute([$parentId]);
                    while ($row = $stmt->fetch()) {
                        getAllCategoryIds($pdo, $row['id'], $ids);
                    }
                }
            }

            $all_category_ids = [];
            getAllCategoryIds($pdo, $category_id, $all_category_ids);

            // Fetch all category photos
            $placeholders = implode(',', array_fill(0, count($all_category_ids), '?'));
            $stmt = $pdo->prepare("SELECT photo_path FROM categories WHERE id IN ($placeholders) AND photo_path IS NOT NULL");
            $stmt->execute($all_category_ids);
            $category_photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Fetch all product images in these categories
            $stmt = $pdo->prepare("SELECT pi.image_path FROM product_images pi 
                                  JOIN products p ON pi.product_id = p.id 
                                  WHERE p.category_id IN ($placeholders)");
            $stmt->execute($all_category_ids);
            $product_images = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Delete from DB (cascade handles related records)
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt->execute([$category_id])) {
                // Delete physical files
                foreach ($category_photos as $photo) {
                    if (!empty($photo) && file_exists('../' . $photo)) {
                        unlink('../' . $photo);
                    }
                }
                foreach ($product_images as $img) {
                    if (!empty($img) && file_exists('../' . $img)) {
                        unlink('../' . $img);
                    }
                }
                $success = "Category and associated media deleted successfully.";
            }
        }
    }
}

// Fetch all categories for the dropdown and display
$stmt = $pdo->query("SELECT c.*, p.name as parent_name FROM categories c LEFT JOIN categories p ON c.parent_id = p.id ORDER BY c.parent_id, c.name");
$categories = $stmt->fetchAll();

$root_categories = [];
$sub_categories = [];
foreach ($categories as $cat) {
    if (empty($cat['parent_id'])) {
        $root_categories[] = $cat;
    } else {
        $sub_categories[$cat['parent_id']][] = $cat;
    }
}

// Handle Edit Mode
$edit_cat = null;
if (isset($_GET['edit'])) {
    foreach ($categories as $cat) {
        if ($cat['id'] == $_GET['edit']) {
            $edit_cat = $cat;
            break;
        }
    }
}

require_once 'includes/header.php';
?>

<h2 style="margin-bottom: 24px; color: var(--primary-green-dark);">Manage Categories</h2>

<?php if (isset($success)): ?>
    <div style="background: #e8f5e9; color: var(--primary-green-dark); padding: 12px; border-radius: 8px; margin-bottom: 20px;">
        <?= $success ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 32px;">
    <!-- Add/Edit Category Form -->
    <div style="background: var(--white); padding: 24px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">
        <h3 style="margin-bottom: 16px;"><?= $edit_cat ? 'Edit' : 'Add New' ?> Category</h3>
        <form method="POST" enctype="multipart/form-data">
            <?php if ($edit_cat): ?>
                <input type="hidden" name="category_id" value="<?= $edit_cat['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Category Name *</label>
                <input type="text" name="name" class="form-control" required value="<?= $edit_cat ? htmlspecialchars($edit_cat['name']) : '' ?>">
            </div>

            <div class="form-group">
                <label>Parent Category (Leave blank for Root)</label>
                <select name="parent_id" class="form-control">
                    <option value="">-- Root Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <?php if ($edit_cat && $cat['id'] == $edit_cat['id']) continue; ?>
                        <option value="<?= $cat['id'] ?>" <?= ($edit_cat && $edit_cat['parent_id'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                            <?= $cat['parent_name'] ? "({$cat['parent_name']})" : "" ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_perishable" value="1" <?= ($edit_cat && $edit_cat['is_perishable']) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span style="font-weight: 600; color: #d32f2f;">Mark as Perishable / Edible</span>
                </label>
                <p style="font-size: 12px; color: #666; margin-top: 4px; margin-left: 28px;">Selecting this will force users to provide safety info and expiry dates when posting ads.</p>
            </div>

            <div class="form-group">
                <label>Category Photo (Optional)</label>
                <?php if ($edit_cat && $edit_cat['photo_path']): ?>
                    <div style="margin-bottom: 10px;">
                        <img src="../<?= htmlspecialchars($edit_cat['photo_path']) ?>" style="width: 50px; height: 50px; border-radius: 4px; object-fit: cover;">
                    </div>
                <?php endif; ?>
                <input type="file" name="photo" class="form-control" accept="image/*" style="padding: 9px;">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" name="<?= $edit_cat ? 'edit_category' : 'add_category' ?>" class="btn-primary" style="flex: 1;"><?= $edit_cat ? 'Update' : 'Add' ?> Category</button>
                <?php if ($edit_cat): ?>
                    <a href="categories.php" class="btn-secondary" style="flex: 1; text-align: center; text-decoration: none; padding: 12px 24px; border-radius: 8px;">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- List Categories -->
    <div style="background: var(--white); border-radius: var(--border-radius); box-shadow: var(--shadow-sm); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa; border-bottom: 1px solid var(--border-color); text-align: left;">
                    <th style="padding: 16px; width: 60px;">Photo</th>
                    <th style="padding: 16px;">Name</th>
                    <th style="padding: 16px;">Type</th>
                    <th style="padding: 16px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($root_categories as $cat): ?>
                    <tr style="border-bottom: 1px solid var(--border-color); background: #fcfcfc;">
                        <td style="padding: 16px;">
                            <?php if ($cat['photo_path']): ?>
                                <img src="../<?= htmlspecialchars($cat['photo_path']) ?>" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; background: #eee; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa fa-folder"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; font-weight: bold; font-size: 16px;"><?= htmlspecialchars($cat['name']) ?></td>
                        <td style="padding: 16px;">
                            <?php if ($cat['is_perishable']): ?>
                                <span style="background: #ffebee; color: #c62828; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Perishable</span>
                            <?php else: ?>
                                <span style="color: #999; font-size: 11px;">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; text-align: right; display: flex; gap: 4px; justify-content: flex-end;">
                            <a href="categories.php?edit=<?= $cat['id'] ?>" class="btn-primary" style="padding: 4px 8px; font-size: 14px; background: transparent; color: var(--primary-green) !important; border: 1px solid var(--primary-green);"><i class="fa fa-edit"></i></a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                <input type="hidden" name="delete_category_id" value="<?= $cat['id'] ?>">
                                <button type="submit" name="delete_category" class="btn-danger" style="background: transparent; color: var(--danger) !important; border: 1px solid var(--danger); padding: 4px 8px;"><i class="fa fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php if (isset($sub_categories[$cat['id']])): ?>
                        <?php foreach ($sub_categories[$cat['id']] as $sub): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 16px; padding-left: 32px;">
                                    <?php if ($sub['photo_path']): ?>
                                        <img src="../<?= htmlspecialchars($sub['photo_path']) ?>" style="width: 32px; height: 32px; border-radius: 8px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 32px; height: 32px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa fa-file-alt" style="font-size: 12px; color: #999;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; font-weight: 500;">
                                    <i class="fa fa-level-up-alt fa-rotate-90" style="margin-right: 8px; color: var(--text-muted);"></i>
                                    <?= htmlspecialchars($sub['name']) ?>
                                </td>
                                <td style="padding: 16px;">
                                    <?php if ($sub['is_perishable']): ?>
                                        <span style="background: #ffebee; color: #c62828; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Perishable</span>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 11px;">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; text-align: right; display: flex; gap: 4px; justify-content: flex-end;">
                                    <a href="categories.php?edit=<?= $sub['id'] ?>" class="btn-primary" style="padding: 4px 8px; font-size: 14px; background: transparent; color: var(--primary-green) !important; border: 1px solid var(--primary-green);"><i class="fa fa-edit"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="delete_category_id" value="<?= $sub['id'] ?>">
                                        <button type="submit" name="delete_category" class="btn-danger" style="background: transparent; color: var(--danger) !important; border: 1px solid var(--danger); padding: 4px 8px;"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>