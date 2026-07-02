<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to view analytics.']);
    exit;
}

$my_id = $_SESSION['user_id'];

try {
    // 1. Fetch user's listings
    $stmt = $pdo->prepare("SELECT id, title, price, status FROM products WHERE user_id = ?");
    $stmt->execute([$my_id]);
    $listings = $stmt->fetchAll();
    
    $listing_ids = array_column($listings, 'id');
    
    $total_views = 0;
    $total_favorites = 0;
    $total_chats = 0;
    $daily_stats = [];
    
    // Initialize 15-day range map
    for ($i = 14; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily_stats[$date] = [
            'date' => date('M d', strtotime($date)),
            'view' => 0,
            'favorite' => 0,
            'chat' => 0
        ];
    }
    
    $listings_breakdown = [];
    foreach ($listings as $l) {
        $listings_breakdown[$l['id']] = [
            'id' => $l['id'],
            'title' => $l['title'],
            'price' => floatval($l['price']),
            'status' => $l['status'],
            'view' => 0,
            'favorite' => 0,
            'chat' => 0
        ];
    }
    
    if (!empty($listing_ids)) {
        $placeholders = implode(',', array_fill(0, count($listing_ids), '?'));
        
        $analytics_stmt = $pdo->prepare("
            SELECT product_id, click_type, click_date, SUM(click_count) as total_count
            FROM analytics_clicks
            WHERE product_id IN ($placeholders)
            GROUP BY product_id, click_type, click_date
        ");
        $analytics_stmt->execute($listing_ids);
        $rows = $analytics_stmt->fetchAll();
        
        foreach ($rows as $row) {
            $p_id = $row['product_id'];
            $type = $row['click_type'];
            $date = $row['click_date'];
            $count = (int)$row['total_count'];
            
            if ($type === 'view') $total_views += $count;
            if ($type === 'favorite') $total_favorites += $count;
            if ($type === 'chat') $total_chats += $count;
            
            if (isset($listings_breakdown[$p_id])) {
                $listings_breakdown[$p_id][$type] += $count;
            }
            
            if (isset($daily_stats[$date])) {
                $daily_stats[$date][$type] += $count;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'summary' => [
            'total_views' => $total_views,
            'total_favorites' => $total_favorites,
            'total_chats' => $total_chats
        ],
        'daily_chart' => array_values($daily_stats),
        'listings' => array_values($listings_breakdown)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
