<?php
/**
 * Shared helper functions for Enteangadi
 */

if (!function_exists('recompressTo50kb')) {
    /**
     * Re-compresses an image to target ~50kb size to save server space.
     * 
     * @param string $sourcePath Absolute path to the image
     * @return bool Success or failure
     */
    function recompressTo50kb($sourcePath)
    {
        if (!file_exists($sourcePath))
            return false;

        if (!function_exists('imagecreatefromjpeg'))
            return false;

        $info = getimagesize($sourcePath);
        if (!$info)
            return false;

        if ($info['mime'] == 'image/jpeg')
            $image = @imagecreatefromjpeg($sourcePath);
        elseif ($info['mime'] == 'image/png')
            $image = @imagecreatefrompng($sourcePath);
        elseif ($info['mime'] == 'image/gif')
            $image = @imagecreatefromgif($sourcePath);
        else
            return false;

        if (!$image)
            return false;

        // "Compress Extra" - Target very low quality for archived/expired items
        imagejpeg($image, $sourcePath, 20);
        return true;
    }
}

/**
 * Compresses and resizes an uploaded image.
 */
if (!function_exists('compressAndResizeImage')) {
    function compressAndResizeImage($source, $destination, $max_width = 800, $quality = 60)
    {
        if (!function_exists('imagecreatefromjpeg')) {
            // Fallback: Just copy file without compression if GD is missing
            $copied = copy($source, $destination);
            if ($copied) {
                @chmod($destination, 0644);
            }
            return $copied;
        }

        $info = getimagesize($source);
        if (!$info)
            return false;

        if ($info['mime'] == 'image/jpeg')
            $image = @imagecreatefromjpeg($source);
        elseif ($info['mime'] == 'image/gif')
            $image = @imagecreatefromgif($source);
        elseif ($info['mime'] == 'image/png')
            $image = @imagecreatefrompng($source);
        else
            return false;

        if (!$image)
            return false;

        list($width, $height) = getimagesize($source);
        $ratio = $width / $height;

        if ($width > $max_width) {
            $new_width = $max_width;
            $new_height = $max_width / $ratio;
        } else {
            $new_width = $width;
            $new_height = $height;
        }

        $new_image = imagecreatetruecolor($new_width, $new_height);

        if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
        }

        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        $result = imagejpeg($new_image, $destination, $quality);
        if ($result) {
            @chmod($destination, 0644);
        }

        return $result;
    }
}

/**
 * Multi-language Translation Helper
 */
if (!function_exists('__')) {
    function __($key)
    {
        static $translations = null;

        if ($translations === null) {
            $lang = $_SESSION['lang'] ?? 'en';
            $lang_file = __DIR__ . "/lang/{$lang}.php";

            if (file_exists($lang_file)) {
                $translations = require $lang_file;
            } else {
                $translations = [];
            }
        }

        return $translations[$key] ?? $key;
    }
}

if (!function_exists('isTextInappropriate')) {
    /**
     * Checks if the given text contains any 18+ adult/restricted keywords or profanity.
     * Checks in English and Malayalam transliterations.
     * 
     * @param string $text The text input to validate (title or description)
     * @return bool True if inappropriate content is found, false otherwise
     */
    function isTextInappropriate($text)
    {
        global $pdo;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'adult_content_check'");
                $stmt->execute();
                $check = $stmt->fetchColumn();
                if ($check === '0') {
                    return false;
                }
            } catch (Exception $e) {
                // Fail silently
            }
        }

        if (empty($text)) {
            return false;
        }

        // List of inappropriate / 18+ banned words (English and popular Malayalam terms)
        $banned_words = [
            // Adult/Sexual Content
            'sex', 'porn', 'nude', 'naked', 'erotic', 'escort', 'massage parlour', 'sensual', 
            'vulgar', 'orgasm', 'xxx', 'hentai', 'playboy', 'slut', 'whore', 'hookup', 
            'condom', 'vagina', 'penis', 'breasts', 'boobs', 'strip club', 'call girl',
            'kambi', 'vedi', 'chundu', 'mulakalo', 'sugam', 'kundila', 'mypu', 'poola', 'kunna',
            
            // Drugs & Prohibited Substances
            'drugs', 'cocaine', 'heroin', 'marijuana', 'weed', 'cannabis', 'meth', 'ecstasy',
            'lsd', 'ganja', 'kannabis', 'mdma', 'hashish', 'steroids',
            
            // Illegal Weapons & Violence
            'weapons', 'ammunition', 'firearms', 'gun for sale', 'pistol for sale', 'explosives',
            'grenade', 'bomb', 'assault rifle', 'murder', 'suicide', 'slaughter'
        ];

        // Normalise text to lowercase for comparison
        $clean_text = strtolower($text);

        foreach ($banned_words as $word) {
            // Match exact words or word boundaries to prevent false positives
            $pattern = '/\b' . preg_quote($word, '/') . '\b/u';
            if (preg_match($pattern, $clean_text)) {
                return true;
            }
            
            // Fallback for substring matching in compound Malayalam words
            if (strlen($word) > 3 && strpos($clean_text, $word) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('isImageNSFW')) {
    /**
     * Checks if the uploaded image file contains adult/NSFW content or is a video file.
     * Uses Sightengine API if configured, otherwise falls back to local skin tone heuristics.
     * 
     * @param string $tmpFilePath Path to the temporary uploaded file
     * @return bool True if image is flagged as NSFW / inappropriate, false otherwise
     */
    function isImageNSFW($tmpFilePath)
    {
        global $pdo;
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'adult_content_check'");
                $stmt->execute();
                $check = $stmt->fetchColumn();
                if ($check === '0') {
                    return false;
                }
            } catch (Exception $e) {
                // Fail silently
            }
        }

        if (empty($tmpFilePath) || !file_exists($tmpFilePath)) {
            return false;
        }

        // 1. Check if the file is actually a video (spoofed or otherwise)
        if (isVideoFile($tmpFilePath)) {
            return true; // Reject videos as inappropriate for image inputs
        }

        $api_user = defined('SIGHTENGINE_USER') ? SIGHTENGINE_USER : '';
        $api_secret = defined('SIGHTENGINE_SECRET') ? SIGHTENGINE_SECRET : '';
        $strictness = defined('MODERATION_STRICTNESS') ? MODERATION_STRICTNESS : 0.70;

        // If credentials are configured, try the external Sightengine API
        if (!empty($api_user) && !empty($api_secret)) {
            // Sightengine native/nudity/offensive endpoint API url
            $url = 'https://api.sightengine.com/1.0/check.json';

            // Prepare raw binary upload parameters
            $post_fields = [
                'media' => new CURLFile($tmpFilePath),
                'models' => 'nudity-2.0,offensive',
                'api_user' => $api_user,
                'api_secret' => $api_secret
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $http_code === 200) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    if (isset($data['nudity'])) {
                        $n = $data['nudity'];
                        $sexual_activity = $n['sexual_activity'] ?? 0;
                        $sexual_display = $n['sexual_display'] ?? 0;
                        $erotica = $n['erotica'] ?? 0;

                        if ($sexual_activity >= $strictness || $sexual_display >= $strictness || $erotica >= $strictness) {
                            return true;
                        }
                    }

                    if (isset($data['offensive'])) {
                        $off = $data['offensive'];
                        $prob = $off['prob'] ?? 0;
                        if ($prob >= $strictness) {
                            return true;
                        }
                    }
                    
                    // Sightengine successfully processed and approved the image
                    return false;
                }
            }
            error_log('Sightengine API call failed or timed out. Falling back to local image analysis.');
        }

        // 2. Fall back to local skin tone / nudity heuristic analysis
        return isImageNSFWLocal($tmpFilePath);
    }
}

if (!function_exists('isVideoFile')) {
    /**
     * Checks if a file is a video by inspecting its MIME type and magic numbers.
     * 
     * @param string $tmpFilePath Path to the temporary file
     * @return bool True if file is identified as a video, false otherwise
     */
    function isVideoFile($tmpFilePath)
    {
        if (empty($tmpFilePath) || !file_exists($tmpFilePath)) {
            return false;
        }

        // 1. Try fileinfo extension MIME checking
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpFilePath);
            if ($mime && strpos($mime, 'video/') === 0) {
                return true;
            }
        }

        // 2. Fallback to reading first 16 bytes for common video format magic numbers
        $fh = @fopen($tmpFilePath, 'rb');
        if ($fh) {
            $bytes = @fread($fh, 16);
            @fclose($fh);
            if (strlen($bytes) >= 4) {
                // WebM / MKV
                if (substr($bytes, 0, 4) === "\x1A\x45\xDF\xA3") {
                    return true;
                }
                // Ogg Video
                if (substr($bytes, 0, 4) === "OggS") {
                    return true;
                }
                // FLV
                if (substr($bytes, 0, 3) === "FLV") {
                    return true;
                }
                // MP4 / QuickTime / 3GP
                if (strlen($bytes) >= 8 && substr($bytes, 4, 4) === "ftyp") {
                    return true;
                }
                // AVI
                if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === "RIFF" && substr($bytes, 8, 4) === "AVI ") {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('isImageNSFWLocal')) {
    /**
     * Local fallback to detect adult/NSFW content using skin tone heuristics.
     * NOTE: This local heuristic is disabled (returns false) by default because color-based skin-tone
     * detection generates high false positives on benign images containing brown, beige, or gold tones
     * (e.g., cakes, wooden furniture, brown boxes, eggs).
     * 
     * @param string $tmpFilePath Path to the temporary file
     * @return bool True if flagged as NSFW, false otherwise
     */
    function isImageNSFWLocal($tmpFilePath)
    {
        // Disabled to prevent blocking benign images.
        return false;
    }
}

if (!function_exists('getUserTrustMetrics')) {
    /**
     * Calculates trust metrics for a given user (average rating, review count, response time, verified flags)
     * 
     * @param int $user_id The ID of the user
     * @return array Trust metrics metadata
     */
    function getUserTrustMetrics($user_id)
    {
        global $pdo;
        
        // 1. Fetch ratings metrics
        $stmt_r = $pdo->prepare("SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as review_count FROM user_ratings WHERE reviewee_id = ?");
        $stmt_r->execute([$user_id]);
        $rating_data = $stmt_r->fetch();
        
        $avg_rating = round(floatval($rating_data['avg_rating']), 1);
        $review_count = intval($rating_data['review_count']);
        
        // 2. Fetch joined/member since date and verification flag
        $stmt_u = $pdo->prepare("SELECT created_at, email, phone_number, is_verified FROM users WHERE id = ?");
        $stmt_u->execute([$user_id]);
        $user_data = $stmt_u->fetch();
        
        $member_since = $user_data ? date('F Y', strtotime($user_data['created_at'])) : 'Unknown';
        
        // 3. Verification flag from admin approval
        $is_verified = $user_data ? (bool)$user_data['is_verified'] : false;
        
        // Map both phone and email verified status to is_verified column for compatibility
        $email_verified = $is_verified;
        $phone_verified = $is_verified;
        
        // 4. Calculate response time:
        // Query chat messages where receiver_id is the user and find the subsequent reply by sender_id (which is this user)
        $stmt_msgs = $pdo->prepare("
            SELECT m1.created_at as received_at, MIN(m2.created_at) as replied_at
            FROM messages m1
            JOIN messages m2 ON m1.product_id = m2.product_id 
                            AND m1.sender_id = m2.receiver_id 
                            AND m1.receiver_id = m2.sender_id 
                            AND m2.created_at > m1.created_at
            WHERE m1.receiver_id = ?
            GROUP BY m1.id
        ");
        $stmt_msgs->execute([$user_id]);
        $pairs = $stmt_msgs->fetchAll();
        
        $total_seconds = 0;
        $reply_count = 0;
        foreach ($pairs as $p) {
            $diff = strtotime($p['replied_at']) - strtotime($p['received_at']);
            // Only count replies within 24 hours to prevent out-of-context delays
            if ($diff > 0 && $diff < 86400) {
                $total_seconds += $diff;
                $reply_count++;
            }
        }
        
        $response_time = "Replies within a few hours";
        if ($reply_count > 0) {
            $avg_seconds = $total_seconds / $reply_count;
            if ($avg_seconds < 1800) {
                $response_time = "Replies in minutes";
            } elseif ($avg_seconds < 3600) {
                $response_time = "Replies in 1 hour";
            } elseif ($avg_seconds < 7200) {
                $response_time = "Replies in 2 hours";
            } elseif ($avg_seconds < 86400) {
                $hours = round($avg_seconds / 3600);
                $response_time = "Replies in $hours hours";
            }
        }
        
        return [
            'avg_rating' => $avg_rating,
            'review_count' => $review_count,
            'member_since' => $member_since,
            'email_verified' => $email_verified,
            'phone_verified' => $phone_verified,
            'response_time' => $response_time
        ];
    }
}

/**
 * CSRF Protection Helpers
 */
if (!function_exists('getCsrfToken')) {
    function getCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $submittedToken = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (empty($_SESSION['csrf_token']) || empty($submittedToken)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $submittedToken);
    }
}

if (!function_exists('csrfField')) {
    function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
    }
}

/**
 * Rate Limiting Helper (Brute-force protection)
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit($actionKey, $maxAttempts = 5, $decaySeconds = 600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $key = 'rate_' . md5($actionKey . '_' . $ip);
        
        $now = time();
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => $now];
        }
        
        $data = $_SESSION[$key];
        if ($now - $data['first_attempt'] > $decaySeconds) {
            // Window expired, reset
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => $now];
            return ['allowed' => true, 'remaining' => $maxAttempts];
        }
        
        if ($data['attempts'] >= $maxAttempts) {
            $retryAfter = $decaySeconds - ($now - $data['first_attempt']);
            return ['allowed' => false, 'retry_after' => $retryAfter];
        }
        
        return ['allowed' => true, 'remaining' => $maxAttempts - $data['attempts']];
    }
}

if (!function_exists('recordRateLimitHit')) {
    function recordRateLimitHit($actionKey) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $key = 'rate_' . md5($actionKey . '_' . $ip);
        $now = time();
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
        } else {
            $_SESSION[$key]['attempts']++;
        }
    }
}

if (!function_exists('resetRateLimit')) {
    function resetRateLimit($actionKey) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $key = 'rate_' . md5($actionKey . '_' . $ip);
        unset($_SESSION[$key]);
    }
}

/**
 * Auto-expire ads whose validity or perishable expiry date has passed
 */
if (!function_exists('checkAndExpireOldAds')) {
    function checkAndExpireOldAds($pdo) {
        if (!$pdo) return;
        $lockFile = sys_get_temp_dir() . '/enteangadi_ad_expiry.lock';
        $now = time();
        if (file_exists($lockFile) && ($now - filemtime($lockFile) < 3600)) {
            // Checked within the last 1 hour
            return;
        }
        @touch($lockFile);
        
        try {
            // 1. Expire perishable products past their expiry date
            $stmt1 = $pdo->prepare("UPDATE products SET status = 'expired', status_reason = 'Perishable validity expired' 
                                   WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");
            $stmt1->execute();
            
            // 2. Expire non-perishable ads older than 90 days
            $stmt2 = $pdo->prepare("UPDATE products SET status = 'expired', status_reason = 'Listing reached 90-day validity limit' 
                                   WHERE status = 'active' AND (expiry_date IS NULL OR expiry_date = '') AND created_at < (NOW() - INTERVAL 90 DAY)");
            $stmt2->execute();
        } catch (Exception $e) {
            // Silently log/ignore on fail
        }
    }
}

/**
 * Full Database Backup Exporter (phpMyAdmin format)
 * Overwrites the destination file in place with complete table structures and data.
 */
if (!function_exists('exportFullDatabaseToSql')) {
    function exportFullDatabaseToSql($pdo, $destinationFile) {
        if (!$pdo) {
            return ['success' => false, 'error' => 'Database connection not available.'];
        }

        $dir = dirname($destinationFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $handle = @fopen($destinationFile, 'w');
        if (!$handle) {
            return ['success' => false, 'error' => "Unable to open target file for writing: $destinationFile"];
        }

        try {
            // Get Server & DB metadata
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn() ?: 'enteangadi';
            $genTime = date('Y-m-d H:i:s');
            $phpVersion = phpversion();

            // Header comments (phpMyAdmin standard format)
            fwrite($handle, "-- phpMyAdmin SQL Dump\n");
            fwrite($handle, "-- version 5.2.1 / Enteangadi Database Exporter\n");
            fwrite($handle, "-- https://www.phpmyadmin.net/\n");
            fwrite($handle, "--\n");
            fwrite($handle, "-- Host: localhost\n");
            fwrite($handle, "-- Generation Time: " . $genTime . "\n");
            fwrite($handle, "-- Server version: " . $serverVersion . "\n");
            fwrite($handle, "-- PHP Version: " . $phpVersion . "\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "START TRANSACTION;\n");
            fwrite($handle, "SET time_zone = \"+00:00\";\n\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
            fwrite($handle, "/*!40101 SET NAMES utf8mb4 */;\n\n");
            fwrite($handle, "--\n-- Database: `" . $dbName . "`\n--\n\n");

            // Fetch all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $totalTables = count($tables);
            $totalRows = 0;

            foreach ($tables as $table) {
                // Table Structure
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $createSql = $createStmt['Create Table'] ?? '';

                fwrite($handle, "-- --------------------------------------------------------\n\n");
                fwrite($handle, "--\n-- Table structure for table `{$table}`\n--\n\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($handle, $createSql . ";\n\n");

                // Table Data
                $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
                $rowCountInTable = 0;
                $batchRows = [];
                $columns = null;

                while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($columns === null) {
                        $columns = array_keys($row);
                    }
                    $rowCountInTable++;
                    $totalRows++;

                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote($val);
                        }
                    }
                    $batchRows[] = '(' . implode(', ', $vals) . ')';

                    // Write in batches of 100 for optimal memory and SQL dump performance
                    if (count($batchRows) >= 100) {
                        if ($rowCountInTable === count($batchRows)) {
                            fwrite($handle, "--\n-- Dumping data for table `{$table}`\n--\n\n");
                        }
                        $colList = '`' . implode('`, `', $columns) . '`';
                        fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batchRows) . ";\n\n");
                        $batchRows = [];
                    }
                }

                if (!empty($batchRows)) {
                    if ($rowCountInTable === count($batchRows)) {
                        fwrite($handle, "--\n-- Dumping data for table `{$table}`\n--\n\n");
                    }
                    $colList = '`' . implode('`, `', $columns) . '`';
                    fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batchRows) . ";\n\n");
                }
            }

            fwrite($handle, "COMMIT;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");

            fclose($handle);

            $fileSize = file_exists($destinationFile) ? filesize($destinationFile) : 0;
            return [
                'success' => true,
                'bytes' => $fileSize,
                'tables' => $totalTables,
                'rows' => $totalRows,
                'file' => $destinationFile
            ];
        } catch (Exception $e) {
            if ($handle) {
                fclose($handle);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('exportCleanProductionSql')) {
    function exportCleanProductionSql($pdo, $destinationFile) {
        if (!$pdo) {
            return ['success' => false, 'error' => 'Database connection not available.'];
        }

        $dir = dirname($destinationFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $handle = @fopen($destinationFile, 'w');
        if (!$handle) {
            return ['success' => false, 'error' => "Unable to open target file for writing: $destinationFile"];
        }

        try {
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn() ?: 'enteangadi';
            $genTime = date('Y-m-d H:i:s');
            $phpVersion = phpversion();

            fwrite($handle, "-- phpMyAdmin SQL Dump\n");
            fwrite($handle, "-- version 5.2.1 / Enteangadi Clean Production Exporter\n");
            fwrite($handle, "-- Clean Production Database (Zero Dummy Data)\n");
            fwrite($handle, "-- Generated: " . $genTime . "\n");
            fwrite($handle, "-- Server version: " . $serverVersion . "\n");
            fwrite($handle, "-- PHP Version: " . $phpVersion . "\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "START TRANSACTION;\n");
            fwrite($handle, "SET time_zone = \"+00:00\";\n\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
            fwrite($handle, "/*!40101 SET NAMES utf8mb4 */;\n\n");
            fwrite($handle, "-- Database: `" . $dbName . "`\n\n");

            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $totalTables = count($tables);
            $totalRows = 0;

            // Tables where structural schema is preserved, but data is completely wiped
            $wipedDataTables = [
                'site_visits', 'announcement_views', 'analytics_clicks', 'reports', 'messages', 
                'wishlist', 'user_ratings', 'verification_requests', 
                'account_closures', 'product_images', 'products', 'blocked_users'
            ];

            foreach ($tables as $table) {
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $createSql = $createStmt['Create Table'] ?? '';

                if (in_array($table, $wipedDataTables)) {
                    $createSql = preg_replace('/AUTO_INCREMENT=\d+/i', 'AUTO_INCREMENT=1', $createSql);
                }

                fwrite($handle, "-- --------------------------------------------------------\n\n");
                fwrite($handle, "-- Table structure for table `{$table}`\n\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($handle, $createSql . ";\n\n");

                // Determine rows to export
                if ($table === 'users') {
                    // Export ONLY admin / staff accounts (exclude dummy users)
                    $rowsStmt = $pdo->query("SELECT * FROM `users` WHERE `role` = 'admin' OR `is_admin` = 1");
                } elseif (in_array($table, $wipedDataTables)) {
                    // Wiped table: 0 rows exported!
                    continue;
                } else {
                    $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
                }

                $rowCountInTable = 0;
                $batchRows = [];
                $columns = null;

                while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($columns === null) {
                        $columns = array_keys($row);
                    }
                    $rowCountInTable++;
                    $totalRows++;

                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote($val);
                        }
                    }
                    $batchRows[] = '(' . implode(', ', $vals) . ')';

                    if (count($batchRows) >= 100) {
                        if ($rowCountInTable === count($batchRows)) {
                            fwrite($handle, "-- Dumping clean data for table `{$table}`\n\n");
                        }
                        $colList = '`' . implode('`, `', $columns) . '`';
                        fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batchRows) . ";\n\n");
                        $batchRows = [];
                    }
                }

                if (!empty($batchRows)) {
                    if ($rowCountInTable === count($batchRows)) {
                        fwrite($handle, "-- Dumping clean data for table `{$table}`\n\n");
                    }
                    $colList = '`' . implode('`, `', $columns) . '`';
                    fwrite($handle, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $batchRows) . ";\n\n");
                }
            }

            fwrite($handle, "COMMIT;\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");

            fclose($handle);

            $fileSize = file_exists($destinationFile) ? filesize($destinationFile) : 0;
            return [
                'success' => true,
                'bytes' => $fileSize,
                'tables' => $totalTables,
                'rows' => $totalRows,
                'file' => $destinationFile
            ];
        } catch (Exception $e) {
            if ($handle) fclose($handle);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('purgeDummyTestData')) {
    function purgeDummyTestData($pdo) {
        if (!$pdo) {
            return ['success' => false, 'error' => 'Database connection not available.'];
        }

        try {
            $root = dirname(__DIR__);

            // 1. Delete physical product images
            $imgStmt = $pdo->query("SELECT image_path FROM product_images");
            $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($images as $img) {
                if (!empty($img)) {
                    $fullPath = $root . '/' . ltrim($img, '/');
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }
            }

            // 2. Delete non-admin user profile pictures
            $userImgStmt = $pdo->query("SELECT profile_picture FROM users WHERE role != 'admin' AND is_admin = 0");
            $userImages = $userImgStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($userImages as $uimg) {
                if (!empty($uimg)) {
                    $fullPath = $root . '/' . ltrim($uimg, '/');
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }
            }

            // 3. Clean up verification request photos if any
            try {
                $verImgStmt = $pdo->query("SELECT id_photo FROM verification_requests");
                if ($verImgStmt) {
                    $verImages = $verImgStmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($verImages as $vimg) {
                        if (!empty($vimg)) {
                            $fullPath = $root . '/' . ltrim($vimg, '/');
                            if (is_file($fullPath)) {
                                @unlink($fullPath);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Fail-safe
            }

            // 4. Truncate dummy data tables
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tablesToTruncate = [
                'site_visits',
                'announcement_views',
                'analytics_clicks',
                'reports',
                'messages',
                'wishlist',
                'user_ratings',
                'verification_requests',
                'account_closures',
                'product_images',
                'products',
                'blocked_users'
            ];

            foreach ($tablesToTruncate as $tbl) {
                $check = $pdo->query("SHOW TABLES LIKE '{$tbl}'")->fetch();
                if ($check) {
                    $pdo->exec("TRUNCATE TABLE `{$tbl}`");
                }
            }

            // 5. Delete non-admin users
            $pdo->exec("DELETE FROM users WHERE role != 'admin' AND is_admin = 0");

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            return ['success' => true];
        } catch (Exception $e) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('calc_growth')) {
    function calc_growth($current, $previous) {
        if ($previous <= 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}

if (!function_exists('render_xls_bar')) {
    function render_xls_bar($val, $max, $color = '#10b981') {
        if ($max <= 0) return '<span style="color:#94a3b8; font-size:11px;">0.0%</span>';
        $pct = round(max(0, min(100, ($val / $max) * 100)), 1);
        $remainder = 100 - $pct;
        return '<table cellpadding="0" cellspacing="0" style="width: 140px; height: 14px; border: 1px solid #cbd5e1; background: #f1f5f9; display: inline-table; vertical-align: middle;">'
             . '<tr><td style="width:' . $pct . '%; background:' . $color . '; font-size:1px;">&nbsp;</td>'
             . '<td style="width:' . $remainder . '%; font-size:1px;">&nbsp;</td></tr></table>'
             . ' <b style="font-size:11px; color:#0f172a; margin-left:6px;">' . $pct . '%</b>';
    }
}




