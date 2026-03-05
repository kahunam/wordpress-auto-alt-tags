<?php
/**
 * Plugin uninstall handler.
 *
 * Cleans up all plugin data when the plugin is deleted from WordPress.
 * Preserves _wp_attachment_image_alt (standard WP core data belonging to the user).
 *
 * @package AutoAltTags
 */

// Security: only run during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
$options_to_delete = array(
	'auto_alt_provider',
	'auto_alt_gemini_api_key',
	'auto_alt_openai_api_key',
	'auto_alt_claude_api_key',
	'auto_alt_openrouter_api_key',
	'auto_alt_model_name',
	'auto_alt_batch_size',
	'auto_alt_image_size',
	'auto_alt_debug_mode',
	'auto_alt_custom_prompt',
	'auto_alt_auto_generate',
	'auto_alt_queue',
);

foreach ( $options_to_delete as $option ) {
	delete_option( $option );
}

// Delete all plugin transients.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options}
		WHERE option_name LIKE %s
		OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_auto_alt_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_auto_alt_' ) . '%'
	)
);

// Delete failed-attempt tracking postmeta from all attachments.
$wpdb->delete(
	$wpdb->postmeta,
	array( 'meta_key' => '_auto_alt_attempts' ),
	array( '%s' )
);

// Clear the WP Cron event.
$timestamp = wp_next_scheduled( 'auto_alt_tags_process_queue' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'auto_alt_tags_process_queue' );
}
