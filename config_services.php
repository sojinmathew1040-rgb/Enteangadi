<?php
/**
 * Configuration for External Services (Email & WhatsApp)
 * Replace these placeholders with your real credentials when going live.
 */

// --- EMAIL SETTINGS (SMTP) ---
// If you are using Gmail, you need an "App Password"
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_PORT', 587);
define('FROM_EMAIL', 'support@enteangadi.com');
define('FROM_NAME', 'Enteangadi Support');

// --- WHATSAPP API SETTINGS ---
// This is a generic setup. Most providers use an API Key and a URL.
define('WA_API_KEY', 'YOUR_API_KEY');
define('WA_API_URL', 'https://api.whatsapp.provider.com/send');
define('WA_TEMPLATE_ID', 'password_reset_v1'); // DLT/Provider Template ID
?>