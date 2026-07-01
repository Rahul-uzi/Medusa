<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/sms.php';

// Check if user is logged in to prepopulate defaults
$default_name = '';
$default_email = '';
$default_phone = '';
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_profile) {
            $default_name = $user_profile['full_name'];
            $default_email = $user_profile['email'];
            $default_phone = $user_profile['phone'];
        }
    } catch (Exception $e) {
        // Fail silently and leave blank for prepopulate
    }
}

// Check for selected table from query string (from visual floor plan)
$selected_table = trim($_GET['table'] ?? '');

$error = '';
$success_booking = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $booking_date = trim($_POST['booking_date'] ?? '');
    $booking_time = trim($_POST['booking_time'] ?? '');
    $guests = intval($_POST['guests'] ?? 1);
    $table_number = trim($_POST['table_number'] ?? '');
    $special_requests = trim($_POST['special_requests'] ?? '');

    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($booking_date) || empty($booking_time) || $guests <= 0) {
        $error = 'Please fill all required fields correctly.';
    } elseif (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo->beginTransaction();

            $venue_name = get_env_var('RESTAURANT_NAME', 'Medusa');
            $venue_address = 'SCO 44,45, District One Market, Sector 68, Sahibzada Ajit Singh Nagar, Punjab 140308';
            $venue_phone = '+91 94272 72798';

            // Insert into table_bookings
            $ins = $pdo->prepare("
                INSERT INTO table_bookings 
                (user_id, customer_name, customer_email, customer_phone, booking_date, booking_time, guests, table_number, special_requests, venue_name, venue_address, venue_phone, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
            ");
            $ins->execute([
                $user_id,
                $customer_name,
                $customer_email,
                $customer_phone,
                $booking_date,
                $booking_time,
                $guests,
                !empty($table_number) ? $table_number : null,
                !empty($special_requests) ? $special_requests : null,
                $venue_name,
                $venue_address,
                $venue_phone
            ]);

            $booking_id = $pdo->lastInsertId();

            // Trigger table booking reservation notification
            require_once __DIR__ . '/includes/notifications_helper.php';
            $res_title = "New Table Reservation";
            $table_lbl = !empty($table_number) ? " (Table/Zone: {$table_number})" : "";
            $res_body = "New reservation secured by {$customer_name} for {$guests} guest(s) on " . date('d M Y', strtotime($booking_date)) . " at " . date('g:i A', strtotime($booking_time)) . "{$table_lbl}.";
            addNotification('reservation', $res_title, $res_body);

            // If there's a special request, trigger a kitchen/reservation notification
            if (!empty($special_requests)) {
                $req_body = "Special request from guest {$customer_name} for reservation #BK-{$booking_id}: \"{$special_requests}\"";
                addNotification('kitchen', 'Special Request Added', $req_body);
            }

            $pdo->commit();

            // Prepare booking details array
            $booking_details = [
                'id' => $booking_id,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'booking_date' => $booking_date,
                'booking_time' => $booking_time,
                'guests' => $guests,
                'table_number' => $table_number,
                'special_requests' => $special_requests,
                'venue_name' => $venue_name,
                'venue_address' => $venue_address,
                'venue_phone' => $venue_phone,
                'status' => 'confirmed'
            ];

            // Trigger Emails & SMS notifications
            $user_notification = [
                'email' => $customer_email,
                'full_name' => $customer_name,
                'phone' => $customer_phone
            ];

            sendBookingEmail($user_notification, $booking_details);
            sendBookingSms($customer_phone, $booking_details);

            $success_booking = $booking_details;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Reservation failed to save: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve a Table — Medusa</title>
    <!-- Outfit & Playfair fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --gold: #d4af37;
            --dark-bg: #050505;
            --card-bg: #0c0b0a;
            --input-bg: rgba(255, 255, 255, 0.04);
            --border-gold: rgba(212, 175, 55, 0.2);
        }

        body {
            background-color: var(--dark-bg);
            color: #f3f3f3;
            font-family: 'Plus Jakarta Sans', Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Navbar */
        .navbar {
            background: rgba(12, 11, 10, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 16px 0;
        }

        .navbar-brand {
            color: var(--gold) !important;
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 700;
            font-size: 1.8rem;
            letter-spacing: 1px;
        }

        /* Main Container */
        .booking-section {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 60px 0;
            background: radial-gradient(circle at top, rgba(212, 175, 55, 0.05) 0%, transparent 60%);
        }

        .booking-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-gold);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.5);
            max-width: 600px;
            margin: 0 auto;
        }

        .gold-title {
            font-family: 'Playfair Display', Georgia, serif;
            color: var(--gold);
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }

        .card-subtitle {
            text-align: center;
            color: #aaa;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        /* Inputs styling */
        .form-label {
            color: #ccc;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .form-control, .form-select {
            background-color: var(--input-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: #fff !important;
            padding: 12px 16px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.06);
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
            outline: none;
        }

        /* Button styling */
        .btn-gold {
            background: linear-gradient(135deg, var(--gold) 0%, #b89225 100%);
            color: #000;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            padding: 14px;
            width: 100%;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 15px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.25);
            transition: all 0.3s ease;
        }

        .btn-gold:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(212, 175, 55, 0.4);
            color: #000;
        }

        .error-message {
            background-color: rgba(231, 76, 60, 0.08);
            border: 1px solid rgba(231, 76, 60, 0.25);
            color: #e74c3c;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Success screen */
        .success-card {
            text-align: center;
        }

        .success-icon {
            font-size: 3.5rem;
            color: #2ec4b6;
            margin-bottom: 20px;
            text-shadow: 0 0 20px rgba(46, 196, 182, 0.3);
        }

        .success-title {
            color: #2ec4b6;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .details-box {
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }

        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.9rem;
        }

        .details-row:last-child {
            border-bottom: none;
        }

        .details-lbl {
            color: #888;
        }

        .details-val {
            font-weight: 600;
            color: #fff;
        }

        .btn-outline-gold {
            border: 1px solid var(--gold);
            color: var(--gold);
            background: transparent;
            font-weight: bold;
            border-radius: 12px;
            padding: 12px 25px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
            margin-top: 10px;
        }

        .btn-outline-gold:hover {
            background: rgba(212, 175, 55, 0.1);
            color: var(--gold);
        }
    </style>

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
</head>
<body>

    <!-- Navbar -->
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>
    <script src="assets/js/navbar.js" defer></script>

    <!-- Main Content Section -->
    <section class="booking-section">
        <div class="container">
            <div class="booking-card">
                <?php if ($success_booking): ?>
                    <!-- Success screen -->
                    <div class="success-card">
                        <div class="success-icon"><i class="fas fa-circle-check"></i></div>
                        <h3 class="success-title">Table Booking Secured</h3>
                        <p class="text-white-50">Concierge confirmation notifications sent via Email & SMS.</p>
                        
                        <div class="details-box">
                            <div class="details-row">
                                <span class="details-lbl">Reference ID:</span>
                                <span class="details-val" style="color: var(--gold);">#BK-<?php echo htmlspecialchars($success_booking['id']); ?></span>
                            </div>
                            <div class="details-row">
                                <span class="details-lbl">Guest Name:</span>
                                <span class="details-val"><?php echo htmlspecialchars($success_booking['customer_name']); ?></span>
                            </div>
                            <div class="details-row">
                                <span class="details-lbl">Reservation:</span>
                                <span class="details-val"><?php echo htmlspecialchars($success_booking['booking_date']); ?> at <?php echo htmlspecialchars($success_booking['booking_time']); ?></span>
                            </div>
                            <div class="details-row">
                                <span class="details-lbl">Guests:</span>
                                <span class="details-val"><?php echo htmlspecialchars($success_booking['guests']); ?> Guests</span>
                            </div>
                            <?php if (!empty($success_booking['table_number'])): ?>
                            <div class="details-row">
                                <span class="details-lbl">Assigned Table:</span>
                                <span class="details-val"><?php echo htmlspecialchars($success_booking['table_number']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="details-row">
                                <span class="details-lbl">Venue:</span>
                                <span class="details-val"><?php echo htmlspecialchars($success_booking['venue_name']); ?></span>
                            </div>
                        </div>

                        <p style="font-size: 0.82rem; color: #888; font-style: italic;">Note: Please arrive 10 minutes before your booking time.</p>
                        
                        <div class="d-flex gap-3 justify-content-center mt-4">
                            <a href="index.html" class="btn-outline-gold">Back to Home</a>
                            <a href="book-table-test.html" class="btn-outline-gold" style="border-color:#ccc;color:#ccc;">Book Another Table</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Form screen -->
                    <h2 class="gold-title">Reserve a Table</h2>
                    <p class="card-subtitle"> Artisanal fine dining experience in SAS Nagar </p>

                    <?php if (!empty($error)): ?>
                        <div class="error-message">
                            <i class="fas fa-circle-exclamation"></i>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="customer_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" required value="<?php echo htmlspecialchars($default_name); ?>">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="customer_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="customer_email" name="customer_email" required value="<?php echo htmlspecialchars($default_email); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="customer_phone" class="form-label">Mobile Number</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" required value="<?php echo htmlspecialchars($default_phone); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="booking_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="booking_time" class="form-label">Preferred Time</label>
                                <input type="time" class="form-control" id="booking_time" name="booking_time" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="guests" class="form-label">Number of Guests</label>
                                <select class="form-select" id="guests" name="guests" required>
                                    <option value="1">1 Person</option>
                                    <option value="2" selected>2 People</option>
                                    <option value="3">3 People</option>
                                    <option value="4">4 People</option>
                                    <option value="5">5 People</option>
                                    <option value="6">6 People</option>
                                    <option value="7">7 People</option>
                                    <option value="8">8 People</option>
                                    <option value="10">10 People</option>
                                    <option value="12">12 People</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="table_number" class="form-label">Table / Zone preference</label>
                                <input type="text" class="form-control" id="table_number" name="table_number" placeholder="e.g. T01, VIP" value="<?php echo htmlspecialchars($selected_table); ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="special_requests" class="form-label">Special Requests (Allergies, Dietary Notes, etc.)</label>
                            <textarea class="form-control" id="special_requests" name="special_requests" rows="3" placeholder="Let us know if you require a high chair, have peanut allergies, or are celebrating an anniversary."></textarea>
                        </div>

                        <button type="submit" class="btn-gold">Secure Table Booking</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center py-4 mt-auto border-top border-secondary border-opacity-10" style="background:#090807; color: #666; font-size: 0.85rem;">
        <p class="mb-0">&copy; 2026 Medusa Restaurant. All Rights Reserved. SCO 44,45, District One Market, Sector 68, Sahibzada Ajit Singh Nagar, Punjab 140308.</p>
    </footer>

</body>
</html>
