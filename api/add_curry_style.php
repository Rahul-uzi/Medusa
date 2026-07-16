<?php
require_once 'D:/xampp/htdocs/Medusa/api/config.php';

try {
    echo "=== ADDING CURRY STYLE CUSTOMIZATION ===\n";
    
    // Update image_url to green curry as default
    $pdo->exec("UPDATE food_items SET image_url = 'thai_curry_green.jpg' WHERE id = 40");
    echo "Updated image_url to 'thai_curry_green.jpg' for ID 40.\n";

    // Delete existing customizations for ID 40 to avoid duplicating Type
    // (Wait, we can keep Type and just insert Curry Style)
    // Let's check if Curry Style already exists
    $stmt = $pdo->prepare("SELECT * FROM dish_customizations WHERE food_item_id = 40 AND group_name = 'Curry Style'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $options = [
            ['label' => 'Green', 'price_add' => 0.0],
            ['label' => 'Red', 'price_add' => 0.0]
        ];
        $options_json = json_encode($options);
        
        $stmt_ins = $pdo->prepare("INSERT INTO dish_customizations (food_item_id, group_name, group_type, is_required, options_json, sort_order) VALUES (40, 'Curry Style', 'single', 1, ?, 2)");
        $stmt_ins->execute([$options_json]);
        echo "Added 'Curry Style' customization group with Green and Red options for ID 40.\n";
    } else {
        echo "'Curry Style' customization group already exists.\n";
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
