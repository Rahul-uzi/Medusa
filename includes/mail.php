<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('renderEmailTemplate')) {
    function renderEmailTemplate($templatePath, $variables) {
        if (!file_exists($templatePath)) {
            return "Template file not found: {$templatePath}";
        }
        extract($variables);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}

if (!function_exists('logEmailDebug')) {
    function logEmailDebug($email, $type, $user, $extra = []) {
        $logFile = dirname(__DIR__) . '/otp_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        
        $details = "Name: {$user['full_name']}\nEmail: {$user['email']}\nPhone: {$user['phone']}\n";
        foreach ($extra as $key => $val) {
            $details .= "{$key}: {$val}\n";
        }
        
        $logMessage = "[{$timestamp}] [{$type}] Email queued/sent to {$email}:\n----------------------------------------\n{$details}----------------------------------------\n\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

if (!function_exists('sendWelcomeEmail')) {
    function sendWelcomeEmail($user, $toName = null) {
        if (is_string($user)) {
            $user = [
                'email' => $user,
                'full_name' => $toName,
                'phone' => 'N/A'
            ];
        }
        if (empty($user['email'])) return true;
        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');
        $smtp_host = get_env_var('SMTP_HOST');
        $smtp_port = get_env_var('SMTP_PORT', 587);
        $smtp_user = get_env_var('SMTP_USER');
        $smtp_pass = get_env_var('SMTP_PASS');
        $smtp_from = get_env_var('SMTP_FROM');
        $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

        $logo_cid = 'logo_cid';
        $login_url = 'http://localhost/restaurant_medusa/login.html';
        $support_email = 'support@medusarestaurant.com';
        $support_phone = '+91 94272 72798';

        $variables = [
            'user' => $user,
            'website_name' => $website_name,
            'logo_cid' => $logo_cid,
            'login_url' => $login_url,
            'support_email' => $support_email,
            'support_phone' => $support_phone
        ];

        $templatePath = dirname(__DIR__) . '/templates/email/welcome.php';
        $htmlContent = renderEmailTemplate($templatePath, $variables);

        // Log to local file for debug
        logEmailDebug($user['email'], 'WELCOME_EMAIL', $user, ['Status' => 'Activated']);

        // Check if SMTP options are default/placeholders
        if (empty($smtp_user) || $smtp_user === 'your_gmail_username_here' || empty($smtp_pass) || $smtp_pass === 'your_gmail_app_password_here') {
            return true;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;

            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($user['email'], $user['full_name']);

            // Embed brand logo image
            $logoPath = dirname(__DIR__) . '/assets/images/medusaa2(onlylogo).png';
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, $logo_cid, 'medusaa2(onlylogo).png');
            }

            $mail->isHTML(true);
            $mail->Subject = "Welcome to {$website_name}!";
            $mail->Body    = $htmlContent;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Welcome Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('sendConfirmationEmail')) {
    function sendConfirmationEmail($user, $toName = null) {
        if (is_string($user)) {
            $user = [
                'email' => $user,
                'full_name' => $toName,
                'phone' => 'N/A'
            ];
        }
        if (empty($user['email'])) return true;
        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');
        $smtp_host = get_env_var('SMTP_HOST');
        $smtp_port = get_env_var('SMTP_PORT', 587);
        $smtp_user = get_env_var('SMTP_USER');
        $smtp_pass = get_env_var('SMTP_PASS');
        $smtp_from = get_env_var('SMTP_FROM');
        $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

        $logo_cid = 'logo_cid';
        $registration_date = date('Y-m-d H:i:s');
        $support_email = 'support@medusarestaurant.com';
        $support_phone = '+91 94272 72798';

        $variables = [
            'user' => $user,
            'website_name' => $website_name,
            'logo_cid' => $logo_cid,
            'registration_date' => $registration_date,
            'support_email' => $support_email,
            'support_phone' => $support_phone
        ];

        $templatePath = dirname(__DIR__) . '/templates/email/confirmation.php';
        $htmlContent = renderEmailTemplate($templatePath, $variables);

        // Log to local file for debug
        logEmailDebug($user['email'], 'ACCOUNT_CONFIRMATION', $user, ['Registration Date' => $registration_date]);

        // Check if SMTP options are default/placeholders
        if (empty($smtp_user) || $smtp_user === 'your_gmail_username_here' || empty($smtp_pass) || $smtp_pass === 'your_gmail_app_password_here') {
            return true;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;

            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($user['email'], $user['full_name']);

            // Embed brand logo image
            $logoPath = dirname(__DIR__) . '/assets/images/medusaa2(onlylogo).png';
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, $logo_cid, 'medusaa2(onlylogo).png');
            }

            $mail->isHTML(true);
            $mail->Subject = "Registration Confirmed - {$website_name}";
            $mail->Body    = $htmlContent;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Confirmation Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('sendBillEmail')) {
    /**
     * Sends order bill confirmation email with invoice PDF attachment via PHPMailer.
     * 
     * @param array|string $user User associative details array or recipient email string
     * @param array $order Order record details array
     * @param string $pdfPath Relative path of the generated PDF invoice file
     * @return bool True if successful, false otherwise
     */
    function sendBillEmail($user, $order, $pdfPath) {
        if (is_string($user)) {
            $user = [
                'email' => $user,
                'full_name' => 'Medusa Customer',
                'phone' => 'N/A'
            ];
        }
        
        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');
        $smtp_host = get_env_var('SMTP_HOST');
        $smtp_port = get_env_var('SMTP_PORT', 587);
        $smtp_user = get_env_var('SMTP_USER');
        $smtp_pass = get_env_var('SMTP_PASS');
        $smtp_from = get_env_var('SMTP_FROM');
        $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

        // Generate download URL
        require_once __DIR__ . '/token_helper.php';
        $download_token = generateToken($order['id']);
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if ($host === 'localhost' || $host === '127.0.0.1') {
            $local_ip = gethostbyname(gethostname());
            if ($local_ip && $local_ip !== '127.0.0.1') {
                $host = $local_ip;
            }
        }
        $download_url = $protocol . $host . "/restaurant_medusa/download_bill.php?id=" . $order['id'] . "&token=" . $download_token;

        $logo_cid = 'logo_cid';
        $support_email = 'support@medusarestaurant.com';
        $support_phone = '+91 94272 72798';

        $variables = [
            'user' => $user,
            'order' => $order,
            'download_url' => $download_url,
            'website_name' => $website_name,
            'logo_cid' => $logo_cid,
            'support_email' => $support_email,
            'support_phone' => $support_phone
        ];

        $templatePath = dirname(__DIR__) . '/templates/email/order_bill.php';
        $htmlContent = renderEmailTemplate($templatePath, $variables);

        // Log to local file for debug
        logEmailDebug($user['email'], 'ORDER_BILL_ATTACHMENT', $user, [
            'Order ID' => $order['order_number'] ?? $order['id'],
            'PDF Path' => $pdfPath,
            'Download URL' => $download_url
        ]);

        // Check if SMTP options are default/placeholders
        if (empty($smtp_user) || $smtp_user === 'your_gmail_username_here' || empty($smtp_pass) || $smtp_pass === 'your_gmail_app_password_here') {
            return true;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;

            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($user['email'], $user['full_name']);

            // Attach PDF invoice as Bill_Order_[ID].pdf
            $pdf_absolute_path = dirname(__DIR__) . '/' . $pdfPath;
            if (!file_exists($pdf_absolute_path)) {
                $pdf_absolute_path = $pdfPath;
            }
            if (file_exists($pdf_absolute_path)) {
                $mail->addAttachment($pdf_absolute_path, 'Bill_Order_' . $order['id'] . '.pdf');
            }

            // Embed brand logo image
            $logoPath = dirname(__DIR__) . '/assets/images/medusaa2(onlylogo).png';
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, $logo_cid, 'medusaa2(onlylogo).png');
            }

            $mail->isHTML(true);
            $mail->Subject = "Your Order #" . ($order['order_number'] ?? $order['id']) . " — Bill & Confirmation";
            $mail->Body    = $htmlContent;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Order Bill Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('sendBookingEmail')) {
    /**
     * Sends table booking confirmation email with detailed summaries via PHPMailer.
     * 
     * @param array|string $user User details array or email string
     * @param array $booking Booking record details array
     * @return bool True if successful, false otherwise
     */
    function sendBookingEmail($user, $booking) {
        if (is_string($user)) {
            $user = [
                'email' => $user,
                'full_name' => $booking['customer_name'] ?? 'Guest Customer',
                'phone' => $booking['customer_phone'] ?? 'N/A'
            ];
        }
        
        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');
        $smtp_host = get_env_var('SMTP_HOST');
        $smtp_port = get_env_var('SMTP_PORT', 587);
        $smtp_user = get_env_var('SMTP_USER');
        $smtp_pass = get_env_var('SMTP_PASS');
        $smtp_from = get_env_var('SMTP_FROM');
        $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

        $logo_cid = 'logo_cid';
        $support_email = 'support@medusarestaurant.com';
        $support_phone = '+91 94272 72798';

        // Set defaults for venue info in the booking if not populated
        if (empty($booking['venue_name'])) {
            $booking['venue_name'] = $website_name;
        }
        if (empty($booking['venue_address'])) {
            $booking['venue_address'] = 'SCO 44,45, District One Market, Sector 68, Sahibzada Ajit Singh Nagar, Punjab 140308';
        }
        if (empty($booking['venue_phone'])) {
            $booking['venue_phone'] = $support_phone;
        }

        $variables = [
            'user' => $user,
            'booking' => $booking,
            'website_name' => $website_name,
            'logo_cid' => $logo_cid,
            'support_email' => $support_email,
            'support_phone' => $support_phone
        ];

        $templatePath = dirname(__DIR__) . '/templates/email/booking_confirmation.php';
        $htmlContent = renderEmailTemplate($templatePath, $variables);

        // Log to local file for debug
        logEmailDebug($user['email'], 'BOOKING_EMAIL', $user, [
            'Booking ID' => 'BK-' . $booking['id'],
            'Date' => $booking['booking_date'],
            'Time' => $booking['booking_time'],
            'Guests' => $booking['guests'],
            'Table Number' => $booking['table_number'] ?? 'Not Assigned',
            'Venue' => $booking['venue_name']
        ]);

        // Check if SMTP options are default/placeholders
        if (empty($smtp_user) || $smtp_user === 'your_gmail_username_here' || empty($smtp_pass) || $smtp_pass === 'your_gmail_app_password_here') {
            return true;
        }

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;

            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($user['email'], $user['full_name']);

            // Embed brand logo image
            $logoPath = dirname(__DIR__) . '/assets/images/medusaa2(onlylogo).png';
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, $logo_cid, 'medusaa2(onlylogo).png');
            }

            $mail->isHTML(true);
            $mail->Subject = "Booking Confirmed — #BK-" . $booking['id'] . " at " . $website_name;
            $mail->Body    = $htmlContent;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Booking Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}
