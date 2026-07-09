<table cellpadding="0" cellspacing="0" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, sans-serif; color: #333333;">
    <!-- Header Logo & Brand Name -->
    <tr>
        <td style="width: 20%; vertical-align: middle;">
            <?php 
                // Use the JPG version of the logo to avoid TCPDF PNG alpha channel errors without GD
                $custom_logo = __DIR__ . '/../../assets/images/medusaa2_pdf.jpg';
                if (!file_exists($custom_logo)) $custom_logo = __DIR__ . '/../../assets/images/medusaa2(onlylogo).png';
                if (file_exists($custom_logo)): 
                    $img_base64 = base64_encode(file_get_contents($custom_logo));
            ?>
                <img src="@<?php echo $img_base64; ?>" width="60" height="60" style="border-radius: 50%;">
            <?php endif; ?>
        </td>
        <td style="width: 80%; text-align: right; vertical-align: middle;">
            <span style="font-size: 22px; font-weight: bold; color: #b89225;">LA-MEDUSAA <span style="font-size: 14px; font-weight: normal; color: #888888; font-style: italic;">bar & lounge</span></span><br>
            <span style="font-size: 10px; color: #666666;">The Signature of Elegance<br>SCO 44,45, Sector 68, SAS Nagar | Support: support@medusarestaurant.com</span>
        </td>
    </tr>
    
    <tr>
        <td colspan="2" style="border-bottom: 1px solid #dddddd; padding-top: 15px; height: 1px;"></td>
    </tr>
    
    <!-- Order Meta Details -->
    <tr>
        <td style="width: 50%; padding-top: 15px; font-size: 11px; line-height: 1.5;">
            <strong style="color: #b89225; font-size: 12px;">DELIVER TO:</strong><br>
            <strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?><br>
            <strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?><br>
            <strong>Address:</strong> <?php echo htmlspecialchars(trim(preg_replace('/\[[\d.-]+,\s*[\d.-]+\]/', '', $order['delivery_address']))); ?><br>
            <?php if (!empty($order['delivery_city'])): ?>
                <strong>City:</strong> <?php echo htmlspecialchars($order['delivery_city']); ?><br>
            <?php endif; ?>

        </td>
        <td style="width: 50%; padding-top: 15px; text-align: right; font-size: 11px; line-height: 1.5;">
            <strong style="color: #b89225; font-size: 12px;">ORDER DETAILS:</strong><br>
            <strong>Order ID:</strong> <?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?><br>
            <strong>Order Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?><br>
            <strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? 'Online'); ?><br>
            <strong>Est. Delivery:</strong> <?php echo htmlspecialchars($order['estimated_delivery'] ?? 'N/A'); ?>
        </td>
    </tr>
    
    <tr>
        <td colspan="2" style="padding-top: 25px; height: 1px;"></td>
    </tr>
    
    <!-- Itemized Table Header -->
    <tr>
        <td colspan="2">
            <table cellpadding="6" cellspacing="0" style="width: 100%; font-size: 10px;">
                <tr style="background-color: #f5f5f5; font-weight: bold; color: #333333;">
                    <th style="width: 45%; border-bottom: 2px solid #dddddd;">Item Name</th>
                    <th style="width: 15%; text-align: center; border-bottom: 2px solid #dddddd;">Qty</th>
                    <th style="width: 20%; text-align: right; border-bottom: 2px solid #dddddd;">Unit Price</th>
                    <th style="width: 20%; text-align: right; border-bottom: 2px solid #dddddd;">Subtotal</th>
                </tr>
                
                <!-- Items list -->
                <?php 
                $items_subtotal = 0;
                foreach ($items as $item): 
                    $item_price = floatval($item['unit_price'] ?? $item['price'] ?? 0);
                    $item_qty = intval($item['quantity'] ?? 1);
                    $item_subtotal = $item_price * $item_qty;
                    $items_subtotal += $item_subtotal;
                ?>
                <tr>
                    <td style="border-bottom: 1px solid #eeeeee;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td style="text-align: center; border-bottom: 1px solid #eeeeee;"><?php echo $item_qty; ?></td>
                    <td style="text-align: right; border-bottom: 1px solid #eeeeee;">Rs. <?php echo number_format($item_price, 2); ?></td>
                    <td style="text-align: right; border-bottom: 1px solid #eeeeee;">Rs. <?php echo number_format($item_subtotal, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Totals Area -->
                <tr>
                    <td colspan="2" style="border-top: 1px solid #dddddd;"></td>
                    <td style="text-align: right; font-weight: bold; border-top: 1px solid #dddddd; font-size: 11px; padding-top: 10px;">Subtotal:</td>
                    <td style="text-align: right; border-top: 1px solid #dddddd; font-size: 11px; padding-top: 10px;">Rs. <?php echo number_format($items_subtotal, 2); ?></td>
                </tr>
                
                <!-- GST Breakdown (split CGST & SGST equally) -->
                <?php 
                $tax_amount = floatval($order['tax_amount'] ?? 0);
                $half_tax = $tax_amount / 2;
                ?>
                <tr>
                    <td colspan="2"></td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">CGST:</td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">Rs. <?php echo number_format($half_tax, 2); ?></td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">SGST:</td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">Rs. <?php echo number_format($half_tax, 2); ?></td>
                </tr>
                
                <!-- Other fees -->
                <?php if (isset($order['packing_charge']) && floatval($order['packing_charge']) > 0): ?>
                <tr>
                    <td colspan="2"></td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">Packing Fee:</td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">Rs. <?php echo number_format(floatval($order['packing_charge']), 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if (isset($order['delivery_charge']) && floatval($order['delivery_charge']) > 0): ?>
                <tr>
                    <td colspan="2"></td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">Delivery Charge:</td>
                    <td style="text-align: right; font-size: 10px; color: #555555;">Rs. <?php echo number_format(floatval($order['delivery_charge']), 2); ?></td>
                </tr>
                <?php endif; ?>

                <!-- Discount -->
                <?php 
                $total_discount = floatval($order['discount'] ?? 0) + floatval($order['coupon_discount'] ?? 0) + floatval($order['tier_discount_amount'] ?? 0) + floatval($order['points_redeemed_discount'] ?? 0);
                if ($total_discount > 0): 
                ?>
                <tr>
                    <td colspan="2"></td>
                    <td style="text-align: right; font-size: 10px; color: #d9534f; font-weight: bold;">Discount:</td>
                    <td style="text-align: right; font-size: 10px; color: #d9534f; font-weight: bold;">-Rs. <?php echo number_format($total_discount, 2); ?></td>
                </tr>
                <?php endif; ?>
                
                <!-- Grand Total -->
                <tr>
                    <td colspan="2" style="border-top: 1.5px double #dddddd;"></td>
                    <td style="text-align: right; font-weight: bold; font-size: 13px; color: #b89225; border-top: 1.5px double #dddddd; padding-top: 12px; padding-bottom: 12px;">Grand Total:</td>
                    <td style="text-align: right; font-weight: bold; font-size: 13px; color: #b89225; border-top: 1.5px double #dddddd; padding-top: 12px; padding-bottom: 12px;">Rs. <?php echo number_format(floatval($order['total_amount']), 2); ?></td>
                </tr>
            </table>
        </td>
    </tr>
    
    <tr>
        <td colspan="2" style="border-top: 1px solid #eeeeee; padding-top: 30px; height: 1px;"></td>
    </tr>
    
    <!-- Footer Section -->
    <tr>
        <td colspan="2" style="text-align: center; font-size: 10px; color: #7c7c7c; line-height: 1.6;">
            <strong style="color: #b89225;">Thank you for dining with Medusa!</strong><br>
            Your support makes our artisanal kitchen possible. For questions, please reach out to our Chandigarh guest relations team.<br>
            <span style="font-size: 8px; color: #aaaaaa;">Generated on <?php echo date('Y-m-d H:i:s'); ?>. System bill hash valid for download.</span>
        </td>
    </tr>
</table>
