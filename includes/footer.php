<!-- Mobile Bottom Navigation -->
<?php
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$home_href = ($base_url ?? '') . (isset($_SESSION['user_id']) ? '/user/index.php' : '/guest/index.php');
$main_js_v = @filemtime(dirname(__DIR__) . '/assets/js/main.js') ?: 1;
?>
<nav class="mobile-bottom-nav">
    <a href="<?= $home_href ?>" class="nav-item <?= $current_page === 'index.php' ? 'active' : '' ?>" title="Home">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $base_url ?>/user/inbox.php" class="nav-item <?= ($current_page === 'inbox.php' || $current_page === 'chat.php') ? 'active' : '' ?>" title="Chats">
            <i class="fa-solid fa-comments"></i>
            <span>Chats</span>
            <span id="mobile-unread-badge" style="display:none;position:absolute;top:4px;right:20%;background:var(--danger);color:#fff;border-radius:50%;width:14px;height:14px;align-items:center;justify-content:center;font-size:9px;font-weight:bold;border:2px solid #fff;">0</span>
        </a>
        <a href="<?= $base_url ?>/user/post_ad.php" class="nav-item <?= $current_page === 'post_ad.php' ? 'active' : '' ?> post-tab" title="Post">
            <i class="fa-solid fa-circle-plus"></i>
            <span>Post</span>
        </a>
        <a href="<?= $base_url ?>/user/my_ads.php" class="nav-item <?= $current_page === 'my_ads.php' ? 'active' : '' ?>" title="My Ads">
            <i class="fa-solid fa-list"></i>
            <span>My Ads</span>
        </a>
        <a href="<?= $base_url ?>/user/profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>" title="Account">
            <i class="fa-solid fa-user"></i>
            <span>Account</span>
        </a>
    <?php else: ?>
        <a href="<?= $base_url ?>/login.php" class="nav-item <?= $current_page === 'login.php' ? 'active' : '' ?>" title="Login">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span>Login</span>
        </a>
    <?php endif; ?>
</nav>

<div id="toast-container" style="position:fixed;top:80px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;"></div>

<footer>
    <div class="container" style="padding:0;">
        <p>&copy; <?= date('Y') ?> Enteangadi. All rights reserved.</p>
    </div>
</footer>

<?php if (isset($_SESSION['user_id'])): ?>
    <?php
    $tut_map = [
        'index.php' => ['col' => 'tut_home', 'icon' => 'fa-hand-sparkles', 'title' => 'Welcome to Enteangadi', 'desc' => 'Your local marketplace. Use the search bar and category icons above to explore listings near you.'],
        'post_ad.php' => ['col' => 'tut_post', 'icon' => 'fa-plus-circle', 'title' => 'Post Your Ad', 'desc' => 'Selling is easy! Fill in the details, upload photos, and set your price to reach local buyers.'],
        'inbox.php' => ['col' => 'tut_inbox', 'icon' => 'fa-comments', 'title' => 'Messages & Chats', 'desc' => 'Chat directly with buyers and sellers. You will get instant notifications for new messages here.'],
        'chat.php' => ['col' => 'tut_inbox', 'icon' => 'fa-comments', 'title' => 'Messages & Chats', 'desc' => 'Chat directly with buyers and sellers. You will get instant notifications for new messages here.'],
        'profile.php' => ['col' => 'tut_profile', 'icon' => 'fa-user-cog', 'title' => 'Your Dashboard', 'desc' => 'Manage your ads, edit your profile, and reach out to our Help & Support team from here.']
    ];
    $tut_step = $tut_map[$current_page] ?? null;
    $tut_column = $tut_step['col'] ?? '';
    $show_tutorial = false;

    if ($tut_column && empty($_SESSION['_tut_done'][$tut_column])) {
        try {
            $stmt = $pdo->prepare("SELECT `$tut_column` FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            if ((int)$stmt->fetchColumn() === 0) {
                $show_tutorial = true;
            } else {
                $_SESSION['_tut_done'][$tut_column] = true;
            }
        } catch (PDOException $e) {
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
                <button onclick="finishSectionTutorial('<?= $tut_column ?>')" class="btn-primary" style="width:100%;border:none;cursor:pointer;">Got it!</button>
            </div>
        </div>
        <script>
            async function finishSectionTutorial(section) {
                document.getElementById('tutorial-overlay').style.display = 'none';
                try { await fetch('<?= $base_url ?>/user/api_finish_tutorial.php?section=' + section); } catch (e) {}
            }
        </script>
    <?php endif; ?>

    <script>
        function playBeep() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator(), gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.type = 'sine'; osc.frequency.setValueAtTime(880, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
                osc.start();
                gain.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + 0.15);
                osc.stop(audioCtx.currentTime + 0.15);
            } catch (e) {}
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'toast-msg';
            toast.innerHTML = `<i class="fa fa-bell"></i> ${message}`;
            document.getElementById('toast-container').appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 300); }, 4000);
        }

        function updateUnreadBadge() {
            if (document.hidden) return;
            fetch('<?= $base_url ?>/user/api_unread_messages.php')
                .then(res => res.json())
                .then(data => {
                    const messages = (data.success && data.messages) ? data.messages : [];
                    const count = messages.length;
                    const dBadge = document.getElementById('desktop-unread-badge');
                    const mBadge = document.getElementById('mobile-unread-badge');

                    const isFirstRun = localStorage.getItem('last_notified_msg_id') === null;
                    let lastNotifiedId = parseInt(localStorage.getItem('last_notified_msg_id') || '0', 10);
                    let maxId = lastNotifiedId, hasNewMessage = false;
                    const isChatPage = window.location.pathname.endsWith('chat.php');

                    if (isFirstRun) {
                        let initialMax = 0;
                        messages.forEach(msg => { const id = parseInt(msg.id, 10); if (id > initialMax) initialMax = id; });
                        localStorage.setItem('last_notified_msg_id', String(initialMax));
                    } else {
                        for (let i = messages.length - 1; i >= 0; i--) {
                            const msg = messages[i], msgId = parseInt(msg.id, 10);
                            if (msgId > lastNotifiedId) {
                                if (msgId > maxId) maxId = msgId;
                                hasNewMessage = true;
                                if (!isChatPage) {
                                    if (window.EnteangadiMobile && typeof window.EnteangadiMobile.showLocalNotification === 'function') {
                                        window.EnteangadiMobile.showLocalNotification(msg.sender_name || 'User', msg.message_text, msg.sender_id, msg.product_id);
                                    }
                                    showToast("New message from " + (msg.sender_name || "User"));
                                }
                            }
                        }
                    }

                    if (hasNewMessage) {
                        localStorage.setItem('last_notified_msg_id', String(maxId));
                        playBeep();
                    }

                    if (count > 0) {
                        if (dBadge) { dBadge.style.display = 'inline-block'; dBadge.innerText = count > 99 ? '99+' : count; }
                        if (mBadge) { mBadge.style.display = 'flex'; mBadge.innerText = count > 99 ? '99+' : count; }
                    } else {
                        if (dBadge) dBadge.style.display = 'none';
                        if (mBadge) mBadge.style.display = 'none';
                    }
                })
                .catch(() => {});
        }

        setTimeout(updateUnreadBadge, 1500);
        setInterval(updateUnreadBadge, 12000);
    </script>
<?php endif; ?>

<?php
$ad_active = ($app_settings['interstitial_ad_active'] ?? '0') === '1';
$ad_frequency = (int)($app_settings['interstitial_ad_frequency'] ?? 10);
$interstitial_ads = [];

if ($ad_active) {
    if (isset($_SESSION['_interstitial_ads_cache'], $_SESSION['_interstitial_ads_ts']) && (time() - $_SESSION['_interstitial_ads_ts']) < 60) {
        $interstitial_ads = $_SESSION['_interstitial_ads_cache'];
    } else {
        try {
            $interstitial_ads = $pdo->query("SELECT * FROM interstitial_ads ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $_SESSION['_interstitial_ads_cache'] = $interstitial_ads;
            $_SESSION['_interstitial_ads_ts'] = time();
        } catch (PDOException $e) {
            $interstitial_ads = [];
        }
    }
}

if ($ad_active && !empty($interstitial_ads)):
    $formatted_ads = [];
    foreach ($interstitial_ads as $ad) {
        $formatted_ads[] = [
            'id' => $ad['id'],
            'media_url' => (!empty($base_url) ? $base_url . '/' : '') . $ad['media_file'],
            'media_type' => $ad['media_type'],
            'link_url' => !empty($ad['link_url']) ? htmlspecialchars($ad['link_url']) : '#',
            'duration' => (int)$ad['duration']
        ];
    }
    ?>
    <div id="interstitial-ad-overlay" style="display:none;">
        <div class="interstitial-ad-wrapper">
            <button id="interstitial-skip-btn" onclick="dismissInterstitialAd()" disabled>Skip in <span id="interstitial-timer-count">5</span>s</button>
            <button id="interstitial-volume-btn" style="display:none;" onclick="toggleInterstitialVolume(event)" title="Toggle Sound"><i class="fa fa-volume-mute"></i></button>
            <div id="interstitial-ad-media" class="interstitial-ad-media-container"></div>
            <a id="interstitial-cta-btn" href="#" target="_blank" class="interstitial-cta-button" onclick="dismissInterstitialAd()">Visit Sponsor <i class="fa fa-external-link-alt" style="margin-left:6px;font-size:11px;"></i></a>
        </div>
    </div>

    <style>
        #interstitial-ad-overlay{position:fixed;inset:0;width:100vw;height:100vh;background:rgba(15,23,42,.75);backdrop-filter:blur(12px);z-index:999999;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;opacity:0;transition:opacity .4s ease}
        #interstitial-ad-overlay.active{opacity:1}
        .interstitial-ad-wrapper{position:relative;width:100%;height:100%;max-width:90vw;max-height:80vh;background:#000;border-radius:24px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center}
        .interstitial-ad-wrapper.portrait-mode{max-width:520px;max-height:85vh}
        .interstitial-ad-media-container{width:100%;height:100%;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .interstitial-ad-media-container img,.interstitial-ad-media-container video{width:100%;height:100%;object-fit:contain;display:block}
        .interstitial-cta-button{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);background:var(--primary-green);color:#fff;padding:12px 28px;border-radius:50px;font-size:13px;font-weight:700;text-decoration:none;z-index:10;display:none;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:.5px}
        #interstitial-skip-btn{position:absolute;top:20px;right:20px;background:rgba(15,23,42,.7);backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,.15);color:#fff;padding:10px 18px;border-radius:50px;font-size:13px;font-weight:700;cursor:not-allowed;z-index:10;display:flex;align-items:center;gap:4px}
        #interstitial-skip-btn.ready{cursor:pointer;background:#fff;color:#0f172a;border-color:#fff}
        #interstitial-volume-btn{position:absolute;bottom:20px;right:20px;background:rgba(15,23,42,.7);border:1px solid rgba(255,255,255,.15);color:#fff;width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;cursor:pointer;z-index:10}
        body.ad-lock-scroll{overflow:hidden !important;touch-action:none}
        @media(max-width:768px){#interstitial-ad-overlay{padding:0}.interstitial-ad-wrapper{max-width:100vw;max-height:100vh;border-radius:0;border:none}#interstitial-skip-btn{top:env(safe-area-inset-top,24px);right:16px}}
    </style>

    <script>
        const interstitialAdsList = <?= json_encode($formatted_ads) ?>;

        function dismissInterstitialAd() {
            const btn = document.getElementById('interstitial-skip-btn');
            if (btn && !btn.classList.contains('ready')) return;
            const overlay = document.getElementById('interstitial-ad-overlay');
            if (overlay) {
                overlay.classList.remove('active');
                setTimeout(() => {
                    overlay.style.display = 'none';
                    document.body.classList.remove('ad-lock-scroll');
                    const videoAd = document.getElementById('interstitial-ad-video');
                    if (videoAd) videoAd.pause();
                }, 350);
            }
        }

        function toggleInterstitialVolume(event) {
            event.preventDefault(); event.stopPropagation();
            const videoAd = document.getElementById('interstitial-ad-video');
            const volumeBtn = document.getElementById('interstitial-volume-btn');
            if (videoAd && volumeBtn) {
                videoAd.muted = !videoAd.muted;
                volumeBtn.innerHTML = videoAd.muted ? '<i class="fa fa-volume-mute"></i>' : '<i class="fa fa-volume-up"></i>';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (!interstitialAdsList.length) return;
            if (<?= !empty($app_settings['announcement_poster']) ? 'true' : 'false' ?> && !sessionStorage.getItem('announcement_shown')) return;

            const adFrequency = <?= $ad_frequency ?>;
            let pageViews = parseInt(localStorage.getItem('enteangadi_interstitial_page_views') || '0', 10) + 1;
            localStorage.setItem('enteangadi_interstitial_page_views', String(pageViews));
            if (pageViews < adFrequency) return;

            localStorage.setItem('enteangadi_interstitial_page_views', '0');
            let adIndex = parseInt(localStorage.getItem('enteangadi_interstitial_ad_index') || '0', 10);
            if (adIndex >= interstitialAdsList.length) adIndex = 0;
            const activeAd = interstitialAdsList[adIndex];
            localStorage.setItem('enteangadi_interstitial_ad_index', String((adIndex + 1) % interstitialAdsList.length));

            const adMediaContainer = document.getElementById('interstitial-ad-media');
            const volumeBtn = document.getElementById('interstitial-volume-btn');
            const ctaBtn = document.getElementById('interstitial-cta-btn');
            const overlay = document.getElementById('interstitial-ad-overlay');
            const adWrapper = document.querySelector('.interstitial-ad-wrapper');
            if (!adMediaContainer || !overlay) return;
            if (adWrapper) adWrapper.classList.remove('portrait-mode');

            if (activeAd.media_type === 'video') {
                adMediaContainer.innerHTML = `<video id="interstitial-ad-video" src="${activeAd.media_url}" muted autoplay playsinline loop style="width:100%;height:100%;object-fit:contain;display:block;"></video>`;
                if (volumeBtn) { volumeBtn.style.display = 'flex'; volumeBtn.innerHTML = '<i class="fa fa-volume-mute"></i>'; }
                const v = document.getElementById('interstitial-ad-video');
                if (v && adWrapper) v.addEventListener('loadedmetadata', () => { if (v.videoWidth < v.videoHeight) adWrapper.classList.add('portrait-mode'); });
            } else {
                adMediaContainer.innerHTML = `<img id="interstitial-ad-image" src="${activeAd.media_url}" alt="Sponsored" style="width:100%;height:100%;object-fit:contain;display:block;">`;
                if (volumeBtn) volumeBtn.style.display = 'none';
                const im = document.getElementById('interstitial-ad-image');
                if (im && adWrapper) im.addEventListener('load', () => { if (im.naturalWidth < im.naturalHeight) adWrapper.classList.add('portrait-mode'); });
            }

            if (ctaBtn) {
                const hasLink = activeAd.link_url && activeAd.link_url !== '#' && activeAd.link_url.trim() !== '';
                ctaBtn.href = hasLink ? activeAd.link_url : '#';
                ctaBtn.style.display = hasLink ? 'flex' : 'none';
            }

            overlay.style.display = 'flex';
            overlay.offsetWidth;
            overlay.classList.add('active');
            document.body.classList.add('ad-lock-scroll');
            const videoAd = document.getElementById('interstitial-ad-video');
            if (videoAd) videoAd.play().catch(() => {});

            let remaining = activeAd.duration;
            const skipBtn = document.getElementById('interstitial-skip-btn');
            if (skipBtn) {
                const unlock = () => { skipBtn.innerHTML = '<i class="fa fa-times" style="font-size:14px;"></i> Close'; skipBtn.classList.add('ready'); skipBtn.disabled = false; };
                skipBtn.classList.remove('ready'); skipBtn.disabled = true;
                if (remaining <= 0) unlock();
                else {
                    skipBtn.innerHTML = `Skip in <span id="interstitial-timer-count">${remaining}</span>s`;
                    const iv = setInterval(() => {
                        remaining--;
                        const tc = document.getElementById('interstitial-timer-count');
                        if (tc) tc.textContent = remaining;
                        if (remaining <= 0) { clearInterval(iv); unlock(); }
                    }, 1000);
                }
            }
        });
    </script>
<?php endif; ?>

<?php if (!empty($app_settings['announcement_poster']) && ($current_page === 'index.php' || strpos($_SERVER['PHP_SELF'] ?? '', '/guest/') !== false || strpos($_SERVER['PHP_SELF'] ?? '', '/user/') !== false)): ?>
    <?php
    $poster_disk = dirname(__DIR__) . '/' . $app_settings['announcement_poster'];
    $poster_v = file_exists($poster_disk) ? filemtime($poster_disk) : 1;
    ?>
    <div id="announcement-poster-overlay" class="announcement-overlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.75);backdrop-filter:blur(8px);z-index:99999;justify-content:center;align-items:center;opacity:0;transition:opacity .35s ease;">
        <div class="announcement-container" style="position:relative;max-width:90%;max-height:85%;width:440px;background:#fff;border-radius:24px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,.25);transform:scale(.9);transition:transform .35s cubic-bezier(.34,1.56,.64,1);display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid #f1f5f9;background:#fff;">
                <h3 style="margin:0;font-size:18px;font-weight:800;color:#1e293b;display:flex;align-items:center;gap:8px;"><i class="fa fa-bullhorn" style="color:var(--primary-green);"></i> Announcement</h3>
                <button onclick="closeAnnouncementPoster()" style="background:#f1f5f9;border:none;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#64748b;font-size:16px;font-weight:bold;">&times;</button>
            </div>
            <div style="overflow-y:auto;flex:1;padding:16px;display:flex;align-items:center;justify-content:center;background:#f8fafc;">
                <img src="<?= $base_url ?>/<?= htmlspecialchars($app_settings['announcement_poster']) ?>?v=<?= $poster_v ?>" alt="Announcement" loading="lazy" style="max-width:100%;max-height:100%;border-radius:12px;object-fit:contain;">
            </div>
            <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;background:#fff;">
                <button onclick="closeAnnouncementPoster()" class="btn-primary" style="padding:10px 24px;border-radius:12px;font-size:14px;font-weight:700;width:100%;">Got it</button>
            </div>
        </div>
    </div>

    <script>
        function showAnnouncementPoster() {
            const overlay = document.getElementById('announcement-poster-overlay');
            if (!overlay) return;
            const container = overlay.querySelector('.announcement-container');
            overlay.style.display = 'flex';
            overlay.offsetWidth;
            overlay.style.opacity = '1';
            if (container) container.style.transform = 'scale(1)';
            document.body.style.overflow = 'hidden';
            try {
                const fd = new FormData();
                fd.append('poster', '<?= htmlspecialchars($app_settings['announcement_poster'] ?? '') ?>');
                fetch('<?= $base_url ?>/api/track_announcement.php', { method: 'POST', body: fd }).catch(() => {});
            } catch (e) {}
        }

        function closeAnnouncementPoster() {
            const overlay = document.getElementById('announcement-poster-overlay');
            if (!overlay) return;
            const container = overlay.querySelector('.announcement-container');
            overlay.style.opacity = '0';
            if (container) container.style.transform = 'scale(0.9)';
            setTimeout(() => { overlay.style.display = 'none'; document.body.style.overflow = ''; }, 350);
            try {
                sessionStorage.setItem('announcement_shown', 'true');
                localStorage.setItem('announcement_last_shown_date', new Date().toDateString());
            } catch (e) {}
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (sessionStorage.getItem('announcement_shown') === 'true') return;
            setTimeout(showAnnouncementPoster, 900);
        });
    </script>
<?php endif; ?>

<script src="<?= $base_url ?? '' ?>/assets/js/main.js?v=<?= $main_js_v ?>"></script>
</body>
</html>
