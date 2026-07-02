<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$my_id = $_SESSION['user_id'];

// 1. Fetch user's listings
$stmt = $pdo->prepare("SELECT id, title, price, status, created_at FROM products WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$my_id]);
$listings = $stmt->fetchAll();

$listing_ids = array_column($listings, 'id');

// Metrics accumulation variables
$total_views = 0;
$total_favorites = 0;
$total_chats = 0;
$daily_stats = [];

// Initialize 15-day range map for the chart
for ($i = 14; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_stats[$date] = [
        'view' => 0,
        'favorite' => 0,
        'chat' => 0
    ];
}

$listings_breakdown = [];
foreach ($listings as $l) {
    $listings_breakdown[$l['id']] = [
        'title' => $l['title'],
        'price' => $l['price'],
        'status' => $l['status'],
        'view' => 0,
        'favorite' => 0,
        'chat' => 0
    ];
}

if (!empty($listing_ids)) {
    $placeholders = implode(',', array_fill(0, count($listing_ids), '?'));
    
    // Fetch all daily stats for these products
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
        
        // Accumulate overall totals
        if ($type === 'view') $total_views += $count;
        if ($type === 'favorite') $total_favorites += $count;
        if ($type === 'chat') $total_chats += $count;
        
        // Accumulate listing breakdown
        if (isset($listings_breakdown[$p_id])) {
            $listings_breakdown[$p_id][$type] += $count;
        }
        
        // Accumulate daily chart data
        if (isset($daily_stats[$date])) {
            $daily_stats[$date][$type] += $count;
        }
    }
}

// Find max daily count for chart scaling
$max_daily = 1;
foreach ($daily_stats as $date => $types) {
    $sum = $types['view'] + $types['favorite'] + $types['chat'];
    if ($sum > $max_daily) {
        $max_daily = $sum;
    }
}

require_once '../includes/header.php';
?>

<div class="container" style="padding-top: 24px; padding-bottom: 60px;">
    <!-- Back Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <a href="profile.php" style="text-decoration: none; color: var(--text-dark); font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa fa-arrow-left"></i> Back to Account
        </a>
        <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: var(--primary-green-dark);">Listing Analytics</h2>
    </div>

    <!-- Overall Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 32px;">
        <!-- Card 1 -->
        <div class="glass-card" style="padding: 24px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white); display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm);">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fa fa-eye"></i>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 600; display: block; text-transform: uppercase;">Total Views</span>
                <strong style="font-size: 24px; color: var(--text-dark); font-weight: 800;"><?= number_format($total_views) ?></strong>
            </div>
        </div>
        <!-- Card 2 -->
        <div class="glass-card" style="padding: 24px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white); display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm);">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #fef2f2; color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fa fa-heart"></i>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 600; display: block; text-transform: uppercase;">Wishlist Saves</span>
                <strong style="font-size: 24px; color: var(--text-dark); font-weight: 800;"><?= number_format($total_favorites) ?></strong>
            </div>
        </div>
        <!-- Card 3 -->
        <div class="glass-card" style="padding: 24px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white); display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm);">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #f0fdf4; color: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                <i class="fa fa-comment-dots"></i>
            </div>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 600; display: block; text-transform: uppercase;">Chat Leads</span>
                <strong style="font-size: 24px; color: var(--text-dark); font-weight: 800;"><?= number_format($total_chats) ?></strong>
            </div>
        </div>
    </div>

    <!-- Daily Performance Chart -->
    <div class="glass-card" style="padding: 24px; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white); margin-bottom: 32px; box-shadow: var(--shadow-sm);">
        <h3 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 800; color: var(--text-dark);">Daily Activity (Last 15 Days)</h3>
        
        <!-- Legend -->
        <div style="display: flex; gap: 16px; margin-bottom: 24px; font-size: 12px; font-weight: 600;">
            <div style="display: flex; align-items: center; gap: 6px;">
                <div style="width:12px; height:12px; border-radius:3px; background:#3b82f6;"></div> <span style="color: var(--text-dark);">Views</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <div style="width:12px; height:12px; border-radius:3px; background:#ef4444;"></div> <span style="color: var(--text-dark);">Saves</span>
            </div>
            <div style="display: flex; align-items: center; gap: 6px;">
                <div style="width:12px; height:12px; border-radius:3px; background:#22c55e;"></div> <span style="color: var(--text-dark);">Chats</span>
            </div>
        </div>

        <!-- Chart Grid -->
        <div style="height: 200px; display: flex; align-items: flex-end; gap: 4px; border-bottom: 2px solid var(--border-color); padding-bottom: 8px; overflow-x: auto;">
            <?php foreach ($daily_stats as $date => $types): 
                $v_height = ($types['view'] / $max_daily) * 100;
                $f_height = ($types['favorite'] / $max_daily) * 100;
                $c_height = ($types['chat'] / $max_daily) * 100;
                $total = $types['view'] + $types['favorite'] + $types['chat'];
            ?>
                <div style="flex: 1; min-width: 16px; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; position: relative;" title="<?= date('M d', strtotime($date)) ?>: <?= $types['view'] ?> views, <?= $types['favorite'] ?> saves, <?= $types['chat'] ?> chats">
                    <!-- Bars -->
                    <div style="width: 100%; height: <?= $c_height ?>%; background: #22c55e; border-radius: 2px 2px 0 0;"></div>
                    <div style="width: 100%; height: <?= $f_height ?>%; background: #ef4444; border-radius: 2px 2px 0 0; margin-top:-1px;"></div>
                    <div style="width: 100%; height: <?= $v_height ?>%; background: #3b82f6; border-radius: 2px 2px 0 0; margin-top:-1px;"></div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- X Axis labels -->
        <div style="display: flex; justify-content: space-between; margin-top: 8px; font-size: 10px; color: var(--text-muted); font-weight: 700;">
            <span><?= date('M d', strtotime(array_key_first($daily_stats))) ?></span>
            <span>Midpoint</span>
            <span>Today</span>
        </div>
    </div>

    <!-- Listings Breakdown Grid -->
    <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 800; color: var(--text-dark);">Listing Breakdown</h3>
    
    <?php if (empty($listings_breakdown)): ?>
        <div class="glass-card" style="padding: 40px; text-align: center; border-radius: 20px; border: 1px solid var(--border-color); background: var(--white);">
            <i class="fa fa-chart-line" style="font-size: 36px; color: var(--text-muted); margin-bottom: 12px;"></i>
            <h4 style="margin:0 0 6px 0; color: var(--text-dark);">No Listing History</h4>
            <p style="margin:0; color: var(--text-muted);">Post an ad to start tracking impressions and buyer inquiries.</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($listings_breakdown as $p_id => $info): ?>
                <div class="glass-card" style="padding: 16px; border-radius: 16px; border: 1px solid var(--border-color); background: var(--white); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; box-shadow: var(--shadow-sm);">
                    <div style="flex: 1; min-width: 200px;">
                        <h4 style="margin: 0 0 4px 0; font-size: 15px; font-weight: 700; color: var(--text-dark); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"><?= htmlspecialchars($info['title']) ?></h4>
                        <span style="font-size: 12px; font-weight: 700; color: var(--primary-green-dark);">₹ <?= number_format($info['price'], 0) ?></span>
                        <span style="display:inline-block; margin-left: 10px; font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase; background: <?= $info['status'] === 'active' ? '#f0fdf4; color:#15803d;' : '#f1f5f9; color:#475569;' ?>"><?= $info['status'] ?></span>
                    </div>
                    <div style="display: flex; gap: 20px; font-size: 13px; font-weight: 700; color: var(--text-dark);">
                        <div style="display: flex; flex-direction: column; align-items: center; min-width: 48px;">
                            <span style="color: #3b82f6;"><i class="fa fa-eye"></i></span>
                            <span style="margin-top: 4px;"><?= $info['view'] ?></span>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: center; min-width: 48px;">
                            <span style="color: #ef4444;"><i class="fa fa-heart"></i></span>
                            <span style="margin-top: 4px;"><?= $info['favorite'] ?></span>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: center; min-width: 48px;">
                            <span style="color: #22c55e;"><i class="fa fa-comment-dots"></i></span>
                            <span style="margin-top: 4px;"><?= $info['chat'] ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
