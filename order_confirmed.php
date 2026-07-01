<?php
/**
 * order_confirmed.php — Post-order confirmation page
 * Reads tracking token from session. Shows animated confirmation + embedded tracker.
 * Auto-redirects to track.php after 5 seconds.
 */
require_once __DIR__ . '/api/config.php';

$token = $_SESSION['active_order_token'] ?? null;
if (!$token || strlen($token) !== 64 || !ctype_xdigit($token)) {
    header('Location: menutest.php');
    exit;
}

// Fetch order from DB
$order = null;
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.customer_name, o.delivery_address,
               o.total_amount, o.order_status, o.tracking_status,
               o.estimated_delivery, o.order_date,
               o.tax_amount, o.packing_charge, o.delivery_charge,
               o.discount, o.tier_discount_amount, o.points_redeemed_discount
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
    header('Location: menutest.php');
    exit;
}

$tracking_status = $order['tracking_status'] ?? 'placed';
$step_order = ['placed'=>1,'confirmed'=>2,'preparing'=>3,'out_for_delivery'=>4,'delivered'=>5,'cancelled'=>0];
$current_step = $step_order[$tracking_status] ?? 1;
$steps = [
    ['key' => 'placed',           'label' => 'Placed',   'icon' => 'fa-receipt'],
    ['key' => 'confirmed',        'label' => 'Confirmed', 'icon' => 'fa-circle-check'],
    ['key' => 'preparing',        'label' => 'Preparing', 'icon' => 'fa-fire-burner'],
    ['key' => 'out_for_delivery', 'label' => 'On the Way','icon' => 'fa-motorcycle'],
    ['key' => 'delivered',        'label' => 'Delivered', 'icon' => 'fa-house'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Order confirmed at LA-MEDUSAA Bar & Lounge.">
    <title>Order Confirmed — LA-MEDUSAA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
            align-items: center;
            padding: 48px 20px 80px;
        }

        /* —— Checkmark Animation —— */
        .checkmark-wrap {
            position: relative;
            margin-bottom: 28px;
            animation: popIn 0.6s cubic-bezier(0.34,1.56,0.64,1) forwards;
        }
        .checkmark-circle {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gold-glow);
            box-shadow: 0 0 25px rgba(201, 168, 76, 0.25);
        }
        .checkmark-circle i { font-size: 2.4rem; color: var(--gold); text-shadow: 0 0 10px rgba(201, 168, 76, 0.4); }
        .checkmark-ring {
            position: absolute;
            inset: -10px;
            border-radius: 50%;
            border: 2px solid rgba(201,168,76,0.25);
            animation: expandRing 1s ease-out 0.3s forwards;
            opacity: 0;
        }
        @keyframes popIn { from { opacity:0; transform:scale(0.4); } to { opacity:1; transform:scale(1); } }
        @keyframes expandRing { from { opacity:0.6; transform:scale(0.9); } to { opacity:0; transform:scale(1.5); } }

        /* —— Hero —— */
        .hero { text-align: center; margin-bottom: 40px; animation: fadeUp 0.6s ease 0.15s both; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .hero-subtitle {
            font-size: 0.72rem;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 10px;
        }
        .hero-title {
            font-family: var(--serif);
            font-size: 2.8rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
            text-shadow: 0 0 15px rgba(255, 255, 255, 0.1);
        }
        .hero-order-num {
            font-family: var(--serif);
            font-size: 1.25rem;
            color: var(--gold);
            letter-spacing: 2px;
            font-weight: 600;
        }
        .hero-tagline { font-size: 0.9rem; color: var(--muted); margin-top: 8px; }

        /* —— Auto-redirect bar —— */
        .redirect-bar {
            width: 100%;
            max-width: 640px;
            background: rgba(201,168,76,0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 28px;
            animation: fadeUp 0.6s ease 0.25s both;
            backdrop-filter: blur(12px);
        }
        .redirect-progress {
            height: 3px;
            background: var(--gold);
            border-radius: 2px;
            animation: progressFill 5s linear forwards;
            margin-top: 8px;
            box-shadow: 0 0 6px var(--gold-dim);
        }
        .redirect-bar-inner { flex: 1; }
        @keyframes progressFill { from { width:100%; } to { width:0%; } }
        .skip-link { color: var(--gold); text-decoration: none; font-weight: 600; white-space: nowrap; margin-left: 20px; font-size: 0.82rem; transition: color 0.2s; }
        .skip-link:hover { color: #d4b05a; }

        /* —— Cards —— */
        .card {
            width: 100%;
            max-width: 640px;
            background: rgba(22, 22, 22, 0.65);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 20px;
            animation: fadeUp 0.6s ease 0.3s both;
            backdrop-filter: blur(16px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255,255,255,0.05);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            border-color: rgba(201, 168, 76, 0.25);
            box-shadow: 0 20px 45px rgba(201, 168, 76, 0.05), inset 0 1px 0 rgba(255,255,255,0.05);
        }
        .card-section { padding: 28px 36px; border-bottom: 1px solid var(--border); }
        .card-section:last-child { border-bottom: none; }
        .card-title {
            font-size: 0.72rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 18px;
            font-weight: 600;
        }

        /* —— Mini Tracker —— */
        .mini-tracker {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            margin-top: 10px;
        }
        .mini-tracker::before {
            content: '';
            position: absolute;
            top: 16px;
            left: 16px;
            right: 16px;
            height: 3px;
            background: rgba(255,255,255,0.06);
            z-index: 0;
        }
        .mini-fill {
            position: absolute;
            top: 16px;
            left: 16px;
            height: 3px;
            background: linear-gradient(90deg, var(--gold), rgba(201,168,76,0.4));
            z-index: 1;
            transition: width 0.8s ease;
            box-shadow: 0 0 6px var(--gold-dim);
        }
        .mini-step { display: flex; flex-direction: column; align-items: center; gap: 10px; z-index: 2; flex: 1; }
        .mini-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.1);
            background: #121212;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: rgba(255,255,255,0.2);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .mini-circle.done { border-color: var(--gold); background: rgba(201, 168, 76, 0.12); color: var(--gold); box-shadow: 0 0 8px rgba(201,168,76,0.2); }
        .mini-circle.active { border-color: var(--gold); background: var(--gold); color: #000; box-shadow: 0 0 15px var(--gold-dim); transform: scale(1.1); }
        .mini-label {
            font-size: 0.62rem;
            font-weight: 600;
            color: var(--muted);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            max-width: 58px;
            transition: color 0.5s;
        }
        .mini-label.active { color: var(--gold); text-shadow: 0 0 5px rgba(201, 168, 76, 0.3); }
        .mini-label.done { color: rgba(245,240,232,0.7); }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gold);
            background: var(--gold-glow);
            border: 1px solid rgba(201,168,76,0.25);
            border-radius: 50px;
            padding: 6px 16px;
            margin-bottom: 18px;
        }
        .status-pulse-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--gold);
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%{box-shadow:0 0 0 0 var(--gold-glow)} 70%{box-shadow:0 0 0 7px rgba(0,0,0,0)} 100%{box-shadow:0 0 0 0 rgba(0,0,0,0)} }

        /* —— Items —— */
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.9rem;
            transition: background-color 0.2s ease;
        }
        .item-row:hover { background-color: rgba(255, 255, 255, 0.01); }
        .item-row:last-child { border-bottom: none; }
        .item-name { color: var(--parchment); font-weight: 500; }
        .item-qty { color: var(--gold); margin-left: 8px; font-size: 0.82rem; font-weight: 600; }
        .item-price { color: var(--gold); font-weight: 600; }
        .grand-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
            padding-top: 16px;
            border-top: 1px solid rgba(201, 168, 76, 0.25);
        }
        .gt-label { font-size: 0.8rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); }
        .gt-value { font-size: 1.3rem; font-weight: 700; color: var(--gold); text-shadow: 0 0 10px rgba(201, 168, 76, 0.2); }

        /* —— Address —— */
        .address-text { font-size: 0.9rem; color: var(--muted); line-height: 1.7; }

        /* —— CTA —— */
        .cta-row { width: 100%; max-width: 640px; display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; animation: fadeUp 0.6s ease 0.4s both; }
        .btn-gold {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--gold); color: #000;
            font-family: var(--sans); font-size: 0.82rem; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none;
            padding: 14px 32px; border-radius: 8px; border: none; cursor: pointer;
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.2);
            transition: all 0.3s ease;
        }
        .btn-gold:hover { background: #d4b05a; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(201, 168, 76, 0.4); }
        .btn-ghost {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255, 255, 255, 0.02); color: var(--parchment);
            font-family: var(--sans); font-size: 0.82rem; font-weight: 600;
            letter-spacing: 1.5px; text-transform: uppercase; text-decoration: none;
            padding: 14px 32px; border-radius: 8px;
            border: 1px solid rgba(245,240,232,0.18); cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-ghost:hover {
            background: rgba(201, 168, 76, 0.05);
            border-color: var(--gold-dim); color: var(--gold);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 168, 76, 0.15);
        }

        @media (max-width: 600px) {
            .hero-title { font-size: 2.2rem; }
            .card-section { padding: 20px; }
            .mini-circle { width: 28px; height: 28px; font-size: 0.65rem; }
            .mini-tracker::before, .mini-fill { top: 14px; left: 14px; right: 14px; }
            .mini-label { font-size: 0.55rem; max-width: 44px; }
        }
    </style>
</head>
<body>

<!-- Animated Checkmark -->
<div class="checkmark-wrap">
    <div class="checkmark-circle"><i class="fas fa-check"></i></div>
    <div class="checkmark-ring"></div>
</div>

<!-- Hero -->
<div class="hero">
    <p class="hero-subtitle">LA-MEDUSAA Bar &amp; Lounge</p>
    <h1 class="hero-title">Order Confirmed!</h1>
    <p class="hero-order-num"><?php echo htmlspecialchars($order['order_number']); ?></p>
    <p class="hero-tagline">
        Thank you, <?php echo htmlspecialchars($order['customer_name']); ?>.
        Your order is in our hands now.
    </p>
</div>

<!-- Auto-redirect bar -->
<div class="redirect-bar">
    <div class="redirect-bar-inner">
        <span>Redirecting you to live tracking in <strong id="countdown">5</strong>s</span>
        <div class="redirect-progress"></div>
    </div>
    <a href="track.php?token=<?php echo htmlspecialchars($token); ?>" class="skip-link">
        Go now <i class="fas fa-arrow-right"></i>
    </a>
</div>

<!-- Live Mini Tracker -->
<div class="card">
    <div class="card-section">
        <p class="card-title">Live Order Status</p>
        <div class="status-chip">
            <div class="status-pulse-dot"></div>
            <span id="statusChipLabel">
                <?php
                $labels = ['placed'=>'Order Placed','confirmed'=>'Confirmed','preparing'=>'Being Prepared','out_for_delivery'=>'Out for Delivery','delivered'=>'Delivered','cancelled'=>'Cancelled'];
                echo $labels[$tracking_status] ?? 'Processing';
                ?>
            </span>
        </div>
        <div class="mini-tracker" id="miniTracker">
            <div class="mini-fill" id="miniFill" style="width:0%;"></div>
            <?php foreach ($steps as $i => $s):
                $sn = $i + 1;
                $cls = '';
                if ($sn < $current_step) $cls = 'done';
                elseif ($sn === $current_step) $cls = 'active';
            ?>
            <div class="mini-step">
                <div class="mini-circle <?php echo $cls; ?>" id="mc<?php echo $sn; ?>">
                    <i class="fas <?php echo $s['icon']; ?>"></i>
                </div>
                <div class="mini-label <?php echo $cls; ?>" id="ml<?php echo $sn; ?>"><?php echo $s['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Order Summary -->
<div class="card">
    <div class="card-section">
        <p class="card-title">Order Summary</p>
        <?php foreach ($order['items'] as $item): ?>
        <div class="item-row">
            <span class="item-name">
                <?php echo htmlspecialchars($item['item_name']); ?>
                <span class="item-qty">× <?php echo intval($item['quantity']); ?></span>
            </span>
            <span class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
        </div>
        <?php endforeach; ?>
        
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
        
        <div class="grand-total">
            <span class="gt-label">Grand Total</span>
            <span class="gt-value">₹<?php echo number_format($order['total_amount'], 2); ?></span>
        </div>
    </div>
    <div class="card-section">
        <p class="card-title">Delivery Address</p>
        <p class="address-text"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
    </div>
</div>

<!-- CTA -->
<div class="cta-row">
    <a href="track.php?token=<?php echo htmlspecialchars($token); ?>" class="btn-gold">
        <i class="fas fa-location-dot"></i> View Full Tracking
    </a>
    <a href="menutest.php" class="btn-ghost">
        <i class="fas fa-utensils"></i> Back to Menu
    </a>
</div>

<script>
const TOKEN    = <?php echo json_encode($token); ?>;
const TRACK_URL = 'track.php?token=' + TOKEN;

// —— Countdown & auto-redirect ——
let secs = 5;
const cdEl = document.getElementById('countdown');
const timer = setInterval(() => {
    secs--;
    cdEl.textContent = secs;
    if (secs <= 0) { clearInterval(timer); window.location.href = TRACK_URL; }
}, 1000);

// —— Mini tracker ——
const STEP_ORDER = { placed:1, confirmed:2, preparing:3, out_for_delivery:4, delivered:5, cancelled:0 };
const CHIP_LABELS = { placed:'Order Placed', confirmed:'Confirmed', preparing:'Being Prepared', out_for_delivery:'Out for Delivery', delivered:'Delivered', cancelled:'Cancelled' };

function setMiniStep(step) {
    const pct = step <= 1 ? 0 : ((step - 1) / 4) * 100;
    document.getElementById('miniFill').style.width = pct + '%';
    for (let i = 1; i <= 5; i++) {
        const c = document.getElementById('mc' + i);
        const l = document.getElementById('ml' + i);
        c.className = 'mini-circle' + (i < step ? ' done' : i === step ? ' active' : '');
        l.className = 'mini-label'  + (i < step ? ' done' : i === step ? ' active' : '');
    }
}

setMiniStep(<?php echo $current_step; ?>);

let pollTimer = setInterval(async () => {
    try {
        const res  = await fetch('api/track-status.php?token=' + TOKEN);
        const data = await res.json();
        if (data.success) {
            setMiniStep(data.step);
            const chipEl = document.getElementById('statusChipLabel');
            if (chipEl) chipEl.textContent = CHIP_LABELS[data.tracking_status] || data.status_label;
            if (!data.is_active) clearInterval(pollTimer);
        }
    } catch(e) {}
}, 10000);

// Clear local cart completely on successful order placement
try {
    localStorage.removeItem('foodie_cart');
    localStorage.removeItem('foodie_cart_timestamp');
} catch (e) {}
</script>
</body>
</html>
