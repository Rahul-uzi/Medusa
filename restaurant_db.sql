-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2026 at 07:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12
<<<<<<< Updated upstream

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
=======
>>>>>>> Stashed changes

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

<<<<<<< Updated upstream
=======

>>>>>>> Stashed changes
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `restaurant_db`
--
<<<<<<< Updated upstream
DROP DATABASE IF EXISTS `restaurant_db`;
=======
>>>>>>> Stashed changes
CREATE DATABASE IF NOT EXISTS `restaurant_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `restaurant_db`;

-- --------------------------------------------------------

--
-- Table structure for table `career_applications`
--

DROP TABLE IF EXISTS `career_applications`;
CREATE TABLE `career_applications` (
  `id` int(11) NOT NULL,
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
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `food_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `food_item_id`, `quantity`) VALUES
(1, 2, 8, 1),
(2, 2, 25, 2),
(3, 3, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `user_id`, `review_id`, `coupon_code`, `campaign_code`, `discount_type`, `discount_value`, `expires_at`, `redeemed_at`, `order_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 6, 1, '5STAR-SUMMER2026-BBB96A83', 'SUMMER2026', 'percentage', 10.00, '2026-07-06 09:23:15', NULL, NULL, 'active', '2026-06-06 07:23:15', '2026-06-06 07:23:15'),
(2, 2, 3, '5STAR-SUMMER2026-230781DC', 'SUMMER2026', 'percentage', 10.00, '2026-07-08 14:19:16', NULL, NULL, 'active', '2026-06-08 12:19:16', '2026-06-08 12:19:16'),
(3, NULL, 4, '5STAR-SUMMER2026-BECF2EC9', 'SUMMER2026', 'percentage', 10.00, '2026-07-08 20:09:29', NULL, NULL, 'active', '2026-06-08 18:09:29', '2026-06-08 18:09:29'),
(4, 12, 5, '5STAR-SUMMER2026-723408D7', 'SUMMER2026', 'percentage', 10.00, '2026-07-09 13:01:36', NULL, NULL, 'active', '2026-06-09 11:01:36', '2026-06-09 11:01:36'),
(5, 2, 6, '5STAR-SUMMER2026-49163668', 'SUMMER2026', 'percentage', 10.00, '2026-07-09 14:52:10', NULL, NULL, 'active', '2026-06-09 12:52:10', '2026-06-09 12:52:10'),
(6, 2, 7, '5STAR-SUMMER2026-BC41BD48', 'SUMMER2026', 'percentage', 10.00, '2026-07-09 14:55:35', NULL, NULL, 'active', '2026-06-09 12:55:35', '2026-06-09 12:55:35');

-- --------------------------------------------------------

--
-- Table structure for table `customer_tiers`
--

DROP TABLE IF EXISTS `customer_tiers`;
CREATE TABLE `customer_tiers` (
  `id` int(11) NOT NULL,
  `tier_name` varchar(50) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 10.00,
  `spending_requirement` decimal(10,2) NOT NULL DEFAULT 0.00,
  `points_earning_percent` decimal(5,2) NOT NULL DEFAULT 2.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer_tiers`
--

INSERT INTO `customer_tiers` (`id`, `tier_name`, `discount_percent`, `spending_requirement`, `points_earning_percent`) VALUES
(1, 'Bronze', 10.00, 0.00, 2.00),
(2, 'Silver', 15.00, 25000.00, 2.00),
(3, 'Gold', 20.00, 75000.00, 2.00);

-- --------------------------------------------------------

--
-- Table structure for table `dish_customizations`
--

DROP TABLE IF EXISTS `dish_customizations`;
CREATE TABLE `dish_customizations` (
  `id` int(11) NOT NULL,
  `food_item_id` int(11) NOT NULL,
  `group_name` varchar(100) NOT NULL COMMENT 'e.g. Crust Type, Size, Toppings',
  `group_type` enum('single','multiple') DEFAULT 'single' COMMENT 'single=radio, multiple=checkbox',
  `is_required` tinyint(1) DEFAULT 0 COMMENT '1=must pick an option, 0=optional',
  `options_json` text NOT NULL COMMENT 'JSON array: [{label, price_add}]',
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dish_customizations`
--

INSERT INTO `dish_customizations` (`id`, `food_item_id`, `group_name`, `group_type`, `is_required`, `options_json`, `sort_order`) VALUES
(1, 8, 'Pizza Size', 'single', 1, '[{\"label\":\"Regular (8\")\",\"price_add\":0},{\"label\":\"Medium (10\")\",\"price_add\":80},{\"label\":\"Large (12\")\",\"price_add\":150}]', 1),
(2, 8, 'Crust Type', 'single', 1, '[{\"label\":\"Thin Crust\",\"price_add\":0},{\"label\":\"Thick Crust\",\"price_add\":30},{\"label\":\"Stuffed Cheese Crust\",\"price_add\":70}]', 2),
(3, 8, 'Extra Toppings', 'multiple', 0, '[{\"label\":\"Extra Mozzarella\",\"price_add\":60},{\"label\":\"Mushrooms\",\"price_add\":40},{\"label\":\"Olives\",\"price_add\":40},{\"label\":\"Jalapeños\",\"price_add\":30}]', 3),
(4, 9, 'Pizza Size', 'single', 1, '[{\"label\":\"Regular (8\")\",\"price_add\":0},{\"label\":\"Medium (10\")\",\"price_add\":80},{\"label\":\"Large (12\")\",\"price_add\":150}]', 1),
(5, 9, 'Crust Type', 'single', 1, '[{\"label\":\"Thin Crust\",\"price_add\":0},{\"label\":\"Thick Crust\",\"price_add\":30},{\"label\":\"Stuffed Cheese Crust\",\"price_add\":70}]', 2),
(6, 9, 'Extra Toppings', 'multiple', 0, '[{\"label\":\"Double Pepperoni\",\"price_add\":80},{\"label\":\"Extra Mozzarella\",\"price_add\":60},{\"label\":\"Bell Peppers\",\"price_add\":40},{\"label\":\"Jalapeños\",\"price_add\":30}]', 3),
(7, 19, 'Patty Type', 'single', 1, '[{\"label\":\"Chicken Patty\",\"price_add\":0},{\"label\":\"Beef Patty\",\"price_add\":50},{\"label\":\"Veggie Patty\",\"price_add\":-30}]', 1),
(8, 19, 'Serving Size', 'single', 1, '[{\"label\":\"Single\",\"price_add\":0},{\"label\":\"Double\",\"price_add\":100}]', 2),
(9, 19, 'Add-ons', 'multiple', 0, '[{\"label\":\"Extra Cheese\",\"price_add\":40},{\"label\":\"Bacon Strip\",\"price_add\":60},{\"label\":\"Fried Egg\",\"price_add\":40},{\"label\":\"Avocado\",\"price_add\":50}]', 3),
(10, 17, 'Broth Base', 'single', 1, '[{\"label\":\"Tonkotsu (Pork)\",\"price_add\":0},{\"label\":\"Shoyu (Soy)\",\"price_add\":0},{\"label\":\"Vegan Miso\",\"price_add\":0}]', 1),
(11, 17, 'Spice Level', 'single', 1, '[{\"label\":\"Mild\",\"price_add\":0},{\"label\":\"Medium\",\"price_add\":0},{\"label\":\"Hot 🌶\",\"price_add\":0},{\"label\":\"Extra Hot 🌶🌶\",\"price_add\":0}]', 2),
(12, 17, 'Add-ons', 'multiple', 0, '[{\"label\":\"Extra Chashu Pork\",\"price_add\":80},{\"label\":\"Extra Egg\",\"price_add\":30},{\"label\":\"Extra Noodles\",\"price_add\":40}]', 3),
(13, 15, 'Protein Choice', 'single', 1, '[{\"label\":\"Chicken\",\"price_add\":0},{\"label\":\"Prawns\",\"price_add\":80},{\"label\":\"Tofu (Veg)\",\"price_add\":-30}]', 1),
(14, 15, 'Spice Level', 'single', 0, '[{\"label\":\"Mild\",\"price_add\":0},{\"label\":\"Medium\",\"price_add\":0},{\"label\":\"Hot 🌶\",\"price_add\":0}]', 2),
(15, 22, 'Sauce Choice', 'single', 1, '[{\"label\":\"Buffalo Hot\",\"price_add\":0},{\"label\":\"BBQ Smoky\",\"price_add\":0},{\"label\":\"Honey Garlic\",\"price_add\":0},{\"label\":\"Lemon Pepper\",\"price_add\":0}]', 1),
(16, 22, 'Serving Size', 'single', 1, '[{\"label\":\"6 Pcs\",\"price_add\":0},{\"label\":\"12 Pcs\",\"price_add\":350},{\"label\":\"18 Pcs\",\"price_add\":680}]', 2),
(17, 26, 'Flavor', 'single', 1, '[{\"label\":\"Vanilla\",\"price_add\":0},{\"label\":\"Chocolate\",\"price_add\":0},{\"label\":\"Strawberry\",\"price_add\":0},{\"label\":\"Mixed Berry\",\"price_add\":0}]', 1),
(18, 26, 'Toppings', 'multiple', 0, '[{\"label\":\"Hot Fudge\",\"price_add\":30},{\"label\":\"Caramel Drizzle\",\"price_add\":30},{\"label\":\"Crushed Oreo\",\"price_add\":40},{\"label\":\"Rainbow Sprinkles\",\"price_add\":20}]', 2),
(19, 27, 'Flavor Selection', 'single', 1, '[{\"label\":\"Strawberry\",\"price_add\":0},{\"label\":\"Matcha Green Tea\",\"price_add\":0},{\"label\":\"Mango\",\"price_add\":0},{\"label\":\"Cookies & Cream\",\"price_add\":0}]', 1);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_number` varchar(20) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  `review` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'order',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `order_number`, `rating`, `review`, `type`, `created_at`) VALUES
(1, NULL, 'ORD-B3D66', 5, '', 'order', '2026-06-06 07:23:14'),
(2, 2, NULL, 5, 'Outstanding dining!', 'general', '2026-06-06 13:29:15'),
(3, NULL, 'ORD-9F36E', 5, '', 'order', '2026-06-08 12:19:16'),
(4, NULL, 'ORD-2F383', 5, '', 'order', '2026-06-08 18:09:29'),
(5, NULL, 'ORD-BCE13', 5, '', 'order', '2026-06-09 11:01:36'),
(6, NULL, 'ORD-E930F', 5, 'cfhfu', 'order', '2026-06-09 12:52:10'),
(7, NULL, 'ORD-4E339', 5, 'gyyuf', 'order', '2026-06-09 12:55:35');

-- --------------------------------------------------------

--
-- Table structure for table `food_items`
--

DROP TABLE IF EXISTS `food_items`;
CREATE TABLE `food_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT 'default.jpg',
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_items`
--

INSERT INTO `food_items` (`id`, `name`, `description`, `price`, `category`, `image_url`, `is_available`) VALUES
(1, 'Cream of Mushroom', 'Roasted button mushrooms, celery, leeks, garlic, and parmesan foam', 425.00, 'Soups', '', 1),
(2, 'Manchow Soup', 'Veg / chicken / prawns — mild asian minced vegetable soup with crispy noodles', 425.00, 'Soups', '', 1),
(3, 'Vietnamese Pho Soup', 'Veg / chicken — flat vietnamese noodles simmered in aromatic broth', 425.00, 'Soups', '', 1),
(4, 'Hot & Sour Soup', 'Veg / chicken / prawns — bamboo shoots, mushrooms, chinese cabbage in a thick and spicy soup', 425.00, 'Soups', '', 1),
(5, 'Sweet Corn Soup', 'Veg / chicken — delicious kernels of corn in a thick, luscious soup', 425.00, 'Soups', '', 1),
(6, 'Lemon Coriander Soup', 'Veg / chicken — healthy and filling lemon coriander soup', 425.00, 'Soups', '', 1),
(7, 'Roasted Tomato Basil Soup', 'Oven-roasted plum tomato soup with a fresh hint of basil', 425.00, 'Soups', '', 1),
(8, 'Tom Yum', 'Veg / chicken / prawns — lemongrass, kaffir lime, and galangal shine through', 425.00, 'Soups', '', 1),
(9, 'Murgh Yakhani Shorba', 'Slow-cooked rich chicken soup accentuated with saffron', 445.00, 'Soups', '', 1),
(10, 'Burrata and Berry Salad', 'Strawberries, blueberries, and californian grapes tossed in mixed green lettuce', 645.00, 'Salad', '', 1),
(11, 'Classical Caesar Salad', 'Veg / chicken / prawns — crispy romaine lettuce tossed in caesar dressing with garlic croutons and parmesan', 425.00, 'Salad', '', 1),
(12, 'Middle Eastern Salad Bowl', 'Veg / chicken — beetroot fattoush, whole wheat pita, grilled sumac vegetables', 545.00, 'Salad', '', 1),
(13, 'Healthy Organic Quinoa', 'Mixed lettuce with organic quinoa, lemon mustard dressing, and green apple', 525.00, 'Salad', '', 1),
(14, 'Som Tom Salad', 'Raw papaya, sweet chili sauce, crushed peanuts, and basil', 465.00, 'Salad', '', 1),
(15, 'Classical Greek Salad', 'Baby cucumber, tomatoes, bell peppers, onions, and feta cheese in homemade dressing', 425.00, 'Salad', '', 1),
(16, 'Tandoori Roti', 'Plain / butter', 45.00, 'Bread Basket', '', 1),
(17, 'Laccha Paratha', 'Plain / butter / mirchi', 55.00, 'Bread Basket', '', 1),
(18, 'Naan', 'Plain / butter / missi roti / garlic parmesan / olive naan', 85.00, 'Bread Basket', '', 1),
(19, 'Stuffed Naan', 'Aloo / onion / paneer / keema', 125.00, 'Bread Basket', '', 1),
(20, 'Indian Green Salad', 'Cucumber, onion, tomato, carrot, and green chilli', 225.00, 'Sides', '', 1),
(21, 'Pappad', '', 125.00, 'Sides', '', 1),
(22, 'Raita', 'Mix / pineapple / boondi raita', 55.00, 'Sides', '', 1),
(23, 'Seoul Bibimbap', 'Veg / chicken — simmering rice with vegetables or chicken, served in traditional dolsot', 585.00, 'Meals in the Bowl', '', 1),
(24, 'Morelos Mexican Burrito Bowl', 'Veg / chicken — hearty bowl with mexican flair, packed with flavor', 585.00, 'Meals in the Bowl', '', 1),
(25, 'Shanghai Bowl', 'Crispy chicken, sesame-sweet soy sauce, and sticky rice', 585.00, 'Meals in the Bowl', '', 1),
(26, 'Gong Bao Bowl', 'Crispy cottage cheese, chilli tomatoes, and soy sauce', 585.00, 'Meals in the Bowl', '', 1),
(27, 'Burmese Khao Suey', 'Veg / chicken — soft and crispy noodles in a fragrant coconut broth', 525.00, 'Meals in the Bowl', '', 1),
(28, 'Atlantic Salmon', 'Grilled norwegian salmon fillet served with seasonal vegetables and a lemon butter glaze', 2550.00, 'Main Course', '', 1),
(29, 'Australian Lamb Chops', 'Char-grilled lamb chops marinated in herbs and spices, served with garlic mash and jus', 2550.00, 'Main Course', '', 1),
(30, 'Gulf Tiger Prawns', 'Succulent tiger prawns grilled with lemon, ajwain, and coastal-style tandoori spices', 1650.00, 'Main Course', '', 1),
(31, 'Grilled Chicken Breast', 'Served with sautéed vegetables, garlic mashed potato, and red wine jus', 625.00, 'Main Course', '', 1),
(32, 'Portuguese Peri Roast Chicken', 'Herbed and peri peri-marinated chicken breast with sautéed vegetables', 625.00, 'Main Course', '', 1),
(33, 'Malai Paneer Steak', 'Stuffed with sautéed spinach, served with wasabi mash and vegetables', 465.00, 'Main Course', '', 1),
(34, 'Grilled River Sole Fish', 'Sautéed vegetables, garlic mashed potato, and lemon butter sauce', 625.00, 'Main Course', '', 1),
(35, 'Steam Ginger Fish', 'Healthy steamed fish served with stir-fried vegetables', 625.00, 'Main Course', '', 1),
(36, 'Peri Peri Grilled Fish', 'Peri peri-marinated fish with exotic vegetables', 625.00, 'Main Course', '', 1),
(37, 'Pad Thai Noodle', 'Veg / chicken — flat rice noodles with your choice of vegetables or chicken', 485.00, 'Main Course', '', 1),
(38, 'Himalayan Thukpa', 'Veg / chicken — comforting bowl of noodles and vegetables in a savory broth', 395.00, 'Main Course', '', 1),
(39, 'Minced Basil Chicken', 'Thai-style minced basil chicken served with rice', 525.00, 'Main Course', '', 1),
(40, 'Thai Curry Green | Red', 'Veg / chicken — authentic thai curry served with rice, choose green or red', 490.00, 'Main Course', '', 1),
(41, 'Kerala Fish Curry', 'Classic south indian style fish curry, just as flavorful as expected', 690.00, 'Main Course', '', 1),
(42, 'Hakka Noodle', 'Available as veg / chicken / prawns', 0.00, 'Choice of Noodle', '', 1),
(43, 'Chilli Garlic Noodle', 'Available as veg / chicken / prawns', 0.00, 'Choice of Noodle', '', 1),
(44, 'Burn Garlic Noodle', 'Available as veg / chicken / prawns', 0.00, 'Choice of Noodle', '', 1),
(45, 'Fried Rice', 'Veg / chicken / prawns', 0.00, 'Choice of Rice', '', 1),
(46, 'Chilli Garlic Fried Rice', 'Veg / chicken / prawns', 0.00, 'Choice of Rice', '', 1),
(47, 'Burn Garlic Fried Rice', 'Veg / chicken / prawns', 0.00, 'Choice of Rice', '', 1),
(48, 'Plain Basmati Rice', '', 0.00, 'Choice of Rice', '', 1),
(49, 'Hot Garlic Sauce', '', 0.00, 'Choice of Gravy', '', 1),
(50, 'Szechuan Sauce', '', 0.00, 'Choice of Gravy', '', 1),
(51, 'Black Bean Sauce', '', 0.00, 'Choice of Gravy', '', 1),
(52, 'Thai Basil Sauce', '', 0.00, 'Choice of Gravy', '', 1),
(53, 'Mushroom and Truffle', 'Steamed dim sum', 475.00, 'Dim Sum Cart', '', 1),
(54, 'Asparagus, Water Chestnut and Corn', 'Steamed dim sum', 495.00, 'Dim Sum Cart', '', 1),
(55, 'Vegetable Crystal Dumpling', 'Translucent crystal skin dumpling', 475.00, 'Dim Sum Cart', '', 1),
(56, 'Edamame Cheese', 'Steamed dim sum', 495.00, 'Dim Sum Cart', '', 1),
(57, 'Chilli Garlic Greens', 'Steamed dim sum', 525.00, 'Dim Sum Cart', '', 1),
(58, 'Spicy Chilli Oil Dumpling', 'Steamed dim sum', 545.00, 'Dim Sum Cart', '', 1),
(59, 'Chicken and Coriander Dumpling', 'Steamed dim sum', 525.00, 'Dim Sum Cart', '', 1),
(60, 'Prawns Harkao', 'Classic prawn dumpling', 575.00, 'Dim Sum Cart', '', 1),
(61, 'Mushroom Kra Pao Bao', 'Steamed bao bun', 525.00, 'Dim Sum Cart', '', 1),
(62, 'Crushed Pepper Tofu Bao', 'Steamed bao bun', 525.00, 'Dim Sum Cart', '', 1),
(63, 'Korean Chicken Bao', 'Steamed bao bun', 545.00, 'Dim Sum Cart', '', 1),
(64, 'Crispy Prawns Bao', 'Steamed bao bun', 575.00, 'Dim Sum Cart', '', 1),
(65, 'Yasai Maki Roll', 'Lettuce, thai cucumber, cream cheese, and pickles', 585.00, 'Sushi Rolls', '', 1),
(66, 'Tempura Asparagus', 'Philadelphia cheese, dill leaves, and crispy asparagus', 595.00, 'Sushi Rolls', '', 1),
(67, 'Cucumber and Avocado Roll', 'Thai baby cucumber, avocado, tenkasu, and spicy mayo', 645.00, 'Sushi Rolls', '', 1),
(68, 'California Mango Roll', 'Fresh mangoes, wasabi, japanese mayo, and pickled ginger', 595.00, 'Sushi Rolls', '', 1),
(69, 'Tuna Roll', 'Fresh tuna, creamy avocado, and spicy mayo wrapped in seasoned sushi rice', 625.00, 'Sushi Rolls', '', 1),
(70, 'Salmon Roll', 'Medusa\'s signature roll for salmon lovers', 725.00, 'Sushi Rolls', '', 1),
(71, 'Dragon Crispy Chicken Sushi Roll', 'Crispy fried chicken, spicy mayo, wasabi, and pickled ginger', 595.00, 'Sushi Rolls', '', 1),
(72, 'Sakura Roll', 'Prawn tempura, thai cucumber, and spicy mayo', 645.00, 'Sushi Rolls', '', 1),
(73, 'Mushroom Burger', 'A classic mushroom melt with organic himalayan cheese', 365.00, 'Burgers & Sandwiches', '', 1),
(74, 'Patty Lamb Burger', 'Young lamb pulled meat patty char-grilled to perfection', 475.00, 'Burgers & Sandwiches', '', 1),
(75, 'Tex Mex Chicken Burger', 'Spicy golden-fried chicken patty with our famous king sauce', 395.00, 'Burgers & Sandwiches', '', 1),
(76, 'Crispy Fish Burger', 'Panko-crusted fish fillet with lettuce, tartar sauce, and house slaw in a toasted bun', 450.00, 'Burgers & Sandwiches', '', 1),
(77, 'Pesto Chicken Croissant Sandwich', 'Roast chicken marinated in homemade pesto', 465.00, 'Burgers & Sandwiches', '', 1),
(78, 'Smokey Paneer Croissant Sandwich', 'Punjabi-style tandoori paneer stuffed in buttery croissant', 455.00, 'Burgers & Sandwiches', '', 1),
(79, 'Chicken Spicy Tikka Sandwich', 'Cucumber, tomato, lettuce, and charred chicken tikka in multigrain bread', 465.00, 'Burgers & Sandwiches', '', 1),
(80, 'Tandoori Paneer Sandwich', 'Cucumber, tomato, lettuce, and charred paneer tikka in multigrain bread', 445.00, 'Burgers & Sandwiches', '', 1),
(81, 'Triple Club Sandwich', 'Pulled roast chicken, stone-ground dill pesto, served with house chips', 485.00, 'Burgers & Sandwiches', '', 1),
(82, 'Green Club Sandwich', 'Multigrain bread overloaded with greens and cheese', 455.00, 'Burgers & Sandwiches', '', 1),
(83, 'Vegetarian Mezze Platter', 'Falafel, paneer shashlik, baba ghanoush, hummus, muhammara, pickled vegetables, and mini soft pita', 665.00, 'Sharing Boards', '', 1),
(84, 'Non-Vegetarian Mezze Platter', 'Fish shish, aamb adana, chicken and cheese adana, chicken shish, and assorted cold dips', 785.00, 'Sharing Boards', '', 1),
(85, 'Tandoori Veg Platter', 'Paneer tikka, veg galouti, tandoori pineapple, and dahi kebab', 655.00, 'Sharing Boards', '', 1),
(86, 'Tandoori Non-Veg Platter', 'Malai chicken tikka, chicken tikka, chicken seekh kebab, mutton kebab, and fish tikka', 785.00, 'Sharing Boards', '', 1),
(87, 'Margherita', 'As simple and classic as you know', 545.00, 'Brick Oven Pizza', '', 1),
(88, 'Paneer Makhni King', 'A favorite — makhni sauce topped with tandoori paneer tikka', 595.00, 'Brick Oven Pizza', '', 1),
(89, 'Andhara Mushroom Sukka', 'A south indian twist — mushroom sukka on pizza', 625.00, 'Brick Oven Pizza', '', 1),
(90, 'Garden Fresh Pizza', 'Mixed bell peppers, broccoli, mushrooms, red onions, and mozzarella', 525.00, 'Brick Oven Pizza', '', 1),
(91, 'Burrata Arugula', 'Classic burrata pizza with fresh arugula and house-roasted tomatoes', 610.00, 'Brick Oven Pizza', '', 1),
(92, 'Mushroom Pizza', 'Shiitake and button mushrooms roasted in the oven, with a perfect blend of mozzarella and cheddar', 545.00, 'Brick Oven Pizza', '', 1),
(93, 'Chicken Makhni King', 'Medusa\'s own recipe — a must-try!', 625.00, 'Brick Oven Pizza', '', 1),
(94, 'Sausage Masala King', 'Old delhi favorite recipe', 595.00, 'Brick Oven Pizza', '', 1),
(95, 'Pepperoni Pizza', 'Minced lamb tenderloin, chargrilled and topped with mozzarella and cheddar cheese', 625.00, 'Brick Oven Pizza', '', 1),
(96, 'Meat Balls', 'Rogan josh gravy with meatballs on pizza — bold and flavorful', 695.00, 'Brick Oven Pizza', '', 1),
(97, 'Chilli Basil Chicken', 'Wok-tossed chicken in spicy chilli basil sauce', 465.00, 'Non-Veg Appetizer', '', 1),
(98, 'Mutton Keema Pav', 'Street-style mutton keema served with buttered pav', 0.00, 'Non-Veg Appetizer', '', 1),
<<<<<<< Updated upstream
(99, 'Johnnie Walker Black Label', 'Premium Scotch Whisky aged 12 years', 4500.00, 'Liquor', 'https://images.unsplash.com/photo-1527061011665-3652c757a4d4?w=400&h=300&fit=crop', 1),
(100, 'Jack Daniel\'s Tennessee Whiskey', 'Smooth Tennessee sour mash whiskey', 3800.00, 'Liquor', 'https://images.unsplash.com/photo-1527061011665-3652c757a4d4?w=400&h=300&fit=crop', 1),
(101, 'Absolut Vodka', 'Classic Swedish premium vodka', 3200.00, 'Liquor', 'https://images.unsplash.com/photo-1551538827-9c037cb4f32a?w=400&h=300&fit=crop', 1),
(102, 'Bacardi White Rum', 'Light-bodied rum with subtle sweetness', 2800.00, 'Liquor', 'https://images.unsplash.com/photo-1551538827-9c037cb4f32a?w=400&h=300&fit=crop', 1),
(103, 'Hendrick\'s Artisanal Gin', 'Scottish gin infused with rose and cucumber', 4200.00, 'Liquor', 'https://images.unsplash.com/photo-1551538827-9c037cb4f32a?w=400&h=300&fit=crop', 1);
=======
(99, 'Johnnie Walker Black Label', 'Premium Scotch Whisky aged 12 years', 4500.00, 'Liquor', 'johnnie_walker.png', 1),
(100, 'Jack Daniel\'s Tennessee Whiskey', 'Smooth Tennessee sour mash whiskey', 3800.00, 'Liquor', 'jack_daniels.png', 1),
(101, 'Absolut Vodka', 'Classic Swedish premium vodka', 3200.00, 'Liquor', 'absolut_vodka.png', 1),
(102, 'Bacardi White Rum', 'Light-bodied rum with subtle sweetness', 2800.00, 'Liquor', 'bacardi_rum.png', 1),
(103, 'Hendrick\'s Artisanal Gin', 'Scottish gin infused with rose and cucumber', 4200.00, 'Liquor', 'hendricks_gin.png', 1);
>>>>>>> Stashed changes

-- --------------------------------------------------------

--
-- Table structure for table `login_activity_logs`
--

DROP TABLE IF EXISTS `login_activity_logs`;
CREATE TABLE `login_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'success'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_activity_logs`
--

INSERT INTO `login_activity_logs` (`id`, `user_id`, `ip_address`, `user_agent`, `login_time`, `status`) VALUES
(1, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 13:25:14', 'success'),
(2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 14:16:54', 'success'),
(3, 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-06 14:30:50', 'success'),
(4, 1, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-06-06 14:41:35', 'success'),
(5, 2, '::1', 'Unknown', '2026-06-06 15:04:43', 'success'),
(6, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 15:05:08', 'success'),
(7, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 15:15:54', 'success'),
(8, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 15:16:02', 'success'),
(9, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-06 15:16:14', 'success'),
(10, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 04:48:54', 'success'),
(11, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 04:55:10', 'success'),
(12, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 04:57:17', 'success'),
(13, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 05:07:00', 'success'),
(14, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 05:29:26', 'success'),
(15, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 05:45:01', 'success'),
(16, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 06:01:49', 'success'),
(17, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 06:03:07', 'success'),
(18, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 06:24:23', 'success'),
(19, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 09:49:22', 'success'),
(20, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 10:37:13', 'success'),
(21, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 12:22:09', 'success'),
(22, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 12:44:15', 'success'),
(23, 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 16:58:10', 'success'),
(24, 10, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 16:58:32', 'success'),
(25, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 17:14:34', 'success'),
(27, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-08 18:10:22', 'success'),
(28, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 07:43:18', 'success'),
(29, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 07:48:09', 'success'),
(30, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 07:48:27', 'success'),
(31, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 07:48:37', 'success'),
(32, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 07:48:52', 'success'),
(33, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 07:51:01', 'success'),
(34, 12, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 10:49:09', 'success'),
(35, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 11:04:23', 'success'),
(36, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 11:06:31', 'success'),
(37, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 11:06:55', 'success'),
(38, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 11:07:45', 'success'),
(39, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 11:09:14', 'success'),
(40, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 12:06:00', 'success'),
(41, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 12:38:57', 'success'),
(42, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-09 12:56:09', 'success'),
(43, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 06:11:21', 'success'),
(44, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 06:18:40', 'success'),
(45, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 06:18:52', 'success'),
(46, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 06:35:28', 'success'),
(47, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 06:36:17', 'success'),
(48, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 08:08:52', 'success'),
(49, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 09:50:44', 'success'),
(50, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 17:44:20', 'success'),
(51, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 18:03:06', 'success'),
(52, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 18:31:32', 'success'),
(53, 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-10 18:33:34', 'success'),
(54, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-06-11 04:49:52', 'success');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_transactions`
--

DROP TABLE IF EXISTS `loyalty_transactions`;
CREATE TABLE `loyalty_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `points_earned` int(11) DEFAULT 0,
  `points_redeemed` int(11) DEFAULT 0,
  `points_deducted` int(11) DEFAULT 0,
  `transaction_type` varchar(50) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loyalty_transactions`
--

INSERT INTO `loyalty_transactions` (`id`, `user_id`, `order_id`, `points_earned`, `points_redeemed`, `points_deducted`, `transaction_type`, `transaction_date`) VALUES
(1, 2, 65, 10, 0, 0, 'earn', '2026-06-06 15:05:54'),
(2, 2, 66, 19, 0, 0, 'earn', '2026-06-06 15:09:02'),
(3, 2, 67, 19, 0, 0, 'earn', '2026-06-08 04:51:34'),
(4, 2, 68, 19, 0, 0, 'earn', '2026-06-08 05:08:24'),
(5, 2, 69, 19, 0, 0, 'earn', '2026-06-08 05:16:41'),
(6, 2, 70, 10, 0, 0, 'earn', '2026-06-08 05:18:05'),
(7, 2, 71, 96, 0, 0, 'earn', '2026-06-08 11:00:24'),
(8, 2, 72, 96, 0, 0, 'earn', '2026-06-08 11:01:03'),
(9, 2, 75, 92, 0, 0, 'earn', '2026-06-08 11:06:14'),
(10, 2, 76, 92, 0, 0, 'earn', '2026-06-08 11:07:05'),
(11, 2, 77, 92, 0, 0, 'earn', '2026-06-08 11:20:05'),
(12, 2, 78, 92, 0, 0, 'earn', '2026-06-08 11:21:33'),
(13, 2, 79, 92, 0, 0, 'earn', '2026-06-08 11:33:00'),
(14, 2, 80, 92, 0, 0, 'earn', '2026-06-08 11:40:49'),
(15, 2, 81, 92, 0, 0, 'earn', '2026-06-08 11:43:03'),
(16, 2, 82, 92, 0, 0, 'earn', '2026-06-08 11:44:13'),
(17, 2, 83, 92, 0, 0, 'earn', '2026-06-08 12:04:24'),
(18, 2, 84, 92, 0, 0, 'earn', '2026-06-08 12:07:20'),
(19, 2, 85, 92, 0, 0, 'earn', '2026-06-08 12:13:51'),
(20, 2, 86, 87, 0, 0, 'earn', '2026-06-08 12:16:54'),
(21, 2, 87, 82, 0, 0, 'earn', '2026-06-08 12:19:06'),
(23, 12, 89, 96, 0, 0, 'earn', '2026-06-09 11:00:02'),
(24, 12, 90, 96, 0, 0, 'earn', '2026-06-09 11:01:00'),
(25, 2, 92, 0, 1469, 0, 'redeem', '2026-06-09 12:51:42'),
(26, 2, 92, 52, 0, 0, 'earn', '2026-06-09 12:51:42'),
(27, 2, 93, 82, 0, 0, 'earn', '2026-06-09 12:52:53'),
(28, 5, 66, 10, 0, 0, 'earn', '2026-06-10 18:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` enum('order','payment','kitchen','reservation','staff','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `title`, `body`, `is_read`, `created_at`) VALUES
(1, 'order', 'New Order Received', 'Order #1042 containing 2x Medusa Special Burger, 1x Long Island Ice Tea has been placed.', 1, '2026-06-08 05:40:16'),
(2, 'payment', 'Payment Successful', 'Online payment of ₹1,450 for Order #1042 was settled successfully via Razorpay.', 1, '2026-06-08 05:40:16'),
(3, 'kitchen', 'Kitchen Warning: Out of Stock', 'Ingredient \"Avocado\" is marked as out-of-stock. 3 menu items affected.', 1, '2026-06-08 05:40:16'),
(4, 'reservation', 'New Table Booking', 'Table 4 reserved for Mr. Rohit Sharma on 2026-06-09 at 20:00 (4 guests).', 1, '2026-06-08 05:40:16'),
(5, 'staff', 'Staff Shift Started', 'Chef Vance checked in for the evening shift.', 1, '2026-06-08 05:40:16'),
(6, 'system', 'Admin Login Detected', 'Administrator logged in from IP 192.168.1.45.', 1, '2026-06-08 05:40:16'),
(7, 'order', 'Table 5 Order Call', 'Dine-in Order #1043: 1x Pepperoni Pizza, 2x Garlic Bread.', 1, '2026-06-08 05:40:16'),
(8, 'kitchen', 'Special Chef Request', 'Table 5 requested \"Extra cheese, no onions\" on Pepperoni Pizza.', 1, '2026-06-08 05:40:16'),
(9, 'payment', 'Cash Settlement Done', 'Bill for Table 2 (₹2,100) settled by Cash.', 1, '2026-06-08 05:40:16'),
(10, 'order', 'Order Cancelled', 'Order #1039 was cancelled by customer.', 1, '2026-06-08 05:40:16'),
(11, 'system', 'Backup Completed', 'Automated nightly database backup was successful.', 1, '2026-06-08 05:40:16'),
(12, 'reservation', 'Reservation Request', 'Vip Cabin reserved for 6 guests at 21:30 under name of Ms. Ananya.', 1, '2026-06-08 05:40:16'),
(13, 'staff', 'Delivery Dispatched', 'Order #1040 handed over to delivery executive.', 1, '2026-06-08 05:40:16'),
(14, 'system', 'Admin Login Detected', 'System Admin logged in via legacy admin login panel from IP ::1.', 1, '2026-06-08 05:41:11'),
(15, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-08 05:45:01'),
(16, 'system', 'Admin Login Detected', 'System Admin logged in via legacy admin login panel from IP ::1.', 1, '2026-06-08 05:56:02'),
(17, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-08 06:03:07'),
(18, 'system', 'Admin Login Detected', 'System Admin logged in via legacy admin login panel from IP ::1.', 1, '2026-06-08 06:09:54'),
(19, 'order', 'New Order Received', 'Order ORD-858AE has been placed by Test Customer via Online. Total amount: ₹4,814.06', 1, '2026-06-08 11:00:24'),
(20, 'payment', 'Payment Successful', 'Payment of ₹4,814.06 processed successfully for order ORD-858AE.', 1, '2026-06-08 11:00:24'),
(21, 'order', 'New Order Received', 'Order ORD-F1C1B has been placed by Test Customer via Online. Total amount: ₹4,814.06', 1, '2026-06-08 11:01:03'),
(22, 'payment', 'Payment Successful', 'Payment of ₹4,814.06 processed successfully for order ORD-F1C1B.', 1, '2026-06-08 11:01:03'),
(23, 'order', 'New Order Received', 'Order TEST-B2117 has been placed by Test Customer. Total amount: ₹4,500.00', 1, '2026-06-08 11:03:55'),
(24, 'payment', 'Payment Successful', 'Payment of ₹4,500.00 processed successfully for order TEST-B2117.', 1, '2026-06-08 11:03:55'),
(25, 'order', 'New Order Received', 'Order TEST-1A13D has been placed by Test Customer. Total amount: ₹4,500.00', 1, '2026-06-08 11:04:17'),
(26, 'payment', 'Payment Successful', 'Payment of ₹4,500.00 processed successfully for order TEST-1A13D.', 1, '2026-06-08 11:04:17'),
(27, 'order', 'New Order Received', 'Order ORD-607E6 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:06:14'),
(28, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-607E6.', 1, '2026-06-08 11:06:14'),
(29, 'order', 'New Order Received', 'Order ORD-9502E has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:07:05'),
(30, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-9502E.', 1, '2026-06-08 11:07:05'),
(31, 'order', 'New Order Received', 'Order ORD-5A8B4 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:20:05'),
(32, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-5A8B4.', 1, '2026-06-08 11:20:05'),
(33, 'order', 'New Order Received', 'Order ORD-D6AE6 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:21:33'),
(34, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-D6AE6.', 1, '2026-06-08 11:21:33'),
(35, 'order', 'New Order Received', 'Order ORD-C5724 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:33:00'),
(36, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-C5724.', 1, '2026-06-08 11:33:00'),
(37, 'order', 'New Order Received', 'Order ORD-12C79 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:40:49'),
(38, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-12C79.', 1, '2026-06-08 11:40:49'),
(39, 'order', 'New Order Received', 'Order ORD-74177 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:43:03'),
(40, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-74177.', 1, '2026-06-08 11:43:03'),
(41, 'order', 'New Order Received', 'Order ORD-CD0F3 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 11:44:13'),
(42, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-CD0F3.', 1, '2026-06-08 11:44:13'),
(43, 'order', 'New Order Received', 'Order ORD-858D6 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 12:04:24'),
(44, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-858D6.', 1, '2026-06-08 12:04:24'),
(45, 'order', 'New Order Received', 'Order ORD-85960 has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 12:07:20'),
(46, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-85960.', 1, '2026-06-08 12:07:20'),
(47, 'order', 'New Order Received', 'Order ORD-F022D has been placed by Test Customer via Online. Total amount: ₹4,589.06', 1, '2026-06-08 12:13:51'),
(48, 'payment', 'Payment Successful', 'Payment of ₹4,589.06 processed successfully for order ORD-F022D.', 1, '2026-06-08 12:13:51'),
(49, 'order', 'New Order Received', 'Order ORD-621C5 has been placed by Test Customer via Online. Total amount: ₹4,364.06', 1, '2026-06-08 12:16:54'),
(50, 'payment', 'Payment Successful', 'Payment of ₹4,364.06 processed successfully for order ORD-621C5.', 1, '2026-06-08 12:16:54'),
(51, 'order', 'New Order Received', 'Order ORD-9F36E has been placed by Test Customer via Online. Total amount: ₹4,076.06', 1, '2026-06-08 12:19:06'),
(52, 'payment', 'Payment Successful', 'Payment of ₹4,076.06 processed successfully for order ORD-9F36E.', 1, '2026-06-08 12:19:06'),
(53, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-08 12:22:09'),
(54, 'order', 'New Order Received', 'Order ORD-2F383 has been placed by Rahul D via Online. Total amount: ₹2,747.06', 1, '2026-06-08 18:09:23'),
(55, 'payment', 'Payment Successful', 'Payment of ₹2,747.06 processed successfully for order ORD-2F383.', 1, '2026-06-08 18:09:23'),
(56, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-08 18:10:22'),
(57, 'order', 'Order Completed', 'Order ORD-A1025 for Janhvi Kapoor (₹950.00) is completed.', 1, '2026-06-08 18:11:19'),
(58, 'kitchen', 'Order In Prep', 'Order ORD-2F383 is now being prepared in the kitchen.', 1, '2026-06-08 18:11:35'),
(59, 'order', 'Order Completed', 'Order ORD-2F383 for Rahul D (₹2,747.06) is completed.', 1, '2026-06-08 18:11:51'),
(60, 'order', 'New Order Received', 'Order ORD-1D1FA has been placed by Piyush hfhdh via Online. Total amount: ₹4,814.06', 1, '2026-06-09 11:00:02'),
(61, 'payment', 'Payment Successful', 'Payment of ₹4,814.06 processed successfully for order ORD-1D1FA.', 1, '2026-06-09 11:00:02'),
(62, 'order', 'New Order Received', 'Order ORD-BCE13 has been placed by Piyush Anushu via Online. Total amount: ₹4,814.06', 1, '2026-06-09 11:01:00'),
(63, 'payment', 'Payment Successful', 'Payment of ₹4,814.06 processed successfully for order ORD-BCE13.', 1, '2026-06-09 11:01:00'),
(64, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-09 11:04:23'),
(65, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-09 11:06:31'),
(66, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-09 11:06:55'),
(67, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-09 11:07:45'),
(68, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 1, '2026-06-09 11:09:14'),
(69, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: ORD-BCE13). Remaining brand quota: 15 pegs.', 1, '2026-06-09 11:18:02'),
(70, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: Piyush). Remaining brand quota: 14 pegs.', 1, '2026-06-09 11:18:40'),
(71, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: #ORD-BCE13). Remaining brand quota: 13 pegs.', 1, '2026-06-09 11:19:13'),
(72, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: #ORD-BCE13). Remaining brand quota: 12 pegs.', 1, '2026-06-09 11:19:21'),
(73, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: Piyush). Remaining brand quota: 11 pegs.', 0, '2026-06-09 11:19:40'),
(74, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: Piyush). Remaining brand quota: 10 pegs.', 0, '2026-06-09 11:19:48'),
(75, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: Piyush). Remaining brand quota: 9 pegs.', 0, '2026-06-09 11:20:03'),
(76, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: Piyush). Remaining brand quota: 8 pegs.', 0, '2026-06-09 11:20:12'),
(77, 'kitchen', 'Order In Prep', 'Order ORD-BCE13 is now being prepared in the kitchen.', 0, '2026-06-09 11:31:32'),
(78, 'order', 'Order Completed', 'Order ORD-BCE13 for Piyush Anushu (₹4,814.06) is completed.', 0, '2026-06-09 11:32:14'),
(79, 'order', 'Order Completed', 'Dine-in order ORD-5EB56 for Tanshik is settled.', 0, '2026-06-09 11:50:44'),
(80, 'payment', 'Payment Successful', 'Payment of ₹0.00 received via CASH for order ORD-5EB56.', 0, '2026-06-09 11:50:44'),
(81, 'order', 'Order Completed', 'Dine-in order ORD-A1026 for Piyush Sharma is settled.', 0, '2026-06-09 12:00:50'),
(82, 'payment', 'Payment Successful', 'Payment of ₹1,260.00 received via CASH for order ORD-A1026.', 0, '2026-06-09 12:00:50'),
(83, 'order', 'Order Completed', 'Dine-in order ORD-AC198 for System Admin is settled.', 0, '2026-06-09 12:01:06'),
(84, 'payment', 'Payment Successful', 'Payment of ₹58.70 received via CASH for order ORD-AC198.', 1, '2026-06-09 12:01:06'),
(85, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-09 12:06:00'),
(86, 'order', 'New Order Received', 'Order ORD-3F079 has been placed by Anshu As via Online. Total amount: ₹1,026.00', 0, '2026-06-09 12:15:00'),
(87, 'payment', 'Payment Successful', 'Payment of ₹1,026.00 processed successfully for order ORD-3F079.', 0, '2026-06-09 12:15:00'),
(88, 'order', 'New Order Received', 'Order ORD-E930F has been placed by Test Customer via Online. Total amount: ₹2,603.00', 0, '2026-06-09 12:51:42'),
(89, 'payment', 'Payment Successful', 'Payment of ₹2,603.00 processed successfully for order ORD-E930F.', 0, '2026-06-09 12:51:42'),
(90, 'order', 'New Order Received', 'Order ORD-4E339 has been placed by Test Customer via Online. Total amount: ₹4,076.06', 0, '2026-06-09 12:52:53'),
(91, 'payment', 'Payment Successful', 'Payment of ₹4,076.06 processed successfully for order ORD-4E339.', 0, '2026-06-09 12:52:53'),
(92, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-09 12:56:09'),
(93, 'order', 'Order Completed', 'Dine-in order ORD-3F079 for Anshu As is settled.', 0, '2026-06-09 12:57:53'),
(94, 'payment', 'Payment Successful', 'Payment of ₹1,026.00 received via UPI for order ORD-3F079.', 0, '2026-06-09 12:57:53'),
(95, 'order', 'Peg Consumed', '1 peg of Johnnie Walker Black Label logged for Piyush (Verified via: ORD-BCE13). Remaining brand quota: 7 pegs.', 0, '2026-06-09 12:58:50'),
(96, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-10 06:11:21'),
(97, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-10 06:18:40'),
(98, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-10 06:36:17'),
(99, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-10 08:08:52'),
(100, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-10 09:50:44'),
(101, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-10 17:44:20'),
(102, 'order', 'New Order Received', 'Order ORD-1994B has been placed by Rahul Dhiman via Online. Total amount: ₹494.56', 0, '2026-06-10 18:29:07'),
(103, 'payment', 'Payment Successful', 'Payment of ₹494.56 processed successfully for order ORD-1994B.', 0, '2026-06-10 18:29:07'),
(104, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-10 18:31:32'),
(105, 'kitchen', 'Order In Prep', 'Order ORD-1994B is now being prepared in the kitchen.', 0, '2026-06-10 18:33:08'),
(106, 'system', 'Admin Login Detected', 'Administrator System Admin logged in from IP address ::1.', 0, '2026-06-11 04:49:52');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(15) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `order_status` varchar(20) DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `tracking_token` char(64) DEFAULT NULL,
  `tracking_status` varchar(30) DEFAULT 'placed',
  `estimated_delivery` datetime DEFAULT NULL,
  `delivery_city` varchar(100) DEFAULT NULL,
  `delivery_state` varchar(100) DEFAULT NULL,
  `delivery_pincode` varchar(20) DEFAULT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tier_discount_amount` decimal(10,2) DEFAULT 0.00,
  `points_redeemed` int(11) DEFAULT 0,
  `points_redeemed_discount` decimal(10,2) DEFAULT 0.00,
  `points_earned` int(11) DEFAULT 0,
  `payment_method` varchar(50) DEFAULT 'Online',
  `packing_charge` decimal(10,2) DEFAULT 0.00,
  `delivery_charge` decimal(10,2) DEFAULT 40.00,
  `pdf_path` varchar(500) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `customer_name`, `customer_phone`, `delivery_address`, `total_amount`, `order_status`, `order_date`, `user_id`, `tracking_token`, `tracking_status`, `estimated_delivery`, `delivery_city`, `delivery_state`, `delivery_pincode`, `tax_amount`, `discount`, `tier_discount_amount`, `points_redeemed`, `points_redeemed_discount`, `points_earned`, `payment_method`, `packing_charge`, `delivery_charge`, `pdf_path`, `status`, `coupon_code`, `coupon_discount`) VALUES
(1, 'ORD-A1010', 'Rahul Verma', '9876543201', '123, Golf Green, Kolkata - 700032', 1280.00, 'completed', '2026-05-21 09:00:00', 2, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(2, 'ORD-A1011', 'Neha Sen', '9876543202', 'Table T02', 670.00, 'completed', '2026-05-21 13:45:00', 3, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(3, 'ORD-A1012', 'Amit Patel', '9876543203', 'Sector 5, Salt Lake, Kolkata - 700091', 1520.00, 'completed', '2026-05-22 07:30:00', 2, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(4, 'ORD-A1013', 'Suresh Kumar', '9876543204', 'Table A02', 930.00, 'completed', '2026-05-22 15:00:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(5, 'ORD-A1014', 'Vikram Singh', '9876543205', 'Park Street, Kolkata - 700016', 2450.00, 'completed', '2026-05-23 07:15:00', 3, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(6, 'ORD-A1015', 'Preeti Bose', '9876543206', 'Table G03', 480.00, 'completed', '2026-05-23 15:30:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(7, 'ORD-A1016', 'Rohan Gupta', '9876543207', 'Lake Gardens, Kolkata - 700045', 1100.00, 'completed', '2026-05-24 08:00:00', 2, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(8, 'ORD-A1017', 'Deepak Sen', '9876543208', 'Table B01', 750.00, 'completed', '2026-05-24 13:15:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(9, 'ORD-A1018', 'Pooja Roy', '9876543209', 'New Town, Kolkata - 700156', 1680.00, 'completed', '2026-05-25 09:50:00', 3, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(10, 'ORD-A1019', 'Ravi Shankar', '9876543220', 'Table T05', 840.00, 'completed', '2026-05-25 16:10:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(11, 'ORD-A1020', 'Piyush Sharma', '9876543212', 'Bidhannagar, Kolkata - 700064', 1350.00, 'completed', '2026-05-26 06:45:00', 3, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(12, 'ORD-A1021', 'Ananya Ray', '9876543221', 'Table RD2', 900.00, 'completed', '2026-05-26 14:40:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(13, 'ORD-A1022', 'Karan Johar', '9876543222', 'Ballygunge, Kolkata - 700019', 2150.00, 'completed', '2026-05-27 06:00:00', 2, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(14, 'ORD-A1023', 'Test Customer', '9876543211', 'Table T01', 800.00, 'preparing', '2026-05-27 06:30:00', 2, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(15, 'ORD-A1024', 'Arjun Kapoor', '9876543223', 'Table A03', 770.00, 'pending', '2026-05-27 06:45:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(16, 'ORD-A1025', 'Janhvi Kapoor', '9876543224', 'Alipore, Kolkata - 700027', 950.00, 'ready', '2026-05-27 06:50:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(17, 'ORD-A1026', 'Piyush Sharma', '9876543212', 'Table G02', 1260.00, 'preparing', '2026-05-27 06:55:00', 3, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(18, 'ORD-72033', 'Test User', '9876543210', '123 Main St, New York, NY', 394.00, 'pending', '2026-05-26 02:18:39', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(19, 'ORD-099E8', 'John Doe', '9876543210', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 333.82, 'pending', '2026-05-26 02:20:56', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(20, 'ORD-4D605', 'John Doe', '9485206796', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 745.64, 'pending', '2026-05-26 02:22:52', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(21, 'ORD-C46AD', 'John Doe', '9485206796', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 804.64, 'pending', '2026-05-26 02:48:04', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(22, 'ORD-42D3B', 'Test User', '9485206796', '123 Main St, New York, NY', 394.00, 'pending', '2026-05-26 02:54:20', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(23, 'ORD-F4239', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 804.64, 'pending', '2026-05-26 02:55:51', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(24, 'ORD-4264A', 'John Doe', '9485206796', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 804.64, 'pending', '2026-05-26 02:59:24', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(25, 'ORD-5D49C', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 745.64, 'pending', '2026-05-26 03:19:10', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(26, 'ORD-17C6B', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 1510.28, 'pending', '2026-05-26 03:23:53', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(27, 'ORD-E2919', 'John Doe', '9855669331', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 804.64, 'pending', '2026-05-26 03:26:35', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(28, 'ORD-E71DB', 'John Doe', '9855669331', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 58.70, 'pending', '2026-05-26 03:27:18', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(29, 'ORD-ADDED', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 804.64, 'pending', '2026-05-26 03:29:07', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(30, 'ORD-9B83B', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 58.70, 'pending', '2026-05-26 03:46:47', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(31, 'ORD-068CF', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 745.64, 'pending', '2026-05-26 03:50:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(32, 'ORD-32342', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 58.70, 'pending', '2026-05-26 05:46:38', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(33, 'ORD-E7011', 'John Doe', '9876543210', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 745.64, 'pending', '2026-05-26 05:49:31', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(34, 'ORD-60507', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 333.82, 'pending', '2026-05-26 05:50:43', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(35, 'ORD-EAAFE', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 486.04, 'pending', '2026-05-27 02:22:36', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(36, 'ORD-E29D0', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 58.70, 'pending', '2026-05-27 04:00:11', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(37, 'ORD-209AF', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 58.70, 'pending', '2026-05-27 04:09:30', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(38, 'ORD-27F9A', 'ramu Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 2459.00, 'pending', '2026-05-27 04:25:51', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(39, 'ORD-861E9', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 1810.00, 'pending', '2026-05-27 04:38:29', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(40, 'ORD-7777F', 'John Doe', '7814585430', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 984.00, 'pending', '2026-05-27 04:47:29', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(41, 'ORD-A6F58', 'John Doe', '9876543210', '123, Main Street, Apt 4B, New York, Delhi - 10001, India', 984.00, 'pending', '2026-05-27 06:34:28', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(42, 'ORD-CB262', 'rahul Bhatt', '7814585430', '123, Main Street, New York, Delhi - 10001, India', 984.00, 'pending', '2026-05-27 07:41:46', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(43, 'ORD-AC198', 'System Admin', '7814585430', 'Table T03, gshg;iofdhg, chd, Delhi - 10001, India', 58.70, 'pending', '2026-05-27 07:53:12', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(44, 'ORD-E2276', 'System Admin', '7814585430', 'gshg;iofdhg, chd, Delhi - 10001, India', 783.40, 'pending', '2026-05-27 07:54:35', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(45, 'ORD-EB956', 'System Admin', '7814585430', 'php, chd, Delhi - 10001, India', 783.40, 'pending', '2026-05-27 07:56:12', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(46, 'ORD-A2487', 'System Admin', '7814585430', 'rajsri, psd, Delhi - 10001, India', 2140.40, 'pending', '2026-05-27 07:58:42', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(47, 'ORD-9E1B9', 'Ramu fgd', '7814585430', '123, Main Street, Apt 4B, chd, Delhi - 10001, India', 1456.00, 'pending', '2026-05-27 08:26:42', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(48, 'ORD-A5DF9', 'Test Customer', '9876543211', '123, Main Street, Apt 4B, chd, Delhi - 10001, India', 1043.00, 'pending', '2026-05-27 09:10:43', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(49, 'ORD-89B48', 'Test Customer', '7814585430', '123, Main Street, Apt 4B, chd, Delhi - 10001, India', 1043.00, 'pending', '2026-05-27 09:11:28', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(50, 'ORD-29934', 'Test Customer', '7814585430', '123, Main Street, Apt 4B, chd, Delhi - 10001, India', 901.40, 'pending', '2026-05-29 05:33:20', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(51, 'ORD-B3D66', 'John Doe Third', '9876543223', '123 Artisanal Way, Chandigarh, Delhi - 160017, India', 1900.06, 'pending', '2026-06-06 03:52:03', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(52, 'ORD-5A8B4', 'Test Customer', '7973667447', 'GAYA BHA, CHDh, Delhi - 134109, India', 4589.06, 'pending', '2026-06-08 07:50:08', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(53, 'ORD-D6AE6', 'Test Customer', '9855669331', 'GAYA BHA, CHDh, Delhi - 134109, India', 4589.06, 'pending', '2026-06-08 07:51:36', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(54, 'ORD-C5724', 'Test Customer', '9855669331', 'GAYA BHA, CHDh, Delhi - 134109, India', 4589.06, 'pending', '2026-06-08 08:03:03', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(55, 'ORD-12C79', 'Test Customer', '9855669331', 'GAYA BHA, CHDh, Delhi - 134109, India', 4589.06, 'pending', '2026-06-08 08:10:49', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(56, 'ORD-74177', 'Test Customer', '9855669331', 'GAYA BHA, CHDh, Delhi - 134109, India', 4589.06, 'pending', '2026-06-08 08:13:06', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(57, 'ORD-CD0F3', 'Test Customer', '9855669331', 'GAYA BHA, CHDh, Delhi - 134109, India', 4589.06, 'pending', '2026-06-08 08:14:13', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(58, 'ORD-621C5', 'Test Customer', '7428723247', 'GAYA BHA, CHDh, Delhi - 134109, India', 4364.06, 'pending', '2026-06-08 08:46:57', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(59, 'ORD-9F36E', 'Test Customer', '9855669331', 'GAYA BHA, CHDh, Delhi - 134109, India', 4076.06, 'pending', '2026-06-08 08:49:06', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(60, 'ORD-2F383', 'Rahul D', '8124735499', 'GAYA BHA, CHDh, Delhi - 134109, India', 2747.06, 'pending', '2026-06-08 14:39:23', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(61, 'ORD-1D1FA', 'Piyush hfhdh', '7814585430', 'GAYA BHA, CHDh, Delhi - 134109, India', 4814.06, 'pending', '2026-06-09 07:30:12', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(62, 'ORD-BCE13', 'Piyush Anushu', '7814585430', 'GAYA BHA, CHDh, Delhi - 134109, India', 4814.06, 'pending', '2026-06-09 07:31:07', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(63, 'ORD-3F079', 'Anshu As', '6484894546', 'Table T03, Jdjdje, Hsjshs, Delhi - 1342728, India', 1026.00, 'pending', '2026-06-09 08:45:09', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(64, 'ORD-E930F', 'Test Customer', '7814585430', 'Table T04, GAYA BHA, CHDh, Delhi - 134109, India', 2603.00, 'pending', '2026-06-09 09:21:50', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(65, 'ORD-4E339', 'Test Customer', '7814585430', 'GAYA BHA, CHDh, Delhi - 134109, India', 4076.06, 'pending', '2026-06-09 09:23:00', NULL, NULL, 'placed', NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0, 0.00, 0, 'Online', 0.00, 40.00, NULL, 'pending', NULL, 0.00),
(66, 'ORD-1994B', 'Rahul Dhiman', '8124735499', 'GAYA BHA, CHDh, Delhi - 134109, India', 494.56, 'ready', '2026-06-10 18:29:05', 5, 'ca3a771e4135b119ffe47ffec02e6b8c83df4bc0ab0db7286fb91ae619410760', 'placed', '2026-06-10 21:14:05', '', '', '', 68.00, 0.00, 42.50, 0, 0.00, 10, 'Online', 0.00, 40.00, 'bills/order_66_20260610_202907.pdf', 'pending', NULL, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `food_item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `food_item_id`, `item_name`, `quantity`, `price`, `unit_price`, `subtotal`) VALUES
(1, 1, 1, 'Butter Chicken', 2, 450.00, NULL, NULL),
(2, 1, 7, 'Garlic Naan', 4, 80.00, NULL, NULL),
(3, 1, 6, 'Samosa', 1, 180.00, NULL, NULL),
(4, 2, 8, 'Margherita Pizza', 1, 350.00, NULL, NULL),
(5, 2, 25, 'Tiramisu', 1, 320.00, NULL, NULL),
(6, 3, 14, 'Sushi Roll (8 pcs)', 2, 550.00, NULL, NULL),
(7, 3, 17, 'Ramen Bowl', 1, 420.00, NULL, NULL),
(8, 4, 19, 'Classic Burger', 2, 350.00, NULL, NULL),
(9, 4, 22, 'Chicken Wings (6 pcs)', 1, 380.00, NULL, NULL),
(10, 5, 20, 'BBQ Ribs', 3, 550.00, NULL, NULL),
(11, 5, 21, 'Mac & Cheese', 2, 320.00, NULL, NULL),
(12, 5, 29, 'Chocolate Lava Cake', 2, 380.00, NULL, NULL),
(13, 6, 15, 'Pad Thai', 1, 380.00, NULL, NULL),
(14, 6, 7, 'Garlic Naan', 1, 80.00, NULL, NULL),
(15, 6, 24, 'Gulab Jamun (3 pcs)', 1, 150.00, NULL, NULL),
(16, 7, 2, 'Chicken Biryani', 2, 350.00, NULL, NULL),
(17, 7, 17, 'Ramen Bowl', 1, 420.00, NULL, NULL),
(18, 8, 10, 'Pasta Alfredo', 1, 380.00, NULL, NULL),
(19, 8, 25, 'Tiramisu', 1, 320.00, NULL, NULL),
(20, 9, 8, 'Margherita Pizza', 2, 350.00, NULL, NULL),
(21, 9, 9, 'Pepperoni Pizza', 1, 420.00, NULL, NULL),
(22, 9, 12, 'Classic Lasagna', 1, 450.00, NULL, NULL),
(23, 10, 1, 'Butter Chicken', 1, 450.00, NULL, NULL),
(24, 10, 24, 'Gulab Jamun (3 pcs)', 1, 150.00, NULL, NULL),
(25, 10, 7, 'Garlic Naan', 2, 80.00, NULL, NULL),
(26, 11, 2, 'Chicken Biryani', 3, 350.00, NULL, NULL),
(27, 11, 26, 'Ice Cream Sundae', 1, 220.00, NULL, NULL),
(28, 12, 16, 'Dim Sum (6 pcs)', 2, 320.00, NULL, NULL),
(29, 12, 13, 'Bruschetta', 1, 250.00, NULL, NULL),
(30, 13, 20, 'BBQ Ribs', 2, 550.00, NULL, NULL),
(31, 13, 22, 'Chicken Wings (6 pcs)', 2, 380.00, NULL, NULL),
(32, 13, 28, 'New York Cheesecake', 1, 350.00, NULL, NULL),
(33, 14, 1, 'Butter Chicken', 1, 450.00, NULL, NULL),
(34, 14, 2, 'Chicken Biryani', 1, 350.00, NULL, NULL),
(35, 15, 10, 'Pasta Alfredo', 1, 380.00, NULL, NULL),
(36, 15, 11, 'Pasta Arrabiata', 1, 350.00, NULL, NULL),
(37, 15, 7, 'Garlic Naan', 1, 80.00, NULL, NULL),
(38, 16, 19, 'Classic Burger', 1, 350.00, NULL, NULL),
(39, 16, 21, 'Mac & Cheese', 1, 320.00, NULL, NULL),
(40, 16, 28, 'New York Cheesecake', 1, 350.00, NULL, NULL),
(41, 17, 14, 'Sushi Roll (8 pcs)', 1, 550.00, NULL, NULL),
(42, 17, 15, 'Pad Thai', 1, 380.00, NULL, NULL),
(43, 17, 27, 'Mochi Ice Cream (3 pcs)', 1, 280.00, NULL, NULL),
(44, 18, NULL, 'Burger', 2, 150.00, NULL, NULL),
(45, 19, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(46, 20, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(47, 20, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(48, 21, 8, 'Margherita Pizza', 1, 299.00, NULL, NULL),
(49, 21, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(50, 22, NULL, 'Burger', 2, 150.00, NULL, NULL),
(51, 23, 8, 'Margherita Pizza', 1, 299.00, NULL, NULL),
(52, 23, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(53, 24, 8, 'Margherita Pizza', 1, 299.00, NULL, NULL),
(54, 24, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(55, 25, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(56, 25, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(57, 26, 8, 'Margherita Pizza', 1, 299.00, NULL, NULL),
(58, 26, 1, 'Butter Chicken', 2, 349.00, NULL, NULL),
(59, 26, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(60, 27, 8, 'Margherita Pizza', 1, 299.00, NULL, NULL),
(61, 27, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(62, 28, NULL, 'Basic Box', 1, 15.85, NULL, NULL),
(63, 29, 8, 'Margherita Pizza', 1, 299.00, NULL, NULL),
(64, 29, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(65, 30, NULL, 'Basic Box', 1, 15.85, NULL, NULL),
(66, 31, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(67, 31, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(68, 32, NULL, 'Basic Box', 1, 15.85, NULL, NULL),
(69, 33, 1, 'Butter Chicken', 1, 349.00, NULL, NULL),
(70, 33, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(71, 34, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(72, 35, NULL, 'Veg Biryani', 1, 249.00, NULL, NULL),
(73, 35, NULL, 'Gulab Jamun', 1, 129.00, NULL, NULL),
(74, 36, NULL, 'Basic Box', 1, 15.85, NULL, NULL),
(75, 37, NULL, 'Basic Box', 1, 15.85, NULL, NULL),
(76, 38, 2, 'Chicken Biryani', 1, 350.00, NULL, NULL),
(77, 38, 3, 'Dal Makhani', 1, 280.00, NULL, NULL),
(78, 38, 14, 'Sushi Roll (8 pcs)', 1, 550.00, NULL, NULL),
(79, 38, 20, 'BBQ Ribs', 1, 550.00, NULL, NULL),
(80, 38, 21, 'Mac & Cheese', 1, 320.00, NULL, NULL),
(81, 39, 8, 'Margherita Pizza', 1, 350.00, NULL, NULL),
(82, 39, 11, 'Pasta Arrabiata', 1, 350.00, NULL, NULL),
(83, 39, 15, 'Pad Thai', 1, 380.00, NULL, NULL),
(84, 39, 17, 'Ramen Bowl', 1, 420.00, NULL, NULL),
(85, 40, 1, 'Butter Chicken', 1, 450.00, NULL, NULL),
(86, 40, 2, 'Chicken Biryani', 1, 350.00, NULL, NULL),
(87, 41, 1, 'Butter Chicken', 1, 450.00, NULL, NULL),
(88, 41, 8, 'Margherita Pizza', 1, 350.00, NULL, NULL),
(89, 42, 1, 'Butter Chicken', 1, 450.00, NULL, NULL),
(90, 42, 2, 'Chicken Biryani', 1, 350.00, NULL, NULL),
(91, 43, NULL, 'Basic Box', 1, 15.85, NULL, NULL),
(92, 44, 2, 'Chicken Biryani', 1, 350.00, NULL, NULL),
(93, 44, 3, 'Dal Makhani', 1, 280.00, NULL, NULL),
(94, 45, 2, 'Chicken Biryani', 1, 350.00, NULL, NULL),
(95, 45, 3, 'Dal Makhani', 1, 280.00, NULL, NULL),
(96, 46, 2, 'Chicken Biryani', 3, 350.00, NULL, NULL),
(97, 46, 3, 'Dal Makhani', 1, 280.00, NULL, NULL),
(98, 46, 1, 'Butter Chicken', 1, 450.00, NULL, NULL),
(99, 47, 5, 'Palak Paneer', 1, 320.00, NULL, NULL),
(100, 47, 6, 'Samosa', 1, 180.00, NULL, NULL),
(101, 47, 8, 'Margherita Pizza', 1, 350.00, NULL, NULL),
(102, 47, 11, 'Pasta Arrabiata', 1, 350.00, NULL, NULL),
(103, 48, 5, 'Palak Paneer', 1, 320.00, NULL, NULL),
(104, 48, 6, 'Samosa', 1, 180.00, NULL, NULL),
(105, 48, 8, 'Margherita Pizza', 1, 350.00, NULL, NULL),
(106, 49, 5, 'Palak Paneer', 1, 320.00, NULL, NULL),
(107, 49, 6, 'Samosa', 1, 180.00, NULL, NULL),
(108, 49, 8, 'Margherita Pizza', 1, 350.00, NULL, NULL),
(109, 50, 1, 'Butter Chicken', 1, 450.00, NULL, NULL),
(110, 50, 3, 'Dal Makhani', 1, 280.00, NULL, NULL),
(111, 51, 1, 'Butter Chicken', 2, 450.00, NULL, NULL),
(112, 51, 2, 'Chicken Biryani', 2, 350.00, NULL, NULL),
(113, 52, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(114, 53, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(115, 54, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(116, 55, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(117, 56, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(118, 57, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(119, 58, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(120, 59, NULL, 'Hendrick\'s Artisanal Gin', 1, 4200.00, NULL, NULL),
(121, 60, NULL, 'Australian Lamb Chops', 1, 2550.00, NULL, NULL),
(122, 61, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(123, 62, NULL, 'Johnnie Walker Black Label', 1, 4500.00, NULL, NULL),
(124, 63, NULL, 'Manchow Soup', 1, 425.00, NULL, NULL),
(125, 63, NULL, 'Vietnamese Pho Soup', 1, 425.00, NULL, NULL),
(126, 64, NULL, 'Hendrick\'s Artisanal Gin', 1, 4200.00, NULL, NULL),
(127, 65, NULL, 'Hendrick\'s Artisanal Gin', 1, 4200.00, NULL, NULL),
(128, 66, 2, 'Manchow Soup', 1, 425.00, 425.00, 425.00);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `guests` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `special_request` text DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `customer_name`, `customer_phone`, `guests`, `reservation_date`, `reservation_time`, `special_request`, `status`, `created_at`) VALUES
(1, 'Vikram Malhotra', '9876543230', 4, '2026-05-27', '19:30:00', 'Window seat preferred', 'confirmed', '2026-06-09 18:09:26'),
(2, 'Shreya Ghoshal', '9876543231', 2, '2026-05-27', '21:00:00', 'Birthday celebration', 'pending', '2026-06-09 18:09:26'),
(3, 'Abhishek Bachchan', '9876543232', 8, '2026-05-28', '20:00:00', 'Quiet booth area', 'confirmed', '2026-06-09 18:09:26'),
(4, 'Ranbir Kapoor', '9876543233', 2, '2026-05-28', '22:30:00', 'Vegetarian food menu only', 'pending', '2026-06-09 18:09:26');

-- --------------------------------------------------------

--
-- Table structure for table `reward_points`
--

DROP TABLE IF EXISTS `reward_points`;
CREATE TABLE `reward_points` (
  `user_id` int(11) NOT NULL,
  `points_earned` int(11) DEFAULT 0,
  `points_redeemed` int(11) DEFAULT 0,
  `points_deducted` int(11) DEFAULT 0,
  `current_balance` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reward_points`
--

INSERT INTO `reward_points` (`user_id`, `points_earned`, `points_redeemed`, `points_deducted`, `current_balance`) VALUES
(1, 0, 0, 0, 0),
(2, 1603, 1469, 0, 134),
(3, 0, 0, 0, 0),
(5, 10, 0, 0, 10),
(6, 0, 0, 0, 0),
(7, 0, 0, 0, 0),
(8, 0, 0, 0, 0),
(9, 0, 0, 0, 0),
(10, 0, 0, 0, 0),
(12, 192, 0, 0, 192);

-- --------------------------------------------------------

--
-- Table structure for table `support_requests`
--

DROP TABLE IF EXISTS `support_requests`;
CREATE TABLE `support_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `support_requests`
--

INSERT INTO `support_requests` (`id`, `user_id`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 2, 'Private Table Query', 'Do you allow private rooms booking?', 'open', '2026-06-06 13:31:18');

-- --------------------------------------------------------

--
-- Table structure for table `table_bookings`
--

DROP TABLE IF EXISTS `table_bookings`;
CREATE TABLE `table_bookings` (
  `id` int(11) NOT NULL,
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
  `venue_address` varchar(255) NOT NULL DEFAULT 'Chandigarh, India',
  `venue_phone` varchar(50) NOT NULL DEFAULT '+91 98765 43210',
  `status` varchar(50) NOT NULL DEFAULT 'confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `table_bookings`
--

INSERT INTO `table_bookings` (`id`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `booking_date`, `booking_time`, `guests`, `table_number`, `special_requests`, `venue_name`, `venue_address`, `venue_phone`, `status`, `created_at`) VALUES
(1, NULL, 'Jane Doe Booking', 'janedoe_test_7799@gmail.com', '9876543224', '2026-06-15', '19:30:00', 4, 'T05', 'Window seat request, celebrating birthday', 'Medusa', 'Chandigarh, India', '+91 98765 43210', 'confirmed', '2026-06-06 08:17:49');

-- --------------------------------------------------------

--
-- Table structure for table `tier_history`
--

DROP TABLE IF EXISTS `tier_history`;
CREATE TABLE `tier_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `previous_tier_id` int(11) NOT NULL,
  `new_tier_id` int(11) NOT NULL,
  `change_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tier_history`
--

INSERT INTO `tier_history` (`id`, `user_id`, `previous_tier_id`, `new_tier_id`, `change_date`, `reason`) VALUES
(1, 2, 1, 2, '2026-06-08 11:01:03', 'Spending threshold met'),
(2, 2, 2, 3, '2026-06-08 12:13:51', 'Spending threshold met');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `role` enum('customer','admin') DEFAULT 'customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `session_token` varchar(64) DEFAULT NULL,
  `current_tier_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(20) DEFAULT NULL,
  `last_inactivity_email_sent` date DEFAULT NULL,
  `email_otp` varchar(10) DEFAULT NULL,
  `phone_otp` varchar(10) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `is_email_verified` tinyint(1) DEFAULT 0,
  `is_phone_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`, `created_at`, `is_active`, `session_token`, `current_tier_id`, `address`, `city`, `state`, `pincode`, `last_inactivity_email_sent`, `email_otp`, `phone_otp`, `otp_expires_at`, `is_email_verified`, `is_phone_verified`) VALUES
(1, 'System Admin', 'admin@example.com', '$2y$10$rRRN5vJIWZltYTywQAgxReBWCWazX1HeadB7ktTjAdxGceY8QJQoG', '9876543210', 'admin', '2026-06-09 18:09:25', 1, '9c676d1e17f7eead00a44aa6d608527a1d341e96bb92d5afa4824b801e64f639', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0),
(2, 'Test Customer', 'customer@example.com', '$2y$10$hdslkG6tN7/j4ja6N63Js.3ImHeNwDwYZ0T5eAhBiqIq7MrGVWHYa', '9876543211', 'customer', '2026-06-09 18:09:25', 1, '1c4aad07603f06c7f1563f585b8de73999c410c1212747da1905dd6438d61610', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0),
(3, 'Piyush Sharma', 'piyush@example.com', '$2y$10$hdslkG6tN7/j4ja6N63Js.3ImHeNwDwYZ0T5eAhBiqIq7MrGVWHYa', '9876543212', 'customer', '2026-06-09 18:09:25', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0),
(5, 'Rahul Dhiman', 'rahuldhiman2080@gmail.com', '$2y$10$dlnr.i.SL2i9HoEmL1x3Q.wXM0LoZphp2v45UI/zPyB/boC16d7TC', '8124735499', 'customer', '2026-06-10 18:00:15', 1, 'db2d61825897de764b2d75bc076c3c35578da0c66848b698cea976426d5f5a1b', NULL, '', '', '', '', NULL, NULL, NULL, NULL, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_liquor_quota`
--

DROP TABLE IF EXISTS `user_liquor_quota`;
CREATE TABLE `user_liquor_quota` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `food_item_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `total_pegs` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_liquor_quota`
--

INSERT INTO `user_liquor_quota` (`id`, `user_id`, `food_item_id`, `item_name`, `total_pegs`, `created_at`, `updated_at`) VALUES
(1, 2, 99, 'Johnnie Walker Black Label', 32, '2026-06-08 12:04:24', '2026-06-08 12:16:54'),
(2, 2, 103, 'Hendrick\'s Artisanal Gin', 24, '2026-06-08 12:19:06', '2026-06-09 12:52:52'),
(3, 12, 99, 'Johnnie Walker Black Label', 7, '2026-06-09 11:00:01', '2026-06-09 12:58:50');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 'Loyalty Tier Upgraded!', 'Congratulations! Your lifetime spend has reached ₹25,325.08. You have been promoted to the Gold tier, giving you a Gold tier discount of 15% on future orders.', 0, '2026-06-08 11:01:03'),
(2, 2, 'Loyalty Tier Upgraded!', 'Congratulations! Your lifetime spend has reached ₹75,804.74. You have been promoted to the Platinum tier, giving you a Platinum tier discount of 20% on future orders.', 0, '2026-06-08 12:13:51'),
(3, 12, 'Order Placed Successfully', 'Your order #ORD-1D1FA has been placed successfully and is being processed.', 0, '2026-06-09 11:00:02'),
(4, 12, 'Order Placed Successfully', 'Your order #ORD-BCE13 has been placed successfully and is being processed.', 0, '2026-06-09 11:01:00'),
(5, 12, 'Order Arriving', 'Your order #ORD-BCE13 is out for delivery and arriving soon.', 0, '2026-06-09 11:32:10'),
(6, 12, 'Order Delivered', 'Your order #ORD-BCE13 has been successfully delivered. Enjoy your meal!', 0, '2026-06-09 11:32:14'),
(7, 2, 'Order Placed Successfully', 'Your order #ORD-4E339 has been placed successfully and is being processed.', 0, '2026-06-09 12:52:53'),
(8, 5, 'Order Placed Successfully', 'Your order #ORD-1994B has been placed successfully and is being processed.', 0, '2026-06-10 18:29:07'),
(9, 5, 'Order Arriving', 'Your order #ORD-1994B is out for delivery and arriving soon.', 0, '2026-06-10 18:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--
<<<<<<< Updated upstream

DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 1,
  `promotional_offers` tinyint(1) DEFAULT 1,
  `privacy_mode` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `theme` varchar(10) DEFAULT 'dark'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`user_id`, `email_notifications`, `sms_notifications`, `promotional_offers`, `privacy_mode`, `language`, `theme`) VALUES
(2, 0, 1, 1, 0, 'en', 'dark');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `career_applications`
--
ALTER TABLE `career_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `food_item_id` (`food_item_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupon_code` (`coupon_code`),
  ADD KEY `idx_coupons_user_id` (`user_id`),
  ADD KEY `idx_coupons_status` (`status`),
  ADD KEY `idx_coupons_expires_at` (`expires_at`),
  ADD KEY `fk_coupons_feedback` (`review_id`),
  ADD KEY `fk_coupons_orders` (`order_id`);

--
-- Indexes for table `customer_tiers`
--
ALTER TABLE `customer_tiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tier_name` (`tier_name`);

--
-- Indexes for table `dish_customizations`
--
ALTER TABLE `dish_customizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `food_item_id` (`food_item_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feedback_orders` (`order_number`),
  ADD KEY `fk_feedback_users` (`user_id`);

--
-- Indexes for table `food_items`
--
ALTER TABLE `food_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_login_logs_users` (`user_id`);

--
-- Indexes for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transactions_users` (`user_id`),
  ADD KEY `fk_transactions_orders` (`order_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_is_read` (`is_read`),
  ADD KEY `idx_notif_created_at` (`created_at`),
  ADD KEY `idx_notif_type` (`type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD UNIQUE KEY `tracking_token` (`tracking_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_tracking_token` (`tracking_token`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `food_item_id` (`food_item_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reward_points`
--
ALTER TABLE `reward_points`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_support_requests_users` (`user_id`);

--
-- Indexes for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tier_history`
--
ALTER TABLE `tier_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_users` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_liquor_quota`
--
ALTER TABLE `user_liquor_quota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_item` (`user_id`,`food_item_id`),
  ADD KEY `fk_quota_items` (`food_item_id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_users` (`user_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `career_applications`
--
ALTER TABLE `career_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_tiers`
--
ALTER TABLE `customer_tiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dish_customizations`
--
ALTER TABLE `dish_customizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `table_bookings`
--
ALTER TABLE `table_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tier_history`
--
ALTER TABLE `tier_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_liquor_quota`
--
ALTER TABLE `user_liquor_quota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `fk_coupons_feedback` FOREIGN KEY (`review_id`) REFERENCES `feedback` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coupons_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coupons_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dish_customizations`
--
ALTER TABLE `dish_customizations`
  ADD CONSTRAINT `fk_customization_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_orders` FOREIGN KEY (`order_number`) REFERENCES `orders` (`order_number`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  ADD CONSTRAINT `fk_login_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD CONSTRAINT `fk_transactions_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transactions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reward_points`
--
ALTER TABLE `reward_points`
  ADD CONSTRAINT `fk_rewards_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD CONSTRAINT `fk_support_requests_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD CONSTRAINT `table_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tier_history`
--
ALTER TABLE `tier_history`
  ADD CONSTRAINT `fk_history_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_addresses_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_liquor_quota`
--
ALTER TABLE `user_liquor_quota`
  ADD CONSTRAINT `fk_quota_items` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quota_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_notifications_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;
=======
>>>>>>> Stashed changes

DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 1,
  `sms_notifications` tinyint(1) DEFAULT 1,
  `promotional_offers` tinyint(1) DEFAULT 1,
  `privacy_mode` tinyint(1) DEFAULT 0,
  `language` varchar(10) DEFAULT 'en',
  `theme` varchar(10) DEFAULT 'dark'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`user_id`, `email_notifications`, `sms_notifications`, `promotional_offers`, `privacy_mode`, `language`, `theme`) VALUES
(2, 0, 1, 1, 0, 'en', 'dark');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `career_applications`
--
ALTER TABLE `career_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `food_item_id` (`food_item_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupon_code` (`coupon_code`),
  ADD KEY `idx_coupons_user_id` (`user_id`),
  ADD KEY `idx_coupons_status` (`status`),
  ADD KEY `idx_coupons_expires_at` (`expires_at`),
  ADD KEY `fk_coupons_feedback` (`review_id`),
  ADD KEY `fk_coupons_orders` (`order_id`);

--
-- Indexes for table `customer_tiers`
--
ALTER TABLE `customer_tiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tier_name` (`tier_name`);

--
-- Indexes for table `dish_customizations`
--
ALTER TABLE `dish_customizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `food_item_id` (`food_item_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feedback_orders` (`order_number`),
  ADD KEY `fk_feedback_users` (`user_id`);

--
-- Indexes for table `food_items`
--
ALTER TABLE `food_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_login_logs_users` (`user_id`);

--
-- Indexes for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transactions_users` (`user_id`),
  ADD KEY `fk_transactions_orders` (`order_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_is_read` (`is_read`),
  ADD KEY `idx_notif_created_at` (`created_at`),
  ADD KEY `idx_notif_type` (`type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD UNIQUE KEY `tracking_token` (`tracking_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_tracking_token` (`tracking_token`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `food_item_id` (`food_item_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `reward_points`
--
ALTER TABLE `reward_points`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_support_requests_users` (`user_id`);

--
-- Indexes for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tier_history`
--
ALTER TABLE `tier_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_users` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_liquor_quota`
--
ALTER TABLE `user_liquor_quota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_item` (`user_id`,`food_item_id`),
  ADD KEY `fk_quota_items` (`food_item_id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_users` (`user_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `career_applications`
--
ALTER TABLE `career_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customer_tiers`
--
ALTER TABLE `customer_tiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dish_customizations`
--
ALTER TABLE `dish_customizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `food_items`
--
ALTER TABLE `food_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=107;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `support_requests`
--
ALTER TABLE `support_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `table_bookings`
--
ALTER TABLE `table_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tier_history`
--
ALTER TABLE `tier_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_liquor_quota`
--
ALTER TABLE `user_liquor_quota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `coupons`
--
ALTER TABLE `coupons`
  ADD CONSTRAINT `fk_coupons_feedback` FOREIGN KEY (`review_id`) REFERENCES `feedback` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coupons_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coupons_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dish_customizations`
--
ALTER TABLE `dish_customizations`
  ADD CONSTRAINT `fk_customization_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_orders` FOREIGN KEY (`order_number`) REFERENCES `orders` (`order_number`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `login_activity_logs`
--
ALTER TABLE `login_activity_logs`
  ADD CONSTRAINT `fk_login_logs_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loyalty_transactions`
--
ALTER TABLE `loyalty_transactions`
  ADD CONSTRAINT `fk_transactions_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_transactions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_items_food` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reward_points`
--
ALTER TABLE `reward_points`
  ADD CONSTRAINT `fk_rewards_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_requests`
--
ALTER TABLE `support_requests`
  ADD CONSTRAINT `fk_support_requests_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `table_bookings`
--
ALTER TABLE `table_bookings`
  ADD CONSTRAINT `table_bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tier_history`
--
ALTER TABLE `tier_history`
  ADD CONSTRAINT `fk_history_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_addresses_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_liquor_quota`
--
ALTER TABLE `user_liquor_quota`
  ADD CONSTRAINT `fk_quota_items` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quota_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `fk_notifications_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
