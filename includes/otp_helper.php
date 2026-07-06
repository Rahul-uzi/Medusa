<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!function_exists('generateOTP')) {
    function generateOTP() {
        return strval(rand(100000, 999999));
    }
}

if (!function_exists('logOTPDebug')) {
    function logOTPDebug($recipient, $otp, $type) {
        $logFile = dirname(__DIR__) . '/otp_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$type}] OTP for {$recipient}: {$otp}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

if (!function_exists('sendOTPEmail')) {
    function sendOTPEmail($toEmail, $toName, $otp) {
        // Log to local file for debug
        logOTPDebug($toEmail, $otp, 'EMAIL');

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        $smtp_host = get_env_var('SMTP_HOST');
        $smtp_port = get_env_var('SMTP_PORT', 587);
        $smtp_user = get_env_var('SMTP_USER');
        $smtp_pass = get_env_var('SMTP_PASS');
        $smtp_from = get_env_var('SMTP_FROM');
        $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

        // Check if SMTP options are default/placeholders
        if (empty($smtp_user) || $smtp_user === 'your_gmail_username_here' || empty($smtp_pass) || $smtp_pass === 'your_gmail_app_password_here') {
            // Treat as fallback local mode, return true since we logged it to otp_log.txt
            return true;
        }

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;

            // Recipients
            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($toEmail, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Medusa Restaurant - Verify Your Account";

            // Professional HTML Template with Inline CSS
            $mail->Body = "
            <html>
            <body style=\"font-family: 'Plus Jakarta Sans', Arial, sans-serif; background-color: #0a0a0a; color: #f3f3f3; margin: 0; padding: 2rem 0;\">
                <div style=\"max-width: 600px; margin: 0 auto; padding: 2rem; background-color: #121111; border: 1px solid rgba(223, 186, 134, 0.2); border-radius: 16px;\">
                    <div style=\"text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 1rem;\">
                        <h2 style=\"font-family: 'Playfair Display', Georgia, serif; color: #dfba86; font-size: 1.6rem; margin: 0; letter-spacing: 2px;\">MEDUSA RESTAURANT</h2>
                    </div>
                    <div style=\"padding: 2rem 0; line-height: 1.6; font-size: 1rem; color: #f3f3f3;\">
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">Dear {$toName},</p>
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">Thank you for registering at Medusa. To activate your account and complete your registration, please verify your email address using the 6-digit One-Time Password (OTP) below:</p>
                        <div style=\"background-color: rgba(223, 186, 134, 0.08); border: 1.5px dashed #dfba86; border-radius: 12px; text-align: center; padding: 1.5rem; font-size: 2.2rem; font-weight: bold; letter-spacing: 6px; color: #dfba86; margin: 1.5rem 0;\">{$otp}</div>
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">This verification code is valid for <strong>10 minutes</strong>. If you did not request this code, please ignore this email.</p>
                    </div>
                    <div style=\"text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 1rem; font-size: 0.8rem; color: #a09f9f;\">
                        <p style=\"color: #a09f9f; margin: 0;\">&copy; 2026 Medusa Restaurant. SCO 44,45, Sector 68, SAS Nagar, Punjab 140308. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mail sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('sendOTPSMS')) {
    function sendOTPSMS($phone, $otp) {
        // Log to local file for debug
        logOTPDebug($phone, $otp, 'SMS');

        $sms_provider = get_env_var('SMS_PROVIDER', 'none');
        $sms_message = "Your Medusa account phone verification OTP is: {$otp}. Valid for 10 minutes.";

        if ($sms_provider === 'none') {
            return true;
        }

        if ($sms_provider === 'android') {
            $gateway_url = get_env_var('ANDROID_GATEWAY_URL');
            if (empty($gateway_url)) return true;

            // Format phone to clean numeric string
            $to_phone = trim($phone);
            if (strpos($to_phone, '+91') === 0) {
                $to_phone = substr($to_phone, 3);
            }
            $to_phone = preg_replace('/[^0-9]/', '', $to_phone);

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

            curl_exec($ch);
            curl_close($ch);
            return true;
        }

        if ($sms_provider === 'twilio') {
            $twilio_sid = get_env_var('TWILIO_ACCOUNT_SID');
            $twilio_token = get_env_var('TWILIO_AUTH_TOKEN');
            $twilio_from = get_env_var('TWILIO_FROM_NUMBER');

            if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_from) || strpos($twilio_sid, 'ACxxxxxxxx') === 0) {
                return true;
            }

            $to_phone = trim($phone);
            if (strlen($to_phone) === 10 && is_numeric($to_phone)) {
                $to_phone = '+91' . $to_phone;
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

            curl_exec($ch);
            curl_close($ch);
            return true;
        }

        return true;
    }
}

if (!function_exists('sendWelcomeEmail')) {
    function sendWelcomeEmail($toEmail, $toName) {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        $smtp_host = get_env_var('SMTP_HOST');
        $smtp_port = get_env_var('SMTP_PORT', 587);
        $smtp_user = get_env_var('SMTP_USER');
        $smtp_pass = get_env_var('SMTP_PASS');
        $smtp_from = get_env_var('SMTP_FROM');
        $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

        if (empty($smtp_user) || $smtp_user === 'your_gmail_username_here' || empty($smtp_pass) || $smtp_pass === 'your_gmail_app_password_here') {
            return true;
        }

        try {
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;

            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = "Welcome to Medusa Restaurant!";
            $mail->Body = "
            <html> 
            <body style=\"font-family: 'Plus Jakarta Sans', Arial, sans-serif; background-color: #0a0a0a; color: #f3f3f3; margin: 0; padding: 2rem 0;\">
                <div style=\"max-width: 600px; margin: 0 auto; padding: 2rem; background-color: #121111; border: 1px solid rgba(223, 186, 134, 0.2); border-radius: 16px;\">
                    <div style=\"text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 1rem;\">
                        <h2 style=\"font-family: 'Playfair Display', Georgia, serif; color: #dfba86; font-size: 1.6rem; margin: 0; letter-spacing: 2px;\">MEDUSA RESTAURANT</h2>
                    </div>
                    <div style=\"padding: 2rem 0; line-height: 1.6; font-size: 1rem; color: #f3f3f3;\">
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">Dear {$toName},</p>
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">Welcome to Medusa! We are thrilled to confirm that your account has been successfully verified and activated.</p>
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">You can now log in to reserve tables, browse our artisanal menu, customize your dishes, and place orders directly from our digital command center.</p>
                        <div style=\"text-align: center; margin-top: 1.5rem;\">
                            <a href=\"http://localhost/restaurant_medusa/login.html\" style=\"display: inline-block; background-color: #dfba86; color: #0a0a0a; padding: 0.8rem 2rem; text-decoration: none; border-radius: 8px; font-weight: bold;\">Log In to Your Account</a>
                        </div>
                    </div>
                    <div style=\"text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 1rem; font-size: 0.8rem; color: #a09f9f;\">
                        <p style=\"color: #a09f9f; margin: 0;\">&copy; 2026 Medusa Restaurant. SCO 44,45, Sector 68, SAS Nagar, Punjab 140308. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Welcome Mail sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('sendLoginAlertEmail')) {
    function sendLoginAlertEmail($toEmail, $toName, $ip, $ua, $time) {
        // Log to local file for debug
        logOTPDebug($toEmail, "Login alert triggered from IP {$ip} / {$ua}", 'LOGIN_ALERT');

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        $smtp_host = get_env_var('SMTP_HOST');
        $smtp_port = get_env_var('SMTP_PORT', 587);
        $smtp_user = get_env_var('SMTP_USER');
        $smtp_pass = get_env_var('SMTP_PASS');
        $smtp_from = get_env_var('SMTP_FROM');
        $smtp_from_name = get_env_var('SMTP_FROM_NAME', 'Medusa Restaurant');

        // Check if SMTP options are default/placeholders
        if (empty($smtp_user) || $smtp_user === 'your_gmail_username_here' || empty($smtp_pass) || $smtp_pass === 'your_gmail_app_password_here') {
            // Treat as fallback local mode, return true
            return true;
        }

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_user;
            $mail->Password   = $smtp_pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp_port;

            // Recipients
            $mail->setFrom($smtp_from, $smtp_from_name);
            $mail->addAddress($toEmail, $toName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Security Alert: New Login to Medusa Restaurant";

            $mail->Body = "
            <html>
            <body style=\"font-family: 'Plus Jakarta Sans', Arial, sans-serif; background-color: #0a0a0a; color: #f3f3f3; margin: 0; padding: 2rem 0;\">
                <div style=\"max-width: 600px; margin: 0 auto; padding: 2rem; background-color: #121111; border: 1px solid rgba(223, 186, 134, 0.2); border-radius: 16px;\">
                    <div style=\"text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 1rem;\">
                        <h2 style=\"font-family: 'Playfair Display', Georgia, serif; color: #dfba86; font-size: 1.6rem; margin: 0; letter-spacing: 2px;\">MEDUSA RESTAURANT</h2>
                    </div>
                    <div style=\"padding: 2rem 0; line-height: 1.6; font-size: 1rem; color: #f3f3f3;\">
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">Dear {$toName},</p>
                        <p style=\"color: #f3f3f3; margin: 0 0 1.5rem 0;\">A new login was detected on your Medusa Restaurant account.</p>
                        <div style=\"background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 12px; padding: 1.2rem; margin: 1.5rem 0;\">
                            <table style=\"width: 100%; border-collapse: collapse; color: #f3f3f3; font-size: 0.9rem;\">
                                <tr>
                                    <td style=\"padding: 0.4rem 0; font-weight: 600; color: #dfba86; width: 120px;\">IP Address:</td>
                                    <td style=\"padding: 0.4rem 0;\">" . htmlspecialchars($ip) . "</td>
                                </tr>
                                <tr>
                                    <td style=\"padding: 0.4rem 0; font-weight: 600; color: #dfba86;\">Device/Browser:</td>
                                    <td style=\"padding: 0.4rem 0;\">" . htmlspecialchars($ua) . "</td>
                                </tr>
                                <tr>
                                    <td style=\"padding: 0.4rem 0; font-weight: 600; color: #dfba86;\">Time:</td>
                                    <td style=\"padding: 0.4rem 0;\">" . htmlspecialchars($time) . "</td>
                                </tr>
                            </table>
                        </div>
                        <p style=\"color: #f3f3f3; margin: 0 0 1rem 0;\">If this was you, no action is needed. If you do not recognize this activity, please log in to your account, navigate to <strong>Security & Sessions</strong>, and revoke this session immediately.</p>
                    </div>
                    <div style=\"text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 1rem; font-size: 0.8rem; color: #a09f9f;\">
                        <p style=\"color: #a09f9f; margin: 0;\">&copy; 2026 Medusa Restaurant. SCO 44,45, Sector 68, SAS Nagar, Punjab 140308. All Rights Reserved.</p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Login Alert Mail sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}
