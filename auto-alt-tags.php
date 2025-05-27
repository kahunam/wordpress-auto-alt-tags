<?php
/**
 * Plugin Name: Auto Alt Tag Generator
 * Plugin URI: https://github.com/kahunam/wordpress-auto-alt-tags
 * Description: Automatically generates alt tags for images using Google's Gemini API. Includes batch processing, cost optimization, and WP-CLI support.
 * Version: 1.1.1
 * Author: Kahunam
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
define( 'AUTO_ALT_TAGS_VERSION', '1.1.1' );
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
	 * Available Gemini models (as of May 2025)
	 *
	 * @var array
	 */
	private array $available_models = array(
		'gemini-2.0-flash' => 'Gemini 2.0 Flash (Recommended - Fast & Efficient)',
		'gemini-1.5-flash' => 'Gemini 1.5 Flash',
		'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B (Smallest)',
		'gemini-1.5-pro' => 'Gemini 1.5 Pro (Most Capable)',
	);
	
	/**
	 * Current Gemini model name
	 *
	 * @var string
	 */
	private string $model_name = 'gemini-2.0-flash';
	
	/**
	 * Debug mode flag
	 *
	 * @var bool
	 */
	private bool $debug_mode = false;
	
	/**
	 * Default prompt for alt text generation
	 *
	 * @var string
	 */
	private string $default_prompt = 'Generate a concise, descriptive alt text for this image. Focus on the main subject and important details. Keep it under 125 characters and avoid phrases like "image of" or "picture of". Be specific and helpful for screen readers.';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_process_alt_tags', array( $this, 'ajax_process_alt_tags' ) );
		add_action( 'wp_ajax_get_image_stats', array( $this, 'ajax_get_image_stats' ) );
		add_action( 'wp_ajax_test_api_connection', array( $this, 'ajax_test_api_connection' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		
		// Initialize settings
		$this->gemini_api_key = defined( 'GEMINI_API_KEY' ) ? GEMINI_API_KEY : get_option( 'auto_alt_gemini_api_key', '' );
		$this->model_name = get_option( 'auto_alt_model_name', 'gemini-2.0-flash' );
		$this->debug_mode = (bool) get_option( 'auto_alt_debug_mode', false );
		
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
		
		// Use WordPress default admin styles - no custom CSS needed
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
	}
	
	/**
	 * Register plugin settings
	 */
	public function register_settings(): void {
		register_setting( 'auto_alt_tags_settings', 'auto_alt_gemini_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_model_name', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'gemini-2.0-flash',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_batch_size', array(
			'sanitize_callback' => 'absint',
			'default'           => 10,
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_image_size', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'medium',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_debug_mode', array(
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_custom_prompt', array(
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
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
			
			<!-- Statistics Cards -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
				<div class="card">
					<div class="inside">
						<h2><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></h2>
						<p><?php esc_html_e( 'Total Images', 'auto-alt-tags' ); ?></p>
					</div>
				</div>
				<div class="card">
					<div class="inside">
						<h2><?php echo esc_html( number_format_i18n( $stats['with_alt'] ) ); ?></h2>
						<p><?php esc_html_e( 'With Alt Tags', 'auto-alt-tags' ); ?></p>
					</div>
				</div>
				<div class="card">
					<div class="inside">
						<h2><?php echo esc_html( number_format_i18n( $stats['without_alt'] ) ); ?></h2>
						<p><?php esc_html_e( 'Need Alt Tags', 'auto-alt-tags' ); ?></p>
					</div>
				</div>
				<div class="card">
					<div class="inside">
						<h2><?php echo esc_html( $stats['percentage'] ); ?>%</h2>
						<p><?php esc_html_e( 'Coverage', 'auto-alt-tags' ); ?></p>
					</div>
				</div>
			</div>
			
			<?php if ( ! $this->gemini_api_key ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Please configure your Gemini API key in the settings below before using the auto-tagging feature.', 'auto-alt-tags' ); ?></p>
				</div>
			<?php endif; ?>
			
			<!-- Processing Section -->
			<div class="card">
				<h2 class="title"><?php esc_html_e( 'Generate Alt Tags', 'auto-alt-tags' ); ?></h2>
				<div class="inside">
					<div id="alt-tag-progress" style="display: none; margin-bottom: 20px;">
						<progress id="progress-bar" max="100" value="0" style="width: 100%; height: 20px;"></progress>
						<p>
							<span id="progress-text"><?php esc_html_e( 'Processing...', 'auto-alt-tags' ); ?></span>
							<span id="progress-percentage" style="float: right;">0%</span>
						</p>
					</div>
					
					<div id="control-buttons">
						<button id="start-processing" class="button button-primary" <?php echo ! $this->gemini_api_key ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Start Auto-Tagging Images', 'auto-alt-tags' ); ?>
						</button>
						
						<button id="test-api" class="button button-secondary" <?php echo ! $this->gemini_api_key ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Test API Connection', 'auto-alt-tags' ); ?>
						</button>
						
						<button id="refresh-stats" class="button button-secondary">
							<?php esc_html_e( 'Refresh Statistics', 'auto-alt-tags' ); ?>
						</button>
						
						<button id="stop-processing" class="button button-secondary" style="display: none;">
							<?php esc_html_e( 'Stop Processing', 'auto-alt-tags' ); ?>
						</button>
					</div>
					
					<!-- Debug Log Area -->
					<div id="debug-log" style="display: <?php echo $this->debug_mode ? 'block' : 'none'; ?>; margin-top: 20px;">
						<h3><?php esc_html_e( 'Debug Log', 'auto-alt-tags' ); ?></h3>
						<div id="log-content" style="background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">
							<!-- Log messages will appear here -->
						</div>
					</div>
				</div>
			</div>
			
			<!-- Settings Section -->
			<div class="card" style="margin-top: 20px;">
				<h2 class="title"><?php esc_html_e( 'Settings', 'auto-alt-tags' ); ?></h2>
				<div class="inside">
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
											'<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google AI Studio', 'auto-alt-tags' ) . '</a>'
										);
										?>
									</p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="auto_alt_model_name"><?php esc_html_e( 'Gemini Model', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<select id="auto_alt_model_name" name="auto_alt_model_name">
										<?php foreach ( $this->available_models as $model => $description ) : ?>
											<option value="<?php echo esc_attr( $model ); ?>" <?php selected( get_option( 'auto_alt_model_name', 'gemini-2.0-flash' ), $model ); ?>>
												<?php echo esc_html( $description ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Choose the Gemini model to use for generating alt text', 'auto-alt-tags' ); ?>
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
									<label for="auto_alt_image_size"><?php esc_html_e( 'Image Size for API', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<select id="auto_alt_image_size" name="auto_alt_image_size">
										<?php
										$sizes = get_intermediate_image_sizes();
										$selected_size = get_option( 'auto_alt_image_size', 'medium' );
										foreach ( $sizes as $size ) :
											?>
											<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $selected_size, $size ); ?>>
												<?php echo esc_html( ucfirst( str_replace( '-', ' ', $size ) ) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Use existing WordPress thumbnail size for API calls (smaller = lower costs)', 'auto-alt-tags' ); ?>
									</p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="auto_alt_custom_prompt"><?php esc_html_e( 'Custom Prompt (Optional)', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<textarea id="auto_alt_custom_prompt" 
											  name="auto_alt_custom_prompt" 
											  rows="4" 
											  class="large-text"
											  placeholder="<?php echo esc_attr( $this->default_prompt ); ?>"><?php echo esc_textarea( get_option( 'auto_alt_custom_prompt', '' ) ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'Override the default prompt for generating alt text. Leave empty to use the default prompt. Keep it focused on accessibility and under 125 characters.', 'auto-alt-tags' ); ?>
									</p>
								</td>
							</tr>
							
							<tr>
								<th scope="row">
									<label for="auto_alt_debug_mode"><?php esc_html_e( 'Debug Mode', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<label for="auto_alt_debug_mode">
										<input type="checkbox" 
											   id="auto_alt_debug_mode" 
											   name="auto_alt_debug_mode" 
											   value="1" 
											   <?php checked( get_option( 'auto_alt_debug_mode', false ) ); ?> />
										<?php esc_html_e( 'Enable debug logging', 'auto-alt-tags' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'Show detailed logs for troubleshooting API issues', 'auto-alt-tags' ); ?>
									</p>
								</td>
							</tr>
						</table>
						
						<?php submit_button(); ?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * AJAX handler for testing API connection
	 */
	public function ajax_test_api_connection(): void {
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
		
		$this->debug_log( 'Testing API connection...' );
		
		// Test API with a simple text prompt
		$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model_name . ':generateContent?key=' . $this->gemini_api_key;
		
		$payload = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => 'Say "Hello, API is working!" in exactly 5 words.',
						),
					),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens' => 20,
				'temperature'     => 0.1,
			),
		);
		
		$this->debug_log( 'API URL: ' . $api_url );
		$this->debug_log( 'Using model: ' . $this->model_name );
		
		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->debug_log( 'API test failed: ' . $error_message );
			wp_send_json_error( sprintf( __( 'Connection failed: %s', 'auto-alt-tags' ), $error_message ) );
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		$this->debug_log( 'Response code: ' . $response_code );
		$this->debug_log( 'Response body: ' . $body );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'auto-alt-tags' );
			wp_send_json_error( sprintf( __( 'API Error (%d): %s', 'auto-alt-tags' ), $response_code, $error_msg ) );
		}
		
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			wp_send_json_success( array(
				'message' => __( 'API connection successful!', 'auto-alt-tags' ),
				'response' => $data['candidates'][0]['content']['parts'][0]['text'],
			) );
		} else {
			wp_send_json_error( __( 'Unexpected API response format', 'auto-alt-tags' ) );
		}
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
		
		$this->debug_log( 'Starting alt tag processing...' );
		
		// Get images without alt text
		$images_without_alt = $this->get_images_without_alt();
		$total_images = count( $images_without_alt );
		
		$this->debug_log( sprintf( 'Found %d images without alt text', $total_images ) );
		
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
			$this->debug_log( 'Processing complete!' );
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
			$this->debug_log( sprintf( 'Processing image ID: %d', $attachment_id ) );
			$result = $this->generate_alt_tag( (int) $attachment_id );
			if ( $result['success'] ) {
				$processed++;
				$this->debug_log( sprintf( 'Success: %s', $result['alt_text'] ) );
			} else {
				$error_msg = sprintf(
					/* translators: %1$d: Image ID, %2$s: Error message */
					__( 'Image ID %1$d: %2$s', 'auto-alt-tags' ), 
					$attachment_id, 
					$result['error']
				);
				$errors[] = $error_msg;
				$this->debug_log( sprintf( 'Error: %s', $result['error'] ) );
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
				/* translators: %1$d: Processed count, %2$d: Total count, %3$d: Success count */
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
			// Get image URL using WordPress built-in sizes
			$image_size = get_option( 'auto_alt_image_size', 'medium' );
			$image_data = wp_get_attachment_image_src( $attachment_id, $image_size );
			
			if ( ! $image_data ) {
				return array(
					'success' => false,
					'error'   => __( 'Failed to get image URL', 'auto-alt-tags' ),
				);
			}
			
			$image_url = $image_data[0];
			$this->debug_log( sprintf( 'Using image URL: %s', $image_url ) );
			
			// Get image file path
			$upload_dir = wp_upload_dir();
			$image_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $image_url );
			
			if ( ! file_exists( $image_path ) ) {
				// Try to get the original file if sized version doesn't exist
				$image_path = get_attached_file( $attachment_id );
			}
			
			if ( ! $image_path || ! file_exists( $image_path ) ) {
				return array(
					'success' => false,
					'error'   => __( 'Image file not found', 'auto-alt-tags' ),
				);
			}
			
			// Call Gemini API
			$alt_text = $this->call_gemini_api( $image_path );
			
			if ( ! $alt_text ) {
				return array(
					'success' => false,
					'error'   => __( 'API request failed', 'auto-alt-tags' ),
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
	 * Call Gemini API to generate alt text
	 *
	 * @param string $image_path Path to image file.
	 * @return string|false Generated alt text or false on failure
	 */
	private function call_gemini_api( string $image_path ) {
		if ( empty( $this->gemini_api_key ) ) {
			$this->debug_log( 'Gemini API key not configured' );
			return false;
		}
		
		// Read and encode image
		$image_data = file_get_contents( $image_path );
		if ( false === $image_data ) {
			$this->debug_log( 'Failed to read image file' );
			return false;
		}
		
		$base64_image = base64_encode( $image_data );
		$mime_type = wp_check_filetype( $image_path )['type'] ?: 'image/jpeg';
		
		// Get the prompt (custom or default)
		$custom_prompt = get_option( 'auto_alt_custom_prompt', '' );
		$prompt = ! empty( $custom_prompt ) ? $custom_prompt : $this->default_prompt;
		
		// Use the selected model
		$model = get_option( 'auto_alt_model_name', 'gemini-2.0-flash' );
		$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $this->gemini_api_key;
		
		$this->debug_log( sprintf( 'Calling Gemini API with model: %s', $model ) );
		$this->debug_log( sprintf( 'Using prompt: %s', $prompt ) );
		
		$payload = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
						array(
							'inline_data' => array(
								'mime_type' => $mime_type,
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
			$this->debug_log( 'API request failed: ' . $response->get_error_message() );
			return false;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		$this->debug_log( sprintf( 'API response code: %d', $response_code ) );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			$this->debug_log( sprintf( 'API error: %s', $error_msg ) );
			return false;
		}
		
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$alt_text = trim( $data['candidates'][0]['content']['parts'][0]['text'] );
			$this->debug_log( sprintf( 'Generated alt text: %s', $alt_text ) );
			return $alt_text;
		}
		
		$this->debug_log( 'Unexpected API response format: ' . $body );
		return false;
	}
	
	/**
	 * Debug logging helper
	 *
	 * @param string $message Log message.
	 */
	private function debug_log( string $message ): void {
		if ( ! $this->debug_mode ) {
			return;
		}
		
		// Log to PHP error log
		error_log( '[Auto Alt Tags] ' . $message );
		
		// Also store in transient for display in admin
		$logs = get_transient( 'auto_alt_debug_logs' ) ?: array();
		$logs[] = date( 'Y-m-d H:i:s' ) . ' - ' . $message;
		
		// Keep only last 100 log entries
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}
		
		set_transient( 'auto_alt_debug_logs', $logs, HOUR_IN_SECONDS );
	}
}

// Initialize the plugin
new AutoAltTagGenerator();

// Activation hook
register_activation_hook( __FILE__, function () {
	// Create any necessary database tables or options here
	add_option( 'auto_alt_batch_size', 10 );
	add_option( 'auto_alt_image_size', 'medium' );
	add_option( 'auto_alt_model_name', 'gemini-2.0-flash' );
	add_option( 'auto_alt_debug_mode', false );
	add_option( 'auto_alt_custom_prompt', '' );
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function () {
	// Clean up transients
	delete_transient( 'auto_alt_offset' );
	delete_transient( 'auto_alt_debug_logs' );
} );
