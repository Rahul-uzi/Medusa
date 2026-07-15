<?php
require 'api/config.php';

// 1. Update Murgh Yakhani Shorba
$newDesc = "Slow-cooked chicken broth infused with aromatic spices, tender chicken pieces and rich flavours";
$stmt = $conn->prepare("UPDATE food_items SET description = ? WHERE name = 'Murgh Yakhani Shorba'");
$stmt->bind_param("s", $newDesc);
$stmt->execute();
echo "Updated Murgh Yakhani Shorba.\n";

// 2. Add Dal Shorba
$stmt = $conn->prepare("INSERT INTO food_items (name, description, price, category, vegetarian) VALUES (?, ?, ?, ?, ?)");
$name = "Dal Shorba";
$desc = "(Vegan, Healthy, Gluten Free) lentil soup seasoned with whole spices, ginger garlic, and fresh herbs";
$price = 425.00;
$cat = "Soup";
$veg = 1;
$stmt->bind_param("ssdsi", $name, $desc, $price, $cat, $veg);
$stmt->execute();
echo "Added Dal Shorba.\n";

// 3. Add Burmese Khao Suey
$name = "Burmese Khao Suey";
$desc = "Veg / chicken — Soft and crispy noodle in coconut broth";
$price = 425.00;
$cat = "Soup";
$veg = 0;
$stmt->bind_param("ssdsi", $name, $desc, $price, $cat, $veg);
$stmt->execute();
echo "Added Burmese Khao Suey.\n";

// 4. Add Himalayan Thukpa
$name = "Himalayan Thukpa";
$desc = "Veg / chicken";
$price = 425.00;
$cat = "Soup";
$veg = 0;
$stmt->bind_param("ssdsi", $name, $desc, $price, $cat, $veg);
$stmt->execute();
echo "Added Himalayan Thukpa.\n";

?>
