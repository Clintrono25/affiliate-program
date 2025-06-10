<?php
/**
 * Hooks file for the AfriValue Affiliates plugin.
 * Records affiliate commissions when WooCommerce orders are completed.
 */

if (!defined('ABSPATH')) exit;

// Enable this for logging (set to true in wp-config.php or plugin setup if needed)
if (!defined('AV_AFFILIATE_DEBUG')) define('AV_AFFILIATE_DEBUG', false);

/**
 * Capture affiliate ID from URL and store in WooCommerce session.
 */
add_action('init', function () {
    if (isset($_GET['ref'])) {
        $ref_id = absint($_GET['ref']);
        if ($ref_id > 0 && get_userdata($ref_id)) {
            WC()->session->set('av_affiliate_id', $ref_id);
        }
    }
});

/**
 * Save affiliate ID to order meta during checkout.
 */
add_action('woocommerce_checkout_create_order', function ($order, $data) {
    $affiliate_id = WC()->session->get('av_affiliate_id');
    if ($affiliate_id && get_userdata($affiliate_id)) {
        $order->update_meta_data('av_affiliate_id', $affiliate_id);
    }
}, 10, 2);

/**
 * Record affiliate commission when order is marked completed.
 */
add_action('woocommerce_order_status_completed', 'av_record_affiliate_commission');
add_action('init', function () {
    if (isset($_GET['ref'])) {
        $referrer_id = intval($_GET['ref']);
        if ($referrer_id > 0) {
            // Save to session for later use
            WC()->session->set('av_affiliate_id', $referrer_id);

            // Only log one click per page load or set a flag to prevent multiple entries
            if (!isset($_COOKIE['av_click_logged'])) {
                global $wpdb;
                $wpdb->insert(
                    "{$wpdb->prefix}av_affiliate_clicks",
                    [
                        'referrer_id' => $referrer_id,
                        'click_time'  => current_time('mysql')
                    ]
                );

                // Set a cookie for 1 day to avoid duplicate logging
                setcookie('av_click_logged', '1', time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }
});


function av_record_affiliate_commission($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        av_log_debug("No order found for ID: $order_id");
        return;
    }

    $affiliate_id = (int) get_post_meta($order_id, 'av_affiliate_id', true);
    if (!$affiliate_id || !get_userdata($affiliate_id)) {
        av_log_debug("Invalid or missing affiliate ID for order: $order_id");
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'av_affiliate_referrals';

    // Prevent duplicate records for same order
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE order_id = %d", $order_id
    ));
    if ($exists) {
        av_log_debug("Commission already recorded for order: $order_id");
        return;
    }

    $commission_rate = get_user_meta($affiliate_id, 'av_commission_rate', true);
    if (!$commission_rate || !is_numeric($commission_rate)) {
        $commission_rate = 10;
    }

    $commission_rate = (float) apply_filters('av_affiliate_commission_rate', $commission_rate, $affiliate_id, $order);
    $commission_amount = round(($order->get_total() * $commission_rate) / 100, 2);

    $wpdb->insert($table, [
        'referrer_id'       => $affiliate_id,
        'order_id'          => $order_id,
        'commission_amount' => $commission_amount,
        'payout_status'     => 'unpaid',
        'date'              => current_time('mysql'),
    ], ['%d', '%d', '%f', '%s', '%s']);

    av_log_debug("Commission recorded for order $order_id — Affiliate: $affiliate_id — Amount: $commission_amount");
}

/**
 * Helper: Conditional debug logger.
 */
function av_log_debug($message) {
    if (defined('AV_AFFILIATE_DEBUG') && AV_AFFILIATE_DEBUG) {
        error_log("[AfriValue Affiliate] $message");
    }
}
