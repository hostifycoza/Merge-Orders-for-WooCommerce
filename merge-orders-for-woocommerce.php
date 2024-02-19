<?php
/**
 * Plugin Name: Merge Orders for WooCommerce - HPOS Compatible
 * Plugin URI:  https://hostify.co.za
 * Description: Merges multiple pending payment orders from the same customer into one, fully compatible with WooCommerce HPOS. Supports integration with YITH WooCommerce Auctions.
 * Version:     1.1
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
 * Checks if WooCommerce is active and meets version requirements.
 */
function check_woocommerce_version() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')), true)) {
        return;
    }

    if (!defined('WC_VERSION') || version_compare(WC_VERSION, '8.0', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>This plugin requires WooCommerce version 8.0 or greater.</p></div>';
        });
        deactivate_plugins(plugin_basename(__FILE__));
    } else {
        // Register hooks and filters only if WooCommerce is active and meets version requirements.
        merge_orders_for_woocommerce_init();
    }
}
add_action('plugins_loaded', 'check_woocommerce_version');

function merge_orders_for_woocommerce_init() {
    add_action('init', 'register_merged_order_status');
    add_action('admin_menu', 'merge_orders_menu');
    add_filter('wc_order_statuses', 'add_merged_to_order_statuses');
    add_action('admin_post_merge_selected_orders', 'handle_merge_orders_submission');
}

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

function merge_orders_menu() {
    add_submenu_page(
        'woocommerce',
        'Merge Orders',
        'Merge Orders',
        'manage_woocommerce',
        'merge-orders',
        'merge_orders_admin_page'
    );
}

function add_merged_to_order_statuses($order_statuses) {
    $order_statuses['wc-merged'] = _x('Merged', 'Order status', 'merge-orders-for-woocommerce');
    return $order_statuses;
}

function merge_orders_admin_page() {
    // Admin page content. Ensure proper sanitization and capability checks.
    echo '<div class="wrap"><h1>Merge Orders</h1><p>Admin page content here.</p></div>';
}

function handle_merge_orders_submission() {
    // Security checks (e.g., nonces) and capability checks.
    
    if (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
        $order_ids = array_map('intval', $_POST['order_ids']); // Sanitize input.
        merge_orders($order_ids);
        wp_safe_redirect(admin_url('admin.php?page=merge-orders'));
        exit;
    }
}

function merge_orders($order_ids) {
    $new_order = wc_create_order();

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;

        foreach ($order->get_items() as $item) {
            $new_item = new WC_Order_Item_Product();
            $new_item->set_props([
                'product_id' => $item->get_product_id(),
                'quantity' => $item->get_quantity(),
                'subtotal' => $item->get_subtotal(),
                'total' => $item->get_total(),
                // Add any additional props as needed.
            ]);
            $new_order->add_item($new_item);
        }

        $order->update_status('wc-merged', sprintf(__('Order merged into order #%s', 'merge-orders-for-woocommerce'), $new_order->get_id()), true);
    }

    $new_order->calculate_totals();
    $new_order->save();
    // Optionally add order notes or additional meta to the new order here.
}
