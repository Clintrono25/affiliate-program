<?php
/**
 * Track affiliate commission for each WooCommerce order.
 */
add_action('woocommerce_order_status_processing', 'av_track_affiliate_commission_debug');
add_action('woocommerce_order_status_completed', function($order_id){
    error_log("🎉 WooCommerce Thankyou hook triggered for order: $order_id");
});

function av_track_affiliate_commission_debug($order_id) {
    error_log("✅ WooCommerce thankyou hook fired! Order ID: $order_id");

    // 1️⃣ Check if referral cookie exists
    if (!isset($_COOKIE['av_referral'])) {
        error_log("⚠️ No referral cookie found for order $order_id");
        return;
    }

    // 2️⃣ Get user ID directly from cookie
    $ref_user_id = intval($_COOKIE['av_referral']);
    error_log("🔍 Referral user ID from cookie: $ref_user_id");

    // 3️⃣ Get the user by ID
    $user = get_user_by('id', $ref_user_id);
    if (!$user) {
        error_log("❌ No user found for referral user ID: $ref_user_id");
        return;
    }

    error_log("👤 Found user ID: {$user->ID} for referral tracking.");

    // 4️⃣ Get user's commission rate
    $rate = get_user_meta($user->ID, 'av_commission_rate', true);
    if (empty($rate)) {
        error_log("⚠️ No commission rate set for user ID {$user->ID}, using default 0%.");
        $rate = 0;
    }
    error_log("💰 Commission rate for user {$user->ID}: $rate%");

    // 5️⃣ Get the order total
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log("❌ Order $order_id not found!");
        return;
    }
    $total = $order->get_total();
    error_log("🛒 Order total for $order_id: $total");

    // 6️⃣ Calculate commission
    $commission = ($rate / 100) * $total;
    error_log("💸 Calculated commission for order $order_id: $commission");

    // 7️⃣ Insert referral record into custom table
    global $wpdb;
    $table = $wpdb->prefix . 'av_affiliate_referrals';
$insert_data = [
    'referrer_id'       => $user->ID,
    'order_id'          => $order_id,
    'commission_amount' => $commission,
    'date'              => current_time('mysql')
];
    $insert_result = $wpdb->insert($table, $insert_data);

    if ($insert_result === false) {
        error_log("❌ DB Insert failed for order $order_id: " . $wpdb->last_error);
    } else {
        error_log("✅ DB Inserted commission successfully for order $order_id!");
    }
}
// Track clicks
add_action('init', function() {
    if (isset($_GET['ref'])) {
        $ref_id = intval($_GET['ref']);
        if ($ref_id > 0) {
            global $wpdb;
            $table = $wpdb->prefix . 'av_affiliate_clicks';
            $wpdb->insert($table, [
                'referrer_id' => $ref_id,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'date'       => current_time('mysql'),
            ]);
        }
    }
});

