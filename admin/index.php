<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// 1. Visitor Stats (Today & Yesterday for comparison)
$stmt_today = $pdo->prepare("
    SELECT 
        COUNT(*) as total_visits, 
        COUNT(DISTINCT ip_address) as unique_visitors,
        SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as user_hits,
        SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest_hits
    FROM site_visits 
    WHERE visit_date = ?
");
$stmt_today->execute([$today]);
$today_stats = $stmt_today->fetch(PDO::FETCH_ASSOC) ?: [];

$today_total_visits = (int)($today_stats['total_visits'] ?? 0);
$today_unique_visitors = (int)($today_stats['unique_visitors'] ?? 0);
$user_hits_today = (int)($today_stats['user_hits'] ?? 0);
$guest_hits_today = (int)($today_stats['guest_hits'] ?? 0);

$stmt_yest = $pdo->prepare("
    SELECT 
        COUNT(*) as total_visits, 
        COUNT(DISTINCT ip_address) as unique_visitors 
    FROM site_visits 
    WHERE visit_date = ?
");
$stmt_yest->execute([$yesterday]);
$yest_stats = $stmt_yest->fetch(PDO::FETCH_ASSOC) ?: [];
$yest_total_visits = (int)($yest_stats['total_visits'] ?? 0);
$yest_unique_visitors = (int)($yest_stats['unique_visitors'] ?? 0);

// All-time visitors
$all_time_visits = (int)$pdo->query("SELECT COUNT(*) FROM site_visits")->fetchColumn();
$all_time_unique = (int)$pdo->query("SELECT COUNT(DISTINCT ip_address) FROM site_visits")->fetchColumn();

// 2. Announcement Poster Stats
$current_poster = trim($app_settings['announcement_poster'] ?? '');
$has_poster = !empty($current_poster);

// Total views for this specific active poster (or all-time if multiple)
$stmt_p_views = $pdo->prepare("SELECT COUNT(*) FROM announcement_views WHERE poster_image = ?");
$stmt_p_views->execute([$current_poster]);
$current_poster_views = $has_poster ? (int)$stmt_p_views->fetchColumn() : 0;

$stmt_p_today = $pdo->prepare("SELECT COUNT(*) FROM announcement_views WHERE poster_image = ? AND view_date = ?");
$stmt_p_today->execute([$current_poster, $today]);
$current_poster_today_views = $has_poster ? (int)$stmt_p_today->fetchColumn() : 0;

$stmt_p_yest = $pdo->prepare("SELECT COUNT(*) FROM announcement_views WHERE poster_image = ? AND view_date = ?");
$stmt_p_yest->execute([$current_poster, $yesterday]);
$current_poster_yest_views = $has_poster ? (int)$stmt_p_yest->fetchColumn() : 0;

// Total all-time announcement views ever recorded
$all_time_poster_views = (int)$pdo->query("SELECT COUNT(*) FROM announcement_views")->fetchColumn();
$today_all_poster_views = (int)$pdo->query("SELECT COUNT(*) FROM announcement_views WHERE view_date = '$today'")->fetchColumn();

// If current poster views is 0 but there are total views recorded, show the lifetime total as reference
$display_poster_views = $current_poster_views > 0 ? $current_poster_views : $all_time_poster_views;
$display_poster_today = $current_poster_today_views > 0 ? $current_poster_today_views : $today_all_poster_views;

// 3. User Activity & Engagement
$users_online_15m = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL 15 MINUTE")->fetchColumn();
$active_users_today_visits = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM site_visits WHERE visit_date = '$today' AND user_id IS NOT NULL")->fetchColumn();
$active_users_today_last_act = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(last_activity) = '$today'")->fetchColumn();
$active_users_today = max($active_users_today_visits, $active_users_today_last_act);

$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$today_registered_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$today'")->fetchColumn();

// 4. Products & Moderation
$total_active_ads = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$today_ads_count = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE DATE(created_at) = '$today' AND status != 'deleted'")->fetchColumn();
$pending_ads_count = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 'pending'")->fetchColumn();
$pending_reports = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();

// 5. 14-Day Traffic Trend
$history_days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $history_days[$d] = [
        'date' => $d,
        'label' => date('d M', strtotime($d)),
        'total_visits' => 0,
        'unique_visitors' => 0
    ];
}
$stmt_history = $pdo->prepare("
    SELECT visit_date, COUNT(*) as total_v, COUNT(DISTINCT ip_address) as unique_v
    FROM site_visits 
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY visit_date
");
$stmt_history->execute();
foreach ($stmt_history->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($history_days[$row['visit_date']])) {
        $history_days[$row['visit_date']]['total_visits'] = (int)$row['total_v'];
        $history_days[$row['visit_date']]['unique_visitors'] = (int)$row['unique_v'];
    }
}

// 6. Today's Hourly Activity Breakdown (0 to 23 hrs)
$hourly_labels = [];
$hourly_values = array_fill(0, 24, 0);
for ($h = 0; $h < 24; $h++) {
    $hourly_labels[] = sprintf('%02d:00', $h);
}
$stmt_hour = $pdo->prepare("
    SELECT HOUR(created_at) as hr, COUNT(*) as cnt
    FROM site_visits 
    WHERE visit_date = ?
    GROUP BY HOUR(created_at)
");
$stmt_hour->execute([$today]);
$peak_hour_cnt = 0;
$peak_hour_label = 'None yet';
foreach ($stmt_hour->fetchAll(PDO::FETCH_ASSOC) as $hrow) {
    $hr_idx = (int)$hrow['hr'];
    $cnt = (int)$hrow['cnt'];
    $hourly_values[$hr_idx] = $cnt;
    if ($cnt > $peak_hour_cnt) {
        $peak_hour_cnt = $cnt;
        $peak_hour_label = sprintf('%02d:00 - %02d:00', $hr_idx, ($hr_idx + 1) % 24);
    }
}

// 7. Top Visited Pages Today
$stmt_top_pages = $pdo->prepare("
    SELECT page_url, COUNT(*) as cnt 
    FROM site_visits 
    WHERE visit_date = ?
    GROUP BY page_url 
    ORDER BY cnt DESC 
    LIMIT 5
");
$stmt_top_pages->execute([$today]);
$top_pages = $stmt_top_pages->fetchAll(PDO::FETCH_ASSOC);

// 8. Recent Real-Time Visits (Live Feed)
$stmt_recent = $pdo->query("
    SELECT sv.page_url, sv.ip_address, sv.created_at, sv.user_id, u.username
    FROM site_visits sv
    LEFT JOIN users u ON sv.user_id = u.id
    ORDER BY sv.id DESC
    LIMIT 6
");
$recent_visits = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';
?>

<style>
    .dash-container {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    
    /* Header Banner */
    .dash-header-banner {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 16px;
        padding: 24px 28px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .dash-header-left h2 {
        margin: 0;
        font-size: 22px;
        font-weight: 800;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .dash-header-left p {
        margin: 6px 0 0 0;
        color: #94a3b8;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pulse-dot {
        width: 8px;
        height: 8px;
        background-color: #10b981;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulse 1.8s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .dash-header-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .btn-dash-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        padding: 9px 16px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.15);
        transition: all 0.2s ease;
    }
    .btn-dash-action:hover {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        transform: translateY(-1px);
    }
    .btn-dash-primary {
        background: var(--primary-green, #10b981);
        border-color: var(--primary-green, #10b981);
    }
    .btn-dash-primary:hover {
        background: var(--primary-green-dark, #059669);
        border-color: var(--primary-green-dark, #059669);
    }

    /* KPI Grid */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }
    .kpi-card {
        background: #fff;
        border-radius: 16px;
        padding: 22px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.08);
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }
    .kpi-card.emerald::before { background: #10b981; }
    .kpi-card.blue::before { background: #0284c7; }
    .kpi-card.amber::before { background: #f59e0b; }
    .kpi-card.purple::before { background: #8b5cf6; }
    .kpi-card.teal::before { background: #14b8a6; }
    .kpi-card.rose::before { background: #f43f5e; }

    .kpi-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    .kpi-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .kpi-card.emerald .kpi-icon { background: #ecfdf5; color: #10b981; }
    .kpi-card.blue .kpi-icon { background: #f0f9ff; color: #0284c7; }
    .kpi-card.amber .kpi-icon { background: #fffbeb; color: #d97706; }
    .kpi-card.purple .kpi-icon { background: #f5f3ff; color: #7c3aed; }
    .kpi-card.teal .kpi-icon { background: #f0fdfa; color: #0d9488; }
    .kpi-card.rose .kpi-icon { background: #fff1f2; color: #e11d48; }

    .kpi-pill {
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .kpi-pill.pill-green { background: #dcfce7; color: #15803d; }
    .kpi-pill.pill-blue { background: #e0f2fe; color: #0369a1; }
    .kpi-pill.pill-amber { background: #fef3c7; color: #b45309; }
    .kpi-pill.pill-red { background: #fee2e2; color: #b91c1c; }
    .kpi-pill.pill-neutral { background: #f1f5f9; color: #475569; }

    .kpi-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .kpi-value {
        font-size: 32px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        letter-spacing: -0.5px;
    }
    .kpi-footer {
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #f1f5f9;
        font-size: 12px;
        color: #64748b;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Announcement Spotlight Card */
    .announcement-spotlight {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        display: grid;
        grid-template-columns: 140px 1fr auto;
        gap: 24px;
        align-items: center;
    }
    @media (max-width: 850px) {
        .announcement-spotlight {
            grid-template-columns: 1fr;
            text-align: center;
        }
        .announcement-spotlight .poster-thumb-wrap {
            margin: 0 auto;
        }
        .announcement-spotlight .poster-actions {
            justify-content: center;
        }
    }
    .poster-thumb-wrap {
        width: 140px;
        height: 110px;
        border-radius: 12px;
        overflow: hidden;
        background: #f8fafc;
        border: 2px dashed #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }
    .poster-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .poster-stats-grid {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .poster-stat-item {
        background: #f8fafc;
        padding: 8px 16px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .poster-stat-item .val {
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
    }
    .poster-stat-item .lbl {
        font-size: 11px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Analytics Chart Sections */
    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    @media (max-width: 1024px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
    .chart-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
    }
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .chart-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Activity Details & Tables Grid */
    .activity-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    @media (max-width: 900px) {
        .activity-grid {
            grid-template-columns: 1fr;
        }
    }
    .activity-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .activity-card h3 {
        margin: 0 0 16px 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .progress-bar-wrap {
        height: 8px;
        background: #f1f5f9;
        border-radius: 999px;
        overflow: hidden;
        margin: 8px 0 16px 0;
        display: flex;
    }
    .progress-fill-user {
        background: #10b981;
        height: 100%;
        transition: width 0.3s;
    }
    .progress-fill-guest {
        background: #64748b;
        height: 100%;
        transition: width 0.3s;
    }

    .top-page-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .top-page-item:last-child {
        border-bottom: none;
    }
    .top-page-url {
        font-family: monospace;
        color: #334155;
        font-weight: 600;
        max-width: 250px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .live-feed-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13px;
    }
    .live-feed-item:last-child {
        border-bottom: none;
    }
    .feed-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
    }
    .feed-details {
        flex: 1;
        min-width: 0;
    }
    .feed-title {
        font-weight: 600;
        color: #1e293b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .feed-sub {
        font-size: 11px;
        color: #94a3b8;
    }
</style>

<div class="dash-container">

    <!-- 1. Top Header Banner / Real-Time Pulse -->
    <div class="dash-header-banner">
        <div class="dash-header-left">
            <h2>
                <i class="fa fa-tachometer-alt" style="color: #10b981;"></i> 
                Admin Daily Activity & Analytics
            </h2>
            <p>
                <span class="pulse-dot"></span> 
                <strong>Live Site Status:</strong> Active &nbsp;|&nbsp; 
                <i class="fa fa-calendar-alt"></i> <?= date('l, d F Y') ?>
            </p>
        </div>
        <div class="dash-header-actions">
            <?php if (has_permission('manage_announcements')): ?>
                <a href="settings.php" class="btn-dash-action">
                    <i class="fa fa-bullhorn" style="color: #f59e0b;"></i> Announcement Poster
                </a>
            <?php endif; ?>
            <a href="reports.php?tab=visitors" class="btn-dash-action btn-dash-primary">
                <i class="fa fa-chart-bar"></i> Full Detailed Reports
            </a>
        </div>
    </div>

    <!-- 2. Primary KPI Grid: Unique Visitors, Total Visits, Announcement Views, Active Users, etc. -->
    <div class="kpi-grid">
        
        <!-- CARD 1: TODAY'S UNIQUE VISITORS -->
        <div class="kpi-card emerald">
            <div class="kpi-top">
                <div class="kpi-icon">
                    <i class="fa fa-user-check"></i>
                </div>
                <?php
                $diff_u = $today_unique_visitors - $yest_unique_visitors;
                $pill_class = $diff_u >= 0 ? 'pill-green' : 'pill-amber';
                $pill_text = ($diff_u > 0 ? "+$diff_u" : "$diff_u") . " vs yesterday";
                ?>
                <span class="kpi-pill <?= $pill_class ?>">
                    <?= htmlspecialchars($pill_text) ?>
                </span>
            </div>
            <div>
                <div class="kpi-label">Today's Unique Visitors</div>
                <div class="kpi-value"><?= number_format($today_unique_visitors) ?></div>
            </div>
            <div class="kpi-footer">
                <span>Distinct visitor IPs today</span>
                <strong>All-time: <?= number_format($all_time_unique) ?></strong>
            </div>
        </div>

        <!-- CARD 2: TODAY'S TOTAL VISITS -->
        <div class="kpi-card blue">
            <div class="kpi-top">
                <div class="kpi-icon">
                    <i class="fa fa-eye"></i>
                </div>
                <?php
                $diff_v = $today_total_visits - $yest_total_visits;
                $pill_v_class = $diff_v >= 0 ? 'pill-blue' : 'pill-neutral';
                $pill_v_text = ($diff_v > 0 ? "+$diff_v" : "$diff_v") . " vs y'day";
                ?>
                <span class="kpi-pill <?= $pill_v_class ?>">
                    <?= htmlspecialchars($pill_v_text) ?>
                </span>
            </div>
            <div>
                <div class="kpi-label">Today's Total Page Visits</div>
                <div class="kpi-value"><?= number_format($today_total_visits) ?></div>
            </div>
            <div class="kpi-footer">
                <span><?= $today_unique_visitors > 0 ? round($today_total_visits / $today_unique_visitors, 1) : 0 ?> views / visitor</span>
                <strong>All-time: <?= number_format($all_time_visits) ?></strong>
            </div>
        </div>

        <!-- CARD 3: ANNOUNCEMENT POSTER VIEWS (Every impression counted) -->
        <div class="kpi-card amber">
            <div class="kpi-top">
                <div class="kpi-icon">
                    <i class="fa fa-bullhorn"></i>
                </div>
                <span class="kpi-pill <?= $display_poster_today > 0 ? 'pill-amber' : 'pill-neutral' ?>">
                    +<?= number_format($display_poster_today) ?> today
                </span>
            </div>
            <div>
                <div class="kpi-label">Announcement Poster Views</div>
                <div class="kpi-value"><?= number_format($display_poster_views) ?></div>
            </div>
            <div class="kpi-footer">
                <span>
                    <?php if ($has_poster): ?>
                        <span style="color: #10b981; font-weight: 700;">● Active Poster</span>
                    <?php else: ?>
                        <span style="color: #94a3b8;">No Poster Set</span>
                    <?php endif; ?>
                </span>
                <span>Cumulative Impressions</span>
            </div>
        </div>

        <!-- CARD 4: ACTIVE USERS (Engagement) -->
        <div class="kpi-card purple">
            <div class="kpi-top">
                <div class="kpi-icon">
                    <i class="fa fa-users"></i>
                </div>
                <span class="kpi-pill <?= $users_online_15m > 0 ? 'pill-green' : 'pill-neutral' ?>">
                    <?= $users_online_15m ?> online now
                </span>
            </div>
            <div>
                <div class="kpi-label">Active Users Today</div>
                <div class="kpi-value"><?= number_format($active_users_today) ?></div>
            </div>
            <div class="kpi-footer">
                <span>Active sessions today</span>
                <strong>Total users: <?= number_format($total_users) ?></strong>
            </div>
        </div>

        <!-- CARD 5: LIVE ADS & TODAY'S LISTINGS -->
        <div class="kpi-card teal">
            <div class="kpi-top">
                <div class="kpi-icon">
                    <i class="fa fa-store"></i>
                </div>
                <span class="kpi-pill <?= $today_ads_count > 0 ? 'pill-green' : 'pill-neutral' ?>">
                    +<?= $today_ads_count ?> today
                </span>
            </div>
            <div>
                <div class="kpi-label">Active Marketplace Ads</div>
                <div class="kpi-value"><?= number_format($total_active_ads) ?></div>
            </div>
            <div class="kpi-footer">
                <?php if ($pending_ads_count > 0): ?>
                    <a href="approve_ads.php" style="color: #f59e0b; font-weight: 700; text-decoration: none;">
                        <i class="fa fa-clock"></i> <?= $pending_ads_count ?> Pending Approval
                    </a>
                <?php else: ?>
                    <span style="color: #10b981; font-weight: 600;">All ads reviewed</span>
                <?php endif; ?>
                <span>Live Listings</span>
            </div>
        </div>

        <!-- CARD 6: SAFETY & MODERATION -->
        <div class="kpi-card rose">
            <div class="kpi-top">
                <div class="kpi-icon">
                    <i class="fa fa-shield-alt"></i>
                </div>
                <span class="kpi-pill <?= $pending_reports > 0 ? 'pill-red' : 'pill-green' ?>">
                    <?= $pending_reports > 0 ? 'Action needed' : 'All clear' ?>
                </span>
            </div>
            <div>
                <div class="kpi-label">Pending User Reports</div>
                <div class="kpi-value"><?= number_format($pending_reports) ?></div>
            </div>
            <div class="kpi-footer">
                <span>Flagged user reports</span>
                <a href="reports.php?tab=moderation" style="color: #e11d48; font-weight: 700; text-decoration: none;">Review &rarr;</a>
            </div>
        </div>

    </div>

    <!-- 3. Announcement Poster Spotlight Widget -->
    <div class="announcement-spotlight">
        <div class="poster-thumb-wrap">
            <?php if ($has_poster && file_exists('../' . $current_poster)): ?>
                <img src="../<?= htmlspecialchars($current_poster) ?>" alt="Poster" class="poster-thumb">
            <?php else: ?>
                <div style="text-align: center; color: #94a3b8; font-size: 11px; padding: 8px;">
                    <i class="fa fa-image" style="font-size: 24px; margin-bottom: 4px; display: block;"></i>
                    No Active Poster
                </div>
            <?php endif; ?>
        </div>

        <div>
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a;">
                    Announcement Poster Performance
                </h3>
                <?php if ($has_poster): ?>
                    <span class="kpi-pill pill-green"><i class="fa fa-check-circle"></i> Displaying on Home Page</span>
                <?php else: ?>
                    <span class="kpi-pill pill-neutral">Poster Inactive</span>
                <?php endif; ?>
            </div>
            <p style="margin: 6px 0 0 0; font-size: 13px; color: #64748b;">
                Every time this popup poster is displayed to website visitors, an impression is automatically logged.
            </p>

            <div class="poster-stats-grid">
                <div class="poster-stat-item">
                    <div class="val"><?= number_format($display_poster_views) ?></div>
                    <div class="lbl">Total Views</div>
                </div>
                <div class="poster-stat-item">
                    <div class="val"><?= number_format($display_poster_today) ?></div>
                    <div class="lbl">Views Today</div>
                </div>
                <div class="poster-stat-item">
                    <div class="val"><?= number_format($current_poster_yest_views) ?></div>
                    <div class="lbl">Views Yesterday</div>
                </div>
                <div class="poster-stat-item">
                    <div class="val"><?= $today_unique_visitors > 0 ? round(($display_poster_today / max(1, $today_unique_visitors)) * 100) : 0 ?>%</div>
                    <div class="lbl">Visitor Reach Rate</div>
                </div>
            </div>
        </div>

        <div class="poster-actions" style="display: flex; flex-direction: column; gap: 8px;">
            <?php if (has_permission('manage_announcements')): ?>
                <a href="settings.php" class="btn-dash-action btn-dash-primary" style="justify-content: center;">
                    <i class="fa fa-upload"></i> Update Poster
                </a>
            <?php endif; ?>
            <?php if ($has_poster && file_exists('../' . $current_poster)): ?>
                <a href="../<?= htmlspecialchars($current_poster) ?>" target="_blank" class="btn-dash-action" style="color: #334155; border-color: #cbd5e1; justify-content: center;">
                    <i class="fa fa-external-link-alt"></i> Preview Full
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. Interactive Charts Grid (14-Day Traffic & Today's Hourly Activity) -->
    <div class="charts-grid">
        
        <!-- Chart 1: 14-Day Traffic & Unique Visitors Trend -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>
                    <i class="fa fa-chart-area" style="color: #0284c7;"></i> 
                    14-Day Visitor Trend & Daily Reports
                </h3>
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">
                    Total vs Unique Visitors
                </span>
            </div>
            <div style="position: relative; height: 280px; width: 100%;">
                <canvas id="trafficTrendChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Today's Hourly Activity Heatmap / Peak Usage -->
        <div class="chart-card">
            <div class="chart-header">
                <h3>
                    <i class="fa fa-clock" style="color: #8b5cf6;"></i> 
                    Today's Hourly Activity
                </h3>
                <span class="kpi-pill pill-blue">Peak: <?= htmlspecialchars($peak_hour_label) ?></span>
            </div>
            <p style="margin: -10px 0 16px 0; font-size: 12px; color: #64748b;">
                Distribution of visitor interactions throughout today (00:00 - 23:00)
            </p>
            <div style="position: relative; height: 230px; width: 100%;">
                <canvas id="hourlyActivityChart"></canvas>
            </div>
        </div>

    </div>

    <!-- 5. Activity Breakdown & Live Stream Feed -->
    <div class="activity-grid">
        
        <!-- Left: Engagement Breakdown & Top Pages -->
        <div class="activity-card">
            <h3>
                <i class="fa fa-layer-group" style="color: #10b981;"></i> 
                Today's Audience Mix & Top Visited Sections
            </h3>

            <!-- Audience Mix (Registered vs Guest) -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 600;">
                    <span style="color: #10b981;"><i class="fa fa-user-circle"></i> Registered Users: <?= $user_hits_today ?></span>
                    <span style="color: #64748b;"><i class="fa fa-user-secret"></i> Guests: <?= $guest_hits_today ?></span>
                </div>
                <?php
                $total_hits_split = max(1, $user_hits_today + $guest_hits_today);
                $pct_users = round(($user_hits_today / $total_hits_split) * 100);
                $pct_guests = 100 - $pct_users;
                ?>
                <div class="progress-bar-wrap">
                    <div class="progress-fill-user" style="width: <?= $pct_users ?>%;" title="Registered: <?= $pct_users ?>%"></div>
                    <div class="progress-fill-guest" style="width: <?= $pct_guests ?>%;" title="Guests: <?= $pct_guests ?>%"></div>
                </div>
            </div>

            <div style="font-weight: 700; font-size: 13px; color: #475569; margin-bottom: 8px;">
                Most Active Pages Today
            </div>

            <?php if (!empty($top_pages)): ?>
                <?php foreach ($top_pages as $page): ?>
                    <div class="top-page-item">
                        <span class="top-page-url" title="<?= htmlspecialchars($page['page_url']) ?>">
                            <?= htmlspecialchars($page['page_url'] ?: '/') ?>
                        </span>
                        <span class="kpi-pill pill-blue">
                            <?= number_format($page['cnt']) ?> hits
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: #94a3b8; font-size: 13px; padding: 12px 0;">No page visit data recorded for today yet.</div>
            <?php endif; ?>
        </div>

        <!-- Right: Recent Live Activity Stream -->
        <div class="activity-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0;">
                    <i class="fa fa-stream" style="color: #0284c7;"></i> 
                    Live Site Activity Stream
                </h3>
                <a href="reports.php?tab=visitors" style="font-size: 12px; color: var(--primary-green); font-weight: 700; text-decoration: none;">View All &rarr;</a>
            </div>

            <?php if (!empty($recent_visits)): ?>
                <?php foreach ($recent_visits as $rv): ?>
                    <div class="live-feed-item">
                        <div class="feed-avatar">
                            <?php if (!empty($rv['username'])): ?>
                                <i class="fa fa-user" style="color: #10b981;"></i>
                            <?php else: ?>
                                <i class="fa fa-globe" style="color: #64748b;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="feed-details">
                            <div class="feed-title">
                                <?php if (!empty($rv['username'])): ?>
                                    <strong><?= htmlspecialchars($rv['username']) ?></strong>
                                <?php else: ?>
                                    <span>Guest (<?= htmlspecialchars($rv['ip_address']) ?>)</span>
                                <?php endif; ?>
                                <span style="font-weight: normal; color: #64748b;"> visited </span>
                                <code style="font-size: 12px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?= htmlspecialchars(substr($rv['page_url'] ?: '/', 0, 30)) ?></code>
                            </div>
                            <div class="feed-sub">
                                <i class="fa fa-clock"></i> <?= date('h:i:s A', strtotime($rv['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: #94a3b8; font-size: 13px; padding: 12px 0;">No recent site visits recorded.</div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- Chart.js Render Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. 14-Day Traffic Trend (Dual-Line Chart)
    const trendCtx = document.getElementById('trafficTrendChart');
    if (trendCtx) {
        const trendLabels = <?= json_encode(array_column($history_days, 'label')) ?>;
        const totalVisitsData = <?= json_encode(array_column($history_days, 'total_visits')) ?>;
        const uniqueVisitorsData = <?= json_encode(array_column($history_days, 'unique_visitors')) ?>;

        new Chart(trendCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Total Visits',
                        data: totalVisitsData,
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.08)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0284c7'
                    },
                    {
                        label: 'Unique Visitors',
                        data: uniqueVisitorsData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#10b981'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { size: 12, weight: 600 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 10,
                        titleFont: { size: 13, weight: 700 },
                        bodyFont: { size: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { size: 11 }
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        ticks: {
                            font: { size: 11 }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // 2. Today's Hourly Activity Breakdown (Bar Chart)
    const hourlyCtx = document.getElementById('hourlyActivityChart');
    if (hourlyCtx) {
        const hourlyLabels = <?= json_encode($hourly_labels) ?>;
        const hourlyData = <?= json_encode($hourly_values) ?>;

        new Chart(hourlyCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Visits by Hour',
                    data: hourlyData,
                    backgroundColor: function(context) {
                        const val = context.raw || 0;
                        return val > 0 ? '#8b5cf6' : '#e2e8f0';
                    },
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 10,
                        callbacks: {
                            title: function(items) {
                                return 'Hour: ' + items[0].label;
                            },
                            label: function(item) {
                                return item.raw + ' visits';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { size: 11 }
                        },
                        grid: {
                            color: '#f1f5f9'
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 8,
                            font: { size: 10 }
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>