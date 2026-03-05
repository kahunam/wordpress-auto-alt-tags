<?php
/**
 * Tests for Media Library filter methods.
 *
 * @package AutoAltTags\Tests
 */

require_once __DIR__ . '/bootstrap.php';

class MediaFilterTest extends PHPUnit\Framework\TestCase {

	private AutoAltTagGenerator $plugin;

	public function setUp(): void {
		$this->plugin = new AutoAltTagGenerator();
	}

	public function test_media_filter_dropdown_outputs_nothing_for_non_attachment(): void {
		ob_start();
		$this->plugin->media_filter_dropdown( 'post' );
		$output = ob_get_clean();
		$this->assertSame( '', $output );
	}

	public function test_media_filter_dropdown_outputs_select_for_attachment(): void {
		ob_start();
		$this->plugin->media_filter_dropdown( 'attachment' );
		$output = ob_get_clean();
		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'auto_alt_filter', $output );
	}

	public function test_media_filter_query_sets_meta_query_for_missing(): void {
		global $pagenow;
		$pagenow = 'upload.php';
		$_GET['auto_alt_filter'] = 'missing';

		$query = new WP_Query();
		$this->plugin->media_filter_query( $query );

		$meta_query = $query->get( 'meta_query' );
		$this->assertIsArray( $meta_query );
		$this->assertArrayHasKey( 'relation', $meta_query );
		$this->assertSame( 'OR', $meta_query['relation'] );

		unset( $_GET['auto_alt_filter'] );
	}

	public function test_media_filter_query_sets_meta_query_for_has(): void {
		global $pagenow;
		$pagenow = 'upload.php';
		$_GET['auto_alt_filter'] = 'has';

		$query = new WP_Query();
		$this->plugin->media_filter_query( $query );

		$meta_query = $query->get( 'meta_query' );
		$this->assertIsArray( $meta_query );
		$this->assertSame( '!=', $meta_query[0]['compare'] );

		unset( $_GET['auto_alt_filter'] );
	}

	public function test_media_filter_query_does_nothing_for_invalid_filter(): void {
		global $pagenow;
		$pagenow = 'upload.php';
		$_GET['auto_alt_filter'] = 'invalid';

		$query = new WP_Query();
		$this->plugin->media_filter_query( $query );

		$meta_query = $query->get( 'meta_query', null );
		$this->assertNull( $meta_query );

		unset( $_GET['auto_alt_filter'] );
	}
}
