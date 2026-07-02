-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: enteangadi
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_closures`
--

DROP TABLE IF EXISTS `account_closures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_closures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_closures`
--

LOCK TABLES `account_closures` WRITE;
/*!40000 ALTER TABLE `account_closures` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_closures` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `analytics_clicks`
--

DROP TABLE IF EXISTS `analytics_clicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `analytics_clicks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `click_type` enum('view','favorite','chat') NOT NULL,
  `click_date` date NOT NULL,
  `click_count` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_type_date` (`product_id`,`click_type`,`click_date`),
  CONSTRAINT `analytics_clicks_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_clicks`
--

LOCK TABLES `analytics_clicks` WRITE;
/*!40000 ALTER TABLE `analytics_clicks` DISABLE KEYS */;
INSERT INTO `analytics_clicks` VALUES (1,1,'view','2026-07-02',2),(3,2,'view','2026-07-02',4),(6,15,'view','2026-07-02',4),(7,8,'view','2026-07-02',1),(10,14,'view','2026-07-02',1),(13,10,'view','2026-07-02',3),(15,10,'chat','2026-07-02',2);
/*!40000 ALTER TABLE `analytics_clicks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=47101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES (1,'ad_approval_mode','auto','2026-05-29 05:07:16'),(944,'announcement_poster','uploads/posters/poster_1780034884.jpg','2026-05-29 06:08:04'),(961,'interstitial_ad_active','1','2026-05-29 06:08:47'),(962,'interstitial_ad_frequency','10','2026-05-29 06:08:47'),(8896,'play_store_url','https://play.google.com/store/apps/details?id=com.enteangadi.app','2026-06-13 04:02:59'),(8897,'app_store_url','https://apps.apple.com/app/enteangadi','2026-06-13 04:02:59'),(8985,'support_email','','2026-06-13 04:04:54'),(8986,'support_phone','','2026-06-13 04:04:54'),(8987,'whatsapp_number','','2026-06-13 04:04:54'),(8988,'facebook_url','','2026-06-13 04:04:54'),(8989,'instagram_url','','2026-06-13 04:04:54'),(8990,'twitter_url','','2026-06-13 04:04:54'),(30755,'adult_content_check','0','2026-07-02 06:33:44'),(30808,'app_logo','','2026-07-02 06:18:51'),(30809,'app_name','Enteangadi','2026-07-02 06:18:51'),(30810,'app_tagline','Your Local Marketplace','2026-07-02 06:18:51');
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blocked_users`
--

DROP TABLE IF EXISTS `blocked_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blocked_users`
--

LOCK TABLES `blocked_users` WRITE;
/*!40000 ALTER TABLE `blocked_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `blocked_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (9,NULL,'Farm products','uploads/categories/farm_products.png',0,'2026-05-29 05:10:19'),(10,9,'Feed & Nutrition','uploads/categories/farm_products.png',1,'2026-05-29 05:10:54'),(11,NULL,'Mobiles','uploads/categories/mobiles.png',0,'2026-05-29 05:11:19'),(12,11,'Mobile Phones','uploads/categories/mobiles.png',0,'2026-05-29 05:11:37'),(13,11,'Tablets','uploads/categories/mobiles.png',0,'2026-05-29 05:12:02'),(14,11,'Accessories','uploads/categories/mobiles.png',0,'2026-05-29 05:17:09'),(25,NULL,'Cars','uploads/categories/cars.png',0,'2026-07-02 05:41:55'),(26,25,'Cars','uploads/categories/cars.png',0,'2026-07-02 05:41:55'),(27,NULL,'Bikes','uploads/categories/bikes.png',0,'2026-07-02 05:41:55'),(28,27,'Motorcycles','uploads/categories/bikes.png',0,'2026-07-02 05:41:55'),(29,27,'Scooters','uploads/categories/bikes.png',0,'2026-07-02 05:41:55'),(30,27,'Spare Parts','uploads/categories/bikes.png',0,'2026-07-02 05:41:55'),(31,27,'Bicycles','uploads/categories/bikes.png',0,'2026-07-02 05:41:55'),(32,NULL,'Properties','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(33,32,'For Sale: Houses & Apartments','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(34,32,'For Rent: Houses & Apartments','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(35,32,'Lands & Plots','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(36,32,'For Rent: Shops & Offices','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(37,32,'For Sale: Shops & Offices','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(38,32,'PG & Guest Houses','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(39,NULL,'Jobs','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(40,39,'Data Entry & Back Office','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(41,39,'Sales & Marketing','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(42,39,'BPO & Telecaller','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(43,39,'Driver','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(44,39,'Office Assistant','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(45,39,'Delivery & Collection','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(46,39,'Teacher','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(47,39,'Cook','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(48,39,'Receptionist & Front Office','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(49,39,'Operator & Technician','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(50,39,'IT Design & Developer','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(51,39,'Other Jobs','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(52,NULL,'Electronics & Appliances','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(53,52,'TVs, Video - Audio','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(54,52,'Kitchen & Other Appliances','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(55,52,'Computers & Laptops','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(56,52,'Cameras & Lenses','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(57,52,'Games & Entertainment','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(58,52,'Fridges','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(59,52,'Washing Machines','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(60,52,'ACs','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(61,NULL,'Commercial Vehicles & Spares','uploads/categories/commercial_vehicles__spares.png',0,'2026-07-02 05:41:55'),(62,61,'Commercial & Other Vehicles','uploads/categories/commercial_vehicles__spares.png',0,'2026-07-02 05:41:55'),(63,61,'Spare Parts','uploads/categories/commercial_vehicles__spares.png',0,'2026-07-02 05:41:55'),(64,NULL,'Furniture','uploads/categories/furniture.png',0,'2026-07-02 05:41:55'),(65,64,'Sofa & Dining','uploads/categories/furniture.png',0,'2026-07-02 05:41:55'),(66,64,'Beds & Wardrobes','uploads/categories/furniture.png',0,'2026-07-02 05:41:55'),(67,64,'Home Decor & Garden','uploads/categories/furniture.png',0,'2026-07-02 05:41:55'),(68,64,'Kids Furniture','uploads/categories/furniture.png',0,'2026-07-02 05:41:55'),(69,64,'Other Household Items','uploads/categories/furniture.png',0,'2026-07-02 05:41:55'),(70,NULL,'Fashion','uploads/categories/fashion.png',0,'2026-07-02 05:41:55'),(71,70,'Men','uploads/categories/fashion.png',0,'2026-07-02 05:41:55'),(72,70,'Women','uploads/categories/fashion.png',0,'2026-07-02 05:41:55'),(73,70,'Kids','uploads/categories/fashion.png',0,'2026-07-02 05:41:55'),(74,NULL,'Books, Sports & Hobbies','uploads/categories/books_sports__hobbies.png',0,'2026-07-02 05:41:55'),(75,74,'Books','uploads/categories/books_sports__hobbies.png',0,'2026-07-02 05:41:55'),(76,74,'Gym & Fitness','uploads/categories/books_sports__hobbies.png',0,'2026-07-02 05:41:55'),(77,74,'Musical Instruments','uploads/categories/books_sports__hobbies.png',0,'2026-07-02 05:41:55'),(78,74,'Sports Equipment','uploads/categories/books_sports__hobbies.png',0,'2026-07-02 05:41:55'),(79,74,'Other Hobbies','uploads/categories/books_sports__hobbies.png',0,'2026-07-02 05:41:55'),(80,NULL,'Pets','uploads/categories/pets.png',0,'2026-07-02 05:41:55'),(81,80,'Dogs','uploads/categories/pets.png',0,'2026-07-02 05:41:55'),(82,80,'Aquarium & Fish','uploads/categories/pets.png',0,'2026-07-02 05:41:55'),(83,80,'Pet Food & Accessories','uploads/categories/pets.png',0,'2026-07-02 05:41:55'),(84,80,'Other Pets','uploads/categories/pets.png',0,'2026-07-02 05:41:55'),(85,NULL,'Services','uploads/categories/services.png',0,'2026-07-02 05:41:55'),(86,85,'Education & Classes','uploads/categories/services.png',0,'2026-07-02 05:41:55'),(87,85,'Web Development','uploads/categories/services.png',0,'2026-07-02 05:41:55'),(88,85,'Electronics & Computer Repair','uploads/categories/services.png',0,'2026-07-02 05:41:55'),(89,85,'Drivers & Taxi','uploads/categories/services.png',0,'2026-07-02 05:41:55'),(90,85,'Health & Beauty','uploads/categories/services.png',0,'2026-07-02 05:41:55'),(91,85,'Other Services','uploads/categories/services.png',0,'2026-07-02 05:41:55');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `interstitial_ads`
--

DROP TABLE IF EXISTS `interstitial_ads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interstitial_ads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `media_file` varchar(255) NOT NULL,
  `media_type` varchar(50) NOT NULL,
  `link_url` varchar(255) DEFAULT '',
  `duration` int(11) DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `interstitial_ads`
--

LOCK TABLES `interstitial_ads` WRITE;
/*!40000 ALTER TABLE `interstitial_ads` DISABLE KEYS */;
/*!40000 ALTER TABLE `interstitial_ads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (34,9,8,10,'Are there any scratches or functional defects?',1,'2026-07-02 09:01:25',0,0,0),(35,9,8,10,'Does it include the original bill and box?',1,'2026-07-02 09:01:26',0,0,0),(40,8,9,10,'Does it include the original bill and box?',1,'2026-07-02 09:28:15',0,0,0);
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (1,1,'uploads/products/1780031595_0_1.jpeg','2026-05-29 05:13:15'),(2,1,'uploads/products/1780031595_1_11187220_4670051.jpg','2026-05-29 05:13:15'),(3,1,'uploads/products/1780031595_2_6208024_3215461.jpg','2026-05-29 05:13:15'),(4,2,'uploads/products/1780031604_0_1.jpeg','2026-05-29 05:13:25'),(5,2,'uploads/products/1780031605_1_11187220_4670051.jpg','2026-05-29 05:13:25'),(6,2,'uploads/products/1780031605_2_6208024_3215461.jpg','2026-05-29 05:13:25'),(7,3,'uploads/products/1780031677_0_2.jpeg','2026-05-29 05:14:37'),(8,3,'uploads/products/1780031677_1_1.webp','2026-05-29 05:14:37'),(9,4,'uploads/products/1780031887_0_2.3.png','2026-05-29 05:18:07'),(10,4,'uploads/products/1780031887_1_2.1.png','2026-05-29 05:18:07'),(11,4,'uploads/products/1780031887_2_2.1.webp','2026-05-29 05:18:07'),(12,5,'uploads/products/1780035149_1_1000401585.jpg','2026-05-29 06:12:29'),(13,5,'uploads/products/1780035149_0_1000402999.jpg','2026-05-29 06:12:29'),(14,5,'uploads/products/1780035149_2_1000401583.jpg','2026-05-29 06:12:29'),(15,6,'uploads/products/1780380346_0_WhatsApp Image 2026-06-02 at 11.13.46 AM.jpeg','2026-06-02 06:05:46'),(16,8,'uploads/products/1782810626_0_3.2.jpeg','2026-06-30 09:10:26'),(17,8,'uploads/products/1782810626_1_3.1.avif','2026-06-30 09:10:26'),(18,8,'uploads/products/1782810626_2_2.3.png','2026-06-30 09:10:26'),(20,10,'uploads/products/1782968797_0.jpg','2026-07-02 05:06:37'),(24,14,'uploads/products/1782972523_0.jpg','2026-07-02 06:08:44'),(25,15,'uploads/products/1782975719_0.jpg','2026-07-02 07:01:59'),(26,15,'uploads/products/1782975719_1.jpg','2026-07-02 07:01:59'),(27,15,'uploads/products/1782975719_2.jpg','2026-07-02 07:02:00'),(28,15,'uploads/products/1782975720_3.jpg','2026-07-02 07:02:00'),(29,15,'uploads/products/1782975720_4.jpg','2026-07-02 07:02:00'),(30,15,'uploads/products/1782975720_5.jpg','2026-07-02 07:02:00'),(31,15,'uploads/products/1782975720_6.jpg','2026-07-02 07:02:00'),(32,15,'uploads/products/1782975720_7.jpg','2026-07-02 07:02:01'),(33,15,'uploads/products/1782975721_8.jpg','2026-07-02 07:02:01'),(34,15,'uploads/products/1782975721_9.jpg','2026-07-02 07:02:01');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_notified` tinyint(1) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  KEY `user_id` (`user_id`),
  KEY `category_id` (`category_id`),
  KEY `idx_products_status_created` (`status`,`created_at`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'ENTAGD6030',2,12,'SUMSAONG MOBILE FOR SALE','Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.',125000.00,'sell',NULL,'','','Current Location',9.95140350,76.63020922,'deleted','Item no longer available',0,1,0,'2026-05-29 05:13:15','2026-05-29 05:13:51'),(2,'ENTAGD1201',2,12,'SUMSAONG MOBILE FOR SALE','Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.',125000.00,'sell',NULL,'','','Current Location',9.95140350,76.63020922,'active',NULL,1,1,28,'2026-05-29 05:13:24','2026-07-02 08:22:28'),(3,'ENTAGD2506',2,10,'EGG','Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.',150.00,'sell','2026-05-30','','','Ernakulam',9.95083934,76.62997645,'expired',NULL,1,1,5,'2026-05-29 05:14:37','2026-05-31 11:34:01'),(4,'ENTAGD4231',3,14,'HEADPHONES','Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.',1205.00,'sell',NULL,'','','Current Location',9.95106800,76.63014200,'active',NULL,1,1,21,'2026-05-29 05:18:07','2026-07-01 09:13:29'),(5,'ENTAGD8258',3,12,'Mobile sumsang','Based on everything you\'ve observed about me from our past chats - mv personalitv, habits. question patterns, overthinking, random chaos, and overall vibe - give me an extremely brutal, witty, sarcastic roast in Manglish script. Make it feel deeply personal, highly intelligent, painfully accurate, and laugh-out-loud funny. Roast my mindset, behavior, insecurities communication style, and signature habits. Use sharp humor. dark sarcasm, clever callbacks, and zero mercy. Don\'t be generic. Make it sound like a close friend who knows too much about me decided to destroy me for entertainment',22500.00,'sell',NULL,'','','Varappetty ',NULL,NULL,'active',NULL,0,1,17,'2026-05-29 06:12:29','2026-06-16 13:57:37'),(6,'ENTAGD8387',3,12,'fuck','fuck',1200.00,'',NULL,'','','Kochi',9.94060000,76.26530000,'deleted','Item no longer available',0,1,1,'2026-06-02 06:05:46','2026-06-02 06:06:48'),(7,'ENTAGD4786',2,10,'Ggsg','Based on everything you\'ve observed about me from our past chats - mv personalitv, habits. question patterns, overthinking, random chaos, and overall vibe - give me an extremely brutal, witty, sarcastic roast in Manglish script. Make it feel deeply personal, highly intelligent, painfully accurate, and laugh-out-loud funny. Roast my mindset, behavior, insecurities communication style, and signature habits. Use sharp humor. dark sarcasm, clever callbacks, and zero mercy. Don\'t be generic. Make it sound like a close friend who knows too much about me decided to destroy me for entertainment',878.00,'sell','2026-06-17','','','Varappetty',10.00789240,76.62321590,'deleted','Item no longer available',0,1,0,'2026-06-16 08:58:37','2026-06-16 08:59:25'),(8,'ENTAGD2191',2,12,'test','Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of \"de Finibus Bonorum et Malorum\" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, \"Lorem ipsum dolor sit amet..\", comes from a line in section 1.10.32.',2222.00,'',NULL,'','','Sampangirama Nagar',12.97530000,77.59100000,'active',NULL,0,0,6,'2026-06-30 09:10:26','2026-07-02 07:02:33'),(10,'ENTAGD1910',8,14,'aa','11',1111.00,'',NULL,'911111111111','','Vazhakulam',9.95131359,76.63073553,'active',NULL,0,1,7,'2026-07-02 05:06:37','2026-07-02 10:09:39'),(14,'ENTAGD3985',8,84,'Hens','udfvhaliduvhc khvlakdjcnv ;sdf; lerkjd ae;ordfjg ;aoedlfkg ;aoeirskjg v;aldkc',120.00,'',NULL,'919946020724','919946020716','Vazhakulam',9.95131359,76.63073553,'active',NULL,0,1,3,'2026-07-02 06:08:43','2026-07-02 08:21:54'),(15,'ENTAGD2419',8,31,'aa','aa',122222.00,'',NULL,'911234567890','911234567890','Vazhakulam',9.95131359,76.63073553,'active',NULL,0,1,4,'2026-07-02 07:01:59','2026-07-02 08:30:53');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_permissions_list`
--

DROP TABLE IF EXISTS `staff_permissions_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_permissions_list` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perm_key` varchar(50) NOT NULL,
  `perm_label` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `perm_key` (`perm_key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_permissions_list`
--

LOCK TABLES `staff_permissions_list` WRITE;
/*!40000 ALTER TABLE `staff_permissions_list` DISABLE KEYS */;
INSERT INTO `staff_permissions_list` VALUES (1,'manage_users','Users'),(2,'manage_categories','Categories'),(3,'manage_pending','Pending Ads'),(4,'manage_listings','Active Ads'),(5,'manage_branding','Branding'),(6,'manage_security','Security'),(7,'manage_general','General Config'),(8,'manage_contact','Contact/Social'),(9,'manage_approval','Approval Mode'),(10,'manage_announcements','Announcement Poster');
/*!40000 ALTER TABLE `staff_permissions_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_ratings`
--

DROP TABLE IF EXISTS `user_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_ratings`
--

LOCK TABLES `user_ratings` WRITE;
/*!40000 ALTER TABLE `user_ratings` DISABLE KEYS */;
INSERT INTO `user_ratings` VALUES (2,8,2,3,'Good','2026-07-02 06:44:18');
/*!40000 ALTER TABLE `user_ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@enteangadi.com','1234567890',NULL,NULL,'$2y$10$okTxdPTK8WSnkiiMsZjWRu6UjrGf18yHR3zYMcZKBkGfG9MFDHyne','admin',1,'*',NULL,NULL,'2026-05-29 05:07:16',0,0,0,0,0),(2,'SOJIN MATHEW','sojinmathew1040@gmail.com','8943804920',NULL,'uploads/profiles/6a43922ec3212.jpg','$2y$10$jhSHFzloKTMgzfqOxq.tMeVN9Ztb3qikBDtEKscnuSrtbMuB./S8K','user',0,NULL,'8c30c58d50ba342c9adaab732a523d48f8261211d3ca9f2b390f238023d3f77f','2026-06-30 10:10:24','2026-05-29 05:08:14',1,1,1,1,0),(3,'DIJO','dijo@gmail.com','9946020724',NULL,NULL,'$2y$10$/.sVY.hRPxufr5YhY5cGZOrAtkUm3w8pjGsVxchV0S5j6Fb9AtGUy','user',0,NULL,'97fa650002523e537cf63ef8299ed4c410615e8d1f01817ce8b53331144b7903','2026-07-01 09:13:07','2026-05-29 05:15:38',1,1,1,1,0),(4,'Melvin','melvi@gmail.com','1234567891',NULL,NULL,'$2y$10$zlxiwWlF3tWr9fzl72kMxOgvO8tV2Ko5UQ8fjEaPUGOc3W9o.sWIS','user',0,NULL,'9fa39e03fa7f2eb2f34b91fa4eda703404c6e299d488a8ab3c58c8fafb0efa8c',NULL,'2026-05-29 06:53:19',1,1,1,1,0),(5,'Test Auto Login User','autologin@test.com','9876543210',NULL,NULL,'$2y$10$JjXM5/HUxI4K1pVMcN6/tetrWCUToALkVBW8FqRRaES/FJlkLIHEW','user',0,NULL,NULL,'2026-05-07 06:06:16','2026-06-07 09:25:21',1,0,1,0,0),(6,'Antigravity Test','test_anti@gmail.com','919999999999',NULL,NULL,'$2y$10$khRrEZvgV3MOE/y3GWCMdOvhoRenEvjjGtgyTEFd1JSJs.9USTK/.','user',0,NULL,'052062bc5c88faa97b5324a869aaae2f606ede55156c2b37addc6bc378344a7b','2026-07-01 08:29:36','2026-07-01 08:29:36',0,1,0,0,0),(8,'Dijo J Perumaly','dijo2@gmail.com','919946020724',NULL,'uploads/profiles/6a45f4952ef2b.jpg','$2y$10$6eyw/ZyXFdbDviIBFim0p.93dfDoM2n03GSiuZZSxkU05MKaVHD/m','user',0,NULL,'26141df431c077f0c02a248730f077454a39e909e9b0d5265409bac4852af887','2026-07-02 09:28:05','2026-07-02 04:56:07',1,1,1,1,0),(9,'Eden','eden@gmail.com','919946020716',NULL,NULL,'$2y$10$QuHLVY5r0Rm.12z6W..gHeoPbeHvl/lpnvXO6WRu8ZDA5lWPoWQuO','user',0,NULL,'c442bae08fa5c1c023389430a4fb83fca2b315ab605323b31c0de9b5b347e3f9','2026-07-02 07:04:08','2026-07-02 07:04:08',1,1,1,1,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_requests`
--

DROP TABLE IF EXISTS `verification_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_requests`
--

LOCK TABLES `verification_requests` WRITE;
/*!40000 ALTER TABLE `verification_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `verification_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlist`
--

DROP TABLE IF EXISTS `wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist`
--

LOCK TABLES `wishlist` WRITE;
/*!40000 ALTER TABLE `wishlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlist` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-02 16:09:09
