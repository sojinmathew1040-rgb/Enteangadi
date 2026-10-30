<?php
/**
 * One-time schema setup, index optimization, and hourly background maintenance.
 * Loaded lazily by config.php only when lock files expire or do not exist.
 */

if (!function_exists('runEnteangadiSchemaSetup')) {
    function runEnteangadiSchemaSetup(PDO $pdo, string $rootDir): void
    {
        $lockFile = sys_get_temp_dir() . '/enteangadi_schema_v4.lock';

        try {
            // Core schema import if products table is completely missing
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'products'");
            if (!$tableCheck->fetch()) {
                $schemaFile = $rootDir . '/db/enteangadi.sql';
                if (!file_exists($schemaFile)) {
                    $schemaFile = $rootDir . '/db_schema.sql';
                }
                if (file_exists($schemaFile)) {
                    $sql = file_get_contents($schemaFile);
                    $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `?[a-zA-Z0-9_]+`?;/i', '', $sql);
                    $sql = preg_replace('/USE `?[a-zA-Z0-9_]+`?;/i', '', $sql);
                    $pdo->exec($sql);
                }
            }

            // Ensure auxiliary tables exist
            $tablesSql = [
                "CREATE TABLE IF NOT EXISTS `site_visits` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `visit_date` DATE NOT NULL,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `user_id` INT DEFAULT NULL,
                    `page_url` VARCHAR(255) DEFAULT NULL,
                    `user_agent` VARCHAR(255) DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_visit_date` (`visit_date`),
                    KEY `idx_user_id` (`user_id`),
                    KEY `idx_ip_date` (`ip_address`, `visit_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `announcement_views` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `poster_image` VARCHAR(255) DEFAULT NULL,
                    `view_date` DATE NOT NULL,
                    `user_id` INT DEFAULT NULL,
                    `ip_address` VARCHAR(45) NOT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_view_date` (`view_date`),
                    KEY `idx_poster` (`poster_image`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `messages` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `sender_id` INT NOT NULL,
                    `receiver_id` INT NOT NULL,
                    `product_id` INT NOT NULL,
                    `message_text` TEXT NOT NULL,
                    `is_read` TINYINT(1) DEFAULT 0,
                    `deleted_by_sender` TINYINT(1) DEFAULT 0,
                    `deleted_by_receiver` TINYINT(1) DEFAULT 0,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `wishlist` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `product_id` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_wishlist` (`user_id`, `product_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `blocked_users` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `blocker_id` INT NOT NULL,
                    `blocked_id` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_block` (`blocker_id`, `blocked_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `account_closures` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(255),
                    `email` VARCHAR(255),
                    `reason` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `app_settings` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `setting_key` VARCHAR(50) UNIQUE NOT NULL,
                    `setting_value` TEXT,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `user_ratings` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `reviewer_id` INT NOT NULL,
                    `reviewee_id` INT NOT NULL,
                    `rating` TINYINT NOT NULL,
                    `comment` TEXT,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `unique_reviewer_reviewee` (`reviewer_id`, `reviewee_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `analytics_clicks` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `product_id` INT NOT NULL,
                    `click_type` ENUM('view', 'favorite', 'chat') NOT NULL,
                    `click_date` DATE NOT NULL,
                    `click_count` INT DEFAULT 1,
                    UNIQUE KEY `unique_product_type_date` (`product_id`, `click_type`, `click_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                "CREATE TABLE IF NOT EXISTS `verification_requests` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `id_type` VARCHAR(100) NOT NULL,
                    `id_photo` VARCHAR(255) NOT NULL,
                    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    `rejection_reason` TEXT DEFAULT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            ];
            foreach ($tablesSql as $sql) {
                $pdo->exec($sql);
            }

            // Ensure columns exist via single SHOW COLUMNS inspection per table
            $getColumns = function (string $table) use ($pdo): array {
                $cols = [];
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        $cols[$r['Field']] = $r;
                    }
                } catch (Exception $e) {
                }
                return $cols;
            };

            $msgCols = $getColumns('messages');
            if (!isset($msgCols['deleted_by_sender'])) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_by_sender TINYINT(1) DEFAULT 0");
            }
            if (!isset($msgCols['deleted_by_receiver'])) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN deleted_by_receiver TINYINT(1) DEFAULT 0");
            }

            $userCols = $getColumns('users');
            $userAdditions = [
                'email' => "VARCHAR(155) UNIQUE DEFAULT NULL",
                'is_admin' => "TINYINT(1) DEFAULT 0",
                'phone' => "VARCHAR(20) DEFAULT NULL",
                'permissions' => "TEXT DEFAULT NULL",
                'session_token' => "VARCHAR(255) DEFAULT NULL",
                'last_activity' => "TIMESTAMP NULL DEFAULT NULL",
                'is_verified' => "TINYINT(1) DEFAULT 0",
                'tut_home' => "TINYINT(1) DEFAULT 0",
                'tut_post' => "TINYINT(1) DEFAULT 0",
                'tut_profile' => "TINYINT(1) DEFAULT 0",
                'tut_inbox' => "TINYINT(1) DEFAULT 0"
            ];
            foreach ($userAdditions as $col => $def) {
                if (!isset($userCols[$col])) {
                    try {
                        $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $def");
                    } catch (Exception $e) {
                    }
                }
            }

            $catCols = $getColumns('categories');
            if (!isset($catCols['is_perishable'])) {
                try {
                    $pdo->exec("ALTER TABLE categories ADD COLUMN is_perishable TINYINT(1) DEFAULT 0");
                } catch (Exception $e) {
                }
            }

            $repCols = $getColumns('reports');
            if (!empty($repCols) && !isset($repCols['reported_user_id'])) {
                try {
                    $pdo->exec("ALTER TABLE reports ADD COLUMN reported_user_id INT DEFAULT NULL AFTER product_id");
                } catch (Exception $e) {
                }
            }

            $prodCols = $getColumns('products');
            $prodAdditions = [
                'unique_id' => "VARCHAR(20) UNIQUE",
                'type' => "ENUM('sell', 'buy', 'rent') DEFAULT 'sell'",
                'expiry_date' => "DATE DEFAULT NULL",
                'whatsapp_number' => "VARCHAR(20) DEFAULT NULL",
                'phone_number' => "VARCHAR(20) DEFAULT NULL",
                'location_name' => "VARCHAR(255) DEFAULT NULL",
                'latitude' => "DECIMAL(10, 8) DEFAULT NULL",
                'longitude' => "DECIMAL(11, 8) DEFAULT NULL",
                'status_reason' => "TEXT DEFAULT NULL",
                'views' => "INT DEFAULT 0",
                'is_verified' => "TINYINT(1) DEFAULT 0",
                'is_admin_posted' => "TINYINT(1) DEFAULT 0",
                'is_notified' => "TINYINT(1) DEFAULT 0",
                'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
            ];
            foreach ($prodAdditions as $col => $def) {
                if (!isset($prodCols[$col])) {
                    try {
                        $pdo->exec("ALTER TABLE products ADD COLUMN `$col` $def");
                    } catch (Exception $e) {
                    }
                }
            }

            // Ensure ENUM definitions include all supported values (only if not already matching)
            if (isset($prodCols['type']) && strpos($prodCols['type']['Type'] ?? '', 'rent') === false) {
                try {
                    $pdo->exec("ALTER TABLE products MODIFY COLUMN type ENUM('sell', 'buy', 'rent') DEFAULT 'sell'");
                } catch (Exception $e) {
                }
            }
            if (isset($prodCols['status']) && strpos($prodCols['status']['Type'] ?? '', 'inactive') === false) {
                try {
                    $pdo->exec("ALTER TABLE products MODIFY COLUMN status ENUM('active', 'deleted', 'sold', 'expired', 'pending', 'inactive') DEFAULT 'active'");
                } catch (Exception $e) {
                }
            }

            // Ensure high-speed composite indexes exist
            $ensureIndex = function (string $table, string $idxName, string $colsSql) use ($pdo): void {
                try {
                    $chk = $pdo->prepare("SHOW INDEX FROM `$table` WHERE Key_name = ?");
                    $chk->execute([$idxName]);
                    if (!$chk->fetch()) {
                        $pdo->exec("ALTER TABLE `$table` ADD INDEX `$idxName` ($colsSql)");
                    }
                } catch (Exception $e) {
                }
            };

            $ensureIndex('products', 'idx_prod_status_created', '`status`, `created_at`');
            $ensureIndex('products', 'idx_prod_cat_status', '`category_id`, `status`');
            $ensureIndex('products', 'idx_prod_type_status', '`type`, `status`');
            $ensureIndex('products', 'idx_prod_status_updated', '`status`, `updated_at`');
            $ensureIndex('products', 'idx_prod_geo', '`status`, `latitude`, `longitude`');
            $ensureIndex('product_images', 'idx_pimg_prod_id', '`product_id`, `id`');
            $ensureIndex('messages', 'idx_msg_recv_unread', '`receiver_id`, `is_read`, `deleted_by_receiver`');
            $ensureIndex('messages', 'idx_msg_prod_users', '`product_id`, `sender_id`, `receiver_id`');
            $ensureIndex('notifications', 'idx_notif_user_read', '`user_id`, `is_read`, `created_at`');
            $ensureIndex('users', 'idx_users_phone', '`phone_number`');
            $ensureIndex('users', 'idx_users_token', '`session_token`');

            // Ensure default admin exists
            $stmtAdmin = $pdo->prepare("SELECT id, password FROM users WHERE username = 'admin' LIMIT 1");
            $stmtAdmin->execute();
            $adminUser = $stmtAdmin->fetch();
            $defaultHash = '$2y$10$OWyw9HIV10p07vkWgiRrh.aWK83dyOhUPH8cxvlxOTR6PeJjkn2QG';
            $oldBrokenHash = '$2y$10$K8pe9htFbLrJD/EjOE3In.RPOFpPz2WZ44lwQVt8RJRmUgXNnfnSC';
            if (!$adminUser) {
                $pdo->prepare("INSERT IGNORE INTO users (username, phone_number, email, password, role, is_admin, permissions) VALUES ('admin', '1234567890', 'admin@enteangadi.com', ?, 'admin', 1, '*')")
                    ->execute([$defaultHash]);
            } elseif ($adminUser['password'] === $oldBrokenHash) {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$defaultHash, $adminUser['id']]);
            }

            // Seed default app settings once without bumping AUTO_INCREMENT on every page load
            $existingKeys = $pdo->query("SELECT setting_key FROM app_settings")->fetchAll(PDO::FETCH_COLUMN);
            $existingMap = array_flip($existingKeys);
            $defaults = [
                'ad_approval_mode' => 'auto',
                'play_store_url' => 'https://play.google.com/store/apps/details?id=com.perumalilcreative.enteangadi',
                'app_store_url' => 'https://apps.apple.com/app/enteangadi',
                'adult_content_check' => '1'
            ];
            $insSetting = $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($defaults as $k => $v) {
                if (!isset($existingMap[$k])) {
                    $insSetting->execute([$k, $v]);
                }
            }
            $pdo->prepare("UPDATE app_settings SET setting_value = ? WHERE setting_key = 'play_store_url' AND setting_value = ?")
                ->execute([
                    'https://play.google.com/store/apps/details?id=com.perumalilcreative.enteangadi',
                    'https://play.google.com/store/apps/details?id=com.enteangadi.app'
                ]);

            @file_put_contents($lockFile, (string)time());
        } catch (Exception $e) {
            // Fail gracefully so live traffic is never blocked
        }
    }
}

if (!function_exists('runEnteangadiHourlyMaintenance')) {
    function runEnteangadiHourlyMaintenance(PDO $pdo, string $rootDir): void
    {
        $lockFile = sys_get_temp_dir() . '/enteangadi_maint_v3.lock';
        @file_put_contents($lockFile, (string)time());

        try {
            // 1. Cleanup expired chats for products sold/deleted > 15 days ago
            $cutoffDate = date('Y-m-d H:i:s', time() - (15 * 86400));
            $stmtExp = $pdo->prepare("SELECT id FROM products WHERE status IN ('sold', 'deleted') AND updated_at < ?");
            $stmtExp->execute([$cutoffDate]);
            $expiredProducts = $stmtExp->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($expiredProducts)) {
                $fileStmt = $pdo->prepare("SELECT message_text FROM messages WHERE product_id = ? AND (message_text LIKE '[AUDIO]:%' OR message_text LIKE '[IMAGE]:%')");
                $delMsgs = $pdo->prepare("DELETE FROM messages WHERE product_id = ?");
                foreach ($expiredProducts as $pId) {
                    $fileStmt->execute([$pId]);
                    foreach ($fileStmt->fetchAll(PDO::FETCH_COLUMN) as $fileText) {
                        $prefix = strpos($fileText, '[AUDIO]:') === 0 ? '[AUDIO]:' : (strpos($fileText, '[IMAGE]:') === 0 ? '[IMAGE]:' : '');
                        if ($prefix !== '') {
                            $absPath = $rootDir . '/' . substr($fileText, strlen($prefix));
                            if (file_exists($absPath)) {
                                @unlink($absPath);
                            }
                        }
                    }
                    $delMsgs->execute([$pId]);
                }
            }

            // 2. Auto-expire active ads older than 60 days
            $expireStmt = $pdo->query("
                SELECT p.id, pi.image_path
                FROM products p
                LEFT JOIN product_images pi ON p.id = pi.product_id
                WHERE p.status = 'active' AND p.created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
            ");
            $toExpire = $expireStmt->fetchAll();
            if (!empty($toExpire)) {
                $updStmt = $pdo->prepare("UPDATE products SET status = 'expired', status_reason = 'System auto-expiry (60 days)', updated_at = NOW() WHERE id = ?");
                foreach (array_unique(array_column($toExpire, 'id')) as $id) {
                    $updStmt->execute([$id]);
                }
                foreach ($toExpire as $item) {
                    if (!empty($item['image_path']) && function_exists('compressProductImage')) {
                        compressProductImage($item['image_path'], 20);
                    }
                }
            }

            // 3. Auto-deactivate perishable items whose expiry_date has passed
            $expPerish = $pdo->query("SELECT id FROM products WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_DATE AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($expPerish)) {
                $updPerish = $pdo->prepare("UPDATE products SET status = 'expired' WHERE id = ?");
                $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
                foreach ($expPerish as $pid) {
                    $updPerish->execute([$pid]);
                    $imgStmt->execute([$pid]);
                    foreach ($imgStmt->fetchAll(PDO::FETCH_COLUMN) as $img) {
                        if (function_exists('recompressTo50kb')) {
                            recompressTo50kb($rootDir . '/' . $img);
                        }
                    }
                }
            }

            // 4. Purge archived ads inactive > 60 days
            $purgeStmt = $pdo->query("
                SELECT p.id, pi.image_path
                FROM products p
                LEFT JOIN product_images pi ON p.id = pi.product_id
                WHERE p.status IN ('deleted', 'sold', 'expired') AND p.updated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
            ");
            $toPurge = $purgeStmt->fetchAll();
            if (!empty($toPurge)) {
                foreach ($toPurge as $item) {
                    if (!empty($item['image_path'])) {
                        $fullPath = $rootDir . '/' . $item['image_path'];
                        if (file_exists($fullPath)) {
                            @unlink($fullPath);
                        }
                    }
                }
                $delProd = $pdo->prepare("DELETE FROM products WHERE id = ?");
                foreach (array_unique(array_column($toPurge, 'id')) as $pid) {
                    $delProd->execute([$pid]);
                }
            }
        } catch (Exception $e) {
            // Fail silently
        }
    }
}

