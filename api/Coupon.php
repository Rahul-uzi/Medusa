<?php

class Coupon {
    public $id;
    public $user_id;
    public $review_id;
    public $coupon_code;
    public $campaign_code;
    public $discount_type;
    public $discount_value;
    public $expires_at;
    public $redeemed_at;
    public $order_id;
    public $status;
    public $created_at;
    public $updated_at;

    /**
     * Coupon constructor.
     * @param array $data
     */
    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
