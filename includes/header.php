<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($base_url)) {
    $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_url = ($current_dir === '/' || $current_dir === '.') ? '' : $current_dir;
    if (in_array(basename($base_url), ['user', 'admin', 'guest'], true)) {
        $base_url = dirname($base_url);
    }
    $base_url = str_replace('\\', '/', $base_url);
    if ($base_url === '/' || $base_url === '.') {
        $base_url = '';
    }
}

// Handle Language Change
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = in_array($_GET['lang'], ['en', 'ml'], true) ? $_GET['lang'] : 'en';
    $clean_url = strtok($_SERVER['REQUEST_URI'], '?');
    $query = $_GET;
    unset($query['lang']);
    $query['lang_changed'] = '1';
    header("Location: " . $clean_url . '?' . http_build_query($query));
    exit;
}

require_once __DIR__ . '/helpers.php';

// Cache app_settings in session for 60s to avoid querying on every click
if (isset($_SESSION['_app_settings_cache'], $_SESSION['_app_settings_ts']) && (time() - $_SESSION['_app_settings_ts']) < 60) {
    $app_settings = $_SESSION['_app_settings_cache'];
} else {
    try {
        $settings_raw = $pdo->query("SELECT setting_key, setting_value FROM app_settings")->fetchAll();
        $app_settings = [];
        foreach ($settings_raw as $s) {
            $app_settings[$s['setting_key']] = $s['setting_value'];
        }
        $_SESSION['_app_settings_cache'] = $app_settings;
        $_SESSION['_app_settings_ts'] = time();
    } catch (Exception $e) {
        $app_settings = ['app_logo' => '', 'app_tagline' => 'Your Local Marketplace'];
    }
}

$root_disk = dirname(__DIR__);
$logo_rel = !empty($app_settings['app_logo']) ? $app_settings['app_logo'] : 'uploads/logo/logo_enteangadi.png';
$logo_disk_file = $root_disk . '/' . $logo_rel;
$logo_exists = file_exists($logo_disk_file);
$logo_version = $logo_exists ? filemtime($logo_disk_file) : 1;
$css_version = @filemtime($root_disk . '/assets/css/style.css') ?: 1;
$loc_js_version = @filemtime($root_disk . '/assets/js/location.js') ?: 1;
$nat_js_version = @filemtime($root_disk . '/assets/js/app-native.js') ?: 1;
$sw_version = @filemtime($root_disk . '/service-worker.js') ?: 1;
$home_href = $base_url . (isset($_SESSION['user_id']) ? '/user/index.php' : '/guest/index.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= __('app_name') ?> - <?= __('tagline') ?></title>
    <meta name="description" content="Buy and sell anything locally with <?= __('app_name') ?>.">

    <?php if (isset($og_title)): ?><meta property="og:title" content="<?= htmlspecialchars($og_title) ?>" /><?php endif; ?>
    <?php if (isset($og_desc)): ?><meta property="og:description" content="<?= htmlspecialchars($og_desc) ?>" /><?php endif; ?>
    <?php if (isset($og_image)): ?><meta property="og:image" content="<?= htmlspecialchars($og_image) ?>" /><?php endif; ?>
    <?php if (isset($og_url)): ?><meta property="og:url" content="<?= htmlspecialchars($og_url) ?>" /><?php endif; ?>
    <meta property="og:type" content="website" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css?v=<?= $css_version ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </noscript>

    <script>
        const EnteangadiConfig = {
            baseUrl: '<?= $base_url ?>',
            homeUrl: '<?= $home_href ?>',
            appLogo: '<?= htmlspecialchars($logo_rel) . '?v=' . $logo_version ?>',
            hasLocation: <?= isset($_SESSION['user_location']) ? 'true' : 'false' ?>,
            location: <?= isset($_SESSION['user_location']) ? json_encode($_SESSION['user_location']) : 'null' ?>,
            isLoggedIn: <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>,
            userId: '<?= $_SESSION['user_id'] ?? '' ?>'
        };

        // On-demand Leaflet loader (loaded only when user opens Map View)
        window.ensureLeafletLoaded = function () {
            if (window.L) return Promise.resolve();
            if (window._leafletPromise) return window._leafletPromise;
            window._leafletPromise = new Promise((resolve, reject) => {
                const css = document.createElement('link');
                css.rel = 'stylesheet';
                css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(css);
                const js = document.createElement('script');
                js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                js.onload = resolve;
                js.onerror = reject;
                document.head.appendChild(js);
            });
            return window._leafletPromise;
        };

        // Ensure same-origin fetch calls include credentials and mobile WebView session headers
        (function () {
            const originalFetch = window.fetch;
            window.fetch = function (resource, init) {
                init = init || {};
                const urlStr = typeof resource === 'string' ? resource : (resource && resource.url ? resource.url : '');
                if (urlStr && (urlStr.startsWith('/') || !urlStr.startsWith('http') || urlStr.includes(window.location.host))) {
                    init.credentials = 'include';
                    const savedUserId = localStorage.getItem('enteangadi_user_id');
                    const savedToken = localStorage.getItem('enteangadi_session_token');
                    if (savedUserId && savedToken) {
                        init.headers = init.headers || {};
                        if (init.headers instanceof Headers) {
                            init.headers.set('X-User-Id', savedUserId);
                            init.headers.set('X-Session-Token', savedToken);
                        } else if (Array.isArray(init.headers)) {
                            init.headers = init.headers.filter(h => h[0].toLowerCase() !== 'x-user-id' && h[0].toLowerCase() !== 'x-session-token');
                            init.headers.push(['X-User-Id', savedUserId], ['X-Session-Token', savedToken]);
                        } else {
                            init.headers['X-User-Id'] = savedUserId;
                            init.headers['X-Session-Token'] = savedToken;
                        }
                    }
                }
                return originalFetch(resource, init);
            };
        })();

        // Theme & Session Persistence
        (function () {
            document.documentElement.setAttribute('data-theme', localStorage.getItem('enteangadi-theme') || 'light');

            const urlParams = new URLSearchParams(window.location.search);
            const flushSwPages = () => {
                if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ action: 'clearPageCache' });
                }
            };

            if (urlParams.has('lang_changed')) {
                flushSwPages();
                urlParams.delete('lang_changed');
                const qs = urlParams.toString();
                window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
            }

            if (urlParams.has('logged_out')) {
                localStorage.removeItem('enteangadi_user_id');
                localStorage.removeItem('enteangadi_session_token');
                flushSwPages();
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
                return;
            }

            if (EnteangadiConfig.isLoggedIn) {
                const prevUid = localStorage.getItem('enteangadi_user_id');
                localStorage.setItem('enteangadi_user_id', '<?= $_SESSION['user_id'] ?? '' ?>');
                localStorage.setItem('enteangadi_session_token', '<?= $_SESSION['session_token'] ?? '' ?>');
                if (prevUid !== '<?= $_SESSION['user_id'] ?? '' ?>') flushSwPages();
                if (window.EnteangadiNativeHelper && typeof window.EnteangadiNativeHelper.setLoggedInUser === 'function') {
                    window.EnteangadiNativeHelper.setLoggedInUser('<?= $_SESSION['user_id'] ?? '' ?>');
                }
            } else {
                if (window.EnteangadiNativeHelper && typeof window.EnteangadiNativeHelper.setLoggedInUser === 'function') {
                    window.EnteangadiNativeHelper.setLoggedInUser('');
                }
                const savedUserId = localStorage.getItem('enteangadi_user_id');
                const savedToken = localStorage.getItem('enteangadi_session_token');
                if (savedUserId && savedToken) {
                    const style = document.createElement('style');
                    style.id = 'autologin-hide-body';
                    style.innerHTML = 'body { opacity: 0 !important; }';
                    document.head.appendChild(style);

                    fetch(EnteangadiConfig.baseUrl + '/api/auto_login.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'user_id=' + encodeURIComponent(savedUserId) + '&session_token=' + encodeURIComponent(savedToken)
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                flushSwPages();
                                const pathname = window.location.pathname;
                                if (pathname.includes('/guest/product.php')) {
                                    window.location.replace(EnteangadiConfig.baseUrl + '/product.php' + window.location.search);
                                } else if (pathname.includes('/guest/')) {
                                    window.location.replace(EnteangadiConfig.baseUrl + '/user/index.php' + window.location.search);
                                } else {
                                    location.reload();
                                }
                            } else {
                                localStorage.removeItem('enteangadi_user_id');
                                localStorage.removeItem('enteangadi_session_token');
                                const h = document.getElementById('autologin-hide-body');
                                if (h) h.remove();
                            }
                        })
                        .catch(() => {
                            const h = document.getElementById('autologin-hide-body');
                            if (h) h.remove();
                        });
                }
            }
        })();

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= $base_url ?>/service-worker.js?v=<?= $sw_version ?>', { updateViaCache: 'none' }).catch(() => {});
            });
        }
    </script>
    <script src="<?= $base_url ?>/capacitor.js" defer></script>
    <script src="<?= $base_url ?>/assets/js/location.js?v=<?= $loc_js_version ?>" defer></script>
    <script src="<?= $base_url ?>/assets/js/app-native.js?v=<?= $nat_js_version ?>" defer></script>
</head>

<body>
    <!-- Breathing Logo + Live Counting Percentage Loader -->
    <div id="loader-wrapper">
        <div class="loader-emblem">
            <span class="loader-ring loader-ring-1"></span>
            <span class="loader-ring loader-ring-2"></span>
            <?php if ($logo_exists): ?>
                <img src="<?= $base_url ?>/<?= htmlspecialchars($logo_rel) ?>?v=<?= $logo_version ?>" alt="Enteangadi" class="loader-logo-img" fetchpriority="high" decoding="sync">
            <?php else: ?>
                <div class="loader-logo"><?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($app_settings['app_tagline'])): ?>
            <div class="loader-tagline"><?= htmlspecialchars($app_settings['app_tagline']) ?></div>
        <?php endif; ?>

        <div class="loader-progress-wrap">
            <div class="loader-percent" id="loader-percent-text">0%</div>
            <div class="loader-bar-track">
                <div class="loader-bar-fill" id="loader-bar-fill"></div>
            </div>
        </div>

        <div class="loader-location-status" id="loader-location-status">
            <?php if (!empty($_SESSION['user_location']['name'])): ?>
                📍 <?= htmlspecialchars(explode(',', $_SESSION['user_location']['name'])[0]) ?>
            <?php else: ?>
                Loading marketplace...
            <?php endif; ?>
        </div>
    </div>

    <script>
        // High-Speed Counting Percentage + Breathing Logo Controller
        (function () {
            const loader = document.getElementById('loader-wrapper');
            const pctEl = document.getElementById('loader-percent-text');
            const barEl = document.getElementById('loader-bar-fill');
            if (!loader) return;

            let progress = 0;
            let timer = null;
            let finishing = false;

            function setPct(val) {
                progress = Math.max(0, Math.min(100, Math.round(val)));
                if (pctEl) pctEl.textContent = progress + '%';
                if (barEl) barEl.style.width = progress + '%';
            }

            function hideOverlay() {
                if (timer) { clearInterval(timer); timer = null; }
                loader.classList.add('loader-hide');
                setTimeout(() => { loader.style.display = 'none'; }, 190);
                try {
                    sessionStorage.setItem('hasLoadedBefore', 'true');
                    sessionStorage.removeItem('enteangadi_nav_loader');
                } catch (e) {}
            }

            function finishTo100() {
                if (finishing) return;
                finishing = true;
                if (timer) { clearInterval(timer); timer = null; }
                const stepUp = () => {
                    if (progress >= 100) {
                        setPct(100);
                        requestAnimationFrame(hideOverlay);
                        return;
                    }
                    setPct(progress + Math.max(6, Math.ceil((100 - progress) * 0.45)));
                    setTimeout(stepUp, 18);
                };
                stepUp();
            }

            function startCounting(initialVal) {
                finishing = false;
                setPct(initialVal || 0);
                loader.style.display = 'flex';
                loader.classList.remove('loader-hide');
                if (timer) clearInterval(timer);
                timer = setInterval(() => {
                    if (progress < 92) {
                        const inc = progress < 45 ? 7 : (progress < 75 ? 4 : 1);
                        setPct(Math.min(92, progress + inc));
                    }
                }, 32);
            }

            // Expose navigation loader API for uncached links & form submissions
            window.EnteangadiLoader = {
                startNavigation(statusMsg) {
                    const st = document.getElementById('loader-location-status');
                    if (st && statusMsg) st.textContent = statusMsg;
                    try { sessionStorage.setItem('enteangadi_nav_loader', '1'); } catch (e) {}
                    startCounting(8);
                },
                finish: finishTo100,
                hideInstantly() {
                    if (timer) { clearInterval(timer); timer = null; }
                    loader.classList.add('loader-hide');
                    loader.style.display = 'none';
                }
            };
            window.hideLoader = finishTo100;
            window.hideLoaderInstantly = window.EnteangadiLoader.hideInstantly;

            // Check if this page was preloaded in background (instant swap without blocking overlay)
            let hasLoadedBefore = false;
            let wasPreloaded = false;
            try {
                hasLoadedBefore = sessionStorage.getItem('hasLoadedBefore') === 'true';
                wasPreloaded = sessionStorage.getItem('enteangadi_preloaded_hit') === '1';
                if (wasPreloaded) sessionStorage.removeItem('enteangadi_preloaded_hit');
            } catch (e) {}

            if (hasLoadedBefore && wasPreloaded) {
                window.EnteangadiLoader.hideInstantly();
            } else {
                startCounting(hasLoadedBefore ? 24 : 4);
                if (document.readyState === 'interactive' || document.readyState === 'complete') {
                    finishTo100();
                } else {
                    document.addEventListener('DOMContentLoaded', finishTo100, { once: true });
                }
                // Hard safety cap so slow 3rd-party assets never trap the user
                setTimeout(finishTo100, 1200);
            }

            // Always hide immediately when restoring from Back/Forward cache
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) window.EnteangadiLoader.hideInstantly();
            });
        })();

        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('enteangadi-theme', newTheme);
            updateThemeIcon(newTheme);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('theme-icon');
            if (icon) icon.className = theme === 'dark' ? 'fa fa-sun' : 'fa fa-moon';
        }

        window.addEventListener('DOMContentLoaded', () => {
            updateThemeIcon(document.documentElement.getAttribute('data-theme'));
            if (EnteangadiConfig.isLoggedIn) {
                setTimeout(fetchNotifications, 1200);
                setInterval(() => {
                    if (!document.hidden) fetchNotifications();
                }, 15000);
            }
        });

        async function fetchNotifications() {
            if (!EnteangadiConfig.isLoggedIn || document.hidden) return;
            try {
                const response = await fetch('<?= $base_url ?>/api/notifications.php');
                const data = await response.json();
                if (!data.success) return;
                ['notification-badge', 'notification-badge-mobile', 'desktop-unread-badge'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.innerText = data.unread_count;
                        el.style.display = data.unread_count > 0 ? 'block' : 'none';
                    }
                });
                renderNotifications(data.notifications);
            } catch (e) {}
        }

        function renderNotifications(notifications) {
            const listDesk = document.getElementById('notification-list');
            const listMobile = document.getElementById('notification-list-mobile');
            const emptyHtml = '<div style="text-align:center;color:var(--text-muted);font-size:12px;padding:20px;">No new notifications</div>';
            const html = (!notifications || notifications.length === 0) ? emptyHtml : notifications.map(n => `
                <div onclick="markRead(${n.id}, '${n.link}')" style="padding:10px;border-radius:8px;background:${n.is_read == 0 ? 'var(--background)' : 'transparent'};cursor:pointer;border-bottom:1px solid var(--border-color);position:relative;">
                    <div style="font-size:13px;font-weight:${n.is_read == 0 ? '600' : '400'};color:var(--text-dark);">${n.message}</div>
                    <div style="font-size:10px;color:var(--text-muted);margin-top:4px;">${new Date(n.created_at).toLocaleDateString()}</div>
                </div>
            `).join('');
            if (listDesk) listDesk.innerHTML = html;
            if (listMobile) listMobile.innerHTML = html;
        }

        function toggleNotifications(e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            const targetId = window.innerWidth <= 768 ? 'notification-dropdown-mobile' : 'notification-dropdown';
            const dropdown = document.getElementById(targetId);
            if (dropdown) {
                const isShowing = dropdown.style.display === 'block';
                const desk = document.getElementById('notification-dropdown');
                const mob = document.getElementById('notification-dropdown-mobile');
                if (desk) desk.style.display = 'none';
                if (mob) mob.style.display = 'none';
                dropdown.style.display = isShowing ? 'none' : 'block';
            }
        }

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.notification-wrapper')) {
                const desk = document.getElementById('notification-dropdown');
                const mob = document.getElementById('notification-dropdown-mobile');
                if (desk) desk.style.display = 'none';
                if (mob) mob.style.display = 'none';
            }
        });

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
            <a href="<?= $home_href ?>" class="logo" style="background:transparent;">
                <?php if (!empty($app_settings['app_logo']) || $logo_exists): ?>
                    <img src="<?= $base_url ?>/<?= htmlspecialchars($logo_rel) ?>?v=<?= $logo_version ?>" alt="Enteangadi"
                        style="max-height:42px;width:auto;object-fit:contain;background:transparent !important;display:block;">
                <?php else: ?>
                    <?= htmlspecialchars($app_settings['app_name'] ?? 'Enteangadi') ?>
                <?php endif; ?>
            </a>
            <nav class="desktop-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= $base_url ?>/user/inbox.php" title="Inbox" style="position:relative;text-decoration:none;font-size:20px;color:var(--text-dark);margin-right:8px;">
                        <i class="fa fa-comments"></i>
                        <span id="desktop-unread-badge" style="display:none;position:absolute;top:-6px;right:-8px;background:var(--danger);color:#fff;border-radius:50%;padding:2px 5px;font-size:10px;font-weight:bold;line-height:1;">0</span>
                    </a>

                    <div class="notification-wrapper" style="position:relative;margin-right:8px;">
                        <a href="javascript:void(0)" onclick="toggleNotifications()" title="Notifications" style="text-decoration:none;font-size:20px;color:var(--text-dark);">
                            <i class="fa fa-bell"></i>
                            <span id="notification-badge" style="display:none;position:absolute;top:-6px;right:-8px;background:var(--danger);color:#fff;border-radius:50%;padding:2px 5px;font-size:10px;font-weight:bold;line-height:1;">0</span>
                        </a>
                        <div id="notification-dropdown" class="glass-card" style="display:none;position:absolute;top:35px;right:0;width:300px;max-height:400px;overflow-y:auto;z-index:1001;padding:12px;border:1px solid var(--border-color);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                <h4 style="margin:0;font-size:14px;">Notifications</h4>
                                <button onclick="markAllRead()" style="background:none;border:none;color:var(--primary-green);font-size:11px;cursor:pointer;">Mark all as read</button>
                            </div>
                            <div id="notification-list" style="display:flex;flex-direction:column;gap:8px;">
                                <div style="text-align:center;color:var(--text-muted);font-size:12px;padding:20px;">No new notifications</div>
                            </div>
                        </div>
                    </div>

                    <a href="<?= $base_url ?>/user/my_ads.php" title="My Ads" style="font-size:20px;color:var(--text-dark);margin-right:8px;"><i class="fa fa-list-alt"></i></a>
                    <a href="<?= $base_url ?>/user/profile.php" title="<?= __('profile') ?>" style="font-size:20px;color:var(--text-dark);margin-right:8px;"><i class="fa fa-user-circle"></i></a>

                    <div class="header-controls desktop-only" style="display:flex;align-items:center;gap:12px;margin-right:16px;">
                        <button onclick="toggleTheme()" class="theme-toggle-btn" title="Toggle Theme" style="background:transparent;border:none;cursor:pointer;font-size:18px;color:var(--text-dark);padding:0;">
                            <i id="theme-icon" class="fa fa-moon"></i>
                        </button>
                        <div class="lang-switcher desktop-nav" style="display:flex;gap:4px;font-size:12px;font-weight:700;">
                            <a href="?lang=en" data-no-preload="1" style="color:<?= ($_SESSION['lang'] ?? 'en') === 'en' ? 'var(--primary-green)' : 'var(--text-muted)' ?>;text-decoration:none;">EN</a>
                            <span style="color:var(--border-color);">|</span>
                            <a href="?lang=ml" data-no-preload="1" style="color:<?= ($_SESSION['lang'] ?? 'en') === 'ml' ? 'var(--primary-green)' : 'var(--text-muted)' ?>;text-decoration:none;">മല</a>
                        </div>
                    </div>

                    <a href="<?= $base_url ?>/user/post_ad.php" class="btn-secondary" title="<?= __('post_ad') ?>" style="display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:24px;"><i class="fa fa-plus"></i> <?= __('post_ad') ?></a>
                <?php else: ?>
                    <a href="<?= $base_url ?>/login.php" title="Login">Login</a>
                    <a href="<?= $base_url ?>/register.php" class="btn-primary">Sign Up</a>
                <?php endif; ?>
            </nav>
        </header>

        <!-- Location Bar -->
        <div class="location-bar">
            <div class="container location-bar-container">
                <div class="location-display" onclick="document.getElementById('location-modal').style.display='flex'">
                    <i class="fa fa-map-marker-alt"></i>
                    <span id="current-location-text"><?= htmlspecialchars($_SESSION['user_location']['name'] ?? 'Set Location') ?></span>
                    <i class="fa fa-chevron-down" style="font-size:10px;margin-left:4px;"></i>
                </div>

                <div class="search-and-notification-wrapper">
                    <div class="search-container-header">
                        <form action="<?= $home_href ?>" method="GET">
                            <input type="text" name="search" id="header-search-input" placeholder="<?= __('search_placeholder') ?>" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            <button type="button" id="header-search-mic-btn" onclick="startVoiceSearch(event)" title="Voice Search"><i class="fa fa-microphone"></i></button>
                            <button type="submit" aria-label="Search"><i class="fa fa-search"></i></button>
                        </form>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="notification-wrapper mobile-only">
                            <a href="javascript:void(0)" onclick="toggleNotifications(event)" title="Notifications" class="mobile-notification-btn">
                                <i class="fa fa-bell"></i>
                                <span class="notification-badge-mobile" id="notification-badge-mobile">0</span>
                            </a>
                            <div id="notification-dropdown-mobile" class="glass-card notification-dropdown-panel" style="display:none;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                    <h4 style="margin:0;font-size:14px;font-weight:700;color:var(--text-dark);">Notifications</h4>
                                    <button type="button" onclick="markAllRead()" style="background:none;border:none;color:var(--primary-green);font-size:11px;cursor:pointer;font-weight:600;">Mark all as read</button>
                                </div>
                                <div id="notification-list-mobile" style="display:flex;flex-direction:column;gap:8px;">
                                    <div style="text-align:center;color:var(--text-muted);font-size:12px;padding:20px;">No new notifications</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Voice Search Modal -->
                <div id="voice-search-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.65);backdrop-filter:blur(8px);z-index:10000;align-items:center;justify-content:center;font-family:'Inter',sans-serif;">
                    <div style="background:var(--white,#fff);padding:40px;border-radius:24px;box-shadow:var(--shadow-lg);text-align:center;max-width:320px;width:90%;">
                        <div style="position:relative;width:90px;height:90px;margin:0 auto 24px;">
                            <div style="position:absolute;width:100%;height:100%;background:rgba(46,125,50,.2);border-radius:50%;animation:voicePulse 2s infinite;"></div>
                            <div style="position:absolute;width:100%;height:100%;background:rgba(46,125,50,.15);border-radius:50%;animation:voicePulse 2s infinite .6s;"></div>
                            <div style="position:absolute;width:70px;height:70px;top:10px;left:10px;background:var(--primary-green);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;box-shadow:0 4px 10px rgba(46,125,50,.3);">
                                <i class="fa fa-microphone"></i>
                            </div>
                        </div>
                        <h3 id="voice-status-text" style="margin:0 0 8px 0;font-size:18px;font-weight:800;color:var(--text-dark);">Listening...</h3>
                        <p id="voice-sub-text" style="margin:0 0 24px 0;font-size:13px;color:var(--text-muted);">Speak matching keywords</p>
                        <button type="button" onclick="stopOrCancelVoiceSearch()" style="background:var(--primary-green);border:none;color:#fff;padding:10px 24px;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;margin-right:8px;">Done</button>
                        <button type="button" onclick="cancelVoiceSearch()" style="background:#f1f5f9;border:none;color:#475569;padding:10px 24px;border-radius:12px;font-weight:700;font-size:13px;cursor:pointer;">Cancel</button>
                    </div>
                </div>
                <style>@keyframes voicePulse{0%{transform:scale(.8);opacity:.5}50%{transform:scale(1.3);opacity:.8}100%{transform:scale(1.6);opacity:0}}</style>
                <script>
                    let currentRecognition = null, originalPlaceholder = '', mediaRecorderInstance = null, audioChunks = [];

                    function startVoiceSearch(e) {
                        if (e) { e.preventDefault(); e.stopPropagation(); }
                        cancelVoiceSearch();
                        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                        if (!SpeechRecognition) { startAudioRecorderFallback(); return; }

                        let recognition;
                        try { recognition = new SpeechRecognition(); } catch (err) { startAudioRecorderFallback(); return; }
                        recognition.lang = navigator.language || 'en-US';
                        recognition.interimResults = false;
                        recognition.maxAlternatives = 1;
                        currentRecognition = recognition;

                        const modal = document.getElementById('voice-search-modal');
                        const statusText = document.getElementById('voice-status-text');
                        const subText = document.getElementById('voice-sub-text');
                        const isMl = '<?= $_SESSION['lang'] ?? 'en' ?>' === 'ml';
                        const searchInput = document.getElementById('header-search-input');

                        if (searchInput) {
                            if (!originalPlaceholder) originalPlaceholder = searchInput.placeholder;
                            searchInput.value = '';
                            searchInput.placeholder = isMl ? 'കേൾക്കുന്നു...' : 'Listening...';
                        }
                        statusText.innerText = isMl ? 'കേൾക്കുന്നു...' : 'Listening...';
                        subText.innerText = isMl ? 'സംസാരിക്കൂ...' : 'Speak now...';
                        modal.style.display = 'flex';

                        recognition.onerror = () => startAudioRecorderFallback();
                        recognition.onend = () => { setTimeout(() => { if (modal.style.display === 'flex' && !mediaRecorderInstance) modal.style.display = 'none'; }, 1800); };
                        recognition.onresult = (event) => {
                            const transcript = event.results[0][0].transcript.trim();
                            statusText.innerText = transcript;
                            subText.innerText = isMl ? 'സെർച്ച് ചെയ്യുന്നു...' : 'Searching...';
                            if (searchInput) {
                                searchInput.value = transcript;
                                const micBtn = document.getElementById('header-search-mic-btn');
                                if (micBtn) micBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
                                setTimeout(() => { modal.style.display = 'none'; searchInput.form.submit(); }, 350);
                            } else {
                                modal.style.display = 'none';
                            }
                        };
                        try { recognition.start(); } catch (err) { startAudioRecorderFallback(); }
                    }

                    async function startAudioRecorderFallback() {
                        const modal = document.getElementById('voice-search-modal');
                        const statusText = document.getElementById('voice-status-text');
                        const subText = document.getElementById('voice-sub-text');
                        const isMl = '<?= $_SESSION['lang'] ?? 'en' ?>' === 'ml';
                        modal.style.display = 'flex';
                        try {
                            const stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
                            const types = [['audio/webm;codecs=opus', 'webm'], ['audio/webm', 'webm'], ['audio/ogg', 'ogg'], ['audio/mp4', 'mp4']];
                            const found = types.find(([t]) => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || ['audio/wav', 'wav'];
                            mediaRecorderInstance = new MediaRecorder(stream, { mimeType: found[0] });
                            audioChunks = [];
                            mediaRecorderInstance.ondataavailable = (ev) => { if (ev.data.size > 0) audioChunks.push(ev.data); };
                            mediaRecorderInstance.onstop = async () => {
                                const audioBlob = new Blob(audioChunks, { type: found[0] });
                                stream.getTracks().forEach(t => t.stop());
                                statusText.innerText = isMl ? 'പ്രൊസസ്സ് ചെയ്യുന്നു...' : 'Transcribing...';
                                subText.innerText = isMl ? 'ദയവായി കാത്തിരിക്കൂ...' : 'Processing audio...';
                                const searchInput = document.getElementById('header-search-input');
                                const micBtn = document.getElementById('header-search-mic-btn');
                                if (micBtn) micBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
                                const fd = new FormData();
                                fd.append('audio', audioBlob, `speech.${found[1]}`);
                                try {
                                    const res = await fetch('<?= $base_url ?>/api/transcribe.php', { method: 'POST', body: fd });
                                    const result = await res.json();
                                    if (result.success && result.transcript) {
                                        const clean = result.transcript.trim();
                                        statusText.innerText = clean;
                                        if (searchInput) {
                                            searchInput.value = clean;
                                            setTimeout(() => { modal.style.display = 'none'; searchInput.form.submit(); }, 350);
                                        } else modal.style.display = 'none';
                                    } else {
                                        statusText.innerText = isMl ? 'തടസ്സം നേരിട്ടു' : 'Failed to transcribe';
                                        subText.innerText = result.message || 'Error transcribing voice';
                                        setTimeout(() => { modal.style.display = 'none'; }, 3500);
                                    }
                                } catch (err) {
                                    statusText.innerText = isMl ? 'എറർ' : 'Error';
                                    subText.innerText = 'Server communication failed.';
                                    setTimeout(() => { modal.style.display = 'none'; }, 2200);
                                }
                            };
                            mediaRecorderInstance.start();
                            statusText.innerText = isMl ? 'കേൾക്കുന്നു...' : 'Listening...';
                            subText.innerText = isMl ? 'സംസാരിക്കൂ (പരമാവധി 7 സെക്കൻഡ്)...' : 'Speak now (max 7s)...';
                            setTimeout(() => { if (mediaRecorderInstance && mediaRecorderInstance.state === 'recording') mediaRecorderInstance.stop(); }, 7000);
                        } catch (err) {
                            statusText.innerText = isMl ? 'തടസ്സം നേരിട്ടു' : 'Microphone unavailable';
                            subText.innerText = isMl ? 'മൈക്രോഫോൺ പെർമിഷൻ നൽകുക' : 'Please grant microphone permission.';
                            setTimeout(() => { modal.style.display = 'none'; }, 2200);
                        }
                    }

                    function stopOrCancelVoiceSearch() {
                        if (mediaRecorderInstance && mediaRecorderInstance.state === 'recording') mediaRecorderInstance.stop();
                        else if (currentRecognition) currentRecognition.stop();
                    }

                    function cancelVoiceSearch() {
                        if (currentRecognition) { try { currentRecognition.abort(); } catch (e) {} currentRecognition = null; }
                        if (mediaRecorderInstance && mediaRecorderInstance.state === 'recording') {
                            mediaRecorderInstance.onstop = null;
                            mediaRecorderInstance.stop();
                        }
                        mediaRecorderInstance = null;
                        const searchInput = document.getElementById('header-search-input');
                        if (searchInput && originalPlaceholder) searchInput.placeholder = originalPlaceholder;
                        const micBtn = document.getElementById('header-search-mic-btn');
                        if (micBtn) micBtn.innerHTML = '<i class="fa fa-microphone"></i>';
                        const modal = document.getElementById('voice-search-modal');
                        if (modal) modal.style.display = 'none';
                    }
                </script>
            </div>
        </div>
    </div>

    <!-- Location Modal -->
    <div id="location-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content location-modal-content">
            <div class="modal-header">
                <h3>Select Location</h3>
                <button class="close-modal" onclick="document.getElementById('location-modal').style.display='none'">&times;</button>
            </div>
            <div class="modal-body">
                <button id="detect-location" class="btn-location-action">
                    <i class="fa fa-crosshairs"></i> Use Current Location (GPS)
                </button>
                <?php if (isset($_SESSION['user_location'])): ?>
                    <button onclick="EnteangadiLocation.clearLocation()" class="btn-location-action" style="background:#fff1f2;color:#e11d48;border-color:#fee2e2;margin-top:10px;">
                        <i class="fa fa-trash-alt"></i> Reset Location
                    </button>
                <?php endif; ?>
                <div class="divider"><span>OR SEARCH LOCATION</span></div>
                <div class="location-search-box">
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="modal-location-search" class="form-control" placeholder="Search city, area...">
                        <button type="button" onclick="EnteangadiLocation.searchLocation()" class="btn-secondary" style="padding:0 16px;"><i class="fa fa-search"></i></button>
                    </div>
                    <div id="location-search-results" class="city-list" style="margin-top:12px;display:none;"></div>
                </div>
            </div>
        </div>
    </div>
