<?php
function av_affiliate_registration_form() {
    if (!is_user_logged_in()) {
        return "<p>You must be logged in to register as an affiliate.</p>";
    }

    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $roles = (array) $user->roles;

    // Only allow Subscribers
    if (!in_array('subscriber', $roles)) {
        return "<p>Only logged in users can join the affiliate program.</p>";
    }

    // Already registered?
    $is_affiliate = get_user_meta($user_id, 'av_is_affiliate', true);
    if ($is_affiliate) {
        $ref_code = get_user_meta($user_id, 'av_ref_code', true);
        $commission = get_user_meta($user_id, 'av_commission_rate', true);
        $ref_link = home_url("?ref=" . $ref_code);

        return "
        <div class='av-affiliate-register av-affiliate-register-success'>
            <p>You are already an affiliate!</p>
            <p><strong>Your Referral Link:</strong><br>
            <input type='text' readonly value='$ref_link' style='width:100%;' /></p>
            <p><strong>Your Commission Rate:</strong> $commission%</p>
        </div>";
    }

    // Pre-fill values
    $first_name = esc_attr(get_user_meta($user_id, 'first_name', true));
    $last_name = esc_attr(get_user_meta($user_id, 'last_name', true));
    $email = esc_attr($user->user_email);
    $avatar = get_avatar($user_id, 96);

    ob_start(); ?>
    <div class="av-affiliate-register">
        <h3>Join the Affiliate Program</h3>
        <div style="text-align: center; margin-bottom: 1rem;"><?php echo $avatar; ?></div>
        <form method="POST">
            <div class="form-group">
                <label>First Name:</label>
                <input type="text" name="first_name" required value="<?php echo $first_name; ?>">
            </div>

            <div class="form-group">
                <label>Last Name:</label>
                <input type="text" name="last_name" required value="<?php echo $last_name; ?>">
            </div>

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required value="<?php echo $email; ?>">
            </div>

            <div class="form-group">
                <label>Select Your Commission Rate:</label>
                <select name="commission" required>
                    <option value="5">5%</option>
                    <option value="10">10%</option>
                    <option value="15">15%</option>
                    <option value="15">20%</option>
                    <option value="15">25%</option>
                    <option value="15">30%</option>
                    <option value="15">35%</option>
                    <option value="15">40%</option>
                    <option value="15">45%</option>
                    <option value="15">50%</option>
                    <option value="15">55%</option>
                    <option value="15">65%</option>
                    <option value="15">70%</option>
                    <option value="15">75%</option>
                    <option value="15">80%</option>
                    <option value="15">85%</option>
                    <option value="15">90%</option>
                </select>
            </div>

            <button type="submit" name="av_affiliate_submit" class="av-button">Register as Affiliate</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('av_register_affiliate', 'av_affiliate_registration_form');

?>
