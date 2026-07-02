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

