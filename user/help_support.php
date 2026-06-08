<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Fetch support settings
try {
    $stmt = $pdo->query("SELECT * FROM app_settings WHERE setting_key IN ('support_email', 'support_phone', 'whatsapp_number', 'facebook_url', 'instagram_url', 'twitter_url')");
    $settings_raw = $stmt->fetchAll();
    $support = [];
    foreach ($settings_raw as $s) {
        $support[$s['setting_key']] = $s['setting_value'];
    }
} catch (PDOException $e) {
    $support = [];
}

require_once '../includes/header.php';
?>

<div class="support-container">
    <a href="profile.php" class="support-back-link">
        <i class="fa fa-arrow-left"></i> Back to Profile
    </a>

    <h2 style="color: var(--primary-green-dark); margin-bottom: 8px;">Help & Support</h2>
    <p style="color: var(--text-muted); margin-bottom: 32px;">We're here to help you. Reach out to us through any of the
        channels below.</p>

    <div class="support-card">

        <?php if (empty($support)): ?>
            <div style="text-align: center; padding: 40px 0;">
                <i class="fa fa-info-circle" style="font-size: 48px; color: #eee; margin-bottom: 16px;"></i>
                <p style="color: var(--text-muted);">No contact information available at the moment.</p>
            </div>
        <?php else: ?>
            <div class="support-channels">

                <?php if (!empty($support['support_phone'])): ?>
                    <div class="support-channel">
                        <div class="support-icon-box">
                            <i class="fa fa-phone"></i>
                        </div>
                        <div>
                            <span style="display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 2px;">Call
                                Support</span>
                            <span
                                style="font-weight: 700; font-size: 16px;"><?= htmlspecialchars($support['support_phone']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($support['support_email'])): ?>
                    <div class="support-channel">
                        <div class="support-icon-box">
                            <i class="fa fa-envelope"></i>
                        </div>
                        <div>
                            <span style="display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 2px;">Email
                                Support</span>
                            <span
                                style="font-weight: 700; font-size: 16px;"><?= htmlspecialchars($support['support_email']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($support['whatsapp_number'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $support['whatsapp_number']) ?>" target="_blank"
                        class="support-whatsapp-btn">
                        <i class="fab fa-whatsapp" style="font-size: 28px;"></i>
                        <div style="flex: 1;">
                            <span style="display: block; font-size: 13px; opacity: 0.9; margin-bottom: 2px;">Chat with us</span>
                            <span style="font-weight: 700; font-size: 18px;">WhatsApp Support</span>
                        </div>
                        <i class="fa fa-external-link-alt" style="opacity: 0.7;"></i>
                    </a>
                <?php endif; ?>

            </div>

            <!-- Social Media Section -->
            <?php if (!empty($support['facebook_url']) || !empty($support['instagram_url']) || !empty($support['twitter_url'])): ?>
                <div class="support-social-section">
                    <h4
                        style="font-size: 14px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px;">
                        Follow Us</h4>
                    <div class="support-social-grid">
                        <?php if (!empty($support['facebook_url'])): ?>
                            <a href="<?= htmlspecialchars($support['facebook_url']) ?>" target="_blank" class="support-social-icon"
                                style="color: #1877F2;">
                                <i class="fab fa-facebook"></i>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($support['instagram_url'])): ?>
                            <a href="<?= htmlspecialchars($support['instagram_url']) ?>" target="_blank" class="support-social-icon"
                                style="color: #E4405F;">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($support['twitter_url'])): ?>
                            <a href="<?= htmlspecialchars($support['twitter_url']) ?>" target="_blank" class="support-social-icon"
                                style="color: #1DA1F2;">
                                <i class="fab fa-twitter"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>