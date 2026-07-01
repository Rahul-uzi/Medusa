<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  MEDUSA RESTAURANT — NOTIFICATIONS BACKEND API
 *  Handles CRUD and action triggers for notifications.
 * ══════════════════════════════════════════════════════════════
 */
header('Content-Type: application/json');
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/notifications_helper.php';

// Secure the API: Admins only
requireAdmin();
require_same_origin_unsafe_request();
rate_limit('admin_notifications', 180, 300);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // ── 1. FETCH NOTIFICATIONS ──
    if ($action === 'fetch') {
        $filter = trim($_GET['filter'] ?? 'all');
        $search = trim($_GET['search'] ?? '');
        $limit  = intval($_GET['limit'] ?? 50);
        $offset = intval($_GET['offset'] ?? 0);
        
        $params = [];
        $where_clauses = [];
        
        // Dynamic Filter
        if ($filter !== 'all') {
            $where_clauses[] = "`type` = ?";
            $params[] = $filter;
        }
        
        // Search Term
        if (!empty($search)) {
            $where_clauses[] = "(`title` LIKE ? OR `body` LIKE ?)";
            $wildcard = '%' . $search . '%';
            $params[] = $wildcard;
            $params[] = $wildcard;
        }
        
        $where_sql = '';
        if (count($where_clauses) > 0) {
            $where_sql = ' WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Count Query for Pagination
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications" . $where_sql);
        $count_stmt->execute($params);
        $total_count = intval($count_stmt->fetchColumn() ?: 0);
        
        // Fetch Query
        $sql = "SELECT * FROM notifications" . $where_sql . " ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?";
        
        // Bind parameters manually to handle integer types for limit and offset in PDO
        $stmt = $pdo->prepare($sql);
        $param_idx = 1;
        foreach ($params as $param) {
            $stmt->bindValue($param_idx++, $param);
        }
        $stmt->bindValue($param_idx++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($param_idx++, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Fetch Total Unread Count
        $unread_stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
        $unread_count = intval($unread_stmt->fetchColumn() ?: 0);
        
        echo json_encode([
            'success' => true,
            'unread_count' => $unread_count,
            'total_count' => $total_count,
            'notifications' => $notifications,
            'limit' => $limit,
            'offset' => $offset
        ]);
        exit;
    }
    
    // ── 2. MARK SINGLE READ ──
    if ($action === 'mark_read') {
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Notification marked as read.']);
        exit;
    }
    
    // ── 3. MARK ALL READ ──
    if ($action === 'mark_all_read') {
        $stmt = $pdo->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read.']);
        exit;
    }
    
    // ── 4. DELETE NOTIFICATION ──
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Notification deleted.']);
        exit;
    }
    
    // Action not matching
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Bad Request: Action is invalid.']);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Notifications API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to process notifications request.'
    ]);
}
?>
