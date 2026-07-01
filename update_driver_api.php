<?php
$content = file_get_contents('d:/New folder/htdocs/restaurant_medusa/api/driver_api.php');

$insertStr = <<<PHP
        case 'update_location':
            \$order_number = \$data['order_number'] ?? '';
            \$lat = \$data['lat'] ?? null;
            \$lng = \$data['lng'] ?? null;
            
            if (empty(\$order_number) || \$lat === null || \$lng === null) {
                throw new Exception("Order number, lat, and lng are required");
            }
            
            \$stmt = \$pdo->prepare("UPDATE orders SET driver_lat = ?, driver_lng = ?, driver_last_updated = NOW() WHERE order_number = ?");
            \$stmt->execute([\$lat, \$lng, \$order_number]);
            
            echo json_encode(['success' => true]);
            break;

PHP;

$content = str_replace("        default:", $insertStr . "        default:", $content);
file_put_contents('d:/New folder/htdocs/restaurant_medusa/api/driver_api.php', $content);
echo "driver_api updated";
?>
