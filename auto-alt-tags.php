<?php
/**
 * Plugin Name: Auto Alt Tag Generator
 * Plugin URI: https://github.com/kahunam/wordpress-auto-alt-tags
 * Description: Automatically generates alt tags for images using Google's Gemini 2.5 Flash API. Includes batch processing, cost optimization, and WP-CLI support.
 * Version: 1.0.2
 * Author: Your Name
 * Author URI: https://github.com/kahunam
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-alt-tags
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Network: false
 *
 * @package AutoAltTags
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'AUTO_ALT_TAGS_VERSION', '1.0.2' );
define( 'AUTO_ALT_TAGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTO_ALT_TAGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AUTO_ALT_TAGS_PLUGIN_FILE', __FILE__ );

/**
 * Main Auto Alt Tag Generator class
 *
 * @since 1.0.0
 */
class AutoAltTagGenerator {
	
	/**
	 * Gemini API key
	 *
	 * @var string
	 */
	private string $gemini_api_key;
	
	/**
	 * Default batch size for processing
	 *
	 * @var int
	 */
	private int $batch_size = 10;
	
	/**
	 * Maximum image size for API calls
	 *
	 * @var int
	 */
	private int $max_image_size = 512;
	
	/**
	 * Current Gemini model name
	 *
	 * @var string
	 */
	private string $model_name = 'gemini-2.5-flash-preview-04-17';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_process_alt_tags', array( $this, 'ajax_process_alt_tags' ) );
		add_action( 'wp_ajax_get_image_stats', array( $this, 'ajax_get_image_stats' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		
		// Get API key from wp_options or wp-config.php
		$this->gemini_api_key = defined( 'GEMINI_API_KEY' ) ? GEMINI_API_KEY : get_option( 'auto_alt_gemini_api_key', '' );
		
		// Load WP-CLI command if available
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once AUTO_ALT_TAGS_PLUGIN_DIR . 'includes/class-wp-cli-command.php';
		}
	}
	
	/**
	 * Load plugin textdomain for translations
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'auto-alt-tags', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
	
	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'media_page_auto-alt-tags' !== $hook ) {
			return;
		}
		
		wp_enqueue_script(
			'auto-alt-tags-admin',
			AUTO_ALT_TAGS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AUTO_ALT_TAGS_VERSION,
			true
		);
		
		wp_localize_script( 'auto-alt-tags-admin', 'autoAltAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'auto_alt_nonce' ),
		) );
		
		wp_enqueue_style(
			'auto-alt-tags-admin',
			AUTO_ALT_TAGS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AUTO_ALT_TAGS_VERSION
		);
	}
	
	/**
	 * Register plugin settings
	 */
	public function register_settings(): void {
		register_setting( 'auto_alt_tags_settings', 'auto_alt_gemini_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_batch_size', array(
			'sanitize_callback' => 'absint',
			'default'           => 10,
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_max_image_size', array(
			'sanitize_callback' => 'absint',
			'default'           => 512,
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_enable_debug', array(
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		) );
	}
	
	/**
	 * Add admin menu page
	 */
	public function add_admin_menu(): void {
		add_media_page(
			__( 'Auto Alt Tags', 'auto-alt-tags' ),
			__( 'Auto Alt Tags', 'auto-alt-tags' ), 
			'manage_options',
			'auto-alt-tags',
			array( $this, 'admin_page' )
		);
	}
	
	/**
	 * Render admin page
	 */
	public function admin_page(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'auto-alt-tags' ) );
		}
		
		// Get statistics
		$stats = $this->get_image_statistics();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Auto Alt Tag Generator', 'auto-alt-tags' ); ?></h1>
			
			<div class="card">
				<h2><?php esc_html_e( 'Image Statistics', 'auto-alt-tags' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Total Images', 'auto-alt-tags' ); ?></td>
							<td><strong><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Images with Alt Tags', 'auto-alt-tags' ); ?></td>
							<td><strong><?php echo esc_html( number_format_i18n( $stats['with_alt'] ) ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Images without Alt Tags', 'auto-alt-tags' ); ?></td>
							<td><strong><?php echo esc_html( number_format_i18n( $stats['without_alt'] ) ); ?></strong></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Coverage', 'auto-alt-tags' ); ?></td>
							<td><strong><?php echo esc_html( $stats['percentage'] ); ?>%</strong></td>
						</tr>
					</tbody>
				</table>
			</div>
			
			<?php if ( ! $this->gemini_api_key ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Please configure your Gemini API key in the settings below before using the auto-tagging feature.', 'auto-alt-tags' ); ?></p>
				</div>
			<?php endif; ?>
			
			<div class="card">
				<h2><?php esc_html_e( 'Generate Alt Tags', 'auto-alt-tags' ); ?></h2>
				
				<div id="alt-tag-progress" style="display: none;">
					<div class="notice notice-info">
						<p>
							<span id="progress-text"><?php esc_html_e( 'Processing...', 'auto-alt-tags' ); ?></span>
							<span id="progress-percentage" style="float: right;">0%</span>
						</p>
						<div style="background: #f0f0f0; height: 20px; border-radius: 3px; margin: 10px 0;">
							<div class="progress-fill" style="background: #0073aa; height: 100%; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
						</div>
					</div>
					<button id="stop-processing" class="button button-secondary">
						<?php esc_html_e( 'Stop Processing', 'auto-alt-tags' ); ?>
					</button>
				</div>
				
				<div id="control-buttons">
					<button id="start-processing" class="button button-primary" <?php echo ! $this->gemini_api_key ? 'disabled' : ''; ?>>
						<?php esc_html_e( 'Start Auto-Tagging Images', 'auto-alt-tags' ); ?>
					</button>
					
					<button id="refresh-stats" class="button button-secondary">
						<?php esc_html_e( 'Refresh Statistics', 'auto-alt-tags' ); ?>
					</button>
				</div>
				
				<div id="processing-log" style="display: none; margin-top: 20px; max-height: 300px; overflow-y: auto; background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">
					<h3><?php esc_html_e( 'Processing Log', 'auto-alt-tags' ); ?></h3>
				</div>
			</div>
			
			<div class="card">
				<h2><?php esc_html_e( 'Settings', 'auto-alt-tags' ); ?></h2>
				
				<form method="post" action="options.php">
					<?php settings_fields( 'auto_alt_tags_settings' ); ?>
					
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="auto_alt_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'auto-alt-tags' ); ?></label>
							</th>
							<td>
								<input type="password" 
								       id="auto_alt_gemini_api_key" 
								       name="auto_alt_gemini_api_key" 
								       value="<?php echo esc_attr( get_option( 'auto_alt_gemini_api_key', '' ) ); ?>" 
								       class="regular-text" 
								       autocomplete="new-password" />
								<p class="description">
									<?php
									printf(
										/* translators: %s: URL to Google AI Studio */
										esc_html__( 'Get your API key from %s', 'auto-alt-tags' ),
										'<a href="https://ai.google.dev/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google AI Studio', 'auto-alt-tags' ) . '</a>'
									);
									?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="auto_alt_batch_size"><?php esc_html_e( 'Batch Size', 'auto-alt-tags' ); ?></label>
							</th>
							<td>
								<input type="number" 
								       id="auto_alt_batch_size" 
								       name="auto_alt_batch_size" 
								       value="<?php echo esc_attr( get_option( 'auto_alt_batch_size', 10 ) ); ?>" 
								       min="1" 
								       max="50" 
								       class="small-text" />
								<p class="description">
									<?php esc_html_e( 'Number of images to process in each batch (1-50)', 'auto-alt-tags' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="auto_alt_max_image_size"><?php esc_html_e( 'Max Image Size (px)', 'auto-alt-tags' ); ?></label>
							</th>
							<td>
								<input type="number" 
								       id="auto_alt_max_image_size" 
								       name="auto_alt_max_image_size" 
								       value="<?php echo esc_attr( get_option( 'auto_alt_max_image_size', 512 ) ); ?>" 
								       min="256" 
								       max="2048" 
								       step="256" 
								       class="small-text" />
								<p class="description">
									<?php esc_html_e( 'Maximum image size sent to API (smaller = lower costs)', 'auto-alt-tags' ); ?>
								</p>
							</td>
						</tr>
						
						<tr>
							<th scope="row">
								<label for="auto_alt_enable_debug"><?php esc_html_e( 'Enable Debug Logging', 'auto-alt-tags' ); ?></label>
							</th>
							<td>
								<input type="checkbox" 
								       id="auto_alt_enable_debug" 
								       name="auto_alt_enable_debug" 
								       value="1" 
								       <?php checked( get_option( 'auto_alt_enable_debug', false ) ); ?> />
								<p class="description">
									<?php esc_html_e( 'Enable detailed error logging to help diagnose issues', 'auto-alt-tags' ); ?>
								</p>
							</td>
						</tr>
					</table>
					
					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}
	
	/**
	 * AJAX handler for processing alt tags
	 */
	public function ajax_process_alt_tags(): void {
		// Verify nonce for security
		if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
		}
		
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'auto-alt-tags' ) );
		}
		
		// Check API key
		if ( empty( $this->gemini_api_key ) ) {
			wp_send_json_error( __( 'Gemini API key not configured', 'auto-alt-tags' ) );
		}
		
		// Get images without alt text
		$images_without_alt = $this->get_images_without_alt();
		$total_images = count( $images_without_alt );
		
		if ( 0 === $total_images ) {
			wp_send_json_success( array(
				'completed' => true,
				'message'   => __( 'No images need alt tags', 'auto-alt-tags' ),
				'progress'  => 100,
			) );
		}
		
		// Get current batch offset
		$current_offset = get_transient( 'auto_alt_offset' ) ?: 0;
		$batch_size = get_option( 'auto_alt_batch_size', $this->batch_size );
		$batch = array_slice( $images_without_alt, $current_offset, $batch_size );
		
		if ( empty( $batch ) ) {
			// Processing complete
			delete_transient( 'auto_alt_offset' );
			wp_send_json_success( array(
				'completed' => true,
				'message'   => __( 'All images processed', 'auto-alt-tags' ),
				'progress'  => 100,
			) );
		}
		
		// Process current batch
		$processed = 0;
		$errors = array();
		
		foreach ( $batch as $attachment_id ) {
			$result = $this->generate_alt_tag( (int) $attachment_id );
			if ( $result['success'] ) {
				$processed++;
			} else {
				$errors[] = sprintf(
					/* translators: %1$d: Image ID, %2$s: Error message */
					__( 'Image ID %1$d: %2$s', 'auto-alt-tags' ), 
					$attachment_id, 
					$result['error']
				);
			}
		}
		
		// Update offset
		$new_offset = $current_offset + $batch_size;
		set_transient( 'auto_alt_offset', $new_offset, HOUR_IN_SECONDS );
		
		// Calculate progress
		$progress = min( 100, ( $new_offset / $total_images ) * 100 );
		
		wp_send_json_success( array(
			'completed' => $new_offset >= $total_images,
			'message'   => sprintf(
				/* translators: %1$d: Processed count, %2$d: New offset, %3$d: Total images, %4$d: Success count */
				__( 'Processed %1$d/%2$d images. %3$d successful.', 'auto-alt-tags' ),
				min( $new_offset, $total_images ),
				$total_images,
				$processed
			),
			'progress'  => round( $progress, 1 ),
			'errors'    => $errors,
		) );
	}
	
	/**
	 * AJAX handler for getting image statistics
	 */
	public function ajax_get_image_stats(): void {
		// Verify nonce for security
		if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
		}
		
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'auto-alt-tags' ) );
		}
		
		$stats = $this->get_image_statistics();
		wp_send_json_success( $stats );
	}
	
	/**
	 * Get image statistics
	 *
	 * @return array Statistics array with total, with_alt, without_alt, percentage keys
	 */
	private function get_image_statistics(): array {
		global $wpdb;
		
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
		$percentage = $total_images > 0 ? round( ( $images_with_alt / $total_images ) * 100, 1 ) : 0;
		
		return array(
			'total'       => $total_images,
			'with_alt'    => $images_with_alt,
			'without_alt' => $images_without_alt,
			'percentage'  => $percentage,
		);
	}
	
	/**
	 * Get images without alt text
	 *
	 * @return array Array of attachment IDs
	 */
	private function get_images_without_alt(): array {
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
			$this->log_debug( "Starting alt tag generation for attachment ID: $attachment_id" );
			
			// Get the appropriate image size
			$image_url = $this->get_optimized_image_url( $attachment_id );
			
			if ( ! $image_url ) {
				$this->log_error( "Failed to get image URL for attachment ID: $attachment_id" );
				return array(
					'success' => false,
					'error'   => __( 'Failed to get image URL', 'auto-alt-tags' ),
				);
			}
			
			// Download image data
			$image_data = $this->download_image( $image_url );
			
			if ( ! $image_data ) {
				$this->log_error( "Failed to download image for attachment ID: $attachment_id" );
				return array(
					'success' => false,
					'error'   => __( 'Failed to download image', 'auto-alt-tags' ),
				);
			}
			
			// Call Gemini API
			$alt_text = $this->call_gemini_api( $image_data );
			
			if ( ! $alt_text ) {
				return array(
					'success' => false,
					'error'   => __( 'API request failed', 'auto-alt-tags' ),
				);
			}
			
			// Save alt text
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
			$this->log_debug( "Successfully generated alt text for attachment ID: $attachment_id - $alt_text" );
			
			return array(
				'success'  => true,
				'alt_text' => $alt_text,
			);
			
		} catch ( Exception $e ) {
			$this->log_error( "Exception in generate_alt_tag for attachment ID $attachment_id: " . $e->getMessage() );
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}
	
	/**
	 * Get optimized image URL using WordPress thumbnail sizes
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false Image URL or false on failure
	 */
	private function get_optimized_image_url( int $attachment_id ) {
		$max_size = get_option( 'auto_alt_max_image_size', $this->max_image_size );
		
		// Try to use existing WordPress thumbnail sizes
		$sizes = array( 'thumbnail', 'medium', 'medium_large', 'large' );
		
		foreach ( $sizes as $size ) {
			$image = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $image && $image[1] <= $max_size && $image[2] <= $max_size ) {
				$this->log_debug( "Using $size size for attachment ID: $attachment_id" );
				return $image[0];
			}
		}
		
		// If no suitable size, use full size
		$image = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( $image ) {
			$this->log_debug( "Using full size for attachment ID: $attachment_id" );
			return $image[0];
		}
		
		return false;
	}
	
	/**
	 * Download image from URL
	 *
	 * @param string $image_url Image URL.
	 * @return string|false Base64 encoded image data or false on failure
	 */
	private function download_image( string $image_url ) {
		$response = wp_remote_get( $image_url, array(
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Failed to download image: ' . $response->get_error_message() );
			return false;
		}
		
		$image_data = wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) ) {
			$this->log_error( 'Empty image data received' );
			return false;
		}
		
		return base64_encode( $image_data );
	}
	
	/**
	 * Call Gemini API to generate alt text
	 *
	 * @param string $base64_image Base64 encoded image data.
	 * @return string|false Generated alt text or false on failure
	 */
	private function call_gemini_api( string $base64_image ) {
		if ( empty( $this->gemini_api_key ) ) {
			$this->log_error( 'Gemini API key not configured' );
			return false;
		}
		
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
		
		$this->log_debug( 'Sending request to Gemini API' );
		
		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Gemini API request failed: ' . $response->get_error_message() );
			return false;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		
		$this->log_debug( "API Response Code: $response_code" );
		
		if ( $response_code !== 200 ) {
			$this->log_error( "API returned non-200 status: $response_code - Body: $body" );
			return false;
		}
		
		$data = json_decode( $body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_error( 'Failed to parse API response: ' . json_last_error_msg() );
			return false;
		}
		
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return trim( $data['candidates'][0]['content']['parts'][0]['text'] );
		}
		
		if ( isset( $data['error'] ) ) {
			$this->log_error( 'API error: ' . wp_json_encode( $data['error'] ) );
		} else {
			$this->log_error( 'Unexpected Gemini API response structure: ' . $body );
		}
		
		return false;
	}
	
	/**
	 * Log debug messages
	 *
	 * @param string $message Message to log.
	 */
	private function log_debug( string $message ): void {
		if ( get_option( 'auto_alt_enable_debug', false ) ) {
			error_log( '[Auto Alt Tags Debug] ' . $message );
		}
	}
	
	/**
	 * Log error messages
	 *
	 * @param string $message Error message to log.
	 */
	private function log_error( string $message ): void {
		error_log( '[Auto Alt Tags Error] ' . $message );
	}
}

// Initialize the plugin
new AutoAltTagGenerator();

// Activation hook
register_activation_hook( __FILE__, function () {
	// Create any necessary database tables or options here
	add_option( 'auto_alt_batch_size', 10 );
	add_option( 'auto_alt_max_image_size', 512 );
	add_option( 'auto_alt_enable_debug', false );
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function () {
	// Clean up transients
	delete_transient( 'auto_alt_offset' );
} );
