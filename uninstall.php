<?php
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define the option names that your plugin has created.
$option_names = array(
	// 'option_name_1',
	// 'option_name_2',
);

// Loop through and delete the options from the options table.
foreach ( $option_names as $option_name ) {
	delete_option( $option_name );
}

// For Multisite: Delete options from each blog.
if ( is_multisite() ) {
	$blog_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}
		restore_current_blog();
	}
}

// Add here any additional cleanup code, such as removing custom tables or post meta data.

