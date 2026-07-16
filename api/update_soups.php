<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

echo "=== UPDATING SOUP SECTION ===\n";

try {
    $pdo->beginTransaction();

    // 1. Delete Vietnamese Pho Soup (ID 3) to prevent duplicates/extra items
    $pdo->exec("DELETE FROM food_items WHERE id = 3");
    $pdo->exec("DELETE FROM dish_customizations WHERE food_item_id = 3");
    echo "Removed Vietnamese Pho Soup (ID 3) from database.\n";

    // 2. Define the exact 11 soups matching the PDF menu
    $soups = [
        [
            'id' => 1,
            'name' => 'Cream of Mushroom',
            'price' => 425.00,
            'description' => 'Roasted button mushrooms, celery, leeks, garlic, and parmesan foam.',
            'diet_type' => 'veg',
            'customization' => null
        ],
        [
            'id' => 358,
            'name' => 'Dal Shorba',
            'price' => 425.00,
            'description' => '(Vegan, Healthy, Gluten Free) lentil soup seasoned with whole spices, ginger garlic, and fresh herbs',
            'diet_type' => 'veg',
            'customization' => null
        ],
        [
            'id' => 5,
            'name' => 'Sweet Corn',
            'price' => 425.00,
            'description' => 'Delicious kernels of corn in a thick, luscious soup.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0]
                ]
            ]
        ],
        [
            'id' => 7,
            'name' => 'Roasted Tomato Basil',
            'price' => 425.00,
            'description' => 'Oven roasted plum tomato with a fresh hint of basil',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0]
                ]
            ]
        ],
        [
            'id' => 27,
            'name' => 'Burmese Khao Suey',
            'price' => 425.00,
            'description' => 'Soft and crispy noodle in coconut broth',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0]
                ]
            ]
        ],
        [
            'id' => 2,
            'name' => 'Manchow',
            'price' => 425.00,
            'description' => 'Mild Asian minced vegetable soup with crispy noodles.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0],
                    ['label' => 'Prawns', 'price_add' => 74.0]
                ]
            ]
        ],
        [
            'id' => 4,
            'name' => 'Hot & Sour',
            'price' => 425.00,
            'description' => 'Bamboo shoots, mushrooms, Chinese cabbage in a thick and spicy soup.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0],
                    ['label' => 'Prawns', 'price_add' => 74.0]
                ]
            ]
        ],
        [
            'id' => 6,
            'name' => 'Lemon Coriander',
            'price' => 425.00,
            'description' => 'Healthy and filling lemon coriander soup.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0]
                ]
            ]
        ],
        [
            'id' => 8,
            'name' => 'Tom Yum',
            'price' => 425.00,
            'description' => 'Lemongrass, kaffir lime, and galangal shine through.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0],
                    ['label' => 'Prawns', 'price_add' => 74.0]
                ]
            ]
        ],
        [
            'id' => 38,
            'name' => 'Himalayan Thukpa',
            'price' => 425.00,
            'description' => 'Comforting bowl of noodles and vegetables in a savory broth.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 25.0]
                ]
            ]
        ],
        [
            'id' => 9,
            'name' => 'Murgh Yakhani Shorba',
            'price' => 450.00,
            'description' => 'Slow-cooked chicken broth infused with aromatic spices, tender chicken pieces and rich flavours',
            'diet_type' => 'nonveg',
            'customization' => null
        ]
    ];

    // Update each soup item
    foreach ($soups as $s) {
        $id = $s['id'];
        $name = $s['name'];
        $price = $s['price'];
        $desc = $s['description'];
        $diet = $s['diet_type'];

        echo "Updating ID $id: $name...\n";
        
        $stmt = $pdo->prepare("UPDATE food_items SET name = ?, price = ?, description = ?, category = 'Soups', subcategory = NULL, diet_type = ? WHERE id = ?");
        $stmt->execute([$name, $price, $desc, $diet, $id]);

        // Clean customizations for this item
        $pdo->prepare("DELETE FROM dish_customizations WHERE food_item_id = ?")->execute([$id]);

        // Insert new customization if any
        if ($s['customization']) {
            $cust = $s['customization'];
            $options_json = json_encode($cust['options']);
            
            $stmt_cust = $pdo->prepare("INSERT INTO dish_customizations (food_item_id, group_name, group_type, is_required, options_json, sort_order) VALUES (?, ?, 'single', 1, ?, 1)");
            $stmt_cust->execute([$id, $cust['group_name'], $options_json]);
        }
    }

    // 3. Normalize all 'non-veg' diet_types in the entire database to 'nonveg'
    $affected = $pdo->exec("UPDATE food_items SET diet_type = 'nonveg' WHERE diet_type = 'non-veg'");
    echo "Normalized $affected diet_type values from 'non-veg' to 'nonveg'.\n";

    $pdo->commit();
    echo "=== SOUP SECTION SYNC COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Transaction failed: " . $e->getMessage() . "\n");
}
?>
