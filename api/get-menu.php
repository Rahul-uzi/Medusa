<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
security_apply_headers('public-short');

try {
    // Fetch all available food items
    $stmt = $pdo->query("SELECT * FROM food_items WHERE is_available = 1 ORDER BY id ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if dish_customizations table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'dish_customizations'")->fetchColumn();

    if ($tableCheck) {
        // Fetch all customizations and group by food_item_id
        $cust_stmt = $pdo->query("SELECT * FROM dish_customizations ORDER BY food_item_id, sort_order ASC");
        $all_customizations = $cust_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parse options_json and group by food_item_id
        $cust_map = [];
        foreach ($all_customizations as $c) {
            $c['options'] = json_decode($c['options_json'], true) ?: [];
            unset($c['options_json']);
            $cust_map[$c['food_item_id']][] = $c;
        }

        // Attach customizations to each food item
        foreach ($items as &$item) {
            $item['customizations'] = $cust_map[$item['id']] ?? [];
        }
    } else {
        // Table doesn't exist yet — return empty customizations
        foreach ($items as &$item) {
            $item['customizations'] = [];
        }
    }

    echo json_encode([
        'success' => true,
        'data'    => $items
    ]);
} catch (Exception $e) {
    error_log('Menu API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Menu is temporarily unavailable.',
        'data'    => []
    ]);
}
?>
