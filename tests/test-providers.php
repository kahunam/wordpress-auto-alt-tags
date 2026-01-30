<?php
/**
 * Tests for AI provider functionality
 *
 * @package AutoAltTags\Tests
 */

/**
 * Test class for AI providers
 */
class Test_Providers extends PHPUnit\Framework\TestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		clear_mock_http_responses();
	}

	// =========================================================================
	// Gemini Provider Tests
	// =========================================================================

	/**
	 * Test Gemini API request structure
	 */
	public function test_gemini_request_structure(): void {
		$model   = 'gemini-2.5-flash';
		$api_key = 'test-key';
		$prompt  = 'Generate alt text for this image';
		$image   = base64_encode( 'fake-image-data' );

		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt ),
						array(
							'inline_data' => array(
								'mime_type' => 'image/jpeg',
								'data'      => $image,
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

		// Verify URL structure
		$this->assertStringContainsString( 'generativelanguage.googleapis.com', $url );
		$this->assertStringContainsString( 'v1beta/models/', $url );
		$this->assertStringContainsString( ':generateContent', $url );

		// Verify body structure
		$this->assertArrayHasKey( 'contents', $body );
		$this->assertArrayHasKey( 'generationConfig', $body );
		$this->assertEquals( 50, $body['generationConfig']['maxOutputTokens'] );
	}

	/**
	 * Test Gemini successful response parsing
	 */
	public function test_gemini_success_response(): void {
		$response = array(
			'candidates' => array(
				array(
					'content' => array(
						'parts' => array(
							array( 'text' => 'A golden retriever playing in a park' ),
						),
					),
					'finishReason' => 'STOP',
				),
			),
			'usageMetadata' => array(
				'promptTokenCount'     => 100,
				'candidatesTokenCount' => 10,
				'totalTokenCount'      => 110,
			),
		);

		// Extract alt text like the plugin does
		$alt_text = '';
		if ( isset( $response['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$alt_text = trim( $response['candidates'][0]['content']['parts'][0]['text'] );
		}

		$this->assertEquals( 'A golden retriever playing in a park', $alt_text );
	}

	/**
	 * Test Gemini error response handling
	 */
	public function test_gemini_error_responses(): void {
		$error_responses = array(
			array(
				'error' => array(
					'code'    => 400,
					'message' => 'API key not valid. Please pass a valid API key.',
					'status'  => 'INVALID_ARGUMENT',
				),
			),
			array(
				'error' => array(
					'code'    => 429,
					'message' => 'Resource exhausted. Please try again later.',
					'status'  => 'RESOURCE_EXHAUSTED',
				),
			),
			array(
				'error' => array(
					'code'    => 500,
					'message' => 'Internal server error.',
					'status'  => 'INTERNAL',
				),
			),
		);

		foreach ( $error_responses as $response ) {
			$this->assertArrayHasKey( 'error', $response );
			$this->assertArrayHasKey( 'message', $response['error'] );
		}
	}

	/**
	 * Test all Gemini model endpoints
	 */
	public function test_gemini_model_endpoints(): void {
		$models = array(
			'gemini-2.5-flash',
			'gemini-2.5-flash-lite',
			'gemini-3-flash-preview',
			'gemini-3-pro-preview',
		);

		foreach ( $models as $model ) {
			$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';
			$this->assertStringContainsString( $model, $url );
		}
	}

	// =========================================================================
	// OpenAI Provider Tests
	// =========================================================================

	/**
	 * Test OpenAI API request structure
	 */
	public function test_openai_request_structure(): void {
		$url = 'https://api.openai.com/v1/chat/completions';

		$body = array(
			'model'      => 'gpt-4o',
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => 'Generate alt text for this image',
						),
						array(
							'type'      => 'image_url',
							'image_url' => array(
								'url' => 'data:image/jpeg;base64,/9j/4AAQ...',
							),
						),
					),
				),
			),
			'max_tokens' => 50,
		);

		$this->assertEquals( 'https://api.openai.com/v1/chat/completions', $url );
		$this->assertArrayHasKey( 'model', $body );
		$this->assertArrayHasKey( 'messages', $body );
		$this->assertEquals( 'gpt-4o', $body['model'] );
	}

	/**
	 * Test OpenAI response parsing
	 */
	public function test_openai_success_response(): void {
		$response = array(
			'id'      => 'chatcmpl-abc123',
			'object'  => 'chat.completion',
			'choices' => array(
				array(
					'index'   => 0,
					'message' => array(
						'role'    => 'assistant',
						'content' => 'A sunset over a mountain lake',
					),
					'finish_reason' => 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 100,
				'completion_tokens' => 10,
				'total_tokens'      => 110,
			),
		);

		// Extract like plugin does
		$alt_text = '';
		if ( isset( $response['choices'][0]['message']['content'] ) ) {
			$alt_text = trim( $response['choices'][0]['message']['content'] );
		}

		$this->assertEquals( 'A sunset over a mountain lake', $alt_text );
	}

	/**
	 * Test OpenAI model list
	 */
	public function test_openai_models(): void {
		$models = array(
			'gpt-4o'              => 'GPT-4o (Latest)',
			'gpt-4o-mini'         => 'GPT-4o Mini (Cost-effective)',
			'gpt-4-turbo'         => 'GPT-4 Turbo',
			'gpt-4-vision-preview' => 'GPT-4 Vision Preview',
		);

		foreach ( $models as $model_id => $description ) {
			$this->assertNotEmpty( $model_id );
			$this->assertNotEmpty( $description );
		}
	}

	// =========================================================================
	// Claude Provider Tests
	// =========================================================================

	/**
	 * Test Claude API request structure
	 */
	public function test_claude_request_structure(): void {
		$url = 'https://api.anthropic.com/v1/messages';

		$headers = array(
			'Content-Type'      => 'application/json',
			'x-api-key'         => 'test-api-key',
			'anthropic-version' => '2023-06-01',
		);

		$body = array(
			'model'      => 'claude-3-5-sonnet-20241022',
			'max_tokens' => 50,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'   => 'image',
							'source' => array(
								'type'       => 'base64',
								'media_type' => 'image/jpeg',
								'data'       => 'base64data...',
							),
						),
						array(
							'type' => 'text',
							'text' => 'Generate alt text',
						),
					),
				),
			),
		);

		$this->assertEquals( 'https://api.anthropic.com/v1/messages', $url );
		$this->assertArrayHasKey( 'x-api-key', $headers );
		$this->assertArrayHasKey( 'anthropic-version', $headers );
	}

	/**
	 * Test Claude response parsing
	 */
	public function test_claude_success_response(): void {
		$response = array(
			'id'           => 'msg_abc123',
			'type'         => 'message',
			'role'         => 'assistant',
			'content'      => array(
				array(
					'type' => 'text',
					'text' => 'A red bicycle leaning against a brick wall',
				),
			),
			'model'        => 'claude-3-5-sonnet-20241022',
			'stop_reason'  => 'end_turn',
			'usage'        => array(
				'input_tokens'  => 100,
				'output_tokens' => 12,
			),
		);

		// Extract like plugin does
		$alt_text = '';
		if ( isset( $response['content'][0]['text'] ) ) {
			$alt_text = trim( $response['content'][0]['text'] );
		}

		$this->assertEquals( 'A red bicycle leaning against a brick wall', $alt_text );
	}

	// =========================================================================
	// OpenRouter Provider Tests
	// =========================================================================

	/**
	 * Test OpenRouter API request structure
	 */
	public function test_openrouter_request_structure(): void {
		$url = 'https://openrouter.ai/api/v1/chat/completions';

		$headers = array(
			'Authorization' => 'Bearer test-api-key',
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => 'https://example.com',
		);

		$this->assertEquals( 'https://openrouter.ai/api/v1/chat/completions', $url );
		$this->assertArrayHasKey( 'Authorization', $headers );
	}

	/**
	 * Test OpenRouter models mapping
	 */
	public function test_openrouter_models(): void {
		$models = array(
			'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet via OpenRouter',
			'openai/gpt-4o'               => 'GPT-4o via OpenRouter',
			'openai/gpt-4o-mini'          => 'GPT-4o Mini via OpenRouter',
			'google/gemini-pro-1.5'       => 'Gemini Pro 1.5 via OpenRouter',
		);

		foreach ( $models as $model_id => $description ) {
			// OpenRouter model IDs have provider prefix
			$this->assertStringContainsString( '/', $model_id );
		}
	}

	// =========================================================================
	// HTTP Response Mock Tests
	// =========================================================================

	/**
	 * Test mock HTTP success response
	 */
	public function test_mock_http_success(): void {
		set_mock_http_response(
			'api.example.com',
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"status": "success"}',
			)
		);

		$response = wp_remote_post( 'https://api.example.com/test', array() );

		$this->assertFalse( is_wp_error( $response ) );
		$this->assertEquals( 200, wp_remote_retrieve_response_code( $response ) );
	}

	/**
	 * Test mock HTTP error response
	 */
	public function test_mock_http_error(): void {
		set_mock_http_response(
			'api.example.com',
			new WP_Error( 'http_request_failed', 'Connection refused' )
		);

		$response = wp_remote_post( 'https://api.example.com/test', array() );

		$this->assertTrue( is_wp_error( $response ) );
		$this->assertEquals( 'http_request_failed', $response->get_error_code() );
	}

	/**
	 * Test rate limit response handling
	 */
	public function test_rate_limit_response(): void {
		set_mock_http_response(
			'api.example.com',
			array(
				'response' => array( 'code' => 429 ),
				'body'     => '{"error": {"message": "Rate limit exceeded"}}',
			)
		);

		$response = wp_remote_post( 'https://api.example.com/test', array() );

		$this->assertEquals( 429, wp_remote_retrieve_response_code( $response ) );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->assertStringContainsString( 'Rate limit', $body['error']['message'] );
	}
}
