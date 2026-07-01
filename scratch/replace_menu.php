<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  MEDUSA RESTAURANT — FULL MENU REPLACEMENT SCRIPT
 *  Deletes all existing food_items and inserts the new menu.
 * ══════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../api/config.php';

// Start transaction so either everything succeeds or nothing changes
$pdo->beginTransaction();

try {
    // ── 1. Clear existing items ──
    $pdo->exec("DELETE FROM food_items");
    $pdo->exec("ALTER TABLE food_items AUTO_INCREMENT = 1");
    echo "✓ Cleared old menu items.\n";

    // ── 2. Insert new menu ──
    $insert = $pdo->prepare("
        INSERT INTO food_items (name, description, price, category, image_url, is_available)
        VALUES (?, ?, ?, ?, '', 1)
    ");

    $menu = [
        // ═══════════════════════════════════════════
        // 🍲 SOUPS — 9 items
        // ═══════════════════════════════════════════
        ['Soups', 'Cream of Mushroom', 'Roasted button mushrooms, celery, leeks, garlic, and parmesan foam', 425],
        ['Soups', 'Manchow Soup', 'Veg / chicken / prawns — mild asian minced vegetable soup with crispy noodles', 425],
        ['Soups', 'Vietnamese Pho Soup', 'Veg / chicken — flat vietnamese noodles simmered in aromatic broth', 425],
        ['Soups', 'Hot & Sour Soup', 'Veg / chicken / prawns — bamboo shoots, mushrooms, chinese cabbage in a thick and spicy soup', 425],
        ['Soups', 'Sweet Corn Soup', 'Veg / chicken — delicious kernels of corn in a thick, luscious soup', 425],
        ['Soups', 'Lemon Coriander Soup', 'Veg / chicken — healthy and filling lemon coriander soup', 425],
        ['Soups', 'Roasted Tomato Basil Soup', 'Oven-roasted plum tomato soup with a fresh hint of basil', 425],
        ['Soups', 'Tom Yum', 'Veg / chicken / prawns — lemongrass, kaffir lime, and galangal shine through', 425],
        ['Soups', 'Murgh Yakhani Shorba', 'Slow-cooked rich chicken soup accentuated with saffron', 445],

        // ═══════════════════════════════════════════
        // 🥗 SALAD — 6 items
        // ═══════════════════════════════════════════
        ['Salad', 'Burrata and Berry Salad', 'Strawberries, blueberries, and californian grapes tossed in mixed green lettuce', 645],
        ['Salad', 'Classical Caesar Salad', 'Veg / chicken / prawns — crispy romaine lettuce tossed in caesar dressing with garlic croutons and parmesan', 425],
        ['Salad', 'Middle Eastern Salad Bowl', 'Veg / chicken — beetroot fattoush, whole wheat pita, grilled sumac vegetables', 545],
        ['Salad', 'Healthy Organic Quinoa', 'Mixed lettuce with organic quinoa, lemon mustard dressing, and green apple', 525],
        ['Salad', 'Som Tom Salad', 'Raw papaya, sweet chili sauce, crushed peanuts, and basil', 465],
        ['Salad', 'Classical Greek Salad', 'Baby cucumber, tomatoes, bell peppers, onions, and feta cheese in homemade dressing', 425],

        // ═══════════════════════════════════════════
        // 🫓 BREAD BASKET — 4 items
        // ═══════════════════════════════════════════
        ['Bread Basket', 'Tandoori Roti', 'Plain / butter', 45],
        ['Bread Basket', 'Laccha Paratha', 'Plain / butter / mirchi', 55],
        ['Bread Basket', 'Naan', 'Plain / butter / missi roti / garlic parmesan / olive naan', 85],
        ['Bread Basket', 'Stuffed Naan', 'Aloo / onion / paneer / keema', 125],

        // ═══════════════════════════════════════════
        // 🥣 SIDES — 3 items
        // ═══════════════════════════════════════════
        ['Sides', 'Indian Green Salad', 'Cucumber, onion, tomato, carrot, and green chilli', 225],
        ['Sides', 'Pappad', '', 125],
        ['Sides', 'Raita', 'Mix / pineapple / boondi raita', 55],

        // ═══════════════════════════════════════════
        // 🍜 MEALS IN THE BOWL — 5 items
        // ═══════════════════════════════════════════
        ['Meals in the Bowl', 'Seoul Bibimbap', 'Veg / chicken — simmering rice with vegetables or chicken, served in traditional dolsot', 585],
        ['Meals in the Bowl', 'Morelos Mexican Burrito Bowl', 'Veg / chicken — hearty bowl with mexican flair, packed with flavor', 585],
        ['Meals in the Bowl', 'Shanghai Bowl', 'Crispy chicken, sesame-sweet soy sauce, and sticky rice', 585],
        ['Meals in the Bowl', 'Gong Bao Bowl', 'Crispy cottage cheese, chilli tomatoes, and soy sauce', 585],
        ['Meals in the Bowl', 'Burmese Khao Suey', 'Veg / chicken — soft and crispy noodles in a fragrant coconut broth', 525],

        // ═══════════════════════════════════════════
        // 🍽️ MAIN COURSE — 14 items
        // ═══════════════════════════════════════════
        ['Main Course', 'Atlantic Salmon', 'Grilled norwegian salmon fillet served with seasonal vegetables and a lemon butter glaze', 2550],
        ['Main Course', 'Australian Lamb Chops', 'Char-grilled lamb chops marinated in herbs and spices, served with garlic mash and jus', 2550],
        ['Main Course', 'Gulf Tiger Prawns', 'Succulent tiger prawns grilled with lemon, ajwain, and coastal-style tandoori spices', 1650],
        ['Main Course', 'Grilled Chicken Breast', 'Served with sautéed vegetables, garlic mashed potato, and red wine jus', 625],
        ['Main Course', 'Portuguese Peri Roast Chicken', 'Herbed and peri peri-marinated chicken breast with sautéed vegetables', 625],
        ['Main Course', 'Malai Paneer Steak', 'Stuffed with sautéed spinach, served with wasabi mash and vegetables', 465],
        ['Main Course', 'Grilled River Sole Fish', 'Sautéed vegetables, garlic mashed potato, and lemon butter sauce', 625],
        ['Main Course', 'Steam Ginger Fish', 'Healthy steamed fish served with stir-fried vegetables', 625],
        ['Main Course', 'Peri Peri Grilled Fish', 'Peri peri-marinated fish with exotic vegetables', 625],
        ['Main Course', 'Pad Thai Noodle', 'Veg / chicken — flat rice noodles with your choice of vegetables or chicken', 485],
        ['Main Course', 'Himalayan Thukpa', 'Veg / chicken — comforting bowl of noodles and vegetables in a savory broth', 395],
        ['Main Course', 'Minced Basil Chicken', 'Thai-style minced basil chicken served with rice', 525],
        ['Main Course', 'Thai Curry Green | Red', 'Veg / chicken — authentic thai curry served with rice, choose green or red', 490],
        ['Main Course', 'Kerala Fish Curry', 'Classic south indian style fish curry, just as flavorful as expected', 690],

        // ═══════════════════════════════════════════
        // 🍝 CHOICE OF NOODLE — 3 items
        // ═══════════════════════════════════════════
        ['Choice of Noodle', 'Hakka Noodle', 'Available as veg / chicken / prawns', 0],
        ['Choice of Noodle', 'Chilli Garlic Noodle', 'Available as veg / chicken / prawns', 0],
        ['Choice of Noodle', 'Burn Garlic Noodle', 'Available as veg / chicken / prawns', 0],

        // ═══════════════════════════════════════════
        // 🍚 CHOICE OF RICE — 4 items
        // ═══════════════════════════════════════════
        ['Choice of Rice', 'Fried Rice', 'Veg / chicken / prawns', 0],
        ['Choice of Rice', 'Chilli Garlic Fried Rice', 'Veg / chicken / prawns', 0],
        ['Choice of Rice', 'Burn Garlic Fried Rice', 'Veg / chicken / prawns', 0],
        ['Choice of Rice', 'Plain Basmati Rice', '', 0],

        // ═══════════════════════════════════════════
        // 🥘 CHOICE OF GRAVY — 4 items
        // ═══════════════════════════════════════════
        ['Choice of Gravy', 'Hot Garlic Sauce', '', 0],
        ['Choice of Gravy', 'Szechuan Sauce', '', 0],
        ['Choice of Gravy', 'Black Bean Sauce', '', 0],
        ['Choice of Gravy', 'Thai Basil Sauce', '', 0],

        // ═══════════════════════════════════════════
        // 🥟 DIM SUM CART — 12 items
        // ═══════════════════════════════════════════
        ['Dim Sum Cart', 'Mushroom and Truffle', 'Steamed dim sum', 475],
        ['Dim Sum Cart', 'Asparagus, Water Chestnut and Corn', 'Steamed dim sum', 495],
        ['Dim Sum Cart', 'Vegetable Crystal Dumpling', 'Translucent crystal skin dumpling', 475],
        ['Dim Sum Cart', 'Edamame Cheese', 'Steamed dim sum', 495],
        ['Dim Sum Cart', 'Chilli Garlic Greens', 'Steamed dim sum', 525],
        ['Dim Sum Cart', 'Spicy Chilli Oil Dumpling', 'Steamed dim sum', 545],
        ['Dim Sum Cart', 'Chicken and Coriander Dumpling', 'Steamed dim sum', 525],
        ['Dim Sum Cart', 'Prawns Harkao', 'Classic prawn dumpling', 575],
        ['Dim Sum Cart', 'Mushroom Kra Pao Bao', 'Steamed bao bun', 525],
        ['Dim Sum Cart', 'Crushed Pepper Tofu Bao', 'Steamed bao bun', 525],
        ['Dim Sum Cart', 'Korean Chicken Bao', 'Steamed bao bun', 545],
        ['Dim Sum Cart', 'Crispy Prawns Bao', 'Steamed bao bun', 575],

        // ═══════════════════════════════════════════
        // 🍣 SUSHI ROLLS — 8 items
        // ═══════════════════════════════════════════
        ['Sushi Rolls', 'Yasai Maki Roll', 'Lettuce, thai cucumber, cream cheese, and pickles', 585],
        ['Sushi Rolls', 'Tempura Asparagus', 'Philadelphia cheese, dill leaves, and crispy asparagus', 595],
        ['Sushi Rolls', 'Cucumber and Avocado Roll', 'Thai baby cucumber, avocado, tenkasu, and spicy mayo', 645],
        ['Sushi Rolls', 'California Mango Roll', 'Fresh mangoes, wasabi, japanese mayo, and pickled ginger', 595],
        ['Sushi Rolls', 'Tuna Roll', 'Fresh tuna, creamy avocado, and spicy mayo wrapped in seasoned sushi rice', 625],
        ['Sushi Rolls', 'Salmon Roll', "Medusa's signature roll for salmon lovers", 725],
        ['Sushi Rolls', 'Dragon Crispy Chicken Sushi Roll', 'Crispy fried chicken, spicy mayo, wasabi, and pickled ginger', 595],
        ['Sushi Rolls', 'Sakura Roll', 'Prawn tempura, thai cucumber, and spicy mayo', 645],

        // ═══════════════════════════════════════════
        // 🍔 BURGERS & SANDWICHES — 10 items
        // ═══════════════════════════════════════════
        ['Burgers & Sandwiches', 'Mushroom Burger', 'A classic mushroom melt with organic himalayan cheese', 365],
        ['Burgers & Sandwiches', 'Patty Lamb Burger', 'Young lamb pulled meat patty char-grilled to perfection', 475],
        ['Burgers & Sandwiches', 'Tex Mex Chicken Burger', 'Spicy golden-fried chicken patty with our famous king sauce', 395],
        ['Burgers & Sandwiches', 'Crispy Fish Burger', 'Panko-crusted fish fillet with lettuce, tartar sauce, and house slaw in a toasted bun', 450],
        ['Burgers & Sandwiches', 'Pesto Chicken Croissant Sandwich', 'Roast chicken marinated in homemade pesto', 465],
        ['Burgers & Sandwiches', 'Smokey Paneer Croissant Sandwich', 'Punjabi-style tandoori paneer stuffed in buttery croissant', 455],
        ['Burgers & Sandwiches', 'Chicken Spicy Tikka Sandwich', 'Cucumber, tomato, lettuce, and charred chicken tikka in multigrain bread', 465],
        ['Burgers & Sandwiches', 'Tandoori Paneer Sandwich', 'Cucumber, tomato, lettuce, and charred paneer tikka in multigrain bread', 445],
        ['Burgers & Sandwiches', 'Triple Club Sandwich', 'Pulled roast chicken, stone-ground dill pesto, served with house chips', 485],
        ['Burgers & Sandwiches', 'Green Club Sandwich', 'Multigrain bread overloaded with greens and cheese', 455],

        // ═══════════════════════════════════════════
        // 🫕 SHARING BOARDS — 4 items
        // ═══════════════════════════════════════════
        ['Sharing Boards', 'Vegetarian Mezze Platter', 'Falafel, paneer shashlik, baba ghanoush, hummus, muhammara, pickled vegetables, and mini soft pita', 665],
        ['Sharing Boards', 'Non-Vegetarian Mezze Platter', 'Fish shish, aamb adana, chicken and cheese adana, chicken shish, and assorted cold dips', 785],
        ['Sharing Boards', 'Tandoori Veg Platter', 'Paneer tikka, veg galouti, tandoori pineapple, and dahi kebab', 655],
        ['Sharing Boards', 'Tandoori Non-Veg Platter', 'Malai chicken tikka, chicken tikka, chicken seekh kebab, mutton kebab, and fish tikka', 785],

        // ═══════════════════════════════════════════
        // 🍕 BRICK OVEN PIZZA — 10 items
        // ═══════════════════════════════════════════
        ['Brick Oven Pizza', 'Margherita', 'As simple and classic as you know', 545],
        ['Brick Oven Pizza', 'Paneer Makhni King', 'A favorite — makhni sauce topped with tandoori paneer tikka', 595],
        ['Brick Oven Pizza', 'Andhara Mushroom Sukka', 'A south indian twist — mushroom sukka on pizza', 625],
        ['Brick Oven Pizza', 'Garden Fresh Pizza', 'Mixed bell peppers, broccoli, mushrooms, red onions, and mozzarella', 525],
        ['Brick Oven Pizza', 'Burrata Arugula', 'Classic burrata pizza with fresh arugula and house-roasted tomatoes', 610],
        ['Brick Oven Pizza', 'Mushroom Pizza', 'Shiitake and button mushrooms roasted in the oven, with a perfect blend of mozzarella and cheddar', 545],
        ['Brick Oven Pizza', 'Chicken Makhni King', "Medusa's own recipe — a must-try!", 625],
        ['Brick Oven Pizza', 'Sausage Masala King', 'Old delhi favorite recipe', 595],
        ['Brick Oven Pizza', 'Pepperoni Pizza', 'Minced lamb tenderloin, chargrilled and topped with mozzarella and cheddar cheese', 625],
        ['Brick Oven Pizza', 'Meat Balls', 'Rogan josh gravy with meatballs on pizza — bold and flavorful', 695],

        // ═══════════════════════════════════════════
        // 🍗 NON-VEG APPETIZER — 2 items
        // ═══════════════════════════════════════════
        ['Non-Veg Appetizer', 'Chilli Basil Chicken', 'Wok-tossed chicken in spicy chilli basil sauce', 465],
        ['Non-Veg Appetizer', 'Mutton Keema Pav', 'Street-style mutton keema served with buttered pav', 0],
    ];

    $count = 0;
    foreach ($menu as $item) {
        $insert->execute([$item[1], $item[2], $item[3], $item[0]]);
        $count++;
    }

    $pdo->commit();
    echo "✓ Inserted $count new menu items across " . count(array_unique(array_column($menu, 0))) . " categories.\n\n";

    // ── Verify ──
    echo "── VERIFICATION ──\n";
    $cats = $pdo->query("SELECT category, COUNT(*) as cnt FROM food_items GROUP BY category ORDER BY MIN(id)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cats as $c) {
        echo str_pad($c['category'], 25) . " → " . $c['cnt'] . " items\n";
    }
    echo "\nTotal: " . $pdo->query("SELECT COUNT(*) FROM food_items")->fetchColumn() . " items\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "No changes were made (rolled back).\n";
}
