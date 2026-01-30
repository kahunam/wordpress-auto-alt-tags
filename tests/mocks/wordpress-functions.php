<?php
/**
 * Mock WordPress functions for standalone testing
 *
 * @package AutoAltTags\Tests
 */

// Mock WordPress options storage
global $mock_options;
$mock_options = array(
	'auto_alt_provider'          => 'gemini',
	'auto_alt_model_name'        => 'gemini-2.5-flash',
	'auto_alt_batch_size'        => 10,
	'auto_alt_image_size'        => 'medium',
	'auto_alt_debug_mode'        => false,
	'auto_alt_custom_prompt'     => '',
	'auto_alt_gemini_api_key'    => '',
	'auto_alt_openai_api_key'    => '',
	'auto_alt_claude_api_key'    => '',
	'auto_alt_openrouter_api_key' => '',
);

// Mock transients storage
global $mock_transients;
$mock_transients = array();

// Mock HTTP responses
global $mock_http_responses;
$mock_http_responses = array();

/**
 * Set a mock HTTP response for testing
 *
 * @param string $url      URL pattern to match.
 * @param mixed  $response Response to return.
 */
function set_mock_http_response( $url, $response ) {
	global $mock_http_responses;
	$mock_http_responses[ $url ] = $response;
}

/**
 * Clear all mock HTTP responses
 */
function clear_mock_http_responses() {
	global $mock_http_responses;
	$mock_http_responses = array();
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Mock get_option function
	 */
	function get_option( $option, $default = false ) {
		global $mock_options;
		return isset( $mock_options[ $option ] ) ? $mock_options[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Mock update_option function
	 */
	function update_option( $option, $value ) {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Mock add_option function
	 */
	function add_option( $option, $value = '' ) {
		global $mock_options;
		if ( ! isset( $mock_options[ $option ] ) ) {
			$mock_options[ $option ] = $value;
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Mock get_transient function
	 */
	function get_transient( $transient ) {
		global $mock_transients;
		return isset( $mock_transients[ $transient ] ) ? $mock_transients[ $transient ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Mock set_transient function
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $mock_transients;
		$mock_transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Mock delete_transient function
	 */
	function delete_transient( $transient ) {
		global $mock_transients;
		unset( $mock_transients[ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * Mock wp_remote_post function
	 */
	function wp_remote_post( $url, $args = array() ) {
		global $mock_http_responses;

		// Check for exact URL match first
		if ( isset( $mock_http_responses[ $url ] ) ) {
			return $mock_http_responses[ $url ];
		}

		// Check for partial URL match
		foreach ( $mock_http_responses as $pattern => $response ) {
			if ( strpos( $url, $pattern ) !== false ) {
				return $response;
			}
		}

		// Default successful response for Gemini API
		if ( strpos( $url, 'generativelanguage.googleapis.com' ) !== false ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array(
					'candidates' => array(
						array(
							'content' => array(
								'parts' => array(
									array( 'text' => 'A test alt text description' ),
								),
							),
						),
					),
				) ),
			);
		}

		// Default error response
		return new WP_Error( 'http_request_failed', 'Mock: No response configured for this URL' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Mock wp_remote_retrieve_response_code function
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return isset( $response['response']['code'] ) ? $response['response']['code'] : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Mock wp_remote_retrieve_body function
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return isset( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Mock is_wp_error function
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Mock WP_Error class
	 */
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message( $code = '' ) {
			return $this->message;
		}

		public function get_error_data( $code = '' ) {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Mock wp_json_encode function
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mock sanitize_text_field function
	 */
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Mock sanitize_textarea_field function
	 */
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_check_filetype' ) ) {
	/**
	 * Mock wp_check_filetype function
	 */
	function wp_check_filetype( $filename, $mimes = null ) {
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		$type_map = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
		);
		return array(
			'ext'  => $ext,
			'type' => isset( $type_map[ $ext ] ) ? $type_map[ $ext ] : false,
		);
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Mock esc_html function
	 */
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Mock esc_attr function
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Mock __ function (translation)
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Mock esc_html__ function
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}
