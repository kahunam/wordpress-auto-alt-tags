<?php
/**
 * Integration tests for Auto Alt Tags plugin
 *
 * @package AutoAltTags\Tests
 */

/**
 * Test class for plugin integration
 */
class Test_Plugin_Integration extends PHPUnit\Framework\TestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset options to defaults
		global $mock_options;
		$mock_options = array(
			'auto_alt_provider'           => 'gemini',
			'auto_alt_model_name'         => 'gemini-2.5-flash',
			'auto_alt_batch_size'         => 10,
			'auto_alt_image_size'         => 'medium',
			'auto_alt_debug_mode'         => false,
			'auto_alt_custom_prompt'      => '',
			'auto_alt_gemini_api_key'     => 'test-api-key',
			'auto_alt_openai_api_key'     => '',
			'auto_alt_claude_api_key'     => '',
			'auto_alt_openrouter_api_key' => '',
		);

		// Reset transients
		global $mock_transients;
		$mock_transients = array();
	}

	/**
	 * Test provider configuration
	 */
	public function test_provider_configuration(): void {
		$providers = array(
			'gemini'     => 'auto_alt_gemini_api_key',
			'openai'     => 'auto_alt_openai_api_key',
			'claude'     => 'auto_alt_claude_api_key',
			'openrouter' => 'auto_alt_openrouter_api_key',
		);

		foreach ( $providers as $provider => $key_option ) {
			update_option( 'auto_alt_provider', $provider );
			$this->assertEquals( $provider, get_option( 'auto_alt_provider' ) );
		}
	}

	/**
	 * Test batch size validation
	 */
	public function test_batch_size_validation(): void {
		// Valid batch sizes
		$valid_sizes = array( 1, 10, 25, 50 );
		foreach ( $valid_sizes as $size ) {
			update_option( 'auto_alt_batch_size', $size );
			$this->assertEquals( $size, get_option( 'auto_alt_batch_size' ) );
		}

		// Test that batch size is retrieved correctly
		update_option( 'auto_alt_batch_size', 15 );
		$this->assertEquals( 15, get_option( 'auto_alt_batch_size' ) );
	}

	/**
	 * Test debug mode toggle
	 */
	public function test_debug_mode_toggle(): void {
		update_option( 'auto_alt_debug_mode', true );
		$this->assertTrue( get_option( 'auto_alt_debug_mode' ) );

		update_option( 'auto_alt_debug_mode', false );
		$this->assertFalse( get_option( 'auto_alt_debug_mode' ) );
	}

	/**
	 * Test custom prompt storage
	 */
	public function test_custom_prompt_storage(): void {
		$custom_prompt = 'Describe this image in detail for accessibility purposes';

		update_option( 'auto_alt_custom_prompt', $custom_prompt );
		$this->assertEquals( $custom_prompt, get_option( 'auto_alt_custom_prompt' ) );
	}

	/**
	 * Test default prompt fallback
	 */
	public function test_default_prompt_fallback(): void {
		$default_prompt = 'Generate a concise, descriptive alt text for this image.';

		// Custom prompt is empty, should use default
		$custom = get_option( 'auto_alt_custom_prompt', '' );
		$prompt = ! empty( $custom ) ? $custom : $default_prompt;

		$this->assertEquals( $default_prompt, $prompt );
	}

	/**
	 * Test progress tracking with transients
	 */
	public function test_progress_tracking(): void {
		// Simulate batch processing
		$total_images = 100;
		$batch_size   = 10;

		for ( $offset = 0; $offset < $total_images; $offset += $batch_size ) {
			set_transient( 'auto_alt_offset', $offset, HOUR_IN_SECONDS );

			$current_offset = get_transient( 'auto_alt_offset' );
			$this->assertEquals( $offset, $current_offset );

			$progress = ( $current_offset / $total_images ) * 100;
			$this->assertGreaterThanOrEqual( 0, $progress );
			$this->assertLessThanOrEqual( 100, $progress );
		}

		// Clean up
		delete_transient( 'auto_alt_offset' );
		$this->assertFalse( get_transient( 'auto_alt_offset' ) );
	}

	/**
	 * Test debug log storage
	 */
	public function test_debug_log_storage(): void {
		$logs = array(
			array(
				'time'    => '2024-01-15 10:30:00',
				'message' => 'Processing image 1',
				'type'    => 'info',
			),
			array(
				'time'    => '2024-01-15 10:30:01',
				'message' => 'API request sent',
				'type'    => 'debug',
			),
		);

		set_transient( 'auto_alt_debug_logs', $logs, HOUR_IN_SECONDS );

		$retrieved_logs = get_transient( 'auto_alt_debug_logs' );
		$this->assertEquals( $logs, $retrieved_logs );
		$this->assertCount( 2, $retrieved_logs );
	}

	/**
	 * Test image size options
	 */
	public function test_image_size_options(): void {
		$valid_sizes = array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' );

		foreach ( $valid_sizes as $size ) {
			update_option( 'auto_alt_image_size', $size );
			$this->assertEquals( $size, get_option( 'auto_alt_image_size' ) );
		}
	}

	/**
	 * Test API key masking for display
	 */
	public function test_api_key_masking(): void {
		$api_key = 'AIzaSyBxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

		// Typical masking pattern: show first 8 and last 4 characters
		$masked = substr( $api_key, 0, 8 ) . '...' . substr( $api_key, -4 );

		$this->assertEquals( 'AIzaSyBx...xxxx', $masked );
		$this->assertNotEquals( $api_key, $masked );
	}
}
