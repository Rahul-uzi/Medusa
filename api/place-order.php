<?php
require_once __DIR__ . '/config.php';
require_same_origin_unsafe_request();
rate_limit('place_order', 8, 300);



header('Content-Type: application/json');

// Read JSON input
$input = (php_sapi_name() === 'cli') ? file_get_contents('php://stdin') : request_raw_body();
$data = json_decode($input, true);

if (!$data) {
    json_response([
        'success' => false,
        'message' => 'Invalid request data.'
    ], 400);
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
$message = trim($data['message'] ?? '');
$payment_method_key = trim(strtolower($data['payment_method'] ?? 'online'));
$payment_method = ucfirst($payment_method_key);
$payment_id = trim($data['razorpay_payment_id'] ?? '');

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

    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        json_response(['success' => false, 'message' => 'Please log in to use Membership Pass.'], 401);
    }

    $stmt = $pdo->prepare("SELECT id FROM membership_cards WHERE user_id = ? AND card_number = ? AND cvv = ?");
    $stmt->execute([$user_id, $card_number, $cvv]);
    if (!$stmt->fetch()) {
        json_response(['success' => false, 'message' => 'Invalid Membership Pass Details.'], 403);
    }

    $payment_id = 'MEMBERSHIP_' . substr($card_number, -4);
} elseif ($payment_method_key === 'cod') {
    $payment_id = 'COD';
}

$cart_items = $data['cart_items'] ?? [];
$coupon_code = trim($data['coupon_code'] ?? '');

$save_address = !empty($data['save_address']);
$saved_address_id = intval($data['saved_address_id'] ?? 0);
$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$country = trim($data['country'] ?? '');
$street = trim($data['street'] ?? '');
$apartment = trim($data['apartment'] ?? '');
$city = trim($data['city'] ?? '');
$state = trim($data['state'] ?? '');
$zip = trim($data['zip'] ?? '');

// Build subtotal from server-side menu records. Browser prices are never trusted.
$subtotal = 0;
$normalized_cart_items = [];
$lookup_by_id = $pdo->prepare("SELECT id, name, price, category, subcategory FROM food_items WHERE id = ? AND is_available = 1");
$lookup_by_name = $pdo->prepare("SELECT id, name, price, category, subcategory FROM food_items WHERE name = ? AND is_available = 1 LIMIT 1");

foreach ($cart_items as $item) {
    if (!is_array($item)) {
        continue;
    }

    $food_item_id = intval($item['food_item_id'] ?? $item['id'] ?? 0);
    $item_qty = max(1, min(99, intval($item['quantity'] ?? 1)));

    if ($food_item_id > 0) {
        $lookup_by_id->execute([$food_item_id]);
        $menu_item = $lookup_by_id->fetch(PDO::FETCH_ASSOC);
    } else {
        $lookup_name = trim($item['name'] ?? '');
        if ($lookup_name === '') {
            continue;
        }
        $lookup_by_name->execute([$lookup_name]);
        $menu_item = $lookup_by_name->fetch(PDO::FETCH_ASSOC);
    }

    if (!$menu_item) {
        json_response([
            'success' => false,
            'message' => 'One or more cart items are invalid or unavailable. Please refresh your cart.'
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
        'message' => 'Your cart is empty or contains no valid items.'
    ], 400);
}

$cart_items = $normalized_cart_items;
$items_lines = [];
foreach ($cart_items as $item) {
    $items_lines[] = "- " . $item['name'] . " (x" . intval($item['quantity']) . "): Rs. " . number_format(floatval($item['subtotal']), 2);
}

// Load Settings
$settings_file = dirname(__DIR__) . '/admintest/settings.json';
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
$order_type_raw = trim($data['order_type'] ?? 'home');
$db_order_type = ($order_type_raw === 'pickup' || $order_type_raw === 'takeaway') ? 'takeaway' : 'delivery';
$restaurant_name = $settings['restaurant_name'];

$gst = $subtotal * ($gst_rate / 100);
$delivery = ($db_order_type === 'takeaway') ? 0.00 : floatval(get_env_var('DELIVERY_CHARGE', '40.00'));
$packing = (strpos(strtolower($delivery_address), 'table') !== false) ? 0.00 : $packing_charge;
$total = $subtotal + $gst + $delivery + $packing;

// Coupon Validation & Application
$coupon_discount = 0;
$coupon_valid = false;
$coupon_entity = null;
if (!empty($coupon_code)) {
    require_once __DIR__ . '/CouponService.php';
    try {
        $couponService = new CouponService($pdo);
        $coupon_entity = $couponService->validateCoupon($coupon_code);
        // Calculate coupon discount (percentage of subtotal)
        $coupon_discount = $subtotal * ($coupon_entity->discount_value / 100);
        $coupon_valid = true;
        $total = max(0, $total - $coupon_discount);
    } catch (Exception $e) {
        error_log('Coupon validation failed: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Coupon validation failed. Please try another coupon or continue without it.'
        ]);
        exit;
    }
}

$order_id = 'ORD-' . strtoupper(substr(uniqid(), 7, 5));

// Resolve host dynamically (use local machine IP if localhost so it's clickable on mobile over Wi-Fi)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($host === 'localhost' || $host === '127.0.0.1') {
    $local_ip = gethostbyname(gethostname());
    if ($local_ip && $local_ip !== '127.0.0.1') {
        $host = $local_ip;
    }
}
$invoice_url = $protocol . $host . "/test/order-success.php?order_id=" . $order_id;

// Construct a detailed, professional business bill SMS layout
$items_block = implode("\n", $items_lines);
$sms_message = "=============================\n"
             . "      " . strtoupper($restaurant_name) . " BILL\n"
             . "=============================\n"
             . "Order ID: {$order_id}\n"
             . "Date: " . date('d-M-Y H:i') . "\n"
             . "Customer: {$customer_name}\n"
             . "-----------------------------\n"
             . "ITEMS:\n"
             . "{$items_block}\n"
             . "-----------------------------\n"
             . "Subtotal: Rs. " . number_format($subtotal, 2) . "\n"
             . "GST ({$gst_rate}%): Rs. " . number_format($gst, 2) . "\n"
             . ($packing > 0 ? "Packing: Rs. " . number_format($packing, 2) . "\n" : "")
             . "Delivery: Rs. " . number_format($delivery, 2) . "\n"
             . "-----------------------------\n"
             . "GRAND TOTAL: Rs. " . number_format($total, 2) . "\n"
             . "-----------------------------\n"
             . "Payment ID: {$payment_id}\n\n"
             . "Download PDF Bill:\n"
             . "{$invoice_url}&print=1\n"
             . "=============================\n"
             . "Thank you for choosing us!";

// SMS Routing logic based on SMS_PROVIDER setting
$sms_provider = get_env_var('SMS_PROVIDER', 'none');
$sms_status = 'not_sent';
$sms_response = '';

if ($sms_provider === 'twilio') {
    $twilio_sid = get_env_var('TWILIO_ACCOUNT_SID');
    $twilio_token = get_env_var('TWILIO_AUTH_TOKEN');
    $twilio_from = get_env_var('TWILIO_FROM_NUMBER');

    if ($twilio_sid && $twilio_token && $twilio_from && $customer_phone) {
        if (strpos($twilio_sid, 'ACxxxxxxxx') === 0 || empty($twilio_sid)) {
            $sms_status = 'api_error';
            $sms_response = json_encode([
                'message' => 'Twilio credentials not configured. Please edit your .env file with your Twilio Account SID, Auth Token, and From Number.'
            ]);
        } else {
            // Format to E.164 format
            $to_phone = trim($customer_phone);
            if (strlen($to_phone) === 10 && is_numeric($to_phone)) {
                $to_phone = '+91' . $to_phone;
            } elseif (strpos($to_phone, '+') !== 0 && is_numeric($to_phone)) {
                $to_phone = '+' . $to_phone;
            }

            $url = "https://api.twilio.com/2010-04-01/Accounts/" . $twilio_sid . "/Messages.json";
            $payload = [
                'From' => $twilio_from,
                'To'   => $to_phone,
                'Body' => $sms_message
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_USERPWD, $twilio_sid . ':' . $twilio_token);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $response = curl_exec($ch);
            $err = curl_error($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($err) {
                $sms_status = 'error';
                $sms_response = json_encode(['message' => 'cURL Error: ' . $err]);
            } else {
                $res_arr = json_decode($response, true);
                if ($status_code >= 200 && $status_code < 300) {
                    $sms_status = 'success';
                } else {
                    $sms_status = 'api_error';
                    $err_msg = $res_arr['message'] ?? 'Twilio API Error';
                    $sms_response = json_encode(['message' => $err_msg]);
                }
            }
        }
    } else {
        $sms_status = 'api_error';
        $sms_response = json_encode([
            'message' => 'Twilio configuration parameters missing in .env file.'
        ]);
    }
} elseif ($sms_provider === 'android') {
    $gateway_url = get_env_var('ANDROID_GATEWAY_URL');

    if ($gateway_url && $customer_phone) {
        // Format phone to clean numeric string for Simple SMS Gateway
        $to_phone = trim($customer_phone);
        // Strip out country code or special chars if present to leave clean 10-digit number
        if (strpos($to_phone, '+91') === 0) {
            $to_phone = substr($to_phone, 3);
        } elseif (strpos($to_phone, '91') === 0 && strlen($to_phone) === 12) {
            $to_phone = substr($to_phone, 2);
        }
        $to_phone = preg_replace('/[^0-9]/', '', $to_phone);

        // Setup payload based on "Simple SMS Gateway" format: {"phone": "1234567890", "message": "Hello"}
        $payload = [
            'phone' => $to_phone,
            'message' => $sms_message
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gateway_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);

        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $sms_status = 'error';
            $sms_response = json_encode(['message' => 'cURL Error: ' . $err]);
        } else {
            if ($status_code >= 200 && $status_code < 300) {
                $sms_status = 'success';
            } else {
                $sms_status = 'api_error';
                $sms_response = $response ?: 'HTTP ' . $status_code;
            }
        }
    } else {
        $sms_status = 'api_error';
        $sms_response = json_encode(['message' => 'Android SMS Gateway configuration parameters missing in .env file.']);
    }
}

// Save order to a local JSON file (orders.json)
$orders_file = dirname(__DIR__) . '/orders.json';
$orders = [];
if (file_exists($orders_file)) {
    $orders = json_decode(file_get_contents($orders_file), true) ?: [];
}

$new_order = [
    'order_id' => $order_id,
    'customer_name' => $customer_name,
    'customer_phone' => $customer_phone,
    'customer_email' => $customer_email,
    'delivery_address' => $delivery_address,
    'message' => $message,
    'payment_id' => $payment_id,
    'cart_items' => $cart_items,
    'subtotal' => $subtotal,
    'gst' => $gst,
    'delivery' => $delivery,
    'packing' => $packing,
    'total' => $total,
    'status' => ($payment_method_key === 'cod' ? 'Pending' : 'Paid'),
    'sms_status' => $sms_status,
    'sms_response' => $sms_response,
    'created_at' => date('Y-m-d H:i:s')
];

if ($coupon_valid && $coupon_discount > 0) {
    $new_order['coupon_code'] = $coupon_code;
    $new_order['coupon_discount'] = $coupon_discount;
}

$orders[$order_id] = $new_order;
file_put_contents($orders_file, json_encode($orders, JSON_PRETTY_PRINT));

// Save order to MySQL database directly
if (isset($pdo)) {
    try {
        $pdo->beginTransaction();

        $db_user_id = $_SESSION['user_id'] ?? null;
        $db_status = 'pending'; // Default status for new orders
        
        $ins_order = $pdo->prepare("INSERT INTO orders (order_number, customer_name, customer_phone, delivery_address, total_amount, order_status, user_id, order_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $ins_order->execute([
            $order_id,
            $customer_name,
            $customer_phone,
            $delivery_address,
            $total,
            $db_status,
            $db_user_id,
            $db_order_type
        ]);
        
        $db_order_id = $pdo->lastInsertId();

        // Generate a unique 64-char hex tracking token
        $tracking_token = bin2hex(random_bytes(32));
        $upd_token = $pdo->prepare("UPDATE orders SET tracking_token = ?, tracking_status = 'placed' WHERE id = ?");
        $upd_token->execute([$tracking_token, $db_order_id]);

        // Store in session so customer can track on all pages
        $_SESSION['active_order_token'] = $tracking_token;
        $_SESSION['active_order_id']    = $db_order_id;
        $_SESSION['order_placed_at']    = time();
        
        $pegs_to_add = 0;
        foreach ($cart_items as $item) {
            $food_item_id = intval($item['food_item_id'] ?? 0);
            $category = trim($item['category'] ?? '');
            $subcategory = trim($item['subcategory'] ?? '');

            $is_alcoholic = false;
            if ($category && strtolower($category) === 'beverages') {
                $non_alcoholic_subs = ['detox', 'signature mocktails', 'coffee (hot)', 'coffee (cold)', 'shakes & smoothies', 'iced teas', 'green tea', 'quenchers'];
                if (!in_array(strtolower($subcategory), $non_alcoholic_subs)) {
                    $is_alcoholic = true;
                }
            } elseif ($category && strtolower($category) === 'liquor') {
                $is_alcoholic = true;
            }

            if ($is_alcoholic) {
                $pegs_to_add += 8 * intval($item['quantity'] ?? 1);
            }
            
            $ins_item = $pdo->prepare("INSERT INTO order_items (order_id, food_item_id, item_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $ins_item->execute([
                $db_order_id,
                $food_item_id > 0 ? $food_item_id : null,
                $item['name'],
                intval($item['quantity'] ?? 1),
                floatval($item['price'] ?? 0.00)
            ]);
        }

        if ($db_user_id && $pegs_to_add > 0) {
            $upd_quota = $pdo->prepare("UPDATE users SET liquor_quota_pegs = liquor_quota_pegs + ? WHERE id = ?");
            $upd_quota->execute([$pegs_to_add, $db_user_id]);
        }
        
        // 3. Save or update customer address if requested
        if ($db_user_id !== null && $save_address && !empty($street) && !empty($city) && !empty($state)) {
            $existing_id = null;
            
            if ($saved_address_id > 0) {
                // Verify the selected address belongs to the logged-in user
                $check_owner = $pdo->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
                $check_owner->execute([$saved_address_id, $db_user_id]);
                if ($check_owner->fetch()) {
                    $existing_id = $saved_address_id;
                }
            }
            
            if (!$existing_id) {
                // If not updating by ID, check if there is an address matching the street, city, state, zip for this user
                $check_match = $pdo->prepare("SELECT id FROM user_addresses WHERE user_id = ? AND street = ? AND city = ? AND state = ? AND zip = ?");
                $check_match->execute([$db_user_id, $street, $city, $state, $zip]);
                $matched_addr = $check_match->fetch();
                if ($matched_addr) {
                    $existing_id = $matched_addr['id'];
                }
            }
            
            if ($existing_id) {
                // Update the existing address
                $upd_stmt = $pdo->prepare("
                    UPDATE user_addresses 
                    SET first_name = ?, last_name = ?, phone = ?, email = ?, country = ?, street = ?, apartment = ?, city = ?, state = ?, zip = ?, is_default = 1
                    WHERE id = ?
                ");
                $upd_stmt->execute([
                    $first_name, $last_name, $customer_phone, $customer_email, $country, $street, $apartment, $city, $state, $zip, $existing_id
                ]);
                $address_id = $existing_id;
            } else {
                // Insert as a new address
                $ins_stmt = $pdo->prepare("
                    INSERT INTO user_addresses (user_id, first_name, last_name, phone, email, country, street, apartment, city, state, zip, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $ins_stmt->execute([
                    $db_user_id, $first_name, $last_name, $customer_phone, $customer_email, $country, $street, $apartment, $city, $state, $zip
                ]);
                $address_id = $pdo->lastInsertId();
            }
            
            // Clear default status of other addresses for this user
            if ($address_id) {
                $clear_defaults = $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ? AND id != ?");
                $clear_defaults->execute([$db_user_id, $address_id]);
            }
        } else {
            // Save guest info
            $ins_guest = $pdo->prepare("
                INSERT INTO guest_info (order_id, first_name, last_name, phone, email, country, street, apartment, city, state, zip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins_guest->execute([
                $db_order_id, $first_name, $last_name, $customer_phone, $customer_email, $country, $street, $apartment, $city, $state, $zip
            ]);
        }

        // If coupon is valid, redeem it inside the transaction!
        if ($coupon_valid && $coupon_entity) {
            $couponService->redeemCoupon($coupon_code, $db_order_id);
        }

        // Clear database cart for this user upon successful order placement
        if ($db_user_id) {
            $clear_cart = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $clear_cart->execute([$db_user_id]);
        } else {
            $session_id = session_id();
            $clear_cart = $pdo->prepare("DELETE FROM cart WHERE session_id = ? AND user_id IS NULL");
            $clear_cart->execute([$session_id]);
        }

        // Trigger notification triggers for admin panel
        require_once dirname(__DIR__) . '/includes/notifications_helper.php';
        
        // 1. Order notification
        $order_notif_body = "Order {$order_id} has been placed by {$customer_name} via {$payment_method}. Total amount: ₹" . number_format($total, 2);
        addNotification('order', 'New Order Received', $order_notif_body);

        // 2. Payment notification
        if ($payment_method_key === 'cod') {
            $payment_notif_body = "Order {$order_id} is placed with Cash on Delivery. Payment will be collected on delivery.";
            addNotification('payment', 'Payment Pending', $payment_notif_body);
        } else {
            $payment_notif_body = "Payment of ₹" . number_format($total, 2) . " processed successfully for order {$order_id}.";
            addNotification('payment', 'Payment Successful', $payment_notif_body);
        }

        // 3. Kitchen notification (special requests)
        $special_req = trim($message ?? '');
        if (!empty($special_req)) {
            $kitchen_notif_body = "Special request added for order {$order_id}: \"{$special_req}\"";
            addNotification('kitchen', 'Special Request Added', $kitchen_notif_body);
        }

        $pdo->commit();
    } catch(Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Log or handle error gracefully so checkout doesn't crash
        error_log("Order save transaction failed: " . $e->getMessage());
    }
}

echo json_encode([
    'success'        => true,
    'order_id'       => $order_id,
    'sms_status'     => $sms_status,
    'sms_response'   => $sms_response,
    'tracking_token' => $tracking_token ?? null,
    'redirect_url'   => ($tracking_token ?? null) ? 'order_confirmed.php' : ('order-success.php?order_id=' . $order_id),
]);
exit;
