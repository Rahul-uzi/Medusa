-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: restaurant_db
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
-- Table structure for table `career_applications`
--

DROP TABLE IF EXISTS `career_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `career_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `position` varchar(50) NOT NULL,
  `experience` int(11) NOT NULL,
  `city` varchar(100) NOT NULL,
  `expected_salary` decimal(10,2) NOT NULL,
  `resume_path` varchar(255) NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('Pending','Reviewed','Shortlisted','Rejected') DEFAULT 'Pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `career_applications`
--

LOCK TABLES `career_applications` WRITE;
/*!40000 ALTER TABLE `career_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `career_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `food_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `food_item_id` (`food_item_id`),
  CONSTRAINT `fk_cart_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cart_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cart`
--

LOCK TABLES `cart` WRITE;
/*!40000 ALTER TABLE `cart` DISABLE KEYS */;
/*!40000 ALTER TABLE `cart` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `review_id` int(11) DEFAULT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `campaign_code` varchar(50) NOT NULL,
  `discount_type` varchar(20) NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `expires_at` datetime NOT NULL,
  `redeemed_at` datetime DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `coupon_code` (`coupon_code`),
  KEY `idx_coupons_user_id` (`user_id`),
  KEY `idx_coupons_status` (`status`),
  KEY `idx_coupons_expires_at` (`expires_at`),
  KEY `fk_coupons_feedback` (`review_id`),
  KEY `fk_coupons_orders` (`order_id`),
  CONSTRAINT `fk_coupons_feedback` FOREIGN KEY (`review_id`) REFERENCES `feedback` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_coupons_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_coupons_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_tiers`
--

DROP TABLE IF EXISTS `customer_tiers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customer_tiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tier_name` varchar(50) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 10.00,
  `spending_requirement` decimal(10,2) NOT NULL DEFAULT 0.00,
  `points_earning_percent` decimal(5,2) NOT NULL DEFAULT 2.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tier_name` (`tier_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_tiers`
--

LOCK TABLES `customer_tiers` WRITE;
/*!40000 ALTER TABLE `customer_tiers` DISABLE KEYS */;
INSERT INTO `customer_tiers` VALUES (1,'Silver',10.00,0.00,2.00),(2,'Gold',15.00,25000.00,2.00),(3,'Platinum',20.00,75000.00,2.00);
/*!40000 ALTER TABLE `customer_tiers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dish_customizations`
--

DROP TABLE IF EXISTS `dish_customizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_customizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `food_item_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL COMMENT 'e.g. Crust Type, Size, Toppings',
  `group_type` enum('single','multiple') DEFAULT 'single' COMMENT 'single=radio, multiple=checkbox',
  `is_required` tinyint(1) DEFAULT 0 COMMENT '1=must pick an option, 0=optional',
  `options_json` text NOT NULL COMMENT 'JSON array: [{label, price_add}]',
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `food_item_id` (`food_item_id`),
  CONSTRAINT `fk_customization_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dish_customizations`
--

LOCK TABLES `dish_customizations` WRITE;
/*!40000 ALTER TABLE `dish_customizations` DISABLE KEYS */;
/*!40000 ALTER TABLE `dish_customizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `order_number` varchar(20) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  `review` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_feedback_orders` (`order_number`),
  KEY `fk_feedback_users` (`user_id`),
  CONSTRAINT `fk_feedback_orders` FOREIGN KEY (`order_number`) REFERENCES `orders` (`order_number`) ON DELETE CASCADE,
  CONSTRAINT `fk_feedback_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feedback`
--

LOCK TABLES `feedback` WRITE;
/*!40000 ALTER TABLE `feedback` DISABLE KEYS */;
/*!40000 ALTER TABLE `feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `food_items`
--

DROP TABLE IF EXISTS `food_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `food_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT 'default.jpg',
  `is_available` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `food_items`
--

LOCK TABLES `food_items` WRITE;
/*!40000 ALTER TABLE `food_items` DISABLE KEYS */;
INSERT INTO `food_items` VALUES (1,'Cream of Mushroom','Roasted button mushrooms, celery, leeks, garlic, and parmesan foam',425.00,'Soups','',1),(2,'Manchow Soup','Veg / chicken / prawns — mild asian minced vegetable soup with crispy noodles',425.00,'Soups','',1),(3,'Vietnamese Pho Soup','Veg / chicken — flat vietnamese noodles simmered in aromatic broth',425.00,'Soups','',1),(4,'Hot & Sour Soup','Veg / chicken / prawns — bamboo shoots, mushrooms, chinese cabbage in a thick and spicy soup',425.00,'Soups','',1),(5,'Sweet Corn Soup','Veg / chicken — delicious kernels of corn in a thick, luscious soup',425.00,'Soups','',1),(6,'Lemon Coriander Soup','Veg / chicken — healthy and filling lemon coriander soup',425.00,'Soups','',1),(7,'Roasted Tomato Basil Soup','Oven-roasted plum tomato soup with a fresh hint of basil',425.00,'Soups','',1),(8,'Tom Yum','Veg / chicken / prawns — lemongrass, kaffir lime, and galangal shine through',425.00,'Soups','',1),(9,'Murgh Yakhani Shorba','Slow-cooked rich chicken soup accentuated with saffron',445.00,'Soups','',1),(10,'Burrata and Berry Salad','Strawberries, blueberries, and californian grapes tossed in mixed green lettuce',645.00,'Salad','',1),(11,'Classical Caesar Salad','Veg / chicken / prawns — crispy romaine lettuce tossed in caesar dressing with garlic croutons and parmesan',425.00,'Salad','',1),(12,'Middle Eastern Salad Bowl','Veg / chicken — beetroot fattoush, whole wheat pita, grilled sumac vegetables',545.00,'Salad','',1),(13,'Healthy Organic Quinoa','Mixed lettuce with organic quinoa, lemon mustard dressing, and green apple',525.00,'Salad','',1),(14,'Som Tom Salad','Raw papaya, sweet chili sauce, crushed peanuts, and basil',465.00,'Salad','',1),(15,'Classical Greek Salad','Baby cucumber, tomatoes, bell peppers, onions, and feta cheese in homemade dressing',425.00,'Salad','',1),(16,'Tandoori Roti','Plain / butter',45.00,'Bread Basket','',1),(17,'Laccha Paratha','Plain / butter / mirchi',55.00,'Bread Basket','',1),(18,'Naan','Plain / butter / missi roti / garlic parmesan / olive naan',85.00,'Bread Basket','',1),(19,'Stuffed Naan','Aloo / onion / paneer / keema',125.00,'Bread Basket','',1),(20,'Indian Green Salad','Cucumber, onion, tomato, carrot, and green chilli',225.00,'Sides','',1),(21,'Pappad','',125.00,'Sides','',1),(22,'Raita','Mix / pineapple / boondi raita',55.00,'Sides','',1),(23,'Seoul Bibimbap','Veg / chicken — simmering rice with vegetables or chicken, served in traditional dolsot',585.00,'Meals in the Bowl','',1),(24,'Morelos Mexican Burrito Bowl','Veg / chicken — hearty bowl with mexican flair, packed with flavor',585.00,'Meals in the Bowl','',1),(25,'Shanghai Bowl','Crispy chicken, sesame-sweet soy sauce, and sticky rice',585.00,'Meals in the Bowl','',1),(26,'Gong Bao Bowl','Crispy cottage cheese, chilli tomatoes, and soy sauce',585.00,'Meals in the Bowl','',1),(27,'Burmese Khao Suey','Veg / chicken — soft and crispy noodles in a fragrant coconut broth',525.00,'Meals in the Bowl','',1),(28,'Atlantic Salmon','Grilled norwegian salmon fillet served with seasonal vegetables and a lemon butter glaze',2550.00,'Main Course','',1),(29,'Australian Lamb Chops','Char-grilled lamb chops marinated in herbs and spices, served with garlic mash and jus',2550.00,'Main Course','',1),(30,'Gulf Tiger Prawns','Succulent tiger prawns grilled with lemon, ajwain, and coastal-style tandoori spices',1650.00,'Main Course','',1),(31,'Grilled Chicken Breast','Served with sautéed vegetables, garlic mashed potato, and red wine jus',625.00,'Main Course','',1),(32,'Portuguese Peri Roast Chicken','Herbed and peri peri-marinated chicken breast with sautéed vegetables',625.00,'Main Course','',1),(33,'Malai Paneer Steak','Stuffed with sautéed spinach, served with wasabi mash and vegetables',465.00,'Main Course','',1),(34,'Grilled River Sole Fish','Sautéed vegetables, garlic mashed potato, and lemon butter sauce',625.00,'Main Course','',1),(35,'Steam Ginger Fish','Healthy steamed fish served with stir-fried vegetables',625.00,'Main Course','',1),(36,'Peri Peri Grilled Fish','Peri peri-marinated fish with exotic vegetables',625.00,'Main Course','',1),(37,'Pad Thai Noodle','Veg / chicken — flat rice noodles with your choice of vegetables or chicken',485.00,'Main Course','',1),(38,'Himalayan Thukpa','Veg / chicken — comforting bowl of noodles and vegetables in a savory broth',395.00,'Main Course','',1),(39,'Minced Basil Chicken','Thai-style minced basil chicken served with rice',525.00,'Main Course','',1),(40,'Thai Curry Green | Red','Veg / chicken — authentic thai curry served with rice, choose green or red',490.00,'Main Course','',1),(41,'Kerala Fish Curry','Classic south indian style fish curry, just as flavorful as expected',690.00,'Main Course','',1),(42,'Hakka Noodle','Available as veg / chicken / prawns',0.00,'Choice of Noodle','',1),(43,'Chilli Garlic Noodle','Available as veg / chicken / prawns',0.00,'Choice of Noodle','',1),(44,'Burn Garlic Noodle','Available as veg / chicken / prawns',0.00,'Choice of Noodle','',1),(45,'Fried Rice','Veg / chicken / prawns',0.00,'Choice of Rice','',1),(46,'Chilli Garlic Fried Rice','Veg / chicken / prawns',0.00,'Choice of Rice','',1),(47,'Burn Garlic Fried Rice','Veg / chicken / prawns',0.00,'Choice of Rice','',1),(48,'Plain Basmati Rice','',0.00,'Choice of Rice','',1),(49,'Hot Garlic Sauce','',0.00,'Choice of Gravy','',1),(50,'Szechuan Sauce','',0.00,'Choice of Gravy','',1),(51,'Black Bean Sauce','',0.00,'Choice of Gravy','',1),(52,'Thai Basil Sauce','',0.00,'Choice of Gravy','',1),(53,'Mushroom and Truffle','Steamed dim sum',475.00,'Dim Sum Cart','',1),(54,'Asparagus, Water Chestnut and Corn','Steamed dim sum',495.00,'Dim Sum Cart','',1),(55,'Vegetable Crystal Dumpling','Translucent crystal skin dumpling',475.00,'Dim Sum Cart','',1),(56,'Edamame Cheese','Steamed dim sum',495.00,'Dim Sum Cart','',1),(57,'Chilli Garlic Greens','Steamed dim sum',525.00,'Dim Sum Cart','',1),(58,'Spicy Chilli Oil Dumpling','Steamed dim sum',545.00,'Dim Sum Cart','',1),(59,'Chicken and Coriander Dumpling','Steamed dim sum',525.00,'Dim Sum Cart','',1),(60,'Prawns Harkao','Classic prawn dumpling',575.00,'Dim Sum Cart','',1),(61,'Mushroom Kra Pao Bao','Steamed bao bun',525.00,'Dim Sum Cart','',1),(62,'Crushed Pepper Tofu Bao','Steamed bao bun',525.00,'Dim Sum Cart','',1),(63,'Korean Chicken Bao','Steamed bao bun',545.00,'Dim Sum Cart','',1),(64,'Crispy Prawns Bao','Steamed bao bun',575.00,'Dim Sum Cart','',1),(65,'Yasai Maki Roll','Lettuce, thai cucumber, cream cheese, and pickles',585.00,'Sushi Rolls','',1),(66,'Tempura Asparagus','Philadelphia cheese, dill leaves, and crispy asparagus',595.00,'Sushi Rolls','',1),(67,'Cucumber and Avocado Roll','Thai baby cucumber, avocado, tenkasu, and spicy mayo',645.00,'Sushi Rolls','',1),(68,'California Mango Roll','Fresh mangoes, wasabi, japanese mayo, and pickled ginger',595.00,'Sushi Rolls','',1),(69,'Tuna Roll','Fresh tuna, creamy avocado, and spicy mayo wrapped in seasoned sushi rice',625.00,'Sushi Rolls','',1),(70,'Salmon Roll','Medusa\'s signature roll for salmon lovers',725.00,'Sushi Rolls','',1),(71,'Dragon Crispy Chicken Sushi Roll','Crispy fried chicken, spicy mayo, wasabi, and pickled ginger',595.00,'Sushi Rolls','',1),(72,'Sakura Roll','Prawn tempura, thai cucumber, and spicy mayo',645.00,'Sushi Rolls','',1),(73,'Mushroom Burger','A classic mushroom melt with organic himalayan cheese',365.00,'Burgers & Sandwiches','',1),(74,'Patty Lamb Burger','Young lamb pulled meat patty char-grilled to perfection',475.00,'Burgers & Sandwiches','',1),(75,'Tex Mex Chicken Burger','Spicy golden-fried chicken patty with our famous king sauce',395.00,'Burgers & Sandwiches','',1),(76,'Crispy Fish Burger','Panko-crusted fish fillet with lettuce, tartar sauce, and house slaw in a toasted bun',450.00,'Burgers & Sandwiches','',1),(77,'Pesto Chicken Croissant Sandwich','Roast chicken marinated in homemade pesto',465.00,'Burgers & Sandwiches','',1),(78,'Smokey Paneer Croissant Sandwich','Punjabi-style tandoori paneer stuffed in buttery croissant',455.00,'Burgers & Sandwiches','',1),(79,'Chicken Spicy Tikka Sandwich','Cucumber, tomato, lettuce, and charred chicken tikka in multigrain bread',465.00,'Burgers & Sandwiches','',1),(80,'Tandoori Paneer Sandwich','Cucumber, tomato, lettuce, and charred paneer tikka in multigrain bread',445.00,'Burgers & Sandwiches','',1),(81,'Triple Club Sandwich','Pulled roast chicken, stone-ground dill pesto, served with house chips',485.00,'Burgers & Sandwiches','',1),(82,'Green Club Sandwich','Multigrain bread overloaded with greens and cheese',455.00,'Burgers & Sandwiches','',1),(83,'Vegetarian Mezze Platter','Falafel, paneer shashlik, baba ghanoush, hummus, muhammara, pickled vegetables, and mini soft pita',665.00,'Sharing Boards','',1),(84,'Non-Vegetarian Mezze Platter','Fish shish, aamb adana, chicken and cheese adana, chicken shish, and assorted cold dips',785.00,'Sharing Boards','',1),(85,'Tandoori Veg Platter','Paneer tikka, veg galouti, tandoori pineapple, and dahi kebab',655.00,'Sharing Boards','',1),(86,'Tandoori Non-Veg Platter','Malai chicken tikka, chicken tikka, chicken seekh kebab, mutton kebab, and fish tikka',785.00,'Sharing Boards','',1),(87,'Margherita','As simple and classic as you know',545.00,'Brick Oven Pizza','',1),(88,'Paneer Makhni King','A favorite — makhni sauce topped with tandoori paneer tikka',595.00,'Brick Oven Pizza','',1),(89,'Andhara Mushroom Sukka','A south indian twist — mushroom sukka on pizza',625.00,'Brick Oven Pizza','',1),(90,'Garden Fresh Pizza','Mixed bell peppers, broccoli, mushrooms, red onions, and mozzarella',525.00,'Brick Oven Pizza','',1),(91,'Burrata Arugula','Classic burrata pizza with fresh arugula and house-roasted tomatoes',610.00,'Brick Oven Pizza','',1),(92,'Mushroom Pizza','Shiitake and button mushrooms roasted in the oven, with a perfect blend of mozzarella and cheddar',545.00,'Brick Oven Pizza','',1),(93,'Chicken Makhni King','Medusa\'s own recipe — a must-try!',625.00,'Brick Oven Pizza','',1),(94,'Sausage Masala King','Old delhi favorite recipe',595.00,'Brick Oven Pizza','',1),(95,'Pepperoni Pizza','Minced lamb tenderloin, chargrilled and topped with mozzarella and cheddar cheese',625.00,'Brick Oven Pizza','',1),(96,'Meat Balls','Rogan josh gravy with meatballs on pizza — bold and flavorful',695.00,'Brick Oven Pizza','',1),(97,'Chilli Basil Chicken','Wok-tossed chicken in spicy chilli basil sauce',465.00,'Non-Veg Appetizer','',1),(98,'Mutton Keema Pav','Street-style mutton keema served with buttered pav',0.00,'Non-Veg Appetizer','',1);
/*!40000 ALTER TABLE `food_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_activity_logs`
--

DROP TABLE IF EXISTS `login_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'success',
  PRIMARY KEY (`id`),
  KEY `fk_login_logs_users` (`user_id`),
  CONSTRAINT `fk_login_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_activity_logs`
--

LOCK TABLES `login_activity_logs` WRITE;
/*!40000 ALTER TABLE `login_activity_logs` DISABLE KEYS */;
INSERT INTO `login_activity_logs` VALUES (1,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-09 07:36:13','success'),(2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-09 07:36:25','success'),(3,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-09 10:04:19','success'),(4,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-09 10:06:20','success'),(5,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-09 10:06:44','success'),(6,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-09 10:08:10','success'),(7,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-09 10:12:15','success'),(8,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-10 07:26:56','success'),(9,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-10 08:23:11','success'),(10,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36','2026-06-11 05:24:06','success');
/*!40000 ALTER TABLE `login_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_transactions`
--

DROP TABLE IF EXISTS `loyalty_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loyalty_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `points_earned` int(11) DEFAULT 0,
  `points_redeemed` int(11) DEFAULT 0,
  `points_deducted` int(11) DEFAULT 0,
  `transaction_type` varchar(50) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_transactions_users` (`user_id`),
  KEY `fk_transactions_orders` (`order_id`),
  CONSTRAINT `fk_transactions_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_transactions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_transactions`
--

LOCK TABLES `loyalty_transactions` WRITE;
/*!40000 ALTER TABLE `loyalty_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('order','payment','kitchen','reservation','staff','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_is_read` (`is_read`),
  KEY `idx_notif_created_at` (`created_at`),
  KEY `idx_notif_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'system','Admin Login Detected','Administrator System Admin logged in from IP address ::1.',0,'2026-06-09 10:04:19'),(2,'kitchen','Order In Prep','Order ORD-A1024 is now being prepared in the kitchen.',0,'2026-06-09 10:05:19'),(3,'system','Admin Login Detected','Administrator System Admin logged in from IP address ::1.',0,'2026-06-09 10:06:44'),(4,'system','Admin Login Detected','Administrator System Admin logged in from IP address ::1.',0,'2026-06-10 07:26:56'),(5,'system','Admin Login Detected','Administrator System Admin logged in from IP address ::1.',0,'2026-06-10 08:23:11');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `food_item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `food_item_id` (`food_item_id`),
  CONSTRAINT `fk_items_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,NULL,'Butter Chicken',2,450.00,NULL,NULL),(2,1,NULL,'Garlic Naan',4,80.00,NULL,NULL),(3,1,NULL,'Samosa',1,180.00,NULL,NULL),(4,2,NULL,'Margherita Pizza',1,350.00,NULL,NULL),(5,2,NULL,'Tiramisu',1,320.00,NULL,NULL),(6,3,NULL,'Sushi Roll (8 pcs)',2,550.00,NULL,NULL),(7,3,NULL,'Ramen Bowl',1,420.00,NULL,NULL),(8,4,NULL,'Classic Burger',2,350.00,NULL,NULL),(9,4,NULL,'Chicken Wings (6 pcs)',1,380.00,NULL,NULL),(10,5,NULL,'BBQ Ribs',3,550.00,NULL,NULL),(11,5,NULL,'Mac & Cheese',2,320.00,NULL,NULL),(12,5,NULL,'Chocolate Lava Cake',2,380.00,NULL,NULL),(13,6,NULL,'Pad Thai',1,380.00,NULL,NULL),(14,6,NULL,'Garlic Naan',1,80.00,NULL,NULL),(15,6,NULL,'Gulab Jamun (3 pcs)',1,150.00,NULL,NULL),(16,7,NULL,'Chicken Biryani',2,350.00,NULL,NULL),(17,7,NULL,'Ramen Bowl',1,420.00,NULL,NULL),(18,8,NULL,'Pasta Alfredo',1,380.00,NULL,NULL),(19,8,NULL,'Tiramisu',1,320.00,NULL,NULL),(20,9,NULL,'Margherita Pizza',2,350.00,NULL,NULL),(21,9,NULL,'Pepperoni Pizza',1,420.00,NULL,NULL),(22,9,NULL,'Classic Lasagna',1,450.00,NULL,NULL),(23,10,NULL,'Butter Chicken',1,450.00,NULL,NULL),(24,10,NULL,'Gulab Jamun (3 pcs)',1,150.00,NULL,NULL),(25,10,NULL,'Garlic Naan',2,80.00,NULL,NULL),(26,11,NULL,'Chicken Biryani',3,350.00,NULL,NULL),(27,11,NULL,'Ice Cream Sundae',1,220.00,NULL,NULL),(28,12,NULL,'Dim Sum (6 pcs)',2,320.00,NULL,NULL),(29,12,NULL,'Bruschetta',1,250.00,NULL,NULL),(30,13,NULL,'BBQ Ribs',2,550.00,NULL,NULL),(31,13,NULL,'Chicken Wings (6 pcs)',2,380.00,NULL,NULL),(32,13,NULL,'New York Cheesecake',1,350.00,NULL,NULL),(33,14,NULL,'Butter Chicken',1,450.00,NULL,NULL),(34,14,NULL,'Chicken Biryani',1,350.00,NULL,NULL),(35,15,NULL,'Pasta Alfredo',1,380.00,NULL,NULL),(36,15,NULL,'Pasta Arrabiata',1,350.00,NULL,NULL),(37,15,NULL,'Garlic Naan',1,80.00,NULL,NULL),(38,16,NULL,'Classic Burger',1,350.00,NULL,NULL),(39,16,NULL,'Mac & Cheese',1,320.00,NULL,NULL),(40,16,NULL,'New York Cheesecake',1,350.00,NULL,NULL),(41,17,NULL,'Sushi Roll (8 pcs)',1,550.00,NULL,NULL),(42,17,NULL,'Pad Thai',1,380.00,NULL,NULL),(43,17,NULL,'Mochi Ice Cream (3 pcs)',1,280.00,NULL,NULL),(44,19,NULL,'Burger',2,150.00,NULL,NULL),(45,20,NULL,'Veg Biryani',1,249.00,NULL,NULL),(46,21,NULL,'Butter Chicken',1,349.00,NULL,NULL),(47,21,NULL,'Veg Biryani',1,249.00,NULL,NULL),(48,22,NULL,'Margherita Pizza',1,299.00,NULL,NULL),(49,22,NULL,'Butter Chicken',1,349.00,NULL,NULL),(50,23,NULL,'Burger',2,150.00,NULL,NULL),(51,24,NULL,'Margherita Pizza',1,299.00,NULL,NULL),(52,24,NULL,'Butter Chicken',1,349.00,NULL,NULL),(53,25,NULL,'Margherita Pizza',1,299.00,NULL,NULL),(54,25,NULL,'Butter Chicken',1,349.00,NULL,NULL),(55,26,NULL,'Butter Chicken',1,349.00,NULL,NULL),(56,26,NULL,'Veg Biryani',1,249.00,NULL,NULL),(57,27,NULL,'Margherita Pizza',1,299.00,NULL,NULL),(58,27,NULL,'Butter Chicken',2,349.00,NULL,NULL),(59,27,NULL,'Veg Biryani',1,249.00,NULL,NULL),(60,28,NULL,'Margherita Pizza',1,299.00,NULL,NULL),(61,28,NULL,'Butter Chicken',1,349.00,NULL,NULL),(62,29,NULL,'Basic Box',1,15.85,NULL,NULL),(63,30,NULL,'Margherita Pizza',1,299.00,NULL,NULL),(64,30,NULL,'Butter Chicken',1,349.00,NULL,NULL),(65,31,NULL,'Basic Box',1,15.85,NULL,NULL),(66,32,NULL,'Butter Chicken',1,349.00,NULL,NULL),(67,32,NULL,'Veg Biryani',1,249.00,NULL,NULL),(68,33,NULL,'Basic Box',1,15.85,NULL,NULL),(69,34,NULL,'Butter Chicken',1,349.00,NULL,NULL),(70,34,NULL,'Veg Biryani',1,249.00,NULL,NULL),(71,35,NULL,'Veg Biryani',1,249.00,NULL,NULL),(72,36,NULL,'Veg Biryani',1,249.00,NULL,NULL),(73,36,NULL,'Gulab Jamun',1,129.00,NULL,NULL),(74,37,NULL,'Basic Box',1,15.85,NULL,NULL),(75,38,NULL,'Basic Box',1,15.85,NULL,NULL),(76,39,NULL,'Chicken Biryani',1,350.00,NULL,NULL),(77,39,NULL,'Dal Makhani',1,280.00,NULL,NULL),(78,39,NULL,'Sushi Roll (8 pcs)',1,550.00,NULL,NULL),(79,39,NULL,'BBQ Ribs',1,550.00,NULL,NULL),(80,39,NULL,'Mac & Cheese',1,320.00,NULL,NULL),(81,40,NULL,'Margherita Pizza',1,350.00,NULL,NULL),(82,40,NULL,'Pasta Arrabiata',1,350.00,NULL,NULL),(83,40,NULL,'Pad Thai',1,380.00,NULL,NULL),(84,40,NULL,'Ramen Bowl',1,420.00,NULL,NULL),(85,41,NULL,'Butter Chicken',1,450.00,NULL,NULL),(86,41,NULL,'Chicken Biryani',1,350.00,NULL,NULL),(87,42,NULL,'Butter Chicken',1,450.00,NULL,NULL),(88,42,NULL,'Margherita Pizza',1,350.00,NULL,NULL),(89,43,NULL,'Butter Chicken',1,450.00,NULL,NULL),(90,43,NULL,'Chicken Biryani',1,350.00,NULL,NULL),(91,44,NULL,'Basic Box',1,15.85,NULL,NULL),(92,45,NULL,'Chicken Biryani',1,350.00,NULL,NULL),(93,45,NULL,'Dal Makhani',1,280.00,NULL,NULL),(94,46,NULL,'Chicken Biryani',1,350.00,NULL,NULL),(95,46,NULL,'Dal Makhani',1,280.00,NULL,NULL),(96,47,NULL,'Chicken Biryani',3,350.00,NULL,NULL),(97,47,NULL,'Dal Makhani',1,280.00,NULL,NULL),(98,47,NULL,'Butter Chicken',1,450.00,NULL,NULL),(99,48,NULL,'Palak Paneer',1,320.00,NULL,NULL),(100,48,NULL,'Samosa',1,180.00,NULL,NULL),(101,48,NULL,'Margherita Pizza',1,350.00,NULL,NULL),(102,48,NULL,'Pasta Arrabiata',1,350.00,NULL,NULL),(103,49,NULL,'Palak Paneer',1,320.00,NULL,NULL),(104,49,NULL,'Samosa',1,180.00,NULL,NULL),(105,49,NULL,'Margherita Pizza',1,350.00,NULL,NULL),(106,50,NULL,'Palak Paneer',1,320.00,NULL,NULL),(107,50,NULL,'Samosa',1,180.00,NULL,NULL),(108,50,NULL,'Margherita Pizza',1,350.00,NULL,NULL),(109,51,NULL,'Butter Chicken',1,450.00,NULL,NULL),(110,51,NULL,'Dal Makhani',1,280.00,NULL,NULL),(111,52,NULL,'Butter Chicken',2,450.00,NULL,NULL),(112,52,NULL,'Chicken Biryani',2,350.00,NULL,NULL);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(15) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `order_status` varchar(20) DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tier_discount_amount` decimal(10,2) DEFAULT 0.00,
  `points_redeemed` int(11) DEFAULT 0,
  `points_redeemed_discount` decimal(10,2) DEFAULT 0.00,
  `points_earned` int(11) DEFAULT 0,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_state` varchar(100) DEFAULT NULL,
  `delivery_pincode` varchar(20) DEFAULT NULL,
  `estimated_delivery` datetime DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_orders_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ORD-A1010','Rahul Verma','9876543201','123, Golf Green, Kolkata - 700032',1280.00,'completed','2026-05-21 09:00:00',2,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(2,'ORD-A1011','Neha Sen','9876543202','Table T02',670.00,'completed','2026-05-21 13:45:00',3,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(3,'ORD-A1012','Amit Patel','9876543203','Sector 5, Salt Lake, Kolkata - 700091',1520.00,'completed','2026-05-22 07:30:00',2,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(4,'ORD-A1013','Suresh Kumar','9876543204','Table A02',930.00,'completed','2026-05-22 15:00:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(5,'ORD-A1014','Vikram Singh','9876543205','Park Street, Kolkata - 700016',2450.00,'completed','2026-05-23 07:15:00',3,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(6,'ORD-A1015','Preeti Bose','9876543206','Table G03',480.00,'completed','2026-05-23 15:30:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(7,'ORD-A1016','Rohan Gupta','9876543207','Lake Gardens, Kolkata - 700045',1100.00,'completed','2026-05-24 08:00:00',2,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(8,'ORD-A1017','Deepak Sen','9876543208','Table B01',750.00,'completed','2026-05-24 13:15:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(9,'ORD-A1018','Pooja Roy','9876543209','New Town, Kolkata - 700156',1680.00,'completed','2026-05-25 09:50:00',3,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(10,'ORD-A1019','Ravi Shankar','9876543220','Table T05',840.00,'completed','2026-05-25 16:10:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(11,'ORD-A1020','Piyush Sharma','9876543212','Bidhannagar, Kolkata - 700064',1350.00,'completed','2026-05-26 06:45:00',3,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(12,'ORD-A1021','Ananya Ray','9876543221','Table RD2',900.00,'completed','2026-05-26 14:40:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(13,'ORD-A1022','Karan Johar','9876543222','Ballygunge, Kolkata - 700019',2150.00,'completed','2026-05-27 06:00:00',2,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(14,'ORD-A1023','Test Customer','9876543211','Table T01',800.00,'preparing','2026-05-27 06:30:00',2,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(15,'ORD-A1024','Arjun Kapoor','9876543223','Table A03',770.00,'preparing','2026-05-27 06:45:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(16,'ORD-A1025','Janhvi Kapoor','9876543224','Alipore, Kolkata - 700027',950.00,'ready','2026-05-27 06:50:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(17,'ORD-A1026','Piyush Sharma','9876543212','Table G02',1260.00,'preparing','2026-05-27 06:55:00',3,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-08 09:45:33'),(19,'ORD-72033','Test User','9876543210','123 Main St, New York, NY',394.00,'pending','2026-05-26 02:18:39',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(20,'ORD-099E8','John Doe','9876543210','123, Main Street, Apt 4B, New York, Delhi - 10001, India',333.82,'pending','2026-05-26 02:20:56',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(21,'ORD-4D605','John Doe','9485206796','123, Main Street, Apt 4B, New York, Delhi - 10001, India',745.64,'pending','2026-05-26 02:22:52',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(22,'ORD-C46AD','John Doe','9485206796','123, Main Street, Apt 4B, New York, Delhi - 10001, India',804.64,'pending','2026-05-26 02:48:04',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(23,'ORD-42D3B','Test User','9485206796','123 Main St, New York, NY',394.00,'pending','2026-05-26 02:54:20',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(24,'ORD-F4239','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',804.64,'pending','2026-05-26 02:55:51',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(25,'ORD-4264A','John Doe','9485206796','123, Main Street, Apt 4B, New York, Delhi - 10001, India',804.64,'pending','2026-05-26 02:59:24',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(26,'ORD-5D49C','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',745.64,'pending','2026-05-26 03:19:10',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(27,'ORD-17C6B','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',1510.28,'pending','2026-05-26 03:23:53',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(28,'ORD-E2919','John Doe','9855669331','123, Main Street, Apt 4B, New York, Delhi - 10001, India',804.64,'pending','2026-05-26 03:26:35',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(29,'ORD-E71DB','John Doe','9855669331','123, Main Street, Apt 4B, New York, Delhi - 10001, India',58.70,'pending','2026-05-26 03:27:18',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(30,'ORD-ADDED','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',804.64,'pending','2026-05-26 03:29:07',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(31,'ORD-9B83B','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',58.70,'pending','2026-05-26 03:46:47',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(32,'ORD-068CF','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',745.64,'pending','2026-05-26 03:50:00',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(33,'ORD-32342','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',58.70,'pending','2026-05-26 05:46:38',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(34,'ORD-E7011','John Doe','9876543210','123, Main Street, Apt 4B, New York, Delhi - 10001, India',745.64,'pending','2026-05-26 05:49:31',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(35,'ORD-60507','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',333.82,'pending','2026-05-26 05:50:43',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(36,'ORD-EAAFE','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',486.04,'pending','2026-05-27 02:22:36',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(37,'ORD-E29D0','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',58.70,'pending','2026-05-27 04:00:11',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(38,'ORD-209AF','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',58.70,'pending','2026-05-27 04:09:30',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(39,'ORD-27F9A','ramu Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',2459.00,'pending','2026-05-27 04:25:51',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(40,'ORD-861E9','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',1810.00,'pending','2026-05-27 04:38:29',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(41,'ORD-7777F','John Doe','7814585430','123, Main Street, Apt 4B, New York, Delhi - 10001, India',984.00,'pending','2026-05-27 04:47:29',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(42,'ORD-A6F58','John Doe','9876543210','123, Main Street, Apt 4B, New York, Delhi - 10001, India',984.00,'pending','2026-05-27 06:34:28',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(43,'ORD-CB262','rahul Bhatt','7814585430','123, Main Street, New York, Delhi - 10001, India',984.00,'pending','2026-05-27 07:41:46',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(44,'ORD-AC198','System Admin','7814585430','Table T03, gshg;iofdhg, chd, Delhi - 10001, India',58.70,'pending','2026-05-27 07:53:12',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(45,'ORD-E2276','System Admin','7814585430','gshg;iofdhg, chd, Delhi - 10001, India',783.40,'pending','2026-05-27 07:54:35',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(46,'ORD-EB956','System Admin','7814585430','php, chd, Delhi - 10001, India',783.40,'pending','2026-05-27 07:56:12',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(47,'ORD-A2487','System Admin','7814585430','rajsri, psd, Delhi - 10001, India',2140.40,'pending','2026-05-27 07:58:42',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(48,'ORD-9E1B9','Ramu fgd','7814585430','123, Main Street, Apt 4B, chd, Delhi - 10001, India',1456.00,'pending','2026-05-27 08:26:42',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(49,'ORD-A5DF9','Test Customer','9876543211','123, Main Street, Apt 4B, chd, Delhi - 10001, India',1043.00,'pending','2026-05-27 09:10:43',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(50,'ORD-89B48','Test Customer','7814585430','123, Main Street, Apt 4B, chd, Delhi - 10001, India',1043.00,'pending','2026-05-27 09:11:28',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(51,'ORD-29934','Test Customer','7814585430','123, Main Street, Apt 4B, chd, Delhi - 10001, India',901.40,'pending','2026-05-29 05:33:20',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21'),(52,'ORD-B3D66','John Doe Third','9876543223','123 Artisanal Way, Chandigarh, Delhi - 160017, India',1900.06,'pending','2026-06-06 03:52:03',NULL,0.00,0.00,0.00,0,0.00,0,'Cash',NULL,NULL,NULL,NULL,NULL,'pending','2026-06-09 10:04:21');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `guests` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `special_request` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservations`
--

LOCK TABLES `reservations` WRITE;
/*!40000 ALTER TABLE `reservations` DISABLE KEYS */;
INSERT INTO `reservations` VALUES (1,'Vikram Malhotra','9876543230',4,'2026-05-27','19:30:00','Window seat preferred','confirmed','2026-06-08 09:43:53'),(2,'Shreya Ghoshal','9876543231',2,'2026-05-27','21:00:00','Birthday celebration','pending','2026-06-08 09:43:53'),(3,'Abhishek Bachchan','9876543232',8,'2026-05-28','20:00:00','Quiet booth area','confirmed','2026-06-08 09:43:53'),(4,'Ranbir Kapoor','9876543233',2,'2026-05-28','22:30:00','Vegetarian food menu only','pending','2026-06-08 09:43:53');
/*!40000 ALTER TABLE `reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reward_points`
--

DROP TABLE IF EXISTS `reward_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reward_points` (
  `user_id` int(11) NOT NULL,
  `points_earned` int(11) DEFAULT 0,
  `points_redeemed` int(11) DEFAULT 0,
  `points_deducted` int(11) DEFAULT 0,
  `current_balance` int(11) DEFAULT 0,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_rewards_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reward_points`
--

LOCK TABLES `reward_points` WRITE;
/*!40000 ALTER TABLE `reward_points` DISABLE KEYS */;
INSERT INTO `reward_points` VALUES (1,0,0,0,0),(2,0,0,0,0),(3,0,0,0,0),(4,0,0,0,0);
/*!40000 ALTER TABLE `reward_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_requests`
--

DROP TABLE IF EXISTS `support_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_support_requests_users` (`user_id`),
  CONSTRAINT `fk_support_requests_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_requests`
--

LOCK TABLES `support_requests` WRITE;
/*!40000 ALTER TABLE `support_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `table_bookings`
--

DROP TABLE IF EXISTS `table_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `table_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `guests` int(11) NOT NULL,
  `table_number` varchar(50) DEFAULT NULL,
  `special_requests` text DEFAULT NULL,
  `venue_name` varchar(255) NOT NULL DEFAULT 'Medusa',
  `venue_address` varchar(255) NOT NULL DEFAULT 'SCO 44,45, District One Market, Sector 68, Sahibzada Ajit Singh Nagar, Punjab 140308',
  `venue_phone` varchar(50) NOT NULL DEFAULT '+91 94272 72798',
  `status` varchar(50) NOT NULL DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `table_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `table_bookings`
--

LOCK TABLES `table_bookings` WRITE;
/*!40000 ALTER TABLE `table_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `table_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tier_history`
--

DROP TABLE IF EXISTS `tier_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tier_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `previous_tier_id` int(11) NOT NULL,
  `new_tier_id` int(11) NOT NULL,
  `change_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_history_users` (`user_id`),
  CONSTRAINT `fk_history_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tier_history`
--

LOCK TABLES `tier_history` WRITE;
/*!40000 ALTER TABLE `tier_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `tier_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `street` varchar(255) NOT NULL,
  `apartment` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `zip` varchar(20) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_addresses_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_addresses`
--

LOCK TABLES `user_addresses` WRITE;
/*!40000 ALTER TABLE `user_addresses` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notifications_users` (`user_id`),
  CONSTRAINT `fk_notifications_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_notifications`
--

LOCK TABLES `user_notifications` WRITE;
/*!40000 ALTER TABLE `user_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_settings`
--

DROP TABLE IF EXISTS `user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 1,
  `promotional_offers` tinyint(1) DEFAULT 1,
  `privacy_mode` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `theme` varchar(10) DEFAULT 'dark',
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_settings_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_settings`
--

LOCK TABLES `user_settings` WRITE;
/*!40000 ALTER TABLE `user_settings` DISABLE KEYS */;
INSERT INTO `user_settings` VALUES (2,1,1,1,0,'en','dark');
/*!40000 ALTER TABLE `user_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `profile_pic` varchar(255) DEFAULT NULL,
  `current_tier_id` int(11) DEFAULT 1,
  `last_inactivity_check` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `address` varchar(255) DEFAULT '',
  `city` varchar(100) DEFAULT '',
  `state` varchar(100) DEFAULT '',
  `pincode` varchar(20) DEFAULT '',
  `email_otp` varchar(10) DEFAULT NULL,
  `phone_otp` varchar(10) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_email_verified` tinyint(1) DEFAULT 1,
  `is_phone_verified` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `session_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_tier` (`current_tier_id`),
  CONSTRAINT `fk_users_tier` FOREIGN KEY (`current_tier_id`) REFERENCES `customer_tiers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System Admin','admin@example.com','$2y$10$rRRN5vJIWZltYTywQAgxReBWCWazX1HeadB7ktTjAdxGceY8QJQoG','9876543210','admin',NULL,1,NULL,'2026-06-08 09:43:53','','','','',NULL,NULL,NULL,1,1,1,'6f01cc9138cffee8c816d31e1e784510d0e85ee87b97d53cd3901cd105d971d4'),(2,'Test Customer','customer@example.com','$2y$10$hdslkG6tN7/j4ja6N63Js.3ImHeNwDwYZ0T5eAhBiqIq7MrGVWHYa','9876543211','customer',NULL,1,NULL,'2026-06-08 09:43:53','','','','',NULL,NULL,NULL,1,1,1,'6f28de34935e9f23051313ddaf60baaeea900e550cbd215e4aa6c5d7edd997bb'),(3,'Piyush Sharma','piyush@example.com','$2y$10$hdslkG6tN7/j4ja6N63Js.3ImHeNwDwYZ0T5eAhBiqIq7MrGVWHYa','9876543212','customer',NULL,1,NULL,'2026-06-08 09:43:53','','','','',NULL,NULL,NULL,1,1,1,NULL),(4,'Harshit Kumar','harshitk80091@gmail.com','$2y$10$3vwdcXeyQKvUCrQF2XHX5u14F1BKRmw4z.uj/gI/Mo7Xs.wjyJiFW','7973667447','customer',NULL,1,NULL,'2026-06-08 12:40:45','','','','',NULL,NULL,NULL,1,1,1,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-11 11:08:48
