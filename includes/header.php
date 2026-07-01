<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Set base URL dynamically
$current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url = ($current_dir == '/' || $current_dir == '.') ? '' : $current_dir;
// If we are in 'user' or 'admin' subfolders, we need to go up
if (basename($base_url) == 'user' || basename($base_url) == 'admin' || basename($base_url) == 'guest') {
    $base_url = dirname($base_url);
}
if ($base_url == '/')
    $base_url = '';

// Handle Language Change
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = in_array($_GET['lang'], ['en', 'ml']) ? $_GET['lang'] : 'en';
    // Redirect to same page without lang param to clean URL
    $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
    $query = $_GET;
    unset($query['lang']);
    if (!empty($query)) {
        $clean_url .= '?' . http_build_query($query);
    }
    header("Location: $clean_url");
    exit;
}

require_once __DIR__ . '/helpers.php';

// Fetch App Settings (Logo, Tagline)
try {
    $stmt = $pdo->query("SELECT * FROM app_settings");
    $settings_raw = $stmt->fetchAll();
    $app_settings = [];
    foreach ($settings_raw as $s) {
        $app_settings[$s['setting_key']] = $s['setting_value'];
    }
} catch (Exception $e) {
    // Fallback if table doesn't exist yet
    $app_settings = [
        'app_logo' => '',
        'app_tagline' => 'Your Local Marketplace'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= __('app_name') ?> - <?= __('tagline') ?></title>
    <meta name="description" content="Buy and sell anything locally with <?= __('app_name') ?>.">
    
    <!-- Open Graph / Facebook Meta Tags -->
    <?php if (isset($og_title)): ?>
        <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>" />
    <?php endif; ?>
    <?php if (isset($og_desc)): ?>
        <meta property="og:description" content="<?= htmlspecialchars($og_desc) ?>" />
    <?php endif; ?>
    <?php if (isset($og_image)): ?>
        <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>" />
    <?php endif; ?>
    <?php if (isset($og_url)): ?>
        <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>" />
    <?php endif; ?>
    <meta property="og:type" content="website" />
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css?v=1.3">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        const EnteangadiConfig = {
            baseUrl: '<?= $base_url ?>',
            appLogo: '<?= !empty($app_settings['app_logo']) ? htmlspecialchars($app_settings['app_logo']) : 'uploads/logo/logo_1778137117.jpg' ?>',
            hasLocation: <?= isset($_SESSION['user_location']) ? 'true' : 'false' ?>,
            location: <?= isset($_SESSION['user_location']) ? json_encode($_SESSION['user_location']) : 'null' ?>,
            isLoggedIn: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>
        };

        // Intercept global fetch to ensure session credentials (cookies) are always included for local/same-site calls.
        // This solves both the geolocation infinite reload loop and the repeating tutorial welcome screen on mobile.
        (function () {
            const originalFetch = window.fetch;
            window.fetch = function (resource, init) {
                init = init || {};
                let isLocal = false;
                if (typeof resource === 'string') {
                    if (resource.startsWith('/') || !resource.startsWith('http') || resource.includes(window.location.host)) {
                        isLocal = true;
                    }
                }
                if (isLocal) {
                    init.credentials = 'include';
                }
                return originalFetch(resource, init);
            };
        })();

        // Session Persistence Management (Auto-Login & Logout cleanups)
        (function () {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('logged_out')) {
                // Clear persistent session storage on manual logout
                localStorage.removeItem('enteangadi_user_id');
                localStorage.removeItem('enteangadi_session_token');

                // Clean the query parameters from the URL
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
                return;
            }

            if (EnteangadiConfig.isLoggedIn) {
                // Store active credentials dynamically
                localStorage.setItem('enteangadi_user_id', '<?= $_SESSION['user_id'] ?? '' ?>');
                localStorage.setItem('enteangadi_session_token', '<?= $_SESSION['session_token'] ?? '' ?>');
            } else {
                // Auto-login from saved persistent state if PHP session is guest
                const savedUserId = localStorage.getItem('enteangadi_user_id');
                const savedToken = localStorage.getItem('enteangadi_session_token');
                if (savedUserId && savedToken) {
                    // Temporarily hide body content until auto-login redirects/completes
                    const style = document.createElement('style');
                    style.id = 'autologin-hide-body';
                    style.innerHTML = 'body { display: none !important; }';
                    document.head.appendChild(style);

                    fetch(EnteangadiConfig.baseUrl + '/api/auto_login.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'user_id=' + encodeURIComponent(savedUserId) + '&session_token=' + encodeURIComponent(savedToken)
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                setTimeout(() => {
                                    const pathname = window.location.pathname;
                                    if (pathname.includes('/guest/product.php')) {
                                        window.location.href = EnteangadiConfig.baseUrl + '/product.php' + window.location.search;
                                    } else if (pathname.includes('/guest/')) {
                                        window.location.href = EnteangadiConfig.baseUrl + '/user/index.php' + window.location.search;
                                    } else {
                                        location.reload();
                                    }
                                }, 100);
                            } else {
                                // Token is invalid/expired
                                localStorage.removeItem('enteangadi_user_id');
                                localStorage.removeItem('enteangadi_session_token');
                                const hStyle = document.getElementById('autologin-hide-body');
                                if (hStyle) hStyle.remove();
                            }
                        })
                        .catch(e => {
                            console.error("Auto-login failed:", e);
                            const hStyle = document.getElementById('autologin-hide-body');
                            if (hStyle) hStyle.remove();
                        });
                }
            }
        })();

        // Theme initialization
        (function () {
            const savedTheme = localStorage.getItem('enteangadi-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();

        // Service Worker registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= $base_url ?>/service-worker.js')
                    .then(reg => console.log('Service Worker registered:', reg.scope))
                    .catch(err => console.warn('Service Worker registration failed:', err));
            });
        }
    </script>
    <script src="<?= $base_url ?>/capacitor.js"></script>
    <script src="<?= $base_url ?>/assets/js/location.js" defer></script>
    <script src="<?= $base_url ?>/assets/js/app-native.js" defer></script>
    <!-- Instant.page for instant preloading of pages on hover/touchstart -->
    <script src="https://cdn.jsdelivr.net/npm/instant.page@5.2.0/instantpage.js" type="module"></script>

</head>

<body>

    <!-- Application Loader -->
    <div id="loader-wrapper">
        <?php if (!empty($app_settings['app_logo'])): ?>
            <img src="<?= $base_url ?>/<?= htmlspecialchars($app_settings['app_logo']) ?>" alt="Logo"
                class="loader-logo-img">
        <?php else: ?>
            <div class="loader-logo"><?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?></div>
        <?php endif; ?>

        <?php if (!empty($app_settings['app_tagline'])): ?>
            <div class="loader-tagline"><?= htmlspecialchars($app_settings['app_tagline']) ?></div>
        <?php endif; ?>

        <div class="loader-location-status" id="loader-location-status">
            <i class="fa fa-spinner fa-spin"></i> Detecting location...
        </div>
    </div>
    <script>
        // Loader / Splash Screen logic
        (function () {
            const loader = document.getElementById('loader-wrapper');
            if (!loader) return;

            // If we have already loaded the app in this session, or if we are on a login/register or guest sub-page (but NOT guest/index.php), hide the loader immediately
            const isGuestOrAuthPage = (window.location.pathname.includes('/guest/') && !window.location.pathname.includes('/guest/index.php')) ||
                window.location.pathname.includes('login.php') ||
                window.location.pathname.includes('register.php');

            let hasLoadedBefore = false;
            try {
                hasLoadedBefore = sessionStorage.getItem('hasLoadedBefore') === 'true';
            } catch (e) {
                console.warn('sessionStorage is not accessible:', e);
            }

            if (hasLoadedBefore || isGuestOrAuthPage) {
                loader.style.display = 'none';
                window.hideLoader = () => { };
                window.hideLoaderInstantly = () => { };
                return;
            }

            // Add a class for the "entrance" animation
            loader.classList.add('loader-active');

            window.hideLoader = () => {
                setTimeout(() => {
                    loader.classList.add('loader-hide');
                    setTimeout(() => {
                        loader.style.display = 'none';
                        try {
                            sessionStorage.setItem('hasLoadedBefore', 'true');
                        } catch (e) { }
                    }, 800); // Wait for fade out
                }, 800); // Premium brief display pause so user can read detected city
            };

            window.hideLoaderInstantly = () => {
                loader.style.display = 'none';
                try {
                    sessionStorage.setItem('hasLoadedBefore', 'true');
                } catch (e) { }
            };

            // Hide loader naturally on window load event
            window.addEventListener('load', () => {
                if (loader.style.display !== 'none') {
                    window.hideLoader();
                }
            });

            // Safeguard fallback: Always hide loader after maximum 2.5s to ensure app access
            setTimeout(() => {
                if (loader.style.display !== 'none') {
                    window.hideLoaderInstantly();
                }
            }, 2500);
        })();

        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('enteangadi-theme', newTheme);

            // Update icon if needed
            updateThemeIcon(newTheme);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('theme-icon');
            if (icon) {
                icon.className = theme === 'dark' ? 'fa fa-sun' : 'fa fa-moon';
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            updateThemeIcon(document.documentElement.getAttribute('data-theme'));
            fetchNotifications();
            setInterval(fetchNotifications, 30000); // Poll every 30s
        });

        async function fetchNotifications() {
            try {
                const response = await fetch('<?= $base_url ?>/api/notifications.php');
                const data = await response.json();
                const badge = document.getElementById('notification-badge');
                const badgeMobile = document.getElementById('notification-badge-mobile');
                const desktopBadge = document.getElementById('desktop-unread-badge');

                if (badge) {
                    badge.innerText = data.unread_count;
                    badge.style.display = data.unread_count > 0 ? 'block' : 'none';
                }
                if (badgeMobile) {
                    badgeMobile.innerText = data.unread_count;
                    badgeMobile.style.display = data.unread_count > 0 ? 'block' : 'none';
                }
                if (desktopBadge) {
                    desktopBadge.innerText = data.unread_count;
                    desktopBadge.style.display = data.unread_count > 0 ? 'block' : 'none';
                }
                renderNotifications(data.notifications);
            } catch (e) { }
        }

        function renderNotifications(notifications) {
            const list = document.getElementById('notification-list');
            if (!notifications || notifications.length === 0) {
                list.innerHTML = '<div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 20px;">No new notifications</div>';
                return;
            }
            list.innerHTML = notifications.map(n => `
                <div onclick="markRead(${n.id}, '${n.link}')" style="padding: 10px; border-radius: 8px; background: ${n.is_read == 0 ? 'var(--background)' : 'transparent'}; cursor: pointer; border-bottom: 1px solid var(--border-color); position: relative;">
                    <div style="font-size: 13px; font-weight: ${n.is_read == 0 ? '600' : '400'}; color: var(--text-dark);">${n.message}</div>
                    <div style="font-size: 10px; color: var(--text-muted); margin-top: 4px;">${new Date(n.created_at).toLocaleDateString()}</div>
                </div>
            `).join('');
        }

        function toggleNotifications() {
            const dropdown = document.getElementById('notification-dropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }

        async function markRead(id, link) {
            await fetch(`<?= $base_url ?>/api/notifications.php?action=mark_read&id=${id}`);
            if (link && link !== 'null') window.location.href = link;
            else fetchNotifications();
        }

        async function markAllRead() {
            await fetch(`<?= $base_url ?>/api/notifications.php?action=mark_read`);
            fetchNotifications();
        }
    </script>


    <div class="main-sticky-header">
        <header>

            <a href="<?= $base_url ?>/index.php" class="logo">
                <?php if (!empty($app_settings['app_logo'])): ?>
                    <img src="<?= $base_url ?>/<?= htmlspecialchars($app_settings['app_logo']) ?>" alt="Enteangadi"
                        style="max-height: 40px; display: block;">
                <?php else: ?>
                    <?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?>
                <?php endif; ?>
            </a>
            <nav class="desktop-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= $base_url ?>/user/inbox.php" title="Inbox"
                        style="position: relative; text-decoration: none; font-size: 20px; color: var(--text-dark); margin-right: 8px;">
                        <i class="fa fa-comments"></i>
                        <span id="desktop-unread-badge"
                            style="display: none; position: absolute; top: -6px; right: -8px; background: var(--danger); color: white; border-radius: 50%; padding: 2px 5px; font-size: 10px; font-weight: bold; line-height: 1;">0</span>
                    </a>

                    <div class="notification-wrapper" style="position: relative; margin-right: 8px;">
                        <a href="javascript:void(0)" onclick="toggleNotifications()" title="Notifications"
                            style="text-decoration: none; font-size: 20px; color: var(--text-dark);">
                            <i class="fa fa-bell"></i>
                            <span id="notification-badge"
                                style="display: none; position: absolute; top: -6px; right: -8px; background: var(--danger); color: white; border-radius: 50%; padding: 2px 5px; font-size: 10px; font-weight: bold; line-height: 1;">0</span>
                        </a>
                        <div id="notification-dropdown" class="glass-card"
                            style="display: none; position: absolute; top: 35px; right: 0; width: 300px; max-height: 400px; overflow-y: auto; z-index: 1001; padding: 12px; border: 1px solid var(--border-color);">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 style="margin: 0; font-size: 14px;">Notifications</h4>
                                <button onclick="markAllRead()"
                                    style="background: none; border: none; color: var(--primary-green); font-size: 11px; cursor: pointer;">Mark
                                    all as read</button>
                            </div>
                            <div id="notification-list" style="display: flex; flex-direction: column; gap: 8px;">
                                <div style="text-align: center; color: var(--text-muted); font-size: 12px; padding: 20px;">
                                    No new notifications</div>
                            </div>
                        </div>
                    </div>
                    <a href="<?= $base_url ?>/user/my_ads.php" title="My Ads"
                        style="font-size: 20px; color: var(--text-dark); margin-right: 8px;"><i
                            class="fa fa-list-alt"></i></a>
                    <a href="<?= $base_url ?>/user/profile.php" title="<?= __('profile') ?>"
                        style="font-size: 20px; color: var(--text-dark); margin-right: 8px;"><i
                            class="fa fa-user-circle"></i></a>

                    <div class="header-controls desktop-only"
                        style="display: flex; align-items: center; gap: 12px; margin-right: 16px;">
                        <button onclick="toggleTheme()" class="theme-toggle-btn" title="Toggle Theme"
                            style="background: transparent; border: none; cursor: pointer; font-size: 18px; color: var(--text-dark); padding: 0;">
                            <i id="theme-icon" class="fa fa-moon"></i>
                        </button>

                        <div class="lang-switcher desktop-nav"
                            style="display: flex; gap: 4px; font-size: 12px; font-weight: 700;">
                            <a href="?lang=en"
                                style="color: <?= ($_SESSION['lang'] ?? 'en') == 'en' ? 'var(--primary-green)' : 'var(--text-muted)' ?>; text-decoration: none;">EN</a>
                            <span style="color: var(--border-color);">|</span>
                            <a href="?lang=ml"
                                style="color: <?= ($_SESSION['lang'] ?? 'en') == 'ml' ? 'var(--primary-green)' : 'var(--text-muted)' ?>; text-decoration: none;">മല</a>
                        </div>
                    </div>

                    <a href="<?= $base_url ?>/user/post_ad.php" class="btn-secondary" title="<?= __('post_ad') ?>"
                        style="display: flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 24px;"><i
                            class="fa fa-plus"></i> <?= __('post_ad') ?></a>
                <?php else: ?>
                    <a href="<?= $base_url ?>/login.php" title="Login">Login</a>
                    <a href="<?= $base_url ?>/register.php" class="btn-primary">Sign Up</a>
                <?php endif; ?>
            </nav>
        </header>

        <!-- Location Bar -->
        <div class="location-bar">
            <div class="container"
                style="padding: 0 24px; display: flex; align-items: center; justify-content: space-between; height: 100%;">
                <div class="location-display" onclick="document.getElementById('location-modal').style.display='flex'">
                    <i class="fa fa-map-marker-alt"></i>
                    <span id="current-location-text">
                        <?= $_SESSION['user_location']['name'] ?? 'Set Location' ?>
                    </span>
                    <i class="fa fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
                </div>

                <div class="search-container-header" style="display: flex; align-items: center; gap: 10px; flex: 1;">
                    <form action="<?= $base_url ?>/index.php" method="GET" style="display: flex; flex: 1;">
                        <input type="text" name="search" placeholder="<?= __('search_placeholder') ?>"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit"><i class="fa fa-search"></i></button>
                    </form>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="notification-wrapper mobile-only" style="position: relative;">
                            <a href="javascript:void(0)" onclick="toggleNotifications()" title="Notifications"
                                style="text-decoration: none; font-size: 18px; color: var(--text-dark); display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: var(--background); border-radius: 50%; border: 1px solid var(--border-color);">
                                <i class="fa fa-bell"></i>
                                <span class="notification-badge-mobile" id="notification-badge-mobile"
                                    style="display: none; position: absolute; top: 0; right: 0; background: var(--danger); color: white; border-radius: 50%; padding: 2px 4px; font-size: 8px; font-weight: bold; line-height: 1;">0</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <!-- Location Modal -->
    <div id="location-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content location-modal-content">
            <div class="modal-header">
                <h3>Select Location</h3>
                <button class="close-modal"
                    onclick="document.getElementById('location-modal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <button id="detect-location" class="btn-location-action">
                    <i class="fa fa-crosshairs"></i> Use Current Location (GPS)
                </button>

                <?php if (isset($_SESSION['user_location'])): ?>
                    <button onclick="EnteangadiLocation.clearLocation()" class="btn-location-action"
                        style="background: #fff1f2; color: #e11d48; border-color: #fee2e2; margin-top: 10px;">
                        <i class="fa fa-trash-alt"></i> Reset Location
                    </button>
                <?php endif; ?>

                <div class="divider"><span>OR SEARCH LOCATION</span></div>

                <div class="location-search-box">
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="modal-location-search" class="form-control"
                            placeholder="Search city, area...">
                        <button type="button" onclick="EnteangadiLocation.searchLocation()" class="btn-secondary"
                            style="padding: 0 16px;"><i class="fa fa-search"></i></button>
                    </div>
                    <div id="location-search-results" class="city-list" style="margin-top: 12px; display: none;">
                        <!-- Results will be injected here -->
                    </div>
                </div>
            </div>
        </div>
    </div>