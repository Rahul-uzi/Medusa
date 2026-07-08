<?php
require_once __DIR__ . '/api/config.php';

$order_id = $_GET['order_id'] ?? '';
$order = null;
$error_msg = null;

if (!empty($order_id)) {
    // 1. Fetch from Database first as primary source of truth
    try {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
        $stmt->execute([$order_id]);
        $db_order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_order) {
            $db_user_id = $db_order['user_id'];
            $session_user_id = $_SESSION['user_id'] ?? null;
            
            // Access control check
            if ($db_user_id !== null) {
                $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
                if (!$is_admin && ($session_user_id === null || intval($db_user_id) !== intval($session_user_id))) {
                    $error_msg = "Access Denied: You do not have permission to view this order details.";
                }
            }

            if (!$error_msg) {
                // Fetch items with images
                $items_stmt = $pdo->prepare("SELECT oi.item_name AS name, oi.price, oi.quantity, fi.image_url FROM order_items oi LEFT JOIN food_items fi ON oi.food_item_id = fi.id WHERE oi.order_id = ?");
                $items_stmt->execute([$db_order['id']]);
                $db_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate subtotal
                $subtotal = 0;
                foreach ($db_items as $item) {
                    $subtotal += floatval($item['price']) * intval($item['quantity']);
                }

                // Try to load email from users table
                $customer_email = '';
                if ($db_user_id) {
                    $u_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $u_stmt->execute([$db_user_id]);
                    $customer_email = $u_stmt->fetchColumn() ?: '';
                }

                // If orders.json has more complete metadata (like customer email/order notes), merge it
                $json_email = '';
                $json_message = '';
                $json_payment_id = 'pay_' . substr(md5($order_id), 0, 14);
                $json_sms_status = 'success';
                
                $orders_file = __DIR__ . '/orders.json';
                if (file_exists($orders_file)) {
                    $json_orders = json_decode(file_get_contents($orders_file), true);
                    if (isset($json_orders[$order_id])) {
                        $json_order = $json_orders[$order_id];
                        $json_email = $json_order['customer_email'] ?? '';
                        $json_message = $json_order['message'] ?? '';
                        $json_payment_id = $json_order['payment_id'] ?? $json_payment_id;
                        $json_sms_status = $json_order['sms_status'] ?? 'success';
                    }
                }

                $order = [
                    'order_id' => $db_order['order_number'],
                    'customer_name' => $db_order['customer_name'],
                    'customer_phone' => $db_order['customer_phone'],
                    'customer_email' => !empty($json_email) ? $json_email : $customer_email,
                    'delivery_address' => $db_order['delivery_address'],
                    'message' => $json_message,
                    'payment_id' => $json_payment_id,
                    'cart_items' => $db_items,
                    'subtotal' => $subtotal,
                    'gst' => floatval($db_order['tax_amount'] ?? ($subtotal * 0.18)),
                    'packing' => floatval($db_order['packing_charge'] ?? 0.00),
                    'delivery' => floatval($db_order['delivery_charge'] ?? 40.00),
                    'total' => floatval($db_order['total_amount']),
                    'status' => $db_order['order_status'],
                    'sms_status' => $json_sms_status,
                    'sms_response' => '',
                    'created_at' => $db_order['order_date']
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Database order fetch failed: " . $e->getMessage());
    }

    // 2. Fallback to orders.json if database fetch failed or returned nothing
    if (!$order && !$error_msg) {
        $orders_file = __DIR__ . '/orders.json';
        if (file_exists($orders_file)) {
            $orders = json_decode(file_get_contents($orders_file), true);
            if (isset($orders[$order_id])) {
                $order = $orders[$order_id];
            }
        }
    }
}

// Redirect or show error if access is denied
if ($error_msg) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
<style id="nav-pt-style">
    #nav-page-transition {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #120307;
        z-index: 999999;
        opacity: 1;
        transition: opacity 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: all;
    }
    #nav-page-transition.nav-pt-fadeout {
        opacity: 0 !important;
        pointer-events: none !important;
    }
</style>

<meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Medusa Luxury</title>
        <!-- Global Theme Controller -->
        <script src="assets/js/theme-toggle.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
        <style>
            body { background: #000000; color: #ffffff; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .error-card { background: #0a0a0a; border: 1px solid rgba(223, 186, 134, 0.15); border-radius: 16px; padding: 3rem; text-align: center; max-width: 500px; width: 90%; }
            .btn-back { background: #dfba86; color: #000; font-weight: 600; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 8px; display: inline-block; margin-top: 1.5rem; }
        </style>
    
    <!-- CRITICAL SPA PAGE TRANSITION CSS & SCRIPT -->
    <style>
        html, body { background-color: #120307; }
        #nav-page-transition {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: #120307;
            pointer-events: all;
            opacity: 1;
            transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        #nav-page-transition.nav-pt-fadeout {
            opacity: 0;
            pointer-events: none;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var overlay = document.getElementById('nav-page-transition');
            if(overlay) {
                setTimeout(function() {
                    overlay.classList.add('nav-pt-fadeout');
                }, 100);
            }
        });
    </script>
</head>
    <body>
<div id="nav-page-transition"></div>

<div class="error-card">
            <h2 class="text-danger mb-3">Error</h2>
            <p><?php echo htmlspecialchars($error_msg); ?></p>
            <a href="menutest.html" class="btn-back">Go to Menu</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fallback to mock order if not found (useful for testing/demoing)
if (!$order) {
    $order = [
        'order_id' => 'ORD-DEMO',
        'customer_name' => 'John Doe',
        'customer_phone' => '9876543210',
        'customer_email' => 'johndoe@example.com',
        'delivery_address' => '123, Main Street, Apt 4B, New York, Delhi - 10001, India',
        'message' => 'Please deliver hot and fresh.',
        'payment_id' => 'pay_test_payment_id',
        'cart_items' => [
            ['name' => 'Premium Margherita Pizza', 'price' => 299.00, 'quantity' => 1],
            ['name' => 'Paneer Tikka', 'price' => 199.00, 'quantity' => 2]
        ],
        'subtotal' => 697.00,
        'gst' => 125.46,
        'delivery' => floatval(get_env_var('DELIVERY_CHARGE', '40.00')),
        'total' => 862.46,
        'status' => 'Paid',
        'created_at' => date('Y-m-d H:i:s')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed Successfully - Medusa</title>
    <!-- Global Theme Controller -->
    <script src="assets/js/theme-toggle.js"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #0D2016;
            --bg-secondary: #132F20;
            --gold: #dfba86;
            --gold-light: #f3dfc1;
            --white: #FAF7F0;
            --gray: #A8A196;
            --success-color: #2ec4b6;
            --rosewood: #5A1827;
            --rosewood-light: #7E2638;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background-color: var(--bg-dark) !important;
            color: var(--white) !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 0;
        }

        /* Success Card & Glassmorphic Container */
        .success-card {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 3rem;
            max-width: 680px;
            width: 95%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Checkmark Animation */
        .success-icon-container {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(46, 196, 182, 0.1);
            border: 2px solid var(--success-color);
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1.5rem;
            color: var(--success-color);
            font-size: 2.2rem;
            animation: scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        .success-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .success-subtitle {
            color: var(--gray);
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        /* SMS Confirmation Badge */
        .sms-badge {
            background: rgba(223, 186, 134, 0.08);
            border: 1px solid rgba(223, 186, 134, 0.2);
            color: var(--gold-light);
            border-radius: 12px;
            padding: 0.8rem 1.2rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            margin-bottom: 2.5rem;
            animation: fadeIn 0.8s ease forwards;
            font-weight: 500;
        }

        .sms-badge i {
            color: var(--gold);
            font-size: 1.1rem;
        }

        /* Invoice Container */
        .invoice-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 14px;
            padding: 2rem;
            text-align: left;
            margin-bottom: 2.5rem;
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .invoice-header h4 {
            font-family: 'Playfair Display', serif;
            color: #ffffff;
            font-weight: 700;
            margin: 0;
        }

        .invoice-meta {
            color: var(--gray);
        }

        .invoice-meta span {
            display: block;
            margin-bottom: 0.2rem;
        }

        .invoice-meta strong {
            color: #ffffff;
        }

        /* Billing/Shipping Address Grid */
        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 1.5rem;
            font-size: 0.88rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 1.5rem;
        }

        .address-col-title {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 1px;
            color: var(--gold);
            margin-bottom: 0.5rem;
        }

        .address-text {
            color: var(--gray);
            line-height: 1.5;
        }

        /* Invoice Table */
        .invoice-table {
            width: 100%;
            margin-bottom: 1.5rem;
        }

        .invoice-table th {
            color: var(--gray);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 0.6rem 0;
            font-weight: 600;
        }

        .invoice-table td {
            padding: 0.8rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.92rem;
        }

        .item-total {
            text-align: right;
            font-weight: 600;
            color: #ffffff;
        }

        /* Summary Total Rows */
        .totals-section {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 1rem;
            font-size: 0.92rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.6rem;
        }

        .total-row.grand-total {
            font-size: 1.2rem;
            font-weight: 700;
            border-top: 1.5px dashed rgba(255, 255, 255, 0.1);
            padding-top: 0.8rem;
            margin-top: 0.8rem;
            color: var(--gold-light);
        }

        .total-row.grand-total span:last-child {
            font-size: 1.35rem;
            color: var(--gold);
            font-weight: 800;
        }

        /* Action Buttons */
        .btn-group-success {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-success-action {
            background-color: var(--rosewood);
            color: var(--gold);
            border: 1px solid var(--gold);
            border-radius: 8px;
            padding: 0.9rem 1.8rem;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-success-action:hover {
            background-color: var(--rosewood-light);
            transform: translateY(-2px);
            color: #ffffff;
        }

        .btn-secondary-action {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.9rem 1.8rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary-action:hover {
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
        }

        /* Animations */
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.6);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Printable styles - Professional Invoice */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                background: #ffffff !important;
                color: #333333 !important;
                padding: 0;
                font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .success-card {
                border: none !important;
                box-shadow: none !important;
                background: #ffffff !important;
                color: #333333 !important;
                padding: 15mm 20mm !important;
                max-width: 100% !important;
                width: 100% !important;
                border-radius: 0 !important;
                margin: 0 !important;
            }
            .success-icon-container, 
            .sms-badge, 
            .btn-group-success,
            .success-subtitle,
            .success-title {
                display: none !important;
            }
            .invoice-box {
                background: #ffffff !important;
                border: none !important;
                color: #333333 !important;
                padding: 0 !important;
                margin: 0 !important;
                border-radius: 0 !important;
            }
            .invoice-header {
                border-bottom: 2px solid #222222 !important;
                padding-bottom: 1.5rem !important;
                margin-bottom: 2rem !important;
            }
            .invoice-header h4 {
                color: #111111 !important;
                font-size: 2rem !important;
                font-family: 'Playfair Display', serif !important;
            }
            .invoice-header h2 {
                color: #111111 !important;
            }
            .invoice-meta {
                color: #555555 !important;
                font-size: 0.95rem !important;
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
            }
            .invoice-meta strong {
                color: #111111 !important;
            }
            .address-col-title {
                color: #777777 !important;
                font-size: 0.85rem !important;
                border-bottom: 1px solid #eeeeee !important;
                padding-bottom: 0.4rem !important;
                margin-bottom: 0.8rem !important;
            }
            .address-text {
                color: #222222 !important;
                font-size: 0.95rem !important;
            }
            .address-text strong {
                color: #111111 !important;
            }
            .address-grid {
                border-bottom: none !important;
                margin-bottom: 2.5rem !important;
            }
            .invoice-items-list {
                margin-bottom: 2rem !important;
                border-top: 2px solid #222222 !important;
            }
            .invoice-item-row {
                border-bottom: 1px solid #eeeeee !important;
                padding: 0.8rem 0 !important;
            }
            .invoice-item-row strong {
                color: #111111 !important;
            }
            .invoice-item-row span {
                color: #555555 !important;
            }
            .invoice-item-row div:last-child {
                color: #111111 !important;
            }
            .totals-wrapper {
                width: 45% !important;
                float: right !important;
                margin-top: 1rem !important;
            }
            .totals-section {
                width: 100% !important;
            }
            .total-row {
                font-size: 0.95rem !important;
                padding: 0.2rem 0 !important;
                margin-bottom: 0.3rem !important;
            }
            .total-row span {
                color: #444444 !important;
            }
            .total-row span:last-child {
                color: #222222 !important;
            }
            .dashed-divider {
                border-top: 2px solid #111111 !important;
                margin: 0.5rem 0 0.8rem 0 !important;
            }
            .total-row.grand-total span {
                color: #111111 !important;
                font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif !important;
                font-size: 1.25rem !important;
                font-weight: bold !important;
            }
            
            .print-footer {
                display: block !important;
            }
            
            /* Clearfix for totals */
            .invoice-box::after {
                content: "";
                display: table;
                clear: both;
            }
        }

        /* Feedback Popup Styling */
        .feedback-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.4s ease;
            backdrop-filter: blur(8px);
        }

        .feedback-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }

        .feedback-card {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(223, 186, 134, 0.15);
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
            position: relative;
            text-align: center;
            transform: scale(0.85) translateY(20px);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            color: var(--white);
            animation: none;
        }

        .feedback-overlay.show .feedback-card {
            transform: scale(1) translateY(0);
        }

        .feedback-close-btn {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            background: transparent;
            border: none;
            color: var(--gray);
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .feedback-close-btn:hover {
            color: var(--gold);
            background: rgba(255, 255, 255, 0.03);
        }

        .feedback-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.75rem;
        }

        .feedback-subtitle {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .feedback-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin: 1.5rem 0;
        }

        .feedback-stars-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 1.2rem 0;
        }

        .feedback-star {
            font-size: 2.2rem;
            color: rgba(255, 255, 255, 0.12);
            cursor: pointer;
            transition: var(--transition);
            background: transparent;
            border: none;
            padding: 0;
        }

        .feedback-star:focus {
            outline: none;
            transform: scale(1.15);
        }

        .feedback-star.hover,
        .feedback-star.selected {
            color: var(--gold);
            text-shadow: 0 0 12px rgba(223, 186, 134, 0.4);
        }

        .feedback-helper-text {
            font-size: 0.88rem;
            color: var(--gold-light);
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .feedback-textarea-wrapper {
            text-align: left;
            margin-bottom: 1.5rem;
        }

        .feedback-label {
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            margin-bottom: 0.5rem;
            display: block;
        }

        .feedback-textarea {
            background-color: rgba(255, 255, 255, 0.02) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            padding: 0.8rem 1.1rem !important;
            font-size: 0.95rem !important;
            width: 100%;
            height: 100px;
            resize: none;
            transition: var(--transition);
        }

        .feedback-textarea::placeholder {
            color: rgba(255, 255, 255, 0.25) !important;
        }

        .feedback-textarea:focus {
            background-color: rgba(255, 255, 255, 0.04) !important;
            border-color: var(--gold) !important;
            box-shadow: 0 0 0 0.25rem rgba(223, 186, 134, 0.15) !important;
            outline: none;
        }

        .feedback-char-count {
            display: flex;
            justify-content: flex-end;
            font-size: 0.75rem;
            color: var(--gray);
            margin-top: 0.35rem;
        }

        .feedback-validation-error {
            color: #ffb3b8;
            background: rgba(230, 57, 70, 0.08);
            border: 1px solid rgba(230, 57, 70, 0.15);
            border-radius: 6px;
            padding: 0.6rem;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: none;
            font-weight: 500;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .feedback-btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .feedback-btn-submit {
            background-color: var(--rosewood);
            color: var(--gold);
            border: 1px solid var(--gold);
            border-radius: 8px;
            padding: 0.85rem 1.8rem;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition);
            cursor: pointer;
            flex-grow: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .feedback-btn-submit:hover {
            background-color: var(--rosewood-light);
            color: #ffffff;
            transform: translateY(-2px);
        }

        .feedback-btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .feedback-btn-cancel {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.85rem 1.8rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            cursor: pointer;
            flex-grow: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .feedback-btn-cancel:hover {
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
        }

        .feedback-success-icon {
            font-size: 3.5rem;
            color: var(--success-color);
            margin-bottom: 1.25rem;
        }

        @media print {
            .feedback-overlay {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="success-card">
        <div class="success-icon-container">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="success-title">Payment Successful</h1>
        <p class="success-subtitle">Thank you! Your order has been placed and is being prepared.</p>

        <?php
        $sms_status = $order['sms_status'] ?? 'not_checked';
        $sms_response = $order['sms_response'] ?? '';
        
        if ($sms_status !== 'not_sent'):
            if ($sms_status === 'success'): ?>
                <div class="sms-badge">
                    <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                    <span>Bill details sent via SMS to <strong>+91 <?php echo htmlspecialchars(substr($order['customer_phone'], 0, 5) . ' ' . substr($order['customer_phone'], 5)); ?></strong></span>
                </div>
            <?php elseif ($sms_status === 'failed' || $sms_status === 'api_error' || $sms_status === 'error'): ?>
                <div class="sms-badge" style="background: rgba(230, 57, 70, 0.08); border-color: rgba(230, 57, 70, 0.2); color: #ffb3b8;">
                    <i class="fas fa-exclamation-triangle" style="color: #ff3333; margin-right: 5px;"></i>
                    <span>SMS delivery failed to <strong>+91 <?php echo htmlspecialchars(substr($order['customer_phone'], 0, 5) . ' ' . substr($order['customer_phone'], 5)); ?></strong> (Gateway Offline/Unreachable)</span>
                </div>
            <?php else: ?>
                <div class="sms-badge">
                    <i class="fas fa-sms"></i>
                    <span>Bill details sent via SMS to <strong>+91 <?php echo htmlspecialchars(substr($order['customer_phone'], 0, 5) . ' ' . substr($order['customer_phone'], 5)); ?></strong></span>
                </div>
            <?php endif;
        endif; ?>

        <div class="invoice-box" id="invoice">
            <div class="invoice-header" style="align-items: center;">
                <div style="display: flex; align-items: center;">
                    <img src="assets/images/medusaa.png" alt="Medusa Luxury Dining" style="height: 220px; object-fit: contain;">
                </div>
                <div class="text-end" style="text-align: right;">
                    <h2 style="margin: 0; font-family: 'Playfair Display', serif; color: var(--gold); font-size: 2.2rem; letter-spacing: 1px;">INVOICE</h2>
                    <div class="invoice-meta mt-1">
                        <span>Invoice No: <strong style="font-family: monospace;">#<?php echo htmlspecialchars($order['order_id']); ?></strong></span>
                        <span>Date: <strong><?php echo htmlspecialchars(date('F j, Y, g:i a', strtotime($order['created_at']))); ?></strong></span>
                    </div>
                </div>
            </div>

            <div class="invoice-meta" style="display: flex; justify-content: space-between; margin-bottom: 2rem; background: rgba(255,255,255,0.02); padding: 1rem 1.5rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                <div>
                    <span>Payment Status: <strong class="text-success"><i class="fas fa-check-circle" style="font-size: 0.85rem;"></i> Paid</strong></span>
                </div>
                <div class="text-end">
                    <span>Payment ID: <strong style="font-family: monospace;"><?php echo htmlspecialchars($order['payment_id']); ?></strong></span>
                </div>
            </div>

            <div class="address-grid">
                <div>
                    <div class="address-col-title">Invoice To</div>
                    <div class="address-text">
                        <strong style="color: var(--white); font-size: 1.05rem; display: block; margin-bottom: 4px;"><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                        <?php echo htmlspecialchars($order['customer_phone']); ?><br>
                        <?php echo htmlspecialchars($order['customer_email']); ?>
                    </div>
                </div>
                <div>
                    <div class="address-col-title">Ship To</div>
                    <div class="address-text">
                        <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($order['message'])): ?>
                <div class="mb-4" style="font-size: 0.88rem; background: rgba(223, 186, 134, 0.05); padding: 1rem 1.5rem; border-radius: 8px; border-left: 3px solid var(--gold);">
                    <div class="address-col-title" style="color: var(--gold); border: none; margin-bottom: 4px; padding: 0;">Order Notes</div>
                    <div class="address-text" style="font-style: italic;">
                        "<?php echo htmlspecialchars($order['message']); ?>"
                    </div>
                </div>
            <?php endif; ?>

            <div class="invoice-items-list" style="margin-bottom: 2rem; border-top: 1px dashed rgba(255,255,255,0.1);">
                <?php foreach ($order['cart_items'] as $item): ?>
                    <div class="invoice-item-row" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px dashed rgba(255,255,255,0.1);">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                                <div style="width: 60px; height: 60px; border-radius: 8px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--gold);">
                                    <i class="fas fa-utensils"></i>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; flex-direction: column;">
                                <strong style="font-size: 1.05rem; font-weight: 600; color: var(--white); margin-bottom: 4px;"><?php echo htmlspecialchars($item['name']); ?></strong>
                                <span style="font-size: 0.85rem; color: var(--gray);">Qty: <?php echo intval($item['quantity']); ?></span>
                            </div>
                        </div>
                        <div style="font-size: 1.05rem; font-weight: 500; color: var(--white);">
                            ₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="totals-wrapper">
                <div class="totals-section" style="width: 100%;">
                    <?php 
                    $gst_percent = ($order['subtotal'] > 0) ? round(($order['gst'] / $order['subtotal']) * 100) : 18;
                    $packing_charge = $order['packing'] ?? 0.00;
                    $computed_total_before_discount = $order['subtotal'] + $order['gst'] + $packing_charge + $order['delivery'];
                    $discount_amount = $computed_total_before_discount - $order['total'];
                    ?>
                    <div class="total-row" style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; color: var(--gray);">
                        <span>Subtotal</span>
                        <span style="color: var(--white);">₹<?php echo number_format($order['subtotal'], 2); ?></span>
                    </div>
                    <div class="total-row" style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; color: var(--gray);">
                        <span>GST (<?php echo $gst_percent; ?>%)</span>
                        <span style="color: var(--white);">₹<?php echo number_format($order['gst'], 2); ?></span>
                    </div>
                    <?php if ($packing_charge > 0): ?>
                    <div class="total-row" style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; color: var(--gray);">
                        <span>Packing Charges</span>
                        <span style="color: var(--white);">₹<?php echo number_format($packing_charge, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="total-row" style="display: flex; justify-content: space-between; margin-bottom: 1rem; color: var(--gray);">
                        <span>Delivery Fee</span>
                        <span style="color: var(--white);">₹<?php echo number_format($order['delivery'], 2); ?></span>
                    </div>
                    
                    <?php if ($discount_amount > 0.01): ?>
                    <div class="total-row" style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; color: var(--gray);">
                        <span>Total Discount <i class="fas fa-info-circle" style="color: var(--gold); margin-left: 5px;" title="Includes all applied discounts"></i></span>
                        <span style="color: var(--white);">-₹<?php echo number_format($discount_amount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="dashed-divider" style="border-top: 1px dashed rgba(255,255,255,0.1); margin: 1rem 0 1.5rem 0;"></div>
                    
                    <div class="total-row grand-total" style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-family: 'Playfair Display', serif; color: var(--gold); font-size: 1.5rem; letter-spacing: 0.5px;">Grand Total</span>
                        <span style="font-family: 'Playfair Display', serif; color: var(--gold); font-size: 1.5rem; font-weight: 700;">₹<?php echo number_format($order['total'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div style="clear: both;"></div>
            
            <div style="margin-top: 3rem; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem; display: none;" class="print-footer">
                <p style="font-size: 0.85rem; color: #777; margin: 0;">Thank you for dining with Medusa Luxury. We hope to serve you again soon.</p>
                <p style="font-size: 0.8rem; color: #999; margin: 5px 0 0 0;">Medusa Luxury Dining | GSTIN: 22AAAAA0000A1Z5 | contact@medusa.local</p>
            </div>
        </div>

        <div class="btn-group-success">
            <a href="menutest.html" class="btn-success-action">
                <i class="fas fa-arrow-left"></i> Order More Food
            </a>
            <button onclick="window.print()" class="btn-secondary-action">
                <i class="fas fa-print"></i> Print Invoice
            </button>
        </div>

    <!-- Scoped Feedback Popup Overlay -->
    <div id="feedbackOverlay" class="feedback-overlay" aria-modal="true" role="dialog" aria-labelledby="feedbackTitle">
        <div class="feedback-card">
            <button id="feedbackClose" class="feedback-close-btn" aria-label="Close Feedback Dialog">
                <i class="fas fa-times"></i>
            </button>
            
            <div id="feedbackFormContent">
                <h2 id="feedbackTitle" class="feedback-title">🎉 Thank You For Your Order!</h2>
                <p class="feedback-subtitle">Your order has been successfully placed and is being prepared.</p>
                
                <p class="feedback-subtitle">We would love your feedback about your experience with our restaurant.</p>
                
                <div class="feedback-divider"></div>
                
                <p class="feedback-helper-text">How was your experience?</p>
                
                <div class="feedback-stars-container" role="radiogroup" aria-label="Rate your experience from 1 to 5 stars">
                    <button type="button" class="feedback-star" data-rating="1" role="radio" aria-checked="false" aria-label="1 star"><i class="fas fa-star"></i></button>
                    <button type="button" class="feedback-star" data-rating="2" role="radio" aria-checked="false" aria-label="2 stars"><i class="fas fa-star"></i></button>
                    <button type="button" class="feedback-star" data-rating="3" role="radio" aria-checked="false" aria-label="3 stars"><i class="fas fa-star"></i></button>
                    <button type="button" class="feedback-star" data-rating="4" role="radio" aria-checked="false" aria-label="4 stars"><i class="fas fa-star"></i></button>
                    <button type="button" class="feedback-star" data-rating="5" role="radio" aria-checked="false" aria-label="5 stars"><i class="fas fa-star"></i></button>
                </div>
                
                <div id="feedbackValidationError" class="feedback-validation-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Please select a rating before submitting.</span>
                </div>

                <div class="feedback-textarea-wrapper">
                    <label for="feedbackReview" class="feedback-label">Tell us more (Optional)</label>
                    <textarea id="feedbackReview" class="feedback-textarea" placeholder="Share your experience..." maxlength="300"></textarea>
                    <div class="feedback-char-count"><span id="feedbackCharCount">0</span>/300</div>
                </div>
                
                <div class="feedback-btn-group">
                    <button type="button" id="feedbackCancel" class="feedback-btn-cancel">Maybe Later</button>
                    <button type="button" id="feedbackSubmit" class="feedback-btn-submit">Submit Feedback</button>
                </div>
            </div>
            
            <div id="feedbackSuccessContent" style="display: none;">
                <div class="feedback-success-icon">
                    <i class="fas fa-check-circle" id="feedbackSuccessIconInner"></i>
                </div>
                <h2 class="feedback-title" id="feedbackSuccessTitle">✅ Thank you for your feedback!</h2>
                <p class="feedback-subtitle" id="feedbackSuccessSubtitle" style="margin-bottom: 0;">Your feedback helps us improve our service.</p>
                
                <!-- Coupon Reward Container -->
                <div id="feedbackCouponReward" style="display: none; margin-top: 1.5rem; padding: 1.5rem; background: rgba(223, 186, 134, 0.08); border: 1px dashed #dfba86; border-radius: 12px; text-align: center;">
                    <div style="font-size: 1.25rem; margin-bottom: 0.5rem; font-weight: bold; color: #dfba86;">🎉 Reward Unlocked!</div>
                    <p style="font-size: 0.9rem; color: #a09f9f; margin-bottom: 1rem;">Use this coupon code on your next order to receive <strong class="text-gold" id="rewardDiscount">10% OFF</strong>:</p>
                    <div class="d-flex align-items-center justify-content-center gap-2" style="background: rgba(0,0,0,0.4); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 1rem; width: fit-content; margin-left: auto; margin-right: auto;">
                        <code id="rewardCouponCode" style="font-family: monospace; font-size: 1.15rem; color: #dfba86; font-weight: bold; letter-spacing: 1px;"></code>
                        <button type="button" id="copyCouponBtn" class="btn btn-sm btn-outline-light" style="padding: 0.25rem 0.5rem; font-size: 0.78rem; border-color: rgba(255,255,255,0.2);"><i class="far fa-copy"></i> Copy</button>
                    </div>
                    <p style="font-size: 0.78rem; color: #a09f9f; margin-bottom: 0;">Expires on: <span id="rewardExpiry" style="color: #ffffff; font-weight: bold;"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-trigger print/PDF download dialog if the print parameter is set to 1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === '1') {
            window.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => {
                    window.print();
                }, 1000); // 1-second delay to ensure graphics and animations load completely
            });
        }

        // Feedback Popup Functionality
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const isPrintMode = urlParams.get('print') === '1';
            
            // If the user requested direct printing, do not show the feedback dialog
            if (isPrintMode) {
                return;
            }

            const overlay = document.getElementById('feedbackOverlay');
            const closeBtn = document.getElementById('feedbackClose');
            const cancelBtn = document.getElementById('feedbackCancel');
            const submitBtn = document.getElementById('feedbackSubmit');
            const formContent = document.getElementById('feedbackFormContent');
            const successContent = document.getElementById('feedbackSuccessContent');
            const validationError = document.getElementById('feedbackValidationError');
            const stars = document.querySelectorAll('.feedback-star');
            const reviewTextarea = document.getElementById('feedbackReview');
            const charCount = document.getElementById('feedbackCharCount');
            
            let selectedRating = 0;
            const currentOrderId = "<?php echo htmlspecialchars($order['order_id']); ?>";

            // 1. Open popup after 1 second
            setTimeout(() => {
                overlay.classList.add('show');
                // Set focus to the first star button for accessibility
                if (stars.length > 0) {
                    stars[0].focus();
                }
            }, 1000);

            // 2. Rating selection logic (click and hover support)
            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    selectedRating = parseInt(star.getAttribute('data-rating'));
                    updateStarHighlights(selectedRating);
                    validationError.style.display = 'none'; // Hide validation warning when a star is selected
                });

                star.addEventListener('mouseenter', () => {
                    const hoverValue = parseInt(star.getAttribute('data-rating'));
                    highlightStarsOnHover(hoverValue);
                });

                star.addEventListener('mouseleave', () => {
                    highlightStarsOnHover(0); // Clear hover states
                });
                
                // Accessible Keyboard controls for Rating selection
                star.addEventListener('keydown', (e) => {
                    let nextIndex = index;
                    if (e.key === 'ArrowRight') {
                        nextIndex = (index + 1) % stars.length;
                        stars[nextIndex].focus();
                        e.preventDefault();
                    } else if (e.key === 'ArrowLeft') {
                        nextIndex = (index - 1 + stars.length) % stars.length;
                        stars[nextIndex].focus();
                        e.preventDefault();
                    } else if (e.key === ' ' || e.key === 'Enter') {
                        star.click();
                        e.preventDefault();
                    }
                });
            });

            function updateStarHighlights(rating) {
                stars.forEach((star) => {
                    const starVal = parseInt(star.getAttribute('data-rating'));
                    if (starVal <= rating) {
                        star.classList.add('selected');
                        star.setAttribute('aria-checked', 'true');
                    } else {
                        star.classList.remove('selected');
                        star.setAttribute('aria-checked', 'false');
                    }
                });
            }

            function highlightStarsOnHover(hoverValue) {
                stars.forEach((star) => {
                    const starVal = parseInt(star.getAttribute('data-rating'));
                    if (hoverValue > 0 && starVal <= hoverValue) {
                        star.classList.add('hover');
                    } else {
                        star.classList.remove('hover');
                    }
                });
            }

            // 3. Character counting for review
            reviewTextarea.addEventListener('input', () => {
                const len = reviewTextarea.value.length;
                charCount.textContent = len;
            });

            // 4. Closing actions
            const closeFeedbackPopup = () => {
                overlay.classList.remove('show');
            };

            closeBtn.addEventListener('click', closeFeedbackPopup);
            cancelBtn.addEventListener('click', closeFeedbackPopup);
            
            // Close when clicking outside on the background overlay
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    closeFeedbackPopup();
                }
            });

            // Escape key closes popup
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && overlay.classList.contains('show')) {
                    closeFeedbackPopup();
                }
            });

            // 5. Submit feedback asynchronously using fetch API
            submitBtn.addEventListener('click', () => {
                if (selectedRating === 0) {
                    validationError.style.display = 'flex';
                    return;
                }

                submitBtn.disabled = true;
                validationError.style.display = 'none';

                const payload = {
                    order_id: currentOrderId,
                    rating: selectedRating,
                    review: reviewTextarea.value
                };

                fetch('api/submit-feedback.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Swap with Success state content
                        formContent.style.display = 'none';
                        successContent.style.display = 'block';
                        
                        if (data.couponGenerated) {
                            // Update details and show coupon reward
                            document.getElementById('feedbackSuccessTitle').textContent = '🎉 Thank You For Your Review!';
                            document.getElementById('feedbackSuccessSubtitle').textContent = "You've unlocked a reward.";
                            document.getElementById('rewardDiscount').textContent = data.discount || '10% OFF';
                            document.getElementById('rewardCouponCode').textContent = data.couponCode;
                            document.getElementById('rewardExpiry').textContent = data.expiresAt;
                            document.getElementById('feedbackCouponReward').style.display = 'block';

                            // Setup clipboard copy button
                            const copyBtn = document.getElementById('copyCouponBtn');
                            copyBtn.addEventListener('click', () => {
                                navigator.clipboard.writeText(data.couponCode).then(() => {
                                    copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                                    setTimeout(() => {
                                        copyBtn.innerHTML = '<i class="far fa-copy"></i> Copy';
                                    }, 2000);
                                }).catch(err => {
                                    console.error('Clipboard copy failed: ', err);
                                    // Fallback copy alert
                                    alert('Coupon Code: ' + data.couponCode);
                                });
                            });
                        } else {
                            // Wait 2 seconds, then close automatically
                            setTimeout(() => {
                                closeFeedbackPopup();
                            }, 2000);
                        }
                    } else {
                        // Display error message
                        validationError.querySelector('span').textContent = data.message || 'An error occurred. Please try again.';
                        validationError.style.display = 'flex';
                        submitBtn.disabled = false;
                    }
                })
                .catch(error => {
                    validationError.querySelector('span').textContent = 'Network error. Please try again.';
                    validationError.style.display = 'flex';
                    submitBtn.disabled = false;
                });
            });
        });

        // Clear local cart completely on successful order placement
        try {
            localStorage.removeItem('foodie_cart');
            localStorage.removeItem('foodie_cart_timestamp');
        } catch (e) {}
    </script>
</body>
</html>
