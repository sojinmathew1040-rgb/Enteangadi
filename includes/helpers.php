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
     * Checks if the uploaded image file contains adult/NSFW content using Sightengine API.
     * Gracefully falls back to false if API is not configured.
     * 
     * @param string $tmpFilePath Path to the temporary uploaded file
     * @return bool True if image is flagged as NSFW, false otherwise
     */
    function isImageNSFW($tmpFilePath)
    {
        if (empty($tmpFilePath) || !file_exists($tmpFilePath)) {
            return false;
        }

        $api_user = defined('SIGHTENGINE_USER') ? SIGHTENGINE_USER : '';
        $api_secret = defined('SIGHTENGINE_SECRET') ? SIGHTENGINE_SECRET : '';
        $strictness = defined('MODERATION_STRICTNESS') ? MODERATION_STRICTNESS : 0.70;

        // If credentials are not configured, skip external API check
        if (empty($api_user) || empty($api_secret)) {
            return false;
        }

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

        if ($response === false || $http_code !== 200) {
            error_log('Sightengine API call failed or timed out.');
            return false;
        }

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
        }

        return false;
    }
}

