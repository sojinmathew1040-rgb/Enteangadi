<!-- Mobile Bottom Navigation -->
<nav class="mobile-bottom-nav">
    <a href="<?= $base_url ?>/index.php"
        class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" title="Home">
        <i class="fa fa-home"></i>
        <span>Home</span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $base_url ?>/user/inbox.php"
            class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'inbox.php' ? 'active' : '' ?>" title="Chats">
            <i class="fa fa-comments"></i>
            <span>Chats</span>
            <span id="mobile-unread-badge"
                style="display: none; position: absolute; top: 4px; right: 20%; background: var(--danger); color: white; border-radius: 50%; width: 14px; height: 14px; align-items: center; justify-content: center; font-size: 9px; font-weight: bold; border: 2px solid white;">0</span>
        </a>
        <a href="<?= $base_url ?>/user/post_ad.php" class="nav-item sell-center" title="Post">
            <div class="sell-circle">
                <i class="fa fa-plus"></i>
            </div>
            <span>POST</span>
        </a>
        <a href="<?= $base_url ?>/user/my_ads.php"
            class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'my_ads.php' ? 'active' : '' ?>" title="My Ads">
            <i class="fa fa-list-alt"></i>
            <span>My Ads</span>
        </a>
        <a href="<?= $base_url ?>/user/profile.php"
            class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" title="Account">
            <i class="fa fa-user"></i>
            <span>Account</span>
        </a>
    <?php else: ?>
        <a href="<?= $base_url ?>/login.php" class="nav-item" title="Login">
            <i class="fa fa-sign-in-alt"></i>
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
            toast.style.background = 'var(--primary-green-dark, #2e7d32)';
            toast.style.color = 'white';
            toast.style.padding = '12px 24px';
            toast.style.borderRadius = '8px';
            toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            toast.style.fontSize = '14px';
            toast.style.fontWeight = '500';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            toast.style.transition = 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.gap = '8px';
            toast.innerHTML = `<i class="fa fa-bell"></i> ${message}`;

            document.getElementById('toast-container').appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            }, 10);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-20px)';
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

        // Check unread count every 5 seconds for quicker notifications
        setInterval(updateUnreadBadge, 5000);
        // Initial check
        updateUnreadBadge();
    </script>
<?php endif; ?>

<script src="<?= $base_url ?? '/Enteangadi' ?>/assets/js/main.js"></script>
</body>

</html>