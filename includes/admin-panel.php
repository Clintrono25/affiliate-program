<?php
/**
 * Admin Panel for AfriaValue Affiliate Program
 */

// Add admin menu item
function av_affiliate_admin_menu() {
    add_menu_page(
        'AfriaValue Affiliates',
        'Affiliates',
        'manage_options',
        'av-affiliates',
        'av_affiliate_admin_page',
        'dashicons-groups',
        26
    );
}
add_action('admin_menu', 'av_affiliate_admin_menu');

// Admin page content
function av_affiliate_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'av_affiliate_referrals';

    // Pagination setup
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;

    // Get affiliate users
    $args = [
        'meta_key'   => 'av_is_affiliate',
        'meta_value' => true,
        'number'     => $per_page,
        'offset'     => $offset,
        'orderby'    => 'user_login',
        'order'      => 'ASC'
    ];
    $affiliates = get_users($args);

    $total_affiliates = count(get_users([
        'meta_key'   => 'av_is_affiliate',
        'meta_value' => true,
        'fields'     => 'ID'
    ]));
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">AfriaValue Affiliates</h1>
        <a href="<?php echo admin_url('admin.php?page=av-affiliates'); ?>" class="page-title-action">Refresh</a>
        <hr class="wp-header-end">

        <!-- Tools/Actions -->
        <div class="av-tools" style="margin-bottom: 15px;">
            <form method="get" action="" style="display: inline-block; margin-right: 20px;">
                <input type="hidden" name="page" value="av-affiliates" />
                <input type="search" name="search" placeholder="Search affiliates..." value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>" />
                <button type="submit" class="button">Search</button>
            </form>
            <a href="<?php echo admin_url('admin.php?page=av-affiliates&export=csv'); ?>" class="button button-primary">
                <span class="dashicons dashicons-download"></span> Export CSV
            </a>
            <a href="<?php echo admin_url('admin.php?page=av-affiliates&action=settings'); ?>" class="button">
                <span class="dashicons dashicons-admin-generic"></span> Settings
            </a>
        </div>

        <?php if ($affiliates): ?>
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th>Name / Email</th>
                        <th>Affiliate ID</th>
                        <th>Group</th>
                        <th>Username</th>
                        <th>Paid Earnings</th>
                        <th>Unpaid Earnings</th>
                        <th>Rate</th>
                        <th>Unpaid Referrals</th>
                        <th>Paid Referrals</th>
                        <th>Visits</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affiliates as $user): ?>
                        <?php
                        $affiliate_id = $user->ID;
                        $username = esc_html($user->user_login);
                        $email = esc_html($user->user_email);
                        $group = get_user_meta($affiliate_id, 'av_affiliate_group', true) ?: '-';
                        $rate = get_user_meta($affiliate_id, 'av_commission_rate', true) ?: '0';
                        $paid_earnings = $wpdb->get_var($wpdb->prepare(
                            "SELECT SUM(commission_amount) FROM $table WHERE referrer_id = %d AND payout_status = 'paid'",
                            $affiliate_id
                        )) ?: 0;
                        $unpaid_earnings = $wpdb->get_var($wpdb->prepare(
                            "SELECT SUM(commission_amount) FROM $table WHERE referrer_id = %d AND payout_status = 'unpaid'",
                            $affiliate_id
                        )) ?: 0;
                        $unpaid_referrals = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table WHERE referrer_id = %d AND payout_status = 'unpaid'",
                            $affiliate_id
                        )) ?: 0;
                        $paid_referrals = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table WHERE referrer_id = %d AND payout_status = 'paid'",
                            $affiliate_id
                        )) ?: 0;
                        $visits = get_user_meta($affiliate_id, 'av_affiliate_visits', true) ?: 0;
                        $status = get_user_meta($affiliate_id, 'av_affiliate_status', true) ?: 'Active';
                        ?>
                        <tr>
                            <td><?php echo $username; ?><br><small><?php echo $email; ?></small></td>
                            <td><?php echo $affiliate_id; ?></td>
                            <td><?php echo esc_html($group); ?></td>
                            <td><?php echo $username; ?></td>
                            <td>$<?php echo number_format($paid_earnings, 2); ?></td>
                            <td>$<?php echo number_format($unpaid_earnings, 2); ?></td>
                            <td><?php echo esc_html($rate); ?>%</td>
                            <td><?php echo $unpaid_referrals; ?></td>
                            <td><?php echo $paid_referrals; ?></td>
                            <td><?php echo $visits; ?></td>
                            <td><?php echo esc_html($status); ?></td>
                            <td>
                                <a href="<?php echo admin_url("user-edit.php?user_id=$affiliate_id"); ?>" class="button button-small">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Pagination links
            $total_pages = ceil($total_affiliates / $per_page);
            if ($total_pages > 1) {
                echo "<div class='tablenav'><div class='tablenav-pages'>";
                echo paginate_links([
                    'base'      => add_query_arg('paged', '%#%'),
                    'format'    => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total'     => $total_pages,
                    'current'   => $paged
                ]);
                echo "</div></div>";
            }
            ?>
        <?php else: ?>
            <p>No affiliates found.</p>
        <?php endif; ?>
    </div>
    <?php
}
