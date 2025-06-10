<?php
if (!defined('ABSPATH')) exit;

add_shortcode('affiliate_dashboard', 'av_affiliate_dashboard_shortcode');

function av_affiliate_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . esc_url(wp_login_url()) . '">log in</a> to access your affiliate dashboard.</p>';
    }

    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js');
    wp_enqueue_script('av-affiliate-dashboard', get_stylesheet_directory_uri() . '/js/av-affiliate-dashboard.js', ['chartjs'], null, true);
    wp_enqueue_style('av-affiliate-dashboard-style', get_stylesheet_directory_uri() . '/css/av-affiliate-dashboard.css');
    


    $user_id = get_current_user_id();
    global $wpdb;

    // Handle settings update
// Handle settings update
// Handle settings update
if (isset($_POST['av_save_settings'])) {
    $new_rate = floatval($_POST['commission_rate']);

    if ($new_rate > 50) {
        $av_settings_error = "Commission rate cannot exceed 50%.";
    } else {
        update_user_meta($user_id, 'av_commission_rate', $new_rate);
        update_user_meta($user_id, 'av_payment_method', sanitize_text_field($_POST['payment_method']));
        update_user_meta($user_id, 'av_paypal_email', sanitize_email($_POST['paypal_email']));
    //    update_user_meta($user_id, 'av_bank_details', sanitize_textarea_field($_POST['bank_details']));
        update_user_meta($user_id, 'av_bank_holder_name', sanitize_text_field($_POST['bank_holder_name']));
        update_user_meta($user_id, 'av_bank_name', sanitize_text_field($_POST['bank_name']));
        update_user_meta($user_id, 'av_bank_account', sanitize_text_field($_POST['bank_account']));
        update_user_meta($user_id, 'av_bank_swift', sanitize_text_field($_POST['bank_swift']));
        update_user_meta($user_id, 'av_bank_country', sanitize_text_field($_POST['bank_country']));

        wp_redirect(get_permalink() . '?settings_saved=1');
        //exit;
    }
}



    // Get updated user meta
    $commission_rate = (float) get_user_meta($user_id, 'av_commission_rate', true);
    $paypal_email = get_user_meta($user_id, 'av_paypal_email', true);
    $bank_details = get_user_meta($user_id, 'av_bank_details', true);
    $payment_method = get_user_meta($user_id, 'av_payment_method', true);
    $bank_holder_name = get_user_meta($user_id, 'av_bank_holder_name', true);
    $bank_name = get_user_meta($user_id, 'av_bank_name', true);
    $bank_account = get_user_meta($user_id, 'av_bank_account', true);
    $bank_swift = get_user_meta($user_id, 'av_bank_swift', true);
    $bank_country = get_user_meta($user_id, 'av_bank_country', true);

    // Get referral stats
    $referral_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}av_affiliate_referrals WHERE referrer_id = %d", $user_id));
    $total_earned = (float) ($wpdb->get_var($wpdb->prepare("SELECT SUM(commission_amount) FROM {$wpdb->prefix}av_affiliate_referrals WHERE referrer_id = %d", $user_id)) ?: 0);
    $referral_link = esc_url(site_url("/?ref={$user_id}"));

    // Prepare statistics data
    $labels = $clicks_data = $earnings_data = [];
    $now = new DateTime();
    for ($i = 5; $i >= 0; $i--) {
        $month = (clone $now)->modify("-$i months");
        $start = $month->format('Y-m-01');
        $end = $month->format('Y-m-t');
        $label = $month->format('M Y');

        $clicks = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}av_affiliate_clicks WHERE referrer_id = %d AND click_time BETWEEN %s AND %s",
            $user_id, $start, $end
        ));

        $sum = (float) ($wpdb->get_var($wpdb->prepare(
            "SELECT SUM(commission_amount) FROM {$wpdb->prefix}av_affiliate_referrals WHERE referrer_id = %d AND date BETWEEN %s AND %s",
            $user_id, $start, $end
        )) ?: 0);

        $labels[] = $label;
        $clicks_data[] = $clicks;
        $earnings_data[] = round($sum, 2);
    }

    // Latest referrals
    $referrals = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}av_affiliate_referrals WHERE referrer_id = %d ORDER BY date DESC LIMIT 20",
        $user_id
    ));

// Handle withdraw request
if (isset($_POST['av_withdraw_request'])) {
    $amount = floatval($_POST['withdraw_amount']);
    
    // Get total withdrawn
    $total_withdrawn = (float) $wpdb->get_var(
        $wpdb->prepare("SELECT SUM(amount) FROM {$wpdb->prefix}av_affiliate_withdrawals WHERE user_id = %d AND status = %s", $user_id, 'approved')
    );
    $available_balance = max(0, $total_earned - $total_withdrawn);

    if ($amount >= 50 && $amount <= $available_balance) {
        $details = ($payment_method === 'paypal') ? $paypal_email : $bank_details;

        $wpdb->insert("{$wpdb->prefix}av_affiliate_withdrawals", [
            'user_id'    => $user_id,
            'amount'     => $amount,
            'status'     => 'pending',
            'method'     => $payment_method,
            'details'    => $details,
            'created_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);

        // Notify admin
        wp_mail(get_option('admin_email'), 'New Withdrawal Request', "User #$user_id requested \$$amount via $payment_method.");

        echo "<p class='av-success'><strong>Withdrawal request submitted.</strong></p>";
    } else {
        echo "<p class='av-error'><strong>Invalid amount. Must be at least \$50 and within your balance.</strong></p>";
    }
}

    
    // Fetch total earned (assuming you already calculate this)
$total_earned = floatval($total_earned); // already provided earlier

// Fetch total withdrawn from DB
$total_withdrawn = (float) $wpdb->get_var(
    $wpdb->prepare("
        SELECT SUM(amount)
        FROM {$wpdb->prefix}av_affiliate_withdrawals
        WHERE user_id = %d AND status IN ('pending', 'approved')
    ", $user_id)
);

// Ensure fallback if NULL
$total_withdrawn = $total_withdrawn ?: 0;

// Calculate available balance
$available_balance = max(0, $total_earned - $total_withdrawn);
// Calculate total pending withdrawal amount
$pending_withdrawals = (float) $wpdb->get_var(
    $wpdb->prepare("
        SELECT SUM(amount)
        FROM {$wpdb->prefix}av_affiliate_withdrawals
        WHERE user_id = %d AND status = 'pending'
    ", $user_id)
);
// Calculate total approved withdrawals
$approved_withdrawals = (float) $wpdb->get_var(
    $wpdb->prepare("
        SELECT SUM(amount)
        FROM {$wpdb->prefix}av_affiliate_withdrawals
        WHERE user_id = %d AND status = 'approved'
    ", $user_id)
);

$withdrawals = $wpdb->get_results(
    $wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}av_affiliate_withdrawals
        WHERE user_id = %d ORDER BY created_at DESC
    ", $user_id)
);

    wp_localize_script('av-affiliate-dashboard', 'avChartData', [
    'total_earned' => $total_earned,
    'approved_withdrawals' => $approved_withdrawals,
    'pending_withdrawals' => $pending_withdrawals,
    'available_balance' => $available_balance
]);
    ob_start();

?>

<div class="av-dashboard-container">
    <div class="av-sidebar">
        <h3>Affiliate Menu</h3>
        <ul>
            <li class="active" data-target="overview">Overview</li>
            <li data-target="referrals">Referral Log</li>
            <li data-target="statistics">Statistics</li>
            <li data-target="settings">Settings</li>
            <li data-target="withdraw">Withdraw</li>
            <li data-target="support">Support</li>
            <li><a href="<?php echo esc_url(wp_logout_url()); ?>">Logout</a></li>
        </ul>
    </div>

    <div class="av-dashboard-content">

        <!-- Overview -->
        <div class="av-section" id="overview">
            <h2>Overview</h2>
            <div class="av-card-grid">
                <div class="av-card"><strong>Total Earned</strong><span>$<?php echo number_format($total_earned, 2); ?></span></div>
                <div class="av-card"><strong>Available Balance</strong><span>$<?php echo number_format($available_balance, 2); ?></span></div>
                <div class="av-card"><strong>Pending Withdrawals</strong><span>$<?php echo number_format($pending_withdrawals, 2); ?></span></div>
                <div class="av-card"><strong>Approved Withdrawals</strong><span>$<?php echo number_format($approved_withdrawals, 2); ?></span></div>
                <div class="av-card"><strong>Referral Link</strong><input type="text" value="<?php echo esc_attr($referral_link); ?>" readonly onclick="this.select()" /></div>
                <div class="av-card"><strong>Commission Rate</strong><span><?php echo esc_html($commission_rate); ?>%</span></div>
                <div class="av-card"><strong>Total Referrals</strong><span><?php echo intval($referral_count); ?></span></div>
            </div>

            <div class="av-subsection">
                <h3>Earnings Overview</h3>
                <canvas id="overviewEarningsChart" width="400" height="200"></canvas>
            </div>

            <div class="av-subsection">
                <h3>Withdrawal History</h3>
                <table class="av-table">
                    <thead>
                        <tr><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($withdrawals): foreach ($withdrawals as $withdrawal): ?>
                        <tr>
                            <td>$<?php echo number_format($withdrawal->amount, 2); ?></td>
                            <td><?php echo esc_html(ucfirst($withdrawal->method)); ?></td>
                            <td><?php echo esc_html(ucfirst($withdrawal->status)); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($withdrawal->created_at)); ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4">No withdrawal history found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Referrals -->
        <div class="av-section" id="referrals">
            <h2>Referral Log</h2>
            <table class="av-table">
                <thead><tr><th>Date</th><th>Order ID</th><th>Commission</th></tr></thead>
                <tbody>
                <?php if ($referrals): foreach ($referrals as $ref): ?>
                    <tr>
                        <td><?php echo esc_html($ref->date); ?></td>
                        <td><?php echo esc_html($ref->order_id); ?></td>
                        <td>$<?php echo number_format($ref->commission_amount, 2); ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3">No referrals found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Statistics -->
        <div class="av-section" id="statistics">
            <h2>Statistics</h2>
            <canvas id="clicksChart" height="100"></canvas>
            <canvas id="earningsChart" height="100"></canvas>
        </div>

        <!-- Settings -->
        <div class="av-section" id="settings">
            <h2>Settings</h2>
            <?php if (!empty($av_settings_error)): ?>
                <p class="av-error"><strong><?php echo esc_html($av_settings_error); ?></strong></p>
            <?php elseif (isset($_GET['settings_saved'])): ?>
                <p class="av-success"><strong>Settings saved successfully.</strong></p>
            <?php endif; ?>

            <form method="post">
                <label>Commission Rate (%)<br>
                    <input type="number" name="commission_rate" value="<?php echo esc_attr($commission_rate); ?>" min="0" max="100" step="0.1" />
                </label><br><br>

                <label>Payment Method<br>
                    <select name="payment_method" id="withdraw_method">
                        <option value="paypal" <?php selected($payment_method, 'paypal'); ?>>PayPal</option>
                        <option value="bank" <?php selected($payment_method, 'bank'); ?>>Bank Transfer</option>
                    </select>
                </label><br><br>

                <div id="paypal_fields" style="display:<?php echo ($payment_method === 'paypal') ? 'block' : 'none'; ?>;">
                    <label>PayPal Email<br>
                        <input type="email" name="paypal_email" value="<?php echo esc_attr($paypal_email); ?>" />
                    </label>
                </div>

                <div id="bank_fields" style="display:<?php echo ($payment_method === 'bank') ? 'block' : 'none'; ?>;">
                    <label>Account Holder Name<br>
                        <input type="text" name="bank_holder_name" value="<?php echo esc_attr($bank_holder_name); ?>" />
                    </label><br><br>
                    <label>Bank Name<br>
                        <input type="text" name="bank_name" value="<?php echo esc_attr($bank_name); ?>" />
                    </label><br><br>
                    <label>Account Number / IBAN<br>
                        <input type="text" name="bank_account" value="<?php echo esc_attr($bank_account); ?>" />
                    </label><br><br>
                    <label>SWIFT / BIC Code<br>
                        <input type="text" name="bank_swift" value="<?php echo esc_attr($bank_swift); ?>" />
                    </label><br><br>
                    <label>Bank Country<br>
                        <input type="text" name="bank_country" value="<?php echo esc_attr($bank_country); ?>" />
                    </label><br><br>
                </div>

                <input type="submit" name="av_save_settings" value="Save Settings" />
            </form>
        </div>

        <!-- Withdraw -->
        <div class="av-section" id="withdraw">
            <h2>Withdraw</h2>
            <p>You can request a withdrawal if your balance is at least $50. You currently have: <strong>$<?php echo number_format($available_balance, 2); ?></strong></p>
            <form method="post">
                <label>Amount to Withdraw ($)<br>
                    <input type="number" name="withdraw_amount" min="50" max="<?php echo esc_attr($available_balance); ?>" step="0.01" required />
                </label><br><br>
                <input type="submit" name="av_withdraw_request" value="Request Withdrawal" />
            </form>
        </div>

        <!-- Support -->
        <div class="av-section" id="support">
            <h2>Support</h2>
            <p>For any support or questions, please <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>">contact us</a>.</p>
        </div>

    </div>
</div>



    <?php
    return ob_get_clean();
}
