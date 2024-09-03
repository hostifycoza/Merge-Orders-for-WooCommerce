<?php
/**
 * Plugin Name: Merge Orders for WooCommerce
 * Plugin URI:  https://dev.hostify.co.za
 * Description: Merges multiple pending payment orders from the same customer into one, fully compatible with WooCommerce HPOS. Supports integration with YITH WooCommerce Auctions.
 * Version:     2.0
 * Author:      Hostify
 * Author URI:  https://hostify.co.za
 * Text Domain: merge-orders-for-woocommerce
 * Requires PHP: 8.1
 * Requires at least: 6.4.3
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Check if WooCommerce is active and version meets requirements.
 */
function check_woocommerce_version() {
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '>=')) {
            add_action('admin_menu', 'merge_orders_menu');
        }
    }
}
add_action('plugins_loaded', 'check_woocommerce_version');

/**
 * Register custom order status for merged orders.
 */
function register_merged_order_status() {
    register_post_status('wc-merged', array(
        'label'                     => _n_noop('Merged <span class="count">(%s)</span>', 'Merged <span class="count">(%s)</span>'),
        'public'                    => false,
        'exclude_from_search'       => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Merged <span class="count">(%s)</span>', 'Merged <span class="count">(%s)</span>')
    ));
}
add_action('init', 'register_merged_order_status');

/**
 * Add merged order status to WooCommerce order filters in the admin.
 */
function add_merged_to_order_statuses($order_statuses) {
    $new_order_statuses = array();

    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-merged'] = 'Merged';
        }
    }
    return $new_order_statuses;
}
add_filter('wc_order_statuses', 'add_merged_to_order_statuses');

/**
 * Merge selected orders into a new order.
 */
function merge_orders($order_ids) {
    if (count($order_ids) === 1) {
        $single_order = wc_get_order($order_ids[0]);
        if ($single_order) {
            $single_order->set_status('pending-payment', 'Single order, set to pending payment.');
            $single_order->save();

            WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger($single_order->get_id());
            send_developer_confirmation($single_order->get_order_number());

            return $single_order->get_id();
        }
    }

    $new_order = wc_create_order();
    $customer_email = '';
    $billing_address = array();
    $shipping_address = array();

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;

        if (empty($customer_email)) {
            $customer_email = $order->get_billing_email();
        }

        if (empty($billing_address)) {
            $billing_address = $order->get_address('billing');
        }

        if (empty($shipping_address)) {
            $shipping_address = $order->get_address('shipping');
        }

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $new_item = new WC_Order_Item_Product();
                $new_item->set_props(array(
                    'product'  => $product,
                    'quantity' => $item->get_quantity(),
                    'subtotal' => $item->get_subtotal(),
                    'total'    => $item->get_total(),
                ));
                $new_order->add_item($new_item);
            }
        }

        $order->update_status('wc-merged', 'Order merged into order #' . $new_order->get_id(), true);
        $order->save();
    }

    $new_order->set_status('pending-payment', 'Newly merged order, pending payment.');

    if (!empty($customer_email)) {
        $new_order->set_billing_email($customer_email);
    }

    if (!empty($billing_address)) {
        $new_order->set_address($billing_address, 'billing');
    }

    if (!empty($shipping_address)) {
        $new_order->set_address($shipping_address, 'shipping');
    }

    $new_order->calculate_totals();
    $new_order->save();

    if (!empty($customer_email)) {
        $mailer = WC()->mailer();
        $mails = $mailer->get_emails();
        if (!empty($mails) && isset($mails['WC_Email_Customer_Invoice'])) {
            $mails['WC_Email_Customer_Invoice']->trigger($new_order->get_id());
        }
        send_developer_confirmation($new_order->get_order_number());
    }

    return $new_order->get_id();
}

/**
 * Send a confirmation email to the developer.
 */
function send_developer_confirmation($order_number) {
    $to = 'henk@vintagevault.co.za';
    $subject = 'Invoice Sent for Order #' . $order_number;
    $body = 'An invoice for order #' . $order_number . ' has been sent to the customer.';
    $headers = array('Content-Type: text/html; charset=UTF-8');

    wp_mail($to, $subject, $body, $headers);
}

/**
 * Register the Merge Orders menu in WooCommerce admin.
 */
function merge_orders_menu() {
    add_submenu_page(
        'woocommerce',
        'Merge Orders',
        'Merge Orders',
        'manage_woocommerce',
        'merge-orders',
        'merge_orders_admin_page'
    );

    add_submenu_page(
        null,
        __('Merge Orders Confirmation', 'merge-orders-for-woocommerce'),
        __('Merge Orders Confirmation', 'merge-orders-for-woocommerce'),
        'manage_woocommerce',
        'merge-orders-confirmation',
        'merge_orders_confirmation_page_callback'
    );
}
add_action('admin_menu', 'merge_orders_menu');

/**
 * Display callback for the Merge Orders admin page.
 */
function merge_orders_admin_page() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $orders = get_orders_to_merge();

    echo '<div class="wrap"><h1>Merge Orders</h1>';
    echo '<div class="merge-orders-instructions">';
    echo '<h2>Instructions:</h2>';
    echo '<p>Select the orders you wish to merge and click "Merge Selected Orders".</p>';
    echo '</div>';

    if (!empty($orders)) {
        echo '<form id="merge-orders-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="merge_selected_orders">';
        wp_nonce_field('merge_orders_nonce_action', 'merge_orders_nonce_name');

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Select</th><th>Order ID</th><th>Customer Name</th><th>Date</th><th>Status</th><th>Total Price</th></tr></thead>';
        echo '<tbody>';

        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            $user = $order->get_user();
            $user_name = is_a($user, 'WP_User') ? $user->user_login : 'Guest';
            $total_price = $order->get_total();

            echo '<tr>';
            echo '<td><input type="checkbox" name="order_ids[]" value="' . esc_attr($order_id) . '"></td>';
            echo '<td>' . esc_html($order->get_order_number()) . '</td>';
            echo '<td>' . esc_html($user_name) . '</td>';
            echo '<td>' . esc_html($order->get_date_created()->date('Y-m-d H:i:s')) . '</td>';
            echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
            echo '<td>' . wc_price($total_price) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<br>';
        echo '<button type="submit" class="button action">Merge / Process Selected Orders</button>';
        echo '</form>';
    } else {
        echo '<p>No eligible orders found for merging.</p>';
    }

    echo '<div class="merge-orders-footer">';
    echo '<br>';
    echo 'Powered by <a href="https://hostify.co.za" target="_blank">Hostify</a>. Need help? Visit our <a href="https://dev.hostify.co.za/support" target="_blank">Support Page</a>.';
    echo '</div>';
    echo '</div>';
}

function merge_orders_confirmation_page_callback() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    $new_order_id = isset($_GET['new_order_id']) ? intval($_GET['new_order_id']) : 0;
    if ($new_order_id) {
        $order = wc_get_order($new_order_id);
        if ($order) {
            echo '<div class="wrap"><h1>' . __('Order Merged Successfully', 'merge-orders-for-woocommerce') . '</h1>';
            echo '<p>' . __('The following order was created:', 'merge-orders-for-woocommerce') . '</p>';
            echo '<p><a href="' . esc_url(get_edit_post_link($new_order_id)) . '">' . esc_html($order->get_order_number()) . '</a></p>';
            echo '</div>';
        } else {
            echo '<div class="wrap"><h1>' . __('Order Not Found', 'merge-orders-for-woocommerce') . '</h1>';
            echo '<p>' . __('The order could not be found.', 'merge-orders-for-woocommerce') . '</p>';
            echo '</div>';
        }
    }
}

/**
 * Get orders to merge.
 */
function get_orders_to_merge() {
    $args = array(
        'status' => 'on-hold',
        'return' => 'ids',
        'limit'  => -1,  // Fetch all orders with 'on-hold' status
    );
    return wc_get_orders($args);
}

add_action('admin_post_merge_selected_orders', 'handle_merge_orders_submission');

function handle_merge_orders_submission() {
    if (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
        $order_ids = array_map('intval', $_POST['order_ids']); // Sanitize input
        $new_order_id = merge_orders($order_ids);

        if (!is_wp_error($new_order_id)) {
            wp_redirect(admin_url('admin.php?page=merge-orders-confirmation&new_order_id=' . $new_order_id));
        } else {
            wp_redirect(admin_url('admin.php?page=merge-orders&merge_orders_failed=1'));
        }
        exit;
    }
    wp_redirect(admin_url('admin.php?page=merge-orders'));
    exit;
}

/**
 * Update order status to on-hold when an auction is won.
 */
function handle_auction_won($auction_id, $old_status, $new_status) {
    if ($new_status === 'won') {
        $auction_product = wc_get_product($auction_id);
        if ($auction_product && $auction_product->is_type('auction')) {
            $order_id = get_post_meta($auction_product->get_id(), '_order_id', true);
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_status('on-hold', __('Auction won, order placed on hold.', 'merge-orders-for-woocommerce'));
            }
        }
    }
}
add_action('yith_wca_before_auction_status_changed', 'handle_auction_won', 10, 3);

?>
