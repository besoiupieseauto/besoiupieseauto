-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: besoiupieseauto.ro
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `besoiupieseauto.ro`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `besoiupieseauto.ro` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `besoiupieseauto.ro`;

--
-- Table structure for table `adaos_comercial_rules`
--

DROP TABLE IF EXISTS `adaos_comercial_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adaos_comercial_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `category_filter` varchar(150) DEFAULT NULL,
  `brand_filter` varchar(150) DEFAULT NULL,
  `price_min` decimal(10,2) DEFAULT NULL,
  `price_max` decimal(10,2) DEFAULT NULL,
  `adjustment_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
  `adjustment_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `round_to` decimal(10,2) DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '100',
  `note` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_adaos_active` (`is_active`),
  KEY `idx_adaos_priority` (`priority`),
  KEY `idx_adaos_category` (`category_filter`),
  KEY `idx_adaos_brand` (`brand_filter`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_nav_items`
--

DROP TABLE IF EXISTS `admin_nav_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_nav_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `section_id` int unsigned NOT NULL,
  `parent_item_id` int unsigned DEFAULT NULL,
  `module_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` enum('link','submenu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'link',
  `label` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `icon` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT 'circle',
  `badge_key` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alert_key` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT '0',
  `open_new_tab` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `customized` tinyint(1) NOT NULL DEFAULT '0',
  `permission_feature` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` enum('core','module','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_nav_item_section_key` (`section_id`,`item_key`),
  KEY `idx_admin_nav_item_section_sort` (`section_id`,`sort_order`,`is_active`),
  KEY `idx_admin_nav_item_module` (`module_id`),
  KEY `idx_admin_nav_item_parent` (`parent_item_id`),
  CONSTRAINT `fk_admin_nav_items_section` FOREIGN KEY (`section_id`) REFERENCES `admin_nav_sections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_nav_sections`
--

DROP TABLE IF EXISTS `admin_nav_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_nav_sections` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `section_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workspace` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `source` enum('core','module','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_nav_section_key` (`section_key`),
  KEY `idx_admin_nav_section_module` (`module_id`),
  KEY `idx_admin_nav_section_sort` (`sort_order`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_intel_aggregates`
--

DROP TABLE IF EXISTS `ai_intel_aggregates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_intel_aggregates` (
  `entity_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_date` date NOT NULL,
  `views` int unsigned NOT NULL DEFAULT '0',
  `clicks` int unsigned NOT NULL DEFAULT '0',
  `purchases` int unsigned NOT NULL DEFAULT '0',
  `ctr` decimal(6,4) DEFAULT NULL COMMENT 'clicks / views when views > 0',
  PRIMARY KEY (`entity_id`,`entity_type`,`period_date`),
  KEY `idx_ai_intel_agg_date` (`period_date`),
  KEY `idx_ai_intel_agg_ctr` (`ctr`),
  KEY `idx_ai_intel_agg_views` (`views`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_intel_events`
--

DROP TABLE IF EXISTS `ai_intel_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_intel_events` (
  `event_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json NOT NULL,
  `ts` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`event_id`),
  KEY `idx_ai_intel_ev_type_ts` (`event_type`,`ts`),
  KEY `idx_ai_intel_ev_entity` (`entity_type`,`entity_id`),
  KEY `idx_ai_intel_ev_session` (`session_id`),
  KEY `idx_ai_intel_ev_ts` (`ts`),
  CONSTRAINT `fk_ai_intel_ev_session` FOREIGN KEY (`session_id`) REFERENCES `ai_intel_sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_intel_sessions`
--

DROP TABLE IF EXISTS `ai_intel_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_intel_sessions` (
  `session_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'idclienti sau cont magazin când e autentificat; NULL = anonim',
  `started_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landing_page` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` datetime(3) DEFAULT NULL,
  `event_count` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`session_id`),
  KEY `idx_ai_intel_sess_user` (`user_id`),
  KEY `idx_ai_intel_sess_started` (`started_at`),
  KEY `idx_ai_intel_sess_last_seen` (`last_seen_at`),
  KEY `idx_ai_intel_sess_country` (`country_code`),
  KEY `idx_ai_intel_sess_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_interaction_logs`
--

DROP TABLE IF EXISTS `ai_interaction_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_interaction_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `module_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'propose',
  `input_summary` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `input_json` longtext COLLATE utf8mb4_unicode_ci,
  `prompt_text` longtext COLLATE utf8mb4_unicode_ci,
  `output_raw` longtext COLLATE utf8mb4_unicode_ci,
  `output_json` longtext COLLATE utf8mb4_unicode_ci,
  `model` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `confidence` decimal(5,4) DEFAULT NULL,
  `latency_ms` int NOT NULL DEFAULT '0',
  `human_action` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'proposed',
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_log_module` (`module_id`,`created_at`),
  KEY `idx_ai_log_status` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_log_summaries`
--

DROP TABLE IF EXISTS `ai_log_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_log_summaries` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `summary_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `stats_json` longtext COLLATE utf8mb4_unicode_ci,
  `sources_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `log_id` bigint DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_summary_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_market_research_entries`
--

DROP TABLE IF EXISTS `ai_market_research_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_market_research_entries` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `entry_uuid` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tip_sursa` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_sursa` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_scraping` datetime NOT NULL,
  `continut_brut` longtext COLLATE utf8mb4_unicode_ci,
  `continut_procesat_json` longtext COLLATE utf8mb4_unicode_ci,
  `embedding_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidence_extractie` decimal(6,4) DEFAULT '0.0000',
  `status_validare` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `seo_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_market_uuid` (`entry_uuid`),
  KEY `idx_market_tip` (`tip_sursa`),
  KEY `idx_market_source` (`source_id`),
  KEY `idx_market_scraped` (`data_scraping`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ai_product_embeddings`
--

DROP TABLE IF EXISTS `ai_product_embeddings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_product_embeddings` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `source_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'produse',
  `source_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sku` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oem` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `embedding_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dims` int NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_emb_source` (`source_type`,`source_id`),
  KEY `idx_ai_emb_sku` (`sku`),
  KEY `idx_ai_emb_oem` (`oem`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alerts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=608 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_token_budgets`
--

DROP TABLE IF EXISTS `api_token_budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_token_budgets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `provider_key` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `env_key` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_quota` int unsigned NOT NULL DEFAULT '1000',
  `tokens_per_request` int unsigned NOT NULL DEFAULT '1' COMMENT 'Tokeni consuma??i per query/request',
  `remaining_override` int unsigned DEFAULT NULL COMMENT 'Tokeni r??ma??i seta??i manual (NULL = calcul automat)',
  `cost_per_unit` decimal(10,4) NOT NULL DEFAULT '0.0000' COMMENT 'RON per request/unit',
  `warning_pct` tinyint unsigned NOT NULL DEFAULT '80',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_token_budget_provider` (`provider_key`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_token_usage_log`
--

DROP TABLE IF EXISTS `api_token_usage_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_token_usage_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider_key` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `units` int unsigned NOT NULL DEFAULT '1',
  `cost_ron` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `source` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_token_usage_provider_created` (`provider_key`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=935 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(180) NOT NULL,
  `title` varchar(255) NOT NULL,
  `tag` varchar(80) DEFAULT 'Articole',
  `excerpt` text,
  `body_html` mediumtext,
  `featured_image` varchar(500) DEFAULT '',
  `is_published` tinyint(1) DEFAULT '0',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_blog_posts_slug` (`slug`),
  KEY `idx_blog_published` (`is_published`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bots`
--

DROP TABLE IF EXISTS `bots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `bot_type` varchar(60) NOT NULL DEFAULT 'message_sender',
  `channel` varchar(40) NOT NULL DEFAULT 'manual',
  `token_value` text,
  `token_status` varchar(30) NOT NULL DEFAULT 'active',
  `token_plan` varchar(20) NOT NULL DEFAULT 'free',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `requests_limit` int unsigned DEFAULT NULL,
  `requests_used` int unsigned NOT NULL DEFAULT '0',
  `webhook_url` varchar(500) DEFAULT NULL,
  `test_url` varchar(500) DEFAULT NULL,
  `last_test_status` varchar(30) DEFAULT NULL,
  `last_test_message` varchar(500) DEFAULT NULL,
  `last_test_at` datetime DEFAULT NULL,
  `notes` text,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `randomn_id` (`randomn_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cart_abandonments`
--

DROP TABLE IF EXISTS `cart_abandonments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_abandonments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `client_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cart_json` json DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `items_count` int unsigned NOT NULL DEFAULT '0',
  `checkout_step` tinyint unsigned NOT NULL DEFAULT '1',
  `status` enum('open','contacted','converted','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `last_seen_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cart_abandon_status` (`status`,`last_seen_at`),
  KEY `idx_cart_abandon_phone` (`phone`),
  KEY `idx_cart_abandon_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cart_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_customer_id` int unsigned DEFAULT NULL,
  `product_id` int unsigned NOT NULL,
  `randomn_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `product_snapshot` json DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cart_session_product` (`session_id`,`randomn_id`),
  KEY `idx_cart_session` (`session_id`),
  KEY `idx_cart_customer` (`shop_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorii`
--

DROP TABLE IF EXISTS `categorii`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorii` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT '',
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `type` enum('categorie','subcategorie','marca','model','motorizare') DEFAULT 'categorie',
  `tecdoc_id` int DEFAULT NULL,
  `meta` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8451 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorii_secundare`
--

DROP TABLE IF EXISTS `categorii_secundare`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorii_secundare` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `art_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int unsigned NOT NULL,
  `tree_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_art_category` (`art_name`,`category_id`),
  KEY `idx_art_name` (`art_name`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorii_sinonime`
--

DROP TABLE IF EXISTS `categorii_sinonime`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorii_sinonime` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `term` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_art_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_term` (`term`),
  KEY `idx_target_art_name` (`target_art_name`)
) ENGINE=InnoDB AUTO_INCREMENT=282 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clienti`
--

DROP TABLE IF EXISTS `clienti`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clienti` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `total_orders` int unsigned NOT NULL DEFAULT '0',
  `total_paid` decimal(12,2) NOT NULL DEFAULT '0.00',
  `preferred_courier` varchar(120) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `randomn_id` (`randomn_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `comenzi`
--

DROP TABLE IF EXISTS `comenzi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comenzi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned DEFAULT NULL,
  `order_number` varchar(40) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `product_image` varchar(500) DEFAULT NULL,
  `client_name` varchar(160) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `vin` varchar(40) DEFAULT NULL,
  `channel` varchar(40) NOT NULL DEFAULT 'website',
  `payment_status` varchar(40) NOT NULL DEFAULT 'ramburs',
  `payment_reference` varchar(120) DEFAULT NULL,
  `payment_status_detail` varchar(40) DEFAULT NULL,
  `invoice_randomn_id` int DEFAULT NULL,
  `livrare_randomn_id` int DEFAULT NULL,
  `delivery_method` varchar(80) DEFAULT NULL,
  `delivery_status` varchar(80) DEFAULT NULL,
  `order_status` varchar(40) NOT NULL DEFAULT 'noua',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `coupon_code` varchar(50) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` tinyint(1) DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `randomn_id` (`randomn_id`),
  KEY `idx_comenzi_created_at` (`created_at`),
  KEY `idx_comenzi_order_status` (`order_status`),
  KEY `idx_comenzi_invoice_randomn_id` (`invoice_randomn_id`),
  KEY `idx_comenzi_livrare_randomn_id` (`livrare_randomn_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `discount_type` enum('percent','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `discount_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_order` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valid_from` datetime DEFAULT NULL,
  `valid_until` datetime DEFAULT NULL,
  `max_uses` int unsigned DEFAULT NULL,
  `used_count` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coupons_code` (`code`),
  KEY `idx_coupons_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cron`
--

DROP TABLE IF EXISTS `cron`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cron` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cross_reference`
--

DROP TABLE IF EXISTS `cross_reference`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cross_reference` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=306 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `facturi`
--

DROP TABLE IF EXISTS `facturi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned DEFAULT NULL,
  `invoice_number` varchar(40) DEFAULT NULL,
  `smartbill_series` varchar(32) DEFAULT NULL,
  `smartbill_number` varchar(32) DEFAULT NULL,
  `smartbill_invoice_id` varchar(64) DEFAULT NULL,
  `order_number` varchar(40) DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `client_name` varchar(160) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `payment_method` varchar(40) NOT NULL DEFAULT 'ramburs',
  `invoice_status` varchar(40) NOT NULL DEFAULT 'neachitata',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `due_date` date DEFAULT NULL,
  `notes` text,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `randomn_id` (`randomn_id`),
  KEY `idx_facturi_order_id` (`order_id`),
  KEY `idx_facturi_smartbill_number` (`smartbill_series`,`smartbill_number`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `furnizori`
--

DROP TABLE IF EXISTS `furnizori`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `furnizori` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `price_markup_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percentage',
  `price_markup_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_round_to` decimal(10,2) DEFAULT NULL,
  `price_min_margin` decimal(10,2) DEFAULT NULL,
  `adaos_template_rule_id` int unsigned DEFAULT NULL,
  `stock_zero_mode` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full',
  `scan_include_zero_stock` tinyint(1) NOT NULL DEFAULT '1',
  `scan_skip_unavailable` tinyint(1) NOT NULL DEFAULT '0',
  `connection_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ftp',
  `ftp_access_mode` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'they',
  `scan_interval_minutes` int unsigned NOT NULL DEFAULT '60',
  `scan_schedule_mode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'interval',
  `scan_schedule_time` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '06:00',
  `scan_window_start` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '08:00',
  `scan_window_end` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '18:00',
  `scan_auto_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `conn_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conn_port` int unsigned DEFAULT NULL,
  `conn_username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conn_password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `conn_remote_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conn_passive` tinyint(1) NOT NULL DEFAULT '1',
  `conn_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conn_email_inbox` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conn_imap_host` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conn_imap_port` int unsigned DEFAULT '993',
  `conn_email_password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `api_base_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_token` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_scan_at` datetime DEFAULT NULL,
  `last_scan_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_scan_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_test_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_test_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_test_at` datetime DEFAULT NULL,
  `products_count` int unsigned NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uq_furnizori_randomn_id` (`randomn_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `garantie`
--

DROP TABLE IF EXISTS `garantie`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `garantie` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `months` int NOT NULL DEFAULT '24',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `img_bd`
--

DROP TABLE IF EXISTS `img_bd`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `img_bd` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nameImg` text,
  `data_inser` text,
  `id_users` text,
  `randomn_id` text,
  `pag` text,
  `connect_id` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `import_learning_decisions`
--

DROP TABLE IF EXISTS `import_learning_decisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_learning_decisions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `supplier` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pattern_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pattern_hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pattern_data` json NOT NULL,
  `ai_verdict` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_confidence` decimal(4,3) NOT NULL,
  `ai_reasoning` text COLLATE utf8mb4_unicode_ci,
  `operator_feedback` enum('accept','reject','modify') COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator_data` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pattern` (`pattern_type`,`pattern_hash`),
  KEY `idx_supplier_date` (`supplier`,`created_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `import_produse`
--

DROP TABLE IF EXISTS `import_produse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_produse` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pName` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pNameMarketplace` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pCode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pCodeNorm` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pBrandNorm` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pBrand` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pMarca` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pModel` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pMotorizare` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pCar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pPrice` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pBasePrice` decimal(10,2) DEFAULT NULL,
  `pStock` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pCategory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pSubcategory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pCompatibilitati` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pOem` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pSupplier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pState` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pCity` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pNote` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pNoteWebsite` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Descriere curatata pentru website',
  `pNoteMarketplace` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Descriere detaliata pentru marketplace',
  `pImages` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pImageSource` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pShipping` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pCurierLivrare` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Da' COMMENT 'Livrare curier: Da sau Nu',
  `pWarranty` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pReturn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pWhatsapp` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pMarkupRuleId` int DEFAULT NULL,
  `pMarkupRuleName` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pMarkupAppliedAt` datetime DEFAULT NULL,
  `pBadge` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pVitrina` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Afisare vitrina homepage',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `raw_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imported_product_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `pSupplierNorm` varchar(160) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (upper(trim(`pSupplier`))) STORED,
  `import_lane` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tecdoc_matched` tinyint(1) NOT NULL DEFAULT '0',
  `tecdoc_matched_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_import_pcode_status` (`pCode`(100),`status`),
  KEY `idx_import_produse_status` (`status`),
  KEY `idx_import_supplier_status` (`pSupplierNorm`,`status`,`id`),
  KEY `idx_import_lane_status` (`status`,`import_lane`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `livrare`
--

DROP TABLE IF EXISTS `livrare`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `livrare` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned DEFAULT NULL,
  `awb` varchar(80) DEFAULT NULL,
  `order_number` varchar(40) DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `client_name` varchar(160) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `courier` varchar(80) NOT NULL DEFAULT 'Fan Courier',
  `courier_provider` varchar(32) DEFAULT NULL,
  `service_type` varchar(80) DEFAULT NULL,
  `delivery_status` varchar(40) NOT NULL DEFAULT 'pregatire',
  `delivery_date` date DEFAULT NULL,
  `delivery_time` varchar(20) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text,
  `courier_response` text,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `randomn_id` (`randomn_id`),
  KEY `idx_livrare_order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_actions`
--

DROP TABLE IF EXISTS `marketing_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_actions` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_kpi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `indicator_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `priority` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'todo',
  `effort` tinyint unsigned NOT NULL DEFAULT '3',
  `impact` tinyint unsigned NOT NULL DEFAULT '3',
  `score` int NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `playbook_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `campaign_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Campanie marketing',
  `kanban_step` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mact_status` (`status`),
  KEY `idx_mact_priority` (`priority`),
  KEY `idx_mact_score` (`score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_campaigns`
--

DROP TABLE IF EXISTS `marketing_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_campaigns` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `problem_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stock' COMMENT 'stock|traffic|marketplace|lead',
  `channel` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'site' COMMENT 'site|google|olx|pieseauto|facebook|whatsapp',
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'identified' COMMENT 'identified|in_progress|published|measured|done',
  `problem_summary` text COLLATE utf8mb4_unicode_ci,
  `focus_oem` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `baseline_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `target_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `current_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `kpi_metric` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'leads' COMMENT 'visitors|traffic|leads|conversions|listings|searches',
  `impact_est_ron` decimal(14,2) NOT NULL DEFAULT '0.00',
  `playbook_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checklist_json` json DEFAULT NULL,
  `evidence_before_json` json DEFAULT NULL,
  `evidence_after_json` json DEFAULT NULL,
  `priority_score` int NOT NULL DEFAULT '0',
  `is_focus` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` int unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `measured_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mcamp_status` (`status`),
  KEY `idx_mcamp_channel` (`channel`),
  KEY `idx_mcamp_focus` (`is_focus`),
  KEY `idx_mcamp_score` (`priority_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_content`
--

DROP TABLE IF EXISTS `marketing_content`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_content` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `format` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'post',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `publish_date` date DEFAULT NULL,
  `linked_action_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linked_indicator_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci,
  `template_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `campaign_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Campanie marketing',
  `metrics_json` json DEFAULT NULL COMMENT 'hashtags, media, spend ads',
  PRIMARY KEY (`id`),
  KEY `idx_mcnt_status` (`status`),
  KEY `idx_mcnt_publish` (`publish_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_indicators`
--

DROP TABLE IF EXISTS `marketing_indicators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_indicators` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `metric` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `target` decimal(14,2) NOT NULL DEFAULT '0.00',
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `auto_key` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `linked_okr_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `category` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  PRIMARY KEY (`id`),
  KEY `idx_mind_platform` (`platform`),
  KEY `idx_mind_auto` (`auto_key`),
  KEY `idx_mind_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketing_okrs`
--

DROP TABLE IF EXISTS `marketing_okrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_okrs` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quarter_label` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `current_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `unit` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mokr_quarter` (`quarter_label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marketplace`
--

DROP TABLE IF EXISTS `marketplace`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `platform` varchar(60) NOT NULL DEFAULT 'custom',
  `account_name` varchar(255) DEFAULT NULL,
  `account_email` varchar(255) DEFAULT NULL,
  `api_token` text,
  `token_status` varchar(30) NOT NULL DEFAULT 'active',
  `token_plan` varchar(20) NOT NULL DEFAULT 'free',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `api_base_url` varchar(500) DEFAULT NULL,
  `webhook_url` varchar(500) DEFAULT NULL,
  `bl_inventory_id` int unsigned DEFAULT NULL COMMENT 'ID inventar BaseLinker',
  `field_mapping` json DEFAULT NULL COMMENT 'Mapare câmpuri Besoiu -> BaseLinker',
  `sync_mode` varchar(40) NOT NULL DEFAULT 'manual',
  `products_synced` int unsigned NOT NULL DEFAULT '0',
  `requests_today` int unsigned NOT NULL DEFAULT '0',
  `offers_sent` int unsigned NOT NULL DEFAULT '0',
  `errors_count` int unsigned NOT NULL DEFAULT '0',
  `last_sync_at` datetime DEFAULT NULL,
  `last_test_status` varchar(30) DEFAULT NULL,
  `last_test_message` varchar(500) DEFAULT NULL,
  `last_test_at` datetime DEFAULT NULL,
  `notes` text,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `randomn_id` (`randomn_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned DEFAULT NULL,
  `conversation_id` int unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(160) DEFAULT NULL,
  `message_body` text,
  `direction` varchar(20) NOT NULL DEFAULT 'inbound',
  `message_status` varchar(30) NOT NULL DEFAULT 'new',
  `channel` varchar(40) NOT NULL DEFAULT 'manual',
  `external_conversation_id` varchar(190) DEFAULT NULL,
  `external_message_id` varchar(190) DEFAULT NULL,
  `delivery_status` varchar(30) NOT NULL DEFAULT 'received',
  `bot_status` varchar(30) NOT NULL DEFAULT 'none',
  `source_url` varchar(500) DEFAULT NULL,
  `assigned_bot` varchar(120) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `randomn_id` (`randomn_id`),
  KEY `idx_messages_created_id` (`created_at`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `module_migrations`
--

DROP TABLE IF EXISTS `module_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `module_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `module_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ran_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_migration` (`module_id`,`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `randomn_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oem_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `line_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  KEY `idx_order_items_randomn` (`randomn_id`),
  CONSTRAINT `fk_order_items_comenzi` FOREIGN KEY (`order_id`) REFERENCES `comenzi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `payment_sessions`
--

DROP TABLE IF EXISTS `payment_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_sessions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_randomn_id` int unsigned DEFAULT NULL,
  `provider` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'stub',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` char(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RON',
  `reference` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_sessions_reference` (`reference`),
  KEY `idx_payment_sessions_order` (`order_randomn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pieseauto_accounts`
--

DROP TABLE IF EXISTS `pieseauto_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pieseauto_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `randomn_id` int unsigned NOT NULL,
  `id_users` int unsigned NOT NULL DEFAULT '0',
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pas` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `target_user` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'besoiu',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_randomn_id` (`randomn_id`),
  KEY `idx_id_users` (`id_users`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `planner_plans`
--

DROP TABLE IF EXISTS `planner_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planner_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `slot` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'current',
  `name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_planner_user_slot` (`user_id`,`slot`),
  KEY `idx_planner_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `price_formation_logic`
--

DROP TABLE IF EXISTS `price_formation_logic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_formation_logic` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `image_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `local_path` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `external_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `title_original` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `price_pln` decimal(10,2) DEFAULT NULL,
  `price_ron_base` decimal(10,2) DEFAULT NULL,
  `price_ron_final` decimal(10,2) DEFAULT NULL,
  `exchange_rate` decimal(10,4) DEFAULT NULL,
  `markup_percent` int DEFAULT NULL,
  `brand` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `car_brand` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `main_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sub_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `product_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `category_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `offer_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `seller_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ad_title` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ad_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ad_disclaimer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ad_full_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `raw_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `products_oem`
--

DROP TABLE IF EXISTS `products_oem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products_oem` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `oem_code` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `oem_norm` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `brand` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `source` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'import',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_oem_product_norm` (`product_id`,`oem_norm`),
  KEY `idx_products_oem_norm` (`oem_norm`),
  KEY `idx_products_oem_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produse`
--

DROP TABLE IF EXISTS `produse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produse` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `pName` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pNameMarketplace` varchar(500) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pCar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pCode` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pCodeNorm` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pBrandNorm` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pPrice` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pBasePrice` decimal(10,2) DEFAULT NULL,
  `pState` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pCity` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pNote` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pNoteWebsite` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'Descriere curatata pentru website',
  `pNoteMarketplace` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT 'Descriere detaliata pentru marketplace',
  `pShipping` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pCurierLivrare` varchar(8) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Da' COMMENT 'Livrare curier: Da sau Nu',
  `pWarranty` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pReturn` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pImages` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pImageSource` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pWhatsapp` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pMarkupRuleId` int DEFAULT NULL,
  `pMarkupRuleName` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pMarkupAppliedAt` datetime DEFAULT NULL,
  `pBadge` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pVitrina` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Afisare vitrina homepage',
  `pSpecial` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Afisare produse speciale homepage',
  `connect_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `randomn_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `id_users` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pSupplier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pBrand` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pMarca` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pModel` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pMotorizare` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pStock` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pCategory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pSubcategory` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pCompatibilitati` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pOem` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pCategoryNorm` varchar(255) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (lower(trim(`pCategory`))) STORED,
  `pSubcategoryNorm` varchar(255) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (lower(trim(`pSubcategory`))) STORED,
  `pMarcaNorm` varchar(160) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (lower(trim(`pMarca`))) STORED,
  `pModelNorm` varchar(255) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (lower(trim(`pModel`))) STORED,
  `pBrandFilterNorm` varchar(160) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (lower(trim(`pBrand`))) STORED,
  `pSupplierNorm` varchar(160) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (upper(trim(`pSupplier`))) STORED,
  `pMarkupRuleNorm` varchar(255) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS (lower(trim(`pMarkupRuleName`))) STORED,
  `pImageState` varchar(12) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS ((case when ((`pImages` is null) or (trim(`pImages`) in (_utf8mb4'',_utf8mb4'[]',_utf8mb4'null'))) then _utf8mb4'missing' else _utf8mb4'present' end)) STORED,
  `pMarkupState` varchar(16) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS ((case when ((`pMarkupRuleName` is null) or (trim(`pMarkupRuleName`) = _utf8mb4'')) then _utf8mb4'without_rule' else _utf8mb4'with_rule' end)) STORED,
  `tecdoc_matched` tinyint(1) NOT NULL DEFAULT '0',
  `tecdoc_matched_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_produse_pcode` (`pCode`(100)),
  KEY `idx_produse_status` (`status`),
  KEY `idx_produse_admin_category` (`pCategoryNorm`,`id`),
  KEY `idx_produse_admin_brand` (`pBrandFilterNorm`,`id`),
  KEY `idx_produse_admin_supplier` (`pSupplierNorm`,`id`),
  KEY `idx_produse_admin_image` (`pImageState`,`status`,`id`),
  KEY `idx_produse_admin_markup` (`pMarkupState`,`pMarkupRuleNorm`,`id`),
  KEY `idx_produse_admin_status` (`status`,`id`),
  KEY `idx_produse_admin_image_source` (`pImageSource`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produse_oem`
--

DROP TABLE IF EXISTS `produse_oem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produse_oem` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produs_id` int NOT NULL,
  `oem_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oem_code` (`oem_code`),
  KEY `idx_produs_id` (`produs_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `queue_jobs`
--

DROP TABLE IF EXISTS `queue_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `job_type` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` json NOT NULL,
  `status` enum('pending','processing','done','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `available_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reserved_at` datetime DEFAULT NULL,
  `last_error` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue_jobs_poll` (`queue`,`status`,`available_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reply_templates`
--

DROP TABLE IF EXISTS `reply_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reply_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `randomn_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `channel` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `body_text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `body_html` text COLLATE utf8mb4_unicode_ci,
  `is_quick` tinyint(1) NOT NULL DEFAULT '0',
  `use_count` int unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reply_templates_randomn` (`randomn_id`),
  KEY `idx_reply_templates_channel` (`channel`,`category`),
  KEY `idx_reply_templates_quick` (`is_quick`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report`
--

DROP TABLE IF EXISTS `report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_nav`
--

DROP TABLE IF EXISTS `role_nav`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_nav` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prime` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `sort_order` int NOT NULL DEFAULT '100',
  `parent_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `icon` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `idx_role_nav_role` (`role_slug`,`is_active`,`sort_order`),
  CONSTRAINT `fk_role_nav_role` FOREIGN KEY (`role_slug`) REFERENCES `roles` (`slug`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_widgets`
--

DROP TABLE IF EXISTS `role_widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_widgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `widget_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '100',
  PRIMARY KEY (`id`),
  KEY `idx_role_widgets_role` (`role_slug`,`is_active`,`sort_order`),
  CONSTRAINT `fk_role_widgets_role` FOREIGN KEY (`role_slug`) REFERENCES `roles` (`slug`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `label` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `scopes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '100',
  `url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `routes`
--

DROP TABLE IF EXISTS `routes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `routes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `controller` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `load_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dir` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `namenav` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `typenav` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `users` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `icon` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_routes_method_path_active` (`method`,`path`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=326 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scan`
--

DROP TABLE IF EXISTS `scan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schema_legacy_registry`
--

DROP TABLE IF EXISTS `schema_legacy_registry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_legacy_registry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_name` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_legacy_table` (`table_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `search_logs`
--

DROP TABLE IF EXISTS `search_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `query_type` enum('vin','oem','name') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vin',
  `query_value` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `found` tinyint(1) NOT NULL DEFAULT '0',
  `car_id` int unsigned DEFAULT NULL,
  `vehicle_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_count` int unsigned NOT NULL DEFAULT '0',
  `notice` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `ip_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_logs_type_value` (`query_type`,`query_value`),
  KEY `idx_search_logs_found_created` (`found`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `search_logs_scaffold`
--

DROP TABLE IF EXISTS `search_logs_scaffold`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `search_logs_scaffold` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_chat_carts`
--

DROP TABLE IF EXISTS `shop_chat_carts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_chat_carts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `visitor_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `profile_id` int unsigned DEFAULT NULL,
  `cart_json` json DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_chat_cart_visitor` (`visitor_key`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_chat_escalations`
--

DROP TABLE IF EXISTS `shop_chat_escalations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_chat_escalations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `visitor_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `channel` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shop_chat',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no_answer',
  `status` enum('open','resolved','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `meta_json` json DEFAULT NULL,
  `resolved_by` int unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_escalation_status` (`status`,`created_at`),
  KEY `idx_escalation_visitor` (`visitor_key`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_chat_events`
--

DROP TABLE IF EXISTS `shop_chat_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_chat_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned DEFAULT NULL,
  `visitor_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `handled_by` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `availability` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `message_excerpt` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `meta_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_chat_evt_type` (`event_type`,`created_at`),
  KEY `idx_shop_chat_evt_visitor` (`visitor_key`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_chat_followups`
--

DROP TABLE IF EXISTS `shop_chat_followups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_chat_followups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL,
  `visitor_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cart_json` json DEFAULT NULL,
  `reason` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'chat_abandoned',
  `status` enum('pending','sent','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_chat_fu_status` (`status`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_chat_knowledge`
--

DROP TABLE IF EXISTS `shop_chat_knowledge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_chat_knowledge` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `randomn_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `entry_type` enum('qa_pair','button_doc','system_rule','rag_chunk') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'qa_pair',
  `channel` enum('internal','external','both') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'both',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `question_examples` json DEFAULT NULL,
  `expected_meaning` text COLLATE utf8mb4_unicode_ci,
  `expected_action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `expected_intent` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `expected_response` text COLLATE utf8mb4_unicode_ci,
  `metadata_json` json DEFAULT NULL,
  `tags_json` json DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '50',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_chat_knowledge_rid` (`randomn_id`),
  KEY `idx_shop_chat_knowledge_type` (`entry_type`,`status`,`priority`),
  KEY `idx_shop_chat_knowledge_channel` (`channel`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=195 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_chat_memory`
--

DROP TABLE IF EXISTS `shop_chat_memory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_chat_memory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL,
  `memory_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `memory_value` text COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_chat_mem` (`profile_id`,`memory_key`),
  KEY `idx_shop_chat_mem_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `shop_customers`
--

DROP TABLE IF EXISTS `shop_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shop_customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_customers_email` (`email`),
  KEY `idx_shop_customers_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_pages`
--

DROP TABLE IF EXISTS `site_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `site_pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT '',
  `meta_description` text,
  `hero_label` varchar(255) DEFAULT '',
  `hero_title` varchar(255) DEFAULT '',
  `hero_subtitle` text,
  `body_html` mediumtext,
  `sections_json` longtext,
  `faq_json` longtext,
  `cta_json` longtext,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_site_pages_slug` (`slug`),
  KEY `idx_site_pages_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_errors`
--

DROP TABLE IF EXISTS `system_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_errors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `level` enum('debug','info','warning','error','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'error',
  `channel` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `message` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `context_json` json DEFAULT NULL,
  `source_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT '0',
  `is_archived` tinyint(1) NOT NULL DEFAULT '0',
  `archived_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_system_errors_channel_created` (`channel`,`created_at`),
  KEY `idx_system_errors_level_created` (`level`,`created_at`),
  KEY `idx_system_errors_resolved_created` (`is_resolved`,`created_at`),
  KEY `idx_system_errors_archived` (`is_archived`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `team_members`
--

DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `firma_id` int unsigned DEFAULT NULL,
  `nume` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rol` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `workfirm` year DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `randomn_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `connect_id` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `id_users` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_api_cache`
--

DROP TABLE IF EXISTS `tecdoc_api_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_api_cache` (
  `cache_key` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(768) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cache_key`),
  KEY `idx_tecdoc_api_cache_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_brands`
--

DROP TABLE IF EXISTS `tecdoc_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_brands` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tecdoc_brands_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=172 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_product_codes`
--

DROP TABLE IF EXISTS `tecdoc_product_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_product_codes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `brand_id` smallint unsigned NOT NULL,
  `code_norm` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code_type` enum('code1','code2','merged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'code1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tecdoc_product_codes` (`product_id`,`code_norm`),
  KEY `idx_tecdoc_lookup_brand_code` (`brand_id`,`code_norm`),
  KEY `idx_tecdoc_lookup_code` (`code_norm`),
  CONSTRAINT `fk_tecdoc_codes_brand` FOREIGN KEY (`brand_id`) REFERENCES `tecdoc_brands` (`id`),
  CONSTRAINT `fk_tecdoc_codes_product` FOREIGN KEY (`product_id`) REFERENCES `tecdoc_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4875795 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_product_compatibilities`
--

DROP TABLE IF EXISTS `tecdoc_product_compatibilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_product_compatibilities` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `ttc_typ_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_brand` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_model` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_typ` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_body` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_of_year` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_to_year` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_kw` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_pm` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_cc` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tecdoc_compat_product` (`product_id`),
  KEY `idx_tecdoc_compat_car` (`car_brand`,`car_model`)
) ENGINE=InnoDB AUTO_INCREMENT=35614173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_product_compatibilities_new`
--

DROP TABLE IF EXISTS `tecdoc_product_compatibilities_new`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_product_compatibilities_new` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `ttc_typ_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_brand` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_typ` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_body` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_of_year` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_to_year` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_kw` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_pm` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_cc` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tecdoc_compat_product` (`product_id`),
  KEY `idx_tecdoc_compat_car` (`car_brand`,`car_model`)
) ENGINE=InnoDB AUTO_INCREMENT=35614173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_product_images`
--

DROP TABLE IF EXISTS `tecdoc_product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_product_images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `brand_id` smallint unsigned NOT NULL,
  `source` enum('poze','autopartner') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ttc_art_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ap_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` tinyint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tecdoc_product_images` (`product_id`,`source`),
  KEY `idx_tecdoc_images_brand` (`brand_id`),
  KEY `idx_tecdoc_images_ttc` (`ttc_art_id`),
  CONSTRAINT `fk_tecdoc_images_brand` FOREIGN KEY (`brand_id`) REFERENCES `tecdoc_brands` (`id`),
  CONSTRAINT `fk_tecdoc_images_product` FOREIGN KEY (`product_id`) REFERENCES `tecdoc_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=141950 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_products`
--

DROP TABLE IF EXISTS `tecdoc_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `brand_id` smallint unsigned NOT NULL,
  `art_code_1` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `art_code_2` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `art_name` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `art_ean` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ttc_art_id` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parts_info` mediumtext COLLATE utf8mb4_unicode_ci,
  `terms_of_use` mediumtext COLLATE utf8mb4_unicode_ci,
  `art_cross` mediumtext COLLATE utf8mb4_unicode_ci,
  `compat_count` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tecdoc_products_brand_code` (`brand_id`,`art_code_1`),
  KEY `idx_tecdoc_products_brand` (`brand_id`),
  KEY `idx_tecdoc_products_ttc` (`ttc_art_id`),
  CONSTRAINT `fk_tecdoc_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `tecdoc_brands` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2301425 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tecdoc_supplier_prices`
--

DROP TABLE IF EXISTS `tecdoc_supplier_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tecdoc_supplier_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code_norm` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_net` decimal(12,2) NOT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RON',
  `priority` tinyint unsigned NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tecdoc_supplier_code` (`code_norm`,`supplier`),
  KEY `idx_tecdoc_prices_lookup` (`code_norm`,`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=1772211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_connect`
--

DROP TABLE IF EXISTS `users_connect`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_connect` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nikname` text,
  `fullname` text,
  `login` text,
  `password` text,
  `contact` text,
  `role` text,
  `permissions_json` text COMMENT 'Module admin delegabile',
  `chat_permissions_json` text COMMENT 'Permisiuni chat Composer (JSON array)',
  `closs` text,
  `status` text,
  `id_users` text,
  `connect_id` text,
  `randomn_id` text,
  `token` text,
  `datainsert` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `datareg` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users_info`
--

DROP TABLE IF EXISTS `users_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users_info` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nikname` text,
  `fname` text,
  `lastname` text,
  `email` text,
  `companyname` text,
  `adress` text,
  `telegram` text,
  `countor` text,
  `city` text,
  `tel` text,
  `dob` text,
  `marital_status` text,
  `age_group` text,
  `experience_level` text,
  `channels` text,
  `availability` text,
  `portfolio` text,
  `gender` text,
  `tools` text,
  `comments` text,
  `zipcode` text,
  `langue` text,
  `rating` text,
  `star` text,
  `des` text,
  `id_users` text,
  `connect_id` text,
  `randomn_id` text,
  `datareg` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `v_schema_convention_gaps`
--

DROP TABLE IF EXISTS `v_schema_convention_gaps`;
/*!50001 DROP VIEW IF EXISTS `v_schema_convention_gaps`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_schema_convention_gaps` AS SELECT 
 1 AS `table_name`,
 1 AS `has_non_standard_name`,
 1 AS `missing_created_at`,
 1 AS `missing_updated_at`,
 1 AS `is_legacy_exception`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'besoiupieseauto.ro'
--

--
-- Current Database: `besoiupieseauto.ro`
--

USE `besoiupieseauto.ro`;

--
-- Final view structure for view `v_schema_convention_gaps`
--

/*!50001 DROP VIEW IF EXISTS `v_schema_convention_gaps`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`pieseauto`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_schema_convention_gaps` AS select `information_schema`.`t`.`TABLE_NAME` AS `table_name`,(case when regexp_like(`information_schema`.`t`.`TABLE_NAME`,'[-A-Z]') then 1 else 0 end) AS `has_non_standard_name`,(case when (`information_schema`.`c_created`.`COLUMN_NAME` is null) then 1 else 0 end) AS `missing_created_at`,(case when (`information_schema`.`c_updated`.`COLUMN_NAME` is null) then 1 else 0 end) AS `missing_updated_at`,(case when (`lr`.`table_name` is not null) then 1 else 0 end) AS `is_legacy_exception` from (((`information_schema`.`TABLES` `t` left join `information_schema`.`COLUMNS` `c_created` on(((`information_schema`.`c_created`.`TABLE_SCHEMA` = `information_schema`.`t`.`TABLE_SCHEMA`) and (`information_schema`.`c_created`.`TABLE_NAME` = `information_schema`.`t`.`TABLE_NAME`) and (`information_schema`.`c_created`.`COLUMN_NAME` = 'created_at')))) left join `information_schema`.`COLUMNS` `c_updated` on(((`information_schema`.`c_updated`.`TABLE_SCHEMA` = `information_schema`.`t`.`TABLE_SCHEMA`) and (`information_schema`.`c_updated`.`TABLE_NAME` = `information_schema`.`t`.`TABLE_NAME`) and (`information_schema`.`c_updated`.`COLUMN_NAME` = 'updated_at')))) left join `schema_legacy_registry` `lr` on(((`lr`.`table_name` = `information_schema`.`t`.`TABLE_NAME`) and (`lr`.`is_active` = 1)))) where ((`information_schema`.`t`.`TABLE_SCHEMA` = database()) and (`information_schema`.`t`.`TABLE_TYPE` = 'BASE TABLE')) */;
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

-- Dump completed on 2026-07-23 14:13:52
