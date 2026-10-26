<?php
require_once '../config.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle Moderation Actions (Delete Ad, Resolve Report)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_product_id'])) {
        $delete_id = (int)$_POST['delete_product_id'];
        $stmt = $pdo->prepare("UPDATE products SET status = 'deleted' WHERE id = ?");
        $stmt->execute([$delete_id]);

        $stmt2 = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE product_id = ?");
        $stmt2->execute([$delete_id]);

        $success = "Product deleted and associated reports resolved successfully.";
    } elseif (isset($_POST['resolve_report_id'])) {
        $resolve_id = (int)$_POST['resolve_report_id'];
        $stmt = $pdo->prepare("UPDATE reports SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$resolve_id]);
        $success = "Report marked as resolved.";
    }
}

// --- FILTER, MONTH SELECTION & COMPARISON PARAMETERS ---
$active_tab = $_GET['tab'] ?? 'visitors'; // visitors, users, ads, moderation
$period_preset = $_GET['period'] ?? 'month'; // daily, yesterday, weekly, month, yearly, year, all, custom
$group_by = $_GET['group_by'] ?? 'daily'; // hourly, daily, weekly, monthly
$sort_order = $_GET['sort'] ?? 'date_desc'; // date_desc, date_asc, count_desc, count_asc
$search_q = trim($_GET['q'] ?? '');

$today = date('Y-m-d');
$current_month_key = date('Y-m');
$selected_month = $_GET['month'] ?? $current_month_key;

// Year selection support
$cur_year = (int)date('Y');
$selected_year = (string)($_GET['year'] ?? $cur_year);
$available_years = [];
for ($y = $cur_year; $y >= $cur_year - 4; $y--) {
    $available_years[] = (string)$y;
}

// Time range parameters
$start_time = trim($_GET['start_time'] ?? '00:00');
$end_time = trim($_GET['end_time'] ?? '23:59');

// Intelligent default scopes:
// When viewing a specific period (Today, Month, etc.), default scope to 'period' so that users/ads from that period display immediately.
// If 'all' preset is active or user explicitly picked scope, honor it.
if (isset($_GET['user_scope'])) {
    $user_scope = $_GET['user_scope'];
} else {
    $user_scope = ($period_preset === 'all') ? 'all' : 'period';
}

if (isset($_GET['ad_scope'])) {
    $ad_scope = $_GET['ad_scope'];
} else {
    $ad_scope = ($period_preset === 'all') ? 'all' : 'period';
}

$user_status = $_GET['user_status'] ?? 'all'; // 'all', 'verified', 'unverified', 'admin'
$ad_status = $_GET['ad_status'] ?? 'all'; // 'all', 'verified', 'unverified', 'active', 'sold'

// Month options generator for past 24 months
$available_months = [];
for ($i = 0; $i < 24; $i++) {
    $m_time = strtotime("-$i months");
    $m_key = date('Y-m', $m_time);
    $m_label = date('F Y', $m_time);
    $available_months[$m_key] = $m_label;
}

// Comparison Mode state
$is_compare = isset($_GET['compare']) && $_GET['compare'] == '1';
$compare_month = $_GET['compare_month'] ?? date('Y-m', strtotime($selected_month . '-01 -1 month'));

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Calculate primary start & end dates based on preset
if ($period_preset === 'month') {
    $start_date = $selected_month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    $group_by = 'daily';
    $primary_month_label = date('F Y', strtotime($start_date));
} elseif ($period_preset === 'daily') {
    $start_date = $today;
    $end_date = $today;
    $group_by = 'hourly';
    $primary_month_label = "Today (" . date('d M Y') . ")";
} elseif ($period_preset === 'yesterday') {
    $start_date = date('Y-m-d', strtotime('-1 day'));
    $end_date = $start_date;
    $group_by = 'hourly';
    $primary_month_label = "Yesterday (" . date('d M Y', strtotime($start_date)) . ")";
} elseif ($period_preset === 'weekly') {
    $start_date = date('Y-m-d', strtotime('-6 days'));
    $end_date = $today;
    $group_by = 'daily';
    $primary_month_label = "Last 7 Days (" . date('d M', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date)) . ")";
} elseif ($period_preset === 'yearly' || $period_preset === 'year') {
    $start_date = $selected_year . '-01-01';
    $end_date = $selected_year . '-12-31';
    $group_by = 'monthly';
    $primary_month_label = "Year " . $selected_year;
} elseif ($period_preset === 'all') {
    $start_date = '2025-01-01';
    $end_date = $today;
    $group_by = 'monthly';
    $primary_month_label = "All Time (Jan 2025 - Present)";
} else { // custom
    if (empty($start_date)) $start_date = date('Y-m-d', strtotime('-29 days'));
    if (empty($end_date)) $end_date = $today;
    $days_diff = (strtotime($end_date) - strtotime($start_date)) / 86400;
    if ($days_diff <= 1) {
        $group_by = 'hourly';
        $primary_month_label = ($start_date === $end_date) ? date('d M Y', strtotime($start_date)) : (date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)));
    } elseif ($days_diff <= 45) {
        $group_by = 'daily';
        $primary_month_label = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
    } else {
        $group_by = 'monthly';
        $primary_month_label = date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date));
    }
}

$s_time_formatted = (strlen($start_time) === 5) ? ($start_time . ':00') : (empty($start_time) ? '00:00:00' : $start_time);
$e_time_formatted = (strlen($end_time) === 5) ? ($end_time . ':59') : (empty($end_time) ? '23:59:59' : $end_time);
$start_ts = $start_date . ' ' . $s_time_formatted;
$end_ts = $end_date . ' ' . $e_time_formatted;

// Calculate comparison start & end dates
$comp_start_date = '';
$comp_end_date = '';
if ($is_compare) {
    $comp_start_date = $compare_month . '-01';
    $comp_end_date = date('Y-m-t', strtotime($comp_start_date));
    $comp_start_ts = $comp_start_date . ' 00:00:00';
    $comp_end_ts = $comp_end_date . ' 23:59:59';
}

$comp_month_label = $is_compare ? date('F Y', strtotime($compare_month . '-01')) : '';

// Build export URL containing current filter parameters
$export_params = $_GET;
$export_params['action'] = 'export_excel';
$export_params['type'] = $active_tab;
$export_excel_url = 'reports.php?' . http_build_query($export_params);

// =========================================================================
// NATIVE OPENXML EXCEL (.XLSX) EXPORT HANDLER (POWER BI / TABLEAU QUALITY)
// =========================================================================
if (isset($_GET['action']) && ($_GET['action'] === 'export_excel' || $_GET['action'] === 'export_csv')) {
    require_once __DIR__ . '/includes/export_excel_handler.php';
    handle_excel_export($pdo, [
        'export_type' => $_GET['type'] ?? $active_tab,
        'period_preset' => $period_preset,
        'selected_month' => $selected_month,
        'selected_year' => $selected_year,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'start_ts' => $start_ts,
        'end_ts' => $end_ts,
        'primary_month_label' => $primary_month_label,
        'is_compare' => $is_compare,
        'compare_month' => $compare_month,
        'comp_start_date' => $comp_start_date,
        'comp_end_date' => $comp_end_date,
        'comp_start_ts' => $comp_start_ts ?? '',
        'comp_end_ts' => $comp_end_ts ?? '',
        'comp_month_label' => $comp_month_label,
        'user_scope' => $user_scope,
        'ad_scope' => $ad_scope,
        'user_status' => $user_status,
        'ad_status' => $ad_status,
        'search_q' => $search_q
    ]);
    exit;
}

// ==========================================
// 1. SITE VISITORS & TRAFFIC DATA
// ==========================================
$v_summary = [
    'total_visits' => 0,
    'unique_visitors' => 0,
    'user_visits' => 0,
    'guest_visits' => 0,
    'peak_day' => 'N/A',
    'peak_day_count' => 0
];
$v_comp_summary = [
    'total_visits' => 0,
    'unique_visitors' => 0,
    'user_visits' => 0,
    'guest_visits' => 0
];
$visitors_breakdown = [];
$visitors_breakdown_chronological = [];
$top_pages = [];
$hourly_traffic = array_fill(0, 24, 0);
$device_traffic = ['Mobile' => 0, 'Desktop' => 0, 'Tablet / Other' => 0];

$v_primary_days = array_fill(1, 31, 0);
$v_comp_days = array_fill(1, 31, 0);

try {
    // Primary Period Summary
    $stmt_vs = $pdo->prepare("
        SELECT 
            COUNT(*) as total_visits,
            COUNT(DISTINCT ip_address) as unique_visitors,
            COUNT(user_id) as user_visits,
            SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest_visits
        FROM site_visits
        WHERE visit_date BETWEEN :s AND :e
    ");
    $stmt_vs->execute(['s' => $start_date, 'e' => $end_date]);
    $res_vs = $stmt_vs->fetch(PDO::FETCH_ASSOC);
    if ($res_vs) {
        $v_summary['total_visits'] = (int)$res_vs['total_visits'];
        $v_summary['unique_visitors'] = (int)$res_vs['unique_visitors'];
        $v_summary['user_visits'] = (int)$res_vs['user_visits'];
        $v_summary['guest_visits'] = (int)$res_vs['guest_visits'];
    }

    // Comparison Period Summary
    if ($is_compare) {
        $stmt_vcs = $pdo->prepare("
            SELECT 
                COUNT(*) as total_visits,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(user_id) as user_visits,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest_visits
            FROM site_visits
            WHERE visit_date BETWEEN :s AND :e
        ");
        $stmt_vcs->execute(['s' => $comp_start_date, 'e' => $comp_end_date]);
        $res_vcs = $stmt_vcs->fetch(PDO::FETCH_ASSOC);
        if ($res_vcs) {
            $v_comp_summary['total_visits'] = (int)$res_vcs['total_visits'];
            $v_comp_summary['unique_visitors'] = (int)$res_vcs['unique_visitors'];
            $v_comp_summary['user_visits'] = (int)$res_vcs['user_visits'];
            $v_comp_summary['guest_visits'] = (int)$res_vcs['guest_visits'];
        }

        // Comparison Day-by-Day (1..31)
        $stmt_vcd = $pdo->prepare("
            SELECT DAY(visit_date) as d, COUNT(*) as c
            FROM site_visits
            WHERE visit_date BETWEEN :s AND :e
            GROUP BY d
        ");
        $stmt_vcd->execute(['s' => $comp_start_date, 'e' => $comp_end_date]);
        while ($cd_row = $stmt_vcd->fetch(PDO::FETCH_ASSOC)) {
            $d_idx = (int)$cd_row['d'];
            if ($d_idx >= 1 && $d_idx <= 31) $v_comp_days[$d_idx] = (int)$cd_row['c'];
        }
    }

    // ==========================================
    // Master Timeline Slot Generator (Hourly, Daily, Monthly)
    // Ensures charts are always continuous, richly rendered and never empty
    // ==========================================
    $timeline_slots = []; // key => human readable label
    if ($group_by === 'hourly') {
        for ($h = 0; $h < 24; $h++) {
            $k = sprintf('%02d', $h);
            $timeline_slots[$k] = sprintf('%02d:00', $h);
        }
    } elseif ($group_by === 'monthly') {
        if ($period_preset === 'yearly' || $period_preset === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $m_str = sprintf('%02d', $m);
                $k = $selected_year . '-' . $m_str;
                $timeline_slots[$k] = date('M Y', strtotime("$selected_year-$m_str-01"));
            }
        } else {
            $cur_t = strtotime(date('Y-m-01', strtotime($start_date)));
            $end_t = strtotime(date('Y-m-01', strtotime($end_date)));
            while ($cur_t <= $end_t) {
                $k = date('Y-m', $cur_t);
                $timeline_slots[$k] = date('M Y', $cur_t);
                $cur_t = strtotime('+1 month', $cur_t);
            }
        }
    } else {
        // Daily: every day between start_date and end_date
        $cur_t = strtotime($start_date);
        $end_t = strtotime($end_date);
        while ($cur_t <= $end_t) {
            $k = date('Y-m-d', $cur_t);
            $timeline_slots[$k] = date('d M', $cur_t);
            $cur_t = strtotime('+1 day', $cur_t);
        }
    }

    // Normalized visitors timeline maps
    $v_norm_total = array_fill_keys(array_keys($timeline_slots), 0);
    $v_norm_users = array_fill_keys(array_keys($timeline_slots), 0);
    $v_norm_guests = array_fill_keys(array_keys($timeline_slots), 0);

    // Primary Breakdown query
    if ($group_by === 'hourly') {
        $stmt_vb = $pdo->prepare("
            SELECT 
                LPAD(HOUR(created_at), 2, '0') as period_label,
                COUNT(*) as total_visits,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(user_id) as user_visits,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest_visits
            FROM site_visits
            WHERE visit_date BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    } elseif ($group_by === 'monthly') {
        $stmt_vb = $pdo->prepare("
            SELECT 
                DATE_FORMAT(visit_date, '%Y-%m') as period_label,
                COUNT(*) as total_visits,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(user_id) as user_visits,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest_visits
            FROM site_visits
            WHERE visit_date BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    } else {
        $stmt_vb = $pdo->prepare("
            SELECT 
                DATE(visit_date) as period_label,
                DAY(visit_date) as day_num,
                COUNT(*) as total_visits,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(user_id) as user_visits,
                SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as guest_visits
            FROM site_visits
            WHERE visit_date BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    }
    $stmt_vb->execute(['s' => $start_date, 'e' => $end_date]);
    $visitors_breakdown_chronological = $stmt_vb->fetchAll(PDO::FETCH_ASSOC);
    $visitors_breakdown = $visitors_breakdown_chronological;

    foreach ($visitors_breakdown_chronological as $vb) {
        $pk = $vb['period_label'];
        if (isset($v_norm_total[$pk])) {
            $v_norm_total[$pk] = (int)$vb['total_visits'];
            $v_norm_users[$pk] = (int)$vb['user_visits'];
            $v_norm_guests[$pk] = (int)$vb['guest_visits'];
        }
        if ($vb['total_visits'] > $v_summary['peak_day_count']) {
            $v_summary['peak_day_count'] = (int)$vb['total_visits'];
            $v_summary['peak_day'] = $timeline_slots[$pk] ?? $pk;
        }
        if (isset($vb['day_num'])) {
            $d_num = (int)$vb['day_num'];
            if ($d_num >= 1 && $d_num <= 31) {
                $v_primary_days[$d_num] = (int)$vb['total_visits'];
            }
        }
    }

    // Sorting breakdown list based on sort parameter for table
    if ($sort_order === 'date_desc') {
        usort($visitors_breakdown, fn($a, $b) => strcmp($b['period_label'] ?? '', $a['period_label'] ?? ''));
    } elseif ($sort_order === 'date_asc') {
        usort($visitors_breakdown, fn($a, $b) => strcmp($a['period_label'] ?? '', $b['period_label'] ?? ''));
    } elseif ($sort_order === 'count_desc') {
        usort($visitors_breakdown, fn($a, $b) => $b['total_visits'] <=> $a['total_visits']);
    } elseif ($sort_order === 'count_asc') {
        usort($visitors_breakdown, fn($a, $b) => $a['total_visits'] <=> $b['total_visits']);
    }

    // Top visited pages
    $stmt_tp = $pdo->prepare("
        SELECT 
            COALESCE(NULLIF(page_url, ''), '/') as url,
            COUNT(*) as visits_count
        FROM site_visits
        WHERE visit_date BETWEEN :s AND :e
        GROUP BY url
        ORDER BY visits_count DESC
        LIMIT 6
    ");
    $stmt_tp->execute(['s' => $start_date, 'e' => $end_date]);
    $top_pages = $stmt_tp->fetchAll(PDO::FETCH_ASSOC);

    // Hourly Traffic Distribution
    $stmt_hour = $pdo->prepare("
        SELECT HOUR(created_at) as visit_hour, COUNT(*) as count
        FROM site_visits
        WHERE visit_date BETWEEN :s AND :e
        GROUP BY visit_hour
        ORDER BY visit_hour ASC
    ");
    $stmt_hour->execute(['s' => $start_date, 'e' => $end_date]);
    while ($h_row = $stmt_hour->fetch(PDO::FETCH_ASSOC)) {
        $hourly_traffic[(int)$h_row['visit_hour']] = (int)$h_row['count'];
    }

    // Device breakdown
    $stmt_dev = $pdo->prepare("
        SELECT user_agent, COUNT(*) as c
        FROM site_visits
        WHERE visit_date BETWEEN :s AND :e
        GROUP BY user_agent
    ");
    $stmt_dev->execute(['s' => $start_date, 'e' => $end_date]);
    while ($dev_row = $stmt_dev->fetch(PDO::FETCH_ASSOC)) {
        $ua = strtolower($dev_row['user_agent'] ?? '');
        $cnt = (int)$dev_row['c'];
        if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
            $device_traffic['Mobile'] += $cnt;
        } elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
            $device_traffic['Tablet / Other'] += $cnt;
        } else {
            $device_traffic['Desktop'] += $cnt;
        }
    }

} catch (Exception $e) {}

// Chart JSON for Visitors (Guaranteed full, continuous timeline)
$chart_visitors_labels = json_encode(array_values($timeline_slots));
$chart_visitors_total = json_encode(array_values($v_norm_total));
$chart_visitors_users = json_encode(array_values($v_norm_users));
$chart_visitors_guests = json_encode(array_values($v_norm_guests));

// Day 1..31 JSON for normalized month-over-month comparison
$comp_day_labels = json_encode(array_map(fn($d) => 'Day ' . $d, range(1, 31)));
$v_primary_days_json = json_encode(array_values($v_primary_days));
$v_comp_days_json = json_encode(array_values($v_comp_days));

$hourly_labels = json_encode(array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)));
$hourly_counts = json_encode(array_values($hourly_traffic));

$device_labels = json_encode(array_keys($device_traffic));
$device_counts = json_encode(array_values($device_traffic));

// ==========================================
// 2. NEW USER REGISTRATIONS DATA & AUDIT
// ==========================================
$u_all_time = [
    'total_all_time' => 0,
    'verified_all_time' => 0,
    'unverified_all_time' => 0,
    'admins_all_time' => 0,
    'active_7d_all_time' => 0
];

$u_summary = [
    'total_registered' => 0,
    'verified_users' => 0,
    'unverified_users' => 0,
    'active_recent' => 0,
    'avg_per_day' => 0
];
$u_comp_summary = [
    'total_registered' => 0,
    'verified_users' => 0,
    'unverified_users' => 0,
    'active_recent' => 0
];
$u_counts = [
    'all' => 0,
    'verified' => 0,
    'unverified' => 0,
    'admin' => 0
];

$users_breakdown = [];
$users_breakdown_chronological = [];
$users_list = [];
$dow_signups = ['Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 'Thursday' => 0, 'Friday' => 0, 'Saturday' => 0, 'Sunday' => 0];

$u_primary_days = array_fill(1, 31, 0);
$u_comp_days = array_fill(1, 31, 0);

try {
    // 1. All-Time Database User Base Metrics
    $stmt_u_all = $pdo->query("
        SELECT 
            COUNT(*) as total_all_time,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_all_time,
            SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_all_time,
            SUM(CASE WHEN is_admin = 1 OR role = 'admin' THEN 1 ELSE 0 END) as admins_all_time,
            SUM(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_7d_all_time
        FROM users
    ");
    $res_u_all = $stmt_u_all->fetch(PDO::FETCH_ASSOC);
    if ($res_u_all) {
        $u_all_time['total_all_time'] = (int)$res_u_all['total_all_time'];
        $u_all_time['verified_all_time'] = (int)$res_u_all['verified_all_time'];
        $u_all_time['unverified_all_time'] = (int)$res_u_all['unverified_all_time'];
        $u_all_time['admins_all_time'] = (int)$res_u_all['admins_all_time'];
        $u_all_time['active_7d_all_time'] = (int)$res_u_all['active_7d_all_time'];
    }

    // 2. Primary Period Summary
    $stmt_us = $pdo->prepare("
        SELECT 
            COUNT(*) as total_registered,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_users,
            SUM(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_recent
        FROM users
        WHERE created_at BETWEEN :s AND :e
    ");
    $stmt_us->execute(['s' => $start_ts, 'e' => $end_ts]);
    $res_us = $stmt_us->fetch(PDO::FETCH_ASSOC);
    if ($res_us) {
        $u_summary['total_registered'] = (int)$res_us['total_registered'];
        $u_summary['verified_users'] = (int)$res_us['verified_users'];
        $u_summary['unverified_users'] = (int)$res_us['unverified_users'];
        $u_summary['active_recent'] = (int)$res_us['active_recent'];
        
        $days_diff = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400 + 1);
        $u_summary['avg_per_day'] = round($u_summary['total_registered'] / $days_diff, 1);
    }

    // 3. Comparison Summary
    if ($is_compare) {
        $stmt_ucs = $pdo->prepare("
            SELECT 
                COUNT(*) as total_registered,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_users,
                SUM(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_recent
            FROM users
            WHERE created_at BETWEEN :s AND :e
        ");
        $stmt_ucs->execute(['s' => $comp_start_ts, 'e' => $comp_end_ts]);
        $res_ucs = $stmt_ucs->fetch(PDO::FETCH_ASSOC);
        if ($res_ucs) {
            $u_comp_summary['total_registered'] = (int)$res_ucs['total_registered'];
            $u_comp_summary['verified_users'] = (int)$res_ucs['verified_users'];
            $u_comp_summary['unverified_users'] = (int)$res_ucs['unverified_users'];
            $u_comp_summary['active_recent'] = (int)$res_ucs['active_recent'];
        }

        // Comparison Day-by-Day
        $stmt_ucd = $pdo->prepare("
            SELECT DAY(created_at) as d, COUNT(*) as c
            FROM users
            WHERE created_at BETWEEN :s AND :e
            GROUP BY d
        ");
        $stmt_ucd->execute(['s' => $comp_start_ts, 'e' => $comp_end_ts]);
        while ($ucd_row = $stmt_ucd->fetch(PDO::FETCH_ASSOC)) {
            $d_idx = (int)$ucd_row['d'];
            if ($d_idx >= 1 && $d_idx <= 31) $u_comp_days[$d_idx] = (int)$ucd_row['c'];
        }
    }

    // 4. User Breakdown by Date
    $u_norm_total = array_fill_keys(array_keys($timeline_slots), 0);
    $u_norm_verified = array_fill_keys(array_keys($timeline_slots), 0);
    $u_norm_unverified = array_fill_keys(array_keys($timeline_slots), 0);

    if ($group_by === 'hourly') {
        $stmt_ub = $pdo->prepare("
            SELECT 
                LPAD(HOUR(created_at), 2, '0') as period_label,
                COUNT(*) as total_signups,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_count
            FROM users
            WHERE created_at BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    } elseif ($group_by === 'monthly') {
        $stmt_ub = $pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period_label,
                COUNT(*) as total_signups,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_count
            FROM users
            WHERE created_at BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    } else {
        $stmt_ub = $pdo->prepare("
            SELECT 
                DATE(created_at) as period_label,
                DAY(created_at) as day_num,
                COUNT(*) as total_signups,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_count
            FROM users
            WHERE created_at BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    }
    $stmt_ub->execute(['s' => $start_ts, 'e' => $end_ts]);
    $users_breakdown_chronological = $stmt_ub->fetchAll(PDO::FETCH_ASSOC);
    $users_breakdown = $users_breakdown_chronological;

    foreach ($users_breakdown_chronological as $ub) {
        $pk = $ub['period_label'];
        if (isset($u_norm_total[$pk])) {
            $u_norm_total[$pk] = (int)$ub['total_signups'];
            $u_norm_verified[$pk] = (int)$ub['verified_count'];
            $u_norm_unverified[$pk] = (int)$ub['unverified_count'];
        }
        if (isset($ub['day_num'])) {
            $d_num = (int)$ub['day_num'];
            if ($d_num >= 1 && $d_num <= 31) {
                $u_primary_days[$d_num] = (int)$ub['total_signups'];
            }
        }
    }

    // 5. Day of Week Distribution
    $stmt_dow = $pdo->prepare("
        SELECT DAYNAME(created_at) as day_name, COUNT(*) as count
        FROM users
        WHERE created_at BETWEEN :s AND :e
        GROUP BY day_name
    ");
    $stmt_dow->execute(['s' => $start_ts, 'e' => $end_ts]);
    while ($d_row = $stmt_dow->fetch(PDO::FETCH_ASSOC)) {
        if (isset($dow_signups[$d_row['day_name']])) {
            $dow_signups[$d_row['day_name']] = (int)$d_row['count'];
        }
    }

    // 6. User Directory Counts for Tab Pills (Respecting user_scope)
    $scope_condition = ($user_scope === 'period') ? "WHERE created_at BETWEEN :s AND :e" : "WHERE 1=1";
    $scope_params = ($user_scope === 'period') ? ['s' => $start_ts, 'e' => $end_ts] : [];

    $stmt_uc_all = $pdo->prepare("SELECT COUNT(*) FROM users {$scope_condition}");
    $stmt_uc_all->execute($scope_params);
    $u_counts['all'] = (int)$stmt_uc_all->fetchColumn();

    $stmt_uc_ver = $pdo->prepare("SELECT COUNT(*) FROM users {$scope_condition} AND is_verified = 1");
    $stmt_uc_ver->execute($scope_params);
    $u_counts['verified'] = (int)$stmt_uc_ver->fetchColumn();

    $stmt_uc_unver = $pdo->prepare("SELECT COUNT(*) FROM users {$scope_condition} AND (is_verified = 0 OR is_verified IS NULL)");
    $stmt_uc_unver->execute($scope_params);
    $u_counts['unverified'] = (int)$stmt_uc_unver->fetchColumn();

    $stmt_uc_adm = $pdo->prepare("SELECT COUNT(*) FROM users {$scope_condition} AND (is_admin = 1 OR role = 'admin')");
    $stmt_uc_adm->execute($scope_params);
    $u_counts['admin'] = (int)$stmt_uc_adm->fetchColumn();

    // 7. Detailed User List Query
    $where_clauses = [];
    $user_params = [];

    if ($user_scope === 'period') {
        $where_clauses[] = "created_at BETWEEN :s AND :e";
        $user_params['s'] = $start_ts;
        $user_params['e'] = $end_ts;
    }

    if ($user_status === 'verified') {
        $where_clauses[] = "is_verified = 1";
    } elseif ($user_status === 'unverified') {
        $where_clauses[] = "(is_verified = 0 OR is_verified IS NULL)";
    } elseif ($user_status === 'admin') {
        $where_clauses[] = "(is_admin = 1 OR role = 'admin')";
    }

    if (!empty($search_q)) {
        $where_clauses[] = "(username LIKE :q OR phone_number LIKE :q OR email LIKE :q OR id LIKE :q_id)";
        $user_params['q'] = '%' . $search_q . '%';
        $user_params['q_id'] = $search_q;
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    $order_user_sql = "ORDER BY created_at DESC";
    if ($sort_order === 'date_asc') $order_user_sql = "ORDER BY created_at ASC";

    $stmt_ul = $pdo->prepare("
        SELECT id, username, phone_number, email, role, is_admin, is_verified, profile_picture, created_at,
               (SELECT COUNT(*) FROM products WHERE user_id = users.id) as total_ads
        FROM users
        {$where_sql}
        {$order_user_sql}
        LIMIT 250
    ");
    $stmt_ul->execute($user_params);
    $users_list = $stmt_ul->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {}

// Chart JSON for Users (Guaranteed full, continuous timeline)
$cum_total = 0;
$chart_users_cumulative = [];
foreach ($u_norm_total as $c_cnt) {
    $cum_total += (int)$c_cnt;
    $chart_users_cumulative[] = $cum_total;
}

$chart_users_labels = json_encode(array_values($timeline_slots));
$chart_users_total = json_encode(array_values($u_norm_total));
$chart_users_verified = json_encode(array_values($u_norm_verified));
$chart_users_cum_json = json_encode($chart_users_cumulative);

$u_primary_days_json = json_encode(array_values($u_primary_days));
$u_comp_days_json = json_encode(array_values($u_comp_days));

$dow_labels = json_encode(array_keys($dow_signups));
$dow_counts = json_encode(array_values($dow_signups));

// ==========================================
// 3. ADS POSTED & MARKETPLACE INVENTORY DATA
// ==========================================
$a_all_time = [
    'total_all_time' => 0,
    'verified_all_time' => 0,
    'unverified_all_time' => 0,
    'active_all_time' => 0,
    'sold_all_time' => 0,
    'expired_all_time' => 0,
    'total_valuation_all_time' => 0
];

$a_summary = [
    'total_ads' => 0,
    'verified_ads' => 0,
    'unverified_ads' => 0,
    'active_ads' => 0,
    'sold_ads' => 0,
    'expired_ads' => 0,
    'total_valuation' => 0,
    'avg_per_day' => 0
];
$a_comp_summary = [
    'total_ads' => 0,
    'verified_ads' => 0,
    'unverified_ads' => 0,
    'active_ads' => 0,
    'sold_ads' => 0,
    'expired_ads' => 0,
    'total_valuation' => 0
];
$a_counts = [
    'all' => 0,
    'verified' => 0,
    'unverified' => 0,
    'active' => 0,
    'sold' => 0
];

$ads_breakdown = [];
$ads_breakdown_chronological = [];
$category_breakdown = [];
$type_breakdown = [];
$price_tiers = [
    '< ₹1K' => 0,
    '₹1K - 5K' => 0,
    '₹5K - 20K' => 0,
    '₹20K - 50K' => 0,
    '₹50K - 1L' => 0,
    '> ₹1 Lakh' => 0
];
$ads_list = [];

$a_primary_days = array_fill(1, 31, 0);
$a_comp_days = array_fill(1, 31, 0);

try {
    // 1. All-Time Database Inventory Metrics
    $stmt_a_all = $pdo->query("
        SELECT 
            COUNT(*) as total_all_time,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_all_time,
            SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_all_time,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_all_time,
            SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_all_time,
            SUM(CASE WHEN status IN ('expired', 'inactive') THEN 1 ELSE 0 END) as expired_all_time,
            COALESCE(SUM(price), 0) as total_valuation_all_time
        FROM products
    ");
    $res_a_all = $stmt_a_all->fetch(PDO::FETCH_ASSOC);
    if ($res_a_all) {
        $a_all_time['total_all_time'] = (int)$res_a_all['total_all_time'];
        $a_all_time['verified_all_time'] = (int)$res_a_all['verified_all_time'];
        $a_all_time['unverified_all_time'] = (int)$res_a_all['unverified_all_time'];
        $a_all_time['active_all_time'] = (int)$res_a_all['active_all_time'];
        $a_all_time['sold_all_time'] = (int)$res_a_all['sold_all_time'];
        $a_all_time['expired_all_time'] = (int)$res_a_all['expired_all_time'];
        $a_all_time['total_valuation_all_time'] = (float)$res_a_all['total_valuation_all_time'];
    }

    // 2. Primary Period Summary
    $stmt_as = $pdo->prepare("
        SELECT 
            COUNT(*) as total_ads,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_ads,
            SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_ads,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
            SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_ads,
            SUM(CASE WHEN status IN ('expired', 'inactive') THEN 1 ELSE 0 END) as expired_ads,
            COALESCE(SUM(price), 0) as total_valuation
        FROM products
        WHERE created_at BETWEEN :s AND :e
    ");
    $stmt_as->execute(['s' => $start_ts, 'e' => $end_ts]);
    $res_as = $stmt_as->fetch(PDO::FETCH_ASSOC);
    if ($res_as) {
        $a_summary['total_ads'] = (int)$res_as['total_ads'];
        $a_summary['verified_ads'] = (int)$res_as['verified_ads'];
        $a_summary['unverified_ads'] = (int)$res_as['unverified_ads'];
        $a_summary['active_ads'] = (int)$res_as['active_ads'];
        $a_summary['sold_ads'] = (int)$res_as['sold_ads'];
        $a_summary['expired_ads'] = (int)$res_as['expired_ads'];
        $a_summary['total_valuation'] = (float)$res_as['total_valuation'];
        
        $days_diff_ads = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400 + 1);
        $a_summary['avg_per_day'] = round($a_summary['total_ads'] / $days_diff_ads, 1);
    }

    // 3. Comparison Summary
    if ($is_compare) {
        $stmt_acs = $pdo->prepare("
            SELECT 
                COUNT(*) as total_ads,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_ads,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_ads,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_ads,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_ads,
                SUM(CASE WHEN status IN ('expired', 'inactive') THEN 1 ELSE 0 END) as expired_ads,
                COALESCE(SUM(price), 0) as total_valuation
            FROM products
            WHERE created_at BETWEEN :s AND :e
        ");
        $stmt_acs->execute(['s' => $comp_start_ts, 'e' => $comp_end_ts]);
        $res_acs = $stmt_acs->fetch(PDO::FETCH_ASSOC);
        if ($res_acs) {
            $a_comp_summary['total_ads'] = (int)$res_acs['total_ads'];
            $a_comp_summary['verified_ads'] = (int)$res_acs['verified_ads'];
            $a_comp_summary['unverified_ads'] = (int)$res_acs['unverified_ads'];
            $a_comp_summary['active_ads'] = (int)$res_acs['active_ads'];
            $a_comp_summary['sold_ads'] = (int)$res_acs['sold_ads'];
            $a_comp_summary['expired_ads'] = (int)$res_acs['expired_ads'];
            $a_comp_summary['total_valuation'] = (float)$res_acs['total_valuation'];
        }

        // Comparison Day-by-Day
        $stmt_acd = $pdo->prepare("
            SELECT DAY(created_at) as d, COUNT(*) as c
            FROM products
            WHERE created_at BETWEEN :s AND :e
            GROUP BY d
        ");
        $stmt_acd->execute(['s' => $comp_start_ts, 'e' => $comp_end_ts]);
        while ($acd_row = $stmt_acd->fetch(PDO::FETCH_ASSOC)) {
            $d_idx = (int)$acd_row['d'];
            if ($d_idx >= 1 && $d_idx <= 31) $a_comp_days[$d_idx] = (int)$acd_row['c'];
        }
    }

    // 4. Category distribution
    $stmt_cat = $pdo->prepare("
        SELECT c.name as category_name, COUNT(p.id) as ad_count, SUM(p.price) as cat_value
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.created_at BETWEEN :s AND :e
        GROUP BY c.id, c.name
        ORDER BY ad_count DESC
        LIMIT 8
    ");
    $stmt_cat->execute(['s' => $start_ts, 'e' => $end_ts]);
    $category_breakdown = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

    // 5. Type distribution (sell, buy, rent)
    $stmt_type = $pdo->prepare("
        SELECT type, COUNT(*) as count
        FROM products
        WHERE created_at BETWEEN :s AND :e
        GROUP BY type
    ");
    $stmt_type->execute(['s' => $start_ts, 'e' => $end_ts]);
    $type_breakdown = $stmt_type->fetchAll(PDO::FETCH_ASSOC);

    // 6. Price tiers breakdown
    $stmt_pt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN price < 1000 THEN 1 ELSE 0 END) as tier1,
            SUM(CASE WHEN price >= 1000 AND price < 5000 THEN 1 ELSE 0 END) as tier2,
            SUM(CASE WHEN price >= 5000 AND price < 20000 THEN 1 ELSE 0 END) as tier3,
            SUM(CASE WHEN price >= 20000 AND price < 50000 THEN 1 ELSE 0 END) as tier4,
            SUM(CASE WHEN price >= 50000 AND price < 100000 THEN 1 ELSE 0 END) as tier5,
            SUM(CASE WHEN price >= 100000 THEN 1 ELSE 0 END) as tier6
        FROM products
        WHERE created_at BETWEEN :s AND :e
    ");
    $stmt_pt->execute(['s' => $start_ts, 'e' => $end_ts]);
    $pt_res = $stmt_pt->fetch(PDO::FETCH_ASSOC);
    if ($pt_res) {
        $price_tiers['< ₹1K'] = (int)$pt_res['tier1'];
        $price_tiers['₹1K - 5K'] = (int)$pt_res['tier2'];
        $price_tiers['₹5K - 20K'] = (int)$pt_res['tier3'];
        $price_tiers['₹20K - 50K'] = (int)$pt_res['tier4'];
        $price_tiers['₹50K - 1L'] = (int)$pt_res['tier5'];
        $price_tiers['> ₹1 Lakh'] = (int)$pt_res['tier6'];
    }

    // 7. Ads timeline breakdown
    $a_norm_total = array_fill_keys(array_keys($timeline_slots), 0);
    $a_norm_verified = array_fill_keys(array_keys($timeline_slots), 0);
    $a_norm_unverified = array_fill_keys(array_keys($timeline_slots), 0);
    $a_norm_active = array_fill_keys(array_keys($timeline_slots), 0);
    $a_norm_sold = array_fill_keys(array_keys($timeline_slots), 0);

    if ($group_by === 'hourly') {
        $stmt_ab = $pdo->prepare("
            SELECT 
                LPAD(HOUR(created_at), 2, '0') as period_label,
                COUNT(*) as total_ads,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_count,
                COALESCE(SUM(price), 0) as period_value
            FROM products
            WHERE created_at BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    } elseif ($group_by === 'monthly') {
        $stmt_ab = $pdo->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as period_label,
                COUNT(*) as total_ads,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_count,
                COALESCE(SUM(price), 0) as period_value
            FROM products
            WHERE created_at BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    } else {
        $stmt_ab = $pdo->prepare("
            SELECT 
                DATE(created_at) as period_label,
                DAY(created_at) as day_num,
                COUNT(*) as total_ads,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_count,
                COALESCE(SUM(price), 0) as period_value
            FROM products
            WHERE created_at BETWEEN :s AND :e
            GROUP BY period_label
            ORDER BY period_label ASC
        ");
    }
    $stmt_ab->execute(['s' => $start_ts, 'e' => $end_ts]);
    $ads_breakdown_chronological = $stmt_ab->fetchAll(PDO::FETCH_ASSOC);
    $ads_breakdown = $ads_breakdown_chronological;

    foreach ($ads_breakdown_chronological as $ab) {
        $pk = $ab['period_label'];
        if (isset($a_norm_total[$pk])) {
            $a_norm_total[$pk] = (int)$ab['total_ads'];
            $a_norm_verified[$pk] = (int)$ab['verified_count'];
            $a_norm_unverified[$pk] = (int)$ab['unverified_count'];
            $a_norm_active[$pk] = (int)$ab['active_count'];
            $a_norm_sold[$pk] = (int)$ab['sold_count'];
        }
        if (isset($ab['day_num'])) {
            $d_num = (int)$ab['day_num'];
            if ($d_num >= 1 && $d_num <= 31) {
                $a_primary_days[$d_num] = (int)$ab['total_ads'];
            }
        }
    }

    // 8. Ad Counts for Filter Tabs (Respecting ad_scope)
    $ad_scope_cond = ($ad_scope === 'period') ? "WHERE created_at BETWEEN :s AND :e" : "WHERE 1=1";
    $ad_scope_params = ($ad_scope === 'period') ? ['s' => $start_ts, 'e' => $end_ts] : [];

    $stmt_ac_all = $pdo->prepare("SELECT COUNT(*) FROM products {$ad_scope_cond}");
    $stmt_ac_all->execute($ad_scope_params);
    $a_counts['all'] = (int)$stmt_ac_all->fetchColumn();

    $stmt_ac_ver = $pdo->prepare("SELECT COUNT(*) FROM products {$ad_scope_cond} AND is_verified = 1");
    $stmt_ac_ver->execute($ad_scope_params);
    $a_counts['verified'] = (int)$stmt_ac_ver->fetchColumn();

    $stmt_ac_unver = $pdo->prepare("SELECT COUNT(*) FROM products {$ad_scope_cond} AND (is_verified = 0 OR is_verified IS NULL)");
    $stmt_ac_unver->execute($ad_scope_params);
    $a_counts['unverified'] = (int)$stmt_ac_unver->fetchColumn();

    $stmt_ac_act = $pdo->prepare("SELECT COUNT(*) FROM products {$ad_scope_cond} AND status = 'active'");
    $stmt_ac_act->execute($ad_scope_params);
    $a_counts['active'] = (int)$stmt_ac_act->fetchColumn();

    $stmt_ac_sld = $pdo->prepare("SELECT COUNT(*) FROM products {$ad_scope_cond} AND status = 'sold'");
    $stmt_ac_sld->execute($ad_scope_params);
    $a_counts['sold'] = (int)$stmt_ac_sld->fetchColumn();

    // 9. Detailed Ads list query
    $where_ad_clauses = [];
    $ad_params = [];

    if ($ad_scope === 'period') {
        $where_ad_clauses[] = "p.created_at BETWEEN :s AND :e";
        $ad_params['s'] = $start_ts;
        $ad_params['e'] = $end_ts;
    }

    if ($ad_status === 'verified') {
        $where_ad_clauses[] = "p.is_verified = 1";
    } elseif ($ad_status === 'unverified') {
        $where_ad_clauses[] = "(p.is_verified = 0 OR p.is_verified IS NULL)";
    } elseif ($ad_status === 'active') {
        $where_ad_clauses[] = "p.status = 'active'";
    } elseif ($ad_status === 'sold') {
        $where_ad_clauses[] = "p.status = 'sold'";
    }

    if (!empty($search_q)) {
        $where_ad_clauses[] = "(p.title LIKE :q OR p.unique_id LIKE :q OR u.username LIKE :q OR c.name LIKE :q OR p.search_keywords LIKE :q)";
        $ad_params['q'] = '%' . $search_q . '%';
    }

    $where_ad_sql = !empty($where_ad_clauses) ? 'WHERE ' . implode(' AND ', $where_ad_clauses) : '';

    $order_ad_sql = "ORDER BY p.created_at DESC";
    if ($sort_order === 'date_asc') $order_ad_sql = "ORDER BY p.created_at ASC";
    if ($sort_order === 'count_desc') $order_ad_sql = "ORDER BY p.price DESC";
    if ($sort_order === 'count_asc') $order_ad_sql = "ORDER BY p.price ASC";

    $stmt_al = $pdo->prepare("
        SELECT p.*, c.name as category_name, u.username as seller_name, u.phone_number as seller_phone,
               (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) as thumbnail
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.user_id = u.id
        {$where_ad_sql}
        {$order_ad_sql}
        LIMIT 250
    ");
    $stmt_al->execute($ad_params);
    $ads_list = $stmt_al->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {}

// Chart JSON for Ads (Guaranteed full, continuous timeline)
$chart_ads_labels = json_encode(array_values($timeline_slots));
$chart_ads_total = json_encode(array_values($a_norm_total));
$chart_ads_verified = json_encode(array_values($a_norm_verified));
$chart_ads_unverified = json_encode(array_values($a_norm_unverified));
$chart_ads_active = json_encode(array_values($a_norm_active));
$chart_ads_sold = json_encode(array_values($a_norm_sold));

$a_primary_days_json = json_encode(array_values($a_primary_days));
$a_comp_days_json = json_encode(array_values($a_comp_days));

$chart_cat_labels = json_encode(array_column($category_breakdown, 'category_name'));
$chart_cat_counts = json_encode(array_map('intval', array_column($category_breakdown, 'ad_count')));
$chart_cat_values = json_encode(array_map('floatval', array_column($category_breakdown, 'cat_value')));

$chart_type_labels = json_encode(array_map(fn($t) => strtoupper($t['type']), $type_breakdown));
$chart_type_counts = json_encode(array_map('intval', array_column($type_breakdown, 'count')));

$price_tier_labels = json_encode(array_keys($price_tiers));
$price_tier_counts = json_encode(array_values($price_tiers));

// ==========================================
// 4. MODERATION & REPORTED ADS DATA
// ==========================================
$reports = [];
$report_reasons = [];
$mod_stats = ['total' => 0, 'pending' => 0, 'resolved' => 0];

try {
    $stmt_rep = $pdo->query("
        SELECT r.*, p.title as product_title, p.status as product_status, u1.username as reporter_name, u2.username as owner_name, u2.phone_number as owner_phone
        FROM reports r 
        JOIN products p ON r.product_id = p.id
        JOIN users u1 ON r.reported_by_user_id = u1.id
        JOIN users u2 ON p.user_id = u2.id
        ORDER BY r.status ASC, r.created_at DESC
    ");
    $reports = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reports as $r) {
        $mod_stats['total']++;
        if ($r['status'] === 'pending') $mod_stats['pending']++;
        else $mod_stats['resolved']++;

        $reason = trim($r['reason'] ?: 'Unspecified');
        $report_reasons[$reason] = ($report_reasons[$reason] ?? 0) + 1;
    }
} catch (Exception $e) {}

$chart_rep_reasons_labels = json_encode(array_keys($report_reasons));
$chart_rep_reasons_counts = json_encode(array_values($report_reasons));

// Excel Export URL preserving all active filter parameters, comparative state & keywords
$export_params = $_GET;
$export_params['action'] = 'export_excel';
$export_params['type'] = $active_tab;
$export_excel_url = 'reports.php?' . http_build_query($export_params);

require_once 'includes/header.php';
?>

<!-- Chart.js 4.4.x Local Bundle + CDN Fallback -->
<script src="<?= $base_url ?>/assets/js/chart.umd.min.js"></script>
<script>
if (typeof Chart === 'undefined') {
    const fallbackScript = document.createElement('script');
    fallbackScript.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    document.head.appendChild(fallbackScript);
}
</script>

<style>
    /* ==========================================================
       ENTEANGADI ULTRA-VIBRANT COMPARATIVE GRAPHICAL REPORTS
       ========================================================== */
    :root {
        --primary-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --blue-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        --purple-gradient: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
        --amber-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --pink-gradient: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
        --cyan-gradient: linear-gradient(135deg, #06b6d4 0%, #0e7490 100%);
    }

    .rep-container {
        max-width: 1360px;
        margin: 0 auto;
    }

    .rep-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .rep-title-box h1 {
        margin: 0 0 6px;
        font-size: 26px;
        font-weight: 850;
        color: #0f172a;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .rep-title-box h1 i {
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .rep-title-box p {
        margin: 0;
        font-size: 13.5px;
        color: #64748b;
        font-weight: 500;
    }

    .rep-nav-tabs {
        display: flex;
        gap: 8px;
        background: #f1f5f9;
        padding: 6px;
        border-radius: 18px;
        margin-bottom: 24px;
        overflow-x: auto;
        border: 1.5px solid #e2e8f0;
    }

    .rep-tab-link {
        padding: 12px 22px;
        border-radius: 14px;
        font-size: 13.5px;
        font-weight: 750;
        color: #64748b;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .rep-tab-link:hover {
        color: #0f172a;
        background: rgba(255,255,255,0.85);
        transform: translateY(-1px);
    }

    .rep-tab-link.active {
        background: #ffffff;
        color: #059669;
        box-shadow: 0 4px 14px rgba(0,0,0,0.07);
        border: 1px solid #d1fae5;
    }

    .rep-tab-badge {
        font-size: 11px;
        padding: 3px 9px;
        border-radius: 12px;
        background: #e2e8f0;
        color: #475569;
        font-weight: 800;
        transition: all 0.2s;
    }

    .rep-tab-link.active .rep-tab-badge {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
    }

    /* Modern Filter Toolbar */
    .rep-filter-card {
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        border-radius: 22px;
        padding: 20px 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    }

    .rep-filter-row-top {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1.5px solid #f1f5f9;
    }

    .rep-presets-group {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .rep-preset-btn {
        padding: 8px 15px;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #475569;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .rep-preset-btn:hover {
        background: #f8fafc;
        color: #1e293b;
        transform: translateY(-1px);
    }

    .rep-preset-btn.active {
        background: var(--primary-gradient);
        border-color: #059669;
        color: #ffffff;
        box-shadow: 0 3px 10px rgba(5, 150, 105, 0.3);
    }

    .rep-filter-row-bottom {
        display: flex;
        flex-wrap: wrap;
        gap: 14px;
        align-items: center;
        justify-content: space-between;
    }

    .rep-date-controls {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .rep-input-group {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #f8fafc;
        border: 1.5px solid #cbd5e1;
        border-radius: 12px;
        padding: 6px 12px;
        transition: all 0.2s;
    }

    .rep-input-group:focus-within {
        border-color: #059669;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    .rep-input-group.highlight-compare {
        border-color: #8b5cf6;
        background: #faf5ff;
    }

    .rep-input-group label {
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        margin: 0;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    .rep-input-group select,
    .rep-input-group input[type="date"],
    .rep-input-group input[type="time"],
    .rep-input-group input[type="text"] {
        border: none;
        background: transparent;
        font-size: 13px;
        font-weight: 650;
        color: #1e293b;
        outline: none;
        padding: 2px 4px;
        font-family: inherit;
    }

    .rep-datetime-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .rep-datetime-pill input[type="date"] {
        width: 125px;
        max-width: 130px;
    }

    .rep-datetime-pill input[type="time"] {
        width: 82px;
        max-width: 85px;
    }

    .rep-sep {
        color: #cbd5e1;
        font-weight: 300;
        font-size: 13px;
        user-select: none;
    }

    .rep-time-icon {
        color: #64748b;
        font-size: 11.5px;
        margin-left: 2px;
    }

    .rep-compare-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1.5px solid #cbd5e1;
        font-size: 12.5px;
        font-weight: 750;
        color: #475569;
        cursor: pointer;
        user-select: none;
        transition: all 0.2s;
    }

    .rep-compare-pill:hover {
        background: #f1f5f9;
    }

    .rep-compare-pill.active {
        background: #faf5ff;
        border-color: #8b5cf6;
        color: #6d28d9;
    }

    .rep-btn-apply {
        background: var(--primary-gradient);
        color: #ffffff;
        border: none;
        padding: 9px 20px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 750;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        box-shadow: 0 3px 10px rgba(5, 150, 105, 0.25);
    }

    .rep-btn-apply:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
    }

    .rep-btn-export {
        background: #ffffff;
        border: 1.5px solid #cbd5e1;
        color: #334155;
        padding: 9px 18px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 750;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        cursor: pointer;
    }

    .rep-btn-export:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
        transform: translateY(-1px);
    }

    /* Comparison Banner */
    .rep-compare-banner {
        background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
        border: 1.5px solid #c4b5fd;
        border-radius: 18px;
        padding: 14px 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .rep-compare-banner-text {
        font-size: 13.5px;
        font-weight: 750;
        color: #5b21b6;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .rep-compare-legend {
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 12.5px;
        font-weight: 700;
    }

    .rep-legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .rep-legend-bullet {
        width: 12px;
        height: 12px;
        border-radius: 4px;
    }

    /* Variance Pill in KPI Cards */
    .rep-variance-pill {
        font-size: 11px;
        font-weight: 850;
        padding: 3px 8px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: 6px;
    }
    .rep-variance-pill.up { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .rep-variance-pill.down { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .rep-variance-pill.neutral { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

    /* Vibrant Multi-Color KPI Cards */
    .rep-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .rep-kpi-card {
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        border-radius: 22px;
        padding: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.03);
        position: relative;
        overflow: hidden;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .rep-kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 28px rgba(0,0,0,0.07);
    }

    .rep-kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 6px;
        height: 100%;
    }

    .rep-kpi-card.green::before { background: var(--primary-gradient); }
    .rep-kpi-card.blue::before { background: var(--blue-gradient); }
    .rep-kpi-card.purple::before { background: var(--purple-gradient); }
    .rep-kpi-card.amber::before { background: var(--amber-gradient); }
    .rep-kpi-card.pink::before { background: var(--pink-gradient); }
    .rep-kpi-card.cyan::before { background: var(--cyan-gradient); }

    .rep-kpi-icon-wrapper {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
        position: relative;
    }

    .rep-kpi-card.green .rep-kpi-icon-wrapper { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
    .rep-kpi-card.blue .rep-kpi-icon-wrapper { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .rep-kpi-card.purple .rep-kpi-icon-wrapper { background: #faf5ff; color: #7c3aed; border: 1px solid #e9d5ff; }
    .rep-kpi-card.amber .rep-kpi-icon-wrapper { background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
    .rep-kpi-card.pink .rep-kpi-icon-wrapper { background: #fdf2f8; color: #db2777; border: 1px solid #fbcfe8; }
    .rep-kpi-card.cyan .rep-kpi-icon-wrapper { background: #ecfeff; color: #0891b2; border: 1px solid #a5f3fc; }

    .rep-kpi-info h3 {
        margin: 0 0 4px;
        font-size: 11.5px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .rep-kpi-info .rep-kpi-value {
        font-size: 26px;
        font-weight: 850;
        color: #0f172a;
        line-height: 1.15;
    }

    .rep-kpi-info .rep-kpi-sub {
        font-size: 12px;
        color: #94a3b8;
        margin-top: 4px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Graphical Charts Layout */
    .rep-charts-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .rep-charts-equal-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    @media (max-width: 1024px) {
        .rep-charts-row, .rep-charts-equal-row {
            grid-template-columns: 1fr;
        }
    }

    .rep-chart-card {
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        border-radius: 24px;
        padding: 24px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.03);
        display: flex;
        flex-direction: column;
        position: relative;
        transition: transform 0.2s;
    }

    .rep-chart-card:hover {
        border-color: #cbd5e1;
    }

    .rep-chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .rep-chart-title {
        font-size: 16px;
        font-weight: 850;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .rep-chart-subtitle {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
        margin-top: 2px;
    }

    .rep-chart-canvas-wrapper {
        position: relative;
        height: 310px;
        min-height: 290px;
        width: 100%;
        display: block;
    }

    /* Progress bar meters */
    .rep-meter-item {
        margin-bottom: 16px;
    }
    .rep-meter-item:last-child {
        margin-bottom: 0;
    }
    .rep-meter-head {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 6px;
    }
    .rep-meter-bar-bg {
        height: 9px;
        background: #f1f5f9;
        border-radius: 10px;
        overflow: hidden;
        position: relative;
    }
    .rep-meter-bar-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Data Tables */
    .rep-table-card {
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        border-radius: 24px;
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    }

    .rep-table-header {
        padding: 20px 24px;
        border-bottom: 1.5px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: #ffffff;
    }

    .rep-table-title {
        font-size: 16px;
        font-weight: 850;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .rep-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .rep-table th {
        background: #f8fafc;
        padding: 14px 22px;
        font-size: 11.5px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1.5px solid #e2e8f0;
    }

    .rep-table td {
        padding: 15px 22px;
        font-size: 13.5px;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .rep-table tr:hover td {
        background: #f8fafc;
    }

    /* Badge Pills */
    .rep-badge {
        font-size: 11px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .rep-badge.active, .rep-badge.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .rep-badge.sold { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .rep-badge.expired { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
    .rep-badge.pending { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

    /* ==========================================================
       PRO-LEVEL EXECUTIVE PRINT & PDF STYLESHEET
       ========================================================== */
    .rep-print-letterhead,
    .rep-print-footer {
        display: none;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 10mm 8mm;
        }
        @page landscape-mode {
            size: A4 landscape;
            margin: 8mm 8mm 10mm 8mm;
        }
        body.print-landscape {
            page: landscape-mode;
        }

        html, body {
            background: #ffffff !important;
            color: #0f172a !important;
            font-size: 10px !important;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Hide all global and admin navigation/header noise, filter forms, search boxes, and interactive directory toolbars */
        .admin-sidebar, 
        .admin-header,
        header,
        .admin-footer,
        .rep-filter-card, 
        .rep-nav-tabs, 
        .rep-header,
        .rep-btn-export, 
        .rep-btn-apply, 
        .rep-actions,
        .rep-directory-toolbar,
        .rep-search-box,
        button,
        form { 
            display: none !important; 
        }

        .admin-main,
        .admin-content,
        .rep-container {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            box-shadow: none !important;
        }

        /* Show Executive Letterhead in Print */
        .rep-print-letterhead {
            display: block !important;
            margin-bottom: 14px !important;
            border-bottom: 2px solid #059669 !important;
            padding-bottom: 10px !important;
        }

        .rep-print-branding {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .rep-print-logo {
            font-size: 18px;
            font-weight: 900;
            color: #059669;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rep-print-badge {
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 3px 8px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            color: #334155;
        }

        .rep-print-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 7px 10px;
            font-size: 10px;
        }

        .rep-print-meta-item strong {
            display: block;
            font-size: 8.5px;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }

        /* KPI Cards in Print */
        .rep-kpi-grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 8px !important;
            margin-bottom: 12px !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rep-kpi-card {
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
            border-radius: 8px !important;
            padding: 8px 10px !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rep-kpi-icon-wrapper {
            width: 28px !important;
            height: 28px !important;
            font-size: 12px !important;
            border-radius: 6px !important;
        }

        .rep-kpi-info h3 {
            font-size: 10px !important;
        }

        .rep-kpi-info .rep-kpi-value {
            font-size: 15px !important;
        }

        .rep-kpi-sub {
            font-size: 9px !important;
        }

        /* Chart Cards in Print */
        .rep-charts-row,
        .rep-charts-equal-row {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 10px !important;
            margin-bottom: 12px !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rep-chart-card {
            border: 1px solid #cbd5e1 !important;
            box-shadow: none !important;
            border-radius: 8px !important;
            padding: 8px 10px !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rep-chart-header {
            margin-bottom: 4px !important;
        }

        .rep-chart-title {
            font-size: 11px !important;
        }

        .rep-chart-subtitle {
            font-size: 9px !important;
        }

        .rep-chart-canvas-wrapper {
            min-height: 160px !important;
            height: 175px !important;
            max-height: 190px !important;
        }

        canvas {
            max-height: 190px !important;
            width: 100% !important;
        }

        /* Comparison Banner in Print */
        .rep-compare-banner {
            border: 1px solid #a78bfa !important;
            border-radius: 6px !important;
            padding: 6px 10px !important;
            margin-bottom: 10px !important;
            background: #f5f3ff !important;
            break-inside: avoid !important;
        }

        /* Data Tables in Print */
        .rep-table-card {
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            box-shadow: none !important;
            margin-bottom: 14px !important;
            break-inside: auto !important;
            page-break-inside: auto !important;
            overflow: visible !important;
        }

        .rep-table-header {
            padding: 8px 10px !important;
            background: #f8fafc !important;
            border-bottom: 1px solid #cbd5e1 !important;
        }

        .rep-table-header form,
        .rep-table-header .rep-directory-toolbar {
            display: none !important;
        }

        .rep-table-title {
            font-size: 11px !important;
            font-weight: 800 !important;
        }

        .rep-table-wrapper {
            overflow: visible !important;
            width: 100% !important;
        }

        .rep-table {
            width: 100% !important;
            max-width: 100% !important;
            table-layout: auto !important;
            border-collapse: collapse !important;
        }

        .rep-table thead {
            display: table-header-group !important;
        }

        .rep-table tbody {
            break-inside: auto !important;
            page-break-inside: auto !important;
        }

        .rep-table tr {
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rep-table th, 
        .rep-table td {
            padding: 4px 6px !important;
            word-break: break-word !important;
            overflow-wrap: anywhere !important;
            white-space: normal !important;
            font-size: 8pt !important;
            line-height: 1.25 !important;
            vertical-align: middle !important;
        }

        /* Hide interactive action buttons in users and ads tables during print */
        .rep-users-table th:last-child,
        .rep-users-table td:last-child,
        .rep-ads-table th:last-child,
        .rep-ads-table td:last-child {
            display: none !important;
        }

        .rep-table th {
            background: #f1f5f9 !important;
            border-bottom: 1.5px solid #94a3b8 !important;
            color: #0f172a !important;
            font-weight: 800 !important;
            font-size: 7.5pt !important;
            text-transform: uppercase !important;
        }

        .rep-table td {
            border-bottom: 1px solid #e2e8f0 !important;
            color: #1e293b !important;
        }

        /* Specific Table Column Widths for Guaranteed Clean Fit */
        .rep-users-table th:nth-child(1), .rep-users-table td:nth-child(1) { width: 22% !important; }
        .rep-users-table th:nth-child(2), .rep-users-table td:nth-child(2) { width: 14% !important; }
        .rep-users-table th:nth-child(3), .rep-users-table td:nth-child(3) { width: 20% !important; }
        .rep-users-table th:nth-child(4), .rep-users-table td:nth-child(4) { width: 13% !important; }
        .rep-users-table th:nth-child(5), .rep-users-table td:nth-child(5) { width: 13% !important; }
        .rep-users-table th:nth-child(6), .rep-users-table td:nth-child(6) { width: 6% !important; text-align: center; }
        .rep-users-table th:nth-child(7), .rep-users-table td:nth-child(7) { width: 12% !important; }

        .rep-ads-table th:nth-child(1), .rep-ads-table td:nth-child(1) { width: 25% !important; }
        .rep-ads-table th:nth-child(2), .rep-ads-table td:nth-child(2) { width: 12% !important; }
        .rep-ads-table th:nth-child(3), .rep-ads-table td:nth-child(3) { width: 15% !important; }
        .rep-ads-table th:nth-child(4), .rep-ads-table td:nth-child(4) { width: 10% !important; }
        .rep-ads-table th:nth-child(5), .rep-ads-table td:nth-child(5) { width: 11% !important; }
        .rep-ads-table th:nth-child(6), .rep-ads-table td:nth-child(6) { width: 9% !important; }
        .rep-ads-table th:nth-child(7), .rep-ads-table td:nth-child(7) { width: 6% !important; text-align: center; }
        .rep-ads-table th:nth-child(8), .rep-ads-table td:nth-child(8) { width: 12% !important; }

        /* Compact Thumbnails & Avatars in Print */
        .rep-table td img,
        .rep-table td .user-avatar-circle {
            width: 20px !important;
            height: 20px !important;
            min-width: 20px !important;
            font-size: 8.5px !important;
            border-radius: 4px !important;
        }

        /* Compact Badges in Print */
        .rep-badge {
            font-size: 7pt !important;
            padding: 2px 5px !important;
            border-radius: 4px !important;
            white-space: normal !important;
            display: inline-block !important;
        }

        /* Show Executive Print Sign-off Footer */
        .rep-print-footer {
            display: block !important;
            margin-top: 18px !important;
            padding-top: 10px !important;
            border-top: 1.5px solid #cbd5e1 !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .rep-print-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 16px;
            margin-bottom: 12px;
        }

        .rep-sig-line {
            border-top: 1px dashed #64748b;
            padding-top: 5px;
            font-size: 10px;
            color: #475569;
            font-weight: 700;
        }

        .rep-print-footnote {
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            margin-top: 8px;
        }
    }
</style>

<div class="rep-container">
    <!-- Executive Print Letterhead (Rendered only on Print / PDF export) -->
    <div class="rep-print-letterhead">
        <div class="rep-print-branding">
            <div>
                <div class="rep-print-logo">
                    <i class="fa fa-shopping-bag"></i> ENTEANGADI MARKETPLACE
                </div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600; margin-top: 2px;">
                    Enterprise Telemetry, Growth Analytics & Comparative Performance Report
                </div>
            </div>
            <div class="rep-print-badge">
                <i class="fa fa-shield-alt" style="color:#059669;"></i> Official Executive Audit
            </div>
        </div>
        <div class="rep-print-meta-grid">
            <div class="rep-print-meta-item">
                <strong>Report Section</strong>
                <?= ucfirst($active_tab) ?> Analytics & Insights
            </div>
            <div class="rep-print-meta-item">
                <strong>Reporting Period</strong>
                <?= htmlspecialchars($primary_month_label) ?>
            </div>
            <div class="rep-print-meta-item">
                <strong>Generated On</strong>
                <?= date('M d, Y - h:i A') ?>
            </div>
            <?php if ($is_compare): ?>
                <div class="rep-print-meta-item">
                    <strong>Comparison Benchmark</strong>
                    <?= htmlspecialchars($comp_month_label) ?>
                </div>
                <div class="rep-print-meta-item">
                    <strong>Analytical Mode</strong>
                    Period-over-Period Variance Analysis
                </div>
                <div class="rep-print-meta-item">
                    <strong>Audited System</strong>
                    Production Database Telemetry
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page Header (Web UI) -->
    <div class="rep-header">
        <div class="rep-title-box">
            <h1><i class="fa fa-chart-pie"></i> Visual Intelligence & Comparative Reports</h1>
            <p>Compare monthly performance, visitor telemetry, user velocity, and inventory dynamics.</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button onclick="printReport('portrait')" class="rep-btn-export" title="Save or Print Graphical Visual Report (Portrait A4)">
                <i class="fa fa-file-pdf" style="color: #ef4444;"></i> Graphical PDF (Portrait)
            </button>
            <button onclick="printReport('landscape')" class="rep-btn-export" style="background: #ffffff; color: #334155; border: 1.5px solid #cbd5e1;" title="Save or Print Graphical Visual Report (Landscape A4 - Wide Visual Charts)">
                <i class="fa fa-print" style="color: #0284c7;"></i> PDF (Landscape)
            </button>
            <a href="<?= $export_excel_url ?>" class="rep-btn-apply" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); border-color: #047857;" title="Download rich styled Excel spreadsheet with native charts (.xlsx)">
                <i class="fa fa-file-excel" style="color: #a7f3d0;"></i> Download Excel (.xlsx)
            </a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div style="background: #ecfdf5; color: #065f46; border: 1.5px solid #a7f3d0; padding: 14px 20px; border-radius: 18px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-check-circle" style="font-size: 20px; color: #059669;"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="rep-nav-tabs">
        <a href="reports.php?tab=visitors&period=<?= $period_preset ?>&month=<?= $selected_month ?>&year=<?= $selected_year ?>&compare=<?= $is_compare ? '1' : '0' ?>&compare_month=<?= $compare_month ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&start_time=<?= urlencode($start_time) ?>&end_time=<?= urlencode($end_time) ?>&group_by=<?= $group_by ?>" class="rep-tab-link <?= $active_tab === 'visitors' ? 'active' : '' ?>">
            <i class="fa fa-globe"></i> 1. Site Visitors Traffic
            <span class="rep-tab-badge"><?= number_format($v_summary['total_visits']) ?></span>
        </a>
        <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&year=<?= $selected_year ?>&compare=<?= $is_compare ? '1' : '0' ?>&compare_month=<?= $compare_month ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&start_time=<?= urlencode($start_time) ?>&end_time=<?= urlencode($end_time) ?>&group_by=<?= $group_by ?>" class="rep-tab-link <?= $active_tab === 'users' ? 'active' : '' ?>">
            <i class="fa fa-users"></i> 2. Users & Registrations
            <span class="rep-tab-badge"><?= number_format($u_all_time['total_all_time']) ?></span>
        </a>
        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&year=<?= $selected_year ?>&compare=<?= $is_compare ? '1' : '0' ?>&compare_month=<?= $compare_month ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&start_time=<?= urlencode($start_time) ?>&end_time=<?= urlencode($end_time) ?>&group_by=<?= $group_by ?>" class="rep-tab-link <?= $active_tab === 'ads' ? 'active' : '' ?>">
            <i class="fa fa-layer-group"></i> 3. Ads Posted & Inventory
            <span class="rep-tab-badge"><?= number_format($a_all_time['total_all_time']) ?></span>
        </a>
        <a href="reports.php?tab=moderation" class="rep-tab-link <?= $active_tab === 'moderation' ? 'active' : '' ?>">
            <i class="fa fa-shield-alt"></i> 4. Flagged & Moderation
            <span class="rep-tab-badge" style="background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;"><?= count($reports) ?></span>
        </a>
    </div>

    <!-- Filter & Custom Date Range / Month Comparison Toolbar -->
    <?php if ($active_tab !== 'moderation'): ?>
        <div class="rep-filter-card">
            <form method="GET" action="reports.php">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">

                <!-- Top Row: Quick Presets, Month Selector & Year Selector -->
                <div class="rep-filter-row-top" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <div class="rep-presets-group" style="flex-wrap: wrap;">
                        <a href="reports.php?tab=<?= $active_tab ?>&period=daily" class="rep-preset-btn <?= $period_preset === 'daily' ? 'active' : '' ?>"><i class="fa fa-sun"></i> Today</a>
                        <a href="reports.php?tab=<?= $active_tab ?>&period=yesterday" class="rep-preset-btn <?= $period_preset === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
                        <a href="reports.php?tab=<?= $active_tab ?>&period=weekly" class="rep-preset-btn <?= $period_preset === 'weekly' ? 'active' : '' ?>">Last 7 Days</a>
                        <a href="reports.php?tab=<?= $active_tab ?>&period=month&month=<?= $current_month_key ?>" class="rep-preset-btn <?= ($period_preset === 'month' && $selected_month === $current_month_key) ? 'active' : '' ?>"><i class="far fa-calendar"></i> This Month</a>
                        <a href="reports.php?tab=<?= $active_tab ?>&period=month&month=<?= date('Y-m', strtotime('-1 month')) ?>" class="rep-preset-btn <?= ($period_preset === 'month' && $selected_month === date('Y-m', strtotime('-1 month'))) ? 'active' : '' ?>">Last Month</a>
                        <a href="reports.php?tab=<?= $active_tab ?>&period=yearly&year=<?= date('Y') ?>" class="rep-preset-btn <?= ($period_preset === 'yearly' || $period_preset === 'year') ? 'active' : '' ?>">This Year</a>
                        <a href="reports.php?tab=<?= $active_tab ?>&period=all" class="rep-preset-btn <?= $period_preset === 'all' ? 'active' : '' ?>">All Time</a>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <!-- Month Picker Dropdown -->
                        <div class="rep-input-group" style="background: #ffffff; border-color: #059669;">
                            <label><i class="far fa-calendar-alt" style="color: #059669;"></i> Select Month</label>
                            <select name="month" onchange="document.getElementById('period_input').value='month'; this.form.submit();" style="font-weight: 750; color: #059669;">
                                <?php foreach ($available_months as $m_k => $m_l): ?>
                                    <option value="<?= $m_k ?>" <?= ($selected_month === $m_k && $period_preset === 'month') ? 'selected' : '' ?>>
                                        <?= $m_l ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year Picker Dropdown -->
                        <div class="rep-input-group" style="background: #ffffff; border-color: #6366f1;">
                            <label><i class="far fa-calendar" style="color: #6366f1;"></i> Select Year</label>
                            <select name="year" onchange="document.getElementById('period_input').value='year'; this.form.submit();" style="font-weight: 750; color: #4f46e5;">
                                <?php foreach ($available_years as $y_opt): ?>
                                    <option value="<?= $y_opt ?>" <?= ($selected_year === $y_opt && ($period_preset === 'yearly' || $period_preset === 'year')) ? 'selected' : '' ?>>
                                        Year <?= $y_opt ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Comparison Controls, Date & Time Range -->
                <div class="rep-filter-row-bottom">
                    <input type="hidden" name="period" id="period_input" value="<?= htmlspecialchars($period_preset) ?>">

                    <div class="rep-date-controls" style="flex-wrap: wrap;">
                        <!-- Compare Mode Checkbox / Toggle -->
                        <label class="rep-compare-pill <?= $is_compare ? 'active' : '' ?>">
                            <input type="checkbox" name="compare" value="1" <?= $is_compare ? 'checked' : '' ?> onchange="this.form.submit()" style="accent-color: #7c3aed; cursor: pointer;">
                            <i class="fa fa-columns"></i> Compare with Another Month
                        </label>

                        <!-- Secondary Month to Compare Against -->
                        <?php if ($is_compare): ?>
                            <div class="rep-input-group highlight-compare">
                                <label><i class="fa fa-exchange-alt" style="color: #7c3aed;"></i> Compare vs</label>
                                <select name="compare_month" onchange="this.form.submit()" style="color: #6d28d9; font-weight: 750;">
                                    <?php foreach ($available_months as $m_k => $m_l): ?>
                                        <?php if ($m_k !== $selected_month): ?>
                                            <option value="<?= $m_k ?>" <?= $compare_month === $m_k ? 'selected' : '' ?>>
                                                <?= $m_l ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <!-- Custom Calendar Date & Time Pickers (Unified Non-Overlapping Pills) -->
                        <div class="rep-input-group rep-datetime-pill">
                            <label><i class="far fa-calendar-alt"></i> From</label>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" onchange="document.getElementById('period_input').value='custom';" title="Start Date">
                            <span class="rep-sep">|</span>
                            <i class="far fa-clock rep-time-icon" title="From Time"></i>
                            <input type="time" name="start_time" value="<?= htmlspecialchars($start_time) ?>" onchange="document.getElementById('period_input').value='custom';" title="From Time (HH:MM)">
                        </div>

                        <div class="rep-input-group rep-datetime-pill">
                            <label><i class="far fa-calendar-alt"></i> To</label>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" onchange="document.getElementById('period_input').value='custom';" title="End Date">
                            <span class="rep-sep">|</span>
                            <i class="far fa-clock rep-time-icon" title="To Time"></i>
                            <input type="time" name="end_time" value="<?= htmlspecialchars($end_time) ?>" onchange="document.getElementById('period_input').value='custom';" title="To Time (HH:MM)">
                        </div>

                        <div class="rep-input-group">
                            <label><i class="fa fa-sort-amount-down"></i> Sort</label>
                            <select name="sort" onchange="this.form.submit()">
                                <option value="date_desc" <?= $sort_order === 'date_desc' ? 'selected' : '' ?>>Date: Newest First</option>
                                <option value="date_asc" <?= $sort_order === 'date_asc' ? 'selected' : '' ?>>Date: Oldest First</option>
                                <option value="count_desc" <?= $sort_order === 'count_desc' ? 'selected' : '' ?>>Volume: Highest First</option>
                                <option value="count_asc" <?= $sort_order === 'count_asc' ? 'selected' : '' ?>>Volume: Lowest First</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="rep-btn-apply">
                        <i class="fa fa-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Comparative Banner Header when Comparison Mode is Active -->
        <?php if ($is_compare): ?>
            <div class="rep-compare-banner">
                <div class="rep-compare-banner-text">
                    <i class="fa fa-balance-scale" style="font-size: 18px;"></i>
                    <span>Comparing <strong><?= $primary_month_label ?></strong> vs <strong><?= $comp_month_label ?></strong></span>
                </div>
                <div class="rep-compare-legend">
                    <div class="rep-legend-item">
                        <span class="rep-legend-bullet" style="background: #059669;"></span>
                        <span style="color: #065f46;"><?= $primary_month_label ?> (Primary)</span>
                    </div>
                    <div class="rep-legend-item">
                        <span class="rep-legend-bullet" style="background: #8b5cf6;"></span>
                        <span style="color: #5b21b6;"><?= $comp_month_label ?> (Comparison)</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ==========================================
         TAB 1: SITE VISITORS & TRAFFIC REPORT
         ========================================== -->
    <?php if ($active_tab === 'visitors'): ?>
        <?php
        $v_growth_total = calc_growth($v_summary['total_visits'], $v_comp_summary['total_visits']);
        $v_growth_unique = calc_growth($v_summary['unique_visitors'], $v_comp_summary['unique_visitors']);
        $v_growth_users = calc_growth($v_summary['user_visits'], $v_comp_summary['user_visits']);
        $v_growth_guests = calc_growth($v_summary['guest_visits'], $v_comp_summary['guest_visits']);
        ?>
        <!-- KPI Cards -->
        <div class="rep-kpi-grid">
            <div class="rep-kpi-card green">
                <div class="rep-kpi-info">
                    <h3>Total Page Visits</h3>
                    <div class="rep-kpi-value">
                        <?= number_format($v_summary['total_visits']) ?>
                        <?php if ($is_compare): ?>
                            <span class="rep-variance-pill <?= $v_growth_total >= 0 ? 'up' : 'down' ?>">
                                <i class="fa <?= $v_growth_total >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= abs($v_growth_total) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="rep-kpi-sub">
                        <?php if ($is_compare): ?>
                            <span>vs <?= number_format($v_comp_summary['total_visits']) ?> in <?= $comp_month_label ?></span>
                        <?php else: ?>
                            <i class="far fa-calendar-check"></i> <?= htmlspecialchars($start_date) ?> to <?= htmlspecialchars($end_date) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-eye"></i></div>
            </div>

            <div class="rep-kpi-card blue">
                <div class="rep-kpi-info">
                    <h3>Unique Visitors (IPs)</h3>
                    <div class="rep-kpi-value">
                        <?= number_format($v_summary['unique_visitors']) ?>
                        <?php if ($is_compare): ?>
                            <span class="rep-variance-pill <?= $v_growth_unique >= 0 ? 'up' : 'down' ?>">
                                <i class="fa <?= $v_growth_unique >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= abs($v_growth_unique) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="rep-kpi-sub">
                        <?php if ($is_compare): ?>
                            <span>vs <?= number_format($v_comp_summary['unique_visitors']) ?> in <?= $comp_month_label ?></span>
                        <?php else: ?>
                            <i class="fa fa-fingerprint"></i> Distinct client devices
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-users"></i></div>
            </div>

            <div class="rep-kpi-card purple">
                <div class="rep-kpi-info">
                    <h3>Registered Member Visits</h3>
                    <div class="rep-kpi-value">
                        <?= number_format($v_summary['user_visits']) ?>
                        <?php if ($is_compare): ?>
                            <span class="rep-variance-pill <?= $v_growth_users >= 0 ? 'up' : 'down' ?>">
                                <i class="fa <?= $v_growth_users >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= abs($v_growth_users) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="rep-kpi-sub">
                        <?php if ($is_compare): ?>
                            <span>vs <?= number_format($v_comp_summary['user_visits']) ?> in <?= $comp_month_label ?></span>
                        <?php else: ?>
                            <i class="fa fa-user-check"></i> <?= $v_summary['total_visits'] > 0 ? round(($v_summary['user_visits'] / $v_summary['total_visits']) * 100, 1) : 0 ?>% of total visits
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-user-shield"></i></div>
            </div>

            <div class="rep-kpi-card amber">
                <div class="rep-kpi-info">
                    <h3>Guest / Public Visits</h3>
                    <div class="rep-kpi-value">
                        <?= number_format($v_summary['guest_visits']) ?>
                        <?php if ($is_compare): ?>
                            <span class="rep-variance-pill <?= $v_growth_guests >= 0 ? 'up' : 'down' ?>">
                                <i class="fa <?= $v_growth_guests >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= abs($v_growth_guests) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="rep-kpi-sub">
                        <?php if ($is_compare): ?>
                            <span>vs <?= number_format($v_comp_summary['guest_visits']) ?> in <?= $comp_month_label ?></span>
                        <?php else: ?>
                            <i class="fa fa-globe-americas"></i> <?= $v_summary['total_visits'] > 0 ? round(($v_summary['guest_visits'] / $v_summary['total_visits']) * 100, 1) : 0 ?>% of total visits
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-user-secret"></i></div>
            </div>
        </div>

        <!-- Colorful Charts Row 1 -->
        <div class="rep-charts-row">
            <!-- Main Area Trend Chart (or Comparative Month-over-Month Chart) -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-chart-area" style="color: #059669;"></i> 
                            <?= $is_compare ? "Month-over-Month Comparative Traffic Overlay (Day 1 to 31)" : "Traffic Volume & Audience Spline" ?>
                        </div>
                        <div class="rep-chart-subtitle">
                            <?= $is_compare ? "Comparing daily traffic: {$primary_month_label} (Solid Green) vs {$comp_month_label} (Dashed Purple)" : "Smooth gradient trend of page visits and returning members" ?>
                        </div>
                    </div>
                    <?php if (!$is_compare): ?>
                        <div style="font-size: 12.5px; color: #64748b; font-weight: 700; background: #ecfdf5; padding: 5px 12px; border-radius: 10px; border: 1px solid #a7f3d0;">
                            Peak: <strong style="color: #059669;"><?= $v_summary['peak_day'] ?> (<?= number_format($v_summary['peak_day_count']) ?> hits)</strong>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="rep-chart-canvas-wrapper">
                    <canvas id="chartVisitorsTimeline"></canvas>
                </div>
            </div>

            <!-- Audience Split Donut Chart -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-chart-pie" style="color: #7c3aed;"></i> Visitor Audience Split
                        </div>
                        <div class="rep-chart-subtitle">Members vs Guest Browsers</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper" style="min-height: 260px;">
                    <canvas id="chartAudienceDonut"></canvas>
                </div>
            </div>
        </div>

        <!-- Comparative Day-by-Day Tabular Matrix (Visible in Compare Mode) -->
        <?php if ($is_compare): ?>
            <div class="rep-table-card">
                <div class="rep-table-header">
                    <div class="rep-table-title"><i class="fa fa-balance-scale" style="color:#7c3aed;"></i> Day-by-Day Comparative Performance Matrix (Day 1 to 31)</div>
                </div>
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th>Day of Month</th>
                            <th><?= $primary_month_label ?> (Visits)</th>
                            <th><?= $comp_month_label ?> (Visits)</th>
                            <th>Variance Difference</th>
                            <th>Growth (%)</th>
                            <th>Status / Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        for ($d = 1; $d <= 31; $d++): 
                            $c1 = $v_primary_days[$d];
                            $c2 = $v_comp_days[$d];
                            $d_diff = $c1 - $c2;
                            $d_pct = calc_growth($c1, $c2);
                        ?>
                            <tr>
                                <td style="font-weight: 750; color: #1e293b;">
                                    <i class="far fa-calendar-day" style="color: #6366f1; margin-right: 6px;"></i> Day <?= $d ?>
                                </td>
                                <td><strong style="color: #059669; font-size: 14px;"><?= number_format($c1) ?></strong></td>
                                <td><strong style="color: #7c3aed; font-size: 14px;"><?= number_format($c2) ?></strong></td>
                                <td>
                                    <span style="font-weight: 750; color: <?= $d_diff >= 0 ? '#059669' : '#dc2626' ?>;">
                                        <?= $d_diff >= 0 ? '+' . number_format($d_diff) : number_format($d_diff) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rep-variance-pill <?= $d_pct >= 0 ? 'up' : 'down' ?>">
                                        <?= $d_pct >= 0 ? '+' : '' ?><?= $d_pct ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php if ($c1 > $c2): ?>
                                        <span class="rep-badge active"><i class="fa fa-arrow-up"></i> Outperformed</span>
                                    <?php elseif ($c1 < $c2): ?>
                                        <span class="rep-badge pending"><i class="fa fa-arrow-down"></i> Underperformed</span>
                                    <?php else: ?>
                                        <span class="rep-badge" style="background:#f1f5f9; color:#64748b;">Equal</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Colorful Charts Row 2: Hourly Peak Heatmap & Device Breakdown -->
        <div class="rep-charts-equal-row">
            <!-- 24-Hour Peak Heatmap Chart -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-clock" style="color: #f59e0b;"></i> 24-Hour Peak Browsing Heatmap
                        </div>
                        <div class="rep-chart-subtitle">Traffic volume distribution across all hours of day</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper">
                    <canvas id="chartHourlyTraffic"></canvas>
                </div>
            </div>

            <!-- Device Distribution Polar Area Chart -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-mobile-alt" style="color: #06b6d4;"></i> Device & Platform Share
                        </div>
                        <div class="rep-chart-subtitle">Mobile Phones vs Desktops vs Tablets</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper">
                    <canvas id="chartDeviceShare"></canvas>
                </div>
            </div>
        </div>

        <!-- Tab 1 Visitors End -->

    <!-- ==========================================
         TAB 2: USERS & REGISTRATIONS AUDIT REPORT
         ========================================== -->
    <?php elseif ($active_tab === 'users'): ?>
        <?php
        $u_growth_total = calc_growth($u_summary['total_registered'], $u_comp_summary['total_registered']);
        $u_growth_verified = calc_growth($u_summary['verified_users'], $u_comp_summary['verified_users']);
        $u_growth_unverified = calc_growth($u_summary['unverified_users'], $u_comp_summary['unverified_users']);
        $ver_pct_all_time = $u_all_time['total_all_time'] > 0 ? round(($u_all_time['verified_all_time'] / $u_all_time['total_all_time']) * 100, 1) : 0;
        $unver_pct_all_time = $u_all_time['total_all_time'] > 0 ? round(($u_all_time['unverified_all_time'] / $u_all_time['total_all_time']) * 100, 1) : 0;
        ?>
        <!-- KPI Cards -->
        <div class="rep-kpi-grid">
            <!-- 1. Total Registered Users -->
            <div class="rep-kpi-card blue">
                <div class="rep-kpi-info">
                    <h3><?= ($period_preset !== 'all' && $period_preset !== '') ? ($period_preset === 'daily' ? "Today's Signups" : ($period_preset === 'yesterday' ? "Yesterday's Signups" : ($period_preset === 'weekly' ? "Last 7 Days Signups" : ($period_preset === 'yearly' || $period_preset === 'year' ? "Yearly Signups" : "Period Signups")))) : "All Registered Users" ?></h3>
                    <div class="rep-kpi-value">
                        <?= ($period_preset !== 'all' && $period_preset !== '') ? number_format($u_summary['total_registered']) : number_format($u_all_time['total_all_time']) ?>
                        <?php if ($is_compare): ?>
                            <span class="rep-variance-pill <?= $u_growth_total >= 0 ? 'up' : 'down' ?>">
                                <i class="fa <?= $u_growth_total >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= abs($u_growth_total) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-user-plus" style="color: #3b82f6;"></i>
                        <?php if ($period_preset !== 'all' && $period_preset !== ''): ?>
                            <span>All-Time: <?= number_format($u_all_time['total_all_time']) ?> users &bull; <?= $u_summary['avg_per_day'] ?? 0 ?>/day</span>
                        <?php else: ?>
                            <span>+<?= number_format($u_summary['total_registered']) ?> in <?= htmlspecialchars($primary_month_label) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-users"></i></div>
            </div>

            <!-- 2. Verified Accounts -->
            <div class="rep-kpi-card green">
                <div class="rep-kpi-info">
                    <h3><?= ($period_preset !== 'all' && $period_preset !== '') ? "Verified in Period" : "Verified Accounts" ?></h3>
                    <div class="rep-kpi-value">
                        <?= ($period_preset !== 'all' && $period_preset !== '') ? number_format($u_summary['verified_users']) : number_format($u_all_time['verified_all_time']) ?>
                        <span class="rep-variance-pill up">
                            <?= $ver_pct_all_time ?>% Ratio
                        </span>
                    </div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-check-circle" style="color: #059669;"></i>
                        <?php if ($period_preset !== 'all' && $period_preset !== ''): ?>
                            <span>All-Time: <?= number_format($u_all_time['verified_all_time']) ?> verified accounts</span>
                        <?php else: ?>
                            <span>+<?= number_format($u_summary['verified_users']) ?> verified in <?= htmlspecialchars($primary_month_label) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-check-circle"></i></div>
            </div>

            <!-- 3. Unverified Accounts -->
            <div class="rep-kpi-card amber">
                <div class="rep-kpi-info">
                    <h3><?= ($period_preset !== 'all' && $period_preset !== '') ? "Unverified in Period" : "Unverified Accounts" ?></h3>
                    <div class="rep-kpi-value">
                        <?= ($period_preset !== 'all' && $period_preset !== '') ? number_format($u_summary['unverified_users']) : number_format($u_all_time['unverified_all_time']) ?>
                        <span class="rep-variance-pill" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a;">
                            <?= $unver_pct_all_time ?>% Pending
                        </span>
                    </div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-exclamation-triangle" style="color: #d97706;"></i>
                        <?php if ($period_preset !== 'all' && $period_preset !== ''): ?>
                            <span>All-Time: <?= number_format($u_all_time['unverified_all_time']) ?> pending verification</span>
                        <?php else: ?>
                            <span>+<?= number_format($u_summary['unverified_users']) ?> unverified in <?= htmlspecialchars($primary_month_label) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-user-clock"></i></div>
            </div>

            <!-- 4. Active & Staff Accounts -->
            <div class="rep-kpi-card purple">
                <div class="rep-kpi-info">
                    <h3>Active & Staff Members</h3>
                    <div class="rep-kpi-value"><?= number_format($u_all_time['active_7d_all_time']) ?> <span style="font-size: 14px; font-weight: 700; color: #64748b;">Active (7d)</span></div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-shield-alt" style="color: #7c3aed;"></i>
                        <span><?= number_format($u_all_time['admins_all_time']) ?> Staff • <?= $u_summary['avg_per_day'] ?> signups/day</span>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-user-shield"></i></div>
            </div>
        </div>

        <!-- Colorful Charts Row 1: Registration Velocity & Verification Donut -->
        <div class="rep-charts-row">
            <!-- Registration Growth Bar/Line Chart -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-chart-line" style="color: #3b82f6;"></i> 
                            <?= $is_compare ? "Month-over-Month Registration Comparison (Day 1 to 31)" : "User Signup Velocity & Growth Trajectory" ?>
                        </div>
                        <div class="rep-chart-subtitle">
                            <?= $is_compare ? "Side-by-side signups: {$primary_month_label} (Blue) vs {$comp_month_label} (Purple)" : "Blue bars show period signups, green bars show verified signups, purple spline shows cumulative volume" ?>
                        </div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper">
                    <canvas id="chartUsersTimeline"></canvas>
                </div>
            </div>

            <!-- Verification Status Donut -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-shield-alt" style="color: #10b981;"></i> Account Trust & Verification Distribution
                        </div>
                        <div class="rep-chart-subtitle">Verified badges vs unverified vs administrators</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper" style="min-height: 260px;">
                    <canvas id="chartVerificationDonut"></canvas>
                </div>
            </div>
        </div>

        <!-- Comparative Day-by-Day Tabular Matrix for Users (Visible in Compare Mode) -->
        <?php if ($is_compare): ?>
            <div class="rep-table-card">
                <div class="rep-table-header">
                    <div class="rep-table-title"><i class="fa fa-balance-scale" style="color:#3b82f6;"></i> Day-by-Day Registration Comparative Breakdown (Day 1 to 31)</div>
                </div>
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th>Day of Month</th>
                            <th><?= $primary_month_label ?> (Signups)</th>
                            <th><?= $comp_month_label ?> (Signups)</th>
                            <th>Variance (+/-)</th>
                            <th>Growth Rate (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        for ($d = 1; $d <= 31; $d++): 
                            $c1 = $u_primary_days[$d];
                            $c2 = $u_comp_days[$d];
                            $d_diff = $c1 - $c2;
                            $d_pct = calc_growth($c1, $c2);
                        ?>
                            <tr>
                                <td style="font-weight: 750; color: #1e293b;">Day <?= $d ?></td>
                                <td><strong style="color: #3b82f6; font-size: 14px;"><?= number_format($c1) ?></strong></td>
                                <td><strong style="color: #8b5cf6; font-size: 14px;"><?= number_format($c2) ?></strong></td>
                                <td>
                                    <span style="font-weight: 750; color: <?= $d_diff >= 0 ? '#059669' : '#dc2626' ?>;">
                                        <?= $d_diff >= 0 ? '+' . number_format($d_diff) : number_format($d_diff) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rep-variance-pill <?= $d_pct >= 0 ? 'up' : 'down' ?>">
                                        <?= $d_pct >= 0 ? '+' : '' ?><?= $d_pct ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Colorful Charts Row 2: Day of Week Distribution -->
        <div class="rep-chart-card" style="margin-bottom: 24px;">
            <div class="rep-chart-header">
                <div>
                    <div class="rep-chart-title">
                        <i class="fa fa-calendar-week" style="color: #8b5cf6;"></i> Signups by Day of Week (Weekly Heatmap)
                    </div>
                    <div class="rep-chart-subtitle">See which days of the week users register most on Enteangadi</div>
                </div>
            </div>
            <div class="rep-chart-canvas-wrapper" style="min-height: 220px;">
                <canvas id="chartDayOfWeek"></canvas>
            </div>
        </div>

        <!-- Registered Users Directory Table with Comprehensive Filters -->
        <div class="rep-table-card">
            <div class="rep-table-header" style="flex-direction: column; align-items: stretch; gap: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div class="rep-table-title">
                            <i class="fa fa-address-book" style="color:#3b82f6;"></i> Registered Users Directory
                            <span style="font-size: 13px; font-weight: 700; color: #64748b; margin-left: 6px;">
                                (<?= count($users_list) ?> displaying)
                            </span>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                            Viewing <?= $user_scope === 'all' ? 'All-Time Registered Marketplace Users' : "Signups in selected period ({$primary_month_label})" ?>
                        </div>
                    </div>

                    <!-- Search Form -->
                    <form method="GET" action="reports.php" class="rep-search-box" style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <input type="hidden" name="tab" value="users">
                        <input type="hidden" name="period" value="<?= htmlspecialchars($period_preset) ?>">
                        <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
                        <input type="hidden" name="user_scope" value="<?= htmlspecialchars($user_scope) ?>">
                        <input type="hidden" name="user_status" value="<?= htmlspecialchars($user_status) ?>">
                        <input type="hidden" name="compare" value="<?= $is_compare ? '1' : '0' ?>">
                        <input type="hidden" name="compare_month" value="<?= htmlspecialchars($compare_month) ?>">
                        <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" placeholder="Search name, phone, email, UID..." style="padding: 8px 14px; border-radius: 12px; border: 1.5px solid #cbd5e1; font-size: 13px; width: 220px;">
                        <button type="submit" class="rep-btn-apply" style="padding: 8px 14px;"><i class="fa fa-search"></i></button>
                        <?php if (!empty($search_q)): ?>
                            <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&user_scope=<?= $user_scope ?>&user_status=<?= $user_status ?>" class="rep-preset-btn" style="padding: 8px 12px; border-color: #fca5a5; color: #dc2626;"><i class="fa fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Directory Filter Toolbar: Scope Switcher & Verification Status Pills -->
                <div class="rep-directory-toolbar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                    <!-- Scope Switcher: All Time vs Period -->
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <span style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-right: 4px;">Scope:</span>
                        <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&user_scope=all&user_status=<?= $user_status ?>&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $user_scope === 'all' ? 'active' : '' ?>">
                            <i class="fa fa-database"></i> All-Time Users (<?= $u_all_time['total_all_time'] ?>)
                        </a>
                        <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&user_scope=period&user_status=<?= $user_status ?>&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $user_scope === 'period' ? 'active' : '' ?>">
                            <i class="far fa-calendar-alt"></i> Period Signups (<?= $u_summary['total_registered'] ?>)
                        </a>
                    </div>

                    <!-- Verification Status Filters -->
                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                        <span style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-right: 4px;">Status:</span>
                        <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&user_scope=<?= $user_scope ?>&user_status=all&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $user_status === 'all' ? 'active' : '' ?>">
                            All (<?= $u_counts['all'] ?>)
                        </a>
                        <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&user_scope=<?= $user_scope ?>&user_status=verified&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $user_status === 'verified' ? 'active' : '' ?>" style="<?= $user_status === 'verified' ? 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-color: #059669; color: #fff;' : 'color: #059669;' ?>">
                            <i class="fa fa-check-circle"></i> Verified (<?= $u_counts['verified'] ?>)
                        </a>
                        <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&user_scope=<?= $user_scope ?>&user_status=unverified&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $user_status === 'unverified' ? 'active' : '' ?>" style="<?= $user_status === 'unverified' ? 'background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-color: #d97706; color: #fff;' : 'color: #d97706;' ?>">
                            <i class="fa fa-user-clock"></i> Unverified (<?= $u_counts['unverified'] ?>)
                        </a>
                        <a href="reports.php?tab=users&period=<?= $period_preset ?>&month=<?= $selected_month ?>&user_scope=<?= $user_scope ?>&user_status=admin&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $user_status === 'admin' ? 'active' : '' ?>" style="<?= $user_status === 'admin' ? 'background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); border-color: #6d28d9; color: #fff;' : 'color: #7c3aed;' ?>">
                            <i class="fa fa-shield-alt"></i> Admins (<?= $u_counts['admin'] ?>)
                        </a>
                    </div>
                </div>
            </div>

            <table class="rep-table rep-users-table">
                <thead>
                    <tr>
                        <th>User Profile</th>
                        <th>Contact Phone</th>
                        <th>Email Address</th>
                        <th>Role & Access</th>
                        <th>Verification Status</th>
                        <th>Total Ads</th>
                        <th>Registered Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users_list)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 45px 20px; color: #94a3b8;">
                                <div style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"><i class="fa fa-user-slash"></i></div>
                                <div style="font-weight: 750; font-size: 15px; color: #475569;">No registered users match your selected filter criteria.</div>
                                <div style="font-size: 12.5px; margin-top: 4px;">Try switching scope to "All-Time Users" or selecting a different status filter.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $u): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php if (!empty($u['profile_picture']) && file_exists('../' . $u['profile_picture'])): ?>
                                            <img src="../<?= htmlspecialchars($u['profile_picture']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1.5px solid #e2e8f0;">
                                        <?php else: ?>
                                            <div class="user-avatar-circle" style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-gradient); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; box-shadow: 0 2px 6px rgba(5,150,105,0.3);">
                                                <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 750; color: #0f172a; font-size: 14px;"><?= htmlspecialchars($u['username']) ?></div>
                                            <div style="font-size: 11px; color: #94a3b8;">UID #<?= $u['id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 650; color: #1e293b;">
                                        <i class="fa fa-phone-alt" style="font-size: 11px; color: #059669; margin-right: 5px;"></i>
                                        <?= htmlspecialchars($u['phone_number']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="color: #475569; font-size: 13px;"><?= htmlspecialchars($u['email'] ?: 'N/A') ?></span>
                                </td>
                                <td>
                                    <?php if ($u['is_admin'] == 1 || $u['role'] === 'admin'): ?>
                                        <span class="rep-badge" style="background: #faf5ff; color: #9333ea; border: 1px solid #e9d5ff;">
                                            <i class="fa fa-shield-alt"></i> Administrator
                                        </span>
                                    <?php else: ?>
                                        <span class="rep-badge" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                                            <i class="fa fa-user"></i> Standard User
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['is_verified'] == 1): ?>
                                        <span class="rep-badge active">
                                            <i class="fa fa-check-circle"></i> Verified Account
                                        </span>
                                    <?php else: ?>
                                        <span class="rep-badge" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a;">
                                            <i class="fa fa-exclamation-circle"></i> Unverified
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #059669; font-size: 14.5px;"><?= $u['total_ads'] ?></strong>
                                    <span style="font-size: 11px; color: #94a3b8;">ads</span>
                                </td>
                                <td style="font-size: 12.5px; color: #64748b; font-weight: 600;">
                                    <?= date('M d, Y h:i A', strtotime($u['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==========================================
         TAB 3: ADS POSTED & MARKETPLACE REPORT
         ========================================== -->
    <?php elseif ($active_tab === 'ads'): ?>
        <?php
        $a_growth_total = calc_growth($a_summary['total_ads'], $a_comp_summary['total_ads']);
        $a_growth_verified = calc_growth($a_summary['verified_ads'], $a_comp_summary['verified_ads']);
        $a_growth_unverified = calc_growth($a_summary['unverified_ads'], $a_comp_summary['unverified_ads']);
        $a_growth_active = calc_growth($a_summary['active_ads'], $a_comp_summary['active_ads']);
        $a_growth_sold = calc_growth($a_summary['sold_ads'], $a_comp_summary['sold_ads']);
        $a_growth_val = calc_growth($a_summary['total_valuation'], $a_comp_summary['total_valuation']);
        $ad_ver_pct_all_time = $a_all_time['total_all_time'] > 0 ? round(($a_all_time['verified_all_time'] / $a_all_time['total_all_time']) * 100, 1) : 0;
        $ad_unver_pct_all_time = $a_all_time['total_all_time'] > 0 ? round(($a_all_time['unverified_all_time'] / $a_all_time['total_all_time']) * 100, 1) : 0;
        ?>
        <!-- KPI Cards -->
        <div class="rep-kpi-grid">
            <!-- 1. Total Ads Listed -->
            <div class="rep-kpi-card purple">
                <div class="rep-kpi-info">
                    <h3><?= ($period_preset !== 'all' && $period_preset !== '') ? ($period_preset === 'daily' ? "Today's New Ads" : ($period_preset === 'yesterday' ? "Yesterday's New Ads" : ($period_preset === 'weekly' ? "Last 7 Days Ads" : ($period_preset === 'yearly' || $period_preset === 'year' ? "Yearly Ads" : "Period New Ads")))) : "Total Listed Ads" ?></h3>
                    <div class="rep-kpi-value">
                        <?= ($period_preset !== 'all' && $period_preset !== '') ? number_format($a_summary['total_ads']) : number_format($a_all_time['total_all_time']) ?>
                        <?php if ($is_compare): ?>
                            <span class="rep-variance-pill <?= $a_growth_total >= 0 ? 'up' : 'down' ?>">
                                <i class="fa <?= $a_growth_total >= 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= abs($a_growth_total) ?>%
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-tags" style="color: #7c3aed;"></i>
                        <?php if ($period_preset !== 'all' && $period_preset !== ''): ?>
                            <span>All-Time: <?= number_format($a_all_time['total_all_time']) ?> ads &bull; <?= $a_summary['avg_per_day'] ?? 0 ?>/day</span>
                        <?php else: ?>
                            <span>+<?= number_format($a_summary['total_ads']) ?> posted in <?= htmlspecialchars($primary_month_label) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-tags"></i></div>
            </div>

            <!-- 2. Verified Ads -->
            <div class="rep-kpi-card green">
                <div class="rep-kpi-info">
                    <h3><?= ($period_preset !== 'all' && $period_preset !== '') ? "Verified in Period" : "Verified Ads" ?></h3>
                    <div class="rep-kpi-value">
                        <?= ($period_preset !== 'all' && $period_preset !== '') ? number_format($a_summary['verified_ads']) : number_format($a_all_time['verified_all_time']) ?>
                        <span class="rep-variance-pill up">
                            <?= $ad_ver_pct_all_time ?>% Ratio
                        </span>
                    </div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-check-circle" style="color: #059669;"></i>
                        <?php if ($period_preset !== 'all' && $period_preset !== ''): ?>
                            <span>All-Time: <?= number_format($a_all_time['verified_all_time']) ?> verified ads</span>
                        <?php else: ?>
                            <span>+<?= number_format($a_summary['verified_ads']) ?> verified in <?= htmlspecialchars($primary_month_label) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-certificate"></i></div>
            </div>

            <!-- 3. Unverified Ads -->
            <div class="rep-kpi-card amber">
                <div class="rep-kpi-info">
                    <h3><?= ($period_preset !== 'all' && $period_preset !== '') ? "Unverified in Period" : "Unverified Ads" ?></h3>
                    <div class="rep-kpi-value">
                        <?= ($period_preset !== 'all' && $period_preset !== '') ? number_format($a_summary['unverified_ads']) : number_format($a_all_time['unverified_all_time']) ?>
                        <span class="rep-variance-pill" style="background:#fffbeb; color:#b45309; border:1px solid #fde68a;">
                            <?= $ad_unver_pct_all_time ?>% Standard
                        </span>
                    </div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-exclamation-circle" style="color: #d97706;"></i>
                        <?php if ($period_preset !== 'all' && $period_preset !== ''): ?>
                            <span>All-Time: <?= number_format($a_all_time['unverified_all_time']) ?> standard ads</span>
                        <?php else: ?>
                            <span>+<?= number_format($a_summary['unverified_ads']) ?> unverified in <?= htmlspecialchars($primary_month_label) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-hourglass-half"></i></div>
            </div>

            <!-- 4. Active Inventory & Valuation -->
            <div class="rep-kpi-card blue">
                <div class="rep-kpi-info">
                    <h3>Live Inventory & Valuation</h3>
                    <div class="rep-kpi-value">
                        <?= number_format($a_all_time['active_all_time']) ?> <span style="font-size: 14px; font-weight: 700; color: #64748b;">Live</span>
                    </div>
                    <div class="rep-kpi-sub">
                        <i class="fa fa-coins" style="color: #2563eb;"></i>
                        <span>₹<?= number_format($a_all_time['total_valuation_all_time']) ?> Total (₹<?= number_format($a_summary['total_valuation']) ?> in period)</span>
                    </div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-store"></i></div>
            </div>
        </div>

        <!-- Colorful Charts Row 1: Ads Timeline & Verified vs Unverified Donut -->
        <div class="rep-charts-row">
            <!-- Ads Posting Dynamics Timeline Chart -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-chart-area" style="color: #7c3aed;"></i> 
                            <?= $is_compare ? "Month-over-Month Ad Posting Dynamics (Day 1 to 31)" : "Ad Volume & Quality Dynamics (" . ucfirst($group_by) . " Trends)" ?>
                        </div>
                        <div class="rep-chart-subtitle">
                            <?= $is_compare ? "Comparing daily ads: {$primary_month_label} (Purple) vs {$comp_month_label} (Amber)" : "Purple line shows total volume, green line highlights verified ads, and blue line tracks concluded transactions" ?>
                        </div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper">
                    <canvas id="chartAdsTimeline"></canvas>
                </div>
            </div>

            <!-- Verified vs Unverified Ads Quality Distribution Donut -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-shield-alt" style="color: #10b981;"></i> Ad Trust & Verification Status
                        </div>
                        <div class="rep-chart-subtitle">Verified badge ads vs unverified listings (<?= number_format($a_all_time['total_all_time']) ?> total)</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper" style="min-height: 260px;">
                    <canvas id="chartAdsVerificationDonut"></canvas>
                </div>
            </div>
        </div>

        <!-- Comparative Day-by-Day Tabular Matrix for Ads (Visible in Compare Mode) -->
        <?php if ($is_compare): ?>
            <div class="rep-table-card">
                <div class="rep-table-header">
                    <div class="rep-table-title"><i class="fa fa-balance-scale" style="color:#7c3aed;"></i> Day-by-Day Ad Postings Comparative Breakdown (Day 1 to 31)</div>
                </div>
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th>Day of Month</th>
                            <th><?= $primary_month_label ?> (Ads Posted)</th>
                            <th><?= $comp_month_label ?> (Ads Posted)</th>
                            <th>Variance (+/-)</th>
                            <th>Growth Rate (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        for ($d = 1; $d <= 31; $d++): 
                            $c1 = $a_primary_days[$d];
                            $c2 = $a_comp_days[$d];
                            $d_diff = $c1 - $c2;
                            $d_pct = calc_growth($c1, $c2);
                        ?>
                            <tr>
                                <td style="font-weight: 750; color: #1e293b;">Day <?= $d ?></td>
                                <td><strong style="color: #8b5cf6; font-size: 14px;"><?= number_format($c1) ?></strong></td>
                                <td><strong style="color: #f59e0b; font-size: 14px;"><?= number_format($c2) ?></strong></td>
                                <td>
                                    <span style="font-weight: 750; color: <?= $d_diff >= 0 ? '#059669' : '#dc2626' ?>;">
                                        <?= $d_diff >= 0 ? '+' . number_format($d_diff) : number_format($d_diff) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="rep-variance-pill <?= $d_pct >= 0 ? 'up' : 'down' ?>">
                                        <?= $d_pct >= 0 ? '+' : '' ?><?= $d_pct ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Colorful Charts Row 2: Categories Market Share & Price Bracket Distribution -->
        <div class="rep-charts-equal-row">
            <!-- Categories Colorful Donut -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-chart-pie" style="color: #ec4899;"></i> Category Market Share
                        </div>
                        <div class="rep-chart-subtitle">Distribution of listings by product category</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper" style="min-height: 250px;">
                    <canvas id="chartCategoriesDonut"></canvas>
                </div>
            </div>

            <!-- Price Bracket Distribution Bar Chart -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-layer-group" style="color: #059669;"></i> Price Range Distribution
                        </div>
                        <div class="rep-chart-subtitle">Spread of listing prices across economic tiers</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper" style="min-height: 250px;">
                    <canvas id="chartPriceTiers"></canvas>
                </div>
            </div>
        </div>

        <!-- Category Valuation & Listing Intent Row -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
            <!-- Category Valuation Table with Progress Bars -->
            <div class="rep-table-card" style="margin-bottom: 0;">
                <div class="rep-table-header">
                    <div class="rep-table-title"><i class="fa fa-chart-bar" style="color:#ec4899;"></i> Category Inventory Breakdown & Valuation (<?= $primary_month_label ?>)</div>
                </div>
                <table class="rep-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Total Listings</th>
                            <th>Market Share</th>
                            <th>Inventory Valuation (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $cat_palette = ['#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#06b6d4', '#f97316', '#64748b'];
                        foreach ($category_breakdown as $c_idx => $c_row): 
                            $c_pct = $a_summary['total_ads'] > 0 ? round(($c_row['ad_count'] / $a_summary['total_ads']) * 100, 1) : 0;
                            $c_col = $cat_palette[$c_idx % count($cat_palette)];
                        ?>
                            <tr>
                                <td style="font-weight: 750; color: #1e293b;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?= $c_col ?>; margin-right:8px;"></span>
                                    <?= htmlspecialchars($c_row['category_name']) ?>
                                </td>
                                <td><strong style="color: #0f172a;"><?= number_format($c_row['ad_count']) ?></strong></td>
                                <td style="width: 200px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="rep-meter-bar-bg" style="flex: 1;">
                                            <div class="rep-meter-bar-fill" style="width: <?= $c_pct ?>%; background: <?= $c_col ?>;"></div>
                                        </div>
                                        <span style="font-weight: 750; font-size: 12px; color: #64748b;"><?= $c_pct ?>%</span>
                                    </div>
                                </td>
                                <td><strong style="color: #059669; font-size: 14px;">₹<?= number_format($c_row['cat_value']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Intent Breakdown (Sell vs Buy vs Rent) -->
            <div class="rep-chart-card">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-bullhorn" style="color: #f59e0b;"></i> Listing Intent
                        </div>
                        <div class="rep-chart-subtitle">Sell vs Buy vs Rent offers</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper" style="min-height: 240px;">
                    <canvas id="chartTypeIntent"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Ads Inventory Table with Comprehensive Filters -->
        <div class="rep-table-card">
            <div class="rep-table-header" style="flex-direction: column; align-items: stretch; gap: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <div class="rep-table-title">
                            <i class="fa fa-list-check" style="color:#7c3aed;"></i> Ads Inventory Audit Directory
                            <span style="font-size: 13px; font-weight: 700; color: #64748b; margin-left: 6px;">
                                (<?= count($ads_list) ?> displaying)
                            </span>
                        </div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                            Viewing <?= $ad_scope === 'all' ? 'All-Time Marketplace Inventory' : "Ads posted in selected period ({$primary_month_label})" ?>
                        </div>
                    </div>

                    <!-- Search Form -->
                    <form method="GET" action="reports.php" class="rep-search-box" style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <input type="hidden" name="tab" value="ads">
                        <input type="hidden" name="period" value="<?= htmlspecialchars($period_preset) ?>">
                        <input type="hidden" name="month" value="<?= htmlspecialchars($selected_month) ?>">
                        <input type="hidden" name="ad_scope" value="<?= htmlspecialchars($ad_scope) ?>">
                        <input type="hidden" name="ad_status" value="<?= htmlspecialchars($ad_status) ?>">
                        <input type="hidden" name="compare" value="<?= $is_compare ? '1' : '0' ?>">
                        <input type="hidden" name="compare_month" value="<?= htmlspecialchars($compare_month) ?>">
                        <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" placeholder="Search title, ad ID, seller..." style="padding: 8px 14px; border-radius: 12px; border: 1.5px solid #cbd5e1; font-size: 13px; width: 220px;">
                        <button type="submit" class="rep-btn-apply" style="padding: 8px 14px;"><i class="fa fa-search"></i></button>
                        <?php if (!empty($search_q)): ?>
                            <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=<?= $ad_scope ?>&ad_status=<?= $ad_status ?>" class="rep-preset-btn" style="padding: 8px 12px; border-color: #fca5a5; color: #dc2626;"><i class="fa fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Directory Filter Toolbar: Scope Switcher & Ad Verification Status Pills -->
                <div class="rep-directory-toolbar" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-top: 12px; border-top: 1px solid #f1f5f9;">
                    <!-- Scope Switcher: All Time vs Period -->
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <span style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-right: 4px;">Scope:</span>
                        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=all&ad_status=<?= $ad_status ?>&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $ad_scope === 'all' ? 'active' : '' ?>">
                            <i class="fa fa-database"></i> All-Time Ads (<?= $a_all_time['total_all_time'] ?>)
                        </a>
                        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=period&ad_status=<?= $ad_status ?>&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $ad_scope === 'period' ? 'active' : '' ?>">
                            <i class="far fa-calendar-alt"></i> Period Ads (<?= $a_summary['total_ads'] ?>)
                        </a>
                    </div>

                    <!-- Verification & Status Filters -->
                    <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                        <span style="font-size: 11.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-right: 4px;">Status:</span>
                        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=<?= $ad_scope ?>&ad_status=all&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $ad_status === 'all' ? 'active' : '' ?>">
                            All (<?= $a_counts['all'] ?>)
                        </a>
                        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=<?= $ad_scope ?>&ad_status=verified&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $ad_status === 'verified' ? 'active' : '' ?>" style="<?= $ad_status === 'verified' ? 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-color: #059669; color: #fff;' : 'color: #059669;' ?>">
                            <i class="fa fa-check-circle"></i> Verified Ads (<?= $a_counts['verified'] ?>)
                        </a>
                        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=<?= $ad_scope ?>&ad_status=unverified&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $ad_status === 'unverified' ? 'active' : '' ?>" style="<?= $ad_status === 'unverified' ? 'background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-color: #d97706; color: #fff;' : 'color: #d97706;' ?>">
                            <i class="fa fa-exclamation-circle"></i> Unverified (<?= $a_counts['unverified'] ?>)
                        </a>
                        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=<?= $ad_scope ?>&ad_status=active&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $ad_status === 'active' ? 'active' : '' ?>" style="<?= $ad_status === 'active' ? 'background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-color: #1d4ed8; color: #fff;' : 'color: #2563eb;' ?>">
                            <i class="fa fa-store"></i> Active (<?= $a_counts['active'] ?>)
                        </a>
                        <a href="reports.php?tab=ads&period=<?= $period_preset ?>&month=<?= $selected_month ?>&ad_scope=<?= $ad_scope ?>&ad_status=sold&q=<?= urlencode($search_q) ?>" class="rep-preset-btn <?= $ad_status === 'sold' ? 'active' : '' ?>" style="<?= $ad_status === 'sold' ? 'background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); border-color: #6d28d9; color: #fff;' : 'color: #7c3aed;' ?>">
                            <i class="fa fa-handshake"></i> Sold (<?= $a_counts['sold'] ?>)
                        </a>
                    </div>
                </div>
            </div>

            <table class="rep-table rep-ads-table">
                <thead>
                    <tr>
                        <th>Item / Ad Title</th>
                        <th>Category</th>
                        <th>Seller Name</th>
                        <th>Price (₹)</th>
                        <th>Verification Status</th>
                        <th>Listing Status</th>
                        <th>Views</th>
                        <th>Date Posted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ads_list)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 45px 20px; color: #94a3b8;">
                                <div style="font-size: 32px; margin-bottom: 8px; color: #cbd5e1;"><i class="fa fa-folder-open"></i></div>
                                <div style="font-weight: 750; font-size: 15px; color: #475569;">No ads match your filter selection.</div>
                                <div style="font-size: 12.5px; margin-top: 4px;">Try switching scope to "All-Time Ads" or clearing the active search filter.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ads_list as $ad): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php if (!empty($ad['thumbnail'])): ?>
                                            <img src="../<?= htmlspecialchars($ad['thumbnail']) ?>" style="width: 44px; height: 44px; border-radius: 10px; object-fit: cover; border: 1px solid #e2e8f0;">
                                        <?php else: ?>
                                            <div class="user-avatar-circle" style="width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                                <i class="fa fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="../product.php?id=<?= $ad['id'] ?>" target="_blank" style="font-weight: 750; color: #0f172a; text-decoration: none;">
                                                <?= htmlspecialchars($ad['title']) ?>
                                            </a>
                                            <div style="font-size: 11px; color: #94a3b8;">
                                                #<?= htmlspecialchars($ad['unique_id']) ?> &bull; <?= htmlspecialchars($ad['location_name'] ?: 'Kerala') ?> &bull; <span style="text-transform: uppercase; font-weight: 700;"><?= htmlspecialchars($ad['type']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span style="font-weight: 650; color: #475569;"><?= htmlspecialchars($ad['category_name']) ?></span></td>
                                <td>
                                    <div style="font-weight: 650; color: #1e293b;"><?= htmlspecialchars($ad['seller_name']) ?></div>
                                    <div style="font-size: 11px; color: #94a3b8;"><i class="fa fa-phone-alt" style="font-size: 10px; color: #059669;"></i> <?= htmlspecialchars($ad['seller_phone'] ?: 'N/A') ?></div>
                                </td>
                                <td><strong style="color: #059669; font-size: 14.5px;">₹<?= number_format($ad['price']) ?></strong></td>
                                <td>
                                    <?php if ($ad['is_verified'] == 1): ?>
                                        <span class="rep-badge active">
                                            <i class="fa fa-check-circle"></i> Verified Ad
                                        </span>
                                    <?php else: ?>
                                        <span class="rep-badge" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a;">
                                            <i class="fa fa-exclamation-circle"></i> Unverified
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $st = strtolower($ad['status']);
                                    $st_class = ($st === 'active') ? 'active' : (($st === 'sold') ? 'sold' : (($st === 'expired') ? 'expired' : 'pending'));
                                    ?>
                                    <span class="rep-badge <?= $st_class ?>"><?= ucfirst($ad['status']) ?></span>
                                </td>
                                <td><i class="fa fa-eye" style="font-size: 11px; color: #94a3b8; margin-right: 2px;"></i> <?= number_format($ad['views']) ?></td>
                                <td style="font-size: 12.5px; color: #64748b; font-weight: 600;"><?= date('M d, Y', strtotime($ad['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <!-- ==========================================
         TAB 4: FLAGGED & REPORTED ADS (MODERATION)
         ========================================== -->
    <?php elseif ($active_tab === 'moderation'): ?>
        <!-- Moderation KPI Grid -->
        <div class="rep-kpi-grid">
            <div class="rep-kpi-card pink">
                <div class="rep-kpi-info">
                    <h3>Total Flagged Reports</h3>
                    <div class="rep-kpi-value"><?= $mod_stats['total'] ?></div>
                    <div class="rep-kpi-sub">All-time submitted notices</div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-shield-alt"></i></div>
            </div>

            <div class="rep-kpi-card amber">
                <div class="rep-kpi-info">
                    <h3>Pending Review</h3>
                    <div class="rep-kpi-value"><?= $mod_stats['pending'] ?></div>
                    <div class="rep-kpi-sub">Needs admin investigation</div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-exclamation-triangle"></i></div>
            </div>

            <div class="rep-kpi-card green">
                <div class="rep-kpi-info">
                    <h3>Resolved Cases</h3>
                    <div class="rep-kpi-value"><?= $mod_stats['resolved'] ?></div>
                    <div class="rep-kpi-sub">Successfully addressed</div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-check-circle"></i></div>
            </div>

            <div class="rep-kpi-card blue">
                <div class="rep-kpi-info">
                    <h3>Resolution Rate</h3>
                    <div class="rep-kpi-value"><?= $mod_stats['total'] > 0 ? round(($mod_stats['resolved'] / $mod_stats['total']) * 100, 1) : 100 ?>%</div>
                    <div class="rep-kpi-sub">Marketplace hygiene score</div>
                </div>
                <div class="rep-kpi-icon-wrapper"><i class="fa fa-award"></i></div>
            </div>
        </div>

        <?php if (!empty($report_reasons)): ?>
            <div class="rep-chart-card" style="margin-bottom: 24px;">
                <div class="rep-chart-header">
                    <div>
                        <div class="rep-chart-title">
                            <i class="fa fa-chart-pie" style="color: #ef4444;"></i> Flagged Reasons Breakdown
                        </div>
                        <div class="rep-chart-subtitle">Primary reasons community members reported listings</div>
                    </div>
                </div>
                <div class="rep-chart-canvas-wrapper" style="min-height: 220px;">
                    <canvas id="chartReportReasons"></canvas>
                </div>
            </div>
        <?php endif; ?>

        <div class="rep-table-card">
            <div class="rep-table-header">
                <div class="rep-table-title"><i class="fa fa-shield-alt" style="color:#ef4444;"></i> Flagged Advertisements & Safety Reports</div>
            </div>
            <table class="rep-table">
                <thead>
                    <tr>
                        <th>Product / Ad</th>
                        <th>Reported By</th>
                        <th>Report Reason</th>
                        <th>Owner Info & Direct Contact</th>
                        <th>Status</th>
                        <th>Moderation Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 45px; color: #94a3b8;">
                                <i class="fa fa-check-circle" style="font-size: 40px; color: #10b981; display: block; margin-bottom: 10px;"></i>
                                <span style="font-weight: 700; color: #0f172a; font-size: 15px;">No active flagged ads or safety reports.</span>
                                <p style="margin: 4px 0 0; font-size: 13px;">The Enteangadi marketplace is clean and trustworthy!</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr style="background: <?= $report['status'] === 'pending' ? '#fffdf7' : 'transparent' ?>;">
                                <td>
                                    <a href="../product.php?id=<?= $report['product_id'] ?>" target="_blank" style="color: #059669; font-weight: 750; text-decoration: none;">
                                        <?= htmlspecialchars($report['product_title']) ?>
                                    </a>
                                    <?php if ($report['product_status'] === 'deleted'): ?>
                                        <span style="color: #ef4444; font-size: 11px; display: block; font-weight: 800;">(Deleted Listing)</span>
                                    <?php endif; ?>
                                </td>
                                <td><span style="font-weight: 650;"><?= htmlspecialchars($report['reporter_name']) ?></span></td>
                                <td style="max-width: 220px; font-size: 13px; color: #475569;"><?= htmlspecialchars($report['reason']) ?></td>
                                <td>
                                    <div style="font-weight: 750; margin-bottom: 4px;"><?= htmlspecialchars($report['owner_name']) ?></div>
                                    <div style="font-size: 11.5px; color: #64748b;"><i class="fa fa-phone-alt"></i> <?= htmlspecialchars($report['owner_phone']) ?></div>
                                </td>
                                <td>
                                    <span class="rep-badge <?= $report['status'] === 'pending' ? 'pending' : 'active' ?>">
                                        <?= ucfirst($report['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <div style="display: flex; gap: 6px; flex-direction: column;">
                                            <?php if ($report['product_status'] !== 'deleted'): ?>
                                                <form method="POST" onsubmit="return confirm('Permanently remove this ad?');">
                                                    <input type="hidden" name="delete_product_id" value="<?= $report['product_id'] ?>">
                                                    <button type="submit" style="background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight: 750; cursor: pointer; width: 100%;">
                                                        <i class="fa fa-trash"></i> Delete Ad
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST">
                                                <input type="hidden" name="resolve_report_id" value="<?= $report['id'] ?>">
                                                <button type="submit" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight: 750; cursor: pointer; width: 100%;">
                                                        <i class="fa fa-check"></i> Mark Resolved
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #94a3b8; font-weight: 650;"><i class="fa fa-check-double"></i> Resolved</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Executive Print Endorsement & Sign-off Footer (Rendered only on Print / PDF export) -->
    <div class="rep-print-footer">
        <div style="font-size: 11px; color: #475569; line-height: 1.5;">
            <strong>Confidentiality Notice & Data Certification:</strong> This document contains proprietary operational telemetry and business performance metrics extracted directly from the Enteangadi Marketplace database. Any unauthorized distribution, reproduction, or disclosure is strictly prohibited.
        </div>
        <div class="rep-print-signatures">
            <div>
                <div class="rep-sig-line">Prepared & Certified By: System Administrator</div>
                <div style="font-size: 9.5px; color: #94a3b8; margin-top: 2px;">Enteangadi Central Analytics System</div>
            </div>
            <div>
                <div class="rep-sig-line">Audited & Verified By: Operations Directorate</div>
                <div style="font-size: 9.5px; color: #94a3b8; margin-top: 2px;">Executive Management Review</div>
            </div>
        </div>
        <div class="rep-print-footnote">
            Enteangadi Marketplace Platform &bull; Automated Analytics Engine &bull; Document Reference: EA-REP-<?= strtoupper($active_tab) ?>-<?= date('Ymd-His') ?>
        </div>
    </div>
</div>

<!-- ==========================================
     CHART.JS INITIALIZATION SCRIPTS
     ========================================== -->
<script>
function printReport(orientation) {
    orientation = orientation || 'portrait';
    if (orientation === 'landscape') {
        document.body.classList.add('print-landscape');
    } else {
        document.body.classList.remove('print-landscape');
    }
    // Ensure all charts are completely rendered and crisp before print dialog opens
    if (typeof Chart !== 'undefined' && Chart.instances) {
        Object.values(Chart.instances).forEach(function(instance) {
            try { instance.render(); } catch(e) {}
        });
    }
    setTimeout(function() {
        window.print();
    }, 200);
}

function initEnteangadiCharts() {
    if (typeof Chart === 'undefined') {
        setTimeout(initEnteangadiCharts, 80);
        return;
    }

    try {
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        Chart.defaults.color = '#64748b';
    } catch (e) {}

    function createVerticalGradient(ctx, colorTop, colorBottom) {
        try {
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, colorTop);
            gradient.addColorStop(1, colorBottom);
            return gradient;
        } catch (e) {
            return colorTop;
        }
    }

    const isCompare = <?= $is_compare ? 'true' : 'false' ?>;

    // ========================================================
    // 1. VISITORS TAB CHARTS
    // ========================================================
    <?php if ($active_tab === 'visitors'): ?>
        const canvasVisitors = document.getElementById('chartVisitorsTimeline');
        if (canvasVisitors) {
            try {
                const existing = Chart.getChart(canvasVisitors);
                if (existing) existing.destroy();
                const ctx = canvasVisitors.getContext('2d');
                const bgGradientGreen = createVerticalGradient(ctx, 'rgba(16, 185, 129, 0.45)', 'rgba(16, 185, 129, 0.01)');
                const bgGradientIndigo = createVerticalGradient(ctx, 'rgba(99, 102, 241, 0.35)', 'rgba(99, 102, 241, 0.01)');

                let datasetsConfig = [];
                let chartLabels = [];

                if (isCompare) {
                    chartLabels = <?= $comp_day_labels ?>;
                    datasetsConfig = [
                        {
                            label: <?= json_encode($primary_month_label . ' (Primary)') ?>,
                            data: <?= $v_primary_days_json ?>,
                            borderColor: '#059669',
                            backgroundColor: bgGradientGreen,
                            fill: true,
                            tension: 0.38,
                            borderWidth: 3.5,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#059669'
                        },
                        {
                            label: <?= json_encode($comp_month_label . ' (Comparison)') ?>,
                            data: <?= $v_comp_days_json ?>,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.08)',
                            fill: true,
                            borderDash: [5, 5],
                            tension: 0.38,
                            borderWidth: 3,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#8b5cf6'
                        }
                    ];
                } else {
                    chartLabels = <?= $chart_visitors_labels ?>;
                    datasetsConfig = [
                        {
                            label: 'Total Visits',
                            data: <?= $chart_visitors_total ?>,
                            borderColor: '#059669',
                            backgroundColor: bgGradientGreen,
                            fill: true,
                            tension: 0.38,
                            borderWidth: 3.5,
                            pointRadius: 4,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#059669'
                        },
                        {
                            label: 'Registered Members',
                            data: <?= $chart_visitors_users ?>,
                            borderColor: '#6366f1',
                            backgroundColor: bgGradientIndigo,
                            fill: true,
                            tension: 0.38,
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#6366f1'
                        },
                        {
                            label: 'Guest / Public',
                            data: <?= $chart_visitors_guests ?>,
                            borderColor: '#f59e0b',
                            borderDash: [5, 5],
                            tension: 0.38,
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#f59e0b'
                        }
                    ];
                }

                new Chart(canvasVisitors, {
                    type: 'line',
                    data: {
                        labels: chartLabels,
                        datasets: datasetsConfig
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'top', labels: { boxWidth: 14, font: { weight: 'bold', size: 12 } } },
                            tooltip: { padding: 14, cornerRadius: 12, titleFont: { size: 13, weight: 'bold' } }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            } catch (err) {
                console.error('Visitors timeline chart error:', err);
            }
        }

        const ctxAudience = document.getElementById('chartAudienceDonut');
        if (ctxAudience) {
            try {
                const existing = Chart.getChart(ctxAudience);
                if (existing) existing.destroy();
                new Chart(ctxAudience, {
                    type: 'doughnut',
                    data: {
                        labels: ['Registered Members', 'Public Guests'],
                        datasets: [{
                            data: [<?= (int)$v_summary['user_visits'] ?>, <?= (int)$v_summary['guest_visits'] ?>],
                            backgroundColor: ['#6366f1', '#f59e0b'],
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 14, font: { weight: 'bold', size: 12 } } }
                        },
                        cutout: '68%'
                    }
                });
            } catch (err) {
                console.error('Audience chart error:', err);
            }
        }

        const canvasHourly = document.getElementById('chartHourlyTraffic');
        if (canvasHourly) {
            try {
                const existing = Chart.getChart(canvasHourly);
                if (existing) existing.destroy();
                const ctxH = canvasHourly.getContext('2d');
                const hGrad = createVerticalGradient(ctxH, '#f59e0b', '#d97706');

                new Chart(canvasHourly, {
                    type: 'bar',
                    data: {
                        labels: <?= $hourly_labels ?>,
                        datasets: [{
                            label: 'Visits by Hour',
                            data: <?= $hourly_counts ?>,
                            backgroundColor: hGrad,
                            borderRadius: 6,
                            barPercentage: 0.7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { padding: 12, cornerRadius: 10 }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            } catch (err) {
                console.error('Hourly traffic chart error:', err);
            }
        }

        const ctxDevice = document.getElementById('chartDeviceShare');
        if (ctxDevice) {
            try {
                const existing = Chart.getChart(ctxDevice);
                if (existing) existing.destroy();
                new Chart(ctxDevice, {
                    type: 'doughnut',
                    data: {
                        labels: <?= $device_labels ?>,
                        datasets: [{
                            data: <?= $device_counts ?>,
                            backgroundColor: ['#06b6d4', '#3b82f6', '#8b5cf6'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { weight: 'bold' } } }
                        }
                    }
                });
            } catch (err) {
                console.error('Device share chart error:', err);
            }
        }
    <?php endif; ?>

    // ========================================================
    // 2. USERS TAB CHARTS
    // ========================================================
    <?php if ($active_tab === 'users'): ?>
        const canvasUsers = document.getElementById('chartUsersTimeline');
        if (canvasUsers) {
            try {
                const existing = Chart.getChart(canvasUsers);
                if (existing) existing.destroy();
                const ctxU = canvasUsers.getContext('2d');
                const uGradBlue = createVerticalGradient(ctxU, 'rgba(59, 130, 246, 0.9)', 'rgba(37, 99, 235, 0.7)');

                if (isCompare) {
                    new Chart(canvasUsers, {
                        type: 'bar',
                        data: {
                            labels: <?= $comp_day_labels ?>,
                            datasets: [
                                {
                                    label: <?= json_encode($primary_month_label . ' (Signups)') ?>,
                                    data: <?= $u_primary_days_json ?>,
                                    backgroundColor: '#3b82f6',
                                    borderRadius: 8,
                                    barPercentage: 0.6
                                },
                                {
                                    label: <?= json_encode($comp_month_label . ' (Signups)') ?>,
                                    data: <?= $u_comp_days_json ?>,
                                    backgroundColor: '#8b5cf6',
                                    borderRadius: 8,
                                    barPercentage: 0.6
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', labels: { boxWidth: 14, font: { weight: 'bold' } } }
                            },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                } else {
                    new Chart(canvasUsers, {
                        data: {
                            labels: <?= $chart_users_labels ?>,
                            datasets: [
                                {
                                    type: 'bar',
                                    label: 'New Signups',
                                    data: <?= $chart_users_total ?>,
                                    backgroundColor: uGradBlue,
                                    borderRadius: 8,
                                    barPercentage: 0.55,
                                    yAxisID: 'y'
                                },
                                {
                                    type: 'bar',
                                    label: 'Verified Accounts',
                                    data: <?= $chart_users_verified ?>,
                                    backgroundColor: '#10b981',
                                    borderRadius: 8,
                                    barPercentage: 0.55,
                                    yAxisID: 'y'
                                },
                                {
                                    type: 'line',
                                    label: 'Cumulative Total Users',
                                    data: <?= $chart_users_cum_json ?>,
                                    borderColor: '#8b5cf6',
                                    backgroundColor: 'transparent',
                                    tension: 0.35,
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#8b5cf6',
                                    yAxisID: 'y1'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', labels: { boxWidth: 14, font: { weight: 'bold' } } }
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    beginAtZero: true,
                                    grid: { color: '#f1f5f9' },
                                    title: { display: true, text: 'Period Signups' }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    beginAtZero: true,
                                    grid: { drawOnChartArea: false },
                                    title: { display: true, text: 'Cumulative Total' }
                                },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            } catch (err) {
                console.error('Users timeline chart error:', err);
            }
        }

        const ctxVerification = document.getElementById('chartVerificationDonut');
        if (ctxVerification) {
            try {
                const existing = Chart.getChart(ctxVerification);
                if (existing) existing.destroy();
                new Chart(ctxVerification, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            'Verified Accounts (<?= (int)$u_all_time['verified_all_time'] ?>)',
                            'Unverified Accounts (<?= (int)$u_all_time['unverified_all_time'] ?>)',
                            'Administrators (<?= (int)$u_all_time['admins_all_time'] ?>)'
                        ],
                        datasets: [{
                            data: [
                                <?= (int)$u_all_time['verified_all_time'] ?>,
                                <?= (int)$u_all_time['unverified_all_time'] ?>,
                                <?= (int)$u_all_time['admins_all_time'] ?>
                            ],
                            backgroundColor: ['#10b981', '#f59e0b', '#8b5cf6'],
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 14, font: { weight: 'bold', size: 12 } } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = <?= max(1, (int)$u_all_time['total_all_time']) ?>;
                                        const val = context.parsed;
                                        const pct = ((val / total) * 100).toFixed(1);
                                        return ` ${context.label}: ${val} users (${pct}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '66%'
                    }
                });
            } catch (err) {
                console.error('Verification donut chart error:', err);
            }
        }

        const canvasDOW = document.getElementById('chartDayOfWeek');
        if (canvasDOW) {
            try {
                const existing = Chart.getChart(canvasDOW);
                if (existing) existing.destroy();
                new Chart(canvasDOW, {
                    type: 'bar',
                    data: {
                        labels: <?= $dow_labels ?>,
                        datasets: [{
                            label: 'Signups by Day',
                            data: <?= $dow_counts ?>,
                            backgroundColor: [
                                '#3b82f6', '#06b6d4', '#10b981', '#8b5cf6', '#ec4899', '#f59e0b', '#f97316'
                            ],
                            borderRadius: 8,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            } catch (err) {
                console.error('Day of week chart error:', err);
            }
        }
    <?php endif; ?>

    // ========================================================
    // 3. ADS TAB CHARTS
    // ========================================================
    <?php if ($active_tab === 'ads'): ?>
        const canvasAds = document.getElementById('chartAdsTimeline');
        if (canvasAds) {
            try {
                const existing = Chart.getChart(canvasAds);
                if (existing) existing.destroy();
                const ctxA = canvasAds.getContext('2d');
                const bgGradPurple = createVerticalGradient(ctxA, 'rgba(139, 92, 246, 0.45)', 'rgba(139, 92, 246, 0.01)');

                if (isCompare) {
                    new Chart(canvasAds, {
                        type: 'line',
                        data: {
                            labels: <?= $comp_day_labels ?>,
                            datasets: [
                                {
                                    label: <?= json_encode($primary_month_label . ' (Ads Posted)') ?>,
                                    data: <?= $a_primary_days_json ?>,
                                    borderColor: '#8b5cf6',
                                    backgroundColor: bgGradPurple,
                                    fill: true,
                                    tension: 0.38,
                                    borderWidth: 3.5,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#8b5cf6'
                                },
                                {
                                    label: <?= json_encode($comp_month_label . ' (Ads Posted)') ?>,
                                    data: <?= $a_comp_days_json ?>,
                                    borderColor: '#f59e0b',
                                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                                    fill: true,
                                    borderDash: [5, 5],
                                    tension: 0.38,
                                    borderWidth: 3,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#f59e0b'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', labels: { boxWidth: 14, font: { weight: 'bold' } } }
                            },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                } else {
                    new Chart(canvasAds, {
                        type: 'line',
                        data: {
                            labels: <?= $chart_ads_labels ?>,
                            datasets: [
                                {
                                    label: 'Total Ads Posted',
                                    data: <?= $chart_ads_total ?>,
                                    borderColor: '#8b5cf6',
                                    backgroundColor: bgGradPurple,
                                    fill: true,
                                    tension: 0.38,
                                    borderWidth: 3.5,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#8b5cf6',
                                    pointBorderColor: '#ffffff',
                                    pointBorderWidth: 2
                                },
                                {
                                    label: 'Verified Ads',
                                    data: <?= $chart_ads_verified ?>,
                                    borderColor: '#10b981',
                                    tension: 0.38,
                                    borderWidth: 2.5,
                                    pointRadius: 4,
                                    pointBackgroundColor: '#10b981'
                                },
                                {
                                    label: 'Unverified Ads',
                                    data: <?= $chart_ads_unverified ?>,
                                    borderColor: '#f59e0b',
                                    borderDash: [4, 4],
                                    tension: 0.38,
                                    borderWidth: 2,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#f59e0b'
                                },
                                {
                                    label: 'Active Listings',
                                    data: <?= $chart_ads_active ?>,
                                    borderColor: '#06b6d4',
                                    tension: 0.38,
                                    borderWidth: 2,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#06b6d4'
                                },
                                {
                                    label: 'Sold / Concluded',
                                    data: <?= $chart_ads_sold ?>,
                                    borderColor: '#3b82f6',
                                    tension: 0.38,
                                    borderWidth: 2,
                                    pointRadius: 3,
                                    pointBackgroundColor: '#3b82f6'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { position: 'top', labels: { boxWidth: 14, font: { weight: 'bold' } } }
                            },
                            scales: {
                                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            } catch (err) {
                console.error('Ads timeline chart error:', err);
            }
        }

        const ctxAdsVer = document.getElementById('chartAdsVerificationDonut');
        if (ctxAdsVer) {
            try {
                const existing = Chart.getChart(ctxAdsVer);
                if (existing) existing.destroy();
                new Chart(ctxAdsVer, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            'Verified Ads (<?= (int)$a_all_time['verified_all_time'] ?>)',
                            'Unverified Ads (<?= (int)$a_all_time['unverified_all_time'] ?>)'
                        ],
                        datasets: [{
                            data: [
                                <?= (int)$a_all_time['verified_all_time'] ?>,
                                <?= (int)$a_all_time['unverified_all_time'] ?>
                            ],
                            backgroundColor: ['#10b981', '#f59e0b'],
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 14, font: { weight: 'bold', size: 12 } } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = <?= max(1, (int)$a_all_time['total_all_time']) ?>;
                                        const val = context.parsed;
                                        const pct = ((val / total) * 100).toFixed(1);
                                        return ` ${context.label}: ${val} ads (${pct}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '66%'
                    }
                });
            } catch (err) {
                console.error('Ads verification donut error:', err);
            }
        }

        const ctxCategories = document.getElementById('chartCategoriesDonut');
        if (ctxCategories) {
            try {
                const existing = Chart.getChart(ctxCategories);
                if (existing) existing.destroy();
                new Chart(ctxCategories, {
                    type: 'doughnut',
                    data: {
                        labels: <?= $chart_cat_labels ?>,
                        datasets: [{
                            data: <?= $chart_cat_counts ?>,
                            backgroundColor: [
                                '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', 
                                '#f59e0b', '#06b6d4', '#f97316', '#64748b'
                            ],
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } } }
                        },
                        cutout: '62%'
                    }
                });
            } catch (err) {
                console.error('Categories chart error:', err);
            }
        }

        const canvasTiers = document.getElementById('chartPriceTiers');
        if (canvasTiers) {
            try {
                const existing = Chart.getChart(canvasTiers);
                if (existing) existing.destroy();
                new Chart(canvasTiers, {
                    type: 'bar',
                    data: {
                        labels: <?= $price_tier_labels ?>,
                        datasets: [{
                            label: 'Number of Listings',
                            data: <?= $price_tier_counts ?>,
                            backgroundColor: [
                                '#10b981', '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b'
                            ],
                            borderRadius: 8,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            } catch (err) {
                console.error('Price tiers chart error:', err);
            }
        }

        const ctxTypeIntent = document.getElementById('chartTypeIntent');
        if (ctxTypeIntent) {
            try {
                const existing = Chart.getChart(ctxTypeIntent);
                if (existing) existing.destroy();
                new Chart(ctxTypeIntent, {
                    type: 'pie',
                    data: {
                        labels: <?= $chart_type_labels ?>,
                        datasets: [{
                            data: <?= $chart_type_counts ?>,
                            backgroundColor: ['#10b981', '#3b82f6', '#f59e0b'],
                            borderWidth: 3,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 14, font: { weight: 'bold' } } }
                        }
                    }
                });
            } catch (err) {
                console.error('Type intent chart error:', err);
            }
        }
    <?php endif; ?>

    // ========================================================
    // 4. MODERATION TAB CHARTS
    // ========================================================
    <?php if ($active_tab === 'moderation' && !empty($report_reasons)): ?>
        const canvasRepReasons = document.getElementById('chartReportReasons');
        if (canvasRepReasons) {
            try {
                const existing = Chart.getChart(canvasRepReasons);
                if (existing) existing.destroy();
                new Chart(canvasRepReasons, {
                    type: 'bar',
                    data: {
                        labels: <?= $chart_rep_reasons_labels ?>,
                        datasets: [{
                            label: 'Flagged Count',
                            data: <?= $chart_rep_reasons_counts ?>,
                            backgroundColor: ['#ef4444', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'],
                            borderRadius: 8,
                            barPercentage: 0.5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            } catch (err) {
                console.error('Moderation report reasons chart error:', err);
            }
        }
    <?php endif; ?>
}

// Multi-tier initialization triggers to guarantee charts always render
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initEnteangadiCharts();
} else {
    document.addEventListener('DOMContentLoaded', initEnteangadiCharts);
}
window.addEventListener('load', initEnteangadiCharts);
</script>

<?php require_once 'includes/footer.php'; ?>