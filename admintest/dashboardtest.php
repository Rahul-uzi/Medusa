<?php
require_once dirname(__DIR__) . '/api/config.php';
requireLogin();

if (empty($_SESSION['user_role'])) {
    header('Location: ../login.html');
    exit;
}

if ($_SESSION['user_role'] === 'driver') {
    require __DIR__ . '/driver_dashboard.php';
    exit; // STOP EXECUTION COMPLETELY FOR DRIVERS
}

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.html');
    exit;
}

if (isset($_REQUEST['action'])) {
    require_same_origin_unsafe_request();
    rate_limit('admin_dashboard_action', 240, 300);
}

// Ensure feedback table exists before running any queries
try {
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS `feedback` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(20) NOT NULL,
            `rating` INT NOT NULL,
            `review` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_feedback_orders` FOREIGN KEY (`order_number`) REFERENCES `orders` (`order_number`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($createTableQuery);

    $createCampaignsQuery = "
        CREATE TABLE IF NOT EXISTS `campaigns` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_code` VARCHAR(50) UNIQUE NOT NULL,
            `discount_type` ENUM('percentage', 'flat') NOT NULL,
            `discount_value` DECIMAL(10,2) NOT NULL,
            `min_order_value` DECIMAL(10,2) DEFAULT 0.00,
            `max_discount` DECIMAL(10,2) DEFAULT NULL,
            `expiry_date` DATETIME DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `usage_limit` INT DEFAULT NULL,
            `used_count` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($createCampaignsQuery);

    // Seed the default SUMMER2026 campaign if not exists
    $pdo->exec("INSERT IGNORE INTO `campaigns` (`campaign_code`, `discount_type`, `discount_value`) VALUES ('SUMMER2026', 'percentage', 10.00)");
} catch (PDOException $e) {
    // Fail silently
}

// Ensure subscribers table exists
try {
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS `subscribers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $pdo->exec($createTableQuery);
} catch (PDOException $e) {
    // Fail silently
}

// Fetch subscribers for Mass Emails tab
$stmt = $pdo->query("SELECT * FROM subscribers ORDER BY subscribed_at DESC");
$subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to render gold stars based on feedback rating
function renderStars($rating, $review = '') {
    $rating = intval($rating);
    if ($rating < 1 || $rating > 5) return '';
    $starsHtml = '<div class="feedback-stars mt-1" style="color: #dfba86; font-size: 0.85rem;" title="' . htmlspecialchars($rating) . '/5 Stars' . (!empty($review) ? ': ' . htmlspecialchars($review) : '') . '">';
    for ($i = 1; $i <= 5; $i++) {
        $starsHtml .= ($i <= $rating) ? '★' : '☆';
    }
    $starsHtml .= '</div>';
    return $starsHtml;
}

// Helper to determine if a dish is Vegetarian based on name/description keywords
function isVegItem($name, $description = '') {
    $non_veg_keywords = ['chicken', 'biryani', 'rogan', 'josh', 'lamb', 'mutton', 'pork', 'fish', 'prawn', 'shrimp', 'pepperoni', 'meat', 'wings', 'ribs', 'chashu', 'bolognese', 'lasagna', 'bacon', 'beef', 'duck', 'egg'];
    $text = strtolower($name . ' ' . $description);
    foreach ($non_veg_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return false;
        }
    }
    return true;
}

// Helper to resolve dish image URLs for admin view and prevent 404s
function getDishImage($image_url) {
    $fallback = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&h=100&fit=crop&auto=format';
    if (empty($image_url)) return $fallback;
    if (strpos($image_url, 'http://') === 0 || strpos($image_url, 'https://') === 0 || strpos($image_url, '//') === 0) {
        return $image_url;
    }
    $cleanPath = ltrim($image_url, '/');
    if (strpos($cleanPath, 'uploads/') !== 0) {
        $cleanPath = 'uploads/' . $cleanPath;
    }
    $localPath = __DIR__ . '/../' . $cleanPath;
    if (file_exists($localPath)) {
        return '../' . $cleanPath;
    }
    return $fallback;
}

// Helper to download external or Google Drive images locally and save to uploads folder
function downloadImageFromUrl($url) {
    if (empty($url)) return null;
    
    // Check if it's already a local path
    if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
        return $url;
    }
    
    // Resolve Google Drive links (convert preview/open links to direct download links)
    if (strpos($url, 'drive.google.com') !== false) {
        $file_id = null;
        if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $file_id = $matches[1];
        } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $file_id = $matches[1];
        }
        if ($file_id) {
            $url = "https://lh3.googleusercontent.com/d/" . $file_id;
        }
    }
    
    // Initialize cURL to fetch the image from URL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
    
    $data = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || empty($data)) {
        return $url; // Return original URL as fallback if download fails
    }
    
    // Map mime types to extensions
    $mime_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'image/bmp' => 'bmp',
        'image/x-icon' => 'ico',
        'image/avif' => 'avif',
        'image/heic' => 'heic',
        'image/heif' => 'heif'
    ];
    
    // Get clean mime type
    $cleanMime = strtolower(explode(';', $contentType)[0]);
    if (!isset($mime_map[$cleanMime])) {
        // If content-type is not a supported image format, return the original URL
        return $url;
    }
    
    $ext = $mime_map[$cleanMime];
    $uploadDir = dirname(__DIR__) . '/uploads/menu/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $newFileName = uniqid('url_dish_', true) . '.' . $ext;
    $destPath = $uploadDir . $newFileName;
    
    if (file_put_contents($destPath, $data) !== false) {
        $relPath  = 'uploads/menu/' . $newFileName;
        $repoRoot = dirname(__DIR__);
        $safeMsg  = 'chore: add downloaded menu image ' . $newFileName;
        $gitCheck = shell_exec('git --version 2>&1');
        if ($gitCheck && strpos($gitCheck, 'git version') !== false) {
            shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git add ' . escapeshellarg($relPath) . ' 2>&1');
            shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git commit -m ' . escapeshellarg($safeMsg) . ' --no-verify 2>&1');
            shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git push 2>&1');
        }
        return $relPath;
    }
    
    return $url;
}

// Helper to determine date boundaries for report filtering
function getDateBounds($range, $start_custom = null, $end_custom = null) {
    date_default_timezone_set('Asia/Kolkata');
    $start = new DateTime();
    $end = new DateTime();
    
    switch ($range) {
        case 'today':
            $start->setTime(0, 0, 0);
            $end->setTime(23, 59, 59);
            break;
        case 'yesterday':
            $start->modify('-1 day')->setTime(0, 0, 0);
            $end->modify('-1 day')->setTime(23, 59, 59);
            break;
        case 'thisweek':
            $start->modify('this week')->setTime(0, 0, 0);
            $end->setTime(23, 59, 59);
            break;
        case 'lastweek':
            $start->modify('last week')->setTime(0, 0, 0);
            $end = clone $start;
            $end->modify('+6 days')->setTime(23, 59, 59);
            break;
        case 'thismonth':
            $start->modify('first day of this month')->setTime(0, 0, 0);
            $end->setTime(23, 59, 59);
            break;
        case 'lastmonth':
            $start->modify('first day of last month')->setTime(0, 0, 0);
            $end = clone $start;
            $end->modify('last day of this month')->setTime(23, 59, 59);
            break;
        case 'thisyear':
            $start->setDate(intval(date('Y')), 1, 1)->setTime(0, 0, 0);
            $end->setTime(23, 59, 59);
            break;
        case 'custom':
            if ($start_custom) {
                $start = new DateTime($start_custom . ' 00:00:00');
            }
            if ($end_custom) {
                $end = new DateTime($end_custom . ' 23:59:59');
            }
            break;
    }
    
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

// 1. Sync orders.json to MySQL database
function syncOrdersJsonToDb($pdo) {
    $orders_file = dirname(__DIR__) . '/orders.json';
    if (!file_exists($orders_file)) return;
    
    $json_content = file_get_contents($orders_file);
    $orders = json_decode($json_content, true) ?: [];
    
    foreach ($orders as $order_id => $data) {
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ?");
        $stmt->execute([$order_id]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            $status = strtolower($data['status'] ?? 'pending');
            if ($status === 'paid') {
                $status = 'pending';
            }
            
            $delivery_address = $data['delivery_address'] ?? '';
            $ins_order = $pdo->prepare("INSERT INTO orders (order_number, customer_name, customer_phone, delivery_address, total_amount, order_status, order_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins_order->execute([
                $order_id,
                $data['customer_name'] ?? 'Customer',
                $data['customer_phone'] ?? '',
                $delivery_address,
                $data['total'] ?? 0.00,
                $status,
                $data['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            
            $db_order_id = $pdo->lastInsertId();
            
            $cart_items = $data['cart_items'] ?? [];
            foreach ($cart_items as $item) {
                $f_stmt = $pdo->prepare("SELECT id FROM food_items WHERE name = ?");
                $f_stmt->execute([$item['name']]);
                $f_item = $f_stmt->fetch();
                $food_item_id = $f_item ? $f_item['id'] : null;
                
                $ins_item = $pdo->prepare("INSERT INTO order_items (order_id, food_item_id, item_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
                $ins_item->execute([
                    $db_order_id,
                    $food_item_id,
                    $item['name'],
                    $item['quantity'] ?? 1,
                    $item['price'] ?? 0.00
                ]);
            }
        }
    }
}

// Perform sync
syncOrdersJsonToDb($pdo);

// Ensure career_applications table exists
try {
    $table_sql = "CREATE TABLE IF NOT EXISTS `career_applications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `full_name` VARCHAR(100) NOT NULL,
      `email` VARCHAR(100) NOT NULL,
      `mobile` VARCHAR(20) NOT NULL,
      `position` VARCHAR(50) NOT NULL,
      `experience` INT NOT NULL,
      `city` VARCHAR(100) NOT NULL,
      `expected_salary` DECIMAL(10,2) NOT NULL,
      `resume_path` VARCHAR(255) NOT NULL,
      `cover_letter` TEXT DEFAULT NULL,
      `status` ENUM('Pending', 'Reviewed', 'Shortlisted', 'Rejected') DEFAULT 'Pending',
      `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($table_sql);
} catch (PDOException $e) {
    // Fail silently in dashboard load
}

// 2. Load Settings
$settings_file = __DIR__ . '/settings.json';
$settings = [
    'restaurant_name' => 'Medusa',
    'gst_rate' => 18,
    'packing_charge' => 0.00,
    'opening_hours' => '11:00 AM - 11:00 PM',
    'silver_discount' => 10.00,
    'gold_discount' => 15.00,
    'platinum_discount' => 20.00,
    'gold_threshold' => 25000.00,
    'platinum_threshold' => 75000.00,
    'points_earning_percent' => 2.00,
    'inactivity_months' => 3,
    'inactivity_deduction_percent' => 20.00,
];
if (file_exists($settings_file)) {
    $settings = array_merge($settings, json_decode(file_get_contents($settings_file), true) ?: []);
}
// Override with .env configurations
$settings['restaurant_name'] = get_env_var('RESTAURANT_NAME', $settings['restaurant_name']);
$settings['gst_rate'] = intval(get_env_var('GST_RATE', $settings['gst_rate']));
$settings['opening_hours'] = get_env_var('OPENING_HOURS', $settings['opening_hours']);
$settings['inactivity_months'] = intval(get_env_var('INACTIVITY_MONTHS', $settings['inactivity_months'] ?? 3));



// 3. API Handlers
if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
    
    // Proxy external images locally to resolve CORS and hotlinking restrictions in preview
    if ($action === 'proxy_image') {
        $url = $_GET['url'] ?? '';
        if (empty($url)) {
            http_response_code(400);
            exit;
        }
        
        // Resolve Google Drive links if needed
        if (strpos($url, 'drive.google.com') !== false) {
            $file_id = null;
            if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
                $file_id = $matches[1];
            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
                $file_id = $matches[1];
            }
            if ($file_id) {
                $url = "https://lh3.googleusercontent.com/d/" . $file_id;
            }
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');
        
        $data = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && !empty($data) && strpos($contentType, 'image/') === 0) {
            header("Content-Type: " . $contentType);
            echo $data;
        } else {
            http_response_code(404);
        }
        exit;
    }
    
    header('Content-Type: application/json');
    
    // Get all active user quotas action
    if ($action === 'load_active_quotas') {
        try {
            $stmt = $pdo->query("
                SELECT q.*, u.full_name as user_name, u.email as user_email, u.phone as user_phone 
                FROM user_liquor_quota q 
                JOIN users u ON q.user_id = u.id 
                ORDER BY u.full_name ASC, q.item_name ASC
            ");
            $quotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'quotas' => $quotas]);
        } catch (PDOException $e) {
            error_log('Admin load active quotas error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to load active quotas.']);
        }
        exit;
    }

    // Verify customer to load active liquor brands
    if ($action === 'verify_order_liquor') {
        $search_term = trim($_POST['search_term'] ?? '');
        // strip # from search term if they copy-pasted #ORD-XXX
        $clean_term = ltrim($search_term, '#');

        if (empty($clean_term)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a search term.']);
            exit;
        }

        try {
            $user_id = null;
            // 1. Check if it's an order number
            $stmt = $pdo->prepare("SELECT user_id, customer_phone FROM orders WHERE order_number = ?");
            $stmt->execute([$clean_term]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order) {
                if (!empty($order['user_id'])) {
                    $user_id = $order['user_id'];
                } elseif (!empty($order['customer_phone'])) {
                    // Order has no direct user_id, try linking via phone
                    $stmt_u = $pdo->prepare("SELECT id FROM users WHERE phone LIKE ?");
                    $stmt_u->execute(['%' . $order['customer_phone'] . '%']);
                    $user = $stmt_u->fetch(PDO::FETCH_ASSOC);
                    if ($user) $user_id = $user['id'];
                }
            } 
            
            if (!$user_id) {
                // 2. Check if it matches phone, email, or partial name
                $stmt = $pdo->prepare("SELECT id FROM users WHERE phone LIKE ? OR email = ? OR full_name LIKE ? LIMIT 1");
                $wildcard = "%" . $clean_term . "%";
                $stmt->execute([$wildcard, $clean_term, $wildcard]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $user_id = $user['id'];
                }
            }

            if (!$user_id) {
                echo json_encode(['success' => false, 'message' => 'Customer not found. Please check the spelling or Order ID.']);
                exit;
            }

            // Find ALL active liquor quota for this user
            $stmt_items = $pdo->prepare("
                SELECT food_item_id, item_name, total_pegs 
                FROM user_liquor_quota
                WHERE user_id = ? AND total_pegs > 0
            ");
            $stmt_items->execute([$user_id]);
            $brands = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            if (empty($brands)) {
                echo json_encode(['success' => false, 'message' => 'This customer has no active liquor pegs left.']);
                exit;
            }

            echo json_encode(['success' => true, 'user_id' => $user_id, 'brands' => $brands]);
        } catch (PDOException $e) {
            error_log('Admin verify liquor order error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to verify customer quota.']);
        }
        exit;
    }

    // Admin consume peg action
    if ($action === 'admin_consume_peg') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $food_item_id = intval($_POST['food_item_id'] ?? 0);
        $search_term = trim($_POST['search_term'] ?? '');

        if ($user_id <= 0 || $food_item_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters. Please verify first.']);
            exit;
        }

        try {
            // Fetch current quota
            $stmt = $pdo->prepare("SELECT total_pegs, item_name FROM user_liquor_quota WHERE user_id = ? AND food_item_id = ?");
            $stmt->execute([$user_id, $food_item_id]);
            $quota = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quota) {
                echo json_encode(['success' => false, 'message' => 'No active quota found for this liquor brand.']);
                exit;
            }

            $current_pegs = intval($quota['total_pegs']);
            if ($current_pegs <= 0) {
                echo json_encode(['success' => false, 'message' => 'Peg quota is already fully consumed (0 pegs left) for ' . $quota['item_name'] . '.']);
                exit;
            }

            $new_pegs = $current_pegs - 1;

            // Fetch user full name for notification
            $stmt_user = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmt_user->execute([$user_id]);
            $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $user_name = $user_info ? $user_info['full_name'] : 'Customer';

            // Start transaction
            $pdo->beginTransaction();

            // 1. Decrement user_liquor_quota
            $upd = $pdo->prepare("UPDATE user_liquor_quota SET total_pegs = ? WHERE user_id = ? AND food_item_id = ?");
            $upd->execute([$new_pegs, $user_id, $food_item_id]);

            // 3. Add system notification of consumption
            $notif_title = "Peg Consumed";
            $notif_body = "1 peg of " . $quota['item_name'] . " logged for " . $user_name . " (Verified via: " . $search_term . "). Remaining brand quota: " . $new_pegs . " pegs.";
            
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (type, title, body) VALUES ('system', ?, ?)");
            $stmt_notif->execute([$notif_title, $notif_body]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Successfully logged 1 peg for ' . $user_name . '. ' . $new_pegs . ' pegs remaining.']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin consume peg error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Unable to consume peg quota. Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Upload Dish Image Action
    if ($action === 'upload_dish_image') {
        if (!isset($_FILES['dish_image']) || $_FILES['dish_image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded or error during upload. Code: ' . ($_FILES['dish_image']['error'] ?? 'none')]);
            exit;
        }
        
        $file = $_FILES['dish_image'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'tiff', 'tif', 'ico', 'avif', 'heic', 'heif'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid file extension. Allowed formats: ' . implode(', ', array_map('strtoupper', $allowed_extensions))]);
            exit;
        }
        
        if ($fileSize > 20 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File size is too large (maximum 20MB).']);
            exit;
        }
        
        $uploadDir = dirname(__DIR__) . '/uploads/menu/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $newFileName = uniqid('dish_', true) . '.' . $ext;
        $destPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($fileTmpName, $destPath)) {
            $relativeUrl = 'uploads/menu/' . $newFileName;

            // ── Auto-commit & push uploaded image to git ──────────────────────
            // This ensures other devs get the image on their next `git pull`.
            $repoRoot   = dirname(__DIR__);
            $safeFile   = 'uploads/menu/' . $newFileName;
            $safeMsg    = 'chore: add uploaded menu image ' . $newFileName;
            $gitCheck   = shell_exec('git --version 2>&1');
            if ($gitCheck && strpos($gitCheck, 'git version') !== false) {
                shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git add ' . escapeshellarg($safeFile) . ' 2>&1');
                shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git commit -m ' . escapeshellarg($safeMsg) . ' --no-verify 2>&1');
                shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git push 2>&1');
            }
            // ─────────────────────────────────────────────────────────────────

            echo json_encode(['success' => true, 'image_url' => $relativeUrl]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to write file to disk. Check directory permissions.']);
        }
        exit;
    }

    // Search Orders Endpoint
    if ($action === 'search_orders') {
        $sql = "SELECT *, (SELECT rating FROM feedback WHERE order_number = orders.order_number LIMIT 1) AS rating, (SELECT review FROM feedback WHERE order_number = orders.order_number LIMIT 1) AS review FROM orders WHERE 1=1";
        $params = [];
        
        if (!empty($_POST['search'])) {
            $sql .= " AND (order_number LIKE ? OR customer_name LIKE ? OR customer_phone LIKE ? OR delivery_address LIKE ?)";
            $wildcard = "%" . $_POST['search'] . "%";
            $params[] = $wildcard; $params[] = $wildcard; $params[] = $wildcard; $params[] = $wildcard;
        }
        
        if (!empty($_POST['status']) && $_POST['status'] !== 'all') {
            $sql .= " AND order_status = ?";
            $params[] = $_POST['status'];
        }
        
        if (!empty($_POST['payment_status']) && $_POST['payment_status'] !== 'all') {
            if ($_POST['payment_status'] === 'paid') {
                $sql .= " AND order_status = 'completed'";
            } elseif ($_POST['payment_status'] === 'unpaid') {
                $sql .= " AND order_status != 'completed'";
            }
        }
        
        if (!empty($_POST['type']) && $_POST['type'] !== 'all') {
            if ($_POST['type'] === 'online') {
                $sql .= " AND delivery_address NOT LIKE 'Table %'";
            } elseif ($_POST['type'] === 'dinein') {
                $sql .= " AND delivery_address LIKE 'Table %'";
            }
        }
        
        if (!empty($_POST['date'])) {
            if ($_POST['date'] === 'today') {
                $sql .= " AND DATE(order_date) = CURDATE()";
            } elseif ($_POST['date'] === 'yesterday') {
                $sql .= " AND DATE(order_date) = SUBDATE(CURDATE(), 1)";
            } elseif ($_POST['date'] === '7days') {
                $sql .= " AND order_date >= SUBDATE(NOW(), INTERVAL 7 DAY)";
            } elseif ($_POST['date'] === '30days') {
                $sql .= " AND order_date >= SUBDATE(NOW(), INTERVAL 30 DAY)";
            } elseif ($_POST['date'] === 'custom' && !empty($_POST['start_date']) && !empty($_POST['end_date'])) {
                $sql .= " AND DATE(order_date) BETWEEN ? AND ?";
                $params[] = $_POST['start_date'];
                $params[] = $_POST['end_date'];
            }
        }
        
        if (isset($_POST['min_amount']) && $_POST['min_amount'] !== '') {
            $sql .= " AND total_amount >= ?";
            $params[] = floatval($_POST['min_amount']);
        }
        if (isset($_POST['max_amount']) && $_POST['max_amount'] !== '') {
            $sql .= " AND total_amount <= ?";
            $params[] = floatval($_POST['max_amount']);
        }
        
        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as &$order) {
            $item_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $item_stmt->execute([$order['id']]);
            $order['items'] = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }
    
    // Search Menu Endpoint
    if ($action === 'search_menu') {
        $sql = "SELECT * FROM food_items WHERE 1=1";
        $params = [];
        
        if (!empty($_POST['search'])) {
            $sql .= " AND (name LIKE ? OR category LIKE ? OR subcategory LIKE ? OR description LIKE ?)";
            $wildcard = "%" . $_POST['search'] . "%";
            $params[] = $wildcard; $params[] = $wildcard; $params[] = $wildcard; $params[] = $wildcard;
        }
        
        if (!empty($_POST['category']) && $_POST['category'] !== 'all') {
            $sql .= " AND category = ?";
            $params[] = $_POST['category'];
        }
        
        if (isset($_POST['availability']) && $_POST['availability'] !== 'all') {
            $sql .= " AND is_available = ?";
            $params[] = intval($_POST['availability']);
        }
        
        if (isset($_POST['min_price']) && $_POST['min_price'] !== '') {
            $sql .= " AND price >= ?";
            $params[] = floatval($_POST['min_price']);
        }
        if (isset($_POST['max_price']) && $_POST['max_price'] !== '') {
            $sql .= " AND price <= ?";
            $params[] = floatval($_POST['max_price']);
        }
        
        $sql .= " ORDER BY category, id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Bestsellers list based on quantities
        $best_stmt = $pdo->query("SELECT food_item_id, SUM(quantity) as qty FROM order_items GROUP BY food_item_id ORDER BY qty DESC LIMIT 10");
        $bestsellers = $best_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        
        $filtered = [];
        foreach ($items as $dish) {
            $is_veg = ($dish['diet_type'] === 'veg');
            $is_bestseller = in_array($dish['id'], $bestsellers);
            
            if (!empty($_POST['diet_type']) && $_POST['diet_type'] !== 'all') {
                if ($_POST['diet_type'] === 'veg' && !$is_veg) continue;
                if ($_POST['diet_type'] === 'nonveg' && $is_veg) continue;
            }
            
            if (isset($_POST['bestseller']) && $_POST['bestseller'] === '1' && !$is_bestseller) {
                continue;
            }
            
            $dish['is_veg'] = $is_veg ? 1 : 0;
            $dish['is_bestseller'] = $is_bestseller ? 1 : 0;
            
            $cc = $pdo->prepare("SELECT COUNT(*) FROM dish_customizations WHERE food_item_id = ?");
            $cc->execute([$dish['id']]);
            $dish['cust_count'] = (int)$cc->fetchColumn();
            $dish['display_image_url'] = getDishImage($dish['image_url']);
            
            $filtered[] = $dish;
        }
        
        echo json_encode(['success' => true, 'menu' => $filtered]);
        exit;
    }
    
    // Search Customers Endpoint
    if ($action === 'search_customers') {
        $sql = "SELECT 
                    o.customer_name, 
                    o.customer_phone, 
                    u.email,
                    u.id as customer_id,
                    GROUP_CONCAT(DISTINCT o.delivery_address SEPARATOR ' | ') as addresses,
                    COUNT(o.id) as order_count, 
                    SUM(o.total_amount) as total_spent,
                    MAX(o.order_date) as last_order_date
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                WHERE 1=1";
        $params = [];
        
        if (!empty($_POST['search'])) {
            $sql .= " AND (o.customer_name LIKE ? OR o.customer_phone LIKE ? OR u.email LIKE ?)";
            $wildcard = "%" . $_POST['search'] . "%";
            $params[] = $wildcard; $params[] = $wildcard; $params[] = $wildcard;
        }
        
        $sql .= " GROUP BY o.customer_phone, o.customer_name ORDER BY total_spent DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($customers as &$c) {
            $fav_stmt = $pdo->prepare("SELECT item_name, SUM(quantity) as qty 
                                       FROM order_items 
                                       WHERE order_id IN (SELECT id FROM orders WHERE customer_phone = ? AND customer_name = ?) 
                                       GROUP BY item_name 
                                       ORDER BY qty DESC LIMIT 1");
            $fav_stmt->execute([$c['customer_phone'], $c['customer_name']]);
            $fav = $fav_stmt->fetch(PDO::FETCH_ASSOC);
            $c['favorite_dish'] = $fav ? $fav['item_name'] : 'N/A';
            
            $pay_stmt = $pdo->prepare("SELECT 
                                           SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as paid_count,
                                           SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as failed_count,
                                           SUM(CASE WHEN order_status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as pending_count
                                       FROM orders 
                                       WHERE customer_phone = ? AND customer_name = ?");
            $pay_stmt->execute([$c['customer_phone'], $c['customer_name']]);
            $c['payment_summary'] = $pay_stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'customers' => $customers]);
        exit;
    }
    
    // Search Payments Endpoint
    if ($action === 'search_payments') {
        $sql = "SELECT * FROM orders WHERE 1=1";
        $params = [];
        
        if (!empty($_POST['search'])) {
            $clean_search = ltrim(trim($_POST['search']), '#');
            $sql .= " AND (order_number LIKE ? OR customer_name LIKE ?)";
            $wildcard = "%" . $clean_search . "%";
            $params[] = $wildcard; $params[] = $wildcard;
        }
        
        if (!empty($_POST['method']) && $_POST['method'] !== 'all') {
            if ($_POST['method'] === 'cash') {
                $sql .= " AND (delivery_address LIKE '%Paid via CASH%' OR payment_method = 'cash' OR payment_method = 'cod')";
            } elseif ($_POST['method'] === 'card') {
                $sql .= " AND (delivery_address LIKE '%Paid via CARD%' OR payment_method = 'card')";
            } elseif ($_POST['method'] === 'upi') {
                $sql .= " AND (delivery_address LIKE '%Paid via UPI%' OR payment_method = 'upi')";
            } elseif ($_POST['method'] === 'netbanking') {
                $sql .= " AND (delivery_address LIKE '%Paid via NETBANKING%' OR delivery_address LIKE '%Paid via NET BANKING%' OR payment_method = 'netbanking')";
            } elseif ($_POST['method'] === 'wallet') {
                $sql .= " AND (delivery_address LIKE '%Paid via WALLET%' OR payment_method = 'wallet')";
            } elseif ($_POST['method'] === 'gateway') {
                $sql .= " AND (delivery_address NOT LIKE '%Paid via %' AND (payment_method = 'Online' OR payment_method IS NULL OR payment_method = ''))";
            }
        }
        
        if (!empty($_POST['status']) && $_POST['status'] !== 'all') {
            if ($_POST['status'] === 'success') {
                $sql .= " AND order_status = 'completed'";
            } elseif ($_POST['status'] === 'failed') {
                $sql .= " AND order_status = 'cancelled'";
            } elseif ($_POST['status'] === 'pending') {
                $sql .= " AND order_status IN ('pending', 'preparing', 'ready')";
            }
        }
        
        if (isset($_POST['min_amount']) && $_POST['min_amount'] !== '') {
            $sql .= " AND total_amount >= ?";
            $params[] = floatval($_POST['min_amount']);
        }
        if (isset($_POST['max_amount']) && $_POST['max_amount'] !== '') {
            $sql .= " AND total_amount <= ?";
            $params[] = floatval($_POST['max_amount']);
        }
        
        $sql .= " ORDER BY id DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'payments' => $logs]);
        exit;
    }
    
    // Business Intelligence Analytics Endpoint
    if ($action === 'get_reports_data') {
        $range = $_POST['range'] ?? 'today';
        $start_custom = $_POST['start_date'] ?? null;
        $end_custom = $_POST['end_date'] ?? null;
        
        list($start_date, $end_date) = getDateBounds($range, $start_custom, $end_custom);
        
        // 1. Revenue
        $rev_stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE order_status = 'completed' AND order_date BETWEEN ? AND ?");
        $rev_stmt->execute([$start_date, $end_date]);
        $revenue = floatval($rev_stmt->fetchColumn() ?: 0);
        
        // Growth Calculation
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);
        $diff = $end_ts - $start_ts;
        $prev_start = date('Y-m-d H:i:s', $start_ts - $diff - 1);
        $prev_end = date('Y-m-d H:i:s', $start_ts - 1);
        
        $prev_rev_stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE order_status = 'completed' AND order_date BETWEEN ? AND ?");
        $prev_rev_stmt->execute([$prev_start, $prev_end]);
        $prev_revenue = floatval($prev_rev_stmt->fetchColumn() ?: 0);
        $revenue_growth = $prev_revenue > 0 ? round((($revenue - $prev_revenue) / $prev_revenue) * 100, 1) : 0;
        
        // 2. Orders Analytics
        $ord_stmt = $pdo->prepare("SELECT 
                                      COUNT(*) as total,
                                      SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed,
                                      SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                                      SUM(CASE WHEN order_status IN ('pending', 'preparing', 'ready') THEN 1 ELSE 0 END) as pending,
                                      SUM(CASE WHEN delivery_address NOT LIKE 'Table %' THEN 1 ELSE 0 END) as online,
                                      SUM(CASE WHEN delivery_address LIKE 'Table %' THEN 1 ELSE 0 END) as dinein
                                  FROM orders 
                                  WHERE order_date BETWEEN ? AND ?");
        $ord_stmt->execute([$start_date, $end_date]);
        $ord_metrics = $ord_stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'pending' => 0, 'online' => 0, 'dinein' => 0];
        
        $total_orders = intval($ord_metrics['total']);
        $completed_orders = intval($ord_metrics['completed']);
        $cancelled_orders = intval($ord_metrics['cancelled']);
        $pending_orders = intval($ord_metrics['pending']);
        
        $prev_ord_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_status = 'completed' AND order_date BETWEEN ? AND ?");
        $prev_ord_stmt->execute([$prev_start, $prev_end]);
        $prev_completed_orders = intval($prev_ord_stmt->fetchColumn() ?: 0);
        $orders_growth = $prev_completed_orders > 0 ? round((($completed_orders - $prev_completed_orders) / $prev_completed_orders) * 100, 1) : 0;
        
        $aov = $completed_orders > 0 ? round($revenue / $completed_orders, 2) : 0;
        $prev_aov = $prev_completed_orders > 0 ? round($prev_revenue / $prev_completed_orders, 2) : 0;
        $aov_growth = $prev_aov > 0 ? round((($aov - $prev_aov) / $prev_aov) * 100, 1) : 0;
        
        $acceptance_rate = $total_orders > 0 ? round((($total_orders - $cancelled_orders) / $total_orders) * 100, 1) : 100;
        $completion_rate = ($total_orders - $cancelled_orders) > 0 ? round(($completed_orders / ($total_orders - $cancelled_orders)) * 100, 1) : 100;
        
        // 3. Category Performance
        $cat_stmt = $pdo->prepare("SELECT 
                                       IFNULL(f.category, 'uncategorized') as category_name, 
                                       SUM(oi.quantity) as units_sold, 
                                       SUM(oi.quantity * oi.price) as revenue
                                   FROM order_items oi
                                   JOIN food_items f ON oi.food_item_id = f.id
                                   JOIN orders o ON oi.order_id = o.id
                                   WHERE o.order_status = 'completed' AND o.order_date BETWEEN ? AND ?
                                   GROUP BY f.category
                                   ORDER BY revenue DESC");
        $cat_stmt->execute([$start_date, $end_date]);
        $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Best Selling Dishes
        $dish_stmt = $pdo->prepare("SELECT 
                                        oi.item_name, 
                                        MAX(f.category) as category,
                                        SUM(oi.quantity) as qty_sold, 
                                        SUM(oi.quantity * oi.price) as revenue
                                    FROM order_items oi
                                    LEFT JOIN food_items f ON oi.food_item_id = f.id
                                    JOIN orders o ON oi.order_id = o.id
                                    WHERE o.order_status = 'completed' AND o.order_date BETWEEN ? AND ?
                                    GROUP BY oi.item_name
                                    ORDER BY qty_sold DESC");
        $dish_stmt->execute([$start_date, $end_date]);
        $dishes = $dish_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 5. Payment Breakup
        $pay_stmt = $pdo->prepare("SELECT total_amount, delivery_address, payment_method FROM orders WHERE order_status = 'completed' AND order_date BETWEEN ? AND ?");
        $pay_stmt->execute([$start_date, $end_date]);
        $pay_orders = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $payments = [
            'CASH' => ['amount' => 0, 'count' => 0],
            'UPI' => ['amount' => 0, 'count' => 0],
            'CARD' => ['amount' => 0, 'count' => 0],
            'NET BANKING' => ['amount' => 0, 'count' => 0],
            'WALLET' => ['amount' => 0, 'count' => 0],
            'ONLINE GATEWAY' => ['amount' => 0, 'count' => 0]
        ];
        
        foreach ($pay_orders as $po) {
            $addr = strtoupper($po['delivery_address'] ?? '');
            $method = strtolower($po['payment_method'] ?? '');
            $amt = floatval($po['total_amount']);
            
            if ($method === 'cash' || $method === 'cod' || strpos($addr, 'PAID VIA CASH') !== false) {
                $payments['CASH']['amount'] += $amt; $payments['CASH']['count']++;
            } elseif ($method === 'card' || strpos($addr, 'PAID VIA CARD') !== false) {
                $payments['CARD']['amount'] += $amt; $payments['CARD']['count']++;
            } elseif ($method === 'upi' || strpos($addr, 'PAID VIA UPI') !== false) {
                $payments['UPI']['amount'] += $amt; $payments['UPI']['count']++;
            } elseif ($method === 'netbanking' || strpos($addr, 'PAID VIA NETBANKING') !== false || strpos($addr, 'PAID VIA NET BANKING') !== false) {
                $payments['NET BANKING']['amount'] += $amt; $payments['NET BANKING']['count']++;
            } elseif ($method === 'wallet' || strpos($addr, 'PAID VIA WALLET') !== false) {
                $payments['WALLET']['amount'] += $amt; $payments['WALLET']['count']++;
            } else {
                if (strpos($addr, 'TABLE ') === false) {
                    $payments['ONLINE GATEWAY']['amount'] += $amt; $payments['ONLINE GATEWAY']['count']++;
                } else {
                    $payments['CASH']['amount'] += $amt; $payments['CASH']['count']++;
                }
            }
        }
        
        // 6. Customers Analysis
        $cust_stmt = $pdo->prepare("SELECT COUNT(DISTINCT customer_phone) FROM orders WHERE order_date BETWEEN ? AND ?");
        $cust_stmt->execute([$start_date, $end_date]);
        $total_customers = intval($cust_stmt->fetchColumn() ?: 0);
        
        $new_cust_stmt = $pdo->prepare("SELECT COUNT(*) FROM (
                                            SELECT customer_phone, MIN(order_date) as first_date 
                                            FROM orders 
                                            GROUP BY customer_phone
                                        ) t WHERE first_date BETWEEN ? AND ?");
        $new_cust_stmt->execute([$start_date, $end_date]);
        $new_customers = intval($new_cust_stmt->fetchColumn() ?: 0);
        
        $returning_customers = max(0, $total_customers - $new_customers);
        $retention_rate = $total_customers > 0 ? round(($returning_customers / $total_customers) * 100, 1) : 0;
        
        $top_cust_stmt = $pdo->prepare("SELECT customer_name, customer_phone, COUNT(*) as order_count, SUM(total_amount) as total_spent 
                                        FROM orders 
                                        WHERE order_status = 'completed' AND order_date BETWEEN ? AND ? 
                                        GROUP BY customer_phone, customer_name 
                                        ORDER BY total_spent DESC LIMIT 5");
        $top_cust_stmt->execute([$start_date, $end_date]);
        $top_customers = $top_cust_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // 7. Trend graphs
        $trend_labels = [];
        $trend_data = [];
        
        if ($diff <= 172800) { // Group by hour if <= 2 days
            $tr_stmt = $pdo->prepare("SELECT HOUR(order_date) as hr, SUM(total_amount) as total 
                                      FROM orders 
                                      WHERE order_status = 'completed' AND order_date BETWEEN ? AND ? 
                                      GROUP BY HOUR(order_date) 
                                      ORDER BY hr ASC");
            $tr_stmt->execute([$start_date, $end_date]);
            $raw_trends = $tr_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            for ($h = 0; $h < 24; $h++) {
                $trend_labels[] = sprintf("%02d:00", $h);
                $found = 0;
                foreach ($raw_trends as $rt) {
                    if (intval($rt['hr']) === $h) {
                        $trend_data[] = floatval($rt['total']);
                        $found = 1;
                        break;
                    }
                }
                if (!$found) $trend_data[] = 0;
            }
        } else { // Group by Date
            $tr_stmt = $pdo->prepare("SELECT DATE(order_date) as dt, SUM(total_amount) as total 
                                      FROM orders 
                                      WHERE order_status = 'completed' AND order_date BETWEEN ? AND ? 
                                      GROUP BY DATE(order_date) 
                                      ORDER BY dt ASC");
            $tr_stmt->execute([$start_date, $end_date]);
            $raw_trends = $tr_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($raw_trends as $rt) {
                $trend_labels[] = date('d M Y', strtotime($rt['dt']));
                $trend_data[] = floatval($rt['total']);
            }
        }
        
        // Performance Score
        $perf_score = round(($completion_rate * 0.4) + ($acceptance_rate * 0.4) + (min(max($revenue_growth, -20), 20) + 20) * 0.5, 0);
        $perf_score = min(max($perf_score, 10), 100);
        
        echo json_encode([
            'success' => true,
            'summary' => [
                'revenue' => $revenue,
                'revenue_growth' => $revenue_growth,
                'orders_count' => $completed_orders,
                'orders_growth' => $orders_growth,
                'aov' => $aov,
                'aov_growth' => $aov_growth,
                'total_orders' => $total_orders,
                'online_orders' => intval($ord_metrics['online']),
                'dinein_orders' => intval($ord_metrics['dinein']),
                'cancelled_orders' => $cancelled_orders,
                'pending_orders' => $pending_orders,
                'acceptance_rate' => $acceptance_rate,
                'completion_rate' => $completion_rate,
                'total_customers' => $total_customers,
                'new_customers' => $new_customers,
                'returning_customers' => $returning_customers,
                'retention_rate' => $retention_rate,
                'performance_score' => $perf_score,
                'start_date' => date('d M Y', strtotime($start_date)),
                'end_date' => date('d M Y', strtotime($end_date)),
                'generated_at' => date('d M Y, h:i A')
            ],
            'trend' => [
                'labels' => $trend_labels,
                'data' => $trend_data
            ],
            'categories' => $categories,
            'dishes' => $dishes,
            'payments' => $payments,
            'top_customers' => $top_customers
        ]);
        exit;
    }
    
    // Live Kitchen Polling
    if ($action === 'get_kitchen_orders') {
        $stmt = $pdo->query("SELECT * FROM orders WHERE order_status IN ('pending', 'preparing', 'ready') ORDER BY id ASC");
        $active_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($active_orders as &$order) {
            $item_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $item_stmt->execute([$order['id']]);
            $order['items'] = $item_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'orders' => $active_orders]);
        exit;
    }
    
    // Update Order Status
    if ($action === 'update_order_status') {
        $order_id = $_POST['order_id'];
        $status = $_POST['status'];
        
        // Fetch order details first to determine order_type
        $o_stmt = $pdo->prepare("SELECT order_number, customer_name, total_amount, user_id, delivery_address, order_type FROM orders WHERE id = ?");
        $o_stmt->execute([$order_id]);
        $ord_info = $o_stmt->fetch(PDO::FETCH_ASSOC);

        // Map order_status to tracking_status to keep customer live tracking updated
        $tracking_status = 'placed';
        if ($status === 'preparing') {
            $tracking_status = 'preparing';
        } elseif ($status === 'ready') {
            $tracking_status = (isset($ord_info['order_type']) && $ord_info['order_type'] === 'takeaway') ? 'ready_for_pickup' : 'out_for_delivery';
        } elseif ($status === 'completed') {
            $tracking_status = 'delivered';
        } elseif ($status === 'cancelled') {
            $tracking_status = 'cancelled';
        }
        
        $stmt = $pdo->prepare("UPDATE orders SET order_status = ?, tracking_status = ? WHERE id = ?");
        $stmt->execute([$status, $tracking_status, $order_id]);
        
        if ($ord_info) {
            require_once dirname(__DIR__) . '/includes/notifications_helper.php';
            
            // Admin Notifications
            if ($status === 'completed') {
                addNotification('order', 'Order Completed', "Order {$ord_info['order_number']} for {$ord_info['customer_name']} (₹" . number_format($ord_info['total_amount'], 2) . ") is completed.");
            } elseif ($status === 'cancelled') {
                addNotification('order', 'Order Cancelled', "Order {$ord_info['order_number']} for {$ord_info['customer_name']} has been cancelled.");
            } elseif ($status === 'preparing') {
                addNotification('kitchen', 'Order In Prep', "Order {$ord_info['order_number']} is now being prepared in the kitchen.");
            }
            
            // Customer Notifications
            $c_user_id = $ord_info['user_id'];
            if ($c_user_id) {
                $is_delivery = (stripos($ord_info['delivery_address'] ?? '', 'table') === false);
                if ($is_delivery) {
                    $cust_title = '';
                    $cust_msg = '';
                    if ($status === 'ready') {
                        $cust_title = 'Order Arriving';
                        $cust_msg = "Your order #{$ord_info['order_number']} is out for delivery and arriving soon.";
                    } elseif ($status === 'completed') {
                        $cust_title = 'Order Delivered';
                        $cust_msg = "Your order #{$ord_info['order_number']} has been successfully delivered. Enjoy your meal!";
                    }
                    
                    if ($cust_title) {
                        $ins_cust_notif = $pdo->prepare("INSERT INTO user_notifications (user_id, title, message) VALUES (?, ?, ?)");
                        $ins_cust_notif->execute([$c_user_id, $cust_title, $cust_msg]);
                    }
                }
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    // Toggle Menu Item Availability
    if ($action === 'toggle_menu_item') {
        $item_id = $_POST['id'];
        $val = intval($_POST['val']);
        
        $stmt = $pdo->prepare("UPDATE food_items SET is_available = ? WHERE id = ?");
        $stmt->execute([$val, $item_id]);
        
        if ($val === 0) {
            $f_stmt = $pdo->prepare("SELECT name FROM food_items WHERE id = ?");
            $f_stmt->execute([$item_id]);
            $item_name = $f_stmt->fetchColumn();
            if ($item_name) {
                require_once dirname(__DIR__) . '/includes/notifications_helper.php';
                addNotification('kitchen', 'Item Out of Stock', "Food item \"{$item_name}\" has been marked as Out of Stock.");
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Add Menu Item
    if ($action === 'add_menu_item') {
        $name = $_POST['name'];
        $desc = $_POST['description'];
        $price = $_POST['price'];
        $category = $_POST['category'];
        $subcategory = $_POST['subcategory'] ?: null;
        $image_url = $_POST['image_url'] ?: 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&h=300&fit=crop';
        $diet_type = $_POST['diet_type'] ?? 'veg';
        
        $image_url = downloadImageFromUrl($image_url);
        
        $stmt = $pdo->prepare("INSERT INTO food_items (name, description, price, category, subcategory, image_url, diet_type, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$name, $desc, $price, $category, $subcategory, $image_url, $diet_type]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    // Edit Menu Item
    if ($action === 'edit_menu_item') {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $desc = $_POST['description'];
        $price = $_POST['price'];
        $category = $_POST['category'];
        $subcategory = $_POST['subcategory'] ?: null;
        $image_url = $_POST['image_url'];
        $diet_type = $_POST['diet_type'] ?? 'veg';
        
        $image_url = downloadImageFromUrl($image_url);
        
        $stmt = $pdo->prepare("UPDATE food_items SET name = ?, description = ?, price = ?, category = ?, subcategory = ?, image_url = ?, diet_type = ? WHERE id = ?");
        $stmt->execute([$name, $desc, $price, $category, $subcategory, $image_url, $diet_type, $id]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    // Delete Menu Item
    if ($action === 'delete_menu_item') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM food_items WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    // Update Menu Order (Drag and Drop)
    if ($action === 'update_menu_order') {
        $order = json_decode($_POST['order'] ?? '[]');
        if (is_array($order) && count($order) > 0) {
            $pdo->beginTransaction();
            try {
                // Fetch the current sort_orders for the items being reordered
                $placeholders = implode(',', array_fill(0, count($order), '?'));
                $stmt = $pdo->prepare("SELECT id, sort_order FROM food_items WHERE id IN ($placeholders) ORDER BY sort_order ASC, id ASC");
                $stmt->execute($order);
                $current_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Map the original sort_orders to the new order sequence
                $original_sort_orders = array_column($current_items, 'sort_order');
                
                $update_stmt = $pdo->prepare("UPDATE food_items SET sort_order = ? WHERE id = ?");
                foreach ($order as $index => $id) {
                    $new_sort_order = isset($original_sort_orders[$index]) ? $original_sort_orders[$index] : $index;
                    $update_stmt->execute([$new_sort_order, intval($id)]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or empty order data']);
        }
        exit;
    }
    
    // Save categories to categories.json
    if ($action === 'save_categories') {
        $categoriesData = json_decode($_POST['categories'] ?? '[]', true);
        if (is_array($categoriesData)) {
            $clean_categories = [];
            $pdo->beginTransaction();
            try {
                foreach ($categoriesData as $item) {
                    if (!is_array($item)) continue;
                    $original = trim($item['original'] ?? '');
                    $current = trim($item['current'] ?? '');
                    
                    if ($current === '') continue;
                    
                    // If it was renamed, update the database items
                    if ($original !== '' && $original !== $current) {
                        $stmt = $pdo->prepare("UPDATE food_items SET category = ? WHERE category = ?");
                        $stmt->execute([$current, $original]);
                    }
                    
                    $clean_categories[] = $current;
                }
                
                $clean_categories = array_values(array_unique($clean_categories));
                
                $cat_file = __DIR__ . '/categories.json';
                if (file_put_contents($cat_file, json_encode($clean_categories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))) {
                    $pdo->commit();
                    echo json_encode(['success' => true]);
                } else {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Failed to write categories file']);
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid categories data']);
        }
        exit;
    }

    // Get categories from categories.json
    if ($action === 'get_categories') {
        $cat_file = __DIR__ . '/categories.json';
        $cats = file_exists($cat_file) ? json_decode(file_get_contents($cat_file), true) : [];
        echo json_encode(['success' => true, 'categories' => $cats]);
        exit;
    }

    // Check category usage in dishes
    if ($action === 'check_category_usage') {
        $category = $_REQUEST['category'] ?? '';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM food_items WHERE category = ?");
        $stmt->execute([$category]);
        $count = (int)$stmt->fetchColumn();
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    // Get dishes by category (for sorting/reordering)
    if ($action === 'get_dishes_by_category') {
        $category = $_REQUEST['category'] ?? '';
        if ($category === '') {
            $stmt = $pdo->query("SELECT id, name, price, image_url, category, sort_order, diet_type FROM food_items ORDER BY sort_order ASC, id ASC");
        } else {
            $stmt = $pdo->prepare("SELECT id, name, price, image_url, category, sort_order, diet_type FROM food_items WHERE category = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$category]);
        }
        $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'dishes' => $dishes]);
        exit;
    }
    
    // Settle dine-in bill
    if ($action === 'settle_bill') {
        $order_id = $_POST['order_id'];
        $payment_method = $_POST['payment_method']; // cash, upi, card
        
        // Append payment method details in the database order status or message and set tracking_status to delivered
        $stmt = $pdo->prepare("UPDATE orders SET order_status = 'completed', tracking_status = 'delivered', delivery_address = CONCAT(delivery_address, ' [Paid via ', ?, ']') WHERE id = ?");
        $stmt->execute([strtoupper($payment_method), $order_id]);
        
        // Fetch order details for notification
        $o_stmt = $pdo->prepare("SELECT order_number, customer_name, total_amount FROM orders WHERE id = ?");
        $o_stmt->execute([$order_id]);
        $ord_info = $o_stmt->fetch(PDO::FETCH_ASSOC);
        if ($ord_info) {
            require_once dirname(__DIR__) . '/includes/notifications_helper.php';
            // Order completed notification
            addNotification('order', 'Order Completed', "Dine-in order {$ord_info['order_number']} for {$ord_info['customer_name']} is settled.");
            // Payment notification
            addNotification('payment', 'Payment Successful', "Payment of ₹" . number_format($ord_info['total_amount'], 2) . " received via " . strtoupper($payment_method) . " for order {$ord_info['order_number']}.");
        }
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Add item to active Dine-In Table Order
    if ($action === 'add_table_item') {
        $order_id = $_POST['order_id'];
        $food_id = $_POST['food_item_id'];
        $qty = intval($_POST['quantity']);
        
        $f_stmt = $pdo->prepare("SELECT * FROM food_items WHERE id = ?");
        $f_stmt->execute([$food_id]);
        $food = $f_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($food) {
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, food_item_id, item_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $food_id, $food['name'], $qty, $food['price']]);
            
            // Recalculate order total amount
            $total_stmt = $pdo->prepare("SELECT SUM(quantity * price) FROM order_items WHERE order_id = ?");
            $total_stmt->execute([$order_id]);
            $new_total = $total_stmt->fetchColumn();
            
            $up_stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $up_stmt->execute([$new_total, $order_id]);
            
            echo json_encode(['success' => true, 'new_total' => $new_total]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Food item not found']);
        }
        exit;
    }

    // Create new dine-in table order
    if ($action === 'create_dinein_order') {
        $table_code = $_POST['table_code'];
        $cust_name = $_POST['customer_name'] ?: 'Guest';
        
        $order_number = 'ORD-' . strtoupper(substr(uniqid(), 7, 5));
        
        $stmt = $pdo->prepare("INSERT INTO orders (order_number, customer_name, delivery_address, total_amount, order_status) VALUES (?, ?, ?, 0.00, 'pending')");
        $stmt->execute([$order_number, $cust_name, "Table " . $table_code]);
        
        echo json_encode(['success' => true, 'order_id' => $pdo->lastInsertId()]);
        exit;
    }

    // Save Settings
    if ($action === 'save_settings') {
        $settings = [
            'restaurant_name' => $_POST['restaurant_name'] ?? 'Medusa',
            'gst_rate' => intval($_POST['gst_rate'] ?? 18),
            'packing_charge' => floatval($_POST['packing_charge'] ?? 0.00),
            'opening_hours' => $_POST['opening_hours'] ?? '11:00 AM - 11:00 PM',
            'bronze_discount' => floatval($_POST['bronze_discount'] ?? 10.00),
            'silver_threshold' => floatval($_POST['silver_threshold'] ?? 25000.00),
            'silver_discount' => floatval($_POST['silver_discount'] ?? 15.00),
            'gold_threshold' => floatval($_POST['gold_threshold'] ?? 75000.00),
            'gold_discount' => floatval($_POST['gold_discount'] ?? 20.00),
            'platinum_threshold' => floatval($_POST['platinum_threshold'] ?? 150000.00),
            'platinum_discount' => floatval($_POST['platinum_discount'] ?? 25.00),
            'points_earning_percent' => floatval($_POST['points_earning_percent'] ?? 2.00),
            'inactivity_months' => intval($_POST['inactivity_months'] ?? 3),
            'inactivity_deduction_percent' => floatval($_POST['inactivity_deduction_percent'] ?? 20.00),
            'last_annual_reset_year' => $settings['last_annual_reset_year'] ?? date('Y')
        ];
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));

        // Sync to customer_tiers table
        try {
            // Update Bronze (ID 1)
            $stmt1 = $pdo->prepare("UPDATE customer_tiers SET discount_percent = ?, points_earning_percent = ? WHERE id = 1");
            $stmt1->execute([$settings['bronze_discount'], $settings['points_earning_percent']]);

            // Update Silver (ID 2)
            $stmt2 = $pdo->prepare("UPDATE customer_tiers SET discount_percent = ?, spending_requirement = ?, points_earning_percent = ? WHERE id = 2");
            $stmt2->execute([$settings['silver_discount'], $settings['silver_threshold'], $settings['points_earning_percent']]);

            // Update Gold (ID 3)
            $stmt3 = $pdo->prepare("UPDATE customer_tiers SET discount_percent = ?, spending_requirement = ?, points_earning_percent = ? WHERE id = 3");
            $stmt3->execute([$settings['gold_discount'], $settings['gold_threshold'], $settings['points_earning_percent']]);
            
            // Update Platinum (ID 4)
            $stmt4 = $pdo->prepare("UPDATE customer_tiers SET discount_percent = ?, spending_requirement = ?, points_earning_percent = ? WHERE id = 4");
            $stmt4->execute([$settings['platinum_discount'], $settings['platinum_threshold'], $settings['points_earning_percent']]);
        } catch (PDOException $db_err) {
            // Fail silently or log
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // Get Career Applications Endpoint
    if ($action === 'get_career_applications') {
        $sql = "SELECT * FROM career_applications WHERE 1=1";
        $params = [];
        
        if (!empty($_POST['search'])) {
            $sql .= " AND (full_name LIKE ? OR email LIKE ? OR mobile LIKE ? OR city LIKE ?)";
            $wildcard = "%" . $_POST['search'] . "%";
            $params[] = $wildcard; $params[] = $wildcard; $params[] = $wildcard; $params[] = $wildcard;
        }
        
        if (!empty($_POST['position']) && $_POST['position'] !== 'all') {
            $sql .= " AND position = ?";
            $params[] = $_POST['position'];
        }
        
        if (!empty($_POST['status']) && $_POST['status'] !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $_POST['status'];
        }
        
        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch counters for applications tab summary
        $c_total = $pdo->query("SELECT COUNT(*) FROM career_applications")->fetchColumn() ?: 0;
        $c_pending = $pdo->query("SELECT COUNT(*) FROM career_applications WHERE status = 'Pending'")->fetchColumn() ?: 0;
        $c_shortlisted = $pdo->query("SELECT COUNT(*) FROM career_applications WHERE status = 'Shortlisted'")->fetchColumn() ?: 0;
        $c_rejected = $pdo->query("SELECT COUNT(*) FROM career_applications WHERE status = 'Rejected'")->fetchColumn() ?: 0;
        
        echo json_encode([
            'success' => true, 
            'applications' => $applications,
            'summary' => [
                'total' => $c_total,
                'pending' => $c_pending,
                'shortlisted' => $c_shortlisted,
                'rejected' => $c_rejected
            ]
        ]);
        exit;
    }
    
    // --- CAMPAIGNS API START ---
    if ($action === 'get_campaigns') {
        $stmt = $pdo->query("SELECT * FROM campaigns ORDER BY created_at DESC");
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'campaigns' => $campaigns]);
        exit;
    }

    if ($action === 'create_campaign') {
        $code = strtoupper(trim($_POST['code']));
        $type = $_POST['discount_type'];
        $val = floatval($_POST['discount_value']);
        $min = floatval($_POST['min_order_value'] ?? 0);
        $max = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
        $exp = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO campaigns (campaign_code, discount_type, discount_value, min_order_value, max_discount, expiry_date, usage_limit) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $type, $val, $min, $max, $exp, $limit]);
            echo json_encode(['success' => true, 'message' => 'Campaign created successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to create campaign (may already exist)']);
        }
        exit;
    }

    if ($action === 'update_campaign') {
        $id = intval($_POST['id']);
        $type = $_POST['discount_type'];
        $val = floatval($_POST['discount_value']);
        $min = floatval($_POST['min_order_value'] ?? 0);
        $max = !empty($_POST['max_discount']) ? floatval($_POST['max_discount']) : null;
        $exp = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        
        $stmt = $pdo->prepare("UPDATE campaigns SET discount_type=?, discount_value=?, min_order_value=?, max_discount=?, expiry_date=?, usage_limit=? WHERE id=?");
        $stmt->execute([$type, $val, $min, $max, $exp, $limit, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Campaign updated successfully']);
        exit;
    }

    if ($action === 'delete_campaign') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully']);
        exit;
    }
    
    if ($action === 'toggle_campaign') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("UPDATE campaigns SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Campaign status toggled']);
        exit;
    }
    // --- CAMPAIGNS API END ---

    // Delete Career Application Endpoint
    if ($action === 'delete_career_application') {
        $app_id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM career_applications WHERE id = ?");
        $stmt->execute([$app_id]);
        echo json_encode(['success' => true, 'message' => 'Application deleted successfully']);
        exit;
    }

    // Update Career Application Status Endpoint
    if ($action === 'update_career_status') {
        $app_id = intval($_POST['id']);
        $status = $_POST['status']; // Reviewed, Shortlisted, Rejected
        
        $stmt = $pdo->prepare("UPDATE career_applications SET status = ? WHERE id = ?");
        $stmt->execute([$status, $app_id]);
        
        echo json_encode(['success' => true, 'message' => 'Application status updated to ' . $status]);
        exit;
    }
}

// 4. Fetch Core Metrics for UI
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0;
$total_revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE order_status = 'completed'")->fetchColumn() ?: 0;
$online_orders_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE delivery_address NOT LIKE 'Table %'")->fetchColumn() ?: 0;
$today_sales = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE DATE(order_date) = CURDATE() AND order_status = 'completed'")->fetchColumn() ?: 0;

$top_selling_dish = $pdo->query("SELECT item_name, SUM(quantity) as qty FROM order_items GROUP BY item_name ORDER BY qty DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$top_dish_name = $top_selling_dish ? $top_selling_dish['item_name'] : 'N/A';

// Fetch tables with active orders
$active_order_stmt = $pdo->query("SELECT delivery_address FROM orders WHERE order_status IN ('pending', 'preparing', 'ready')");
$occupied_tables = [];
while ($row = $active_order_stmt->fetch(PDO::FETCH_ASSOC)) {
    // extract Table code (e.g. Table T01 -> T01)
    if (preg_match('/Table\s+([A-Za-z0-9]+)/i', $row['delivery_address'], $matches)) {
        $occupied_tables[] = trim($matches[1]);
    }
}
$active_tables_count = count(array_unique($occupied_tables));

// 7-day Sales Chart Query
$chart_stmt = $pdo->query("SELECT * FROM (SELECT DATE(order_date) as d, SUM(total_amount) as total FROM orders WHERE order_status = 'completed' GROUP BY DATE(order_date) ORDER BY DATE(order_date) DESC LIMIT 7) as recent_days ORDER BY d ASC");
$chart_labels = [];
$chart_data = [];
while ($row = $chart_stmt->fetch(PDO::FETCH_ASSOC)) {
    $chart_labels[] = date('d M', strtotime($row['d']));
    $chart_data[] = floatval($row['total']);
}
if (empty($chart_data)) {
    $chart_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $chart_data = [0, 0, 0, 0, 0, 0, 0];
}

// 10 Recent Orders
$recent_orders = $pdo->query("SELECT *, (SELECT rating FROM feedback WHERE order_number = orders.order_number LIMIT 1) AS rating, (SELECT review FROM feedback WHERE order_number = orders.order_number LIMIT 1) AS review FROM orders ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// Full Menu List
$menu_list = $pdo->query("SELECT * FROM food_items ORDER BY category, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Customers list
$customer_list_raw = $pdo->query("SELECT 
                                     o.customer_name, 
                                     o.customer_phone, 
                                     u.email,
                                     u.id as customer_id,
                                     COUNT(o.id) as order_count, 
                                     SUM(o.total_amount) as total_spent,
                                     MAX(o.order_date) as last_order_date
                                 FROM orders o
                                 LEFT JOIN users u ON o.user_id = u.id
                                 GROUP BY o.customer_phone, o.customer_name 
                                 ORDER BY total_spent DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
$customer_list = [];
foreach ($customer_list_raw as $c) {
    // Favorite dish
    $fav_stmt = $pdo->prepare("SELECT item_name, SUM(quantity) as qty FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE customer_phone = ? AND customer_name = ?) GROUP BY item_name ORDER BY qty DESC LIMIT 1");
    $fav_stmt->execute([$c['customer_phone'], $c['customer_name']]);
    $fav = $fav_stmt->fetch(PDO::FETCH_ASSOC);
    $c['favorite_dish'] = $fav ? $fav['item_name'] : 'N/A';
    
    // Payment summary
    $pay_stmt = $pdo->prepare("SELECT 
                                   SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as paid_count,
                                   SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as failed_count,
                                   SUM(CASE WHEN order_status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as pending_count
                               FROM orders 
                               WHERE customer_phone = ? AND customer_name = ?");
    $pay_stmt->execute([$c['customer_phone'], $c['customer_name']]);
    $c['payment_summary'] = $pay_stmt->fetch(PDO::FETCH_ASSOC);
    
    $customer_list[] = $c;
}

// All Tables Definition matching book-table-test.html
$table_zones = [
    'VIP Area' => ['T01','T02','T03','T04','T05','T06','T07','T08','R1','R2'],
    'Indoor Dining' => ['A01','A02','A03','A04','B01','B02','B03','B04','RD1','RD2','RD3','C01','C02','C03','C04'],
    'Outdoor / Garden' => ['G01','G02','G03','G04','G05','G06','G07','G08'],
    'Booth Seating' => ['F01','F02','F03','F04','F05','F06'],
    'Banquet & Communal' => ['L01','L02','BQ1','BQ2']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['restaurant_name']); ?> - Luxury Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- PDF Export (html2pdf) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <!-- Excel Export (SheetJS) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        (function() {
            // Theme check
            const theme = localStorage.getItem('medusa_admin_theme');
            if (theme === 'light') {
                document.documentElement.classList.add('light-mode');
            }

            // Tab check (flicker prevention)
            const activeTab = localStorage.getItem('medusa_active_admin_tab');
            if (activeTab && activeTab !== 'dashboard-tab') {
                const style = document.createElement('style');
                style.id = 'temp-tab-css';
                style.innerHTML = `
                    #dashboard-tab.tab-panel.active { display: none !important; }
                    #${activeTab}.tab-panel { display: block !important; }
                    .sidebar-link[onclick*="dashboard-tab"] { background-color: transparent !important; color: var(--gray) !important; }
                    .sidebar-link[onclick*="${activeTab}"] { color: var(--gold) !important; background-color: rgba(223, 186, 134, 0.08) !important; }
                `;
                document.head.appendChild(style);
            }
        })();
    </script>
    
    <style>
        :root {
            --bg-dark: #0a0a0a;
            --bg-secondary: #121111;
            --gold: #dfba86;
            --gold-light: #e6c89f;
            --white: #f3f3f3;
            --gray: #a09f9f;
            --gray-dark: #222222;
            --success-color: #2ec4b6;
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            --radius: 16px;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--white);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background-color: var(--bg-secondary);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .sidebar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--gold);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--gray);
            padding: 0.8rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .sidebar-link:hover, .sidebar-link.active {
            color: var(--gold);
            background-color: rgba(223, 186, 134, 0.08);
        }

        /* Main Content wrapper */
        .main-content {
            margin-left: 260px;
            padding: 2.5rem;
            min-height: 100vh;
        }

        /* Header block */
        .page-header {
            margin-bottom: 2.5rem;
        }

        .page-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: #ffffff;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* Metric Cards */
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .metric-card {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .metric-info h5 {
            font-size: 0.85rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .metric-info .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ffffff;
        }

        .metric-icon {
            font-size: 2rem;
            color: var(--gold);
            background-color: rgba(223, 186, 134, 0.05);
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Content Blocks */
        .content-card {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
        }

        .card-header-premium {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.8rem;
        }

        /* Custom Table Styling */
        .premium-table {
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.05);
        }

        .premium-table th {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 0.8rem;
            background: transparent;
        }

        .premium-table td {
            padding: 1rem 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            vertical-align: middle;
            background: transparent;
            color: #ffffff !important; /* Force high-contrast text color for cell readability */
        }

        .premium-table td strong {
            color: #ffffff !important;
        }

        .premium-table td .text-muted {
            color: #b2bec3 !important; /* Brighter gray for secondary information to keep it readable */
        }

        .text-gold {
            color: var(--gold) !important;
        }

        /* Badges */
        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .status-pending { background-color: rgba(223, 186, 134, 0.1); color: var(--gold-light); }
        .status-preparing { background-color: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .status-ready { background-color: rgba(46, 196, 182, 0.1); color: var(--success-color); }
        .status-completed { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .status-cancelled { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }

        /* Tabs Panels */
        .tab-panel {
            display: none;
        }
        .tab-panel.active {
            display: block;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Dine-In Tables Visual Grid */
        .tables-zone-box {
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(255, 255, 255, 0.01);
            border-radius: 12px;
            padding: 1.5rem;
        }
        .zone-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.2rem;
            color: var(--gold-light);
            margin-bottom: 1rem;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
        }
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 1rem;
        }
        .table-cell {
            background-color: var(--bg-dark);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }
        .table-cell:hover {
            border-color: var(--gold);
            transform: translateY(-2px);
        }
        .table-cell.occupied {
            background-color: rgba(223, 186, 134, 0.04);
            border-color: var(--gold);
        }
        .table-cell .table-name {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 3px;
        }
        .table-cell .table-status {
            font-size: 0.72rem;
            font-weight: 500;
            color: var(--gray);
        }
        .table-cell.occupied .table-status {
            color: var(--gold);
        }
        
        /* Kitchen layout */
        .kitchen-columns {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
        }
        .kitchen-col {
            background-color: rgba(255, 255, 255, 0.01);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            padding: 1.2rem;
            min-height: 400px;
        }
        .kitchen-col-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            color: #ffffff;
            margin-bottom: 1.2rem;
            border-bottom: 1.5px solid var(--gold);
            padding-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .kitchen-card {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: var(--transition);
        }
        .kitchen-card:hover {
            border-color: rgba(223, 186, 134, 0.3);
        }
        .kitchen-card-header {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.4rem;
        }
        .kitchen-card-items {
            list-style: none;
            padding: 0;
            margin: 0 0 0.8rem 0;
            font-size: 0.82rem;
            color: var(--text-secondary, #b0b8c8);
        }
        .kitchen-card-items li {
            padding: 0.2rem 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.02);
        }
        .kitchen-card-items li:last-child {
            border-bottom: none;
        }

        /* Kitchen column badge colors */
        .bg-gold {
            background-color: var(--gold) !important;
        }
        .count-badge-pending {
            background-color: var(--gold);
            color: #1a1200;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 0.28em 0.65em;
            border-radius: 20px;
            min-width: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .count-badge-cooking {
            background-color: #2196F3;
            color: #fff;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 0.28em 0.65em;
            border-radius: 20px;
            min-width: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .count-badge-ready {
            background-color: #4CAF50;
            color: #fff;
            font-weight: 700;
            font-size: 0.78rem;
            padding: 0.28em 0.65em;
            border-radius: 20px;
            min-width: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Settings CSS */
        .form-control-dashboard {
            background-color: var(--bg-dark) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            padding: 0.75rem !important;
        }
        .form-control-dashboard:focus {
            border-color: var(--gold) !important;
            box-shadow: none !important;
        }

        /* Premium Search Bar Custom Layout */
        .premium-search-group {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .premium-search-group .search-icon {
            position: absolute;
            left: 1rem;
            color: var(--gold);
            z-index: 5;
            pointer-events: none;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        .premium-search-group .form-control-dashboard {
            padding-left: 2.6rem !important;
            width: 100%;
        }
        .premium-search-group:focus-within .search-icon {
            color: var(--gold-light);
            transform: scale(1.1);
        }

        .btn-gold-action {
            background-color: var(--gold);
            color: #000000;
            font-weight: 700;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: var(--transition);
        }
        .btn-gold-action:hover {
            background-color: var(--gold-light);
            transform: translateY(-1px);
        }


        /* QR block */
        .qr-card-view {
            text-align: center;
            background-color: var(--bg-secondary);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: var(--transition);
        }
        .qr-card-view:hover {
            border-color: var(--gold);
        }
        .qr-title-text {
            font-weight: bold;
            color: #ffffff;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .qr-sub {
            color: var(--gray);
            font-size: 0.75rem;
            margin-bottom: 1rem;
        }

        /* Orders tab active border-bottom styling */
        #orderTabNav .nav-link.active {
            border-bottom: 2px solid var(--gold) !important;
        }

        /* LIGHT MODE OVERRIDES */
        html.light-mode body {
            --bg-dark: #f8f9fc;
            --bg-secondary: #ffffff;
            --white: #1e293b;
            --gray: #64748b;
            --gray-dark: #cbd5e1;
        }

        html.light-mode .sidebar {
            background-color: var(--bg-secondary);
            border-right: 1px solid rgba(0, 0, 0, 0.08);
        }

        html.light-mode .sidebar-link {
            color: var(--gray);
        }

        html.light-mode .sidebar-link:hover, html.light-mode .sidebar-link.active {
            color: var(--gold);
            background-color: rgba(223, 186, 134, 0.12);
        }

        html.light-mode .page-title,
        html.light-mode .card-header-premium,
        html.light-mode .qr-title-text,
        html.light-mode .metric-info .value,
        html.light-mode .premium-table,
        html.light-mode .premium-table td,
        html.light-mode .premium-table td strong {
            color: var(--white) !important;
        }

        html.light-mode .kitchen-col-title {
            color: #1a1a2e !important;
        }

        html.light-mode .kitchen-card-items {
            color: #374151 !important;
        }

        html.light-mode .kitchen-card-header {
            color: #1a1a2e !important;
        }

        html.light-mode .text-white {
            color: var(--white) !important;
        }

        html.light-mode .bg-dark {
            background-color: var(--bg-secondary) !important;
        }

        html.light-mode .list-group-item.bg-dark {
            background-color: var(--bg-secondary) !important;
            color: var(--white) !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
        }

        html.light-mode .list-group-item.bg-dark:hover {
            background-color: rgba(223, 186, 134, 0.08) !important;
        }

        html.light-mode .content-card,
        html.light-mode .metric-card,
        html.light-mode .qr-card-view,
        html.light-mode .kitchen-col,
        html.light-mode .kitchen-card,
        html.light-mode .tables-zone-box {
            background-color: var(--bg-secondary);
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        }

        html.light-mode .table-cell {
            background-color: #f1f5f9;
            border-color: rgba(0, 0, 0, 0.08);
        }

        html.light-mode .table-cell.occupied {
            background-color: rgba(223, 186, 134, 0.1);
            border-color: var(--gold);
        }

        html.light-mode .table-cell .table-name {
            color: var(--white);
        }

        html.light-mode .premium-table th {
            color: var(--gray);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        html.light-mode .premium-table td {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        html.light-mode .form-control-dashboard,
        html.light-mode .form-control,
        html.light-mode .form-select {
            background-color: #f1f5f9 !important;
            color: var(--white) !important;
            border: 1.5px solid rgba(0, 0, 0, 0.08) !important;
        }

        html.light-mode .form-control-dashboard:focus,
        html.light-mode .form-control:focus,
        html.light-mode .form-select:focus {
            border-color: var(--gold) !important;
            background-color: #ffffff !important;
        }

        html.light-mode .modal-content {
            background-color: var(--bg-secondary) !important;
            color: var(--white) !important;
            border: 1px solid rgba(0, 0, 0, 0.1) !important;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.15) !important;
        }

        html.light-mode .btn-close {
            filter: invert(1) grayscale(100%) brightness(0%);
        }

        html.light-mode .btn-outline-light {
            border-color: #cbd5e1;
            color: #475569;
        }

        html.light-mode .btn-outline-light:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }

        html.light-mode .status-pending {
            background-color: rgba(223, 186, 134, 0.15);
        }

        html.light-mode .status-preparing {
            background-color: rgba(59, 130, 246, 0.15);
        }

        html.light-mode .status-ready {
            background-color: rgba(46, 196, 182, 0.15);
        }

        html.light-mode .status-completed {
            background-color: rgba(40, 167, 69, 0.15);
        }

        html.light-mode .status-cancelled {
            background-color: rgba(220, 53, 69, 0.15);
        }

        /* Smooth Transition */
        body, 
        .sidebar, 
        .sidebar-link, 
        .main-content, 
        .content-card, 
        .metric-card, 
        .kitchen-col, 
        .kitchen-card, 
        .table-cell, 
        .form-control, 
        .form-select, 
        .form-control-dashboard,
        .premium-table td,
        .page-title,
        .card-header-premium {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
    

/* ===== CHATGPT FINAL UI FIX ===== */
.sidebar{
    transition:all .3s ease;
    width:260px;
    height: 100vh;
    overflow-y: auto;
}
.sidebar::-webkit-scrollbar {
    width: 6px;
}
.sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(223, 186, 134, 0.4);
    border-radius: 4px;
}
.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(223, 186, 134, 0.8);
}
.sidebar.collapsed{
    width:80px;
    padding-left:.8rem;
    padding-right:.8rem;
}
.sidebar.collapsed .sidebar-brand span,
.sidebar.collapsed .sidebar-link span{
    display:none;
}
.sidebar.collapsed .sidebar-link,
.sidebar.collapsed .sidebar-brand {
    justify-content:center;
}
.main-content{
    transition:all .3s ease;
}
.main-content.expanded{
    margin-left:80px!important;
}

/* Sidebar toggle: fixed on viewport */
  #sidebarToggle{
      position: fixed;
      top: 2rem;
      left: 215px;
      z-index: 2000;
      width:36px;
      height:36px;
      display: flex;
      align-items:center;
      justify-content:center;
      background: var(--bg-secondary);
      border: 1px solid rgba(255,255,255,0.08);
      color: #94a3b8;
      border-radius: 8px;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
  #sidebarToggle .mobile-menu-icon,
  #sidebarToggle .mobile-close-icon {
      display: none;
  }
  #sidebarToggle .desktop-toggle-icon {
      display: block;
  }
  #sidebarToggle i {
      transition: transform 0.3s ease;
  }
  #sidebarToggle.closed .desktop-toggle-icon {
      transform: rotate(180deg);
  }
  #sidebarToggle:hover{
      background: rgba(255,255,255,0.05);
      color: #fff;
      border-color: rgba(255,255,255,0.15);
  }
  .sidebar-collapsed #sidebarToggle {
      left: 22px;
      opacity: 0;
      pointer-events: none;
      visibility: hidden;
  }
  .sidebar.collapsed {
      cursor: pointer;
  }
  .sidebar.collapsed * {
      cursor: default;
  }
  .sidebar.collapsed .sidebar-link,
  .sidebar.collapsed .sidebar-link * {
      cursor: pointer;
  }
  .sidebar.collapsed .sidebar-brand {
      flex-direction: column;
      gap: 1rem;
      padding-top: 1.5rem;
  }
  .sidebar.collapsed .medusa-logo {
      display: block;
  }
  html.light-mode #sidebarToggle{
      background: #ffffff;
      border-color: rgba(15,23,42,0.08);
      color: #475569;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  }
  html.light-mode #sidebarToggle:hover{
      background: rgba(15,23,42,0.05);
      border-color: rgba(15,23,42,0.15);
  }

/* Gold action buttons: consistent and no wrapping */
.btn-gold-action{
    width:auto!important;
    min-width:108px!important;
    height:42px!important;
    min-height:42px!important;
    padding:0 14px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:8px!important;
    white-space:nowrap!important;
    line-height:1!important;
    flex-wrap:nowrap!important;
    vertical-align:middle;
}
.btn-gold-action i{
    flex:0 0 auto;
}
.btn-action-form{
    min-width:112px!important;
}
.btn-action-wide{
    min-width:148px!important;
}
.btn-action-full{
    width:100%!important;
    min-width:0!important;
}
.btn-icon-only{
    min-width:42px!important;
    width:42px!important;
    padding:0!important;
}
.content-card .btn-gold-action{
    max-height:42px!important;
}

/* Dark mode visibility fixes */
html:not(.light-mode) .form-label,
html:not(.light-mode) label,
html:not(.light-mode) th,
html:not(.light-mode) .text-muted,
html:not(.light-mode) .small,
html:not(.light-mode) .card-header-premium,
html:not(.light-mode) .page-subtitle{
    color:#cbd5e1!important;
}
html:not(.light-mode) input,
html:not(.light-mode) select,
html:not(.light-mode) textarea,
.form-control-dashboard{
    color:var(--white)!important;
}
html:not(.light-mode) input::placeholder,
html:not(.light-mode) textarea::placeholder{
    color:#9ca3af!important;
    opacity:1;
}
html:not(.light-mode) .premium-table td{
    color:#f5f5f5!important;
}
html:not(.light-mode) .premium-table th{
    color:#94a3b8!important;
}
html:not(.light-mode) .form-control-dashboard:focus,
html:not(.light-mode) .form-control:focus,
html:not(.light-mode) .form-select:focus{
    color:var(--white)!important;
}

/* Align buttons in filter rows a bit closer to fields */
.content-card form .row.g-3 > [class*="col-"]{
    align-self:flex-start;
}
.content-card form .row.g-3 > [class*="col-"]:has(.btn-gold-action){
    align-self:end;
}

/* Mobile drawer */
@media(max-width:768px){
    .sidebar{
        transform:translateX(-100%);
        width:260px;
        z-index:2500;
    }
    .sidebar.mobile-open{
        transform:translateX(0);
        width:260px;
    }
    .main-content{
        margin-left:0!important;
        padding-left:1rem;
        padding-right:1rem;
    }
    .main-content.expanded{
        margin-left:0!important;
    }
    #sidebarToggle{
          left:1rem!important;
          top:1.25rem!important;
          bottom:auto!important;
          z-index: 3000;
          background: var(--bg-secondary) !important;
          border: 1px solid rgba(255,255,255,0.08) !important;
    }
    #sidebarToggle .desktop-toggle-icon {
        display: none !important;
    }
    #sidebarToggle .mobile-menu-icon {
        display: block !important;
    }
    #sidebarToggle.mobile-open .mobile-menu-icon {
        display: none !important;
    }
    #sidebarToggle.mobile-open .mobile-close-icon {
        display: block !important;
    }
}

/* Orders Management filter box tuning */
#ordersSearchForm .row.g-3{
    align-items:end;
}

#ordersSearchForm .form-label{
    margin-bottom:.35rem;
    font-size:.72rem;
    letter-spacing:.4px;
}

#ordersSearchForm .form-control-dashboard,
#ordersSearchForm .form-select{
    min-height:48px;
    height:48px;
    padding-top:.6rem !important;
    padding-bottom:.6rem !important;
    font-size:.95rem;
}

#ordersSearchForm .premium-search-group .form-control-dashboard{
    min-height:48px;
    height:48px;
}

#ordersSearchForm .premium-search-group .search-icon{
    top:50%;
    transform:translateY(-50%);
}

#ordersSearchForm .btn-action-form{
    min-width:96px!important;
    width:auto!important;
    height:48px!important;
    min-height:48px!important;
    padding:0 12px!important;
}

/* Kitchen quick status alignment */
#kitchen-tab .content-card.mb-4 .d-flex.gap-2.flex-wrap{
    margin-top:0 !important;
}

#kitchen-tab .content-card.mb-4 .btn.btn-outline-light.btn-sm{
    height:36px;
    padding:.35rem .7rem;
    line-height:1;
}

#kitchen-tab .content-card.mb-4 .form-label.mb-1{
    margin-bottom:.35rem !important;
}

/* ===== END FIX ===== */



/* ===== V10 FINAL FILTER COLUMN GAP FIX ===== */
.content-card form .row.g-3 > .d-flex:has(.btn-gold-action),
.filter-btn-wrapper{
    width:auto!important;
    flex:0 0 auto!important;
    max-width:max-content!important;
    margin-left:0!important;
    margin-top:0!important;
    padding-left:4px!important;
    padding-right:4px!important;
    justify-content:flex-start!important;
    align-items:flex-end!important;
}

.content-card form .row.g-3 > [class*="col-"]:has(.btn-gold-action){
    width:auto!important;
    flex:0 0 auto!important;
    max-width:max-content!important;
    justify-content:flex-start!important;
}

.btn-gold-action{
    width:auto!important;
    min-width:110px!important;
    height:42px!important;
    margin:0!important;
    flex:none!important;
}
/* ===== END V10 FIX ===== */
/* ===== V13 SIZE TWEAKS ONLY ===== */

/* Keep the working layout, only tighten control sizing */
#ordersSearchForm .btn-action-form,
#paymentsSearchForm .btn-action-form,
#menuSearchForm .btn-action-form{
    min-width: 104px !important;
    width: auto !important;
    height: 50px !important;
    min-height: 50px !important;
    padding: 0 12px !important;
    font-size: 0.92rem;
}

#customersSearchForm .btn-action-wide{
    min-width: 140px !important;
    width: auto !important;
    height: 50px !important;
    min-height: 50px !important;
    padding: 0 14px !important;
    font-size: 0.92rem;
}

#reportsFilterForm .btn-action-wide{
    min-width: 178px !important;
    width: auto !important;
    height: 50px !important;
    min-height: 50px !important;
    padding: 0 14px !important;
    font-size: 0.92rem;
}

/* Align the action columns closer to their fields */
#customersSearchForm .col-md-3.d-flex.align-items-end.justify-content-end.ms-md-1,
#reportsFilterForm .col-md-3.d-flex.align-items-end.justify-content-end.ms-md-1,
#paymentsSearchForm .col-md-2.d-flex.align-items-end.justify-content-end,
#ordersSearchForm .col-auto.d-flex.align-items-end,
#paymentsSearchForm .col-auto.d-flex.align-items-end{
    margin-left: 0 !important;
    padding-left: 4px !important;
    padding-right: 4px !important;
}

/* Keep the controls at a consistent visual height */
#ordersSearchForm .form-control-dashboard,
#ordersSearchForm .form-select,
#paymentsSearchForm .form-control-dashboard,
#paymentsSearchForm .form-select,
#menuSearchForm .form-control-dashboard,
#menuSearchForm .form-select,
#customersSearchForm .form-control-dashboard,
#reportsFilterForm .form-control-dashboard,
#reportsFilterForm .form-select{
    min-height: 50px !important;
    height: 50px !important;
}

/* Match the icon size to the tighter buttons */
#ordersSearchForm .btn-gold-action i,
#paymentsSearchForm .btn-gold-action i,
#menuSearchForm .btn-gold-action i,
#customersSearchForm .btn-gold-action i,
#reportsFilterForm .btn-gold-action i{
    font-size: 14px;
}

/* Prevent text wrapping in the wider buttons */
#customersSearchForm .btn-action-wide span,
#reportsFilterForm .btn-action-wide span,
#ordersSearchForm .btn-action-form span,
#paymentsSearchForm .btn-action-form span,
#menuSearchForm .btn-action-form span{
    white-space: nowrap !important;
}

/* ===== Kitchen monitor size alignment fix ONLY ===== */

#kitchen-tab .content-card.mb-4 .row{
    align-items: end !important;
}

#kitchen-tab .content-card.mb-4 .col-md-6{
    width: 50% !important;
    flex: 0 0 50% !important;
}

#kitchen-tab .content-card.mb-4 .premium-search-group{
    width: 100% !important;
}

#kitchen-tab .content-card.mb-4 .form-control-dashboard{
    height: 48px !important;
    min-height: 48px !important;
}

#kitchen-tab .content-card.mb-4 .d-flex.gap-2.flex-wrap{
    height: 48px !important;
    align-items: center !important;
    gap: 10px !important;
}

#kitchen-tab .content-card.mb-4 .btn.btn-outline-light.btn-sm{
    height: 38px !important;
    padding: 0 14px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* ===== END V13 SIZE TWEAKS ONLY ===== */

/* ===== Careers Portal Premium Action Styles ===== */
.btn-action-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    font-size: 0.85rem;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    background: rgba(255, 255, 255, 0.04);
}
.btn-action-circle:hover {
    transform: translateY(-2px);
}
.btn-action-circle-success {
    color: #2ec4b6;
    border-color: rgba(46, 196, 182, 0.2);
    background: rgba(46, 196, 182, 0.05);
}
.btn-action-circle-success:hover {
    background: #2ec4b6;
    color: #0b0a09 !important;
    box-shadow: 0 4px 12px rgba(46, 196, 182, 0.2);
}
.btn-action-circle-danger {
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.2);
    background: rgba(239, 68, 68, 0.05);
}
.btn-action-circle-danger:hover {
    background: #ef4444;
    color: #ffffff !important;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}
.btn-action-circle-info {
    color: #00d2d3;
    border-color: rgba(0, 210, 211, 0.2);
    background: rgba(0, 210, 211, 0.05);
}
.btn-action-circle-info:hover {
    background: #00d2d3;
    color: #0b0a09 !important;
    box-shadow: 0 4px 12px rgba(0, 210, 211, 0.2);
}
.btn-action-circle-light {
    color: #dfba86;
    border-color: rgba(223, 186, 134, 0.2);
    background: rgba(223, 186, 134, 0.05);
}

        /* --- Notification Bell & Badge --- */
        .notification-dropdown-wrapper {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid var(--bg-secondary);
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
            animation: pulseBadge 2s infinite;
        }

        @keyframes pulseBadge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); box-shadow: 0 0 8px rgba(239, 68, 68, 0.6); }
        }

        .bell-bounce {
            animation: bellRing 0.8s ease-in-out;
        }

        @keyframes bellRing {
            0%, 100% { transform: rotate(0); }
            15% { transform: rotate(25deg); }
            30% { transform: rotate(-20deg); }
            45% { transform: rotate(15deg); }
            60% { transform: rotate(-10deg); }
            75% { transform: rotate(5deg); }
        }

        /* --- Dropdown Menu --- */
        .notification-dropdown-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 360px;
            background: rgba(18, 17, 17, 0.95);
            border: 1px solid rgba(223, 186, 134, 0.15);
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 1100;
            display: none;
            flex-direction: column;
            overflow: hidden;
            transform-origin: top right;
            animation: dropdownScale 0.25s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
        }

        .notification-dropdown-menu.show {
            display: flex;
        }

        @keyframes dropdownScale {
            from { transform: scale(0.9) translateY(-10px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }

        .dropdown-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.15rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            color: #ffffff;
        }

        .mark-all-read-btn {
            background: transparent;
            border: none;
            color: var(--gold);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .mark-all-read-btn:hover {
            color: var(--gold-light);
            text-decoration: underline;
        }

        .dropdown-notification-list {
            max-height: 380px;
            overflow-y: auto;
        }

        .dropdown-footer-premium {
            padding: 1rem;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(0,0,0,0.2);
        }

        .dropdown-footer-premium a {
            color: var(--gold);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .dropdown-footer-premium a:hover {
            color: var(--gold-light);
            text-decoration: underline;
        }

        /* --- Notification Item --- */
        .notification-item {
            display: flex;
            gap: 12px;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }

        .notification-item:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .notification-item.unread {
            background: rgba(223, 186, 134, 0.03);
            border-left: 3px solid var(--gold);
        }

        .notif-icon-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        /* Type Colors */
        .notif-order { background: rgba(223, 186, 134, 0.1); color: var(--gold); border: 1px solid rgba(223, 186, 134, 0.2); }
        .notif-payment { background: rgba(46, 196, 182, 0.1); color: #2ec4b6; border: 1px solid rgba(46, 196, 182, 0.2); }
        .notif-kitchen { background: rgba(253, 150, 68, 0.1); color: #fd9644; border: 1px solid rgba(253, 150, 68, 0.2); }
        .notif-reservation { background: rgba(0, 210, 211, 0.1); color: #00d2d3; border: 1px solid rgba(0, 210, 211, 0.2); }
        .notif-staff { background: rgba(165, 94, 234, 0.1); color: #a55eea; border: 1px solid rgba(165, 94, 234, 0.2); }
        .notif-system { background: rgba(235, 94, 85, 0.1); color: #eb5e55; border: 1px solid rgba(235, 94, 85, 0.2); }

        .notif-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex-grow: 1;
        }

        .notif-title-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }

        .notif-title-text {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 0.88rem;
            color: #ffffff;
        }

        .notif-time {
            font-size: 0.72rem;
            color: var(--gray);
        }

        .notif-body-text {
            font-size: 0.78rem;
            color: var(--gray);
            line-height: 1.4;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notif-unread-dot {
            width: 8px;
            height: 8px;
            background-color: var(--gold);
            border-radius: 50%;
            position: absolute;
            right: 1.25rem;
            bottom: 1.25rem;
            box-shadow: 0 0 6px var(--gold);
        }

        /* --- Toasts System --- */
        .toast-container-medusa {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 360px;
            width: 90%;
            pointer-events: none;
        }

        .toast-medusa {
            background: rgba(18, 17, 17, 0.95);
            border: 1px solid rgba(223, 186, 134, 0.15);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            pointer-events: auto;
            transform: translateY(30px);
            opacity: 0;
            animation: slideInToast 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
            transition: opacity 0.3s, transform 0.3s;
        }

        @keyframes slideInToast {
            to { transform: translateY(0); opacity: 1; }
        }

        .toast-medusa.fade-out {
            transform: translateY(-20px);
            opacity: 0;
        }

        .toast-medusa-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-medusa-content {
            flex-grow: 1;
        }

        .toast-medusa-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 0.88rem;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .toast-medusa-body {
            font-size: 0.78rem;
            color: var(--gray);
            margin: 0;
            line-height: 1.35;
        }

        .toast-medusa-close {
            background: transparent;
            border: none;
            color: var(--gray);
            font-size: 0.8rem;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .toast-medusa-close:hover {
            color: #ffffff;
        }

        /* --- SOS Urgent Toast --- */
        .toast-medusa-sos {
            background: rgba(25, 8, 8, 0.97);
            border: 2px solid rgba(244, 67, 54, 0.7);
            box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.5), 0 10px 30px rgba(244, 67, 54, 0.3);
            animation: slideInToast 0.3s cubic-bezier(0.25, 0.8, 0.25, 1) forwards,
                       sosPulse 1s ease-in-out 0.3s 5 alternate;
        }

        @keyframes sosPulse {
            from { box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.5), 0 10px 30px rgba(244, 67, 54, 0.2); border-color: rgba(244, 67, 54, 0.5); }
            to   { box-shadow: 0 0 0 8px rgba(244, 67, 54, 0), 0 10px 30px rgba(244, 67, 54, 0.4); border-color: rgba(244, 67, 54, 1); }
        }

        .toast-medusa-sos .toast-medusa-title {
            color: #ff5252;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }

        .notif-sos-urgent {
            background: rgba(244, 67, 54, 0.2) !important;
            color: #f44336 !important;
        }

        /* --- Empty State Styles --- */
        .notif-empty-state {
            text-align: center;
            padding: 2.5rem 1.5rem;
            color: var(--gray);
        }

        .notif-empty-icon {
            font-size: 2.5rem;
            color: rgba(223, 186, 134, 0.15);
            margin-bottom: 1rem;
        }

        .notif-empty-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 0.95rem;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .notif-empty-desc {
            font-size: 0.78rem;
            margin: 0;
        }

        /* --- Filters & Badges on Center Page --- */
        .notif-filter-btn {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            color: var(--gray);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: var(--transition);
            cursor: pointer;
        }

        .notif-filter-btn:hover {
            border-color: rgba(223, 186, 134, 0.3);
            color: #ffffff;
        }

        .notif-filter-btn.active {
            background: rgba(223, 186, 134, 0.08);
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Read state for row */
        .notif-row.read-row {
            opacity: 0.72;
        }
        .notif-row.unread-row {
            background: rgba(223, 186, 134, 0.015);
        }

        /* Sound Control Switch */
        .sound-toggle-container {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.82rem;
            color: var(--gray);
        }

        /* Light Mode Visible Overrides */
        html.light-mode .notification-dropdown-menu {
            background: rgba(255, 255, 255, 0.98);
            border-color: rgba(223, 186, 134, 0.35);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }
        html.light-mode .dropdown-header-premium, 
        html.light-mode .notif-title-text,
        html.light-mode .notif-empty-title {
            color: #0f172a;
        }
        html.light-mode .toast-medusa {
            background: rgba(255, 255, 255, 0.98);
            border-color: rgba(223, 186, 134, 0.35);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        html.light-mode .toast-medusa-title {
            color: #0f172a;
        }
        html.light-mode .notification-item.unread {
            background: rgba(223, 186, 134, 0.05);
        }
        html.light-mode .notif-filter-btn {
            background: rgba(0,0,0,0.02);
            border-color: rgba(0,0,0,0.08);
        }
        html.light-mode .notif-filter-btn.active {
            background: rgba(223, 186, 134, 0.1);
            color: #b89225;
        }
        html.light-mode .notif-row-title {
            color: #0f172a !important;
        }

        /* --- Image Dropzone & Selector --- */
        .image-dropzone-premium {
            border: 2px dashed rgba(223, 186, 134, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.01);
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        .image-dropzone-premium:hover, 
        .image-dropzone-premium.dragover {
            border-color: var(--gold);
            background: rgba(223, 186, 134, 0.04);
        }
        .dropzone-icon {
            font-size: 2.2rem;
            color: var(--gold);
            margin-bottom: 8px;
            opacity: 0.8;
            transition: var(--transition);
        }
        .image-dropzone-premium:hover .dropzone-icon {
            transform: translateY(-2px);
            opacity: 1;
        }
        .dropzone-text {
            font-size: 0.82rem;
            color: #ffffff;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .dropzone-text span {
            color: var(--gold);
            text-decoration: underline;
        }
        .dropzone-subtext {
            font-size: 0.72rem;
            color: var(--gray);
        }
        .btn-outline-gold {
            border: 1px solid rgba(223, 186, 134, 0.4);
            color: var(--gold);
            background: transparent;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            transition: var(--transition);
        }
        .btn-outline-gold:hover,
        .btn-outline-gold.active {
            background: var(--gold) !important;
            color: #000000 !important;
            border-color: var(--gold) !important;
        }
        html.light-mode .dropzone-text {
            color: #0f172a;
        }

        /* Luxury Action Buttons Styles */
        .btn-luxury-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 38px;
            width: 42px;
            padding: 0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            border: 1px solid transparent;
            transition: var(--transition);
            background: transparent;
            color: #ffffff;
            cursor: pointer;
        }

        /* Customization manager button width override to contain badge */
        .btn-luxury-action.btn-luxury-custom {
            border-color: rgba(223, 186, 134, 0.3);
            background: rgba(223, 186, 134, 0.05);
            color: var(--gold);
            width: auto;
            padding: 0 14px;
        }
        .btn-luxury-action.btn-luxury-custom:hover {
            border-color: var(--gold);
            background: rgba(223, 186, 134, 0.15);
            color: var(--gold-light);
            box-shadow: 0 0 12px rgba(223, 186, 134, 0.15);
        }
        .btn-luxury-action.btn-luxury-custom.active {
            background-color: var(--gold);
            color: #000000;
            border-color: var(--gold);
        }
        .btn-luxury-action.btn-luxury-custom.active:hover {
            background-color: var(--gold-light);
            border-color: var(--gold-light);
        }

        /* Customization count badge */
        .luxury-badge.bg-gold-badge {
            background-color: var(--gold);
            color: #0a0a0a;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 6px;
            display: inline-block;
            line-height: 1;
            transition: var(--transition);
        }
        .btn-luxury-action.btn-luxury-custom.active .luxury-badge.bg-gold-badge {
            background-color: #000000;
            color: var(--gold);
        }

        /* Edit action button */
        .btn-luxury-action.btn-luxury-edit {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.03);
            color: var(--white);
        }
        .btn-luxury-action.btn-luxury-edit:hover {
            border-color: var(--white);
            background: rgba(255, 255, 255, 0.15);
            color: var(--white);
        }

        /* Delete action button */
        .btn-luxury-action.btn-luxury-delete {
            border-color: rgba(239, 68, 68, 0.25);
            background: rgba(239, 68, 68, 0.05);
            color: #fca5a5;
        }
        .btn-luxury-action.btn-luxury-delete:hover {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.18);
            color: #ffffff;
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.2);
        }

        /* Custom switch styling */
        .premium-switch .form-check-input {
            width: 2.6em;
            height: 1.3em;
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.15);
            cursor: pointer;
            transition: var(--transition);
        }
        .premium-switch .form-check-input:checked {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        /* Responsive Alignment: Prevent wrap in Actions column */
        .premium-table td:last-child {
            white-space: nowrap;
            width: 1%; /* Shrink column to fit contents without wrapping */
        }

        /* LIGHT MODE OVERRIDES FOR ACTIONS */
        html.light-mode .btn-luxury-action.btn-luxury-edit {
            border-color: rgba(0, 0, 0, 0.15);
            background: rgba(0, 0, 0, 0.03);
        }
        html.light-mode .btn-luxury-action.btn-luxury-edit:hover {
            border-color: var(--white);
            background: rgba(0, 0, 0, 0.08);
        }
        html.light-mode .btn-luxury-action.btn-luxury-custom {
            border-color: rgba(184, 134, 11, 0.3);
            background: rgba(184, 134, 11, 0.04);
        }
        html.light-mode .btn-luxury-action.btn-luxury-custom:hover {
            border-color: #b8860b;
            background: rgba(184, 134, 11, 0.12);
        }
        html.light-mode .btn-luxury-action.btn-luxury-custom.active {
            background-color: #b8860b;
            color: #ffffff;
            border-color: #b8860b;
        }
        html.light-mode .btn-luxury-action.btn-luxury-custom.active:hover {
            background-color: #9b7008;
            border-color: #9b7008;
        }
        html.light-mode .btn-luxury-action.btn-luxury-custom.active .luxury-badge.bg-gold-badge {
            background-color: #ffffff;
            color: #b8860b;
        }
        html.light-mode .btn-luxury-action.btn-luxury-delete {
            border-color: rgba(220, 38, 38, 0.25);
            background: rgba(220, 38, 38, 0.04);
            color: #dc2626;
        }
        html.light-mode .btn-luxury-action.btn-luxury-delete:hover {
            border-color: #b91c1c;
            background: rgba(220, 38, 38, 0.12);
            color: #ffffff;
        }

        #printableReportTemplate {
            background: white !important;
            color: black !important;
        }
        #printableReportTemplate * {
            color: #000 !important;
        }
        #printableReportTemplate th, #printableReportTemplate td {
            color: #000 !important;
            border-color: #ddd !important;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #printableReportTemplate, #printableReportTemplate * {
                visibility: visible !important;
            }
            #printableReportTemplate {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0 !important;
                margin: 0 !important;
            }
            @page {
                size: A4;
                margin: 20mm;
            }
        }
</style>
</head>
<body>
<!-- GLOBAL HEADER ACTIONS (THEME & NOTIFICATIONS) -->
    <div style="position: fixed; top: 2rem; right: 2.5rem; z-index: 1050; display: flex; align-items: center; gap: 12px;">
        <!-- Notification Bell Dropdown -->
        <div class="notification-dropdown-wrapper">
            <button id="notificationBellBtn" class="btn btn-outline-light rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: var(--transition); position: relative;" onclick="toggleNotificationDropdown(event)" title="Notifications">
                <i class="fas fa-bell" style="color: var(--gold); font-size: 1.2rem;"></i>
                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
            </button>
            <div id="notificationDropdownMenu" class="notification-dropdown-menu">
                <div class="dropdown-header-premium">
                    <span>Notifications</span>
                    <button class="mark-all-read-btn" onclick="markAllNotificationsRead(event)">Mark all read</button>
                </div>
                <div id="dropdownNotificationList" class="dropdown-notification-list">
                    <!-- Loaded dynamically via polling -->
                </div>
                <div class="dropdown-footer-premium">
                    <a href="javascript:void(0)" onclick="goToNotificationsTab(event)">View All Notifications</a>
                </div>
            </div>
        </div>

        <!-- Theme Toggle -->
        <button id="themeToggleBtn" class="btn btn-outline-light rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; background: var(--bg-secondary); border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 4px 15px rgba(0,0,0,0.3); transition: var(--transition);" onclick="toggleTheme()" title="Toggle Theme">
            <i class="fas fa-moon" id="themeIcon" style="color: var(--gold); font-size: 1.2rem;"></i>
        </button>
    </div>

    <!-- Sidebar Toggle Button -->
    <button id="sidebarToggle" type="button" onclick="toggleSidebar()" aria-label="Toggle sidebar" title="Toggle sidebar">
        <i class="fas fa-chevron-left desktop-toggle-icon"></i>
        <i class="fas fa-bars mobile-menu-icon"></i>
        <i class="fas fa-times mobile-close-icon"></i>
    </button>

    <!-- LEFT SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-brand" style="display: flex; align-items: center; gap: 8px;">
            <img src="../assets/images/medusaa2(onlylogo).png" alt="Medusa Logo" style="height: 32px;" class="medusa-logo">
            <span>Admin</span>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a class="sidebar-link active" onclick="switchTab('dashboard-tab', this)">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('orders-tab', this)">
                    <i class="fas fa-receipt"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('tables-tab', this)">
                    <i class="fas fa-chair"></i>
                    <span>Tables & QR</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('kitchen-tab', this)">
                    <i class="fas fa-fire-burner"></i>
                    <span>Kitchen Panel</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('menu-tab', this)">
                    <i class="fas fa-book-open"></i>
                    <span>Menu Card</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('customers-tab', this)">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('mass-emails-tab', this)">
                    <i class="fas fa-envelope"></i>
                    <span>Mass Emails</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('liquor-tab', this); loadActiveQuotas();">
                    <i class="fas fa-wine-bottle"></i>
                    <span>Liquor Quota</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('payments-tab', this)">
                    <i class="fas fa-wallet"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('careers-tab', this)">
                    <i class="fas fa-briefcase"></i>
                    <span>Careers</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('reports-tab', this)">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('campaigns-tab', this)">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Campaigns</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('notifications-tab', this); fetchNotificationsPage(0);">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('driver-tab', this)">
                    <i class="fas fa-motorcycle"></i>
                    <span>Driver View</span>
                </a>
            </li>
            <li>
                <a class="sidebar-link" onclick="switchTab('settings-tab', this)">
                    <i class="fas fa-sliders"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="../api/logout.php" class="sidebar-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- MAIN CONTAINER -->
    <div class="main-content">
        
        <!-- ==================== DRIVER TAB ==================== -->
        <div id="driver-tab" class="tab-panel" style="height: calc(100vh - 40px); overflow: hidden; padding: 0;">
            <iframe src="driver.php" style="width: 100%; height: 100%; border: none; border-radius: 12px;"></iframe>
        </div>

        <!-- ==================== DASHBOARD TAB ==================== -->
        <div id="dashboard-tab" class="tab-panel active">
            <div class="page-header">
                <h1 class="page-title"><?php echo htmlspecialchars($settings['restaurant_name']); ?></h1>
                <p class="page-subtitle">Premium Command Center for Client Demo</p>
            </div>

            <!-- METRIC CARDS GRID -->
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Total Orders</h5>
                        <div class="value"><?php echo $total_orders; ?></div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-shopping-basket"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Total Revenue</h5>
                        <div class="value">₹<?php echo number_format($total_revenue, 2); ?></div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-dollar-sign"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Online Orders</h5>
                        <div class="value"><?php echo $online_orders_count; ?></div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-globe"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Active Tables</h5>
                        <div class="value"><?php echo $active_tables_count; ?></div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-chair"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Today's Sales</h5>
                        <div class="value">₹<?php echo number_format($today_sales, 2); ?></div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-cash-register"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Top Dish</h5>
                        <div class="value" style="font-size: 1.1rem; line-height: 1.6;"><?php echo htmlspecialchars($top_dish_name); ?></div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-star"></i></div>
                </div>
            </div>

            <div class="row">
                <!-- CHART -->
                <div class="col-lg-12 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Revenue Analytics</div>
                        <div style="height: 300px; position: relative;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== ORDERS TAB ==================== -->
        <div id="orders-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Order Management</h1>
                <p class="page-subtitle">Process online food requests and generate tableside bills</p>
            </div>

            <!-- Orders Search Box -->
            <div class="content-card mb-4">
                <form id="ordersSearchForm" onsubmit="performOrdersSearch(event)">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Search Text</label>
                            <div class="premium-search-group">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="order_search_input" class="form-control form-control-dashboard" placeholder="Order ID, Customer, Phone, Address...">
                            </div>

                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small text-uppercase">Status</label>
                            <select id="order_status_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="preparing">Preparing</option>
                                <option value="ready">Ready</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small text-uppercase">Payment Status</label>
                            <select id="order_payment_status_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All</option>
                                <option value="paid">Paid</option>
                                <option value="unpaid">Unpaid</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small text-uppercase">Order Type</label>
                            <select id="order_type_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All Types</option>
                                <option value="online">Online Order</option>
                                <option value="dinein">Dine-In Order</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Date Filter</label>
                            <div class="d-flex gap-2 align-items-end">
                                <select id="order_date_select" class="form-select bg-dark text-white border-secondary form-control-dashboard" onchange="toggleCustomDateFields('orders', this.value)">
                                    <option value="all">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="7days">Last 7 Days</option>
                                    <option value="30days">Last 30 Days</option>
                                    <option value="custom">Custom Range</option>
                                </select>
                                <button type="submit" class="btn btn-gold-action btn-action-form" title="Filter"><i class="fas fa-search me-1"></i><span>Filter</span></button>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-2" id="orders_custom_date_row" style="display:none;">
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Start Date</label>
                            <input type="date" id="order_start_date" class="form-control bg-dark text-white border-secondary form-control-dashboard">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">End Date</label>
                            <input type="date" id="order_end_date" class="form-control bg-dark text-white border-secondary form-control-dashboard">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Min Amount (₹)</label>
                            <input type="number" id="order_min_amount" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Max Amount (₹)</label>
                            <input type="number" id="order_max_amount" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="10000">
                        </div>
                    </div>
                </form>
            </div>

            <!-- ORDERS SEARCH RESULTS CARD (shown by JS after search) -->
            <div id="orders-search-results-card" class="content-card mb-4" style="display:none;">
                <div class="card-header-premium">
                    <span>Search Results</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('orders-search-results-card').style.display='none'">
                        <i class="fas fa-times me-1"></i> Clear Results
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table premium-table align-middle">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="orders-search-results-body"></tbody>
                    </table>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4" id="orderTabNav" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active text-white" style="background: transparent; border: none;" id="online-orders-tab" data-bs-toggle="tab" data-bs-target="#online-orders-panel" type="button">Online Orders</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link text-white" style="background: transparent; border: none; margin-left: 10px;" id="dinein-orders-tab" data-bs-toggle="tab" data-bs-target="#dinein-orders-panel" type="button">Dine-In Orders</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link text-white" style="background: transparent; border: none; margin-left: 10px;" id="recent-orders-tab" data-bs-toggle="tab" data-bs-target="#recent-orders-panel" type="button">Recent Orders</button>
                </li>
            </ul>

            <div class="tab-content" id="orderTabContent">
                <!-- ONLINE LIST -->
                <div class="tab-pane fade show active" id="online-orders-panel">
                    <div class="content-card">
                        <div class="card-header-premium">Active Online Delivery & Takeaway Orders</div>
                        <div class="table-responsive">
                            <table class="table premium-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Items Ordered</th>
                                        <th>Total Bill</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $on_stmt = $pdo->query("SELECT *, (SELECT rating FROM feedback WHERE order_number = orders.order_number LIMIT 1) AS rating, (SELECT review FROM feedback WHERE order_number = orders.order_number LIMIT 1) AS review FROM orders WHERE delivery_address NOT LIKE 'Table %' ORDER BY id DESC");
                                    $online_orders = $on_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    if (empty($online_orders)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No online orders found.</td></tr>
                                    <?php else:
                                    foreach ($online_orders as $ord):
                                        // Fetch items
                                        $it_stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                                        $it_stmt->execute([$ord['id']]);
                                        $items = $it_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        $items_text = [];
                                        foreach ($items as $it) {
                                            $items_text[] = $it['item_name'] . ' x' . $it['quantity'];
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong class="text-gold">#<?php echo htmlspecialchars($ord['order_number']); ?></strong> <?php if (isset($ord['order_type']) && strcasecmp($ord['order_type'], 'takeaway') === 0): ?><span class="badge bg-warning text-dark ms-1" style="font-size: 0.7rem;"><i class="fas fa-shopping-bag"></i> Takeaway</span><?php else: ?><span class="badge bg-primary text-white ms-1" style="font-size: 0.7rem;"><i class="fas fa-truck"></i> Delivery</span><?php endif; ?>
                                                <?php echo renderStars($ord['rating'] ?? 0, $ord['review'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ord['customer_name']); ?></strong><br>
                                                <small class="text-muted"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($ord['customer_phone']); ?></small><br>
                                                <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ord['delivery_address']); ?></small>
                                            </td>
                                            <td><?php echo implode(', ', $items_text); ?></td>
                                            <td class="text-gold"><strong>₹<?php echo number_format($ord['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($ord['order_status']); ?>">
                                                    <?php echo htmlspecialchars($ord['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (strtolower($ord['order_status']) === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="updateOrderStatus(<?php echo $ord['id']; ?>, 'preparing')">Accept</button>
                                                    <button class="btn btn-sm btn-danger ms-1" onclick="updateOrderStatus(<?php echo $ord['id']; ?>, 'cancelled')">Reject</button>
                                                <?php else: ?>
                                                    <select class="form-select form-select-sm bg-dark text-white border-secondary w-auto d-inline-block" onchange="updateOrderStatus(<?php echo $ord['id']; ?>, this.value)">
                                                        <option value="">Change Status</option>
                                                        <option value="preparing" <?php echo strtolower($ord['order_status'])=='preparing'?'selected':''; ?>>Preparing</option>
                                                        <option value="ready" <?php echo strtolower($ord['order_status'])=='ready'?'selected':''; ?>>Ready</option>
                                                        <option value="completed" <?php echo strtolower($ord['order_status'])=='completed'?'selected':''; ?>>Completed</option>
                                                    </select>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- DINE IN TABLE ORDERS -->
                <div class="tab-pane fade" id="dinein-orders-panel">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="content-card">
                                <div class="card-header-premium">Active Dine-in Tables</div>
                                <div class="list-group bg-dark border-0">
                                    <?php
                                    $dine_active_stmt = $pdo->query("SELECT * FROM orders WHERE order_status IN ('pending', 'preparing', 'ready') AND delivery_address LIKE 'Table %'");
                                    $active_dinein_orders = $dine_active_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    if (empty($active_dinein_orders)): ?>
                                        <p class="text-center text-muted py-3">No active tables placing orders.</p>
                                    <?php else:
                                    foreach ($active_dinein_orders as $d_ord):
                                        // extract table
                                        $tbl = 'Unknown';
                                        if (preg_match('/Table\s+([A-Za-z0-9]+)/i', $d_ord['delivery_address'], $m)) {
                                            $tbl = $m[1];
                                        }
                                        ?>
                                        <button class="list-group-item list-group-item-action bg-dark text-white border-secondary d-flex justify-content-between align-items-center mb-2 rounded" onclick="loadTableOrderDetails(<?php echo htmlspecialchars(json_encode($d_ord)); ?>)">
                                            <span><strong>Table <?php echo $tbl; ?></strong> (<?php echo htmlspecialchars($d_ord['customer_name']); ?>)</span>
                                            <span class="badge bg-gold text-dark">₹<?php echo number_format($d_ord['total_amount'], 2); ?></span>
                                        </button>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="content-card" id="table-detail-card" style="display:none;">
                                <div class="card-header-premium">
                                    <span id="detail-table-title">Select an Active Table</span>
                                    <span class="status-badge" id="detail-table-status"></span>
                                </div>
                                <div id="table-detail-body">
                                    <!-- Populated dynamically via JS -->
                                    <table class="table premium-table align-middle">
                                        <thead>
                                            <tr>
                                                <th>Dish Name</th>
                                                <th>Qty</th>
                                                <th>Price</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="detail-table-items"></tbody>
                                    </table>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary">
                                        <h4>Grand Total: <span id="detail-table-total" class="text-gold">₹0.00</span></h4>
                                        <div>
                                            <button class="btn btn-outline-light" onclick="openAddTableItemModal()"><i class="fas fa-plus"></i> Add Items</button>
                                            <button class="btn btn-gold-action ms-2 btn-action-wide" onclick="openBillSettleModal()"><i class="fas fa-file-invoice"></i><span>Generate Bill & Pay</span></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECENT ORDERS -->
                <div class="tab-pane fade" id="recent-orders-panel">
                    <div class="content-card">
                        <div class="card-header-premium">Recent Orders</div>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table premium-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Details</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_orders)): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No recent orders found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_orders as $ord): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-gold">#<?php echo htmlspecialchars($ord['order_number']); ?></strong>
                                                <?php echo renderStars($ord['rating'] ?? 0, $ord['review'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($ord['customer_name']); ?></strong>
                                                <?php if (!empty($ord['customer_phone'])): ?>
                                                    <br><small class="text-muted"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($ord['customer_phone']); ?></small>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($ord['delivery_address']); ?></small>
                                            </td>
                                            <td class="text-gold"><strong>₹<?php echo number_format($ord['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="status-badge status-<?php echo strtolower($ord['order_status']); ?>">
                                                    <?php echo htmlspecialchars($ord['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($ord['order_date']); ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TABLES & QR TAB ==================== -->
        <div id="tables-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Table Layout & QR Codes</h1>
                <p class="page-subtitle">Configure dine-in layout and download assigned QR menus</p>
            </div>

            <!-- TABLE LAYOUT -->
            <?php foreach ($table_zones as $zone_name => $tables): ?>
            <div class="tables-zone-box">
                <div class="zone-title"><?php echo htmlspecialchars($zone_name); ?></div>
                <div class="table-grid">
                    <?php foreach ($tables as $t_code): 
                        $is_occ = in_array($t_code, $occupied_tables);
                    ?>
                    <div class="table-cell <?php echo $is_occ ? 'occupied' : ''; ?>" onclick="openTableQRModal('<?php echo $t_code; ?>', <?php echo $is_occ ? 'true' : 'false'; ?>)">
                        <div class="table-name">Table <?php echo $t_code; ?></div>
                        <div class="table-status">
                            <?php echo $is_occ ? '🔴 Occupied' : '🟢 Free'; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ==================== KITCHEN PANEL TAB ==================== -->
        <div id="kitchen-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Kitchen Monitor</h1>
                <p class="page-subtitle">Live screen for chefs - updates automatically when orders are placed</p>
            </div>

            <!-- Kitchen Search Control -->
            <div class="content-card mb-4">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label text-muted small text-uppercase">Search Active Orders</label>
                        <div class="d-flex gap-2">
                            <div class="premium-search-group flex-grow-1" style="min-width:0;">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="kitchen_search_input"
                                    class="form-control form-control-dashboard"
                                    placeholder="Search Order ID, Dish Name, Customer, Table..."
                                    onkeydown="if(event.key==='Enter') filterKitchenOrders()">
                            </div>
                            <button class="btn btn-sm btn-gold-action flex-shrink-0" onclick="filterKitchenOrders()" title="Search">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button class="btn btn-sm flex-shrink-0" id="kitchen-reset-btn"
                                style="display:none;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:#94a3b8;"
                                onclick="resetKitchenSearch()" title="Reset">
                                <i class="fas fa-times"></i> Reset
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small text-uppercase mb-1">Quick Status Filter</label>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <button class="btn btn-outline-light active btn-sm" id="btn-kitchen-filter-all" onclick="filterKitchenStatus('all')">All</button>
                            <button class="btn btn-outline-light btn-sm ms-1" id="btn-kitchen-filter-pending" onclick="filterKitchenStatus('pending')">Pending</button>
                            <button class="btn btn-outline-light btn-sm ms-1" id="btn-kitchen-filter-preparing" onclick="filterKitchenStatus('preparing')">Cooking</button>
                            <button class="btn btn-outline-light btn-sm ms-1" id="btn-kitchen-filter-ready" onclick="filterKitchenStatus('ready')">Ready</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="kitchen-columns">
                <!-- INCOMING / NEW -->
                <div class="kitchen-col">
                    <div class="kitchen-col-title">
                        <span>New Orders</span>
                        <span class="count-badge-pending" id="count-kitchen-pending">0</span>
                    </div>
                    <div id="kitchen-pending-list"></div>
                </div>

                <!-- PREPARING -->
                <div class="kitchen-col">
                    <div class="kitchen-col-title">
                        <span>Preparing / Cooking</span>
                        <span class="count-badge-cooking" id="count-kitchen-preparing">0</span>
                    </div>
                    <div id="kitchen-preparing-list"></div>
                </div>

                <!-- READY FOR PICKUP -->
                <div class="kitchen-col">
                    <div class="kitchen-col-title">
                        <span>Ready for Service</span>
                        <span class="count-badge-ready" id="count-kitchen-ready">0</span>
                    </div>
                    <div id="kitchen-ready-list"></div>
                </div>
            </div>
        </div>

        <!-- ==================== MENU TAB ==================== -->
        <div id="menu-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Menu Management</h1>
                <p class="page-subtitle">Add, edit, toggle availability, and set pricing for dishes</p>
            </div>

            <!-- Menu Search Box -->
            <div class="content-card mb-4">
                <form id="menuSearchForm" onsubmit="performMenuSearch(event)">
                    <!-- Row 1: Primary Filters -->
                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase">Dish Name</label>
                            <input type="text" id="menu_search_input" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="Search dish name, details...">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase">Category</label>
                            <select id="menu_category_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="">All Categories</option>
                                <?php
                                $cat_file = __DIR__ . '/categories.json';
                                $cats = file_exists($cat_file) ? json_decode(file_get_contents($cat_file), true) : [];
                                foreach ($cats as $c) {
                                    echo '<option value="' . htmlspecialchars($c) . '">' . htmlspecialchars($c) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase">Diet Type</label>
                            <select id="menu_diet_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All Types</option>
                                <option value="veg">Veg</option>
                                <option value="nonveg">Non-Veg</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Secondary Filters & Actions -->
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase">Price Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="menu_price_min" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="Min">
                                <input type="number" id="menu_price_max" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="Max">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase">Availability</label>
                            <select id="menu_availability_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All</option>
                                <option value="1">Available</option>
                                <option value="0">Out of Stock</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-center gap-3">
                            <div class="form-check form-switch mb-0 flex-grow-1">
                                <input type="checkbox" id="menu_bestseller_check" class="form-check-input">
                                <label class="form-check-label text-muted small text-uppercase" for="menu_bestseller_check">Show Bestsellers Only</label>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-gold-action px-4" title="Filter"><i class="fas fa-search me-2"></i>Search</button>
                                <button type="button" class="btn px-3" id="menu-reset-btn"
                                    style="display:none;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);color:#94a3b8;height:42px;"
                                    onclick="resetMenuSearch()" title="Reset Filters">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- MENU SEARCH RESULTS CARD (shown by JS after search) -->
            <div id="menu-search-results-card" class="content-card mb-4" style="display:none;">
                <div class="card-header-premium">
                    <span>Menu Search Results</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="resetMenuSearch()">
                        <i class="fas fa-times me-1"></i> Clear Results
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table premium-table align-middle" id="menu-search-results-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="text-center">
                                    <input class="form-check-input" type="checkbox" id="selectAllSearchResults" onclick="document.querySelectorAll('.search-result-checkbox').forEach(cb => cb.checked = this.checked)">
                                </th>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Description</th>
                                <th class="text-center">Available</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="menu-search-results-body"></tbody>
                    </table>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header-premium flex-wrap gap-2">
                    <span>Active Food Items</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary text-white border-secondary" onclick="openManageCategoriesModal()">
                            <i class="fas fa-tags me-1"></i> Manage Categories
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary text-white border-secondary" onclick="openReorderDishesModal()">
                            <i class="fas fa-sort me-1"></i> Reorder Dishes
                        </button>
                        <button class="btn btn-gold-action btn-action-wide" onclick="openAddMenuModal()"><i class="fas fa-plus"></i><span>Add New Dish</span></button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table premium-table align-middle">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Description</th>
                                <th class="text-center">Available</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menu_list as $dish): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo htmlspecialchars(getDishImage($dish['image_url'])); ?>" alt="" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&h=100&fit=crop&auto=format'">
                                </td>
                                <td>
                                    <?php if (($dish['diet_type'] ?? 'veg') === 'veg'): ?>
                                        <svg viewBox="0 0 24 24" width="16" height="16" class="align-middle me-1" style="display:inline-block;"><rect x="2" y="2" width="20" height="20" rx="2" fill="none" stroke="#0f8a45" stroke-width="2.5"/><circle cx="12" cy="12" r="5" fill="#0f8a45"/></svg>
                                    <?php else: ?>
                                        <svg viewBox="0 0 24 24" width="16" height="16" class="align-middle me-1" style="display:inline-block;"><rect x="2" y="2" width="20" height="20" rx="2" fill="none" stroke="#c82333" stroke-width="2.5"/><circle cx="12" cy="12" r="5" fill="#c82333"/></svg>
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($dish['name']); ?></strong>
                                </td>
                                <td>
                                    <span class="text-uppercase"><?php echo htmlspecialchars($dish['category']); ?></span>
                                    <?php if (!empty($dish['subcategory'])): ?>
                                        <br><small class="text-gold" style="font-size: 0.75rem; letter-spacing: 0.5px;"><?php echo htmlspecialchars($dish['subcategory']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?php echo number_format($dish['price'], 2); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($dish['description']); ?></small></td>
                                <td class="text-center">
                                    <div class="form-check form-switch premium-switch d-inline-block">
                                        <input class="form-check-input" type="checkbox" role="switch" <?php echo $dish['is_available'] ? 'checked' : ''; ?> onchange="toggleMenuAvailability(<?php echo $dish['id']; ?>, this.checked)">
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <?php
                                        // Count customization groups for this dish
                                        $cust_count = 0;
                                        try {
                                            $cc = $pdo->prepare("SELECT COUNT(*) FROM dish_customizations WHERE food_item_id = ?");
                                            $cc->execute([$dish['id']]);
                                            $cust_count = (int)$cc->fetchColumn();
                                        } catch(Exception $e) { $cust_count = 0; }
                                        $dish['display_image_url'] = getDishImage($dish['image_url']);
                                        ?>
                                        <button class="btn btn-sm btn-luxury-action btn-luxury-custom <?php echo $cust_count > 0 ? 'active' : ''; ?>" onclick="openCustomizationManager(<?php echo $dish['id']; ?>, '<?php echo htmlspecialchars(addslashes($dish['name'])); ?>')" title="Manage Customizations">
                                            <i class="fas fa-sliders-h"></i> 
                                            <span class="luxury-badge bg-gold-badge ms-1"><?php echo $cust_count; ?></span>
                                        </button>
                                        <button class="btn btn-sm btn-luxury-action btn-luxury-edit" onclick="openEditMenuModal(<?php echo htmlspecialchars(json_encode($dish)); ?>)" title="Edit Item">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-luxury-action btn-luxury-delete" onclick="deleteMenuItem(<?php echo $dish['id']; ?>)" title="Delete Item">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="customers-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Customer History</h1>
                <p class="page-subtitle">View phone records and lifetime values for regular guests</p>
            </div>

            <!-- Customers Search Box -->
            <div class="content-card mb-4">
                <form id="customersSearchForm" onsubmit="performCustomersSearch(event)">
                    <div class="row g-3">
                        <div class="col-md-9">
                            <label class="form-label text-muted small text-uppercase">Customer Lookup</label>
                            <div class="premium-search-group">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="customer_search_input" class="form-control form-control-dashboard" placeholder="Search by name, phone, email, customer ID...">
                            </div>

                        </div>
                        <div class="col-md-3 d-flex align-items-end justify-content-end ms-md-1">
                            <button type="submit" class="btn btn-gold-action btn-action-wide"><i class="fas fa-search me-1"></i><span>Search</span></button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="content-card">
                <div class="card-header-premium">Active Customers Registry</div>
                <div class="table-responsive">
                    <table class="table premium-table align-middle">
                        <thead>
                            <tr>
                                <th>ID / Name</th>
                                <th>Phone Number</th>
                                <th>Email</th>
                                <th>Total Orders</th>
                                <th>Total Spend</th>
                                <th>Last Order</th>
                                <th>Favorite Dish</th>
                                <th>Payment Summary</th>
                            </tr>
                        </thead>
                        <tbody id="customers-table-body">
                            <?php if (empty($customer_list)): ?>
                                <tr><td colspan="8" class="text-center text-muted">No customer data available yet.</td></tr>
                            <?php else:
                            foreach ($customer_list as $cust): 
                                $paid = $cust['payment_summary']['paid_count'] ?? 0;
                                $failed = $cust['payment_summary']['failed_count'] ?? 0;
                                $pending = $cust['payment_summary']['pending_count'] ?? 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($cust['customer_name'] ?: 'Guest'); ?></strong><br>
                                    <small class="text-muted">ID: <?php echo htmlspecialchars($cust['customer_id'] ?: 'GUEST'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($cust['customer_phone'] ?: 'N/A'); ?></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($cust['email'] ?: 'N/A'); ?></small></td>
                                <td><?php echo $cust['order_count']; ?> orders</td>
                                <td class="text-gold">₹<?php echo number_format($cust['total_spent'], 2); ?></td>
                                <td><small class="text-muted"><?php echo $cust['last_order_date'] ? date('d M Y, h:i A', strtotime($cust['last_order_date'])) : 'N/A'; ?></small></td>
                                <td><span class="badge bg-dark border border-secondary text-white"><?php echo htmlspecialchars($cust['favorite_dish']); ?></span></td>
                                <td>
                                    <span class="badge bg-success text-dark" title="Completed Orders">Paid: <?php echo $paid; ?></span>
                                    <?php if ($pending > 0): ?><span class="badge bg-warning text-dark" title="Pending Orders">Pending: <?php echo $pending; ?></span><?php endif; ?>
                                    <?php if ($failed > 0): ?><span class="badge bg-danger text-white" title="Cancelled Orders">Failed: <?php echo $failed; ?></span><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== PAYMENTS TAB ==================== -->
        <div id="payments-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Transaction Log</h1>
                <p class="page-subtitle">Track and settle pending cash, card, and UPI bills</p>
            </div>

            <!-- Payments Search Box -->
            <div class="content-card mb-4">
                <form id="paymentsSearchForm" onsubmit="performPaymentsSearch(event)">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Search Text</label>
                            <input type="text" id="payment_search_input" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="Order ID or Customer...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small text-uppercase">Method</label>
                            <select id="payment_method_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All Methods</option>
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                                <option value="netbanking">Net Banking</option>
                                <option value="wallet">Wallet</option>
                                <option value="gateway">Online Gateway</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small text-uppercase">Status</label>
                            <select id="payment_status_select" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All Statuses</option>
                                <option value="success">Success / Paid</option>
                                <option value="failed">Failed / Cancelled</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Amount Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="payment_min_amount" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="Min">
                                <input type="number" id="payment_max_amount" class="form-control bg-dark text-white border-secondary form-control-dashboard" placeholder="Max">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end justify-content-end">
                            <button type="submit" class="btn btn-gold-action btn-action-form"><i class="fas fa-search me-1"></i><span>Filter</span></button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="content-card">
                <div class="card-header-premium">Settled Receipts</div>
                <div class="table-responsive">
                    <table class="table premium-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="payments-table-body">
                            <?php
                            $pay_stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 50");
                            $pay_logs = $pay_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($pay_logs as $log):
                                $method = 'ONLINE GATEWAY';
                                $addr = strtoupper($log['delivery_address'] ?? '');
                                $pm = strtolower($log['payment_method'] ?? '');
                                if (strpos($addr, 'PAID VIA CASH') !== false || $pm === 'cash' || $pm === 'cod') $method = 'CASH';
                                elseif (strpos($addr, 'PAID VIA CARD') !== false || $pm === 'card') $method = 'CARD';
                                elseif (strpos($addr, 'PAID VIA UPI') !== false || $pm === 'upi') $method = 'UPI';
                                elseif (strpos($addr, 'PAID VIA NETBANKING') !== false || strpos($addr, 'PAID VIA NET BANKING') !== false || $pm === 'netbanking') $method = 'NET BANKING';
                                elseif (strpos($addr, 'PAID VIA WALLET') !== false || $pm === 'wallet') $method = 'WALLET';
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($log['order_number']); ?></td>
                                <td><?php echo htmlspecialchars($log['customer_name']); ?></td>
                                <td>₹<?php echo number_format($log['total_amount'], 2); ?></td>
                                <td><span class="badge bg-dark border border-secondary text-white"><?php echo $method; ?></span></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($log['order_status'])==='completed'?'bg-success text-dark':'bg-warning text-dark'; ?>">
                                        <?php echo strtolower($log['order_status'])==='completed'?'Paid':'Pending Settlement'; ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($log['order_date']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== CAMPAIGNS TAB ==================== -->
        <div id="campaigns-tab" class="tab-panel">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">Campaigns Management</h1>
                    <p class="page-subtitle">Create and track master campaign codes (e.g., SUMMER2026) instead of managing them in .env</p>
                </div>
                <button class="btn btn-primary d-flex align-items-center gap-2" onclick="openAddCampaignModal()">
                    <i class="fas fa-plus"></i> Generate New Campaign
                </button>
            </div>

            <div class="content-card">
                <div class="card-header-premium">Active & Past Campaigns</div>
                <div class="table-responsive">
                    <table class="table premium-table align-middle" id="campaigns-table">
                        <thead>
                            <tr>
                                <th>Campaign Code</th>
                                <th>Discount</th>
                                <th>Min. Order</th>
                                <th>Max. Discount</th>
                                <th>Expiry Date</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th style="min-width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="campaigns-table-body">
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Loading campaigns...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== REPORTS TAB ==================== -->
        <div id="reports-tab" class="tab-panel">
            
            <!-- Professional Written Report Template (Hidden from screen) -->
            <div id="printableReportTemplate" style="display: none; background: white; color: black; padding: 40px; font-family: 'Times New Roman', serif;">
                <div style="text-align: center; border-bottom: 2px solid #dfba86; padding-bottom: 20px; margin-bottom: 30px;">
                    <h1 style="margin: 0; color: #111; font-family: 'Playfair Display', serif; font-size: 32px; letter-spacing: 2px;">LA MEDUSA</h1>
                    <p style="margin: 5px 0 0; color: #555; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Bar & Lounge - Professional Business Report</p>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 30px; font-size: 12px; color: #333;">
                    <div>
                        <strong>Report Period:</strong> <span id="print_report_period"></span><br>
                        <strong>Generated On:</strong> <span id="print_report_date"></span>
                    </div>
                    <div style="text-align: right;">
                        <strong>Total Revenue:</strong> <span id="print_report_revenue" style="font-size: 16px; font-weight: bold; color: #dfba86;"></span>
                    </div>
                </div>

                <div style="margin-bottom: 30px;">
                    <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Executive Summary</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8f8f8;">
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Completed Orders</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Average Order Value (AOV)</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Acceptance / Completion</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Performance Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_orders"></td>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_aov"></td>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_rates"></td>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_score"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-bottom: 30px;">
                    <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Customer Analytics</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8f8f8;">
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Total Customers</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">New Customers</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Returning</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Retention Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_cust_total"></td>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_cust_new"></td>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_cust_return"></td>
                                <td style="padding: 10px; border: 1px solid #ddd;" id="print_report_cust_rate"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; gap: 20px;">
                    <div style="flex: 1; break-inside: avoid;">
                        <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Revenue Trend</h2>
                        <div style="text-align: center; margin-top: 15px;">
                            <img id="print_sales_chart_img" src="" style="width: 100%; height: auto; border: 1px solid #eee; padding: 10px; background: white;" alt="Sales Chart">
                        </div>
                    </div>
                    <div style="flex: 1; break-inside: avoid;">
                        <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Payment Split</h2>
                        <div style="text-align: center; margin-top: 15px;">
                            <img id="print_payment_chart_img" src="" style="width: 100%; height: auto; border: 1px solid #eee; padding: 10px; background: white;" alt="Payment Chart">
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 30px; display: flex; justify-content: space-between; gap: 20px;">
                    <div style="flex: 1; break-inside: avoid;">
                        <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Category Split</h2>
                        <div style="text-align: center; margin-top: 15px;">
                            <img id="print_category_chart_img" src="" style="width: 100%; height: auto; border: 1px solid #eee; padding: 10px; background: white;" alt="Category Chart">
                        </div>
                    </div>
                    <div style="flex: 1; break-inside: avoid;">
                        <!-- Empty flex space to align sizes nicely -->
                    </div>
                </div>

                <div style="margin-bottom: 30px; padding-top: 20px; break-inside: avoid;">
                    <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Top Performing Items</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8f8f8;">
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Item Name</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Category</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Qty Sold</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Revenue</th>
                            </tr>
                        </thead>
                        <tbody id="print_top_dishes_tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 30px; break-inside: avoid;">
                    <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Payment Breakdown</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8f8f8;">
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Payment Method</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Transactions</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Amount (INR)</th>
                            </tr>
                        </thead>
                        <tbody id="print_payments_tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 30px; break-inside: avoid;">
                    <h2 style="font-family: 'Playfair Display', serif; font-size: 20px; color: #222; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Top Performing Customers</h2>
                    <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8f8f8;">
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Customer Name & Phone</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Orders</th>
                                <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">Total Spent (INR)</th>
                            </tr>
                        </thead>
                        <tbody id="print_customers_tbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 50px; text-align: center; font-size: 11px; color: #777; border-top: 1px solid #eee; padding-top: 20px;">
                    End of Report. Confidential Business Document.
                </div>
            </div>

            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">Business Intelligence Dashboard</h1>
                    <p class="page-subtitle">Real-time enterprise analytics and professional reporting center</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-light btn-sm" onclick="printReport()"><i class="fas fa-print me-1"></i> Print Report</button>
                    <button class="btn btn-outline-light btn-sm" onclick="exportReportToPDF()"><i class="fas fa-file-pdf me-1"></i> Export PDF</button>
                    <button class="btn btn-outline-light btn-sm" onclick="exportReportToExcel()"><i class="fas fa-file-excel me-1"></i> Export Excel</button>
                </div>
            </div>

            <!-- Date Filters Selector -->
            <div class="content-card mb-4">
                <form id="reportsFilterForm" onsubmit="loadReportsData(event)">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase">Reporting Period</label>
                            <select id="report_range_select" class="form-select bg-dark text-white border-secondary form-control-dashboard" onchange="toggleCustomDateFields('reports', this.value)">
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="thisweek" selected>This Week</option>
                                <option value="lastweek">Last Week</option>
                                <option value="thismonth">This Month</option>
                                <option value="lastmonth">Last Month</option>
                                <option value="thisyear">This Year</option>
                                <option value="custom">Custom Date Range</option>
                            </select>
                        </div>
                        <div class="col-md-3 reports_custom_date" style="display:none;">
                            <label class="form-label text-muted small text-uppercase">Start Date</label>
                            <input type="date" id="report_start_date" class="form-control bg-dark text-white border-secondary form-control-dashboard">
                        </div>
                        <div class="col-md-3 reports_custom_date" style="display:none;">
                            <label class="form-label text-muted small text-uppercase">End Date</label>
                            <input type="date" id="report_end_date" class="form-control bg-dark text-white border-secondary form-control-dashboard">
                        </div>
                        <div class="col-md-3 d-flex align-items-end justify-content-end ms-md-1">
                            <button type="submit" class="btn btn-gold-action btn-action-wide"><i class="fas fa-sync-alt me-1"></i><span>Update Report</span></button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- BI Overview Summary Cards -->
            <div class="metric-grid mb-4" id="reports-metrics-grid">
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Total Revenue</h5>
                        <div class="value text-gold" id="rep_revenue">₹0.00</div>
                        <small id="rep_revenue_growth" class="text-success"><i class="fas fa-caret-up"></i> 0% vs last period</small>
                    </div>
                    <div class="metric-icon"><i class="fas fa-wallet"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Completed Orders</h5>
                        <div class="value" id="rep_orders">0</div>
                        <small id="rep_orders_growth" class="text-success"><i class="fas fa-caret-up"></i> 0% vs last period</small>
                    </div>
                    <div class="metric-icon"><i class="fas fa-shopping-cart"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Average Order Value</h5>
                        <div class="value" id="rep_aov">₹0.00</div>
                        <small id="rep_aov_growth" class="text-success"><i class="fas fa-caret-up"></i> 0% vs last period</small>
                    </div>
                    <div class="metric-icon"><i class="fas fa-calculator"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Performance Score</h5>
                        <div class="value text-gold" id="rep_perf_score" style="font-size: 2.2rem; font-weight: 800;">0/100</div>
                        <small class="text-muted">Acceptance & Completion rate</small>
                    </div>
                    <div class="metric-icon"><i class="fas fa-award"></i></div>
                </div>
            </div>

            <!-- Visual Analytics Grid (Charts) -->
            <div class="row mb-4">
                <div class="col-lg-7 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Sales Revenue Trend</div>
                        <div style="height: 320px; position: relative;">
                            <canvas id="repSalesChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Payment Breakup</div>
                        <div style="height: 320px; position: relative; display: flex; justify-content: center; align-items: center;">
                            <canvas id="repPaymentChart" style="max-height: 280px; max-width: 280px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-lg-6 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Category Performance Analysis</div>
                        <div style="height: 320px; position: relative; display: flex; justify-content: center; align-items: center;">
                            <canvas id="repCategoryChart" style="max-height: 280px; max-width: 280px;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Operations & Order Analytics</div>
                        <div class="p-3">
                            <div class="row text-center mb-4">
                                <div class="col-6 mb-3">
                                    <h6 class="text-muted small text-uppercase">Online Orders</h6>
                                    <h4 class="text-white" id="rep_op_online">0</h4>
                                </div>
                                <div class="col-6 mb-3">
                                    <h6 class="text-muted small text-uppercase">Dine-In Orders</h6>
                                    <h4 class="text-white" id="rep_op_dinein">0</h4>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-muted small text-uppercase">Acceptance Rate</h6>
                                    <h4 class="text-success" id="rep_op_acceptance">0%</h4>
                                </div>
                                <div class="col-6">
                                    <h6 class="text-muted small text-uppercase">Completion Rate</h6>
                                    <h4 class="text-success" id="rep_op_completion">0%</h4>
                                </div>
                            </div>
                            <hr class="border-secondary mb-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Customers Reached:</span>
                                <strong id="rep_cust_total">0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>New Guest Registrations:</span>
                                <strong id="rep_cust_new">0</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Returning Customer Base:</span>
                                <strong id="rep_cust_returning">0</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Guest Retention Rate:</span>
                                <strong class="text-gold" id="rep_cust_retention">0%</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Best Sellers & Customers Breakdown Table -->
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Best Selling Dishes Breakdown</div>
                        <div class="table-responsive" style="max-height: 350px;">
                            <table class="table premium-table align-middle" id="rep-dishes-table">
                                <thead>
                                    <tr>
                                        <th>Dish Name</th>
                                        <th>Qty Sold</th>
                                        <th>Revenue Generated</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Top Performing Customers</div>
                        <div class="table-responsive" style="max-height: 350px;">
                            <table class="table premium-table align-middle" id="rep-customers-table">
                                <thead>
                                    <tr>
                                        <th>Name / Phone</th>
                                        <th>Orders</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CAREERS TAB ==================== -->
        <div id="careers-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Career Applications</h1>
                <p class="page-subtitle">Manage, filter, and track applications submitted to the Medusa recruitment portal</p>
            </div>

            <!-- Metrics grid for Careers -->
            <div class="metric-grid mb-4">
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Total Applications</h5>
                        <div class="value text-white" id="careers_total_metric">0</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-folder-open"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Pending Review</h5>
                        <div class="value text-warning" id="careers_pending_metric">0</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-clock"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Shortlisted</h5>
                        <div class="value text-success" id="careers_shortlisted_metric">0</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-user-check"></i></div>
                </div>
                <div class="metric-card">
                    <div class="metric-info">
                        <h5>Rejected</h5>
                        <div class="value text-danger" id="careers_rejected_metric">0</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-user-times"></i></div>
                </div>
            </div>

            <!-- Filters Box -->
            <div class="content-card mb-4">
                <form id="careersFilterForm" onsubmit="performCareersSearch(event)">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label text-muted small text-uppercase">Search Candidate</label>
                            <div class="premium-search-group">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="career_search_input" class="form-control form-control-dashboard" placeholder="Name, Email, Mobile or City...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Position</label>
                            <select id="career_position_filter" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All Positions</option>
                                <option value="Waiter">Waiter</option>
                                <option value="Captain">Captain</option>
                                <option value="Head Chef">Head Chef</option>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Cleaning Staff">Cleaning Staff</option>
                                <option value="CDP (Chef de Partie)">CDP (Chef de Partie)</option>
                                <option value="Barista">Barista</option>
                                <option value="Commis 1">Commis 1</option>
                                <option value="Commis 2">Commis 2</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small text-uppercase">Status</label>
                            <select id="career_status_filter" class="form-select bg-dark text-white border-secondary form-control-dashboard">
                                <option value="all">All Statuses</option>
                                <option value="Pending">Pending</option>
                                <option value="Reviewed">Reviewed</option>
                                <option value="Shortlisted">Shortlisted</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-gold-action btn-action-full"><i class="fas fa-filter me-1"></i>Filter</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Applications Table -->
            <div class="content-card">
                <div class="card-header-premium">Applications List</div>
                <div class="table-responsive">
                    <table class="table premium-table align-middle" id="careers-table">
                        <thead>
                            <tr>
                                <th>Applicant Details</th>
                                <th>Position</th>
                                <th>Experience</th>
                                <th>Salary (Expected)</th>
                                <th style="min-width: 120px;">Resume</th>
                                <th>Status</th>
                                <th style="min-width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="careers-table-body">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Loading applications...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ==================== NOTIFICATIONS TAB ==================== -->
        <div id="notifications-tab" class="tab-panel">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">Notification Center</h1>
                    <p class="page-subtitle">Track payments, reservations, kitchen status, orders, and system logs in real-time</p>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="sound-toggle-container">
                        <i class="fas fa-volume-up" id="soundToggleIcon" style="color: var(--gold); font-size: 1.1rem;"></i>
                        <span>Order Sound Chime:</span>
                        <div class="form-check form-switch m-0" style="padding-left: 2.5em;">
                            <input class="form-check-input" type="checkbox" id="notificationSoundToggle" checked style="cursor: pointer; width: 2.5em; height: 1.25em;" onchange="toggleSoundPreference(this.checked)">
                        </div>
                    </div>
                    <button class="btn btn-outline-light btn-sm d-flex align-items-center gap-1" style="border-color: rgba(255,255,255,0.08); background: var(--bg-secondary); color: var(--gold);" onclick="fetchNotificationsPage(0)" title="Refresh Notifications">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-gold-action btn-sm d-flex align-items-center gap-1" onclick="markAllNotificationsRead(event)" title="Mark all read">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </div>
            </div>

            <!-- Filters & Search Box -->
            <div class="content-card mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <div class="premium-search-group m-0">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" id="notif_search_input" class="form-control form-control-dashboard" placeholder="Search notification title or details..." oninput="handleNotifSearchInput(event)">
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="d-flex flex-wrap gap-2 justify-content-md-end" id="notif_filter_buttons_container">
                            <button class="notif-filter-btn active" data-filter="all" onclick="setNotifFilter('all')">All</button>
                            <button class="notif-filter-btn" data-filter="order" onclick="setNotifFilter('order')"><i class="fas fa-receipt me-1"></i>Orders</button>
                            <button class="notif-filter-btn" data-filter="payment" onclick="setNotifFilter('payment')"><i class="fas fa-wallet me-1"></i>Payments</button>
                            <button class="notif-filter-btn" data-filter="kitchen" onclick="setNotifFilter('kitchen')"><i class="fas fa-fire-burner me-1"></i>Kitchen</button>
                            <button class="notif-filter-btn" data-filter="reservation" onclick="setNotifFilter('reservation')"><i class="fas fa-chair me-1"></i>Reservations</button>
                            <button class="notif-filter-btn" data-filter="staff" onclick="setNotifFilter('staff')"><i class="fas fa-user-tie me-1"></i>Staff</button>
                            <button class="notif-filter-btn" data-filter="system" onclick="setNotifFilter('system')"><i class="fas fa-cogs me-1"></i>System</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications Table List -->
            <div class="content-card">
                <div class="card-header-premium d-flex justify-content-between align-items-center">
                    <span>Notifications Log</span>
                    <span id="notif_total_badge" class="badge" style="font-size: 0.8rem; background: rgba(223, 186, 134, 0.15); color: var(--gold); border: 1px solid rgba(223, 186, 134, 0.2); font-weight: 700;">0 total</span>
                </div>
                <div class="table-responsive">
                    <table class="table premium-table align-middle" id="notifications-center-table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Type</th>
                                <th>Details</th>
                                <th style="width: 180px;">Received At</th>
                                <th style="width: 150px; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="notifications-table-body">
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="spinner-border text-gold" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination Footer -->
                <div class="d-flex justify-content-between align-items-center p-3 border-top border-secondary flex-wrap gap-2" id="notif_pagination_container" style="border-top-color: rgba(255,255,255,0.06) !important;">
                    <div class="text-muted small" id="notif_pagination_info">
                        Showing 0 to 0 of 0 entries
                    </div>
                    <nav aria-label="Page navigation" id="notif_pagination_nav">
                        <ul class="pagination pagination-sm m-0" id="notif_pagination_list">
                            <!-- Dynamically populated -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- ==================== SETTINGS TAB ==================== -->
        <div id="settings-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Restaurant Settings</h1>
                <p class="page-subtitle">Configure core branding, GST rates, and operational parameters</p>
            </div>

            <div class="content-card col-md-8">
                <div class="card-header-premium">Branding & System Configurations</div>
                <form id="settingsForm" onsubmit="saveSettings(event)">
                    <div class="mb-3">
                        <label class="form-label text-muted text-uppercase small">Restaurant Brand Name</label>
                        <input type="text" id="set_restaurant_name" class="form-control form-control-dashboard" value="<?php echo htmlspecialchars($settings['restaurant_name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted text-uppercase small">GST Surcharge Rate (%)</label>
                        <input type="number" id="set_gst_rate" class="form-control form-control-dashboard" value="<?php echo intval($settings['gst_rate']); ?>" required min="0" max="30">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted text-uppercase small">Packing Charges (₹)</label>
                        <input type="number" step="0.01" id="set_packing_charge" class="form-control form-control-dashboard" value="<?php echo isset($settings['packing_charge']) ? floatval($settings['packing_charge']) : 0.00; ?>" required min="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label text-muted text-uppercase small">Operational Business Hours</label>
                        <input type="text" id="set_opening_hours" class="form-control form-control-dashboard" value="<?php echo htmlspecialchars($settings['opening_hours']); ?>" required>
                    </div>

                    <div class="card-header-premium mt-4 pt-4 border-top border-secondary">Loyalty & Tier Configurations</div>
                    
                    <div class="row g-3">
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-muted text-uppercase small">Bronze Discount (%)</label>
                            <input type="number" step="0.1" id="set_bronze_discount" class="form-control form-control-dashboard" value="<?php echo floatval($settings['bronze_discount']); ?>" required min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-muted text-uppercase small">Silver Discount (%)</label>
                            <input type="number" step="0.1" id="set_silver_discount" class="form-control form-control-dashboard" value="<?php echo floatval($settings['silver_discount']); ?>" required min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-muted text-uppercase small">Gold Discount (%)</label>
                            <input type="number" step="0.1" id="set_gold_discount" class="form-control form-control-dashboard" value="<?php echo floatval($settings['gold_discount']); ?>" required min="0" max="100">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-muted text-uppercase small">Platinum Discount (%)</label>
                            <input type="number" step="0.1" id="set_platinum_discount" class="form-control form-control-dashboard" value="<?php echo floatval($settings['platinum_discount']); ?>" required min="0" max="100">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted text-uppercase small">Silver Spend Req. (₹)</label>
                            <input type="number" step="0.01" id="set_silver_threshold" class="form-control form-control-dashboard" value="<?php echo floatval($settings['silver_threshold']); ?>" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted text-uppercase small">Gold Spend Req. (₹)</label>
                            <input type="number" step="0.01" id="set_gold_threshold" class="form-control form-control-dashboard" value="<?php echo floatval($settings['gold_threshold']); ?>" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted text-uppercase small">Platinum Spend Req. (₹)</label>
                            <input type="number" step="0.01" id="set_platinum_threshold" class="form-control form-control-dashboard" value="<?php echo floatval($settings['platinum_threshold']); ?>" required min="0">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted text-uppercase small">Points Earning Rate (%)</label>
                            <input type="number" step="0.1" id="set_points_earning_percent" class="form-control form-control-dashboard" value="<?php echo floatval($settings['points_earning_percent']); ?>" required min="0" max="100">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted text-uppercase small">Inactivity Period (Months)</label>
                            <input type="number" id="set_inactivity_months" class="form-control form-control-dashboard" value="<?php echo intval($settings['inactivity_months']); ?>" required min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-muted text-uppercase small">Inactivity Penalty (%)</label>
                            <input type="number" step="0.1" id="set_inactivity_deduction_percent" class="form-control form-control-dashboard" value="<?php echo floatval($settings['inactivity_deduction_percent']); ?>" required min="0" max="100">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-gold-action mt-3 btn-action-wide">Save Server Config</button>
                </form>
            </div>
        </div>


        <!-- ==================== LIQUOR QUOTA TAB ==================== -->
        <div id="liquor-tab" class="tab-panel">
            <div class="page-header">
                <h1 class="page-title">Liquor Quota & Peg Consumption</h1>
                <p class="page-subtitle">Track customer liquor quotas and record peg consumption</p>
            </div>

            <div class="row">
                <!-- Consume Peg Form -->
                <div class="col-lg-5 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Record Peg Consumption</div>
                        <form id="consumePegForm" onsubmit="adminConsumePeg(event)">
                            <div class="mb-3">
                                <label class="form-label text-muted small text-uppercase">Search Customer</label>
                                <input type="text" id="consume_search_term" class="form-control form-control-dashboard" placeholder="Enter Name, Phone, or Order ID..." required>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-light btn-sm mb-3" id="btn-admin-verify" onclick="loadCustomerBrands()">
                                    <i class="fas fa-search me-1"></i> Search Customer
                                </button>
                            </div>
                            
                            <div id="consume_brand_section" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label text-muted small text-uppercase">Select Liquor Brand</label>
                                    <select id="consume_brand_id" class="form-select form-control-dashboard" required>
                                        <option value="">-- Choose Brand --</option>
                                    </select>
                                </div>
                                <hr style="border-color: rgba(255,255,255,0.08);">
                                <button type="submit" class="btn btn-gold-action w-100 mt-2" id="btn-admin-consume" disabled>
                                    <i class="fas fa-glass-water me-1"></i> Consume 1 Peg
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Active Quotas List -->
                <div class="col-lg-7 mb-4">
                    <div class="content-card h-100">
                        <div class="card-header-premium">Active Customer Quotas</div>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table premium-table align-middle">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Liquor Brand</th>
                                        <th class="text-center">Bottles Left</th>
                                        <th class="text-center">Pegs Left</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="activeQuotasTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fas fa-spinner fa-spin me-2"></i> Loading active quotas...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mass Emails Tab -->
        <div id="mass-emails-tab" class="tab-panel">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title mb-1" style="color:#fff;">Marketing & Newsletters</h1>
                    <p class="page-subtitle" style="color:var(--gray);">Manage your subscribers and send mass emails.</p>
                </div>
                <div class="badge bg-success p-2 px-3" style="font-size: 14px;">
                    <i class="fas fa-users me-2"></i> <?= count($subscribers) ?> Subscribers
                </div>
            </div>

            <div class="row">
                <!-- Compose Email Section -->
                <div class="col-lg-8 mb-4">
                    <div class="metric-card shadow-sm h-100" style="display: block;">
                        <div class="card-header border-bottom border-secondary mb-3 pb-3">
                            <h4 class="mb-0 text-white" style="font-family: 'Playfair Display', serif;"><i class="fas fa-paper-plane text-gold me-2" style="color: #dfba86;"></i> Compose Mass Email</h4>
                        </div>
                        <div class="card-body p-0">
                            <div id="emailAlert" class="alert d-none"></div>
                            <form id="massEmailForm">
                                <div class="mb-3">
                                    <label class="form-label" style="color: var(--gray);">Email Subject</label>
                                    <input type="text" class="form-control" name="subject" required placeholder="e.g. Join us for a VIP Wine Tasting Event!" style="background: #2a2a2a; border: 1px solid rgba(255,255,255,0.1); color: #fff;">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" style="color: var(--gray);">Message Content</label>
                                    <textarea class="form-control" name="message" rows="8" required placeholder="Write your beautiful newsletter here..." style="background: #2a2a2a; border: 1px solid rgba(255,255,255,0.1); color: #fff;"></textarea>
                                </div>
                                <button type="submit" class="btn px-4 py-2" id="sendBtn" style="background: #dfba86; color: #121212; font-weight: 600;">
                                    <i class="fas fa-paper-plane me-2"></i> Send to All Subscribers
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Subscribers List -->
                <div class="col-lg-4 mb-4">
                    <div class="metric-card shadow-sm h-100" style="display: block; padding: 0;">
                        <div class="card-header border-bottom border-secondary p-3">
                            <h4 class="mb-0 text-white" style="font-family: 'Playfair Display', serif;"><i class="fas fa-list text-gold me-2" style="color: #dfba86;"></i> Subscriber List</h4>
                        </div>
                        <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-dark table-hover mb-0" style="background: transparent;">
                                <thead style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="color: var(--gray); font-weight: 500; border-bottom: 1px solid rgba(255,255,255,0.1); background: var(--bg-secondary);">Email Address</th>
                                        <th class="text-end" style="color: var(--gray); font-weight: 500; border-bottom: 1px solid rgba(255,255,255,0.1); background: var(--bg-secondary);">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($subscribers) > 0): ?>
                                        <?php foreach ($subscribers as $sub): ?>
                                            <tr>
                                                <td style="border-bottom: 1px solid rgba(255,255,255,0.05);"><?= htmlspecialchars($sub['email']) ?></td>
                                                <td class="text-end text-muted" style="font-size:0.85rem; border-bottom: 1px solid rgba(255,255,255,0.05);"><?= date('M j, Y', strtotime($sub['subscribed_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="text-center py-4 text-muted" style="border-bottom: none;">No subscribers yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    </div>

    </div>

    <!-- ==================== MODALS ==================== -->
    
    <!-- Table QR Modal -->
    <div class="modal" id="tableQRModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair" id="qrModalTitle">Table Config</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="qr-card-view p-4">
                        <div class="qr-title-text" id="qrTableLabel">Table T01</div>
                        <p class="qr-sub">Scan to land automatically on ordering menu</p>
                        
                        <div id="qrCodeContainer" class="d-flex justify-content-center p-3 bg-white rounded w-50 mx-auto mb-3">
                            <!-- QR code generated here -->
                        </div>
                        
                        <div class="d-flex justify-content-center gap-2 mt-2">
                            <a id="qrOpenLink" href="#" target="_blank" class="btn btn-sm btn-outline-light"><i class="fas fa-external-link-alt"></i> Open Menu</a>
                            <button id="btnDineInAct" class="btn btn-sm btn-gold-action"><i class="fas fa-plus"></i> Open Dine-In Bill</button>
                            <button onclick="printTableQR()" class="btn btn-sm btn-outline-secondary text-white"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settle Bill Modal -->
    <div class="modal" id="settleBillModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair">Settle Dine-In Invoice</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="settleBillForm" onsubmit="submitSettleBill(event)">
                    <div class="modal-body">
                        <input type="hidden" id="settle_order_id">
                        
                        <div class="mb-3 text-center">
                            <h6 class="text-muted">Total Amount Due</h6>
                            <h2 class="text-gold" id="settle_bill_total">₹0.00</h2>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Select Settlement Payment Method</label>
                            <select id="settle_payment_method" class="form-select bg-dark text-white border-secondary" required>
                                <option value="cash">CASH</option>
                                <option value="upi">UPI (GPay/Paytm)</option>
                                <option value="card">CREDIT/DEBIT CARD</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="submit" class="btn btn-gold-action btn-action-full">Settle & Mark Paid</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Table Item Modal -->
    <div class="modal" id="addTableItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair">Add Dish to Table</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addTableItemForm" onsubmit="submitAddTableItem(event)">
                    <div class="modal-body">
                        <input type="hidden" id="add_table_order_id">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Select Food Dish</label>
                            <select id="add_table_food_id" class="form-select bg-dark text-white border-secondary" required>
                                <?php foreach ($menu_list as $dish): ?>
                                    <option value="<?php echo $dish['id']; ?>"><?php echo htmlspecialchars($dish['name']); ?> (₹<?php echo number_format($dish['price'], 2); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Quantity</label>
                            <input type="number" id="add_table_qty" class="form-control bg-dark text-white border-secondary" value="1" min="1" required>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="submit" class="btn btn-gold-action btn-action-full">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cover Letter Viewer Modal -->
    <div class="modal" id="coverLetterModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair text-gold">Cover Letter / Message</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:1.5rem; white-space:pre-wrap; font-size:0.9rem;" id="coverLetterContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resume Viewer Modal -->
    <div class="modal" id="resumeViewerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 900px;">
            <div class="modal-content bg-dark border-secondary text-white" style="border: 1px solid rgba(223, 186, 134, 0.25) !important;">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair text-gold"><i class="fas fa-file-lines me-2"></i>Resume Viewer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" id="resumeViewerBody" style="min-height: 600px; display: flex; align-items: center; justify-content: center; background: #141311;">
                    <!-- Content will be injected dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== CUSTOMIZATION MANAGER MODAL ==================== -->
    <div class="modal" id="customizationManagerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content text-white" style="background:#121111; border:1px solid rgba(223,186,134,0.25);">
                <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,0.07);">
                    <div>
                        <h5 class="modal-title font-playfair text-gold" id="custManagerTitle">Manage Customizations</h5>
                        <small class="text-muted" id="custManagerSubtitle">Loading...</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height:70vh; overflow-y:auto;">

                    <!-- Existing customization groups -->
                    <div id="existingCustomizationsContainer"></div>

                    <!-- Divider -->
                    <hr style="border-color:rgba(255,255,255,0.07); margin:1.5rem 0;">

                    <!-- Add New Customization Group -->
                    <div style="background:rgba(223,186,134,0.05); border:1px dashed rgba(223,186,134,0.25); border-radius:12px; padding:1.5rem;">
                        <h6 class="text-gold mb-3"><i class="fas fa-plus-circle me-2"></i>Add New Customization Group</h6>
                        <form id="addCustomGroupForm" onsubmit="submitCustomGroup(event)">
                            <input type="hidden" id="cust_food_item_id">
                            <input type="hidden" id="cust_group_edit_id" value="">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small text-uppercase">Group Name</label>
                                    <input type="text" id="cust_group_name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Crust Type, Size, Toppings" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted small text-uppercase">Selection Type</label>
                                    <select id="cust_group_type" class="form-select bg-dark text-white border-secondary">
                                        <option value="single">Single Choice (Radio)</option>
                                        <option value="multiple">Multi-Select (Checkbox)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label text-muted small text-uppercase">Required?</label>
                                    <select id="cust_group_required" class="form-select bg-dark text-white border-secondary">
                                        <option value="0">Optional</option>
                                        <option value="1">Required</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Options Builder -->
                            <div class="mt-3">
                                <label class="form-label text-muted small text-uppercase">Options <span class="text-gold">(add at least 1)</span></label>
                                <div id="optionsBuilderContainer"></div>
                                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="addOptionRow()">
                                    <i class="fas fa-plus"></i> Add Option
                                </button>
                            </div>

                            <div class="mt-3 d-flex gap-2">
                                <button type="submit" class="btn btn-gold-action btn-action-wide" id="custGroupSubmitBtn">Save Group</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetCustomGroupForm()">Cancel / Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <!-- Manage Categories Modal -->
    <div class="modal" id="manageCategoriesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair"><i class="fas fa-tags text-gold me-2"></i>Manage Categories</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Drag and drop categories to reorder them on the public menu. You can also edit names inline. Deleting is only allowed for unused categories.</p>
                    
                    <div class="mb-4" style="max-height: 300px; overflow-y: auto;">
                        <ul id="categories-sortable-list" class="list-group bg-dark border-secondary">
                            <!-- Items populated dynamically by JS -->
                        </ul>
                    </div>

                    <div class="input-group">
                        <input type="text" id="new-category-input" class="form-control bg-dark text-white border-secondary" placeholder="New category name (e.g. Desserts)">
                        <button type="button" class="btn btn-gold-action" id="add-category-btn" onclick="addNewCategoryRow()">
                            <i class="fas fa-plus me-1"></i> Add
                        </button>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-gold-action" onclick="saveCategoriesSequence()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reorder Dishes Modal -->
    <div class="modal" id="reorderDishesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair"><i class="fas fa-sort text-gold me-2"></i>Reorder Dishes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Select Category to Reorder</label>
                        <select id="reorder-category-select" class="form-select bg-dark text-white border-secondary" onchange="loadDishesForReordering(this.value)">
                            <option value="">-- Choose Category --</option>
                            <!-- Dynamically loaded -->
                        </select>
                    </div>

                    <p class="text-muted small mb-2" id="reorder-dishes-instruction" style="display:none;">Drag and drop the dishes below to change their visual sequence on the menu card.</p>
                    
                    <div class="position-relative mb-2" style="max-height: 400px; overflow-y: auto;">
                        <ul id="dishes-sortable-list" class="list-group bg-dark border-secondary">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-gold-action" id="save-dishes-order-btn" onclick="saveDishesSequence()" disabled>Save Order</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu CRUD Modal -->
    <div class="modal" id="menuCrudModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title font-playfair" id="menuModalTitle">Add Menu Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="menuCrudForm" onsubmit="submitMenuCrud(event)">
                    <div class="modal-body">
                        <input type="hidden" id="menu_item_id">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Dish Name</label>
                            <input type="text" id="menu_name" class="form-control bg-dark text-white border-secondary" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted">Category</label>
                            <select id="menu_category" class="form-select bg-dark text-white border-secondary" required>
                                <option value="Beverages">Beverages</option>
                                <option value="Soups">Soups</option>
                                <option value="Salad">Salad</option>
                                <option value="Bread Basket">Bread Basket</option>
                                <option value="Sides">Sides</option>
                                <option value="Meals in the Bowl">Meals in the Bowl</option>
                                <option value="Main Course">Main Course</option>
                                <option value="Chinese & Korean">Chinese & Korean</option>
                                <option value="Indian">Indian</option>
                                <option value="Dim Sum Cart">Dim Sum Cart</option>
                                <option value="Sushi Rolls">Sushi Rolls</option>
                                <option value="Burgers & Sandwiches">Burgers & Sandwiches</option>
                                <option value="Sharing Boards">Sharing Boards</option>
                                <option value="Brick Oven Pizza">Brick Oven Pizza</option>
                                <option value="Non-Veg Appetizer">Non-Veg Appetizer</option>
                                <option value="Pasta & Risotto Station">Pasta & Risotto Station</option>
                                <option value="Veg Appetizer">Veg Appetizer</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Subcategory (Optional)</label>
                            <input type="text" id="menu_subcategory" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Liqueur, Gin, Vodka, Whiskey">
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Diet Type</label>
                            <select id="menu_diet_type" class="form-select bg-dark text-white border-secondary" required>
                                <option value="veg">Veg</option>
                                <option value="nonveg">Non-Veg</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Base Price (INR)</label>
                            <input type="number" step="0.01" id="menu_price" class="form-control bg-dark text-white border-secondary" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Description</label>
                            <textarea id="menu_description" rows="2" class="form-control bg-dark text-white border-secondary" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Dish Image</label>
                            
                            <!-- Source Selector Tab Buttons -->
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn-outline-gold active" id="btn_notif_img_upload" onclick="switchImageSource('upload')">
                                    <i class="fas fa-upload me-1"></i>Upload File
                                </button>
                                <button type="button" class="btn-outline-gold" id="btn_notif_img_url" onclick="switchImageSource('url')">
                                    <i class="fas fa-link me-1"></i>Image URL
                                </button>
                            </div>
                            
                            <!-- File Drag & Drop Zone -->
                            <div id="image_upload_container" class="image-dropzone-premium" onclick="document.getElementById('dish_image_file').click()">
                                <i class="fas fa-cloud-upload-alt dropzone-icon"></i>
                                <div class="dropzone-text">Drag & drop image here, or <span>browse</span></div>
                                <div class="dropzone-subtext">Supports all image formats (Max 20MB)</div>
                            </div>
                            <input type="file" id="dish_image_file" accept="image/*" style="display: none;" onchange="handleImageFileSelect(this)">
                            
                            <!-- Text Input for URL -->
                            <div id="image_url_container" style="display: none;">
                                <input type="text" id="menu_image_url" class="form-control bg-dark text-white border-secondary" placeholder="https://example.com/image.jpg" oninput="updateImagePreview(this.value)">
                                <div id="image_url_warning" class="text-danger small mt-1" style="display: none;"><i class="fas fa-exclamation-circle me-1"></i>Unable to load image preview. Please make sure this is a direct image link or Google Drive file sharing link (folders are not supported).</div>
                            </div>
                            
                            <!-- Dynamic Preview Thumbnail -->
                            <div id="image_preview_wrapper" style="display: none; margin-top: 10px; position: relative; width: 100%; height: 120px; border-radius: 8px; overflow: hidden; border: 1px solid rgba(223, 186, 134, 0.2);">
                                <img id="dish_image_preview" src="" style="width: 100%; height: 100%; object-fit: cover;">
                                <button type="button" class="btn btn-sm btn-danger d-flex align-items-center justify-content-center" style="position: absolute; top: 5px; right: 5px; width: 25px; height: 25px; border-radius: 50%; padding: 0; border: none;" onclick="removeDishImage(event)" title="Remove Image">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="submit" class="btn btn-gold-action btn-action-full" id="btnMenuSubmit">Save Dish</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>


    <!-- Bootstrap & jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables for active table tracking
        let activeDineInOrder = null;
        let selectedTableQR = null;

        // Dark/Light Theme Switching System
        function updateThemeUI() {
            const isLight = document.documentElement.classList.contains('light-mode');
            const icon = document.getElementById('themeIcon');
            const btn = document.getElementById('themeToggleBtn');
            
            if (isLight) {
                if (icon) {
                    icon.className = 'fas fa-sun';
                    icon.style.color = 'var(--gold)';
                }
                if (btn) {
                    btn.style.background = 'var(--bg-secondary)';
                    btn.style.borderColor = 'rgba(0, 0, 0, 0.08)';
                    btn.style.boxShadow = '0 4px 15px rgba(0,0,0,0.06)';
                }
            } else {
                if (icon) {
                    icon.className = 'fas fa-moon';
                    icon.style.color = 'var(--gold)';
                }
                if (btn) {
                    btn.style.background = 'var(--bg-secondary)';
                    btn.style.borderColor = 'rgba(255, 255, 255, 0.08)';
                    btn.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
                }
            }
            updateChartTheme();
        }

        function toggleTheme() {
            if (document.documentElement.classList.contains('light-mode')) {
                document.documentElement.classList.remove('light-mode');
                localStorage.setItem('medusa_admin_theme', 'dark');
            } else {
                document.documentElement.classList.add('light-mode');
                localStorage.setItem('medusa_admin_theme', 'light');
            }
            updateThemeUI();
        }

        function updateChartTheme() {
            if (!window.salesChartInstance) return;
            const isLight = document.documentElement.classList.contains('light-mode');
            const gridColor = isLight ? 'rgba(0, 0, 0, 0.05)' : 'rgba(255, 255, 255, 0.05)';
            const tickColor = isLight ? '#64748b' : '#a09f9f';
            
            window.salesChartInstance.options.scales.x.grid.color = gridColor;
            window.salesChartInstance.options.scales.x.ticks.color = tickColor;
            window.salesChartInstance.options.scales.y.grid.color = gridColor;
            window.salesChartInstance.options.scales.y.ticks.color = tickColor;
            window.salesChartInstance.update();
        }

        // Switch Sidebar Tabs
        function switchTab(tabId, el) {
            // Remove active classes
            document.querySelectorAll('.sidebar-link').forEach(link => link.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            
            // Add active class
            if (el) el.classList.add('active');
            const panel = document.getElementById(tabId);
            if (panel) panel.classList.add('active');
            
            // Save active tab to localStorage
            localStorage.setItem('medusa_active_admin_tab', tabId);
            
            // If kitchen panel is active, start live polling
            if (tabId === 'kitchen-tab') {
                startKitchenPolling();
            } else {
                stopKitchenPolling();
            }

            // If liquor quota panel is active, reload active quotas list
            if (tabId === 'liquor-tab') {
                loadActiveQuotas();
            }
            
            if (tabId === 'reports-tab') {
                setTimeout(() => { if(typeof loadReportsData === 'function') loadReportsData(null); }, 150);
            }
            
            if (tabId === 'campaigns-tab') {
                setTimeout(() => { if(typeof fetchCampaigns === 'function') fetchCampaigns(); }, 150);
            }
        }

        // 1. Chart.js Sales Graph
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            window.salesChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Sales Revenue (₹)',
                        data: <?php echo json_encode($chart_data); ?>,
                        borderColor: '#dfba86',
                        backgroundColor: 'rgba(223, 186, 134, 0.1)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#a09f9f' }
                        },
                        x: {
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#a09f9f' }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
            updateChartTheme(); // apply theme colors to chart immediately
        }

        // Initialize theme UI state and restore active tab on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            updateThemeUI();
            
            // Move all modals to the root body element to fix Bootstrap z-index backdrop bugs
            document.querySelectorAll('.modal').forEach(m => document.body.appendChild(m));

            // Restore active tab
            const activeTab = localStorage.getItem('medusa_active_admin_tab');
            if (activeTab) {
                const sidebarLink = document.querySelector(`.sidebar-link[onclick*="${activeTab}"]`);
                if (sidebarLink) {
                    switchTab(activeTab, sidebarLink);
                }
            }
            // Remove temporary style tag to allow normal stylesheet rules to take over
            document.getElementById('temp-tab-css')?.remove();
        });

        // 2. Order Status Controls (Online list)
        function updateOrderStatus(id, newStatus) {
            if (!newStatus) return;
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'update_order_status',
                    order_id: id,
                    status: newStatus
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error updating order status');
                }
            });
        }

        // 3. Dine-In Table Selection Loader
        function loadTableOrderDetails(order) {
            activeDineInOrder = order;
            document.getElementById('table-detail-card').style.display = 'block';
            
            // extract table number
            let tbl = 'Unknown';
            if (preg_match = order.delivery_address.match(/Table\s+([A-Za-z0-9]+)/i)) {
                tbl = preg_match[1];
            }
            
            document.getElementById('detail-table-title').textContent = 'Table ' + tbl + ' Order Details';
            
            const badge = document.getElementById('detail-table-status');
            badge.className = 'status-badge status-' + order.order_status.toLowerCase();
            badge.textContent = order.order_status;
            
            // Fetch items
            fetch('dashboardtest.php?action=get_kitchen_orders')
            .then(res => res.json())
            .then(data => {
                const updatedOrder = data.orders.find(o => o.id == order.id);
                if (updatedOrder) {
                    activeDineInOrder = updatedOrder;
                    renderTableItems(updatedOrder.items, updatedOrder.total_amount);
                } else {
                    // Fallback to active order items
                    renderTableItems([], order.total_amount);
                }
            });
        }

        function renderTableItems(items, total) {
            const tbody = document.getElementById('detail-table-items');
            tbody.innerHTML = '';
            
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No items in this order yet.</td></tr>';
            } else {
                items.forEach(it => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><strong>${it.item_name}</strong></td>
                        <td>${it.quantity}</td>
                        <td>₹${parseFloat(it.price).toFixed(2)}</td>
                        <td>₹${(it.price * it.quantity).toFixed(2)}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
            document.getElementById('detail-table-total').textContent = '₹' + parseFloat(total).toFixed(2);
        }

        // 4. Dine-in Order Modification
        function openAddTableItemModal() {
            if (!activeDineInOrder) return;
            document.getElementById('add_table_order_id').value = activeDineInOrder.id;
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addTableItemModal'));
            modal.show();
        }

        function submitAddTableItem(e) {
            e.preventDefault();
            const order_id = document.getElementById('add_table_order_id').value;
            const food_item_id = document.getElementById('add_table_food_id').value;
            const quantity = document.getElementById('add_table_qty').value;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'add_table_item',
                    order_id: order_id,
                    food_item_id: food_item_id,
                    quantity: quantity
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addTableItemModal')).hide();
                    alert('Item added successfully!');
                    // Reload table details
                    loadTableOrderDetails(activeDineInOrder);
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        // 5. Dine-in Settle Invoice
        function openBillSettleModal() {
            if (!activeDineInOrder) return;
            document.getElementById('settle_order_id').value = activeDineInOrder.id;
            document.getElementById('settle_bill_total').textContent = '₹' + parseFloat(activeDineInOrder.total_amount).toFixed(2);
            
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('settleBillModal'));
            modal.show();
        }

        function submitSettleBill(e) {
            e.preventDefault();
            const order_id = document.getElementById('settle_order_id').value;
            const method = document.getElementById('settle_payment_method').value;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'settle_bill',
                    order_id: order_id,
                    payment_method: method
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('settleBillModal')).hide();
                    alert('Invoice successfully settled & table released!', () => {
                        location.reload();
                    });
                } else {
                    alert('Error settling bill');
                }
            });
        }

        // 6. QR Code Configuration Dialog
        function openTableQRModal(tableCode, isOccupied) {
            selectedTableQR = tableCode;
            document.getElementById('qrModalTitle').textContent = 'Table ' + tableCode + ' Configuration';
            document.getElementById('qrTableLabel').textContent = 'Table ' + tableCode;
            
            // Generate QR Code targeting the menu page with this table number prefilled
            // Use PHP to inject the real local Wi-Fi IP address so smartphones can reach it instead of looking for 'localhost'
            const networkIp = '<?php echo gethostbyname(gethostname()); ?>';
            const serverPort = window.location.port ? ':' + window.location.port : '';
            
            // Extract the path by removing /admintest/dashboardtest.php from current pathname
            const basePath = window.location.pathname.replace('/admintest/dashboardtest.php', '');
            const menuUrl = `http://${networkIp}${serverPort}${basePath}/menutest.html?table=${tableCode}`;
            
            const qrContainer = document.getElementById('qrCodeContainer');
            qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(menuUrl)}" alt="QR Code" style="width: 150px; height: 150px;">`;
            
            document.getElementById('qrOpenLink').href = menuUrl;
            
            const btnAct = document.getElementById('btnDineInAct');
            if (isOccupied) {
                btnAct.textContent = 'View Active Order';
                btnAct.className = 'btn btn-sm btn-gold-action';
                btnAct.onclick = () => {
                    bootstrap.Modal.getInstance(document.getElementById('tableQRModal')).hide();
                    switchTab('orders-tab', document.querySelector('.sidebar-link[onclick*="orders-tab"]'));
                    // Load table active order
                    // Look up order matching tableCode
                    fetch('dashboardtest.php?action=get_kitchen_orders')
                    .then(res => res.json())
                    .then(data => {
                        const ord = data.orders.find(o => o.delivery_address.includes('Table ' + tableCode));
                        if (ord) {
                            loadTableOrderDetails(ord);
                        } else {
                            alert('No active order data found');
                        }
                    });
                };
            } else {
                btnAct.textContent = 'Open New Dine-In Order';
                btnAct.className = 'btn btn-sm btn-outline-light';
                btnAct.onclick = () => {
                    const custName = prompt('Enter Guest Name (Optional):', 'Guest');
                    if (custName === null) return;
                    
                    fetch('dashboardtest.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'create_dinein_order',
                            table_code: tableCode,
                            customer_name: custName
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            bootstrap.Modal.getInstance(document.getElementById('tableQRModal')).hide();
                            alert('New table order successfully opened!', () => {
                                location.reload();
                            });
                        } else {
                            alert('Error opening table order');
                        }
                    });
                };
            }

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('tableQRModal'));
            modal.show();
        }

        // 7. Kitchen Panel Live Polling Logic
        let kitchenInterval = null;
        
        function startKitchenPolling() {
            loadKitchenOrders();
            kitchenInterval = setInterval(loadKitchenOrders, 5000); // Poll every 5 seconds
        }
        
        function stopKitchenPolling() {
            if (kitchenInterval) {
                clearInterval(kitchenInterval);
                kitchenInterval = null;
            }
        }
        
        function loadKitchenOrders() {
            fetch('dashboardtest.php?action=get_kitchen_orders')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderKitchenColumn(data.orders.filter(o => o.order_status.toLowerCase() === 'pending'), 'kitchen-pending-list', 'pending');
                    renderKitchenColumn(data.orders.filter(o => o.order_status.toLowerCase() === 'preparing'), 'kitchen-preparing-list', 'preparing');
                    renderKitchenColumn(data.orders.filter(o => o.order_status.toLowerCase() === 'ready'), 'kitchen-ready-list', 'ready');
                    // Re-apply search filter after every refresh so results don't vanish
                    filterKitchenOrders();
                }
            });
        }
        
        function renderKitchenColumn(orders, containerId, columnType) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            // Update counter badge
            document.getElementById('count-kitchen-' + columnType).textContent = orders.length;
            
            if (orders.length === 0) {
                container.innerHTML = `<div class="text-center text-muted py-5">No orders in this state.</div>`;
                return;
            }
            
            orders.forEach(order => {
                const card = document.createElement('div');
                card.className = 'kitchen-card';
                
                const itemsList = order.items.map(it => `<li>${it.item_name} <strong>x${it.quantity}</strong></li>`).join('');
                
                const isDineIn = order.delivery_address && order.delivery_address.toLowerCase().startsWith('table');
                const isTakeaway = order.order_type && order.order_type.toLowerCase() === 'takeaway';

                let btn = '';
                if (columnType === 'pending') {
                    btn = `<button class="btn btn-sm btn-gold-action btn-action-full" onclick="updateOrderStatus(${order.id}, 'preparing')">Start Cooking</button>`;
                } else if (columnType === 'preparing') {
                    btn = `<button class="btn btn-sm btn-success w-100 text-dark" onclick="updateOrderStatus(${order.id}, 'ready')">Mark Ready</button>`;
                } else if (columnType === 'ready') {
                    if (isDineIn || isTakeaway) {
                        btn = `<button class="btn btn-sm btn-primary w-100 text-white" onclick="updateOrderStatus(${order.id}, 'completed')">Complete / Serve</button>`;
                    } else {
                        btn = `<button class="btn btn-sm btn-secondary w-100 text-muted" disabled style="cursor: not-allowed; opacity: 0.65;"><i class="fas fa-truck"></i> Awaiting Driver Pickup</button>`;
                    }
                }
                
                let typeBadge = '';
                if (isDineIn) {
                    typeBadge = `<span class="badge bg-danger text-white ms-1" style="font-size: 0.65rem; padding: 2px 6px; vertical-align: middle;"><i class="fas fa-chair"></i> Dine-In</span>`;
                } else if (isTakeaway) {
                    typeBadge = `<span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem; padding: 2px 6px; vertical-align: middle;"><i class="fas fa-shopping-bag"></i> Takeaway</span>`;
                } else {
                    typeBadge = `<span class="badge bg-primary text-white ms-1" style="font-size: 0.65rem; padding: 2px 6px; vertical-align: middle;"><i class="fas fa-truck"></i> Delivery</span>`;
                }

                card.innerHTML = `
                    <div class="kitchen-card-header">
                        <span>#${order.order_number}${typeBadge}</span>
                        <span class="text-gold">${order.delivery_address}</span>
                    </div>
                    <ul class="kitchen-card-items">
                        ${itemsList}
                    </ul>
                    ${btn}
                `;
                container.appendChild(card);
            });
        }

        // ======= CUSTOMIZATION MANAGER =======
        let custFoodItemId = null;

        function openCustomizationManager(foodItemId, dishName) {
            custFoodItemId = foodItemId;
            document.getElementById('custManagerTitle').textContent = 'Customizations: ' + dishName;
            document.getElementById('custManagerSubtitle').textContent = 'Add/remove selection groups (size, crust, toppings, sauce, etc.)';
            document.getElementById('cust_food_item_id').value = foodItemId;
            resetCustomGroupForm();
            loadExistingCustomizations(foodItemId);
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('customizationManagerModal'));
            modal.show();
        }

        function loadExistingCustomizations(foodItemId) {
            const container = document.getElementById('existingCustomizationsContainer');
            container.innerHTML = '<div class="text-muted text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>';

            fetch('../api/save-customization.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_customizations', food_item_id: foodItemId })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    container.innerHTML = '<div class="alert alert-warning">Could not load customizations. Please import the updated restaurant_db.sql first.</div>';
                    return;
                }
                if (data.customizations.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-info-circle me-2"></i>No customizations set up for this dish yet.</div>';
                    return;
                }
                container.innerHTML = '';
                data.customizations.forEach(group => {
                    const card = document.createElement('div');
                    card.className = 'mb-3';
                    card.style.cssText = 'background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:10px; padding:1rem;';

                    const optionTags = group.options.map(o => {
                        const priceLabel = o.price_add > 0 ? ` <span style="color:#2ec4b6">+₹${o.price_add}</span>` : (o.price_add < 0 ? ` <span style="color:#ff6b6b">-₹${Math.abs(o.price_add)}</span>` : '');
                        return `<span style="background:rgba(223,186,134,0.1); color:#dfba86; border:1px solid rgba(223,186,134,0.2); border-radius:20px; padding:2px 10px; font-size:0.8rem; display:inline-block; margin:2px;">${o.label}${priceLabel}</span>`;
                    }).join('');

                    const typeBadge = group.group_type === 'multiple' ? '<span class="badge bg-primary ms-2">Multi-Select</span>' : '<span class="badge bg-secondary ms-2">Single Choice</span>';
                    const reqBadge = group.is_required == 1 ? '<span class="badge bg-danger ms-1">Required</span>' : '<span class="badge bg-dark ms-1">Optional</span>';

                    card.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong style="color:#fff;">${group.group_name}</strong>
                                ${typeBadge}${reqBadge}
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-light" onclick="editCustomGroup(${JSON.stringify(group).replace(/"/g,'&quot;')})" title="Edit Group"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteCustomGroup(${group.id})" title="Delete Group"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                        <div>${optionTags}</div>
                    `;
                    container.appendChild(card);
                });
            })
            .catch(() => {
                container.innerHTML = '<div class="alert alert-danger">Failed to load. Check that dish_customizations table exists in your database.</div>';
            });
        }

        function addOptionRow(label = '', priceAdd = 0) {
            const container = document.getElementById('optionsBuilderContainer');
            const idx = container.children.length;
            const row = document.createElement('div');
            row.className = 'd-flex gap-2 mb-2 option-row align-items-center';
            row.innerHTML = `
                <input type="text" class="form-control bg-dark text-white border-secondary option-label" placeholder="Option label (e.g. Thin Crust)" value="${label}" required>
                <input type="number" step="1" class="form-control bg-dark text-white border-secondary option-price" style="max-width:130px;" placeholder="Price (+/-)" value="${priceAdd}" title="Price added to base (0 = free, negative = discount)">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.option-row').remove()" title="Remove"><i class="fas fa-times"></i></button>
            `;
            container.appendChild(row);
        }

        function collectOptionsFromForm() {
            const rows = document.querySelectorAll('#optionsBuilderContainer .option-row');
            const options = [];
            rows.forEach(row => {
                const lbl = row.querySelector('.option-label').value.trim();
                const price = parseFloat(row.querySelector('.option-price').value) || 0;
                if (lbl) options.push({ label: lbl, price_add: price });
            });
            return options;
        }

        function submitCustomGroup(e) {
            e.preventDefault();
            const options = collectOptionsFromForm();
            if (options.length === 0) {
                alert('Please add at least one option to this group.');
                return;
            }

            const editId = document.getElementById('cust_group_edit_id').value;
            const bodyData = {
                action: 'save_customization_group',
                food_item_id: custFoodItemId,
                group_name: document.getElementById('cust_group_name').value,
                group_type: document.getElementById('cust_group_type').value,
                is_required: document.getElementById('cust_group_required').value,
                options_json: JSON.stringify(options),
                sort_order: 0,
                id: editId
            };

            fetch('../api/save-customization.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(bodyData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    resetCustomGroupForm();
                    loadExistingCustomizations(custFoodItemId);
                } else {
                    alert('Error: ' + (data.message || 'Save failed'));
                }
            });
        }

        function editCustomGroup(group) {
            document.getElementById('cust_group_edit_id').value = group.id;
            document.getElementById('cust_group_name').value = group.group_name;
            document.getElementById('cust_group_type').value = group.group_type;
            document.getElementById('cust_group_required').value = group.is_required;
            document.getElementById('custGroupSubmitBtn').textContent = 'Update Group';

            // Clear and repopulate options
            const container = document.getElementById('optionsBuilderContainer');
            container.innerHTML = '';
            (group.options || []).forEach(o => addOptionRow(o.label, o.price_add));

            // Scroll to form
            document.getElementById('addCustomGroupForm').scrollIntoView({ behavior: 'smooth' });
        }

        function deleteCustomGroup(id) {
            if (!confirm('Delete this customization group? This cannot be undone.')) return;
            fetch('../api/save-customization.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete_customization_group', id: id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadExistingCustomizations(custFoodItemId);
                } else {
                    alert('Delete failed');
                }
            });
        }

        function resetCustomGroupForm() {
            document.getElementById('addCustomGroupForm').reset();
            document.getElementById('cust_group_edit_id').value = '';
            document.getElementById('cust_food_item_id').value = custFoodItemId || '';
            document.getElementById('optionsBuilderContainer').innerHTML = '';
            document.getElementById('custGroupSubmitBtn').textContent = 'Save Group';
            // Add one blank option row to start
            addOptionRow();
        }

        // 8. Menu Management CRUD
        function toggleMenuAvailability(id, isChecked) {
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'toggle_menu_item',
                    id: id,
                    val: isChecked ? 1 : 0
                })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert('Failed to update availability status.');
                }
            });
        }

        function switchImageSource(source) {
            const uploadBtn = document.getElementById('btn_notif_img_upload');
            const urlBtn = document.getElementById('btn_notif_img_url');
            const uploadContainer = document.getElementById('image_upload_container');
            const urlContainer = document.getElementById('image_url_container');
            
            if (source === 'upload') {
                if (uploadBtn) uploadBtn.classList.add('active');
                if (urlBtn) urlBtn.classList.remove('active');
                if (uploadContainer) uploadContainer.style.display = 'block';
                if (urlContainer) urlContainer.style.display = 'none';
            } else {
                if (uploadBtn) uploadBtn.classList.remove('active');
                if (urlBtn) urlBtn.classList.add('active');
                if (uploadContainer) uploadContainer.style.display = 'none';
                if (urlContainer) urlContainer.style.display = 'block';
            }
        }

        function handleImageFileSelect(input) {
            const file = input.files[0];
            if (!file) return;

            const dropzone = document.getElementById('image_upload_container');
            const originalHTML = dropzone.innerHTML;
            dropzone.innerHTML = `
                <div class="spinner-border text-gold my-2" role="status" style="width: 1.5rem; height: 1.5rem;">
                    <span class="visually-hidden">Uploading...</span>
                </div>
                <div class="dropzone-text text-gold">Compressing & Uploading image...</div>
            `;

            // If it's a standard image (not GIF or SVG), compress and resize on client side
            const isCompressible = file.type.startsWith('image/') && 
                                   file.type !== 'image/svg+xml' && 
                                   file.type !== 'image/gif';

            if (isCompressible) {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = function (event) {
                    const img = new Image();
                    img.src = event.target.result;
                    img.onload = function () {
                        // Max 1000px width/height to make upload extremely fast
                        const MAX_WIDTH = 1000;
                        const MAX_HEIGHT = 1000;
                        let width = img.width;
                        let height = img.height;

                        if (width > height) {
                            if (width > MAX_WIDTH) {
                                height *= MAX_WIDTH / width;
                                width = MAX_WIDTH;
                            }
                        } else {
                            if (height > MAX_HEIGHT) {
                                width *= MAX_HEIGHT / height;
                                height = MAX_HEIGHT;
                            }
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;

                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);

                        canvas.toBlob(function (blob) {
                            if (!blob) {
                                uploadImageFile(file, file.name, dropzone, originalHTML);
                            } else {
                                const baseName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name;
                                const finalName = baseName + '_compressed.jpg';
                                uploadImageFile(blob, finalName, dropzone, originalHTML);
                            }
                        }, 'image/jpeg', 0.75); // 75% quality JPEG
                    };
                    img.onerror = function () {
                        uploadImageFile(file, file.name, dropzone, originalHTML);
                    };
                };
                reader.onerror = function () {
                    uploadImageFile(file, file.name, dropzone, originalHTML);
                };
            } else {
                uploadImageFile(file, file.name, dropzone, originalHTML);
            }
        }

        function uploadImageFile(fileOrBlob, filename, dropzone, originalHTML) {
            const formData = new FormData();
            formData.append('action', 'upload_dish_image');
            formData.append('dish_image', fileOrBlob, filename);

            fetch('dashboardtest.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('menu_image_url').value = data.image_url;
                    updateImagePreview(data.image_url);
                } else {
                    alert(data.message || 'Upload failed');
                    dropzone.innerHTML = originalHTML;
                }
            })
            .catch(err => {
                console.error("Error uploading image:", err);
                alert("An error occurred during file upload.");
                dropzone.innerHTML = originalHTML;
            });
        }

        function updateImagePreview(url) {
            const previewWrapper = document.getElementById('image_preview_wrapper');
            const previewImg = document.getElementById('dish_image_preview');
            const dropzone = document.getElementById('image_upload_container');
            const warningEl = document.getElementById('image_url_warning');
            
            if (warningEl) warningEl.style.display = 'none';

            if (!url) {
                if (previewWrapper) previewWrapper.style.display = 'none';
                return;
            }
            
            let displayUrl = url;
            if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
                // Pipe external links through local proxy to bypass browser-side CORS and referrer policies
                displayUrl = 'dashboardtest.php?action=proxy_image&url=' + encodeURIComponent(url);
            } else if (url && !url.startsWith('//')) {
                if (url.startsWith('../')) {
                    displayUrl = url;
                } else if (url.startsWith('uploads/')) {
                    displayUrl = '../' + url;
                } else {
                    displayUrl = '../uploads/' + url;
                }
            }

            if (previewImg) {
                previewImg.onload = function() {
                    if (warningEl) warningEl.style.display = 'none';
                };
                previewImg.onerror = function() {
                    if (warningEl) warningEl.style.display = 'block';
                };
                previewImg.src = displayUrl;
            }
            if (previewWrapper) previewWrapper.style.display = 'block';
            
            if (dropzone) {
                dropzone.innerHTML = `
                    <i class="fas fa-cloud-upload-alt dropzone-icon"></i>
                    <div class="dropzone-text">Drag & drop image here, or <span>browse</span></div>
                    <div class="dropzone-subtext">Supports all image formats (Max 20MB)</div>
                `;
            }
        }

        function removeDishImage(event) {
            if (event) event.stopPropagation();
            const urlInput = document.getElementById('menu_image_url');
            if (urlInput) urlInput.value = '';
            const fileInput = document.getElementById('dish_image_file');
            if (fileInput) fileInput.value = '';
            const previewWrapper = document.getElementById('image_preview_wrapper');
            if (previewWrapper) previewWrapper.style.display = 'none';
        }

        function setupImageDragAndDrop() {
            const dropzone = document.getElementById('image_upload_container');
            if (!dropzone) return;

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('dragover');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('dragover');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                
                // 1. Handle dragged files
                if (dt.files && dt.files.length > 0) {
                    const fileInput = document.getElementById('dish_image_file');
                    if (fileInput) {
                        fileInput.files = dt.files;
                        handleImageFileSelect(fileInput);
                    }
                } 
                // 2. Handle dragged URLs from other tabs
                else {
                    const url = dt.getData('text/uri-list') || dt.getData('text/plain');
                    if (url && (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:image/'))) {
                        const urlInput = document.getElementById('menu_image_url');
                        if (urlInput) {
                            urlInput.value = url;
                            switchImageSource('url');
                            updateImagePreview(url);
                        }
                    }
                }
            }, false);

            // 3. Handle Clipboard Paste (Ctrl+V) anywhere inside the CRUD Modal
            const modalEl = document.getElementById('menuCrudModal');
            if (modalEl) {
                modalEl.addEventListener('paste', (e) => {
                    // Check if current focused element is a text input/textarea (like Name or Description)
                    // and allow default paste behavior for those fields
                    const activeEl = document.activeElement;
                    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                        if (activeEl.id !== 'menu_image_url') {
                            return; // Let standard text inputs handle text paste normally
                        }
                    }

                    const clipboardData = e.clipboardData || window.clipboardData;
                    if (!clipboardData) return;

                    // A. Check for pasted files (e.g. screenshots, copied local image file)
                    if (clipboardData.files && clipboardData.files.length > 0) {
                        e.preventDefault();
                        const fileInput = document.getElementById('dish_image_file');
                        if (fileInput) {
                            fileInput.files = clipboardData.files;
                            switchImageSource('upload');
                            handleImageFileSelect(fileInput);
                        }
                    } 
                    // B. Check for pasted URLs/links
                    else {
                        const pastedText = clipboardData.getData('text').trim();
                        if (pastedText && (pastedText.startsWith('http://') || pastedText.startsWith('https://') || pastedText.startsWith('data:image/'))) {
                            e.preventDefault();
                            const urlInput = document.getElementById('menu_image_url');
                            if (urlInput) {
                                urlInput.value = pastedText;
                                switchImageSource('url');
                                updateImagePreview(pastedText);
                            }
                        }
                    }
                });
            }
        }

        function openAddMenuModal() {
            document.getElementById('menuCrudForm').reset();
            document.getElementById('menu_item_id').value = '';
            document.getElementById('menuModalTitle').textContent = 'Add New Dish';
            document.getElementById('btnMenuSubmit').textContent = 'Save Dish';
            document.getElementById('menu_diet_type').value = 'veg';
            
            removeDishImage();
            switchImageSource('upload');
            
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menuCrudModal'));
            modal.show();
        }

        function openEditMenuModal(dish) {
            document.getElementById('menu_item_id').value = dish.id;
            document.getElementById('menu_name').value = dish.name;
            document.getElementById('menu_category').value = dish.category;
            document.getElementById('menu_subcategory').value = dish.subcategory || '';
            document.getElementById('menu_price').value = dish.price;
            document.getElementById('menu_description').value = dish.description;
            document.getElementById('menu_image_url').value = dish.image_url;
            document.getElementById('menu_diet_type').value = dish.diet_type || 'veg';
            
            const fileInput = document.getElementById('dish_image_file');
            if (fileInput) fileInput.value = '';
            
            if (dish.image_url) {
                updateImagePreview(dish.display_image_url || dish.image_url);
                if (dish.image_url.startsWith('uploads/')) {
                    switchImageSource('upload');
                } else {
                    switchImageSource('url');
                }
            } else {
                removeDishImage();
                switchImageSource('upload');
            }
            
            document.getElementById('menuModalTitle').textContent = 'Edit Dish Details';
            document.getElementById('btnMenuSubmit').textContent = 'Update Dish';
            
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('menuCrudModal'));
            modal.show();
        }

        function submitMenuCrud(e) {
            e.preventDefault();
            const id = document.getElementById('menu_item_id').value;
            const action = id ? 'edit_menu_item' : 'add_menu_item';

            const submitBtn = document.getElementById('btnMenuSubmit');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
            
            const bodyData = {
                action: action,
                name: document.getElementById('menu_name').value,
                category: document.getElementById('menu_category').value,
                subcategory: document.getElementById('menu_subcategory').value,
                price: document.getElementById('menu_price').value,
                description: document.getElementById('menu_description').value,
                image_url: document.getElementById('menu_image_url').value || '',
                diet_type: document.getElementById('menu_diet_type').value
            };
            if (id) bodyData.id = id;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(bodyData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('menuCrudModal')).hide();
                    showToast(id ? 'Dish updated successfully!' : 'New dish added successfully!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast('Error saving dish: ' + (data.message || 'Unknown error'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(err => {
                showToast('Network error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        function deleteMenuItem(id) {
            if (!confirm('Are you sure you want to delete this menu dish permanently?')) return;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_menu_item',
                    id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting menu item');
                }
            });
        }

        // 9. Save settings
        function saveSettings(e) {
            e.preventDefault();
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'save_settings',
                    restaurant_name: document.getElementById('set_restaurant_name').value,
                    gst_rate: document.getElementById('set_gst_rate').value,
                    packing_charge: document.getElementById('set_packing_charge').value,
                    opening_hours: document.getElementById('set_opening_hours').value,
                    bronze_discount: document.getElementById('set_bronze_discount').value,
                    silver_discount: document.getElementById('set_silver_discount').value,
                    gold_discount: document.getElementById('set_gold_discount').value,
                    platinum_discount: document.getElementById('set_platinum_discount').value,
                    silver_threshold: document.getElementById('set_silver_threshold').value,
                    gold_threshold: document.getElementById('set_gold_threshold').value,
                    platinum_threshold: document.getElementById('set_platinum_threshold').value,
                    points_earning_percent: document.getElementById('set_points_earning_percent').value,
                    inactivity_months: document.getElementById('set_inactivity_months').value,
                    inactivity_deduction_percent: document.getElementById('set_inactivity_deduction_percent').value
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Settings updated successfully!', () => {
                        location.reload();
                    });
                } else {
                    alert('Error saving configs');
                }
            });
        }

        // =====================================================================
        // ADVANCED SEARCH & REPORTING FRONTEND CONTROLLER
        // =====================================================================

        // ---- Utility: debounce helper ----
        function debounce(fn, delay) {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        // ---- Utility: show loading spinner in a tbody ----
        function setTableLoading(tbodyId, colSpan) {
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Searching...</td></tr>`;
        }

        // ---- Utility: format currency ----
        function fmtINR(val) {
            return '₹' + parseFloat(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // ---- Utility: growth badge HTML ----
        function growthBadge(val) {
            const num = parseFloat(val || 0);
            const cls = num >= 0 ? 'text-success' : 'text-danger';
            const icon = num >= 0 ? 'fa-caret-up' : 'fa-caret-down';
            return `<i class="fas ${icon}"></i> ${Math.abs(num)}% vs last period`;
        }

        // ---- Toggle Custom Date Fields ----
        function toggleCustomDateFields(context, value) {
            if (context === 'orders') {
                const row = document.getElementById('orders_custom_date_row');
                if (row) row.style.display = (value === 'custom') ? 'flex' : 'none';
            } else if (context === 'reports') {
                document.querySelectorAll('.reports_custom_date').forEach(el => {
                    el.style.display = (value === 'custom') ? 'block' : 'none';
                });
            }
        }

        // =====================================================================
        // 1. ORDERS SEARCH
        // =====================================================================
        function performOrdersSearch(event) {
            if (event) event.preventDefault();
            setTableLoading('orders-search-results-body', 7);

            const params = new URLSearchParams({
                action: 'search_orders',
                search: document.getElementById('order_search_input')?.value || '',
                status: document.getElementById('order_status_select')?.value || 'all',
                payment_status: document.getElementById('order_payment_status_select')?.value || 'all',
                type: document.getElementById('order_type_select')?.value || 'all',
                date: document.getElementById('order_date_select')?.value || 'all',
                start_date: document.getElementById('order_start_date')?.value || '',
                end_date: document.getElementById('order_end_date')?.value || '',
                min_amount: document.getElementById('order_min_amount')?.value || '',
                max_amount: document.getElementById('order_max_amount')?.value || ''
            });

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('orders-search-results-body', 7, 'Search failed.'); return; }
                renderOrdersSearchResults(data.orders);
            })
            .catch(() => showSearchError('orders-search-results-body', 7, 'Network error.'));
        }

        function getStarRatingHtml(rating, review) {
            rating = parseInt(rating);
            if (isNaN(rating) || rating < 1 || rating > 5) return '';
            let title = rating + '/5 Stars' + (review ? ': ' + review.replace(/"/g, '&quot;') : '');
            let html = `<div class="feedback-stars mt-1" style="color: #dfba86; font-size: 0.85rem;" title="${title}">`;
            for (let i = 1; i <= 5; i++) {
                html += (i <= rating) ? '★' : '☆';
            }
            html += '</div>';
            return html;
        }

        function renderOrdersSearchResults(orders) {
            // Show/hide the results card
            const card = document.getElementById('orders-search-results-card');
            if (card) card.style.display = 'block';

            const tbody = document.getElementById('orders-search-results-body');
            if (!tbody) return;

            if (!orders || orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No orders found matching your criteria.</td></tr>';
                return;
            }

            tbody.innerHTML = orders.map(ord => {
                const items = (ord.items || []).map(i => `${i.item_name} ×${i.quantity}`).join(', ') || '—';
                const statusMap = {
                    pending: 'bg-warning text-dark',
                    preparing: 'bg-primary text-white',
                    ready: 'bg-info text-dark',
                    completed: 'bg-success text-dark',
                    cancelled: 'bg-danger text-white'
                };
                const badgeCls = statusMap[ord.order_status?.toLowerCase()] || 'bg-secondary text-white';
                const isOnline = !ord.delivery_address?.toLowerCase().startsWith('table ');
                const typeBadge = isOnline
                    ? '<span class="badge bg-dark border border-secondary text-white">Online</span>'
                    : '<span class="badge bg-dark border border-secondary text-white">Dine-In</span>';

                return `<tr>
                    <td>
                        <strong class="text-gold">#${ord.order_number || ord.id}</strong>
                        ${getStarRatingHtml(ord.rating, ord.review)}
                    </td>
                    <td><strong>${ord.customer_name || '—'}</strong><br><small class="text-muted">${ord.customer_phone || ''}</small></td>
                    <td><small class="text-muted">${items}</small></td>
                    <td class="text-gold">${fmtINR(ord.total_amount)}</td>
                    <td>${typeBadge}</td>
                    <td><span class="status-badge ${badgeCls}">${(ord.order_status || '').toUpperCase()}</span></td>
                    <td><small class="text-muted">${ord.order_date ? new Date(ord.order_date).toLocaleString('en-IN') : '—'}</small></td>
                </tr>`;
            }).join('');
        }

        function showSearchError(tbodyId, colSpan, msg) {
            const tbody = document.getElementById(tbodyId);
            if (tbody) tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>${msg}</td></tr>`;
        }

        // =====================================================================
        // 2. KITCHEN SEARCH (Client-Side)
        // =====================================================================
        let _kitchenStatusFilter = 'all';

        function filterKitchenOrders() {
            const query = (document.getElementById('kitchen_search_input')?.value || '').trim().toLowerCase();
            const resetBtn = document.getElementById('kitchen-reset-btn');

            // Show reset button only when there's an active query
            if (resetBtn) {
                resetBtn.style.display = query ? '' : 'none';
            }

            // Filter cards and track per-column matches
            const columnMatches = {
                'kitchen-pending-list': 0,
                'kitchen-preparing-list': 0,
                'kitchen-ready-list': 0
            };

            document.querySelectorAll('.kitchen-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                const match = !query || text.includes(query);
                card.style.display = match ? '' : 'none';

                // Count visible cards per column
                const parentList = card.closest('[id^="kitchen-"][id$="-list"]');
                if (match && parentList && parentList.id in columnMatches) {
                    columnMatches[parentList.id]++;
                }
            });

            // Show 'no results' message per column when search has no matches
            if (query) {
                Object.entries(columnMatches).forEach(([listId, count]) => {
                    const list = document.getElementById(listId);
                    if (!list) return;
                    let noRes = list.querySelector('.kitchen-no-results');
                    if (count === 0) {
                        if (!noRes) {
                            noRes = document.createElement('div');
                            noRes.className = 'kitchen-no-results text-center text-muted py-4';
                            noRes.innerHTML = `<i class="fas fa-search me-2"></i>No match for "${query}"`;
                            list.appendChild(noRes);
                        } else {
                            noRes.innerHTML = `<i class="fas fa-search me-2"></i>No match for "${query}"`;
                            noRes.style.display = '';
                        }
                    } else if (noRes) {
                        noRes.style.display = 'none';
                    }
                });
            } else {
                // Remove all no-results messages when query cleared
                document.querySelectorAll('.kitchen-no-results').forEach(el => el.remove());
            }
        }

        function resetKitchenSearch() {
            const input = document.getElementById('kitchen_search_input');
            if (input) input.value = '';
            filterKitchenOrders();
        }

        function filterKitchenStatus(status) {
            _kitchenStatusFilter = status;
            // Toggle active button
            ['all', 'pending', 'preparing', 'ready'].forEach(s => {
                const btn = document.getElementById(`btn-kitchen-filter-${s}`);
                if (btn) btn.classList.toggle('active', s === status);
            });
            // Show/hide kitchen columns
            const colMap = {
                all: ['kitchen-pending-list', 'kitchen-preparing-list', 'kitchen-ready-list'],
                pending: ['kitchen-pending-list'],
                preparing: ['kitchen-preparing-list'],
                ready: ['kitchen-ready-list']
            };
            ['kitchen-pending-list', 'kitchen-preparing-list', 'kitchen-ready-list'].forEach(id => {
                const el = document.getElementById(id);
                const parent = el ? el.closest('.kitchen-col') : null;
                if (parent) parent.style.display = (status === 'all' || (colMap[status] || []).includes(id)) ? '' : 'none';
            });
        }

        // =====================================================================
        // 3. MENU SEARCH
        // =====================================================================
        function performMenuSearch(event) {
            if (event) event.preventDefault();

            const searchVal = document.getElementById('menu_search_input')?.value || '';
            const catVal = document.getElementById('menu_category_select')?.value || 'all';
            const availVal = document.getElementById('menu_availability_select')?.value || 'all';
            const dietVal = document.getElementById('menu_diet_select')?.value || 'all';
            const minPrice = document.getElementById('menu_price_min')?.value || '';
            const maxPrice = document.getElementById('menu_price_max')?.value || '';
            const bestsellerVal = document.getElementById('menu_bestseller_check')?.checked ? '1' : '0';

            // Save search filters state in sessionStorage
            sessionStorage.setItem('medusa_menu_search', JSON.stringify({
                search: searchVal,
                category: catVal,
                availability: availVal,
                diet_type: dietVal,
                min_price: minPrice,
                max_price: maxPrice,
                bestseller: bestsellerVal,
                active: true
            }));

            const params = new URLSearchParams({
                action: 'search_menu',
                search: searchVal,
                category: catVal,
                availability: availVal,
                diet_type: dietVal,
                min_price: minPrice,
                max_price: maxPrice,
                bestseller: bestsellerVal
            });

            // Show reset button when search is active
            const resetBtn = document.getElementById('menu-reset-btn');
            if (resetBtn) resetBtn.style.display = '';

            const card = document.getElementById('menu-search-results-card');
            const tbody = document.getElementById('menu-search-results-body');
            if (card) card.style.display = 'block';
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Searching menu...</td></tr>';

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('menu-search-results-body', 7, 'Search failed.'); return; }
                renderMenuSearchResults(data.menu);
            })
            .catch(() => showSearchError('menu-search-results-body', 7, 'Network error.'));
        }

        function resetMenuSearch() {
            // Clear saved search state
            sessionStorage.removeItem('medusa_menu_search');

            // Clear all filter inputs
            const fields = ['menu_search_input', 'menu_price_min', 'menu_price_max'];
            fields.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            const selects = ['menu_category_select', 'menu_diet_select', 'menu_availability_select'];
            selects.forEach(id => { const el = document.getElementById(id); if (el) el.value = el.options[0].value; });
            const check = document.getElementById('menu_bestseller_check');
            if (check) check.checked = false;
            // Hide results and reset button
            const card = document.getElementById('menu-search-results-card');
            if (card) card.style.display = 'none';
            const resetBtn = document.getElementById('menu-reset-btn');
            if (resetBtn) resetBtn.style.display = 'none';
        }

        function renderMenuSearchResults(menu) {
            const tbody = document.getElementById('menu-search-results-body');
            if (!tbody) return;

            if (!menu || menu.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No menu items found.</td></tr>';
                return;
            }

            tbody.innerHTML = menu.map(dish => {
                let imgSrc = dish.display_image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&h=100&fit=crop&auto=format';

                const isVeg = (dish.diet_type === 'veg');
                const dietBadge = isVeg 
                    ? `<svg viewBox="0 0 24 24" width="16" height="16" class="align-middle me-1" style="display:inline-block;"><rect x="2" y="2" width="20" height="20" rx="2" fill="none" stroke="#0f8a45" stroke-width="2.5"/><circle cx="12" cy="12" r="5" fill="#0f8a45"/></svg>`
                    : `<svg viewBox="0 0 24 24" width="16" height="16" class="align-middle me-1" style="display:inline-block;"><rect x="2" y="2" width="20" height="20" rx="2" fill="none" stroke="#c82333" stroke-width="2.5"/><circle cx="12" cy="12" r="5" fill="#c82333"/></svg>`;
                const bestBadge = dish.is_bestseller
                    ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem;">⭐ BESTSELLER</span>'
                    : '';

                const custCount = dish.cust_count || 0;
                const custActive = custCount > 0 ? 'active' : '';
                const isAvail = dish.is_available == 1;

                return `<tr data-id="${dish.id}">
                    <td class="text-center">
                        <input class="form-check-input search-result-checkbox" type="checkbox" value="${dish.id}">
                    </td>
                    <td><img src="${imgSrc}" alt="" style="width:44px;height:44px;border-radius:8px;object-fit:cover;" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=100&h=100&fit=crop&auto=format'"></td>
                    <td>${dietBadge}<strong>${dish.name}</strong> ${bestBadge}</td>
                    <td class="text-uppercase">
                        <small>${dish.category || '—'}</small>
                        ${dish.subcategory ? `<br><small class="text-gold" style="font-size:0.75rem; letter-spacing:0.5px;">${dish.subcategory}</small>` : ''}
                    </td>
                    <td class="text-gold">${fmtINR(dish.price)}</td>
                    <td><small class="text-muted">${(dish.description || '').substring(0, 60)}${(dish.description || '').length > 60 ? '…' : ''}</small></td>
                    <td class="text-center">
                        <div class="form-check form-switch premium-switch d-inline-block">
                            <input class="form-check-input" type="checkbox" role="switch"
                                ${isAvail ? 'checked' : ''}
                                onchange="toggleMenuAvailability(${dish.id}, this.checked)">
                        </div>
                    </td>
                    <td>
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <button class="btn btn-sm btn-luxury-action btn-luxury-custom ${custActive}"
                                onclick="openCustomizationManager(${dish.id}, '${dish.name.replace(/'/g, "\\'")}')"
                                title="Manage Customizations">
                                <i class="fas fa-sliders-h"></i>
                                <span class="luxury-badge bg-gold-badge ms-1">${custCount}</span>
                            </button>
                            <button class="btn btn-sm btn-luxury-action btn-luxury-edit"
                                onclick="openEditMenuModal(${JSON.stringify(dish).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;')})"
                                title="Edit Item">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-luxury-action btn-luxury-delete"
                                onclick="deleteMenuItem(${dish.id})"
                                title="Delete Item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        // =====================================================================
        // 4. CUSTOMERS SEARCH
        // =====================================================================
        function performCustomersSearch(event) {
            if (event) event.preventDefault();

            const params = new URLSearchParams({
                action: 'search_customers',
                search: document.getElementById('customer_search_input')?.value || ''
            });

            setTableLoading('customers-table-body', 8);

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('customers-table-body', 8, 'Search failed.'); return; }
                renderCustomersSearchResults(data.customers);
            })
            .catch(() => showSearchError('customers-table-body', 8, 'Network error.'));
        }

        function renderCustomersSearchResults(customers) {
            const tbody = document.getElementById('customers-table-body');
            if (!tbody) return;

            if (!customers || customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No customers found.</td></tr>';
                return;
            }

            tbody.innerHTML = customers.map(c => {
                const paid = c.payment_summary?.paid_count || 0;
                const failed = c.payment_summary?.failed_count || 0;
                const pending = c.payment_summary?.pending_count || 0;
                const lastDate = c.last_order_date ? new Date(c.last_order_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

                return `<tr>
                    <td><strong>${c.customer_name || 'Guest'}</strong><br><small class="text-muted">ID: ${c.customer_id || 'GUEST'}</small></td>
                    <td>${c.customer_phone || '—'}</td>
                    <td><small class="text-muted">${c.email || '—'}</small></td>
                    <td>${c.order_count || 0} orders</td>
                    <td class="text-gold">${fmtINR(c.total_spent)}</td>
                    <td><small class="text-muted">${lastDate}</small></td>
                    <td><span class="badge bg-dark border border-secondary text-white">${c.favorite_dish || '—'}</span></td>
                    <td>
                        <span class="badge bg-success text-dark">Paid: ${paid}</span>
                        ${pending > 0 ? `<span class="badge bg-warning text-dark ms-1">Pending: ${pending}</span>` : ''}
                        ${failed > 0 ? `<span class="badge bg-danger text-white ms-1">Failed: ${failed}</span>` : ''}
                    </td>
                </tr>`;
            }).join('');
        }

        // =====================================================================
        // 5. PAYMENTS SEARCH
        // =====================================================================
        function performPaymentsSearch(event) {
            if (event) event.preventDefault();

            const params = new URLSearchParams({
                action: 'search_payments',
                search: document.getElementById('payment_search_input')?.value || '',
                method: document.getElementById('payment_method_select')?.value || 'all',
                status: document.getElementById('payment_status_select')?.value || 'all',
                min_amount: document.getElementById('payment_min_amount')?.value || '',
                max_amount: document.getElementById('payment_max_amount')?.value || ''
            });

            setTableLoading('payments-table-body', 6);

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('payments-table-body', 6, 'Search failed.'); return; }
                renderPaymentsSearchResults(data.payments);
            })
            .catch(() => showSearchError('payments-table-body', 6, 'Network error.'));
        }

        function renderPaymentsSearchResults(logs) {
            const tbody = document.getElementById('payments-table-body');
            if (!tbody) return;

            if (!logs || logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No transactions found.</td></tr>';
                return;
            }

            tbody.innerHTML = logs.map(log => {
                let method = 'ONLINE GATEWAY';
                const addr = (log.delivery_address || '').toUpperCase();
                const pm = (log.payment_method || '').toLowerCase();
                if (addr.includes('PAID VIA CASH') || pm === 'cash' || pm === 'cod') method = 'CASH';
                else if (addr.includes('PAID VIA CARD') || pm === 'card') method = 'CARD';
                else if (addr.includes('PAID VIA UPI') || pm === 'upi') method = 'UPI';
                else if (addr.includes('PAID VIA NETBANKING') || addr.includes('PAID VIA NET BANKING') || pm === 'netbanking') method = 'NET BANKING';
                else if (addr.includes('PAID VIA WALLET') || pm === 'wallet') method = 'WALLET';

                const isPaid = log.order_status?.toLowerCase() === 'completed';
                const statusHtml = isPaid
                    ? '<span class="status-badge bg-success text-dark">Paid</span>'
                    : '<span class="status-badge bg-warning text-dark">Pending Settlement</span>';
                const dateStr = log.order_date ? new Date(log.order_date).toLocaleString('en-IN') : '—';

                return `<tr>
                    <td>#${log.order_number || log.id}</td>
                    <td>${log.customer_name || '—'}</td>
                    <td class="text-gold">${fmtINR(log.total_amount)}</td>
                    <td><span class="badge bg-dark border border-secondary text-white">${method}</span></td>
                    <td>${statusHtml}</td>
                    <td><small class="text-muted">${dateStr}</small></td>
                </tr>`;
            }).join('');
        }

        // =====================================================================
        // 6. REPORTS / BI DASHBOARD
        // =====================================================================
        let repSalesChartInst = null;
        let repPaymentChartInst = null;
        let repCategoryChartInst = null;
        let _lastReportData = null;

        function fmtINR(val) {
            return '₹' + Number(val || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function growthBadge(val) {
            const num = parseFloat(val || 0);
            const icon = num >= 0 ? 'fa-caret-up' : 'fa-caret-down';
            return `<i class="fas ${icon}"></i> ${Math.abs(num)}% vs last period`;
        }

        function loadReportsData(event) {
            if (event) event.preventDefault();

            const range = document.getElementById('report_range_select')?.value || 'thisweek';
            const start_date = document.getElementById('report_start_date')?.value || '';
            const end_date = document.getElementById('report_end_date')?.value || '';

            // Show loading skeleton on summary cards
            ['rep_revenue','rep_orders','rep_aov','rep_perf_score'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
            });

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_reports_data', range, start_date, end_date })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { alert('Failed to load report data. Please try again.'); return; }
                _lastReportData = data;
                renderReportSummary(data.summary);
                renderReportTrendChart(data.trend);
                renderReportPaymentChart(data.payments);
                renderReportCategoryChart(data.categories);
                renderReportDishesTable(data.dishes);
                renderReportCustomersTable(data.top_customers);
            })
            .catch(() => alert('Network error while loading reports.'));
        }

        function renderReportSummary(summary) {
            if (!summary) return;

            const rev = document.getElementById('rep_revenue');
            const revG = document.getElementById('rep_revenue_growth');
            if (rev) rev.textContent = fmtINR(summary.revenue);
            if (revG) { revG.className = parseFloat(summary.revenue_growth) >= 0 ? 'text-success' : 'text-danger'; revG.innerHTML = growthBadge(summary.revenue_growth); }

            const ord = document.getElementById('rep_orders');
            const ordG = document.getElementById('rep_orders_growth');
            if (ord) ord.textContent = summary.orders_count || 0;
            if (ordG) { ordG.className = parseFloat(summary.orders_growth) >= 0 ? 'text-success' : 'text-danger'; ordG.innerHTML = growthBadge(summary.orders_growth); }

            const aov = document.getElementById('rep_aov');
            const aovG = document.getElementById('rep_aov_growth');
            if (aov) aov.textContent = fmtINR(summary.aov);
            if (aovG) { aovG.className = parseFloat(summary.aov_growth) >= 0 ? 'text-success' : 'text-danger'; aovG.innerHTML = growthBadge(summary.aov_growth); }

            const perf = document.getElementById('rep_perf_score');
            if (perf) perf.textContent = (summary.performance_score || 0) + '/100';

            // Operations panel
            const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setEl('rep_op_online', summary.online_orders || 0);
            setEl('rep_op_dinein', summary.dinein_orders || 0);
            setEl('rep_op_acceptance', (summary.acceptance_rate || 0) + '%');
            setEl('rep_op_completion', (summary.completion_rate || 0) + '%');
            setEl('rep_cust_total', summary.total_customers || 0);
            setEl('rep_cust_new', summary.new_customers || 0);
            setEl('rep_cust_returning', summary.returning_customers || 0);
            setEl('rep_cust_retention', (summary.retention_rate || 0) + '%');
        }

        function getChartColors() {
            const isLight = document.documentElement.classList.contains('light-mode');
            return {
                gridColor: isLight ? 'rgba(0,0,0,0.07)' : 'rgba(255,255,255,0.06)',
                tickColor: isLight ? '#475569' : '#a09f9f',
                labelColor: isLight ? '#1e293b' : '#f0ece4',
                gold: '#dfba86',
                palette: ['#dfba86','#2ec4b6','#6366f1','#f97316','#ec4899','#84cc16','#14b8a6','#f43f5e']
            };
        }

        function renderReportTrendChart(trend) {
            const canvas = document.getElementById('repSalesChart');
            if (!canvas) return;
            const colors = getChartColors();

            if (repSalesChartInst) repSalesChartInst.destroy();

            repSalesChartInst = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: trend?.labels || [],
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: trend?.data || [],
                        backgroundColor: 'rgba(223,186,134,0.18)',
                        borderColor: colors.gold,
                        borderWidth: 2,
                        borderRadius: 6,
                        hoverBackgroundColor: 'rgba(223,186,134,0.38)'
                    }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            grid: { color: colors.gridColor },
                            ticks: { color: colors.tickColor, callback: v => '₹' + Number(v).toLocaleString('en-IN') }
                        },
                        x: {
                            grid: { color: colors.gridColor },
                            ticks: { color: colors.tickColor, maxRotation: 45 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: { label: ctx => ' ₹' + Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }
                        }
                    }
                }
            });
        }

        function renderReportPaymentChart(payments) {
            const canvas = document.getElementById('repPaymentChart');
            if (!canvas || !payments) return;
            const colors = getChartColors();

            const labels = Object.keys(payments).filter(k => payments[k].amount > 0);
            const data = labels.map(k => payments[k].amount);

            if (repPaymentChartInst) repPaymentChartInst.destroy();

            if (labels.length === 0) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                canvas.parentElement.innerHTML = `<canvas id="repPaymentChart" style="max-height:280px;max-width:280px;"></canvas><p class="text-center text-muted mt-3">No payment data for this period.</p>`;
                return;
            }

            repPaymentChartInst = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: colors.palette.slice(0, labels.length), borderWidth: 2, borderColor: document.documentElement.classList.contains('light-mode') ? '#fff' : '#0a0a0a' }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: colors.labelColor, padding: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: { label: ctx => ` ${ctx.label}: ₹${Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 })} (${((ctx.raw / data.reduce((a,b) => a+b, 0))*100).toFixed(1)}%)` }
                        }
                    }
                }
            });
        }

        function renderReportCategoryChart(categories) {
            const canvas = document.getElementById('repCategoryChart');
            if (!canvas || !categories) return;
            const colors = getChartColors();

            const labels = categories.map(c => (c.category_name || 'Other').toUpperCase());
            const data = categories.map(c => parseFloat(c.revenue || 0));

            if (repCategoryChartInst) repCategoryChartInst.destroy();

            if (labels.length === 0) {
                canvas.parentElement.innerHTML = `<canvas id="repCategoryChart" style="max-height:280px;max-width:280px;"></canvas><p class="text-center text-muted mt-3">No category data for this period.</p>`;
                return;
            }

            repCategoryChartInst = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{ data, backgroundColor: colors.palette.slice(0, labels.length), borderWidth: 2, borderColor: document.documentElement.classList.contains('light-mode') ? '#fff' : '#0a0a0a' }]
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: colors.labelColor, padding: 10, font: { size: 11 } } },
                        tooltip: {
                            callbacks: { label: ctx => ` ${ctx.label}: ₹${Number(ctx.raw).toLocaleString('en-IN', { minimumFractionDigits: 2 })}` }
                        }
                    }
                }
            });
        }

        function renderReportDishesTable(dishes) {
            const tbody = document.querySelector('#rep-dishes-table tbody');
            if (!tbody) return;

            if (!dishes || dishes.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No dish data for this period.</td></tr>';
                return;
            }

            tbody.innerHTML = dishes.map((d, i) => `<tr>
                <td>
                    <span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:rgba(223,186,134,0.15);color:#dfba86;font-size:0.7rem;font-weight:700;text-align:center;line-height:20px;margin-right:8px;">${i+1}</span>
                    ${d.item_name}
                </td>
                <td>${d.qty_sold || 0}</td>
                <td class="text-gold">${fmtINR(d.revenue)}</td>
            </tr>`).join('');
        }

        function renderReportCustomersTable(customers) {
            const tbody = document.querySelector('#rep-customers-table tbody');
            if (!tbody) return;

            if (!customers || customers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No customer data for this period.</td></tr>';
                return;
            }

            tbody.innerHTML = customers.map((c, i) => `<tr>
                <td>
                    <span style="display:inline-block;width:20px;height:20px;border-radius:50%;background:rgba(223,186,134,0.15);color:#dfba86;font-size:0.7rem;font-weight:700;text-align:center;line-height:20px;margin-right:8px;">${i+1}</span>
                    <strong>${c.customer_name || 'Guest'}</strong><br><small class="text-muted">${c.customer_phone || ''}</small>
                </td>
                <td>${c.order_count || 0}</td>
                <td class="text-gold">${fmtINR(c.total_spent)}</td>
            </tr>`).join('');
        }

        // =====================================================================
        // 7. EXPORT FUNCTIONS
        // =====================================================================
        function populateReportTemplate() {
            if (!_lastReportData) return false;
            
            const summary = _lastReportData.summary || {};
            const dishes = _lastReportData.dishes || [];
            
            // Format dates
            document.getElementById('print_report_period').textContent = `${summary.start_date || 'N/A'} to ${summary.end_date || 'N/A'}`;
            document.getElementById('print_report_date').textContent = new Date().toLocaleString();
            
            // Populate summary
            document.getElementById('print_report_revenue').textContent = `₹${parseFloat(summary.revenue || 0).toFixed(2)}`;
            document.getElementById('print_report_orders').textContent = `${summary.orders_count || 0} (${summary.online_orders || 0} Online, ${summary.dinein_orders || 0} Dine-In)`;
            document.getElementById('print_report_aov').textContent = `₹${parseFloat(summary.aov || 0).toFixed(2)}`;
            
            let acc = parseFloat(summary.acceptance_rate || 0).toFixed(0);
            let cmp = parseFloat(summary.completion_rate || 0).toFixed(0);
            document.getElementById('print_report_rates').textContent = `${acc}% / ${cmp}%`;
            
            let score = parseFloat(summary.performance_score || 0);
            document.getElementById('print_report_score').textContent = `${score.toFixed(0)} / 100`;
            
            // Customer Analytics
            document.getElementById('print_report_cust_total').textContent = summary.total_customers || 0;
            document.getElementById('print_report_cust_new').textContent = summary.new_customers || 0;
            document.getElementById('print_report_cust_return').textContent = summary.returning_customers || 0;
            document.getElementById('print_report_cust_rate').textContent = `${summary.retention_rate || 0}%`;
            
            // Top Dishes
            const dishesTbody = document.getElementById('print_top_dishes_tbody');
            dishesTbody.innerHTML = '';
            dishes.slice(0, 15).forEach(dish => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 10px; border: 1px solid #ddd;">${dish.item_name || dish.name || 'Unknown'}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${dish.category || dish.category_name || 'N/A'}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${dish.qty_sold || 0}</td>
                    <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₹${parseFloat(dish.revenue || 0).toFixed(2)}</td>
                `;
                dishesTbody.appendChild(tr);
            });
            
            // Payment Methods
            const payments = _lastReportData.payments || {};
            const paymentsTbody = document.getElementById('print_payments_tbody');
            paymentsTbody.innerHTML = '';
            Object.entries(payments).forEach(([method, vals]) => {
                if (vals.count > 0) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="padding: 10px; border: 1px solid #ddd;">${method.toUpperCase()}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${vals.count}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₹${parseFloat(vals.amount || 0).toFixed(2)}</td>
                    `;
                    paymentsTbody.appendChild(tr);
                }
            });

            // Top Customers
            const customers = _lastReportData.top_customers || [];
            const customersTbody = document.getElementById('print_customers_tbody');
            if (customersTbody) {
                customersTbody.innerHTML = '';
                customers.slice(0, 10).forEach(c => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="padding: 10px; border: 1px solid #ddd;">${c.customer_name || 'Unknown'}<br><small style="color:#666;">${c.customer_phone}</small></td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: center;">${c.order_count || 0}</td>
                        <td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₹${parseFloat(c.total_spent || 0).toFixed(2)}</td>
                    `;
                    customersTbody.appendChild(tr);
                });
            }

            // Helper to grab chart canvas with white background
            function getCanvasDataURL(canvasId) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return '';
                try {
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = canvas.width;
                    tempCanvas.height = canvas.height;
                    const ctx = tempCanvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                    ctx.drawImage(canvas, 0, 0);
                    return tempCanvas.toDataURL('image/jpeg', 0.95);
                } catch(e) {
                    console.error('Canvas extract error:', e);
                    return '';
                }
            }

            // Grab chart canvas images
            document.getElementById('print_sales_chart_img').src = getCanvasDataURL('repSalesChart');
            document.getElementById('print_payment_chart_img').src = getCanvasDataURL('repPaymentChart');
            document.getElementById('print_category_chart_img').src = getCanvasDataURL('repCategoryChart');
            
            return true;
        }

        function printReport() {
            if (!populateReportTemplate()) {
                alert('Please generate a report first by clicking "Update Report".');
                return;
            }
            window.print();
        }
  
        function exportReportToPDF() {
            if (!populateReportTemplate()) {
                alert('Please generate a report first by clicking "Update Report".');
                return;
            }
            
            const element = document.getElementById('printableReportTemplate');
            
            // Temporarily show the template for html2pdf rendering since it clones it
            const originalDisplay = element.style.display;
            const originalWidth = element.style.width;
            
            element.style.display = 'block';
            // Force A4 physical pixel width so that it wraps and sizes identically to print view
            element.style.width = '794px'; 
            
            const opt = {
                margin:       10,
                filename:     'Medusa_Business_Report_' + new Date().toISOString().slice(0, 10) + '.pdf',
                image:        { type: 'jpeg', quality: 1.0 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                element.style.display = originalDisplay;
                element.style.width = originalWidth;
            });
        }

        async function exportReportToExcel() {
            if (!_lastReportData) {
                alert('Please generate a report first by clicking "Update Report".');
                return;
            }

            // Dynamically load ExcelJS if not available
            if (typeof ExcelJS === 'undefined') {
                const btn = document.querySelector('[onclick="exportReportToExcel()"]');
                const origHtml = btn ? btn.innerHTML : '';
                if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';
                
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = "https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js";
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
                if (btn) btn.innerHTML = origHtml;
            }

            const summary = _lastReportData.summary || {};
            const dishes = _lastReportData.dishes || [];
            const categories = _lastReportData.categories || [];
            const payments = _lastReportData.payments || {};
            const customers = _lastReportData.top_customers || [];

            const workbook = new ExcelJS.Workbook();
            workbook.creator = 'Medusa Luxury Dashboard';
            workbook.created = new Date();

            // Colors
            const gold = 'FFD4AF37'; // Medusa Gold
            const dark = 'FF111111'; // Dark bg
            const white = 'FFFFFFFF';
            const gray = 'FFF0F0F0';
            
            // Reusable Border Style
            const thinBorder = {
                top: {style:'thin', color: {argb:'FFCCCCCC'}},
                left: {style:'thin', color: {argb:'FFCCCCCC'}},
                bottom: {style:'thin', color: {argb:'FFCCCCCC'}},
                right: {style:'thin', color: {argb:'FFCCCCCC'}}
            };

            // 1. Summary Sheet
            const wsSum = workbook.addWorksheet('Summary');
            wsSum.columns = [{ width: 25 }, { width: 20 }, { width: 25 }];
            
            // Header
            wsSum.addRow(['MEDUSA RESTAURANT - BUSINESS INTELLIGENCE REPORT']);
            wsSum.mergeCells('A1:C1');
            wsSum.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsSum.getCell('A1').font = { color: { argb: gold }, size: 14, bold: true };
            wsSum.getCell('A1').alignment = { horizontal: 'center', vertical: 'middle' };
            wsSum.getRow(1).height = 30;

            wsSum.addRow([]);
            wsSum.addRow(['Report Period', `${summary.start_date || ''} to ${summary.end_date || ''}`]);
            wsSum.mergeCells('B3:C3');
            wsSum.addRow(['Generated At', new Date().toLocaleString()]);
            wsSum.mergeCells('B4:C4');
            wsSum.addRow([]);

            // Subheader - Financials
            const sumHeader = wsSum.addRow(['Financial Metric', 'Value', 'Growth vs Last Period']);
            sumHeader.eachCell(c => {
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF333333' } };
                c.font = { color: { argb: white }, bold: true };
                c.border = thinBorder;
            });

            // Data - Financials
            const revG = parseFloat(summary.revenue_growth || 0);
            const ordG = parseFloat(summary.orders_growth || 0);
            const aovG = parseFloat(summary.aov_growth || 0);

            const r1 = wsSum.addRow(['Total Revenue (INR)', parseFloat(summary.revenue || 0), (revG>0?'+':'') + revG + '%']);
            r1.getCell(2).numFmt = '₹#,##0.00';
            r1.getCell(3).font = { color: { argb: revG >= 0 ? 'FF007A33' : 'FFC1272D' }, bold: true };
            r1.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            const r2 = wsSum.addRow(['Completed Orders', parseInt(summary.orders_count || 0), (ordG>0?'+':'') + ordG + '%']);
            r2.getCell(3).font = { color: { argb: ordG >= 0 ? 'FF007A33' : 'FFC1272D' }, bold: true };
            r2.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            const r3 = wsSum.addRow(['Average Order Value (INR)', parseFloat(summary.aov || 0), (aovG>0?'+':'') + aovG + '%']);
            r3.getCell(2).numFmt = '₹#,##0.00';
            r3.getCell(3).font = { color: { argb: aovG >= 0 ? 'FF007A33' : 'FFC1272D' }, bold: true };
            r3.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            const r4 = wsSum.addRow(['Performance Score', `${summary.performance_score || 0}/100`, '']);
            r4.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 1 ? 'right' : 'left' }; });

            wsSum.addRow([]);

            // Subheader - Operations
            const opsHeader = wsSum.addRow(['Operations & Customers', 'Value', '']);
            wsSum.mergeCells(`B${opsHeader.number}:C${opsHeader.number}`);
            opsHeader.eachCell(c => {
                c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF333333' } };
                c.font = { color: { argb: white }, bold: true };
                c.border = thinBorder;
            });
            
            const addOpRow = (label, val) => {
                const row = wsSum.addRow([label, val, '']);
                wsSum.mergeCells(`B${row.number}:C${row.number}`);
                row.eachCell(c => { c.border = thinBorder; });
                row.getCell(2).alignment = { horizontal: 'right' };
            };
            
            addOpRow('Online Orders', summary.online_orders || 0);
            addOpRow('Dine-in Orders', summary.dinein_orders || 0);
            addOpRow('Acceptance Rate', `${parseFloat(summary.acceptance_rate || 0).toFixed(1)}%`);
            addOpRow('Completion Rate', `${parseFloat(summary.completion_rate || 0).toFixed(1)}%`);
            addOpRow('Total Customers Reached', summary.total_customers || 0);
            addOpRow('New Guest Registrations', summary.new_customers || 0);
            addOpRow('Returning Customer Base', summary.returning_customers || 0);
            addOpRow('Guest Retention Rate', `${parseFloat(summary.retention_rate || 0).toFixed(1)}%`);

            // 2. Top Dishes Sheet
            const wsDishes = workbook.addWorksheet('Top Dishes');
            wsDishes.columns = [{ width: 10 }, { width: 35 }, { width: 25 }, { width: 15 }, { width: 20 }];
            
            wsDishes.addRow(['BEST SELLING DISHES']);
            wsDishes.mergeCells('A1:E1');
            wsDishes.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsDishes.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsDishes.getCell('A1').alignment = { horizontal: 'center' };
            
            const dh = wsDishes.addRow(['Rank', 'Dish Name', 'Category', 'Qty Sold', 'Revenue (INR)']);
            dh.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });
            
            dishes.forEach((d, i) => {
                const r = wsDishes.addRow([i + 1, d.item_name || d.name, d.category || 'N/A', parseInt(d.qty_sold), parseFloat(d.revenue || 0)]);
                r.getCell(5).numFmt = '₹#,##0.00';
                r.eachCell(c => { c.border = thinBorder; c.alignment = { horizontal: c._column._number > 3 ? 'right' : 'left' }; });
                r.getCell(1).alignment = { horizontal: 'center' };
            });

            // 3. Category Performance
            const wsCat = workbook.addWorksheet('Categories');
            wsCat.columns = [{ width: 25 }, { width: 15 }, { width: 20 }];
            
            wsCat.addRow(['CATEGORY PERFORMANCE']);
            wsCat.mergeCells('A1:C1');
            wsCat.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsCat.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsCat.getCell('A1').alignment = { horizontal: 'center' };

            const ch = wsCat.addRow(['Category', 'Units Sold', 'Revenue (INR)']);
            ch.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });

            categories.forEach(c => {
                const r = wsCat.addRow([c.category_name || c.category, parseInt(c.units_sold || c.qty), parseFloat(c.revenue || 0)]);
                r.getCell(3).numFmt = '₹#,##0.00';
                r.eachCell(cell => { cell.border = thinBorder; cell.alignment = { horizontal: cell._column._number > 1 ? 'right' : 'left' }; });
            });

            // 4. Payments
            const wsPay = workbook.addWorksheet('Payments');
            wsPay.columns = [{ width: 25 }, { width: 15 }, { width: 20 }];
            
            wsPay.addRow(['PAYMENT BREAKDOWN']);
            wsPay.mergeCells('A1:C1');
            wsPay.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsPay.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsPay.getCell('A1').alignment = { horizontal: 'center' };

            const ph = wsPay.addRow(['Payment Method', 'Transactions', 'Total Amount (INR)']);
            ph.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });

            Object.entries(payments).forEach(([method, vals]) => {
                if (vals.count > 0) {
                    const r = wsPay.addRow([method.toUpperCase(), parseInt(vals.count), parseFloat(vals.amount || 0)]);
                    r.getCell(3).numFmt = '₹#,##0.00';
                    r.eachCell(cell => { cell.border = thinBorder; cell.alignment = { horizontal: cell._column._number > 1 ? 'right' : 'left' }; });
                }
            });

            // 5. Top Customers
            const wsCust = workbook.addWorksheet('Top Customers');
            wsCust.columns = [{ width: 25 }, { width: 15 }, { width: 15 }, { width: 20 }];
            
            wsCust.addRow(['TOP PERFORMING CUSTOMERS']);
            wsCust.mergeCells('A1:D1');
            wsCust.getCell('A1').fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: dark } };
            wsCust.getCell('A1').font = { color: { argb: gold }, size: 12, bold: true };
            wsCust.getCell('A1').alignment = { horizontal: 'center' };

            const cuh = wsCust.addRow(['Customer Name', 'Phone', 'Orders', 'Total Spent (INR)']);
            cuh.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: gray } }; c.font = { bold: true }; c.border = thinBorder; });

            customers.slice(0, 50).forEach(c => {
                const r = wsCust.addRow([c.customer_name || 'Unknown', c.customer_phone, parseInt(c.order_count || 0), parseFloat(c.total_spent || 0)]);
                r.getCell(4).numFmt = '₹#,##0.00';
                r.eachCell(cell => { cell.border = thinBorder; cell.alignment = { horizontal: cell._column._number > 2 ? 'right' : 'left' }; });
            });

            // Trigger Download
            const buffer = await workbook.xlsx.writeBuffer();
            const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'Medusa_Business_Report_' + new Date().toISOString().slice(0, 10) + '.xlsx';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // =====================================================================
        // 8. AUTO-INITIALIZATION
        // =====================================================================
        document.addEventListener('DOMContentLoaded', function() {
            // Real-time debounced orders search (search-as-you-type on the text field)
            const orderInput = document.getElementById('order_search_input');
            if (orderInput) {
                orderInput.addEventListener('input', debounce(() => performOrdersSearch(null), 400));
            }

            // Real-time debounced menu search
            const menuInput = document.getElementById('menu_search_input');
            if (menuInput) {
                menuInput.addEventListener('input', debounce(() => performMenuSearch(null), 400));
            }

            // Real-time debounced customer search
            const custInput = document.getElementById('customer_search_input');
            if (custInput) {
                custInput.addEventListener('input', debounce(() => performCustomersSearch(null), 400));
            }

            // Real-time debounced payment search
            const payInput = document.getElementById('payment_search_input');
            if (payInput) {
                payInput.addEventListener('input', debounce(() => performPaymentsSearch(null), 400));
            }

            // Real-time debounced careers search
            const careerInput = document.getElementById('career_search_input');
            if (careerInput) {
                careerInput.addEventListener('input', debounce(() => performCareersSearch(null), 400));
            }

            // Real-time selectors search for careers
            const posFilter = document.getElementById('career_position_filter');
            if (posFilter) {
                posFilter.addEventListener('change', () => performCareersSearch(null));
            }
            const statFilter = document.getElementById('career_status_filter');
            if (statFilter) {
                statFilter.addEventListener('change', () => performCareersSearch(null));
            }

            // Auto-load career applications when careers tab is activated
            const careersLink = document.querySelector('[onclick*="careers-tab"]');
            if (careersLink) {
                careersLink.addEventListener('click', function() {
                    setTimeout(() => { performCareersSearch(); }, 150);
                });
            }

            // Ensure orders-search-results-card is hidden by default
            const ordResCard = document.getElementById('orders-search-results-card');
            if (ordResCard) ordResCard.style.display = 'none';

            const menuResCard = document.getElementById('menu-search-results-card');
            if (menuResCard) menuResCard.style.display = 'none';

            // Restore menu search if active
            const savedSearch = sessionStorage.getItem('medusa_menu_search');
            if (savedSearch) {
                try {
                    const criteria = JSON.parse(savedSearch);
                    if (criteria && criteria.active) {
                        if (document.getElementById('menu_search_input')) document.getElementById('menu_search_input').value = criteria.search || '';
                        if (document.getElementById('menu_category_select')) document.getElementById('menu_category_select').value = criteria.category || 'all';
                        if (document.getElementById('menu_availability_select')) document.getElementById('menu_availability_select').value = criteria.availability || 'all';
                        if (document.getElementById('menu_diet_select')) document.getElementById('menu_diet_select').value = criteria.diet_type || 'all';
                        if (document.getElementById('menu_price_min')) document.getElementById('menu_price_min').value = criteria.min_price || '';
                        if (document.getElementById('menu_price_max')) document.getElementById('menu_price_max').value = criteria.max_price || '';
                        if (document.getElementById('menu_bestseller_check')) document.getElementById('menu_bestseller_check').checked = criteria.bestseller === '1';
                        
                        // Run search to show the results card
                        setTimeout(() => { performMenuSearch(null); }, 100);
                    }
                } catch (e) {
                    console.error("Error restoring menu search:", e);
                }
            }

            // Update BI report chart colors when theme toggles
            document.addEventListener('themeChanged', () => {
                if (repSalesChartInst) { renderReportTrendChart(_lastReportData?.trend); }
                if (repPaymentChartInst && _lastReportData) { renderReportPaymentChart(_lastReportData.payments); }
                if (repCategoryChartInst && _lastReportData) { renderReportCategoryChart(_lastReportData.categories); }
            });
        });

        // Patch toggleTheme to dispatch custom event for chart theme updates
        const _origToggleTheme = toggleTheme;
        toggleTheme = function() {
            _origToggleTheme();
            document.dispatchEvent(new Event('themeChanged'));
        };

        // =====================================================================
        // CAREERS PORTAL DASHBOARD CONTROLLER
        // =====================================================================
        function performCareersSearch(event) {
            if (event) event.preventDefault();
            setTableLoading('careers-table-body', 7);

            const params = new URLSearchParams({
                action: 'get_career_applications',
                search: document.getElementById('career_search_input')?.value || '',
                position: document.getElementById('career_position_filter')?.value || 'all',
                status: document.getElementById('career_status_filter')?.value || 'all'
            });

            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) { showSearchError('careers-table-body', 7, 'Failed to load applications.'); return; }
                
                // Update metrics counters
                if (data.summary) {
                    const setMetric = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                    setMetric('careers_total_metric', data.summary.total);
                    setMetric('careers_pending_metric', data.summary.pending);
                    setMetric('careers_shortlisted_metric', data.summary.shortlisted);
                    setMetric('careers_rejected_metric', data.summary.rejected);
                }

                renderCareersSearchResults(data.applications);
            })
            .catch(() => showSearchError('careers-table-body', 7, 'Network error loading applications.'));
        }

        function renderCareersSearchResults(applications) {
            const tbody = document.getElementById('careers-table-body');
            if (!tbody) return;

            if (!applications || applications.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No applications found matching criteria.</td></tr>';
                return;
            }

            tbody.innerHTML = applications.map(app => {
                const statusMap = {
                    pending: 'bg-warning text-dark',
                    reviewed: 'bg-info text-dark',
                    shortlisted: 'bg-success text-dark',
                    rejected: 'bg-danger text-white'
                };
                const badgeCls = statusMap[app.status?.toLowerCase()] || 'bg-secondary text-white';
                const appliedDate = app.applied_at ? new Date(app.applied_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
                const escLetter = (app.cover_letter || '').replace(/'/g, "\\'").replace(/"/g, '&quot;').replace(/\n/g, '\\n');
                
                let viewLetterBtn = '';
                if (app.cover_letter && app.cover_letter.trim() !== '') {
                    viewLetterBtn = `<button class="btn-action-circle btn-action-circle-info" onclick="openCoverLetterModal('${escLetter}')" title="View Message"><i class="fas fa-envelope"></i></button>`;
                }

                const ext = app.resume_path.split('.').pop().toLowerCase();
                const downloadName = `${app.full_name.replace(/\s+/g, '_')}_Resume.${ext}`;

                return `<tr>
                    <td>
                        <strong>${app.full_name}</strong><br>
                        <small class="text-muted"><i class="fas fa-envelope"></i> ${app.email}</small><br>
                        <small class="text-muted"><i class="fas fa-phone"></i> ${app.mobile}</small><br>
                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> ${app.city}</small>
                    </td>
                    <td><strong>${app.position}</strong></td>
                    <td>${app.experience} Years</td>
                    <td class="text-gold">₹${parseFloat(app.expected_salary).toLocaleString('en-IN')}</td>
                    <td>
                        <div class="d-flex align-items-center gap-1">
                            <button class="btn btn-sm btn-outline-warning d-flex align-items-center gap-1" onclick="openResumeModal('${app.resume_path}', '${app.full_name.replace(/'/g, "\\'")}')" title="View Resume"><i class="fas fa-eye"></i> View</button>
                            <a href="../${app.resume_path}" download="${downloadName}" class="btn btn-sm btn-outline-secondary d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0;" title="Download"><i class="fas fa-download"></i></a>
                        </div>
                    </td>
                    <td><span class="status-badge ${badgeCls}">${app.status || 'Pending'}</span></td>
                    <td>
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            ${viewLetterBtn}
                            <button class="btn-action-circle btn-action-circle-light" onclick="updateCareerStatus(${app.id}, 'Reviewed')" title="Mark Reviewed"><i class="fas fa-check-double"></i></button>
                            <button class="btn-action-circle btn-action-circle-success" onclick="updateCareerStatus(${app.id}, 'Shortlisted')" title="Shortlist"><i class="fas fa-user-check"></i></button>
                            <button class="btn-action-circle btn-action-circle-danger" onclick="updateCareerStatus(${app.id}, 'Rejected')" title="Reject"><i class="fas fa-user-times"></i></button>
                            <button class="btn-action-circle btn-action-circle-danger" style="background: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626;" onclick="deleteCareerApplication(${app.id})" title="Delete Application"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        }

        function deleteCareerApplication(id) {
            if (!confirm('Are you sure you want to permanently delete this application?')) return;
            
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_career_application',
                    id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    performCareersSearch(); // Refresh list
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete'));
                }
            })
            .catch(() => alert('Network error while deleting application.'));
        }

        function updateCareerStatus(id, newStatus) {
            fetch('dashboardtest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'update_career_status',
                    id: id,
                    status: newStatus
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    performCareersSearch();
                } else {
                    alert('Error updating status: ' + data.message);
                }
            })
            .catch(() => alert('Network error updating application status.'));
        }

        function openCoverLetterModal(message) {
            document.getElementById('coverLetterContent').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('coverLetterModal'));
            modal.show();
        }

        function openResumeModal(resumePath, candidateName) {
            const body = document.getElementById('resumeViewerBody');
            if (!body) return;
            
            const ext = resumePath.split('.').pop().toLowerCase();
            const fullUrl = '../' + resumePath;
            
            if (ext === 'pdf') {
                body.innerHTML = `<iframe src="${fullUrl}" style="width: 100%; height: 600px; border: none; background: #ffffff;"></iframe>`;
            } else {
                const downloadName = candidateName.replace(/\s+/g, '_') + '_Resume.' + ext;
                body.innerHTML = `
                    <div class="text-center p-5">
                        <div style="font-size: 4rem; color: var(--gold); margin-bottom: 1.5rem;"><i class="far fa-file-word"></i></div>
                        <h4 class="mb-3 text-gold">Word Document Resume</h4>
                        <p class="text-muted mb-4">Word documents (.doc / .docx) cannot be previewed directly in the browser.<br>Please download the file to view its contents.</p>
                        <a href="${fullUrl}" class="btn btn-outline-warning btn-lg px-4" download="${downloadName}" style="border-radius: 10px;">
                            <i class="fas fa-file-download me-2"></i>Download Word Document
                        </a>
                    </div>
                `;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('resumeViewerModal'));
            modal.show();
        }

        // =====================================================================
        // END OF ADVANCED SEARCH & REPORTING CONTROLLER
        // =====================================================================

        // Global Premium Theme Alert Override
        (function() {
            window.alert = function(message, callback) {
                const existing = document.getElementById('customAlertModal');
                if (existing) existing.remove();

                const overlay = document.createElement('div');
                overlay.id = 'customAlertModal';
                overlay.style.cssText = 'position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:99999; display:flex; align-items:center; justify-content:center; opacity:0; transition:opacity 0.22s ease-out; padding:1.5rem;';

                const isLight = document.documentElement.classList.contains('light-mode');

                const box = document.createElement('div');
                box.style.cssText = `background:${isLight ? 'rgba(255,255,255,0.96)' : 'linear-gradient(135deg, #1c1a17 0%, #0d0c0a 100%)'}; border:1px solid ${isLight ? 'rgba(223,186,134,0.35)' : 'rgba(223,186,134,0.25)'}; border-radius:20px; width:100%; max-width:400px; padding:2.2rem 2rem; box-shadow:${isLight ? '0 20px 50px rgba(0,0,0,0.08)' : '0 30px 70px rgba(0,0,0,0.8)'}; transform:scale(0.85); transition:transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); text-align:center; position:relative;`;

                let iconHtml = '';
                const msgLower = message.toLowerCase();
                if (msgLower.includes('success') || msgLower.includes('booked') || msgLower.includes('✅') || msgLower.includes('settled') || msgLower.includes('opened')) {
                    iconHtml = '<div style="width:58px; height:58px; border-radius:50%; background:rgba(46,196,182,0.1); border:2px solid #2ec4b6; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.2rem; color:#2ec4b6; font-size:1.6rem;"><i class="fas fa-check"></i></div>';
                } else if (msgLower.includes('error') || msgLower.includes('fail') || msgLower.includes('denied') || msgLower.includes('invalid') || msgLower.includes('please') || msgLower.includes('failed')) {
                    iconHtml = '<div style="width:58px; height:58px; border-radius:50%; background:rgba(239,68,68,0.08); border:2px solid #ef4444; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.2rem; color:#ef4444; font-size:1.6rem;"><i class="fas fa-exclamation-triangle"></i></div>';
                } else {
                    iconHtml = '<div style="width:58px; height:58px; border-radius:50%; background:rgba(223,186,134,0.08); border:2px solid #dfba86; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1.2rem; color:#dfba86; font-size:1.6rem;"><i class="fas fa-info-circle"></i></div>';
                }

                const cleanMessage = message.replace('✅', '').replace('❌', '').trim();

                box.innerHTML = `
                    ${iconHtml}
                    <div style="font-size:0.95rem; line-height:1.6; color:${isLight ? '#1e293b' : '#f0ece4'}; margin-bottom:1.8rem; font-weight:500; font-family:'Plus Jakarta Sans', sans-serif;">
                        ${cleanMessage}
                    </div>
                    <button id="customAlertOkBtn" style="background:linear-gradient(135deg, #dfba86 0%, #c89640 100%); color:#0a0a0a; border:none; border-radius:10px; padding:0.72rem 2.8rem; font-weight:700; font-size:0.88rem; cursor:pointer; transition:all 0.2s; letter-spacing:0.4px; outline:none; font-family:'Plus Jakarta Sans', sans-serif;">OK</button>
                `;

                overlay.appendChild(box);
                document.body.appendChild(overlay);

                overlay.offsetHeight; // Reflow
                overlay.style.opacity = '1';
                box.style.transform = 'scale(1)';

                const closeAlert = () => {
                    overlay.style.opacity = '0';
                    box.style.transform = 'scale(0.85)';
                    setTimeout(() => {
                        overlay.remove();
                        if (typeof callback === 'function') {
                            callback();
                        }
                    }, 220);
                    window.removeEventListener('keydown', handleKeydown);
                };

                const handleKeydown = (e) => {
                    if (e.key === 'Enter' || e.key === 'Escape') {
                        e.preventDefault();
                        closeAlert();
                    }
                };

                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) closeAlert();
                });
                box.querySelector('#customAlertOkBtn').addEventListener('click', closeAlert);
                window.addEventListener('keydown', handleKeydown);
            };
        })();

        // ==========================================
        // MEDUSA NOTIFICATION SYSTEM CLIENT CONTROLLERS
        // ==========================================
        let notifActiveFilter = 'all';
        let notifSearchTerm = '';
        let notifCurrentPage = 0;
        const notifPageLimit = 15;
        let soundEnabled = localStorage.getItem('medusa_sound_enabled') !== 'false';
        let lastFetchedNotifId = 0;
        let isInitialLoad = true;

        // Custom chime/bell sound synth fallback using Web Audio API
        function playChimeSound() {
            if (!soundEnabled) return;
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                // Play first tone (A5)
                playTone(audioCtx, 880, 0.1, 0.4);
                // Play second tone (C6) slightly delayed and higher
                setTimeout(() => {
                    playTone(audioCtx, 1046.5, 0.1, 0.4);
                }, 120);
            } catch (e) {
                console.warn("Web Audio API not supported or blocked by browser policies: ", e);
            }
        }

        function playSOSAlarm() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                // Urgent repeating alarm: high-low-high pattern
                const pattern = [1200, 800, 1200, 800, 1200];
                pattern.forEach((freq, i) => {
                    setTimeout(() => playTone(audioCtx, freq, 0, 0.18), i * 200);
                });
            } catch (e) {
                console.warn("SOS alarm audio error:", e);
            }
        }

        function playTone(ctx, freq, startTime, duration) {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, ctx.currentTime);
            
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            // Exponential decay to avoid clicking pops
            gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + duration);
            
            osc.start();
            osc.stop(ctx.currentTime + duration);
        }

        // Toggle sound preference dynamically
        function toggleSoundPreference(enabled) {
            soundEnabled = enabled;
            localStorage.setItem('medusa_sound_enabled', enabled ? 'true' : 'false');
            const icon = document.getElementById('soundToggleIcon');
            if (icon) {
                icon.className = enabled ? 'fas fa-volume-up' : 'fas fa-volume-mute';
                icon.style.color = enabled ? 'var(--gold)' : 'var(--gray)';
            }
        }

        // Toggle notification bell dropdown
        function toggleNotificationDropdown(event) {
            if (event) event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdownMenu');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        // Close dropdown on click outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdownMenu');
            const bellBtn = document.getElementById('notificationBellBtn');
            if (dropdown && dropdown.classList.contains('show')) {
                if (!dropdown.contains(e.target) && (!bellBtn || !bellBtn.contains(e.target))) {
                    dropdown.classList.remove('show');
                }
            }
        });

        // Navigate to notification center tab
        function goToNotificationsTab(event) {
            if (event) event.stopPropagation();
            // Find notifications sidebar tab button
            const sidebarBtn = document.querySelector('.sidebar-link[onclick*="notifications-tab"]');
            if (sidebarBtn) {
                sidebarBtn.click();
            }
            // Close dropdown
            const dropdown = document.getElementById('notificationDropdownMenu');
            if (dropdown) dropdown.classList.remove('show');
        }

        // Toast notifications stacking system
        function showToastNotification(notif, isSOS) {
            let toastContainer = document.getElementById('toastContainerMedusa');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainerMedusa';
                toastContainer.className = 'toast-container-medusa';
                document.body.appendChild(toastContainer);
            }

            const toast = document.createElement('div');
            toast.className = isSOS ? 'toast-medusa toast-medusa-sos' : 'toast-medusa';
            
            const iconClass = getNotifIcon(notif.type, notif.title);
            const colorClass = isSOS ? 'notif-sos-urgent' : getNotifClass(notif.type);

            toast.innerHTML = `
                <div class="notif-icon-circle ${colorClass}" style="width: 34px; height: 34px; font-size: 0.95rem;">
                    <i class="${iconClass}"></i>
                </div>
                <div class="toast-medusa-content">
                    <div class="toast-medusa-title">${escapeHtml(notif.title)}</div>
                    <div class="toast-medusa-body">${escapeHtml(notif.body)}</div>
                </div>
                <button class="toast-medusa-close" onclick="this.parentElement.classList.add('fade-out'); setTimeout(() => this.parentElement.remove(), 300);">&times;</button>
            `;

            toastContainer.appendChild(toast);

            // SOS stays visible for 12 seconds; regular toasts 4 seconds
            const dismissDelay = isSOS ? 12000 : 4000;
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('fade-out');
                    setTimeout(() => toast.remove(), 300);
                }
            }, dismissDelay);
        }

        // Fetch paginated history for center page tab
        function fetchNotificationsPage(page) {
            notifCurrentPage = page;
            const offset = page * notifPageLimit;
            
            const tableBody = document.getElementById('notifications-table-body');
            if (!tableBody) return;
            
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-5">
                        <div class="spinner-border text-gold" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </td>
                </tr>
            `;
            
            const url = `../notifications_api.php?action=fetch&filter=${notifActiveFilter}&search=${encodeURIComponent(notifSearchTerm)}&limit=${notifPageLimit}&offset=${offset}`;
            
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const totalBadge = document.getElementById('notif_total_badge');
                        if (totalBadge) {
                            totalBadge.innerText = `${data.total_count} total`;
                        }
                        
                        renderNotificationsTable(data.notifications);
                        renderNotificationsPagination(data.total_count, page);
                    } else {
                        tableBody.innerHTML = `
                            <tr>
                                <td colspan="4" class="text-center py-4 text-danger">
                                    Error loading notifications: ${escapeHtml(data.message)}
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(err => {
                    console.error("Error fetching notifications page:", err);
                    tableBody.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4 text-danger">
                                Failed to fetch notifications history from server.
                            </td>
                        </tr>
                    `;
                });
        }

        // Render notifications inside center page table
        function renderNotificationsTable(notifications) {
            const tableBody = document.getElementById('notifications-table-body');
            if (!tableBody) return;
            
            if (notifications.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <div class="notif-empty-state">
                                <div class="notif-empty-icon"><i class="fas fa-bell-slash"></i></div>
                                <div class="notif-empty-title">No notifications found</div>
                                <div class="notif-empty-desc">No events match your search or filter options.</div>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            let html = '';
            notifications.forEach(n => {
                const isSOS = n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS');
                const iconClass = getNotifIcon(n.type, n.title);
                const colorClass = isSOS ? 'notif-sos-urgent' : getNotifClass(n.type);
                const rowClass = n.is_read == 1 ? 'read-row' : 'unread-row';
                const formattedTime = formatDateTime(n.created_at);
                
                html += `
                    <tr class="notif-row ${rowClass}" data-id="${n.id}">
                        <td>
                            <div class="notif-icon-circle ${colorClass}">
                                <i class="${iconClass}"></i>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold notif-row-title">${escapeHtml(n.title)}</div>
                            <div class="text-muted small">${escapeHtml(n.body)}</div>
                        </td>
                        <td>
                            <span class="text-muted small">${formattedTime}</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                ${n.is_read == 0 ? `
                                <button class="btn btn-sm btn-outline-success p-1 px-2" onclick="markNotifRead(${n.id}, event)" title="Mark as Read" style="border-color: rgba(46, 196, 182, 0.4); color: #2ec4b6; background: transparent;">
                                    <i class="fas fa-check"></i>
                                </button>` : ''}
                                <button class="btn btn-sm btn-outline-danger p-1 px-2" onclick="deleteNotification(${n.id}, event)" title="Delete" style="border-color: rgba(235, 94, 85, 0.4); color: #eb5e55; background: transparent;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tableBody.innerHTML = html;
        }

        // Render pagination links for center page tab
        function renderNotificationsPagination(totalCount, currentPage) {
            const paginationInfo = document.getElementById('notif_pagination_info');
            const paginationList = document.getElementById('notif_pagination_list');
            
            if (!paginationInfo || !paginationList) return;
            
            const startIdx = totalCount === 0 ? 0 : currentPage * notifPageLimit + 1;
            const endIdx = Math.min((currentPage + 1) * notifPageLimit, totalCount);
            
            paginationInfo.innerText = `Showing ${startIdx} to ${endIdx} of ${totalCount} entries`;
            
            const totalPages = Math.ceil(totalCount / notifPageLimit);
            
            if (totalPages <= 1) {
                paginationList.innerHTML = '';
                return;
            }
            
            const isLight = document.documentElement.classList.contains('light-mode');
            const pageLinkClass = isLight ? 'bg-light text-dark border-secondary' : 'bg-dark text-white border-secondary';
            
            let html = '';
            
            // Previous link
            const prevDisabled = currentPage === 0 ? 'disabled' : '';
            html += `
                <li class="page-item ${prevDisabled}">
                    <a class="page-link ${pageLinkClass}" href="javascript:void(0)" onclick="${currentPage > 0 ? `fetchNotificationsPage(${currentPage - 1})` : ''}" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            `;
            
            // Page numbers link
            for (let i = 0; i < totalPages; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                const linkStyle = i === currentPage ? 'background-color: var(--gold) !important; border-color: var(--gold) !important; color: #000 !important; font-weight: bold;' : '';
                const currentLinkClass = i === currentPage ? '' : pageLinkClass;
                
                html += `
                    <li class="page-item ${activeClass}">
                        <a class="page-link ${currentLinkClass}" style="${linkStyle}" href="javascript:void(0)" onclick="fetchNotificationsPage(${i})">${i + 1}</a>
                    </li>
                `;
            }
            
            // Next link
            const nextDisabled = currentPage === totalPages - 1 ? 'disabled' : '';
            html += `
                <li class="page-item ${nextDisabled}">
                    <a class="page-link ${pageLinkClass}" href="javascript:void(0)" onclick="${currentPage < totalPages - 1 ? `fetchNotificationsPage(${currentPage + 1})` : ''}" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            `;
            
            paginationList.innerHTML = html;
        }

        // Set active filters on click
        function setNotifFilter(filter) {
            notifActiveFilter = filter;
            const buttons = document.querySelectorAll('#notif_filter_buttons_container .notif-filter-btn');
            buttons.forEach(btn => {
                if (btn.getAttribute('data-filter') === filter) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            fetchNotificationsPage(0);
        }

        // Debounced search keyup
        let notifSearchTimeout = null;
        function handleNotifSearchInput(event) {
            notifSearchTerm = event.target.value;
            clearTimeout(notifSearchTimeout);
            notifSearchTimeout = setTimeout(() => {
                fetchNotificationsPage(0);
            }, 300);
        }

        // Action: Mark single read
        function markNotifRead(id, event) {
            if (event) event.stopPropagation();
            fetch(`../notifications_api.php?action=mark_read&id=${id}`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        pollNotifications();
                        // If notifications-tab is active, reload current page
                        const notifTab = document.getElementById('notifications-tab');
                        if (notifTab && notifTab.classList.contains('active')) {
                            fetchNotificationsPage(notifCurrentPage);
                        }
                    }
                })
                .catch(err => console.error("Error marking read:", err));
        }

        // Action: Mark all read
        function markAllNotificationsRead(event) {
            if (event) event.stopPropagation();
            fetch(`../notifications_api.php?action=mark_all_read`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        pollNotifications();
                        const notifTab = document.getElementById('notifications-tab');
                        if (notifTab && notifTab.classList.contains('active')) {
                            fetchNotificationsPage(notifCurrentPage);
                        }
                    }
                })
                .catch(err => console.error("Error marking all read:", err));
        }

        // Action: Delete notification
        function deleteNotification(id, event) {
            if (event) event.stopPropagation();
            if (confirm("Are you sure you want to delete this notification?")) {
                fetch(`../notifications_api.php?action=delete&id=${id}`, { method: 'POST' })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            pollNotifications();
                            const notifTab = document.getElementById('notifications-tab');
                            if (notifTab && notifTab.classList.contains('active')) {
                                fetchNotificationsPage(notifCurrentPage);
                            }
                        }
                    })
                    .catch(err => console.error("Error deleting notification:", err));
            }
        }

        // Handle dropdown item action routing based on type
        function handleDropdownItemClick(id, type, event) {
            if (event) event.stopPropagation();
            
            // Mark read immediately
            fetch(`../notifications_api.php?action=mark_read&id=${id}`, { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    pollNotifications();
                    
                    // Route to correct tab
                    if (type === 'order') {
                        const tabBtn = document.querySelector('.sidebar-link[onclick*="orders-tab"]');
                        if (tabBtn) tabBtn.click();
                    } else if (type === 'reservation') {
                        const tabBtn = document.querySelector('.sidebar-link[onclick*="tables-tab"]');
                        if (tabBtn) tabBtn.click();
                    } else {
                        // Default to notification center
                        goToNotificationsTab();
                    }
                })
                .catch(err => console.error("Error clicking dropdown item:", err));

            // Hide dropdown menu
            const dropdown = document.getElementById('notificationDropdownMenu');
            if (dropdown) dropdown.classList.remove('show');
        }

        // Populate badges and bell dropdown content
        function updateNotificationsDropdown(notifications, unreadCount) {
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                if (unreadCount > 0) {
                    badge.innerText = unreadCount > 99 ? '99+' : unreadCount;
                    badge.style.display = 'flex';
                    // Trigger dynamic bounce animation on bell
                    const bellIcon = document.querySelector('#notificationBellBtn i');
                    if (bellIcon) {
                        bellIcon.classList.add('fa-bounce');
                        setTimeout(() => bellIcon.classList.remove('fa-bounce'), 1000);
                    }
                } else {
                    badge.style.display = 'none';
                }
            }

            const dropdownList = document.getElementById('dropdownNotificationList');
            if (!dropdownList) return;

            if (notifications.length === 0) {
                dropdownList.innerHTML = `
                    <div class="notif-empty-state">
                        <div class="notif-empty-icon"><i class="fas fa-bell-slash"></i></div>
                        <div class="notif-empty-title">All caught up!</div>
                        <div class="notif-empty-desc">No new notifications.</div>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(n => {
                const isSOS = n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS');
                const iconClass = getNotifIcon(n.type, n.title);
                const colorClass = isSOS ? 'notif-sos-urgent' : getNotifClass(n.type);
                const unreadClass = n.is_read == 0 ? 'unread' : '';
                const timeStr = formatRelativeTime(n.created_at);

                html += `
                    <div class="notification-item ${unreadClass}" onclick="handleDropdownItemClick(${n.id}, '${n.type}', event)">
                        <div class="notif-icon-circle ${colorClass}">
                            <i class="${iconClass}"></i>
                        </div>
                        <div class="notif-details">
                            <div class="notif-title-row">
                                <span class="notif-title-text">${escapeHtml(n.title)}</span>
                                <span class="notif-time">${timeStr}</span>
                            </div>
                            <p class="notif-body-text">${escapeHtml(n.body)}</p>
                        </div>
                        ${n.is_read == 0 ? '<span class="notif-unread-dot"></span>' : ''}
                    </div>
                `;
            });
            dropdownList.innerHTML = html;
        }

        // Polling controller
        function pollNotifications() {
            fetch(`../notifications_api.php?action=fetch&limit=6`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Handle sound chime and toasts for new arrivals
                        if (!isInitialLoad) {
                            const newNotifs = data.notifications.filter(n => n.id > lastFetchedNotifId);
                            if (newNotifs.length > 0) {
                                // Detect SOS alerts first (highest priority)
                                const sosNotifs = newNotifs.filter(n => n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS'));
                                const orderNotifs = newNotifs.filter(n => n.type === 'order');

                                if (sosNotifs.length > 0) {
                                    // Urgent SOS alarm
                                    playSOSAlarm();
                                    sosNotifs.forEach(n => showToastNotification(n, true));
                                }

                                if (orderNotifs.length > 0) {
                                    playChimeSound();
                                }
                                
                                // Slide-in toast for all other new notifications
                                newNotifs.filter(n => !(n.type === 'system' && n.title && n.title.toUpperCase().includes('SOS')))
                                    .forEach(n => showToastNotification(n, false));

                                // Reload page history in case center tab is actively shown
                                const notifTab = document.getElementById('notifications-tab');
                                if (notifTab && notifTab.classList.contains('active')) {
                                    fetchNotificationsPage(notifCurrentPage);
                                }
                            }
                        }

                        // Maintain max tracked ID
                        if (data.notifications.length > 0) {
                            const maxId = Math.max(...data.notifications.map(n => n.id));
                            lastFetchedNotifId = Math.max(lastFetchedNotifId, maxId);
                        }
                        
                        isInitialLoad = false;
                        
                        // Populate badges and bell dropdown content
                        updateNotificationsDropdown(data.notifications, data.unread_count);
                    }
                })
                .catch(err => console.error("Error polling notifications:", err));
        }

        // Helper formatting functions
        function getNotifIcon(type, title) {
            if (type === 'system' && title && title.toUpperCase().includes('SOS')) {
                return 'fas fa-triangle-exclamation';
            }
            switch (type) {
                case 'order': return 'fas fa-receipt';
                case 'payment': return 'fas fa-wallet';
                case 'kitchen': return 'fas fa-fire-burner';
                case 'reservation': return 'fas fa-chair';
                case 'staff': return 'fas fa-user-tie';
                case 'system': return 'fas fa-cogs';
                default: return 'fas fa-bell';
            }
        }

        function getNotifClass(type) {
            return 'notif-' + type;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;")
                      .replace(/</g, "&lt;")
                      .replace(/>/g, "&gt;")
                      .replace(/"/g, "&quot;")
                      .replace(/'/g, "&#039;");
        }

        function formatRelativeTime(dateStr) {
            const date = new Date(dateStr.replace(/-/g, '/'));
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + 'm ago';
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return diffHours + 'h ago';
            
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays === 1) return 'Yesterday';
            if (diffDays < 7) return diffDays + 'd ago';
            
            return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        }

        function formatDateTime(dateStr) {
            const date = new Date(dateStr.replace(/-/g, '/'));
            return date.toLocaleString(undefined, { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        // Liquor Quota functions
        let verifiedUserId = null;

        function showToast(message, type = 'success') {
            showToastNotification({
                type: type === 'success' ? 'payment' : (type === 'error' ? 'system' : 'staff'),
                title: type.charAt(0).toUpperCase() + type.slice(1),
                body: message
            }, false);
        }

        function loadActiveQuotas() {
            const body = document.getElementById('activeQuotasTableBody');
            if (!body) return;
            body.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Loading...</td></tr>`;

            fetch('dashboardtest.php?action=load_active_quotas')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.quotas.length === 0) {
                            body.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No active customer quotas found.</td></tr>`;
                            return;
                        }
                        let html = '';
                        data.quotas.forEach(q => {
                            const total_pegs = parseInt(q.total_pegs);
                            const bottles = Math.floor(total_pegs / 8);
                            const pegs = total_pegs % 8;
                            html += `
                                <tr>
                                    <td>
                                        <strong>${escapeHtml(q.user_name)}</strong><br>
                                        <small class="text-muted">${escapeHtml(q.user_phone || q.user_email)}</small>
                                    </td>
                                    <td><span class="text-gold font-weight-bold">${escapeHtml(q.item_name)}</span></td>
                                    <td class="text-center"><strong>${bottles}</strong></td>
                                    <td class="text-center"><strong>${pegs}</strong></td>
                                    <td class="text-center">
                                        <button class="btn btn-gold-action btn-sm" onclick="selectQuotaForConsume('${escapeHtml(q.user_name)}', ${bottles > 0 || pegs > 0}, ${q.food_item_id})">
                                            <i class="fas fa-glass-water me-1"></i> Log Consume
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                        body.innerHTML = html;
                    } else {
                        body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${escapeHtml(data.message)}</td></tr>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">Network error loading quotas.</td></tr>`;
                });
        }

        function selectQuotaForConsume(customerName, hasQuota, brandId = null) {
            document.getElementById('consume_search_term').value = customerName;
            document.getElementById('consume_brand_id').innerHTML = '<option value="">-- Verifying... --</option>';
            document.getElementById('btn-admin-consume').disabled = true;
            verifiedUserId = null;
            
            showToast(`Selected ${customerName}. Verifying active quota...`, 'info');
            loadCustomerBrands(brandId); // Auto load it!
        }

        function loadCustomerBrands(autoSelectBrandId = null) {
            const searchTerm = document.getElementById('consume_search_term').value.trim();
            const brandSelect = document.getElementById('consume_brand_id');
            const brandSection = document.getElementById('consume_brand_section');
            const btnVerify = document.getElementById('btn-admin-verify');

            if (!searchTerm) {
                showToast('Please enter a search term.', 'error');
                return;
            }

            const origText = btnVerify.innerHTML;
            btnVerify.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Searching...';
            btnVerify.disabled = true;

            brandSection.style.display = 'none';
            document.getElementById('btn-admin-consume').disabled = true;
            verifiedUserId = null;

            const formData = new FormData();
            formData.append('search_term', searchTerm);

            fetch('dashboardtest.php?action=verify_order_liquor', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnVerify.innerHTML = origText;
                btnVerify.disabled = false;

                if (data.success) {
                    verifiedUserId = data.user_id;
                    let html = '<option value="">-- Choose Brand --</option>';
                    data.brands.forEach(b => {
                        const total_pegs = parseInt(b.total_pegs || 0);
                        html += `<option value="${b.food_item_id}">${escapeHtml(b.item_name)} (${total_pegs} pegs left)</option>`;
                    });
                    brandSelect.innerHTML = html;
                    
                    // Auto-select the brand if requested
                    if (autoSelectBrandId) {
                        brandSelect.value = autoSelectBrandId;
                    }
                    
                    // Reveal the hidden selection box and consume button
                    brandSection.style.display = 'block';
                    document.getElementById('btn-admin-consume').disabled = false;
                    
                    showToast('Customer verified successfully! Please select a brand to log peg consumption.', 'success');
                } else {
                    brandSelect.innerHTML = '<option value="">Verification Failed</option>';
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                btnVerify.innerHTML = origText;
                btnVerify.disabled = false;
                brandSelect.innerHTML = '<option value="">Error verifying customer</option>';
                showToast('Network error verifying customer.', 'error');
            });
        }

        function adminConsumePeg(e) {
            e.preventDefault();
            const searchTerm = document.getElementById('consume_search_term').value.trim();
            const brandId = document.getElementById('consume_brand_id').value;
            const btn = document.getElementById('btn-admin-consume');

            if (!verifiedUserId || !brandId || !searchTerm) {
                showToast('Please verify customer first.', 'error');
                return;
            }

            const origText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData();
            formData.append('user_id', verifiedUserId);
            formData.append('food_item_id', brandId);
            formData.append('search_term', searchTerm);

            fetch('dashboardtest.php?action=admin_consume_peg', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Reset form select & verify state
                    document.getElementById('consume_brand_id').innerHTML = '<option value="">-- Click Verify to load brands --</option>';
                    document.getElementById('btn-admin-consume').disabled = true;
                    verifiedUserId = null;
                    document.getElementById('consumePegForm').reset();
                    // Reload quotas list
                    loadActiveQuotas();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Network error logging peg.', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = origText;
            });
        }

        // Initialize notification elements on DOM load
        document.addEventListener('DOMContentLoaded', () => {
            // Set sound switch state and icons
            const toggleInput = document.getElementById('notificationSoundToggle');
            if (toggleInput) {
                toggleInput.checked = soundEnabled;
                toggleSoundPreference(soundEnabled);
            }
            
            // Start AJAX Polling
            pollNotifications();
            setInterval(pollNotifications, 15000);

            // Set up image dropzone drag & drop events
            setupImageDragAndDrop();
        });
    </script>

<script>
function toggleSidebar(){
 const sidebar=document.querySelector('.sidebar');
 const main=document.querySelector('.main-content');
 const btn=document.getElementById('sidebarToggle');
 if(window.innerWidth<=768){
  sidebar.classList.toggle('mobile-open');
  btn.classList.toggle('mobile-open');
  return;
 }
    sidebar.classList.toggle('collapsed');
   main.classList.toggle('expanded');
   btn.classList.toggle('closed');
   
 document.body.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed'));
 localStorage.setItem('sidebarCollapsed',sidebar.classList.contains('collapsed'));
}
document.addEventListener('DOMContentLoaded',()=>{
 const sidebar=document.querySelector('.sidebar');
 const main=document.querySelector('.main-content');
 const btn=document.getElementById('sidebarToggle');
 if(localStorage.getItem('sidebarCollapsed')==='true'){
      sidebar?.classList.add('collapsed');
    main?.classList.add('expanded');
    btn?.classList.add('closed');
    
  document.body.classList.add('sidebar-collapsed');
 }

 // Expand sidebar on click of collapsed sidebar's empty space
 sidebar?.addEventListener('click', (e) => {
     if (sidebar.classList.contains('collapsed') && !e.target.closest('.sidebar-link')) {
         toggleSidebar();
     }
 });
});

function printTableQR() {
    const tableLabel = document.getElementById('qrTableLabel').innerText;
    const qrContainer = document.getElementById('qrCodeContainer');
    
    // Some QR libraries generate a canvas, some generate an img, some generate both.
    let imgSrc = '';
    const canvasEl = qrContainer.querySelector('canvas');
    const imgEl = qrContainer.querySelector('img');
    
    if (canvasEl) {
        imgSrc = canvasEl.toDataURL("image/png");
    } else if (imgEl && imgEl.src) {
        imgSrc = imgEl.src;
    }
    
    if (!imgSrc) {
        alert("Please wait for the QR code to generate before printing.");
        return;
    }
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Print QR - ${tableLabel}</title>
            <style>
                body { font-family: 'Inter', sans-serif; text-align: center; margin-top: 50px; }
                .title { font-size: 32px; font-weight: bold; margin-bottom: 5px; color: #161412; }
                .subtitle { font-size: 16px; margin-bottom: 20px; color: #666; }
                img { max-width: 350px; height: auto; }
            </style>
        </head>
        <body>
            <div class="title">${tableLabel}</div>
            <div class="subtitle">Scan to land automatically on ordering menu</div>
            <img src="${imgSrc}" />
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 500);
                };
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}
    document.getElementById('massEmailForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('sendBtn');
        const alert = document.getElementById('emailAlert');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
        btn.disabled = true;
        alert.className = 'alert d-none';
        
        try {
            const formData = new FormData(this);
            const response = await fetch('api/send_newsletter.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            alert.classList.remove('d-none');
            if (result.success) {
                alert.classList.add('alert-success');
                alert.innerText = result.message;
                this.reset();
            } else {
                alert.classList.add('alert-danger');
                alert.innerText = result.message;
            }
        } catch (err) {
            alert.classList.remove('d-none');
            alert.classList.add('alert-danger');
            alert.innerText = 'Failed to send mass email due to a network error.';
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });

    // =====================================================================
    // CAMPAIGNS MANAGEMENT JS
    // =====================================================================
    function fetchCampaigns() {
        setTableLoading('campaigns-table-body', 8);
        fetch('dashboardtest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_campaigns'
        })
        .then(res => res.json())
        .then(data => {
            const tbody = document.getElementById('campaigns-table-body');
            if (!data.success || !data.campaigns || data.campaigns.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No campaigns found.</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.campaigns.map(c => {
                const isAct = parseInt(c.is_active) === 1;
                const typeStr = c.discount_type === 'percentage' ? c.discount_value + '%' : '₹' + c.discount_value;
                const statusBadge = isAct ? '<span class="status-badge bg-success text-dark">Active</span>' : '<span class="status-badge bg-danger text-white">Inactive</span>';
                
                let usageStr = c.used_count;
                if (c.usage_limit) usageStr += ' / ' + c.usage_limit;
                
                return `<tr>
                    <td><strong>${c.campaign_code}</strong></td>
                    <td class="text-gold">${typeStr}</td>
                    <td>₹${c.min_order_value}</td>
                    <td>${c.max_discount ? '₹'+c.max_discount : 'N/A'}</td>
                    <td>${c.expiry_date ? new Date(c.expiry_date).toLocaleDateString() : 'Never'}</td>
                    <td>${usageStr}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn-action-circle btn-action-circle-light" onclick='openEditCampaignModal(${JSON.stringify(c).replace(/'/g, "&#39;")})' title="Edit Campaign"><i class="fas fa-edit"></i></button>
                            <button class="btn-action-circle ${isAct ? 'btn-action-circle-warning' : 'btn-action-circle-success'}" onclick="toggleCampaign(${c.id})" title="${isAct ? 'Deactivate' : 'Activate'}"><i class="fas ${isAct ? 'fa-ban' : 'fa-check'}"></i></button>
                            <button class="btn-action-circle btn-action-circle-danger" style="background: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626;" onclick="deleteCampaign(${c.id})" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => showSearchError('campaigns-table-body', 8, 'Failed to load campaigns'));
    }

    function generateRandomCampaignCode() {
        const input = document.getElementById('campaign_code');
        if (input.readOnly) return; // Cannot modify if editing
        
        const rand4 = () => Math.random().toString(36).substring(2, 6).toUpperCase();
        let base = input.value.trim().toUpperCase();
        
        // If empty, or looks like a previously auto-generated code, replace entirely
        if (!base || /^[A-Z0-9]{4}-[A-Z0-9]{4}$/.test(base)) {
            input.value = rand4() + '-' + rand4();
            return;
        }
        
        // Otherwise, keep the custom event name the admin typed
        // If it already ends with -XXXX from a previous click, replace just that part
        if (/-[A-Z0-9]{4}$/.test(base)) {
            base = base.substring(0, base.length - 5);
        }
        
        input.value = base + '-' + rand4();
    }

    function openAddCampaignModal() {
        document.getElementById('campaignForm').reset();
        document.getElementById('campaign_id').value = '';
        document.getElementById('campaignModalLabel').innerText = 'Generate New Campaign';
        new bootstrap.Modal(document.getElementById('campaignModal')).show();
    }

    function openEditCampaignModal(campaign) {
        document.getElementById('campaignForm').reset();
        document.getElementById('campaign_id').value = campaign.id;
        document.getElementById('campaign_code').value = campaign.campaign_code;
        document.getElementById('campaign_code').readOnly = true;
        document.getElementById('discount_type').value = campaign.discount_type;
        document.getElementById('discount_value').value = campaign.discount_value;
        document.getElementById('min_order_value').value = campaign.min_order_value || '';
        document.getElementById('max_discount').value = campaign.max_discount || '';
        document.getElementById('expiry_date').value = campaign.expiry_date ? campaign.expiry_date.replace(' ', 'T').slice(0,16) : '';
        document.getElementById('usage_limit').value = campaign.usage_limit || '';
        
        document.getElementById('campaignModalLabel').innerText = 'Edit Campaign';
        new bootstrap.Modal(document.getElementById('campaignModal')).show();
    }

    document.getElementById('campaignForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('campaign_id').value;
        const formData = new URLSearchParams(new FormData(this));
        formData.append('action', id ? 'update_campaign' : 'create_campaign');
        if (id) formData.append('id', id);

        fetch('dashboardtest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('campaignModal')).hide();
                fetchCampaigns();
            } else {
                alert(data.message || 'Error saving campaign');
            }
        });
    });

    function toggleCampaign(id) {
        fetch('dashboardtest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=toggle_campaign&id=' + id
        })
        .then(() => fetchCampaigns());
    }

    function deleteCampaign(id) {
        if (!confirm('Are you sure you want to delete this campaign permanently?')) return;
        fetch('dashboardtest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_campaign&id=' + id
        })
        .then(() => fetchCampaigns());
    }
</script>

<!-- Campaign Modal -->
<div class="modal fade" id="campaignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-white">
            <div class="modal-header border-bottom border-dark">
                <h5 class="modal-title text-gold" id="campaignModalLabel">Generate Campaign</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="campaignForm">
                    <input type="hidden" name="campaign_id" id="campaign_id">
                    <div class="mb-3">
                        <label class="form-label text-white">Campaign Code</label>
                        <div class="input-group">
                            <input type="text" class="form-control bg-dark text-white border-secondary" name="code" id="campaign_code" required placeholder="e.g. SUMMER2026" style="text-transform: uppercase;">
                            <button class="btn btn-outline-secondary" type="button" onclick="generateRandomCampaignCode()" title="Generate Random Code">
                                <i class="fas fa-random"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label text-white">Discount Type</label>
                            <select class="form-select bg-dark text-white border-secondary" name="discount_type" id="discount_type" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="flat">Flat Amount (₹)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-white">Discount Value</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" name="discount_value" id="discount_value" required min="1" step="0.01">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label text-white">Min Order Value (₹)</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" name="min_order_value" id="min_order_value" min="0" step="0.01">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-white">Max Discount (₹)</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" name="max_discount" id="max_discount" min="0" step="0.01" placeholder="(Optional for %)">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label text-white">Expiry Date</label>
                            <input type="datetime-local" class="form-control bg-dark text-white border-secondary" name="expiry_date" id="expiry_date">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-white">Usage Limit</label>
                            <input type="number" class="form-control bg-dark text-white border-secondary" name="usage_limit" id="usage_limit" min="1" placeholder="(Total overall uses)">
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Coupon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tbody = document.getElementById('menu-search-results-body');
        if (tbody) {
            new Sortable(tbody, {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function (evt) {
                    const rowIds = Array.from(tbody.querySelectorAll('tr')).map(tr => tr.getAttribute('data-id')).filter(id => id);
                    if(rowIds.length > 0) {
                        fetch('dashboardtest.php?action=update_menu_order', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'order=' + encodeURIComponent(JSON.stringify(rowIds))
                        }).then(r => r.json()).then(res => {
                            if(res.success) showToast('Menu order updated!', 'success');
                            else showToast('Failed to update order.', 'error');
                        });
                    }
                }
            });
        }
    });

    // Categories and reordering variables
    let categoriesSortable = null;
    let dishesSortable = null;

    // Load categories dynamically for the Manage Categories list & dropdowns
    function openManageCategoriesModal() {
        fetch('dashboardtest.php?action=get_categories')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    populateCategoriesList(data.categories);
                    const modal = new bootstrap.Modal(document.getElementById('manageCategoriesModal'));
                    modal.show();
                } else {
                    showToast('Failed to load categories.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error fetching categories.', 'error');
            });
    }

    function populateCategoriesList(categories) {
        const list = document.getElementById('categories-sortable-list');
        list.innerHTML = '';
        categories.forEach(cat => {
            const li = document.createElement('li');
            li.className = 'list-group-item bg-dark border-secondary text-white d-flex align-items-center justify-content-between p-2';
            li.setAttribute('data-category', cat);
            li.innerHTML = `
                <div class="d-flex align-items-center flex-grow-1 gap-2">
                    <span class="category-drag-handle text-muted cursor-grab" style="cursor: move;"><i class="fas fa-grip-vertical"></i></span>
                    <input type="text" class="form-control form-control-sm bg-dark text-white border-0 py-0 category-name-input" value="${escapeHtml(cat)}" style="box-shadow: none;">
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="deleteCategoryRow(this, '${escapeHtml(cat)}')">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
            list.appendChild(li);
        });

        // Initialize Sortable on categories list
        if (categoriesSortable) {
            categoriesSortable.destroy();
        }
        categoriesSortable = new Sortable(list, {
            handle: '.category-drag-handle',
            animation: 150
        });
    }

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Add temporary new category row to the modal list
    function addNewCategoryRow() {
        const input = document.getElementById('new-category-input');
        const catName = input.value.trim();
        if (!catName) {
            showToast('Category name cannot be empty', 'error');
            return;
        }

        // Check for duplicates in list
        const existingInputs = Array.from(document.querySelectorAll('.category-name-input')).map(inp => inp.value.trim().toLowerCase());
        if (existingInputs.includes(catName.toLowerCase())) {
            showToast('Category already exists in list', 'error');
            return;
        }

        const list = document.getElementById('categories-sortable-list');
        const li = document.createElement('li');
        li.className = 'list-group-item bg-dark border-secondary text-white d-flex align-items-center justify-content-between p-2';
        li.setAttribute('data-category', ''); // empty means added fresh
        li.innerHTML = `
            <div class="d-flex align-items-center flex-grow-1 gap-2">
                <span class="category-drag-handle text-muted cursor-grab" style="cursor: move;"><i class="fas fa-grip-vertical"></i></span>
                <input type="text" class="form-control form-control-sm bg-dark text-white border-0 py-0 category-name-input" value="${escapeHtml(catName)}" style="box-shadow: none;">
            </div>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-2" onclick="deleteCategoryRow(this, '')">
                <i class="fas fa-trash-alt"></i>
            </button>
        `;
        list.appendChild(li);
        input.value = '';
    }

    // Delete a category row from the list (with DB check if it was pre-existing)
    function deleteCategoryRow(button, originalName) {
        const row = button.closest('li');
        if (!originalName) {
            // It's a newly added category, not saved in DB yet. Safe to delete.
            row.remove();
            return;
        }

        // Call backend to check if the category is used by dishes
        fetch('dashboardtest.php?action=check_category_usage&category=' + encodeURIComponent(originalName))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.count > 0) {
                        alert(`Cannot delete category "${originalName}" because it is currently used by ${data.count} dishes.\nPlease assign those dishes to another category first.`);
                    } else {
                        if (confirm(`Are you sure you want to delete category "${originalName}"?`)) {
                            row.remove();
                        }
                    }
                } else {
                    showToast('Failed to check category usage.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error checking category usage.', 'error');
            });
    }

    // Save the entire categories sequence (renamed, added, reordered) to the backend
    function saveCategoriesSequence() {
        const listItems = Array.from(document.querySelectorAll('#categories-sortable-list li'));
        const categoriesData = listItems.map(li => {
            return {
                original: li.getAttribute('data-category'), // empty if new
                current: li.querySelector('.category-name-input').value.trim()
            };
        });

        // Validation: check for empty names
        if (categoriesData.some(c => !c.current)) {
            showToast('Category names cannot be empty.', 'error');
            return;
        }

        // Validation: check for duplicates
        const names = categoriesData.map(c => c.current.toLowerCase());
        const uniqueNames = new Set(names);
        if (names.length !== uniqueNames.size) {
            showToast('Category names must be unique.', 'error');
            return;
        }

        fetch('dashboardtest.php?action=save_categories', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'categories=' + encodeURIComponent(JSON.stringify(categoriesData))
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Categories updated successfully!', 'success');
                // Hide modal
                const modalEl = document.getElementById('manageCategoriesModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                
                // Reload page to refresh all category dropdowns and menus
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Failed to save categories.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error saving categories.', 'error');
        });
    }

    // Reorder Dishes JS logic
    function openReorderDishesModal() {
        // Load categories into select
        fetch('dashboardtest.php?action=get_categories')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const select = document.getElementById('reorder-category-select');
                    select.innerHTML = '<option value="">-- Choose Category --</option>';
                    data.categories.forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = cat;
                        opt.textContent = cat;
                        select.appendChild(opt);
                    });

                    // Clear dishes list
                    document.getElementById('dishes-sortable-list').innerHTML = '';
                    document.getElementById('reorder-dishes-instruction').style.display = 'none';
                    document.getElementById('save-dishes-order-btn').disabled = true;

                    const modal = new bootstrap.Modal(document.getElementById('reorderDishesModal'));
                    modal.show();
                } else {
                    showToast('Failed to load categories.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error loading categories.', 'error');
            });
    }

    // Load dishes under selected category to sort
    function loadDishesForReordering(category) {
        const list = document.getElementById('dishes-sortable-list');
        list.innerHTML = '';
        document.getElementById('reorder-dishes-instruction').style.display = 'none';
        document.getElementById('save-dishes-order-btn').disabled = true;

        if (!category) return;

        fetch('dashboardtest.php?action=get_dishes_by_category&category=' + encodeURIComponent(category))
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.dishes.length === 0) {
                        list.innerHTML = '<div class="text-center p-3 text-muted">No dishes found in this category.</div>';
                        return;
                    }

                    document.getElementById('reorder-dishes-instruction').style.display = 'block';
                    document.getElementById('save-dishes-order-btn').disabled = false;

                    data.dishes.forEach(dish => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item bg-dark border-secondary text-white d-flex align-items-center justify-content-between p-2';
                        li.setAttribute('data-id', dish.id);
                        
                        const imageHtml = dish.image ? 
                            `<img src="../${escapeHtml(dish.image)}" alt="" class="rounded" style="width: 40px; height: 40px; object-fit: cover; border: 1px solid rgba(255,255,255,0.1);">` :
                            `<div class="rounded bg-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fas fa-utensils text-white-50"></i></div>`;

                        li.innerHTML = `
                            <div class="d-flex align-items-center gap-3">
                                <span class="dish-drag-handle text-muted cursor-grab" style="cursor: move;"><i class="fas fa-grip-vertical"></i></span>
                                ${imageHtml}
                                <div>
                                    <div class="fw-bold">${escapeHtml(dish.name)}</div>
                                    <div class="small text-muted">Price: ₹${parseFloat(dish.price).toFixed(2)} | Diet: ${escapeHtml(dish.diet_type)}</div>
                                </div>
                            </div>
                            <div class="text-muted small">ID: #${dish.id}</div>
                        `;
                        list.appendChild(li);
                    });

                    // Initialize Sortable on dishes list
                    if (dishesSortable) {
                        dishesSortable.destroy();
                    }
                    dishesSortable = new Sortable(list, {
                        handle: '.dish-drag-handle',
                        animation: 150
                    });
                } else {
                    showToast('Failed to load dishes.', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Error loading dishes.', 'error');
            });
    }

    // Save the reordered sequence of dishes
    function saveDishesSequence() {
        const listItems = Array.from(document.querySelectorAll('#dishes-sortable-list li'));
        const rowIds = listItems.map(li => li.getAttribute('data-id')).filter(id => id);

        if (rowIds.length === 0) {
            showToast('No dishes to sort.', 'error');
            return;
        }

        fetch('dashboardtest.php?action=update_menu_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order=' + encodeURIComponent(JSON.stringify(rowIds))
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('Dish order updated successfully!', 'success');
                const modalEl = document.getElementById('reorderDishesModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                
                // Refresh menu search table if open
                if (typeof performMenuSearch === 'function') {
                    performMenuSearch();
                }
            } else {
                showToast('Failed to update dish order.', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error saving dish sequence.', 'error');
        });
    }
</script>
</body>
</html>
















