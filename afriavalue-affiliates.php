<?php
/**
 * Plugin Name: AfriaValue Affiliate Program
 * Description: Custom affiliate program for AfriaValue service providers.
 * Version: 1.1
 * Author: AfriaValue
 */

if (!defined('ABSPATH')) exit;

// Set referral cookie securely
add_action('init', function () {
    if (isset($_GET['ref']) && is_numeric($_GET['ref'])) {
        $ref_id = absint($_GET['ref']);
        if ($ref_id > 0 && (!isset($_COOKIE['av_referral']) || absint($_COOKIE['av_referral']) === 0)) {
            setcookie(
                'av_referral',
                $ref_id,
                time() + (30 * DAY_IN_SECONDS),
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true // HttpOnly
            );
            $_COOKIE['av_referral'] = $ref_id;
            // Optional: remove error_log line for production
            error_log("🎯 Referral cookie set for user ID: $ref_id");
        }
    }
});

// Create affiliate referrals table on plugin activation
register_activation_hook(__FILE__, 'av_create_referrals_table');

function av_create_referrals_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'av_affiliate_referrals';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        referrer_id BIGINT UNSIGNED NOT NULL,
        order_id BIGINT UNSIGNED NOT NULL,
        commission_amount DECIMAL(10,2) NOT NULL,
        date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Include additional plugin files
add_action('plugins_loaded', function () {
    $includes = [
        'includes/register-affiliate.php',
        'includes/track-referrals.php',
        'includes/affiliate-dashboard.php',
        'includes/admin-panel.php',
        'includes/hooks.php'
    ];
    foreach ($includes as $file) {
        $path = plugin_dir_path(__FILE__) . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

// Handle affiliate registration securely
add_action('template_redirect', 'av_handle_affiliate_registration');
function av_handle_affiliate_registration() {
    if (!is_user_logged_in()) return;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['av_affiliate_submit'])) {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Sanitize inputs
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $commission = floatval($_POST['commission'] ?? 0);

        // Generate referral code
        $ref_code = sanitize_key($user->user_login . str_pad($user_id, 3, '0', STR_PAD_LEFT));

        // Save affiliate data
        update_user_meta($user_id, 'av_affiliate_first_name', $first_name);
        update_user_meta($user_id, 'av_affiliate_last_name', $last_name);
        update_user_meta($user_id, 'av_affiliate_email', $email);
        update_user_meta($user_id, 'av_commission_rate', $commission);
        update_user_meta($user_id, 'av_ref_code', $ref_code);
        update_user_meta($user_id, 'av_is_affiliate', true);

        // Redirect to dashboard
        wp_safe_redirect(home_url('/affiliate-program/'));
        exit;
    }
}

// Restrict dashboard to affiliates only
add_action('template_redirect', 'av_restrict_affiliate_dashboard');
function av_restrict_affiliate_dashboard() {
    if (is_page('affiliate-program')) {
        if (!is_user_logged_in()) {
            wp_safe_redirect('https://afrivalue.com/affiliate-registration/');
            exit;
        }

        $user_id = get_current_user_id();
        $commission_rate = floatval(get_user_meta($user_id, 'av_commission_rate', true));

        if ($commission_rate <= 0) {
            wp_safe_redirect('https://afrivalue.com/affiliate-registration/');
            exit;
        }
    }
}
