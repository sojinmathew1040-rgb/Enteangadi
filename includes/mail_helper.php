<?php
require_once dirname(__FILE__) . '/../config_services.php';

/**
 * Sends a password reset email.
 * This version uses the PHP mail() function as a fallback,
 * but is structured to easily integrate with PHPMailer.
 */
function sendResetEmail($toEmail, $username, $resetLink)
{
    $subject = "Reset Your Enteangadi Password";

    // HTML Email Content
    $message = "
    <html>
    <head>
        <title>Reset Your Password</title>
        <style>
            .container { font-family: sans-serif; padding: 20px; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; }
            .header { text-align: center; margin-bottom: 30px; }
            .button-container { text-align: center; margin: 40px 0; }
            .button { background-color: #2E7D32; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; }
            .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2 style='color: #2E7D32;'>Enteangadi</h2>
            </div>
            <p>Hi <strong>$username</strong>,</p>
            <p>We received a request to reset your password. Click the button below to choose a new one:</p>
            
            <div class='button-container'>
                <a href='$resetLink' class='button'>Reset My Password</a>
            </div>
            
            <p style='font-size: 14px; color: #666;'>If the button doesn't work, copy and paste this link into your browser:</p>
            <p style='font-size: 12px; color: #2E7D32; word-break: break-all;'>$resetLink</p>
            
            <p>If you didn't request this, you can safely ignore this email.</p>
            
            <div class='footer'>
                This link will expire in 1 hour.<br>
                &copy; " . date('Y') . " Enteangadi Marketplace
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">" . "\r\n";
    $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";

    // In production with a real SMTP, use PHPMailer here.
    // For now, we use the system mailer with @ to suppress local errors.
    return @mail($toEmail, $subject, $message, $headers);
}

/**
 * Sends a password reset link via WhatsApp API
 */
function sendWhatsAppReset($phoneNumber, $username, $resetLink)
{
    // Clean phone number (remove +, spaces, etc.)
    $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);

    // Check if we have a real API URL
    if (WA_API_KEY === 'YOUR_API_KEY' || empty(WA_API_URL)) {
        return false; // Force fallback to manual link
    }

    // Example API Call using cURL (Standard for most WhatsApp Providers)
    $payload = [
        'apikey' => WA_API_KEY,
        'mobile' => $cleanPhone,
        'username' => $username,
        'link' => $resetLink,
        'template_id' => WA_TEMPLATE_ID
    ];

    $ch = curl_init(WA_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return ($httpCode == 200);
}
?>