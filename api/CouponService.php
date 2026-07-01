<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Coupon.php';

class CouponService {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTableExists();
    }

    /**
     * Ensure the coupons table exists in the database.
     */
    private function ensureTableExists() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS `coupons` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT DEFAULT NULL,
                    `review_id` INT DEFAULT NULL,
                    `coupon_code` VARCHAR(50) NOT NULL UNIQUE,
                    `campaign_code` VARCHAR(50) NOT NULL,
                    `discount_type` VARCHAR(20) NOT NULL DEFAULT 'percentage',
                    `discount_value` DECIMAL(10, 2) NOT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `redeemed_at` DATETIME DEFAULT NULL,
                    `order_id` INT DEFAULT NULL,
                    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_coupons_user_id` (`user_id`),
                    INDEX `idx_coupons_status` (`status`),
                    INDEX `idx_coupons_expires_at` (`expires_at`),
                    CONSTRAINT `fk_coupons_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_coupons_feedback` FOREIGN KEY (`review_id`) REFERENCES `feedback` (`id`) ON DELETE SET NULL,
                    CONSTRAINT `fk_coupons_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            // Fail silently or log depending on requirements
            error_log("Error creating coupons table: " . $e->getMessage());
        }
    }

    /**
     * Generate a unique discount coupon code for a 5-star review.
     * 
     * @param int|null $userId
     * @param int $reviewId
     * @param string $campaignCode
     * @return string Generated coupon code
     * @throws Exception
     */
    public function generateCoupon($userId, $reviewId, $campaignCode) {
        // Prevent duplicate coupon for the same review
        if ($reviewId !== null) {
            $check = $this->pdo->prepare("SELECT coupon_code FROM coupons WHERE review_id = ?");
            $check->execute([$reviewId]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                return $existing['coupon_code'];
            }
        }
        
        // Prevent duplicate ACTIVE coupons for the same user and campaign
        if ($userId !== null) {
            $checkUserCampaign = $this->pdo->prepare("SELECT coupon_code FROM coupons WHERE user_id = ? AND campaign_code = ? AND status = 'active'");
            $checkUserCampaign->execute([$userId, $campaignCode]);
            $existingActive = $checkUserCampaign->fetch(PDO::FETCH_ASSOC);
            if ($existingActive) {
                return $existingActive['coupon_code'];
            }
        }

        $secretKey = get_env_var('COUPON_SECRET_KEY', 'MedusaDefaultSecretKey2026!');
        $expiryDays = intval(get_env_var('DEFAULT_COUPON_EXPIRY_DAYS', 30));
        $discountValue = floatval(get_env_var('DEFAULT_DISCOUNT_VALUE', 10.00));

        $maxAttempts = 5;
        $attempt = 0;
        $couponCode = '';

        while ($attempt < $maxAttempts) {
            $timestamp = time() . '_' . rand(1000, 9999);
            $couponSeed = ($userId ?? 'GUEST') . '_' . $reviewId . '_' . $campaignCode . '_' . $timestamp . '_' . $secretKey;
            $couponHash = hash('sha256', $couponSeed);
            $hashPart = strtoupper(substr($couponHash, 0, 8));
            $couponCode = '5STAR-' . strtoupper($campaignCode) . '-' . $hashPart;

            // Check uniqueness in database
            $checkCode = $this->pdo->prepare("SELECT id FROM coupons WHERE coupon_code = ?");
            $checkCode->execute([$couponCode]);
            if (!$checkCode->fetch()) {
                break;
            }
            $attempt++;
        }

        if ($attempt >= $maxAttempts) {
            throw new Exception("Failed to generate a unique coupon code after maximum attempts.");
        }

        $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));

        $stmt = $this->pdo->prepare("
            INSERT INTO coupons (user_id, review_id, coupon_code, campaign_code, discount_type, discount_value, expires_at, status)
            VALUES (?, ?, ?, ?, 'percentage', ?, ?, 'active')
        ");
        $stmt->execute([$userId, $reviewId, $couponCode, $campaignCode, $discountValue, $expiresAt]);

        return $couponCode;
    }

    /**
     * Validate a coupon code.
     * 
     * @param string $couponCode
     * @return Coupon Valid coupon entity
     * @throws Exception If coupon is invalid, expired, or redeemed
     */
    public function validateCoupon($couponCode) {
        $couponCode = strtoupper(trim($couponCode));
        
        $stmt = $this->pdo->prepare("SELECT * FROM coupons WHERE coupon_code = ?");
        $stmt->execute([$couponCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception("Coupon code not found.");
        }

        $coupon = new Coupon($row);

        // Check if expired and update status
        if ($coupon->status === 'active' && strtotime($coupon->expires_at) < time()) {
            $update = $this->pdo->prepare("UPDATE coupons SET status = 'expired' WHERE id = ?");
            $update->execute([$coupon->id]);
            $coupon->status = 'expired';
        }

        if ($coupon->status === 'redeemed') {
            throw new Exception("This coupon has already been redeemed.");
        }

        if ($coupon->status === 'expired') {
            throw new Exception("This coupon has expired.");
        }

        if ($coupon->status !== 'active') {
            throw new Exception("This coupon is inactive.");
        }

        return $coupon;
    }

    /**
     * Redeem a coupon.
     * 
     * @param string $couponCode
     * @param int $orderId
     * @return bool Success status
     * @throws Exception If coupon cannot be redeemed
     */
    public function redeemCoupon($couponCode, $orderId) {
        $couponCode = strtoupper(trim($couponCode));
        
        // Validate first
        $this->validateCoupon($couponCode);

        $stmt = $this->pdo->prepare("
            UPDATE coupons 
            SET status = 'redeemed', redeemed_at = NOW(), order_id = ? 
            WHERE coupon_code = ? AND status = 'active'
        ");
        $stmt->execute([$orderId, $couponCode]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Failed to redeem coupon.");
        }

        return true;
    }

    /**
     * Get all coupons for a given user.
     * 
     * @param int $userId
     * @return Coupon[] Array of Coupon entities
     */
    public function getUserCoupons($userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM coupons WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $coupons = [];
        foreach ($rows as $row) {
            $coupons[] = new Coupon($row);
        }
        return $coupons;
    }

    /**
     * Mark all past-due active coupons as expired.
     */
    public function expireCoupons() {
        $stmt = $this->pdo->prepare("
            UPDATE coupons 
            SET status = 'expired' 
            WHERE status = 'active' AND expires_at < NOW()
        ");
        $stmt->execute();
    }
}
