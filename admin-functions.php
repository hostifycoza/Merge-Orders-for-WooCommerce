function merge_orders_add_details_page() {
    add_submenu_page(
        null, // Set to null so it doesn't appear as a submenu item
        'Merge Orders Details', // Page title
        'Merge Orders Details', // Menu title
        'manage_options', // Capability
        'merge-orders-details', // Menu slug
        'merge_orders_details_page_content' // Function to display the page content
    );
}

add_action('admin_menu', 'merge_orders_add_details_page');

function merge_orders_details_page_content() {
    ?>
    <div class="wrap">
        <h1>Merge Orders for WooCommerce - Details</h1>
        <p>Here you can add detailed information about your plugin, how to use it, FAQs, and more.</p>
        <!-- Add more content here -->
    </div>
    <?php
}
