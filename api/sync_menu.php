<?php
// CLI check: make sure script is run from CLI
if (php_sapi_name() !== 'cli') {
    echo "Warning: Running from web browser.\n";
}

require_once 'D:/xampp/htdocs/Medusa/api/config.php';

$json_path = 'C:/Users/LENOVO/.gemini/antigravity-ide/brain/dfea8c32-8917-427c-9225-09d84a0ae7cd/scratch/menu_changes.json';
if (!file_exists($json_path)) {
    die("Error: menu_changes.json not found.\n");
}

$data = json_decode(file_get_contents($json_path), true);
if (!$data) {
    die("Error: Failed to decode menu_changes.json.\n");
}

$dry_run = true;
if (isset($argv[1]) && $argv[1] === '--execute') {
    $dry_run = false;
}

if ($dry_run) {
    echo "=== DRY RUN (No changes will be committed to DB) ===\n";
} else {
    echo "=== EXECUTING DATABASE UPDATES ===\n";
}

try {
    $pdo->beginTransaction();

    $updates = $data['updates'];
    $insertions = $data['insertions'];

    echo "Processing " . count($updates) . " updates...\n";
    foreach ($updates as $upd) {
        $id = $upd['id'];
        $name = $upd['name'];
        $price = $upd['price'];
        $description = $upd['description'];
        $category = $upd['category'];
        $subcategory = $upd['subcategory'];
        $diet_type = $upd['diet_type'];

        echo "Update ID $id: $name (Price: $price, Category: $category, Subcategory: $subcategory)\n";

        if (!$dry_run) {
            $stmt = $pdo->prepare("UPDATE food_items SET name = ?, price = ?, description = ?, category = ?, subcategory = ?, diet_type = ? WHERE id = ?");
            $stmt->execute([$name, $price, $description, $category, $subcategory, $diet_type, $id]);

            // Clear old customizations for this item
            $stmt_del = $pdo->prepare("DELETE FROM dish_customizations WHERE food_item_id = ?");
            $stmt_del->execute([$id]);

            // Add new customization if any
            if ($upd['customization']) {
                $cust = $upd['customization'];
                $stmt_cust = $pdo->prepare("INSERT INTO dish_customizations (food_item_id, group_name, group_type, is_required, options_json, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_cust->execute([$id, $cust['group_name'], $cust['group_type'], $cust['is_required'], $cust['options_json'], $cust['sort_order']]);
            }
        }
    }

    echo "\nProcessing " . count($insertions) . " insertions...\n";
    foreach ($insertions as $ins) {
        $name = $ins['name'];
        $price = $ins['price'];
        $description = $ins['description'];
        $category = $ins['category'];
        $subcategory = $ins['subcategory'];
        $diet_type = $ins['diet_type'];

        echo "Insert: $name (Price: $price, Category: $category, Subcategory: $subcategory, Diet: $diet_type)\n";

        if (!$dry_run) {
            $stmt = $pdo->prepare("INSERT INTO food_items (name, price, description, category, subcategory, diet_type, is_available, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, 0)");
            $stmt->execute([$name, $price, $description, $category, $subcategory, $diet_type]);
            $new_id = $pdo->lastInsertId();

            // Add customization if any
            if ($ins['customization']) {
                $cust = $ins['customization'];
                $stmt_cust = $pdo->prepare("INSERT INTO dish_customizations (food_item_id, group_name, group_type, is_required, options_json, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_cust->execute([$new_id, $cust['group_name'], $cust['group_type'], $cust['is_required'], $cust['options_json'], $cust['sort_order']]);
            }
        }
    }

    if ($dry_run) {
        $pdo->rollBack();
        echo "\nDry run complete. Transaction rolled back successfully.\n";
    } else {
        $pdo->commit();
        echo "\nExecution complete. Transaction committed successfully.\n";
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("\nTransaction failed: " . $e->getMessage() . "\n");
}
?>
