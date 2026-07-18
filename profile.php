<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  MEDUSA RESTAURANT — CUSTOMER ACCOUNT DASHBOARD
 *  Unified hub for profiles, orders, settings, rewards, and support.
 * ══════════════════════════════════════════════════════════════
 */
$settings_tabs = ['settings', 'security', 'feedback', 'support'];
$requested_tab = $_GET['tab'] ?? '';
$account_section = in_array($requested_tab, $settings_tabs, true) ? 'settings' : 'profile';
$is_settings_page = $account_section === 'settings';
$account_page_title = $is_settings_page ? 'Account Settings' : 'Profile';

require_once __DIR__ . '/api/config.php';
requireLogin();

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Fetch user profile info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$profile_pic = !empty($user['profile_pic']) && file_exists(__DIR__ . '/' . $user['profile_pic']) 
    ? htmlspecialchars($user['profile_pic']) 
    : '';

// Ensure user_liquor_quota table exists and fetch user's quotas
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
} catch (PDOException $ex) {}

// Ensure users table has dob and preferred_ambience
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `dob` DATE NULL DEFAULT NULL");
} catch (PDOException $ex) {}
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `preferred_ambience` VARCHAR(100) NULL DEFAULT NULL");
} catch (PDOException $ex) {}

$has_liquor_quota = false;
$user_liquor_quotas = [];
if ($user_id) {
    try {
        $stmt_quota = $pdo->prepare("SELECT * FROM user_liquor_quota WHERE user_id = ?");
        $stmt_quota->execute([$user_id]);
        $user_liquor_quotas = $stmt_quota->fetchAll(PDO::FETCH_ASSOC);
        if (count($user_liquor_quotas) > 0) {
            $has_liquor_quota = true;
        }
    } catch (PDOException $ex) {}
}
$phone = $user['phone'] ?? '';
$is_email_verified = $user['is_email_verified'] ?? 0;
$is_phone_verified = $user['is_phone_verified'] ?? 0;
$dob_raw = $user['dob'] ?? '';
$dob_display = !empty($dob_raw) ? date('d M Y', strtotime($dob_raw)) : 'Not Set';
$preferred_ambience = $user['preferred_ambience'] ?? '';

// Fetch settings
$settings_stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$settings_stmt->execute([$user_id]);
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'email_notifications'  => 1,
    'sms_notifications'    => 1,
    'promotional_offers'   => 1,
    'privacy_mode'         => 0,
    'language'             => 'en',
    'theme'                => 'dark',
    'two_factor_enabled'   => 0,
    'login_alerts'         => 1
];

// Fetch orders for history
try {
    // Auto-cancel active orders older than 12 hours
$twelveHoursAgo = date('Y-m-d H:i:s', time() - 12 * 3600);
$auto_cancel = $pdo->prepare("UPDATE orders SET order_status = 'cancelled', tracking_status = 'cancelled', cancellation_reason = 'System auto-cancelled: exceeded 12 hours limit' WHERE user_id = ? AND order_date < ? AND order_status NOT IN ('completed', 'delivered', 'cancelled')");
$auto_cancel->execute([$user_id, $twelveHoursAgo]);

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

// Fetch coupons
require_once __DIR__ . '/api/CouponService.php';
$userCoupons = [];
try {
    $couponService = new CouponService($pdo);
    $couponService->expireCoupons();
    $userCoupons = $couponService->getUserCoupons($user_id);
} catch (Exception $e) {
    // Ignore
}

// Calculate spent & loyalty rewards
$total_spent = 0;
$completed_count = 0;
$active_count = 0;
$tier_spend = 0;
foreach ($orders as $o) {
    if ($o['order_status'] !== 'cancelled') {
        $tier_spend += floatval($o['total_amount']);
    }
    if ($o['order_status'] === 'completed') {
        $total_spent += floatval($o['total_amount']);
        $completed_count++;
    } elseif ($o['order_status'] !== 'cancelled') {
        $active_count++;
    }
}

$all_tiers_stmt = $pdo->query("SELECT * FROM customer_tiers ORDER BY spending_requirement ASC");
$all_tiers_data = $all_tiers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's current loyalty tier
$tier_stmt = $pdo->prepare("
    SELECT t.id as tier_id, t.tier_name, t.discount_percent 
    FROM users u 
    LEFT JOIN customer_tiers t ON u.current_tier_id = t.id 
    WHERE u.id = ?
");
$tier_stmt->execute([$user_id]);
$tier_info = $tier_stmt->fetch(PDO::FETCH_ASSOC);
if (!$tier_info) $tier_info = [];
$user_tier_id = intval($tier_info['tier_id'] ?? 1);
$user_tier_name = $tier_info['tier_name'] ?? 'Bronze';
$user_tier_discount = floatval($tier_info['discount_percent'] ?? 10.00);

// Fetch points balance and statistics
$pts_stmt = $pdo->prepare("SELECT * FROM reward_points WHERE user_id = ?");
$pts_stmt->execute([$user_id]);
$reward_points_row = $pts_stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'points_earned' => 0,
    'points_redeemed' => 0,
    'points_deducted' => 0,
    'current_balance' => 0
];
$points_balance = intval($reward_points_row['current_balance']);
$loyalty_points = $points_balance; // backward compatibility

// Fetch notifications log
$notif_stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC");
$notif_stmt->execute([$user_id]);
$user_notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch support ticket requests
$support_stmt = $pdo->prepare("SELECT * FROM support_requests WHERE user_id = ? ORDER BY created_at DESC");
$support_stmt->execute([$user_id]);
$support_tickets = $support_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch feedback submissions
$fb_stmt = $pdo->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC");
$fb_stmt->execute([$user_id]);
$feedbacks = $fb_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch table reservations
$res_stmt = $pdo->prepare("SELECT * FROM table_bookings WHERE user_id = ? ORDER BY booking_date DESC, booking_time DESC");
$res_stmt->execute([$user_id]);
$user_reservations = $res_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get the closest upcoming reservation
$upcoming_reservation = null;
$upcoming_stmt = $pdo->prepare("SELECT * FROM table_bookings WHERE user_id = ? AND booking_date >= CURDATE() ORDER BY booking_date ASC, booking_time ASC LIMIT 1");
$upcoming_stmt->execute([$user_id]);
$upcoming_reservation = $upcoming_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch login activity logs
$login_logs_stmt = $pdo->prepare("SELECT * FROM login_activity_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 6");
$login_logs_stmt->execute([$user_id]);
$login_logs = $login_logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch trusted devices (successful, unrevoked unique IP/UA sessions)
$trusted_devices_stmt = $pdo->prepare("
    SELECT MAX(id) as id, ip_address, user_agent, MAX(login_time) as last_used 
    FROM login_activity_logs 
    WHERE user_id = ? AND status = 'success' AND (revoked IS NULL OR revoked = 0)
    GROUP BY ip_address, user_agent 
    ORDER BY last_used DESC 
    LIMIT 5
");
$trusted_devices_stmt->execute([$user_id]);
$trusted_devices = $trusted_devices_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        background-color: #F9F6F0;
        z-index: 999999;
        opacity: 1;
        transition: opacity 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: all;
    }
    #nav-page-transition.nav-pt-fadeout {
        opacity: 0 !important;
        pointer-events: none !important;
    }
    .bg-card-custom { background-color: #ffffff !important; color: #333333 !important; border-color: rgba(0,0,0,0.1) !important; }
    .text-light { color: #F9F6F0 !important; }
    .text-muted { color: #6c757d !important; }
</style>

<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Medusa Luxury Restaurant - Customer Account Dashboard">
    <title><?php echo htmlspecialchars($account_page_title); ?> - Medusa Luxury</title>
    <!-- Global Theme Controller -->
    <script src="assets/js/theme-toggle.js"></script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-dark: #F9F6F0;
            --bg-secondary: #ffffff;
            --bg-sidebar: #143628;
            --bg-header: #C09B5B;
            --bg-card: #220a11;
            --gold: #C09B5B;
            --gold-light: #d6b883;
            --gold-dark: #a17c40;
            --text-light: #F9F6F0;
            --text-muted: #a09f9f;
            --white: #ffffff;
            --gray: #a09f9f;
            --border-glass: rgba(192, 155, 91, 0.2);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        /* Dashboard Toggle Buttons */
        .dashboard-toggle-buttons {
            display: flex;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-glass);
            border-radius: 8px;
            padding: 4px;
            margin: 1.5rem 1rem;
            gap: 4px;
        }

        .btn-dashboard-toggle {
            flex: 1;
            background: transparent;
            border: none;
            color: var(--gray);
            padding: 8px 12px;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-dashboard-toggle:hover {
            color: var(--white);
            background: rgba(255, 255, 255, 0.05);
        }

        .btn-dashboard-toggle.active {
            color: var(--bg-dark);
            background: var(--gold);
            font-weight: 600;
        }

        /* Tier Badges styling */
        .tier-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .tier-bronze {
            background: linear-gradient(135deg, #cd7f32, #8c521f);
            color: #ffffff;
            border: 1px solid rgba(205, 127, 50, 0.4);
        }
        .tier-silver {
            background: linear-gradient(135deg, #bdc3c7, #2c3e50);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .tier-gold {
            background: linear-gradient(135deg, #f39c12, #d35400);
            color: #ffffff;
            border: 1px solid rgba(243, 156, 18, 0.3);
        }

        /* Loyalty Status Dashboard Layout */
        .loyalty-progress-container {
            background: var(--bg-secondary);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .loyalty-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .loyalty-progress-bar-bg {
            height: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 5px;
            overflow: hidden;
            position: relative;
            margin-bottom: 1rem;
        }
        .loyalty-progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--gold-dark), var(--gold));
            border-radius: 5px;
            width: 0%;
            transition: width 1s ease-out;
        }
        .loyalty-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .loyalty-stat-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-glass);
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            transition: var(--transition);
        }
        .loyalty-stat-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(223, 186, 134, 0.25);
        }
        .loyalty-stat-val {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--gold);
            margin-top: 0.5rem;
        }
        .loyalty-stat-label {
            font-size: 0.8rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Notifications styling */
        .notif-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .notif-item {
            background: var(--bg-secondary);
            border: 1px solid var(--border-glass);
            border-radius: 10px;
            padding: 1.25rem;
            position: relative;
            transition: var(--transition);
        }
        .notif-item.unread {
            border-left: 3px solid var(--gold);
        }
        .notif-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .notif-item-title {
            font-weight: 600;
            color: var(--gold-light);
            font-size: 1rem;
        }
        .notif-item-date {
            font-size: 0.75rem;
            color: var(--gray);
        }
        .notif-item-msg {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.4;
        }

        body {
            background-color: var(--bg-dark);
            color: #3f1519;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Luxury Top Header Bar */
        .luxury-navbar {
            background-color: #3f1519;
            border-bottom: 1px solid var(--border-glass);
            padding: 1.2rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--bg-dark) !important;
            font-weight: 700;
            letter-spacing: 1px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-link-custom {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-link-custom:hover {
            color: var(--gold);
        }

        /* Layout Grid */
        .dashboard-wrapper {
            width: 100%;
            margin: 0;
            padding: 0;
            flex: 1;
            display: flex;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            width: 100%;
            gap: 0;
        }

        @media (max-width: 991px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar Container */
        .sidebar-container {
            background-color: var(--bg-sidebar);
            border: none;
            border-radius: 0;
            padding: 3rem 1.5rem;
            min-height: calc(100vh - 80px); /* Adjust based on header height */
            box-shadow: 2px 0 20px rgba(0,0,0,0.05);
        }

        /* Profile Summary */
        .profile-summary {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 1.5rem;
        }

        .avatar-uploader {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem auto;
            border-radius: 50%;
            border: 2px solid var(--gold);
            padding: 3px;
            background: #000;
            overflow: hidden;
            cursor: pointer;
        }

        .avatar-uploader img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(223, 186, 134, 0.1);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-family: 'Playfair Display', serif;
            font-weight: 700;
        }

        .avatar-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: var(--transition);
            border-radius: 50%;
        }

        .avatar-uploader:hover .avatar-overlay {
            opacity: 1;
        }

        .avatar-overlay i {
            color: var(--gold);
            font-size: 1.25rem;
        }

        .profile-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 0.25rem 0;
        }

        .profile-email {
            font-size: 0.82rem;
            color: var(--gray);
            word-break: break-all;
        }

        /* Sidebar Nav Buttons */
        .sidebar-menu .nav-link {
            width: 100%;
            text-align: left;
            padding: 1rem 1.2rem;
            color: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 500;
            border: 1px solid transparent;
            margin-bottom: 0.5rem;
            transition: var(--transition);
            background: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-menu .nav-link i {
            font-size: 1rem;
            width: 18px;
            text-align: center;
        }

        .sidebar-menu .nav-link:hover {
            color: var(--gold);
            background: rgba(223, 186, 134, 0.04);
            border-color: rgba(223, 186, 134, 0.08);
        }

        .sidebar-menu .nav-link.active {
            color: var(--white);
            background: #4A151D;
            border-color: transparent;
            font-weight: 600;
        }

        /* Main Content Container */
        .main-content {
            background-color: transparent;
            border: none;
            border-radius: 0;
            padding: 3rem 4rem;
            box-shadow: none;
            animation: fadeIn 0.5s ease-out;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #3f1519;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--gold);
            font-size: 1.5rem;
        }

        /* Inputs & Forms styling */
        .form-control-medusa {
            background-color: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #3f1519 !important;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: var(--transition);
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.02);
        }

        .form-control-medusa:focus {
            background-color: #ffffff;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(192, 155, 91, 0.15);
            outline: none;
        }

        .form-label-medusa {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
        }

        .btn-gold-medusa {
            background-color: var(--bg-header);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.8rem;
            font-weight: 600;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-gold-medusa:hover {
            background-color: #381016;
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(74, 21, 29, 0.2);
        }

        .btn-outline-medusa {
            background-color: transparent;
            border: 1px solid var(--gold);
            color: var(--gold);
            border-radius: 8px;
            padding: 0.75rem 1.8rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-outline-medusa:hover {
            background-color: var(--gold);
            color: #000000;
            transform: translateY(-1px);
        }

        /* Order Cards in History */
        .order-card {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.2rem;
            transition: var(--transition);
        }

        .order-card:hover {
            border-color: rgba(223, 186, 134, 0.25);
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            gap: 8px;
        }

        .order-number {
            font-weight: 700;
            color: var(--gold);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.74rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-pending { background: rgba(255,193,7,0.1); color: #ffc107; border: 1px solid rgba(255,193,7,0.2); }
        .status-preparing { background: rgba(13,110,253,0.1); color: #0d6efd; border: 1px solid rgba(13,110,253,0.2); }
        .status-ready { background: rgba(25,135,84,0.1); color: #2ecc71; border: 1px solid rgba(25,135,84,0.2); }
        .status-completed { background: rgba(223,186,134,0.1); color: var(--gold); border: 1px solid rgba(223,186,134,0.2); }
        .status-cancelled { background: rgba(220,53,69,0.1); color: #ff6b6b; border: 1px solid rgba(220,53,69,0.2); }

        /* Loyalty Badge */
        .tier-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }

        .tier-bronze { background: linear-gradient(135deg, #8c5230, #c68a4c); color: #fff; }
        .tier-silver { background: linear-gradient(135deg, #7f8c8d, #bdc3c7); color: #000; }
        .tier-gold { background: linear-gradient(135deg, #8B6914, #E8D5B0); color: #000; }

        /* Security Strength meter */
        .strength-bar { display: flex; gap: 4px; margin-top: 5px; }
        .seg { flex: 1; height: 4px; border-radius: 2px; background: rgba(255,255,255,0.1); transition: var(--transition); }
        .seg.weak { background: #ff6b6b; }
        .seg.fair { background: #f39c12; }
        .seg.good { background: #2ecc71; }
        .seg.strong { background: var(--gold); }

        /* Verification badges */
        .verification-tag {
            font-size: 0.72rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            margin-left: 8px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }
        .tag-verified { background: rgba(46,204,113,0.15); color: #2ecc71; border: 1px solid rgba(46,204,113,0.2); }
        .tag-pending { background: rgba(230,126,34,0.15); color: #e67e22; border: 1px solid rgba(230,126,34,0.2); cursor: pointer; }

        /* Accordion Customization */
        .accordion-item-medusa {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border-glass);
            border-radius: 8px !important;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }

        .accordion-button-medusa {
            background: transparent !important;
            color: #fff !important;
            font-weight: 600;
            padding: 1.1rem;
            border: none !important;
            box-shadow: none !important;
        }

        .accordion-button-medusa:not(.collapsed) {
            color: var(--gold) !important;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .accordion-body-medusa {
            padding: 1.2rem;
            color: var(--gray);
            line-height: 1.6;
            font-size: 0.92rem;
        }

        /* Star Rating */
        .star-rating {
            display: flex;
            gap: 8px;
            font-size: 1.8rem;
            cursor: pointer;
            margin-bottom: 1rem;
        }

        .star-rating i {
            color: rgba(0,0,0,0.15);
            transition: var(--transition);
        }

        .star-rating i.active {
            color: var(--gold);
            text-shadow: 0 0 10px rgba(223,186,134,0.3);
        }

        /* Toast notifications */
        .medusa-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #212529;
            border: 1px solid var(--gold);
            border-radius: 8px;
            padding: 1rem 1.5rem;
            color: #ffffff !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 12px;
            animation: slideInUp 0.3s ease;
        }

        /* Custom Scrollbar for modern feel */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: rgba(223, 186, 134, 0.2); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(223, 186, 134, 0.4); }

        @keyframes slideInUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Settings Sub-Navigation */
        .settings-subnav {
            display: flex;
            gap: 2.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            flex-wrap: wrap;
            padding-bottom: 2px;
        }
        .settings-subnav .nav-link {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray);
            padding: 0.5rem 0;
            border: none;
            background: transparent;
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .settings-subnav .nav-link i {
            font-size: 0.9rem;
        }
        .settings-subnav .nav-link.active {
            color: var(--bg-header);
        }
        .settings-subnav .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--bg-header);
        }

        /* Settings Action Cards */
        .settings-action-card {
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 16px;
            padding: 1.5rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }
        .settings-action-card:hover {
            box-shadow: 0 10px 20px rgba(0,0,0,0.02);
            border-color: rgba(0,0,0,0.08);
        }
        .settings-icon-container {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(74, 21, 29, 0.05); /* very light maroon */
            color: var(--bg-header);
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
        .danger-zone-card {
            background-color: #fcf6f6;
            border: 1px solid #f8e5e5;
        }
        .danger-zone-icon {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
    </style>
    <?php if ($settings['language'] !== 'en') { ?>
    <!-- Auto Google Translate Integration -->
    <script>
        document.cookie = "googtrans=/en/<?php echo htmlspecialchars($settings['language']); ?>; path=/";
        // Also set domain cookie to be safe
        document.cookie = "googtrans=/en/<?php echo htmlspecialchars($settings['language']); ?>; domain=" + window.location.hostname + "; path=/";
    </script>
    <style>
        /* Hide the Google Translate UI frame and body top-padding */
        .VIpgJd-ZVi9od-ORHb-OEVmcd { display: none !important; }
        .goog-te-banner-frame { display: none !important; }
        body { top: 0 !important; }
        #google_translate_element { display: none !important; }
        /* Prevent translation styling glitches */
        font { background: transparent !important; color: inherit !important; box-shadow: none !important; }
    </style>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
    <script type="text/javascript">
        function googleTranslateElementInit() {
          new google.translate.TranslateElement({pageLanguage: 'en', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <?php } else { ?>
    <!-- Clear translate cookie if English is selected -->
    <script>
        document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        document.cookie = "googtrans=; expires=Thu, 01 Jan 1970 00:00:00 UTC; domain=" + window.location.hostname + "; path=/;";
    </script>
    <?php } ?>

    <!-- Navbar Performance Optimization Links -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
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

    <!-- CRITICAL SPA PAGE TRANSITION CSS & SCRIPT -->
    <style>
        html, body { background-color: #F9F6F0; }
        #nav-page-transition {
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: #F9F6F0;
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

<div id="google_translate_element"></div>

    <!-- Luxury Top Header Bar -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <script src="assets/js/navbar.js" defer></script>

    <div class="dashboard-wrapper">
        <div class="dashboard-grid">
            
            <!-- Left Sidebar Navigation -->
            <aside class="sidebar-container">
                <div class="profile-summary">
                    <div class="avatar-uploader" onclick="document.getElementById('profile_pic_input').click()">
                        <?php if ($profile_pic): ?>
                            <img id="avatar-img" src="<?php echo $profile_pic; ?>" alt="Profile Picture">
                        <?php else: ?>
                            <div id="avatar-placeholder" class="avatar-placeholder">
                                <?php 
                                    $parts = explode(' ', $user_name);
                                    $initials = '';
                                    foreach($parts as $p) {
                                        $initials .= strtoupper(substr($p, 0, 1));
                                    }
                                    echo htmlspecialchars(substr($initials, 0, 2));
                                ?>
                            </div>
                        <?php endif; ?>
                        <div class="avatar-overlay">
                            <i class="fa-solid fa-camera"></i>
                        </div>
                    </div>
                    <input type="file" id="profile_pic_input" accept="image/*" style="display: none;" onchange="handleProfilePicUpload(event)">
                    
                    <h4 class="profile-name"><?php echo htmlspecialchars($user_name); ?></h4>
                    <div class="profile-email"><?php echo htmlspecialchars($user_email); ?></div>
                    
                    <div class="mt-2" style="color: #b8973a !important;">
                        <i class="fa-solid fa-crown me-1"></i><?php echo htmlspecialchars($user_tier_name); ?> Member
                    </div>
                </div>
                
                <!-- Dashboard Toggle Buttons -->
                <div class="dashboard-toggle-buttons" style="display: none;">
                    <button class="btn-dashboard-toggle <?php echo !$is_settings_page ? 'active' : ''; ?>" id="btn-toggle-profile" onclick="switchDashboardMode('profile')">
                        <i class="fa-solid fa-id-card"></i> Profile Hub
                    </button>
                    <button class="btn-dashboard-toggle <?php echo $is_settings_page ? 'active' : ''; ?>" id="btn-toggle-settings" onclick="switchDashboardMode('settings')">
                        <i class="fa-solid fa-gears"></i> Settings Hub
                    </button>
                </div>
                
                <nav class="sidebar-menu nav flex-column nav-pills" role="tablist">
                    <?php if (!$is_settings_page): ?>
                    <!-- Profile Dashboard Group -->
                    <button class="nav-link dashboard-pill-profile" id="pill-profile-tab" data-bs-target="#pill-profile" type="button" role="tab">
                        <i class="fa-regular fa-user"></i> Profile Overview
                    </button>
                    <button class="nav-link dashboard-pill-profile" id="pill-orders-tab" data-bs-target="#pill-orders" type="button" role="tab">
                        <i class="fa-solid fa-receipt"></i> Order History
                    </button>
                    <button class="nav-link dashboard-pill-profile" id="pill-reservations-tab" data-bs-target="#pill-reservations" type="button" role="tab">
                        <i class="fa-regular fa-calendar-check"></i> Table Reservations
                    </button>
                    <button class="nav-link dashboard-pill-profile" id="pill-loyalty-tab" data-bs-target="#pill-loyalty" type="button" role="tab">
                        <i class="fa-solid fa-crown"></i> My Tier & Rewards
                    </button>
                    <button class="nav-link dashboard-pill-profile" id="pill-coupons-tab" data-bs-target="#pill-coupons" type="button" role="tab">
                        <i class="fa-solid fa-gift"></i> Coupons & Rewards
                    </button>
                    <?php if ($has_liquor_quota): ?>
                    <button class="nav-link dashboard-pill-profile" id="pill-quota-tab" data-bs-target="#pill-quota" type="button" role="tab">
                        <i class="fa-solid fa-wine-bottle"></i> Liquor Quota
                    </button>
                    <?php endif; ?>
                    <button class="nav-link dashboard-pill-profile" id="pill-membership-tab" data-bs-target="#pill-membership" type="button" role="tab">
                        <i class="fa-solid fa-id-badge"></i> Membership Pass
                    </button>
                    <button class="nav-link dashboard-pill-profile" id="pill-notifications-tab" data-bs-target="#pill-notifications" type="button" role="tab">
                        <i class="fa-solid fa-bell"></i> Notification
                    </button>
                    <!-- Hidden tab button for programmatic switching to Terms -->
                    <button id="pill-terms-tab" data-bs-target="#pill-terms" type="button" role="tab" style="display: none;"></button>
                    <?php else: ?>
                    <!-- Settings Dashboard Group -->
                    <button class="nav-link dashboard-pill-settings" id="pill-settings-tab" data-bs-target="#pill-settings" type="button" role="tab">
                        <i class="fa-solid fa-sliders"></i> Account Settings
                    </button>
                    <button class="nav-link dashboard-pill-settings" id="pill-security-tab" data-bs-target="#pill-security" type="button" role="tab">
                        <i class="fa-solid fa-shield-halved"></i> Security & Sessions
                    </button>
                    <button class="nav-link dashboard-pill-settings" id="pill-feedback-tab" data-bs-target="#pill-feedback" type="button" role="tab">
                        <i class="fa-solid fa-star"></i> Customer Feedback
                    </button>
                    <button class="nav-link dashboard-pill-settings" id="pill-support-tab" data-bs-target="#pill-support" type="button" role="tab">
                        <i class="fa-solid fa-headset"></i> Support & Help
                    </button>
                    <?php endif; ?>
                </nav>
            </aside>
            <script>
            (function() {
                // Synchronously set the active class on the correct sidebar button to prevent flicker
                const accountSection = '<?php echo htmlspecialchars($account_section, ENT_QUOTES); ?>';
                const params = new URLSearchParams(window.location.search);
                let requested = (window.location.hash && window.location.hash.startsWith('#pill-')) ? window.location.hash + '-tab' : null;
                if (!requested) {
                    const t = params.get('tab');
                    if (t) requested = '#pill-' + t + '-tab';
                }
                if (params.get('edit') === '1') requested = '#pill-profile-tab';
                
                const stored = localStorage.getItem('medusa_active_pill') || localStorage.getItem('medusaActiveTab:' + accountSection) || localStorage.getItem('medusaActiveTab');
                const defaultTab = accountSection === 'settings' ? '#pill-settings-tab' : '#pill-profile-tab';
                const activeId = requested || stored || defaultTab;
                
                // Add active class immediately so it paints correctly on first render
                const btn = document.querySelector(activeId);
                if (btn) btn.classList.add('active');
                else {
                    const defBtn = document.querySelector(defaultTab);
                    if (defBtn) defBtn.classList.add('active');
                }

                // Also activate the correct pane synchronously to prevent Bootstrap show() from ignoring it
                const defaultPaneId = accountSection === 'settings' ? '#pill-settings' : '#pill-profile';
                const activePaneId = activeId.replace('-tab', '');
                
                if (activePaneId !== defaultPaneId) {
                    const defPane = document.querySelector(defaultPaneId);
                    if (defPane) {
                        defPane.classList.remove('show');
                        defPane.classList.remove('active');
                    }
                    const activePane = document.querySelector(activePaneId);
                    if (activePane) {
                        activePane.classList.add('show');
                        activePane.classList.add('active');
                    }
                }
            })();
            </script>
            
            <!-- Prevent tab flicker -->
            <style id="prevent-flicker">
                .main-content { visibility: hidden; opacity: 0; transition: opacity 0.2s ease-in; }
            </style>

            <!-- Right Main Panels -->
            <main class="main-content tab-content">
                <?php if (!$is_settings_page): ?>
                
                <!-- ══ TAB 1: PROFILE DETAILS ══ -->
                <div class="tab-pane fade show active" id="pill-profile" role="tabpanel">
                    <!-- Welcome Header & Rewards Card -->
                    <div class="row mb-5 align-items-center">
                        <div class="col-md-6 mb-4 mb-md-0">
                            <p class="text-muted mb-1" style="font-size: 0.9rem;">Welcome back,</p>
                            <h1 class="display-4 mb-3" style="font-family: 'Playfair Display', serif; color: var(--bg-header);"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></h1>
                            <p class="text-muted" style="font-size: 0.85rem; max-width: 250px;">Manage your account details and enjoy exclusive experiences.</p>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 rounded-4 text-dark" style="background-color: var(--bg-sidebar); color: #fff !important; box-shadow: 0 10px 25px rgba(20, 54, 40, 0.2);">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fa-solid fa-crown text-gold me-2"></i>
                                    <span class="text-uppercase tracking-widest text-gold" style="font-size: 0.75rem; letter-spacing: 1px;">Medusa Rewards</span>
                                </div>
                                <h4 class="mb-1 text-white" style="font-family: 'Playfair Display', serif;"><?php echo htmlspecialchars($user_tier_name); ?> Member</h4>
                                <p class="mb-4 text-white" style="color: rgba(255, 255, 255, 0.8) !important; font-size: 0.85rem;">You have <?php echo number_format($loyalty_points); ?> points</p>
                                <a href="javascript:void(0)" onclick="goToProfileTab('loyalty');" style="color: #0d6efd; font-size: 0.85rem; font-weight: 500; text-decoration: none;">View Rewards &rarr;</a>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.4rem;">
                            <i class="fa-regular fa-user me-2"></i> Personal Information
                        </h3>
                        <button type="button" id="btn-edit-profile" class="btn btn-link text-dark text-decoration-none p-0" onclick="document.getElementById('profile-view').style.display='none'; document.getElementById('profile-edit').style.display='block';" style="font-size: 0.85rem; font-weight: 500;">
                            Edit Profile <i class="fa-solid fa-pencil ms-1" style="font-size: 0.75rem;"></i>
                        </button>
                    </div>

                    <!-- Static View -->
                    <div id="profile-view" class="bg-card-custom p-4 rounded-4 border mb-5" style="border-color: rgba(0,0,0,0.05) !important;">
                        <div class="row justify-content-center">
                            <div class="col-md-4 p-3 border-bottom border-end">
                                <label class="text-muted text-uppercase d-block mb-1" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">Full Name</label>
                                <div id="view-profile-name" class="text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($user_name); ?></div>
                            </div>
                            <div class="col-md-4 p-3 border-bottom border-end">
                                <label class="text-muted text-uppercase d-block mb-1" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">Mobile Number</label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($phone); ?></div>
                                    <?php if ($is_phone_verified): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success" style="font-weight: 500; font-size: 0.7rem;">Verified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 p-3 border-bottom">
                                <label class="text-muted text-uppercase d-block mb-1" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">Membership Tier</label>
                                <div class="text-dark d-flex align-items-center gap-2" style="font-size: 0.95rem; font-weight: 500;">
                                    <i class="fa-solid fa-crown text-gold"></i> <?php echo htmlspecialchars($user_tier_name); ?> Member
                                </div>
                                <div class="text-muted mt-1" style="font-size: 0.75rem;">Member since May 2024</div>
                            </div>
                            
                            <div class="col-md-4 p-3 border-end">
                                <label class="text-muted text-uppercase d-block mb-1" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">Email Address</label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($user_email); ?></div>
                                    <?php if ($is_email_verified): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success" style="font-weight: 500; font-size: 0.7rem;">Verified</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 p-3 <?php echo empty($preferred_ambience) ? '' : 'border-end'; ?>" id="view-profile-dob-container">
                                <label class="text-muted text-uppercase d-block mb-1" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">Date of Birth</label>
                                <div class="text-dark d-flex justify-content-between align-items-center" style="font-size: 0.95rem;">
                                    <span id="view-profile-dob"><?php echo htmlspecialchars($dob_display); ?></span> <i class="fa-regular fa-calendar text-muted"></i>
                                </div>
                            </div>
                            <div class="col-md-4 p-3" id="view-profile-ambience-container" style="<?php echo empty($preferred_ambience) ? 'display: none;' : ''; ?>">
                                <label class="text-muted text-uppercase d-block mb-1" style="font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;">Preferred Ambience</label>
                                <div id="view-profile-ambience" class="text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($preferred_ambience); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Form (Hidden initially) -->
                    <div id="profile-edit" class="bg-card-custom p-4 rounded-4 border mb-5" style="border-color: rgba(0,0,0,0.05) !important; display: none;">
                        <form id="profileForm" onsubmit="submitProfileForm(event)">
                            <div class="mb-4">
                                <label class="form-label-medusa" for="profile_name">Full Name *</label>
                                <input type="text" id="profile_name" class="form-control form-control-medusa" value="<?php echo htmlspecialchars($user_name); ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label-medusa" for="profile_email">
                                    Email Address 
                                    <?php if (empty($user_email)): ?>
                                        <span class="verification-tag tag-pending"><i class="fa-solid fa-circle-info"></i> Not Provided</span>
                                    <?php elseif ($is_email_verified): ?>
                                        <span class="verification-tag tag-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="verification-tag tag-pending" onclick="sendOTP('email')"><i class="fa-solid fa-triangle-exclamation"></i> Verify email</span>
                                    <?php endif; ?>
                                </label>
                                <div class="input-group">
                                    <input type="email" id="profile_email" class="form-control form-control-medusa" value="<?php echo htmlspecialchars($user_email); ?>">
                                    <button type="button" id="btn-verify-email" class="btn btn-outline-medusa" style="display: none;" onclick="sendOTP('email')">Verify</button>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label-medusa" for="profile_phone">
                                    Mobile Number
                                    <?php if ($is_phone_verified): ?>
                                        <span class="verification-tag tag-verified"><i class="fa-solid fa-circle-check"></i> Verified</span>
                                    <?php else: ?>
                                        <span class="verification-tag tag-pending" onclick="sendOTP('phone')"><i class="fa-solid fa-triangle-exclamation"></i> Verify phone</span>
                                    <?php endif; ?>
                                </label>
                                <div class="input-group">
                                    <input type="tel" id="profile_phone" class="form-control form-control-medusa" value="<?php echo htmlspecialchars($phone); ?>" maxlength="10">
                                    <button type="button" id="btn-verify-phone" class="btn btn-outline-medusa" style="display: none;" onclick="sendOTP('phone')">Verify</button>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-medusa" for="profile_dob">Date of Birth</label>
                                    <input type="date" id="profile_dob" class="form-control form-control-medusa" value="<?php echo htmlspecialchars($dob_raw); ?>">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label-medusa" for="profile_ambience">Preferred Ambience</label>
                                    <select id="profile_ambience" class="form-select form-control-medusa">
                                        <option value="" <?php echo empty($preferred_ambience) ? 'selected' : ''; ?>>Not Selected</option>
                                        <option value="Lounge, Live Music" <?php echo $preferred_ambience === 'Lounge, Live Music' ? 'selected' : ''; ?>>Lounge, Live Music</option>
                                        <option value="Quiet Dining" <?php echo $preferred_ambience === 'Quiet Dining' ? 'selected' : ''; ?>>Quiet Dining</option>
                                        <option value="Outdoor/Patio" <?php echo $preferred_ambience === 'Outdoor/Patio' ? 'selected' : ''; ?>>Outdoor/Patio</option>
                                        <option value="Private Room" <?php echo $preferred_ambience === 'Private Room' ? 'selected' : ''; ?>>Private Room</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex gap-3 mt-4">
                                <button type="submit" class="btn-gold-medusa">Save Changes</button>
                                <button type="button" class="btn btn-outline-dark" onclick="document.getElementById('profile-edit').style.display='none'; document.getElementById('profile-view').style.display='block';">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <!-- Statistics Summary Bar -->
                    <div class="rounded-4 p-4 mb-5" style="background-color: #4A151D; color: var(--white);">
                        <div class="row text-center text-md-start">
                            <div class="col-6 col-md-3 mb-3 mb-md-0 d-flex align-items-center justify-content-center justify-content-md-start gap-3 border-end border-dark border-opacity-25">
                                <i class="fa-regular fa-star text-gold" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h4 class="mb-0 text-white" style="font-weight: 700; font-size: 1.2rem;"><?php echo number_format($loyalty_points); ?></h4>
                                    <span style="font-size: 0.75rem; opacity: 0.8;">Total Points</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-3 mb-md-0 d-flex align-items-center justify-content-center justify-content-md-start gap-3 border-end-md border-dark border-opacity-25 ps-md-4">
                                <i class="fa-regular fa-calendar text-gold" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h4 class="mb-0 text-white" style="font-weight: 700; font-size: 1.2rem;"><?php echo count($user_reservations); ?></h4>
                                    <span style="font-size: 0.75rem; opacity: 0.8;">Reservations</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 d-flex align-items-center justify-content-center justify-content-md-start gap-3 border-end border-dark border-opacity-25 ps-md-4">
                                <i class="fa-solid fa-bag-shopping text-gold" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h4 class="mb-0 text-white" style="font-weight: 700; font-size: 1.2rem;"><?php echo count($orders); ?></h4>
                                    <span style="font-size: 0.75rem; opacity: 0.8;">Orders</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 d-flex align-items-center justify-content-center justify-content-md-start gap-3 ps-md-4">
                                <i class="fa-solid fa-gift text-gold" style="font-size: 1.5rem;"></i>
                                <div>
                                    <h4 class="mb-0 text-white" style="font-weight: 700; font-size: 1.2rem;"><?php echo count($userCoupons); ?></h4>
                                    <span style="font-size: 0.75rem; opacity: 0.8;">Coupons</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Reservation -->
                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.2rem;">
                                <i class="fa-regular fa-calendar-check me-2"></i> Upcoming Reservation
                            </h4>
                            <a href="javascript:void(0)" onclick="goToProfileTab('reservations');" class="text-dark text-decoration-none" style="font-size: 0.85rem; font-weight: 500;">View All Reservations &rarr;</a>
                        </div>
                        <?php if ($upcoming_reservation): ?>
                        <div class="bg-card-custom p-4 rounded-4 border d-flex align-items-center gap-4 flex-wrap" style="border-color: rgba(0,0,0,0.05) !important;">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 150px; height: 100px; background-color: var(--bg-sidebar); color: #b8973a !important;">
                                <i class="fa-solid fa-wine-glass" style="font-size: 2.5rem;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex gap-3 text-muted mb-2" style="font-size: 0.8rem;">
                                    <span><i class="fa-regular fa-calendar me-1"></i> <?php echo date('D, d M Y', strtotime($upcoming_reservation['booking_date'])); ?></span>
                                    <span><i class="fa-regular fa-clock me-1"></i> <?php echo date('g:i A', strtotime($upcoming_reservation['booking_time'])); ?></span>
                                    <span><i class="fa-regular fa-user me-1"></i> <?php echo htmlspecialchars($upcoming_reservation['guests']); ?> Guests</span>
                                </div>
                                <h5 class="text-dark mb-1" style="font-size: 1rem;"><?php echo htmlspecialchars($upcoming_reservation['venue_name']); ?></h5>
                                <p class="text-muted m-0" style="font-size: 0.85rem;"><?php echo htmlspecialchars($upcoming_reservation['venue_address']); ?></p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success bg-opacity-10 text-success mb-3 d-inline-block text-uppercase" style="padding: 0.5rem 1rem;"><?php echo htmlspecialchars($upcoming_reservation['status']); ?></span><br>
                                <button class="btn-gold-medusa" onclick="goToProfileTab('reservations');" style="padding: 0.5rem 1.5rem; font-size: 0.8rem;">VIEW DETAILS</button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-card-custom p-4 rounded-4 border text-center" style="border-color: rgba(0,0,0,0.05) !important;">
                            <div class="mb-3">
                                <i class="fa-regular fa-calendar-xmark text-muted" style="font-size: 2.5rem; opacity: 0.5;"></i>
                            </div>
                            <h5 class="text-dark mb-2" style="font-size: 1rem;">No Upcoming Reservations</h5>
                            <p class="text-muted mb-3" style="font-size: 0.85rem;">You don't have any table reservations booked at the moment.</p>
                            <a href="book-table-test.html" class="btn-gold-medusa text-decoration-none d-inline-block" style="padding: 0.5rem 1.5rem; font-size: 0.8rem;">Book a Table</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Actions & Account Security -->
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h4 class="mb-3" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.2rem;">Quick Actions</h4>
                            <div class="bg-card-custom p-4 rounded-4 border d-flex justify-content-around text-center" style="border-color: rgba(0,0,0,0.05) !important;">
                                <a href="book-table-test.html" class="text-dark text-decoration-none action-icon">
                                    <div class="rounded-circle border d-flex align-items-center justify-content-center mx-auto mb-2 hover-gold-border" style="width: 50px; height: 50px;">
                                        <i class="fa-regular fa-calendar"></i>
                                    </div>
                                    <span style="font-size: 0.75rem;">Reserve</span>
                                </a>
                                <a href="menutest.html" class="text-dark text-decoration-none action-icon">
                                    <div class="rounded-circle border d-flex align-items-center justify-content-center mx-auto mb-2 hover-gold-border" style="width: 50px; height: 50px;">
                                        <i class="fa-solid fa-utensils"></i>
                                    </div>
                                    <span style="font-size: 0.75rem;">View Menu</span>
                                </a>
                                <a href="#" onclick="handleOrderNowClick(event)" class="text-dark text-decoration-none action-icon">
                                    <div class="rounded-circle border d-flex align-items-center justify-content-center mx-auto mb-2 hover-gold-border" style="width: 50px; height: 50px;">
                                        <i class="fa-solid fa-bag-shopping"></i>
                                    </div>
                                    <span style="font-size: 0.75rem;">Order Now</span>
                                </a>
                                <a href="#" onclick="showToast('Premium Gift Cards feature is launching soon!', 'info'); event.preventDefault();" class="text-dark text-decoration-none action-icon">
                                    <div class="rounded-circle border d-flex align-items-center justify-content-center mx-auto mb-2 hover-gold-border" style="width: 50px; height: 50px;">
                                        <i class="fa-solid fa-gift"></i>
                                    </div>
                                    <span style="font-size: 0.75rem;">Gift Cards</span>
                                </a>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <h4 class="mb-3" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.2rem;">
                                <i class="fa-solid fa-shield-halved me-2"></i> Account Security
                            </h4>
                            <div class="bg-card-custom p-4 rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                                <p class="text-muted mb-4" style="font-size: 0.85rem;">Keep your account safe and secure.</p>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted" style="font-size: 0.85rem; width: 100px;">Password</span>
                                    <span class="text-dark fw-bold flex-grow-1">••••••••••••</span>
                                    <a href="settings.php?tab=security" class="text-success text-decoration-none" style="font-size: 0.85rem; font-weight: 500;">Change</a>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 0.85rem; width: 100px;">Last Login</span>
                                    <span class="text-dark flex-grow-1" style="font-size: 0.85rem;">
                                        <?php echo !empty($login_logs) ? date('M d, Y • h:i A', strtotime($login_logs[0]['login_time'])) : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ TAB 2: ORDER HISTORY ══ -->
                <div class="tab-pane fade" id="pill-orders" role="tabpanel">
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.8rem;">
                                <i class="fa-solid fa-clock-rotate-left me-2" style="color: #d4af37;"></i> Order History
                            </h2>
                            <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem;">View and manage your past orders.</p>
                        </div>
                        <button class="btn btn-outline-dark" style="border-color: rgba(0,0,0,0.15); font-weight: 500; font-size: 0.9rem;">
                            <i class="fa-solid fa-download me-2 text-gold"></i> Export History
                        </button>
                    </div>
                    
                    <!-- Search & Filter Controls -->
                    <div class="row g-3 mb-4 bg-card-custom p-4 rounded-4 border align-items-end" style="border-color: rgba(0,0,0,0.05) !important;">
                        <div class="col-md-5">
                            <label class="form-label-medusa text-dark" style="font-weight: 500; font-size: 0.8rem;">Search Order</label>
                            <div class="input-group">
                                <span class="input-group-text bg-card-custom border-end-0" style="border-color: rgba(0,0,0,0.1);"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                                <input type="text" id="order-search" autocomplete="off" class="form-control form-control-medusa border-start-0 ps-0" placeholder="Enter Order Number..." oninput="filterOrders()" style="background-color: transparent; border-color: rgba(0,0,0,0.1); box-shadow: none;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-medusa text-dark" style="font-weight: 500; font-size: 0.8rem;">Filter By Status</label>
                            <select id="order-status-filter" class="form-select form-control-medusa" onchange="filterOrders()" style="background-color: transparent; border-color: rgba(0,0,0,0.1);">
                                <option value="all">All Orders</option>
                                <option value="pending">Pending</option>
                                <option value="preparing">Preparing</option>
                                <option value="ready">Ready to Serve</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn w-100 d-flex justify-content-center align-items-center" onclick="resetOrderFilters()" style="background-color: #1a3324; color: #e8ebe9; border-radius: 6px; padding: 0.6rem; font-weight: 500;">
                                <i class="fa-solid fa-arrows-rotate me-2" style="color: #d4a755;"></i> Reset Filters
                            </button>
                        </div>
                    </div>

                    <!-- Order Cards list -->
                    <div id="orders-list-container">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5 bg-card-custom rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                                <i class="fa-solid fa-utensils text-muted mb-3" style="font-size: 3rem; opacity: 0.4;"></i>
                                <h4 class="mb-2 text-dark" style="font-family: 'Playfair Display', serif;">No Orders Yet</h4>
                                <p class="text-muted mb-4" style="max-width: 380px; margin: 0 auto;">You haven't placed any fine dining orders yet.</p>
                                <a href="menutest.php" class="btn-gold-medusa">Browse Our Menu</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                    $status_class = 'bg-warning bg-opacity-25 text-warning';
                                    $status_label = 'PENDING';
                                    $status_icon = 'fa-solid fa-clock';
                                    $status_style = '';
                                    $icon_style = '';
                                    
                                    switch (strtolower($order['order_status'])) {
                                        case 'pending':
                                            $status_class = '';
                                            $status_style = 'background-color: #fceecb; color: #b48530; border: none; padding: 0.6rem 1.2rem;';
                                            $status_label = 'PENDING';
                                            $status_icon = 'fa-solid fa-clock';
                                            $icon_style = 'color: #f9c823; font-size: 0.9rem;';
                                            break;
                                        case 'preparing':
                                            $status_class = 'bg-primary bg-opacity-10 text-primary';
                                            $status_label = 'PREPARING';
                                            $status_icon = 'fa-solid fa-clock';
                                            break;
                                        case 'ready':
                                            $status_class = 'bg-info bg-opacity-10 text-info';
                                            $status_label = 'READY';
                                            $status_icon = 'fa-solid fa-bell';
                                            break;
                                        case 'out for delivery':
                                            $status_class = 'bg-info bg-opacity-10 text-info';
                                            $status_label = 'OUT FOR DELIVERY';
                                            $status_icon = 'fa-solid fa-motorcycle';
                                            break;
                                        case 'completed':
                                            $status_class = 'bg-success bg-opacity-10 text-success';
                                            $status_label = 'COMPLETED';
                                            $status_icon = 'fa-solid fa-check-circle';
                                            break;
                                        case 'delivered':
                                            $status_class = 'bg-success bg-opacity-10 text-success';
                                            $status_label = 'DELIVERED';
                                            $status_icon = 'fa-solid fa-check-double';
                                            $status_style = 'background-color: #e6f8f1; color: #0a5f38; border: none; padding: 0.6rem 1.2rem;';
                                            $icon_style = 'color: #108653; font-size: 0.9rem;';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'bg-danger bg-opacity-10 text-danger';
                                            $status_label = 'CANCELLED';
                                            $status_icon = 'fa-solid fa-times-circle';
                                            break;
                                    }
                                ?>
                                <div class="rounded-4 border mb-4 order-card" data-number="<?php echo htmlspecialchars($order['order_number']); ?>" data-status="<?php echo strtolower($order['order_status']); ?>" style="background-color: #FAF6F4; border-color: rgba(0,0,0,0.05) !important; box-shadow: 0 4px 15px rgba(0,0,0,0.02); overflow: hidden; position: relative;">
                                    
                                    <!-- Card Header -->
                                    <div class="d-flex justify-content-between align-items-center p-4 border-bottom" style="border-color: rgba(0,0,0,0.05) !important;">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="background-color: #143628; width: 48px; height: 48px;">
                                                <i class="fa-solid fa-bag-shopping" style="color: #d4af37; font-size: 1.2rem;"></i>
                                            </div>
                                            <div>
                                                <h4 class="mb-1" style="font-family: 'Playfair Display', serif; color: #5a2a35; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                                    Order #<?php echo htmlspecialchars($order['order_number']); ?>
                                                    <?php if (isset($order['order_type']) && strcasecmp($order['order_type'], 'takeaway') === 0): ?>
                                                        <span class="badge bg-warning text-white" style="font-size: 0.7rem; font-family: 'Plus Jakarta Sans', sans-serif;"><i class="fa-solid fa-shopping-bag"></i> Takeaway</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary text-white" style="font-size: 0.7rem; font-family: 'Plus Jakarta Sans', sans-serif;"><i class="fa-solid fa-truck"></i> Delivery</span>
                                                    <?php endif; ?>
                                                </h4>
                                                <div class="text-muted" style="font-size: 0.85rem;">
                                                    <i class="fa-regular fa-calendar me-1"></i> <?php echo date('d M Y, h:i A', strtotime($order['order_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge rounded-pill <?php echo $status_class; ?>" style="font-weight: 600; padding: 0.5rem 1rem; font-size: 0.75rem; letter-spacing: 0.5px; border: 1px solid rgba(0,0,0,0.05); <?php echo $status_style; ?>">
                                                <i class="<?php echo $status_icon; ?> me-1" style="<?php echo $icon_style; ?>"></i> <?php echo $status_label; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Card Body -->
                                    <div class="row g-0">
                                        <!-- Left Side: Items -->
                                        <div class="col-md-7 p-4 border-end" style="border-color: rgba(0,0,0,0.05) !important;">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <?php 
                                                $raw_img = !empty($item['image_url']) ? htmlspecialchars($item['image_url']) : 'assets/images/hero_steak.png'; 
                                                $img_src = (strpos($raw_img, 'http') === 0 || strpos($raw_img, '//') === 0 || strpos($raw_img, 'assets/') === 0 || strpos($raw_img, 'uploads/') === 0) ? $raw_img : 'uploads/' . $raw_img;
                                                ?>
                                                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom" style="border-color: rgba(0,0,0,0.03) !important;">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="rounded-3 overflow-hidden d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #143628;">
                                                            <img src="<?php echo $img_src; ?>" class="w-100 h-100" style="object-fit: cover;" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                                        </div>
                                                        <div>
                                                            <div class="text-dark" style="font-weight: 500; font-size: 0.95rem;"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                            <div class="text-muted" style="font-size: 0.85rem;"><?php echo $item['quantity']; ?> x</div>
                                                        </div>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.95rem;">
                                                        ₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (strtolower($order['order_status']) === 'cancelled' && !empty($order['cancellation_reason'])): ?>
                                                <div class="mt-3 p-3 rounded" style="background-color: rgba(220,53,69,0.05); border: 1px solid rgba(220,53,69,0.1); color: #dc3545; font-size: 0.85rem;">
                                                    <i class="fa-solid fa-circle-info me-2"></i><strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($order['cancellation_reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Right Side: Totals & Actions -->
                                        <div class="col-md-5 p-4 position-relative">
                                            <!-- Faint Watermark -->
                                            <div class="position-absolute" style="top: 50%; right: -80px; transform: translateY(-50%) scale(2.2); opacity: 0.05; pointer-events: none;">
                                                <img src="assets/images/medusaa2(onlylogo).png" width="250" alt="">
                                            </div>
                                            
                                            <div class="text-end position-relative h-100 d-flex flex-column justify-content-center" style="z-index: 1;">
                                                <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">Grand Total</div>
                                                <div class="mb-4" style="font-family: 'Playfair Display', serif; color: #5a2a35; font-size: 1.8rem; font-weight: 600;">
                                                    ₹<?php echo number_format($order['total_amount'], 2); ?>
                                                </div>
                                                
                                                <div class="d-flex justify-content-end gap-2 flex-wrap mt-auto">
                                                    <?php
                                                    $trk_token  = $order['tracking_token']  ?? null;
                                                    $trk_active = !in_array(strtolower($order['order_status']), ['completed','cancelled','delivered']) && $trk_token;
                                                    ?>
                                                    <?php if ($trk_active): ?>
                                                    <a href="track.php?token=<?php echo htmlspecialchars($trk_token); ?>" class="btn btn-sm text-gold" style="border: 1px solid var(--gold); background: transparent; font-weight: 500; border-radius: 6px; padding: 0.4rem 0.8rem;">
                                                        <i class="fa-solid fa-location-dot me-1"></i> Track Order
                                                    </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm" style="background-color: #5a2a35; color: white; font-weight: 500; border-radius: 6px; padding: 0.4rem 0.8rem;" onclick="reorderItems(<?php echo $order['id']; ?>)">
                                                        <i class="fa-solid fa-arrows-rotate me-1 text-dark-50"></i> Reorder
                                                    </button>
                                                    <a href="order-details.php?order_id=<?php echo urlencode($order['order_number']); ?>" class="btn btn-sm text-gold" style="border: 1px solid var(--gold); background: transparent; font-weight: 500; border-radius: 6px; padding: 0.4rem 0.8rem;">
                                                        <i class="fa-solid fa-file-invoice me-1"></i> Invoice
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ══ TAB: TABLE RESERVATIONS ══ -->
                <div class="tab-pane fade" id="pill-reservations" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <div>
                            <h2 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.8rem; font-weight: 600;">
                                <i class="fa-regular fa-calendar-check me-2" style="color: #d4af37;"></i> Table Reservations
                            </h2>
                            <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem;">Manage your upcoming and past dining experiences.</p>
                        </div>
                        <a href="book-table-test.html" class="btn text-white text-decoration-none" style="background-color: #3f1519; border-radius: 4px; padding: 0.5rem 1.5rem; font-weight: 500;">Book New Table</a>
                    </div>
                    
                    <div class="reservations-list">
                        <?php if (empty($user_reservations)): ?>
                            <div class="text-center py-5 bg-card-custom rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                                <i class="fa-regular fa-calendar-xmark text-muted mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
                                <h4 style="font-family: 'Playfair Display', serif;">No Reservations Found</h4>
                                <p class="text-muted mb-4">You haven't made any table reservations yet.</p>
                                <a href="book-table-test.html" class="btn-gold-medusa text-decoration-none">Explore & Book</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($user_reservations as $res): ?>
                                    <div class="col-12">
                                        <div class="bg-card-custom p-4 rounded-4 border d-flex align-items-center gap-4 flex-wrap" style="border-color: rgba(0,0,0,0.05) !important;">
                                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; background-color: var(--bg-sidebar); color: #b8973a !important;">
                                                <i class="fa-solid fa-wine-glass" style="font-size: 2.5rem;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex gap-3 text-muted mb-2" style="font-size: 0.85rem;">
                                                    <span><i class="fa-regular fa-calendar me-1 text-gold"></i> <?php echo date('D, d M Y', strtotime($res['booking_date'])); ?></span>
                                                    <span><i class="fa-regular fa-clock me-1 text-gold"></i> <?php echo date('g:i A', strtotime($res['booking_time'])); ?></span>
                                                    <span><i class="fa-regular fa-user me-1 text-gold"></i> <?php echo htmlspecialchars($res['guests']); ?> Guests</span>
                                                </div>
                                                <h4 class="text-dark mb-1" style="font-family: 'Playfair Display', serif; font-size: 1.2rem;"><?php echo htmlspecialchars($res['venue_name']); ?></h4>
                                                <p class="text-muted mb-2" style="font-size: 0.85rem;"><i class="fa-solid fa-location-dot me-1 text-muted"></i> <?php echo htmlspecialchars($res['venue_address']); ?></p>
                                                <?php if (!empty($res['table_number'])): ?>
                                                    <span class="badge text-dark border me-2" style="background-color: #f8f9fa;"><i class="fa-solid fa-chair me-1" style="color: #6c757d;"></i> Table/Zone: <?php echo htmlspecialchars($res['table_number']); ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($res['special_requests'])): ?>
                                                    <span class="badge text-dark border" style="background-color: #f8f9fa;"><i class="fa-solid fa-comment-dots me-1" style="color: #6c757d;"></i> Special Request Added</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end" style="min-width: 150px;">
                                                <?php 
                                                    $st = strtolower($res['status']);
                                                    $st_class = 'bg-secondary text-white';
                                                    if ($st === 'confirmed') $st_class = 'bg-success bg-opacity-10 text-success';
                                                    elseif ($st === 'cancelled') $st_class = 'bg-danger bg-opacity-10 text-danger';
                                                    elseif ($st === 'completed') $st_class = 'bg-primary bg-opacity-10 text-primary';
                                                    elseif ($st === 'pending') $st_class = 'bg-warning bg-opacity-10 text-warning';
                                                ?>
                                                <span class="badge <?php echo $st_class; ?> mb-3 d-inline-block text-uppercase" style="padding: 0.5rem 1rem; letter-spacing: 0.5px; border: 1px solid rgba(0,0,0,0.05);"><?php echo htmlspecialchars($res['status']); ?></span><br>
                                                
                                                <?php if ($res['booking_date'] >= date('Y-m-d') && $st !== 'cancelled'): ?>
                                                    <button class="btn-outline-medusa border text-dark w-100 mb-2 rounded-3" style="padding: 0.5rem; font-size: 0.85rem;" onclick="alert('Modification requests can be made by calling the concierge.')">Modify</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ══ TAB: MY TIER & REWARDS ══ -->
                <div class="tab-pane fade" id="pill-loyalty" role="tabpanel">
                    <?php
                    $next_tier_name = '';
                    $next_tier_req = 0;
                    $remaining_spend = 0;
                    $progress_percent = 100;
                    
                    // Fetch next tier dynamically
                    $next_tier_stmt = $pdo->prepare("SELECT tier_name, spending_requirement FROM customer_tiers WHERE id = ?");
                    $next_tier_stmt->execute([$user_tier_id + 1]);
                    $next_tier = $next_tier_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($next_tier) {
                        $next_tier_name = $next_tier['tier_name'];
                        $next_tier_req = floatval($next_tier['spending_requirement']);
                        $remaining_spend = max(0, $next_tier_req - $tier_spend);
                        $progress_percent = min(100, round(($tier_spend / max(1, $next_tier_req)) * 100));
                    }
                    ?>
                    
                    <div class="d-flex flex-column mb-5">
                        <h2 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.8rem; font-weight: 600;">
                            <i class="fa-solid fa-crown me-2" style="color: #d4af37;"></i> Loyalty Status & Rewards
                        </h2>
                        <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem;">Track your progress, points, and rewards.</p>
                    </div>
                    
                    <!-- Progress Card -->
                    <div class="rounded-4 border p-4 p-md-5 mb-5 position-relative" style="background-color: #fcfbf8; border-color: rgba(0,0,0,0.05) !important;">
                        <div class="row align-items-center">
                            <div class="col-md-7 border-end" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="d-flex align-items-center">
                                    <div class="me-4 flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle" style="width: 110px; height: 110px; background-color: #fff; border: 2px dashed #d4af37; box-shadow: 0 4px 10px rgba(0,0,0,0.02);">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 85px; height: 85px; background: linear-gradient(135deg, #e6e6e6, #f5f5f5);">
                                            <i class="fa-solid fa-crown" style="font-size: 2.5rem; color: #999;"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 pe-4">
                                        <p class="text-muted mb-1" style="font-size: 0.85rem; font-weight: 500;">Active Tier</p>
                                        <h3 class="mb-3" style="font-family: 'Playfair Display', serif; color: #5a2a35; font-size: 2rem; font-weight: 600;"><?php echo htmlspecialchars($user_tier_name); ?></h3>
                                        
                                        <div style="height: 8px; max-width: 300px; background-color: #e8e3d8; border-radius: 10px; overflow: hidden; margin-bottom: 0.5rem;">
                                            <div style="height: 100%; width: <?php echo $progress_percent; ?>%; background-color: #b48530; border-radius: 10px;"></div>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.8rem;">
                                            <i class="fa-solid fa-crown me-1 text-gold" style="color: #b48530;"></i> <?php echo number_format($remaining_spend); ?> pts to reach <strong style="color: #b48530;"><?php echo $next_tier_name ?: 'Max Tier'; ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5 ps-md-5 pt-4 pt-md-0 d-flex flex-row justify-content-between align-items-center">
                                <div class="d-flex flex-column justify-content-center">
                                    <p class="text-muted mb-1" style="font-size: 0.85rem; font-weight: 500;">Next Tier</p>
                                    <h3 class="mb-1" style="font-family: 'Playfair Display', serif; color: #b48530; font-size: 1.8rem; font-weight: 600;"><?php echo $next_tier_name ?: 'Maximum Tier'; ?></h3>
                                    <p class="text-muted mb-0" style="font-size: 0.85rem;"><?php echo $next_tier_req ? number_format($next_tier_req) . ' pts required' : 'You are at the top!'; ?></p>
                                </div>
                                
                                <div>
                                    <button type="button" class="btn btn-sm text-white px-4 py-2 text-nowrap" style="background-color: #143628; border-radius: 6px; font-weight: 500;" data-bs-toggle="modal" data-bs-target="#tierBenefitsModal">
                                        View Tier Benefits <i class="fa-solid fa-chevron-right ms-2" style="font-size: 0.7rem;"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Section -->
                    <h3 class="mb-4" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.4rem; font-weight: 600;">
                        <i class="fa-solid fa-chart-line me-2"></i> Rewards Statistics
                    </h3>
                    
                    <div class="row g-4 mb-5">
                        <div class="col-lg-3 col-sm-6">
                            <div class="bg-card-custom rounded-4 border p-4 d-flex align-items-center" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 50px; height: 50px; background-color: #fcf4db; border: 1px solid #f1e2b3;">
                                    <i class="fa-regular fa-star" style="color: #b48530; font-size: 1.2rem;"></i>
                                </div>
                                <div>
                                    <div class="text-muted mb-1" style="font-size: 0.8rem;">Current Balance</div>
                                    <div style="font-family: 'Playfair Display', serif; color: #5a2a35; font-size: 1.4rem; font-weight: 600;"><?php echo number_format($points_balance); ?> <span style="font-size: 0.9rem; font-family: 'Plus Jakarta Sans', sans-serif;">pts</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="bg-card-custom rounded-4 border p-4 d-flex align-items-center" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 50px; height: 50px; background-color: #f1efed; border: 1px solid #e2e0dd;">
                                    <i class="fa-solid fa-gift" style="color: #6a8c79; font-size: 1.2rem;"></i>
                                </div>
                                <div>
                                    <div class="text-muted mb-1" style="font-size: 0.8rem;">Total Points Earned</div>
                                    <div style="font-family: 'Playfair Display', serif; color: #143628; font-size: 1.4rem; font-weight: 600;"><?php echo number_format($reward_points_row['points_earned']); ?> <span style="font-size: 0.9rem; font-family: 'Plus Jakarta Sans', sans-serif;">pts</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="bg-card-custom rounded-4 border p-4 d-flex align-items-center" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 50px; height: 50px; background-color: #fcf4db; border: 1px solid #f1e2b3;">
                                    <i class="fa-solid fa-clock-rotate-left" style="color: #5a2a35; font-size: 1.2rem;"></i>
                                </div>
                                <div>
                                    <div class="text-muted mb-1" style="font-size: 0.8rem;">Total Points Redeemed</div>
                                    <div style="font-family: 'Playfair Display', serif; color: #5a2a35; font-size: 1.4rem; font-weight: 600;"><?php echo number_format($reward_points_row['points_redeemed']); ?> <span style="font-size: 0.9rem; font-family: 'Plus Jakarta Sans', sans-serif;">pts</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="bg-card-custom rounded-4 border p-4 d-flex align-items-center" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 50px; height: 50px; background-color: #fcfbf8; border: 1px solid rgba(0,0,0,0.05);">
                                    <i class="fa-solid fa-wallet" style="color: #b48530; font-size: 1.2rem;"></i>
                                </div>
                                <div>
                                    <div class="text-muted mb-1" style="font-size: 0.8rem;">Lifetime Spend</div>
                                    <div style="font-family: 'Playfair Display', serif; color: #143628; font-size: 1.4rem; font-weight: 600;">₹<?php echo number_format($tier_spend, 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transactions Section -->
                    <div class="d-flex justify-content-between align-items-center mb-4 mt-5">
                        <h3 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.4rem; font-weight: 600;">
                            <i class="fa-solid fa-clock-rotate-left me-2"></i> Point Transactions
                        </h3>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" id="txDropdownBtn" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: transparent; border-color: rgba(0,0,0,0.1); color: #555; padding: 0.4rem 1rem;">
                                All Types
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="border: 1px solid rgba(0,0,0,0.05); border-radius: 8px;">
                                <li><a class="dropdown-item filter-tx" href="#" data-filter="all">All Types</a></li>
                                <li><a class="dropdown-item filter-tx" href="#" data-filter="earn">Earn</a></li>
                                <li><a class="dropdown-item filter-tx" href="#" data-filter="redeem">Redeem</a></li>
                                <li><a class="dropdown-item filter-tx" href="#" data-filter="deduct">Deduct</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="bg-card-custom rounded-4 border p-0 overflow-hidden" style="border-color: rgba(0,0,0,0.05) !important;">
                        <table class="table mb-0" style="font-size: 0.85rem;">
                            <thead style="background-color: #fcfbf8; border-bottom: 1px solid rgba(0,0,0,0.05);">
                                <tr>
                                    <th class="text-muted text-uppercase" style="font-weight: 600; font-size: 0.7rem; letter-spacing: 0.5px; padding: 1rem 1.5rem; border: none;">Date</th>
                                    <th class="text-muted text-uppercase" style="font-weight: 600; font-size: 0.7rem; letter-spacing: 0.5px; padding: 1rem 1.5rem; border: none;">Type</th>
                                    <th class="text-muted text-uppercase" style="font-weight: 600; font-size: 0.7rem; letter-spacing: 0.5px; padding: 1rem 1.5rem; border: none;">Change</th>
                                    <th class="text-muted text-uppercase" style="font-weight: 600; font-size: 0.7rem; letter-spacing: 0.5px; padding: 1rem 1.5rem; border: none;">Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $tx_stmt = $pdo->prepare("
                                    SELECT lt.*, o.order_number 
                                    FROM loyalty_transactions lt 
                                    LEFT JOIN orders o ON lt.order_id = o.id 
                                    WHERE lt.user_id = ? 
                                    ORDER BY lt.transaction_date DESC 
                                    LIMIT 100
                                ");
                                $tx_stmt->execute([$user_id]);
                                $txs = $tx_stmt->fetchAll(PDO::FETCH_ASSOC);
                                if (empty($txs)):
                                ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted p-4">No point transactions found yet.</td>
                                    </tr>
                                <?php
                                else:
                                    foreach ($txs as $t):
                                        $change_str = '';
                                        $change_class = '';
                                        $type_badge = '';
                                        $type_filter = '';
                                        if ($t['points_earned'] > 0) {
                                            $change_str = '+' . $t['points_earned'];
                                            $change_class = 'text-success';
                                            $type_badge = '<span class="badge rounded-pill" style="background-color: #e6f2eb; color: #2e7d51; font-weight: 500; padding: 0.35rem 0.8rem;">Earn</span>';
                                            $type_filter = 'earn';
                                        } elseif ($t['points_redeemed'] > 0) {
                                            $change_str = '-' . $t['points_redeemed'];
                                            $change_class = 'text-danger';
                                            $type_badge = '<span class="badge rounded-pill" style="background-color: #fde8eb; color: #c92a3e; font-weight: 500; padding: 0.35rem 0.8rem;">Redeem</span>';
                                            $type_filter = 'redeem';
                                        } elseif ($t['points_deducted'] > 0) {
                                            $change_str = '-' . $t['points_deducted'];
                                            $change_class = 'text-warning';
                                            $type_badge = '<span class="badge rounded-pill bg-warning text-white" style="font-weight: 500; padding: 0.35rem 0.8rem;">Deduct</span>';
                                            $type_filter = 'deduct';
                                        }
                                        
                                        $ref = $t['order_number'] ? 'Order #' . $t['order_number'] : 'System Adjust';
                                ?>
                                        <tr class="transaction-row" data-type="<?php echo $type_filter; ?>" style="border-bottom: 1px solid rgba(0,0,0,0.03);">
                                            <td class="text-dark" style="padding: 1rem 1.5rem; border: none; font-weight: 500;"> <?php echo date('d M Y, h:i A', strtotime($t['transaction_date'])); ?></td>
                                            <td style="padding: 1rem 1.5rem; border: none;"><?php echo $type_badge; ?></td>
                                            <td class="<?php echo $change_class; ?>" style="padding: 1rem 1.5rem; border: none; font-weight: 600;"><?php echo $change_str; ?></td>
                                            <td class="text-dark" style="padding: 1rem 1.5rem; border: none;"> <?php echo htmlspecialchars($ref); ?></td>
                                        </tr>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                                <tr id="tx-empty-row" style="display: none;">
                                    <td colspan="4" class="text-center text-muted p-4">No point transactions match this filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- How to Redeem Points Section -->
                    <h3 class="mb-4 mt-5" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.4rem; font-weight: 600;">
                        <i class="fa-solid fa-gift me-2 text-gold" style="color: #d4af37;"></i> How to Redeem & Use Points
                    </h3>
                    
                    <div class="row g-4 mb-5">
                        <div class="col-md-4">
                            <div class="bg-card-custom rounded-4 border p-4 h-100" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 45px; height: 45px; background-color: #e6f2eb; color: #2e7d51;">
                                    <i class="fa-solid fa-receipt fa-lg"></i>
                                </div>
                                <h5 class="mb-2" style="font-size: 1.1rem; color: #3f1519; font-weight: 600;">1. Earn on Every Order</h5>
                                <p class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.6;">You automatically earn points for every rupee spent at Medusa. Your Tier determines your points multiplier!</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-card-custom rounded-4 border p-4 h-100" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 45px; height: 45px; background-color: #fde8eb; color: #c92a3e;">
                                    <i class="fa-solid fa-money-bill-wave fa-lg"></i>
                                </div>
                                <h5 class="mb-2" style="font-size: 1.1rem; color: #3f1519; font-weight: 600;">2. Apply at Checkout</h5>
                                <p class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.6;">During your next order, look for the "Use Reward Points" toggle on the checkout page to instantly apply your discount.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-card-custom rounded-4 border p-4 h-100" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 45px; height: 45px; background-color: #fcf4db; color: #b48530;">
                                    <i class="fa-solid fa-ticket fa-lg"></i>
                                </div>
                                <h5 class="mb-2" style="font-size: 1.1rem; color: #3f1519; font-weight: 600;">3. Claim Special Coupons</h5>
                                <p class="text-muted mb-0" style="font-size: 0.85rem; line-height: 1.6;">You can also exchange your points for high-value exclusive coupons in the <strong>Coupons & Rewards</strong> tab.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rounded-4 border p-4 p-md-5 mb-5 position-relative" style="background-color: #F9F6F0; border-color: rgba(0,0,0,0.05) !important; overflow: hidden;">
                        <h4 class="mb-4" style="color: #5a2a35; font-family: 'Playfair Display', serif; font-weight: 600;">
                            <i class="fa-solid fa-circle-exclamation me-2"></i> Important Rules
                        </h4>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 style="color: #3f1519; font-weight: 600;">Points Expiry</h6>
                                <p class="text-muted mb-0" style="font-size: 0.85rem;">To encourage regular visits, a 20% deduction is applied to your balance if no orders are placed for 90 consecutive days. We'll email you 7 days before!</p>
                            </div>
                            <div class="col-md-6">
                                <h6 style="color: #3f1519; font-weight: 600;">Annual Tier Reset</h6>
                                <p class="text-muted mb-0" style="font-size: 0.85rem;">On January 1st, all customers in Silver or Gold tiers are moved down one tier. Make sure to enjoy your premium benefits before year-end!</p>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const filterLinks = document.querySelectorAll('.filter-tx');
                            const txRows = document.querySelectorAll('.transaction-row');
                            const txDropdownBtn = document.getElementById('txDropdownBtn');

                            if (filterLinks.length > 0 && txDropdownBtn) {
                                filterLinks.forEach(link => {
                                    link.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        const filter = this.getAttribute('data-filter');
                                        txDropdownBtn.textContent = this.textContent;
                                        
                                        let hasVisible = false;
                                        txRows.forEach(row => {
                                            if (filter === 'all' || row.getAttribute('data-type') === filter) {
                                                row.style.display = '';
                                                hasVisible = true;
                                            } else {
                                                row.style.display = 'none';
                                            }
                                        });
                                        
                                        const emptyRow = document.getElementById('tx-empty-row');
                                        if (emptyRow) {
                                            emptyRow.style.display = hasVisible ? 'none' : '';
                                        }
                                    });
                                });
                            }
                        });
                    </script>
                </div>

                <!-- ══ TAB: NOTIFICATIONS LOG ══ -->
                <div class="tab-pane fade" id="pill-notifications" role="tabpanel">
                    <div class="d-flex flex-column mb-5">
                        <h2 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.8rem; font-weight: 600;">
                            <i class="fa-regular fa-bell me-2" style="color: #d4af37;"></i> Notification
                        </h2>
                        <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem;">Stay updated with your recent activity and important alerts.</p>
                    </div>
                    
                    <div class="notif-list">
                        <?php if (empty($user_notifications)): ?>
                            <div class="text-center py-5 bg-card-custom rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                                <i class="fa-regular fa-bell-slash mb-3" style="font-size: 2.5rem; color: #d4af37; opacity: 0.5;"></i>
                                <p class="text-muted m-0">No notifications found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($user_notifications as $notif): 
                                $title_lower = strtolower($notif['title']);
                                $is_loyalty = strpos($title_lower, 'loyalty') !== false || strpos($title_lower, 'tier') !== false || strpos($title_lower, 'point') !== false;
                                
                                if ($is_loyalty) {
                                    $icon_bg = '#fcf4db';
                                    $icon_color = '#b48530';
                                    $icon_class = 'fa-trophy';
                                    $badge_bg = '#fcf4db';
                                    $badge_color = '#b48530';
                                    $badge_text = 'LOYALTY';
                                } else {
                                    $icon_bg = '#e6f2eb';
                                    $icon_color = '#2e7d51';
                                    $icon_class = 'fa-bag-shopping';
                                    $badge_bg = '#e6f2eb';
                                    $badge_color = '#2e7d51';
                                    $badge_text = 'ORDER';
                                }
                            ?>
                                <div class="bg-card-custom rounded-4 p-4 mb-3 d-flex align-items-center justify-content-between position-relative" style="border: 1px solid rgba(0,0,0,0.05); overflow: hidden; <?php echo $notif['is_read'] ? '' : 'box-shadow: 0 4px 15px rgba(0,0,0,0.03);'; ?>">
                                    <div class="position-absolute top-0 bottom-0 start-0" style="width: 4px; background-color: #d4af37;"></div>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 me-4" style="width: 55px; height: 55px; background-color: <?php echo $icon_bg; ?>;">
                                            <i class="fa-solid <?php echo $icon_class; ?>" style="color: <?php echo $icon_color; ?>; font-size: 1.2rem;"></i>
                                        </div>
                                        <div class="d-flex flex-column">
                                            <span class="badge rounded-pill mb-2" style="background-color: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; width: fit-content; font-size: 0.65rem; padding: 0.3rem 0.6rem; letter-spacing: 0.5px;"><?php echo $badge_text; ?></span>
                                            <h5 class="mb-1" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.15rem; font-weight: 600;"><?php echo htmlspecialchars($notif['title']); ?></h5>
                                            <p class="text-muted m-0" style="font-size: 0.85rem; max-width: 600px;"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-muted flex-shrink-0 ms-4" style="font-size: 0.8rem;">
                                        <i class="fa-regular fa-clock me-1"></i> <?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ══ TAB 3: COUPONS & REWARDS ══ -->
                <div class="tab-pane fade" id="pill-coupons" role="tabpanel">
                    <div class="d-flex flex-column mb-4">
                        <h2 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.8rem; font-weight: 600;">
                            <i class="fa-solid fa-gift me-2" style="color: #d4af37;"></i> Coupons & Loyalty Rewards
                        </h2>
                        <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem;">Use your rewards and save more on your orders.</p>
                    </div>
                    
                    <!-- Points Summary -->
                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <div class="bg-card-custom rounded-4 border p-4 h-100 d-flex align-items-center" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-4 flex-shrink-0" style="width: 70px; height: 70px; background-color: #fcf4db;">
                                    <i class="fa-regular fa-gem" style="color: #b48530; font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 600;">Loyalty Points</p>
                                    <h3 class="mb-1" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 2rem; font-weight: 600;"><?php echo number_format($loyalty_points); ?> <span style="font-size: 1.2rem; font-family: 'Plus Jakarta Sans', sans-serif;">pts</span></h3>
                                    <p class="text-muted m-0" style="font-size: 0.8rem;">Earn 1 point for every ₹100 spent.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-card-custom rounded-4 border p-4 h-100 d-flex align-items-center" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-4 flex-shrink-0" style="width: 70px; height: 70px; background-color: #fcfbf8; border: 1px solid rgba(0,0,0,0.03);">
                                    <i class="fa-solid fa-wallet" style="color: #b48530; font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <p class="text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 600;">Total Spent</p>
                                    <h3 class="mb-1" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 2rem; font-weight: 600;">₹<?php echo number_format($total_spent, 2); ?></h3>
                                    <p class="text-muted m-0" style="font-size: 0.8rem;">Calculated from <?php echo $completed_count; ?> completed orders.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coupons List -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.3rem; font-weight: 600;">
                            <i class="fa-solid fa-tag me-2" style="color: #b48530;"></i> Promo Coupons
                        </h4>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm coupon-filter-btn" data-filter="active" style="background-color: #143628; color: #fff; font-weight: 500; border: 1px solid #143628; border-radius: 6px 0 0 6px; padding: 0.35rem 1rem;">Active</button>
                            <button type="button" class="btn btn-sm coupon-filter-btn" data-filter="expired" style="background-color: transparent; color: #555; font-weight: 500; border: 1px solid rgba(0,0,0,0.1); border-radius: 0 6px 6px 0; padding: 0.35rem 1rem;">Expired / Used</button>
                        </div>
                    </div>
                    
                    <?php if (empty($userCoupons)): ?>
                        <div class="text-center py-5 bg-card-custom rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                            <i class="fa-solid fa-ticket-simple mb-3" style="font-size: 2.5rem; color: #d4af37; opacity: 0.5;"></i>
                            <p class="text-muted m-0">No active coupons found. Leave a 5-star review to unlock a coupon!</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-4 mb-5">
                            <?php foreach ($userCoupons as $coupon): ?>
                                <?php
                                    $statusBadge = '';
                                    $cardOpacity = '1';
                                    switch (strtolower($coupon->status)) {
                                        case 'active':
                                            $statusBadge = '<span class="badge rounded-pill" style="background-color: #143628; color: #fff; font-weight: 500; font-size: 0.7rem; padding: 0.35rem 0.8rem;">Active</span>';
                                            break;
                                        case 'redeemed':
                                            $statusBadge = '<span class="badge rounded-pill bg-secondary text-white" style="font-weight: 500; font-size: 0.7rem; padding: 0.35rem 0.8rem;">Redeemed</span>';
                                            $cardOpacity = '0.6';
                                            break;
                                        case 'expired':
                                            $statusBadge = '<span class="badge rounded-pill bg-danger text-white" style="font-weight: 500; font-size: 0.7rem; padding: 0.35rem 0.8rem;">Expired</span>';
                                            $cardOpacity = '0.5';
                                            break;
                                    }
                                ?>
                                <div class="col-xl-6 coupon-card" data-status="<?php echo strtolower($coupon->status); ?>" style="opacity: <?php echo $cardOpacity; ?>;">
                                    <div class="bg-card-custom p-4 rounded-4 border d-flex position-relative" style="border-color: rgba(0,0,0,0.05) !important; box-shadow: 0 4px 10px rgba(0,0,0,0.01);">
                                        <div class="position-absolute top-0 end-0 mt-4 me-4">
                                            <?php echo $statusBadge; ?>
                                        </div>
                                        <div class="me-4 flex-shrink-0 d-flex flex-column justify-content-center align-items-center" style="width: 80px; height: 100px; background-color: #f6efe1; border-radius: 8px; position: relative;">
                                            <div style="position: absolute; left: -5px; top: 50%; transform: translateY(-50%); width: 10px; height: 20px; background-color: #fff; border-radius: 0 10px 10px 0; border: 1px solid rgba(0,0,0,0.05); border-left: none;"></div>
                                            <div style="position: absolute; right: -5px; top: 50%; transform: translateY(-50%); width: 10px; height: 20px; background-color: #fff; border-radius: 10px 0 0 10px; border: 1px solid rgba(0,0,0,0.05); border-right: none;"></div>
                                            <h3 class="m-0" style="font-family: 'Playfair Display', serif; color: #5a2a35; font-size: 1.6rem; font-weight: 600;"><?php echo intval($coupon->discount_value); ?>%</h3>
                                            <span style="font-family: 'Playfair Display', serif; color: #887a6b; font-size: 0.9rem; font-weight: 500;">OFF</span>
                                        </div>
                                        <div class="flex-grow-1 pe-5 d-flex flex-column justify-content-between">
                                            <div>
                                                <h5 class="mb-1" style="font-family: 'Playfair Display', serif; color: #3f1519; font-weight: 600; font-size: 1.1rem;"><?php echo intval($coupon->discount_value); ?>% Discount</h5>
                                                <p class="text-muted m-0" style="font-size: 0.75rem;">Campaign: <?php echo htmlspecialchars($coupon->campaign_code); ?></p>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-between mt-3" style="border: 1px dashed #d4af37; border-radius: 6px; padding: 0.4rem 0.8rem; background-color: #fcfbf8; width: fit-content;">
                                                <code style="font-family: monospace; font-size: 0.8rem; color: #5a2a35; font-weight: 600; margin-right: 1.5rem; letter-spacing: 0.5px;"><?php echo htmlspecialchars($coupon->coupon_code); ?></code>
                                                <?php if ($coupon->status === 'active'): ?>
                                                    <a href="javascript:void(0)" onclick="copyCouponCode(this, '<?php echo htmlspecialchars($coupon->coupon_code); ?>')" style="color: #b48530; transition: 0.2s;"><i class="fa-regular fa-copy"></i></a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted mt-2" style="font-size: 0.75rem;">
                                                Expires: <?php echo date('d M Y', strtotime($coupon->expires_at)); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="rounded-4 p-3 d-flex align-items-center" style="background-color: #F9F6F0; border: 1px solid rgba(0,0,0,0.03);">
                        <div class="rounded-circle d-flex justify-content-center align-items-center me-3 flex-shrink-0" style="width: 30px; height: 30px; background-color: #d4af37; color: #fff;">
                            <i class="fa-solid fa-info" style="font-size: 0.8rem;"></i>
                        </div>
                        <p class="text-muted m-0" style="font-size: 0.85rem;">Coupons are only applicable on eligible orders and cannot be combined with other offers.</p>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const couponBtns = document.querySelectorAll('.coupon-filter-btn');
                            const couponCards = document.querySelectorAll('.coupon-card');

                            // Initial load: show active
                            filterCoupons('active');

                            couponBtns.forEach(btn => {
                                btn.addEventListener('click', function() {
                                    // Update button styling
                                    couponBtns.forEach(b => {
                                        b.style.backgroundColor = 'transparent';
                                        b.style.color = '#555';
                                        b.style.borderColor = 'rgba(0,0,0,0.1)';
                                    });
                                    this.style.backgroundColor = '#143628';
                                    this.style.color = '#fff';
                                    this.style.borderColor = '#143628';
                                    
                                    // Filter cards
                                    const filter = this.getAttribute('data-filter');
                                    filterCoupons(filter);
                                });
                            });

                            function filterCoupons(filter) {
                                let hasVisible = false;
                                couponCards.forEach(card => {
                                    const status = card.getAttribute('data-status');
                                    if (filter === 'active' && status === 'active') {
                                        card.style.display = '';
                                        hasVisible = true;
                                    } else if (filter === 'expired' && (status === 'expired' || status === 'redeemed')) {
                                        card.style.display = '';
                                        hasVisible = true;
                                    } else {
                                        card.style.display = 'none';
                                    }
                                });
                            }
                        });
                    </script>
                </div>

                <!-- ══ TAB: LIQUOR QUOTA ══ -->
                <?php if ($has_liquor_quota): ?>
                <div class="tab-pane fade" id="pill-quota" role="tabpanel">
                    
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <div>
                            <h2 class="m-0" style="font-family: 'Playfair Display', serif; color: #5a2a35; font-size: 1.8rem;">
                                <i class="fa-solid fa-wine-bottle me-2" style="color: #d4af37;"></i> Liquor Quota Balance
                            </h2>
                            <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem;">Track and manage your available bottles and pegs.</p>
                        </div>
                        <button class="btn btn-outline-dark" style="border-color: #5a2a35; color: #5a2a35; font-weight: 500; font-size: 0.9rem; border-radius: 6px;">
                            <i class="fa-regular fa-circle-question me-2"></i> How It Works
                        </button>
                    </div>
                    
                    <!-- Quota Cards -->
                    <div class="row g-4 mb-5">
                        <?php foreach ($user_liquor_quotas as $quota): 
                            $total_pegs = intval($quota['total_pegs']);
                            $bottles_left = floor($total_pegs / 8);
                            $pegs_left = $total_pegs % 8;
                            
                            $name_lower = strtolower($quota['item_name']);
                            $cat_badge = 'PREMIUM WHISKY';
                            $bg_circle = '#5a2a35'; 
                            $img_src = 'assets/images/black_label.png'; 
                            
                            if (strpos($name_lower, 'gin') !== false) {
                                $cat_badge = 'PREMIUM GIN';
                                $bg_circle = '#143628'; 
                                $img_src = 'assets/images/hendricks.png';
                            } elseif (strpos($name_lower, 'vodka') !== false) {
                                $cat_badge = 'PREMIUM VODKA';
                                $bg_circle = '#223843'; 
                                $img_src = 'assets/images/absolut_vodka.png';
                            }
                        ?>
                        <div class="col-lg-6">
                            <div class="bg-card-custom rounded-4 border p-4 position-relative" style="border-color: rgba(0,0,0,0.05) !important; box-shadow: 0 4px 15px rgba(0,0,0,0.02); overflow: hidden;">
                                <div class="position-absolute" style="top: 50%; right: -20px; transform: translateY(-50%); opacity: 0.08; pointer-events: none; z-index: 0;">
                                    <img src="assets/images/leaf_watermark.png" width="180" alt="" onerror="this.style.display='none'">
                                </div>
                                
                                <div class="d-flex position-relative mb-4" style="z-index: 1;">
                                    <!-- Left Side: Image -->
                                    <div class="me-4 d-flex align-items-center justify-content-center" style="width: 140px; height: 140px; flex-shrink: 0;">
                                        <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($quota['item_name']); ?>" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="rounded-circle align-items-center justify-content-center" style="background-color: <?php echo $bg_circle; ?>; width: 110px; height: 110px; display: none;">
                                            <i class="fa-solid fa-wine-bottle" style="font-size: 4rem; color: #d4af37;"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Right Side: Details -->
                                    <div class="flex-grow-1">
                                        <span class="badge rounded-pill mb-2" style="background-color: #f1ebd9; color: #8e734a; font-weight: 600; font-size: 0.7rem; letter-spacing: 0.5px; border: 1px solid rgba(0,0,0,0.05);">
                                            <?php echo $cat_badge; ?>
                                        </span>
                                        <h4 class="mb-3" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.25rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($quota['item_name']); ?>
                                        </h4>
                                        
                                        <div class="d-flex gap-3">
                                            <div class="d-flex align-items-center justify-content-center flex-column rounded-3 border" style="background-color: #fcfbf8; border-color: rgba(0,0,0,0.05) !important; padding: 0.6rem; flex: 1;">
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="fa-solid fa-wine-bottle me-2" style="color: #d4af37; font-size: 1rem;"></i>
                                                    <span style="font-size: 1.3rem; font-family: 'Playfair Display', serif; color: #5a2a35; font-weight: 600;"><?php echo $bottles_left; ?></span>
                                                </div>
                                                <span class="text-muted" style="font-size: 0.7rem;">Bottles Left</span>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-center flex-column rounded-3 border" style="background-color: #fcfbf8; border-color: rgba(0,0,0,0.05) !important; padding: 0.6rem; flex: 1;">
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="fa-solid fa-wine-glass me-2" style="color: #d4af37; font-size: 1rem;"></i>
                                                    <span style="font-size: 1.3rem; font-family: 'Playfair Display', serif; color: #5a2a35; font-weight: 600;"><?php echo $pegs_left; ?></span>
                                                </div>
                                                <span class="text-muted" style="font-size: 0.7rem;">Pegs Left</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button onclick="buyLiquorQuotaAgain(<?php echo $quota['food_item_id']; ?>)" class="btn w-100 position-relative" style="z-index: 1; border: 1px solid #5a2a35; color: #5a2a35; background-color: #fffafb; font-weight: 500; padding: 0.6rem; border-radius: 6px;">
                                    <i class="fa-solid fa-cart-plus me-2" style="color: #5a2a35;"></i> Buy Again
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- How It Works Section -->
                    <div class="rounded-4 border p-4 p-md-5 position-relative mb-4" style="background-color: #F9F6F0; border-color: rgba(0,0,0,0.05) !important; overflow: hidden;">
                        <div class="position-relative" style="z-index: 1; max-width: 800px;">
                            <div class="d-flex align-items-center mb-4 pb-2 border-bottom" style="border-color: rgba(212, 175, 55, 0.3) !important;">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="background-color: #5a2a35; width: 32px; height: 32px;">
                                    <i class="fa-solid fa-info text-white" style="font-size: 0.9rem;"></i>
                                </div>
                                <h4 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.4rem;">How the Liquor Quota Works</h4>
                            </div>

                            <div class="d-flex align-items-start mb-4">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-4 mt-1 shadow-sm" style="background-color: #fdf4d7; width: 40px; height: 40px; border: 1px solid #f9c823; flex-shrink: 0;">
                                    <i class="fa-solid fa-wine-bottle" style="color: #b48530;"></i>
                                </div>
                                <div class="pt-2">
                                    <p class="m-0" style="color: #555; font-size: 0.95rem;">Select your favorite premium brands from our <strong style="color: #5a2a35;">Liquor</strong> category in the menu.</p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mb-4">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-4 mt-1 shadow-sm" style="background-color: #fdf4d7; width: 40px; height: 40px; border: 1px solid #f9c823; flex-shrink: 0;">
                                    <i class="fa-solid fa-wine-glass" style="color: #b48530;"></i>
                                </div>
                                <div class="pt-2">
                                    <p class="m-0" style="color: #555; font-size: 0.95rem;">Order a bottle. Upon payment confirmation, <strong style="color: #5a2a35;">8 pegs</strong> will be credited to your quota balance per bottle.</p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start mb-4">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-4 mt-1 shadow-sm" style="background-color: #fdf4d7; width: 40px; height: 40px; border: 1px solid #f9c823; flex-shrink: 0;">
                                    <i class="fa-solid fa-chart-simple" style="color: #b48530;"></i>
                                </div>
                                <div class="pt-2">
                                    <p class="m-0" style="color: #555; font-size: 0.95rem;">You can track and manage your available bottles and pegs from this panel.</p>
                                </div>
                            </div>

                            <div class="d-flex align-items-start">
                                <div class="rounded-circle d-flex align-items-center justify-content-center me-4 mt-1 shadow-sm" style="background-color: #fdf4d7; width: 40px; height: 40px; border: 1px solid #f9c823; flex-shrink: 0;">
                                    <i class="fa-solid fa-bell-concierge" style="color: #b48530;"></i>
                                </div>
                                <div class="pt-2">
                                    <p class="m-0" style="color: #555; font-size: 0.95rem;">To consume a peg during your visit, please request the waiter/admin to record the consumption at the counter.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ══ TAB *: MEMBERSHIP PASS ══ -->
                <div class="tab-pane fade" id="pill-membership" role="tabpanel">
                    <style>
                    .membership-card-wrapper {
                        width: 400px;
                        height: 250px;
                        border-radius: 16px;
                        background: linear-gradient(135deg, #143628 0%, #0a1f16 100%);
                        position: relative;
                        overflow: hidden;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
                        border: 1px solid rgba(223, 186, 134, 0.3);
                        color: #fff;
                        padding: 24px;
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between;
                        margin: 0 auto;
                    }
                    .membership-card-wrapper::before {
                        content: '';
                        position: absolute;
                        top: -50%;
                        left: -50%;
                        width: 200%;
                        height: 200%;
                        background: radial-gradient(circle, rgba(223, 186, 134, 0.1) 0%, transparent 60%);
                        pointer-events: none;
                    }
                    .mc-logo {
                        display: flex;
                        align-items: center;
                        gap: 12px;
                    }
                    .mc-logo img {
                        width: 40px;
                        height: 40px;
                        filter: brightness(1.2);
                    }
                    .mc-title {
                        font-family: 'Playfair Display', serif;
                        font-size: 1.2rem;
                        font-weight: 700;
                        color: #C09B5B;
                        letter-spacing: 2px;
                        text-transform: uppercase;
                    }
                    .mc-chip {
                        width: 45px;
                        height: 35px;
                        background: linear-gradient(135deg, #d4af37, #f9f5e8, #c59b27);
                        border-radius: 6px;
                        margin-top: 20px;
                        border: 1px solid rgba(0,0,0,0.2);
                    }
                    .mc-number {
                        font-family: 'Courier New', Courier, monospace;
                        font-size: 1.6rem;
                        letter-spacing: 3px;
                        text-shadow: 1px 1px 2px rgba(0,0,0,0.8);
                        margin-top: 15px;
                    }
                    .mc-details {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-end;
                        margin-top: 20px;
                    }
                    .mc-label {
                        font-size: 0.6rem;
                        text-transform: uppercase;
                        color: rgba(255,255,255,0.6);
                        letter-spacing: 1px;
                        margin-bottom: 2px;
                    }
                    .mc-value {
                        font-family: 'Plus Jakarta Sans', sans-serif;
                        font-size: 1rem;
                        font-weight: 600;
                        letter-spacing: 1px;
                        text-transform: uppercase;
                    }
                    @media (max-width: 500px) {
                        .membership-card-wrapper {
                            width: 100%;
                            height: auto;
                            min-height: 220px;
                            padding: 15px;
                        }
                        .mc-number { font-size: 1.2rem; }
                        .mc-value { font-size: 0.85rem; }
                    }
                    </style>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="m-0" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.8rem;">
                                <i class="fa-solid fa-id-badge me-2" style="color: #d4af37;"></i> Membership Pass
                            </h2>
                            <p class="text-muted mt-2 mb-0" style="font-size: 0.95rem;">Your exclusive Medusa dining card.</p>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center align-items-center py-5 flex-column" id="membership-card-container">
                        <div class="spinner-border text-gold" role="status" id="mc-spinner">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        
                        <!-- Purchase / Renew Section -->
                        <div id="mc-purchase" style="display:none; text-align: center; max-width: 400px; margin-top: 20px;">
                            <i class="fas fa-crown mb-3" style="font-size: 3rem; color: #b8973a !important;"></i>
                            <h4 class="text-gold" id="mc-purchase-title">Get Your Medusa Elite Pass</h4>
                            <p class="text-muted mb-4" id="mc-purchase-desc">Unlock premium features, seamless billing, and exclusive rewards for just ₹599.</p>
                            <button class="btn btn-gold w-100 py-3" id="mc-buy-btn" onclick="buyMembership()" style="border-radius: 8px; font-weight: bold;">
                                <i class="fas fa-lock"></i> Buy Now for ₹599
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-center align-items-center position-relative w-100" style="perspective: 1000px; cursor: pointer; display: none;" id="mc-card">
                            <!-- Glowing Shadow underneath the card -->
                            <div style="position: absolute; bottom: -20px; width: 60%; height: 20px; background: rgba(223,186,134,0.4); filter: blur(20px); border-radius: 50%; pointer-events: none;"></div>

                            <!-- Tilt Container -->
                            <div class="membership-card-3d" id="membership-card-3d" style="width: 540px; height: 340px; transform-style: preserve-3d; transition: transform 0.1s ease-out; position: relative;">
                                
                                <!-- Card Face (Front) -->
                                <div class="card-face card-front position-absolute w-100 h-100" style="background-color: #0b1712; border-radius: 20px; box-shadow: 0 30px 60px rgba(0,0,0,0.6); border: 1px solid rgba(255, 255, 255, 0.03); padding: 35px 40px; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; backface-visibility: hidden;">
                                    
                                    <!-- Medusa Watermark -->
                                    <img src="assets/images/medusaa2(onlylogo).png" alt="" style="position: absolute; right: -15%; top: -15%; width: 85%; opacity: 0.08; filter: grayscale(100%) sepia(100%) hue-rotate(90deg) brightness(1.5) contrast(1.2); pointer-events: none;">
                                    
                                    <!-- Top Row -->
                                    <div class="d-flex justify-content-between align-items-start position-relative z-1">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="assets/images/medusaa2(onlylogo).png" alt="Logo" style="width: 48px; height: 48px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));">
                                            <div class="d-flex flex-column mt-1">
                                                <span style="font-family: 'Cinzel', 'Playfair Display', serif; font-size: 1.45rem; font-weight: 700; color: #d4b572; letter-spacing: 2px;">MEDUSA</span>
                                                <span style="font-family: 'Inter', sans-serif; font-size: 0.65rem; color: #d4b572; letter-spacing: 5px; opacity: 0.8; margin-top: 2px;">PREMIUM</span>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column text-end mt-1">
                                            <span style="font-family: 'Cinzel', 'Playfair Display', serif; font-size: 1.6rem; font-weight: 700; color: #ffffff; letter-spacing: 2.5px;" id="mc-tier">SILVER</span>
                                            <span style="font-family: 'Inter', sans-serif; font-size: 0.7rem; color: rgba(255,255,255,0.6); letter-spacing: 4px; margin-top: 2px;">MEMBER</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Chip & NFC -->
                                    <div class="d-flex align-items-center gap-4 position-relative z-1" style="margin-top: 20px;">
                                        <!-- Chip -->
                                        <div style="width: 52px; height: 38px; background: linear-gradient(135deg, #d8b86d 0%, #b38b22 100%); border-radius: 6px; position: relative; overflow: hidden; border: 1px solid rgba(0,0,0,0.4); box-shadow: 0 2px 5px rgba(0,0,0,0.4);">
                                            <div style="position: absolute; width: 100%; height: 1px; background: rgba(0,0,0,0.15); top: 35%;"></div>
                                            <div style="position: absolute; width: 100%; height: 1px; background: rgba(0,0,0,0.15); top: 65%;"></div>
                                            <div style="position: absolute; height: 100%; width: 1px; background: rgba(0,0,0,0.15); left: 35%;"></div>
                                            <div style="position: absolute; height: 100%; width: 1px; background: rgba(0,0,0,0.15); right: 35%;"></div>
                                            <div style="position: absolute; width: 30%; height: 30%; border: 1px solid rgba(0,0,0,0.15); border-radius: 4px; top: 35%; left: 35%;"></div>
                                        </div>
                                        <!-- NFC -->
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="opacity: 0.85;">
                                            <circle cx="7" cy="12" r="2.5" fill="white"/>
                                            <path d="M12 16.5C13.8 14.5 13.8 9.5 12 7.5" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
                                            <path d="M15.5 19.5C18.5 15.5 18.5 8.5 15.5 4.5" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    
                                    <!-- Card Number -->
                                    <div class="position-relative z-1" style="margin-top: 15px; margin-bottom: 10px;">
                                        <div style="font-family: 'Consolas', 'Courier New', monospace; font-size: 1.95rem; color: #ffffff; letter-spacing: 6.5px; text-shadow: 0px 2px 4px rgba(0,0,0,0.4);">
                                            <span id="mc-number">XXXX XXXX XXXX XXXX</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Bottom Details -->
                                    <div class="d-flex flex-column position-relative z-1">
                                        <!-- Valid Row -->
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="d-flex flex-column text-start me-2" style="font-size: 0.45rem; color: #6a8276; font-weight: 700; line-height: 1.2; letter-spacing: 1px;">
                                                <span>VALID</span>
                                                <span>THRU</span>
                                            </div>
                                            <div style="font-family: 'Consolas', 'Courier New', monospace; font-size: 1.05rem; color: #d4b572; font-weight: bold; letter-spacing: 1.5px;" id="mc-valid">
                                                XX/XX
                                            </div>
                                        </div>
                                        
                                        <!-- Names Row -->
                                        <div class="d-flex justify-content-between align-items-end w-100">
                                            <div>
                                                <div style="font-size: 0.55rem; color: #d4b572; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px; font-weight: 600;">Member Name</div>
                                                <div style="font-family: 'Inter', sans-serif; font-size: 1.15rem; color: #ffffff; font-weight: 500; letter-spacing: 2px; text-transform: uppercase;" id="mc-name">Loading...</div>
                                            </div>
                                            <div class="text-end">
                                                <div style="font-size: 0.55rem; color: #d4b572; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px; font-weight: 600;">Member Since</div>
                                                <div style="font-family: 'Inter', sans-serif; font-size: 1.15rem; color: #d4b572; font-weight: 500; letter-spacing: 2px; text-transform: uppercase;" id="mc-since">MMM YYYY</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="mc-cvv" style="display: none;"></div>
                                </div>
                                
                                <!-- Card Face (Back) -->
                                <div class="card-face card-back position-absolute w-100 h-100" style="background-color: #0b1712; border-radius: 20px; box-shadow: 0 30px 60px rgba(0,0,0,0.6); border: 1px solid rgba(255, 255, 255, 0.03); display: flex; flex-direction: column; overflow: hidden; backface-visibility: hidden; transform: rotateY(180deg);">
                                    <!-- Magnetic Stripe -->
                                    <div style="width: 100%; height: 50px; background-color: #000; margin-top: 30px;"></div>
                                    
                                    <div style="padding: 20px 40px; display: flex; flex-direction: column; flex-grow: 1; justify-content: space-between;">
                                        
                                        <!-- Signature & CVV Row -->
                                        <div class="d-flex align-items-center mt-3">
                                            <div style="background-color: #e0e0e0; height: 45px; flex-grow: 1; border-radius: 4px; position: relative; overflow: hidden;">
                                                <div style="background: repeating-linear-gradient(45deg, transparent, transparent 5px, rgba(0,0,0,0.05) 5px, rgba(0,0,0,0.05) 10px); width: 100%; height: 100%;"></div>
                                                <span style="font-family: 'Inter', sans-serif; font-style: italic; font-weight: 500; font-size: 1.2rem; color: #333; position: absolute; top: 50%; left: 20px; transform: translateY(-50%); letter-spacing: 1px;" id="mc-signature">Loading...</span>
                                            </div>
                                            <div class="d-flex flex-column align-items-center ms-3">
                                                <div style="font-size: 0.5rem; color: #7f938b; letter-spacing: 1px; margin-bottom: 3px; font-weight: bold;">CVV</div>
                                                <div style="background-color: #fff; height: 35px; width: 55px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-family: 'Consolas', monospace; font-size: 1.1rem; font-weight: bold; color: #000; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);" id="mc-cvv-back">
                                                    XXX
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- QR Code & Info -->
                                        <div class="d-flex align-items-center justify-content-center" style="margin-top: auto; margin-bottom: auto;">
                                            <div style="width: 65px; height: 65px; background-color: #fff; border-radius: 6px; padding: 4px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
                                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&color=0b1712&bgcolor=ffffff&data=https://medusarestaurant.com/elite-portal" alt="QR Code" style="width: 100%; height: 100%;">
                                            </div>
                                            <div class="ms-4 text-start">
                                                <div style="color: #d4b572; font-size: 0.75rem; font-family: 'Cinzel', serif; font-weight: bold; letter-spacing: 1.5px; margin-bottom: 3px;"><i class="fa-solid fa-qrcode me-2"></i> SCAN FOR PORTAL</div>
                                                <div style="color: rgba(255,255,255,0.5); font-size: 0.6rem; max-width: 200px; line-height: 1.4;">Access your digital portfolio, exclusive menus, track your reward points, and manage reservations.</div>
                                            </div>
                                        </div>
                                        
                                        <!-- Fine Print -->
                                        <div style="font-size: 0.55rem; color: rgba(255,255,255,0.4); text-align: center; margin-top: auto; line-height: 1.4; padding: 0 10px;">
                                            This card is the property of Medusa Restaurant. If found, please return to:<br>
                                            <strong style="color: rgba(255,255,255,0.6);">SCO 44,45, District One Market, Sector 68, SAS Nagar, Punjab 140308</strong><br>
                                            <strong style="color: #d4b572; font-size: 0.65rem; display: inline-block; margin-top: 6px; letter-spacing: 0.5px;">contact@medusa.com | +91 94272 72798</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="mc-info" style="display:none; width: 100%; max-width: 1200px; margin: 40px auto 0;">
                            <div class="row g-3 text-start">
                                <div class="col-lg-3 col-md-6">
                                    <div class="p-3" style="background-color: #ffffff; border: 1px solid rgba(212, 181, 114, 0.4); border-radius: 12px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                                        <h6 style="color: #b8912e; font-family: 'Cinzel', serif; font-weight: bold; letter-spacing: 1px; margin-bottom: 8px;"><i class="fa-solid fa-gem me-2"></i> How to Use</h6>
                                        <p style="font-size: 0.8rem; color: #555555; margin-bottom: 0; line-height: 1.5;">Present this digital pass at the restaurant or tap it during checkout to automatically apply your tier benefits and earn reward points.</p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="p-3" style="background-color: #ffffff; border: 1px solid rgba(212, 181, 114, 0.4); border-radius: 12px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                                        <h6 style="color: #b8912e; font-family: 'Cinzel', serif; font-weight: bold; letter-spacing: 1px; margin-bottom: 8px;"><i class="fa-solid fa-location-dot me-2"></i> Where to Use</h6>
                                        <p style="font-size: 0.8rem; color: #555555; margin-bottom: 0; line-height: 1.5;">Valid at all Medusa physical locations, exclusive dining lounges, and our online ordering platform.</p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="p-3" style="background-color: #ffffff; border: 1px solid rgba(212, 181, 114, 0.4); border-radius: 12px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                                        <h6 style="color: #b8912e; font-family: 'Cinzel', serif; font-weight: bold; letter-spacing: 1px; margin-bottom: 8px;"><i class="fa-regular fa-clock me-2"></i> Validity</h6>
                                        <p style="font-size: 0.8rem; color: #555555; margin-bottom: 0; line-height: 1.5;">This card is valid until <strong id="mc-info-valid" style="color: #333333;">XX/XX</strong>. Renew before expiration to maintain your tier status.</p>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <div class="p-3" style="background-color: #ffffff; border: 1px solid rgba(212, 181, 114, 0.4); border-radius: 12px; height: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                                        <h6 style="color: #b8912e; font-family: 'Cinzel', serif; font-weight: bold; letter-spacing: 1px; margin-bottom: 8px;"><i class="fa-solid fa-headset me-2"></i> Support</h6>
                                        <p style="font-size: 0.8rem; color: #555555; margin-bottom: 0; line-height: 1.5;">Experiencing issues? Email us at <a href="mailto:contact@medusa.com" style="color: #b8912e; text-decoration: none;">contact@medusa.com</a> or call <strong style="color: #333333;">+91 94272 72798</strong>.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Membership Guide Data -->
                            <div class="mt-5 text-start w-100 mx-auto" style="max-width: 1200px;">
                                <h5 style="font-family: 'Cinzel', serif; color: #b8912e; border-bottom: 1px solid rgba(212, 181, 114, 0.3); padding-bottom: 10px; margin-bottom: 25px; font-weight: bold;"><i class="fa-solid fa-crown me-2"></i> Membership Privileges & Usage Guide</h5>
                                
                                <div class="d-flex flex-column gap-4 px-2">
                                    <!-- Privilege Item 1 -->
                                    <div class="d-flex align-items-start pb-4" style="border-bottom: 1px dashed rgba(212, 181, 114, 0.3);">
                                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px; border-radius: 50%; background: linear-gradient(135deg, #fdfbf7, #f4ecd8); border: 1px solid rgba(212, 181, 114, 0.4); color: #b8912e; font-size: 1.4rem;">
                                            <i class="fa-solid fa-mobile-screen-button"></i>
                                        </div>
                                        <div class="ms-4">
                                            <h6 style="color: #111; font-family: 'Cinzel', serif; font-weight: 700; font-size: 1.1rem; margin-bottom: 8px; letter-spacing: 0.5px;">Digital & Physical Usage</h6>
                                            <ul style="font-size: 0.85rem; color: #555555; line-height: 1.6; padding-left: 18px; margin-bottom: 0;">
                                                <li class="mb-1"><strong>In-Person Dining:</strong> Simply present this digital pass on your smartphone to your server, or use the physical Medusa NFC card to tap and instantly sync your profile.</li>
                                                <li class="mb-1"><strong>Online Ordering:</strong> Your membership tier is securely linked to your account. Log in to automatically receive tier discounts and earn points on deliveries.</li>
                                                <li><strong>Exclusive Lounges:</strong> Flash your elite pass at the entrance of our Medusa VIP lounges for immediate priority access.</li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Privilege Item 2 -->
                                    <div class="d-flex align-items-start pb-4" style="border-bottom: 1px dashed rgba(212, 181, 114, 0.3);">
                                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px; border-radius: 50%; background: linear-gradient(135deg, #fdfbf7, #f4ecd8); border: 1px solid rgba(212, 181, 114, 0.4); color: #b8912e; font-size: 1.4rem;">
                                            <i class="fa-solid fa-star"></i>
                                        </div>
                                        <div class="ms-4">
                                            <h6 style="color: #111; font-family: 'Cinzel', serif; font-weight: 700; font-size: 1.1rem; margin-bottom: 8px; letter-spacing: 0.5px;">Earning & Redeeming Rewards</h6>
                                            <ul style="font-size: 0.85rem; color: #555555; line-height: 1.6; padding-left: 18px; margin-bottom: 0;">
                                                <li class="mb-1"><strong>Points Accumulation:</strong> Earn 10 points for every ₹100 spent. Points are credited within 24 hours of your dining experience.</li>
                                                <li class="mb-1"><strong>Reward Redemption:</strong> Redeem points for complimentary premium appetizers, exclusive cocktails, or direct discounts on your final bill.</li>
                                                <li><strong>Special Occasions:</strong> Enjoy a complimentary premium dessert and a complimentary bottle of house wine during your birthday month.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Privilege Item 3 -->
                                    <div class="d-flex align-items-start pb-4" style="border-bottom: 1px dashed rgba(212, 181, 114, 0.3);">
                                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px; border-radius: 50%; background: linear-gradient(135deg, #fdfbf7, #f4ecd8); border: 1px solid rgba(212, 181, 114, 0.4); color: #b8912e; font-size: 1.4rem;">
                                            <i class="fa-solid fa-wine-glass"></i>
                                        </div>
                                        <div class="ms-4">
                                            <h6 style="color: #111; font-family: 'Cinzel', serif; font-weight: 700; font-size: 1.1rem; margin-bottom: 8px; letter-spacing: 0.5px;">Fine Dining & Culinary Perks</h6>
                                            <ul style="font-size: 0.85rem; color: #555555; line-height: 1.6; padding-left: 18px; margin-bottom: 0;">
                                                <li class="mb-1"><strong>Priority Reservations:</strong> Skip the waitlist. Medusa Elite members get priority seating, even on our busiest weekend evenings.</li>
                                                <li class="mb-1"><strong>Chef's Tasting Menu:</strong> Gain exclusive access to seasonal, off-menu culinary creations prepared specially by our head chef.</li>
                                                <li><strong>Sommelier Service:</strong> Receive complimentary expert wine pairings for your multi-course meals.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Privilege Item 4 -->
                                    <div class="d-flex align-items-start pb-2">
                                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px; border-radius: 50%; background: linear-gradient(135deg, #fdfbf7, #f4ecd8); border: 1px solid rgba(212, 181, 114, 0.4); color: #b8912e; font-size: 1.4rem;">
                                            <i class="fa-solid fa-car"></i>
                                        </div>
                                        <div class="ms-4">
                                            <h6 style="color: #111; font-family: 'Cinzel', serif; font-weight: 700; font-size: 1.1rem; margin-bottom: 8px; letter-spacing: 0.5px;">Valet & Luxury Hospitality</h6>
                                            <ul style="font-size: 0.85rem; color: #555555; line-height: 1.6; padding-left: 18px; margin-bottom: 0;">
                                                <li class="mb-1"><strong>Complimentary Valet:</strong> Arrive in style. Show your digital card to our valet staff for complimentary premium parking.</li>
                                                <li class="mb-1"><strong>Dedicated Concierge:</strong> Enjoy the personalized service of a dedicated table concierge throughout your entire dining experience.</li>
                                                <li><strong>Private Events:</strong> Receive VIP invitations to our closed-door wine tasting events, mixology masterclasses, and gala dinners.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const tab = document.getElementById('pill-membership-tab');
                            if (tab) {
                                tab.addEventListener('shown.bs.tab', loadMembershipCard);
                            }
                            
                            const card3d = document.getElementById('membership-card-3d');
                            let isCardFlipped = false;
                            
                            if (card3d) {
                                card3d.style.cursor = 'pointer';
                                card3d.title = 'Click to flip card';
                                
                                card3d.addEventListener('click', () => {
                                    isCardFlipped = !isCardFlipped;
                                    card3d.style.transition = 'transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
                                    card3d.style.transform = `rotateY(${isCardFlipped ? 180 : 0}deg)`;
                                    
                                    setTimeout(() => {
                                        card3d.style.transition = 'transform 0.1s ease-out';
                                    }, 500);
                                });

                                document.addEventListener('mousemove', (e) => {
                                    if (document.getElementById('mc-card').style.display === 'none') return;
                                    
                                    const rect = card3d.getBoundingClientRect();
                                    const cardCenterX = rect.left + rect.width / 2;
                                    const cardCenterY = rect.top + rect.height / 2;
                                    
                                    const mouseX = e.clientX;
                                    const mouseY = e.clientY;
                                    
                                    const rotateX = ((cardCenterY - mouseY) / (window.innerHeight / 2)) * 15;
                                    let rotateY = ((mouseX - cardCenterX) / (window.innerWidth / 2)) * 15;
                                    
                                    if (isCardFlipped) {
                                        rotateY += 180;
                                    }
                                    
                                    card3d.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
                                });
                                
                                document.addEventListener('mouseleave', () => {
                                    card3d.style.transform = `rotateX(0deg) rotateY(${isCardFlipped ? 180 : 0}deg)`;
                                });
                            }
                        });

                        async function loadMembershipCard() {
                            document.getElementById('mc-spinner').style.display = 'block';
                            document.getElementById('mc-card').style.display = 'none';
                            document.getElementById('mc-info').style.display = 'none';
                            document.getElementById('mc-purchase').style.display = 'none';

                            try {
                                const res = await fetch('api/get-membership-card.php');
                                const data = await res.json();
                                if (data.success) {
                                    document.getElementById('mc-spinner').style.display = 'none';
                                    
                                    if (data.has_card && !data.expired) {
                                        // Valid card
                                        document.getElementById('mc-card').style.display = 'flex';
                                        document.getElementById('mc-card').style.opacity = '1';
                                        document.getElementById('mc-info').style.display = 'block';
                                        
                                        document.getElementById('mc-number').innerText = data.card.card_number;
                                        document.getElementById('mc-name').innerText = data.card.member_name;
                                        document.getElementById('mc-valid').innerText = data.card.valid_thru;
                                        document.getElementById('mc-cvv').innerText = data.card.cvv;
                                        if (document.getElementById('mc-cvv-back')) document.getElementById('mc-cvv-back').innerText = data.card.cvv;
                                        if (document.getElementById('mc-signature')) document.getElementById('mc-signature').innerText = data.card.member_name;
                                        if (document.getElementById('mc-since') && data.card.member_since) {
                                            document.getElementById('mc-since').innerText = data.card.member_since;
                                        }
                                        if (document.getElementById('mc-tier') && data.card.member_tier) {
                                            document.getElementById('mc-tier').innerText = data.card.member_tier;
                                        }
                                        if (document.getElementById('mc-info-valid')) {
                                            document.getElementById('mc-info-valid').innerText = data.card.valid_thru;
                                        }
                                    } else {
                                        // No card or expired
                                        document.getElementById('mc-purchase').style.display = 'block';
                                        if (data.expired) {
                                            document.getElementById('mc-purchase-title').innerText = 'Renew Your Medusa Elite Pass';
                                            document.getElementById('mc-purchase-desc').innerText = 'Your membership has expired. Renew now to continue enjoying exclusive benefits for just ₹599.';
                                            document.getElementById('mc-buy-btn').innerHTML = '<i class="fas fa-sync"></i> Renew Now for ₹599';
                                            
                                            // Show expired card in background slightly dimmed
                                            document.getElementById('mc-card').style.display = 'flex';
                                            document.getElementById('mc-card').style.opacity = '0.4';
                                            document.getElementById('mc-number').innerText = data.card.card_number;
                                            document.getElementById('mc-name').innerText = data.card.member_name;
                                            document.getElementById('mc-valid').innerText = data.card.valid_thru;
                                            document.getElementById('mc-cvv').innerText = data.card.cvv;
                                            if (document.getElementById('mc-cvv-back')) document.getElementById('mc-cvv-back').innerText = data.card.cvv;
                                            if (document.getElementById('mc-signature')) document.getElementById('mc-signature').innerText = data.card.member_name;
                                        } else {
                                            document.getElementById('mc-purchase-title').innerText = 'Get Your Medusa Elite Pass';
                                            document.getElementById('mc-purchase-desc').innerText = 'Unlock premium features, seamless billing, and exclusive rewards for just ₹599.';
                                            document.getElementById('mc-buy-btn').innerHTML = '<i class="fas fa-lock"></i> Buy Now for ₹599';
                                        }
                                    }
                                }
                            } catch(err) {
                                if(typeof showToast === 'function') showToast('Error loading membership card', 'error');
                            }
                        }

                        function buyMembership() {
                            const razorpayKey = "<?php echo get_env_var('RAZORPAY_KEY_ID'); ?>";
                            if (!razorpayKey) {
                                alert("Payment configuration missing. Please try again later.");
                                return;
                            }
                            const options = {
                                "key": razorpayKey,
                                "amount": 59900, // ₹599
                                "currency": "INR",
                                "name": "Medusa Elite Pass",
                                "description": "Membership Pass Purchase/Renewal",
                                "handler": async function (response) {
                                    document.getElementById('mc-buy-btn').disabled = true;
                                    document.getElementById('mc-buy-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                                    
                                    try {
                                        const res = await fetch('api/buy-membership-card.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ razorpay_payment_id: response.razorpay_payment_id })
                                        });
                                        const result = await res.json();
                                        if (result.success) {
                                            if(typeof showToast === 'function') showToast(result.message, 'success');
                                            loadMembershipCard();
                                        } else {
                                            alert(result.message || 'Payment processing failed.');
                                        }
                                    } catch(e) {
                                        alert('Error verifying payment.');
                                    }
                                    
                                    document.getElementById('mc-buy-btn').disabled = false;
                                },
                                "prefill": {
                                    "name": "<?php echo addslashes((string)$user_name); ?>",
                                    "email": "<?php echo addslashes((string)$user_email); ?>"
                                },
                                "theme": {
                                    "color": "#dfba86"
                                }
                            };

                            const rzp = new window.Razorpay(options);
                            rzp.open();
                        }
                    </script>
                </div>
                <!-- ══ TAB 4: ACCOUNT SETTINGS ══ -->
                <?php else: ?>
                <div class="tab-pane fade show active" id="pill-settings" role="tabpanel">
                    <h2 class="section-title mb-1" style="border:none; padding-bottom:0;">Account Settings</h2>
                    <p class="text-muted mb-4" style="font-size: 0.9rem;">Manage your account preferences and security settings.</p>
                    
                    <div class="settings-subnav nav" role="tablist">
                        <button class="nav-link active" id="subnav-account-tab" data-bs-toggle="tab" data-bs-target="#subnav-account" type="button" role="tab"><i class="fa-regular fa-user"></i> ACCOUNT</button>
                        <button class="nav-link" id="subnav-notifications-tab" data-bs-toggle="tab" data-bs-target="#subnav-notifications" type="button" role="tab"><i class="fa-regular fa-bell"></i> NOTIFICATIONS</button>
                        <button class="nav-link" id="subnav-preferences-tab" data-bs-toggle="tab" data-bs-target="#subnav-preferences" type="button" role="tab"><i class="fa-solid fa-sliders"></i> PREFERENCES</button>
                        <button class="nav-link" id="subnav-privacy-tab" data-bs-toggle="tab" data-bs-target="#subnav-privacy" type="button" role="tab"><i class="fa-solid fa-lock"></i> PRIVACY</button>
                    </div>

                    <div class="tab-content" id="settings-tabContent">
                        <!-- ACCOUNT TAB -->
                        <div class="tab-pane fade show active" id="subnav-account" role="tabpanel">
                            <!-- Account Information -->
                        <div class="bg-card-custom p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4 class="m-0 text-dark" style="font-size: 1.1rem; display: flex; align-items: center; gap: 10px;">
                                    <div style="background: rgba(0,0,0,0.05); padding: 8px; border-radius: 50%; display: flex;">
                                        <i class="fa-regular fa-user" style="font-size: 0.9rem;"></i>
                                    </div>
                                    Account Information
                                </h4>
                                <a href="profile.php?edit=1" class="text-dark text-decoration-none" style="font-size: 0.85rem; font-weight: 500;">
                                    Edit Profile <i class="fa-solid fa-pencil ms-1" style="font-size: 0.75rem;"></i>
                                </a>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 p-3 border-bottom border-end">
                                    <label class="text-muted d-block mb-1" style="font-size: 0.8rem;">Full Name</label>
                                    <div class="text-dark" style="font-size: 0.95rem; font-weight: 500;"><?php echo htmlspecialchars($user_name); ?></div>
                                </div>
                                <div class="col-md-6 p-3 border-bottom">
                                    <label class="text-muted d-block mb-1" style="font-size: 0.8rem;">Date of Birth</label>
                                    <div class="text-dark d-flex justify-content-between align-items-center" style="font-size: 0.95rem; font-weight: 500;">
                                        15 Jan 1990 <i class="fa-regular fa-calendar text-muted"></i>
                                    </div>
                                </div>
                                <div class="col-md-6 p-3 border-bottom border-end">
                                    <label class="text-muted d-block mb-1" style="font-size: 0.8rem;">Email Address</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="text-dark" style="font-size: 0.95rem; font-weight: 500;"><?php echo htmlspecialchars($user_email); ?></div>
                                        <?php if ($is_email_verified): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success" style="font-weight: 500; font-size: 0.7rem;">Verified <i class="fa-solid fa-check"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 p-3 border-bottom">
                                    <label class="text-muted d-block mb-1" style="font-size: 0.8rem;">Membership Tier</label>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="text-dark d-flex align-items-center gap-2" style="font-size: 0.95rem; font-weight: 500;">
                                            <i class="fa-solid fa-crown text-gold"></i> <?php echo htmlspecialchars($user_tier_name); ?> Member
                                        </div>
                                        <div class="text-secondary" style="font-size: 0.75rem;">Member since May 2024</div>
                                    </div>
                                </div>
                                <div class="col-md-6 p-3 border-end">
                                    <label class="text-muted d-block mb-1" style="font-size: 0.8rem;">Mobile Number</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="text-dark" style="font-size: 0.95rem; font-weight: 500;"><?php echo htmlspecialchars($phone); ?></div>
                                        <?php if ($is_phone_verified): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success" style="font-weight: 500; font-size: 0.7rem;">Verified <i class="fa-solid fa-check"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6 p-3">
                                    <label class="text-muted d-block mb-1" style="font-size: 0.8rem;">Preferred Ambience</label>
                                    <div class="text-dark" style="font-size: 0.95rem; font-weight: 500;">Lounge, Live Music, Outdoor Seating</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Cards Grid -->
                        <div class="row g-4 mb-4">
                            <!-- Change Password -->
                            <div class="col-md-6 col-lg-3">
                                <div class="settings-action-card">
                                    <div class="settings-icon-container">
                                        <i class="fa-solid fa-lock"></i>
                                    </div>
                                    <h5 class="text-dark" style="font-size: 1rem; font-weight: 600;">Change Password</h5>
                                    <p class="text-muted flex-grow-1" style="font-size: 0.8rem; line-height: 1.5; margin-bottom: 1.5rem;">Keep your account safe with a strong password.</p>
                                    <button class="btn btn-outline-dark btn-sm text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; border-radius: 6px; padding: 0.5rem;" onclick="goToSettingsTab('security');">Change Password <i class="fa-solid fa-chevron-right ms-1"></i></button>
                                </div>
                            </div>
                            <!-- Two-Factor Authentication -->
                            <div class="col-md-6 col-lg-3">
                                <div class="settings-action-card">
                                    <div class="settings-icon-container">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </div>
                                    <h5 class="text-dark" style="font-size: 1rem; font-weight: 600;">Two-Factor<br>Authentication</h5>
                                    <p class="text-muted flex-grow-1" style="font-size: 0.8rem; line-height: 1.5; margin-bottom: 1.5rem;">Add an extra layer of security to your account.</p>
                                    <div class="d-flex justify-content-between align-items-center" style="cursor: pointer;" onclick="goToSettingsTab('security');">
                                        <?php if ($settings['two_factor_enabled']): ?>
                                            <span id="tfa-status-badge" class="badge bg-success bg-opacity-10 text-success px-2 py-1" style="font-size: 0.75rem;">Enabled <i class="fa-solid fa-check ms-1"></i></span>
                                        <?php else: ?>
                                            <span id="tfa-status-badge" class="badge bg-secondary bg-opacity-10 text-secondary px-2 py-1" style="font-size: 0.75rem;">Disabled <i class="fa-solid fa-xmark ms-1"></i></span>
                                        <?php endif; ?>
                                        <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.7rem;"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Login Activity -->
                            <div class="col-md-6 col-lg-3">
                                <div class="settings-action-card">
                                    <div class="settings-icon-container">
                                        <i class="fa-regular fa-clock"></i>
                                    </div>
                                    <h5 class="text-dark" style="font-size: 1rem; font-weight: 600;">Login Activity</h5>
                                    <p class="text-muted flex-grow-1" style="font-size: 0.8rem; line-height: 1.5; margin-bottom: 1.5rem;">Review your recent login activity.</p>
                                    <button class="btn btn-outline-dark btn-sm text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.5px; border-radius: 6px; padding: 0.5rem;" onclick="goToSettingsTab('security');">View Activity <i class="fa-solid fa-chevron-right ms-1"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="bg-card-custom p-4 rounded-4 border danger-zone-card d-flex align-items-center flex-wrap gap-4">
                            <div class="settings-icon-container danger-zone-icon m-0" style="width: 50px; height: 50px;">
                                <i class="fa-solid fa-trash-can"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h5 class="text-danger mb-1" style="font-size: 1.05rem; font-weight: 600;">Danger Zone</h5>
                                <p class="text-muted m-0" style="font-size: 0.8rem;">Once you delete your account, there is no going back.<br>Please be certain.</p>
                            </div>
                            <button type="button" class="btn btn-outline-danger text-uppercase fw-bold bg-card-custom" style="font-size: 0.8rem; letter-spacing: 0.5px; padding: 0.6rem 1.2rem; border-color: rgba(220,53,69,0.3);" onclick="showDeleteAccountModal()">Delete Account Permanently</button>
                        </div>

                    </div> <!-- End of subnav-account -->

                        <!-- ══ TAB: NOTIFICATIONS ══ -->
                        <div class="tab-pane fade" id="subnav-notifications" role="tabpanel">
                            <div class="bg-card-custom p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-1" style="font-size: 1.1rem; font-weight: 600;">Notification Preferences</h4>
                                <p class="text-muted mb-4" style="font-size: 0.85rem;">Manage how we contact you.</p>
                                
                                <form id="notifForm" onsubmit="submitSettingsForm(event)">
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="notif_email" <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-dark" style="font-size: 0.9rem;" for="notif_email">Email Notifications <span class="text-muted d-block" style="font-size: 0.75rem;">Order receipts, booking alerts</span></label>
                                    </div>
                                    <div class="mb-3 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="notif_sms" <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-dark" style="font-size: 0.9rem;" for="notif_sms">SMS / WhatsApp Alerts <span class="text-muted d-block" style="font-size: 0.75rem;">Instant delivery progress updates</span></label>
                                    </div>
                                    <div class="mb-4 form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="notif_promo" <?php echo $settings['promotional_offers'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-dark" style="font-size: 0.9rem;" for="notif_promo">Promotional Offers <span class="text-muted d-block" style="font-size: 0.75rem;">Discounts, special menus, chef events</span></label>
                                    </div>
                                    <button type="submit" class="btn btn-dark text-uppercase fw-bold mt-2" style="font-size: 0.8rem; letter-spacing: 0.5px; border-radius: 6px; padding: 0.6rem 1.2rem;">Save Notifications</button>
                                </form>
                            </div>
                        </div>

                        <!-- ══ TAB: PREFERENCES ══ -->
                        <div class="tab-pane fade" id="subnav-preferences" role="tabpanel">
                            <div class="bg-card-custom p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-3" style="font-size: 1.1rem; font-weight: 600;">System Preferences</h4>
                                <form id="prefForm" onsubmit="submitSettingsForm(event)">
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label class="text-muted d-block mb-1" style="font-size: 0.8rem;" for="pref_lang">Language Preference</label>
                                            <select id="pref_lang" class="form-select" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);">
                                                <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                                <option value="hi" <?php echo $settings['language'] === 'hi' ? 'selected' : ''; ?>>Hindi</option>
                                                <option value="es" <?php echo $settings['language'] === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                                <option value="fr" <?php echo $settings['language'] === 'fr' ? 'selected' : ''; ?>>French</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted d-block mb-1" style="font-size: 0.8rem;" for="pref_theme">Theme Preference</label>
                                            <select id="pref_theme" class="form-select" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);">
                                                <option value="dark" <?php echo $settings['theme'] === 'dark' ? 'selected' : ''; ?>>Medusa Dark (Gold)</option>
                                                <option value="light" <?php echo $settings['theme'] === 'light' ? 'selected' : ''; ?>>Medusa Light</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-dark text-uppercase fw-bold" style="font-size: 0.8rem; letter-spacing: 0.5px; border-radius: 6px; padding: 0.6rem 1.2rem;">Save Preferences</button>
                                </form>
                            </div>
                        </div>

                        <!-- ══ TAB: PRIVACY ══ -->
                        <div class="tab-pane fade" id="subnav-privacy" role="tabpanel">
                            <div class="bg-card-custom p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-1" style="font-size: 1.1rem; font-weight: 600;">Privacy Controls</h4>
                                <p class="text-muted mb-4" style="font-size: 0.85rem;">Manage how your data is used across the platform.</p>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="privacy_analytics" checked>
                                    <label class="form-check-label text-dark" style="font-size: 0.9rem;" for="privacy_analytics">Allow Analytics Tracking <span class="text-muted d-block" style="font-size: 0.75rem;">Help us improve by sharing usage data</span></label>
                                </div>
                                <div class="form-check form-switch mb-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="privacy_marketing" checked>
                                    <label class="form-check-label text-dark" style="font-size: 0.9rem;" for="privacy_marketing">Marketing Partners <span class="text-muted d-block" style="font-size: 0.75rem;">Share data with partners for tailored offers</span></label>
                                </div>
                                <button class="btn btn-dark text-uppercase fw-bold" style="font-size: 0.8rem; letter-spacing: 0.5px; border-radius: 6px; padding: 0.6rem 1.2rem;">Save Privacy Settings</button>
                            </div>
                        </div>

                    </div> <!-- End of settings-tabContent -->
                </div> <!-- End of pill-settings -->

                <!-- ══ NEW TAB 5: SECURITY & SESSIONS ══ -->
                <div class="tab-pane fade" id="pill-security" role="tabpanel">
                    <h2 class="section-title mb-1" style="border:none; padding-bottom:0;"><i class="fa-solid fa-shield-halved"></i> Security & Sessions</h2>
                    <p class="text-muted mb-4" style="font-size: 0.9rem;">Manage your password, 2FA, and trusted devices.</p>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <!-- Change Password -->
                            <div class="bg-card-custom p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-3" style="font-size: 1.1rem; font-weight: 600;">Change Password</h4>
                                <form id="passwordForm" onsubmit="submitPasswordForm(event)">
                                    <!-- Hidden username field for accessibility and password managers -->
                                    <input type="text" id="hidden_username" name="username" autocomplete="username" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" style="display:none;" aria-hidden="true">
                                    <div class="mb-3">
                                        <label class="text-muted d-block mb-1" style="font-size: 0.8rem;" for="cur_pass">Current Password *</label>
                                        <div class="input-group">
                                            <input type="password" id="cur_pass" autocomplete="current-password" class="form-control" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);" required>
                                            <button class="btn btn-outline-secondary bg-card-custom" type="button" onclick="togglePasswordVisibility('cur_pass', this)" style="border-color: rgba(0,0,0,0.1); color: #6c757d;"><i class="fa-regular fa-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted d-block mb-1" style="font-size: 0.8rem;" for="new_pass">New Password *</label>
                                        <div class="input-group">
                                            <input type="password" id="new_pass" autocomplete="new-password" class="form-control" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);" oninput="checkPassStrength(this.value)" required>
                                            <button class="btn btn-outline-secondary bg-card-custom" type="button" onclick="togglePasswordVisibility('new_pass', this)" style="border-color: rgba(0,0,0,0.1); color: #6c757d;"><i class="fa-regular fa-eye"></i></button>
                                        </div>
                                        <div class="strength-bar mt-2">
                                            <div class="seg" id="seg1"></div>
                                            <div class="seg" id="seg2"></div>
                                            <div class="seg" id="seg3"></div>
                                            <div class="seg" id="seg4"></div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted d-block mb-1" style="font-size: 0.8rem;" for="conf_pass">Confirm New Password *</label>
                                        <div class="input-group">
                                            <input type="password" id="conf_pass" autocomplete="new-password" class="form-control" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);" required>
                                            <button class="btn btn-outline-secondary bg-card-custom" type="button" onclick="togglePasswordVisibility('conf_pass', this)" style="border-color: rgba(0,0,0,0.1); color: #6c757d;"><i class="fa-regular fa-eye"></i></button>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-dark text-uppercase fw-bold" style="font-size: 0.8rem; letter-spacing: 0.5px; border-radius: 6px; padding: 0.6rem 1.2rem;">Update Password</button>
                                </form>
                            </div>

                            <!-- Two Factor Authentication -->
                            <div class="bg-card-custom p-4 rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h4 class="text-dark mb-1" style="font-size: 1.1rem; font-weight: 600;">Two-Factor Authentication</h4>
                                        <p class="text-muted m-0" style="font-size: 0.85rem;">Secure login with dynamic OTP codes.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="two_factor_toggle" <?php echo $settings['two_factor_enabled'] ? 'checked' : ''; ?> onchange="toggle2FA(this)">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <!-- New: Login Alerts -->
                            <div class="bg-card-custom p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h4 class="text-dark mb-1" style="font-size: 1.1rem; font-weight: 600;">Login Alerts</h4>
                                        <p class="text-muted m-0" style="font-size: 0.85rem;">Get notified of unrecognized logins.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="login_alerts_toggle" <?php echo (!isset($settings['login_alerts']) || $settings['login_alerts']) ? 'checked' : ''; ?> onchange="toggleLoginAlerts(this)">
                                    </div>
                                </div>
                            </div>

                            <!-- New: Trusted Devices -->
                            <div class="bg-card-custom p-4 rounded-4 border mb-4" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-3" style="font-size: 1.1rem; font-weight: 600;">Trusted Devices</h4>
                                <ul class="list-group list-group-flush mb-3" id="trusted-devices-list">
                                    <?php
                                    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                                    $current_ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                                    $has_devices = false;

                                    foreach ($trusted_devices as $index => $device):
                                        $has_devices = true;
                                        $device_name = parseUserAgent($device['user_agent']);
                                        $is_current = ($device['ip_address'] === $current_ip && $device['user_agent'] === $current_ua);
                                        $border_class = $index === 0 ? 'border-0' : 'border-top';
                                    ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0 <?php echo $border_class; ?>" id="device-item-<?php echo $device['id']; ?>">
                                            <div>
                                                <div style="font-size: 0.95rem; font-weight: 500;"><?php echo htmlspecialchars($device_name); ?></div>
                                                <small class="text-muted">
                                                    <?php if ($is_current): ?>
                                                        Currently active
                                                    <?php else: ?>
                                                        Last used: <?php echo date('M d, Y • h:i A', strtotime($device['last_used'])); ?>
                                                    <?php endif; ?>
                                                    <span class="ms-1" style="font-size: 0.75rem; opacity: 0.7;">(IP: <?php echo htmlspecialchars($device['ip_address'] === '::1' || $device['ip_address'] === '127.0.0.1' ? 'Localhost' : $device['ip_address']); ?>)</span>
                                                </small>
                                            </div>
                                            <?php if ($is_current): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-danger" onclick="revokeDevice(this, <?php echo $device['id']; ?>)">Revoke</button>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (!$has_devices): ?>
                                        <li class="list-group-item text-center text-muted py-3 px-0 border-0">
                                            No trusted devices found.
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <!-- New: Account Recovery -->
                            <div class="bg-card-custom p-4 rounded-4 border" style="border-color: rgba(0,0,0,0.05) !important;">
                                <h4 class="text-dark mb-3" style="font-size: 1.1rem; font-weight: 600;">Account Recovery</h4>
                                <p class="text-muted mb-3" style="font-size: 0.85rem;">Add a fallback email in case you lose access.</p>
                                <div class="input-group">
                                    <input type="text" id="rec_fallback_field" class="form-control" placeholder="Fallback recovery address" value="<?php echo htmlspecialchars($user['recovery_email'] ?? ''); ?>" style="background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);" autocomplete="new-email" readonly onfocus="this.removeAttribute('readonly');">
                                    <button class="btn btn-dark" type="button" onclick="saveRecoveryEmail()">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Login Sessions -->
                    <div class="bg-card-custom p-4 rounded-4 border mt-4" style="border-color: rgba(0,0,0,0.05) !important;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="text-dark m-0" style="font-size: 1.1rem; font-weight: 600;">Recent Login Sessions</h4>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="logoutOtherDevices()">Logout Other Devices</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" style="font-size: 0.85rem; border: 1px solid rgba(0,0,0,0.05); border-radius: 8px; overflow: hidden;">
                                <thead style="background: rgba(0,0,0,0.02);">
                                    <tr>
                                        <th class="text-muted">IP Address</th>
                                        <th class="text-muted">Device / Browser</th>
                                        <th class="text-muted">Timestamp</th>
                                        <th class="text-muted">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $first = true;
                                    if (empty($login_logs)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No logs found</td>
                                        </tr>
                                    <?php else: foreach ($login_logs as $log): ?>
                                        <tr id="session-row-<?php echo $log['id']; ?>">
                                            <td class="text-dark" style="font-family: monospace;">
                                                <?php echo htmlspecialchars($log['ip_address'] === '::1' || $log['ip_address'] === '127.0.0.1' ? 'Localhost' : $log['ip_address']); ?>
                                            </td>
                                            <td class="text-muted" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                <?php
                                                    $ua = $log['user_agent'];
                                                    if (preg_match('/(Chrome|Safari|Firefox|Edge|MSIE|Trident|Opera)/i', $ua, $matches)) {
                                                        echo $matches[0];
                                                    } else {
                                                        echo "Browser";
                                                    }
                                                    echo (strpos(strtolower($ua), 'mobile') !== false) ? " (Mobile)" : " (Desktop)";
                                                ?>
                                            </td>
                                            <td class="text-muted"><?php echo date('d M Y, H:i:s', strtotime($log['login_time'])); ?></td>
                                            <td id="session-status-<?php echo $log['id']; ?>">
                                                <?php if (!empty($log['revoked'])): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-danger mb-1">Revoked</span>
                                                <?php elseif ($first): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success mb-1">Success</span>
                                                    <br><span class="text-muted" style="font-size: 0.75rem;"><i class="fa-solid fa-circle-dot me-1 text-success" style="font-size: 0.6rem;"></i>Current Session</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success mb-1">Success</span>
                                                    <br><button type="button" onclick="openRevokeModal(<?php echo $log['id']; ?>, this)" class="btn btn-link p-0 text-danger text-decoration-underline" style="font-size: 0.75rem; border: none; background: none;">Not you? Logout</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php $first = false; endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ══ TAB 6: CUSTOMER FEEDBACK ══ -->
                <div class="tab-pane fade" id="pill-feedback" role="tabpanel">
                    <h2 class="section-title"><i class="fa-solid fa-star"></i> Customer Feedback & Reviews</h2>
                    
                    <div class="row g-4">
                        <!-- Submit feedback form -->
                        <div class="col-md-6">
                            <h4 class="text-gold mb-3" style="font-size: 1.1rem; text-transform: uppercase;">Submit Review</h4>
                            <form id="feedbackForm" onsubmit="submitFeedbackForm(event)">
                                <div class="mb-3">
                                    <label class="form-label-medusa">Overall Experience Rating *</label>
                                    <div class="star-rating" id="feedback-stars">
                                        <i class="fa-solid fa-star" data-index="1"></i>
                                        <i class="fa-solid fa-star" data-index="2"></i>
                                        <i class="fa-solid fa-star" data-index="3"></i>
                                        <i class="fa-solid fa-star" data-index="4"></i>
                                        <i class="fa-solid fa-star" data-index="5"></i>
                                    </div>
                                    <input type="hidden" id="feedback-rating-val" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label-medusa" for="feedback_type">Feedback Category</label>
                                    <select id="feedback_type" class="form-select form-control-medusa">
                                        <option value="general">General Dining Feedback</option>
                                        <option value="suggestion">Improvement Suggestion</option>
                                        <option value="issue">Issue Report</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label-medusa" for="feedback_review">Review Comments *</label>
                                    <textarea id="feedback_review" rows="4" class="form-control form-control-medusa" placeholder="Write your dining review, feedback or complaints here..." required></textarea>
                                </div>

                                <button type="submit" class="btn-gold-medusa"><i class="fa-solid fa-paper-plane"></i> Submit Feedback</button>
                            </form>
                        </div>

                        <!-- Previous feedbacks list -->
                        <div class="col-md-6">
                            <h4 class="text-gold mb-3" style="font-size: 1.1rem; text-transform: uppercase;">Your Previous Feedback</h4>
                            <div id="feedback-history" style="max-height: 400px; overflow-y: auto;">
                                <?php if (empty($feedbacks)): ?>
                                    <div class="text-center py-4 bg-black rounded border border-secondary">
                                        <p class="text-dark-50 m-0" style="font-size: 0.9rem;">You haven't submitted any feedback yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($feedbacks as $fb): ?>
                                        <div class="bg-black p-3 rounded border border-secondary mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <div>
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fa-solid fa-star" style="font-size: 0.8rem; color: <?php echo $i <= $fb['rating'] ? 'var(--gold)' : 'rgba(255,255,255,0.15)'; ?>;"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <span class="badge bg-dark border border-secondary text-capitalize" style="font-size: 0.7rem;"><?php echo htmlspecialchars($fb['type']); ?></span>
                                            </div>
                                            <p class="m-0 text-dark-50" style="font-size: 0.88rem;"><?php echo nl2br(htmlspecialchars($fb['review'])); ?></p>
                                            <div class="text-end text-muted mt-2" style="font-size: 0.72rem;"><?php echo date('d M Y, h:i A', strtotime($fb['created_at'])); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ TAB 7: SUPPORT & FAQS ══ -->
                <div class="tab-pane fade" id="pill-support" role="tabpanel">
                    <h2 class="section-title"><i class="fa-solid fa-headset"></i> Support & Help Desk</h2>
                    
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h4 class="text-gold mb-3" style="font-size: 1.1rem; text-transform: uppercase;">Contact Help Desk</h4>
                            <p class="text-dark-50 mb-4" style="font-size: 0.92rem;">Need instant help? Feel free to call us or drop a message directly on WhatsApp.</p>
                            
                            <div class="d-flex gap-3 mb-5">
                                <a href="https://wa.me/919427272798" target="_blank" class="btn-gold-medusa" style="background-color: #25d366; color: #fff; width: 50%;">
                                    <i class="fa-brands fa-whatsapp" style="font-size: 1.2rem;"></i> WhatsApp
                                </a>
                                <a href="tel:+919427272798" class="btn-outline-medusa" style="width: 50%;">
                                    <i class="fa-solid fa-phone"></i> Call Support
                                </a>
                            </div>

                            <h4 class="text-gold mb-3" style="font-size: 1.1rem; text-transform: uppercase;">Submit Support Ticket</h4>
                            <form id="supportForm" onsubmit="submitSupportForm(event)">
                                <div class="mb-3">
                                    <label class="form-label-medusa" for="support_subject">Subject *</label>
                                    <input type="text" id="support_subject" class="form-control form-control-medusa" placeholder="Enter ticket subject..." required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label-medusa" for="support_message">Details / Message *</label>
                                    <textarea id="support_message" rows="4" class="form-control form-control-medusa" placeholder="Provide details about your query or concern..." required></textarea>
                                </div>
                                <button type="submit" class="btn-gold-medusa"><i class="fa-solid fa-circle-plus"></i> Submit Ticket</button>
                            </form>

                            <!-- Ticket History -->
                            <div class="mt-4 pt-3 border-top border-secondary">
                                <h5 class="text-dark-50 mb-3" style="font-size: 0.9rem; text-transform: uppercase;">Active Tickets</h5>
                                <?php if (empty($support_tickets)): ?>
                                    <p class="text-muted" style="font-size: 0.85rem;">No registered support tickets.</p>
                                <?php else: ?>
                                    <div class="table-responsive bg-black p-2 rounded border border-secondary">
                                        <table class="table table-dark table-striped table-hover m-0" style="font-size: 0.8rem;">
                                            <thead>
                                                <tr>
                                                    <th>Subject</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($support_tickets as $ticket): ?>
                                                    <tr>
                                                        <td class="text-dark"><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $ticket['status'] === 'open' ? 'bg-warning text-white' : 'bg-secondary'; ?> text-uppercase" style="font-size: 0.65rem;">
                                                                <?php echo htmlspecialchars($ticket['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-dark-50"><?php echo date('d M Y', strtotime($ticket['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <h4 class="text-gold mb-3" style="font-size: 1.1rem; text-transform: uppercase;">Frequently Asked Questions</h4>
                            
                            <div class="accordion" id="faqAccordion">
                                <div class="accordion-item accordion-item-medusa">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button accordion-button-medusa collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                            How do I cancel or modify a table reservation?
                                        </button>
                                    </h2>
                                    <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body accordion-body-medusa">
                                            You can cancel or modify bookings directly by contacting our concierge desk via phone or WhatsApp. Changes must be requested at least 2 hours prior to reservation time.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item accordion-item-medusa">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button accordion-button-medusa collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                            How does the loyalty rewards program work?
                                        </button>
                                    </h2>
                                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body accordion-body-medusa">
                                            For every ₹100 spent on completed orders with Medusa, you earn 1 point automatically. Accumulating points unlocks Bronze, Silver Premium, and Gold Elite tiers, qualifying you for exclusive promotions.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item accordion-item-medusa">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button accordion-button-medusa collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                            Can I get dynamic discount coupons?
                                        </button>
                                    </h2>
                                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body accordion-body-medusa">
                                            Yes! After placing and receiving an order, navigate to the Feedback page to rate and review your experience. Submitting feedback often generates instant coupons in your account.
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item accordion-item-medusa">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button accordion-button-medusa collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                            What are the delivery ranges and timings?
                                        </button>
                                    </h2>
                                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body accordion-body-medusa">
                                            We deliver culinary creations from 11:00 AM to 11:30 PM daily within a 15km radius of Chandigarh. Order values above ₹2000 qualify for free luxury delivery.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <!-- ══ MODALS ══ -->

    <!-- OTP Verification Modal -->
    <div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-secondary text-white border-gold" style="border: 1px solid var(--gold);">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-gold" id="otpModalLabel">OTP Verification</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 text-center">
                    <p class="text-dark-50 mb-4" id="otpModalDesc">Please enter the 6-digit One-Time Password sent to your new destination.</p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <input type="text" maxlength="6" id="otp-input-field" class="form-control form-control-medusa text-center font-weight-bold" style="font-size: 1.8rem; letter-spacing: 5px; width: 200px;" placeholder="000000">
                    </div>
                    
                    <div class="text-dark-50" style="font-size: 0.85rem;">
                        Didn't receive code? 
                        <button type="button" class="btn btn-link text-gold p-0" id="otp-resend-btn" onclick="resendOTP()" disabled>Resend in <span id="otp-timer">30</span>s</button>
                    </div>
                </div>
                <div class="modal-footer border-secondary justify-content-center">
                    <button type="button" class="btn btn-outline-medusa" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-gold-medusa" onclick="verifyOTPCode()">Verify Code</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-card-custom text-dark border-danger" style="border: 1px solid #ff4d4d; border-radius: 12px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation"></i> Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-dark fw-medium mb-3" style="font-size: 1.1rem;">Do you want to delete your account permanently?</p>
                    <p class="text-muted mb-4" style="font-size: 0.9rem;">This action will delete your profile, reservations, reviews, and reward points permanently.</p>
                    
                    <div class="mb-3">
                        <label class="form-label text-dark fw-bold" style="font-size: 0.9rem;">How would you like to receive your confirmation OTP?</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="delete_otp_method" id="delete_otp_email" value="email">
                            <label class="form-check-label text-muted" for="delete_otp_email">
                                Get OTP by Email (<?php echo htmlspecialchars($user_email ?? 'Not set'); ?>)
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="delete_otp_method" id="delete_otp_phone" value="phone">
                            <label class="form-check-label text-muted" for="delete_otp_phone">
                                Get OTP by Phone number (<?php echo htmlspecialchars($phone ?? 'Not set'); ?>)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">No, Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDeleteAccount()">Yes, Send OTP</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account OTP Modal -->
    <div class="modal fade" id="deleteOtpModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-card-custom text-dark border-danger" style="border: 1px solid #ff4d4d; border-radius: 12px;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title text-danger fw-bold"><i class="fa-solid fa-key"></i> Verify Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 text-center">
                    <p class="text-muted mb-4" id="deleteOtpModalDesc">Please enter the 6-digit OTP sent to your selected method to confirm account deletion.</p>
                    
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <input type="text" maxlength="6" id="delete-otp-input-field" class="form-control text-center font-weight-bold" style="font-size: 1.8rem; letter-spacing: 5px; width: 200px; background: rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.1);" placeholder="000000">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 justify-content-center">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="verifyDeleteAccountOTP()">Verify & Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Toast Notification -->
    <div class="medusa-toast" id="medusaToast">
        <i class="fa-solid fa-bell text-gold" id="toast-icon"></i>
        <span id="toast-message">Message text</span>
    </div>

    <!-- Bootstrap JS (bundle contains Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Client-Side Dashboard Operations -->
    <script>
        const ACCOUNT_SECTION = <?php echo json_encode($account_section); ?>;
        const ACCOUNT_URLS = {
            profile: 'profile.php',
            settings: 'settings.php'
        };

        function goToProfileTab(tabName) {
            const tab = document.getElementById(`pill-${tabName}-tab`);
            if (tab) {
                tab.click();
            } else {
                window.location.href = `profile.php?tab=${tabName}`;
            }
        }

        function goToSettingsTab(tabName) {
            const tab = document.getElementById(`pill-${tabName}-tab`);
            if (tab) {
                tab.click();
            } else {
                window.location.href = `settings.php?tab=${tabName}`;
            }
        }

        // Alert Toast Helper
        function showToast(message, type = 'info') {
            const toast = document.getElementById('medusaToast');
            const msgSpan = document.getElementById('toast-message');
            const icon = document.getElementById('toast-icon');
            
            msgSpan.textContent = message;
            
            if (type === 'success') {
                icon.className = "fa-solid fa-circle-check text-success";
            } else if (type === 'error') {
                icon.className = "fa-solid fa-circle-xmark text-danger";
            } else {
                icon.className = "fa-solid fa-bell text-gold";
            }
            
            toast.style.display = 'flex';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 4000);
        }

        // Live Password Strength Indicator
        function checkPassStrength(pw) {
            const segs = [
                document.getElementById('seg1'),
                document.getElementById('seg2'),
                document.getElementById('seg3'),
                document.getElementById('seg4')
            ];

            if (segs.some(seg => !seg)) return;
            
            segs.forEach(s => s.className = 'seg');
            if (!pw) return;

            let score = 0;
            if (pw.length >= 6) score++;
            if (pw.length >= 10) score++;
            if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
            if (/[0-9]/.test(pw) && /[^A-Za-z0-9]/.test(pw)) score++;

            const levels = ['weak', 'fair', 'good', 'strong'];
            const activeLevel = levels[Math.min(score - 1, 3)];

            for(let i = 0; i < score; i++) {
                segs[i].classList.add(activeLevel);
            }
        }

        // Detect profile updates and show verify button if email/phone changed
        const originalEmail = "<?php echo htmlspecialchars($user_email); ?>";
        const originalPhone = "<?php echo htmlspecialchars($phone); ?>";

        const profileEmailInput = document.getElementById('profile_email');
        const profilePhoneInput = document.getElementById('profile_phone');

        if (profileEmailInput) {
            profileEmailInput.addEventListener('input', function() {
            const btn = document.getElementById('btn-verify-email');
            const hint = document.getElementById('email-change-hint');
            if (this.value.trim() !== originalEmail) {
                btn.style.display = 'block';
                hint.style.display = 'block';
            } else {
                btn.style.display = 'none';
                hint.style.display = 'none';
            }
            });
        }

        if (profilePhoneInput) {
            profilePhoneInput.addEventListener('input', function() {
            const btn = document.getElementById('btn-verify-phone');
            const hint = document.getElementById('phone-change-hint');
            if (this.value.trim() !== originalPhone) {
                btn.style.display = 'block';
                hint.style.display = 'block';
            } else {
                btn.style.display = 'none';
                hint.style.display = 'none';
            }
            });
        }

        // Feedback stars behavior
        const stars = document.querySelectorAll('#feedback-stars i');
        const ratingValInput = document.getElementById('feedback-rating-val');
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                ratingValInput.value = idx;
                stars.forEach((s, sIdx) => {
                    if (sIdx < idx) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            star.addEventListener('mouseover', function() {
                const idx = parseInt(this.getAttribute('data-index'));
                stars.forEach((s, sIdx) => {
                    if (sIdx < idx) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
            star.addEventListener('mouseout', function() {
                const activeVal = parseInt(ratingValInput.value);
                stars.forEach((s, sIdx) => {
                    if (sIdx < activeVal) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });

        // OTP Timer & Operations
        let otpType = 'email'; // email or phone
        let otpCountdown = 30;
        let timerInterval;

        function startOTPTimer() {
            otpCountdown = 30;
            const timerSpan = document.getElementById('otp-timer');
            const resendBtn = document.getElementById('otp-resend-btn');
            resendBtn.disabled = true;
            timerSpan.textContent = otpCountdown;
            
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                otpCountdown--;
                timerSpan.textContent = otpCountdown;
                if (otpCountdown <= 0) {
                    clearInterval(timerInterval);
                    resendBtn.innerHTML = "Resend OTP";
                    resendBtn.disabled = false;
                } else {
                    resendBtn.innerHTML = `Resend in <span id="otp-timer">${otpCountdown}</span>s`;
                }
            }, 1000);
        }

        async function sendOTP(type) {
            otpType = type;
            let val = '';
            let action = '';
            
            if (type === 'email') {
                val = document.getElementById('profile_email').value.trim();
                action = 'send_email_otp';
                if (!val) {
                    showToast('Please enter a valid email address first.', 'error');
                    return;
                }
            } else {
                val = document.getElementById('profile_phone').value.trim();
                action = 'send_phone_otp';
                if (!val || val.length !== 10) {
                    showToast('Please enter a valid 10-digit mobile number first.', 'error');
                    return;
                }
            }

            try {
                const response = await fetch(`api/account-api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ [type]: val })
                });
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    // Show OTP modal
                    document.getElementById('otpModalDesc').textContent = `Please enter the 6-digit One-Time Password sent to your new ${type}: ${val}`;
                    document.getElementById('otp-input-field').value = '';
                    
                    const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
                    otpModal.show();
                    startOTPTimer();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Failed to trigger verification code.', 'error');
            }
        }

        async function resendOTP() {
            let val = '';
            let action = '';
            if (otpType === 'email') {
                val = document.getElementById('profile_email').value.trim();
                action = 'send_email_otp';
            } else {
                val = document.getElementById('profile_phone').value.trim();
                action = 'send_phone_otp';
            }

            try {
                const response = await fetch(`api/account-api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ [otpType]: val })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    startOTPTimer();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Failed to resend code.', 'error');
            }
        }

        async function verifyOTPCode() {
            const code = document.getElementById('otp-input-field').value.trim();
            if (code.length !== 6 || isNaN(code)) {
                showToast('Please enter a valid 6-digit number code.', 'error');
                return;
            }

            const action = otpType === 'email' ? 'verify_email_otp' : 'verify_phone_otp';
            try {
                const response = await fetch(`api/account-api.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp: code })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    // Dismiss modal and reload
                    const myModalEl = document.getElementById('otpModal');
                    const modal = bootstrap.Modal.getInstance(myModalEl);
                    modal.hide();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Verification request failed.', 'error');
            }
        }

        // Profile pic AJAX upload
        async function handleProfilePicUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('profile_pic', file);

            try {
                showToast('Uploading profile picture...', 'info');
                const response = await fetch('api/account-api.php?action=upload_profile_pic', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    // update elements
                    const img = document.getElementById('avatar-img');
                    if (img) {
                        img.src = result.path;
                    } else {
                        const placeholder = document.getElementById('avatar-placeholder');
                        const parent = placeholder.parentNode;
                        parent.innerHTML = `<img id="avatar-img" src="${result.path}" alt="Profile Picture"><div class="avatar-overlay"><i class="fa-solid fa-camera"></i></div>`;
                    }
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Error uploading avatar picture', 'error');
            }
        }

        // Submit Profile Details form
        async function submitProfileForm(e) {
            e.preventDefault();
            const name = document.getElementById('profile_name').value.trim();
            const dob = document.getElementById('profile_dob').value;
            const ambience = document.getElementById('profile_ambience').value;

            try {
                const response = await fetch('api/account-api.php?action=update_profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name, dob: dob, preferred_ambience: ambience })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    // update profile displays
                    document.querySelectorAll('.profile-name').forEach(el => el.textContent = name);
                    
                    // Update static view mode
                    const viewNameEl = document.getElementById('view-profile-name');
                    if (viewNameEl) viewNameEl.textContent = name;
                    
                    const viewDobEl = document.getElementById('view-profile-dob');
                    if (viewDobEl) {
                        if (dob) {
                            const d = new Date(dob);
                            viewDobEl.textContent = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                        } else {
                            viewDobEl.textContent = 'Not Set';
                        }
                    }

                    const viewAmbienceEl = document.getElementById('view-profile-ambience');
                    const viewAmbienceContainer = document.getElementById('view-profile-ambience-container');
                    const viewDobContainer = document.getElementById('view-profile-dob-container');
                    if (viewAmbienceEl && viewAmbienceContainer) {
                        viewAmbienceEl.textContent = ambience;
                        if (ambience) {
                            viewAmbienceContainer.style.display = 'block';
                            if (viewDobContainer) viewDobContainer.classList.add('border-end');
                        } else {
                            viewAmbienceContainer.style.display = 'none';
                            if (viewDobContainer) viewDobContainer.classList.remove('border-end');
                        }
                    }
                    
                    // Toggle views back to normal
                    document.getElementById('profile-edit').style.display = 'none';
                    document.getElementById('profile-view').style.display = 'block';
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Profile save error.', 'error');
            }
        }

        // Submit preferences settings
        async function submitSettingsForm(e) {
            e.preventDefault();
            const emailNotif = document.getElementById('notif_email').checked ? 1 : 0;
            const smsNotif = document.getElementById('notif_sms').checked ? 1 : 0;
            const promo = document.getElementById('notif_promo').checked ? 1 : 0;
            const lang = document.getElementById('pref_lang').value;
            const theme = document.getElementById('pref_theme').value;
            const privacy = document.getElementById('two_factor_toggle').checked ? 1 : 0; // mapping 2FA to privacy_mode

            try {
                const response = await fetch('api/account-api.php?action=update_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email_notifications: emailNotif,
                        sms_notifications: smsNotif,
                        promotional_offers: promo,
                        language: lang,
                        theme: theme,
                        privacy_mode: privacy
                    })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    
                    // Update localStorage for the theme so the script picks it up on reload
                    localStorage.setItem('medusa_admin_theme', theme);
                    
                    // Reload to the exact same tab
                    setTimeout(() => {
                        window.location.href = window.location.pathname + '?tab=settings&sub=preferences';
                    }, 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to save settings.', 'error');
            }
        }

        // Toggle 2FA switch from Security Tab
        function toggle2FA(switcher) {
            const enabled = switcher.checked ? 1 : 0;
            fetch('api/account-api.php?action=toggle_2fa', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: enabled })
            }).then(res => res.json()).then(result => {
                if (result.success) {
                    showToast(result.message, 'success');
                    // Update the card badge live without page refresh
                    const badge = document.getElementById('tfa-status-badge');
                    if (badge) {
                        if (enabled) {
                            badge.className = 'badge bg-success bg-opacity-10 text-success px-2 py-1';
                            badge.innerHTML = 'Enabled <i class="fa-solid fa-check ms-1"></i>';
                        } else {
                            badge.className = 'badge bg-secondary bg-opacity-10 text-secondary px-2 py-1';
                            badge.innerHTML = 'Disabled <i class="fa-solid fa-xmark ms-1"></i>';
                        }
                    }
                } else {
                    showToast(result.message, 'error');
                    switcher.checked = !switcher.checked; // revert
                }
            }).catch(() => {
                showToast('Failed to update 2FA state.', 'error');
                switcher.checked = !switcher.checked;
            });
        }

        // Toggle Login Alerts switch
        function toggleLoginAlerts(switcher) {
            const enabled = switcher.checked ? 1 : 0;
            fetch('api/account-api.php?action=toggle_login_alerts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: enabled })
            }).then(res => res.json()).then(result => {
                if (result.success) {
                    showToast(result.message, 'success');
                } else {
                    showToast(result.message, 'error');
                    switcher.checked = !switcher.checked; // revert
                }
            }).catch(() => {
                showToast('Failed to update login alerts state.', 'error');
                switcher.checked = !switcher.checked;
            });
        }

        // Save Recovery Email
        async function saveRecoveryEmail() {
            const email = document.getElementById('rec_fallback_field').value.trim();
            if (!email) {
                showToast('Please enter an email address', 'error');
                return;
            }
            try {
                const response = await fetch('api/account-api.php?action=save_recovery_email', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ recovery_email: email })
                });
                const result = await response.json();
                showToast(result.message, result.success ? 'success' : 'error');
            } catch (err) {
                showToast('Network error while saving recovery email.', 'error');
            }
        }

        // Submit password change
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        async function submitPasswordForm(e) {
            e.preventDefault();
            const currentPw = document.getElementById('cur_pass').value;
            const newPw = document.getElementById('new_pass').value;
            const confPw = document.getElementById('conf_pass').value;

            if (newPw.length < 6) {
                showToast('New password must be at least 6 characters long.', 'error');
                return;
            }
            if (newPw !== confPw) {
                showToast('Passwords do not match.', 'error');
                return;
            }

            try {
                const response = await fetch('api/account-api.php?action=change_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        current_password: currentPw,
                        new_password: newPw,
                        confirm_password: confPw
                    })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    document.getElementById('passwordForm').reset();
                    checkPassStrength(''); // reset meter
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to update password.', 'error');
            }
        }

        // Submit customer support ticket
        async function submitSupportForm(e) {
            e.preventDefault();
            const subject = document.getElementById('support_subject').value.trim();
            const message = document.getElementById('support_message').value.trim();

            try {
                const response = await fetch('api/account-api.php?action=submit_support', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subject, message })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    document.getElementById('supportForm').reset();
                    // refresh after delay or alert
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to submit support request.', 'error');
            }
        }

        // Submit general feedback
        async function submitFeedbackForm(e) {
            e.preventDefault();
            const rating = parseInt(document.getElementById('feedback-rating-val').value);
            const review = document.getElementById('feedback_review').value.trim();
            const type = document.getElementById('feedback_type').value;

            if (rating < 1) {
                showToast('Please select a star rating first.', 'error');
                return;
            }

            try {
                const response = await fetch('api/account-api.php?action=submit_feedback', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ rating, review, type })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    document.getElementById('feedbackForm').reset();
                    stars.forEach(s => s.classList.remove('active'));
                    document.getElementById('feedback-rating-val').value = '0';
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to submit review feedback.', 'error');
            }
        }


        // Buy Liquor Quota Again handler
        async function buyLiquorQuotaAgain(foodItemId) {
            try {
                showToast('Adding item to cart...', 'info');
                const response = await fetch('api/add-to-cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ food_item_id: foodItemId, quantity: 1 })
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Item added to cart!', 'success');
                    // Redirect to cart page after short delay
                    setTimeout(() => { window.location.href = 'carttest.html'; }, 1000);
                } else {
                    showToast(result.message || 'Failed to add to cart.', 'error');
                }
            } catch(e) {
                showToast('Error adding to cart.', 'error');
            }
        }

        // Reorder Items handler
        async function reorderItems(orderId) {
            try {
                showToast('Adding order items to cart...', 'info');
                const response = await fetch('api/account-api.php?action=reorder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: orderId })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    // Redirect to cart page after short delay so they can checkout
                    setTimeout(() => { window.location.href = 'menutest.html'; }, 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to reorder items.', 'error');
            }
        }

        // Logout all other devices
        async function logoutOtherDevices() {
            if (!confirm('Are you sure you want to invalidate all other active sessions? You will remain logged in on this device.')) return;
            try {
                const response = await fetch('api/account-api.php?action=logout_all_devices', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    // Reload page after successful invalidation so trusted list reflects it
                    setTimeout(() => { window.location.reload(); }, 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Device logout request failed.', 'error');
            }
        }

        // Revoke a specific device session
        async function revokeDevice(button, logId) {
            if (!confirm('Are you sure you want to revoke this session? The device will be logged out immediately.')) return;
            try {
                const response = await fetch('api/account-api.php?action=revoke_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ log_id: logId })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    const listItem = document.getElementById(`device-item-${logId}`);
                    if (listItem) {
                        listItem.style.transition = 'all 0.5s ease';
                        listItem.style.opacity = '0';
                        listItem.style.transform = 'translateX(20px)';
                        setTimeout(() => {
                            listItem.remove();
                            // If no devices left besides active one, or empty, show empty state
                            const list = document.getElementById('trusted-devices-list');
                            if (list && list.children.length === 0) {
                                list.innerHTML = `<li class="list-group-item text-center text-muted py-3 px-0 border-0">No trusted devices found.</li>`;
                            }
                        }, 500);
                    }
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to revoke session.', 'error');
            }
        }

        // Permanent Delete modal triggers
        function showDeleteAccountModal() {
            // Uncheck radios
            const emailRadio = document.getElementById('delete_otp_email');
            const phoneRadio = document.getElementById('delete_otp_phone');
            if(emailRadio) emailRadio.checked = false;
            if(phoneRadio) phoneRadio.checked = false;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteAccountModal'));
            modal.show();
        }

        async function confirmDeleteAccount() {
            const emailRadio = document.getElementById('delete_otp_email');
            const phoneRadio = document.getElementById('delete_otp_phone');
            
            let method = null;
            if (emailRadio && emailRadio.checked) method = 'email';
            if (phoneRadio && phoneRadio.checked) method = 'phone';
            
            if (!method) {
                showToast('Please select a method to receive your OTP (Email or Phone number).', 'error');
                return;
            }

            try {
                const response = await fetch('api/account-api.php?action=send_delete_otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ method: method })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    // Hide confirm modal
                    const confirmModalEl = document.getElementById('deleteAccountModal');
                    const confirmModal = bootstrap.Modal.getInstance(confirmModalEl);
                    if(confirmModal) confirmModal.hide();
                    
                    // Show OTP modal
                    document.getElementById('delete-otp-input-field').value = '';
                    const otpModal = new bootstrap.Modal(document.getElementById('deleteOtpModal'));
                    otpModal.show();
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to send OTP.', 'error');
            }
        }

        async function verifyDeleteAccountOTP() {
            const otp = document.getElementById('delete-otp-input-field').value.trim();
            if (!otp || otp.length < 6) {
                showToast('Please enter a valid 6-digit OTP.', 'error');
                return;
            }

            try {
                const response = await fetch('api/account-api.php?action=delete_account', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ otp: otp })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    const otpModalEl = document.getElementById('deleteOtpModal');
                    const otpModal = bootstrap.Modal.getInstance(otpModalEl);
                    if(otpModal) otpModal.hide();
                    setTimeout(() => { window.location.href = 'index.html'; }, 2000);
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to verify OTP.', 'error');
            }
        }

        // Copy coupon codes
        function copyCouponCode(btn, code) {
            navigator.clipboard.writeText(code).then(() => {
                const orig = btn.textContent;
                btn.textContent = 'Copied!';
                showToast(`Coupon code ${code} copied to clipboard!`, 'success');
                setTimeout(() => btn.textContent = orig, 2000);
            }).catch(() => {
                showToast(`Coupon code is: ${code}`);
            });
        }

        // Search & Filter Orders
        function filterOrders() {
            const query = document.getElementById('order-search').value.toLowerCase().trim();
            const status = document.getElementById('order-status-filter').value.toLowerCase();
            const cards = document.querySelectorAll('#orders-list-container .order-card');
            
            cards.forEach(card => {
                const num = card.getAttribute('data-number').toLowerCase();
                const cardStatus = card.getAttribute('data-status');
                
                const matchesSearch = num.includes(query);
                const matchesStatus = (status === 'all' || cardStatus === status);
                
                if (matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function resetOrderFilters() {
            document.getElementById('order-search').value = '';
            document.getElementById('order-status-filter').value = 'all';
            filterOrders();
        }

        function switchDashboardMode(mode) {
            if (mode !== ACCOUNT_SECTION) {
                window.location.href = ACCOUNT_URLS[mode] || 'profile.php';
                return;
            }

            document.querySelectorAll('.btn-dashboard-toggle').forEach(btn => btn.classList.remove('active'));
            const activeToggle = document.getElementById(`btn-toggle-${mode}`);
            if (activeToggle) activeToggle.classList.add('active');

            document.querySelectorAll('.dashboard-pill-profile').forEach(p => {
                p.style.display = mode === 'profile' ? 'block' : 'none';
            });
            document.querySelectorAll('.dashboard-pill-settings').forEach(p => {
                p.style.display = mode === 'settings' ? 'block' : 'none';
            });
        }

        async function consumePeg() {
            const btn = document.getElementById('btn-consume-peg');
            if (!btn) return;
            const origText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            try {
                const response = await fetch('api/consume-quota.php', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    document.getElementById('quota-display-pegs').innerHTML = `${result.new_quota} <span style="font-size: 1.5rem; color: #fff;">pegs</span>`;
                } else {
                    showToast(result.message, 'error');
                }
            } catch(e) {
                showToast('Failed to consume peg quota.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        }

        // ── Custom Tab Switcher (bypasses Bootstrap tab engine for reliability) ──
        function switchTab(btnSelector) {
            if (!btnSelector) return false;
            const btn = document.querySelector(btnSelector);
            if (!btn) return false;

            const targetPaneId = btn.getAttribute('data-bs-target') || btn.getAttribute('data-target');
            if (!targetPaneId) return false;

            const targetPane = document.querySelector(targetPaneId);
            if (!targetPane) return false;

            // Deactivate all sidebar buttons
            document.querySelectorAll('.sidebar-menu .nav-link').forEach(b => b.classList.remove('active'));

            // Deactivate all main tab panes
            document.querySelectorAll('.main-content.tab-content > .tab-pane').forEach(p => {
                p.classList.remove('show', 'active');
            });

            // Activate the clicked button
            btn.classList.add('active');

            // Activate the target pane
            targetPane.classList.add('show', 'active');

            // Save to localStorage
            const btnId = btn.getAttribute('id');
            if (btnId) {
                localStorage.setItem(`medusaActiveTab:${ACCOUNT_SECTION}`, '#' + btnId);
                localStorage.setItem('medusaActiveTab', '#' + btnId);
            }

            // Trigger loadMembershipCard if needed
            if (targetPaneId === '#pill-membership' && typeof loadMembershipCard === 'function') {
                loadMembershipCard();
            }

            return true;
        }

        function activatePill(selector) {
            return switchTab(selector);
        }

        function selectorFromHash(hash) {
            if (!hash || !hash.startsWith('#pill-')) return null;
            return `${hash}-tab`;
        }

        function selectorFromTabParam(tabParam) {
            if (!tabParam || tabParam === ACCOUNT_SECTION) return null;
            if (tabParam === 'settings' || tabParam === 'profile') return null;
            return `#pill-${tabParam}-tab`;
        }


        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            switchDashboardMode(ACCOUNT_SECTION);

            let requestedPill = selectorFromHash(window.location.hash) || selectorFromTabParam(params.get('tab'));
            if (params.get('edit') === '1') {
                requestedPill = '#pill-profile-tab';
            }
            const storedPill = localStorage.getItem(`medusaActiveTab:${ACCOUNT_SECTION}`) || localStorage.getItem('medusaActiveTab');
            const defaultPill = ACCOUNT_SECTION === 'settings' ? '#pill-settings-tab' : '#pill-profile-tab';

            // Requested pill (from URL hash/param) always wins over localStorage
            if (requestedPill) {
                if (!switchTab(requestedPill)) switchTab(defaultPill);
                // Clear hash from URL so refreshing doesn't re-activate it
                if (window.location.hash) history.replaceState(null, '', window.location.pathname + window.location.search);
            } else if (!switchTab(storedPill)) {
                switchTab(defaultPill);
            }

            const subTab = params.get('sub');
            if (subTab) {
                // Sub-tabs (settings inner tabs) still use Bootstrap
                const subEl = document.querySelector(`#subnav-${subTab}-tab`);
                if (subEl) bootstrap.Tab.getOrCreateInstance(subEl).show();
            }
            
            // Remove the flicker prevention style now that the correct tab is active
            const flickerStyle = document.getElementById('prevent-flicker');
            if (flickerStyle) {
                flickerStyle.remove();
                document.querySelector('.main-content').style.visibility = 'visible';
                document.querySelector('.main-content').style.opacity = '1';
            }

            if (params.get('edit') === '1') {
                const editButton = document.getElementById('btn-edit-profile');
                if (editButton) editButton.click();
            }

            // Attach click listeners to all sidebar tab buttons
            document.querySelectorAll('.sidebar-menu .nav-link').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    switchTab('#' + btn.getAttribute('id'));
                });
            });
        });
    </script>

<style>
/* ── Track Order Button ── */
.btn-track-live-acc {
    background: transparent;
    border: 1px solid rgba(201,168,76,0.4);
    color: #c9a84c;
    font-size: 0.78rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 6px;
    padding: 0.3rem 0.8rem;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-track-live-acc:hover {
    background: rgba(201,168,76,0.1);
    border-color: #c9a84c;
    color: #d4b05a;
    transform: translateY(-1px);
}
.live-dot-acc {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #c9a84c;
    animation: liveAcc 1.6s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes liveAcc { 0%,100%{opacity:1} 50%{opacity:0.3} }
</style>

<!-- Tier Benefits Modal -->
<div class="modal fade" id="tierBenefitsModal" tabindex="-1" aria-labelledby="tierBenefitsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg">
      <div class="modal-header border-0 pb-0 px-4 pt-4">
        <h5 class="modal-title" id="tierBenefitsModalLabel" style="font-family: 'Playfair Display', serif; color: #3f1519; font-size: 1.5rem; font-weight: 600;">Loyalty Tier Benefits</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body px-4 py-4">
        <p class="text-muted mb-4">Learn about the perks you receive as you level up your loyalty tier by ordering with us.</p>
        <div class="row g-4">
          <?php foreach ($all_tiers_data as $tier): ?>
          <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm" style="background-color: #fcfbf8; border-radius: 12px;">
              <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                  <div class="rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #e6e6e6, #f5f5f5); border: 2px solid #d4af37;">
                    <i class="fa-solid fa-crown" style="font-size: 1.5rem; color: #b48530;"></i>
                  </div>
                  <div>
                    <h5 class="mb-0" style="font-family: 'Playfair Display', serif; font-weight: 600; color: #5a2a35;"><?php echo htmlspecialchars($tier['tier_name']); ?></h5>
                    <small class="text-muted"><?php echo $tier['spending_requirement'] > 0 ? number_format($tier['spending_requirement']) . '+ pts required' : 'Base Tier'; ?></small>
                  </div>
                </div>
                <ul class="list-unstyled mb-0 text-muted" style="font-size: 0.9rem; line-height: 1.6;">
                  <li><i class="fa-solid fa-check text-success me-2"></i><strong><?php echo floatval($tier['discount_percent']); ?>% Discount</strong> on all orders</li>
                  <li><i class="fa-solid fa-check text-success me-2"></i>Earn <strong><?php echo floatval($tier['points_earning_percent']); ?>%</strong> of order value back in points</li>
                </ul>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-footer border-0 px-4 pb-4 pt-0">
        <button type="button" class="btn text-white w-100 py-2" style="background-color: #143628; border-radius: 8px; font-weight: 500;" data-bs-dismiss="modal">Got it</button>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/active_order_bar.php'; ?>
<?php require_once __DIR__ . '/includes/order_toast.php'; ?>
<script>
async function handleOrderNowClick(e) {
    e.preventDefault();
    try {
        const res = await fetch('api/get-cart.php', { headers: { 'Accept': 'application/json' }});
        const result = await res.json();
        if (result.success && result.items && result.items.length > 0) {
            window.location.href = 'carttest.html';
        } else {
            window.location.href = 'menutest.html';
        }
    } catch(err) {
        window.location.href = 'menutest.html';
    }
}
</script>

    <!-- Session Revoke Confirmation Modal -->
    <div id="revokeSessionModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:2rem; width:100%; max-width:400px; box-shadow:0 20px 60px rgba(0,0,0,0.25); position:relative; text-align:center; margin: 1rem;">
            <button onclick="closeRevokeModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.4rem;cursor:pointer;color:#aaa;">&times;</button>
            <div style="width:56px;height:56px;background:linear-gradient(135deg,#dc3545,#a71d2a);border-radius:50%;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#fff;font-size:1.3rem;"></i>
            </div>
            <h5 style="font-weight:700;color:#1a1a1a;margin-bottom:0.5rem;">Revoke this session?</h5>
            <p style="color:#6c757d;font-size:0.88rem;margin-bottom:1.5rem;line-height:1.6;">This will permanently log out any device using this session. <strong>All other logged-in devices will also be disconnected</strong> as a security measure. You will remain logged in.</p>
            <div style="display:flex;gap:0.75rem;">
                <button onclick="closeRevokeModal()" style="flex:1;padding:0.65rem;border:1.5px solid #dee2e6;background:#fff;border-radius:8px;font-weight:600;color:#6c757d;cursor:pointer;">Cancel</button>
                <button id="revokeConfirmBtn" onclick="confirmRevokeSession()" style="flex:1;padding:0.65rem;background:linear-gradient(135deg,#dc3545,#a71d2a);border:none;border-radius:8px;font-weight:600;color:#fff;cursor:pointer;">Yes, Revoke</button>
            </div>
            <p id="revokeModalMsg" style="margin-top:0.75rem;font-size:0.83rem;min-height:1.2rem;"></p>
        </div>
    </div>
    <style>#revokeSessionModal.active { display: flex !important; }</style>

    <script>
    let _revokeLogId = null;
    let _revokeRowBtn = null;

    function openRevokeModal(logId, btn) {
        _revokeLogId = logId;
        _revokeRowBtn = btn;
        document.getElementById('revokeModalMsg').textContent = '';
        document.getElementById('revokeConfirmBtn').disabled = false;
        document.getElementById('revokeConfirmBtn').textContent = 'Yes, Revoke';
        document.getElementById('revokeSessionModal').classList.add('active');
    }

    function closeRevokeModal() {
        document.getElementById('revokeSessionModal').classList.remove('active');
        _revokeLogId = null;
        _revokeRowBtn = null;
    }

    async function confirmRevokeSession() {
        if (!_revokeLogId) return;
        const btn = document.getElementById('revokeConfirmBtn');
        const msg = document.getElementById('revokeModalMsg');
        btn.disabled = true;
        btn.textContent = 'Revoking…';
        msg.textContent = '';

        try {
            const res = await fetch('api/account-api.php?action=revoke_session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id: _revokeLogId })
            });
            const result = await res.json();
            if (result.success) {
                // Update the status cell live
                const statusCell = document.getElementById('session-status-' + _revokeLogId);
                if (statusCell) {
                    statusCell.innerHTML = '<span class="badge bg-danger bg-opacity-10 text-danger">Revoked</span>';
                }
                showToast('Session revoked successfully. Other devices have been logged out.', 'success');
                closeRevokeModal();
            } else {
                msg.style.color = '#dc3545';
                msg.textContent = result.message || 'Failed to revoke. Please try again.';
                btn.disabled = false;
                btn.textContent = 'Yes, Revoke';
            }
        } catch(e) {
            msg.style.color = '#dc3545';
            msg.textContent = 'Network error. Please try again.';
            btn.disabled = false;
            btn.textContent = 'Yes, Revoke';
        }
    }
    </script>
</body>
</html>
