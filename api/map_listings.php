<?php
require_once dirname(__DIR__) . '/config.php';

header('Content-Type: application/json');

$min_lat = isset($_GET['min_lat']) ? floatval($_GET['min_lat']) : null;
$max_lat = isset($_GET['max_lat']) ? floatval($_GET['max_lat']) : null;
$min_lng = isset($_GET['min_lng']) ? floatval($_GET['min_lng']) : null;
$max_lng = isset($_GET['max_lng']) ? floatval($_GET['max_lng']) : null;
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;

if ($min_lat === null || $max_lat === null || $min_lng === null || $max_lng === null) {
    echo json_encode(['success' => false, 'message' => 'Missing bounds coordinates']);
    exit;
}

try {
    $query = "SELECT p.*, c.name as category_name, pi.image_path as main_image 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id
              LEFT JOIN (
                  SELECT product_id, MIN(id) as min_img_id 
                  FROM product_images 
                  GROUP BY product_id
              ) pim ON p.id = pim.product_id
              LEFT JOIN product_images pi ON pim.min_img_id = pi.id
              WHERE p.status = 'active' 
                AND p.latitude BETWEEN ? AND ? 
                AND p.longitude BETWEEN ? AND ?";
    
    $params = [$min_lat, $max_lat, $min_lng, $max_lng];

    if ($category_id) {
        $query .= " AND (p.category_id = ? OR c.parent_id = ?)";
        $params[] = $category_id;
        $params[] = $category_id;
    }

    if ($search) {
        $query .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
