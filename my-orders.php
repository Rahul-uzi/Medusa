<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/token_helper.php';
requireLogin();

// Fetch orders to render on server side (initial load)
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Handle order cancellation via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    header('Content-Type: application/json');
    $order_id = $_POST['order_id'];
    try {
        $chk_stmt = $pdo->prepare("SELECT order_status, order_number FROM orders WHERE id = ? AND user_id = ?");
        $chk_stmt->execute([$order_id, $user_id]);
        $order_info = $chk_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order_info) {
            $status = strtolower($order_info['order_status']);
            if ($status === 'pending' || $status === 'confirmed') {
                $upd_stmt = $pdo->prepare("UPDATE orders SET order_status = 'cancelled', tracking_status = 'cancelled' WHERE id = ?");
                $upd_stmt->execute([$order_id]);
                
                try {
                    require_once __DIR__ . '/includes/notifications_helper.php';
                    addNotification($user_id, 'Order Cancelled', "Your order #{$order_info['order_number']} has been cancelled successfully.");
                } catch (Exception $notif_ex) {
                    // Fail-safe for notification helpers
                }
                echo json_encode(['success' => true, 'message' => 'Order cancelled successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Order cannot be cancelled at this stage.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT *, tracking_token, tracking_status FROM orders WHERE user_id = ? ORDER BY order_date DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($orders as &$order) {
        $item_stmt = $pdo->prepare("SELECT oi.*, fi.image_url FROM order_items oi LEFT JOIN food_items fi ON oi.food_item_id = fi.id WHERE oi.order_id = ?");
        $item_stmt->execute([$order['id']]);
        $order['items'] = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    $orders = [];
}

// Check initial status parameter from tab query (e.g. ?tab=active)
$initialStatus = 'all';
if (isset($_GET['tab'])) {
    if ($_GET['tab'] === 'active') $initialStatus = 'active';
    elseif ($_GET['tab'] === 'past') $initialStatus = 'past';
    elseif ($_GET['tab'] === 'cancelled') $initialStatus = 'cancelled';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders - La Medusaa</title>
  
  <!-- Tailwind CSS & Fonts -->
  <script>
    const originalWarn = console.warn;
    console.warn = function(...args) {
        if (args[0] && typeof args[0] === 'string' && args[0].includes('cdn.tailwindcss.com should not be used in production')) return;
        originalWarn.apply(console, args);
    };
</script>
<script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
        corePlugins: {
            preflight: true
        },
        theme: {
            extend: {
                colors: {
                    cream: '#F8F4EC',
                    maroon: '#4A121E',
                    gold: '#C89B3C',
                    charcoal: '#2E2E2E',
                    borderlux: '#E8DCCB'
                },
                fontFamily: {
                    serif: ['Cormorant Garamond', 'Playfair Display', 'Georgia', 'serif'],
                    sans: ['Jost', 'Plus Jakarta Sans', 'sans-serif']
                }
            }
        }
    };
  </script>
  
  <!-- FontAwesome & Flatpickr -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  
  <style>
      /* Flatpickr Luxury Hotel Theme Overrides */
      .flatpickr-calendar {
          background: #F8F4EC !important;
          border: 1px solid #E8DCCB !important;
          box-shadow: 0 10px 25px rgba(59,17,27,0.06) !important;
          border-radius: 12px !important;
          font-family: 'Jost', sans-serif !important;
      }
      .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange {
          background: #4A121E !important;
          border-color: #4A121E !important;
          color: white !important;
      }
      .flatpickr-day:hover {
          background: #E8DCCB !important;
      }
      .flatpickr-months .flatpickr-month {
          color: #4A121E !important;
      }
      .flatpickr-current-month .flatpickr-monthDropdown-months {
          font-weight: 600 !important;
      }
  </style>

    <!-- Navbar Performance Optimization Links -->
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body class="bg-[#F8F4EC] text-[#2E2E2E] font-sans min-h-screen flex flex-col">

  <!-- SHARED NAVIGATION BAR -->
  <?php include_once __DIR__ . '/includes/navbar.php'; ?>
  <script src="assets/js/navbar.js" defer></script>

  <!-- MAIN CONTENT AREA -->
  <main class="flex-grow w-full mx-auto px-6 md:px-12 py-12" style="max-width:1440px;">
    
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-4xl md:text-5xl font-serif font-semibold text-[#4A121E]">My Orders</h1>
            <p class="text-sm md:text-base text-[#2E2E2E]/60 mt-1 font-normal">Track and manage your dining experiences.</p>
        </div>
        <button onclick="location.reload();" class="self-start sm:self-center flex items-center gap-2 px-5 py-2.5 bg-white border border-[#E8DCCB] hover:bg-[#F8F4EC] transition-colors rounded-xl text-xs font-semibold tracking-wider text-[#4A121E] shadow-sm uppercase">
            <i class="fa-solid fa-rotate-right"></i> Refresh
        </button>
    </div>

    <!-- Filter Section Card -->
    <div class="bg-white border border-[#E8DCCB] rounded-2xl p-6 mb-8 shadow-sm flex flex-col lg:flex-row lg:items-end gap-6">
        <!-- Search Order -->
        <div class="flex-1 w-full flex flex-col gap-1.5">
            <label class="text-[11px] font-bold uppercase tracking-wider text-[#2E2E2E]/70">Search Order</label>
            <div class="relative flex items-center">
                <i class="fa-solid fa-magnifying-glass absolute left-4 text-[#2E2E2E]/40 text-sm"></i>
                <input type="text" id="search-input" onkeyup="filterOrders()" placeholder="Enter order number..." class="w-full pl-10 pr-4 py-2.5 bg-[#F8F4EC]/40 border border-[#E8DCCB] rounded-xl text-sm text-[#2E2E2E] outline-none focus:border-[#4A121E] transition-colors">
            </div>
        </div>
        
        <!-- Order Status -->
        <div class="w-full lg:w-56 flex flex-col gap-1.5">
            <label class="text-[11px] font-bold uppercase tracking-wider text-[#2E2E2E]/70">Filter by Status</label>
            <select id="status-select" onchange="filterOrders()" class="w-full px-4 py-2.5 bg-[#F8F4EC]/40 border border-[#E8DCCB] rounded-xl text-sm text-[#2E2E2E] outline-none focus:border-[#4A121E] transition-colors cursor-pointer">
                <option value="all" <?php echo $initialStatus === 'all' ? 'selected' : ''; ?>>All Orders</option>
                <option value="active" <?php echo $initialStatus === 'active' ? 'selected' : ''; ?>>Active Orders</option>
                <option value="past" <?php echo $initialStatus === 'past' ? 'selected' : ''; ?>>Past Orders</option>
                <option value="cancelled" <?php echo $initialStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled Orders</option>
            </select>
        </div>
        
        <!-- Date Range -->
        <div class="w-full lg:w-64 flex flex-col gap-1.5">
            <label class="text-[11px] font-bold uppercase tracking-wider text-[#2E2E2E]/70">Date Range</label>
            <div class="relative flex items-center">
                <i class="fa-regular fa-calendar absolute left-4 text-[#2E2E2E]/40 text-sm"></i>
                <input type="text" id="date-range" placeholder="Select date range" class="w-full pl-10 pr-4 py-2.5 bg-[#F8F4EC]/40 border border-[#E8DCCB] rounded-xl text-sm text-[#2E2E2E] outline-none focus:border-[#4A121E] transition-colors cursor-pointer">
            </div>
        </div>
        
        <!-- Reset Filters Button -->
        <button id="reset-filters-btn" class="w-full lg:w-auto px-5 py-2.5 bg-[#4A121E] hover:bg-[#3B111B] text-white transition-colors rounded-xl text-xs font-semibold tracking-wider uppercase flex items-center justify-center gap-2 shadow-md shrink-0">
            <i class="fa-solid fa-arrow-rotate-left"></i> Reset Filters
        </button>
    </div>

    <!-- Orders List -->
    <div id="orders-list-container" style="display:flex; flex-direction:column; gap:18px;">
        <?php if (empty($orders)): ?>
            <div id="no-orders-message" style="background:#fff; border:1px solid #E8DCCB; border-radius:18px; padding:60px 30px; text-align:center;">
                <i class="fa-solid fa-utensils" style="font-size:2.5rem; color:#C89B3C; opacity:0.5; display:block; margin-bottom:16px;"></i>
                <h3 style="font-family:'Cormorant Garamond',serif; font-size:1.2rem; font-weight:600; color:#4A121E; margin-bottom:8px;">No Orders Found</h3>
                <p style="font-size:13px; color:rgba(46,46,46,0.55); max-width:360px; margin:0 auto 24px;">We couldn't find any orders matching your selected filters. Explore our menu to place an order.</p>
                <a href="menutest.html" style="display:inline-block; padding:10px 24px; background:#4A121E; color:#fff; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-decoration:none;">View Menu</a>
            </div>
        <?php else: ?>
            <!-- JS No-results placeholder -->
            <div id="no-orders-message" style="background:#fff; border:1px solid #E8DCCB; border-radius:18px; padding:60px 30px; text-align:center; display:none;">
                <i class="fa-solid fa-utensils" style="font-size:2.5rem; color:#C89B3C; opacity:0.5; display:block; margin-bottom:16px;"></i>
                <h3 style="font-family:'Cormorant Garamond',serif; font-size:1.2rem; font-weight:600; color:#4A121E; margin-bottom:8px;">No Orders Found</h3>
                <p style="font-size:13px; color:rgba(46,46,46,0.55); max-width:360px; margin:0 auto 24px;">We couldn't find any orders matching your selected filters. Explore our menu to place an order.</p>
                <a href="menutest.html" style="display:inline-block; padding:10px 24px; background:#4A121E; color:#fff; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-decoration:none;">View Menu</a>
            </div>

            <?php foreach ($orders as $order):
                $status  = strtolower($order['order_status']);
                $stepMap = ['pending'=>1,'confirmed'=>2,'preparing'=>3,'ready'=>4,'completed'=>5,'cancelled'=>0];
                $curStep = $stepMap[$status] ?? 1;

                switch ($status) {
                    case 'pending': case 'confirmed': case 'preparing':
                        $badgeStyle = 'color:#9a7320; background:rgba(200,155,60,0.13); border:1px solid rgba(200,155,60,0.5);';
                        $badgeText  = ucfirst($status);
                        break;
                    case 'ready':
                        $badgeStyle = 'color:#15803d; background:rgba(22,163,74,0.1); border:1px solid rgba(22,163,74,0.4);';
                        $badgeText  = 'Ready';
                        break;
                    case 'completed': case 'delivered':
                        $badgeStyle = 'color:#15803d; background:rgba(22,163,74,0.1); border:1px solid rgba(22,163,74,0.4);';
                        $badgeText  = 'Delivered';
                        break;
                    case 'cancelled':
                        $badgeStyle = 'color:#dc2626; background:rgba(220,38,38,0.07); border:1px solid rgba(220,38,38,0.35);';
                        $badgeText  = 'Cancelled';
                        break;
                    default:
                        $badgeStyle = 'color:#6b7280; background:#f9fafb; border:1px solid #e5e7eb;';
                        $badgeText  = ucfirst($status);
                }

                $orderDate = date('d M Y, h:i A', strtotime($order['order_date']));
                $type      = (strpos(strtolower($order['delivery_address']), 'table') !== false) ? 'Dine-in' : 'Delivery';
                // Compute real ETA countdown from estimated_delivery
                $estTime = '---';
                if ($curStep > 0 && $curStep < 5) {
                    if (!empty($order['estimated_delivery'])) {
                        $eta_ts  = strtotime($order['estimated_delivery']);
                        $now_ts  = time();
                        $diff_s  = $eta_ts - $now_ts;
                        if ($diff_s > 0) {
                            $eta_mins = ceil($diff_s / 60);
                            $estTime  = $eta_mins === 1 ? '1 min' : $eta_mins . ' mins';
                        } else {
                            $estTime = 'Arriving Soon';
                        }
                    } elseif ($curStep === 1) {
                        $estTime = '20-25 mins';
                    } elseif ($curStep >= 2 && $curStep <= 3) {
                        $estTime = '10-15 mins';
                    } elseif ($curStep === 4) {
                        $estTime = '5 mins';
                    }
                }
            ?>


            <!-- ══ ORDER CARD ══ -->
            <div class="order-card-item"
                 data-order-number="<?php echo htmlspecialchars($order['order_number']); ?>"
                 data-order-status="<?php echo htmlspecialchars($order['order_status']); ?>"
                 data-order-date="<?php echo htmlspecialchars($order['order_date']); ?>"
                 style="background:#fff; border:1px solid #E8DCCB; border-radius:18px; padding:36px; display:flex; gap:0; align-items:flex-start; box-shadow:0 1px 6px rgba(0,0,0,0.05);">

                <!-- ─── TIMELINE (220px) ─── -->
                <div style="width:240px; flex-shrink:0; padding-right:30px; border-right:1.5px solid #EDE5D8;">
                    <?php
                    $steps = [
                        1 => ['label'=>'Order Placed', 'icon'=>'fa-check'],
                        2 => ['label'=>'Confirmed',    'icon'=>'fa-check'],
                        3 => ['label'=>'Preparing',    'icon'=>'fa-fire-burner'],
                        4 => ['label'=>'Ready',        'icon'=>'fa-bell-concierge'],
                        5 => ['label'=>'Delivered',    'icon'=>'fa-check'],
                    ];
                    foreach ($steps as $i => $s):
                        $isDone   = ($curStep > 0 && $i < $curStep);
                        $isActive = ($curStep > 0 && $i == $curStep);
                        $isPend   = ($curStep == 0 || $i > $curStep);
                        $isLast   = ($i === 5);
                    ?>
                    <div style="display:flex; align-items:flex-start; gap:11px; position:relative; <?php echo !$isLast ? 'padding-bottom:16px;' : ''; ?>">
                        <?php if (!$isLast): ?>
                        <div style="position:absolute; left:12px; top:27px; bottom:0; width:2px; background:<?php echo ($isDone ? '#4A121E' : '#E8DCCB'); ?>;"></div>
                        <?php endif; ?>

                        <?php if ($isDone || $isActive): ?>
                        <div style="width:25px; height:25px; border-radius:50%; background:#4A121E; display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative; z-index:1; margin-top:1px;">
                            <i class="fa-solid <?php echo $isDone ? 'fa-check' : $s['icon']; ?>" style="color:#fff; font-size:9px;"></i>
                        </div>
                        <?php else: ?>
                        <div style="width:25px; height:25px; border-radius:50%; background:#fff; border:2px solid #D4C9B8; display:flex; align-items:center; justify-content:center; flex-shrink:0; position:relative; z-index:1; margin-top:1px;">
                            <i class="fa-regular fa-user" style="color:#D4C9B8; font-size:9px;"></i>
                        </div>
                        <?php endif; ?>

                        <div>
                            <div style="font-size:13px; font-weight:600; line-height:1.3; color:<?php echo $isPend ? 'rgba(46,46,46,0.28)' : '#1E1E1E'; ?>;"><?php echo $s['label']; ?></div>
                            <div style="font-size:11px; color:rgba(46,46,46,0.42); margin-top:1px;">
                                <?php echo ($curStep > 0 && $i <= $curStep)
                                    ? date('h:i A', strtotime($order['order_date']) + (($i-1)*120))
                                    : '--:--'; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div><!-- /timeline -->

                <!-- ─── MAIN CONTENT (flex:1) ─── -->
                <div style="flex:1; padding-left:26px; min-width:0;">

                    <!-- Header: order info left + est. time right -->
                    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px;">

                        <div style="display:flex; align-items:center; gap:13px;">
                            <div style="width:44px; height:44px; background:#1C1C1E; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="white" style="width:21px;height:21px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007Z"/>
                                </svg>
                            </div>
                            <div>
                                <div style="display:flex; align-items:center; gap:9px; margin-bottom:4px; flex-wrap:wrap;">
                                    <span style="font-size:17px; font-weight:700; color:#4A121E; letter-spacing:0.3px;">ORDER #<?php echo htmlspecialchars($order['order_number']); ?></span>
                                    <span style="font-size:9px; padding:3px 9px; border-radius:20px; font-weight:700; letter-spacing:0.8px; text-transform:uppercase; <?php echo $badgeStyle; ?>"><?php echo $badgeText; ?></span>
                                </div>
                                <div style="font-size:12px; color:rgba(46,46,46,0.48);"><?php echo $orderDate; ?> &bull; <?php echo $type; ?></div>
                            </div>
                        </div>

                        <!-- Estimated Time / Status -->
                        <div style="text-align:right; flex-shrink:0; padding-top:2px;">
                            <?php if ($curStep > 0 && $curStep < 5): ?>
                                <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:2px; color:rgba(46,46,46,0.38); margin-bottom:5px;">Estimated Time</div>
                                <div style="display:flex; align-items:center; justify-content:flex-end; gap:7px; color:#C89B3C;">
                                    <i class="fa-regular fa-clock" style="font-size:15px;"></i>
                                    <span style="font-family:'Cormorant Garamond',serif; font-size:21px; font-weight:600; line-height:1;"><?php echo $estTime; ?></span>
                                </div>
                            <?php elseif ($curStep == 5): ?>
                                <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:2px; color:rgba(46,46,46,0.38); margin-bottom:5px;">Delivered on</div>
                                <div style="display:flex; align-items:center; justify-content:flex-end; gap:7px; color:#15803d;">
                                    <i class="fa-regular fa-circle-check" style="font-size:14px;"></i>
                                    <span style="font-size:14px; font-weight:600;"><?php echo date('d M Y', strtotime($order['order_date'])); ?></span>
                                </div>
                            <?php elseif ($curStep == 0): ?>
                                <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:2px; color:rgba(46,46,46,0.38); margin-bottom:5px;">Status</div>
                                <div style="display:flex; align-items:center; justify-content:flex-end; gap:7px; color:#dc2626; margin-bottom:4px;">
                                    <i class="fa-regular fa-circle-xmark" style="font-size:14px;"></i>
                                    <span style="font-size:14px; font-weight:600;">Cancelled</span>
                                </div>
                                <?php if (!empty($order['cancellation_reason'])): ?>
                                <div style="font-size:11px; color:#dc2626; font-weight:500; font-style:italic; text-align:right; max-width:160px; margin-left:auto; word-break:break-word; line-height:1.2;">
                                    <?php echo htmlspecialchars($order['cancellation_reason']); ?>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div><!-- /header -->

                    <?php if ($curStep == 0 && !empty($order['cancellation_reason'])): ?>
                    <div style="margin-bottom:16px; padding:12px 16px; background:#fef2f2; border:1px solid #fecaca; border-radius:10px; display:flex; align-items:center; gap:8px; color:#b91c1c; font-size:12px; line-height:1.4;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size:14px;"></i>
                        <span><strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($order['cancellation_reason']); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Items -->
                    <div style="border-top:1px solid #EDE5D8; padding-top:14px;">
                        <div style="font-size:12px; font-weight:600; color:#2E2E2E; margin-bottom:11px;">Order Items (<?php echo count($order['items']); ?>)</div>
                        <?php foreach ($order['items'] as $item):
                            $dStyle = 'color:#15803d; border-color:rgba(22,163,74,0.55);';
                            $dText  = 'Veg';
                            $ln     = strtolower($item['item_name']);
                            if (strpos($ln,'chicken')!==false||strpos($ln,'lamb')!==false||strpos($ln,'prawn')!==false||strpos($ln,'fish')!==false){
                                $dStyle='color:#dc2626; border-color:rgba(220,38,38,0.55);'; $dText='Non-Veg';
                            }
                            if (strpos($ln,'mojito')!==false||strpos($ln,'wine')!==false||strpos($ln,'beer')!==false||strpos($ln,'cola')!==false){
                                $dStyle='color:#2563eb; border-color:rgba(37,99,235,0.55);'; $dText='Bar';
                            }
                            $img = !empty($item['image_url']) ? $item['image_url'] : '';
                            if (!empty($img) && strpos($img,'http')!==0 && strpos($img,'//')!==0)
                                $img = (strpos($img,'uploads/')!==0) ? 'uploads/'.$img : $img;
                            if (empty($img)) $img = 'uploads/default.jpg';
                        ?>
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
                            <img src="<?php echo htmlspecialchars($img); ?>"
                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                 style="width:50px; height:50px; border-radius:8px; object-fit:cover; flex-shrink:0; border:1px solid #EDE5D8; background:#F8F4EC;"
                                 onerror="this.src='assets/images/logo.png'">
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:13px; font-weight:600; color:#1E1E1E; line-height:1.3; margin-bottom:4px;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <span style="display:inline-block; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; padding:2px 7px; border-radius:4px; border:1px solid; <?php echo $dStyle; ?>"><?php echo $dText; ?></span>
                            </div>
                            <div style="font-size:13px; font-weight:600; color:#1E1E1E; flex-shrink:0; min-width:70px; text-align:right;">₹<?php echo number_format($item['price'],2); ?></div>
                            <div style="font-size:12px; color:rgba(46,46,46,0.4); width:26px; text-align:right; flex-shrink:0;">× <?php echo $item['quantity']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div><!-- /items -->

                    <!-- Total + Buttons -->
                    <div style="border-top:1px solid #EDE5D8; padding-top:14px; margin-top:8px; display:flex; align-items:center; gap:9px; flex-wrap:wrap;">
                        <div style="margin-right:auto;">
                            <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1.8px; color:rgba(46,46,46,0.42); margin-bottom:3px;">Total Amount</div>
                            <div style="font-family:'Cormorant Garamond',serif; font-size:24px; font-weight:600; color:#C89B3C; line-height:1.1;">₹<?php echo number_format($order['total_amount'],2); ?></div>
                        </div>

                        <?php if ($curStep > 0 && $curStep < 5 && !empty($order['tracking_token'])): ?>
                        <a href="track.php?token=<?php echo urlencode($order['tracking_token']); ?>"
                           style="display:inline-flex; align-items:center; gap:7px; height:44px; padding:0 20px; background:#C89B3C; color:#fff; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-decoration:none; transition:background .2s; white-space:nowrap; border:none;"
                           onmouseover="this.style.background='#b88c2e'" onmouseout="this.style.background='#C89B3C'">
                            Track Order <i class="fa-solid fa-location-arrow" style="font-size:9px;"></i>
                        </a>
                        <?php endif; ?>

                        <a href="order-details.php?order_id=<?php echo urlencode($order['order_number']); ?>"
                           style="display:inline-flex; align-items:center; height:44px; padding:0 18px; background:#fff; color:#2E2E2E; border:1px solid #D4C9B8; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-decoration:none; transition:background .2s; white-space:nowrap;"
                           onmouseover="this.style.background='#F8F4EC'" onmouseout="this.style.background='#fff'">
                            View Details
                        </a>

                        <a href="download_bill.php?id=<?php echo $order['id']; ?>&token=<?php echo generateToken($order['id']); ?>" target="_blank"
                           style="display:inline-flex; align-items:center; height:44px; padding:0 18px; background:#fff; color:#2E2E2E; border:1px solid #D4C9B8; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-decoration:none; transition:background .2s; white-space:nowrap;"
                           onmouseover="this.style.background='#F8F4EC'" onmouseout="this.style.background='#fff'">
                            Invoice
                        </a>

                        <?php if ($curStep > 0 && $curStep < 3): ?>
                        <button onclick="cancelOrder(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')"
                                style="display:inline-flex; align-items:center; height:44px; padding:0 18px; background:#fff; color:#dc2626; border:1px solid rgba(220,38,38,0.4); border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; cursor:pointer; transition:background .2s; white-space:nowrap;"
                                onmouseover="this.style.background='#fff5f5'" onmouseout="this.style.background='#fff'">
                            Cancel Order
                        </button>
                        <?php endif; ?>

                        <?php if ($curStep == 5): ?>
                        <button onclick="reorderItems(<?php echo $order['id']; ?>)"
                                style="display:inline-flex; align-items:center; gap:7px; height:44px; padding:0 20px; background:#C89B3C; color:#fff; border:none; border-radius:10px; font-size:11px; font-weight:700; letter-spacing:1px; text-transform:uppercase; cursor:pointer; transition:background .2s; white-space:nowrap;"
                                onmouseover="this.style.background='#b88c2e'" onmouseout="this.style.background='#C89B3C'">
                            <i class="fa-solid fa-rotate-left" style="font-size:9px;"></i> Reorder
                        </button>
                        <?php endif; ?>
                    </div><!-- /total+buttons -->

                </div><!-- /main content -->
            </div><!-- /order-card-item -->
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- SUPPORT CARD / HELP SECTION -->
    <div class="bg-white border border-[#E8DCCB] rounded-2xl p-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-6 mb-8 mt-16">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-[#C89B3C]/10 flex items-center justify-center text-[#C89B3C] text-xl shrink-0">
                <i class="fa-solid fa-headset"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-[#4A121E]">Need Help?</h4>
                <p class="text-xs text-[#2E2E2E]/60 mt-0.5">Our support team is here to help you with your orders.</p>
            </div>
        </div>
        <a href="contact.html" class="px-6 py-2.5 bg-[#4A121E] hover:bg-[#3B111B] text-white rounded-xl text-xs font-semibold uppercase tracking-wider no-underline transition-colors shadow-md text-center shrink-0">Contact Support</a>
    </div>

  </main>

  <!-- SHARED FOOTER -->
  <?php include_once __DIR__ . '/includes/footer.php'; ?>

  <!-- Flatpickr Range JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  
  <script>
      // Initialize Date Picker Range
      flatpickr("#date-range", {
          mode: "range",
          dateFormat: "Y-m-d",
          altInput: true,
          altFormat: "d M Y",
          onChange: function(selectedDates, dateStr, instance) {
              filterOrders();
          }
      });

      // Filter Logic
      function filterOrders() {
          const searchQuery = document.getElementById('search-input').value.trim().toLowerCase();
          const statusQuery = document.getElementById('status-select').value.toLowerCase();
          const dateRangeInput = document.getElementById('date-range').value;
          
          let startDate = null;
          let endDate = null;
          if (dateRangeInput.includes(' to ')) {
              const parts = dateRangeInput.split(' to ');
              startDate = new Date(parts[0]);
              endDate = new Date(parts[1]);
              endDate.setHours(23, 59, 59, 999);
          } else if (dateRangeInput) {
              startDate = new Date(dateRangeInput);
              endDate = new Date(dateRangeInput);
              endDate.setHours(23, 59, 59, 999);
          }
          
          const cards = document.querySelectorAll('.order-card-item');
          let visibleCount = 0;
          
          cards.forEach(card => {
              const orderNumber = card.getAttribute('data-order-number').toLowerCase();
              const orderStatus = card.getAttribute('data-order-status').toLowerCase();
              const orderDateStr = card.getAttribute('data-order-date');
              const orderDate = new Date(orderDateStr);
              
              let matchesSearch = !searchQuery || orderNumber.includes(searchQuery);
              
              let matchesStatus = true;
              if (statusQuery === 'active') {
                  matchesStatus = ['pending', 'confirmed', 'preparing', 'ready'].includes(orderStatus);
              } else if (statusQuery === 'past') {
                  matchesStatus = ['completed', 'delivered'].includes(orderStatus);
              } else if (statusQuery && statusQuery !== 'all') {
                  matchesStatus = (orderStatus === statusQuery || (statusQuery === 'delivered' && orderStatus === 'completed'));
              }
              
              let matchesDate = true;
              if (startDate && endDate) {
                  matchesDate = (orderDate >= startDate && orderDate <= endDate);
              }
              
              if (matchesSearch && matchesStatus && matchesDate) {
                  card.style.display = 'flex';
                  visibleCount++;
              } else {
                  card.style.display = 'none';
              }
          });
          
          const noOrdersMsg = document.getElementById('no-orders-message');
          if (visibleCount === 0) {
              noOrdersMsg.classList.remove('hidden');
              noOrdersMsg.style.display = 'block';
          } else {
              noOrdersMsg.classList.add('hidden');
              noOrdersMsg.style.display = 'none';
          }
      }

      // Reset Filters Event
      document.getElementById('reset-filters-btn').addEventListener('click', () => {
          document.getElementById('search-input').value = '';
          document.getElementById('status-select').value = 'all';
          const fpInstance = document.getElementById('date-range')._flatpickr;
          if (fpInstance) fpInstance.clear();
          filterOrders();
      });

      // Run filter initially on load (for initialStatus parameters like active, past, cancelled)
      document.addEventListener('DOMContentLoaded', () => {
          filterOrders();
      });

      // Cancel Order AJAX Action
      async function cancelOrder(orderId, orderNumber) {
          if (!confirm(`Are you sure you want to cancel order #${orderNumber}?`)) {
              return;
          }
          try {
              const formData = new FormData();
              formData.append('action', 'cancel_order');
              formData.append('order_id', orderId);
              
              const response = await fetch('my-orders.php', {
                  method: 'POST',
                  body: formData
              });
              
              const result = await response.json();
              if (result.success) {
                  alert(result.message);
                  location.reload();
              } else {
                  alert(result.message || 'Failed to cancel order.');
              }
          } catch (e) {
              console.error('Error cancelling order:', e);
              alert('Network error. Failed to cancel order.');
          }
      }

      // Reorder AJAX Action
      async function reorderItems(orderId) {
          try {
              const response = await fetch('api/account-api.php?action=reorder', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ order_id: orderId })
              });
              const result = await response.json();
              if (result.success) {
                  window.location.href = 'carttest.html';
              } else {
                  alert(result.message || 'Failed to reorder items.');
              }
          } catch (e) {
              console.error('Error reordering items:', e);
              alert('Network error. Failed to reorder items.');
          }
      }
  </script>
</body>
</html>
