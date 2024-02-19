<?php
// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the wp-load.php to get access to the WordPress database functions
include_once(ABSPATH . 'wp-load.php');

// Check if WooCommerce is active
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    // Get all orders with 'wc-merged' status
    $args = array(
        'status' => 'merged', // Use the custom status slug without 'wc-' prefix
        'type' => 'shop_order',
        'return' => 'ids',
        'limit' => -1 // Retrieve all orders
    );

    $orders = wc_get_orders($args);

    // Loop through the orders and change their status to 'wc-draft'
    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status('draft', 'Order status changed to draft on plugin uninstall', true);
        }
    }
}
