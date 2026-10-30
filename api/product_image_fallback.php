<?php
/**
 * Smart Fallback Resolver for Replaced/Deleted Product Images
 * If a cached guest feed or external link requests an old product image filename
 * (e.g. uploads/products/1793339894_0.jpg) that was replaced during Edit Ad,
 * this script resolves the product's current active image and serves it seamlessly.
 */
require_once __DIR__ . '/../config.php';

$rawFile = basename($_GET['file'] ?? '');
$rootDir = dirname(__DIR__);

if (!headers_sent()) {
    header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("X-Accel-Expires: 0");
}

function serveResolvedImage(string $absPath): void
{
    if (!is_file($absPath) || !is_readable($absPath)) {
        return;
    }
    $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    $mime = 'image/jpeg';
    if ($ext === 'png') $mime = 'image/png';
    elseif ($ext === 'webp') $mime = 'image/webp';
    elseif ($ext === 'gif') $mime = 'image/gif';
    elseif ($ext === 'svg') $mime = 'image/svg+xml';

    if (!headers_sent()) {
        header("Content-Type: " . $mime);
        header("Content-Length: " . filesize($absPath));
    }
    readfile($absPath);
    exit;
}

if ($rawFile !== '') {
    $directPath = $rootDir . '/uploads/products/' . $rawFile;
    if (is_file($directPath)) {
        serveResolvedImage($directPath);
    }

    $relOldPath = 'uploads/products/' . $rawFile;

    // 1. Check explicit redirect map table if present
    try {
        $mapStmt = $pdo->prepare("SELECT product_id, new_image_path FROM product_image_redirects WHERE old_image_path = ? ORDER BY id DESC LIMIT 1");
        $mapStmt->execute([$relOldPath]);
        $mapped = $mapStmt->fetch();
        if ($mapped) {
            if (!empty($mapped['new_image_path']) && is_file($rootDir . '/' . $mapped['new_image_path'])) {
                serveResolvedImage($rootDir . '/' . $mapped['new_image_path']);
            }
            if (!empty($mapped['product_id'])) {
                $piStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY id ASC");
                $piStmt->execute([(int)$mapped['product_id']]);
                foreach ($piStmt->fetchAll(PDO::FETCH_COLUMN) as $candidate) {
                    if ($candidate && is_file($rootDir . '/' . $candidate)) {
                        serveResolvedImage($rootDir . '/' . $candidate);
                    }
                }
            }
        }
    } catch (Exception $e) {
    }

    // 2. Match by timestamp prefix in filename (e.g. 1793339894_0.jpg -> product created_at / updated_at)
    if (preg_match('/^(\d{9,11})_(\d+)\.[a-zA-Z0-9]+$/', $rawFile, $m)) {
        $ts = (int)$m[1];
        $idx = (int)$m[2];

        try {
            $pStmt = $pdo->prepare("
                SELECT id
                FROM products
                WHERE ABS(UNIX_TIMESTAMP(created_at) - :ts1) <= 60
                   OR ABS(UNIX_TIMESTAMP(updated_at) - :ts2) <= 60
                ORDER BY LEAST(
                    ABS(UNIX_TIMESTAMP(created_at) - :ts3),
                    ABS(UNIX_TIMESTAMP(updated_at) - :ts4)
                ) ASC
                LIMIT 1
            ");
            $pStmt->execute([
                ':ts1' => $ts,
                ':ts2' => $ts,
                ':ts3' => $ts,
                ':ts4' => $ts
            ]);
            $prodId = (int)$pStmt->fetchColumn();

            if ($prodId > 0) {
                $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY id ASC");
                $imgStmt->execute([$prodId]);
                $imgs = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($imgs)) {
                    if (isset($imgs[$idx]) && is_file($rootDir . '/' . $imgs[$idx])) {
                        serveResolvedImage($rootDir . '/' . $imgs[$idx]);
                    }
                    foreach ($imgs as $imgRel) {
                        if ($imgRel && is_file($rootDir . '/' . $imgRel)) {
                            serveResolvedImage($rootDir . '/' . $imgRel);
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
    }
}

// 3. Clean SVG placeholder fallback so a broken <img> icon never renders
header("Content-Type: image/svg+xml");
echo '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400" viewBox="0 0 400 400">
  <rect width="400" height="400" fill="#f1f5f9"/>
  <g transform="translate(160, 150)" fill="#94a3b8">
    <path d="M72 8H8C3.6 8 0 11.6 0 16v48c0 4.4 3.6 8 8 8h64c4.4 0 8-3.6 8-8V16c0-4.4-3.6-8-8-8zm0 56H8V16h64v48zM22 36c4.4 0 8-3.6 8-8s-3.6-8-8-8-8 3.6-8 8 3.6 8 8 8zm42 20H16l12-16 9 11 13-17 14 22z"/>
  </g>
</svg>';
exit;

