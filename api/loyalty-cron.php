<?php
/**
 * ══════════════════════════════════════════════════════════════
 *  MEDUSA RESTAURANT — LOYALTY PROGRAM CRON LOGIC
 *  Handles automatic inactivity point deductions and annual reset.
 * ══════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/config.php';

// Helper function to send notification alert via dashboard + email
function sendLoyaltyNotification($user_id, $title, $message) {
    global $pdo;
    try {
        // 1. Dashboard notification
        $stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
        $stmt->execute([$user_id, $title, $message]);

        // 2. Email notification if settings allow
        $set_stmt = $pdo->prepare("SELECT email_notifications FROM user_settings WHERE user_id = ?");
        $set_stmt->execute([$user_id]);
        $pref = $set_stmt->fetchColumn();
        
        // If preferences row not found, default to enabled (1)
        if ($pref === false || (int)$pref === 1) {
            $u_stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $u_stmt->execute([$user_id]);
            $u = $u_stmt->fetch(PDO::FETCH_ASSOC);
            if ($u && !empty($u['email'])) {
                // Inline dispatch
                require_once __DIR__ . '/../includes/mail.php';
                if (function_exists('sendWelcomeEmail')) {
                    // Send custom transactional email using PHPMailer
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mail->CharSet = 'UTF-8';
                    $smtp_host = get_env_var('SMTP_HOST');
                    $smtp_port = get_env_var('SMTP_PORT', 587);
                    $smtp_user = get_env_var('SMTP_USER');
                    $smtp_pass = get_env_var('SMTP_PASS');
                    $smtp_from = get_env_var('SMTP_FROM');
                    $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

                    if (!empty($smtp_user) && $smtp_user !== 'your_gmail_username_here') {
                        try {
                            $mail->isSMTP();
                            $mail->Host       = $smtp_host;
                            $mail->SMTPAuth   = true;
                            $mail->Username   = $smtp_user;
                            $mail->Password   = $smtp_pass;
                            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = $smtp_port;

                            $mail->setFrom($smtp_from, $smtp_from_name);
                            $mail->addAddress($u['email'], $u['full_name']);
                            $mail->isHTML(true);
                            $mail->Subject = "Medusa Loyalty Program Update: " . $title;
                            $mail->Body = "
                            <html>
                            <body style=\"font-family: 'Plus Jakarta Sans', sans-serif; background-color: #050505; color: #fff; padding: 2rem;\">
                                <div style=\"max-width: 600px; margin: 0 auto; background: #111; border: 1px solid #dfba86; border-radius: 12px; padding: 2rem;\">
                                    <h2 style=\"color: #dfba86; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 1rem;\">MEDUSA RESTAURANT</h2>
                                    <h3 style=\"color: #ffffff;\">{$title}</h3>
                                    <p style=\"color: #ccc; line-height: 1.6;\">Dear {$u['full_name']},</p>
                                    <p style=\"color: #ccc; line-height: 1.6;\">{$message}</p>
                                    <p style=\"color: #999; font-size: 0.8rem; margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem;\">Medusa Luxury Dining Room, SCO 44,45, Sector 68, SAS Nagar</p>
                                </div>
                            </body>
                            </html>";
                            $mail->send();
                        } catch (Exception $mail_err) {
                            error_log("Failed sending loyalty email: " . $mail->ErrorInfo);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Notification dispatch error: " . $e->getMessage());
    }
}

// ── LOAD CONFIG RULES ──
$settings_file = dirname(__DIR__) . '/admintest/settings.json';
$rules = [
    'inactivity_months' => 3,
    'inactivity_deduction_percent' => 20.00,
    'gold_threshold' => 25000.00,
    'platinum_threshold' => 75000.00,
    'last_annual_reset_year' => null
];
if (file_exists($settings_file)) {
    $rules = array_merge($rules, json_decode(file_get_contents($settings_file), true) ?: []);
}
// Override with .env configurations
$rules['inactivity_months'] = intval(get_env_var('INACTIVITY_MONTHS', $rules['inactivity_months'] ?? 3));

$inactivity_months = intval($rules['inactivity_months']);
$penalty_percent   = floatval($rules['inactivity_deduction_percent']);

// ── 1. 3-MONTH INACTIVITY PENALTY ROUTINE ──
// We check all customers with a balance > 0.
// Warning triggers at (inactivity_months * 30) - 7 days (e.g. 83 days).
// Deduction triggers at (inactivity_months * 30) days (e.g. 90 days).
try {
    $days_limit = $inactivity_months * 30;
    $warning_days = $days_limit - 7;

    $stmt_users = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, u.phone, rp.current_balance, u.last_inactivity_check,
               COALESCE(
                   (SELECT MAX(order_date) FROM orders WHERE user_id = u.id AND order_status = 'completed'),
                   u.created_at
               ) AS last_activity
        FROM users u
        JOIN reward_points rp ON u.id = rp.user_id
        WHERE u.role = 'customer' AND rp.current_balance > 0
    ");
    $stmt_users->execute();
    $customers = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    foreach ($customers as $c) {
        $last_act_ts = strtotime($c['last_activity']);
        $days_inactive = floor((time() - $last_act_ts) / 86400);

        // A. WARNING TRIGGERS (83 - 89 days inactive)
        if ($days_inactive >= $warning_days && $days_inactive < $days_limit) {
            // Check if warning already sent recently to prevent spam
            $check_warn = $pdo->prepare("
                SELECT COUNT(*) FROM user_notifications 
                WHERE user_id = ? AND title = 'Loyalty Warning: Impending Point Deduction' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $check_warn->execute([$c['id']]);
            if ($check_warn->fetchColumn() == 0) {
                $rem_days = $days_limit - $days_inactive;
                $msg = "We missed you! You haven't placed an order in nearly {$inactivity_months} months. Please place an order within the next {$rem_days} days to prevent an inactivity penalty deduction of {$penalty_percent}% of your reward points balance.";
                sendLoyaltyNotification($c['id'], "Loyalty Warning: Impending Point Deduction", $msg);
                
                // Write log to otp_log
                file_put_contents(dirname(__DIR__) . '/otp_log.txt', "[" . date('Y-m-d H:i:s') . "] [LOYALTY_WARN] Sent warning to user {$c['id']} ({$c['email']})\n", FILE_APPEND);
            }
        }

        // B. DEDUCTION TRIGGERS (>= 90 days inactive)
        if ($days_inactive >= $days_limit) {
            // Check if user has already been checked/penalized within the last 90 days
            $need_deduction = false;
            if (empty($c['last_inactivity_check'])) {
                $need_deduction = true;
            } else {
                $last_check_ts = strtotime($c['last_inactivity_check']);
                if ((time() - $last_check_ts) >= (90 * 86400)) {
                    $need_deduction = true;
                }
            }

            if ($need_deduction) {
                $curr_points = intval($c['current_balance']);
                $deducted = floor($curr_points * ($penalty_percent / 100));

                if ($deducted > 0) {
                    $new_balance = $curr_points - $deducted;

                    // Deduct
                    $pdo->beginTransaction();
                    
                    // Update reward balance
                    $upd_rp = $pdo->prepare("
                        UPDATE reward_points 
                        SET current_balance = ?, points_deducted = points_deducted + ? 
                        WHERE user_id = ?
                    ");
                    $upd_rp->execute([$new_balance, $deducted, $c['id']]);

                    // Log Loyalty Transaction
                    $ins_tx = $pdo->prepare("
                        INSERT INTO loyalty_transactions 
                        (user_id, points_deducted, transaction_type) 
                        VALUES (?, ?, 'inactivity_deduction')
                    ");
                    $ins_tx->execute([$c['id'], $deducted]);

                    // Update user check date
                    $upd_usr = $pdo->prepare("UPDATE users SET last_inactivity_check = CURDATE() WHERE id = ?");
                    $upd_usr->execute([$c['id']]);

                    $pdo->commit();

                    // Notify
                    $msg = "Due to inactivity for more than {$inactivity_months} consecutive months, a {$penalty_percent}% penalty has been applied. {$deducted} points have been deducted. Your remaining balance is {$new_balance} points.";
                    sendLoyaltyNotification($c['id'], "Loyalty Reward Points Deducted", $msg);
                    
                    file_put_contents(dirname(__DIR__) . '/otp_log.txt', "[" . date('Y-m-d H:i:s') . "] [LOYALTY_DEDUCT] Deducted {$deducted} points from user {$c['id']} due to inactivity.\n", FILE_APPEND);
                } else {
                    // Update user check date anyway to skip checks
                    $upd_usr = $pdo->prepare("UPDATE users SET last_inactivity_check = CURDATE() WHERE id = ?");
                    $upd_usr->execute([$c['id']]);
                }
            }
        }
    }
} catch (Exception $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Loyalty inactivity check failed: " . $ex->getMessage());
}

// ── 2. ANNUAL RESET RULE ROUTINE (January 1st) ──
$current_year = intval(date('Y'));
$last_reset_year = isset($rules['last_annual_reset_year']) ? intval($rules['last_annual_reset_year']) : null;

// For local testing, we can override or force reset via query parameter: `?force_annual_reset=1`
if ($last_reset_year === null || $current_year > $last_reset_year || (isset($_GET['force_annual_reset']) && $_GET['force_annual_reset'] == 1)) {
    try {
        echo "\nRunning Annual reset routines...\n";
        
        // Fetch all active customers having tier > 1 (Gold/Platinum)
        $stmt_tier_users = $pdo->prepare("
            SELECT id, full_name, current_tier_id 
            FROM users 
            WHERE role = 'customer' AND current_tier_id > 1
        ");
        $stmt_tier_users->execute();
        $tier_users = $stmt_tier_users->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tier_users as $tu) {
            $prev_tier_id = intval($tu['current_tier_id']);
            $new_tier_id  = max(1, $prev_tier_id - 1); // Downgrade by one tier

            if ($prev_tier_id !== $new_tier_id) {
                // Fetch tier names
                $t_stmt = $pdo->prepare("SELECT id, tier_name FROM customer_tiers WHERE id IN (?, ?)");
                $t_stmt->execute([$prev_tier_id, $new_tier_id]);
                $tier_names = $t_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $prev_name = $tier_names[$prev_tier_id] ?? 'Unknown';
                $new_name  = $tier_names[$new_tier_id] ?? 'Unknown';

                $pdo->beginTransaction();

                // Update user tier
                $upd_usr_t = $pdo->prepare("UPDATE users SET current_tier_id = ? WHERE id = ?");
                $upd_usr_t->execute([$new_tier_id, $tu['id']]);

                // Insert Tier History log
                $ins_hist = $pdo->prepare("
                    INSERT INTO tier_history (user_id, previous_tier_id, new_tier_id, reason) 
                    VALUES (?, ?, ?, 'Annual Tier Adjustment')
                ");
                $ins_hist->execute([$tu['id'], $prev_tier_id, $new_tier_id]);

                $pdo->commit();

                // Notify User
                $msg = "Happy New Year! As of January 1st, all customers have moved down one loyalty tier. Your tier has been adjusted from {$prev_name} to {$new_name}. Place orders to regain your premium status!";
                sendLoyaltyNotification($tu['id'], "Annual Loyalty Tier Adjustment", $msg);
                
                file_put_contents(dirname(__DIR__) . '/otp_log.txt', "[" . date('Y-m-d H:i:s') . "] [LOYALTY_RESET] Downgraded user {$tu['id']} from {$prev_name} to {$new_name} due to Annual Reset.\n", FILE_APPEND);
            }
        }

        // Update settings rules
        $rules['last_annual_reset_year'] = $current_year;
        file_put_contents($settings_file, json_encode($rules, JSON_PRETTY_PRINT));
        echo "✓ Annual reset routines completed.\n";

    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Annual reset routine failed: " . $ex->getMessage());
    }
} else {
    echo "• Annual reset checked: already executed for year {$current_year}.\n";
}

echo "── LOYALTY CRON COMPLETED SUCCESSFULLY ──\n";
