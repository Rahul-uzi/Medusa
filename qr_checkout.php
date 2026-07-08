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

        // Fetch saved addresses
        $addr_stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
        $addr_stmt->execute([$_SESSION['user_id']]);
        $saved_addresses = $addr_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // ignore
    }
} else {
    $saved_addresses = [];
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
    <!-- Global Theme Controller -->
    <script src="assets/js/theme-toggle.js"></script>
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
        body {
            background-color: var(--bg-dark) !important;
            color: var(--white) !important;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
        }
        
        /* Hero Banner */
        .checkout-hero {
            position: relative;
            background-image: linear-gradient(rgba(10, 10, 10, 0.65), rgba(10, 10, 10, 0.85)), url('assets/images/checkout_hero.png');
            background-size: cover;
            background-position: center;
            padding: 8rem 0 6rem 0;
            text-align: center;
            margin-bottom: 4rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
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
            background-color: var(--gold);
            color: #0c0a0a;
            border: none;
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
            background-color: var(--gold-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(223, 186, 134, 0.2);
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
            background-color: #0b0f19;
            color: #8a99ad;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            position: relative;
        }

        .footer-title {
            color: #ffffff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            letter-spacing: 0.5px;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: #8a99ad;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }

        .footer-links a i {
            font-size: 0.75rem;
            color: #8a99ad;
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
        }

        .footer-contact-info span {
            color: #8a99ad;
        }

        .footer-social-icons a {
            color: #8a99ad;
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
            margin: 2rem 0 1rem 0;
            opacity: 1;
        }

        .footer-bottom-text {
            font-size: 0.85rem;
            color: #6c7a8d !important;
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
            background-color: var(--gold);
            color: #0c0a0a;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(223, 186, 134, 0.3);
            z-index: 1000;
        }

        .scroll-to-top-btn.visible {
            opacity: 1;
            visibility: visible;
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
            padding: 3rem 2.5rem;
            background: rgba(18, 17, 17, 0.7);
            border: 1px solid rgba(223, 186, 134, 0.15);
            border-radius: 24px;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.8), inset 0 1px 0 rgba(255, 255, 255, 0.05);
            transform: scale(0.95);
            transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }

        .payment-loader-overlay.active .loader-content {
            transform: scale(1);
        }

        .logo-container-loader {
            position: relative;
            width: 90px;
            height: 90px;
            margin: 0 auto 2.5rem auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .loader-logo {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 2px solid #dfba86;
            padding: 2px;
            z-index: 2;
            animation: pulseLogo 2s ease-in-out infinite;
        }

        .logo-glow {
            position: absolute;
            width: 90px;
            height: 90px;
            background: radial-gradient(circle, rgba(223, 186, 134, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
            animation: pulseGlow 2s ease-in-out infinite;
        }

        .spinner-ring {
            position: absolute;
            width: 90px;
            height: 90px;
            border: 3px solid rgba(223, 186, 134, 0.08);
            border-top: 3px solid #dfba86;
            border-radius: 50%;
            animation: spinLoader 1.2s cubic-bezier(0.5, 0.1, 0.5, 0.9) infinite;
            z-index: 3;
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
            margin-bottom: 2rem;
            min-height: 1.5rem;
            font-weight: 500;
        }

        .loader-progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .loader-progress-fill {
            width: 5%;
            height: 100%;
            background: linear-gradient(90deg, #dfba86 0%, #e6c89f 100%);
            border-radius: 10px;
            transition: width 0.4s cubic-bezier(0.25, 1, 0.5, 1);
        }

        /* Keyframes */
        @keyframes spinLoader {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulseLogo {
            0%, 100% { transform: scale(1); box-shadow: 0 0 10px rgba(223, 186, 134, 0.2); }
            50% { transform: scale(1.05); box-shadow: 0 0 25px rgba(223, 186, 134, 0.5); }
        }

        @keyframes pulseGlow {
            0%, 100% { transform: scale(0.85); opacity: 0.5; }
            50% { transform: scale(1.15); opacity: 0.8; }
        }
    </style>

    <!-- Navbar Performance Optimization Links -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
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

    <div class="checkout-hero">
        <div class="container">
            <h1>Checkout</h1>
            <p>Medusa Premium Theme</p>
        </div>
    </div>

    <div class="container my-5 fade-up">
        <form id="orderForm">
            <!-- Hidden inputs for legacy API compatibility -->
            <input type="hidden" id="name" value="">
            <input type="hidden" id="address" value="">
            <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="row g-5">
                <!-- Left Column: Billing Details Form -->
                <div class="col-lg-7">
                    <div class="checkout-form-card">
                        <h2 class="checkout-section-title">Billing Details</h2>
                        
                        <!-- Removed saved addresses for QR checkout -->
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="billing_name" class="form-label-checkout">Full Name<span class="required-asterisk">*</span></label>
                                <input type="text" id="billing_name" class="form-control-checkout" placeholder="Full Name" value="<?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?>" required>
                            </div>

                            <!-- Phone Number (Restored) -->
                            <div class="col-12 mt-3">
                                <label for="billing_phone" class="form-label-checkout">Phone Number<span class="required-asterisk">*</span></label>
                                <input type="tel" id="billing_phone" class="form-control-checkout" placeholder="10-digit Phone Number" value="<?php echo htmlspecialchars($user_details['phone']); ?>" required pattern="[0-9]{10}" maxlength="10" minlength="10" title="Phone number must be exactly 10 digits">
                            </div>
                            <!-- Removed address, email, notes, and save address checkboxes for QR checkout -->
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sticky Order & Payment Sidebar -->
                <div class="col-lg-5">
                    <div class="sticky-sidebar">
                        <!-- Your Order Box -->
                        <div class="order-summary-box">
                            <h2 class="checkout-section-title">Your Order</h2>
                            <table class="checkout-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="header-total">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="order-items-list">
                                    <!-- Dynamic order items will be inserted here -->
                                </tbody>
                            </table>

                            <div class="mt-3">
                                <div class="summary-totals-row">
                                    <span>Subtotal</span>
                                    <span id="checkout-subtotal">₹0.00</span>
                                </div>
                                <div class="summary-totals-row">
                                    <span>GST (<?php echo $gst_rate; ?>%)</span>
                                    <span id="checkout-gst">₹0.00</span>
                                </div>
                                <div class="summary-totals-row">
                                    <span>Packing Charges</span>
                                    <span id="checkout-packing">₹<?php echo number_format($packing_charge, 2); ?></span>
                                </div>
                                <div class="summary-totals-row">
                                    <span>Delivery Charge</span>
                                    <span id="checkout-delivery">₹40.00</span>
                                </div>
                                <div class="summary-totals-row" id="coupon-discount-row" style="display: none; color: #dfba86;">
                                    <span>Coupon Discount (<span id="coupon-percent-label">0</span>%)</span>
                                    <span id="checkout-discount">-₹0.00</span>
                                </div>
                                <div class="summary-totals-row" id="tier-discount-row" style="display: none; color: #dfba86;">
                                    <span>Tier Discount (<span id="tier-name-label">Silver</span> <span id="tier-percent-label">10</span>%)</span>
                                    <span id="checkout-tier-discount">-₹0.00</span>
                                </div>
                                <div class="summary-totals-row" id="points-discount-row" style="display: none; color: #dfba86;">
                                    <span>Points Redeemed (<span id="redeemed-points-label">0</span> pts)</span>
                                    <span id="checkout-points-discount">-₹0.00</span>
                                </div>
                                <div class="summary-totals-row grand-total">
                                    <span>Total</span>
                                    <span id="checkout-total">₹0.00</span>
                                </div>
                                <div class="mt-2 text-end text-success small" id="points-earned-tracker" style="display: none; font-weight: 600;">
                                    <i class="fa-solid fa-gift"></i> You will earn <span id="earned-points-value">0</span> reward points!
                                </div>
                            </div>
                        </div>

                        <!-- Coupon Code Toggle Box -->
                        <div class="coupon-toggle-box">
                            Have a coupon? <span class="coupon-link" id="couponToggle">Click here to enter your coupon code</span>
                            <div class="coupon-input-group" id="couponInputGroup">
                                <input type="text" id="couponCodeInput" class="form-control-checkout" placeholder="Coupon Code">
                                <button type="button" class="btn-premium" style="padding: 0 20px; font-size: 0.9rem;" id="applyCouponBtn">Apply</button>
                            </div>
                        </div>

                        <!-- Loyalty Points Redemption Box removed for QR checkout -->

                        <!-- Payment & Submission Box -->
                        <div class="payment-box">
                            <!-- Payment options removed for QR Checkout -->

                            <p class="privacy-policy-text">
                                Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our privacy policy.
                            </p>

                            <!-- Place Order Button -->
                            <button type="submit" class="btn-place-order w-100">
                                <span>Place order</span>
                            </button>

                            <!-- Center-aligned Modify Order -->
                            <div class="modify-order-container">
                                <a href="carttest.html" class="modify-order-link">
                                    <i class="fas fa-edit"></i> Modify Order
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Footer Section -->
    <footer class="main-footer py-5 mt-5">
        <div class="container">
            <div class="row g-4">
                <!-- Column 1: Menu Links -->
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-title">Menu Links</h5>
                    <ul class="footer-links list-unstyled m-0">
                        <li><a href="index.html"><i class="fas fa-chevron-right me-2"></i>Home</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>FAQ</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>Contacts</a></li>
                    </ul>
                </div>
                
                <!-- Column 2: Order Wizard -->
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-title">Order Wizard</h5>
                    <ul class="footer-links list-unstyled m-0">
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>Pay online</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-2"></i>Pay with cash on delivery</a></li>
                    </ul>
                </div>
                
                <!-- Column 3: Contacts -->
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-title">Contacts</h5>
                    <ul class="footer-contact-info list-unstyled m-0">
                        <li class="d-flex align-items-start mb-3">
                            <i class="fas fa-map-marker-alt footer-icon me-3 mt-1"></i>
                            <span>Address: 1234 Street Name, City Name, USA</span>
                        </li>
                        <li class="d-flex align-items-start mb-3">
                            <i class="fas fa-envelope footer-icon me-3 mt-1"></i>
                            <span>Mail: info@yourdomain.com</span>
                        </li>
                        <li class="d-flex align-items-start">
                            <i class="fas fa-phone-alt footer-icon me-3 mt-1"></i>
                            <span>Phone: +3630123456789</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Column 4: Find Us On -->
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-title">Find Us On</h5>
                    <div class="footer-social-icons d-flex gap-3">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest-p"></i></a>
                    </div>
                </div>
            </div>
            
            <hr class="footer-divider">
            
            <div class="d-flex justify-content-between align-items-center flex-wrap pt-2">
                <span class="text-muted footer-bottom-text">With <i class="fas fa-heart text-danger"></i> by UWS</span>
                <a href="#" class="text-muted footer-bottom-text hover-orange" style="text-decoration: none;">Terms and conditions</a>
            </div>
        </div>
    </footer>

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
        
        document.getElementById('billing_street').value = selectedOption.dataset.street || "";
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
couponToggle.addEventListener('click', () => {
    couponInputGroup.classList.toggle('active');
});

document.getElementById('applyCouponBtn').addEventListener('click', () => {
    const code = document.getElementById('couponCodeInput').value.trim();
    if (!code) {
        alert('Please enter a coupon code.');
        return;
    }
    
    const applyBtn = document.getElementById('applyCouponBtn');
    applyBtn.disabled = true;
    applyBtn.textContent = 'Applying...';

    fetch(`api/validate-coupon.php?code=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(data => {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
            if (data.success) {
                appliedCouponCode = data.coupon_code;
                appliedCouponDiscountPercent = parseFloat(data.discount_value);
                alert(`Coupon "${data.coupon_code}" applied successfully: ${data.discount_value}% OFF!`);
                loadCheckoutSummary(); // Refresh UI with discount
            } else {
                appliedCouponCode = '';
                appliedCouponDiscountPercent = 0;
                alert('Invalid Coupon: ' + data.message);
                loadCheckoutSummary(); // Refresh UI to remove discount
            }
        })
        .catch(err => {
            applyBtn.disabled = false;
            applyBtn.textContent = 'Apply';
            alert('Failed to validate coupon due to network error.');
        });
});

// Interactive payment method selection
const paymentOptions = document.querySelectorAll('.payment-option-item');
paymentOptions.forEach(option => {
    option.addEventListener('click', function(e) {
        // If clicked directly on the radio or label inside content, let the browser handle it
        const radio = this.querySelector('.payment-option-radio');
        if (e.target !== radio && e.target.tagName !== 'LABEL') {
            radio.checked = true;
        }
        
        // Update active class on items
        paymentOptions.forEach(opt => opt.classList.remove('active'));
        this.classList.add('active');
        
        // Update visible panel
        const targetPanelId = this.getAttribute('data-target');
        document.querySelectorAll('.payment-details-panel').forEach(panel => {
            panel.classList.remove('active');
        });
        document.getElementById(targetPanelId).classList.add('active');
    });
});

// Load checkout summary totals
async function loadCheckoutSummary() {
    try {
        const response = await fetch('api/get-cart.php');
        const result = await response.json();
        if (result.success && result.items && result.items.length > 0) {
            // Compute subtotal from items — API doesn't return a separate total field
            const subtotal = result.items.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
            const gst = subtotal * (GST_RATE / 100);
            const delivery = DELIVERY_CHARGE;
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
                const delivery = DELIVERY_CHARGE;
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
    useMockSummary();
}

function renderOrderItems(items) {
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
    
    // 1. Coupon Discount
    let couponDiscount = 0;
    if (typeof appliedCouponCode !== 'undefined' && appliedCouponCode && appliedCouponDiscountPercent > 0) {
        couponDiscount = subtotal * (appliedCouponDiscountPercent / 100);
        document.getElementById('coupon-percent-label').textContent = appliedCouponDiscountPercent;
        document.getElementById('checkout-discount').textContent = `-₹${couponDiscount.toFixed(2)}`;
        document.getElementById('coupon-discount-row').style.display = 'flex';
    } else {
        document.getElementById('coupon-discount-row').style.display = 'none';
    }

    // 2. Tier Discount
    let tierDiscount = 0;
    if (typeof USER_TIER_DISCOUNT_PERCENT !== 'undefined' && USER_TIER_DISCOUNT_PERCENT > 0) {
        tierDiscount = subtotal * (USER_TIER_DISCOUNT_PERCENT / 100);
        document.getElementById('tier-name-label').textContent = USER_TIER_NAME;
        document.getElementById('tier-percent-label').textContent = USER_TIER_DISCOUNT_PERCENT;
        document.getElementById('checkout-tier-discount').textContent = `-₹${tierDiscount.toFixed(2)}`;
        document.getElementById('tier-discount-row').style.display = 'flex';
    } else {
        document.getElementById('tier-discount-row').style.display = 'none';
    }

    // 3. Points Discount
    let pointsDiscount = 0;
    const redeemCheckbox = document.getElementById('redeem_loyalty_points');
    const baseTotal = subtotal + gst + delivery + packing - couponDiscount - tierDiscount;
    if (redeemCheckbox && redeemCheckbox.checked && typeof USER_POINTS_BALANCE !== 'undefined') {
        pointsDiscount = Math.min(USER_POINTS_BALANCE, Math.max(0, baseTotal));
        document.getElementById('redeemed-points-label').textContent = pointsDiscount;
        document.getElementById('checkout-points-discount').textContent = `-₹${pointsDiscount.toFixed(2)}`;
        document.getElementById('points-discount-row').style.display = 'flex';
    } else {
        document.getElementById('points-discount-row').style.display = 'none';
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

function useMockSummary() {
    // Standard mock price from mockup screenshot ($18.70 / ₹18.70)
    const listContainer = document.getElementById('order-items-list');
    listContainer.innerHTML = `
        <tr>
            <td class="product-name">Basic Box × 1</td>
            <td class="product-total">₹15.85</td>
        </tr>
    `;
    const subtotal = 15.85;
    const gst = subtotal * (GST_RATE / 100);
    const delivery = 2.00;
    const packing = PACKING_CHARGE;
    const total = subtotal + gst + delivery + packing;
    updateSummaryUI(subtotal, delivery, gst, total, packing);
}

document.addEventListener('DOMContentLoaded', () => {
    loadCheckoutSummary();
    const redeemCheckbox = document.getElementById('redeem_loyalty_points');
    if (redeemCheckbox) {
        redeemCheckbox.addEventListener('change', () => {
            loadCheckoutSummary();
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
    const fullName = document.getElementById('billing_name').value.trim();
    const compiledName = fullName;
    document.getElementById('name').value = compiledName;

    // Dummy values for QR mode since address is not needed
    const street = "Dine In";
    const apartment = "";
    const city = "Restaurant";
    const state = "Local";
    const zip = "000000";
    const country = "India";
    
    let compiledAddress = street;
    
    const tableNum = localStorage.getItem('table_number');
    if (tableNum) {
        compiledAddress = `Table ${tableNum}`;
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

    // Bypass Razorpay entirely for QR checkout
    showLoaderOverlay();
    submitBackendOrder(compiledName, phone, compiledAddress, 'COD', 'cod');
};

async function submitBackendOrder(compiledName, phone, compiledAddress, paymentId, paymentMethod = 'cod') {
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

    const saveAddress = false;
    const savedAddressId = '';
    
    const street = "Dine In";
    const apartment = "";
    const city = "Restaurant";
    const state = "Local";
    const zip = "000000";
    const country = "India";
    const fullName = document.getElementById('billing_name').value.trim();
    const nameParts = fullName.split(' ');
    const firstName = nameParts[0] || 'Guest';
    const lastName = nameParts.slice(1).join(' ') || '';

    const data = {
        customer_name: compiledName,
        customer_phone: phone,
        delivery_address: compiledAddress,
        customer_email: 'guest@medusa.local',
        message: '',
        csrf_token: document.getElementById('csrf_token').value,
        razorpay_payment_id: paymentId,
        payment_method: paymentMethod,
        cart_items: cartItems,
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
        redeem_loyalty_points: false
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
                    window.location.href = `order-success.php?order_id=${result.order_id}`;
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
</script>

<!-- Premium Payment Loading Overlay -->
<div id="payment-loader-overlay" class="payment-loader-overlay">
    <div class="loader-content">
        <div class="logo-container-loader">
            <img src="assets/images/versace_logo.png" alt="Medusa Logo" class="loader-logo">
            <div class="logo-glow"></div>
            <div class="spinner-ring"></div>
        </div>
        <h2 class="loader-title">Payment Successful</h2>
        <p class="loader-subtitle" id="loader-status-text">Verifying payment details...</p>
        <div class="loader-progress-bar">
            <div class="loader-progress-fill" id="loader-progress-fill"></div>
        </div>
    </div>
</div>

</body>
</html>
