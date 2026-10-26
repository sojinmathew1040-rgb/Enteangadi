<?php
/**
 * Enteangadi Marketplace - Executive XLSX Export Handler
 * Implements Power BI / Tableau quality reporting for Microsoft Excel (.xlsx)
 * Supports all 4 report modules in Single Period & Month-over-Month Comparison modes.
 */

require_once __DIR__ . '/xlsx_report_builder.php';

function handle_excel_export(PDO $pdo, array $params, $savePathOnly = null) {
    $export_type = $params['export_type'] ?? 'visitors';
    $period_preset = $params['period_preset'] ?? 'month';
    $selected_month = $params['selected_month'] ?? date('Y-m');
    $start_date = $params['start_date'];
    $end_date = $params['end_date'];
    $start_ts = $params['start_ts'];
    $end_ts = $params['end_ts'];
    $primary_month_label = $params['primary_month_label'];
    $is_compare = !empty($params['is_compare']);
    $compare_month = $params['compare_month'] ?? '';
    $comp_start_date = $params['comp_start_date'] ?? '';
    $comp_end_date = $params['comp_end_date'] ?? '';
    $comp_start_ts = $params['comp_start_ts'] ?? '';
    $comp_end_ts = $params['comp_end_ts'] ?? '';
    $comp_month_label = $params['comp_month_label'] ?? '';
    $search_q = $params['search_q'] ?? '';

    $mode_label = $is_compare ? "comparison_{$selected_month}_vs_{$compare_month}" : "{$selected_month}";
    $clean_filename = "enteangadi_{$export_type}_{$mode_label}_" . date('Ymd_His');

    $builder = new EnteangadiXlsxReportBuilder();

    // =========================================================================
    // 1. VISITORS & TRAFFIC REPORT MODULE
    // =========================================================================
    if ($export_type === 'visitors') {
        // Query Primary Period
        $s_v1 = $pdo->prepare("SELECT COUNT(*) as tot, COUNT(DISTINCT ip_address) as unq, COUNT(user_id) as usr, SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as gst FROM site_visits WHERE visit_date BETWEEN :s AND :e");
        $s_v1->execute(['s' => $start_date, 'e' => $end_date]);
        $r_v1 = $s_v1->fetch(PDO::FETCH_ASSOC);
        $v_tot1 = max(0, (int)$r_v1['tot']);
        $v_unq1 = max(0, (int)$r_v1['unq']);
        $v_usr1 = max(0, (int)$r_v1['usr']);
        $v_gst1 = max(0, (int)$r_v1['gst']);

        // Query Comparison Period
        $v_tot2 = 0; $v_unq2 = 0; $v_usr2 = 0; $v_gst2 = 0;
        if ($is_compare) {
            $s_v2 = $pdo->prepare("SELECT COUNT(*) as tot, COUNT(DISTINCT ip_address) as unq, COUNT(user_id) as usr, SUM(CASE WHEN user_id IS NULL THEN 1 ELSE 0 END) as gst FROM site_visits WHERE visit_date BETWEEN :s AND :e");
            $s_v2->execute(['s' => $comp_start_date, 'e' => $comp_end_date]);
            $r_v2 = $s_v2->fetch(PDO::FETCH_ASSOC);
            $v_tot2 = max(0, (int)$r_v2['tot']);
            $v_unq2 = max(0, (int)$r_v2['unq']);
            $v_usr2 = max(0, (int)$r_v2['usr']);
            $v_gst2 = max(0, (int)$r_v2['gst']);
        }

        // Query Device Breakdown
        $dev_counts = ['Desktop / Laptop' => 0, 'Mobile Phones' => 0, 'Tablets / Other' => 0];
        $st_dev = $pdo->prepare("SELECT user_agent, COUNT(*) as c FROM site_visits WHERE visit_date BETWEEN :s AND :e GROUP BY user_agent");
        $st_dev->execute(['s' => $start_date, 'e' => $end_date]);
        while ($dev_r = $st_dev->fetch(PDO::FETCH_ASSOC)) {
            $ua = strtolower($dev_r['user_agent'] ?? '');
            $c_cnt = (int)$dev_r['c'];
            if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
                $dev_counts['Mobile Phones'] += $c_cnt;
            } elseif (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
                $dev_counts['Tablets / Other'] += $c_cnt;
            } else {
                $dev_counts['Desktop / Laptop'] += $c_cnt;
            }
        }

        // Query 24-Hour Browsing
        $hourly_traffic = array_fill(0, 24, 0);
        $st_hour = $pdo->prepare("SELECT HOUR(created_at) as h, COUNT(*) as c FROM site_visits WHERE visit_date BETWEEN :s AND :e GROUP BY h ORDER BY h ASC");
        $st_hour->execute(['s' => $start_date, 'e' => $end_date]);
        while ($hr_row = $st_hour->fetch(PDO::FETCH_ASSOC)) {
            $hourly_traffic[(int)$hr_row['h']] = (int)$hr_row['c'];
        }

        // Query Daily Breakdown Day 1 to 31
        $d_v1 = array_fill(1, 31, 0);
        $d_v2 = array_fill(1, 31, 0);
        $st_d1 = $pdo->prepare("SELECT DAY(visit_date) as d, COUNT(*) as c FROM site_visits WHERE visit_date BETWEEN :s AND :e GROUP BY d");
        $st_d1->execute(['s' => $start_date, 'e' => $end_date]);
        while ($row = $st_d1->fetch(PDO::FETCH_ASSOC)) { $d_v1[(int)$row['d']] = (int)$row['c']; }

        if ($is_compare) {
            $st_d2 = $pdo->prepare("SELECT DAY(visit_date) as d, COUNT(*) as c FROM site_visits WHERE visit_date BETWEEN :s AND :e GROUP BY d");
            $st_d2->execute(['s' => $comp_start_date, 'e' => $comp_end_date]);
            while ($row = $st_d2->fetch(PDO::FETCH_ASSOC)) { $d_v2[(int)$row['d']] = (int)$row['c']; }
        }

        // --- SHEET 1: VISUAL DASHBOARD ---
        $builder->addSheet('Visual Dashboard', [
            1 => 32, 2 => 18, 3 => 18, 4 => 16, 5 => 16, 6 => 16, 7 => 14, 8 => 14, 9 => 14, 10 => 14, 11 => 14, 12 => 14
        ]);

        // Banner Header
        $builder->addRow('Visual Dashboard', 1, [
            ['col' => 'A', 'val' => 'ENTEANGADI MARKETPLACE - VISITORS EXECUTIVE REPORT', 'type' => 'string', 'style' => 1]
        ], 34);
        $builder->mergeCells('Visual Dashboard', 'A1:F1');

        $meta_str = "Generated: " . date('Y-m-d H:i:s') . " | Period: {$primary_month_label} ({$start_date} to {$end_date})";
        if ($is_compare) {
            $meta_str .= " | Comparison Benchmark: {$comp_month_label} ({$comp_start_date} to {$comp_end_date})";
        }
        if (!empty($search_q)) {
            $meta_str .= " | Search: \"{$search_q}\"";
        }
        $builder->addRow('Visual Dashboard', 2, [
            ['col' => 'A', 'val' => $meta_str, 'type' => 'string', 'style' => 5]
        ], 18);

        // Section 1 Header: KPIs
        $builder->addRow('Visual Dashboard', 4, [
            ['col' => 'A', 'val' => 'SECTION 1: EXECUTIVE KPI SUMMARY & GROWTH METRICS', 'type' => 'string', 'style' => 2]
        ], 24);
        $builder->mergeCells('Visual Dashboard', 'A4:F4');

        if ($is_compare) {
            $builder->addRow('Visual Dashboard', 5, [
                ['col' => 'A', 'val' => 'Metric Name', 'type' => 'string', 'style' => 3],
                ['col' => 'B', 'val' => $primary_month_label . ' (Primary)', 'type' => 'string', 'style' => 3],
                ['col' => 'C', 'val' => $comp_month_label . ' (Benchmark)', 'type' => 'string', 'style' => 3],
                ['col' => 'D', 'val' => 'Variance (+/-)', 'type' => 'string', 'style' => 3],
                ['col' => 'E', 'val' => 'Growth Rate (%)', 'type' => 'string', 'style' => 3],
                ['col' => 'F', 'val' => 'Trend Status', 'type' => 'string', 'style' => 3]
            ], 22);

            $metrics = [
                ['Total Page Visits', $v_tot1, $v_tot2],
                ['Unique Visitors (IPs)', $v_unq1, $v_unq2],
                ['Registered Member Visits', $v_usr1, $v_usr2],
                ['Guest / Public Visits', $v_gst1, $v_gst2]
            ];

            foreach ($metrics as $mIdx => $m) {
                $rNum = 6 + $mIdx;
                $diff = $m[1] - $m[2];
                $pct = $m[2] > 0 ? round(($diff / $m[2]), 4) : ($m[1] > 0 ? 1.0 : 0.0);
                $trend_badge = $diff >= 0 ? "▲ + " . round($pct * 100, 1) . "%" : "▼ " . round($pct * 100, 1) . "%";
                $badge_style = $diff >= 0 ? 13 : 14;

                $builder->addRow('Visual Dashboard', $rNum, [
                    ['col' => 'A', 'val' => $m[0], 'type' => 'string', 'style' => 4],
                    ['col' => 'B', 'val' => $m[1], 'type' => 'number', 'style' => 6],
                    ['col' => 'C', 'val' => $m[2], 'type' => 'number', 'style' => 6],
                    ['col' => 'D', 'val' => $diff, 'type' => 'number', 'style' => 6],
                    ['col' => 'E', 'val' => $pct, 'type' => 'percent', 'style' => 7],
                    ['col' => 'F', 'val' => $trend_badge, 'type' => 'string', 'style' => $badge_style]
                ], 20);
            }
        } else {
            $builder->addRow('Visual Dashboard', 5, [
                ['col' => 'A', 'val' => 'Metric Name', 'type' => 'string', 'style' => 3],
                ['col' => 'B', 'val' => 'Count / Value', 'type' => 'string', 'style' => 3],
                ['col' => 'C', 'val' => 'Share of Total', 'type' => 'string', 'style' => 3]
            ], 22);

            $metrics = [
                ['Total Page Visits', $v_tot1, 1.0],
                ['Unique Visitors (IPs)', $v_unq1, $v_tot1 > 0 ? round($v_unq1 / $v_tot1, 4) : 0],
                ['Registered Member Visits', $v_usr1, $v_tot1 > 0 ? round($v_usr1 / $v_tot1, 4) : 0],
                ['Guest / Public Visits', $v_gst1, $v_tot1 > 0 ? round($v_gst1 / $v_tot1, 4) : 0]
            ];

            foreach ($metrics as $mIdx => $m) {
                $rNum = 6 + $mIdx;
                $builder->addRow('Visual Dashboard', $rNum, [
                    ['col' => 'A', 'val' => $m[0], 'type' => 'string', 'style' => 4],
                    ['col' => 'B', 'val' => $m[1], 'type' => 'number', 'style' => 6],
                    ['col' => 'C', 'val' => $m[2], 'type' => 'percent', 'style' => 7]
                ], 20);
            }
        }

        // Section 2: Reference tables for Donut Charts
        $builder->addRow('Visual Dashboard', 11, [
            ['col' => 'A', 'val' => 'Audience Segment', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => 'Visits', 'type' => 'string', 'style' => 15],
            ['col' => 'C', 'val' => 'Device Platform', 'type' => 'string', 'style' => 15],
            ['col' => 'D', 'val' => 'Visits', 'type' => 'string', 'style' => 15]
        ], 22);

        $builder->addRow('Visual Dashboard', 12, [
            ['col' => 'A', 'val' => 'Registered Members', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $v_usr1, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => 'Desktop / Laptop', 'type' => 'string', 'style' => 4],
            ['col' => 'D', 'val' => $dev_counts['Desktop / Laptop'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 13, [
            ['col' => 'A', 'val' => 'Guest Browsers', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $v_gst1, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => 'Mobile Phones', 'type' => 'string', 'style' => 4],
            ['col' => 'D', 'val' => $dev_counts['Mobile Phones'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 14, [
            ['col' => 'A', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'B', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'C', 'val' => 'Tablets / Other', 'type' => 'string', 'style' => 4],
            ['col' => 'D', 'val' => $dev_counts['Tablets / Other'], 'type' => 'number', 'style' => 6]
        ]);

        // Place Chart 1 (Audience Donut) at Col E to H, Row 10 to 25
        $builder->addDoughnutChart(
            'Visual Dashboard',
            'Audience Split (Members vs Guests)',
            "'Visual Dashboard'!\$A\$12:\$A\$13",
            "'Visual Dashboard'!\$B\$12:\$B\$13",
            ['Registered Members', 'Guest Browsers'],
            [$v_usr1, $v_gst1],
            4, 10, 8, 25
        );

        // Place Chart 2 (Device Donut) at Col I to L, Row 10 to 25
        $builder->addDoughnutChart(
            'Visual Dashboard',
            'Device & Platform Share',
            "'Visual Dashboard'!\$C\$12:\$C\$14",
            "'Visual Dashboard'!\$D\$12:\$D\$14",
            array_keys($dev_counts),
            array_values($dev_counts),
            8, 10, 12, 25
        );

        // Section 3: 24-Hour Browsing Reference Table
        $builder->addRow('Visual Dashboard', 16, [
            ['col' => 'A', 'val' => 'Hour Slot', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => 'Hourly Traffic', 'type' => 'string', 'style' => 15]
        ], 20);

        $hCats = [];
        $hVals = [];
        for ($h = 0; $h < 24; $h++) {
            $hSlot = sprintf('%02d:00', $h);
            $hCats[] = $hSlot;
            $hVals[] = $hourly_traffic[$h];
            $rNum = 17 + $h;
            $builder->addRow('Visual Dashboard', $rNum, [
                ['col' => 'A', 'val' => $hSlot, 'type' => 'string', 'style' => 5],
                ['col' => 'B', 'val' => $hourly_traffic[$h], 'type' => 'number', 'style' => 6]
            ]);
        }

        // Place Chart 3 (24-Hour Column Histogram) at Col E to L, Row 26 to 41
        $builder->addColumnChart(
            'Visual Dashboard',
            '24-Hour Peak Browsing Hourly Histogram',
            'Visits',
            "'Visual Dashboard'!\$A\$17:\$A\$40",
            "'Visual Dashboard'!\$B\$17:\$B\$40",
            $hCats,
            $hVals,
            4, 26, 12, 41,
            '3B82F6' // Vibrant Blue
        );

        // Section 4: Day 1 to 31 Reference Table
        $row_d_start = 43;
        $builder->addRow('Visual Dashboard', $row_d_start, [
            ['col' => 'A', 'val' => 'Day of Month', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => $primary_month_label, 'type' => 'string', 'style' => 15],
            ['col' => 'C', 'val' => $is_compare ? $comp_month_label : 'Daily Volume', 'type' => 'string', 'style' => 15]
        ], 20);

        $dCats = [];
        $dVals1 = [];
        $dVals2 = [];
        for ($d = 1; $d <= 31; $d++) {
            $dCats[] = 'D' . $d;
            $dVals1[] = $d_v1[$d];
            $dVals2[] = $d_v2[$d];
            $rNum = $row_d_start + $d;
            $builder->addRow('Visual Dashboard', $rNum, [
                ['col' => 'A', 'val' => 'D' . $d, 'type' => 'string', 'style' => 5],
                ['col' => 'B', 'val' => $d_v1[$d], 'type' => 'number', 'style' => 6],
                ['col' => 'C', 'val' => $is_compare ? $d_v2[$d] : $d_v1[$d], 'type' => 'number', 'style' => 6]
            ]);
        }

        // Place Chart 4: Day 1 to 31 Comparison or Dynamics Chart at Col E to L, Row 42 to 62
        if ($is_compare) {
            $builder->addClusteredComparisonChart(
                'Visual Dashboard',
                'Month-over-Month Daily Traffic Volume Comparison (Day 1 to 31)',
                "'Visual Dashboard'!\$A\$" . ($row_d_start + 1) . ":\$A\$" . ($row_d_start + 31),
                $dCats,
                [
                    'name' => $primary_month_label . ' (Primary)',
                    'valRange' => "'Visual Dashboard'!\$B\$" . ($row_d_start + 1) . ":\$B\$" . ($row_d_start + 31),
                    'vals' => $dVals1,
                    'color' => '059669' // Emerald
                ],
                [
                    'name' => $comp_month_label . ' (Benchmark)',
                    'valRange' => "'Visual Dashboard'!\$C\$" . ($row_d_start + 1) . ":\$C\$" . ($row_d_start + 31),
                    'vals' => $dVals2,
                    'color' => '8B5CF6' // Purple
                ],
                4, 42, 12, 62
            );
        } else {
            $builder->addColumnChart(
                'Visual Dashboard',
                'Daily Traffic Volume Dynamics (Day 1 to 31)',
                'Daily Visits',
                "'Visual Dashboard'!\$A\$" . ($row_d_start + 1) . ":\$A\$" . ($row_d_start + 31),
                "'Visual Dashboard'!\$B\$" . ($row_d_start + 1) . ":\$B\$" . ($row_d_start + 31),
                $dCats,
                $dVals1,
                4, 42, 12, 62,
                '059669'
            );
        }

        // --- SHEET 2: DETAILED DATA MATRIX ---
        $builder->addSheet('Detailed Daily Matrix', [
            1 => 16, 2 => 22, 3 => 22, 4 => 18, 5 => 18, 6 => 18
        ]);

        if ($is_compare) {
            $builder->addRow('Detailed Daily Matrix', 1, [
                ['col' => 'A', 'val' => 'Day of Month', 'type' => 'string', 'style' => 3],
                ['col' => 'B', 'val' => $primary_month_label . ' (Visits)', 'type' => 'string', 'style' => 3],
                ['col' => 'C', 'val' => $comp_month_label . ' (Visits)', 'type' => 'string', 'style' => 3],
                ['col' => 'D', 'val' => 'Variance (+/-)', 'type' => 'string', 'style' => 3],
                ['col' => 'E', 'val' => 'Growth Rate (%)', 'type' => 'string', 'style' => 3],
                ['col' => 'F', 'val' => 'Trend Status', 'type' => 'string', 'style' => 3]
            ], 24);

            for ($d = 1; $d <= 31; $d++) {
                $rNum = $d + 1;
                $pv = $d_v1[$d];
                $cv = $d_v2[$d];
                $diff = $pv - $cv;
                $pct = $cv > 0 ? round($diff / $cv, 4) : ($pv > 0 ? 1.0 : 0.0);
                $trend_badge = $diff >= 0 ? "▲ + " . round($pct * 100, 1) . "%" : "▼ " . round($pct * 100, 1) . "%";
                $badge_style = $diff >= 0 ? 13 : 14;

                $builder->addRow('Detailed Daily Matrix', $rNum, [
                    ['col' => 'A', 'val' => 'Day ' . $d, 'type' => 'string', 'style' => 4],
                    ['col' => 'B', 'val' => $pv, 'type' => 'number', 'style' => 6],
                    ['col' => 'C', 'val' => $cv, 'type' => 'number', 'style' => 6],
                    ['col' => 'D', 'val' => $diff, 'type' => 'number', 'style' => 6],
                    ['col' => 'E', 'val' => $pct, 'type' => 'percent', 'style' => 7],
                    ['col' => 'F', 'val' => $trend_badge, 'type' => 'string', 'style' => $badge_style]
                ]);
            }
            $builder->setAutoFilter('Detailed Daily Matrix', 'A1:F32');
        } else {
            $builder->addRow('Detailed Daily Matrix', 1, [
                ['col' => 'A', 'val' => 'Day of Month', 'type' => 'string', 'style' => 3],
                ['col' => 'B', 'val' => 'Visits Count', 'type' => 'string', 'style' => 3],
                ['col' => 'C', 'val' => 'Share of Month (%)', 'type' => 'string', 'style' => 3]
            ], 24);

            for ($d = 1; $d <= 31; $d++) {
                $rNum = $d + 1;
                $pv = $d_v1[$d];
                $pct = $v_tot1 > 0 ? round($pv / $v_tot1, 4) : 0.0;
                $builder->addRow('Detailed Daily Matrix', $rNum, [
                    ['col' => 'A', 'val' => 'Day ' . $d, 'type' => 'string', 'style' => 4],
                    ['col' => 'B', 'val' => $pv, 'type' => 'number', 'style' => 6],
                    ['col' => 'C', 'val' => $pct, 'type' => 'percent', 'style' => 7]
                ]);
            }
            $builder->setAutoFilter('Detailed Daily Matrix', 'A1:C32');
        }
    }

    // =========================================================================
    // 2. USERS & REGISTRATIONS REPORT MODULE
    // =========================================================================
    elseif ($export_type === 'users') {
        $stmt_all_time = $pdo->query("
            SELECT 
                COUNT(*) as total_all_time,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_all_time,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_all_time,
                SUM(CASE WHEN is_admin = 1 OR role = 'admin' THEN 1 ELSE 0 END) as admins_all_time
            FROM users
        ");
        $r_all = $stmt_all_time->fetch(PDO::FETCH_ASSOC);
        $u_tot_all = max(0, (int)$r_all['total_all_time']);
        $u_ver_all = max(0, (int)$r_all['verified_all_time']);
        $u_unver_all = max(0, (int)$r_all['unverified_all_time']);
        $u_adm_all = max(0, (int)$r_all['admins_all_time']);
        $ver_rate = $u_tot_all > 0 ? round(($u_ver_all / $u_tot_all), 4) : 0.0;

        $s_u1 = $pdo->prepare("SELECT COUNT(*) as tot, SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as ver, SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unver FROM users WHERE created_at BETWEEN :s AND :e");
        $s_u1->execute(['s' => $start_ts, 'e' => $end_ts]);
        $r_u1 = $s_u1->fetch(PDO::FETCH_ASSOC);
        $u_p_tot = max(0, (int)$r_u1['tot']);
        $u_p_ver = max(0, (int)$r_u1['ver']);
        $u_p_unver = max(0, (int)$r_u1['unver']);

        $u_c_tot = 0;
        if ($is_compare) {
            $s_u2 = $pdo->prepare("SELECT COUNT(*) as tot FROM users WHERE created_at BETWEEN :s AND :e");
            $s_u2->execute(['s' => $comp_start_ts, 'e' => $comp_end_ts]);
            $r_u2 = $s_u2->fetch(PDO::FETCH_ASSOC);
            $u_c_tot = max(0, (int)$r_u2['tot']);
        }

        // Day of week signups
        $dow_counts = ['Mon' => 0, 'Tue' => 0, 'Wed' => 0, 'Thu' => 0, 'Fri' => 0, 'Sat' => 0, 'Sun' => 0];
        $st_dow = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%a') as dn, COUNT(*) as c FROM users WHERE created_at BETWEEN :s AND :e GROUP BY dn");
        $st_dow->execute(['s' => $start_ts, 'e' => $end_ts]);
        while ($d_row = $st_dow->fetch(PDO::FETCH_ASSOC)) {
            if (isset($dow_counts[$d_row['dn']])) $dow_counts[$d_row['dn']] = (int)$d_row['c'];
        }

        // Daily Signups Day 1 to 31
        $u_d1 = array_fill(1, 31, 0);
        $u_d2 = array_fill(1, 31, 0);
        $st_ud1 = $pdo->prepare("SELECT DAY(created_at) as d, COUNT(*) as c FROM users WHERE created_at BETWEEN :s AND :e GROUP BY d");
        $st_ud1->execute(['s' => $start_ts, 'e' => $end_ts]);
        while ($row = $st_ud1->fetch(PDO::FETCH_ASSOC)) { $u_d1[(int)$row['d']] = (int)$row['c']; }

        if ($is_compare) {
            $st_ud2 = $pdo->prepare("SELECT DAY(created_at) as d, COUNT(*) as c FROM users WHERE created_at BETWEEN :s AND :e GROUP BY d");
            $st_ud2->execute(['s' => $comp_start_ts, 'e' => $comp_end_ts]);
            while ($row = $st_ud2->fetch(PDO::FETCH_ASSOC)) { $u_d2[(int)$row['d']] = (int)$row['c']; }
        }

        // Fetch User Directory records
        $sql_users = "SELECT id, username, email, phone_number, is_verified, is_admin, role, created_at FROM users WHERE created_at BETWEEN :s AND :e ORDER BY created_at DESC LIMIT 500";
        $stmt_ulist = $pdo->prepare($sql_users);
        $stmt_ulist->execute(['s' => $start_ts, 'e' => $end_ts]);
        $users_list = $stmt_ulist->fetchAll(PDO::FETCH_ASSOC);

        // --- SHEET 1: VISUAL DASHBOARD ---
        $builder->addSheet('Visual Dashboard', [
            1 => 32, 2 => 20, 3 => 20, 4 => 18, 5 => 16, 6 => 16, 7 => 14, 8 => 14, 9 => 14, 10 => 14, 11 => 14, 12 => 14
        ]);

        $builder->addRow('Visual Dashboard', 1, [
            ['col' => 'A', 'val' => 'ENTEANGADI MARKETPLACE - USERS & REGISTRATIONS EXECUTIVE REPORT', 'type' => 'string', 'style' => 1]
        ], 34);
        $builder->mergeCells('Visual Dashboard', 'A1:F1');

        $builder->addRow('Visual Dashboard', 2, [
            ['col' => 'A', 'val' => "Generated: " . date('Y-m-d H:i:s') . " | Period: {$primary_month_label}" . ($is_compare ? " vs {$comp_month_label}" : ""), 'type' => 'string', 'style' => 5]
        ], 18);

        // Section 1 Header: KPIs
        $builder->addRow('Visual Dashboard', 4, [
            ['col' => 'A', 'val' => 'SECTION 1: USER BASE VELOCITY & ACCOUNT VERIFICATION OVERVIEW', 'type' => 'string', 'style' => 2]
        ], 24);
        $builder->mergeCells('Visual Dashboard', 'A4:F4');

        $builder->addRow('Visual Dashboard', 5, [
            ['col' => 'A', 'val' => 'Total Marketplace Users', 'type' => 'string', 'style' => 3],
            ['col' => 'B', 'val' => 'Verified Accounts', 'type' => 'string', 'style' => 3],
            ['col' => 'C', 'val' => 'Unverified Accounts', 'type' => 'string', 'style' => 3],
            ['col' => 'D', 'val' => 'Period New Signups', 'type' => 'string', 'style' => 3],
            ['col' => 'E', 'val' => 'Verification Rate', 'type' => 'string', 'style' => 3],
            ['col' => 'F', 'val' => 'Platform Staff', 'type' => 'string', 'style' => 3]
        ], 22);

        $builder->addRow('Visual Dashboard', 6, [
            ['col' => 'A', 'val' => $u_tot_all, 'type' => 'number', 'style' => 6],
            ['col' => 'B', 'val' => $u_ver_all, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => $u_unver_all, 'type' => 'number', 'style' => 6],
            ['col' => 'D', 'val' => $u_p_tot, 'type' => 'number', 'style' => 6],
            ['col' => 'E', 'val' => $ver_rate, 'type' => 'percent', 'style' => 7],
            ['col' => 'F', 'val' => $u_adm_all, 'type' => 'number', 'style' => 6]
        ], 22);

        // Reference tables for Charts
        $builder->addRow('Visual Dashboard', 9, [
            ['col' => 'A', 'val' => 'Account Status', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => 'Count', 'type' => 'string', 'style' => 15],
            ['col' => 'C', 'val' => 'Day of Week', 'type' => 'string', 'style' => 15],
            ['col' => 'D', 'val' => 'Signups', 'type' => 'string', 'style' => 15]
        ], 20);

        $builder->addRow('Visual Dashboard', 10, [
            ['col' => 'A', 'val' => 'Verified Accounts', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $u_ver_all, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => 'Mon', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => $dow_counts['Mon'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 11, [
            ['col' => 'A', 'val' => 'Unverified Accounts', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $u_unver_all, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => 'Tue', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => $dow_counts['Tue'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 12, [
            ['col' => 'A', 'val' => 'Platform Staff', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $u_adm_all, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => 'Wed', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => $dow_counts['Wed'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 13, [
            ['col' => 'A', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'B', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'C', 'val' => 'Thu', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => $dow_counts['Thu'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 14, [
            ['col' => 'A', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'B', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'C', 'val' => 'Fri', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => $dow_counts['Fri'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 15, [
            ['col' => 'A', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'B', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'C', 'val' => 'Sat', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => $dow_counts['Sat'], 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 16, [
            ['col' => 'A', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'B', 'val' => '', 'type' => 'string', 'style' => 0],
            ['col' => 'C', 'val' => 'Sun', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => $dow_counts['Sun'], 'type' => 'number', 'style' => 6]
        ]);

        // Place Chart 1: Donut (Verification Share) at Col E to H, Row 8 to 23
        $builder->addDoughnutChart(
            'Visual Dashboard',
            'Account Trust & Verification Ratio',
            "'Visual Dashboard'!\$A\$10:\$A\$12",
            "'Visual Dashboard'!\$B\$10:\$B\$12",
            ['Verified Accounts', 'Unverified Accounts', 'Platform Staff'],
            [$u_ver_all, $u_unver_all, $u_adm_all],
            4, 8, 8, 23
        );

        // Place Chart 2: Column (Day of Week Signups) at Col I to L, Row 8 to 23
        $builder->addColumnChart(
            'Visual Dashboard',
            'Day-of-Week Signup Distribution',
            'Signups',
            "'Visual Dashboard'!\$C\$10:\$C\$16",
            "'Visual Dashboard'!\$D\$10:\$D\$16",
            array_keys($dow_counts),
            array_values($dow_counts),
            8, 8, 12, 23,
            '3B82F6'
        );

        // Day 1 to 31 Data Table
        $row_u_d = 18;
        $builder->addRow('Visual Dashboard', $row_u_d, [
            ['col' => 'A', 'val' => 'Day of Month', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => $primary_month_label . ' Signups', 'type' => 'string', 'style' => 15],
            ['col' => 'C', 'val' => $is_compare ? $comp_month_label . ' Signups' : 'Velocity', 'type' => 'string', 'style' => 15]
        ], 20);

        $uCats = [];
        $uVals1 = [];
        $uVals2 = [];
        for ($d = 1; $d <= 31; $d++) {
            $uCats[] = 'D' . $d;
            $uVals1[] = $u_d1[$d];
            $uVals2[] = $u_d2[$d];
            $rNum = $row_u_d + $d;
            $builder->addRow('Visual Dashboard', $rNum, [
                ['col' => 'A', 'val' => 'D' . $d, 'type' => 'string', 'style' => 5],
                ['col' => 'B', 'val' => $u_d1[$d], 'type' => 'number', 'style' => 6],
                ['col' => 'C', 'val' => $is_compare ? $u_d2[$d] : $u_d1[$d], 'type' => 'number', 'style' => 6]
            ]);
        }

        // Place Chart 3: Day 1 to 31 Signup Velocity (Comparison or Single) at Col E to L, Row 24 to 45
        if ($is_compare) {
            $builder->addClusteredComparisonChart(
                'Visual Dashboard',
                'Month-over-Month User Signup Velocity Comparison (Day 1 to 31)',
                "'Visual Dashboard'!\$A\$" . ($row_u_d + 1) . ":\$A\$" . ($row_u_d + 31),
                $uCats,
                [
                    'name' => $primary_month_label . ' (Signups)',
                    'valRange' => "'Visual Dashboard'!\$B\$" . ($row_u_d + 1) . ":\$B\$" . ($row_u_d + 31),
                    'vals' => $uVals1,
                    'color' => '3B82F6' // Blue
                ],
                [
                    'name' => $comp_month_label . ' (Signups)',
                    'valRange' => "'Visual Dashboard'!\$C\$" . ($row_u_d + 1) . ":\$C\$" . ($row_u_d + 31),
                    'vals' => $uVals2,
                    'color' => '8B5CF6' // Purple
                ],
                4, 24, 12, 45
            );
        } else {
            $builder->addColumnChart(
                'Visual Dashboard',
                'Daily User Signup Dynamics (Day 1 to 31)',
                'Signups',
                "'Visual Dashboard'!\$A\$" . ($row_u_d + 1) . ":\$A\$" . ($row_u_d + 31),
                "'Visual Dashboard'!\$B\$" . ($row_u_d + 1) . ":\$B\$" . ($row_u_d + 31),
                $uCats,
                $uVals1,
                4, 24, 12, 45,
                '3B82F6'
            );
        }

        // --- SHEET 2: USER DIRECTORY & AUDIT ---
        $builder->addSheet('User Directory', [
            1 => 12, 2 => 22, 3 => 28, 4 => 20, 5 => 16, 6 => 14, 7 => 22
        ]);

        $builder->addRow('User Directory', 1, [
            ['col' => 'A', 'val' => 'User ID', 'type' => 'string', 'style' => 3],
            ['col' => 'B', 'val' => 'Username', 'type' => 'string', 'style' => 3],
            ['col' => 'C', 'val' => 'Email Address', 'type' => 'string', 'style' => 3],
            ['col' => 'D', 'val' => 'Phone / WhatsApp', 'type' => 'string', 'style' => 3],
            ['col' => 'E', 'val' => 'Account Status', 'type' => 'string', 'style' => 3],
            ['col' => 'F', 'val' => 'Role', 'type' => 'string', 'style' => 3],
            ['col' => 'G', 'val' => 'Registered Date', 'type' => 'string', 'style' => 3]
        ], 24);

        $uRowIdx = 2;
        foreach ($users_list as $u) {
            $is_v = !empty($u['is_verified']);
            $builder->addRow('User Directory', $uRowIdx++, [
                ['col' => 'A', 'val' => '#' . $u['id'], 'type' => 'string', 'style' => 4],
                ['col' => 'B', 'val' => $u['username'], 'type' => 'string', 'style' => 4],
                ['col' => 'C', 'val' => $u['email'] ?: 'N/A', 'type' => 'string', 'style' => 5],
                ['col' => 'D', 'val' => $u['phone_number'] ?: 'N/A', 'type' => 'string', 'style' => 4],
                ['col' => 'E', 'val' => $is_v ? 'Verified' : 'Unverified', 'type' => 'string', 'style' => $is_v ? 13 : 14],
                ['col' => 'F', 'val' => ucfirst($u['role'] ?? 'User'), 'type' => 'string', 'style' => 5],
                ['col' => 'G', 'val' => $u['created_at'], 'type' => 'string', 'style' => 5]
            ]);
        }
        $builder->setAutoFilter('User Directory', 'A1:G' . max(2, $uRowIdx - 1));
    }

    // =========================================================================
    // 3. ADS POSTED & INVENTORY REPORT MODULE
    // =========================================================================
    elseif ($export_type === 'ads') {
        $stmt_a_all = $pdo->query("
            SELECT 
                COUNT(*) as total_all_time,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_all_time,
                SUM(CASE WHEN is_verified = 0 OR is_verified IS NULL THEN 1 ELSE 0 END) as unverified_all_time,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_all_time,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_all_time,
                COALESCE(SUM(price), 0) as valuation_all_time
            FROM products
        ");
        $r_a_all = $stmt_a_all->fetch(PDO::FETCH_ASSOC);
        $a_tot_all = max(0, (int)$r_a_all['total_all_time']);
        $a_ver_all = max(0, (int)$r_a_all['verified_all_time']);
        $a_unver_all = max(0, (int)$r_a_all['unverified_all_time']);
        $a_act_all = max(0, (int)$r_a_all['active_all_time']);
        $a_sld_all = max(0, (int)$r_a_all['sold_all_time']);
        $a_val_all = max(0, (float)$r_a_all['valuation_all_time']);

        // Period metrics
        $s_a1 = $pdo->prepare("SELECT COUNT(*) as tot, SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as ver, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as act, SUM(CASE WHEN status='sold' THEN 1 ELSE 0 END) as sld, COALESCE(SUM(price), 0) as val FROM products WHERE created_at BETWEEN :s AND :e");
        $s_a1->execute(['s' => $start_ts, 'e' => $end_ts]);
        $r_a1 = $s_a1->fetch(PDO::FETCH_ASSOC);
        $a_p_tot = max(0, (int)$r_a1['tot']);
        $a_p_ver = max(0, (int)$r_a1['ver']);
        $a_p_act = max(0, (int)$r_a1['act']);
        $a_p_sld = max(0, (int)$r_a1['sld']);
        $a_p_val = max(0, (float)$r_a1['val']);

        // Type breakdown
        $type_counts = ['Sell Items' => 0, 'Buy Requests' => 0, 'Rental Ads' => 0];
        $st_type = $pdo->prepare("SELECT type, COUNT(*) as c FROM products WHERE created_at BETWEEN :s AND :e GROUP BY type");
        $st_type->execute(['s' => $start_ts, 'e' => $end_ts]);
        while ($tr = $st_type->fetch(PDO::FETCH_ASSOC)) {
            $tk = strtolower($tr['type'] ?? '');
            if ($tk === 'sell') $type_counts['Sell Items'] += (int)$tr['c'];
            elseif ($tk === 'buy') $type_counts['Buy Requests'] += (int)$tr['c'];
            elseif ($tk === 'rent') $type_counts['Rental Ads'] += (int)$tr['c'];
        }

        // Category breakdown
        $cat_counts = [];
        $st_cat = $pdo->prepare("SELECT c.name as cname, COUNT(p.id) as cnt FROM products p JOIN categories c ON p.category_id = c.id WHERE p.created_at BETWEEN :s AND :e GROUP BY c.id ORDER BY cnt DESC LIMIT 8");
        $st_cat->execute(['s' => $start_ts, 'e' => $end_ts]);
        while ($cr = $st_cat->fetch(PDO::FETCH_ASSOC)) {
            $cat_counts[$cr['cname']] = (int)$cr['cnt'];
        }
        if (empty($cat_counts)) $cat_counts = ['General' => 0];

        // Daily Ads Day 1 to 31
        $a_d1 = array_fill(1, 31, 0);
        $a_d2 = array_fill(1, 31, 0);
        $st_ad1 = $pdo->prepare("SELECT DAY(created_at) as d, COUNT(*) as c FROM products WHERE created_at BETWEEN :s AND :e GROUP BY d");
        $st_ad1->execute(['s' => $start_ts, 'e' => $end_ts]);
        while ($row = $st_ad1->fetch(PDO::FETCH_ASSOC)) { $a_d1[(int)$row['d']] = (int)$row['c']; }

        if ($is_compare) {
            $st_ad2 = $pdo->prepare("SELECT DAY(created_at) as d, COUNT(*) as c FROM products WHERE created_at BETWEEN :s AND :e GROUP BY d");
            $st_ad2->execute(['s' => $comp_start_ts, 'e' => $comp_end_ts]);
            while ($row = $st_ad2->fetch(PDO::FETCH_ASSOC)) { $a_d2[(int)$row['d']] = (int)$row['c']; }
        }

        // Fetch Inventory rows
        $sql_prods = "SELECT p.id, p.title, c.name as category_name, p.type, p.price, p.status, p.views, p.is_verified, u.username, u.phone_number, p.created_at FROM products p JOIN categories c ON p.category_id = c.id JOIN users u ON p.user_id = u.id WHERE p.created_at BETWEEN :s AND :e ORDER BY p.created_at DESC LIMIT 500";
        $stmt_plist = $pdo->prepare($sql_prods);
        $stmt_plist->execute(['s' => $start_ts, 'e' => $end_ts]);
        $prod_list = $stmt_plist->fetchAll(PDO::FETCH_ASSOC);

        // --- SHEET 1: VISUAL DASHBOARD ---
        $builder->addSheet('Visual Dashboard', [
            1 => 32, 2 => 20, 3 => 20, 4 => 18, 5 => 16, 6 => 16, 7 => 14, 8 => 14, 9 => 14, 10 => 14, 11 => 14, 12 => 14
        ]);

        $builder->addRow('Visual Dashboard', 1, [
            ['col' => 'A', 'val' => 'ENTEANGADI MARKETPLACE - INVENTORY & ADS EXECUTIVE REPORT', 'type' => 'string', 'style' => 1]
        ], 34);
        $builder->mergeCells('Visual Dashboard', 'A1:F1');

        $builder->addRow('Visual Dashboard', 2, [
            ['col' => 'A', 'val' => "Generated: " . date('Y-m-d H:i:s') . " | Period: {$primary_month_label}" . ($is_compare ? " vs {$comp_month_label}" : ""), 'type' => 'string', 'style' => 5]
        ], 18);

        // Section 1 Header: KPIs
        $builder->addRow('Visual Dashboard', 4, [
            ['col' => 'A', 'val' => 'SECTION 1: MARKETPLACE INVENTORY VALUATION & QUALITY AUDIT', 'type' => 'string', 'style' => 2]
        ], 24);
        $builder->mergeCells('Visual Dashboard', 'A4:F4');

        $builder->addRow('Visual Dashboard', 5, [
            ['col' => 'A', 'val' => 'Total Listed Ads', 'type' => 'string', 'style' => 3],
            ['col' => 'B', 'val' => 'Active Live Listings', 'type' => 'string', 'style' => 3],
            ['col' => 'C', 'val' => 'Sold Deals', 'type' => 'string', 'style' => 3],
            ['col' => 'D', 'val' => 'Verified Badge Ads', 'type' => 'string', 'style' => 3],
            ['col' => 'E', 'val' => 'Period Valuation', 'type' => 'string', 'style' => 3],
            ['col' => 'F', 'val' => 'Lifetime Valuation', 'type' => 'string', 'style' => 3]
        ], 22);

        $builder->addRow('Visual Dashboard', 6, [
            ['col' => 'A', 'val' => $a_tot_all, 'type' => 'number', 'style' => 6],
            ['col' => 'B', 'val' => $a_p_act, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => $a_p_sld, 'type' => 'number', 'style' => 6],
            ['col' => 'D', 'val' => $a_p_ver, 'type' => 'number', 'style' => 6],
            ['col' => 'E', 'val' => $a_p_val, 'type' => 'currency', 'style' => 8],
            ['col' => 'F', 'val' => $a_val_all, 'type' => 'currency', 'style' => 8]
        ], 22);

        // Reference tables for Charts
        $builder->addRow('Visual Dashboard', 9, [
            ['col' => 'A', 'val' => 'Listing Intent', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => 'Ads Count', 'type' => 'string', 'style' => 15],
            ['col' => 'C', 'val' => 'Top Category', 'type' => 'string', 'style' => 15],
            ['col' => 'D', 'val' => 'Volume', 'type' => 'string', 'style' => 15]
        ], 20);

        $builder->addRow('Visual Dashboard', 10, [
            ['col' => 'A', 'val' => 'Sell Items', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $type_counts['Sell Items'], 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => array_keys($cat_counts)[0] ?? 'General', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => array_values($cat_counts)[0] ?? 0, 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 11, [
            ['col' => 'A', 'val' => 'Buy Requests', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $type_counts['Buy Requests'], 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => array_keys($cat_counts)[1] ?? '', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => array_values($cat_counts)[1] ?? 0, 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 12, [
            ['col' => 'A', 'val' => 'Rental Ads', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $type_counts['Rental Ads'], 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => array_keys($cat_counts)[2] ?? '', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => array_values($cat_counts)[2] ?? 0, 'type' => 'number', 'style' => 6]
        ]);

        // Place Chart 1: Donut (Listing Intent) at Col E to H, Row 8 to 23
        $builder->addDoughnutChart(
            'Visual Dashboard',
            'Listing Intent Share (Sell / Buy / Rent)',
            "'Visual Dashboard'!\$A\$10:\$A\$12",
            "'Visual Dashboard'!\$B\$10:\$B\$12",
            array_keys($type_counts),
            array_values($type_counts),
            4, 8, 8, 23
        );

        // Place Chart 2: Column (Top Categories) at Col I to L, Row 8 to 23
        $builder->addColumnChart(
            'Visual Dashboard',
            'Top Categories Inventory Breakdown',
            'Listings',
            "'Visual Dashboard'!\$C\$10:\$C\$12",
            "'Visual Dashboard'!\$D\$10:\$D\$12",
            array_slice(array_keys($cat_counts), 0, 3),
            array_slice(array_values($cat_counts), 0, 3),
            8, 8, 12, 23,
            '059669'
        );

        // Day 1 to 31 Data Table
        $row_a_d = 15;
        $builder->addRow('Visual Dashboard', $row_a_d, [
            ['col' => 'A', 'val' => 'Day of Month', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => $primary_month_label . ' Ads', 'type' => 'string', 'style' => 15],
            ['col' => 'C', 'val' => $is_compare ? $comp_month_label . ' Ads' : 'Daily Postings', 'type' => 'string', 'style' => 15]
        ], 20);

        $aCats = [];
        $aVals1 = [];
        $aVals2 = [];
        for ($d = 1; $d <= 31; $d++) {
            $aCats[] = 'D' . $d;
            $aVals1[] = $a_d1[$d];
            $aVals2[] = $a_d2[$d];
            $rNum = $row_a_d + $d;
            $builder->addRow('Visual Dashboard', $rNum, [
                ['col' => 'A', 'val' => 'D' . $d, 'type' => 'string', 'style' => 5],
                ['col' => 'B', 'val' => $a_d1[$d], 'type' => 'number', 'style' => 6],
                ['col' => 'C', 'val' => $is_compare ? $a_d2[$d] : $a_d1[$d], 'type' => 'number', 'style' => 6]
            ]);
        }

        // Place Chart 3: Day 1 to 31 Daily Dynamics (Comparison or Single) at Col E to L, Row 24 to 45
        if ($is_compare) {
            $builder->addClusteredComparisonChart(
                'Visual Dashboard',
                'Month-over-Month Daily Ad Postings Comparison (Day 1 to 31)',
                "'Visual Dashboard'!\$A\$" . ($row_a_d + 1) . ":\$A\$" . ($row_a_d + 31),
                $aCats,
                [
                    'name' => $primary_month_label . ' (Ads)',
                    'valRange' => "'Visual Dashboard'!\$B\$" . ($row_a_d + 1) . ":\$B\$" . ($row_a_d + 31),
                    'vals' => $aVals1,
                    'color' => '059669' // Emerald
                ],
                [
                    'name' => $comp_month_label . ' (Ads)',
                    'valRange' => "'Visual Dashboard'!\$C\$" . ($row_a_d + 1) . ":\$C\$" . ($row_a_d + 31),
                    'vals' => $aVals2,
                    'color' => '8B5CF6' // Purple
                ],
                4, 24, 12, 45
            );
        } else {
            $builder->addColumnChart(
                'Visual Dashboard',
                'Daily Ad Posting Dynamics (Day 1 to 31)',
                'Ads',
                "'Visual Dashboard'!\$A\$" . ($row_a_d + 1) . ":\$A\$" . ($row_a_d + 31),
                "'Visual Dashboard'!\$B\$" . ($row_a_d + 1) . ":\$B\$" . ($row_a_d + 31),
                $aCats,
                $aVals1,
                4, 24, 12, 45,
                '059669'
            );
        }

        // --- SHEET 2: INVENTORY DIRECTORY ---
        $builder->addSheet('Inventory Directory', [
            1 => 12, 2 => 28, 3 => 20, 4 => 14, 5 => 16, 6 => 14, 7 => 12, 8 => 20, 9 => 22
        ]);

        $builder->addRow('Inventory Directory', 1, [
            ['col' => 'A', 'val' => 'Ad ID', 'type' => 'string', 'style' => 3],
            ['col' => 'B', 'val' => 'Listing Title', 'type' => 'string', 'style' => 3],
            ['col' => 'C', 'val' => 'Category', 'type' => 'string', 'style' => 3],
            ['col' => 'D', 'val' => 'Type', 'type' => 'string', 'style' => 3],
            ['col' => 'E', 'val' => 'Price (INR)', 'type' => 'string', 'style' => 3],
            ['col' => 'F', 'val' => 'Status', 'type' => 'string', 'style' => 3],
            ['col' => 'G', 'val' => 'Views', 'type' => 'string', 'style' => 3],
            ['col' => 'H', 'val' => 'Seller Name', 'type' => 'string', 'style' => 3],
            ['col' => 'I', 'val' => 'Posted Date', 'type' => 'string', 'style' => 3]
        ], 24);

        $pRowIdx = 2;
        foreach ($prod_list as $p) {
            $builder->addRow('Inventory Directory', $pRowIdx++, [
                ['col' => 'A', 'val' => '#' . $p['id'], 'type' => 'string', 'style' => 4],
                ['col' => 'B', 'val' => $p['title'], 'type' => 'string', 'style' => 4],
                ['col' => 'C', 'val' => $p['category_name'], 'type' => 'string', 'style' => 5],
                ['col' => 'D', 'val' => strtoupper($p['type']), 'type' => 'string', 'style' => 5],
                ['col' => 'E', 'val' => (float)$p['price'], 'type' => 'currency', 'style' => 8],
                ['col' => 'F', 'val' => ucfirst($p['status']), 'type' => 'string', 'style' => $p['status'] === 'active' ? 13 : 15],
                ['col' => 'G', 'val' => (int)$p['views'], 'type' => 'number', 'style' => 6],
                ['col' => 'H', 'val' => $p['username'] . ' (' . $p['phone_number'] . ')', 'type' => 'string', 'style' => 4],
                ['col' => 'I', 'val' => $p['created_at'], 'type' => 'string', 'style' => 5]
            ]);
        }
        $builder->setAutoFilter('Inventory Directory', 'A1:I' . max(2, $pRowIdx - 1));
    }

    // =========================================================================
    // 4. MODERATION & FLAGGED REPORT MODULE
    // =========================================================================
    elseif ($export_type === 'moderation') {
        $stmt_rep = $pdo->query("
            SELECT r.*, p.title as product_title, p.status as product_status, u1.username as reporter_name, u2.username as owner_name, u2.phone_number as owner_phone
            FROM reports r 
            JOIN products p ON r.product_id = p.id
            JOIN users u1 ON r.reported_by_user_id = u1.id
            JOIN users u2 ON p.user_id = u2.id
            ORDER BY r.status ASC, r.created_at DESC
        ");
        $rep_rows = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);
        $m_tot = count($rep_rows);
        $m_pnd = 0;
        $m_res = 0;
        $m_reasons = [];

        foreach ($rep_rows as $rr) {
            if ($rr['status'] === 'pending') $m_pnd++;
            else $m_res++;
            $rsn = trim($rr['reason'] ?: 'Unspecified');
            $m_reasons[$rsn] = ($m_reasons[$rsn] ?? 0) + 1;
        }

        // --- SHEET 1: VISUAL DASHBOARD ---
        $builder->addSheet('Visual Dashboard', [
            1 => 32, 2 => 20, 3 => 20, 4 => 18, 5 => 16, 6 => 16, 7 => 14, 8 => 14, 9 => 14, 10 => 14
        ]);

        $builder->addRow('Visual Dashboard', 1, [
            ['col' => 'A', 'val' => 'ENTEANGADI MARKETPLACE - MODERATION & SAFETY REPORT', 'type' => 'string', 'style' => 1]
        ], 34);
        $builder->mergeCells('Visual Dashboard', 'A1:E1');

        $builder->addRow('Visual Dashboard', 2, [
            ['col' => 'A', 'val' => "Generated: " . date('Y-m-d H:i:s') . " | Total Cases Audited: {$m_tot}", 'type' => 'string', 'style' => 5]
        ], 18);

        // Section 1 Header: KPIs
        $builder->addRow('Visual Dashboard', 4, [
            ['col' => 'A', 'val' => 'SECTION 1: MODERATION AUDIT & RESOLUTION OVERVIEW', 'type' => 'string', 'style' => 2]
        ], 24);
        $builder->mergeCells('Visual Dashboard', 'A4:E4');

        $builder->addRow('Visual Dashboard', 5, [
            ['col' => 'A', 'val' => 'Pending Review Cases', 'type' => 'string', 'style' => 3],
            ['col' => 'B', 'val' => 'Resolved Cases', 'type' => 'string', 'style' => 3],
            ['col' => 'C', 'val' => 'Total Flags Filed', 'type' => 'string', 'style' => 3],
            ['col' => 'D', 'val' => 'Resolution Rate', 'type' => 'string', 'style' => 3]
        ], 22);

        $res_rate = $m_tot > 0 ? round($m_res / $m_tot, 4) : 1.0;
        $builder->addRow('Visual Dashboard', 6, [
            ['col' => 'A', 'val' => $m_pnd, 'type' => 'number', 'style' => 6],
            ['col' => 'B', 'val' => $m_res, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => $m_tot, 'type' => 'number', 'style' => 6],
            ['col' => 'D', 'val' => $res_rate, 'type' => 'percent', 'style' => 7]
        ], 22);

        // Reference tables for Charts
        $builder->addRow('Visual Dashboard', 9, [
            ['col' => 'A', 'val' => 'Case Status', 'type' => 'string', 'style' => 15],
            ['col' => 'B', 'val' => 'Count', 'type' => 'string', 'style' => 15],
            ['col' => 'C', 'val' => 'Violation Reason', 'type' => 'string', 'style' => 15],
            ['col' => 'D', 'val' => 'Incidents', 'type' => 'string', 'style' => 15]
        ], 20);

        $builder->addRow('Visual Dashboard', 10, [
            ['col' => 'A', 'val' => 'Pending Review', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $m_pnd, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => array_keys($m_reasons)[0] ?? 'General', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => array_values($m_reasons)[0] ?? 0, 'type' => 'number', 'style' => 6]
        ]);
        $builder->addRow('Visual Dashboard', 11, [
            ['col' => 'A', 'val' => 'Resolved Cases', 'type' => 'string', 'style' => 4],
            ['col' => 'B', 'val' => $m_res, 'type' => 'number', 'style' => 6],
            ['col' => 'C', 'val' => array_keys($m_reasons)[1] ?? '', 'type' => 'string', 'style' => 5],
            ['col' => 'D', 'val' => array_values($m_reasons)[1] ?? 0, 'type' => 'number', 'style' => 6]
        ]);

        // Place Chart 1: Donut (Status) at Col E to H, Row 8 to 23
        $builder->addDoughnutChart(
            'Visual Dashboard',
            'Moderation Case Status Breakdown',
            "'Visual Dashboard'!\$A\$10:\$A\$11",
            "'Visual Dashboard'!\$B\$10:\$B\$11",
            ['Pending Review', 'Resolved Cases'],
            [$m_pnd, $m_res],
            4, 8, 8, 23
        );

        // Place Chart 2: Column (Reasons) at Col I to L, Row 8 to 23
        if (!empty($m_reasons)) {
            $builder->addColumnChart(
                'Visual Dashboard',
                'Report Violation Reasons Breakdown',
                'Incidents',
                "'Visual Dashboard'!\$C\$10:\$C\$11",
                "'Visual Dashboard'!\$D\$10:\$D\$11",
                array_slice(array_keys($m_reasons), 0, 2),
                array_slice(array_values($m_reasons), 0, 2),
                8, 8, 12, 23,
                'EF4444' // Red
            );
        }

        // --- SHEET 2: MODERATION AUDIT LOG ---
        $builder->addSheet('Moderation Audit', [
            1 => 12, 2 => 14, 3 => 26, 4 => 26, 5 => 18, 6 => 22, 7 => 16, 8 => 20
        ]);

        $builder->addRow('Moderation Audit', 1, [
            ['col' => 'A', 'val' => 'Report ID', 'type' => 'string', 'style' => 3],
            ['col' => 'B', 'val' => 'Product ID', 'type' => 'string', 'style' => 3],
            ['col' => 'C', 'val' => 'Listing Title', 'type' => 'string', 'style' => 3],
            ['col' => 'D', 'val' => 'Report Reason', 'type' => 'string', 'style' => 3],
            ['col' => 'E', 'val' => 'Reporter Name', 'type' => 'string', 'style' => 3],
            ['col' => 'F', 'val' => 'Seller / Owner', 'type' => 'string', 'style' => 3],
            ['col' => 'G', 'val' => 'Status', 'type' => 'string', 'style' => 3],
            ['col' => 'H', 'val' => 'Report Date', 'type' => 'string', 'style' => 3]
        ], 24);

        $mRowIdx = 2;
        foreach ($rep_rows as $rr) {
            $is_pnd = ($rr['status'] === 'pending');
            $builder->addRow('Moderation Audit', $mRowIdx++, [
                ['col' => 'A', 'val' => '#' . $rr['id'], 'type' => 'string', 'style' => 4],
                ['col' => 'B', 'val' => '#' . $rr['product_id'], 'type' => 'string', 'style' => 4],
                ['col' => 'C', 'val' => $rr['product_title'], 'type' => 'string', 'style' => 4],
                ['col' => 'D', 'val' => $rr['reason'] ?: 'Unspecified', 'type' => 'string', 'style' => 5],
                ['col' => 'E', 'val' => $rr['reporter_name'], 'type' => 'string', 'style' => 4],
                ['col' => 'F', 'val' => $rr['owner_name'] . ' (' . $rr['owner_phone'] . ')', 'type' => 'string', 'style' => 4],
                ['col' => 'G', 'val' => $is_pnd ? 'Pending Review' : 'Resolved', 'type' => 'string', 'style' => $is_pnd ? 14 : 13],
                ['col' => 'H', 'val' => $rr['created_at'], 'type' => 'string', 'style' => 5]
            ]);
        }
        $builder->setAutoFilter('Moderation Audit', 'A1:H' . max(2, $mRowIdx - 1));
    }

    if ($savePathOnly !== null) {
        $builder->saveToFile($savePathOnly);
        return $clean_filename;
    }

    // Deliver the .xlsx file
    $builder->download($clean_filename);
}
