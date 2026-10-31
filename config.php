<?php
// Configure secure session cookie params before starting session
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($httpHost !== '' && strpos($httpHost, 'localhost') === false && strpos($httpHost, '127.0.0.1') === false) {
        $secure = true;
    }

    if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(0, '/; SameSite=Lax', '', $secure, true);
    }
}
session_start();

$is_preload_req = !empty($_SERVER['HTTP_X_ENTEANGADI_PRELOAD'])
    || (($_SERVER['HTTP_PURPOSE'] ?? '') === 'prefetch')
    || (($_SERVER['HTTP_SEC_PURPOSE'] ?? '') === 'prefetch');

// Prevent reverse-proxy and CDN caches from serving stale dynamic PHP responses
if (!headers_sent()) {
    header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    header("X-Accel-Expires: 0");
    header("X-LiteSpeed-Cache-Control: no-cache");
    if (isset($_COOKIE['wordpress_logged_in_1'])) {
        header("Set-Cookie: wordpress_logged_in_1=; Path=/; Max-Age=0; SameSite=Lax", false);
    }
}

if (file_exists(__DIR__ . '/config_local.php')) {
    include_once __DIR__ . '/config_local.php';
}
if (!defined('GROQ_API_KEY')) {
    define('GROQ_API_KEY', '');
}

// Set base URL dynamically
if (!isset($base_url)) {
    $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_url = ($current_dir === '/' || $current_dir === '.') ? '' : $current_dir;
    $subDirs = ['user', 'admin', 'guest', 'api', 'includes', 'cron'];
    if (in_array(basename($base_url), $subDirs, true)) {
        $base_url = dirname($base_url);
    }
    if (in_array(basename($base_url), $subDirs, true)) {
        $base_url = dirname($base_url);
    }
    $base_url = str_replace('\\', '/', $base_url);
    if ($base_url === '/' || $base_url === '.') {
        $base_url = '';
    }
}

if (!function_exists('enteangadi_set_cookie')) {
    function enteangadi_set_cookie($name, $value, $expiry)
    {
        $secure = (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $is_local = $host !== '' && (
            strpos($host, 'localhost') !== false ||
            strpos($host, '127.0.0.1') !== false ||
            strpos($host, '10.') === 0 ||
            strpos($host, '192.168.') !== false ||
            preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host)
        );
        if (!$is_local && $host !== '') {
            $secure = true;
        }
        if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
            setcookie($name, $value, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } else {
            setcookie($name, $value, $expiry, '/; SameSite=Lax', '', $secure, true);
        }
    }
}

if (!function_exists('compressProductImage')) {
    function compressProductImage($source_path, $quality = 30)
    {
        $abs_path = __DIR__ . '/' . $source_path;
        if (!file_exists($abs_path)) return false;
        $info = @getimagesize($abs_path);
        if (!$info) return false;
        if ($info['mime'] === 'image/jpeg') $image = @imagecreatefromjpeg($abs_path);
        elseif ($info['mime'] === 'image/gif') $image = @imagecreatefromgif($abs_path);
        elseif ($info['mime'] === 'image/png') $image = @imagecreatefrompng($abs_path);
        else return false;
        if (!$image) return false;
        imagejpeg($image, $abs_path, $quality);
        imagedestroy($image);
        return true;
    }
}

if (!function_exists('has_permission')) {
    function has_permission($module)
    {
        if (!isset($_SESSION['admin_permissions'])) return false;
        if ($_SESSION['admin_permissions'] === '*') return true;
        return in_array($module, explode(',', $_SESSION['admin_permissions']), true);
    }
}

// Restore user location from cookie if session is missing
if (!isset($_SESSION['user_location']) && isset($_COOKIE['user_location'])) {
    $cookie_loc = json_decode($_COOKIE['user_location'], true);
    if ($cookie_loc && isset($cookie_loc['name'], $cookie_loc['lat'], $cookie_loc['lng'])) {
        $_SESSION['user_location'] = [
            'name' => $cookie_loc['name'],
            'lat' => $cookie_loc['lat'],
            'lng' => $cookie_loc['lng']
        ];
    }
}

if (!defined('SIGHTENGINE_USER')) define('SIGHTENGINE_USER', '');
if (!defined('SIGHTENGINE_SECRET')) define('SIGHTENGINE_SECRET', '');
if (!defined('MODERATION_STRICTNESS')) define('MODERATION_STRICTNESS', 0.70);

// CORS headers for Capacitor / Vite clients
if (!headers_sent()) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: " . ($origin !== '*' ? $origin : '*'));
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-User-Id, X-Session-Token, X-Enteangadi-Preload");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/includes/helpers.php';

$defaultHost = (PHP_OS_FAMILY === 'Windows') ? '127.0.0.1' : 'localhost';
$host = defined('DB_HOST') ? ((PHP_OS_FAMILY === 'Windows' && DB_HOST === 'localhost') ? '127.0.0.1' : DB_HOST) : $defaultHost;
$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';
$dbname = defined('DB_NAME') ? DB_NAME : 'enteangadi';

try {
    $pdoOpts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $pdoOpts);
    } catch (PDOException $connErr) {
        $errCode = (int)($connErr->errorInfo[1] ?? 0);
        if ($errCode === 1049) {
            // Database not created yet: create & select it
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, $pdoOpts);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");
            @unlink(sys_get_temp_dir() . '/enteangadi_schema_v4.lock');
        } elseif ($host === 'localhost') {
            $host = '127.0.0.1';
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $pdoOpts);
            } catch (PDOException $e2) {
                if ((int)($e2->errorInfo[1] ?? 0) === 1049) {
                    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, $pdoOpts);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `$dbname`");
                    @unlink(sys_get_temp_dir() . '/enteangadi_schema_v4.lock');
                } else {
                    throw $e2;
                }
            }
        } else {
            throw $connErr;
        }
    }

    // Run schema migrations & index setup only once (or when lock missing), and maintenance hourly
    $schemaLock = sys_get_temp_dir() . '/enteangadi_schema_v4.lock';
    $maintLock = sys_get_temp_dir() . '/enteangadi_maint_v3.lock';
    $needSchema = !file_exists($schemaLock) || isset($_GET['force_schema_sync']);
    $needMaint = !$is_preload_req && (!file_exists($maintLock) || (time() - @filemtime($maintLock)) >= 3600);

    if ($needSchema || $needMaint) {
        require_once __DIR__ . '/includes/schema_maintenance.php';
        if ($needSchema) {
            runEnteangadiSchemaSetup($pdo, __DIR__);
        }
        if ($needMaint) {
            runEnteangadiHourlyMaintenance($pdo, __DIR__);
        }
    }

    // Refresh admin permissions at most once per minute
    if (isset($_SESSION['admin_id']) && (!isset($_SESSION['_adm_perm_ts']) || (time() - $_SESSION['_adm_perm_ts']) > 60)) {
        $stmt_admin = $pdo->prepare("SELECT permissions FROM users WHERE id = ?");
        $stmt_admin->execute([$_SESSION['admin_id']]);
        $_SESSION['admin_permissions'] = $stmt_admin->fetchColumn();
        $_SESSION['_adm_perm_ts'] = time();
    }

    // Lightweight visit logger (skips background preloads, API calls, assets, and throttles to 30s)
    if (!$is_preload_req && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        if (!isset($_SESSION['last_visit_log']) || (time() - $_SESSION['last_visit_log']) >= 30) {
            $req_uri = $_SERVER['REQUEST_URI'] ?? '';
            $skipVisit = preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|webp|ico|woff|woff2|ttf|map|json)$/i', $req_uri)
                || strpos($req_uri, 'cron/') !== false
                || strpos($req_uri, 'sync_') !== false
                || strpos($req_uri, '/admin/') !== false
                || strpos($req_uri, '/scratch/') !== false
                || strpos($req_uri, '/api/') !== false
                || strpos($req_uri, 'api_') !== false;
            if (!$skipVisit) {
                $_SESSION['last_visit_log'] = time();
                try {
                    $v_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                    if (strpos($v_ip, ',') !== false) $v_ip = trim(explode(',', $v_ip)[0]);
                    $v_uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null);
                    $pdo->prepare("INSERT INTO site_visits (visit_date, ip_address, user_id, page_url, user_agent) VALUES (?, ?, ?, ?, ?)")
                        ->execute([date('Y-m-d'), $v_ip, $v_uid, substr($req_uri, 0, 250), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250)]);
                } catch (Exception $e_sv) {
                }
            }
        }
    }

    // Token-Based Header Auto-Login for mobile WebView clients
    if (!isset($_SESSION['user_id'])) {
        $header_user_id = $_SERVER['HTTP_X_USER_ID'] ?? ($_SERVER['REDIRECT_HTTP_X_USER_ID'] ?? '');
        $header_token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? ($_SERVER['REDIRECT_HTTP_X_SESSION_TOKEN'] ?? '');
        if ($header_user_id !== '' && $header_token !== '') {
            $stmt = $pdo->prepare("SELECT id, username, role, session_token, last_activity, created_at FROM users WHERE id = ? AND session_token = ? LIMIT 1");
            $stmt->execute([$header_user_id, $header_token]);
            $user = $stmt->fetch();
            if ($user) {
                $last_act = $user['last_activity'] ? strtotime($user['last_activity']) : strtotime($user['created_at']);
                if ((time() - $last_act) <= (30 * 86400)) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['session_token'] = $user['session_token'];
                    $_SESSION['_tok_verified_at'] = time();
                    if (!$user['last_activity'] || (time() - strtotime($user['last_activity'])) > 900) {
                        $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$user['id']]);
                    }
                }
            }
        }
    }

    // Cookie-Based Server-Side Auto-Login
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['enteangadi_remember_user'], $_COOKIE['enteangadi_remember_token'])) {
        $stmt = $pdo->prepare("SELECT id, username, role, session_token, last_activity, created_at FROM users WHERE id = ? AND session_token = ? LIMIT 1");
        $stmt->execute([$_COOKIE['enteangadi_remember_user'], $_COOKIE['enteangadi_remember_token']]);
        $user = $stmt->fetch();
        if ($user) {
            $last_act = $user['last_activity'] ? strtotime($user['last_activity']) : strtotime($user['created_at']);
            if ((time() - $last_act) <= (30 * 86400)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['session_token'] = $user['session_token'];
                $_SESSION['_tok_verified_at'] = time();
                $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$user['id']]);
                enteangadi_set_cookie('enteangadi_remember_user', $user['id'], time() + 30 * 86400);
                enteangadi_set_cookie('enteangadi_remember_token', $user['session_token'], time() + 30 * 86400);
            } else {
                enteangadi_set_cookie('enteangadi_remember_user', '', time() - 3600);
                enteangadi_set_cookie('enteangadi_remember_token', '', time() - 3600);
            }
        } else {
            enteangadi_set_cookie('enteangadi_remember_user', '', time() - 3600);
            enteangadi_set_cookie('enteangadi_remember_token', '', time() - 3600);
        }
    }

    // Session token & 30-day inactivity check (re-verified against DB once per 60s instead of every API/page hit)
    if (isset($_SESSION['user_id']) && !isset($_SESSION['is_admin_session'])) {
        $now = time();
        if (!isset($_SESSION['_tok_verified_at']) || ($now - $_SESSION['_tok_verified_at']) >= 60) {
            $stmt = $pdo->prepare("SELECT session_token, last_activity, created_at FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user_record = $stmt->fetch();

            if (!$user_record || (isset($user_record['session_token']) && (($_SESSION['session_token'] ?? '') !== $user_record['session_token']))) {
                session_destroy();
                enteangadi_set_cookie('enteangadi_remember_user', '', $now - 3600);
                enteangadi_set_cookie('enteangadi_remember_token', '', $now - 3600);
                $current_page = basename($_SERVER['PHP_SELF'] ?? '');
                if ($current_page !== 'login.php' && $current_page !== 'index.php') {
                    $curDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
                    $prefix = in_array($curDir, ['user', 'admin', 'guest'], true) ? '../' : '';
                    header("Location: {$prefix}login.php?msg=account_inactive");
                    exit;
                }
            } else {
                $last_act = $user_record['last_activity'] ? strtotime($user_record['last_activity']) : strtotime($user_record['created_at']);
                if (($now - $last_act) > (30 * 86400)) {
                    $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?")->execute([$_SESSION['user_id']]);
                    session_destroy();
                    enteangadi_set_cookie('enteangadi_remember_user', '', $now - 3600);
                    enteangadi_set_cookie('enteangadi_remember_token', '', $now - 3600);
                    $curDir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
                    $prefix = in_array($curDir, ['user', 'admin', 'guest'], true) ? '../' : '';
                    header("Location: {$prefix}login.php?msg=session_expired");
                    exit;
                }
                $_SESSION['_tok_verified_at'] = $now;
                if (!$user_record['last_activity'] || ($now - strtotime($user_record['last_activity'])) > 900) {
                    $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$_SESSION['user_id']]);
                    enteangadi_set_cookie('enteangadi_remember_user', $_SESSION['user_id'], $now + 30 * 86400);
                    enteangadi_set_cookie('enteangadi_remember_token', $_SESSION['session_token'], $now + 30 * 86400);
                }
            }
        }
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Intercept unauthenticated visits to protected /user/ pages to restore mobile WebView session seamlessly
if (!isset($_SESSION['user_id'])) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    $is_user_page = (strpos($request_uri, '/user/') !== false);
    $is_api_call = (strpos($current_page, 'api_') === 0 || $current_page === 'toggle_wishlist.php' || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    if ($is_user_page && !$is_api_call) {
        $cssVer = @filemtime(__DIR__ . '/assets/css/style.css') ?: '2.0';
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Authenticating...</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <link rel="stylesheet" href="../assets/css/style.css?v=<?= $cssVer ?>">
        </head>
        <body>
            <div id="loader-wrapper" class="loader-active">
                <div class="loader-emblem">
                    <span class="loader-ring loader-ring-1"></span>
                    <span class="loader-ring loader-ring-2"></span>
                    <img src="../uploads/logo/logo_enteangadi.png" alt="Enteangadi" class="loader-logo-img" onerror="this.style.display='none'">
                </div>
                <div class="loader-progress-wrap">
                    <div class="loader-percent" id="auth-pct">12%</div>
                    <div class="loader-bar-track"><div class="loader-bar-fill" id="auth-bar" style="width:12%"></div></div>
                </div>
                <div class="loader-location-status">Restoring session...</div>
            </div>
            <script>
                (function () {
                    let p = 12;
                    const pct = document.getElementById('auth-pct'), bar = document.getElementById('auth-bar');
                    const iv = setInterval(() => {
                        if (p < 90) { p += Math.max(1, Math.round((92 - p) * 0.14)); if (pct) pct.textContent = p + '%'; if (bar) bar.style.width = p + '%'; }
                    }, 60);
                    const uid = localStorage.getItem('enteangadi_user_id');
                    const token = localStorage.getItem('enteangadi_session_token');
                    const fallbackUrl = window.location.pathname.endsWith('/user/index.php') ? ('../guest/index.php' + window.location.search) : '../login.php';
                    const done = (url) => { clearInterval(iv); if (pct) pct.textContent = '100%'; if (bar) bar.style.width = '100%'; window.location.replace(url); };
                    if (uid && token) {
                        fetch('../api/auto_login.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'user_id=' + encodeURIComponent(uid) + '&session_token=' + encodeURIComponent(token)
                        })
                            .then(r => r.json())
                            .then(d => {
                                if (d.success) { clearInterval(iv); if (pct) pct.textContent = '100%'; if (bar) bar.style.width = '100%'; location.reload(); }
                                else { localStorage.removeItem('enteangadi_user_id'); localStorage.removeItem('enteangadi_session_token'); done(fallbackUrl); }
                            })
                            .catch(() => done(fallbackUrl));
                    } else {
                        done(fallbackUrl);
                    }
                })();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}
