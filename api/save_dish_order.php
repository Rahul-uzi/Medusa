<?php
require_once __DIR__ . '/config.php';
requireLogin();

// Allow only admin
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['order']) && is_array($data['order'])) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE food_items SET sort_order = ? WHERE id = ?");
            foreach ($data['order'] as $item) {
                if (isset($item['id']) && isset($item['sort_order'])) {
                    $stmt->execute([$item['sort_order'], $item['id']]);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
