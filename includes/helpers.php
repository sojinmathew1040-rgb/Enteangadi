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

        $info = getimagesize($sourcePath);
        if (!$info)
            return false;

        if ($info['mime'] == 'image/jpeg')
            $image = imagecreatefromjpeg($sourcePath);
        elseif ($info['mime'] == 'image/png')
            $image = imagecreatefrompng($sourcePath);
        elseif ($info['mime'] == 'image/gif')
            $image = imagecreatefromgif($sourcePath);
        else
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
        $info = getimagesize($source);
        if (!$info)
            return false;

        if ($info['mime'] == 'image/jpeg')
            $image = imagecreatefromjpeg($source);
        elseif ($info['mime'] == 'image/gif')
            $image = imagecreatefromgif($source);
        elseif ($info['mime'] == 'image/png')
            $image = imagecreatefrompng($source);
        else
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

        return $result;
    }
}
