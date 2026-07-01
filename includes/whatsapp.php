<?php
require_once dirname(__DIR__) . '/api/config.php';

if (!function_exists('sendWhatsappBill')) {
    /**
     * Sends the order bill PDF as a document attachment via WhatsApp Business API.
     * 
     * @param string $phone Recipient mobile number
     * @param array $order Order record details array
     * @param string $pdfPath Relative path of the generated PDF invoice file
     * @return bool True if successful (or skipped cleanly), false on error
     */
    function sendWhatsappBill($phone, $order, $pdfPath) {
        $logFile = dirname(__DIR__) . '/otp_log.txt';
        $timestamp = date('Y-m-d H:i:s');

        // Check if WhatsApp delivery is active
        if (!defined('WHATSAPP_ENABLED') || !WHATSAPP_ENABLED) {
            // Skips silently + logs notice
            file_put_contents(
                $logFile,
                "[{$timestamp}] [WHATSAPP_INVOICE] WhatsApp dispatch skipped (API not configured) for {$phone} with attachment: {$pdfPath}\n",
                FILE_APPEND
            );
            return true;
        }

        // Check if API settings are empty
        $api_url = defined('WHATSAPP_API_URL') ? WHATSAPP_API_URL : '';
        $api_key = defined('WHATSAPP_API_KEY') ? WHATSAPP_API_KEY : '';
        $from_num = defined('WHATSAPP_FROM_NUMBER') ? WHATSAPP_FROM_NUMBER : '';

        if (empty($api_url) || empty($api_key)) {
            error_log("WhatsApp API settings are incomplete in config.php");
            file_put_contents(
                $logFile,
                "[{$timestamp}] [WHATSAPP_ERROR] Dispatch failed: Incomplete API configuration.\n",
                FILE_APPEND
            );
            return false;
        }

        // Format phone to clean country-code prefixed numeric string
        $to_phone = trim($phone);
        if (strlen($to_phone) === 10 && is_numeric($to_phone)) {
            $to_phone = '91' . $to_phone; // Default prefix to India if 10 digits
        }
        $to_phone = preg_replace('/[^0-9]/', '', $to_phone);

        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');
        $order_id = $order['order_number'] ?? $order['id'];
        $amount = number_format((float)$order['total_amount'], 2);
        $method = $order['payment_method'] ?? 'Online';

        // Format the WhatsApp message text template
        $message = "Hi " . ($order['customer_name'] ?? 'Customer') . " 👋, your order #{$order_id} is confirmed! 🎉\n" .
                  "Please find your bill attached. 🧾\n" .
                  "Total: ₹{$amount} | Payment: {$method}\n" .
                  "Thank you for shopping with us! 😊\n" .
                  "- {$website_name}";

        // Calculate secure download URL (to pass as reference or document link source)
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
        $pdf_url = $protocol . $host . "/restaurant_medusa/download_bill.php?id=" . $order['id'] . "&token=" . $download_token;

        // Construct standard Meta WhatsApp Business Cloud API JSON payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to_phone,
            'type' => 'document',
            'document' => [
                'link' => $pdf_url,
                'filename' => 'Bill_Order_' . $order['id'] . '.pdf',
                'caption' => $message
            ]
        ];

        // Send cURL POST request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            $error_msg = "cURL Error: " . $err;
            error_log("WhatsApp sending failed for Order {$order_id}: " . $error_msg);
            file_put_contents($logFile, "[{$timestamp}] [WHATSAPP_ERROR] WhatsApp failed for {$phone}: {$error_msg}\n", FILE_APPEND);
            return false;
        } else {
            if ($status_code >= 200 && $status_code < 300) {
                file_put_contents($logFile, "[{$timestamp}] [WHATSAPP_SUCCESS] WhatsApp document sent to {$phone}\n", FILE_APPEND);
                return true;
            } else {
                $error_msg = "HTTP Code {$status_code}. Response: " . $response;
                error_log("WhatsApp API Error for Order {$order_id}: " . $error_msg);
                file_put_contents($logFile, "[{$timestamp}] [WHATSAPP_ERROR] WhatsApp API returned error: {$error_msg}\n", FILE_APPEND);
                return false;
            }
        }
    }
}
