<?php
/**
 * Plugin Name: Merge Orders for WooCommerce
 * Plugin URI:  https://dev.hostify.co.za
 * Description: Merges multiple pending payment orders from the same customer into one, fully compatible with WooCommerce HPOS. Supports integration with YITH WooCommerce Auctions.
 * Version:     1.5
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
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        // WooCommerce is active, you can run your code that depends on WooCommerce here.

        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.0', '>=' ) ) {
            // Code for WooCommerce 8.0 or newer.
            // Initialize your plugin's main functionality or hooks here.
        }
    }
}
add_action( 'plugins_loaded', 'check_woocommerce_version' );


function register_merged_order_status() {
    register_post_status('wc-merged', array(
        'label'                     => 'Merged',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Merged <span class="count">(%s)</span>', 'Merged <span class="count">(%s)</span>')
    ));
}
add_action('init', 'register_merged_order_status');

function add_merged_to_order_statuses($order_statuses) {
    $new_order_statuses = array();

    // add new order status after processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) { // You might want to change this condition
            $new_order_statuses['wc-merged'] = 'Merged';
        }
    }
    return $new_order_statuses;
}
add_filter('wc_order_statuses', 'add_merged_to_order_statuses');


function merge_orders_menu() {
    add_submenu_page(
        'woocommerce', // Parent slug
        'Merge Orders', // Page title
        'Merge Orders', // Menu title
        'manage_woocommerce', // Capability
        'merge-orders', // Menu slug
        'merge_orders_admin_page' // Function to display the admin page
    );
}
add_action('admin_menu', 'merge_orders_menu');


function merge_orders_admin_page() {
    // Ensure the user has the right capability
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    // Fetch orders that can be merged (simplified example)
    $orders = get_orders_to_merge();

    // Start building the admin page
    echo '<div class="wrap"><h1>Merge Orders</h1>';
    echo '<div class="merge-orders-instructions">';
    echo '<h2>Instructions:</h2>';
    echo '<p>Select the orders you wish to merge and click "Merge Selected Orders".</p>';
    echo '</div>';

    if (!empty($orders)) {
        // Example layout for listing orders
        // Update the form to include action URL and nonce for security
        echo '<form id="merge-orders-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        // Hidden input fields for action and nonce
        echo '<input type="hidden" name="action" value="merge_selected_orders">';
        wp_nonce_field('merge_orders_nonce_action', 'merge_orders_nonce_name');

        echo '<table class="wp-list-table widefat fixed striped">';
        // Added 'Total Price' in the header
        echo '<thead><tr><th>Select</th><th>Order ID</th><th>Customer Name</th><th>Date</th><th>Status</th><th>Total Price</th></tr></thead>';
        echo '<tbody>';

        foreach ($orders as $order_id) {
    $order = wc_get_order($order_id);
    // Fetch the WP_User object
    $user = $order->get_user();
    // Use the user's login (username) instead of the customer's billing name
    $user_name = is_a($user, 'WP_User') ? $user->user_login : 'Guest'; // Fallback to 'Guest' if no user is associated with the order

    $total_price = $order->get_total(); // Total Price of the order

    echo '<tr>';
    echo '<td><input type="checkbox" name="order_ids[]" value="' . esc_attr($order_id) . '"></td>';
    echo '<td>' . esc_html($order->get_order_number()) . '</td>';
    // Display User Name instead of Customer Name
    echo '<td>' . esc_html($user_name) . '</td>';
    echo '<td>' . esc_html($order->get_date_created()->date('Y-m-d H:i:s')) . '</td>';
    echo '<td>' . esc_html(wc_get_order_status_name($order->get_status())) . '</td>';
    // Display Total Price
    echo '<td>' . wc_price($total_price) . '</td>'; // Using wc_price to format the price
    echo '</tr>';
}

        echo '</tbody></table>';
        echo '<br>';
        echo '<button type="submit" class="button action">Merge Selected Orders</button>';
        echo '</form>';
    } else {
        echo '<p>No eligible orders found for merging.</p>';
    }

    // Sticky footer
    echo '<div class="merge-orders-footer">';
    echo '<br>';
    echo 'Powered by <a href="https://hostify.co.za" target="_blank">Hostify</a>. Need help? Visit our <a href="https://dev.hostify.co.za/support" target="_blank">Support Page</a>.';
    echo '</div>';

    echo '</div>'; // Close wrap
}

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
        merge_orders($order_ids);
    }
    wp_redirect(admin_url('admin.php?page=merge-orders'));
    exit;
}



function merge_orders($order_ids) {
    // Create a new order
    $new_order = wc_create_order();

    // Variable to store customer email
    $customer_email = '';

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            continue;
        }

        // Store customer email from the first order
        if (empty($customer_email)) {
            $customer_email = $order->get_billing_email();
        }

        // Copy items from existing orders to the new order
        foreach ($order->get_items() as $item) {
            // Ensure compatibility with HPOS by using CRUD methods
            $product = $item->get_product();
            if ($product) {
                $new_item = new WC_Order_Item_Product();
                $new_item->set_props(array(
                    'product'  => $product,
                    'quantity' => $item->get_quantity(),
                    'subtotal' => $item->get_subtotal(),
                    'total'    => $item->get_total(),
                    // Include any other properties you need to copy
                ));
                $new_order->add_item($new_item);
            }
        }

        // Mark the original order as merged
        $order->update_status('merged', 'Order merged into order #' . $new_order->get_id(), true);
    }

    // Set the new order status to 'pending'
    $new_order->set_status('pending', 'Newly merged order, pending payment.');

    // Set the billing email for the new order
    if (!empty($customer_email)) {
        $new_order->set_billing_email($customer_email);
    }

    // Save the new order to ensure all data is stored correctly
    $new_order->calculate_totals();
    $new_order->save();

    // Trigger the email for the new order if we have an email to send to
    if (!empty($customer_email)) {
        $mailer = WC()->mailer();
        $mails = $mailer->get_emails();
        
        // Check if the customer invoice mailer exists before triggering
        if (!empty($mails) && isset($mails['WC_Email_Customer_Invoice'])) {
            $mails['WC_Email_Customer_Invoice']->trigger($new_order->get_id());
        }

        // Send a confirmation email to the web developer
        $to = 'webdev@hostify.co.za';
        $subject = 'Invoice Sent for Order #' . $new_order->get_order_number();
        $body = 'An invoice for the new merged order #' . $new_order->get_order_number() . ' has been sent to the customer.';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        if (!wp_mail($to, $subject, $body, $headers)) {
            // Handle the email sending failure
            error_log('Failed to send the developer confirmation email for Order #' . $new_order->get_order_number());
        }
    }
}
