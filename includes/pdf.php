<?php
require_once dirname(__DIR__) . '/api/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('generateBillPdf')) {
    function generateBillPdf($order, $items) {
        // Initialize TCPDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Document details
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Medusa Restaurant');
        $pdf->SetTitle('Medusa Invoice ' . ($order['order_number'] ?? $order['id']));
        $pdf->SetSubject('Medusa Bill');

        // Disable standard headers & footers
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Margins & auto page breaks
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        // Add page
        $pdf->AddPage();

        // Template variables setup
        $website_name = get_env_var('RESTAURANT_NAME', 'Medusa');
        $logo_path = dirname(__DIR__) . '/assets/images/versace_logo.jpg';

        $variables = [
            'order' => $order,
            'items' => $items,
            'website_name' => $website_name,
            'logo_path' => $logo_path
        ];

        // Render template using output buffering
        ob_start();
        extract($variables);
        include dirname(__DIR__) . '/templates/pdf/bill.php';
        $html = ob_get_clean();

        // Render HTML inside TCPDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // Ensure bills folder exists in project root
        $billsDir = dirname(__DIR__) . '/bills';
        if (!file_exists($billsDir)) {
            mkdir($billsDir, 0777, true);
        }

        // Generate clean file name format: order_{order_id}_{timestamp}.pdf
        $timestamp = date('Ymd_His');
        $filename = 'order_' . ($order['id'] ?? uniqid()) . '_' . $timestamp . '.pdf';
        $filepath = $billsDir . '/' . $filename;

        // Save file to root bills/ folder
        $pdf->Output($filepath, 'F');

        // Return relative path for DB persistence
        return 'bills/' . $filename;
    }
}
