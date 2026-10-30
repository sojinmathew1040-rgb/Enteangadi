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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `analytics_clicks`
--

LOCK TABLES `analytics_clicks` WRITE;
/*!40000 ALTER TABLE `analytics_clicks` DISABLE KEYS */;
INSERT INTO `analytics_clicks` VALUES (1,1,'view','2026-10-26',7),(2,2,'view','2026-10-26',4);
/*!40000 ALTER TABLE `analytics_clicks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement_views`
--

DROP TABLE IF EXISTS `announcement_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcement_views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `poster_image` varchar(255) DEFAULT NULL,
  `view_date` date NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_view_date` (`view_date`),
  KEY `idx_poster` (`poster_image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement_views`
--

LOCK TABLES `announcement_views` WRITE;
/*!40000 ALTER TABLE `announcement_views` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcement_views` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=109379 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES (1,'ad_approval_mode','auto','2026-05-29 05:07:16'),(944,'announcement_poster','','2026-10-26 10:20:42'),(961,'interstitial_ad_active','1','2026-05-29 06:08:47'),(962,'interstitial_ad_frequency','10','2026-05-29 06:08:47'),(8896,'play_store_url','https://play.google.com/store/apps/details?id=com.perumalilcreative.enteangadi','2026-06-13 04:02:59'),(8897,'app_store_url','https://apps.apple.com/app/enteangadi','2026-06-13 04:02:59'),(8985,'support_email','','2026-06-13 04:04:54'),(8986,'support_phone','','2026-06-13 04:04:54'),(8987,'whatsapp_number','','2026-06-13 04:04:54'),(8988,'facebook_url','','2026-06-13 04:04:54'),(8989,'instagram_url','','2026-06-13 04:04:54'),(8990,'twitter_url','','2026-06-13 04:04:54'),(30755,'adult_content_check','0','2026-07-02 06:33:44'),(30808,'app_logo','uploads/logo/logo_enteangadi.png','2026-10-26 10:51:32'),(30809,'app_name','Enteangadi','2026-07-02 06:18:51'),(30810,'app_tagline','Your Local Marketplace','2026-07-02 06:18:51'),(79550,'call_feature_active','1','2026-07-03 13:09:26'),(79551,'bg_notification_active','1','2026-07-03 13:09:26'),(79552,'bg_notification_interval','30','2026-07-03 13:09:26');
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (9,NULL,'Farm products','uploads/categories/farm_products.png',0,'2026-05-29 05:10:19'),(10,9,'Feed & Nutrition','uploads/categories/subcat_feed_nutrition.svg',1,'2026-05-29 05:10:54'),(11,NULL,'Mobiles','uploads/categories/mobiles.png',0,'2026-05-29 05:11:19'),(12,11,'Mobile Phones','uploads/categories/subcat_mobile_phones.svg',0,'2026-05-29 05:11:37'),(13,11,'Tablets','uploads/categories/subcat_tablets.svg',0,'2026-05-29 05:12:02'),(14,11,'Accessories','uploads/categories/subcat_mobile_accessories.svg',0,'2026-05-29 05:17:09'),(25,NULL,'Cars','uploads/categories/cars.png',0,'2026-07-02 05:41:55'),(26,25,'Cars','uploads/categories/subcat_cars_l2.svg',0,'2026-07-02 05:41:55'),(27,NULL,'Bikes','uploads/categories/bikes.png',0,'2026-07-02 05:41:55'),(28,27,'Motorcycles','uploads/categories/subcat_motorcycles.svg',0,'2026-07-02 05:41:55'),(29,27,'Scooters','uploads/categories/subcat_scooters.svg',0,'2026-07-02 05:41:55'),(30,27,'Spare Parts','uploads/categories/subcat_bike_spares.svg',0,'2026-07-02 05:41:55'),(31,27,'Bicycles','uploads/categories/subcat_bicycles.svg',0,'2026-07-02 05:41:55'),(32,NULL,'Properties','uploads/categories/properties.png',0,'2026-07-02 05:41:55'),(33,32,'For Sale: Houses & Apartments','uploads/categories/subcat_houses_sale.svg',0,'2026-07-02 05:41:55'),(34,32,'For Rent: Houses & Apartments','uploads/categories/subcat_houses_rent.svg',0,'2026-07-02 05:41:55'),(35,32,'Lands & Plots','uploads/categories/subcat_lands_plots.svg',0,'2026-07-02 05:41:55'),(36,32,'For Rent: Shops & Offices','uploads/categories/subcat_offices_rent.svg',0,'2026-07-02 05:41:55'),(37,32,'For Sale: Shops & Offices','uploads/categories/subcat_offices_sale.svg',0,'2026-07-02 05:41:55'),(38,32,'PG & Guest Houses','uploads/categories/subcat_pg_hostels.svg',0,'2026-07-02 05:41:55'),(39,NULL,'Jobs','uploads/categories/jobs.png',0,'2026-07-02 05:41:55'),(40,39,'Data Entry & Back Office','uploads/categories/subcat_job_data_entry.svg',0,'2026-07-02 05:41:55'),(41,39,'Sales & Marketing','uploads/categories/subcat_job_sales.svg',0,'2026-07-02 05:41:55'),(42,39,'BPO & Telecaller','uploads/categories/subcat_job_bpo.svg',0,'2026-07-02 05:41:55'),(43,39,'Driver','uploads/categories/subcat_job_driver.svg',0,'2026-07-02 05:41:55'),(44,39,'Office Assistant','uploads/categories/subcat_job_assistant.svg',0,'2026-07-02 05:41:55'),(45,39,'Delivery & Collection','uploads/categories/subcat_job_delivery.svg',0,'2026-07-02 05:41:55'),(46,39,'Teacher','uploads/categories/subcat_job_teacher.svg',0,'2026-07-02 05:41:55'),(47,39,'Cook','uploads/categories/subcat_job_cook.svg',0,'2026-07-02 05:41:55'),(48,39,'Receptionist & Front Office','uploads/categories/subcat_job_reception.svg',0,'2026-07-02 05:41:55'),(49,39,'Operator & Technician','uploads/categories/subcat_job_technician.svg',0,'2026-07-02 05:41:55'),(50,39,'IT Design & Developer','uploads/categories/subcat_job_it_dev.svg',0,'2026-07-02 05:41:55'),(51,39,'Other Jobs','uploads/categories/subcat_job_other.svg',0,'2026-07-02 05:41:55'),(52,NULL,'Electronics & Appliances','uploads/categories/electronics__appliances.png',0,'2026-07-02 05:41:55'),(53,52,'TVs, Video - Audio','uploads/categories/subcat_tv_audio.svg',0,'2026-07-02 05:41:55'),(54,52,'Kitchen & Other Appliances','uploads/categories/subcat_kitchen_appliances.svg',0,'2026-07-02 05:41:55'),(55,52,'Computers & Laptops','uploads/categories/subcat_computers_laptops.svg',0,'2026-07-02 05:41:55'),(56,52,'Cameras & Lenses','uploads/categories/subcat_cameras_lenses.svg',0,'2026-07-02 05:41:55'),(57,52,'Games & Entertainment','uploads/categories/subcat_gaming.svg',0,'2026-07-02 05:41:55'),(58,52,'Fridges','uploads/categories/subcat_fridges.svg',0,'2026-07-02 05:41:55'),(59,52,'Washing Machines','uploads/categories/subcat_washing_machines.svg',0,'2026-07-02 05:41:55'),(60,52,'ACs','uploads/categories/subcat_air_conditioners.svg',0,'2026-07-02 05:41:55'),(61,NULL,'Commercial Vehicles & Spares','uploads/categories/commercial_vehicles__spares.png',0,'2026-07-02 05:41:55'),(62,61,'Commercial & Other Vehicles','uploads/categories/subcat_commercial_trucks.svg',0,'2026-07-02 05:41:55'),(63,61,'Spare Parts','uploads/categories/subcat_commercial_spares.svg',0,'2026-07-02 05:41:55'),(64,NULL,'Furniture','uploads/categories/furniture.png',0,'2026-07-02 05:41:55'),(65,64,'Sofa & Dining','uploads/categories/subcat_sofa_dining.svg',0,'2026-07-02 05:41:55'),(66,64,'Beds & Wardrobes','uploads/categories/subcat_beds_wardrobes.svg',0,'2026-07-02 05:41:55'),(67,64,'Home Decor & Garden','uploads/categories/subcat_home_decor.svg',0,'2026-07-02 05:41:55'),(68,64,'Kids Furniture','uploads/categories/subcat_kids_furniture.svg',0,'2026-07-02 05:41:55'),(69,64,'Other Household Items','uploads/categories/subcat_household_other.svg',0,'2026-07-02 05:41:55'),(70,NULL,'Fashion','uploads/categories/fashion.png',0,'2026-07-02 05:41:55'),(71,70,'Men','uploads/categories/subcat_fashion_men.svg',0,'2026-07-02 05:41:55'),(72,70,'Women','uploads/categories/subcat_fashion_women.svg',0,'2026-07-02 05:41:55'),(73,70,'Kids','uploads/categories/subcat_fashion_kids.svg',0,'2026-07-02 05:41:55'),(74,NULL,'Books, Sports & Hobbies','uploads/categories/books_sports__hobbies.png',0,'2026-07-02 05:41:55'),(75,74,'Books','uploads/categories/subcat_books.svg',0,'2026-07-02 05:41:55'),(76,74,'Gym & Fitness','uploads/categories/subcat_gym_fitness.svg',0,'2026-07-02 05:41:55'),(77,74,'Musical Instruments','uploads/categories/subcat_music_instruments.svg',0,'2026-07-02 05:41:55'),(78,74,'Sports Equipment','uploads/categories/subcat_sports_equipment.svg',0,'2026-07-02 05:41:55'),(79,74,'Other Hobbies','uploads/categories/subcat_other_hobbies.svg',0,'2026-07-02 05:41:55'),(80,NULL,'Pets','uploads/categories/pets.png',0,'2026-07-02 05:41:55'),(81,80,'Dogs','uploads/categories/subcat_pets_dogs.svg',0,'2026-07-02 05:41:55'),(82,80,'Aquarium & Fish','uploads/categories/subcat_pets_aquarium.svg',0,'2026-07-02 05:41:55'),(83,80,'Pet Food & Accessories','uploads/categories/subcat_pets_food.svg',0,'2026-07-02 05:41:55'),(84,80,'Other Pets','uploads/categories/subcat_pets_other.svg',0,'2026-07-02 05:41:55'),(85,NULL,'Services','uploads/categories/services.png',0,'2026-07-02 05:41:55'),(86,85,'Education & Classes','uploads/categories/subcat_services_education.svg',0,'2026-07-02 05:41:55'),(87,85,'Web Development','uploads/categories/subcat_services_webdev.svg',0,'2026-07-02 05:41:55'),(88,85,'Electronics & Computer Repair','uploads/categories/subcat_services_repair.svg',0,'2026-07-02 05:41:55'),(89,85,'Drivers & Taxi','uploads/categories/subcat_services_taxi.svg',0,'2026-07-02 05:41:55'),(90,85,'Health & Beauty','uploads/categories/subcat_services_beauty.svg',0,'2026-07-02 05:41:55'),(91,85,'Other Services','uploads/categories/subcat_services_other.svg',0,'2026-07-02 05:41:55'),(92,80,'Goats & Kids','uploads/categories/subcat_pets_goats.svg',0,'2026-10-26 14:16:57'),(93,80,'Cows & Dairy Cattle','uploads/categories/subcat_pets_cows.svg',0,'2026-10-26 14:16:57'),(94,80,'Buffaloes','uploads/categories/subcat_pets_buffaloes.svg',0,'2026-10-26 14:16:57'),(95,80,'Chickens & Country Poultry','uploads/categories/subcat_pets_poultry.svg',0,'2026-10-26 14:16:57'),(96,80,'Ducks & Waterfowl','uploads/categories/subcat_pets_ducks.svg',0,'2026-10-26 14:16:57'),(97,80,'Rabbits & Bunnies','uploads/categories/subcat_pets_rabbits.svg',0,'2026-10-26 14:16:57'),(98,80,'Cats & Kittens','uploads/categories/subcat_pets_cats.svg',0,'2026-10-26 14:16:58'),(99,80,'Birds, Pigeons & Parrots','uploads/categories/subcat_pets_birds.svg',0,'2026-10-26 14:16:58'),(100,80,'Cages, Kennels & Farm Equipment','uploads/categories/subcat_pets_cages.svg',0,'2026-10-26 14:16:58'),(101,25,'Hatchbacks','uploads/categories/subcat_cars_hatchback.svg',0,'2026-10-26 14:16:58'),(102,25,'Sedans','uploads/categories/subcat_cars_sedan.svg',0,'2026-10-26 14:16:58'),(103,25,'SUVs & MUVs','uploads/categories/subcat_cars_suv.svg',0,'2026-10-26 14:16:58'),(104,25,'Compact & Micro SUVs','uploads/categories/subcat_cars_compact_suv.svg',0,'2026-10-26 14:16:58'),(105,25,'Luxury & Premium Cars','uploads/categories/subcat_cars_luxury.svg',0,'2026-10-26 14:16:58'),(106,25,'Electric & Hybrid Cars','uploads/categories/subcat_cars_ev.svg',0,'2026-10-26 14:16:58'),(107,25,'Commercial & Taxi Permit Cars','uploads/categories/subcat_cars_taxi.svg',0,'2026-10-26 14:16:58'),(108,25,'Car Accessories & Modifications','uploads/categories/subcat_cars_accessories.svg',0,'2026-10-26 14:16:58'),(109,27,'Commuter Bikes (100cc-150cc)','uploads/categories/subcat_bikes_commuter.svg',0,'2026-10-26 14:16:58'),(110,27,'Cruisers & Retro (Bullet, RE)','uploads/categories/subcat_bikes_cruisers.svg',0,'2026-10-26 14:16:58'),(111,27,'Sports & Superbikes','uploads/categories/subcat_bikes_sports.svg',0,'2026-10-26 14:16:58'),(112,27,'Electric Scooters & Bikes','uploads/categories/subcat_bikes_ev.svg',0,'2026-10-26 14:16:58'),(113,27,'Helmets & Riding Gear','uploads/categories/subcat_bikes_gear.svg',0,'2026-10-26 14:16:58'),(114,9,'Fresh Dairy, Milk & Ghee','uploads/categories/subcat_farm_milk.svg',1,'2026-10-26 14:16:58'),(115,9,'Country Eggs & Duck Eggs','uploads/categories/subcat_farm_eggs.svg',1,'2026-10-26 14:16:58'),(116,9,'Spices & Plantations (Pepper, Cardamom)','uploads/categories/subcat_farm_spices.svg',0,'2026-10-26 14:16:58'),(117,9,'Coconuts, Copra & Pure Coconut Oil','uploads/categories/subcat_farm_coconut.svg',0,'2026-10-26 14:16:58'),(118,9,'Fresh Fruits & Vegetables (Banana, Tapioca)','uploads/categories/subcat_farm_fruits_veg.svg',1,'2026-10-26 14:16:58'),(119,9,'Pure Honey & Bee Keeping','uploads/categories/subcat_farm_honey.svg',0,'2026-10-26 14:16:58'),(120,9,'Seeds, Plant Saplings & Nursery','uploads/categories/subcat_farm_seeds.svg',0,'2026-10-26 14:16:58'),(121,9,'Organic Fertilizers & Manure (Chanakam)','uploads/categories/subcat_farm_manure.svg',0,'2026-10-26 14:16:58'),(122,9,'Farm Machinery, Pumps & Sprayers','uploads/categories/subcat_farm_tools.svg',0,'2026-10-26 14:16:58'),(123,61,'Auto Rickshaws & 3-Wheelers','uploads/categories/subcat_comm_autorickshaw.svg',0,'2026-10-26 14:16:58'),(124,61,'Pickups & Mini Trucks (Tata Ace, Bolero)','uploads/categories/subcat_comm_pickups.svg',0,'2026-10-26 14:16:58'),(125,61,'Lorries, Tippers & Heavy Trucks','uploads/categories/subcat_comm_tipper_truck.svg',0,'2026-10-26 14:16:58'),(126,61,'Tractors & Farm Harvesters','uploads/categories/subcat_comm_tractor.svg',0,'2026-10-26 14:16:58'),(127,61,'Tourist Buses, Vans & Travellers','uploads/categories/subcat_comm_bus_traveller.svg',0,'2026-10-26 14:16:58'),(128,61,'Earth Moving, JCB & Cranes','uploads/categories/subcat_comm_jcb.svg',0,'2026-10-26 14:16:58'),(129,11,'Android Smartphones','uploads/categories/subcat_mobiles_android.svg',0,'2026-10-26 14:16:58'),(130,11,'iPhones & Apple Devices','uploads/categories/subcat_mobiles_iphone.svg',0,'2026-10-26 14:16:58'),(131,11,'Smart Watches & Bands','uploads/categories/subcat_mobiles_smartwatch.svg',0,'2026-10-26 14:16:58'),(132,11,'Earphones, TWS & Headphones','uploads/categories/subcat_mobiles_earphones.svg',0,'2026-10-26 14:16:58'),(133,32,'Agricultural Land & Plantations','uploads/categories/subcat_prop_agri_land.svg',0,'2026-10-26 14:16:58'),(134,32,'Villas & Independent Houses','uploads/categories/subcat_prop_villas.svg',0,'2026-10-26 14:16:58'),(135,32,'Commercial Land & Industrial Plots','uploads/categories/subcat_prop_comm_land.svg',0,'2026-10-26 14:16:58'),(136,32,'Godowns & Storage Warehouses','uploads/categories/subcat_prop_godowns.svg',0,'2026-10-26 14:16:58'),(137,32,'Homestays, Resorts & Holiday Homes','uploads/categories/subcat_prop_homestays.svg',0,'2026-10-26 14:16:58'),(138,52,'Inverters, Solar & Batteries','uploads/categories/subcat_elec_solar_inverter.svg',0,'2026-10-26 14:16:58'),(139,52,'Water Purifiers & Coolers','uploads/categories/subcat_elec_water_purifier.svg',0,'2026-10-26 14:16:58'),(140,52,'Home Theatres & Soundbars','uploads/categories/subcat_elec_soundbars.svg',0,'2026-10-26 14:16:58'),(141,52,'Mixers, Grinders & Blenders','uploads/categories/subcat_elec_mixers.svg',0,'2026-10-26 14:16:58'),(142,52,'CCTV & Security Systems','uploads/categories/subcat_elec_cctv.svg',0,'2026-10-26 14:16:58'),(143,85,'Home Electrical, Plumbing & Painting','uploads/categories/subcat_serv_maint.svg',0,'2026-10-26 14:16:58'),(144,85,'House Construction & Interior Design','uploads/categories/subcat_serv_construction.svg',0,'2026-10-26 14:16:58'),(145,85,'Catering, Events & Photography','uploads/categories/subcat_serv_catering.svg',0,'2026-10-26 14:16:58'),(146,85,'Septic Tank & Water Tank Cleaning','uploads/categories/subcat_serv_cleaning.svg',0,'2026-10-26 14:16:58');
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
  KEY `idx_messages_receiver_read_deleted` (`receiver_id`,`is_read`,`deleted_by_receiver`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_brands`
--

DROP TABLE IF EXISTS `product_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_brands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_brands`
--

LOCK TABLES `product_brands` WRITE;
/*!40000 ALTER TABLE `product_brands` DISABLE KEYS */;
INSERT INTO `product_brands` VALUES (1,25,'Maruti Suzuki','maruti-suzuki',NULL,0),(2,25,'Hyundai','hyundai',NULL,1),(3,25,'Tata','tata',NULL,2),(4,25,'Mahindra','mahindra',NULL,3),(5,25,'Toyota','toyota',NULL,4),(6,25,'Honda','honda',NULL,5),(7,25,'Kia','kia',NULL,6),(8,25,'Volkswagen','volkswagen',NULL,7),(9,25,'Skoda','skoda',NULL,8),(10,25,'Renault','renault',NULL,9),(11,25,'Nissan','nissan',NULL,10),(12,25,'MG (Morris Garages)','mg-morris-garages-',NULL,11),(13,25,'Mercedes-Benz','mercedes-benz',NULL,12),(14,25,'BMW','bmw',NULL,13),(15,25,'Audi','audi',NULL,14),(16,25,'Land Rover','land-rover',NULL,15),(17,27,'Royal Enfield','royal-enfield',NULL,0),(18,27,'Hero','hero',NULL,1),(19,27,'Honda','honda',NULL,2),(20,27,'TVS','tvs',NULL,3),(21,27,'Bajaj','bajaj',NULL,4),(22,27,'Yamaha','yamaha',NULL,5),(23,27,'KTM','ktm',NULL,6),(24,27,'Suzuki','suzuki',NULL,7),(25,27,'Jawa & Yezdi','jawa-yezdi',NULL,8),(26,27,'Ather Energy','ather-energy',NULL,9),(27,27,'Ola Electric','ola-electric',NULL,10),(28,27,'Revolt','revolt',NULL,11),(29,27,'Ultraviolette','ultraviolette',NULL,12),(30,11,'Apple','apple',NULL,0),(31,11,'Samsung','samsung',NULL,1),(32,11,'OnePlus','oneplus',NULL,2),(33,11,'Xiaomi & Redmi','xiaomi-redmi',NULL,3),(34,11,'Vivo','vivo',NULL,4),(35,11,'Realme','realme',NULL,5),(36,11,'Google Pixel','google-pixel',NULL,6),(37,80,'Goats (ആട്)','goats-',NULL,0),(38,80,'Cows (പശു)','cows-',NULL,1),(39,80,'Buffaloes (പോത്ത് / എരുമ)','buffaloes-',NULL,2),(40,80,'Dogs (നായ)','dogs-',NULL,3),(41,80,'Chickens & Poultry (കോഴി)','chickens-poultry-',NULL,4);
/*!40000 ALTER TABLE `product_brands` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (1,1,'uploads/products/1792987063_0.jpg','2026-10-25 15:26:41'),(2,2,'uploads/products/1792989176_0.jpg','2026-10-25 16:02:56'),(3,2,'uploads/products/1792989177_1.jpg','2026-10-25 16:02:56'),(4,2,'uploads/products/1792989177_2.jpg','2026-10-25 16:02:56');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_models`
--

DROP TABLE IF EXISTS `product_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_models` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `body_type` varchar(50) DEFAULT NULL,
  `slug` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `brand_id` (`brand_id`),
  KEY `name` (`name`),
  KEY `idx_pm_body_type` (`body_type`)
) ENGINE=InnoDB AUTO_INCREMENT=253 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_models`
--

LOCK TABLES `product_models` WRITE;
/*!40000 ALTER TABLE `product_models` DISABLE KEYS */;
INSERT INTO `product_models` VALUES (1,1,'Fronx','Compact SUV','fronx',0),(2,1,'Swift','Hatchback','swift',1),(3,1,'Baleno','Hatchback','baleno',2),(4,1,'Brezza','Compact SUV','brezza',3),(5,1,'Grand Vitara','SUV','grand-vitara',4),(6,1,'Jimny','Compact SUV','jimny',5),(7,1,'Dzire','Sedan','dzire',6),(8,1,'Ertiga','MUV','ertiga',7),(9,1,'WagonR','Hatchback','wagonr',8),(10,1,'Alto K10','Hatchback','alto-k10',9),(11,1,'Alto 800','Hatchback','alto-800',10),(12,1,'Celerio','Hatchback','celerio',11),(13,1,'Ignis','Hatchback','ignis',12),(14,1,'S-Presso','Hatchback','s-presso',13),(15,1,'Ciaz','Sedan','ciaz',14),(16,1,'XL6','MUV','xl6',15),(17,1,'Invicto','MUV','invicto',16),(18,1,'Eeco','MUV','eeco',17),(19,1,'Ritz','Hatchback','ritz',18),(20,1,'Zen','Hatchback','zen',19),(21,1,'Omni','MUV','omni',20),(22,1,'Maruti 800','Hatchback','maruti-800',21),(23,1,'Gypsy','SUV','gypsy',22),(24,2,'Creta','SUV','creta',0),(25,2,'Venue','Compact SUV','venue',1),(26,2,'i20','Hatchback','i20',2),(27,2,'Verna','Sedan','verna',3),(28,2,'Grand i10 Nios','Hatchback','grand-i10-nios',4),(29,2,'Exter','Compact SUV','exter',5),(30,2,'Alcazar','SUV','alcazar',6),(31,2,'Tucson','SUV','tucson',7),(32,2,'Aura','Sedan','aura',8),(33,2,'Santro','Hatchback','santro',9),(34,2,'Eon','Hatchback','eon',10),(35,2,'i10','Hatchback','i10',11),(36,2,'Accent','Sedan','accent',12),(37,3,'Nexon','Compact SUV','nexon',0),(38,3,'Punch','Compact SUV','punch',1),(39,3,'Harrier','SUV','harrier',2),(40,3,'Safari','SUV','safari',3),(41,3,'Tiago','Hatchback','tiago',4),(42,3,'Altroz','Hatchback','altroz',5),(43,3,'Tigor','Sedan','tigor',6),(44,3,'Curvv','Compact SUV','curvv',7),(45,3,'Sumo','MUV','sumo',8),(46,3,'Indica','Hatchback','indica',9),(47,3,'Indigo','Sedan','indigo',10),(48,3,'Safari Storme','SUV','safari-storme',11),(49,4,'Thar','SUV','thar',0),(50,4,'Scorpio-N','SUV','scorpio-n',1),(51,4,'Scorpio Classic','SUV','scorpio-classic',2),(52,4,'XUV700','SUV','xuv700',3),(53,4,'XUV 3XO','Compact SUV','xuv-3xo',4),(54,4,'Bolero','SUV','bolero',5),(55,4,'XUV300','Compact SUV','xuv300',6),(56,4,'XUV500','SUV','xuv500',7),(57,5,'Innova Crysta','MUV','innova-crysta',0),(58,5,'Innova Hycross','MUV','innova-hycross',1),(59,5,'Fortuner','SUV','fortuner',2),(60,5,'Urban Cruiser Hyryder','Compact SUV','urban-cruiser-hyryder',3),(61,5,'Glanza','Hatchback','glanza',4),(62,5,'Rumion','MUV','rumion',5),(63,5,'Hilux','SUV','hilux',6),(64,5,'Qualis','MUV','qualis',7),(65,5,'Etios','Sedan','etios',8),(66,6,'City','Sedan','city',0),(67,6,'Elevate','Compact SUV','elevate',1),(68,6,'Amaze','Sedan','amaze',2),(69,6,'Civic','Sedan','civic',3),(70,6,'Jazz','Hatchback','jazz',4),(71,6,'WR-V','Compact SUV','wr-v',5),(72,7,'Seltos','SUV','seltos',0),(73,7,'Sonet','Compact SUV','sonet',1),(74,7,'Carens','MUV','carens',2),(75,7,'Carnival','MUV','carnival',3),(76,8,'Virtus','Sedan','virtus',0),(77,8,'Taigun','Compact SUV','taigun',1),(78,8,'Polo','Hatchback','polo',2),(79,8,'Vento','Sedan','vento',3),(80,9,'Slavia','Sedan','slavia',0),(81,9,'Kushaq','Compact SUV','kushaq',1),(82,9,'Octavia','Sedan','octavia',2),(83,9,'Rapid','Sedan','rapid',3),(84,10,'Kwid','Hatchback','kwid',0),(85,10,'Triber','MUV','triber',1),(86,10,'Kiger','Compact SUV','kiger',2),(87,10,'Duster','Compact SUV','duster',3),(88,11,'Magnite','Compact SUV','magnite',0),(89,11,'Micra','Hatchback','micra',1),(90,12,'Hector','SUV','hector',0),(91,12,'Astor','Compact SUV','astor',1),(92,12,'ZS EV','Compact SUV','zs-ev',2),(93,12,'Comet EV','Hatchback','comet-ev',3),(94,13,'C-Class C200/C220d','Sedan','c-class-c200-c220d',0),(95,13,'E-Class E200/E220d','Sedan','e-class-e200-e220d',1),(96,13,'S-Class S350d/S450','Sedan','s-class-s350d-s450',2),(97,13,'GLA 200/220d','SUV','gla-200-220d',3),(98,13,'GLC 300/220d','SUV','glc-300-220d',4),(99,13,'GLE 300d/450','SUV','gle-300d-450',5),(100,13,'GLS 400d/450','SUV','gls-400d-450',6),(101,13,'A-Class Limousine','Sedan','a-class-limousine',7),(102,14,'3 Series Gran Limousine','Sedan','3-series-gran-limousine',0),(103,14,'5 Series 520d/530i','Sedan','5-series-520d-530i',1),(104,14,'7 Series 740i','Sedan','7-series-740i',2),(105,14,'X1 sDrive18d/18i','SUV','x1-sdrive18d-18i',3),(106,14,'X3 xDrive20d','SUV','x3-xdrive20d',4),(107,14,'X5 xDrive30d','SUV','x5-xdrive30d',5),(108,14,'X7 xDrive40i','SUV','x7-xdrive40i',6),(109,14,'2 Series Gran Coupe','Sedan','2-series-gran-coupe',7),(110,15,'A4 40 TFSI','Sedan','a4-40-tfsi',0),(111,15,'A6 45 TFSI','Sedan','a6-45-tfsi',1),(112,15,'Q3 40 TFSI','SUV','q3-40-tfsi',2),(113,15,'Q5 45 TFSI','SUV','q5-45-tfsi',3),(114,15,'Q7 55 TFSI','SUV','q7-55-tfsi',4),(115,15,'Q8','SUV','q8',5),(116,16,'Defender 90','SUV','defender-90',0),(117,16,'Defender 110','SUV','defender-110',1),(118,16,'Defender 130','SUV','defender-130',2),(119,16,'Range Rover Evoque','SUV','range-rover-evoque',3),(120,16,'Range Rover Velar','SUV','range-rover-velar',4),(121,16,'Range Rover Sport','SUV','range-rover-sport',5),(122,16,'Discovery Sport','SUV','discovery-sport',6),(123,17,'Classic 350','Cruiser','classic-350',0),(124,17,'Hunter 350','Cruiser','hunter-350',1),(125,17,'Bullet 350','Cruiser','bullet-350',2),(126,17,'Meteor 350','Cruiser','meteor-350',3),(127,17,'Continental GT 650','Cruiser','continental-gt-650',4),(128,17,'Interceptor 650','Cruiser','interceptor-650',5),(129,17,'Himalayan 450','Cruiser','himalayan-450',6),(130,17,'Guerrilla 450','Cruiser','guerrilla-450',7),(131,17,'Shotgun 650','Cruiser','shotgun-650',8),(132,17,'Super Meteor 650','Cruiser','super-meteor-650',9),(133,17,'Scram 411','Cruiser','scram-411',10),(134,17,'Thunderbird 350 / 350X','Cruiser','thunderbird-350-350x',11),(135,17,'Electra 350','Cruiser','electra-350',12),(136,18,'Splendor Plus','Motorcycle','splendor-plus',0),(137,18,'HF Deluxe','Motorcycle','hf-deluxe',1),(138,18,'Passion','Motorcycle','passion',2),(139,18,'Glamour','Motorcycle','glamour',3),(140,18,'Xtreme 125R','Sports Bike','xtreme-125r',4),(141,18,'Xtreme 160R','Sports Bike','xtreme-160r',5),(142,18,'Xpulse 200','Motorcycle','xpulse-200',6),(143,18,'Karizma','Sports Bike','karizma',7),(144,18,'Pleasure Plus','Scooter','pleasure-plus',8),(145,18,'Destini 125','Scooter','destini-125',9),(146,18,'Xoom 110','Scooter','xoom-110',10),(147,19,'Activa','Scooter','activa',0),(148,19,'Dio','Scooter','dio',1),(149,19,'Shine','Motorcycle','shine',2),(150,19,'SP 125','Motorcycle','sp-125',3),(151,19,'SP 160','Motorcycle','sp-160',4),(152,19,'Unicorn 160','Motorcycle','unicorn-160',5),(153,19,'CB350 H\'ness','Cruiser','cb350-h-ness',6),(154,19,'CB350RS','Cruiser','cb350rs',7),(155,19,'CB300R','Sports Bike','cb300r',8),(156,19,'Hornet 2.0','Sports Bike','hornet-2-0',9),(157,20,'Jupiter','Scooter','jupiter',0),(158,20,'Ntorq 125','Scooter','ntorq-125',1),(159,20,'Raider 125','Motorcycle','raider-125',2),(160,20,'Apache RTR 160','Sports Bike','apache-rtr-160',3),(161,20,'Apache RTR 200 4V','Sports Bike','apache-rtr-200-4v',4),(162,20,'Apache RR 310','Sports Bike','apache-rr-310',5),(163,20,'Ronin 225','Cruiser','ronin-225',6),(164,20,'XL100','Motorcycle','xl100',7),(165,20,'iQube EV','Electric Scooter','iqube-ev',8),(166,21,'Pulsar 125','Motorcycle','pulsar-125',0),(167,21,'Pulsar 150','Motorcycle','pulsar-150',1),(168,21,'Pulsar NS125','Sports Bike','pulsar-ns125',2),(169,21,'Pulsar NS200','Sports Bike','pulsar-ns200',3),(170,21,'Pulsar N160','Sports Bike','pulsar-n160',4),(171,21,'Pulsar N250','Sports Bike','pulsar-n250',5),(172,21,'Pulsar 220F','Sports Bike','pulsar-220f',6),(173,21,'Pulsar NS400Z','Sports Bike','pulsar-ns400z',7),(174,21,'Platina','Motorcycle','platina',8),(175,21,'Chetak EV','Electric Scooter','chetak-ev',9),(176,21,'Dominar','Motorcycle','dominar',10),(177,21,'Avenger','Cruiser','avenger',11),(178,22,'YZF R15','Sports Bike','yzf-r15',0),(179,22,'MT-15','Sports Bike','mt-15',1),(180,22,'FZ','Sports Bike','fz',2),(181,22,'Aerox 155','Scooter','aerox-155',3),(182,22,'RayZR 125','Scooter','rayzr-125',4),(183,22,'Fascino 125','Scooter','fascino-125',5),(184,22,'RX 100 / RX 135','Motorcycle','rx-100-rx-135',6),(185,23,'Duke','Sports Bike','duke',0),(186,23,'RC','Sports Bike','rc',1),(187,23,'Adventure','Sports Bike','adventure',2),(188,24,'Access 125','Scooter','access-125',0),(189,24,'Burgman Street','Scooter','burgman-street',1),(190,24,'Avenis 125','Scooter','avenis-125',2),(191,24,'Gixxer','Sports Bike','gixxer',3),(192,24,'V-Strom SX','Sports Bike','v-strom-sx',4),(193,25,'Jawa','Cruiser','jawa',0),(194,25,'Yezdi','Cruiser','yezdi',1),(195,26,'450S 2.9 kWh','Electric Scooter','450s-2-9-kwh',0),(196,26,'450X 2.9 kWh','Electric Scooter','450x-2-9-kwh',1),(197,26,'450X 3.7 kWh Pro','Electric Scooter','450x-3-7-kwh-pro',2),(198,26,'450 Apex Limited','Electric Scooter','450-apex-limited',3),(199,26,'Rizta S 2.9 kWh','Electric Scooter','rizta-s-2-9-kwh',4),(200,26,'Rizta Z 3.7 kWh','Electric Scooter','rizta-z-3-7-kwh',5),(201,27,'S1 X 2 kWh','Electric Scooter','s1-x-2-kwh',0),(202,27,'S1 X 3 kWh','Electric Scooter','s1-x-3-kwh',1),(203,27,'S1 X 4 kWh','Electric Scooter','s1-x-4-kwh',2),(204,27,'S1 Air 3 kWh','Electric Scooter','s1-air-3-kwh',3),(205,27,'S1 Pro Gen 2 4 kWh','Electric Scooter','s1-pro-gen-2-4-kwh',4),(206,28,'RV400 BRZ','Motorcycle','rv400-brz',0),(207,28,'RV400 Premium Fast Charger','Motorcycle','rv400-premium-fast-charger',1),(208,29,'F77 Mach 2 Original','Sports Bike','f77-mach-2-original',0),(209,29,'F77 Mach 2 Recon','Sports Bike','f77-mach-2-recon',1),(210,30,'iPhone 16 Series',NULL,'iphone-16-series',0),(211,30,'iPhone 15 Series',NULL,'iphone-15-series',1),(212,30,'iPhone 14 Series',NULL,'iphone-14-series',2),(213,30,'iPhone 13 Series',NULL,'iphone-13-series',3),(214,30,'iPhone 12 & 11',NULL,'iphone-12-11',4),(215,30,'iPad',NULL,'ipad',5),(216,31,'Galaxy S Series',NULL,'galaxy-s-series',0),(217,31,'Galaxy Z Fold & Flip',NULL,'galaxy-z-fold-flip',1),(218,31,'Galaxy A Series',NULL,'galaxy-a-series',2),(219,31,'Galaxy M & F Series',NULL,'galaxy-m-f-series',3),(220,31,'Galaxy Tab',NULL,'galaxy-tab',4),(221,32,'Number Flagships',NULL,'number-flagships',0),(222,32,'Nord Series',NULL,'nord-series',1),(223,32,'Open Foldable',NULL,'open-foldable',2),(224,33,'Xiaomi Flagship',NULL,'xiaomi-flagship',0),(225,33,'Redmi Note 13 Series',NULL,'redmi-note-13-series',1),(226,33,'Redmi Budget Series',NULL,'redmi-budget-series',2),(227,34,'X Series',NULL,'x-series',0),(228,34,'V Series',NULL,'v-series',1),(229,34,'T & Y Series',NULL,'t-y-series',2),(230,35,'GT Series',NULL,'gt-series',0),(231,35,'Number Pro Series',NULL,'number-pro-series',1),(232,35,'Narzo Series',NULL,'narzo-series',2),(233,36,'Pixel 9 Series',NULL,'pixel-9-series',0),(234,36,'Pixel 8 Series',NULL,'pixel-8-series',1),(235,36,'Pixel 7 & 6 Series',NULL,'pixel-7-6-series',2),(236,37,'Malabari / Tellicherry (തലശ്ശേരി ആട്)',NULL,'malabari-tellicherry-',0),(237,37,'Jamnapari (ജമുനാപാരി)',NULL,'jamnapari-',1),(238,37,'Boer (ബോയർ)',NULL,'boer-',2),(239,37,'Beetle / Sirohi',NULL,'beetle-sirohi',3),(240,38,'Holstein Friesian (HF)',NULL,'holstein-friesian-hf-',0),(241,38,'Jersey (ജേഴ്സി)',NULL,'jersey-',1),(242,38,'Vechoor & Native (വെച്ചൂർ / നാടൻ)',NULL,'vechoor-native-',2),(243,39,'Murrah Buffalo',NULL,'murrah-buffalo',0),(244,39,'Meat Buffalo (പോത്ത്)',NULL,'meat-buffalo-',1),(245,40,'Labrador Retriever',NULL,'labrador-retriever',0),(246,40,'German Shepherd (GSD)',NULL,'german-shepherd-gsd-',1),(247,40,'Golden Retriever',NULL,'golden-retriever',2),(248,40,'Rottweiler',NULL,'rottweiler',3),(249,40,'Small Breeds',NULL,'small-breeds',4),(250,41,'Country Hen (നാടൻ കോഴി)',NULL,'country-hen-',0),(251,41,'High Egg Yielders',NULL,'high-egg-yielders',1),(252,41,'Specialty Breeds',NULL,'specialty-breeds',2);
/*!40000 ALTER TABLE `product_models` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `model_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `model_id` (`model_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=971 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (1,1,'Sigma',0),(2,1,'Delta',1),(3,1,'Delta Plus',2),(4,1,'Delta Plus (O)',3),(5,1,'Zeta',4),(6,1,'Zeta Plus',5),(7,1,'Alpha',6),(8,1,'Alpha Plus',7),(9,1,'1.0L Turbo',8),(10,1,'CNG',9),(11,2,'LXI',0),(12,2,'VXI',1),(13,2,'VXI (O)',2),(14,2,'ZXI',3),(15,2,'ZXI+',4),(16,2,'AMT',5),(17,2,'CNG',6),(18,2,'Dzire',7),(19,3,'Sigma',0),(20,3,'Delta',1),(21,3,'Zeta',2),(22,3,'Alpha',3),(23,3,'AGS',4),(24,3,'CNG',5),(25,4,'LXI',0),(26,4,'VXI',1),(27,4,'ZXI',2),(28,4,'ZXI+',3),(29,4,'AT',4),(30,4,'CNG',5),(31,5,'Sigma',0),(32,5,'Delta',1),(33,5,'Zeta',2),(34,5,'Alpha',3),(35,5,'Zeta+ (Hybrid)',4),(36,5,'Alpha+ (Hybrid)',5),(37,5,'AWD',6),(38,6,'Zeta',0),(39,6,'Alpha',1),(40,6,'AT',2),(41,6,'AllGrip 4x4',3),(42,7,'LXI',0),(43,7,'VXI',1),(44,7,'ZXI',2),(45,7,'ZXI+',3),(46,7,'AGS',4),(47,7,'CNG',5),(48,8,'LXI',0),(49,8,'VXI',1),(50,8,'ZXI',2),(51,8,'ZXI+',3),(52,8,'AT',4),(53,8,'CNG',5),(54,9,'LXI 1.0L',0),(55,9,'VXI 1.0L',1),(56,9,'ZXI 1.2L',2),(57,9,'ZXI+ 1.2L',3),(58,9,'AGS',4),(59,9,'CNG',5),(60,10,'Std',0),(61,10,'LXI',1),(62,10,'VXI',2),(63,10,'VXI+',3),(64,10,'AGS',4),(65,10,'CNG',5),(66,11,'Std',0),(67,11,'LXI',1),(68,11,'VXI',2),(69,11,'CNG',3),(70,12,'LXI',0),(71,12,'VXI',1),(72,12,'ZXI',2),(73,12,'ZXI+',3),(74,12,'AGS',4),(75,12,'CNG',5),(76,13,'Sigma',0),(77,13,'Delta',1),(78,13,'Zeta',2),(79,13,'Alpha',3),(80,13,'AMT',4),(81,14,'Std',0),(82,14,'LXI',1),(83,14,'VXI',2),(84,14,'VXI+',3),(85,14,'AGS',4),(86,14,'CNG',5),(87,15,'Sigma',0),(88,15,'Delta',1),(89,15,'Zeta',2),(90,15,'Alpha',3),(91,15,'AT',4),(92,16,'Zeta',0),(93,16,'Alpha',1),(94,16,'Alpha+',2),(95,16,'AT',3),(96,16,'CNG',4),(97,17,'Zeta+',0),(98,17,'Alpha+',1),(99,18,'5 Seater Std',0),(100,18,'5 Seater AC',1),(101,18,'7 Seater Std',2),(102,18,'Cargo',3),(103,18,'CNG',4),(104,19,'LXI',0),(105,19,'VXI',1),(106,19,'ZXI',2),(107,19,'LDI',3),(108,19,'VDI',4),(109,19,'ZDI',5),(110,20,'LX',0),(111,20,'LXI',1),(112,20,'VXI',2),(113,20,'Classic',3),(114,20,'Carbon',4),(115,20,'Steel',5),(116,20,'Estilo',6),(117,21,'5 Seater',0),(118,21,'8 Seater',1),(119,21,'Cargo',2),(120,21,'Ambulance',3),(121,22,'Std',0),(122,22,'AC',1),(123,22,'DX',2),(124,22,'5 Speed',3),(125,23,'King Hard Top',0),(126,23,'King Soft Top',1),(127,23,'4WD',2),(128,24,'E',0),(129,24,'EX',1),(130,24,'S',2),(131,24,'S(O)',3),(132,24,'SX',4),(133,24,'SX Tech',5),(134,24,'SX(O)',6),(135,24,'N Line',7),(136,24,'Knight Edition',8),(137,25,'E',0),(138,25,'S',1),(139,25,'S(O)',2),(140,25,'S+',3),(141,25,'SX',4),(142,25,'SX(O)',5),(143,25,'N Line',6),(144,26,'Era',0),(145,26,'Magna',1),(146,26,'Sportz',2),(147,26,'Sportz(O)',3),(148,26,'Asta',4),(149,26,'Asta(O)',5),(150,26,'N Line N6',6),(151,26,'N Line N8',7),(152,27,'EX',0),(153,27,'S',1),(154,27,'SX',2),(155,27,'SX(O)',3),(156,27,'1.5 Turbo',4),(157,28,'Era',0),(158,28,'Magna',1),(159,28,'Sportz',2),(160,28,'Asta',3),(161,28,'Corporate',4),(162,28,'CNG',5),(163,29,'EX',0),(164,29,'EX(O)',1),(165,29,'S',2),(166,29,'S(O)',3),(167,29,'SX',4),(168,29,'SX(O)',5),(169,29,'SX(O) Connect',6),(170,29,'CNG',7),(171,30,'Prestige',0),(172,30,'Platinum',1),(173,30,'Signature',2),(174,30,'Adventure Edition',3),(175,31,'Platinum',0),(176,31,'Signature',1),(177,31,'4WD',2),(178,32,'E',0),(179,32,'S',1),(180,32,'SX',2),(181,32,'SX(O)',3),(182,32,'CNG',4),(183,33,'Era',0),(184,33,'Magna',1),(185,33,'Sportz',2),(186,33,'Asta',3),(187,33,'Xing',4),(188,34,'D-Lite',0),(189,34,'Era',1),(190,34,'Magna',2),(191,34,'Sportz',3),(192,35,'D-Lite',0),(193,35,'Era',1),(194,35,'Magna',2),(195,35,'Sportz',3),(196,35,'Asta',4),(197,36,'GLS',0),(198,36,'GLE',1),(199,36,'Executive',2),(200,36,'CRDi',3),(201,37,'Smart',0),(202,37,'Smart+',1),(203,37,'Pure',2),(204,37,'Pure+',3),(205,37,'Creative',4),(206,37,'Creative+',5),(207,37,'Fearless',6),(208,37,'Fearless+',7),(209,37,'Dark Edition',8),(210,37,'Nexon EV',9),(211,38,'Pure',0),(212,38,'Adventure',1),(213,38,'Accomplished',2),(214,38,'Creative',3),(215,38,'Camo Edition',4),(216,38,'iCNG',5),(217,38,'Punch EV',6),(218,39,'Smart',0),(219,39,'Pure',1),(220,39,'Adventure',2),(221,39,'Fearless',3),(222,39,'Dark Edition',4),(223,39,'Kaziranga',5),(224,40,'Smart',0),(225,40,'Pure',1),(226,40,'Adventure',2),(227,40,'Accomplished',3),(228,40,'Dark Edition',4),(229,41,'XE',0),(230,41,'XM',1),(231,41,'XT',2),(232,41,'XZ',3),(233,41,'XZ+',4),(234,41,'iCNG',5),(235,41,'NRG',6),(236,41,'Tiago EV',7),(237,42,'XE',0),(238,42,'XM',1),(239,42,'XT',2),(240,42,'XZ',3),(241,42,'XZ+',4),(242,42,'Racer',5),(243,42,'iCNG',6),(244,42,'Dark Edition',7),(245,43,'XE',0),(246,43,'XM',1),(247,43,'XT',2),(248,43,'XZ',3),(249,43,'XZ+',4),(250,43,'iCNG',5),(251,43,'Tigor EV',6),(252,44,'Smart',0),(253,44,'Pure',1),(254,44,'Creative',2),(255,44,'Accomplished',3),(256,44,'Curvv EV',4),(257,45,'Gold',0),(258,45,'Victa',1),(259,45,'Spacio',2),(260,45,'SE',3),(261,45,'Grande',4),(262,46,'V2',0),(263,46,'V2 DLE',1),(264,46,'V2 DLS',2),(265,46,'eV2',3),(266,46,'Vista',4),(267,47,'CS',0),(268,47,'Marina',1),(269,47,'XL',2),(270,47,'Manza',3),(271,48,'LX',0),(272,48,'EX',1),(273,48,'VX',2),(274,48,'Varicor 400',3),(275,48,'4x4',4),(276,49,'AX(O)',0),(277,49,'LX',1),(278,49,'Earth Edition',2),(279,49,'RWD',3),(280,49,'4x4',4),(281,49,'Roxx MX1',5),(282,49,'Roxx MX3',6),(283,49,'Roxx AX7L',7),(284,50,'Z2',0),(285,50,'Z4',1),(286,50,'Z6',2),(287,50,'Z8',3),(288,50,'Z8 Select',4),(289,50,'Z8L',5),(290,50,'4WD',6),(291,51,'S',0),(292,51,'S11',1),(293,52,'MX',0),(294,52,'AX3',1),(295,52,'AX5',2),(296,52,'AX5 Select',3),(297,52,'AX7',4),(298,52,'AX7L',5),(299,52,'AWD',6),(300,53,'MX1',0),(301,53,'MX2',1),(302,53,'MX3',2),(303,53,'AX5',3),(304,53,'AX5L',4),(305,53,'AX7',5),(306,53,'AX7L',6),(307,54,'B4',0),(308,54,'B6',1),(309,54,'B6(O)',2),(310,54,'Power+',3),(311,54,'Neo N4',4),(312,54,'Neo N8',5),(313,54,'Neo N10',6),(314,54,'Neo N10(O)',7),(315,54,'Camper',8),(316,55,'W4',0),(317,55,'W6',1),(318,55,'W8',2),(319,55,'W8(O)',3),(320,55,'TurboSport',4),(321,56,'W4',0),(322,56,'W6',1),(323,56,'W8',2),(324,56,'W10',3),(325,56,'W11',4),(326,56,'AWD',5),(327,57,'GX',0),(328,57,'VX',1),(329,57,'ZX',2),(330,57,'Touring Sport',3),(331,58,'G',0),(332,58,'GX',1),(333,58,'GX(O)',2),(334,58,'VX',3),(335,58,'VX(O)',4),(336,58,'ZX',5),(337,58,'ZX(O) (Strong Hybrid)',6),(338,59,'4x2 MT',0),(339,59,'4x2 AT',1),(340,59,'4x4 MT',2),(341,59,'4x4 AT',3),(342,59,'Legender',4),(343,59,'GR-S',5),(344,60,'E',0),(345,60,'S',1),(346,60,'G',2),(347,60,'V',3),(348,60,'Strong Hybrid S/G/V',4),(349,60,'NeoDrive AWD',5),(350,60,'CNG',6),(351,61,'E',0),(352,61,'S',1),(353,61,'G',2),(354,61,'V',3),(355,61,'AMT',4),(356,61,'CNG',5),(357,62,'S',0),(358,62,'G',1),(359,62,'V',2),(360,62,'AT',3),(361,62,'CNG',4),(362,63,'Std 4x4',0),(363,63,'High 4x4',1),(364,64,'FS',0),(365,64,'GS',1),(366,64,'RS',2),(367,64,'Family',3),(368,65,'GD',0),(369,65,'VD',1),(370,65,'VXD',2),(371,65,'Cross',3),(372,65,'Liva GD/VD',4),(373,66,'SV',0),(374,66,'V',1),(375,66,'VX',2),(376,66,'ZX',3),(377,66,'e:HEV Hybrid',4),(378,66,'4th Gen V/VX',5),(379,67,'SV',0),(380,67,'V',1),(381,67,'VX',2),(382,67,'ZX',3),(383,67,'CVT',4),(384,68,'E',0),(385,68,'S',1),(386,68,'VX',2),(387,68,'CVT',3),(388,68,'Diesel S/VX',4),(389,69,'V',0),(390,69,'VX',1),(391,69,'ZX',2),(392,70,'V',0),(393,70,'VX',1),(394,70,'ZX',2),(395,70,'CVT',3),(396,71,'S',0),(397,71,'VX',1),(398,71,'Edge Edition',2),(399,72,'HTE',0),(400,72,'HTK',1),(401,72,'HTK+',2),(402,72,'HTX',3),(403,72,'HTX+',4),(404,72,'GTX+',5),(405,72,'X-Line',6),(406,73,'HTE',0),(407,73,'HTK',1),(408,73,'HTK+',2),(409,73,'HTX',3),(410,73,'HTX+',4),(411,73,'GTX+',5),(412,73,'X-Line',6),(413,74,'Premium',0),(414,74,'Prestige',1),(415,74,'Prestige Plus',2),(416,74,'Luxury',3),(417,74,'Luxury Plus',4),(418,74,'X-Line',5),(419,75,'Prestige',0),(420,75,'Limousine',1),(421,75,'Limousine Plus',2),(422,76,'Comfortline',0),(423,76,'Highline',1),(424,76,'Topline',2),(425,76,'GT',3),(426,76,'GT Plus',4),(427,77,'Comfortline',0),(428,77,'Highline',1),(429,77,'Topline',2),(430,77,'GT',3),(431,77,'GT Plus',4),(432,78,'Trendline',0),(433,78,'Comfortline',1),(434,78,'Highline',2),(435,78,'GT TSI',3),(436,78,'GT TDI',4),(437,79,'Trendline',0),(438,79,'Comfortline',1),(439,79,'Highline',2),(440,79,'Highline Plus',3),(441,80,'Active',0),(442,80,'Ambition',1),(443,80,'Style',2),(444,80,'Monte Carlo',3),(445,81,'Active',0),(446,81,'Ambition',1),(447,81,'Style',2),(448,81,'Monte Carlo',3),(449,82,'Style',0),(450,82,'L&K',1),(451,82,'vRS',2),(452,83,'Rider',0),(453,83,'Ambition',1),(454,83,'Style',2),(455,83,'Monte Carlo',3),(456,84,'RXE',0),(457,84,'RXL',1),(458,84,'RXT',2),(459,84,'Climber',3),(460,84,'AMT',4),(461,85,'RXE',0),(462,85,'RXL',1),(463,85,'RXT',2),(464,85,'RXZ',3),(465,85,'AMT',4),(466,86,'RXE',0),(467,86,'RXL',1),(468,86,'RXT',2),(469,86,'RXZ',3),(470,86,'Turbo CVT',4),(471,87,'RxE',0),(472,87,'RxL',1),(473,87,'RxZ',2),(474,87,'AWD',3),(475,87,'Turbo',4),(476,88,'XE',0),(477,88,'XL',1),(478,88,'XV',2),(479,88,'XV Premium',3),(480,88,'Kuro',4),(481,88,'Geza',5),(482,88,'Turbo CVT',6),(483,89,'Active',0),(484,89,'XL',1),(485,89,'XV',2),(486,89,'Diesel',3),(487,90,'Style',0),(488,90,'Shine',1),(489,90,'Select',2),(490,90,'Smart',3),(491,90,'Sharp',4),(492,90,'Savvy',5),(493,90,'Plus 6-Seater',6),(494,90,'Plus 7-Seater',7),(495,91,'Sprint',0),(496,91,'Shine',1),(497,91,'Select',2),(498,91,'Smart',3),(499,91,'Sharp',4),(500,91,'Savvy',5),(501,92,'Executive',0),(502,92,'Excite',1),(503,92,'Exclusive',2),(504,92,'Exclusive Plus',3),(505,92,'Essence',4),(506,93,'Executive',0),(507,93,'Excite',1),(508,93,'Exclusive',2),(509,94,'Standard',0),(510,95,'Standard',0),(511,96,'Standard',0),(512,97,'Standard',0),(513,98,'Standard',0),(514,99,'Standard',0),(515,100,'Standard',0),(516,101,'Standard',0),(517,102,'Standard',0),(518,103,'Standard',0),(519,104,'Standard',0),(520,105,'Standard',0),(521,106,'Standard',0),(522,107,'Standard',0),(523,108,'Standard',0),(524,109,'Standard',0),(525,110,'Standard',0),(526,111,'Standard',0),(527,112,'Standard',0),(528,113,'Standard',0),(529,114,'Standard',0),(530,115,'Standard',0),(531,116,'Standard',0),(532,117,'Standard',0),(533,118,'Standard',0),(534,119,'Standard',0),(535,120,'Standard',0),(536,121,'Standard',0),(537,122,'Standard',0),(538,123,'Redditch',0),(539,123,'Halcyon',1),(540,123,'Signals',2),(541,123,'Dark Edition',3),(542,123,'Chrome Edition',4),(543,124,'Retro Factory',0),(544,124,'Dapper White/Ash/Grey',1),(545,124,'Rebel Black/Blue/Red',2),(546,125,'Military Red/Black',0),(547,125,'Standard Black/Maroon',1),(548,125,'Black Gold',2),(549,126,'Fireball',0),(550,126,'Stellar',1),(551,126,'Supernova',2),(552,126,'Aurora',3),(553,127,'Standard',0),(554,127,'Custom',1),(555,127,'Chrome',2),(556,127,'Dark Alloy',3),(557,128,'Canyon Red',0),(558,128,'Sunset Strip',1),(559,128,'Mark 2 Chrome',2),(560,128,'Barcelona Blue',3),(561,129,'Base',0),(562,129,'Pass',1),(563,129,'Summit',2),(564,130,'Analogue',0),(565,130,'Dash',1),(566,130,'Flash',2),(567,131,'Custom Shed',0),(568,131,'Custom Pro',1),(569,131,'Custom Special',2),(570,132,'Astral',0),(571,132,'Interstellar',1),(572,132,'Celestial',2),(573,133,'Base',0),(574,133,'Mid',1),(575,133,'Premium',2),(576,134,'Standard',0),(577,134,'350X Alloy Disc',1),(578,134,'500X',2),(579,135,'Cast Iron 4-Speed',0),(580,135,'CI 5-Speed',1),(581,135,'UCE 350',2),(582,135,'Disc ES',3),(583,136,'Kick Start',0),(584,136,'Self Start i3S',1),(585,136,'XTEC Bluetooth',2),(586,136,'Silver Nexus',3),(587,136,'Black and Accent',4),(588,137,'Kick Start',0),(589,137,'Self Start i3S',1),(590,137,'Canvas Edition',2),(591,138,'Passion Plus',0),(592,138,'Passion Pro i3S',1),(593,138,'Passion XTEC Disc',2),(594,139,'Glamour Drum/Disc',0),(595,139,'Glamour XTEC Connected',1),(596,140,'IBS Drum',0),(597,140,'Single Channel ABS',1),(598,141,'Single Disc',0),(599,141,'Dual Disc',1),(600,141,'4V Connected',2),(601,142,'Xpulse 200 4V',0),(602,142,'Xpulse 200 4V Pro',1),(603,142,'Xpulse 200T 4V',2),(604,143,'Karizma XMR 210',0),(605,143,'Karizma ZMR',1),(606,143,'Karizma R',2),(607,144,'LX Drum',0),(608,144,'VX Self',1),(609,144,'XTEC Connected',2),(610,145,'Prime Drum',0),(611,145,'XTEC Connected Disc',1),(612,146,'LX',0),(613,146,'VX',1),(614,146,'ZX Cornering Lamp',2),(615,147,'Activa 6G Std',0),(616,147,'Activa 6G Deluxe',1),(617,147,'Activa 6G H-Smart',2),(618,147,'Activa 125 Drum',3),(619,147,'Activa 125 Disc',4),(620,147,'Activa 125 H-Smart',5),(621,148,'Dio 110 Std',0),(622,148,'Dio 110 Deluxe',1),(623,148,'Dio 110 H-Smart',2),(624,148,'Dio 125 Std',3),(625,148,'Dio 125 Smart',4),(626,149,'Shine 100 Drum',0),(627,149,'Shine 125 Drum',1),(628,149,'Shine 125 Disc',2),(629,149,'Celebration Edition',3),(630,150,'SP 125 Drum',0),(631,150,'SP 125 Disc',1),(632,150,'Sports Edition',2),(633,151,'Single Disc',0),(634,151,'Dual Disc',1),(635,152,'Monoshock ABS',0),(636,153,'DLX',0),(637,153,'DLX Pro',1),(638,153,'DLX Pro Chrome',2),(639,153,'Legacy Edition',3),(640,154,'Monotone',0),(641,154,'Dual Tone',1),(642,154,'Hue Edition',2),(643,155,'Neo Sports Cafe ABS',0),(644,156,'Single Channel ABS',0),(645,156,'Repsol Edition',1),(646,157,'Jupiter 110 Sheet Metal',0),(647,157,'Jupiter 110 Std',1),(648,157,'Jupiter 110 ZX SmartXonnect',2),(649,157,'Jupiter 110 Classic',3),(650,157,'Jupiter 125 Drum',4),(651,157,'Jupiter 125 Disc',5),(652,157,'Jupiter 125 SmartXonnect',6),(653,158,'Drum',0),(654,158,'Disc',1),(655,158,'Race Edition',2),(656,158,'Super Squad Marvel',3),(657,158,'Race XP Dual Mode',4),(658,158,'XT Smart Connected',5),(659,159,'Single Seat Drum',0),(660,159,'Split Seat Disc',1),(661,159,'SmartXonnect TFT',2),(662,159,'Super Squad',3),(663,160,'RTR 160 2V Drum/Disc',0),(664,160,'RTR 160 4V Single Disc',1),(665,160,'RTR 160 4V Dual Disc',2),(666,160,'RTR 160 4V Special Edition',3),(667,161,'Single Channel ABS',0),(668,161,'Dual Channel ABS with Riding Modes',1),(669,162,'Racing Red',0),(670,162,'Titanium Black',1),(671,162,'BTO Track Edition',2),(672,163,'SS Single Tone',0),(673,163,'DS Dual Tone',1),(674,163,'TD Triple Tone Triple Mode ABS',2),(675,164,'Heavy Duty',0),(676,164,'Comfort i-Touchstart',1),(677,164,'Win Edition',2),(678,165,'iQube 2.2 kWh',0),(679,165,'iQube 3.4 kWh',1),(680,165,'iQube S',2),(681,165,'iQube ST 5.1 kWh',3),(682,166,'Neon Single Seat',0),(683,166,'Carbon Fiber Split Seat',1),(684,167,'Single Disc',0),(685,167,'Twin Disc Dual Channel',1),(686,168,'Single Channel ABS',0),(687,169,'Dual Channel ABS USD Forks',0),(688,170,'Dual Channel ABS',0),(689,170,'USD Fork Edition',1),(690,171,'Dual Channel ABS Traction Control',0),(691,172,'Standard Twin Disc Dual Channel ABS',0),(692,173,'Standard 400cc 43PS Ride-by-wire',0),(693,174,'Platina 100 Kick/Self',0),(694,174,'Platina 110 Drum',1),(695,174,'Platina 110 ABS',2),(696,175,'Chetak 2901',0),(697,175,'Chetak Urbane',1),(698,175,'Chetak Premium TecPac',2),(699,176,'Dominar 250 Touring',0),(700,176,'Dominar 400 Factory Tourer',1),(701,177,'Avenger Street 160 ABS',0),(702,177,'Avenger Cruise 220 Windscreen',1),(703,178,'R15 V4 Metallic Red/Dark Knight',0),(704,178,'R15 V4 Racing Blue Quickshifter',1),(705,178,'R15M Silver Quickshifter',2),(706,178,'R15S V3 Unibody Seat',3),(707,179,'MT-15 V2 Standard',0),(708,179,'MT-15 V2 Cyber Green / Ice Fluo',1),(709,179,'MT-15 V2 MotoGP Edition',2),(710,180,'FZ-FI V3',0),(711,180,'FZ-S FI V3',1),(712,180,'FZ-S FI V4 Deluxe LED Traction',2),(713,180,'FZ-X Neo-Retro',3),(714,180,'FZ25 250cc',4),(715,181,'Standard LED Traction',0),(716,181,'MotoGP Edition',1),(717,182,'Drum Fi Hybrid',0),(718,182,'Disc Fi Hybrid',1),(719,182,'Street Rally Edition',2),(720,183,'Drum Fi Hybrid',0),(721,183,'Disc Fi Hybrid Special Edition',1),(722,184,'RX 100 2-Stroke Classic',0),(723,184,'RX 135 4-Speed',1),(724,184,'RX 135 5-Speed',2),(725,184,'RX-Z',3),(726,185,'Duke 125 ABS',0),(727,185,'Duke 200 Gen 3',1),(728,185,'Duke 250 Quickshifter',2),(729,185,'Duke 390 Cornering ABS Quickshifter+',3),(730,186,'RC 125 ABS',0),(731,186,'RC 200 Dual Channel',1),(732,186,'RC 390 Adjustable Suspension',2),(733,187,'250 Adventure',0),(734,187,'390 Adventure X',1),(735,187,'390 Adventure Spoke Wheels',2),(736,188,'Steel Drum',0),(737,188,'Alloy Drum',1),(738,188,'Alloy Disc',2),(739,188,'Special Edition',3),(740,188,'Ride Connect Bluetooth',4),(741,189,'Standard 125',0),(742,189,'Ride Connect Bluetooth',1),(743,189,'Burgman Street EX 12-inch Rear',2),(744,190,'Standard Bluetooth',0),(745,190,'Race Edition',1),(746,191,'Gixxer 150 Fi ABS',0),(747,191,'Gixxer SF 150 Fairing',1),(748,191,'Gixxer 250',2),(749,191,'Gixxer SF 250 MotoGP',3),(750,192,'V-Strom SX 250 Adventure',0),(751,193,'Jawa 350 Classic',0),(752,193,'Jawa 42 2.1',1),(753,193,'Jawa 42 Bobber',2),(754,193,'Jawa Perak Bobber',3),(755,194,'Yezdi Roadster',0),(756,194,'Yezdi Scrambler',1),(757,194,'Yezdi Adventure',2),(758,195,'Standard',0),(759,196,'Standard',0),(760,197,'Standard',0),(761,198,'Standard',0),(762,199,'Standard',0),(763,200,'Standard',0),(764,201,'Standard',0),(765,202,'Standard',0),(766,203,'Standard',0),(767,204,'Standard',0),(768,205,'Standard',0),(769,206,'Standard',0),(770,207,'Standard',0),(771,208,'Standard',0),(772,209,'Standard',0),(773,210,'iPhone 16 128GB',0),(774,210,'iPhone 16 256GB',1),(775,210,'iPhone 16 512GB',2),(776,210,'iPhone 16 Plus',3),(777,210,'iPhone 16 Pro 128GB',4),(778,210,'iPhone 16 Pro 256GB',5),(779,210,'iPhone 16 Pro 512GB',6),(780,210,'iPhone 16 Pro Max 256GB',7),(781,210,'iPhone 16 Pro Max 512GB',8),(782,210,'iPhone 16 Pro Max 1TB',9),(783,211,'iPhone 15 128GB',0),(784,211,'iPhone 15 256GB',1),(785,211,'iPhone 15 512GB',2),(786,211,'iPhone 15 Plus',3),(787,211,'iPhone 15 Pro',4),(788,211,'iPhone 15 Pro Max',5),(789,212,'iPhone 14 128GB',0),(790,212,'iPhone 14 256GB',1),(791,212,'iPhone 14 Plus',2),(792,212,'iPhone 14 Pro',3),(793,212,'iPhone 14 Pro Max',4),(794,213,'iPhone 13 128GB',0),(795,213,'iPhone 13 256GB',1),(796,213,'iPhone 13 mini',2),(797,213,'iPhone 13 Pro',3),(798,213,'iPhone 13 Pro Max',4),(799,214,'iPhone 12 64GB',0),(800,214,'iPhone 12 128GB',1),(801,214,'iPhone 11 64GB',2),(802,214,'iPhone 11 128GB',3),(803,215,'iPad 9th Gen',0),(804,215,'iPad 10th Gen',1),(805,215,'iPad Air M1/M2',2),(806,215,'iPad Pro M2/M4',3),(807,215,'iPad mini',4),(808,216,'Galaxy S24 Ultra',0),(809,216,'Galaxy S24+',1),(810,216,'Galaxy S24',2),(811,216,'Galaxy S23 Ultra',3),(812,216,'Galaxy S23 FE',4),(813,216,'Galaxy S23',5),(814,216,'Galaxy S22 Ultra',6),(815,216,'Galaxy S21 FE 5G',7),(816,217,'Galaxy Z Fold 6',0),(817,217,'Galaxy Z Fold 5',1),(818,217,'Galaxy Z Flip 6',2),(819,217,'Galaxy Z Flip 5',3),(820,218,'Galaxy A55 5G',0),(821,218,'Galaxy A35 5G',1),(822,218,'Galaxy A54 5G',2),(823,218,'Galaxy A34 5G',3),(824,218,'Galaxy A25 5G',4),(825,218,'Galaxy A15 5G',5),(826,218,'Galaxy A14 5G',6),(827,218,'Galaxy A05s',7),(828,219,'Galaxy M55 5G',0),(829,219,'Galaxy M34 5G',1),(830,219,'Galaxy M14 5G',2),(831,219,'Galaxy F54 5G',3),(832,219,'Galaxy F34 5G',4),(833,220,'Tab S9 Ultra',0),(834,220,'Tab S9',1),(835,220,'Tab S9 FE',2),(836,220,'Tab A9+',3),(837,220,'Tab A9',4),(838,221,'OnePlus 12 (12/256GB)',0),(839,221,'OnePlus 12 (16/512GB)',1),(840,221,'OnePlus 12R (8/128GB)',2),(841,221,'OnePlus 12R (16/256GB)',3),(842,221,'OnePlus 11 5G',4),(843,221,'OnePlus 11R 5G',5),(844,221,'OnePlus 10 Pro',6),(845,221,'OnePlus 10R',7),(846,222,'OnePlus Nord 4 5G',0),(847,222,'OnePlus Nord CE 4 5G',1),(848,222,'OnePlus Nord CE 4 Lite 5G',2),(849,222,'OnePlus Nord 3 5G',3),(850,222,'OnePlus Nord CE 3 Lite 5G',4),(851,223,'OnePlus Open 16GB/512GB',0),(852,224,'Xiaomi 14 Ultra',0),(853,224,'Xiaomi 14',1),(854,224,'Xiaomi 13 Pro',2),(855,225,'Redmi Note 13 Pro+ 5G',0),(856,225,'Redmi Note 13 Pro 5G',1),(857,225,'Redmi Note 13 5G',2),(858,225,'Redmi Note 13 4G',3),(859,226,'Redmi 13C 5G',0),(860,226,'Redmi 12 5G',1),(861,226,'Redmi A3',2),(862,226,'Redmi 10 Power',3),(863,227,'Vivo X100 Pro Zeiss',0),(864,227,'Vivo X100 Zeiss',1),(865,227,'Vivo X90 Pro',2),(866,228,'Vivo V40 Pro',0),(867,228,'Vivo V40',1),(868,228,'Vivo V30 Pro',2),(869,228,'Vivo V30',3),(870,228,'Vivo V30e',4),(871,228,'Vivo V29 Pro',5),(872,228,'Vivo V29',6),(873,229,'Vivo T3 Pro 5G',0),(874,229,'Vivo T3 5G',1),(875,229,'Vivo T3x 5G',2),(876,229,'Vivo Y200 5G',3),(877,229,'Vivo Y28 5G',4),(878,230,'Realme GT 6 5G',0),(879,230,'Realme GT 6T 5G',1),(880,230,'Realme GT 2 Pro',2),(881,231,'Realme 13 Pro+ 5G',0),(882,231,'Realme 13 Pro 5G',1),(883,231,'Realme 12 Pro+ 5G',2),(884,231,'Realme 12 Pro 5G',3),(885,231,'Realme 12 5G',4),(886,232,'Narzo 70 Pro 5G',0),(887,232,'Narzo 70x 5G',1),(888,232,'Narzo 60 Pro 5G',2),(889,232,'Narzo 60x 5G',3),(890,233,'Pixel 9 Pro XL',0),(891,233,'Pixel 9 Pro',1),(892,233,'Pixel 9 Pro Fold',2),(893,233,'Pixel 9',3),(894,234,'Pixel 8 Pro',0),(895,234,'Pixel 8',1),(896,234,'Pixel 8a',2),(897,235,'Pixel 7 Pro',0),(898,235,'Pixel 7',1),(899,235,'Pixel 7a',2),(900,235,'Pixel 6a',3),(901,236,'Milking Nanny (കറവയുള്ളത്)',0),(902,236,'Young Kid (ആട്ടിൻകുട്ടി)',1),(903,236,'Breeding Buck (മുട്ടനാട്)',2),(904,236,'Pregnant Doe (ചെനയുള്ളത്)',3),(905,236,'Pair (ജോടി)',4),(906,237,'Pure Breed (പ്യുവർ ബ്രീഡ്)',0),(907,237,'Cross Breed (ക്രോസ്സ്)',1),(908,237,'Kid (കുഞ്ഞ്)',2),(909,237,'Buck (മുട്ടനാട്)',3),(910,238,'100% Pure Boer',0),(911,238,'F1 Cross',1),(912,238,'F2 Cross',2),(913,238,'Boer Buck',3),(914,239,'Beetle Goat (ബീറ്റിൽ)',0),(915,239,'Sirohi (സിരോഹി)',1),(916,239,'Barbari (ബാർബറി)',2),(917,239,'Attappadi Black (അട്ടപ്പാടി കറുപ്പ്)',3),(918,240,'HF Pure (പ്യുവർ എച്ച്.എഫ്)',0),(919,240,'HF Cross (ക്രോസ്സ് ബ്രീഡ്)',1),(920,240,'High Milk Yield (20L+ കറവ)',2),(921,240,'First Calver (ആദ്യ പ്രസവം)',3),(922,240,'Heifer Calf (കിടാവ്)',4),(923,241,'Jersey Pure (ജേഴ്സി)',0),(924,241,'Jersey Cross',1),(925,241,'Milking (കറവയുള്ള പശു)',2),(926,241,'Pregnant (ചെനയുള്ള പശു)',3),(927,242,'Vechoor Cow (വെച്ചൂർ പശു)',0),(928,242,'Kasaragod Dwarf (കാസർഗോഡ് കുള്ളൻ)',1),(929,242,'Gir Cow (ഗിർ പശു)',2),(930,242,'Sahiwal (സാഹിവാൾ)',3),(931,243,'Milking Buffalo (കറവയുള്ള എരുമ)',0),(932,243,'High Milk Yield (15L+ പാലുള്ളത്)',1),(933,243,'Heifer / Calf (കിടാവ്)',2),(934,244,'Young Male Calf (പോത്തിൻകുട്ടി)',0),(935,244,'Fattening Male (വളർത്താനുള്ള പോത്ത്)',1),(936,244,'Slaughter Ready',2),(937,245,'Puppy Male',0),(938,245,'Puppy Female',1),(939,245,'KCI Registered',2),(940,245,'Golden Lab',3),(941,245,'Black Lab',4),(942,245,'Chocolate Lab',5),(943,246,'Puppy Male',0),(944,246,'Puppy Female',1),(945,246,'Long Coat',2),(946,246,'Short Coat',3),(947,246,'KCI Registered',4),(948,247,'Show Quality Puppy',0),(949,247,'KCI Registered Male',1),(950,247,'KCI Registered Female',2),(951,248,'Heavy Bone Puppy',0),(952,248,'Trained Adult Guard Dog',1),(953,248,'KCI Registered',2),(954,249,'Pug',0),(955,249,'Shih Tzu',1),(956,249,'Pomeranian / Spitz',2),(957,249,'Beagle',3),(958,249,'French Bulldog',4),(959,250,'Egg Laying Hen (മുട്ടയിടുന്ന കോഴി)',0),(960,250,'Country Rooster (നാടൻ പൂവൻ)',1),(961,250,'Chicks (കോഴിക്കുഞ്ഞുങ്ങൾ)',2),(962,251,'BV380 (മുട്ടക്കോഴി)',0),(963,251,'Gramasree (ഗ്രാമശ്രീ)',1),(964,251,'White Leghorn',2),(965,251,'Australorp',3),(966,252,'Kadaknath (കരിങ്കോഴി)',0),(967,252,'Aseel Fighter (അങ്കക്കോഴി)',1),(968,252,'Bantam',2),(969,252,'Turkey (വാൻകോഴി)',3),(970,252,'Quail (കാട)',4);
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
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
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `variant` varchar(100) DEFAULT NULL,
  `body_type` varchar(50) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `search_keywords` text DEFAULT NULL,
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
  KEY `idx_prod_cat_brand` (`category_id`,`brand`),
  KEY `idx_prod_model` (`model`),
  KEY `idx_p_body_type` (`body_type`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'ENTAGD6590',34,92,'Goats (ആട്)','Malabari / Tellicherry (തലശ്ശേരി ആട്)','Milking Nanny (കറവയുള്ളത്)',NULL,'കറവയുള്ള ആട് ','ആടും കുഞ്ഞുങ്ങളും ','goat, aadu, karavayulla aadu, milking goat, goat with kids, aadu kunju, livestock, pet goat, dairy goat, bakri, bakri aur bachche, milking aadu, kerala goat, local goat, aadu sale, goat for sale, milking aadu with kids, nanny goat, milk, domestic animal, pets, dairy, ആട്, കറവയുള്ള ആട്, കറവയുള്ള',25000.00,'buy',NULL,'919947197604','919947197604','Kodikulam',9.94755900,76.76773650,'active',NULL,1,0,1,7,'2026-10-25 15:26:41','2026-10-26 14:31:30'),(2,'ENTAGD6775',36,28,'Royal Enfield','Hunter 350','Dapper White/Ash/Grey','Cruiser','Hunter 350','Total KM : 29,000\r\nBrand New Tyres\r\n','hunter 350, hero hunter 350, hero bike, motorcycle, bike, motorbike, two wheeler, 350cc bike, hero 350, hero hunter, hero bike for sale, used bike, hero motorcycle, hero 350cc, hero hunter 350 price, hero bike kerala, hero bike entangadi, hero bike used, hero bike second hand, hero bike 29000 km, hunter, 350',150000.00,'sell',NULL,'919946020724','919946020724','Kodikulam ',9.95140510,76.63070580,'active',NULL,0,0,1,4,'2026-10-25 16:02:56','2026-10-26 15:26:45');
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
-- Table structure for table `site_visits`
--

DROP TABLE IF EXISTS `site_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_visits`
--

LOCK TABLES `site_visits` WRITE;
/*!40000 ALTER TABLE `site_visits` DISABLE KEYS */;
INSERT INTO `site_visits` VALUES (1,'2026-10-26','::1',NULL,'/enteangadi/','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 07:37:53'),(2,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php','curl/8.21.0','2026-10-26 07:37:59'),(3,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php','curl/8.21.0','2026-10-26 07:38:05'),(4,'2026-10-26','::1',NULL,'/Enteangadi/index.php','curl/8.21.0','2026-10-26 07:38:17'),(5,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php','curl/8.21.0','2026-10-26 07:38:17'),(6,'2026-10-26','::1',NULL,'/Enteangadi/index.php','curl/8.21.0','2026-10-26 07:38:23'),(7,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php','curl/8.21.0','2026-10-26 07:38:23'),(8,'2026-10-26','::1',NULL,'/Enteangadi/login.php','curl/8.21.0','2026-10-26 07:38:30'),(9,'2026-10-26','::1',NULL,'/Enteangadi/forgot_password.php','curl/8.21.0','2026-10-26 07:38:30'),(10,'2026-10-26','::1',NULL,'/Enteangadi/product.php?id=10','curl/8.21.0','2026-10-26 07:38:30'),(11,'2026-10-26','::1',1,'/enteangadi/','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 07:57:50'),(12,'2026-10-26','::1',1,'/enteangadi/guest/index.php','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:01:54'),(13,'2026-10-26','::1',1,'/enteangadi/guest/index.php?post_type=sell','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:02:12'),(14,'2026-10-26','::1',1,'/enteangadi/guest/index.php?post_type=rent','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:02:15'),(15,'2026-10-26','::1',1,'/enteangadi/guest/index.php?post_type=buy','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:02:18'),(16,'2026-10-26','::1',1,'/enteangadi/guest/index.php?','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:02:21'),(17,'2026-10-26','::1',1,'/enteangadi/guest/index.php?','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:05:11'),(18,'2026-10-26','::1',1,'/enteangadi/guest/index.php?','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:05:14'),(19,'2026-10-26','::1',1,'/enteangadi/guest/index.php?','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 08:06:48'),(20,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php','curl/8.21.0','2026-10-26 08:13:03'),(21,'2026-10-26','::1',NULL,'/Enteangadi/product.php?id=1','curl/8.21.0','2026-10-26 08:13:14'),(22,'2026-10-26','::1',NULL,'/Enteangadi/product.php?id=2','curl/8.21.0','2026-10-26 08:13:27'),(23,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php','curl/8.21.0','2026-10-26 08:14:46'),(24,'2026-10-26','::1',NULL,'/Enteangadi/product.php?id=1','curl/8.21.0','2026-10-26 08:14:47'),(25,'2026-10-26','::1',NULL,'/Enteangadi/product.php?id=2','curl/8.21.0','2026-10-26 08:14:47'),(26,'2026-10-26','::1',NULL,'/Enteangadi/login.php','curl/8.21.0','2026-10-26 08:14:47'),(27,'2026-10-26','::1',1,'/enteangadi/guest/index.php?','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 09:02:10'),(28,'2026-10-26','::1',1,'/enteangadi/guest/index.php?','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 09:47:35'),(29,'2026-10-26','::1',1,'/enteangadi/guest/index.php?','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 10:19:57'),(30,'2026-10-26','::1',NULL,'/enteangadi/','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 11:18:50'),(31,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?loc_set=1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 11:18:55'),(32,'2026-10-26','::1',1,'/enteangadi/','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 13:42:08'),(33,'2026-10-26','::1',1,'/enteangadi/index.php?search=goat','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 13:42:21'),(34,'2026-10-26','::1',1,'/enteangadi/guest/index.php?search=goat','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:06:29'),(35,'2026-10-26','::1',1,'/enteangadi/index.php?search=goat','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:06:32'),(36,'2026-10-26','::1',1,'/enteangadi/index.php?search=milk','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:06:39'),(37,'2026-10-26','::1',1,'/enteangadi/index.php?search=bullet','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:06:46'),(38,'2026-10-26','::1',1,'/enteangadi/index.php?search=bike','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:06:52'),(39,'2026-10-26','::1',1,'/enteangadi/index.php?search=two+wheeler','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:07:01'),(40,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=80','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:07:35'),(41,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=80','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:23:10'),(42,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=9','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:23:13'),(43,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=70','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:23:31'),(44,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=64','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:23:34'),(45,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=39','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:23:38'),(46,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=11','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:23:43'),(47,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=130','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:23:49'),(48,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=12','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:24:02'),(49,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=12&spec=Samsung','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:24:08'),(50,'2026-10-26','::1',1,'/enteangadi/guest/index.php?category_id=12','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:24:29'),(51,'2026-10-26','::1',1,'/enteangadi/guest/index.php','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:24:33'),(52,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?loc_set=1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:56:22'),(53,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=11','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:56:30'),(54,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=129','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:56:34'),(55,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=129&brand=Samsung','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:56:38'),(56,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=129&brand=Samsung&model=Galaxy+M+%26+F+Series','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:56:56'),(57,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=129&brand=Samsung&model=Galaxy+S+Series','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:57:08'),(58,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=129&brand=Samsung&model=Galaxy+Z+Fold+%26+Flip','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:57:20'),(59,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=25','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 14:57:31'),(60,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=25&brand=Honda','Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Mobile Safari/537.36','2026-10-26 14:57:58'),(61,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=25&brand=Honda&model=City','Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Mobile Safari/537.36','2026-10-26 14:58:32'),(62,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25','','2026-10-26 14:59:31'),(63,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25&brand=Maruti+Suzuki','','2026-10-26 14:59:31'),(64,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25&brand=Maruti+Suzuki&model=Fronx','','2026-10-26 14:59:31'),(65,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=25&brand=Honda&model=City','Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Mobile Safari/537.36','2026-10-26 15:00:27'),(66,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25','','2026-10-26 15:11:31'),(67,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25&brand=Honda','','2026-10-26 15:11:31'),(68,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25&brand=Honda&model=City','','2026-10-26 15:11:32'),(69,'2026-10-26','::1',NULL,'/Enteangadi/user/index.php?category_id=25','','2026-10-26 15:11:49'),(70,'2026-10-26','::1',NULL,'/Enteangadi/user/index.php?category_id=25&brand=Honda','','2026-10-26 15:11:49'),(71,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 15:13:07'),(72,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 15:16:03'),(73,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=26','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 15:16:14'),(74,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 15:17:22'),(75,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=26','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 15:17:27'),(76,'2026-10-26','::1',NULL,'/Enteangadi/guest/index.php?category_id=25&brand=Maruti+Suzuki&model=Fronx','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 15:45:27'),(77,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=25&brand=Honda&model=City','Mozilla/5.0 (Linux; Android 16; Pixel 10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Mobile Safari/537.36','2026-10-26 16:12:08'),(78,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php?category_id=25','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','2026-10-26 16:12:25'),(79,'2026-10-26','::1',NULL,'/enteangadi/guest/index.php','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','2026-10-26 16:13:21'),(80,'2026-10-26','::1',1,'/enteangadi/','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-26 16:16:35');
/*!40000 ALTER TABLE `site_visits` ENABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_ratings`
--

LOCK TABLES `user_ratings` WRITE;
/*!40000 ALTER TABLE `user_ratings` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@enteangadi.com','1234567890',NULL,NULL,'$2y$10$okTxdPTK8WSnkiiMsZjWRu6UjrGf18yHR3zYMcZKBkGfG9MFDHyne','admin',1,'*',NULL,NULL,'2026-05-29 05:07:16',0,0,0,0,0),(34,'Joseph P L','joseph@enteangadi.com','919947197604','919947197604','uploads/profiles/6adee4b409ebd.jpg','$2y$10$6eyw/ZyXFdbDviIBFim0p.93dfDoM2n03GSiuZZSxkU05MKaVHD/m','user',0,NULL,NULL,NULL,'2026-10-25 14:30:00',0,0,0,0,0),(36,'Dijo J Perumaly','dijo2@gmail.com','919946020724','919946020724',NULL,'$2y$10$6eyw/ZyXFdbDviIBFim0p.93dfDoM2n03GSiuZZSxkU05MKaVHD/m','user',0,NULL,NULL,NULL,'2026-10-25 14:30:00',0,0,0,0,0);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

--
-- Dumping routines for database 'enteangadi'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-26 21:55:24
