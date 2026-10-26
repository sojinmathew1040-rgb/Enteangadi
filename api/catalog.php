<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'brands') {
        $cat_id = intval($_GET['category_id'] ?? 0);
        $body_type = trim($_GET['body_type'] ?? '');
        if ($body_type) {
            if ($body_type === 'Luxury') {
                $stmt = $pdo->prepare("SELECT DISTINCT b.id, b.name, b.slug FROM product_brands b JOIN product_models m ON m.brand_id = b.id WHERE b.category_id = ? AND b.name IN ('Mercedes-Benz', 'BMW', 'Audi', 'Land Rover') ORDER BY b.sort_order, b.name");
                $stmt->execute([$cat_id]);
            } else {
                $stmt = $pdo->prepare("SELECT DISTINCT b.id, b.name, b.slug FROM product_brands b JOIN product_models m ON m.brand_id = b.id WHERE b.category_id = ? AND m.body_type = ? ORDER BY b.sort_order, b.name");
                $stmt->execute([$cat_id, $body_type]);
            }
        } else {
            $stmt = $pdo->prepare("SELECT id, name, slug FROM product_brands WHERE category_id = ? ORDER BY sort_order, name");
            $stmt->execute([$cat_id]);
        }
        $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'brands' => $brands]);
        exit;
    }

    if ($action === 'models') {
        $brand_id = intval($_GET['brand_id'] ?? 0);
        $brand_name = trim($_GET['brand_name'] ?? '');
        $cat_id = intval($_GET['category_id'] ?? 0);
        $body_type = trim($_GET['body_type'] ?? '');
        if (!$brand_id && $brand_name && $cat_id) {
            $b_stmt = $pdo->prepare("SELECT id FROM product_brands WHERE category_id = ? AND name = ?");
            $b_stmt->execute([$cat_id, $brand_name]);
            $brand_id = $b_stmt->fetchColumn();
        }
        if ($body_type && $body_type !== 'Luxury') {
            $stmt = $pdo->prepare("SELECT id, name, slug, body_type FROM product_models WHERE brand_id = ? AND body_type = ? ORDER BY sort_order, name");
            $stmt->execute([$brand_id, $body_type]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name, slug, body_type FROM product_models WHERE brand_id = ? ORDER BY sort_order, name");
            $stmt->execute([$brand_id]);
        }
        $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'models' => $models]);
        exit;
    }

    if ($action === 'variants') {
        $model_id = intval($_GET['model_id'] ?? 0);
        $model_name = trim($_GET['model_name'] ?? '');
        $brand_id = intval($_GET['brand_id'] ?? 0);
        if (!$model_id && $model_name && $brand_id) {
            $m_stmt = $pdo->prepare("SELECT id FROM product_models WHERE brand_id = ? AND name = ?");
            $m_stmt->execute([$brand_id, $model_name]);
            $model_id = $m_stmt->fetchColumn();
        }
        $stmt = $pdo->prepare("SELECT id, name FROM product_variants WHERE model_id = ? ORDER BY sort_order, name");
        $stmt->execute([$model_id]);
        $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'variants' => $variants]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
