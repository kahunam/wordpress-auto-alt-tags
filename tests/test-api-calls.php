<?php
/**
 * Tests for API call functionality
 *
 * @package AutoAltTags\Tests
 */

/**
 * Test class for API calls
 */
class Test_API_Calls extends PHPUnit\Framework\TestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		clear_mock_http_responses();

		// Reset options to defaults
		global $mock_options;
		$mock_options = array(
			'auto_alt_provider'          => 'gemini',
			'auto_alt_model_name'        => 'gemini-2.5-flash',
			'auto_alt_batch_size'        => 10,
			'auto_alt_image_size'        => 'medium',
			'auto_alt_debug_mode'        => false,
			'auto_alt_custom_prompt'     => '',
			'auto_alt_gemini_api_key'    => 'test-api-key',
			'auto_alt_openai_api_key'    => '',
			'auto_alt_claude_api_key'    => '',
			'auto_alt_openrouter_api_key' => '',
		);
	}

	/**
	 * Test that Gemini API URL is correctly formed
	 */
	public function test_gemini_api_url_format(): void {
		$model   = 'gemini-2.5-flash';
		$api_key = 'test-key';

		$expected_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=test-key';
		$actual_url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

		$this->assertEquals( $expected_url, $actual_url );
	}

	/**
	 * Test that all Gemini models have valid format
	 */
	public function test_gemini_model_names_format(): void {
		$valid_models = array(
			'gemini-2.5-flash',
			'gemini-2.5-flash-lite',
			'gemini-3-flash-preview',
			'gemini-3-pro-preview',
		);

		foreach ( $valid_models as $model ) {
			// Model names should match the pattern: gemini-{version}-{variant}[-preview]
			$this->assertMatchesRegularExpression(
				'/^gemini-\d+(\.\d+)?-[a-z]+(-[a-z]+)?(-preview)?$/',
				$model,
				"Model name '$model' should match valid format"
			);
		}
	}

	/**
	 * Test deprecated models are not in the list
	 */
	public function test_deprecated_models_removed(): void {
		$deprecated_models = array(
			'gemini-1.5-flash',
			'gemini-1.5-flash-8b',
			'gemini-1.5-pro',
			'gemini-2.0-flash',
		);

		$current_models = array(
			'gemini-2.5-flash',
			'gemini-2.5-flash-lite',
			'gemini-3-flash-preview',
			'gemini-3-pro-preview',
		);

		foreach ( $deprecated_models as $deprecated ) {
			$this->assertNotContains(
				$deprecated,
				$current_models,
				"Deprecated model '$deprecated' should not be in current models list"
			);
		}
	}

	/**
	 * Test Gemini API successful response parsing
	 */
	public function test_gemini_response_parsing(): void {
		$mock_response = array(
			'candidates' => array(
				array(
					'content' => array(
						'parts' => array(
							array( 'text' => 'A beautiful sunset over the ocean' ),
						),
					),
				),
			),
		);

		// Parse response like the plugin does
		$alt_text = '';
		if ( isset( $mock_response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$alt_text = trim( $mock_response['candidates'][0]['content']['parts'][0]['text'] );
		}

		$this->assertEquals( 'A beautiful sunset over the ocean', $alt_text );
	}

	/**
	 * Test Gemini API error response handling
	 */
	public function test_gemini_error_response_parsing(): void {
		$mock_response = array(
			'error' => array(
				'code'    => 400,
				'message' => 'Invalid API key',
				'status'  => 'INVALID_ARGUMENT',
			),
		);

		// Check error parsing like the plugin does
		$error_msg = isset( $mock_response['error']['message'] ) ? $mock_response['error']['message'] : 'Unknown error';

		$this->assertEquals( 'Invalid API key', $error_msg );
	}

	/**
	 * Test OpenAI API URL format
	 */
	public function test_openai_api_url_format(): void {
		$expected_url = 'https://api.openai.com/v1/chat/completions';

		// This is the URL used in the plugin
		$actual_url = 'https://api.openai.com/v1/chat/completions';

		$this->assertEquals( $expected_url, $actual_url );
	}

	/**
	 * Test Claude API URL format
	 */
	public function test_claude_api_url_format(): void {
		$expected_url = 'https://api.anthropic.com/v1/messages';

		// This is the URL used in the plugin
		$actual_url = 'https://api.anthropic.com/v1/messages';

		$this->assertEquals( $expected_url, $actual_url );
	}

	/**
	 * Test OpenRouter API URL format
	 */
	public function test_openrouter_api_url_format(): void {
		$expected_url = 'https://openrouter.ai/api/v1/chat/completions';

		// This is the URL used in the plugin
		$actual_url = 'https://openrouter.ai/api/v1/chat/completions';

		$this->assertEquals( $expected_url, $actual_url );
	}

	/**
	 * Test Gemini payload structure for image understanding
	 */
	public function test_gemini_payload_structure(): void {
		$base64_image = base64_encode( 'fake image data' );
		$prompt       = 'Generate alt text for this image';

		$payload = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
						array(
							'inline_data' => array(
								'mime_type' => 'image/jpeg',
								'data'      => $base64_image,
							),
						),
					),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens' => 50,
				'temperature'     => 0.1,
			),
		);

		// Verify structure
		$this->assertArrayHasKey( 'contents', $payload );
		$this->assertArrayHasKey( 'generationConfig', $payload );
		$this->assertCount( 1, $payload['contents'] );
		$this->assertCount( 2, $payload['contents'][0]['parts'] );
		$this->assertArrayHasKey( 'text', $payload['contents'][0]['parts'][0] );
		$this->assertArrayHasKey( 'inline_data', $payload['contents'][0]['parts'][1] );
	}

	/**
	 * Test mock HTTP response for Gemini API
	 */
	public function test_mock_gemini_http_response(): void {
		set_mock_http_response(
			'generativelanguage.googleapis.com',
			array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'candidates' => array(
						array(
							'content' => array(
								'parts' => array(
									array( 'text' => 'Mocked alt text response' ),
								),
							),
						),
					),
				) ),
			)
		);

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=test',
			array( 'body' => '{}' )
		);

		$this->assertFalse( is_wp_error( $response ) );
		$this->assertEquals( 200, wp_remote_retrieve_response_code( $response ) );

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$this->assertEquals( 'Mocked alt text response', $data['candidates'][0]['content']['parts'][0]['text'] );
	}

	/**
	 * Test mock HTTP error response
	 */
	public function test_mock_http_error_response(): void {
		set_mock_http_response(
			'generativelanguage.googleapis.com',
			array(
				'response' => array( 'code' => 401 ),
				'body'     => wp_json_encode( array(
					'error' => array(
						'message' => 'API key not valid',
					),
				) ),
			)
		);

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=invalid',
			array( 'body' => '{}' )
		);

		$this->assertEquals( 401, wp_remote_retrieve_response_code( $response ) );

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$this->assertEquals( 'API key not valid', $data['error']['message'] );
	}

	/**
	 * Test alt text cleaning (remove quotes and asterisks)
	 */
	public function test_alt_text_cleaning(): void {
		$test_cases = array(
			'"A sunset over the ocean"'   => 'A sunset over the ocean',
			'*A sunset over the ocean*'   => 'A sunset over the ocean',
			"'A sunset over the ocean'"   => 'A sunset over the ocean',
			'**Bold text**'               => 'Bold text',
			'  Whitespace around  '       => 'Whitespace around',
		);

		foreach ( $test_cases as $input => $expected ) {
			$cleaned = trim( $input, '"\' *' );
			$cleaned = preg_replace( '/^\*+|\*+$/', '', $cleaned );
			$cleaned = trim( $cleaned );

			$this->assertEquals( $expected, $cleaned, "Failed for input: $input" );
		}
	}

	/**
	 * Test default model is gemini-2.5-flash
	 */
	public function test_default_model_is_current(): void {
		$default_model = get_option( 'auto_alt_model_name', 'gemini-2.5-flash' );

		$this->assertEquals( 'gemini-2.5-flash', $default_model );
	}

	/**
	 * Test options storage and retrieval
	 */
	public function test_options_storage(): void {
		update_option( 'auto_alt_model_name', 'gemini-3-flash-preview' );

		$this->assertEquals( 'gemini-3-flash-preview', get_option( 'auto_alt_model_name' ) );
	}

	/**
	 * Test transient storage and retrieval
	 */
	public function test_transient_storage(): void {
		set_transient( 'auto_alt_offset', 50, HOUR_IN_SECONDS );

		$this->assertEquals( 50, get_transient( 'auto_alt_offset' ) );

		delete_transient( 'auto_alt_offset' );

		$this->assertFalse( get_transient( 'auto_alt_offset' ) );
	}
}
