-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: text_labeling_system
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('login','logout','create','update','delete','upload','assign','complete','review','approve','reject') NOT NULL,
  `entity_type` enum('document','task','user','summary','review','upload','system') DEFAULT 'system',
  `entity_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_entity_type` (`entity_type`),
  KEY `idx_entity_id` (`entity_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_summaries`
--

DROP TABLE IF EXISTS `ai_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_summaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `summary` longtext NOT NULL,
  `summary_type` enum('ai_generated','human_edited','mixed') DEFAULT 'ai_generated',
  `word_count` int(11) DEFAULT 0,
  `char_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_summary_type` (`summary_type`),
  KEY `idx_word_count` (`word_count`),
  FULLTEXT KEY `idx_summary` (`summary`),
  CONSTRAINT `ai_summaries_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_summaries_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `CONSTRAINT_1` CHECK (`document_id` is not null and `group_id` is null or `document_id` is null and `group_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_summaries`
--

LOCK TABLES `ai_summaries` WRITE;
/*!40000 ALTER TABLE `ai_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_summaries` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER IF NOT EXISTS ai_summaries_word_count_insert
BEFORE INSERT ON ai_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.summary) - LENGTH(REPLACE(NEW.summary, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.summary);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER IF NOT EXISTS ai_summaries_word_count_update
BEFORE UPDATE ON ai_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.summary) - LENGTH(REPLACE(NEW.summary, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.summary);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `backup_documents`
--

DROP TABLE IF EXISTS `backup_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_documents` (
  `id` int(11) NOT NULL DEFAULT 0,
  `title` varchar(500) NOT NULL,
  `content` longtext NOT NULL,
  `type` enum('single','multi') NOT NULL DEFAULT 'single',
  `group_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_auto_generated_title` tinyint(1) DEFAULT 0,
  `word_count` int(11) DEFAULT 0,
  `char_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_documents`
--

LOCK TABLES `backup_documents` WRITE;
/*!40000 ALTER TABLE `backup_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_labeling_tasks`
--

DROP TABLE IF EXISTS `backup_labeling_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_labeling_tasks` (
  `id` int(11) NOT NULL DEFAULT 0,
  `document_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','reviewed','rejected') DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `deadline` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `completion_percentage` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_labeling_tasks`
--

LOCK TABLES `backup_labeling_tasks` WRITE;
/*!40000 ALTER TABLE `backup_labeling_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_labeling_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_users`
--

DROP TABLE IF EXISTS `backup_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_users` (
  `id` int(11) NOT NULL DEFAULT 0,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','labeler','reviewer') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_users`
--

LOCK TABLES `backup_users` WRITE;
/*!40000 ALTER TABLE `backup_users` DISABLE KEYS */;
INSERT INTO `backup_users` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator','admin@textlabeling.local','admin','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(2,'labeler1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Labeler One','labeler1@textlabeling.local','labeler','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(3,'labeler2','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Labeler Two','labeler2@textlabeling.local','labeler','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(4,'reviewer1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Reviewer One','reviewer1@textlabeling.local','reviewer','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(5,'reviewer2','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Reviewer Two','reviewer2@textlabeling.local','reviewer','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL);
/*!40000 ALTER TABLE `backup_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_group_items`
--

DROP TABLE IF EXISTS `document_group_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_group_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_document` (`group_id`,`document_id`),
  KEY `fk_group_items_group` (`group_id`),
  KEY `fk_group_items_document` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_group_items`
--

LOCK TABLES `document_group_items` WRITE;
/*!40000 ALTER TABLE `document_group_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_group_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_groups`
--

DROP TABLE IF EXISTS `document_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_auto_generated_title` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_auto_generated` (`is_auto_generated_title`),
  FULLTEXT KEY `idx_title_description` (`title`,`description`),
  CONSTRAINT `document_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_groups`
--

LOCK TABLES `document_groups` WRITE;
/*!40000 ALTER TABLE `document_groups` DISABLE KEYS */;
INSERT INTO `document_groups` VALUES (17,'Blockchain Công nghệ Blockchain và Cryptocurrency','Blockchain Công nghệ Blockchain và Cryptocurrency','Tổng quan về công nghệ blockchain và ứng dụng trong tiền điện tử',1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0),(18,'Blockchain Cách mạng công nghiệp 4.0','Blockchain Cách mạng công nghiệp 4.0','Tổng quan về cuộc cách mạng công nghiệp thứ 4 và các công nghệ liên quan',1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0),(19,'Blockchain Công nghệ Blockchain và Cryptocurrency','Blockchain Công nghệ Blockchain và Cryptocurrency','Tổng quan về công nghệ blockchain và ứng dụng trong tiền điện tử',1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0),(20,'Blockchain Công nghệ Blockchain và Cryptocurrency','Blockchain Công nghệ Blockchain và Cryptocurrency','Tổng quan về công nghệ blockchain và ứng dụng trong tiền điện tử',1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0),(22,'Lập trình Web hiện đại','Lập trình Web hiện đại','Tổng quan về các công nghệ web frontend và backend',1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0),(23,'Data Science và Analytics','Data Science và Analytics','Khám phá về khoa học dữ liệu và phân tích dữ liệu',1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0);
/*!40000 ALTER TABLE `document_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(500) NOT NULL,
  `content` longtext NOT NULL,
  `ai_summary` text DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT 1,
  `type` enum('single','multi') NOT NULL DEFAULT 'single',
  `group_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_auto_generated_title` tinyint(1) DEFAULT 0,
  `word_count` int(11) DEFAULT 0,
  `char_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_group_id` (`group_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_auto_generated` (`is_auto_generated_title`),
  KEY `idx_word_count` (`word_count`),
  FULLTEXT KEY `idx_content` (`content`),
  FULLTEXT KEY `idx_title_content` (`title`,`content`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (62,'Blockchain là gì?','Blockchain là một cơ sở dữ liệu phân tán được duy trì bởi một mạng lưới các máy tính. Mỗi khối chứa thông tin giao dịch và được liên kết với khối trước đó thông qua mã hash. Điều này tạo ra một chuỗi không thể thay đổi và minh bạch. Công nghệ blockchain đảm bảo tính bảo mật và loại bỏ nhu cầu có một trung gian tin cậy.','Tóm tắt cho \'Blockchain là gì?\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',17,1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0,70,421),(63,'Bitcoin - Đồng tiền điện tử đầu tiên','Bitcoin được tạo ra vào năm 2009 bởi Satoshi Nakamoto như một hệ thống thanh toán peer-to-peer. Bitcoin sử dụng công nghệ blockchain để ghi nhận các giao dịch mà không cần ngân hàng trung ương. Giá trị của Bitcoin được xác định bởi cung và cầu trên thị trường, và nó đã trở thành một tài sản đầu tư phổ biến.','Tóm tắt cho \'Bitcoin - Đồng tiền điện tử đầu tiên\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',17,1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0,62,393),(64,'Ethereum và Smart Contracts','Ethereum là một nền tảng blockchain cho phép tạo ra các smart contracts - những hợp đồng tự thực hiện khi đáp ứng điều kiện định sẵn. Điều này mở ra nhiều khả năng ứng dụng mới như DeFi (tài chính phi tập trung), NFT và các ứng dụng phi tập trung (DApps). Ethereum có đồng tiền riêng gọi là Ether (ETH).','Tóm tắt cho \'Ethereum và Smart Contracts\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',17,1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0,62,381),(65,'Phát triển bền vững','Phát triển bền vững là mô hình phát triển đáp ứng nhu cầu hiện tại mà không làm tổn hại đến khả năng đáp ứng nhu cầu của các thế hệ tương lai. Điều này đòi hỏi sự cân bằng giữa tăng trưởng kinh tế, bảo vệ môi trường và công bằng xã hội. Các mục tiêu phát triển bền vững của Liên Hợp Quốc bao gồm 17 mục tiêu cụ thể để đạt được một thế giới tốt đẹp hơn vào năm 2030.','Phát triển bền vững cân bằng giữa kinh tế, môi trường và xã hội, nhằm đáp ứng nhu cầu hiện tại mà không ảnh hưởng đến thế hệ tương lai, theo 17 mục tiêu của LHQ.',1,'single',NULL,1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0,84,503),(66,'Internet of Things (IoT)','Internet of Things là mạng lưới các thiết bị vật lý được kết nối internet và có khả năng thu thập, chia sẻ dữ liệu. Từ điện thoại thông minh đến xe hơi, từ thiết bị gia dụng đến cảm biến công nghiệp, tất cả đều có thể kết nối và trao đổi thông tin. IoT tạo ra những cơ hội mới cho việc tối ưu hóa hoạt động và cải thiện chất lượng cuộc sống.','Tóm tắt cho \'Internet of Things (IoT)\': Cách mạng công nghiệp 4.0 được đặc trưng bởi sự tích hợp của AI, IoT và tự động hóa trong sản xuất. IoT kết nối các thiết bị thông minh, AI tối ưu hóa q...',1,'multi',18,1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0,76,459),(67,'Trí tuệ nhân tạo trong sản xuất','AI đang được ứng dụng rộng rãi trong sản xuất để tối ưu hóa quy trình, dự đoán bảo trì và kiểm soát chất lượng. Machine learning giúp phân tích dữ liệu từ các cảm biến để phát hiện sớm các vấn đề tiềm ẩn. Computer vision được sử dụng để kiểm tra chất lượng sản phẩm một cách tự động và chính xác hơn con người.','Tóm tắt cho \'Trí tuệ nhân tạo trong sản xuất\': Cách mạng công nghiệp 4.0 được đặc trưng bởi sự tích hợp của AI, IoT và tự động hóa trong sản xuất. IoT kết nối các thiết bị thông minh, AI tối ưu hóa q...',1,'multi',18,1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0,67,419),(68,'Tự động hóa và Robot','Tự động hóa và robot đang thay đổi cách thức sản xuất truyền thống. Các robot công nghiệp có thể làm việc 24/7 với độ chính xác cao và ít lỗi. Collaborative robots (cobots) được thiết kế để làm việc cùng con người, kết hợp sức mạnh của máy móc với sự linh hoạt của con người. Điều này giúp tăng năng suất nhưng cũng đòi hỏi người lao động phải nâng cao kỹ năng.','Tóm tắt cho \'Tự động hóa và Robot\': Cách mạng công nghiệp 4.0 được đặc trưng bởi sự tích hợp của AI, IoT và tự động hóa trong sản xuất. IoT kết nối các thiết bị thông minh, AI tối ưu hóa q...',1,'multi',18,1,'2025-09-24 00:38:31','2025-09-24 00:38:31',0,75,472),(69,'Blockchain là gì?','Blockchain là một cơ sở dữ liệu phân tán được duy trì bởi một mạng lưới các máy tính. Mỗi khối chứa thông tin giao dịch và được liên kết với khối trước đó thông qua mã hash. Điều này tạo ra một chuỗi không thể thay đổi và minh bạch. Công nghệ blockchain đảm bảo tính bảo mật và loại bỏ nhu cầu có một trung gian tin cậy.','Tóm tắt cho \'Blockchain là gì?\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',19,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,70,421),(70,'Bitcoin - Đồng tiền điện tử đầu tiên','Bitcoin được tạo ra vào năm 2009 bởi Satoshi Nakamoto như một hệ thống thanh toán peer-to-peer. Bitcoin sử dụng công nghệ blockchain để ghi nhận các giao dịch mà không cần ngân hàng trung ương. Giá trị của Bitcoin được xác định bởi cung và cầu trên thị trường, và nó đã trở thành một tài sản đầu tư phổ biến.','Tóm tắt cho \'Bitcoin - Đồng tiền điện tử đầu tiên\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',19,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,62,393),(71,'Ethereum và Smart Contracts','Ethereum là một nền tảng blockchain cho phép tạo ra các smart contracts - những hợp đồng tự thực hiện khi đáp ứng điều kiện định sẵn. Điều này mở ra nhiều khả năng ứng dụng mới như DeFi (tài chính phi tập trung), NFT và các ứng dụng phi tập trung (DApps). Ethereum có đồng tiền riêng gọi là Ether (ETH).','Tóm tắt cho \'Ethereum và Smart Contracts\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',19,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,62,381),(72,'Blockchain là gì?','Blockchain là một cơ sở dữ liệu phân tán được duy trì bởi một mạng lưới các máy tính. Mỗi khối chứa thông tin giao dịch và được liên kết với khối trước đó thông qua mã hash. Điều này tạo ra một chuỗi không thể thay đổi và minh bạch. Công nghệ blockchain đảm bảo tính bảo mật và loại bỏ nhu cầu có một trung gian tin cậy.','Tóm tắt cho \'Blockchain là gì?\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',20,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,70,421),(73,'Bitcoin - Đồng tiền điện tử đầu tiên','Bitcoin được tạo ra vào năm 2009 bởi Satoshi Nakamoto như một hệ thống thanh toán peer-to-peer. Bitcoin sử dụng công nghệ blockchain để ghi nhận các giao dịch mà không cần ngân hàng trung ương. Giá trị của Bitcoin được xác định bởi cung và cầu trên thị trường, và nó đã trở thành một tài sản đầu tư phổ biến.','Tóm tắt cho \'Bitcoin - Đồng tiền điện tử đầu tiên\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',20,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,62,393),(74,'Ethereum và Smart Contracts','Ethereum là một nền tảng blockchain cho phép tạo ra các smart contracts - những hợp đồng tự thực hiện khi đáp ứng điều kiện định sẵn. Điều này mở ra nhiều khả năng ứng dụng mới như DeFi (tài chính phi tập trung), NFT và các ứng dụng phi tập trung (DApps). Ethereum có đồng tiền riêng gọi là Ether (ETH).','Tóm tắt cho \'Ethereum và Smart Contracts\': Blockchain là công nghệ cơ sở cho cryptocurrency, mang lại tính minh bạch và bảo mật. Bitcoin là ứng dụng đầu tiên và nổi tiếng nhất, trong khi Ethereum mở rộng kh...',1,'multi',20,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,62,381),(75,'Giới thiệu về Machine Learning','Machine Learning là một nhánh của trí tuệ nhân tạo (AI) cho phép máy tính học và cải thiện từ kinh nghiệm mà không cần được lập trình cụ thể. Các thuật toán ML có thể nhận dạng patterns trong dữ liệu và đưa ra dự đoán hoặc quyết định. Có ba loại chính: supervised learning, unsupervised learning và reinforcement learning.','Machine Learning là nhánh AI giúp máy tính tự học từ dữ liệu, bao gồm 3 loại chính: supervised, unsupervised và reinforcement learning.',1,'single',NULL,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,61,397),(76,'Giới thiệu về Cloud Computing','Cloud Computing là mô hình cung cấp dịch vụ máy tính qua internet, bao gồm servers, storage, databases, networking, software và analytics. Thay vì sở hữu và duy trì cơ sở hạ tầng IT vật lý, các doanh nghiệp có thể thuê quyền truy cập vào các dịch vụ này từ cloud providers như AWS, Azure, Google Cloud.','Cloud Computing cung cấp dịch vụ máy tính qua internet, giúp doanh nghiệp tiết kiệm chi phí và tăng tính linh hoạt thay vì đầu tư hạ tầng IT riêng.',1,'single',NULL,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,58,354),(77,'Frontend Development với React','React là thư viện JavaScript phổ biến để xây dựng user interfaces, đặc biệt cho single-page applications. React sử dụng component-based architecture, virtual DOM và state management để tạo ra các ứng dụng web tương tác và hiệu quả. Ecosystem React bao gồm Redux cho state management, React Router cho navigation và nhiều thư viện khác.','Tóm tắt cho \'Frontend Development với React\': Lập trình web hiện đại sử dụng nhiều công nghệ tiên tiến. Frontend với React/Vue tạo giao diện tương tác, backend với Node.js/Python xử lý logic nghiệp vụ, và da...',1,'multi',22,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,56,386),(78,'Backend Development và APIs','Backend development tập trung vào server-side logic, databases và APIs. Các ngôn ngữ phổ biến bao gồm Node.js, Python (Django/Flask), Java (Spring), C# (.NET). RESTful APIs và GraphQL là hai cách chính để frontend giao tiếp với backend. Database có thể là SQL (MySQL, PostgreSQL) hoặc NoSQL (MongoDB, Redis).','Tóm tắt cho \'Backend Development và APIs\': Lập trình web hiện đại sử dụng nhiều công nghệ tiên tiến. Frontend với React/Vue tạo giao diện tương tác, backend với Node.js/Python xử lý logic nghiệp vụ, và da...',1,'multi',22,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,49,339),(79,'DevOps và Deployment','DevOps kết hợp development và operations để cải thiện collaboration và productivity. Các công cụ như Git cho version control, Docker cho containerization, Kubernetes cho orchestration, và CI/CD pipelines cho automated testing và deployment. Cloud platforms như AWS, Azure giúp scale ứng dụng và monitoring performance.','Tóm tắt cho \'DevOps và Deployment\': Lập trình web hiện đại sử dụng nhiều công nghệ tiên tiến. Frontend với React/Vue tạo giao diện tương tác, backend với Node.js/Python xử lý logic nghiệp vụ, và da...',1,'multi',22,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,46,345),(80,'Cybersecurity cơ bản','Cybersecurity là việc bảo vệ hệ thống, networks và dữ liệu khỏi các cuộc tấn công mạng. Các nguy cơ phổ biến bao gồm malware, phishing, ransomware và data breaches. Các biện pháp bảo mật cơ bản gồm: sử dụng passwords mạnh, two-factor authentication, regular software updates, firewall và antivirus protection.','Cybersecurity bảo vệ hệ thống khỏi tấn công mạng thông qua password mạnh, 2FA, cập nhật phần mềm và các biện pháp bảo mật khác.',1,'single',NULL,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,51,364),(81,'Data Collection và Preprocessing','Data collection là bước đầu tiên trong data science pipeline. Dữ liệu có thể đến từ databases, APIs, web scraping, surveys hoặc IoT sensors. Data preprocessing bao gồm data cleaning (xử lý missing values, outliers), data transformation (normalization, encoding) và feature engineering để chuẩn bị dữ liệu cho analysis.','Tóm tắt cho \'Data Collection và Preprocessing\': Data Science kết hợp statistics, programming và domain knowledge để extract insights từ dữ liệu. Quy trình bao gồm data collection, cleaning, analysis và visualization. Python/R là ...',1,'multi',23,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,49,357),(82,'Exploratory Data Analysis','EDA là quá trình khám phá và hiểu dữ liệu thông qua statistical analysis và visualization. Các techniques bao gồm descriptive statistics, correlation analysis, distribution plots và hypothesis testing. Tools như matplotlib, seaborn (Python) hoặc ggplot2 (R) giúp tạo charts và graphs để identify patterns và insights.','Tóm tắt cho \'Exploratory Data Analysis\': Data Science kết hợp statistics, programming và domain knowledge để extract insights từ dữ liệu. Quy trình bao gồm data collection, cleaning, analysis và visualization. Python/R là ...',1,'multi',23,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,47,346),(83,'Machine Learning cho Data Science','ML algorithms giúp tự động hóa việc tìm patterns trong dữ liệu và đưa ra predictions. Supervised learning cho classification/regression, unsupervised learning cho clustering/dimensionality reduction. Model evaluation sử dụng metrics như accuracy, precision, recall. Feature selection và hyperparameter tuning giúp cải thiện model performance.','Tóm tắt cho \'Machine Learning cho Data Science\': Data Science kết hợp statistics, programming và domain knowledge để extract insights từ dữ liệu. Quy trình bao gồm data collection, cleaning, analysis và visualization. Python/R là ...',1,'multi',23,1,'2025-09-24 00:38:32','2025-09-24 00:38:32',0,44,370);
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER IF NOT EXISTS documents_word_count_insert
BEFORE INSERT ON documents
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.content) - LENGTH(REPLACE(NEW.content, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.content);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER IF NOT EXISTS documents_word_count_update
BEFORE UPDATE ON documents
FOR EACH ROW
BEGIN
    SET NEW.word_count = (LENGTH(NEW.content) - LENGTH(REPLACE(NEW.content, ' ', '')) + 1);
    SET NEW.char_count = LENGTH(NEW.content);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `edited_summaries`
--

DROP TABLE IF EXISTS `edited_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `edited_summaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `original_summary` text NOT NULL,
  `edited_summary` text NOT NULL,
  `edit_reason` text DEFAULT NULL,
  `quality_score` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_summaries_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `edited_summaries`
--

LOCK TABLES `edited_summaries` WRITE;
/*!40000 ALTER TABLE `edited_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `edited_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labeling_tasks`
--

DROP TABLE IF EXISTS `labeling_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `labeling_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) NOT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','reviewed','rejected') DEFAULT 'pending',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `deadline` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `completion_percentage` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  KEY `group_id` (`group_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_assigned_by` (`assigned_by`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_assigned_at` (`assigned_at`),
  KEY `idx_deadline` (`deadline`),
  KEY `idx_completion` (`completion_percentage`),
  CONSTRAINT `labeling_tasks_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `labeling_tasks_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `document_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `labeling_tasks_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `labeling_tasks_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `CONSTRAINT_1` CHECK (`document_id` is not null and `group_id` is null or `document_id` is null and `group_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labeling_tasks`
--

LOCK TABLES `labeling_tasks` WRITE;
/*!40000 ALTER TABLE `labeling_tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `labeling_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `performance_metrics`
--

DROP TABLE IF EXISTS `performance_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `metric_type` enum('task_completion_time','accuracy_rate','productivity','quality_score') NOT NULL,
  `metric_value` decimal(10,4) NOT NULL,
  `metric_date` date NOT NULL,
  `additional_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_metric_type` (`metric_type`),
  KEY `idx_metric_date` (`metric_date`),
  KEY `idx_metric_value` (`metric_value`),
  CONSTRAINT `performance_metrics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `performance_metrics`
--

LOCK TABLES `performance_metrics` WRITE;
/*!40000 ALTER TABLE `performance_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `performance_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `rating` enum('excellent','good','fair','poor','needs_revision') DEFAULT 'good',
  `quality_score` decimal(3,2) DEFAULT NULL,
  `accuracy_score` decimal(3,2) DEFAULT NULL,
  `completeness_score` decimal(3,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `review_time_minutes` int(11) DEFAULT NULL,
  `review_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_reviewer` (`task_id`,`reviewer_id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_reviewer_id` (`reviewer_id`),
  KEY `idx_rating` (`rating`),
  KEY `idx_approved` (`approved`),
  KEY `idx_quality_score` (`quality_score`),
  KEY `idx_review_date` (`review_date`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sentence_selections`
--

DROP TABLE IF EXISTS `sentence_selections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sentence_selections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `sentence_text` longtext NOT NULL,
  `sentence_order` int(11) NOT NULL,
  `sentence_start_pos` int(11) DEFAULT 0,
  `sentence_end_pos` int(11) DEFAULT 0,
  `is_selected` tinyint(1) DEFAULT 0,
  `importance_level` enum('low','medium','high','critical') DEFAULT 'medium',
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `user_comment` text DEFAULT NULL,
  `selection_timestamp` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_task_doc_sentence` (`task_id`,`document_id`,`sentence_order`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_document_id` (`document_id`),
  KEY `idx_is_selected` (`is_selected`),
  KEY `idx_importance_level` (`importance_level`),
  KEY `idx_sentence_order` (`sentence_order`),
  KEY `idx_confidence_score` (`confidence_score`),
  CONSTRAINT `sentence_selections_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sentence_selections_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sentence_selections`
--

LOCK TABLES `sentence_selections` WRITE;
/*!40000 ALTER TABLE `sentence_selections` DISABLE KEYS */;
/*!40000 ALTER TABLE `sentence_selections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','float','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`),
  KEY `idx_is_public` (`is_public`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'system_name','Text Labeling System','string','Name of the system',1,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(2,'max_upload_size','50','integer','Maximum upload file size in MB',0,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(3,'auto_assign_tasks','false','boolean','Automatically assign tasks to available labelers',0,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(4,'task_deadline_days','7','integer','Default deadline for tasks in days',0,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(5,'require_review','true','boolean','Require review for completed tasks',0,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(6,'min_sentence_length','10','integer','Minimum sentence length for selection',0,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(7,'max_sentence_length','500','integer','Maximum sentence length for selection',0,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(8,'auto_generate_titles','true','boolean','Enable automatic title generation for JSONL uploads',0,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(9,'enable_drag_drop_upload','true','boolean','Enable drag and drop file uploads',1,'2025-09-23 15:41:27','2025-09-23 15:41:27'),(10,'system_version','1.2.0','string','Current system version',1,'2025-09-23 15:41:27','2025-09-23 15:41:27');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `text_styles`
--

DROP TABLE IF EXISTS `text_styles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `text_styles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `style_type` enum('formal','informal','technical','academic','journalistic','conversational','legal','medical') NOT NULL,
  `confidence_level` enum('low','medium','high','very_high') DEFAULT 'medium',
  `style_score` decimal(3,2) DEFAULT NULL,
  `detected_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detected_features`)),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_style_type` (`style_type`),
  KEY `idx_confidence_level` (`confidence_level`),
  KEY `idx_style_score` (`style_score`),
  CONSTRAINT `text_styles_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `text_styles`
--

LOCK TABLES `text_styles` WRITE;
/*!40000 ALTER TABLE `text_styles` DISABLE KEYS */;
/*!40000 ALTER TABLE `text_styles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `upload_logs`
--

DROP TABLE IF EXISTS `upload_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `upload_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uploaded_by` int(11) NOT NULL,
  `file_name` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `file_type` enum('jsonl','txt','docx','manual','other') DEFAULT 'other',
  `records_processed` int(11) DEFAULT 0,
  `records_success` int(11) DEFAULT 0,
  `records_failed` int(11) DEFAULT 0,
  `auto_generated_titles` int(11) DEFAULT 0,
  `upload_type` enum('single','multi','mixed','manual') DEFAULT 'mixed',
  `status` enum('processing','completed','failed','partially_failed') DEFAULT 'processing',
  `error_details` longtext DEFAULT NULL,
  `warning_details` longtext DEFAULT NULL,
  `processing_time_seconds` int(11) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_file_type` (`file_type`),
  KEY `idx_upload_type` (`upload_type`),
  KEY `idx_status` (`status`),
  KEY `idx_upload_date` (`upload_date`),
  KEY `idx_processing_time` (`processing_time_seconds`),
  CONSTRAINT `upload_logs_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `upload_logs`
--

LOCK TABLES `upload_logs` WRITE;
/*!40000 ALTER TABLE `upload_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `upload_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_summaries`
--

DROP TABLE IF EXISTS `user_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_summaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `original_summary` longtext NOT NULL,
  `edited_summary` longtext NOT NULL,
  `edit_type` enum('minor','major','complete_rewrite','no_change') DEFAULT 'minor',
  `change_notes` text DEFAULT NULL,
  `edit_count` int(11) DEFAULT 1,
  `word_count_original` int(11) DEFAULT 0,
  `word_count_edited` int(11) DEFAULT 0,
  `similarity_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_edit_type` (`edit_type`),
  KEY `idx_edit_count` (`edit_count`),
  KEY `idx_similarity_score` (`similarity_score`),
  CONSTRAINT `user_summaries_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `labeling_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_summaries`
--

LOCK TABLES `user_summaries` WRITE;
/*!40000 ALTER TABLE `user_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_summaries` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER IF NOT EXISTS user_summaries_word_count_insert
BEFORE INSERT ON user_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count_original = (LENGTH(NEW.original_summary) - LENGTH(REPLACE(NEW.original_summary, ' ', '')) + 1);
    SET NEW.word_count_edited = (LENGTH(NEW.edited_summary) - LENGTH(REPLACE(NEW.edited_summary, ' ', '')) + 1);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER IF NOT EXISTS user_summaries_word_count_update
BEFORE UPDATE ON user_summaries
FOR EACH ROW
BEGIN
    SET NEW.word_count_original = (LENGTH(NEW.original_summary) - LENGTH(REPLACE(NEW.original_summary, ' ', '')) + 1);
    SET NEW.word_count_edited = (LENGTH(NEW.edited_summary) - LENGTH(REPLACE(NEW.edited_summary, ' ', '')) + 1);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','labeler','reviewer') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator','admin@textlabeling.local','admin','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(2,'labeler1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Labeler One','labeler1@textlabeling.local','labeler','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(3,'labeler2','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Labeler Two','labeler2@textlabeling.local','labeler','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(4,'reviewer1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Reviewer One','reviewer1@textlabeling.local','reviewer','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL),(5,'reviewer2','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Reviewer Two','reviewer2@textlabeling.local','reviewer','active','2025-09-23 15:41:27','2025-09-23 15:41:27',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `view_document_summary`
--

DROP TABLE IF EXISTS `view_document_summary`;
/*!50001 DROP VIEW IF EXISTS `view_document_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `view_document_summary` AS SELECT
 1 AS `document_id`,
  1 AS `title`,
  1 AS `type`,
  1 AS `word_count`,
  1 AS `char_count`,
  1 AS `document_created`,
  1 AS `group_id`,
  1 AS `group_title`,
  1 AS `summary`,
  1 AS `summary_type`,
  1 AS `summary_word_count`,
  1 AS `created_by_username`,
  1 AS `title_source` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `view_task_progress`
--

DROP TABLE IF EXISTS `view_task_progress`;
/*!50001 DROP VIEW IF EXISTS `view_task_progress`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `view_task_progress` AS SELECT
 1 AS `task_id`,
  1 AS `status`,
  1 AS `priority`,
  1 AS `completion_percentage`,
  1 AS `assigned_at`,
  1 AS `deadline`,
  1 AS `days_remaining`,
  1 AS `assigned_to_username`,
  1 AS `assigned_to_name`,
  1 AS `assigned_by_username`,
  1 AS `document_title`,
  1 AS `document_type`,
  1 AS `group_title`,
  1 AS `hours_spent` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `view_upload_statistics`
--

DROP TABLE IF EXISTS `view_upload_statistics`;
/*!50001 DROP VIEW IF EXISTS `view_upload_statistics`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `view_upload_statistics` AS SELECT
 1 AS `id`,
  1 AS `file_name`,
  1 AS `file_type`,
  1 AS `upload_type`,
  1 AS `status`,
  1 AS `records_processed`,
  1 AS `records_success`,
  1 AS `records_failed`,
  1 AS `auto_generated_titles`,
  1 AS `processing_time_seconds`,
  1 AS `upload_date`,
  1 AS `uploaded_by_username`,
  1 AS `uploaded_by_name`,
  1 AS `success_rate_percentage` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `writing_styles`
--

DROP TABLE IF EXISTS `writing_styles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `writing_styles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL,
  `style_name` varchar(100) NOT NULL,
  `style_description` text DEFAULT NULL,
  `selected` tinyint(1) DEFAULT 0,
  `custom_style` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_styles_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `writing_styles`
--

LOCK TABLES `writing_styles` WRITE;
/*!40000 ALTER TABLE `writing_styles` DISABLE KEYS */;
/*!40000 ALTER TABLE `writing_styles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `view_document_summary`
--

/*!50001 DROP VIEW IF EXISTS `view_document_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_document_summary` AS select `d`.`id` AS `document_id`,`d`.`title` AS `title`,`d`.`type` AS `type`,`d`.`word_count` AS `word_count`,`d`.`char_count` AS `char_count`,`d`.`created_at` AS `document_created`,`dg`.`id` AS `group_id`,`dg`.`title` AS `group_title`,`ai`.`summary` AS `summary`,`ai`.`summary_type` AS `summary_type`,`ai`.`word_count` AS `summary_word_count`,`u`.`username` AS `created_by_username`,case when `d`.`is_auto_generated_title` = 1 then 'Auto-generated' else 'Manual' end AS `title_source` from (((`documents` `d` left join `document_groups` `dg` on(`d`.`group_id` = `dg`.`id`)) left join `ai_summaries` `ai` on(`d`.`id` = `ai`.`document_id` or `dg`.`id` = `ai`.`group_id`)) left join `users` `u` on(`d`.`created_by` = `u`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `view_task_progress`
--

/*!50001 DROP VIEW IF EXISTS `view_task_progress`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_task_progress` AS select `t`.`id` AS `task_id`,`t`.`status` AS `status`,`t`.`priority` AS `priority`,`t`.`completion_percentage` AS `completion_percentage`,`t`.`assigned_at` AS `assigned_at`,`t`.`deadline` AS `deadline`,to_days(`t`.`deadline`) - to_days(current_timestamp()) AS `days_remaining`,`u_assigned`.`username` AS `assigned_to_username`,`u_assigned`.`full_name` AS `assigned_to_name`,`u_creator`.`username` AS `assigned_by_username`,`d`.`title` AS `document_title`,`d`.`type` AS `document_type`,`dg`.`title` AS `group_title`,case when `t`.`status` = 'completed' then timestampdiff(HOUR,`t`.`assigned_at`,`t`.`completed_at`) else timestampdiff(HOUR,`t`.`assigned_at`,current_timestamp()) end AS `hours_spent` from ((((`labeling_tasks` `t` join `users` `u_assigned` on(`t`.`assigned_to` = `u_assigned`.`id`)) left join `users` `u_creator` on(`t`.`assigned_by` = `u_creator`.`id`)) left join `documents` `d` on(`t`.`document_id` = `d`.`id`)) left join `document_groups` `dg` on(`t`.`group_id` = `dg`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `view_upload_statistics`
--

/*!50001 DROP VIEW IF EXISTS `view_upload_statistics`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_upload_statistics` AS select `ul`.`id` AS `id`,`ul`.`file_name` AS `file_name`,`ul`.`file_type` AS `file_type`,`ul`.`upload_type` AS `upload_type`,`ul`.`status` AS `status`,`ul`.`records_processed` AS `records_processed`,`ul`.`records_success` AS `records_success`,`ul`.`records_failed` AS `records_failed`,`ul`.`auto_generated_titles` AS `auto_generated_titles`,`ul`.`processing_time_seconds` AS `processing_time_seconds`,`ul`.`upload_date` AS `upload_date`,`u`.`username` AS `uploaded_by_username`,`u`.`full_name` AS `uploaded_by_name`,round(`ul`.`records_success` / nullif(`ul`.`records_processed`,0) * 100,2) AS `success_rate_percentage` from (`upload_logs` `ul` join `users` `u` on(`ul`.`uploaded_by` = `u`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-24  7:41:44
