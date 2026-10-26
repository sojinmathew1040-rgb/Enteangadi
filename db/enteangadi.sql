-- phpMyAdmin SQL Dump
-- version 5.2.1 / Enteangadi Database Exporter
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: 2026-10-12 08:44:31
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `enteangadi_restored`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_closures`
--

DROP TABLE IF EXISTS `account_closures`;
CREATE TABLE `account_closures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_clicks`
--

DROP TABLE IF EXISTS `analytics_clicks`;
CREATE TABLE `analytics_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `click_type` enum('view','favorite','chat') NOT NULL,
  `click_date` date NOT NULL,
  `click_count` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_type_date` (`product_id`,`click_type`,`click_date`),
  CONSTRAINT `analytics_clicks_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=103465 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
('1', 'ad_approval_mode', 'auto', '2026-05-29 10:37:16'),
('944', 'announcement_poster', 'uploads/posters/poster_1780034884.jpg', '2026-05-29 11:38:04'),
('961', 'interstitial_ad_active', '1', '2026-05-29 11:38:47'),
('962', 'interstitial_ad_frequency', '10', '2026-05-29 11:38:47'),
('8896', 'play_store_url', 'https://play.google.com/store/apps/details?id=com.enteangadi.app', '2026-06-13 09:32:59'),
('8897', 'app_store_url', 'https://apps.apple.com/app/enteangadi', '2026-06-13 09:32:59'),
('8985', 'support_email', '', '2026-06-13 09:34:54'),
('8986', 'support_phone', '', '2026-06-13 09:34:54'),
('8987', 'whatsapp_number', '', '2026-06-13 09:34:54'),
('8988', 'facebook_url', '', '2026-06-13 09:34:54'),
('8989', 'instagram_url', '', '2026-06-13 09:34:54'),
('8990', 'twitter_url', '', '2026-06-13 09:34:54'),
('30755', 'adult_content_check', '0', '2026-07-02 12:03:44'),
('30808', 'app_logo', '', '2026-07-02 11:48:51'),
('30809', 'app_name', 'Enteangadi', '2026-07-02 11:48:51'),
('30810', 'app_tagline', 'Your Local Marketplace', '2026-07-02 11:48:51'),
('79550', 'call_feature_active', '1', '2026-07-03 18:39:26'),
('79551', 'bg_notification_active', '1', '2026-07-03 18:39:26'),
('79552', 'bg_notification_interval', '30', '2026-07-03 18:39:26');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_users`
--

DROP TABLE IF EXISTS `blocked_users`;
CREATE TABLE `blocked_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_block` (`blocker_id`,`blocked_id`),
  KEY `blocked_id` (`blocked_id`),
  CONSTRAINT `blocked_users_ibfk_1` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blocked_users_ibfk_2` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_perishable` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=92 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `photo_path`, `is_perishable`, `created_at`) VALUES
('9', NULL, 'Farm products', 'uploads/categories/farm_products.png', '0', '2026-05-29 10:40:19'),
('10', '9', 'Feed & Nutrition', 'uploads/categories/subcat_feed_nutrition.svg', '1', '2026-05-29 10:40:54'),
('11', NULL, 'Mobiles', 'uploads/categories/mobiles.png', '0', '2026-05-29 10:41:19'),
('12', '11', 'Mobile Phones', 'uploads/categories/subcat_mobile_phones.svg', '0', '2026-05-29 10:41:37'),
('13', '11', 'Tablets', 'uploads/categories/subcat_tablets.svg', '0', '2026-05-29 10:42:02'),
('14', '11', 'Accessories', 'uploads/categories/subcat_mobile_accessories.svg', '0', '2026-05-29 10:47:09'),
('25', NULL, 'Cars', 'uploads/categories/cars.png', '0', '2026-07-02 11:11:55'),
('26', '25', 'Cars', 'uploads/categories/subcat_cars_l2.svg', '0', '2026-07-02 11:11:55'),
('27', NULL, 'Bikes', 'uploads/categories/bikes.png', '0', '2026-07-02 11:11:55'),
('28', '27', 'Motorcycles', 'uploads/categories/subcat_motorcycles.svg', '0', '2026-07-02 11:11:55'),
('29', '27', 'Scooters', 'uploads/categories/subcat_scooters.svg', '0', '2026-07-02 11:11:55'),
('30', '27', 'Spare Parts', 'uploads/categories/subcat_bike_spares.svg', '0', '2026-07-02 11:11:55'),
('31', '27', 'Bicycles', 'uploads/categories/subcat_bicycles.svg', '0', '2026-07-02 11:11:55'),
('32', NULL, 'Properties', 'uploads/categories/properties.png', '0', '2026-07-02 11:11:55'),
('33', '32', 'For Sale: Houses & Apartments', 'uploads/categories/subcat_houses_sale.svg', '0', '2026-07-02 11:11:55'),
('34', '32', 'For Rent: Houses & Apartments', 'uploads/categories/subcat_houses_rent.svg', '0', '2026-07-02 11:11:55'),
('35', '32', 'Lands & Plots', 'uploads/categories/subcat_lands_plots.svg', '0', '2026-07-02 11:11:55'),
('36', '32', 'For Rent: Shops & Offices', 'uploads/categories/subcat_offices_rent.svg', '0', '2026-07-02 11:11:55'),
('37', '32', 'For Sale: Shops & Offices', 'uploads/categories/subcat_offices_sale.svg', '0', '2026-07-02 11:11:55'),
('38', '32', 'PG & Guest Houses', 'uploads/categories/subcat_pg_hostels.svg', '0', '2026-07-02 11:11:55'),
('39', NULL, 'Jobs', 'uploads/categories/jobs.png', '0', '2026-07-02 11:11:55'),
('40', '39', 'Data Entry & Back Office', 'uploads/categories/subcat_job_data_entry.svg', '0', '2026-07-02 11:11:55'),
('41', '39', 'Sales & Marketing', 'uploads/categories/subcat_job_sales.svg', '0', '2026-07-02 11:11:55'),
('42', '39', 'BPO & Telecaller', 'uploads/categories/subcat_job_bpo.svg', '0', '2026-07-02 11:11:55'),
('43', '39', 'Driver', 'uploads/categories/subcat_job_driver.svg', '0', '2026-07-02 11:11:55'),
('44', '39', 'Office Assistant', 'uploads/categories/subcat_job_assistant.svg', '0', '2026-07-02 11:11:55'),
('45', '39', 'Delivery & Collection', 'uploads/categories/subcat_job_delivery.svg', '0', '2026-07-02 11:11:55'),
('46', '39', 'Teacher', 'uploads/categories/subcat_job_teacher.svg', '0', '2026-07-02 11:11:55'),
('47', '39', 'Cook', 'uploads/categories/subcat_job_cook.svg', '0', '2026-07-02 11:11:55'),
('48', '39', 'Receptionist & Front Office', 'uploads/categories/subcat_job_reception.svg', '0', '2026-07-02 11:11:55'),
('49', '39', 'Operator & Technician', 'uploads/categories/subcat_job_technician.svg', '0', '2026-07-02 11:11:55'),
('50', '39', 'IT Design & Developer', 'uploads/categories/subcat_job_it_dev.svg', '0', '2026-07-02 11:11:55'),
('51', '39', 'Other Jobs', 'uploads/categories/subcat_job_other.svg', '0', '2026-07-02 11:11:55'),
('52', NULL, 'Electronics & Appliances', 'uploads/categories/electronics__appliances.png', '0', '2026-07-02 11:11:55'),
('53', '52', 'TVs, Video - Audio', 'uploads/categories/subcat_tv_audio.svg', '0', '2026-07-02 11:11:55'),
('54', '52', 'Kitchen & Other Appliances', 'uploads/categories/subcat_kitchen_appliances.svg', '0', '2026-07-02 11:11:55'),
('55', '52', 'Computers & Laptops', 'uploads/categories/subcat_computers_laptops.svg', '0', '2026-07-02 11:11:55'),
('56', '52', 'Cameras & Lenses', 'uploads/categories/subcat_cameras_lenses.svg', '0', '2026-07-02 11:11:55'),
('57', '52', 'Games & Entertainment', 'uploads/categories/subcat_gaming.svg', '0', '2026-07-02 11:11:55'),
('58', '52', 'Fridges', 'uploads/categories/subcat_fridges.svg', '0', '2026-07-02 11:11:55'),
('59', '52', 'Washing Machines', 'uploads/categories/subcat_washing_machines.svg', '0', '2026-07-02 11:11:55'),
('60', '52', 'ACs', 'uploads/categories/subcat_air_conditioners.svg', '0', '2026-07-02 11:11:55'),
('61', NULL, 'Commercial Vehicles & Spares', 'uploads/categories/commercial_vehicles__spares.png', '0', '2026-07-02 11:11:55'),
('62', '61', 'Commercial & Other Vehicles', 'uploads/categories/subcat_commercial_trucks.svg', '0', '2026-07-02 11:11:55'),
('63', '61', 'Spare Parts', 'uploads/categories/subcat_commercial_spares.svg', '0', '2026-07-02 11:11:55'),
('64', NULL, 'Furniture', 'uploads/categories/furniture.png', '0', '2026-07-02 11:11:55'),
('65', '64', 'Sofa & Dining', 'uploads/categories/subcat_sofa_dining.svg', '0', '2026-07-02 11:11:55'),
('66', '64', 'Beds & Wardrobes', 'uploads/categories/subcat_beds_wardrobes.svg', '0', '2026-07-02 11:11:55'),
('67', '64', 'Home Decor & Garden', 'uploads/categories/subcat_home_decor.svg', '0', '2026-07-02 11:11:55'),
('68', '64', 'Kids Furniture', 'uploads/categories/subcat_kids_furniture.svg', '0', '2026-07-02 11:11:55'),
('69', '64', 'Other Household Items', 'uploads/categories/subcat_household_other.svg', '0', '2026-07-02 11:11:55'),
('70', NULL, 'Fashion', 'uploads/categories/fashion.png', '0', '2026-07-02 11:11:55'),
('71', '70', 'Men', 'uploads/categories/subcat_fashion_men.svg', '0', '2026-07-02 11:11:55'),
('72', '70', 'Women', 'uploads/categories/subcat_fashion_women.svg', '0', '2026-07-02 11:11:55'),
('73', '70', 'Kids', 'uploads/categories/subcat_fashion_kids.svg', '0', '2026-07-02 11:11:55'),
('74', NULL, 'Books, Sports & Hobbies', 'uploads/categories/books_sports__hobbies.png', '0', '2026-07-02 11:11:55'),
('75', '74', 'Books', 'uploads/categories/subcat_books.svg', '0', '2026-07-02 11:11:55'),
('76', '74', 'Gym & Fitness', 'uploads/categories/subcat_gym_fitness.svg', '0', '2026-07-02 11:11:55'),
('77', '74', 'Musical Instruments', 'uploads/categories/subcat_music_instruments.svg', '0', '2026-07-02 11:11:55'),
('78', '74', 'Sports Equipment', 'uploads/categories/subcat_sports_equipment.svg', '0', '2026-07-02 11:11:55'),
('79', '74', 'Other Hobbies', 'uploads/categories/subcat_other_hobbies.svg', '0', '2026-07-02 11:11:55'),
('80', NULL, 'Pets', 'uploads/categories/pets.png', '0', '2026-07-02 11:11:55'),
('81', '80', 'Dogs', 'uploads/categories/subcat_pets_dogs.svg', '0', '2026-07-02 11:11:55'),
('82', '80', 'Aquarium & Fish', 'uploads/categories/subcat_pets_aquarium.svg', '0', '2026-07-02 11:11:55'),
('83', '80', 'Pet Food & Accessories', 'uploads/categories/subcat_pets_food.svg', '0', '2026-07-02 11:11:55'),
('84', '80', 'Other Pets', 'uploads/categories/subcat_pets_other.svg', '0', '2026-07-02 11:11:55'),
('85', NULL, 'Services', 'uploads/categories/services.png', '0', '2026-07-02 11:11:55'),
('86', '85', 'Education & Classes', 'uploads/categories/subcat_services_education.svg', '0', '2026-07-02 11:11:55'),
('87', '85', 'Web Development', 'uploads/categories/subcat_services_webdev.svg', '0', '2026-07-02 11:11:55'),
('88', '85', 'Electronics & Computer Repair', 'uploads/categories/subcat_services_repair.svg', '0', '2026-07-02 11:11:55'),
('89', '85', 'Drivers & Taxi', 'uploads/categories/subcat_services_taxi.svg', '0', '2026-07-02 11:11:55'),
('90', '85', 'Health & Beauty', 'uploads/categories/subcat_services_beauty.svg', '0', '2026-07-02 11:11:55'),
('91', '85', 'Other Services', 'uploads/categories/subcat_services_other.svg', '0', '2026-07-02 11:11:55');

-- --------------------------------------------------------

--
-- Table structure for table `interstitial_ads`
--

DROP TABLE IF EXISTS `interstitial_ads`;
CREATE TABLE `interstitial_ads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `media_file` varchar(255) NOT NULL,
  `media_type` varchar(50) NOT NULL,
  `link_url` varchar(255) DEFAULT '',
  `duration` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by_sender` tinyint(1) DEFAULT 0,
  `deleted_by_receiver` tinyint(1) DEFAULT 0,
  `is_delivered` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_messages_receiver_read_deleted` (`receiver_id`,`is_read`,`deleted_by_receiver`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(20) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `type` enum('sell','buy','rent') DEFAULT 'sell',
  `expiry_date` date DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `location_name` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `status` enum('active','deleted','sold','expired','pending','inactive') DEFAULT 'active',
  `status_reason` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_admin_posted` tinyint(1) DEFAULT 0,
  `is_notified` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  KEY `idx_products_status_created` (`status`,`created_at`),
  KEY `idx_products_status_type_created` (`status`,`type`,`created_at`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `reported_by_user_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','resolved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `reported_by_user_id` (`reported_by_user_id`),
  KEY `reported_user_id` (`reported_user_id`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_visits`
--

DROP TABLE IF EXISTS `site_visits`;
CREATE TABLE `site_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visit_date` date NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_visit_date` (`visit_date`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_ip_date` (`ip_address`,`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_permissions_list`
--

DROP TABLE IF EXISTS `staff_permissions_list`;
CREATE TABLE `staff_permissions_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perm_key` varchar(50) NOT NULL,
  `perm_label` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perm_key` (`perm_key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_permissions_list`
--

INSERT INTO `staff_permissions_list` (`id`, `perm_key`, `perm_label`) VALUES
('1', 'manage_users', 'Users'),
('2', 'manage_categories', 'Categories'),
('3', 'manage_pending', 'Pending Ads'),
('4', 'manage_listings', 'Active Ads'),
('5', 'manage_branding', 'Branding'),
('6', 'manage_security', 'Security'),
('7', 'manage_general', 'General Config'),
('8', 'manage_contact', 'Contact/Social'),
('9', 'manage_approval', 'Approval Mode'),
('10', 'manage_announcements', 'Announcement Poster');

-- --------------------------------------------------------

--
-- Table structure for table `user_ratings`
--

DROP TABLE IF EXISTS `user_ratings`;
CREATE TABLE `user_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reviewer_id` int(11) NOT NULL,
  `reviewee_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reviewer_reviewee` (`reviewer_id`,`reviewee_id`),
  KEY `reviewee_id` (`reviewee_id`),
  CONSTRAINT `user_ratings_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_ratings_ibfk_2` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(155) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `is_admin` tinyint(1) DEFAULT 0,
  `permissions` text DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tut_home` tinyint(1) DEFAULT 0,
  `tut_post` tinyint(1) DEFAULT 0,
  `tut_profile` tinyint(1) DEFAULT 0,
  `tut_inbox` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_number` (`phone_number`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone_number`, `phone`, `profile_picture`, `password`, `role`, `is_admin`, `permissions`, `session_token`, `last_activity`, `created_at`, `tut_home`, `tut_post`, `tut_profile`, `tut_inbox`, `is_verified`) VALUES
('1', 'admin', 'admin@enteangadi.com', '1234567890', NULL, NULL, '$2y$10$okTxdPTK8WSnkiiMsZjWRu6UjrGf18yHR3zYMcZKBkGfG9MFDHyne', 'admin', '1', '*', NULL, NULL, '2026-05-29 10:37:16', '0', '0', '0', '0', '0');

-- --------------------------------------------------------

--
-- Table structure for table `verification_requests`
--

DROP TABLE IF EXISTS `verification_requests`;
CREATE TABLE `verification_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `id_type` varchar(100) NOT NULL,
  `id_photo` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `verification_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

DROP TABLE IF EXISTS `wishlist`;
CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wishlist` (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
SET FOREIGN_KEY_CHECKS=1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
