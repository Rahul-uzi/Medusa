<?php
require_once __DIR__ . '/api/config.php';

$user_details = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => ''
];

$user_points_balance = 0;
$user_tier_discount_percent = 0.00;
$user_tier_name = 'Silver';

if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.email, u.phone, u.current_tier_id, t.tier_name, t.discount_percent 
            FROM users u
            LEFT JOIN customer_tiers t ON u.current_tier_id = t.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $name_parts = explode(' ', trim($user['full_name']), 2);
            $user_details['first_name'] = $name_parts[0] ?? '';
            $user_details['last_name'] = $name_parts[1] ?? '';
            $user_details['email'] = $user['email'];
            $user_details['phone'] = $user['phone'] ?? '';
            $user_tier_name = $user['tier_name'] ?? 'Bronze';
            $user_tier_discount_percent = floatval($user['discount_percent'] ?? 10.00);
        }
        
        // Fetch points balance
        $pts_stmt = $pdo->prepare("SELECT current_balance FROM reward_points WHERE user_id = ?");
        $pts_stmt->execute([$_SESSION['user_id']]);
        $user_points_balance = intval($pts_stmt->fetchColumn() ?: 0);

        $addr_stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
        $addr_stmt->execute([$_SESSION['user_id']]);
        $saved_addresses = $addr_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch notifications
        $notif_stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $notif_stmt->execute([$_SESSION['user_id']]);
        $user_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

        $unread_notifications_count = 0;
        foreach ($user_notifications as $n) {
            if (empty($n['is_read'])) $unread_notifications_count++;
        }
    } catch (PDOException $e) {
        // ignore
    }
} else {
    $saved_addresses = [];
    $user_notifications = [];
    $unread_notifications_count = 0;
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
// Override with .env configurations
$settings['restaurant_name'] = get_env_var('RESTAURANT_NAME', $settings['restaurant_name']);
$settings['gst_rate'] = intval(get_env_var('GST_RATE', $settings['gst_rate']));
$settings['opening_hours'] = get_env_var('OPENING_HOURS', $settings['opening_hours']);

$gst_rate = isset($settings['gst_rate']) ? intval($settings['gst_rate']) : 18;
$packing_charge = isset($settings['packing_charge']) ? floatval($settings['packing_charge']) : 0.00;



// require_once 'api/config.php';
// if (empty($_SESSION['user_id'])) {
//     header('Location: login.html');
//     exit;
// }
// $csrf_token = generateCSRFToken();
$csrf_token = csrf_token();
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
    <title>Checkout - Medusa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <!-- Razorpay Checkout library -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        :root {
            --rosewood: #5A1827;
            --rosewood-light: #7E2638;
            --gold: #dfba86;
            --gold-hover: #f3dfc1;
        }

        body {
            background-color: var(--bg-dark) !important;
            color: var(--white) !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
        }
        
        /* Hero Banner */
        .checkout-hero {
            position: relative;
            background-image: linear-gradient(135deg, rgba(19, 47, 32, 0.75) 0%, rgba(90, 24, 39, 0.75) 100%), url('assets/images/checkout_hero.png');
            background-size: cover;
            background-position: center;
            padding: 8rem 0 6rem 0;
            text-align: center;
            margin-bottom: 4rem;
            border-bottom: 1px solid rgba(223, 186, 134, 0.15);
        }

        .checkout-hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
        }

        .checkout-hero p {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 600;
            margin: 0;
        }

        /* Billing Details & Sidebar Card styling */
        .checkout-section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 1.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.75rem;
        }

        .checkout-form-card {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .form-label-checkout {
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-label-checkout .required-asterisk {
            color: var(--gold);
            margin-left: 3px;
            font-weight: bold;
        }

        .form-control-checkout {
            background-color: rgba(255, 255, 255, 0.02) !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            padding: 0.8rem 1.1rem !important;
            font-size: 0.95rem !important;
            width: 100%;
            transition: var(--transition);
        }

        .form-control-checkout::placeholder {
            color: rgba(255, 255, 255, 0.25) !important;
        }

        .form-control-checkout:focus {
            background-color: rgba(255, 255, 255, 0.04) !important;
            border-color: var(--gold) !important;
            box-shadow: 0 0 0 0.25rem rgba(223, 186, 134, 0.15) !important;
            outline: none;
        }

        select.form-control-checkout {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23dfba86' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1.1rem center;
            background-size: 12px;
            padding-right: 2.5rem !important;
        }

        select.form-control-checkout option {
            background-color: var(--bg-dark);
            color: #ffffff;
        }

        /* Order Summary Table styling */
        .order-summary-box {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 2.2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            margin-bottom: 1.8rem;
        }

        .checkout-table {
            width: 100%;
            margin-bottom: 0;
            color: #ffffff;
        }

        .checkout-table th {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 0.8rem 0;
            font-weight: 600;
        }

        .checkout-table td {
            padding: 1.2rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.95rem;
        }

        .checkout-table td.product-name {
            color: var(--white);
            font-weight: 500;
        }

        .checkout-table td.product-total {
            text-align: right;
            font-weight: 600;
            color: var(--gold);
        }

        .checkout-table th.header-total {
            text-align: right;
        }

        .summary-totals-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.95rem;
        }

        .summary-totals-row span:first-child {
            color: var(--gray);
            font-weight: 500;
        }

        .summary-totals-row span:last-child {
            color: #ffffff;
            font-weight: 600;
        }

        .summary-totals-row.grand-total {
            border-bottom: none;
            padding-top: 1.2rem;
            font-size: 1.15rem;
        }

        .summary-totals-row.grand-total span:first-child {
            color: #ffffff;
            font-weight: 700;
        }

        .summary-totals-row.grand-total span:last-child {
            color: var(--gold);
            font-weight: 700;
            font-size: 1.35rem;
        }

        /* Coupon Box Toggle */
        .coupon-toggle-box {
            border: 1px dashed rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.01);
            border-radius: 8px;
            padding: 1.1rem;
            margin-bottom: 1.8rem;
            text-align: center;
            font-size: 0.92rem;
            transition: var(--transition);
        }

        .coupon-toggle-box:hover {
            border-color: var(--gold);
            background: rgba(223, 186, 134, 0.02);
        }

        .coupon-link {
            color: var(--gold);
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
            transition: var(--transition);
        }

        .coupon-link:hover {
            color: var(--gold-light);
        }

        .coupon-input-group {
            display: flex;
            gap: 8px;
            margin-top: 0.8rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            opacity: 0;
        }

        .coupon-input-group.active {
            max-height: 80px;
            opacity: 1;
        }

        /* Payment/Submit Box styling */
        .payment-box {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 2.2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .payment-method-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Playfair Display', serif;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.5rem;
        }

        .payment-method-title i {
            color: var(--gold);
        }

        /* Payment options list */
        .payment-options-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 1.5rem;
        }

        .payment-option-item {
            background: rgba(255, 255, 255, 0.01);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
        }

        .payment-option-item:hover {
            background: rgba(223, 186, 134, 0.02);
            border-color: rgba(223, 186, 134, 0.2);
        }

        .payment-option-item.active {
            background: rgba(223, 186, 134, 0.04);
            border-color: var(--gold);
        }

        .payment-option-radio {
            appearance: none;
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            outline: none;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .payment-option-radio:checked {
            border-color: var(--gold);
        }

        .payment-option-radio:checked::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: var(--gold);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .payment-option-content {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payment-option-text {
            font-weight: 600;
            color: #ffffff;
            font-size: 0.95rem;
        }

        .payment-option-icons {
            display: flex;
            gap: 8px;
            color: var(--gold);
            font-size: 1.1rem;
            align-items: center;
        }

        .payment-option-icons i {
            transition: var(--transition);
        }

        .payment-details-panel {
            background: rgba(0, 0, 0, 0.25);
            border-radius: 8px;
            padding: 1.1rem;
            font-size: 0.88rem;
            line-height: 1.5;
            color: var(--gray);
            margin-bottom: 1.5rem;
            border-left: 3px solid var(--gold);
            display: none;
        }

        .payment-details-panel.active {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }

        .privacy-policy-text {
            font-size: 0.82rem;
            line-height: 1.5;
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        /* Buttons & Center Links */
        .btn-place-order {
            background-color: var(--rosewood);
            color: var(--gold);
            border: 1px solid var(--gold);
            border-radius: 8px;
            padding: 1.1rem;
            font-weight: 700;
            font-size: 1.05rem;
            width: 100%;
            transition: var(--transition);
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-place-order:hover {
            background-color: var(--rosewood-light);
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(90, 24, 39, 0.4);
        }

        .btn-place-order:active {
            transform: translateY(0);
        }

        .modify-order-container {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .modify-order-link {
            color: var(--gold);
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .modify-order-link:hover {
            color: var(--gold-light);
            text-decoration: underline;
        }

        /* Navbar customizations */
        .navbar {
            background: #0a0a0a !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Sticky sidebar on large screens */
        @media (min-width: 992px) {
            .sticky-sidebar {
                position: sticky;
                top: 2rem;
                z-index: 10;
            }
        }

        /* Footer Styling */
        .main-footer {
            background-color: #080c0a; /* Dark Medusa theme */
            color: rgba(255,255,255,0.6);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            position: relative;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .footer-title {
            color: var(--gold);
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            letter-spacing: 0.5px;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }

        .footer-links a i {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.4);
            transition: all 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--gold);
            transform: translateX(3px);
        }

        .footer-links a:hover i {
            color: var(--gold);
        }

        .footer-icon {
            color: var(--gold);
            font-size: 1.1rem;
            width: 20px;
        }

        .footer-contact-info span {
            color: rgba(255, 255, 255, 0.6);
        }

        .footer-social-icons a {
            color: var(--gold);
            font-size: 1.25rem;
            transition: all 0.2s ease;
        }

        .footer-social-icons a:hover {
            color: var(--gold);
            transform: translateY(-3px);
        }

        .footer-divider {
            border: none;
            border-top: 1px dotted rgba(255, 255, 255, 0.2);
            border-color: rgba(255,255,255,0.05);
            margin: 2.5rem 0 1.5rem 0;
            opacity: 1;
        }

        .footer-bottom-text {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.4) !important;
        }

        .hover-orange:hover {
            color: var(--gold) !important;
            text-decoration: none;
        }

        /* Scroll to Top Button */
        .scroll-to-top-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 45px;
            height: 45px;
            background-color: var(--rosewood);
            color: var(--gold);
            border: 1px solid var(--gold);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(90, 24, 39, 0.35);
            z-index: 1000;
        }

        .scroll-to-top-btn.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top-btn:hover {
            background-color: var(--rosewood-light);
            color: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(90, 24, 39, 0.45);
        }

        /* Premium Payment Loader Overlay */
        .payment-loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at center, rgba(10, 10, 10, 0.98) 0%, rgba(5, 5, 5, 0.99) 100%);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            z-index: 99999;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s cubic-bezier(0.25, 1, 0.5, 1);
        }

        .payment-loader-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .loader-content {
            text-align: center;
            max-width: 450px;
            width: 90%;
            padding: 0;
            background: transparent;
            border: none;
            box-shadow: none;
            transform: scale(0.95);
            transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }

        .payment-loader-overlay.active .loader-content {
            transform: scale(1);
        }

        .food-animation-container {
            position: relative;
            width: 280px;
            height: 280px;
            margin: 0 auto;
            border-radius: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .food-loader-gif {
            width: 100%;
            height: 100%;
            border-radius: 24px;
            object-fit: cover;
            box-shadow: none;
        }

        .loader-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.75rem;
            letter-spacing: 0.5px;
        }

        .loader-subtitle {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            color: #a09f9f;
            margin-bottom: 2.5rem;
            min-height: 1.5rem;
            font-weight: 500;
        }

        .loader-progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .loader-progress-fill {
            width: 5%;
            height: 100%;
            background: linear-gradient(90deg, #dfba86 0%, #e6c89f 50%, #dfba86 100%);
            background-size: 200% 100%;
            border-radius: 10px;
            transition: width 0.4s cubic-bezier(0.25, 1, 0.5, 1);
            animation: gradientMove 2s linear infinite;
            box-shadow: 0 0 12px rgba(223, 186, 134, 0.5);
        }

        /* Keyframes */
        @keyframes floatAnimation {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes gradientMove {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }
    
        /* Redesign Styles */
        body {
            background-color: #0b1110 !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .navbar-medusa-checkout {
            background-color: #0b1110;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .navbar-medusa-checkout .nav-link {
            color: #ffffff;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 1rem;
        }
        .navbar-medusa-checkout .nav-link:hover, .navbar-medusa-checkout .nav-link.active {
            color: var(--gold);
        }

        .checkout-page-title {
            text-align: center;
            margin: 3rem 0 1rem 0;
        }
        .checkout-page-title h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            color: var(--gold);
            margin-bottom: 0.5rem;
        }
        .checkout-page-title p {
            color: rgba(255,255,255,0.7);
            font-size: 0.95rem;
        }

        .checkout-steps {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem 0 4rem 0;
            gap: 15px;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #151e1b;
            border: 2px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .step-item.completed .step-circle {
            border-color: var(--gold);
            color: var(--gold);
        }
        .step-item.active .step-circle {
            background: #5A1827;
            border-color: #5A1827;
            color: #ffffff;
            box-shadow: 0 0 15px rgba(90, 24, 39, 0.5);
        }
        .step-label {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
        }
        .step-item.completed .step-label {
            color: var(--gold);
        }
        .step-item.active .step-label {
            color: #ffffff;
        }
        .step-connector {
            width: 80px;
            height: 2px;
            background: rgba(255,255,255,0.1);
            margin-bottom: 25px;
        }
        .step-connector.completed {
            background: var(--gold);
        }
        .step-connector.active {
            background: linear-gradient(90deg, var(--gold) 50%, rgba(255,255,255,0.1) 50%);
        }

        .checkout-layout {
            max-width: 1100px;
            margin: 0 auto;
            padding-bottom: 120px;
        }

        .medusa-card {
            background-color: #0f1714;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .medusa-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 1.5rem;
        }
        .medusa-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #5A1827;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .medusa-card-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: #ffffff;
            margin: 0;
            line-height: 1.2;
        }
        .medusa-card-subtitle {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            margin: 0;
        }

        .medusa-input-group {
            position: relative;
            margin-bottom: 1rem;
        }
        .medusa-input-group input, .medusa-input-group select {
            width: 100%;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 1.5rem 1rem 0.5rem 1rem;
            color: #ffffff;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .medusa-input-group select option {
            background-color: #0f1714;
            color: #ffffff;
        }
        .medusa-input-group input:focus, .medusa-input-group select:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 1px rgba(223, 186, 134, 0.3);
        }
        .medusa-input-group label {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
            pointer-events: none;
            transition: all 0.3s;
        }
        .medusa-input-group input:focus ~ label,
        .medusa-input-group input:not(:placeholder-shown) ~ label,
        .medusa-input-group select:focus ~ label,
        .medusa-input-group select:not([value=""]) ~ label {
            top: 0.8rem;
            font-size: 0.7rem;
            color: var(--gold);
        }
        
        .medusa-square-btn {
            width: 54px;
            height: 54px;
            border: 1px solid rgba(223, 186, 134, 0.3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            cursor: pointer;
            transition: all 0.3s;
            flex-shrink: 0;
            background: rgba(0,0,0,0.2);
        }
        .medusa-square-btn:hover {
            border-color: var(--gold);
            background: rgba(223, 186, 134, 0.1);
        }

        .selectable-boxes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media(max-width: 768px){
            .selectable-boxes { grid-template-columns: 1fr; }
        }
        .option-box {
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .option-box:hover {
            border-color: rgba(223, 186, 134, 0.5);
            background: rgba(255,255,255,0.02);
        }
        .option-box.active {
            border-color: var(--gold);
            background: rgba(223, 186, 134, 0.05);
        }
        .option-box-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 5px;
        }
        .option-radio {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            margin: 0;
            position: relative;
            cursor: pointer;
        }
        .option-box.active .option-radio {
            border-color: var(--gold);
        }
        .option-box.active .option-radio::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--gold);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .option-title {
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .option-desc {
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
            padding-left: 28px;
            margin-bottom: 5px;
        }
        .option-meta {
            padding-left: 28px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .text-red { color: #dc3545; }
        .text-green { color: #2ecc71; }
        
        .option-icon-right {
            position: absolute;
            right: 1.2rem;
            top: 1.2rem;
            color: rgba(255,255,255,0.2);
            font-size: 1.2rem;
        }

        .secure-note {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
            margin-top: 1.5rem;
        }
        .secure-note i {
            color: var(--gold);
        }

        .summary-card {
            background-color: #0b1a13;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: sticky;
            top: 2rem;
        }
        .summary-header {
            background-color: #3b0d19;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .summary-header-top {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .summary-header i {
            color: #b54256;
            font-size: 1.4rem;
            opacity: 0.9;
        }
        .summary-header h3 {
            color: #f3efe6;
            font-family: 'Playfair Display', serif;
            margin: 0;
            font-size: 1.4rem;
            font-weight: 500;
        }
        .summary-body {
            padding: 1.5rem;
        }
        .summary-order-id {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .summary-order-id span {
            color: #d8c8a7;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .summary-items-badge {
            border: 1px solid #d8c8a7;
            color: #d8c8a7 !important;
            font-size: 0.75rem !important;
            padding: 3px 12px;
            border-radius: 20px;
        }

        .summary-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed rgba(255,255,255,0.05);
        }
        .summary-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .summary-item-img {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .summary-item-img-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: rgba(223, 186, 134, 0.1);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border: 1px solid rgba(223, 186, 134, 0.2);
        }
        .summary-item-details {
            flex-grow: 1;
        }
        .summary-item-name {
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .summary-item-qty {
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
        }
        .summary-item-price {
            color: #ffffff;
            font-size: 0.95rem;
        }

        .summary-totals {
            margin-top: 1rem;
            padding-top: 1.5rem;
            border-top: 1px dashed rgba(255,255,255,0.1);
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.9rem;
        }
        .summary-row.grand-total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed rgba(255,255,255,0.1);
            font-size: 1.4rem;
            font-family: 'Playfair Display', serif;
        }
        .summary-row.grand-total .lbl {
            color: var(--gold);
        }
        .summary-row.grand-total .val {
            color: var(--gold);
            font-weight: 500;
        }
        .summary-row .lbl {
            color: rgba(255,255,255,0.6);
        }
        .summary-row .val {
            color: rgba(255,255,255,0.8);
            font-weight: 400;
        }
        
        .coupon-container {
            margin-top: 1.5rem;
            border: 1px dashed rgba(223, 186, 134, 0.4);
            border-radius: 8px;
            padding: 1.2rem;
            background-color: transparent;
        }
        .coupon-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--gold);
            font-size: 0.95rem;
            margin-bottom: 15px;
        }
        .coupon-input-wrapper {
            display: flex;
            gap: 10px;
        }
        .coupon-input-wrapper input {
            flex-grow: 1;
            background: rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
            color: #ffffff;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .coupon-input-wrapper button {
            background: #3b0d19;
            color: #ffffff;
            border: none;
            padding: 0 20px;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .coupon-input-wrapper button:hover {
            background: #7E2638;
        }

        .eta-callout {
            background: #0f1714;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .eta-icon {
            color: var(--gold);
            font-size: 2rem;
        }
        .eta-text {
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
        }
        .eta-text strong {
            display: block;
            color: var(--gold);
            font-size: 1.2rem;
            font-family: 'Playfair Display', serif;
            margin: 2px 0;
        }

        .sticky-action-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #0b1110;
            border-top: 1px solid rgba(255,255,255,0.05);
            padding: 1.5rem 0;
            z-index: 100;
            box-shadow: 0 -10px 30px rgba(0,0,0,0.5);
        }
        .sticky-action-container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 15px;
        }
        .btn-back-cart {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.2);
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-back-cart:hover {
            border-color: var(--gold);
            color: var(--gold);
        }
        .secure-badge {
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .btn-proceed {
            background: #5A1827;
            color: #ffffff;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-proceed:hover {
            background: #7E2638;
            transform: translateY(-2px);
        }

        /* Map Modal Styles */
        .map-modal {
            display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);
        }
        .map-modal-content {
            background-color: #0f1714; border: 1px solid var(--gold); border-radius: 12px;
            margin: 5% auto; padding: 20px; width: 90%; max-width: 600px;
            position: relative; box-shadow: 0 4px 30px rgba(0,0,0,0.5);
        }
        #leafletMap { height: 400px; width: 100%; border-radius: 8px; margin-bottom: 15px; }
        .map-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .map-modal-title { color: var(--gold); font-family: 'Playfair Display', serif; font-size: 1.5rem; margin: 0; }
        .map-modal-close { color: #fff; font-size: 28px; font-weight: bold; cursor: pointer; background: none; border: none; }
        .btn-confirm-map { background: var(--gold); color: #000; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; width: 100%; cursor: pointer; }

        /* Hide scrollbar but keep scroll functionality */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        
        /* Remove dropdown arrow from bell */
        #notifDropdown::after {
            display: none !important;
        }
        
        /* Bell Ringing Animation */
        @keyframes bell-ring {
            0% { transform: rotate(0); }
            10% { transform: rotate(20deg); }
            20% { transform: rotate(-15deg); }
            30% { transform: rotate(10deg); }
            40% { transform: rotate(-5deg); }
            50% { transform: rotate(0); }
            100% { transform: rotate(0); }
        }
        .bell-ringing {
            display: inline-block;
            transform-origin: top center;
            animation: bell-ring 1.5s infinite;
        }

        /* Hide Theme Toggle Button on Checkout */
        #themeToggleBtn {
            display: none !important;
        }
</style>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo get_env_var('GOOGLE_MAPS_API_KEY', ''); ?>&libraries=places&loading=async" defer></script>

    <!-- Navbar Performance Optimization Links -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
        <script>
        const originalWarn = console.warn;
        console.warn = function(...args) {
            if (args[0] && typeof args[0] === "string" && (
                args[0].includes("cdn.tailwindcss.com should not be used in production") ||
                args[0].includes("google.maps.Marker") ||
                args[0].includes("DirectionsService") ||
                args[0].includes("DirectionsRenderer") ||
                args[0].includes("deprecated")
            )) {
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

<!-- NAVBAR -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <script src="assets/js/navbar.js" defer></script>

    <form id="orderForm">
        <input type="hidden" id="name" value="">
        <input type="hidden" id="address" value="">
        <input type="hidden" id="delivery_coords" value="">
        <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="checkout-page-title">
            <h1>Checkout</h1>
            <p>Almost there! Please review your order<br>and complete the payment.</p>
        </div>

        <div class="checkout-steps">
            <div class="step-item completed">
                <div class="step-circle"><i class="fas fa-check"></i></div>
                <div class="step-label">Cart</div>
            </div>
            <div class="step-connector completed"></div>
            <div class="step-item active">
                <div class="step-circle">2</div>
                <div class="step-label">Checkout</div>
            </div>
            <div class="step-connector active"></div>
            <div class="step-item">
                <div class="step-circle">3</div>
                <div class="step-label">Payment</div>
            </div>
            <div class="step-connector"></div>
            <div class="step-item">
                <div class="step-circle">4</div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>

        <div class="container checkout-layout">
            <div class="row g-4">
                
                <div class="col-lg-7">
                    <div class="medusa-card">
                        <div class="medusa-card-header">
                            <div class="medusa-card-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h2 class="medusa-card-title">Delivery Details</h2>
                                <p class="medusa-card-subtitle">Where should we deliver your order?</p>
                            </div>
                        </div>

                        <?php if (!empty($_SESSION['user_id'])): ?>
                            <div class="medusa-input-group mb-4">
                                <select id="saved_address_id">
                                    <option value="">-- Use a new address --</option>
                                    <?php foreach ($saved_addresses as $addr): ?>
                                        <option value="<?php echo $addr['id']; ?>" 
                                                data-first-name="<?php echo htmlspecialchars($addr['first_name']); ?>"
                                                data-last-name="<?php echo htmlspecialchars($addr['last_name']); ?>"
                                                data-phone="<?php echo htmlspecialchars($addr['phone']); ?>"
                                                data-email="<?php echo htmlspecialchars($addr['email']); ?>"
                                                data-country="<?php echo htmlspecialchars($addr['country']); ?>"
                                                data-street="<?php echo htmlspecialchars($addr['street']); ?>"
                                                data-apartment="<?php echo htmlspecialchars($addr['apartment']); ?>"
                                                data-city="<?php echo htmlspecialchars($addr['city']); ?>"
                                                data-state="<?php echo htmlspecialchars($addr['state']); ?>"
                                                data-zip="<?php echo htmlspecialchars($addr['zip']); ?>"
                                                <?php echo $addr['is_default'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($addr['street'] . ', ' . $addr['city']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Saved Addresses</label>
                            </div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="medusa-input-group mb-0">
                                    <input type="text" id="billing_first_name" placeholder=" " value="<?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?>" required>
                                    <label>Full Name*</label>
                                    <input type="hidden" id="billing_last_name" value="">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="medusa-input-group mb-0">
                                    <input type="tel" id="billing_phone" placeholder=" " value="<?php echo htmlspecialchars($user_details['phone']); ?>" required>
                                    <label>Phone Number*</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="medusa-input-group mb-0">
                                    <input type="email" id="billing_email" placeholder=" " value="<?php echo htmlspecialchars($user_details['email']); ?>" required>
                                    <label>Email Address*</label>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-3 d-flex gap-2">
                                <div class="medusa-input-group mb-0 flex-grow-1">
                                    <input type="text" id="billing_street" placeholder=" " required>
                                    <label>Delivery Address*</label>
                                </div>
                                <div class="medusa-square-btn" id="btnChooseFromMap" title="Choose from Map">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="medusa-input-group mb-0">
                                    <input type="text" id="billing_apartment" placeholder=" ">
                                    <label>Apartment, suite, etc. (optional)</label>
                                </div>
                            </div>
                            <?php if (!empty($_SESSION['user_id'])): ?>
                            <div class="col-12 mt-2">
                                <div class="form-check" style="color: rgba(255,255,255,0.8); font-size: 0.9rem;">
                                    <input class="form-check-input" type="checkbox" id="save_address" value="1" style="background-color: transparent; border-color: rgba(255,255,255,0.2);">
                                    <label class="form-check-label" for="save_address">
                                        Save this address for future use
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="col-12 mt-3">
                                <div class="medusa-input-group mb-0">
                                    <input type="text" id="billing_message" placeholder=" ">
                                    <label>Order Instructions (optional)</label>
                                </div>
                            </div>
                            
                            <input type="hidden" id="billing_city" value="Kolkata">
                            <input type="hidden" id="billing_state" value="West Bengal">
                            <input type="hidden" id="billing_zip" value="700019">
                            <input type="hidden" id="billing_country" value="India">
                        </div>
                    </div>

                    <div class="medusa-card">
                        <div class="medusa-card-header">
                            <div class="medusa-card-icon" style="background: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                <i class="fas fa-motorcycle"></i>
                            </div>
                            <div>
                                <h2 class="medusa-card-title">Delivery Options</h2>
                                <p class="medusa-card-subtitle">Choose how you want your order</p>
                            </div>
                        </div>

                        <div class="selectable-boxes">
                            <label class="option-box active" onclick="setDeliveryMode('home', this)">
                                <div class="option-box-header">
                                    <input type="radio" name="delivery_option" value="home" class="option-radio" checked>
                                    <span class="option-title">Home Delivery</span>
                                </div>
                                <div class="option-desc">Delivered to your doorstep</div>
                                <div class="option-meta text-red">20-25 mins</div>
                            </label>
                            <label class="option-box" onclick="setDeliveryMode('pickup', this)">
                                <div class="option-box-header">
                                    <input type="radio" name="delivery_option" value="pickup" class="option-radio">
                                    <span class="option-title">Takeaway</span>
                                </div>
                                <div class="option-desc">Pick up your order from restaurant</div>
                            </label>
                        </div>
                    </div>

                    <div class="medusa-card mb-0">
                        <div class="medusa-card-header">
                            <div class="medusa-card-icon" style="background: #5A1827; color: #ffffff;">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div>
                                <h2 class="medusa-card-title">Payment Method</h2>
                                <p class="medusa-card-subtitle">Select a payment method</p>
                            </div>
                        </div>

                        <div class="selectable-boxes">
                            <label class="option-box active" onclick="setPaymentMode('online', this)">
                                <div class="option-box-header">
                                    <input type="radio" name="payment_method" value="online" class="option-radio" checked>
                                    <span class="option-title text-gold">Online Payment</span>
                                </div>
                                <div class="option-desc" style="padding-left: 0;">Pay securely online</div>
                            </label>
                            <label class="option-box" onclick="setPaymentMode('upi', this)">
                                <div class="option-box-header">
                                    <input type="radio" name="payment_method" value="upi" class="option-radio">
                                    <span class="option-title">UPI</span>
                                </div>
                                <div class="option-desc" style="padding-left: 0;">Google Pay, PhonePe</div>
                                <i class="fas fa-qrcode option-icon-right"></i>
                            </label>
                            <label class="option-box" onclick="setPaymentMode('card', this)">
                                <div class="option-box-header">
                                    <input type="radio" name="payment_method" value="card" class="option-radio">
                                    <span class="option-title">Card</span>
                                </div>
                                <div class="option-desc" style="padding-left: 0;">Visa, MasterCard, Rupay</div>
                                <i class="far fa-credit-card option-icon-right"></i>
                            </label>
                            <label class="option-box" onclick="setPaymentMode('cod', this)">
                                <div class="option-box-header">
                                    <input type="radio" name="payment_method" value="cod" class="option-radio">
                                    <span class="option-title">Cash on Delivery</span>
                                </div>
                                <div class="option-desc" style="padding-left: 0;">Pay upon delivery</div>
                                <i class="fas fa-wallet option-icon-right"></i>
                            </label>
                        </div>

                        <div class="secure-note">
                            <i class="fas fa-lock"></i> Your payment information is secure and encrypted.
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="summary-card">
                        <div class="summary-header">
                            <div class="summary-header-top">
                                <i class="fas fa-shopping-bag"></i>
                                <h3>Order Summary</h3>
                            </div>
                            <div class="summary-order-id">
                                <span>ORDER #<span id="order-id-display"></span></span>
                                <script>
                                    document.getElementById('order-id-display').textContent = 'P' + Math.floor(10000 + Math.random() * 90000);
                                </script>
                                <span class="summary-items-badge"><span id="item-count">0</span> Items</span>
                            </div>
                        </div>
                        <div class="summary-body">
                            <div id="order-items-list"></div>

                            <div class="summary-totals">
                                <div class="summary-row">
                                    <span class="lbl">Subtotal</span>
                                    <span class="val" id="checkout-subtotal">₹0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span class="lbl">GST (<span id="gst-rate-display"><?php echo $gst_rate; ?></span>%)</span>
                                    <span class="val" id="checkout-gst">₹0.00</span>
                                </div>
                                <div class="summary-row" id="packing-row">
                                    <span class="lbl">Packing Charges</span>
                                    <span class="val" id="checkout-packing">₹<?php echo number_format($packing_charge, 2); ?></span>
                                </div>
                                <div class="summary-row" id="delivery-row">
                                    <span class="lbl">Delivery Fee</span>
                                    <span class="val" id="checkout-delivery">₹40.00</span>
                                </div>
                                <div class="summary-row text-success" id="total-discount-row" style="display: none; align-items: center; position: relative;">
                                    <span class="lbl" style="display: flex; align-items: center; gap: 6px; position: relative;">
                                        Total Discount 
                                        <i class="fas fa-info-circle discount-info-icon" id="discountInfoBtn" style="cursor: pointer; color: var(--gold);"></i>
                                        <div id="discountPopover" style="display: none; position: absolute; top: 120%; left: 0; background: var(--bg-dark, #0a0a0a); border: 1px solid rgba(223, 186, 134, 0.3); padding: 12px; border-radius: 8px; z-index: 100; min-width: 200px; box-shadow: 0 8px 25px rgba(0,0,0,0.5);">
                                            <div id="bd-mrp-row" style="display: none; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px;"><span style="color: rgba(255,255,255,0.7);">Discount on MRP</span><span class="text-success" id="bd-mrp">-₹0.00</span></div>
                                            <div id="bd-tier-row" style="display: none; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px;"><span style="color: rgba(255,255,255,0.7);" id="bd-tier-label">Tier Discount</span><span class="text-success" id="bd-tier">-₹0.00</span></div>
                                            <div id="bd-coupon-row" style="display: none; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px;"><span style="color: rgba(255,255,255,0.7);">Coupon Discount</span><span class="text-success" id="bd-coupon">-₹0.00</span></div>
                                            <div id="bd-points-row" style="display: none; justify-content: space-between; font-size: 0.8rem;"><span style="color: rgba(255,255,255,0.7);">Reward Points</span><span class="text-success" id="bd-points">-₹0.00</span></div>
                                        </div>
                                    </span>
                                    <span class="val" id="checkout-total-discount">-₹0.00</span>
                                </div>
                                <div class="summary-row grand-total">
                                    <span class="lbl">Grand Total</span>
                                    <span class="val" id="checkout-total">₹0.00</span>
                                </div>
                            </div>

                            <div class="coupon-container">
                                <div class="coupon-title">
                                    <i class="fas fa-tag"></i> Have a coupon code?
                                </div>
                                <div id="couponMessage" style="display: none; font-size: 0.85rem; margin-bottom: 10px;"></div>
                                <div class="coupon-input-wrapper">
                                    <input type="text" id="couponCodeInput" placeholder="Enter coupon code">
                                    <button type="button" id="applyCouponBtn">Apply</button>
                                </div>
                            </div>

                            <!-- Points Container -->
                            <div class="points-container mt-3" style="border: 1px dashed rgba(255, 255, 255, 0.15); background: rgba(255, 255, 255, 0.01); border-radius: 8px; padding: 1.1rem; transition: all 0.3s;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <div class="coupon-title m-0" style="font-size: 1rem; color: var(--gold); display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-star"></i> Medusa Rewards
                                    </div>
                                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.6);">
                                        Tier: <strong class="text-white"><?php echo htmlspecialchars($user_tier_name); ?></strong>
                                    </div>
                                </div>
                                <p style="font-size: 0.85rem; color: rgba(255,255,255,0.7); margin-bottom: 12px;">
                                    Available Points: <strong class="text-white"><?php echo $user_points_balance; ?></strong> <span style="font-size: 0.75rem; opacity: 0.7;">(1 pt = ₹1)</span>
                                </p>
                                
                                <?php if ($user_points_balance > 0): ?>
                                <div class="form-check form-switch" style="display: flex; align-items: center; gap: 10px; padding-left: 0;">
                                    <input class="form-check-input" type="checkbox" id="redeem_loyalty_points" style="margin: 0; width: 40px; height: 20px; cursor: pointer; border-color: rgba(255,255,255,0.3); background-color: rgba(255,255,255,0.1);">
                                    <label class="form-check-label text-white" for="redeem_loyalty_points" style="font-size: 0.9rem; cursor: pointer; padding-top: 2px;">
                                        Redeem points on this order
                                    </label>
                                </div>
                                <?php else: ?>
                                <div style="font-size: 0.8rem; color: #ef4444;">
                                    <i class="fas fa-info-circle"></i> You don't have enough points to redeem yet.
                                </div>
                                <?php endif; ?>
                                
                                <div id="points-earned-tracker" style="display: none; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; color: rgba(255,255,255,0.6);">
                                    <i class="fas fa-gift text-success me-1"></i> You will earn <strong class="text-success"><span id="earned-points-value">0</span> points</strong> on this order!
                                </div>
                            </div>
                        </div>
                    </div>


                </div>

            </div>
        </div>

        <div class="sticky-action-bar">
            <div class="sticky-action-container">
                <a href="carttest.html" class="btn-back-cart">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </a>
                
                <div class="d-none d-md-flex secure-badge">
                    <i class="fas fa-lock"></i> Secure Checkout
                </div>

                <button type="submit" class="btn-proceed" id="submitOrderBtn">
                    Proceed to Payment <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </form>

    <div id="mapModal" class="map-modal">
        <div class="map-modal-content">
            <div class="map-modal-header">
                <h3 class="map-modal-title"><i class="fas fa-map-marked-alt"></i> Select Location</h3>
                <button id="closeMapModal" class="map-modal-close">&times;</button>
            </div>
            <p style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">Drag the marker to your exact delivery location.</p>
            <div id="leafletMap"></div>
            <button id="confirmLocationBtn" class="btn-confirm-map">Confirm Location</button>
        </div>
    </div>
    </div>

    <!-- Scroll to Top Button -->
    <button id="scrollToTop" class="scroll-to-top-btn">
        <i class="fas fa-chevron-up"></i>
    </button>

<script>
const GST_RATE = <?php echo $gst_rate; ?>;
const PACKING_CHARGE = <?php echo $packing_charge; ?>;
const DELIVERY_CHARGE = <?php echo floatval(get_env_var('DELIVERY_CHARGE', '40.00')); ?>;
const USER_POINTS_BALANCE = <?php echo $user_points_balance; ?>;
const USER_TIER_DISCOUNT_PERCENT = <?php echo $user_tier_discount_percent; ?>;
const USER_TIER_NAME = "<?php echo htmlspecialchars($user_tier_name); ?>";

// Phone number input restriction
const phoneInput = document.getElementById('billing_phone');
phoneInput.addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '');
});

// Dynamic state dropdown population based on country
const countrySelect = document.getElementById('billing_country');
const stateSelect = document.getElementById('billing_state');

const countryStates = {
    "India": ["Delhi", "Maharashtra", "Karnataka", "Tamil Nadu", "Uttar Pradesh", "Telangana"],
    "United States (US)": ["California", "New York", "Texas", "Florida", "Illinois", "Washington"],
    "United Kingdom (UK)": ["England", "Scotland", "Wales", "Northern Ireland"],
    "Canada": ["Ontario", "Quebec", "British Columbia", "Alberta"],
    "Australia": ["New South Wales", "Victoria", "Queensland", "Western Australia"]
};

function updateStates() {
    const selectedCountry = countrySelect.value;
    const states = countryStates[selectedCountry] || [];
    stateSelect.innerHTML = states.map(state => `<option value="${state}">${state}</option>`).join('');
}

countrySelect.addEventListener('change', updateStates);
updateStates(); // Initialize on load

// Saved Address Selector Logic
const savedAddressSelect = document.getElementById('saved_address_id');
if (savedAddressSelect) {
    function handleSavedAddressChange() {
        const selectedOption = savedAddressSelect.options[savedAddressSelect.selectedIndex];
        if (!selectedOption || selectedOption.value === "") {
            // Clear address details but keep the main user contact info if available
            document.getElementById('billing_street').value = "";
            document.getElementById('billing_apartment').value = "";
            document.getElementById('billing_city').value = "";
            document.getElementById('billing_zip').value = "";
            // Reset country to default India
            document.getElementById('billing_country').value = "India";
            updateStates();
            return;
        }
        
        // Auto-fill all fields from dataset
        document.getElementById('billing_first_name').value = selectedOption.dataset.firstName || "";
        document.getElementById('billing_last_name').value = selectedOption.dataset.lastName || "";
        document.getElementById('billing_phone').value = selectedOption.dataset.phone || "";
        document.getElementById('billing_email').value = selectedOption.dataset.email || "";
        
        const country = selectedOption.dataset.country || "India";
        document.getElementById('billing_country').value = country;
        updateStates(); // load states for this country
        
        let streetVal = selectedOption.dataset.street || "";
        const coordMatch = streetVal.match(/\[([0-9.-]+,\s*[0-9.-]+)\]/);
        if (coordMatch) {
            document.getElementById('delivery_coords').value = coordMatch[1];
            streetVal = streetVal.replace(/\[[0-9.-]+,\s*[0-9.-]+\]/, '').trim();
        } else {
            document.getElementById('delivery_coords').value = "";
        }
        document.getElementById('billing_street').value = streetVal;
        document.getElementById('billing_apartment').value = selectedOption.dataset.apartment || "";
        document.getElementById('billing_city').value = selectedOption.dataset.city || "";
        
        const state = selectedOption.dataset.state || "";
        document.getElementById('billing_state').value = state;
        
        document.getElementById('billing_zip').value = selectedOption.dataset.zip || "";
    }
    
    savedAddressSelect.addEventListener('change', handleSavedAddressChange);
    // Trigger on load to pre-populate with default address
    handleSavedAddressChange();
}

// Coupon state variables
let appliedCouponCode = '';
let appliedCouponDiscountPercent = 0;

// Coupon input toggle
const couponToggle = document.getElementById('couponToggle');
const couponInputGroup = document.getElementById('couponInputGroup');
if (couponToggle) {
    couponToggle.addEventListener('click', () => {
        if (couponInputGroup) couponInputGroup.classList.toggle('active');
    });
}

document.getElementById('applyCouponBtn').addEventListener('click', () => {
    const code = document.getElementById('couponCodeInput').value.trim();
    const messageBox = document.getElementById('couponMessage');
    
    if (!code) {
        messageBox.style.display = 'block';
        messageBox.style.color = '#ef4444'; // Red error color
        messageBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a coupon code.';
        return;
    }
    
    const applyBtn = document.getElementById('applyCouponBtn');
    applyBtn.disabled = true;
    applyBtn.textContent = 'Applying...';
    messageBox.style.display = 'none'; // Hide previous message

    fetch(`api/validate-coupon.php?code=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(data => {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
            
            messageBox.style.display = 'block';
            if (data.success) {
                appliedCouponCode = data.coupon_code;
                appliedCouponDiscountPercent = parseFloat(data.discount_value);
                
                messageBox.style.color = '#2ecc71'; // Green success color
                messageBox.innerHTML = `<i class="fas fa-check-circle"></i> Coupon "${data.coupon_code}" applied: ${data.discount_value}% OFF!`;
                
                loadCheckoutSummary(); // Refresh UI with discount
            } else {
                appliedCouponCode = '';
                appliedCouponDiscountPercent = 0;
                
                messageBox.style.color = '#ef4444'; // Red error color
                messageBox.innerHTML = `<i class="fas fa-exclamation-circle"></i> Invalid Coupon: ${data.message}`;
                
                loadCheckoutSummary(); // Refresh UI to remove discount
            }
        })
        .catch(err => {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
            
            messageBox.style.display = 'block';
            messageBox.style.color = '#ef4444';
            messageBox.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed to validate coupon due to network error.';
        });
});
function setPaymentMode(mode, element) {
    // Update active class on items
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        if (radio.value === mode) radio.checked = true;
    });
    
    document.querySelectorAll('label[onclick^="setPaymentMode"]').forEach(opt => opt.classList.remove('active'));
    element.classList.add('active');
    
    // Update submit button text
    const submitBtn = document.getElementById('submitOrderBtn');
    if (submitBtn) {
        if (mode === 'cod') {
            submitBtn.innerHTML = 'Place Order <i class="fas fa-arrow-right"></i>';
        } else {
            submitBtn.innerHTML = 'Proceed to Payment <i class="fas fa-arrow-right"></i>';
        }
    }
    
    // Auto-submit if COD is selected
    if (mode === 'cod') {
        const form = document.getElementById('orderForm');
        if (form) {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        }
    }
}

function setDeliveryMode(mode, element) {
    document.querySelectorAll('input[name="delivery_option"]').forEach(radio => {
        if (radio.value === mode) radio.checked = true;
    });
    
    document.querySelectorAll('label[onclick^="setDeliveryMode"]').forEach(opt => opt.classList.remove('active'));
    element.classList.add('active');
    
    // Optionally update summary
    loadCheckoutSummary();
}

// Remove old broken event listeners



// Load checkout summary totals
async function loadCheckoutSummary() {
    try {
        const response = await fetch('api/get-cart.php');
        const result = await response.json();
        if (result.success && result.items && result.items.length > 0) {
            // Compute subtotal from items — API doesn't return a separate total field
            const subtotal = result.items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
            const gst = subtotal * (GST_RATE / 100);
            const delivery = currentDeliveryMode === 'home' ? DELIVERY_CHARGE : 0;
            const packing = PACKING_CHARGE;
            const total = subtotal + gst + delivery + packing;

            renderOrderItems(result.items);
            updateSummaryUI(subtotal, delivery, gst, total, packing);
            return;
        }
    } catch (error) {
        console.warn('API get-cart failed, using local cart for total.', error);
    }

    // Fallback: Read from localStorage
    const savedCart = localStorage.getItem('foodie_cart');
    if (savedCart) {
        try {
            const items = JSON.parse(savedCart);
            if (items && items.length > 0) {
                const subtotal = items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
                const gst = subtotal * (GST_RATE / 100);
                const delivery = currentDeliveryMode === 'home' ? DELIVERY_CHARGE : 0;
                const packing = PACKING_CHARGE;
                const total = subtotal + gst + delivery + packing;
                
                renderOrderItems(items);
                updateSummaryUI(subtotal, delivery, gst, total, packing);
                return;
            }
        } catch (e) {
            console.error('Error parsing cart from localStorage:', e);
        }
    }
    handleEmptyCart();
}

function renderOrderItems(items) {
    const listContainer = document.getElementById('order-items-list');
    listContainer.innerHTML = items.map(item => {
        let imgSrc = item.image_url || item.image || '';
        if (imgSrc && !imgSrc.startsWith('http') && !imgSrc.startsWith('//')) {
            if (!imgSrc.startsWith('uploads/')) {
                imgSrc = 'uploads/' + imgSrc;
            }
        }
        if (!imgSrc) imgSrc = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&h=400&fit=crop';

        return `
        <div class="summary-item">
            <img src="${imgSrc}" class="summary-item-img" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500&h=400&fit=crop'">
            <div class="summary-item-details">
                <div class="summary-item-name">${item.name}</div>
                <div class="summary-item-qty">Qty: ${item.quantity}</div>
            </div>
            <div class="summary-item-price">₹${(item.price * item.quantity).toFixed(2)}</div>
        </div>
        `;
    }).join('');

    const totalQty = items.reduce((sum, item) => sum + (parseInt(item.quantity) || 1), 0);
    const countEl = document.getElementById('item-count');
    if (countEl) countEl.textContent = totalQty;
}
function _old_renderOrderItems(items) {
    const listContainer = document.getElementById('order-items-list');
    listContainer.innerHTML = items.map(item => `
        <tr>
            <td class="product-name">${item.name} × ${item.quantity}</td>
            <td class="product-total">₹${(item.price * item.quantity).toFixed(2)}</td>
        </tr>
    `).join('');
}

function updateSummaryUI(subtotal, delivery, gst, total, packing = 0) {
    document.getElementById('checkout-subtotal').textContent = `₹${subtotal.toFixed(2)}`;
    document.getElementById('checkout-delivery').textContent = `₹${delivery.toFixed(2)}`;
    document.getElementById('checkout-gst').textContent = `₹${gst.toFixed(2)}`;
    if (document.getElementById('checkout-packing')) {
        document.getElementById('checkout-packing').textContent = `₹${packing.toFixed(2)}`;
    }
    
    // Calculate all discounts
    let mrpDiscount = 0; // For future logic if items have MRP > Price
    
    let couponDiscount = 0;
    if (typeof appliedCouponCode !== 'undefined' && appliedCouponCode && appliedCouponDiscountPercent > 0) {
        couponDiscount = subtotal * (appliedCouponDiscountPercent / 100);
    }

    let tierDiscount = 0;
    if (typeof USER_TIER_DISCOUNT_PERCENT !== 'undefined' && USER_TIER_DISCOUNT_PERCENT > 0) {
        tierDiscount = subtotal * (USER_TIER_DISCOUNT_PERCENT / 100);
    }

    let pointsDiscount = 0;
    const redeemCheckbox = document.getElementById('redeem_loyalty_points');
    const baseTotal = subtotal + gst + delivery + packing - couponDiscount - tierDiscount;
    if (redeemCheckbox && redeemCheckbox.checked && typeof USER_POINTS_BALANCE !== 'undefined') {
        pointsDiscount = Math.min(USER_POINTS_BALANCE, Math.max(0, baseTotal));
    }
    
    const totalDiscount = mrpDiscount + couponDiscount + tierDiscount + pointsDiscount;
    
    const discountRow = document.getElementById('total-discount-row');
    if (discountRow) {
        if (totalDiscount > 0) {
            discountRow.style.display = 'flex';
            document.getElementById('checkout-total-discount').textContent = `-₹${totalDiscount.toFixed(2)}`;
            
            // Update popover rows
            const bdMrp = document.getElementById('bd-mrp-row');
            if(bdMrp) { bdMrp.style.display = mrpDiscount > 0 ? 'flex' : 'none'; document.getElementById('bd-mrp').textContent = `-₹${mrpDiscount.toFixed(2)}`; }
            
            const bdTier = document.getElementById('bd-tier-row');
            if(bdTier) { 
                bdTier.style.display = tierDiscount > 0 ? 'flex' : 'none'; 
                const bdTierLabel = document.getElementById('bd-tier-label');
                if (bdTierLabel) bdTierLabel.textContent = `${USER_TIER_NAME} Tier (${USER_TIER_DISCOUNT_PERCENT}%)`;
                document.getElementById('bd-tier').textContent = `-₹${tierDiscount.toFixed(2)}`; 
            }
            
            const bdCoupon = document.getElementById('bd-coupon-row');
            if(bdCoupon) { bdCoupon.style.display = couponDiscount > 0 ? 'flex' : 'none'; document.getElementById('bd-coupon').textContent = `-₹${couponDiscount.toFixed(2)}`; }
            
            const bdPoints = document.getElementById('bd-points-row');
            if(bdPoints) { bdPoints.style.display = pointsDiscount > 0 ? 'flex' : 'none'; document.getElementById('bd-points').textContent = `-₹${pointsDiscount.toFixed(2)}`; }
        } else {
            discountRow.style.display = 'none';
        }
    }
    
    const finalTotal = Math.max(0, baseTotal - pointsDiscount);

    document.getElementById('checkout-total').textContent = `₹${finalTotal.toFixed(2)}`;

    // 4. Points Earned Tracker (2% of order value)
    const pointsEarned = Math.round(finalTotal * 0.02);
    const earnedTracker = document.getElementById('points-earned-tracker');
    const earnedVal = document.getElementById('earned-points-value');
    if (earnedTracker && earnedVal) {
        earnedVal.textContent = pointsEarned;
        earnedTracker.style.display = 'block';
    }
}

function handleEmptyCart() {
    const listContainer = document.getElementById('order-items-list');
    listContainer.innerHTML = `
        <div class="text-center py-5 text-muted" style="background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px dashed rgba(255,255,255,0.1);">
            <i class="fas fa-shopping-basket mb-3" style="font-size: 3rem; color: rgba(255,255,255,0.2);"></i>
            <h5 class="text-white">Your cart is empty</h5>
            <p class="mb-4" style="font-size: 0.9rem;">Looks like you haven't added anything to your order yet.</p>
            <a href="menutest.html" class="btn btn-outline-light" style="border-color: var(--gold); color: var(--gold); padding: 8px 25px; border-radius: 8px; font-weight: bold; text-decoration: none;">Browse Menu</a>
        </div>
    `;
    
    // Disable Proceed to Payment Button
    const submitBtn = document.getElementById('submitOrderBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
    }

    updateSummaryUI(0, 0, 0, 0, 0);
}

document.addEventListener('DOMContentLoaded', () => {
    loadCheckoutSummary();
    const redeemCheckbox = document.getElementById('redeem_loyalty_points');
    if (redeemCheckbox) {
        redeemCheckbox.addEventListener('change', () => {
            loadCheckoutSummary();
        });
    }
    
    // Toggle discount popover
    const discountInfoBtn = document.getElementById('discountInfoBtn');
    const discountPopover = document.getElementById('discountPopover');
    if (discountInfoBtn && discountPopover) {
        discountInfoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (discountPopover.style.display === 'none') {
                discountPopover.style.display = 'block';
            } else {
                discountPopover.style.display = 'none';
            }
        });
        
        // Close popover when clicking outside
        document.addEventListener('click', (e) => {
            if (!discountInfoBtn.contains(e.target) && !discountPopover.contains(e.target)) {
                discountPopover.style.display = 'none';
            }
        });
    }
});

document.getElementById('orderForm').onsubmit = async (e) => {
    e.preventDefault();
    
    // Additional validation
    const phone = document.getElementById('billing_phone').value;
    if (phone.length !== 10) {
        alert('Please enter a valid 10-digit phone number.');
        return;
    }

    // Compile fields for backend compatibility
    const firstName = document.getElementById('billing_first_name').value.trim();
    const lastName = document.getElementById('billing_last_name').value.trim();
    const compiledName = firstName + ' ' + lastName;
    document.getElementById('name').value = compiledName;

    const street = document.getElementById('billing_street').value.trim();
    const apartment = document.getElementById('billing_apartment').value.trim();
    const city = document.getElementById('billing_city').value.trim();
    const state = document.getElementById('billing_state').value.trim();
    const zip = document.getElementById('billing_zip').value.trim();
    const country = document.getElementById('billing_country').value.trim();
    
    let compiledAddress = street;
    if (apartment) compiledAddress += ', ' + apartment;
    compiledAddress += ', ' + city + ', ' + state + ' - ' + zip + ', ' + country;
    
    // Append coordinates from map selection if present
    const coords = document.getElementById('delivery_coords').value.trim();
    if (coords) {
        compiledAddress += ` [${coords}]`;
    }
    
    const tableNum = localStorage.getItem('table_number');
    if (tableNum) {
        compiledAddress = `Table ${tableNum}, ` + compiledAddress;
    }
    document.getElementById('address').value = compiledAddress;

    // Parse the total amount from checkout-total
    const totalText = document.getElementById('checkout-total').textContent;
    const totalAmount = parseFloat(totalText.replace(/[^\d.]/g, '')) || 0;
    const amountInPaise = Math.round(totalAmount * 100);

    if (amountInPaise <= 0) {
        alert('Your cart is empty or the amount is invalid.');
        return;
    }

    // Configure Razorpay checkout options
    const razorpayKey = "<?php echo get_env_var('RAZORPAY_KEY_ID'); ?>";
    const options = {
        "key": razorpayKey,
        "amount": amountInPaise,
        "currency": "INR",
        "name": "Medusa",
        "description": "Order Payment",
        "handler": function (response) {
            showLoaderOverlay();
            submitBackendOrder(compiledName, phone, compiledAddress, response.razorpay_payment_id);
        },
        "prefill": {
            "name": compiledName,
            "email": document.getElementById('billing_email').value,
            "contact": phone
        },
        "theme": {
            "color": "#dfba86"
        }
    };

    // Prefill the method based on user selection in UI
    const selectedMethodElement = document.querySelector('input[name="payment_method"]:checked');
    if (selectedMethodElement) {
        const selectedMethod = selectedMethodElement.value;
        if (selectedMethod === 'membership') {
            const cardNum = document.getElementById('membership_card_number').value.trim();
            const cvv = document.getElementById('membership_cvv').value.trim();
            if (!cardNum || !cvv) {
                alert('Please enter your Membership Card Number and CVV.');
                return;
            }
            showLoaderOverlay();
            submitBackendOrder(compiledName, phone, compiledAddress, 'MEMBERSHIP_PASS');
            return;
        } else if (selectedMethod === 'cod') {
            showLoaderOverlay();
            submitBackendOrder(compiledName, phone, compiledAddress, 'COD');
            return;
        } else if (selectedMethod === 'upi') {
            options.prefill.method = 'upi';
            const upiIdVal = document.getElementById('upi_id').value.trim();
            if (upiIdVal) {
                options.prefill.vpa = upiIdVal;
            }
        } else if (selectedMethod === 'gpay') {
            options.prefill.method = 'wallet';
            options.prefill.provider = 'google_pay';
        }
    }

    const rzp = new Razorpay(options);
    rzp.open();
};

async function submitBackendOrder(compiledName, phone, compiledAddress, paymentId) {
    let cartItems = [];
    const savedCart = localStorage.getItem('foodie_cart');
    if (savedCart) {
        try {
            cartItems = JSON.parse(savedCart);
        } catch(e) {
            console.error('Error parsing cart items:', e);
        }
    }

    cartItems = (Array.isArray(cartItems) ? cartItems : []).map(item => {
        return {
            food_item_id: parseInt(item.food_item_id ?? item.id ?? 0, 10),
            quantity: Math.max(1, parseInt(item.quantity ?? 1, 10)),
            name: item.name ?? ''
        };
    }).filter(item => item.food_item_id > 0);

    if (cartItems.length === 0) {
        alert('Your cart is empty or contains invalid items. Please refresh and try again.');
        hideLoaderOverlay();
        return;
    }

    const saveAddressCheckbox = document.getElementById('save_address');
    const saveAddress = saveAddressCheckbox ? saveAddressCheckbox.checked : false;
    const savedAddressSelect = document.getElementById('saved_address_id');
    const savedAddressId = savedAddressSelect ? savedAddressSelect.value : '';
    
    const street = document.getElementById('billing_street').value.trim();
    const apartment = document.getElementById('billing_apartment').value.trim();
    const city = document.getElementById('billing_city').value.trim();
    const state = document.getElementById('billing_state').value.trim();
    const zip = document.getElementById('billing_zip').value.trim();
    const country = document.getElementById('billing_country').value.trim();
    const firstName = document.getElementById('billing_first_name').value.trim();
    const lastName = document.getElementById('billing_last_name').value.trim();

    const data = {
        order_type: currentDeliveryMode,
        customer_name: compiledName,
        customer_phone: phone,
        delivery_address: compiledAddress,
        delivery_city: city,
        delivery_state: state,
        delivery_pincode: zip,
        customer_email: document.getElementById('billing_email').value,
        message: document.getElementById('billing_message').value || '',
        csrf_token: document.getElementById('csrf_token').value,
        razorpay_payment_id: paymentId,
        cart_items: cartItems,
        payment_method: (document.querySelector('input[name="payment_method"]:checked') || {}).value || 'Online',
        save_address: saveAddress,
        saved_address_id: savedAddressId,
        first_name: firstName,
        last_name: lastName,
        country: country,
        street: street,
        apartment: apartment,
        city: city,
        state: state,
        zip: zip,
        coupon_code: appliedCouponCode,
        redeem_loyalty_points: document.getElementById('redeem_loyalty_points') ? document.getElementById('redeem_loyalty_points').checked : false,
        membership_card_number: document.getElementById('membership_card_number') ? document.getElementById('membership_card_number').value.trim() : '',
        membership_cvv: document.getElementById('membership_cvv') ? document.getElementById('membership_cvv').value.trim() : ''
    };

    try {
        const response = await fetch('order_place.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        const text = await response.text();
        console.log('Response text:', text);
        try {
            const result = JSON.parse(text);
            if (result.success) {
                // Clear cart on successful order
                localStorage.removeItem('foodie_cart');
                localStorage.removeItem('table_number');
                
                // Animate loader to completion
                if (window.paymentLoaderInterval) clearInterval(window.paymentLoaderInterval);
                const progressFill = document.getElementById('loader-progress-fill');
                const statusText = document.getElementById('loader-status-text');
                if (progressFill) progressFill.style.width = '100%';
                if (statusText) statusText.textContent = "Order secured successfully! Redirecting...";
                
                setTimeout(() => {
                    // Use server-provided redirect_url (order_confirmed.php) if available
                    const dest = result.redirect_url
                        ? result.redirect_url
                        : `order-success.php?order_id=${result.order_id}`;
                    window.location.href = dest;
                }, 1000);
            } else {
                if (window.paymentLoaderInterval) clearInterval(window.paymentLoaderInterval);
                document.getElementById('payment-loader-overlay').classList.remove('active');
                document.body.style.overflow = '';
                alert('Error submitting order details: ' + result.message);
            }
        } catch(e) {
            if (window.paymentLoaderInterval) clearInterval(window.paymentLoaderInterval);
            document.getElementById('payment-loader-overlay').classList.remove('active');
            document.body.style.overflow = '';
            alert('Invalid JSON response from server: ' + text);
        }
    } catch(err) {
        if (window.paymentLoaderInterval) clearInterval(window.paymentLoaderInterval);
        document.getElementById('payment-loader-overlay').classList.remove('active');
        document.body.style.overflow = '';
        alert('Server communication error: ' + err.message);
    }
}

function showLoaderOverlay() {
    const overlay = document.getElementById('payment-loader-overlay');
    if (overlay) {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // prevent scrolling
    }
    
    // Animate loader steps
    let progress = 5;
    const progressFill = document.getElementById('loader-progress-fill');
    const statusText = document.getElementById('loader-status-text');
    
    // Interval for simulating progress
    const steps = [
        { progress: 20, text: "Verifying transaction with Razorpay secure server..." },
        { progress: 45, text: "Processing your order in Medusa guest booking..." },
        { progress: 65, text: "Deducting loyalty rewards & applying campaign codes..." },
        { progress: 80, text: "Generating digital PDF invoice & receipt..." },
        { progress: 95, text: "Almost finished! Finalizing order prep dispatch..." }
    ];
    
    let currentStep = 0;
    if (progressFill) progressFill.style.width = progress + '%';
    
    window.paymentLoaderInterval = setInterval(() => {
        if (currentStep < steps.length) {
            progress = steps[currentStep].progress;
            if (statusText) statusText.textContent = steps[currentStep].text;
            if (progressFill) progressFill.style.width = progress + '%';
            currentStep++;
        }
    }, 1200);
}

// Scroll to top button logic
const scrollToTopBtn = document.getElementById('scrollToTop');
window.addEventListener('scroll', () => {
    if (window.scrollY > 300) {
        scrollToTopBtn.classList.add('visible');
    } else {
        scrollToTopBtn.classList.remove('visible');
    }
});
scrollToTopBtn.addEventListener('click', () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});

// Clear map coordinates if user manually changes address text
document.addEventListener('DOMContentLoaded', () => {
    const billingStreet = document.getElementById('billing_street');
    if (billingStreet) {
        billingStreet.addEventListener('input', () => {
            const coordsInput = document.getElementById('delivery_coords');
            if (coordsInput) coordsInput.value = '';
        });
    }
});
</script>

<!-- Premium Payment Loading Overlay -->
<div id="payment-loader-overlay" class="payment-loader-overlay">
    <div class="loader-content">
        <div class="food-animation-container">
            <img src="assets/video/original-8456508d6dad952727bb9f969e1ecd55.gif" alt="Preparing Order..." class="food-loader-gif">
        </div>
        <h2 class="loader-title" style="display: none;">Payment Successful</h2>
        <p class="loader-subtitle" id="loader-status-text" style="display: none;">Verifying payment details...</p>
        <div class="loader-progress-bar" style="display: none;">
            <div class="loader-progress-fill glow-pulse" id="loader-progress-fill"></div>
        </div>
    </div>
</div>

<!-- QR Code Checkout Mode Logic -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const tableCode = params.get('table');
    
    if (tableCode) {
        // Change Title
        const sectionTitle = document.querySelector('.checkout-section-title');
        if (sectionTitle) sectionTitle.innerHTML = `Table ${tableCode} Order Details`;
        
        // Hide saved addresses
        const savedAddrSec = document.getElementById('saved-addresses-section');
        if (savedAddrSec) savedAddrSec.style.display = 'none';

        // Hide Delivery Charge in summary
        document.getElementById('checkout-delivery').textContent = '₹0.00';
        // We also need to hide the row, let's find its parent
        const deliveryRow = document.getElementById('checkout-delivery').closest('.summary-totals-row');
        if (deliveryRow) deliveryRow.style.display = 'none';

        // Hide irrelevant fields and remove required
        const fieldsToHide = [
            'billing_country', 'billing_street', 'billing_apartment', 
            'billing_city', 'billing_state', 'billing_zip', 'billing_email', 'save_address'
        ];

        fieldsToHide.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.removeAttribute('required');
                el.value = (id === 'billing_email') ? 'guest@medusa.local' : `Table ${tableCode}`;
                
                // Hide its closest col wrapper
                const col = el.closest('.col-12, .col-md-6, .form-check');
                if (col) col.style.display = 'none';
            }
        });

        // Ensure delivery charge isn't added to total (will require redefining the calculateTotal function but let's just do it cleanly)
        // Wait, the checkout script calculates total dynamically. Let's override delivery charge logic
        // We will just patch the global delivery variable if it exists or reset the total element later.
        
        // Hide "Pay with cash on delivery" text in payment panel if needed, but the main goal is just hiding address fields!
    }
});

// UI Toggles for Redesign
let currentDeliveryMode = 'home';
let currentPaymentMode = 'online';

function setDeliveryMode(mode, element) {
    currentDeliveryMode = mode;
    document.querySelectorAll('input[name="delivery_option"]').forEach(radio => radio.closest('.option-box').classList.remove('active'));
    element.classList.add('active');
    element.querySelector('input').checked = true;
    loadCheckoutSummary();
}

function setPaymentMode(mode, element) {
    currentPaymentMode = mode;
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => radio.closest('.option-box').classList.remove('active'));
    element.classList.add('active');
    element.querySelector('input').checked = true;
}

// Map Modal Logic
let map, marker;
const mapModal = document.getElementById('mapModal');
const btnChooseFromMap = document.getElementById('btnChooseFromMap');
const closeMapModalBtn = document.getElementById('closeMapModal');
const confirmLocationBtn = document.getElementById('confirmLocationBtn');

let selectedLat = 22.5726; // Default: Kolkata
let selectedLon = 88.3639;

if(btnChooseFromMap) {
    btnChooseFromMap.addEventListener('click', function() {
        mapModal.style.display = 'block';
        
        if (!map) {
            // Google Maps Styling (Dark theme)
            const darkTheme = [
              {elementType: 'geometry', stylers: [{color: '#242f3e'}]},
              {elementType: 'labels.text.stroke', stylers: [{color: '#242f3e'}]},
              {elementType: 'labels.text.fill', stylers: [{color: '#746855'}]},
              {featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{color: '#d59563'}]},
              {featureType: 'road', elementType: 'geometry', stylers: [{color: '#38414e'}]},
              {featureType: 'road', elementType: 'geometry.stroke', stylers: [{color: '#212a37'}]},
              {featureType: 'road', elementType: 'labels.text.fill', stylers: [{color: '#9ca5b3'}]},
              {featureType: 'water', elementType: 'geometry', stylers: [{color: '#17263c'}]},
              {featureType: 'water', elementType: 'labels.text.fill', stylers: [{color: '#515c6d'}]},
              {featureType: 'water', elementType: 'labels.text.stroke', stylers: [{color: '#17263c'}]}
            ];

            const mapOptions = {
                zoom: 13,
                center: {lat: selectedLat, lng: selectedLon},
                styles: darkTheme,
                disableDefaultUI: true,
                zoomControl: true
            };
            map = new google.maps.Map(document.getElementById('leafletMap'), mapOptions);

            marker = new google.maps.Marker({
                position: {lat: selectedLat, lng: selectedLon},
                map: map,
                draggable: true,
                animation: google.maps.Animation.DROP
            });

            google.maps.event.addListener(marker, 'dragend', function() {
                const position = marker.getPosition();
                selectedLat = position.lat();
                selectedLon = position.lng();
            });

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    selectedLat = position.coords.latitude;
                    selectedLon = position.coords.longitude;
                    const pos = {lat: selectedLat, lng: selectedLon};
                    map.setCenter(pos);
                    map.setZoom(15);
                    marker.setPosition(pos);
                });
            }
        }
        
        // Trigger resize so it renders correctly inside the modal
        setTimeout(() => { 
            google.maps.event.trigger(map, 'resize'); 
            map.setCenter({lat: selectedLat, lng: selectedLon}); 
        }, 200);
    });
}

if(closeMapModalBtn) {
    closeMapModalBtn.addEventListener('click', () => { mapModal.style.display = 'none'; });
}

if(confirmLocationBtn) {
    confirmLocationBtn.addEventListener('click', function() {
        const originalText = confirmLocationBtn.innerHTML;
        confirmLocationBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching...';
        
        const geocoder = new google.maps.Geocoder();
        const latlng = {lat: selectedLat, lng: selectedLon};
        
        geocoder.geocode({ location: latlng }, function(results, status) {
            confirmLocationBtn.innerHTML = originalText;
            if (status === 'OK' && results[0]) {
                let street = 'Selected Location';
                let city = '';
                let state = '';
                let zip = '';
                
                results[0].address_components.forEach(comp => {
                    if (comp.types.includes('route') || comp.types.includes('neighborhood')) street = comp.long_name;
                    if (comp.types.includes('locality')) city = comp.long_name;
                    if (comp.types.includes('administrative_area_level_1')) state = comp.long_name;
                    if (comp.types.includes('postal_code')) zip = comp.long_name;
                });
                
                // Save coordinates to hidden field
                document.getElementById('delivery_coords').value = selectedLat + ',' + selectedLon;
                
                // Fill billing street with full formatted address
                document.getElementById('billing_street').value = results[0].formatted_address;
                
                const cityInput = document.getElementById('billing_city');
                if(cityInput) cityInput.value = city;
                
                const stateInput = document.getElementById('billing_state');
                if(stateInput) stateInput.value = state;
                
                const zipInput = document.getElementById('billing_zip');
                if(zipInput) zipInput.value = zip;
                
                mapModal.style.display = 'none';
            } else {
                alert('Could not determine address from maps API. Status: ' + status);
            }
        });
    });
}

// Handle Notification Dropdown Click to Mark as Read
const notifDropdown = document.getElementById('notifDropdown');
if (notifDropdown) {
    notifDropdown.addEventListener('show.bs.dropdown', function () {
        const bellIcon = document.getElementById('notifBellIcon');
        const redDot = document.getElementById('notifRedDot');
        
        if (bellIcon && bellIcon.classList.contains('bell-ringing')) {
            // Remove animation and dot instantly for better UX
            bellIcon.classList.remove('bell-ringing');
            if (redDot) redDot.style.display = 'none';
            
            // Call API to update database
            fetch('api/mark-notifications-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            }).catch(err => console.error('Error marking notifications as read:', err));
        }
    });
}

// Pre-load coupon from sessionStorage if set from cart page
document.addEventListener('DOMContentLoaded', () => {
    const preAppliedCoupon = sessionStorage.getItem('applied_coupon_code');
    if (preAppliedCoupon) {
        const couponInput = document.getElementById('couponCodeInput');
        if (couponInput) {
            couponInput.value = preAppliedCoupon;
            const applyBtn = document.getElementById('applyCouponBtn');
            if (applyBtn) {
                const couponInputGroup = document.getElementById('couponInputGroup');
                if (couponInputGroup) couponInputGroup.classList.add('active');
                applyBtn.click();
            }
        }
    }
});
</script>
<?php require_once __DIR__ . '/includes/active_order_bar.php'; ?>
<?php require_once __DIR__ . '/includes/order_toast.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
