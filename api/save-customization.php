<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
requireAdmin();
require_same_origin_unsafe_request();
rate_limit('admin_customization', 120, 300);

$action = $_POST['action'] ?? '';

try {
    // --- Get all customizations for a given food item ---
    if ($action === 'get_customizations') {
        $food_item_id = intval($_POST['food_item_id']);
        $stmt = $pdo->prepare("SELECT * FROM dish_customizations WHERE food_item_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$food_item_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['options'] = json_decode($r['options_json'], true) ?: [];
            unset($r['options_json']);
        }
        echo json_encode(['success' => true, 'customizations' => $rows]);
        exit;
    }

    // --- Save (upsert) a customization group ---
    if ($action === 'save_customization_group') {
        $food_item_id = intval($_POST['food_item_id']);
        $group_name   = trim($_POST['group_name']);
        $group_type   = in_array($_POST['group_type'], ['single', 'multiple']) ? $_POST['group_type'] : 'single';
        $is_required  = intval($_POST['is_required'] ?? 0);
        $options_raw  = $_POST['options_json'] ?? '[]';
        $sort_order   = intval($_POST['sort_order'] ?? 0);
        $id           = intval($_POST['id'] ?? 0);

        // Validate JSON
        $parsed = json_decode($options_raw, true);
        if (!is_array($parsed)) {
            echo json_encode(['success' => false, 'message' => 'Invalid options JSON']);
            exit;
        }
        $options_json = json_encode($parsed);

        if ($id > 0) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE dish_customizations SET group_name=?, group_type=?, is_required=?, options_json=?, sort_order=? WHERE id=? AND food_item_id=?");
            $stmt->execute([$group_name, $group_type, $is_required, $options_json, $sort_order, $id, $food_item_id]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO dish_customizations (food_item_id, group_name, group_type, is_required, options_json, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$food_item_id, $group_name, $group_type, $is_required, $options_json, $sort_order]);
        }
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    // --- Delete a customization group ---
    if ($action === 'delete_customization_group') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM dish_customizations WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (Exception $e) {
    error_log('Customization API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to process customization request.']);
}
?>
