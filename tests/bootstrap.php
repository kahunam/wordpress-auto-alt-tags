<?php
/**
 * PHPUnit bootstrap file for Auto Alt Tags plugin tests
 *
 * @package AutoAltTags
 */

// Define test constants
define( 'AUTO_ALT_TAGS_TESTING', true );

// Load WordPress test environment if available
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Check if we're running WordPress tests
if ( file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// WordPress test environment is available
	require_once $_tests_dir . '/includes/functions.php';

	/**
	 * Manually load the plugin being tested.
	 */
	function _manually_load_plugin() {
		require dirname( dirname( __FILE__ ) ) . '/auto-alt-tags.php';
	}

	tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

	// Start up the WP testing environment
	require $_tests_dir . '/includes/bootstrap.php';
} else {
	// Standalone testing without WordPress
	// Define minimal WordPress-like constants and functions for unit testing

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/' );
	}

	// Load the mock functions
	require_once dirname( __FILE__ ) . '/mocks/wordpress-functions.php';
}
