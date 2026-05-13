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
    <title>Enteangadi - Your Local Marketplace</title>
    <meta name="description" content="Buy and sell anything locally with Enteangadi.">
    <link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css?v=1.2">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        const EnteangadiConfig = {
            baseUrl: '<?= $base_url ?>'
        };
    </script>
    <script src="<?= $base_url ?>/assets/js/location.js" defer></script>

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
    </div>
    <script>
        // Loader logic (First time per session only)
        (function () {
            const loader = document.getElementById('loader-wrapper');
            if (!loader) return;

            if (sessionStorage.getItem('loaderShown')) {
                loader.style.display = 'none';
            } else {
                // Timer starts immediately for the first visit
                setTimeout(() => {
                    loader.classList.add('loader-hide');
                    setTimeout(() => {
                        loader.style.display = 'none';
                        sessionStorage.setItem('loaderShown', 'true');
                    }, 500);
                }, 3000);
            }
        })();
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
                    <a href="<?= $base_url ?>/user/my_ads.php" title="My Ads"
                        style="font-size: 20px; color: var(--text-dark); margin-right: 8px;"><i
                            class="fa fa-list-alt"></i></a>
                    <a href="<?= $base_url ?>/user/profile.php" title="Profile"
                        style="font-size: 20px; color: var(--text-dark); margin-right: 16px;"><i
                            class="fa fa-user-circle"></i></a>
                    <a href="<?= $base_url ?>/user/post_ad.php" class="btn-secondary" title="Post"
                        style="display: flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 24px;"><i
                            class="fa fa-plus"></i> POST</a>
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
                        <?php if (isset($_SESSION['user_location']['lat'])): ?>
                            <small
                                style="font-size: 10px; opacity: 0.7; margin-left: 5px;">(<?= round($_SESSION['user_location']['lat'], 2) ?>,
                                <?= round($_SESSION['user_location']['lng'], 2) ?>)</small>
                        <?php endif; ?>
                    </span>
                    <i class="fa fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
                </div>

                <div class="search-container-header">
                    <form action="<?= $base_url ?>/index.php" method="GET" style="display: flex; width: 100%;">
                        <input type="text" name="search" placeholder="Search products..."
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button type="submit"><i class="fa fa-search"></i></button>
                    </form>
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