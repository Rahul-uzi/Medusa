<?php
/**
 * track.php — Public live order tracking page
 * Token-based auth: ?token=<64-char hex>
 */
require_once __DIR__ . '/api/config.php';

$token = trim($_GET['token'] ?? $_SESSION['active_order_token'] ?? '');

// Validate token format
if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    header('Location: menutest.html');
    exit;
}

// Initial server-side fetch for SEO + no-JS fallback
$order = null;
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.delivery_address, o.delivery_city,
               o.total_amount, o.order_status, o.tracking_status,
               o.estimated_delivery, o.order_date,
               o.tax_amount, o.packing_charge, o.delivery_charge,
               o.discount, o.tier_discount_amount, o.points_redeemed_discount,
               o.order_type
        FROM orders o
        WHERE o.tracking_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $items_stmt = $pdo->prepare("SELECT item_name, quantity, price FROM order_items WHERE order_id = ?");
        $items_stmt->execute([$order['id']]);
        $order['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        $items_subtotal = 0;
        foreach ($order['items'] as $item) {
            $items_subtotal += floatval($item['price']) * intval($item['quantity']);
        }
    }
} catch (PDOException $e) { /* silent */ }

if (!$order) {
    header('Location: menutest.html');
    exit;
}

// Clean up session if order is terminal
$terminal = in_array($order['tracking_status'], ['delivered', 'cancelled']);
if ($terminal) {
    unset($_SESSION['active_order_token'], $_SESSION['active_order_id']);
}

$tracking_status = $order['tracking_status'] ?? 'placed';
$is_takeaway = (isset($order['order_type']) && strcasecmp($order['order_type'], 'takeaway') === 0);
$steps = [
    ['key' => 'placed',    'label' => 'Order Placed',   'icon' => 'fa-receipt'],
    ['key' => 'confirmed', 'label' => 'Confirmed',      'icon' => 'fa-circle-check'],
    ['key' => 'preparing', 'label' => 'Preparing',      'icon' => 'fa-fire-burner'],
    ['key' => $is_takeaway ? 'ready_for_pickup' : 'out_for_delivery', 'label' => $is_takeaway ? 'Ready for Pickup' : 'On the Way', 'icon' => $is_takeaway ? 'fa-store' : 'fa-motorcycle'],
    ['key' => 'delivered', 'label' => $is_takeaway ? 'Picked Up' : 'Delivered', 'icon' => $is_takeaway ? 'fa-circle-user' : 'fa-house'],
];
$step_order = [
    'placed'           => 1,
    'confirmed'        => 2,
    'preparing'        => 3,
    'out_for_delivery' => 4,
    'ready_for_pickup' => 4,
    'delivered'        => 5,
    'cancelled'        => 0
];
$current_step = $step_order[$tracking_status] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Track your Medusa order live — real-time status updates.">
    <title>Track Order <?php echo htmlspecialchars($order['order_number']); ?> — LA-MEDUSAA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        function initGoogleMapsTrack() {
            if (typeof __initTrackMap === 'function') __initTrackMap();
        }
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBiHCxp2jLKJOxy_pteRZbiMaWxkg2Mepk&libraries=places&loading=async&callback=initGoogleMapsTrack" defer></script>

    <style>
        :root {
            --obsidian:  #0a0a0a;
            --dark:      #111111;
            --card:      #161616;
            --gold:      #c9a84c;
            --gold-glow: rgba(201,168,76,0.18);
            --gold-dim:  rgba(201,168,76,0.55);
            --parchment: #f5f0e8;
            --muted:     rgba(245,240,232,0.45);
            --border:    rgba(201,168,76,0.14);
            --serif:     'Cormorant Garamond', Georgia, serif;
            --sans:      'Jost', sans-serif;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--sans);
            background: radial-gradient(circle at 50% 20%, #161512 0%, #0a0a0a 80%);
            color: var(--parchment);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* —— NAV —— */
        .top-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 48px;
            height: 68px;
            background: rgba(10,10,10,0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
        }
        .nav-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .nav-logo img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 50%;
            border: 1px solid var(--gold-dim);
            transition: transform 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .nav-logo:hover img {
            transform: scale(1.1) rotate(5deg);
            border-color: var(--gold);
            box-shadow: 0 0 12px var(--gold-glow);
        }
        .nav-brand {
            font-family: var(--serif);
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--gold);
            letter-spacing: 3px;
            text-transform: uppercase;
            text-shadow: 0 0 8px rgba(201, 168, 76, 0.2);
        }
        .nav-actions { display: flex; gap: 20px; align-items: center; }
        .nav-link {
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.2s;
        }
        .nav-link:hover { color: var(--gold); }

        /* —— MAIN —— */
        main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 60px 20px 80px;
            gap: 32px;
            max-width: 780px;
            margin: 0 auto;
            width: 100%;
            animation: pageFadeIn 0.8s ease-out;
        }
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* —— ORDER HEADER —— */
        .order-header { text-align: center; }
        .order-number-label {
            font-size: 0.72rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 8px;
        }
        .order-number-value {
            font-family: var(--serif);
            font-size: 2.6rem;
            font-weight: 600;
            color: #fff;
            letter-spacing: 2px;
        }
        .order-date-label { font-size: 0.82rem; color: var(--muted); margin-top: 6px; }

        /* —— STATUS CARD —— */
        .status-card {
            width: 100%;
            background: rgba(22, 22, 22, 0.65);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 40px;
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .status-card:hover {
            border-color: rgba(201, 168, 76, 0.25);
            box-shadow: 0 20px 50px rgba(201, 168, 76, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .status-headline {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
        }
        .status-pulse {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 0 0 var(--gold-glow);
            animation: pulse 2s infinite;
            flex-shrink: 0;
        }
        .status-pulse.terminal { background: #555; animation: none; box-shadow: none; }
        @keyframes pulse {
            0%   { box-shadow: 0 0 0 0 var(--gold-glow); }
            70%  { box-shadow: 0 0 0 10px rgba(0,0,0,0); }
            100% { box-shadow: 0 0 0 0 rgba(0,0,0,0); }
        }
        .status-text-group { flex: 1; }
        .status-main-label {
            font-family: var(--serif);
            font-size: 1.6rem;
            font-weight: 600;
            color: #fff;
            line-height: 1.2;
            letter-spacing: 0.5px;
        }
        .status-sub-label { font-size: 0.88rem; color: var(--muted); margin-top: 4px; line-height: 1.5; }
        .eta-badge {
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: var(--gold);
            background: var(--gold-glow);
            border: 1px solid rgba(201,168,76,0.25);
            border-radius: 50px;
            padding: 6px 16px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* —— STEPPER —— */
        .stepper {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            margin-top: 15px;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 22px;
            left: 22px;
            right: 22px;
            height: 3px;
            background: rgba(255, 255, 255, 0.05);
            z-index: 0;
        }
        .step-fill {
            position: absolute;
            top: 22px;
            left: 22px;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), #d4b05a);
            z-index: 1;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 8px var(--gold-dim);
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.1);
            background: #121212;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: rgba(255,255,255,0.25);
            transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .step-circle.done {
            border-color: var(--gold);
            background: rgba(201, 168, 76, 0.12);
            color: var(--gold);
            box-shadow: 0 0 10px rgba(201, 168, 76, 0.2);
        }
        .step-circle.active {
            border-color: var(--gold);
            background: var(--gold);
            color: #000;
            box-shadow: 0 0 20px var(--gold-dim);
            transform: scale(1.12);
            animation: activeStepPulse 2s infinite ease-in-out;
        }
        @keyframes activeStepPulse {
            0%, 100% { box-shadow: 0 0 15px var(--gold-dim); }
            50% { box-shadow: 0 0 25px var(--gold); }
        }
        .step-label {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 1px;
            color: var(--muted);
            text-align: center;
            text-transform: uppercase;
            transition: color 0.5s;
            max-width: 78px;
        }
        .step-label.active { color: var(--gold); text-shadow: 0 0 5px rgba(201, 168, 76, 0.3); }
        .step-label.done { color: rgba(245,240,232,0.7); }

        /* —— DETAILS CARD —— */
        .details-card {
            width: 100%;
            background: rgba(22, 22, 22, 0.65);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .details-card:hover {
            border-color: rgba(201, 168, 76, 0.25);
            box-shadow: 0 20px 50px rgba(201, 168, 76, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }
        .details-section {
            padding: 28px 36px;
            border-bottom: 1px solid var(--border);
        }
        .details-section:last-child { border-bottom: none; }
        .details-title {
            font-size: 0.72rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 18px;
            font-weight: 600;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.9rem;
            transition: background-color 0.2s ease;
        }
        .item-row:hover {
            background-color: rgba(255, 255, 255, 0.01);
        }
        .item-row:last-child { border-bottom: none; }
        .item-name { color: var(--parchment); font-weight: 500; }
        .item-qty { color: var(--gold); margin-left: 8px; font-size: 0.82rem; font-weight: 600; }
        .item-price { color: var(--gold); font-weight: 600; font-family: var(--sans); }
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1rem;
            font-weight: 600;
            padding-top: 16px;
            margin-top: 8px;
            border-top: 1px solid rgba(201, 168, 76, 0.25);
        }
        .total-label { color: var(--muted); font-size: 0.8rem; letter-spacing: 1.5px; text-transform: uppercase; }
        .total-value { color: var(--gold); font-size: 1.3rem; font-weight: 700; text-shadow: 0 0 10px rgba(201, 168, 76, 0.2); }
        .address-text { font-size: 0.9rem; color: var(--muted); line-height: 1.7; }

        /* —— CTA —— */
        .cta-row {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-gold {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gold);
            color: #000;
            font-family: var(--sans);
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.2);
            transition: all 0.3s ease;
        }
        .btn-gold:hover {
            background: #d4b05a;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(201, 168, 76, 0.4);
        }
        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.02);
            color: var(--parchment);
            font-family: var(--sans);
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            border: 1px solid rgba(245,240,232,0.18);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-ghost:hover {
            background: rgba(201, 168, 76, 0.05);
            border-color: var(--gold-dim);
            color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.15);
        }

        /* ———— FOOTER ———— */
        footer {
            text-align: center;
            padding: 28px;
            font-size: 0.75rem;
            color: var(--muted);
            border-top: 1px solid var(--border);
            letter-spacing: 0.5px;
        }

        .custom-scooter-icon {
            background: transparent !important;
            border: none !important;
        }

        #partnerMap {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.4);
            transition: border-color 0.3s;
        }
        #partnerMap:hover {
            border-color: rgba(201, 168, 76, 0.3) !important;
        }

        @media (max-width: 640px) {
            .top-nav { padding: 0 20px; }
            main { padding: 32px 16px 60px; }
            .status-card { padding: 24px 20px; }
            .status-headline { flex-direction: column; align-items: flex-start; gap: 10px; }
            .step-circle { width: 38px; height: 38px; font-size: 0.85rem; }
            .stepper::before, .step-fill { top: 19px; left: 19px; right: 19px; }
            .step-label { font-size: 0.6rem; max-width: 52px; }
            .details-section { padding: 20px; }
        }
    </style>

    <!-- Navbar Performance Optimization Links -->
    <link rel="stylesheet" href="assets/css/components.css">
        <script>
        const originalWarn = console.warn;
        console.warn = function(...args) {
            if (args[0] && typeof args[0] === "string" && args[0].includes("cdn.tailwindcss.com should not be used in production")) {
                return;
            }
            originalWarn.apply(console, args);
        };
    </script>
<script src="https://cdn.tailwindcss.com"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                corePlugins: {
                    preflight: false
                },
                theme: {
                    extend: {
                        colors: {
                            gold: '#b8973a',
                            'gold-light': '#d4af5a',
                        }
                    }
                }
            };
        }
    </script>
</head>
<body>

<!-- TOP NAV -->
<?php include_once __DIR__ . '/includes/navbar.php'; ?>
<script src="assets/js/navbar.js" defer></script>

<main>
    <!-- Order Header -->
    <div class="order-header">
        <p class="order-number-label">Tracking Order</p>
        <h1 class="order-number-value"><?php echo htmlspecialchars($order['order_number']); ?></h1>
        <p class="order-date-label">
            Placed on <?php echo date('j F Y, g:i A', strtotime($order['order_date'])); ?>
            &nbsp;·&nbsp;
            For <?php echo htmlspecialchars($order['customer_name']); ?>
        </p>
    </div>

    <!-- Status Card -->
    <div class="status-card" id="statusCard">
        <div class="status-headline">
            <div class="status-pulse <?php echo $terminal ? 'terminal' : ''; ?>" id="statusPulse"></div>
            <div class="status-text-group">
                <div class="status-main-label" id="statusLabel">
                    <?php
                    $labels = [
                        'placed'           => 'Order Placed',
                        'confirmed'        => 'Order Confirmed',
                        'preparing'        => 'Being Prepared',
                        'out_for_delivery' => 'Out for Delivery',
                        'ready_for_pickup' => 'Ready for Pickup',
                        'delivered'        => $is_takeaway ? 'Picked Up' : 'Delivered',
                        'cancelled'        => 'Cancelled'
                    ];
                    echo $labels[$tracking_status] ?? 'Processing';
                    ?>
                </div>
                <div class="status-sub-label" id="statusMsg">
                    <?php
                    $msgs = [
                        'placed'           => 'We have received your order and are confirming it.',
                        'confirmed'        => 'Your order has been confirmed by our team!',
                        'preparing'        => 'Our chefs are preparing your order right now.',
                        'out_for_delivery' => 'Your order is on its way to you!',
                        'ready_for_pickup' => 'Your order is ready! Please collect it at the restaurant counter.',
                        'delivered'        => $is_takeaway ? 'Your order has been picked up from our counter. Enjoy your meal!' : 'Your order has been delivered. Enjoy your meal!',
                        'cancelled'        => 'This order has been cancelled.'
                    ];
                    echo $msgs[$tracking_status] ?? '';
                    ?>
                </div>
            </div>
            <?php
            $eta_mins_php = null;
            if (!empty($order['estimated_delivery']) && !$terminal) {
                $diff_sec = strtotime($order['estimated_delivery']) - time();
                $eta_mins_php = $diff_sec > 0 ? ceil($diff_sec / 60) : null;
            }
            ?>
            <?php if ($eta_mins_php !== null): ?>
            <div class="eta-badge" id="etaBadge">
                <i class="fas fa-clock"></i>
                <?php echo $eta_mins_php === 1 ? '1 min away' : $eta_mins_php . ' mins away'; ?>
            </div>
            <?php else: ?>
            <div class="eta-badge" id="etaBadge" style="display:none;"></div>
            <?php endif; ?>
        </div>

        <!-- 5-Step Progress Bar -->
        <div class="stepper" id="stepper">
            <div class="step-fill" id="stepFill" style="width:0%;"></div>
            <?php foreach ($steps as $i => $s):
                $sn = $i + 1;
                $cls = '';
                if ($sn < $current_step) $cls = 'done';
                elseif ($sn === $current_step) $cls = 'active';
            ?>
            <div class="step-item">
                <div class="step-circle <?php echo $cls; ?>" id="stepCircle<?php echo $sn; ?>">
                    <i class="fas <?php echo $s['icon']; ?>"></i>
                </div>
                <div class="step-label <?php echo $cls; ?>" id="stepLabel<?php echo $sn; ?>"><?php echo $s['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Live Map Card -->
    <div class="details-card" id="liveMapCard" style="display: <?php echo $tracking_status === 'out_for_delivery' ? 'block' : 'none'; ?>; overflow: hidden;">
        <div class="details-section pb-0">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <p class="details-title m-0" style="margin:0;">Live Tracking</p>
                <div style="display:flex; align-items:center; gap:8px;">
                    <div id="liveEtaStrip" style="display:none; background: rgba(201,168,76,0.12); border:1px solid var(--gold); border-radius:20px; padding:4px 14px; font-size:0.78rem; font-weight:600; color:var(--gold); letter-spacing:0.5px;">
                        <i class="fa-solid fa-clock" style="margin-right:5px;"></i><span id="etaCountdown">---</span>
                    </div>
                    <div style="background: var(--gold); padding: 4px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; color:#000; display:flex; align-items:center; gap:5px;">
                        <span style="width:6px;height:6px;background:#000;border-radius:50%;display:inline-block;animation:pulse-dot 1.2s infinite;"></span> LIVE
                    </div>
                </div>
            </div>
            <div id="partnerMap" style="height: 300px; width: 100%; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 24px; background:#111;"></div>
        </div>
    </div>

    <!-- Order Details -->
    <div class="details-card">
        <!-- Items -->
        <div class="details-section">
            <p class="details-title">Items Ordered</p>
            <div id="itemsList">
                <?php foreach ($order['items'] as $item): ?>
                <div class="item-row">
                    <span class="item-name">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                        <span class="item-qty">× <?php echo intval($item['quantity']); ?></span>
                    </span>
                    <span class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="charge-breakdown" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; gap: 8px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--muted);">
                    <span>Subtotal</span>
                    <span style="color: var(--parchment);">₹<?php echo number_format($items_subtotal, 2); ?></span>
                </div>
                <?php if (floatval($order['tax_amount'] ?? 0) > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--muted);">
                    <span>GST (Tax)</span>
                    <span style="color: var(--parchment);">₹<?php echo number_format($order['tax_amount'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if (floatval($order['packing_charge'] ?? 0) > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--muted);">
                    <span>Packing Charges</span>
                    <span style="color: var(--parchment);">₹<?php echo number_format($order['packing_charge'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if (floatval($order['delivery_charge'] ?? 0) > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--muted);">
                    <span>Delivery Charges</span>
                    <span style="color: var(--parchment);">₹<?php echo number_format($order['delivery_charge'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if (floatval($order['discount'] ?? 0) > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--muted);">
                    <span>Coupon Discount</span>
                    <span style="color: #4CAF50;">-₹<?php echo number_format($order['discount'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if (floatval($order['tier_discount_amount'] ?? 0) > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--muted);">
                    <span>Tier Discount</span>
                    <span style="color: #4CAF50;">-₹<?php echo number_format($order['tier_discount_amount'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if (floatval($order['points_redeemed_discount'] ?? 0) > 0): ?>
                <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--muted);">
                    <span>Loyalty Points Discount</span>
                    <span style="color: #4CAF50;">-₹<?php echo number_format($order['points_redeemed_discount'], 2); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="total-row">
                <span class="total-label">Grand Total</span>
                <span class="total-value">₹<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>

        <!-- Address -->
        <div class="details-section">
            <p class="details-title"><?php echo $is_takeaway ? 'Pickup Location' : 'Delivery Address'; ?></p>
            <p class="address-text">
                <?php 
                if ($is_takeaway) {
                    echo "<strong>LA-MEDUSAA Restaurant Counter</strong><br><span style='font-size: 0.85rem; opacity: 0.7;'>Please collect your order at our main kitchen counter.</span>";
                } else {
                    echo nl2br(htmlspecialchars(preg_replace('/\[[0-9.-]+,\s*[0-9.-]+\]/', '', $order['delivery_address'])));
                }
                ?>
            </p>
        </div>
    </div>

    <!-- CTA -->
    <div class="cta-row">
        <a href="menutest.html" class="btn-ghost"><i class="fas fa-utensils"></i> Browse Menu</a>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <a href="my-orders.php" class="btn-gold"><i class="fas fa-receipt"></i> VIEW ORDERS</a>
        <?php endif; ?>
    </div>
</main>

<footer>
    LA-MEDUSAA Bar &amp; Lounge, Sector 68, Mohali &nbsp;·&nbsp; Live tracking updates every 15 seconds
</footer>

<script>
const TOKEN   = <?php echo json_encode($token); ?>;
const POLL_MS = 10000;
const DELIVERY_ADDRESS = <?php echo json_encode($order['delivery_address']); ?>;
const DELIVERY_CITY = <?php echo json_encode($order['delivery_city'] ?? ''); ?>;
const GMAPS_KEY = 'AIzaSyBiHCxp2jLKJOxy_pteRZbiMaWxkg2Mepk';

let currentStep = <?php echo $current_step; ?>;
setFill(currentStep);

function setFill(step) {
    const pct = step <= 1 ? 0 : ((step - 1) / 4) * 100;
    document.getElementById('stepFill').style.width = pct + '%';
}

// ─── Google Maps globals ───
let gmap = null;
let driverMarker = null;
let customerMarker = null;
let directionsService = null;
let directionsRenderer = null;
let mapReady = false;
let pendingDriverLat = null;
let pendingDriverLng = null;

window.__initTrackMap = function() {
    mapReady = true;
    // Only create map if map card is already visible
    const card = document.getElementById('liveMapCard');
    if (card && card.style.display !== 'none') {
        _createGMap();
    }
};

function _createGMap() {
    if (gmap || !mapReady) return;
    const center = { lat: 30.6813, lng: 76.7233 };
    gmap = new google.maps.Map(document.getElementById('partnerMap'), {
        center: center,
        zoom: 14,
        styles: [
            { elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
            { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
            { elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
            { elementType: 'labels.text.stroke', stylers: [{ color: '#f5f5f5' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
            { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#e0e0e0' }] },
            { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] },
            { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#dadada' }] },
            { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#c8c8c8' }] },
            { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c9e8f5' }] },
            { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] },
            { featureType: 'poi', stylers: [{ visibility: 'off' }] },
            { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#e5f5e0' }] },
            { featureType: 'transit', stylers: [{ visibility: 'off' }] },
            { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#e0e0e0' }] },
        ],
        disableDefaultUI: false,
        zoomControl: true,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map: gmap,
        suppressMarkers: true,
        polylineOptions: {
            strokeColor: '#c9a84c',
            strokeWeight: 5,
            strokeOpacity: 0.85,
        },
    });

    // Place customer marker via geocoding
    _geocodeCustomer();

    // If we have pending driver position, place it
    if (pendingDriverLat && pendingDriverLng) {
        _updateDriverMarker(pendingDriverLat, pendingDriverLng);
        pendingDriverLat = null; pendingDriverLng = null;
    }
}

function _geocodeCustomer() {
    const geocoder = new google.maps.Geocoder();
    let addr = DELIVERY_ADDRESS.replace(/Table\s+[A-Za-z0-9]+/gi, '').trim();
    geocoder.geocode({ address: addr }, (results, status) => {
        if (status === 'OK' && results[0]) {
            const pos = results[0].geometry.location;
            customerMarker = new google.maps.Marker({
                position: pos,
                map: gmap,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 9,
                    fillColor: '#c9a84c',
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2,
                },
                title: 'Your Location',
                zIndex: 5,
            });
            gmap.setCenter(pos);
        }
    });
}

function _updateDriverMarker(lat, lng) {
    if (!mapReady || !gmap) {
        pendingDriverLat = lat;
        pendingDriverLng = lng;
        return;
    }
    const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
    if (!driverMarker) {
        driverMarker = new google.maps.Marker({
            position: pos,
            map: gmap,
            icon: {
                path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                scale: 6,
                fillColor: '#2196F3',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2,
            },
            title: 'Driver',
            zIndex: 10,
        });
    } else {
        driverMarker.setPosition(pos);
    }
    gmap.panTo(pos);
    // Draw route from driver to customer
    if (customerMarker && directionsService && directionsRenderer) {
        directionsService.route({
            origin: pos,
            destination: customerMarker.getPosition(),
            travelMode: google.maps.TravelMode.DRIVING,
        }, (result, status) => {
            if (status === 'OK') {
                directionsRenderer.setDirections(result);
                const bounds = new google.maps.LatLngBounds();
                bounds.extend(pos);
                bounds.extend(customerMarker.getPosition());
                gmap.fitBounds(bounds, { padding: 50 });
            }
        });
    }
}

function _showMap() {
    const card = document.getElementById('liveMapCard');
    if (card.style.display === 'none' || card.style.display === '') {
        card.style.display = 'block';
        if (mapReady && !gmap) _createGMap();
        else if (mapReady) google.maps.event.trigger(gmap, 'resize');
    }
}

function _hideMap() {
    document.getElementById('liveMapCard').style.display = 'none';
}

// ─── ETA countdown strip ───
function _updateEtaStrip(etaMinutes, estimatedDelivery) {
    const strip = document.getElementById('liveEtaStrip');
    const countdown = document.getElementById('etaCountdown');
    if (!strip || !countdown) return;

    if (etaMinutes !== null && etaMinutes !== undefined && etaMinutes > 0) {
        strip.style.display = 'flex';
        if (etaMinutes === 1) countdown.textContent = '1 min away';
        else if (etaMinutes <= 2) countdown.textContent = etaMinutes + ' mins away';
        else countdown.textContent = etaMinutes + ' mins away';
    } else if (estimatedDelivery) {
        // Fallback: show formatted time
        const d = new Date(estimatedDelivery.replace(' ', 'T'));
        const now = new Date();
        const diffMs = d - now;
        if (diffMs > 0) {
            const mins = Math.ceil(diffMs / 60000);
            strip.style.display = 'flex';
            countdown.textContent = mins + ' min' + (mins !== 1 ? 's' : '') + ' away';
        } else {
            strip.style.display = 'none';
        }
    } else {
        strip.style.display = 'none';
    }
}

// ─── etaBadge (top summary) ───
function _updateEtaBadge(estimatedDelivery, isTerminal) {
    const eta = document.getElementById('etaBadge');
    if (!eta) return;
    if (estimatedDelivery && !isTerminal) {
        const diffMs = new Date(estimatedDelivery.replace(' ', 'T')) - new Date();
        if (diffMs > 0) {
            const mins = Math.ceil(diffMs / 60000);
            eta.innerHTML = `<i class="fas fa-clock"></i> ${mins === 1 ? '1 min away' : mins + ' mins away'}`;
            eta.style.display = '';
        } else {
            eta.innerHTML = `<i class="fas fa-clock"></i> Arriving now`;
            eta.style.display = '';
        }
    } else {
        eta.style.display = 'none';
    }
}

// ─── updateUI called by poll ───
function updateUI(data) {
    const step = data.step;
    const status = data.tracking_status;
    const isTerminal = !data.is_active;

    document.getElementById('statusLabel').textContent = data.status_label;
    document.getElementById('statusMsg').textContent   = data.status_message;

    const pulse = document.getElementById('statusPulse');
    if (pulse) pulse.classList.toggle('terminal', isTerminal);

    _updateEtaBadge(data.estimated_delivery, isTerminal);

    for (let i = 1; i <= 5; i++) {
        const circle = document.getElementById('stepCircle' + i);
        const label  = document.getElementById('stepLabel' + i);
        if (circle) circle.className = 'step-circle' + (i < step ? ' done' : i === step ? ' active' : '');
        if (label)  label.className  = 'step-label'  + (i < step ? ' done' : i === step ? ' active' : '');
    }
    setFill(step);
    currentStep = step;

    if (status === 'out_for_delivery') {
        _showMap();
        if (data.driver_lat && data.driver_lng) {
            _updateDriverMarker(data.driver_lat, data.driver_lng);
        }
        _updateEtaStrip(data.eta_minutes, data.estimated_delivery);
    } else {
        _hideMap();
    }

    if (isTerminal) clearInterval(pollTimer);
}

// ─── Polling ───
let pollTimer = null;

async function poll() {
    try {
        const res  = await fetch(`api/track-status.php?token=${TOKEN}`);
        const data = await res.json();
        if (data.success) updateUI(data);
    } catch(e) { /* silent */ }
}

// Initial load if already out for delivery
<?php if ($tracking_status === 'out_for_delivery'): ?>
document.addEventListener('DOMContentLoaded', () => {
    _showMap();
    <?php if (!empty($order['driver_lat']) && !empty($order['driver_lng'])): ?>
    _updateDriverMarker(<?php echo floatval($order['driver_lat']); ?>, <?php echo floatval($order['driver_lng']); ?>);
    <?php endif; ?>
    <?php if (!empty($order['estimated_delivery'])): ?>
    const initEtaMs = new Date('<?php echo str_replace(' ', 'T', $order['estimated_delivery']); ?>') - new Date();
    const initMins = initEtaMs > 0 ? Math.ceil(initEtaMs / 60000) : null;
    _updateEtaStrip(initMins, '<?php echo addslashes($order['estimated_delivery']); ?>');
    <?php endif; ?>
});
<?php endif; ?>

<?php if (!$terminal): ?>
pollTimer = setInterval(poll, POLL_MS);
<?php endif; ?>
</script>

</body>
</html>
