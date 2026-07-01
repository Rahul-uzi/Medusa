<?php
/**
 * api/get-table-status.php
 * Returns JSON map of table statuses based on today's bookings.
 * Used by book-table-test.html to sync the visual floor plan with the DB.
 *
 * Logic:
 *  - booking_date = TODAY  AND  status IN ('confirmed','pending')
 *    → if booking_time <= NOW (i.e. guest should already be seated) → "occupied"
 *    → if booking_time >  NOW (upcoming today)                      → "reserved"
 *  - No booking today → "available"
 *
 * Response: { "success": true, "statuses": { "A01": "reserved", "B03": "occupied", ... }, "counts": {...} }
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

try {
    $filterDate = trim($_GET['date'] ?? date('Y-m-d'));

    // Fetch all active bookings for the specified date
    $stmt = $pdo->prepare("
        SELECT table_number, booking_time, status
        FROM table_bookings
        WHERE booking_date = ?
          AND status IN ('confirmed', 'pending', 'occupied')
          AND table_number IS NOT NULL
          AND table_number != ''
        ORDER BY booking_time ASC
    ");
    $stmt->execute([$filterDate]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $now = strtotime('now');
    $isToday = ($filterDate === date('Y-m-d'));

    // Build a map: table_key => status
    // table_number stored as e.g. "A01 (Round Table, 2 seats)" — we extract the leading code
    $statusMap = [];

    foreach ($bookings as $row) {
        // Extract leading table code (everything before the first space or opening paren)
        $raw = trim($row['table_number']);
        // Match the table code at the start: e.g. "A01", "BQ1", "T01", "G05", "F02", "L01", "R1"
        if (preg_match('/^([A-Za-z]+\d+)\b/', $raw, $m)) {
            $code = strtoupper($m[1]);
        } else {
            // Fallback: use the full raw string trimmed
            $code = preg_replace('/\s*\(.*\)/', '', $raw);
            $code = strtoupper(trim($code));
        }

        if (empty($code)) continue;

        // Determine status
        if ($row['status'] === 'occupied') {
            $tableStatus = 'occupied';
        } else {
            // If booking time has passed (on today), consider occupied, else reserved
            if ($isToday) {
                $bookingTs = strtotime(date('Y-m-d') . ' ' . $row['booking_time']);
                $tableStatus = ($bookingTs <= $now) ? 'occupied' : 'reserved';
            } else {
                $tableStatus = 'reserved';
            }
        }

        // Occupied wins over reserved if multiple bookings for same table
        if (!isset($statusMap[$code]) || $statusMap[$code] === 'reserved') {
            $statusMap[$code] = $tableStatus;
        }
    }

    // Also get total counts for the summary
    $countStmt = $pdo->prepare("
        SELECT
            COUNT(*)                                        AS total_day,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END)  AS confirmed,
            SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END)  AS pending
        FROM table_bookings
        WHERE booking_date = ?
    ");
    $countStmt->execute([$filterDate]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'statuses'  => $statusMap,      // e.g. { "A01": "reserved", "B03": "occupied" }
        'today'     => [
            'total'     => (int)($counts['total_today'] ?? 0),
            'confirmed' => (int)($counts['confirmed']   ?? 0),
            'pending'   => (int)($counts['pending']     ?? 0),
        ]
    ]);

} catch (Exception $e) {
    error_log('Table status error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Unable to load table status.', 'statuses' => []], 500);
}
