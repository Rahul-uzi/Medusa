<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

echo "=== UPDATING SALAD SECTION ===\n";

try {
    $pdo->beginTransaction();

    // 1. Delete Middle Eastern Salad Bowl (ID 12) and Classical Greek Salad (ID 15)
    $pdo->exec("DELETE FROM food_items WHERE id IN (12, 15)");
    $pdo->exec("DELETE FROM dish_customizations WHERE food_item_id IN (12, 15)");
    echo "Removed Middle Eastern Salad Bowl (ID 12) and Classical Greek Salad (ID 15) from database.\n";

    // 2. Define the exact 4 salads matching the PDF menu
    $salads = [
        [
            'id' => 10,
            'name' => 'Burrata and Berry Salad',
            'price' => 649.00,
            'description' => 'Strawberries, blueberries, and Californian grapes tossed in mixed green lettuce.',
            'diet_type' => 'veg',
            'customization' => null
        ],
        [
            'id' => 14,
            'name' => 'Som Tom Salad',
            'price' => 475.00,
            'description' => 'Raw papaya, sweet chili sauce, crushed peanuts, and basil.',
            'diet_type' => 'veg',
            'customization' => null
        ],
        [
            'id' => 11,
            'name' => 'Classical Caesar Salad',
            'price' => 425.00,
            'description' => 'Crispy romaine lettuce tossed in Caesar dressing with garlic croutons and parmesan.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 74.0],
                    ['label' => 'Prawns', 'price_add' => 225.0]
                ]
            ]
        ],
        [
            'id' => 13,
            'name' => 'Healthy Organic Quinoa',
            'price' => 549.00,
            'description' => 'Mixed lettuce with organic quinoa, lemon mustard dressing, and green apple.',
            'diet_type' => 'veg',
            'customization' => null
        ]
    ];

    // Update each salad item
    foreach ($salads as $s) {
        $id = $s['id'];
        $name = $s['name'];
        $price = $s['price'];
        $desc = $s['description'];
        $diet = $s['diet_type'];

        echo "Updating ID $id: $name...\n";
        
        $stmt = $pdo->prepare("UPDATE food_items SET name = ?, price = ?, description = ?, category = 'Salad', subcategory = NULL, diet_type = ? WHERE id = ?");
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

    $pdo->commit();
    echo "=== SALAD SECTION SYNC COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Transaction failed: " . $e->getMessage() . "\n");
}
?>
