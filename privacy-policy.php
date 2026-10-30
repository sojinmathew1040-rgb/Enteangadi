<?php
require_once 'config.php';

$page_title = 'Privacy Policy';
$og_title = 'Privacy Policy - Enteangadi';
$og_desc = 'Learn how Enteangadi collects, uses, and protects your information across our website and mobile application.';

include 'includes/header.php';
$effective_date = 'October 30, 2026';
?>

<style>
.policy-wrap {
    max-width: 880px;
    margin: 28px auto 64px;
    padding: 0 16px;
}
.policy-hero {
    background: linear-gradient(135deg, #1B5E20 0%, #2E7D32 60%, #10b981 100%);
    color: #ffffff;
    border-radius: 20px;
    padding: 32px 28px;
    margin-bottom: 24px;
    box-shadow: 0 12px 30px rgba(27, 94, 32, 0.18);
}
.policy-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.18);
    padding: 5px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    margin-bottom: 12px;
}
.policy-hero h1 {
    margin: 0 0 8px;
    font-size: clamp(1.6rem, 4vw, 2.2rem);
    font-weight: 800;
    color: #ffffff;
}
.policy-hero p {
    margin: 0;
    font-size: 0.95rem;
    opacity: 0.92;
    line-height: 1.55;
}
.policy-card {
    background: #ffffff;
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
}
.policy-section {
    padding-bottom: 22px;
    margin-bottom: 22px;
    border-bottom: 1px solid #f1f5f9;
}
.policy-section:last-child {
    padding-bottom: 0;
    margin-bottom: 0;
    border-bottom: none;
}
.policy-section h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 10px;
}
.policy-section h2 i {
    color: #2E7D32;
    font-size: 1.05rem;
}
.policy-section p,
.policy-section li {
    color: #475569;
    font-size: 0.94rem;
    line-height: 1.68;
}
.policy-section p {
    margin: 0 0 10px;
}
.policy-section ul {
    margin: 8px 0 0;
    padding-left: 20px;
}
.policy-section li {
    margin-bottom: 6px;
}
.policy-perm-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
    margin-top: 12px;
}
.policy-perm-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
}
.policy-perm-box strong {
    display: block;
    color: #0f172a;
    font-size: 0.9rem;
    margin-bottom: 4px;
}
.policy-perm-box span {
    display: block;
    color: #64748b;
    font-size: 0.84rem;
    line-height: 1.5;
}
.policy-contact-box {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 14px;
    padding: 18px 20px;
    margin-top: 12px;
}
.policy-contact-box a {
    color: #166534;
    font-weight: 700;
    text-decoration: none;
}
.policy-contact-box a:hover {
    text-decoration: underline;
}
@media (max-width: 640px) {
    .policy-hero { padding: 24px 20px; }
    .policy-card { padding: 20px 18px; }
}
</style>

<main class="policy-wrap">
    <section class="policy-hero">
        <div class="policy-badge"><i class="fa-solid fa-shield-halved"></i> Official Policy</div>
        <h1>Privacy Policy</h1>
        <p>Effective Date: <?= $effective_date ?> &bull; Applies to the Enteangadi Website (enteangadi.com) and Mobile Application.</p>
    </section>

    <div class="policy-card">
        <section class="policy-section">
            <h2><i class="fa-solid fa-circle-info"></i> 1. Overview</h2>
            <p>Welcome to <strong>Enteangadi</strong> ("we", "our", or "us"). We value your trust and are committed to protecting your privacy. This Privacy Policy explains what information we collect, how we use and safeguard it, and the choices you have when using our local classifieds marketplace website and Android mobile application.</p>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-database"></i> 2. Information We Collect</h2>
            <ul>
                <li><strong>Account Information:</strong> When you register, we collect your username, phone number / WhatsApp number, optional email address, profile picture, and securely hashed password.</li>
                <li><strong>Marketplace Listings:</strong> When you post an ad (for sale, rent, or wanted), we collect the listing title, description, category, price, images, contact preferences, and listing location.</li>
                <li><strong>In-App Messages &amp; Media:</strong> When you communicate with buyers or sellers through Enteangadi Chat, we store text messages, shared photos, and voice notes to deliver conversations reliably.</li>
                <li><strong>Usage &amp; Technical Data:</strong> Basic request logs, browser/device type, language preference (English/Malayalam), and listing view counts used to keep the platform fast and secure.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-mobile-screen-button"></i> 3. Mobile App Permissions</h2>
            <p>Our mobile app requests device permissions strictly on an opt-in, feature-by-feature basis:</p>
            <div class="policy-perm-grid">
                <div class="policy-perm-box">
                    <strong><i class="fa-solid fa-location-dot" style="color:#16a34a;"></i> Location Access</strong>
                    <span>Used to show nearby local listings, filter by distance radius, and help tag ad locations. You can also select a location manually at any time.</span>
                </div>
                <div class="policy-perm-box">
                    <strong><i class="fa-solid fa-camera" style="color:#16a34a;"></i> Camera &amp; Photos</strong>
                    <span>Used only when you choose to capture or select photos for your marketplace ads, profile picture, or chat attachments.</span>
                </div>
                <div class="policy-perm-box">
                    <strong><i class="fa-solid fa-microphone" style="color:#16a34a;"></i> Microphone</strong>
                    <span>Used exclusively when you tap the microphone button to send a voice note in chat or perform a voice search. Audio is never recorded in the background.</span>
                </div>
                <div class="policy-perm-box">
                    <strong><i class="fa-solid fa-bell" style="color:#16a34a;"></i> Notifications</strong>
                    <span>Used to notify you about new buyer/seller messages and important updates regarding your listings. You can mute chats or disable notifications anytime.</span>
                </div>
            </div>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-gears"></i> 4. How We Use Your Information</h2>
            <ul>
                <li>Publish, display, and manage local buy, sell, and rent listings near your area.</li>
                <li>Connect buyers and sellers via in-app chat, phone call, or WhatsApp (according to the contact details you provide on your listing).</li>
                <li>Verify accounts, moderate prohibited or unsafe content, prevent spam/fraud, and enforce marketplace safety standards.</li>
                <li>Improve page speed, search relevance, and overall user experience.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-share-nodes"></i> 5. Information Sharing &amp; Visibility</h2>
            <p><strong>We do not sell or rent your personal data to third parties.</strong> Information is shared only in the following circumstances:</p>
            <ul>
                <li><strong>Public Listings:</strong> Information you include in a published ad (such as product photos, title, price, approximate location, seller name, and optional phone/WhatsApp number) is visible to marketplace visitors so buyers can reach you.</li>
                <li><strong>Service Providers:</strong> Trusted infrastructure providers (such as map tile/geocoding services, hosting, or optional automated image safety moderation) process minimal technical data needed to operate those features.</li>
                <li><strong>Legal Compliance:</strong> When required by applicable Indian law, court order, or lawful government request, or to protect the safety and rights of users.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-cookie-bite"></i> 6. Cookies, Local Storage &amp; Caching</h2>
            <p>Enteangadi uses essential cookies, browser local storage, and Service Worker caching to keep you signed in across sessions, remember your preferred location and language, and preload pages in the background for fast navigation. Clearing your browser data or signing out removes your active session tokens.</p>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-trash-can"></i> 7. Data Retention &amp; Account Deletion</h2>
            <ul>
                <li><strong>Listings &amp; Chats:</strong> You can edit or delete your listings at any time from <em>My Ads</em>, and delete individual chat messages ("Delete for me" or "Delete for everyone"). Expired listings and old chat media are periodically purged automatically.</li>
                <li><strong>Account &amp; Data Deletion Requests:</strong> You may request permanent deletion of your Enteangadi account and associated personal data at any time through your account support options or by emailing us at <a href="mailto:<?= defined('FROM_EMAIL') ? htmlspecialchars(FROM_EMAIL) : 'support@enteangadi.com' ?>" style="color:#166534;font-weight:700;"><?= defined('FROM_EMAIL') ? htmlspecialchars(FROM_EMAIL) : 'support@enteangadi.com' ?></a> with your registered phone number or username. Verified deletion requests are processed promptly.</li>
            </ul>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-lock"></i> 8. Data Security &amp; Children's Privacy</h2>
            <p>We protect your data using HTTPS/TLS encryption in transit, strong password hashing, and restricted administrative access. While no online service can guarantee absolute security, we continuously maintain safeguards to protect your account.</p>
            <p>Enteangadi is intended for individuals aged <strong>18 years and older</strong>. We do not knowingly collect personal information from children under 13.</p>
        </section>

        <section class="policy-section">
            <h2><i class="fa-solid fa-envelope"></i> 9. Updates &amp; Contact Us</h2>
            <p>We may update this Privacy Policy from time to time to reflect new features or legal requirements. Updated versions will be posted at this page with a revised effective date.</p>
            <div class="policy-contact-box">
                <strong>Questions or Privacy Requests?</strong><br>
                Email: <a href="mailto:<?= defined('FROM_EMAIL') ? htmlspecialchars(FROM_EMAIL) : 'support@enteangadi.com' ?>"><?= defined('FROM_EMAIL') ? htmlspecialchars(FROM_EMAIL) : 'support@enteangadi.com' ?></a><br>
                Website: <a href="<?= htmlspecialchars(($base_url ?: '') . '/privacy-policy') ?>">enteangadi.com/privacy-policy</a>
            </div>
        </section>
    </div>
</main>

<?php include 'includes/footer.php'; ?>

