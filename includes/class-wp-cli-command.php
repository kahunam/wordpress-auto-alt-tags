<?php
/**
 * WP-CLI command for auto-generating alt tags
 * Part of the Auto Alt Tag Generator plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	
	/**
	 * Auto Alt CLI Command class
	 */
	class Auto_Alt_CLI_Command {
		
		/**
		 * Gemini API key
		 *
		 * @var string
		 */
		private string $gemini_api_key;
		
		/**
		 * Maximum image size for API calls
		 *
		 * @var int
		 */
		private int $max_image_size;
		
		/**
		 * Current Gemini model name
		 *
		 * @var string
		 */
		private string $model_name = 'gemini-2.5-flash-preview-05-20';
		
		/**
		 * Constructor
		 */
		public function __construct() {
			$this->gemini_api_key = defined( 'GEMINI_API_KEY' ) ? GEMINI_API_KEY : get_option( 'auto_alt_gemini_api_key', '' );
			$this->max_image_size = (int) get_option( 'auto_alt_max_image_size', 512 );
		}
		
		/**
		 * Generate alt tags for images without them using Gemini API
		 *
		 * ## OPTIONS
		 *
		 * [--batch-size=<number>]
		 * : Number of images to process in each batch. Default: 10
		 *
		 * [--dry-run]
		 * : Show what would be processed without making changes
		 *
		 * [--limit=<number>]
		 * : Maximum number of images to process. Default: no limit
		 *
		 * [--force]
		 * : Force regeneration of alt tags even for images that already have them
		 *
		 * [--verbose]
		 * : Show detailed output for each processed image
		 *
		 * ## EXAMPLES
		 *
		 *     # Generate alt tags for all images missing them
		 *     wp auto-alt generate
		 *
		 *     # Process with smaller batches and limit total
		 *     wp auto-alt generate --batch-size=5 --limit=100
		 *
		 *     # See what would be processed without making changes
		 *     wp auto-alt generate --dry-run
		 *
		 *     # Regenerate alt tags for all images (including existing ones)
		 *     wp auto-alt generate --force
		 *
		 *     # Verbose output showing each processed image
		 *     wp auto-alt generate --verbose --limit=10
		 *
		 * @when after_wp_load
		 */
		public function generate( array $args, array $assoc_args ): void {
			
			if ( empty( $this->gemini_api_key ) ) {
				WP_CLI::error( 'Gemini API key not configured. Set GEMINI_API_KEY in wp-config.php or use: wp option update auto_alt_gemini_api_key "your-key"' );
			}
			
			$batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 10;
			$dry_run = isset( $assoc_args['dry-run'] );
			$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : null;
			$force = isset( $assoc_args['force'] );
			$verbose = isset( $assoc_args['verbose'] );
			
			// Validate batch size
			if ( $batch_size < 1 || $batch_size > 50 ) {
				WP_CLI::error( 'Batch size must be between 1 and 50.' );
			}
			
			// Get images based on force flag
			$images = $force ? $this->get_all_images( $limit ) : $this->get_images_without_alt( $limit );
			$total = count( $images );
			
			if ( 0 === $total ) {
				$message = $force ? 'No images found in media library.' : 'No images found that need alt tags.';
				WP_CLI::success( $message );
				return;
			}
			
			$mode = $force ? 'regenerating' : 'generating missing';
			WP_CLI::log( "Found {$total} images for {$mode} alt tags." );
			
			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN - No changes will be made:' );
				$this->show_dry_run_results( $images, $force );
				return;
			}
			
			// Process in batches
			$processed = 0;
			$success_count = 0;
			$error_count = 0;
			$progress = \WP_CLI\Utils\make_progress_bar( 'Processing images', $total );
			
			foreach ( array_chunk( $images, $batch_size ) as $batch_num => $batch ) {
				if ( $verbose ) {
					WP_CLI::log( "\nProcessing batch " . ( $batch_num + 1 ) . '...' );
				}
				
				foreach ( $batch as $attachment_id ) {
					$result = $this->generate_alt_tag( (int) $attachment_id );
					
					if ( $result['success'] ) {
						$success_count++;
						if ( $verbose ) {
							$image_title = get_the_title( $attachment_id );
							WP_CLI::log( "✓ {$image_title} (ID: {$attachment_id}): {$result['alt_text']}" );
						}
					} else {
						$error_count++;
						if ( $verbose ) {
							$image_title = get_the_title( $attachment_id );
							WP_CLI::warning( "✗ {$image_title} (ID: {$attachment_id}): {$result['error']}" );
						}
					}
					
					$processed++;
					$progress->tick();
				}
				
				// Small delay between batches to avoid API rate limits
				if ( $batch_num < count( array_chunk( $images, $batch_size ) ) - 1 ) {
					sleep( 1 );
				}
			}
			
			$progress->finish();
			
			WP_CLI::success( "Processed {$processed} images. {$success_count} successful, {$error_count} errors." );
			
			if ( $error_count > 0 ) {
				WP_CLI::log( 'Use --verbose flag to see detailed error information.' );
			}
		}
		
		/**
		 * Show statistics about images and alt tags
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 *   - yaml
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     wp auto-alt stats
		 *     wp auto-alt stats --format=json
		 *
		 * @when after_wp_load
		 */
		public function stats( array $args, array $assoc_args ): void {
			global $wpdb;
			
			$format = $assoc_args['format'] ?? 'table';
			
			// Total images
			$total_images = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->posts} 
				WHERE post_type = %s 
				AND post_mime_type LIKE %s",
				'attachment',
				'image/%'
			) );
			
			// Images with alt tags
			$images_with_alt = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
				WHERE p.post_type = %s 
				AND p.post_mime_type LIKE %s
				AND pm.meta_key = %s
				AND pm.meta_value != %s",
				'attachment',
				'image/%',
				'_wp_attachment_image_alt',
				''
			) );
			
			$images_without_alt = $total_images - $images_with_alt;
			$percentage_with_alt = $total_images > 0 ? round( ( $images_with_alt / $total_images ) * 100, 1 ) : 0;
			
			$stats = array(
				array(
					'metric' => 'Total Images',
					'count'  => $total_images,
				),
				array(
					'metric' => 'With Alt Tags',
					'count'  => $images_with_alt,
				),
				array(
					'metric' => 'Without Alt Tags',
					'count'  => $images_without_alt,
				),
				array(
					'metric' => 'Coverage Percentage',
					'count'  => $percentage_with_alt . '%',
				),
			);
			
			if ( 'table' === $format ) {
				WP_CLI::log( '=== Image Alt Tag Statistics ===' );
			}
			
			\WP_CLI\Utils\format_items( $format, $stats, array( 'metric', 'count' ) );
			
			if ( 'table' === $format && $images_without_alt > 0 ) {
				WP_CLI::log( '' );
				WP_CLI::log( "Run 'wp auto-alt generate' to auto-generate missing alt tags." );
			}
		}
		
		/**
		 * Test the Gemini API connection
		 *
		 * ## EXAMPLES
		 *
		 *     wp auto-alt test-api
		 *
		 * @when after_wp_load
		 */
		public function test_api( array $args, array $assoc_args ): void {
			if ( empty( $this->gemini_api_key ) ) {
				WP_CLI::error( 'Gemini API key not configured.' );
			}
			
			WP_CLI::log( 'Testing Gemini API connection...' );
			
			// Create a small test image
			$test_image = $this->create_test_image();
			
			if ( ! $test_image ) {
				WP_CLI::error( 'Failed to create test image.' );
			}
			
			$result = $this->call_gemini_api( $test_image );
			
			// Clean up test image
			if ( file_exists( $test_image ) ) {
				wp_delete_file( $test_image );
			}
			
			if ( $result ) {
				WP_CLI::success( "API connection successful! Test response: \"{$result}\"" );
			} else {
				WP_CLI::error( 'API connection failed. Check your API key and network connection.' );
			}
		}
		
		/**
		 * Show dry run results
		 *
		 * @param array $images Array of image IDs.
		 * @param bool  $force Whether forcing regeneration.
		 */
		private function show_dry_run_results( array $images, bool $force ): void {
			$count = 0;
			foreach ( $images as $attachment_id ) {
				if ( $count >= 10 ) {
					WP_CLI::log( '... and ' . ( count( $images ) - 10 ) . ' more images' );
					break;
				}
				
				$url = wp_get_attachment_url( $attachment_id );
				$title = get_the_title( $attachment_id );
				$current_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				
				$status = $force && $current_alt ? " (current: \"{$current_alt}\")" : '';
				WP_CLI::log( "  - ID: {$attachment_id} - {$title}{$status}" );
				WP_CLI::log( "    URL: {$url}" );
				
				$count++;
			}
		}
		
		/**
		 * Get images without alt text
		 *
		 * @param int|null $limit Maximum number to return.
		 * @return array Array of attachment IDs
		 */
		private function get_images_without_alt( ?int $limit = null ): array {
			global $wpdb;
			
			$query = $wpdb->prepare(
				"SELECT p.ID 
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				WHERE p.post_type = %s
				AND p.post_mime_type LIKE %s
				AND (pm.meta_value IS NULL OR pm.meta_value = %s)
				ORDER BY p.ID ASC",
				'_wp_attachment_image_alt',
				'attachment',
				'image/%',
				''
			);
			
			if ( $limit ) {
				$query .= $wpdb->prepare( ' LIMIT %d', $limit );
			}
			
			return $wpdb->get_col( $query );
		}
		
		/**
		 * Get all images
		 *
		 * @param int|null $limit Maximum number to return.
		 * @return array Array of attachment IDs
		 */
		private function get_all_images( ?int $limit = null ): array {
			global $wpdb;
			
			$query = $wpdb->prepare(
				"SELECT ID 
				FROM {$wpdb->posts} 
				WHERE post_type = %s
				AND post_mime_type LIKE %s
				ORDER BY ID ASC",
				'attachment',
				'image/%'
			);
			
			if ( $limit ) {
				$query .= $wpdb->prepare( ' LIMIT %d', $limit );
			}
			
			return $wpdb->get_col( $query );
		}
		
		/**
		 * Generate alt tag for a single image
		 *
		 * @param int $attachment_id Attachment ID.
		 * @return array Result array with success boolean and alt_text or error
		 */
		private function generate_alt_tag( int $attachment_id ): array {
			try {
				// Create small version of image
				$resized_image = $this->create_small_image( $attachment_id );
				
				if ( ! $resized_image ) {
					return array(
						'success' => false,
						'error'   => 'Failed to create resized image',
					);
				}
				
				// Call Gemini API
				$alt_text = $this->call_gemini_api( $resized_image );
				
				// Clean up temp file
				if ( file_exists( $resized_image ) ) {
					wp_delete_file( $resized_image );
				}
				
				if ( ! $alt_text ) {
					return array(
						'success' => false,
						'error'   => 'API request failed',
					);
				}
				
				// Save alt text
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
				
				return array(
					'success'  => true,
					'alt_text' => $alt_text,
				);
				
			} catch ( Exception $e ) {
				return array(
					'success' => false,
					'error'   => $e->getMessage(),
				);
			}
		}
		
		/**
		 * Create a smaller version of an image for API calls
		 *
		 * @param int $attachment_id Attachment ID.
		 * @return string|false Path to temporary file or false on failure
		 */
		private function create_small_image( int $attachment_id ) {
			$image_path = get_attached_file( $attachment_id );
			
			if ( ! $image_path || ! file_exists( $image_path ) ) {
				return false;
			}
			
			$image_editor = wp_get_image_editor( $image_path );
			
			if ( is_wp_error( $image_editor ) ) {
				return false;
			}
			
			$current_size = $image_editor->get_size();
			
			if ( $current_size['width'] > $this->max_image_size || $current_size['height'] > $this->max_image_size ) {
				$image_editor->resize( $this->max_image_size, $this->max_image_size, false );
			}
			
			$temp_file = tempnam( sys_get_temp_dir(), 'auto_alt_' ) . '.jpg';
			
			$saved = $image_editor->save( $temp_file, 'image/jpeg' );
			
			if ( is_wp_error( $saved ) ) {
				return false;
			}
			
			return $temp_file;
		}
		
		/**
		 * Create a test image for API testing
		 *
		 * @return string|false Path to test image or false on failure
		 */
		private function create_test_image() {
			// Create a simple 100x100 test image
			$test_image = imagecreate( 100, 100 );
			$white = imagecolorallocate( $test_image, 255, 255, 255 );
			$black = imagecolorallocate( $test_image, 0, 0, 0 );
			
			// Add some text
			imagestring( $test_image, 5, 25, 40, 'TEST', $black );
			
			$temp_file = tempnam( sys_get_temp_dir(), 'api_test_' ) . '.jpg';
			imagejpeg( $test_image, $temp_file );
			imagedestroy( $test_image );
			
			return $temp_file;
		}
		
		/**
		 * Call Gemini API to generate alt text
		 *
		 * @param string $image_path Path to image file.
		 * @return string|false Generated alt text or false on failure
		 */
		private function call_gemini_api( string $image_path ) {
			$image_data = file_get_contents( $image_path );
			if ( false === $image_data ) {
				return false;
			}
			
			$base64_image = base64_encode( $image_data );
			
			// Use current Gemini 2.5 Flash model
			$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model_name . ':generateContent?key=' . $this->gemini_api_key;
			
			$payload = array(
				'contents' => array(
					array(
						'parts' => array(
							array(
								'text' => 'Generate a concise, descriptive alt text for this image. Focus on the main subject and important details. Keep it under 125 characters and avoid phrases like "image of" or "picture of". Be specific and helpful for screen readers.',
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
					'temperature'     => 0.3,
				),
			);
			
			$response = wp_remote_post( $api_url, array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			) );
			
			if ( is_wp_error( $response ) ) {
				return false;
			}
			
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			
			if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
				return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
			}
			
			return false;
		}
	}
	
	WP_CLI::add_command( 'auto-alt', 'Auto_Alt_CLI_Command' );
}
