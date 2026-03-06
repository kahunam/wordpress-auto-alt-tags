<?php
/**
 * Tests for Select Images feature
 *
 * @package AutoAltTags\Tests
 */

class Test_Select_Images extends PHPUnit\Framework\TestCase {

	public function setUp(): void {
		parent::setUp();
		global $mock_options, $mock_transients, $mock_postmeta;
		$mock_options = array(
			'auto_alt_provider'       => 'gemini',
			'auto_alt_gemini_api_key' => 'test-key',
			'auto_alt_batch_size'     => 10,
			'auto_alt_image_size'     => 'medium',
			'auto_alt_model_name'     => 'gemini-2.5-flash',
			'auto_alt_debug_mode'     => false,
			'auto_alt_custom_prompt'  => '',
		);
		$mock_transients = array();
		$mock_postmeta   = array();
	}

	/**
	 * Pagination helper: calculate page offsets correctly
	 */
	public function test_pagination_math(): void {
		$per_page = 24;

		// Page 1: offset 0, fetch 24
		$page   = 1;
		$offset = ( $page - 1 ) * $per_page;
		$this->assertSame( 0, $offset );

		// Page 3: offset 48, fetch 24
		$page   = 3;
		$offset = ( $page - 1 ) * $per_page;
		$this->assertSame( 48, $offset );
	}

	/**
	 * Input sanitisation: image_ids must be positive integers only
	 */
	public function test_image_ids_sanitisation(): void {
		$raw_ids = array( '42', '-5', '0', 'abc', '100', '' );

		// Simulate the sanitisation the AJAX handler will apply
		$sanitised = array_values(
			array_filter(
				array_map( 'intval', $raw_ids ),
				fn( $id ) => $id > 0
			)
		);

		$this->assertSame( array( 42, 100 ), $sanitised );
	}

	/**
	 * When image_ids provided, processing uses exactly those IDs
	 */
	public function test_selected_ids_used_when_provided(): void {
		$selected = array( 10, 20, 30 );

		// Simulate handler logic: if image_ids posted, use them directly
		$posted_ids = array( '10', '20', '30' );
		$ids = array_values(
			array_filter(
				array_map( 'intval', $posted_ids ),
				fn( $id ) => $id > 0
			)
		);

		$this->assertSame( $selected, $ids );
		$this->assertCount( 3, $ids );
	}

	/**
	 * When image_ids is empty or absent, the handler should fall back to processing all
	 */
	public function test_empty_image_ids_falls_back(): void {
		// Simulate no image_ids posted
		$posted_ids = array();
		$should_use_posted = ! empty( $posted_ids );
		$this->assertFalse( $should_use_posted );

		// Simulate image_ids posted but all invalid
		$raw_invalid = array( '0', '-1', 'abc' );
		$sanitised = array_values(
			array_filter(
				array_map( 'intval', $raw_invalid ),
				fn( $id ) => $id > 0
			)
		);
		$this->assertEmpty( $sanitised );
		$should_use_posted = ! empty( $sanitised );
		$this->assertFalse( $should_use_posted );
	}

	/**
	 * Total pages calculation
	 */
	public function test_total_pages(): void {
		$this->assertSame( 1, max( 1, (int) ceil( 0 / 24 ) ) );  // no images — ceil(0) = 0, clamped to 1
		$this->assertSame( 1, (int) ceil( 10 / 24 ) ); // less than one page
		$this->assertSame( 1, (int) ceil( 24 / 24 ) ); // exactly one page
		$this->assertSame( 2, (int) ceil( 25 / 24 ) ); // one over
		$this->assertSame( 2, (int) ceil( 48 / 24 ) ); // exactly two pages
		$this->assertSame( 3, (int) ceil( 49 / 24 ) ); // one over two pages
	}
}
