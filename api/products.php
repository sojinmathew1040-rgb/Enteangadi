<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (isset($_GET['action']) && $_GET['action'] === 'view') {
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($product_id) {
        try {
            // Update total views
            $update_views = $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
            $update_views->execute([$product_id]);
            
            // Increment daily analytics view count
            $stmt_an = $pdo->prepare("INSERT INTO analytics_clicks (product_id, click_type, click_date, click_count) 
                                     VALUES (?, 'view', CURRENT_DATE, 1) 
                                     ON DUPLICATE KEY UPDATE click_count = click_count + 1");
            $stmt_an->execute([$product_id]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
    exit;
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$category_filter = $_GET['category_id'] ?? null;
$search_query = $_GET['search'] ?? null;
$user_location = $_SESSION['user_location'] ?? null;

$min_price = $_GET['min_price'] ?? null;
$max_price = $_GET['max_price'] ?? null;
$ad_type = $_GET['ad_type'] ?? null;
$sort_by = $_GET['sort_by'] ?? 'newest';

$where_clause = "WHERE p.status = 'active'";
$params = [];

if ($category_filter) {
    $where_clause .= " AND (p.category_id = ? OR c.parent_id = ?)";
    $params[] = $category_filter;
    $params[] = $category_filter;
}

if ($search_query) {
    $words = array_filter(array_map('trim', explode(' ', $search_query)));
    if (!empty($words)) {
        $word_clauses = [];
        foreach ($words as $w) {
            $word_clauses[] = "(p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ? OR c.name LIKE ? OR parent_c.name LIKE ? OR p.location_name LIKE ?)";
            $wildcard = "%$w%";
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
            $params[] = $wildcard;
        }
        $where_clause .= " AND (" . implode(" AND ", $word_clauses) . ")";
    }
}

if ($min_price !== null && $min_price !== '') {
    $where_clause .= " AND p.price >= ?";
    $params[] = floatval($min_price);
}

if ($max_price !== null && $max_price !== '') {
    $where_clause .= " AND p.price <= ?";
    $params[] = floatval($max_price);
}

if ($ad_type && in_array($ad_type, ['buy', 'sell'])) {
    $where_clause .= " AND p.type = ?";
    $params[] = $ad_type;
}

$spec = $_GET['spec'] ?? null;
if ($spec) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = '%' . $spec . '%';
    $params[] = '%' . $spec . '%';
    $params[] = '%' . $spec . '%';
}

$body_type = $_GET['body_type'] ?? null;
if ($body_type) {
    if ($body_type === 'Luxury') {
        $where_clause .= " AND (p.body_type = 'Luxury' OR p.brand IN ('Mercedes-Benz', 'BMW', 'Audi', 'Land Rover') OR p.title LIKE '%Luxury%' OR p.search_keywords LIKE '%Luxury%')";
    } elseif ($body_type === 'SUV') {
        $where_clause .= " AND (p.body_type IN ('SUV', 'Compact SUV') OR p.title LIKE '%SUV%' OR p.description LIKE '%SUV%' OR p.search_keywords LIKE '%SUV%')";
    } else {
        $where_clause .= " AND (p.body_type = ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
        $params[] = $body_type;
        $params[] = '%' . $body_type . '%';
        $params[] = '%' . $body_type . '%';
        $params[] = '%' . $body_type . '%';
    }
}

$fuel = $_GET['fuel'] ?? null;
if ($fuel) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = '%' . $fuel . '%';
    $params[] = '%' . $fuel . '%';
    $params[] = '%' . $fuel . '%';
}

$transmission = $_GET['transmission'] ?? null;
if ($transmission) {
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = '%' . $transmission . '%';
    $params[] = '%' . $transmission . '%';
    $params[] = '%' . $transmission . '%';
}

$brand = $_GET['brand'] ?? null;
if ($brand) {
    $where_clause .= " AND (p.brand = ? OR p.title LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = $brand;
    $params[] = '%' . $brand . '%';
    $params[] = '%' . $brand . '%';
}

$model = $_GET['model'] ?? null;
if ($model) {
    $where_clause .= " AND (p.model = ? OR p.title LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = $model;
    $params[] = '%' . $model . '%';
    $params[] = '%' . $model . '%';
}

$variant = $_GET['variant'] ?? null;
if ($variant) {
    $where_clause .= " AND (p.variant = ? OR p.title LIKE ? OR p.description LIKE ? OR p.search_keywords LIKE ?)";
    $params[] = $variant;
    $params[] = '%' . $variant . '%';
    $params[] = '%' . $variant . '%';
    $params[] = '%' . $variant . '%';
}

$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : null;
if ($radius && $user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = floatval($user_location['lat']);
    $lng = floatval($user_location['lng']);
    $where_clause .= " AND (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) <= ?";
    $params[] = $radius;
}

$distance_select = "";
$order_by = "p.created_at DESC";

if ($user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = floatval($user_location['lat']);
    $lng = floatval($user_location['lng']);
    $distance_select = ", (6371 * acos(cos(radians($lat)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(p.latitude)))) AS distance";
}

if ($sort_by === 'price_asc') {
    $order_by = "p.price ASC";
} elseif ($sort_by === 'price_desc') {
    $order_by = "p.price DESC";
} elseif ($sort_by === 'distance' && !empty($distance_select)) {
    $order_by = "distance ASC";
} else {
    if (!empty($distance_select)) {
        $order_by = "p.created_at DESC, distance ASC";
    } else {
        $order_by = "p.created_at DESC";
    }
}

try {
    $sql = "SELECT p.*, c.name as category_name, c.parent_id as parent_category_id, parent_c.name as parent_category_name, u.username as seller_username, u.profile_picture as seller_picture, u.is_verified as seller_verified,
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image,
            COALESCE((SELECT ROUND(AVG(rating), 1) FROM user_ratings ur WHERE ur.reviewee_id = p.user_id), 0) as seller_rating,
            (SELECT COUNT(*) FROM user_ratings ur WHERE ur.reviewee_id = p.user_id) as seller_reviews_count
            $distance_select
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN categories parent_c ON c.parent_id = parent_c.id
            JOIN users u ON p.user_id = u.id
            $where_clause 
            ORDER BY $order_by
            LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'products' => $products,
        'has_more' => count($products) === $limit
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>