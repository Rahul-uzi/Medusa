<?php
require_once dirname(__DIR__) . '/api/config.php';

if (!function_exists('sendOrderSms')) {
    /**
     * Sends order confirmation SMS with download link via the configured SMS Gateway (Android, Twilio, or Simple SMS Gateway).
     * 
     * @param string $phone Customer phone number
     * @param string $name Customer name
     * @param string $order_id Alphanumeric order number or database ID
     * @param string $pdfUrl Secure download URL for the PDF bill
     * @return bool True if successful, false otherwise
     */
    function sendOrderSms($phone, $name, $order_id, $pdfUrl) {
        $sms_provider = get_env_var('SMS_PROVIDER', 'none');
        if ($sms_provider === 'none') {
            return true;
        }

        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');

        // Construct the message matching the exact format
        $sms_message = "Dear {$name}, your order #{$order_id} is confirmed!\n" .
                       "Download your bill here: {$pdfUrl}\n" .
                       "Thank you! - {$website_name}";

        // Format phone to clean numeric string
        $to_phone = trim($phone);
        if (strpos($to_phone, '+91') === 0) {
            $to_phone = substr($to_phone, 3);
        } elseif (strpos($to_phone, '91') === 0 && strlen($to_phone) === 12) {
            $to_phone = substr($to_phone, 2);
        }
        $to_phone = preg_replace('/[^0-9]/', '', $to_phone);

        $logFile = dirname(__DIR__) . '/otp_log.txt';
        $timestamp = date('Y-m-d H:i:s');

        // 1. Android gateway (local device, e.g. Simple SMS Gateway app)
        if ($sms_provider === 'android') {
            $gateway_url = get_env_var('ANDROID_GATEWAY_URL');
            if (empty($gateway_url)) {
                $gateway_url = 'http://192.168.1.20:8080/send-sms'; // default fallback android gateway URL
            }

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
                $error_msg = "cURL Error: " . $err;
                error_log("SMS sending failed for Order {$order_id}: " . $error_msg);
                file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_ERROR] SMS failed for {$phone}: {$error_msg}\n", FILE_APPEND);
                return false;
            } else {
                if ($status_code >= 200 && $status_code < 300) {
                    file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_SUCCESS] SMS sent to {$phone}: {$sms_message}\n", FILE_APPEND);
                    return true;
                } else {
                    $error_msg = "HTTP Code {$status_code}. Response: " . $response;
                    error_log("SMS Gateway API Error for Order {$order_id}: " . $error_msg);
                    file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_ERROR] Gateway returned error: {$error_msg}\n", FILE_APPEND);
                    return false;
                }
            }
        }

        // 2. Twilio Gateway
        if ($sms_provider === 'twilio') {
            $twilio_sid = get_env_var('TWILIO_ACCOUNT_SID');
            $twilio_token = get_env_var('TWILIO_AUTH_TOKEN');
            $twilio_from = get_env_var('TWILIO_FROM_NUMBER');

            if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_from) || strpos($twilio_sid, 'ACxxxxxxxx') === 0) {
                file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_ERROR] Twilio credentials not configured.\n", FILE_APPEND);
                return false;
            }

            $to_phone_full = $to_phone;
            if (strlen($to_phone_full) === 10 && is_numeric($to_phone_full)) {
                $to_phone_full = '+91' . $to_phone_full;
            }

            $url = "https://api.twilio.com/2010-04-01/Accounts/" . $twilio_sid . "/Messages.json";
            $payload = [
                'From' => $twilio_from,
                'To'   => $to_phone_full,
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
                file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_ERROR] Twilio SMS failed for {$phone}: {$err}\n", FILE_APPEND);
                return false;
            } else {
                if ($status_code >= 200 && $status_code < 300) {
                    file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_SUCCESS] Twilio SMS sent to {$phone}: {$sms_message}\n", FILE_APPEND);
                    return true;
                } else {
                    file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_ERROR] Twilio returned error Code {$status_code}: {$response}\n", FILE_APPEND);
                    return false;
                }
            }
        }

        // 3. Fallback or legacy cloud-based Simple SMS Gateway
        $gateway_url = get_env_var('SMS_GATEWAY_URL');
        $token = get_env_var('SMS_GATEWAY_TOKEN');
        $device_id = get_env_var('SMS_GATEWAY_DEVICE_ID');

        // Fallback to .env.php in the project root if empty
        $envPhpPath = dirname(__DIR__) . '/.env.php';
        if (file_exists($envPhpPath)) {
            $envPhp = require $envPhpPath;
            if (is_array($envPhp)) {
                if (empty($gateway_url)) {
                    $gateway_url = $envPhp['SMS_GATEWAY_URL'] ?? '';
                }
                if (empty($token)) {
                    $token = $envPhp['SMS_GATEWAY_TOKEN'] ?? '';
                }
                if (empty($device_id)) {
                    $device_id = $envPhp['SMS_GATEWAY_DEVICE_ID'] ?? '';
                }
            }
        }

        if (empty($gateway_url)) {
            $gateway_url = 'http://100.81.109.141:8080/send';
        }

        $payload = [
            'phone' => $to_phone,
            'message' => $sms_message,
            'device' => $device_id,
            'device_id' => $device_id
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gateway_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $error_msg = "cURL Error: " . $err;
            error_log("SMS sending failed for Order {$order_id}: " . $error_msg);
            file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_ERROR] SMS failed for {$phone}: {$error_msg}\n", FILE_APPEND);
            return false;
        } else {
            if ($status_code >= 200 && $status_code < 300) {
                file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_SUCCESS] SMS sent to {$phone}: {$sms_message}\n", FILE_APPEND);
                return true;
            } else {
                $error_msg = "HTTP Code {$status_code}. Response: " . $response;
                error_log("SMS Gateway API Error for Order {$order_id}: " . $error_msg);
                file_put_contents($logFile, "[{$timestamp}] [SMS_GATEWAY_ERROR] Gateway returned error: {$error_msg}\n", FILE_APPEND);
                return false;
            }
        }
    }
}

if (!function_exists('sendBookingSms')) {
    /**
     * Sends table booking confirmation SMS via the configured SMS Gateway (Android, Twilio, or Simple SMS Gateway).
     * 
     * @param string $phone Recipient mobile number
     * @param array $booking Booking record details array
     * @return bool True if successful, false otherwise
     */
    function sendBookingSms($phone, $booking) {
        $sms_provider = get_env_var('SMS_PROVIDER', 'none');
        if ($sms_provider === 'none') {
            return true;
        }

        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');
        
        $name = $booking['customer_name'] ?? 'Customer';
        $booking_id = $booking['id'];
        $date = $booking['booking_date'];
        $time = $booking['booking_time'];
        $guests = $booking['guests'];
        $venue_name = $booking['venue_name'] ?? $website_name;
        $venue_address = $booking['venue_address'] ?? 'SCO 44,45, District One Market, Sector 68, Sahibzada Ajit Singh Nagar, Punjab 140308';

        // Construct the message matching the exact required format
        $sms_message = "Dear {$name}, your table booking #BK-{$booking_id} is confirmed!\n" .
                       "Date: {$date} | Time: {$time} | Guests: {$guests}\n" .
                       "Venue: {$venue_name}, {$venue_address}\n" .
                       "Please arrive 10 mins early. - {$website_name}";

        // Format phone to clean numeric string
        $to_phone = trim($phone);
        if (strpos($to_phone, '+91') === 0) {
            $to_phone = substr($to_phone, 3);
        } elseif (strpos($to_phone, '91') === 0 && strlen($to_phone) === 12) {
            $to_phone = substr($to_phone, 2);
        }
        $to_phone = preg_replace('/[^0-9]/', '', $to_phone);

        $logFile = dirname(__DIR__) . '/otp_log.txt';
        $timestamp = date('Y-m-d H:i:s');

        // 1. Android gateway (local device, e.g. Simple SMS Gateway app)
        if ($sms_provider === 'android') {
            $gateway_url = get_env_var('ANDROID_GATEWAY_URL');
            if (empty($gateway_url)) {
                $gateway_url = 'http://192.168.1.20:8080/send-sms'; // default fallback android gateway URL
            }

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
                $error_msg = "cURL Error: " . $err;
                error_log("Booking SMS sending failed for Booking BK-{$booking_id}: " . $error_msg);
                file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_ERROR] SMS failed for {$phone}: {$error_msg}\n", FILE_APPEND);
                return false;
            } else {
                if ($status_code >= 200 && $status_code < 300) {
                    file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_SUCCESS] SMS sent to {$phone}: {$sms_message}\n", FILE_APPEND);
                    return true;
                } else {
                    $error_msg = "HTTP Code {$status_code}. Response: " . $response;
                    error_log("Booking SMS Gateway API Error for Booking BK-{$booking_id}: " . $error_msg);
                    file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_ERROR] Gateway returned error: {$error_msg}\n", FILE_APPEND);
                    return false;
                }
            }
        }

        // 2. Twilio Gateway
        if ($sms_provider === 'twilio') {
            $twilio_sid = get_env_var('TWILIO_ACCOUNT_SID');
            $twilio_token = get_env_var('TWILIO_AUTH_TOKEN');
            $twilio_from = get_env_var('TWILIO_FROM_NUMBER');

            if (empty($twilio_sid) || empty($twilio_token) || empty($twilio_from) || strpos($twilio_sid, 'ACxxxxxxxx') === 0) {
                file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_ERROR] Twilio credentials not configured.\n", FILE_APPEND);
                return false;
            }

            $to_phone_full = $to_phone;
            if (strlen($to_phone_full) === 10 && is_numeric($to_phone_full)) {
                $to_phone_full = '+91' . $to_phone_full;
            }

            $url = "https://api.twilio.com/2010-04-01/Accounts/" . $twilio_sid . "/Messages.json";
            $payload = [
                'From' => $twilio_from,
                'To'   => $to_phone_full,
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
                file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_ERROR] Twilio SMS failed for {$phone}: {$err}\n", FILE_APPEND);
                return false;
            } else {
                if ($status_code >= 200 && $status_code < 300) {
                    file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_SUCCESS] Twilio SMS sent to {$phone}: {$sms_message}\n", FILE_APPEND);
                    return true;
                } else {
                    file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_ERROR] Twilio returned error Code {$status_code}: {$response}\n", FILE_APPEND);
                    return false;
                }
            }
        }

        // 3. Fallback or legacy cloud-based Simple SMS Gateway
        $gateway_url = get_env_var('SMS_GATEWAY_URL');
        $token = get_env_var('SMS_GATEWAY_TOKEN');
        $device_id = get_env_var('SMS_GATEWAY_DEVICE_ID');

        // Fallback to .env.php in the project root if empty
        $envPhpPath = dirname(__DIR__) . '/.env.php';
        if (file_exists($envPhpPath)) {
            $envPhp = require $envPhpPath;
            if (is_array($envPhp)) {
                if (empty($gateway_url)) {
                    $gateway_url = $envPhp['SMS_GATEWAY_URL'] ?? '';
                }
                if (empty($token)) {
                    $token = $envPhp['SMS_GATEWAY_TOKEN'] ?? '';
                }
                if (empty($device_id)) {
                    $device_id = $envPhp['SMS_GATEWAY_DEVICE_ID'] ?? '';
                }
            }
        }

        if (empty($gateway_url)) {
            $gateway_url = 'http://100.81.109.141:8080/send';
        }

        $payload = [
            'phone' => $to_phone,
            'message' => $sms_message,
            'device' => $device_id,
            'device_id' => $device_id
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gateway_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $error_msg = "cURL Error: " . $err;
            error_log("Booking SMS sending failed for Booking BK-{$booking_id}: " . $error_msg);
            file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_ERROR] SMS failed for {$phone}: {$error_msg}\n", FILE_APPEND);
            return false;
        } else {
            if ($status_code >= 200 && $status_code < 300) {
                file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_SUCCESS] SMS sent to {$phone}: {$sms_message}\n", FILE_APPEND);
                return true;
            } else {
                $error_msg = "HTTP Code {$status_code}. Response: " . $response;
                error_log("Booking SMS Gateway API Error for Booking BK-{$booking_id}: " . $error_msg);
                file_put_contents($logFile, "[{$timestamp}] [BOOKING_SMS_ERROR] Gateway returned error: {$error_msg}\n", FILE_APPEND);
                return false;
            }
        }
    }
}
?>
