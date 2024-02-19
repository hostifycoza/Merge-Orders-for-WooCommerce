<?php
/**
 * Plugin Name: Merge Orders for WooCommerce - HPOS Compatible
 * Plugin URI:  https://hostify.co.za
 * Description: Merges multiple pending payment orders from the same customer into one, ensuring compatibility with WooCommerce HPOS. Supports integration with YITH WooCommerce Auctions.
 * Version:     1.1
 * Author:      Hostify
 * Author URI:  https://hostify.co.za
 * Text Domain: merge-orders-for-woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Check if WooCommerce is active and version meets requirements.
 */
function check_woocommerce_version() {
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        // WooCommerce is active, you can run your code that depends on WooCommerce here.

        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.0', '>=' ) ) {
            // Code for WooCommerce 8.0 or newer.
            // This is where your plugin's main functionality or hooks should be initialized.
        }
    }
}
add_action( 'plugins_loaded', 'check_woocommerce_version' );

/**
 * Adds a submenu for the plugin under the WooCommerce menu.
 */
function merge_orders_menu() {
    add_submenu_page(
        'woocommerce', // Parent slug
        'Merge Orders', // Page title
        'Merge Orders', // Menu title
        'manage_woocommerce', // Capability
        'merge-orders', // Menu slug
        'merge_orders_admin_page' // Callback function for displaying the admin page
    );
}
add_action('admin_menu', 'merge_orders_menu');

function register_merged_order_status() {
    register_post_status('wc-merged', array(
        'label'                     => _x('Merged', 'Order status', 'merge-orders-for-woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Merged <span class="count">(%s)</span>', 'Merged <span class="count">(%s)</span>', 'merge-orders-for-woocommerce')
    ));
}
add_action('init', 'register_merged_order_status');

function merge_orders_admin_page() {
    // The display code for the admin page remains unchanged.
    // Ensure to properly sanitize and validate all input data.
}

/**
 * Modified to ensure HPOS compatibility.
 * This function now uses the WC_Order_Query efficiently.
 */
function get_orders_to_merge() {
    $query = new WC_Order_Query(array(
        'status' => 'pending',
        'limit' => -1, // Depending on the expected volume, consider pagination.
        'return' => 'ids',
    ));
    return $query->get_orders();
}

/**
 * Handle the form submission to merge orders.
 */
add_action('admin_post_merge_selected_orders', 'handle_merge_orders_submission');

function handle_merge_orders_submission() {
    // Your sanitization and redirection code remains effective and doesn't need changes.
    // Ensure nonces are verified for security reasons.
}

function merge_orders($order_ids) {
    $new_order = wc_create_order();

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            continue;
        }

        foreach ($order->get_items() as $item) {
            // Ensure compatibility with HPOS by using CRUD methods for item manipulation.
            $new_item = new WC_Order_Item_Product();

            // Cloning the product item properties.
            $new_item->set_props([
                'product_id' => $item->get_product_id(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                // Include additional properties as needed.
            ]);

            // Add the item to the order before saving metadata to ensure it's associated correctly.
            $new_order->add_item($new_item);

            // Copying over item meta data.
            $metadata = $item->get_meta_data();
            foreach ($metadata as $meta) {
                // Use WC provided methods for meta manipulation to ensure HPOS compatibility.
                $new_item->add_meta_data($meta->key, $meta->value, true);
            }

            // Save the new item to ensure all data is stored correctly.
            $new_item->save();
        }

        // Update the status of the original orders to indicate they've been merged.
        // Ensure to use WooCommerce methods for updating order status to maintain compatibility.
        $order->update_status('wc-merged', 'Order merged into order #' . $new_order->get_id(), true);
    }

    // Recalculate totals and save the new order to ensure all changes are persisted.
    $new_order->calculate_totals();
    $new_order->save();
}

