<!-- Mobile Bottom Navigation -->
<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="mobile-bottom-nav">
    <a href="<?= $base_url ?>/index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>"
        title="Home">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $base_url ?>/user/inbox.php"
            class="nav-item <?= ($current_page == 'inbox.php' || $current_page == 'chat.php') ? 'active' : '' ?>"
            title="Chats">
            <i class="fa-solid fa-comments"></i>
            <span>Chats</span>
            <span id="mobile-unread-badge"
                style="display: none; position: absolute; top: 4px; right: 20%; background: var(--danger); color: white; border-radius: 50%; width: 14px; height: 14px; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; border: 2px solid white;">0</span>
        </a>
        <a href="<?= $base_url ?>/user/post_ad.php"
            class="nav-item <?= ($current_page == 'post_ad.php') ? 'active' : '' ?> post-tab" title="Post">
            <i class="fa-solid fa-circle-plus"></i>
            <span>Post</span>
        </a>
        <a href="<?= $base_url ?>/user/my_ads.php" class="nav-item <?= $current_page == 'my_ads.php' ? 'active' : '' ?>"
            title="My Ads">
            <i class="fa-solid fa-list"></i>
            <span>My Ads</span>
        </a>
        <a href="<?= $base_url ?>/user/profile.php" class="nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>"
            title="Account">
            <i class="fa-solid fa-user"></i>
            <span>Account</span>
        </a>
    <?php else: ?>
        <a href="<?= $base_url ?>/login.php" class="nav-item <?= $current_page == 'login.php' ? 'active' : '' ?>"
            title="Login">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span>Login</span>
        </a>
    <?php endif; ?>
</nav>

<div id="toast-container"
    style="position: fixed; top: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;">
</div>

<footer>
    <div class="container" style="padding: 0;">
        <p>&copy; <?= date('Y') ?> Enteangadi. All rights reserved.</p>
    </div>
</footer>

<?php if (isset($_SESSION['user_id'])): ?>
    <?php
    // Detect current page section
    $current_page = basename($_SERVER['PHP_SELF']);
    $tut_column = '';
    $tut_step = null;

    if ($current_page == 'index.php') {
        $tut_column = 'tut_home';
        $tut_step = [
            'icon' => 'fa-hand-sparkles',
            'title' => 'Welcome to Enteangadi',
            'desc' => 'Your local marketplace. Use the search bar and category icons above to explore listings near you.'
        ];
    } elseif ($current_page == 'post_ad.php') {
        $tut_column = 'tut_post';
        $tut_step = [
            'icon' => 'fa-plus-circle',
            'title' => 'Post Your Ad',
            'desc' => 'Selling is easy! Fill in the details, upload photos, and set your price to reach local buyers.'
        ];
    } elseif ($current_page == 'inbox.php' || $current_page == 'chat.php') {
        $tut_column = 'tut_inbox';
        $tut_step = [
            'icon' => 'fa-comments',
            'title' => 'Messages & Chats',
            'desc' => 'Chat directly with buyers and sellers. You will get instant notifications for new messages here.'
        ];
    } elseif ($current_page == 'profile.php') {
        $tut_column = 'tut_profile';
        $tut_step = [
            'icon' => 'fa-user-cog',
            'title' => 'Your Dashboard',
            'desc' => 'Manage your ads, edit your profile, and reach out to our Help & Support team from here.'
        ];
    }

    $show_tutorial = false;
    if ($tut_column) {
        try {
            $stmt = $pdo->prepare("SELECT $tut_column FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $show_tutorial = ($stmt->fetchColumn() == 0);
        } catch (PDOException $e) {
            // Self-healing migration for new columns
            if ($e->getCode() == '42S22') {
                $pdo->exec("ALTER TABLE users ADD COLUMN tut_home TINYINT(1) DEFAULT 0, ADD COLUMN tut_post TINYINT(1) DEFAULT 0, ADD COLUMN tut_profile TINYINT(1) DEFAULT 0, ADD COLUMN tut_inbox TINYINT(1) DEFAULT 0");
                $show_tutorial = true;
            }
        }
    }
    ?>

    <?php if ($show_tutorial && $tut_step): ?>
        <div id="tutorial-overlay" class="tutorial-overlay">
            <div id="tutorial-card" class="tutorial-card">
                <i class="fa <?= $tut_step['icon'] ?> tutorial-icon-large"></i>
                <h2 class="tutorial-step-title"><?= $tut_step['title'] ?></h2>
                <p class="tutorial-step-desc"><?= $tut_step['desc'] ?></p>
                <button onclick="finishSectionTutorial('<?= $tut_column ?>')" class="btn-primary"
                    style="width: 100%; border: none; cursor: pointer;">Got it!</button>
            </div>
        </div>
        <script>
            async function finishSectionTutorial(section) {
                document.getElementById('tutorial-overlay').style.display = 'none';
                try {
                    await fetch('<?= $base_url ?>/user/api_finish_tutorial.php?section=' + section);
                } catch (e) { console.error(e); }
            }
        </script>
    <?php endif; ?>

    <script>
        function playBeep() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, audioCtx.currentTime);
                gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
                oscillator.start();
                gainNode.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.15);
                oscillator.stop(audioCtx.currentTime + 0.15);
            } catch (e) { }
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'toast-msg';
            toast.innerHTML = `<i class="fa fa-bell"></i> ${message}`;

            document.getElementById('toast-container').appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        let lastUnreadCount = -1;

        function updateUnreadBadge() {
            fetch('<?= $base_url ?>/user/api_unread_count.php')
                .then(res => res.json())
                .then(data => {
                    const count = data.success ? data.count : 0;
                    const dBadge = document.getElementById('desktop-unread-badge');
                    const mBadge = document.getElementById('mobile-unread-badge');

                    if (lastUnreadCount !== -1 && count > lastUnreadCount) {
                        showToast("You have a new message!");
                        playBeep();
                    }
                    if (count >= 0) lastUnreadCount = count;

                    if (count > 0) {
                        if (dBadge) { dBadge.style.display = 'inline-block'; dBadge.innerText = count > 99 ? '99+' : count; }
                        if (mBadge) { mBadge.style.display = 'flex'; mBadge.innerText = count > 99 ? '99+' : count; }
                    } else {
                        if (dBadge) dBadge.style.display = 'none';
                        if (mBadge) mBadge.style.display = 'none';
                    }
                })
                .catch(err => console.error("Error fetching unread count", err));
        }

        // Check unread count every 2 seconds for quicker notifications
        setInterval(updateUnreadBadge, 2000);
        // Initial check
        updateUnreadBadge();
    </script>
<?php endif; ?>

<?php
$ad_active = ($app_settings['interstitial_ad_active'] ?? '0') === '1';
$ad_frequency = (int) ($app_settings['interstitial_ad_frequency'] ?? 10);
$interstitial_ads = [];

if ($ad_active) {
    try {
        $stmt = $pdo->query("SELECT * FROM interstitial_ads ORDER BY id ASC");
        $interstitial_ads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $interstitial_ads = [];
    }
}

if ($ad_active && !empty($interstitial_ads)):
    // Format ads list for secure Javascript serialization
    $formatted_ads = [];
    foreach ($interstitial_ads as $ad) {
        $formatted_ads[] = [
            'id' => $ad['id'],
            'media_url' => (!empty($base_url) ? $base_url . '/' : '') . $ad['media_file'],
            'media_type' => $ad['media_type'],
            'link_url' => !empty($ad['link_url']) ? htmlspecialchars($ad['link_url']) : '#',
            'duration' => (int) $ad['duration']
        ];
    }
    ?>
    <!-- Premium Full-Screen Interstitial Ad Modal -->
    <div id="interstitial-ad-overlay" style="display: none;">
        <div class="interstitial-ad-wrapper">
            <!-- Close / Countdown Skip Trigger -->
            <button id="interstitial-skip-btn" onclick="dismissInterstitialAd()" disabled>
                Skip in <span id="interstitial-timer-count">5</span>s
            </button>

            <!-- Interactive Floating Speaker/Audio Toggle Overlay -->
            <button id="interstitial-volume-btn" style="display: none;" onclick="toggleInterstitialVolume(event)"
                title="Toggle Sound">
                <i class="fa fa-volume-mute"></i>
            </button>

            <!-- Media Container (No longer clickable directly) -->
            <div id="interstitial-ad-media" class="interstitial-ad-media-container">
                <!-- Populated dynamically via JS -->
            </div>

            <!-- Sleek Action Button Overlay at the bottom -->
            <a id="interstitial-cta-btn" href="#" target="_blank" class="interstitial-cta-button"
                onclick="dismissInterstitialAd()">
                Visit Sponsor <i class="fa fa-external-link-alt" style="margin-left: 6px; font-size: 11px;"></i>
            </a>
        </div>
    </div>

    <style>
        #interstitial-ad-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            height: -webkit-fill-available;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            box-sizing: border-box;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        #interstitial-ad-overlay.active {
            opacity: 1;
        }

        .interstitial-ad-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            max-width: 1000px;
            max-width: 90vw;
            max-height: 650px;
            max-height: 80vh;
            background: #000;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: modalPopUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            transition: max-width 0.4s cubic-bezier(0.25, 0.8, 0.25, 1), max-height 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .interstitial-ad-wrapper.portrait-mode {
            max-width: 520px;
            max-height: 85vh;
        }

        .interstitial-ad-media-container {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            overflow: hidden;
        }

        .interstitial-ad-media-container img,
        .interstitial-ad-media-container video {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .interstitial-cta-button {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-green);
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.4);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            z-index: 10;
            display: none;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .interstitial-cta-button:hover {
            background: #15803d;
            transform: translateX(-50%) scale(1.05);
            box-shadow: 0 12px 30px -5px rgba(22, 163, 74, 0.6);
            color: #ffffff;
        }

        #interstitial-skip-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 10px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            cursor: not-allowed;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        #interstitial-skip-btn.ready {
            cursor: pointer;
            background: #ffffff;
            color: #0f172a;
            border-color: #ffffff;
        }

        #interstitial-skip-btn.ready:hover {
            transform: scale(1.05);
        }

        #interstitial-volume-btn {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            animation: volumePulse 2s infinite;
        }

        #interstitial-volume-btn:hover {
            background: #ffffff;
            color: #0f172a;
            border-color: #ffffff;
            transform: scale(1.1);
        }

        body.ad-lock-scroll {
            overflow: hidden !important;
            touch-action: none;
        }

        @keyframes modalPopUp {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes volumePulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
            }
        }

        /* Full Screen bleeding view specifically on mobile */
        @media (max-width: 768px) {
            #interstitial-ad-overlay {
                padding: 0;
            }

            .interstitial-ad-wrapper {
                max-width: 100vw;
                max-height: 100vh;
                border-radius: 0;
                border: none;
            }

            #interstitial-skip-btn {
                top: env(safe-area-inset-top, 24px);
                right: 16px;
            }
        }
    </style>

    <script>
        const interstitialAdsList = <?= json_encode($formatted_ads) ?>;

        function dismissInterstitialAd() {
            const btn = document.getElementById('interstitial-skip-btn');
            if (btn && !btn.classList.contains('ready')) {
                return; // Not clickable yet
            }
            const overlay = document.getElementById('interstitial-ad-overlay');
            if (overlay) {
                overlay.classList.remove('active');
                setTimeout(() => {
                    overlay.style.display = 'none';
                    document.body.classList.remove('ad-lock-scroll');

                    // Stop video if active to save battery
                    const videoAd = document.getElementById('interstitial-ad-video');
                    if (videoAd) {
                        videoAd.pause();
                    }
                }, 400);
            }
        }

        function toggleInterstitialVolume(event) {
            event.preventDefault();
            event.stopPropagation();

            const videoAd = document.getElementById('interstitial-ad-video');
            const volumeBtn = document.getElementById('interstitial-volume-btn');

            if (videoAd && volumeBtn) {
                videoAd.muted = !videoAd.muted;
                if (videoAd.muted) {
                    volumeBtn.innerHTML = '<i class="fa fa-volume-mute"></i>';
                } else {
                    volumeBtn.innerHTML = '<i class="fa fa-volume-up"></i>';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (interstitialAdsList.length === 0) return;

            // 1. Block interstitial ads if an active announcement poster has not been shown yet in this session
            const hasActiveAnnouncement = <?= !empty($app_settings['announcement_poster']) ? 'true' : 'false' ?>;
            if (hasActiveAnnouncement && !sessionStorage.getItem('announcement_shown')) {
                console.log("Interstitial ad blocked: waiting for announcement poster to be shown first.");
                return;
            }

            // 2. Manage Page View tracking count
            const adFrequency = <?= $ad_frequency ?>;
            let pageViews = parseInt(localStorage.getItem('enteangadi_interstitial_page_views') || '0');

            // Increment page views
            pageViews++;
            localStorage.setItem('enteangadi_interstitial_page_views', pageViews);

            // 3. Check if we should trigger the overlay
            if (pageViews >= adFrequency) {
                // Reset counter immediately
                localStorage.setItem('enteangadi_interstitial_page_views', '0');

                // Fetch circular index
                let adIndex = parseInt(localStorage.getItem('enteangadi_interstitial_ad_index') || '0');
                if (adIndex >= interstitialAdsList.length) {
                    adIndex = 0;
                }

                const activeAd = interstitialAdsList[adIndex];

                // Store next index in circular rotation
                localStorage.setItem('enteangadi_interstitial_ad_index', (adIndex + 1) % interstitialAdsList.length);

                // Build and render HTML contents inside overlay
                const adMediaContainer = document.getElementById('interstitial-ad-media');
                const volumeBtn = document.getElementById('interstitial-volume-btn');
                const ctaBtn = document.getElementById('interstitial-cta-btn');
                const overlay = document.getElementById('interstitial-ad-overlay');

                // Reset wrapper layout styling classes first
                const adWrapper = document.querySelector('.interstitial-ad-wrapper');
                if (adWrapper) {
                    adWrapper.classList.remove('portrait-mode');
                }

                if (adMediaContainer && overlay) {
                    // Populate media tag
                    if (activeAd.media_type === 'video') {
                        adMediaContainer.innerHTML = `
                            <video id="interstitial-ad-video" src="${activeAd.media_url}" muted autoplay playsinline loop style="width: 100%; height: 100%; object-fit: contain; display: block;"></video>
                        `;
                        // Show volume button
                        if (volumeBtn) {
                            volumeBtn.style.display = 'flex';
                            volumeBtn.innerHTML = '<i class="fa fa-volume-mute"></i>';
                        }

                        // Flex wrapper according to video aspect ratio
                        const videoAd = document.getElementById('interstitial-ad-video');
                        if (videoAd && adWrapper) {
                            videoAd.addEventListener('loadedmetadata', () => {
                                if (videoAd.videoWidth < videoAd.videoHeight) {
                                    adWrapper.classList.add('portrait-mode');
                                }
                            });
                            // Immediately flex if metadata is already loaded (cached case)
                            if (videoAd.readyState >= 1) {
                                if (videoAd.videoWidth < videoAd.videoHeight) {
                                    adWrapper.classList.add('portrait-mode');
                                }
                            }
                        }
                    } else {
                        adMediaContainer.innerHTML = `
                            <img id="interstitial-ad-image" src="${activeAd.media_url}" alt="Sponsored Advertisement" style="width: 100%; height: 100%; object-fit: contain; display: block;">
                        `;
                        // Hide volume button
                        if (volumeBtn) {
                            volumeBtn.style.display = 'none';
                        }

                        // Flex wrapper according to image aspect ratio
                        const imageAd = document.getElementById('interstitial-ad-image');
                        if (imageAd && adWrapper) {
                            imageAd.addEventListener('load', () => {
                                if (imageAd.naturalWidth < imageAd.naturalHeight) {
                                    adWrapper.classList.add('portrait-mode');
                                }
                            });
                            // Trigger check immediately if cached
                            if (imageAd.complete) {
                                if (imageAd.naturalWidth < imageAd.naturalHeight) {
                                    adWrapper.classList.add('portrait-mode');
                                }
                            }
                        }
                    }

                    // Handle CTA button display
                    if (ctaBtn) {
                        if (activeAd.link_url && activeAd.link_url !== '#' && activeAd.link_url.trim() !== '') {
                            ctaBtn.href = activeAd.link_url;
                            ctaBtn.style.display = 'flex';
                        } else {
                            ctaBtn.style.display = 'none';
                        }
                    }

                    // Open overlay popup modal
                    overlay.style.display = 'flex';
                    overlay.offsetWidth; // force layout reflow
                    overlay.classList.add('active');
                    document.body.classList.add('ad-lock-scroll');

                    // Start video if active
                    const videoAd = document.getElementById('interstitial-ad-video');
                    if (videoAd) {
                        videoAd.play().catch(e => console.log("Autoplay was blocked by browser", e));
                    }

                    // 3. Countdown timer execution
                    let remainingSeconds = activeAd.duration;
                    const timerCount = document.getElementById('interstitial-timer-count');
                    const skipBtn = document.getElementById('interstitial-skip-btn');

                    if (skipBtn) {
                        skipBtn.classList.remove('ready');
                        skipBtn.disabled = true;

                        if (remainingSeconds <= 0) {
                            skipBtn.innerHTML = '<i class="fa fa-times" style="font-size: 14px;"></i> Close';
                            skipBtn.classList.add('ready');
                            skipBtn.disabled = false;
                        } else {
                            if (timerCount) {
                                timerCount.textContent = remainingSeconds;
                            }
                            skipBtn.innerHTML = `Skip in <span id="interstitial-timer-count">${remainingSeconds}</span>s`;

                            const countdownInterval = setInterval(() => {
                                remainingSeconds--;
                                const activeTimerCount = document.getElementById('interstitial-timer-count');
                                if (activeTimerCount) {
                                    activeTimerCount.textContent = remainingSeconds;
                                }

                                if (remainingSeconds <= 0) {
                                    clearInterval(countdownInterval);
                                    skipBtn.innerHTML = '<i class="fa fa-times" style="font-size: 14px;"></i> Close';
                                    skipBtn.classList.add('ready');
                                    skipBtn.disabled = false;
                                }
                            }, 1000);
                        }
                    }
                }
            }
        });
    </script>
<?php endif; ?>

<?php if (isset($_SESSION['user_id']) && !empty($app_settings['announcement_poster']) && basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], '/user/') !== false): ?>
    <!-- Announcement Poster Modal -->
    <div id="announcement-poster-overlay" class="announcement-overlay"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(8px); z-index: 99999; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.4s ease;">
        <div class="announcement-container"
            style="position: relative; max-width: 90%; max-height: 85%; width: 440px; background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.1); transform: scale(0.9); transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); display: flex; flex-direction: column;">

            <!-- Header -->
            <div
                style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid #f1f5f9; background: white;">
                <h3
                    style="margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-bullhorn" style="color: var(--primary-green);"></i> Announcement
                </h3>
                <button onclick="closeAnnouncementPoster()"
                    style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; font-size: 16px; font-weight: bold; transition: all 0.2s;"
                    onmouseover="this.style.background='#e2e8f0'; this.style.color='#1e293b';"
                    onmouseout="this.style.background='#f1f5f9'; this.style.color='#64748b';">&times;</button>
            </div>

            <!-- Body (Image) -->
            <div
                style="overflow-y: auto; flex: 1; padding: 16px; display: flex; align-items: center; justify-content: center; background: #f8fafc;">
                <img src="<?= $base_url ?>/<?= htmlspecialchars($app_settings['announcement_poster']) ?>" alt="Announcement"
                    style="max-width: 100%; max-height: 100%; border-radius: 12px; object-fit: contain; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            </div>

            <!-- Footer -->
            <div
                style="padding: 16px 24px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; background: white;">
                <button onclick="closeAnnouncementPoster()" class="btn-primary"
                    style="padding: 10px 24px; border-radius: 12px; font-size: 14px; font-weight: 700; width: 100%;">Got
                    it</button>
            </div>
        </div>
    </div>

    <script>
        function showAnnouncementPoster() {
            const overlay = document.getElementById('announcement-poster-overlay');
            const container = overlay.querySelector('.announcement-container');

            overlay.style.display = 'flex';
            overlay.offsetWidth; // Force reflow

            overlay.style.opacity = '1';
            container.style.transform = 'scale(1)';
            document.body.style.overflow = 'hidden'; // Lock background scroll
        }

        function closeAnnouncementPoster() {
            const overlay = document.getElementById('announcement-poster-overlay');
            const container = overlay.querySelector('.announcement-container');

            overlay.style.opacity = '0';
            container.style.transform = 'scale(0.9)';

            setTimeout(() => {
                overlay.style.display = 'none';
                document.body.style.overflow = ''; // Restore scroll
            }, 400);

            // Save states to prevent showing again
            try {
                sessionStorage.setItem('announcement_shown', 'true');
                localStorage.setItem('announcement_last_shown_date', new Date().toDateString());
            } catch (e) { }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Only show if not already shown in this session
            if (sessionStorage.getItem('announcement_shown') === 'true') {
                return;
            }
            // Always display announcement poster shortly after the loader/splash screen is removed
            setTimeout(() => {
                showAnnouncementPoster();
            }, 1600);
        });
    </script>
<?php endif; ?>

<script src="<?= $base_url ?? '/Enteangadi' ?>/assets/js/main.js"></script>
</body>

</html>