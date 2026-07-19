<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/pdf.php';
require_once __DIR__ . '/includes/token_helper.php';
require_once __DIR__ . '/includes/sms.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/whatsapp.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
require_same_origin_unsafe_request();
rate_limit('order_place', 8, 300);

// Read JSON input
$input = request_raw_body();
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid order request data.'
    ]);
    exit;
}

$sent_csrf = (string)($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent_csrf)) {
    json_response([
        'success' => false,
        'message' => 'Your session expired. Please refresh checkout and try again.'
    ], 403);
}

$customer_name = trim($data['customer_name'] ?? 'Customer');
$customer_phone = trim($data['customer_phone'] ?? '');
$customer_email = trim($data['customer_email'] ?? '');
$delivery_address = trim($data['delivery_address'] ?? '');
$delivery_city = trim($data['delivery_city'] ?? '');
$delivery_state = trim($data['delivery_state'] ?? '');
$delivery_pincode = trim($data['delivery_pincode'] ?? '');
$payment_method = trim($data['payment_method'] ?? 'Online');
$payment_method_key = strtolower($payment_method);
$payment_id = trim($data['razorpay_payment_id'] ?? '');
$cart_items = $data['cart_items'] ?? [];
$coupon_code = trim($data['coupon_code'] ?? '');
$redeem_loyalty_points = !empty($data['redeem_loyalty_points']);
$save_address = !empty($data['save_address']);
$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$country = trim($data['country'] ?? 'India');
$street = trim($data['street'] ?? $delivery_address);
$apartment = trim($data['apartment'] ?? '');

if (empty($customer_phone) || empty($cart_items)) {
    echo json_encode([
        'success' => false,
        'message' => 'Customer phone and cart items are required.'
    ]);
    exit;
}

$online_methods = ['online', 'card', 'upi', 'gpay'];
if (in_array($payment_method_key, $online_methods, true) && ($payment_id === '' || stripos($payment_id, 'mock') !== false)) {
    json_response([
        'success' => false,
        'message' => 'Payment confirmation is required before placing this order.'
    ], 402);
}

if ($payment_method_key === 'membership') {
    $card_number = trim($data['membership_card_number'] ?? '');
    $cvv = trim($data['membership_cvv'] ?? '');

    if (empty($db_user_id ?? $_SESSION['user_id'] ?? null)) {
        json_response(['success' => false, 'message' => 'Please log in to use Membership Pass.'], 401);
    }

    $membership_user_id = $_SESSION['user_id'];
    $card_stmt = $pdo->prepare("SELECT id FROM membership_cards WHERE user_id = ? AND card_number = ? AND cvv = ?");
    $card_stmt->execute([$membership_user_id, $card_number, $cvv]);
    if (!$card_stmt->fetch()) {
        json_response(['success' => false, 'message' => 'Invalid Membership Pass details.'], 403);
    }

    $payment_id = 'MEMBERSHIP_' . substr($card_number, -4);
} elseif ($payment_method_key === 'cod') {
    $payment_id = 'COD';
}

// Build the bill from server-side menu records. Browser prices/names are display-only.
$subtotal = 0;
$normalized_cart_items = [];
$lookup_by_id = $pdo->prepare("SELECT id, name, price, category, subcategory FROM food_items WHERE id = ? AND is_available = 1");
$lookup_by_name = $pdo->prepare("SELECT id, name, price, category, subcategory FROM food_items WHERE name = ? AND is_available = 1 LIMIT 1");

foreach ($cart_items as $item) {
    if (!is_array($item)) continue;

    $food_item_id = intval($item['food_item_id'] ?? ($item['id'] ?? 0));
    $item_qty = max(1, min(99, intval($item['quantity'] ?? 1)));

    if ($food_item_id > 0) {
        $lookup_by_id->execute([$food_item_id]);
        $menu_item = $lookup_by_id->fetch(PDO::FETCH_ASSOC);
    } else {
        $lookup_name = trim($item['name'] ?? '');
        $lookup_by_name->execute([$lookup_name]);
        $menu_item = $lookup_by_name->fetch(PDO::FETCH_ASSOC);
    }

    if (!$menu_item) {
        json_response([
            'success' => false,
            'message' => 'One or more cart items are no longer available. Please refresh your cart.'
        ], 400);
    }

    $item_price = floatval($menu_item['price']);
    $item_subtotal = $item_price * $item_qty;
    $subtotal += $item_subtotal;

    $normalized_cart_items[] = [
        'id' => intval($menu_item['id']),
        'food_item_id' => intval($menu_item['id']),
        'name' => $menu_item['name'],
        'price' => $item_price,
        'quantity' => $item_qty,
        'category' => $menu_item['category'] ?? '',
        'subcategory' => $menu_item['subcategory'] ?? '',
        'subtotal' => $item_subtotal
    ];
}

if (empty($normalized_cart_items) || $subtotal <= 0) {
    json_response([
        'success' => false,
        'message' => 'Your cart is empty or unavailable. Please refresh and try again.'
    ], 400);
}
$cart_items = $normalized_cart_items;

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
// Override with .env configurations
$settings['restaurant_name'] = get_env_var('RESTAURANT_NAME', $settings['restaurant_name']);
$settings['gst_rate'] = intval(get_env_var('GST_RATE', $settings['gst_rate']));
$settings['opening_hours'] = get_env_var('OPENING_HOURS', $settings['opening_hours']);

$gst_rate = isset($settings['gst_rate']) ? intval($settings['gst_rate']) : 18;
$packing_charge = isset($settings['packing_charge']) ? floatval($settings['packing_charge']) : 0.00;

$tax_amount = $subtotal * ($gst_rate / 100);
$delivery_charge = floatval(get_env_var('DELIVERY_CHARGE', '40.00'));
$packing_fee = (strpos(strtolower($delivery_address), 'table') !== false) ? 0.00 : $packing_charge;

$db_user_id = $_SESSION['user_id'] ?? null;
$user_tier_discount_percent = 0;
$user_points_balance = 0;
$points_earning_percent = 2.00;
$current_tier_id = 1;

if ($db_user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.current_tier_id, t.discount_percent, t.points_earning_percent 
            FROM users u 
            LEFT JOIN customer_tiers t ON u.current_tier_id = t.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$db_user_id]);
        $user_tier = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_tier) {
            $current_tier_id = intval($user_tier['current_tier_id'] ?? 1);
            $user_tier_discount_percent = floatval($user_tier['discount_percent'] ?? 10.00);
            $points_earning_percent = floatval($user_tier['points_earning_percent'] ?? 2.00);
        }
        
        $pts_stmt = $pdo->prepare("SELECT current_balance FROM reward_points WHERE user_id = ?");
        $pts_stmt->execute([$db_user_id]);
        $user_points_balance = intval($pts_stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {
        error_log("Failed to fetch user loyalty tier info: " . $e->getMessage());
    }
}

$tier_discount = 0;
if ($db_user_id && $user_tier_discount_percent > 0) {
    $tier_discount = $subtotal * ($user_tier_discount_percent / 100);
}

$total = $subtotal + $tax_amount + $delivery_charge + $packing_fee - $tier_discount;

// Coupon discount validation
$coupon_discount = 0;
$coupon_valid = false;
$coupon_entity = null;
if (!empty($coupon_code)) {
    require_once __DIR__ . '/api/CouponService.php';
    try {
        $couponService = new CouponService($pdo);
        $coupon_entity = $couponService->validateCoupon($coupon_code);
        $coupon_discount = $subtotal * ($coupon_entity->discount_value / 100);
        $coupon_valid = true;
    } catch (Exception $e) {
        // Log and continue without coupon to prevent checkout failure
        error_log("Coupon validation failed: " . $e->getMessage());
    }
}
$total = max(0, $total - $coupon_discount);

// Points redemption
$points_redeemed = 0;
$points_discount = 0;
if ($db_user_id && $redeem_loyalty_points && $user_points_balance > 0) {
    $points_redeemed = min($user_points_balance, intval($total));
    $points_discount = $points_redeemed * 1.00;
    $total = max(0, $total - $points_discount);
}

// Points earned
$points_earned = 0;
if ($db_user_id) {
    $points_earned = round($total * ($points_earning_percent / 100));
}

// Generate unique order number (ORD-XXXXX)
$order_number = 'ORD-' . strtoupper(substr(uniqid(), 7, 5));
$estimated_delivery = date('Y-m-d H:i:s', strtotime('+45 minutes'));

// Ensure user_liquor_quota table exists before starting transaction
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_liquor_quota` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `food_item_id` INT NOT NULL,
            `item_name` VARCHAR(255) NOT NULL,
            `total_pegs` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `user_item` (`user_id`, `food_item_id`),
            CONSTRAINT `fk_quota_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_quota_items` FOREIGN KEY (`food_item_id`) REFERENCES `food_items` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $ex) {
    error_log("Failed to create user_liquor_quota table: " . $ex->getMessage());
}

// Save address if requested
if ($db_user_id && $save_address) {
    try {
        $ins_addr = $pdo->prepare("
            INSERT INTO user_addresses 
            (user_id, first_name, last_name, phone, email, country, street, apartment, city, state, zip) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins_addr->execute([
            $db_user_id,
            $first_name ?: $customer_name,
            $last_name,
            $customer_phone,
            $customer_email,
            $country,
            $street,
            $apartment,
            $delivery_city,
            $delivery_state,
            $delivery_pincode
        ]);
    } catch (Exception $e) {
        error_log("Failed to save address: " . $e->getMessage());
    }
}

try {
    $pdo->beginTransaction();

    // If guest, save info to guest_info table
    if (!$db_user_id) {
        $ins_guest = $pdo->prepare("
            INSERT INTO guest_info 
            (name, email, phone, address, city, state, pincode) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins_guest->execute([
            $customer_name,
            $customer_email ?? '',
            $customer_phone,
            $delivery_address,
            $delivery_city,
            $delivery_state,
            $delivery_pincode
        ]);
    }

    // 1. Insert order into orders table
    $ins_order = $pdo->prepare("
        INSERT INTO orders 
        (order_number, customer_name, customer_phone, delivery_address, delivery_city, delivery_state, delivery_pincode, total_amount, tax_amount, discount, tier_discount_amount, points_redeemed, points_redeemed_discount, points_earned, payment_method, estimated_delivery, user_id, order_status, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
    ");
    $ins_order->execute([
        $order_number,
        $customer_name,
        $customer_phone,
        $delivery_address,
        $delivery_city,
        $delivery_state,
        $delivery_pincode,
        $total,
        $tax_amount,
        $coupon_discount,
        $tier_discount,
        $points_redeemed,
        $points_discount,
        $points_earned,
        $payment_method,
        $estimated_delivery,
        $db_user_id,
    ]);

    $db_order_id = $pdo->lastInsertId();

    // Generate tracking token and store in session
    $tracking_token = bin2hex(random_bytes(32));
    $upd_token = $pdo->prepare("UPDATE orders SET tracking_token = ?, tracking_status = 'placed', estimated_delivery = ? WHERE id = ?");
    $upd_token->execute([$tracking_token, $estimated_delivery, $db_order_id]);
    $_SESSION['active_order_token'] = $tracking_token;
    $_SESSION['active_order_id']    = $db_order_id;
    $_SESSION['order_placed_at']    = time();

    // 2. Insert order items into order_items table
    $items_for_pdf = [];
    $pegs_to_add = 0;
    foreach ($cart_items as $item) {
        $food_item_id = intval($item['food_item_id']);
        $category = $item['category'] ?? '';
        $item_price = floatval($item['price']);
        $item_qty = intval($item['quantity'] ?? 1);
        $item_subtotal = floatval($item['subtotal'] ?? ($item_price * $item_qty));

        $subcategory = $item['subcategory'] ?? '';

        $is_alcoholic = false;
        if ($category && strtolower(trim($category)) === 'beverages') {
            $non_alcoholic_subs = ['detox', 'signature mocktails', 'coffee (hot)', 'coffee (cold)', 'shakes & smoothies', 'iced teas', 'green tea', 'quenchers'];
            if (!in_array(strtolower(trim($subcategory)), $non_alcoholic_subs)) {
                $is_alcoholic = true;
            }
        } elseif ($category && strtolower(trim($category)) === 'liquor') {
            $is_alcoholic = true;
        }

        if ($is_alcoholic) {
            $pegs_to_add += 8 * $item_qty;
            if ($db_user_id && $food_item_id) {
                $check_q = $pdo->prepare("SELECT id FROM user_liquor_quota WHERE user_id = ? AND food_item_id = ?");
                $check_q->execute([$db_user_id, $food_item_id]);
                if ($check_q->fetch()) {
                    $upd_q = $pdo->prepare("UPDATE user_liquor_quota SET total_pegs = total_pegs + ? WHERE user_id = ? AND food_item_id = ?");
                    $upd_q->execute([8 * $item_qty, $db_user_id, $food_item_id]);
                } else {
                    $ins_q = $pdo->prepare("INSERT INTO user_liquor_quota (user_id, food_item_id, item_name, total_pegs) VALUES (?, ?, ?, ?)");
                    $ins_q->execute([$db_user_id, $food_item_id, $item['name'], 8 * $item_qty]);
                }
            }
        }

        $ins_item = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, food_item_id, item_name, quantity, price, unit_price, subtotal) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ins_item->execute([
            $db_order_id,
            $food_item_id,
            $item['name'],
            $item_qty,
            $item_price, // price (legacy support)
            $item_price, // unit_price
            $item_subtotal
        ]);

        $items_for_pdf[] = [
            'item_name' => $item['name'],
            'quantity' => $item_qty,
            'unit_price' => $item_price,
            'subtotal' => $item_subtotal
        ];
    }

    if ($db_user_id && $pegs_to_add > 0) {
        $upd_quota = $pdo->prepare("UPDATE users SET liquor_quota_pegs = liquor_quota_pegs + ? WHERE id = ?");
        $upd_quota->execute([$pegs_to_add, $db_user_id]);
    }

    // Prepare variables for PDF generation
    $order_data = [
        'id' => $db_order_id,
        'order_number' => $order_number,
        'order_date' => date('Y-m-d H:i:s'),
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'delivery_address' => $delivery_address,
        'delivery_city' => $delivery_city,
        'delivery_state' => $delivery_state,
        'delivery_pincode' => $delivery_pincode,
        'total_amount' => $total,
        'tax_amount' => $tax_amount,
        'discount' => $coupon_discount,
        'tier_discount_amount' => $tier_discount,
        'points_redeemed' => $points_redeemed,
        'points_redeemed_discount' => $points_discount,
        'points_earned' => $points_earned,
        'payment_method' => $payment_method,
        'estimated_delivery' => $estimated_delivery,
        'packing_charge' => $packing_fee,
        'delivery_charge' => $delivery_charge
    ];

    // 3. Generate PDF bill using TCPDF
    $pdf_relative_path = generateBillPdf($order_data, $items_for_pdf);

    // 4. Update order with PDF path
    $upd_pdf = $pdo->prepare("UPDATE orders SET pdf_path = ? WHERE id = ?");
    $upd_pdf->execute([$pdf_relative_path, $db_order_id]);

    // Redeem coupon inside the transaction if valid
    if ($coupon_valid && $coupon_entity) {
        $couponService->redeemCoupon($coupon_code, $db_order_id);
    }

    // Process Loyalty points & Tier progression
    if ($db_user_id) {
        // Clear database cart for this user upon successful order placement
        $clear_cart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $clear_cart->execute([$db_user_id]);

        // Deduct points
        if ($points_redeemed > 0) {
            $upd_pts = $pdo->prepare("
                UPDATE reward_points 
                SET points_redeemed = points_redeemed + ?, 
                    current_balance = current_balance - ? 
                WHERE user_id = ?
            ");
            $upd_pts->execute([$points_redeemed, $points_redeemed, $db_user_id]);
            
            $ins_trans = $pdo->prepare("
                INSERT INTO loyalty_transactions 
                (user_id, order_id, points_redeemed, transaction_type) 
                VALUES (?, ?, ?, 'redeem')
            ");
            $ins_trans->execute([$db_user_id, $db_order_id, $points_redeemed]);
        }

        // Earn points
        if ($points_earned > 0) {
            $upd_pts2 = $pdo->prepare("
                UPDATE reward_points 
                SET points_earned = points_earned + ?, 
                    current_balance = current_balance + ? 
                WHERE user_id = ?
            ");
            $upd_pts2->execute([$points_earned, $points_earned, $db_user_id]);
            
            $ins_trans2 = $pdo->prepare("
                INSERT INTO loyalty_transactions 
                (user_id, order_id, points_earned, transaction_type) 
                VALUES (?, ?, ?, 'earn')
            ");
            $ins_trans2->execute([$db_user_id, $db_order_id, $points_earned]);
        }

        // Calculate user's updated lifetime spend
        $spend_stmt = $pdo->prepare("
            SELECT SUM(total_amount) 
            FROM orders 
            WHERE user_id = ? AND order_status != 'cancelled'
        ");
        $spend_stmt->execute([$db_user_id]);
        $lifetime_spend = floatval($spend_stmt->fetchColumn() ?: 0.00);

        // Fetch all tiers to see if eligible for upgrade
        $tiers_stmt = $pdo->query("SELECT * FROM customer_tiers ORDER BY spending_requirement ASC");
        $all_tiers = $tiers_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $eligible_tier_id = 1;
        $eligible_tier_name = 'Bronze';
        $eligible_discount_percent = 10.00;
        foreach ($all_tiers as $t) {
            if ($lifetime_spend >= floatval($t['spending_requirement'])) {
                $eligible_tier_id = intval($t['id']);
                $eligible_tier_name = $t['tier_name'];
                $eligible_discount_percent = floatval($t['discount_percent']);
            }
        }

        if ($eligible_tier_id > $current_tier_id) {
            $upd_user_tier = $pdo->prepare("UPDATE users SET current_tier_id = ? WHERE id = ?");
            $upd_user_tier->execute([$eligible_tier_id, $db_user_id]);
            
            $ins_history = $pdo->prepare("
                INSERT INTO tier_history (user_id, previous_tier_id, new_tier_id, reason) 
                VALUES (?, ?, ?, 'Spending threshold met')
            ");
            $ins_history->execute([$db_user_id, $current_tier_id, $eligible_tier_id]);
            
            $notif_title = "Loyalty Tier Upgraded!";
            $notif_msg = "Congratulations! Your lifetime spend has reached ₹" . number_format($lifetime_spend, 2) . ". You have been promoted to the " . $eligible_tier_name . " tier, giving you a " . $eligible_tier_name . " tier discount of " . $eligible_discount_percent . "% on future orders.";
            
            $ins_notif = $pdo->prepare("
                INSERT INTO user_notifications (user_id, title, message) 
                VALUES (?, ?, ?)
            ");
            $ins_notif->execute([$db_user_id, $notif_title, $notif_msg]);
        }

        // Customer notification for Order Placed
        if ($db_user_id) {
            $is_delivery = (stripos($delivery_address, 'table') === false);
            if ($is_delivery) {
                $cust_notif_title = "Order Placed Successfully";
                $cust_notif_msg = "Your order #{$order_number} has been placed successfully and is being processed.";
                $ins_cust_notif = $pdo->prepare("INSERT INTO user_notifications (user_id, title, message) VALUES (?, ?, ?)");
                $ins_cust_notif->execute([$db_user_id, $cust_notif_title, $cust_notif_msg]);
            }
        }
    } else {
        // Clear database cart for guest user upon successful order placement
        $session_id = session_id();
        $clear_cart = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL");
        $clear_cart->execute([$session_id]);
    }
        // Trigger notification triggers for admin panel
        require_once __DIR__ . '/includes/notifications_helper.php';
        
        // 1. Order notification
        $order_notif_body = "Order {$order_number} has been placed by {$customer_name} via {$payment_method}. Total amount: ₹" . number_format($total, 2);
        addNotification('order', 'New Order Received', $order_notif_body);

        // 2. Payment notification
        if ($payment_method_key === 'cod') {
            $payment_notif_body = "Order {$order_number} is placed with Cash on Delivery. Payment will be collected on delivery.";
            addNotification('payment', 'Payment Pending', $payment_notif_body);
        } else {
            $payment_notif_body = "Payment of ₹" . number_format($total, 2) . " processed successfully for order {$order_number}.";
            addNotification('payment', 'Payment Successful', $payment_notif_body);
        }

        // 3. Kitchen notification (special requests)
        $special_req = trim($data['message'] ?? '');
        if (!empty($special_req)) {
            $kitchen_notif_body = "Special request added for order {$order_number}: \"{$special_req}\"";
            addNotification('kitchen', 'Special Request Added', $kitchen_notif_body);
        }

        $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save order to database: ' . $e->getMessage()
    ]);
    exit;
}

// Generate secure download link
$download_token = generateToken($db_order_id);
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($host === 'localhost' || $host === '127.0.0.1') {
    $local_ip = gethostbyname(gethostname());
    if ($local_ip && $local_ip !== '127.0.0.1') {
        $host = $local_ip;
    }
}
$download_url = $protocol . $host . "/restaurant_medusa/download_bill.php?id=" . $db_order_id . "&token=" . $download_token;

$logFile = __DIR__ . '/otp_log.txt';
$timestamp = date('Y-m-d H:i:s');

// ==========================================
// Delivery Channels
// ==========================================

// 1. Send SMS with PDF download link (Simple SMS Gateway)
$sms_sent = sendOrderSms($customer_phone, $customer_name, $order_number, $download_url);
$sms_status = $sms_sent ? 'sent_gateway' : 'failed_gateway';


// 2. Send Email with PDF attached (PHPMailer)
if (!empty($customer_email)) {
    $user_data = [
        'email' => $customer_email,
        'full_name' => $customer_name,
        'phone' => $customer_phone
    ];
    sendBillEmail($user_data, $order_data, $pdf_relative_path);
}

// 3. Send WhatsApp with PDF attached
sendWhatsappBill($customer_phone, $order_data, $pdf_relative_path);

// Save order to local JSON file for success page rendering
try {
    $orders_json_file = __DIR__ . '/orders.json';
    $orders_list = [];
    if (file_exists($orders_json_file)) {
        $orders_list = json_decode(file_get_contents($orders_json_file), true) ?: [];
    }

    $orders_list[$order_number] = [
        'order_id' => $order_number,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'customer_email' => $customer_email,
        'delivery_address' => $delivery_address,
        'message' => trim($data['message'] ?? ''),
        'payment_id' => $payment_id,
        'cart_items' => $cart_items,
        'subtotal' => $subtotal,
        'gst' => $tax_amount,
        'packing' => $packing_fee,
        'delivery' => $delivery_charge,
        'total' => $total,
        'status' => ($payment_method_key === 'cod' ? 'Pending' : 'Paid'),
        'sms_status' => ($sms_status === 'sent_gateway') ? 'success' : 'failed',
        'sms_response' => '',
        'created_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents($orders_json_file, json_encode($orders_list, JSON_PRETTY_PRINT));
} catch (Exception $json_ex) {
    error_log("Failed to write to orders.json: " . $json_ex->getMessage());
}

echo json_encode([
    'success'        => true,
    'order_id'       => $order_number,
    'sms_status'     => $sms_status,
    'tracking_token' => $tracking_token ?? null,
    'redirect_url'   => 'order-success.php?order_id=' . $order_number,
]);
exit;
