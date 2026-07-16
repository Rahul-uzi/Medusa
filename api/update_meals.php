<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

echo "=== UPDATING MEALS IN THE BOWL SECTION ===\n";

try {
    $pdo->beginTransaction();

    // 1. Delete Morelos Mexican Burrito Bowl (ID 24)
    $pdo->exec("DELETE FROM food_items WHERE id = 24");
    $pdo->exec("DELETE FROM dish_customizations WHERE food_item_id = 24");
    echo "Removed Morelos Mexican Burrito Bowl (ID 24) from database.\n";

    // 2. Define the exact 5 items matching the PDF menu
    $meals = [
        [
            'id' => 23,
            'name' => 'Seoul Bibimbap',
            'price' => 625.00,
            'description' => 'Simmering rice with vegetables or chicken, served in traditional dolsot.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 74.0]
                ]
            ]
        ],
        [
            'id' => 39,
            'name' => 'Minced Basil Chicken',
            'price' => 825.00,
            'description' => 'Thai-style minced basil chicken served with rice.',
            'diet_type' => 'nonveg',
            'customization' => null
        ],
        [
            'id' => 25,
            'name' => 'Shanghai Bowl',
            'price' => 649.00,
            'description' => 'Crispy chicken, sesame-sweet soy sauce, and sticky rice.',
            'diet_type' => 'nonveg',
            'customization' => null
        ],
        [
            'id' => 26,
            'name' => 'Gong Bao Bowl',
            'price' => 625.00,
            'description' => 'Crispy cottage cheese, chilli tomatoes, and soy sauce.',
            'diet_type' => 'veg',
            'customization' => null
        ],
        [
            'id' => 40,
            'name' => 'Thai Curry Green | Red',
            'price' => 695.00,
            'description' => 'Authentic Thai curry served with rice - choose green or red.',
            'diet_type' => 'veg',
            'customization' => [
                'group_name' => 'Type',
                'options' => [
                    ['label' => 'Veg', 'price_add' => 0.0],
                    ['label' => 'Chicken', 'price_add' => 54.0]
                ]
            ]
        ]
    ];

    // Update each item
    foreach ($meals as $m) {
        $id = $m['id'];
        $name = $m['name'];
        $price = $m['price'];
        $desc = $m['description'];
        $diet = $m['diet_type'];

        echo "Updating ID $id: $name...\n";
        
        $stmt = $pdo->prepare("UPDATE food_items SET name = ?, price = ?, description = ?, category = 'Meals in the Bowl', subcategory = NULL, diet_type = ? WHERE id = ?");
        $stmt->execute([$name, $price, $desc, $diet, $id]);

        // Clean customizations for this item
        $pdo->prepare("DELETE FROM dish_customizations WHERE food_item_id = ?")->execute([$id]);

        // Insert new customization if any
        if ($m['customization']) {
            $cust = $m['customization'];
            $options_json = json_encode($cust['options']);
            
            $stmt_cust = $pdo->prepare("INSERT INTO dish_customizations (food_item_id, group_name, group_type, is_required, options_json, sort_order) VALUES (?, ?, 'single', 1, ?, 1)");
            $stmt_cust->execute([$id, $cust['group_name'], $options_json]);
        }
    }

    $pdo->commit();
    echo "=== MEALS IN THE BOWL SECTION SYNC COMPLETED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Transaction failed: " . $e->getMessage() . "\n");
}
?>
