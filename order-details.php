<?php
require_once __DIR__ . '/api/config.php';
requireLogin();

$order_number = $_GET['order_id'] ?? '';
$order = null;
$order_items = [];

if (!empty($order_number)) {
    try {
        $stmt = $pdo->prepare("SELECT o.*, u.email AS user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.order_number = ?");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
            if (!$is_admin && intval($order['user_id']) !== intval($_SESSION['user_id'])) {
                $error_msg = "Access Denied: You do not have permission to view this invoice.";
                $order = null;
            } else {
                $item_stmt = $pdo->prepare("SELECT oi.*, fi.image_url FROM order_items oi LEFT JOIN food_items fi ON oi.food_item_id = fi.id WHERE oi.order_id = ?");
                $item_stmt->execute([$order['id']]);
                $order_items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $error_msg = "Order not found. Please verify the order number.";
        }
    } catch(PDOException $e) {
        $error_msg = "Database error: " . $e->getMessage();
    }
} else {
    $error_msg = "No order number specified.";
}

// Redirect or show error if order could not be loaded
if (!$order) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Medusa Luxury</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600&display=swap" rel="stylesheet">
        <style>
            body { background: #050a07; color: #ffffff; font-family: 'Plus Jakarta Sans', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
            .error-card { background: #0b1c13; border: 1px solid rgba(192, 155, 91, 0.15); border-radius: 12px; padding: 3rem; text-align: center; max-width: 500px; width: 90%; }
            .btn-back { background: #C09B5B; color: #0b1c13; font-weight: 600; text-decoration: none; padding: 0.8rem 1.5rem; border-radius: 8px; display: inline-block; margin-top: 1.5rem; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <h2 class="text-danger mb-3">Error</h2>
            <p><?php echo htmlspecialchars($error_msg ?? 'An unknown error occurred.'); ?></p>
            <a href="my-orders.php" class="btn-back">Return to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Order calculations
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += floatval($item['price']) * intval($item['quantity']);
}

// Load Settings
$settings_file = __DIR__ . '/admintest/settings.json';
$settings = [
    'restaurant_name' => 'Medusa',
    'gst_rate' => 18,
    'packing_charge' => 0.00,
    'opening_hours' => '11:00 AM - 11:00 PM'
];
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true) ?: $settings;
}
$gst_rate = isset($settings['gst_rate']) ? intval($settings['gst_rate']) : 18;
$packing_charge = isset($settings['packing_charge']) ? floatval($settings['packing_charge']) : 0.00;

$gst = $subtotal * ($gst_rate / 100);
$delivery = (strpos(strtolower($order['delivery_address']), 'table') !== false) ? 0.00 : floatval($order['delivery_charge'] ?? 40.00);
$packing = (strpos(strtolower($order['delivery_address']), 'table') !== false) ? 0.00 : $packing_charge;

$grand_total = $subtotal + $gst + $delivery + $packing;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($order['order_number']); ?> - Medusa</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #050a07;
            --bg-card: #0b1c13;
            --bg-box: #0e2217;
            --gold: #C09B5B;
            --rosewood: #5A1827;
            --rosewood-hover: #7E2638;
            --white: #FAF7F0;
            --gray: #8d9a93;
            --border-glass: rgba(192, 155, 91, 0.2);
            --border-light: rgba(255, 255, 255, 0.05);
        }

        body {
            background-color: var(--bg-body);
            color: var(--white);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 3rem 1rem;
            margin: 0;
            line-height: 1.5;
        }

        /* Container */
        .invoice-wrapper {
            background-color: var(--bg-card);
            max-width: 800px;
            width: 100%;
            border-radius: 4px;
            padding: 40px;
        }

        /* Top Header */
        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            padding: 20px 30px;
            margin-bottom: 20px;
            background: var(--bg-box);
        }

        .brand-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand-logo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 1px solid var(--gold);
            padding: 2px;
        }

        .brand-text h1 {
            font-family: 'Playfair Display', serif;
            color: var(--gold);
            font-size: 1.6rem;
            font-weight: 500;
            margin: 0 0 2px 0;
            letter-spacing: 1px;
        }

        .brand-text p {
            font-size: 0.6rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--gray);
            margin: 0;
        }

        .inv-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-print-icon {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border-glass);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            background: transparent;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-print-icon:hover {
            background: rgba(192, 155, 91, 0.1);
        }

        .inv-number-block {
            text-align: right;
        }

        .inv-number-block span {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray);
            margin-bottom: 4px;
        }

        .inv-number-block strong {
            font-size: 1.2rem;
            color: var(--gold);
            font-family: 'Playfair Display', serif;
            letter-spacing: 1px;
        }

        /* Status & Meta Block */
        .meta-container {
            display: flex;
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
            background: var(--bg-box);
            min-height: 160px;
        }

        .status-block {
            background-color: var(--rosewood);
            width: 35%;
            padding: 30px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow: hidden;
        }

        /* Floral pattern using generated leaf watermark */
        .status-block::before {
            content: '';
            position: absolute;
            bottom: -50px; left: -100px; width: 400px; height: 400px;
            background-image: url('assets/images/leaf_watermark.png');
            background-size: contain;
            background-position: bottom left;
            background-repeat: no-repeat;
            opacity: 0.25;
            pointer-events: none;
            mix-blend-mode: screen;
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gold);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 10px;
            position: relative;
        }

        .status-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: white;
            margin: 0 0 10px 0;
            position: relative;
        }

        .status-desc {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.7);
            margin: 0;
            position: relative;
            line-height: 1.4;
        }

        .meta-grid-wrap {
            width: 65%;
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .meta-item {
            display: flex;
            gap: 12px;
        }

        .meta-icon {
            color: var(--gold);
            font-size: 1.1rem;
            margin-top: 2px;
        }

        .meta-item-content span {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .meta-item-content strong {
            display: block;
            font-size: 0.9rem;
            color: white;
            font-weight: 500;
        }

        /* Two Cards Grid */
        .info-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-card {
            background: var(--bg-box);
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            padding: 25px;
        }

        .info-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gold);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .info-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            color: white;
            margin: 0 0 15px 0;
        }

        .info-card-line {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .info-card-line i {
            width: 14px;
            color: var(--gold);
            opacity: 0.8;
        }

        /* Order Items */
        .items-box {
            background: var(--bg-box);
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .items-header-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px 25px;
            color: var(--gold);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: var(--rosewood);
            color: rgba(255,255,255,0.7);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 25px;
            text-align: left;
            font-weight: 600;
        }

        .items-table th.text-center { text-align: center; }
        .items-table th.text-right { text-align: right; }

        .items-table td {
            padding: 15px 25px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .item-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .item-img {
            width: 50px; height: 50px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .item-name {
            font-size: 0.95rem;
            color: white;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .diet-badge {
            font-size: 0.55rem;
            padding: 2px 6px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .diet-veg { border: 1px solid rgba(76, 175, 80, 0.5); color: #4CAF50; }
        .diet-nonveg { border: 1px solid rgba(244, 67, 54, 0.5); color: #F44336; }
        .diet-bar { border: 1px solid rgba(33, 150, 243, 0.5); color: #2196F3; }

        .qty-cell { text-align: center; font-size: 0.9rem; color: white; }
        .price-cell { text-align: right; font-size: 0.9rem; color: var(--gray); }
        .total-cell { text-align: right; font-size: 0.95rem; color: white; font-weight: 500; }

        /* Summary Box */
        .summary-box {
            background: var(--bg-box);
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            padding: 30px 40px;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-end;
            position: relative;
            overflow: hidden;
        }

        .summary-box::before {
            content: '';
            position: absolute;
            bottom: -120px; left: -150px; width: 500px; height: 500px;
            background-image: url('assets/images/leaf_watermark.png');
            background-size: contain;
            background-position: bottom left;
            background-repeat: no-repeat;
            opacity: 0.25;
            pointer-events: none;
            mix-blend-mode: screen;
            filter: contrast(1.5) brightness(0.8);
        }

        .summary-content {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 2;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .summary-row span:last-child {
            color: white;
            font-weight: 500;
        }

        .grand-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px dashed rgba(255,255,255,0.15);
        }

        .grand-total-row span:first-child {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--gold);
            font-weight: 600;
        }

        .grand-total-row span:last-child {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--gold);
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: 0.3s;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--rosewood);
            border: 1px solid var(--rosewood);
            color: white;
        }
        .btn-primary:hover {
            background: var(--rosewood-hover);
            border-color: var(--rosewood-hover);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.15);
            color: white;
        }
        .btn-outline:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Footer Msg */
        .footer-msg {
            background: var(--bg-box);
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            padding: 25px 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .footer-icon {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: var(--gold);
            color: var(--bg-card);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .footer-text p {
            margin: 0 0 3px 0;
            color: var(--gray);
            font-size: 0.85rem;
        }
        .footer-text p:first-child {
            color: white;
            font-size: 0.95rem;
        }

        .footer-msg::before {
            content: '';
            position: absolute;
            right: -20px; bottom: -50px; width: 200px; height: 200px;
            background-image: url('assets/images/leaf_watermark.png');
            background-size: contain;
            background-position: bottom right;
            background-repeat: no-repeat;
            opacity: 0.15;
            pointer-events: none;
            mix-blend-mode: screen;
            transform: rotate(-15deg);
        }
        @media print {
            body { background: white !important; color: black !important; padding: 0; }
            .invoice-wrapper { background: white; padding: 0; border: none; max-width: 100%; }
            .status-block { background: transparent; color: black; border: 1px solid #ddd; }
            .status-text, .status-header { color: black; }
            .action-buttons { display: none; }
            .items-table th { background: #eee; color: black; }
            .btn-print-icon { display: none; }
            .meta-item-content strong, .info-card-title, .item-name, .qty-cell, .total-cell { color: black; }
            .info-card, .items-box, .summary-box, .footer-msg, .meta-container, .inv-header { border-color: #ddd; background: white; }
            .grand-total-row span:last-child { color: black; }
        }

        @media (max-width: 768px) {
            .meta-container { flex-direction: column; }
            .status-block, .meta-grid-wrap { width: 100%; }
            .info-cards { grid-template-columns: 1fr; }
            .action-buttons { grid-template-columns: 1fr; }
            .items-table th:nth-child(3), .items-table td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>

    <div class="invoice-wrapper">
        
        <!-- Header -->
        <div class="inv-header">
            <div class="brand-info">
                <img src="assets/images/logo.png" alt="Medusa" class="brand-logo" onerror="this.src='assets/images/logo_right.png'">
                <div class="brand-text">
                    <h1>Medusa</h1>
                    <p>Restaurant, Bar & Lounge</p>
                </div>
            </div>
            <div class="inv-info">
                <button onclick="window.print()" class="btn-print-icon"><i class="fa-solid fa-print"></i></button>
                <div class="inv-number-block">
                    <span>Invoice</span>
                    <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                </div>
            </div>
        </div>

        <!-- Status & Meta -->
        <div class="meta-container">
            <div class="status-block">
                <div class="status-header">
                    <i class="fa-solid fa-clipboard-check"></i> Order Status
                </div>
                <h2 class="status-text"><?php echo ucfirst(htmlspecialchars($order['order_status'])); ?></h2>
                <?php if (strtolower($order['order_status']) === 'cancelled'): ?>
                    <p class="status-desc" style="color:#dc2626;">
                        <?php if (!empty($order['cancellation_reason'])): ?>
                            <i class="fa-solid fa-triangle-exclamation" style="margin-right:4px;"></i>
                            <strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($order['cancellation_reason']); ?>
                        <?php else: ?>
                            This order has been cancelled.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p class="status-desc">Thank you! Your order has been processed successfully.</p>
                <?php endif; ?>
            </div>
            <div class="meta-grid-wrap">
                <div class="meta-grid">
                    <div class="meta-item">
                        <i class="fa-regular fa-calendar meta-icon"></i>
                        <div class="meta-item-content">
                            <span>Date & Time</span>
                            <strong><?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?></strong>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fa-solid fa-download meta-icon"></i>
                        <div class="meta-item-content">
                            <span>Invoice Number</span>
                            <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fa-regular fa-credit-card meta-icon"></i>
                        <div class="meta-item-content">
                            <span>Payment ID</span>
                            <strong><?php echo htmlspecialchars($order['payment_id'] ?? 'MOCK_PAYMENT'); ?></strong>
                        </div>
                    </div>
                    <div class="meta-item">
                        <i class="fa-solid fa-money-check-dollar meta-icon"></i>
                        <div class="meta-item-content">
                            <span>Payment Method</span>
                            <strong>Online Payment</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer & Delivery -->
        <div class="info-cards">
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fa-regular fa-user"></i> Customer Details
                </div>
                <h3 class="info-card-title"><?php echo htmlspecialchars($order['customer_name']); ?></h3>
                <div class="info-card-line">
                    <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($order['customer_phone'] ?: 'N/A'); ?>
                </div>
                <div class="info-card-line">
                    <i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($order['user_email'] ?: 'N/A'); ?>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fa-solid fa-bell-concierge"></i> Fulfillment Mode
                </div>
                <?php if (strpos(strtolower($order['delivery_address']), 'table') !== false): ?>
                    <h3 class="info-card-title" style="color: #dfba86;"><i class="fa-solid fa-chair me-1"></i> Dine-In Service</h3>
                    <div class="info-card-line">
                        <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($order['delivery_address']); ?>
                    </div>
                <?php elseif (isset($order['order_type']) && strcasecmp($order['order_type'], 'takeaway') === 0): ?>
                    <h3 class="info-card-title" style="color: #dfba86;"><i class="fa-solid fa-shopping-bag me-1"></i> Takeaway / Pickup</h3>
                    <div class="info-card-line">
                        <i class="fa-solid fa-store"></i> Pick up at Restaurant Counter
                    </div>
                <?php else: ?>
                    <h3 class="info-card-title" style="color: #dfba86;"><i class="fa-solid fa-truck me-1"></i> Home Delivery</h3>
                    <div class="info-card-line">
                        <i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($order['delivery_address']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Items -->
        <div class="items-box">
            <div class="items-header-bar">
                <i class="fa-solid fa-bell-concierge"></i> Order Items
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Dish / Item Name</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): 
                        // Mock diet badge
                        $dClass = 'diet-veg'; $dText = 'Veg';
                        $lname = strtolower($item['item_name']);
                        if (strpos($lname, 'chicken') !== false || strpos($lname, 'lamb') !== false || strpos($lname, 'ribs') !== false || strpos($lname, 'prawn') !== false) {
                            $dClass = 'diet-nonveg'; $dText = 'Non-Veg';
                        }
                        // Resolve image source
                        $imgSrc = !empty($item['image_url']) ? $item['image_url'] : '';
                        if (!empty($imgSrc) && strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '//') !== 0) {
                            if (strpos($imgSrc, 'uploads/') !== 0) {
                                $imgSrc = 'uploads/' . $imgSrc;
                            }
                        }
                        if (empty($imgSrc)) {
                            $imgSrc = 'uploads/default.jpg';
                        }
                    ?>
                    <tr>
                        <td>
                            <div class="item-cell">
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="item" class="item-img" onerror="this.src='assets/images/logo.png'">
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <span class="diet-badge <?php echo $dClass; ?>"><?php echo $dText; ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="qty-cell"><?php echo $item['quantity']; ?></td>
                        <td class="price-cell">₹<?php echo number_format($item['price'], 2); ?></td>
                        <td class="total-cell">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="summary-box">
            <div class="summary-content">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₹<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>GST (<?php echo $gst_rate; ?>%)</span>
                    <span>₹<?php echo number_format($gst, 2); ?></span>
                </div>
                <?php if ($packing > 0): ?>
                <div class="summary-row">
                    <span>Packing Charges</span>
                    <span>₹<?php echo number_format($packing, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row">
                    <span>Delivery / Table Service</span>
                    <span>₹<?php echo number_format($delivery, 2); ?></span>
                </div>
                
                <div class="grand-total-row">
                    <span>Grand Total</span>
                    <span>₹<?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="action-buttons">
            <a href="javascript:void(0)" onclick="window.print()" class="btn-action btn-primary">
                <i class="fa-solid fa-print"></i> Print Bill
            </a>
            <a href="profile.php" class="btn-action btn-outline">
                <i class="fa-solid fa-arrow-left"></i> My Dashboard
            </a>
            <a href="menutest.html" class="btn-action btn-outline">
                <i class="fa-solid fa-utensils"></i> Order More
            </a>
        </div>

        <!-- Footer Msg -->
        <div class="footer-msg">
            <div class="footer-icon"><i class="fa-regular fa-heart"></i></div>
            <div class="footer-text">
                <p>We appreciate your order. See you again soon!</p>
                <p>Medusa Restaurant, Bar & Lounge</p>
            </div>
        </div>

    </div>

</body>
</html>
