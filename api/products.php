<?php
require_once '../config.php';

header('Content-Type: application/json');

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
    $where_clause .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
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

$distance_select = "";
$order_by = "p.created_at DESC";

if ($user_location && isset($user_location['lat']) && isset($user_location['lng'])) {
    $lat = $user_location['lat'];
    $lng = $user_location['lng'];
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
    $sql = "SELECT p.*, c.name as category_name, 
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id LIMIT 1) as main_image 
            $distance_select
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id
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