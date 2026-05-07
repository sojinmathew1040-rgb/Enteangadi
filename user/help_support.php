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

$base_url = "http://" . $_SERVER['HTTP_HOST'] . "/Enteangadi";
require_once '../includes/header.php';
?>

<div class="container" style="max-width: 600px; padding-top: 40px; padding-bottom: 40px;">
    <a href="profile.php"
        style="text-decoration: none; color: var(--text-muted); display: inline-flex; align-items: center; gap: 8px; margin-bottom: 24px; font-weight: 600;">
        <i class="fa fa-arrow-left"></i> Back to Profile
    </a>

    <h2 style="color: var(--primary-green-dark); margin-bottom: 8px;">Help & Support</h2>
    <p style="color: var(--text-muted); margin-bottom: 32px;">We're here to help you. Reach out to us through any of the
        channels below.</p>

    <div
        style="background: var(--white); padding: 32px; border-radius: var(--border-radius); box-shadow: var(--shadow-sm);">

        <?php if (empty($support)): ?>
            <div style="text-align: center; padding: 40px 0;">
                <i class="fa fa-info-circle" style="font-size: 48px; color: #eee; margin-bottom: 16px;"></i>
                <p style="color: var(--text-muted);">No contact information available at the moment.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 24px;">

                <?php if (!empty($support['support_phone'])): ?>
                    <div
                        style="display: flex; align-items: center; gap: 16px; padding: 16px; border-radius: 16px; border: 1px solid #f1f5f9; background: #f8fafc;">
                        <div
                            style="width: 48px; height: 48px; border-radius: 12px; background: #f0fdf4; color: var(--primary-green); display: flex; align-items: center; justify-content: center; font-size: 20px;">
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
                    <div
                        style="display: flex; align-items: center; gap: 16px; padding: 16px; border-radius: 16px; border: 1px solid #f1f5f9; background: #f8fafc;">
                        <div
                            style="width: 48px; height: 48px; border-radius: 12px; background: #f0fdf4; color: var(--primary-green); display: flex; align-items: center; justify-content: center; font-size: 20px;">
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
                        style="text-decoration: none; display: flex; align-items: center; gap: 16px; padding: 20px; border-radius: 16px; background: #25D366; color: white; transition: transform 0.2s, opacity 0.2s;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.opacity='0.9';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.opacity='1';">
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
                <div style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 32px; text-align: center;">
                    <h4
                        style="font-size: 14px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px;">
                        Follow Us</h4>
                    <div style="display: flex; justify-content: center; gap: 24px;">
                        <?php if (!empty($support['facebook_url'])): ?>
                            <a href="<?= htmlspecialchars($support['facebook_url']) ?>" target="_blank"
                                style="color: #1877F2; font-size: 32px; transition: transform 0.2s;"
                                onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                <i class="fab fa-facebook"></i>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($support['instagram_url'])): ?>
                            <a href="<?= htmlspecialchars($support['instagram_url']) ?>" target="_blank"
                                style="color: #E4405F; font-size: 32px; transition: transform 0.2s;"
                                onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                <i class="fab fa-instagram"></i>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($support['twitter_url'])): ?>
                            <a href="<?= htmlspecialchars($support['twitter_url']) ?>" target="_blank"
                                style="color: #1DA1F2; font-size: 32px; transition: transform 0.2s;"
                                onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
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