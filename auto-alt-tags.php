<?php
/**
 * Plugin Name: Auto Alt Tag Generator
 * Plugin URI: https://github.com/kahunam/wordpress-auto-alt-tags
 * Description: Automatically generates alt tags for images using AI APIs (Gemini, OpenAI, Claude, OpenRouter). Includes batch processing, cost optimization, preview testing, and WP-CLI support.
 * Version: 1.0.0
 * Author: Kahunam
 * Author URI: https://github.com/kahunam
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-alt-tags
 * Domain Path: /languages
 * Requires at least: 4.1
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
define( 'AUTO_ALT_TAGS_VERSION', '1.0.0' );
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
	 * Current API key
	 *
	 * @var string
	 */
	private string $current_api_key;
	
	/**
	 * Default batch size for processing
	 *
	 * @var int
	 */
	private int $batch_size = 10;
	
	/**
	 * Available AI providers and their models
	 *
	 * @var array
	 */
	private array $available_providers = array(
		'gemini' => array(
			'name' => 'Google Gemini',
			'api_key_setting' => 'auto_alt_gemini_api_key',
			'models' => array(
				'gemini-2.5-flash' => 'Gemini 2.5 Flash (Recommended - Best Price/Performance)',
				'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite (Cost Effective)',
				'gemini-3-flash-preview' => 'Gemini 3 Flash Preview (Latest - Advanced Vision)',
				'gemini-3-pro-preview' => 'Gemini 3 Pro Preview (Most Capable)',
			),
		),
		'openai' => array(
			'name' => 'OpenAI',
			'api_key_setting' => 'auto_alt_openai_api_key',
			'models' => array(
				'gpt-4o' => 'GPT-4o (Latest - Vision Capable)',
				'gpt-4o-mini' => 'GPT-4o Mini (Cost Effective)',
				'gpt-4-turbo' => 'GPT-4 Turbo (Most Capable)',
				'gpt-4-vision-preview' => 'GPT-4 Vision Preview',
			),
		),
		'claude' => array(
			'name' => 'Anthropic Claude',
			'api_key_setting' => 'auto_alt_claude_api_key',
			'models' => array(
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Latest)',
				'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Fast)',
				'claude-3-opus-20240229' => 'Claude 3 Opus (Most Capable)',
			),
		),
		'openrouter' => array(
			'name' => 'OpenRouter',
			'api_key_setting' => 'auto_alt_openrouter_api_key',
			'models' => array(
				'anthropic/claude-3.5-sonnet' => 'Claude 3.5 Sonnet via OpenRouter',
				'openai/gpt-4o' => 'GPT-4o via OpenRouter',
				'openai/gpt-4o-mini' => 'GPT-4o Mini via OpenRouter',
				'google/gemini-pro-1.5' => 'Gemini Pro 1.5 via OpenRouter',
			),
		),
	);
	
	/**
	 * Current AI provider
	 *
	 * @var string
	 */
	private string $current_provider = 'gemini';
	
	/**
	 * Get available models for current provider
	 *
	 * @return array
	 */
	private function get_available_models(): array {
		$provider = get_option( 'auto_alt_provider', 'gemini' );
		return $this->available_providers[ $provider ]['models'] ?? $this->available_providers['gemini']['models'];
	}

	/**
	 * Get free-tier rate limits for Gemini models.
	 * Source: https://ai.google.dev/gemini-api/docs/rate-limits (Feb 2026)
	 *
	 * Keys: rpm (requests/min), rpd (requests/day), tpm (tokens/min),
	 *       sleep (seconds between calls), max_batch (safe batch ceiling).
	 *
	 * @return array<string, array<string, int>>
	 */
	private function get_model_rate_limits(): array {
		return array(
			'gemini-2.5-flash'       => array( 'rpm' => 10, 'rpd' => 250,  'tpm' => 250000, 'sleep' => 5,  'max_batch' => 5 ),
			'gemini-2.5-flash-lite'  => array( 'rpm' => 15, 'rpd' => 1000, 'tpm' => 250000, 'sleep' => 3,  'max_batch' => 7 ),
			'gemini-3-flash-preview' => array( 'rpm' => 10, 'rpd' => 250,  'tpm' => 250000, 'sleep' => 5,  'max_batch' => 5 ),
			'gemini-3-pro-preview'   => array( 'rpm' => 5,  'rpd' => 100,  'tpm' => 250000, 'sleep' => 11, 'max_batch' => 2 ),
		);
	}

	/**
	 * Rate limits for all supported providers, used for UI display after key test.
	 * Sources (Feb 2026):
	 *   Gemini:     https://ai.google.dev/gemini-api/docs/rate-limits
	 *   OpenAI:     https://platform.openai.com/docs/guides/rate-limits (Tier 1)
	 *   Claude:     https://docs.anthropic.com/en/api/rate-limits (Tier 1)
	 *   OpenRouter: https://openrouter.ai/docs/api/reference/limits (Free Tier)
	 *
	 * @return array<string, array>
	 */
	private function get_all_provider_rate_limits(): array {
		return array(
			'gemini' => array(
				'tier'   => 'Free Tier',
				'docs'   => 'https://ai.google.dev/gemini-api/docs/rate-limits',
				'models' => $this->get_model_rate_limits(),
			),
			'openai' => array(
				'tier'   => 'Tier 1',
				'docs'   => 'https://platform.openai.com/docs/guides/rate-limits',
				'models' => array(
					'gpt-4o'               => array( 'rpm' => 500, 'rpd' => null,  'tpm' => 30000 ),
					'gpt-4o-mini'          => array( 'rpm' => 500, 'rpd' => 10000, 'tpm' => 200000 ),
					'gpt-4-turbo'          => array( 'rpm' => 500, 'rpd' => null,  'tpm' => 30000 ),
					'gpt-4-vision-preview' => array( 'rpm' => 100, 'rpd' => null,  'tpm' => 10000 ),
				),
			),
			'claude' => array(
				'tier'   => 'Tier 1',
				'docs'   => 'https://docs.anthropic.com/en/api/rate-limits',
				'models' => array(
					'claude-3-5-sonnet-20241022' => array( 'rpm' => 50, 'itpm' => 30000, 'otpm' => 8000 ),
					'claude-3-5-haiku-20241022'  => array( 'rpm' => 50, 'itpm' => 50000, 'otpm' => 10000 ),
					'claude-3-opus-20240229'     => array( 'rpm' => 50, 'itpm' => 10000, 'otpm' => 2000 ),
				),
			),
			'openrouter' => array(
				'tier'   => 'Free Tier',
				'docs'   => 'https://openrouter.ai/docs/api/reference/limits',
				'all'    => array( 'rpm' => 20, 'rpd' => 50 ),
			),
		);
	}

	/**
	 * Get API key for current provider
	 *
	 * @param string $provider Provider name.
	 * @return string
	 */
	private function get_current_api_key( string $provider = '' ): string {
		if ( empty( $provider ) ) {
			$provider = get_option( 'auto_alt_provider', 'gemini' );
		}
		
		$provider_data = $this->available_providers[ $provider ] ?? null;
		if ( ! $provider_data ) {
			return '';
		}
		
		// Check for wp-config constant first (for Gemini backward compatibility)
		if ( 'gemini' === $provider && defined( 'GEMINI_API_KEY' ) ) {
			return GEMINI_API_KEY;
		}
		
		return get_option( $provider_data['api_key_setting'], '' );
	}
	
	/**
	 * Current Gemini model name
	 *
	 * @var string
	 */
	private string $model_name = 'gemini-2.5-flash';
	
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
	private string $default_prompt = 'You are an accessibility expert. Generate ONLY the alt text for this image - no explanations, no options, just the final alt text. Describe what is shown objectively. For people, describe only their actions, clothing, or position - never mention age, attractiveness, weight, or other physical attributes that could be considered judgmental. Keep it under 125 characters. Do not include phrases like "image of" or "picture of". Return only the alt text string, nothing else.';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_process_alt_tags', array( $this, 'ajax_process_alt_tags' ) );
		add_action( 'wp_ajax_get_image_stats', array( $this, 'ajax_get_image_stats' ) );
		add_action( 'wp_ajax_test_api_connection', array( $this, 'ajax_test_api_connection' ) );
		add_action( 'wp_ajax_test_provider_key', array( $this, 'ajax_test_provider_key' ) );
		add_action( 'wp_ajax_test_first_five', array( $this, 'ajax_test_first_five' ) );
		add_action( 'wp_ajax_check_alt_session', array( $this, 'ajax_check_session' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		
		// Initialize settings
		$this->current_provider = get_option( 'auto_alt_provider', 'gemini' );
		$this->current_api_key = $this->get_current_api_key( $this->current_provider );
		$this->model_name = get_option( 'auto_alt_model_name', 'gemini-2.5-flash' );
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

		// Enqueue admin styles
		wp_enqueue_style(
			'ka-alt-tags-admin',
			AUTO_ALT_TAGS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			AUTO_ALT_TAGS_VERSION
		);

		wp_enqueue_script(
			'ka-alt-tags-admin',
			AUTO_ALT_TAGS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			AUTO_ALT_TAGS_VERSION,
			true
		);

		wp_localize_script( 'ka-alt-tags-admin', 'autoAltAjax', array(
			'ajaxurl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'auto_alt_nonce' ),
			'rateLimits'     => $this->get_model_rate_limits(),
			'modelNames'     => $this->available_providers['gemini']['models'],
			'providerLimits' => $this->get_all_provider_rate_limits(),
		) );
	}
	
	/**
	 * Register plugin settings
	 */
	public function register_settings(): void {
		register_setting( 'auto_alt_tags_settings', 'auto_alt_provider', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'gemini',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_gemini_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_openai_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_claude_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_openrouter_api_key', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		
		register_setting( 'auto_alt_tags_settings', 'auto_alt_model_name', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'gemini-2.5-flash',
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
			
			<?php 
			$current_provider = get_option( 'auto_alt_provider', 'gemini' );
			$current_api_key = $this->get_current_api_key( $current_provider );
			$provider_name = $this->available_providers[ $current_provider ]['name'] ?? 'Selected';
			?>
			<?php if ( ! $current_api_key ) : ?>
				<div class="notice notice-warning">
					<p><?php printf( esc_html__( 'Please configure your %s API key in the settings below before using the auto-tagging feature.', 'auto-alt-tags' ), esc_html( $provider_name ) ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Top row: Generate Alt Tags (left) | Cost & Security (right) -->
			<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; align-items: start; margin-bottom: 20px;">
			<div><!-- left: Generate Alt Tags -->

			<!-- Processing Section -->
			<div class="card">
				<h2 class="title"><?php esc_html_e( 'Generate Alt Tags', 'auto-alt-tags' ); ?></h2>
				<div class="inside">
					<div id="ka_alt_progress" style="display: none; margin-bottom: 20px;">
						<progress id="ka_alt_progress_bar" max="100" value="0" style="width: 100%; height: 20px;"></progress>
						<p>
							<span id="ka_alt_progress_text"><?php esc_html_e( 'Processing...', 'auto-alt-tags' ); ?></span>
							<span id="ka_alt_progress_percentage" style="float: right;">0%</span>
						</p>
					</div>

					<div id="ka_alt_control_buttons">
						<button id="ka_alt_test_first_five" class="button button-primary" <?php echo ! $current_api_key ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Test on First 5 Images', 'auto-alt-tags' ); ?>
						</button>

						<button id="ka_alt_start_processing" class="button button-primary" <?php echo ! $current_api_key ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Start Auto-Tagging All Images', 'auto-alt-tags' ); ?>
						</button>

						<button id="ka_alt_test_api" class="button button-secondary" <?php echo ! $current_api_key ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Test API Connection', 'auto-alt-tags' ); ?>
						</button>

						<button id="ka_alt_refresh_stats" class="button button-secondary">
							<?php esc_html_e( 'Refresh Statistics', 'auto-alt-tags' ); ?>
						</button>

						<button id="ka_alt_resume_processing" class="button button-secondary" style="display:none;" <?php echo ! $current_api_key ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Resume Processing', 'auto-alt-tags' ); ?>
						</button>

						<button id="ka_alt_stop_processing" class="button button-secondary" style="display: none;">
							<?php esc_html_e( 'Stop Processing', 'auto-alt-tags' ); ?>
						</button>
					</div>

					<div id="ka_alt_missing_only_notice" style="display:none;margin-top:12px;padding:10px 14px;background:#f0f6fc;border:1px solid #0969da;border-left:4px solid #0969da;border-radius:4px;font-size:13px;"></div>

					<!-- Debug Log Area -->
					<div id="ka_alt_debug_log" style="display: <?php echo $this->debug_mode ? 'block' : 'none'; ?>; margin-top: 20px;">
						<h3><?php esc_html_e( 'Debug Log', 'auto-alt-tags' ); ?></h3>
						<div id="ka_alt_log_content" style="background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">
							<!-- Log messages will appear here -->
						</div>
					</div>
				</div>
			</div>
			</div><!-- end left: Generate Alt Tags -->

			<div><!-- right: Cost & Security -->
			<!-- Cost and Security Information -->
			<div class="card" style="border-left: 4px solid #ffb900;">
				<h2 class="title"><?php esc_html_e( 'Important: Cost & Security Information', 'auto-alt-tags' ); ?></h2>
				<div class="inside">
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
						<div>
							<h3><?php esc_html_e( '💰 API Costs', 'auto-alt-tags' ); ?></h3>
							<ul>
								<li><?php esc_html_e( 'Each image processed uses API credits/tokens', 'auto-alt-tags' ); ?></li>
								<li><?php esc_html_e( 'Costs vary by provider and model selected', 'auto-alt-tags' ); ?></li>
								<li><?php esc_html_e( 'Gemini Flash models are recommended, as they are very cost-effective', 'auto-alt-tags' ); ?></li>
								<li><?php esc_html_e( 'You can try "Test on First 5 Images" to test first before running on a large batch', 'auto-alt-tags' ); ?></li>
								<li><?php esc_html_e( 'Monitor your usage and costs on provider dashboards', 'auto-alt-tags' ); ?></li>
							</ul>
						</div>
						<div>
							<h3><?php esc_html_e( '🔒 Security Best Practices', 'auto-alt-tags' ); ?></h3>
							<ul>
								<li><?php esc_html_e( 'Rotate API keys regularly', 'auto-alt-tags' ); ?></li>
								<li><?php esc_html_e( 'Set spending limits on provider accounts', 'auto-alt-tags' ); ?></li>
								<li><?php esc_html_e( 'Monitor API usage for unexpected activity', 'auto-alt-tags' ); ?></li>
								<li><?php esc_html_e( 'Review generated alt text before publishing', 'auto-alt-tags' ); ?></li>
							</ul>
						</div>
					</div>
					<div style="background: #f0f6fc; border: 1px solid #0969da; padding: 15px; border-radius: 5px;">
						<strong><?php esc_html_e( 'Recommendation:', 'auto-alt-tags' ); ?></strong>
						<?php esc_html_e( 'Start with the "Test on First 5 Images" feature to verify quality and cost before processing all images. This helps you adjust settings and prompts to achieve the best results.', 'auto-alt-tags' ); ?>
					</div>
				</div>
			</div>
			</div><!-- end right: Cost & Security -->
			</div><!-- end top grid -->

			<!-- Test Results Modal -->
			<div id="ka_alt_test_results_modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
				<div style="background-color: #fefefe; margin: 2% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 1000px; border-radius: 5px; max-height: 90%; overflow-y: auto;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
						<h2><?php esc_html_e( 'Test Results - First 5 Images', 'auto-alt-tags' ); ?></h2>
						<span id="ka_alt_close_modal" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
					</div>
					<div id="ka_alt_test_results_content">
						<!-- Results will be populated here -->
					</div>
					<div style="margin-top: 20px; text-align: center;">
						<button id="ka_alt_proceed_with_all" class="button button-primary" style="display: none;">
							<?php esc_html_e( 'Proceed with All Images', 'auto-alt-tags' ); ?>
						</button>
						<button id="ka_alt_close_modal_btn" class="button button-secondary">
							<?php esc_html_e( 'Close', 'auto-alt-tags' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Settings Section (full width below) -->
			<div class="card">
				<h2 class="title"><?php esc_html_e( 'Settings', 'auto-alt-tags' ); ?></h2>
				<div class="inside">
					<form method="post" action="options.php">
						<?php settings_fields( 'auto_alt_tags_settings' ); ?>
						
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="auto_alt_provider"><?php esc_html_e( 'AI Provider', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<select id="auto_alt_provider" name="auto_alt_provider">
										<?php 
										$selected_provider = get_option( 'auto_alt_provider', 'gemini' );
										foreach ( $this->available_providers as $provider_key => $provider_data ) : 
										?>
											<option value="<?php echo esc_attr( $provider_key ); ?>" <?php selected( $selected_provider, $provider_key ); ?>>
												<?php echo esc_html( $provider_data['name'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Choose your AI provider for generating alt text', 'auto-alt-tags' ); ?>
									</p>
								</td>
							</tr>
							
							<?php foreach ( $this->available_providers as $provider_key => $provider_data ) : ?>
							<tr class="ka_alt_provider_setting" data-provider="<?php echo esc_attr( $provider_key ); ?>" style="display: <?php echo $selected_provider === $provider_key ? 'table-row' : 'none'; ?>;">
								<th scope="row">
									<label for="<?php echo esc_attr( $provider_data['api_key_setting'] ); ?>"><?php echo esc_html( $provider_data['name'] ); ?> <?php esc_html_e( 'API Key', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<div style="display: flex; gap: 10px; align-items: center;">
										<input type="password"
											   id="<?php echo esc_attr( $provider_data['api_key_setting'] ); ?>"
											   name="<?php echo esc_attr( $provider_data['api_key_setting'] ); ?>"
											   value="<?php echo esc_attr( get_option( $provider_data['api_key_setting'], '' ) ); ?>"
											   class="regular-text ka_alt_api_key_input"
											   autocomplete="new-password"
											   data-provider="<?php echo esc_attr( $provider_key ); ?>" />
										<button type="button" class="button button-secondary ka_alt_test_api_key" data-provider="<?php echo esc_attr( $provider_key ); ?>">
											<?php esc_html_e( 'Test Key', 'auto-alt-tags' ); ?>
										</button>
										<span class="ka_alt_test_result" id="ka_alt_test_result_<?php echo esc_attr( $provider_key ); ?>"></span>
									</div>
									<?php if ( 'gemini' === $provider_key ) : ?>
										<p class="description">
											<?php
											printf(
												/* translators: %s: URL to Google AI Studio */
												esc_html__( 'Get your API key from %s', 'auto-alt-tags' ),
												'<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google AI Studio', 'auto-alt-tags' ) . '</a>'
											);
											?>
										</p>
									<?php elseif ( 'openai' === $provider_key ) : ?>
										<p class="description">
											<?php
											printf(
												esc_html__( 'Get your API key from %s', 'auto-alt-tags' ),
												'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'OpenAI Platform', 'auto-alt-tags' ) . '</a>'
											);
											?>
										</p>
									<?php elseif ( 'claude' === $provider_key ) : ?>
										<p class="description">
											<?php
											printf(
												esc_html__( 'Get your API key from %s', 'auto-alt-tags' ),
												'<a href="https://console.anthropic.com/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Anthropic Console', 'auto-alt-tags' ) . '</a>'
											);
											?>
										</p>
									<?php elseif ( 'openrouter' === $provider_key ) : ?>
										<p class="description">
											<?php
											printf(
												esc_html__( 'Get your API key from %s', 'auto-alt-tags' ),
												'<a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">' . esc_html__( 'OpenRouter', 'auto-alt-tags' ) . '</a>'
											);
											?>
										</p>
									<?php endif; ?>
									<div id="ka_alt_rate_limit_result_<?php echo esc_attr( $provider_key ); ?>" style="display:none;margin-top:10px;"></div>
								</td>
							</tr>
							<?php endforeach; ?>
							
							<tr>
								<th scope="row">
									<label for="auto_alt_model_name"><?php esc_html_e( 'AI Model', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<select id="auto_alt_model_name" name="auto_alt_model_name">
										<?php 
										$current_models = $this->get_available_models();
										foreach ( $current_models as $model => $description ) : 
										?>
											<option value="<?php echo esc_attr( $model ); ?>" <?php selected( get_option( 'auto_alt_model_name', 'gemini-2.5-flash' ), $model ); ?>>
												<?php echo esc_html( $description ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">
										<?php esc_html_e( 'Choose the AI model to use for generating alt text', 'auto-alt-tags' ); ?>
									</p>
								</td>
							</tr>

							<?php
							$all_rate_limits             = $this->get_model_rate_limits();
							$current_model               = get_option( 'auto_alt_model_name', 'gemini-2.5-flash' );
							$current_limits              = $all_rate_limits[ $current_model ] ?? null;
							$current_provider_for_limits = get_option( 'auto_alt_provider', 'gemini' );
							if ( 'gemini' === $current_provider_for_limits ) :
							?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Rate Limits', 'auto-alt-tags' ); ?></th>
								<td>
									<div style="background:#fff8e1;border:1px solid #ffcc02;border-left:4px solid #ffb900;padding:12px 15px;border-radius:4px;font-size:13px;">
										<strong><?php esc_html_e( 'Google AI Studio — Free Tier Limits', 'auto-alt-tags' ); ?></strong>
										<table style="margin-top:8px;border-collapse:collapse;width:100%;max-width:500px;">
											<thead><tr style="background:rgba(0,0,0,0.05);">
												<th style="text-align:left;padding:4px 8px;"><?php esc_html_e( 'Model', 'auto-alt-tags' ); ?></th>
												<th style="text-align:center;padding:4px 8px;">RPM</th>
												<th style="text-align:center;padding:4px 8px;">RPD</th>
												<th style="text-align:center;padding:4px 8px;">TPM</th>
											</tr></thead>
											<tbody>
												<?php foreach ( $all_rate_limits as $m_key => $lim ) : ?>
												<tr <?php echo ( $m_key === $current_model ) ? 'style="font-weight:bold;background:rgba(255,185,0,0.12);"' : ''; ?>>>
													<td style="padding:4px 8px;"><?php echo esc_html( $this->available_providers['gemini']['models'][ $m_key ] ?? $m_key ); ?></td>
													<td style="text-align:center;padding:4px 8px;"><?php echo esc_html( $lim['rpm'] ); ?></td>
													<td style="text-align:center;padding:4px 8px;"><?php echo esc_html( number_format( $lim['rpd'] ) ); ?></td>
													<td style="text-align:center;padding:4px 8px;"><?php echo esc_html( number_format( $lim['tpm'] ) ); ?></td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
										<p style="margin:8px 0 0;color:#555;">
											<?php printf(
												/* translators: %s: link to Google rate limits page */
												esc_html__( 'Batch size and inter-call delays are automatically capped to stay within these limits. %s', 'auto-alt-tags' ),
												'<a href="https://ai.google.dev/gemini-api/docs/rate-limits" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View live limits →', 'auto-alt-tags' ) . '</a>'
											); ?>
										</p>
									</div>
								</td>
							</tr>
							<?php endif; ?>

							<tr>
								<th scope="row">
									<label for="auto_alt_batch_size"><?php esc_html_e( 'Batch Size', 'auto-alt-tags' ); ?></label>
								</th>
								<td>
									<?php
									$max_batch   = ( 'gemini' === $current_provider_for_limits && $current_limits ) ? $current_limits['max_batch'] : 50;
									$saved_batch = (int) get_option( 'auto_alt_batch_size', 5 );
									$safe_batch  = min( $saved_batch, $max_batch );
									?>
									<input type="number"
									   id="auto_alt_batch_size"
									   name="auto_alt_batch_size"
									   value="<?php echo esc_attr( $safe_batch ); ?>"
									   min="1"
									   max="<?php echo esc_attr( $max_batch ); ?>"
									   class="small-text" />
									<p class="description">
										<?php
										if ( 'gemini' === $current_provider_for_limits && $current_limits ) {
											printf(
												/* translators: %1$d: max batch, %2$d: RPM */
												esc_html__( 'Max %1$d images per batch for this model (%2$d RPM free-tier limit). A delay is applied between each API call to respect the rate limit.', 'auto-alt-tags' ),
												$max_batch,
												$current_limits['rpm']
											);
										} else {
											esc_html_e( 'Number of images to process in each batch (1-50)', 'auto-alt-tags' );
										}
										?>
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
										<?php esc_html_e( 'Override the default prompt for generating alt text. Leave empty to use the default prompt. The prompt should instruct the AI to return only the alt text, avoid subjective descriptions of people, and keep it under 125 characters.', 'auto-alt-tags' ); ?>
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
			</div><!-- end Settings card -->
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
		
		// Get provider from request or use current setting
		$provider = sanitize_text_field( $_POST['provider'] ?? get_option( 'auto_alt_provider', 'gemini' ) );
		$api_key = sanitize_text_field( $_POST['api_key'] ?? $this->get_current_api_key( $provider ) );
		
		if ( empty( $api_key ) ) {
			$provider_name = $this->available_providers[ $provider ]['name'] ?? 'Selected';
			wp_send_json_error( sprintf( __( '%s API key not provided', 'auto-alt-tags' ), $provider_name ) );
		}
		
		$this->debug_log( sprintf( 'Testing %s API connection...', $provider ) );
		
		// Test API connection based on provider
		$result = $this->test_api_connection_for_provider( $provider, $api_key );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
	
	/**
	 * AJAX handler for testing individual provider API keys
	 */
	public function ajax_test_provider_key(): void {
		// Verify nonce for security
		if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
		}
		
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'auto-alt-tags' ) );
		}
		
		$provider = sanitize_text_field( $_POST['provider'] ?? '' );
		$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
		
		if ( empty( $provider ) || empty( $api_key ) ) {
			wp_send_json_error( __( 'Provider and API key are required', 'auto-alt-tags' ) );
		}
		
		$this->debug_log( sprintf( 'Testing %s API key...', $provider ) );
		
		// Test API connection
		$result = $this->test_api_connection_for_provider( $provider, $api_key );
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result['message'] );
		}
	}
	
	/**
	 * Test API connection for a specific provider
	 *
	 * @param string $provider Provider name.
	 * @param string $api_key API key.
	 * @return array Result array
	 */
	private function test_api_connection_for_provider( string $provider, string $api_key ): array {
		switch ( $provider ) {
			case 'gemini':
				return $this->test_gemini_connection( $api_key );
			case 'openai':
				return $this->test_openai_connection( $api_key );
			case 'claude':
				return $this->test_claude_connection( $api_key );
			case 'openrouter':
				return $this->test_openrouter_connection( $api_key );
			default:
				return array(
					'success' => false,
					'message' => __( 'Unsupported provider', 'auto-alt-tags' ),
				);
		}
	}
	
	/**
	 * Test Gemini API connection
	 *
	 * @param string $api_key API key.
	 * @return array Result array
	 */
	private function test_gemini_connection( string $api_key ): array {
		$model = get_option( 'auto_alt_model_name', 'gemini-2.5-flash' );
		$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
		
		$payload = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => 'Respond with exactly these 5 words: "Hello, API is working correctly"',
						),
					),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens' => 10,
				'temperature'     => 0,
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
			return array(
				'success' => false,
				'message' => sprintf( __( 'Connection failed: %s', 'auto-alt-tags' ), $response->get_error_message() ),
			);
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'auto-alt-tags' );
			return array(
				'success' => false,
				'message' => sprintf( __( 'API Error (%d): %s', 'auto-alt-tags' ), $response_code, $error_msg ),
			);
		}
		
		if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'API connection successful!', 'auto-alt-tags' ),
				'response' => $data['candidates'][0]['content']['parts'][0]['text'],
			);
		}
		
		return array(
			'success' => false,
			'message' => __( 'Unexpected API response format', 'auto-alt-tags' ),
		);
	}
	
	/**
	 * Test OpenAI API connection
	 *
	 * @param string $api_key API key.
	 * @return array Result array
	 */
	private function test_openai_connection( string $api_key ): array {
		$payload = array(
			'model' => 'gpt-3.5-turbo',
			'messages' => array(
				array(
					'role' => 'user',
					'content' => 'Respond with exactly these 5 words: "Hello, API is working correctly"',
				),
			),
			'max_tokens' => 10,
		);
		
		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Connection failed: %s', 'auto-alt-tags' ), $response->get_error_message() ),
			);
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'auto-alt-tags' );
			return array(
				'success' => false,
				'message' => sprintf( __( 'API Error (%d): %s', 'auto-alt-tags' ), $response_code, $error_msg ),
			);
		}
		
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'API connection successful!', 'auto-alt-tags' ),
				'response' => $data['choices'][0]['message']['content'],
			);
		}
		
		return array(
			'success' => false,
			'message' => __( 'Unexpected API response format', 'auto-alt-tags' ),
		);
	}
	
	/**
	 * Test Claude API connection
	 *
	 * @param string $api_key API key.
	 * @return array Result array
	 */
	private function test_claude_connection( string $api_key ): array {
		$payload = array(
			'model' => 'claude-3-haiku-20240307',
			'max_tokens' => 10,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => 'Respond with exactly these 5 words: "Hello, API is working correctly"',
				),
			),
		);
		
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'x-api-key' => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Connection failed: %s', 'auto-alt-tags' ), $response->get_error_message() ),
			);
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'auto-alt-tags' );
			return array(
				'success' => false,
				'message' => sprintf( __( 'API Error (%d): %s', 'auto-alt-tags' ), $response_code, $error_msg ),
			);
		}
		
		if ( isset( $data['content'][0]['text'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'API connection successful!', 'auto-alt-tags' ),
				'response' => $data['content'][0]['text'],
			);
		}
		
		return array(
			'success' => false,
			'message' => __( 'Unexpected API response format', 'auto-alt-tags' ),
		);
	}
	
	/**
	 * Test OpenRouter API connection
	 *
	 * @param string $api_key API key.
	 * @return array Result array
	 */
	private function test_openrouter_connection( string $api_key ): array {
		$payload = array(
			'model' => 'anthropic/claude-3.5-sonnet',
			'messages' => array(
				array(
					'role' => 'user',
					'content' => 'Respond with exactly these 5 words: "Hello, API is working correctly"',
				),
			),
			'max_tokens' => 10,
		);
		
		$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Connection failed: %s', 'auto-alt-tags' ), $response->get_error_message() ),
			);
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'auto-alt-tags' );
			return array(
				'success' => false,
				'message' => sprintf( __( 'API Error (%d): %s', 'auto-alt-tags' ), $response_code, $error_msg ),
			);
		}
		
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'API connection successful!', 'auto-alt-tags' ),
				'response' => $data['choices'][0]['message']['content'],
			);
		}
		
		return array(
			'success' => false,
			'message' => __( 'Unexpected API response format', 'auto-alt-tags' ),
		);
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
		
		// Check API key for current provider
		$current_provider = get_option( 'auto_alt_provider', 'gemini' );
		$api_key = $this->get_current_api_key( $current_provider );
		
		if ( empty( $api_key ) ) {
			$provider_name = $this->available_providers[ $current_provider ]['name'] ?? 'Selected';
			wp_send_json_error( sprintf( __( '%s API key not configured', 'auto-alt-tags' ), $provider_name ) );
		}
		
		// Rate limiting
		$user_id = get_current_user_id();
		$rate_limit_key = 'auto_alt_rate_limit_' . $user_id;
		$attempts = get_transient( $rate_limit_key ) ?: 0;
		
		if ( $attempts > 30 ) { // 30 requests per hour
			wp_send_json_error( __( 'Rate limit exceeded. Please try again later.', 'auto-alt-tags' ) );
		}
		
		set_transient( $rate_limit_key, $attempts + 1, HOUR_IN_SECONDS );
		
		$this->debug_log( 'Starting alt tag processing...' );

		// Always query fresh — only images that still need alt text are returned.
		// This means failed images from a previous batch are naturally retried.
		$images_without_alt = $this->get_images_without_alt();
		$total_remaining    = count( $images_without_alt );

		$this->debug_log( sprintf( 'Found %d images without alt text', $total_remaining ) );

		// On first call of a session, store the initial total for accurate progress.
		$session_total = (int) ( get_transient( 'auto_alt_session_total' ) ?: 0 );
		if ( ! $session_total ) {
			$session_total = $total_remaining;
			set_transient( 'auto_alt_session_total', $session_total, 2 * HOUR_IN_SECONDS );
		}

		if ( 0 === $total_remaining ) {
			$cumulative_success = get_transient( 'auto_alt_success_count' ) ?: 0;
			delete_transient( 'auto_alt_session_total' );
			delete_transient( 'auto_alt_success_count' );
			$this->debug_log( 'Processing complete!' );
			wp_send_json_success( array(
				'completed' => true,
				'message'   => sprintf(
					/* translators: %d: Total success count */
					__( 'All images processed. %d alt tags generated successfully.', 'auto-alt-tags' ),
					(int) $cumulative_success
				),
				'progress'  => 100,
			) );
		}

		// Get and validate batch size — cap to model rate limit for Gemini
		$batch_size = (int) get_option( 'auto_alt_batch_size', $this->batch_size );
		$model_limits = array();
		if ( 'gemini' === $current_provider ) {
			$model_name   = get_option( 'auto_alt_model_name', 'gemini-2.5-flash' );
			$model_limits = $this->get_model_rate_limits()[ $model_name ] ?? array();
		}
		$hard_max   = ! empty( $model_limits ) ? $model_limits['max_batch'] : 50;
		$batch_size = max( 1, min( $hard_max, $batch_size ) );

		// Get cumulative success count
		$cumulative_success = get_transient( 'auto_alt_success_count' ) ?: 0;

		// Always start from index 0 — successful images drop off the list between calls,
		// so failed images are never skipped.
		$batch = array_slice( $images_without_alt, 0, $batch_size );
		
		// Process current batch
		$batch_processed = 0;
		$batch_success = 0;
		$errors = array();
		$inter_call_sleep = ! empty( $model_limits ) ? $model_limits['sleep'] : 0;

		foreach ( $batch as $idx => $attachment_id ) {
			$this->debug_log( sprintf( 'Processing image ID: %d', $attachment_id ) );

			// Retry on failure up to 2 additional attempts
			$max_retries = 2;
			$attempt = 0;
			$result = null;
			do {
				if ( $attempt > 0 ) {
					sleep( 1 );
					$this->debug_log( sprintf( 'Retrying image ID: %d (attempt %d)', $attachment_id, $attempt + 1 ) );
				}
				$result = $this->generate_alt_tag( (int) $attachment_id );
				$attempt++;
			} while ( ! $result['success'] && $attempt <= $max_retries );

			// Rate-limit sleep between calls (skip after last image in batch)
			if ( $inter_call_sleep > 0 && $idx < count( $batch ) - 1 ) {
				$this->debug_log( sprintf( 'Rate limit pause: %ds between calls', $inter_call_sleep ) );
				sleep( $inter_call_sleep );
			}

			$batch_processed++;
			if ( $result['success'] ) {
				$batch_success++;
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
		
		// Update cumulative success count
		$cumulative_success += $batch_success;
		set_transient( 'auto_alt_success_count', $cumulative_success, HOUR_IN_SECONDS );

		// Progress: images processed this session = session_total minus what's still remaining.
		// Estimate remaining after this batch (successful ones will disappear from next query).
		$estimated_remaining = $total_remaining - $batch_success;
		$processed_so_far    = $session_total - $estimated_remaining;
		$progress = $session_total > 0 ? min( 100, ( $processed_so_far / $session_total ) * 100 ) : 100;

		wp_send_json_success( array(
			'completed' => ( 0 === $estimated_remaining ),
			'message'   => sprintf(
				/* translators: %1$d: Processed count, %2$d: Total count, %3$d: Success count */
				__( 'Processed %1$d/%2$d images. %3$d successful.', 'auto-alt-tags' ),
				max( 0, $processed_so_far ),
				$session_total,
				$cumulative_success
			),
			'progress'  => round( $progress, 1 ),
			'errors'    => $errors,
			'batch_success' => $batch_success,
			'total_success' => $cumulative_success,
		) );
	}
	
	/**
	 * AJAX handler for checking whether an incomplete processing session exists.
	 * Used to show/hide the Resume button on page load.
	 */
	public function ajax_check_session(): void {
		if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'auto-alt-tags' ) );
		}

		$session_total = (int) ( get_transient( 'auto_alt_session_total' ) ?: 0 );
		$remaining     = count( $this->get_images_without_alt() );

		if ( $session_total > 0 && $remaining > 0 ) {
			wp_send_json_success( array(
				'has_session'   => true,
				'session_total' => $session_total,
				'remaining'     => $remaining,
				'processed'     => $session_total - $remaining,
			) );
		} else {
			// Clean up stale transients if everything is already done.
			if ( 0 === $remaining ) {
				delete_transient( 'auto_alt_session_total' );
				delete_transient( 'auto_alt_success_count' );
			}
			wp_send_json_success( array( 'has_session' => false ) );
		}
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
			
			// Security: Ensure file path is within uploads directory
			$image_path = realpath( $image_path );
			$basedir = realpath( $upload_dir['basedir'] );
			if ( strpos( $image_path, $basedir ) !== 0 ) {
				return array(
					'success' => false,
					'error'   => __( 'Invalid image path', 'auto-alt-tags' ),
				);
			}
			
			// Call AI API
			$alt_text = $this->call_ai_api( $image_path );
			
			if ( ! $alt_text ) {
				return array(
					'success' => false,
					'error'   => __( 'API request failed', 'auto-alt-tags' ),
				);
			}
			
			// Clean up the alt text - remove any leading/trailing quotes or asterisks
			$alt_text = trim( $alt_text, '"\'*' );
			$alt_text = preg_replace( '/^\*+|\*+$/', '', $alt_text );
			
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
	 * Call AI API to generate alt text (supports multiple providers)
	 *
	 * @param string $image_path Path to image file.
	 * @return string|false Generated alt text or false on failure
	 */
	private function call_ai_api( string $image_path ) {
		$provider = get_option( 'auto_alt_provider', 'gemini' );
		
		switch ( $provider ) {
			case 'openai':
				return $this->call_openai_api( $image_path );
			case 'claude':
				return $this->call_claude_api( $image_path );
			case 'openrouter':
				return $this->call_openrouter_api( $image_path );
			case 'gemini':
			default:
				return $this->call_gemini_api( $image_path );
		}
	}
	
	/**
	 * Call Gemini API to generate alt text
	 *
	 * @param string $image_path Path to image file.
	 * @return string|false Generated alt text or false on failure
	 */
	private function call_gemini_api( string $image_path ) {
		$api_key = $this->get_current_api_key( 'gemini' );
		if ( empty( $api_key ) ) {
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
		
		// Use the selected model and validate it
		$model = get_option( 'auto_alt_model_name', 'gemini-2.5-flash' );
		$available_models = $this->available_providers['gemini']['models'];
		if ( ! array_key_exists( $model, $available_models ) ) {
			$model = 'gemini-2.5-flash'; // Fallback to default if invalid
			$this->debug_log( 'Invalid model name detected, using default: gemini-2.5-flash' );
		}
		$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
		
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
				'temperature'     => 0.1,
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
	 * Call OpenAI API to generate alt text
	 *
	 * @param string $image_path Path to image file.
	 * @return string|false Generated alt text or false on failure
	 */
	private function call_openai_api( string $image_path ) {
		$api_key = $this->get_current_api_key( 'openai' );
		if ( empty( $api_key ) ) {
			$this->debug_log( 'OpenAI API key not configured' );
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
		$model = get_option( 'auto_alt_model_name', 'gpt-4o' );
		
		$this->debug_log( sprintf( 'Calling OpenAI API with model: %s', $model ) );
		
		$payload = array(
			'model' => $model,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $prompt,
						),
						array(
							'type' => 'image_url',
							'image_url' => array(
								'url' => 'data:' . $mime_type . ';base64,' . $base64_image,
							),
						),
					),
				),
			),
			'max_tokens' => 50,
		);
		
		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'OpenAI API request failed: ' . $response->get_error_message() );
			return false;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		$this->debug_log( sprintf( 'OpenAI API response code: %d', $response_code ) );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			$this->debug_log( sprintf( 'OpenAI API error: %s', $error_msg ) );
			return false;
		}
		
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$alt_text = trim( $data['choices'][0]['message']['content'] );
			$this->debug_log( sprintf( 'Generated alt text: %s', $alt_text ) );
			return $alt_text;
		}
		
		$this->debug_log( 'Unexpected OpenAI API response format: ' . $body );
		return false;
	}
	
	/**
	 * Call Claude API to generate alt text
	 *
	 * @param string $image_path Path to image file.
	 * @return string|false Generated alt text or false on failure
	 */
	private function call_claude_api( string $image_path ) {
		$api_key = $this->get_current_api_key( 'claude' );
		if ( empty( $api_key ) ) {
			$this->debug_log( 'Claude API key not configured' );
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
		$model = get_option( 'auto_alt_model_name', 'claude-3-5-sonnet-20241022' );
		
		$this->debug_log( sprintf( 'Calling Claude API with model: %s', $model ) );
		
		$payload = array(
			'model' => $model,
			'max_tokens' => 50,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => array(
						array(
							'type' => 'image',
							'source' => array(
								'type' => 'base64',
								'media_type' => $mime_type,
								'data' => $base64_image,
							),
						),
						array(
							'type' => 'text',
							'text' => $prompt,
						),
					),
				),
			),
		);
		
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'x-api-key' => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'Claude API request failed: ' . $response->get_error_message() );
			return false;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		$this->debug_log( sprintf( 'Claude API response code: %d', $response_code ) );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			$this->debug_log( sprintf( 'Claude API error: %s', $error_msg ) );
			return false;
		}
		
		if ( isset( $data['content'][0]['text'] ) ) {
			$alt_text = trim( $data['content'][0]['text'] );
			$this->debug_log( sprintf( 'Generated alt text: %s', $alt_text ) );
			return $alt_text;
		}
		
		$this->debug_log( 'Unexpected Claude API response format: ' . $body );
		return false;
	}
	
	/**
	 * Call OpenRouter API to generate alt text
	 *
	 * @param string $image_path Path to image file.
	 * @return string|false Generated alt text or false on failure
	 */
	private function call_openrouter_api( string $image_path ) {
		$api_key = $this->get_current_api_key( 'openrouter' );
		if ( empty( $api_key ) ) {
			$this->debug_log( 'OpenRouter API key not configured' );
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
		$model = get_option( 'auto_alt_model_name', 'anthropic/claude-3.5-sonnet' );
		
		$this->debug_log( sprintf( 'Calling OpenRouter API with model: %s', $model ) );
		
		$payload = array(
			'model' => $model,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $prompt,
						),
						array(
							'type' => 'image_url',
							'image_url' => array(
								'url' => 'data:' . $mime_type . ';base64,' . $base64_image,
							),
						),
					),
				),
			),
			'max_tokens' => 50,
		);
		
		$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->debug_log( 'OpenRouter API request failed: ' . $response->get_error_message() );
			return false;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		$this->debug_log( sprintf( 'OpenRouter API response code: %d', $response_code ) );
		
		if ( 200 !== $response_code ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown error';
			$this->debug_log( sprintf( 'OpenRouter API error: %s', $error_msg ) );
			return false;
		}
		
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$alt_text = trim( $data['choices'][0]['message']['content'] );
			$this->debug_log( sprintf( 'Generated alt text: %s', $alt_text ) );
			return $alt_text;
		}
		
		$this->debug_log( 'Unexpected OpenRouter API response format: ' . $body );
		return false;
	}
	
	/**
	 * AJAX handler for testing first 5 images
	 */
	public function ajax_test_first_five(): void {
		// Verify nonce for security
		if ( ! check_ajax_referer( 'auto_alt_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed', 'auto-alt-tags' ) );
		}
		
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized access', 'auto-alt-tags' ) );
		}
		
		// Check API key for current provider
		$current_provider = get_option( 'auto_alt_provider', 'gemini' );
		$api_key = $this->get_current_api_key( $current_provider );
		
		if ( empty( $api_key ) ) {
			$provider_name = $this->available_providers[ $current_provider ]['name'] ?? 'Selected';
			wp_send_json_error( sprintf( __( '%s API key not configured', 'auto-alt-tags' ), $provider_name ) );
		}
		
		$this->debug_log( 'Testing first 5 images...' );
		
		// Get first 5 images without alt text
		$images_without_alt = $this->get_images_without_alt();
		$test_images = array_slice( $images_without_alt, 0, 5 );
		
		if ( empty( $test_images ) ) {
			wp_send_json_success( array(
				'message' => __( 'No images need alt tags', 'auto-alt-tags' ),
				'results' => array(),
			) );
		}
		
		$results = array();
		$successful = 0;
		$errors = array();

		// Determine inter-call sleep based on model rate limits
		$test_model_limits    = array();
		if ( 'gemini' === $current_provider ) {
			$test_model_name   = get_option( 'auto_alt_model_name', 'gemini-2.5-flash' );
			$test_model_limits = $this->get_model_rate_limits()[ $test_model_name ] ?? array();
		}
		$test_inter_call_sleep = ! empty( $test_model_limits ) ? $test_model_limits['sleep'] : 0;

		foreach ( $test_images as $test_idx => $attachment_id ) {
			$this->debug_log( sprintf( 'Testing image ID: %d', $attachment_id ) );

			// Get image info
			$image_title = get_the_title( $attachment_id );
			$image_url = wp_get_attachment_url( $attachment_id );
			$thumbnail_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

			// Generate alt text with retry on failure
			$max_retries = 2;
			$attempt = 0;
			$result = null;
			do {
				if ( $attempt > 0 ) {
					sleep( 1 );
					$this->debug_log( sprintf( 'Retrying image ID: %d (attempt %d)', $attachment_id, $attempt + 1 ) );
				}
				$result = $this->generate_alt_tag_preview( (int) $attachment_id );
				$attempt++;
			} while ( ! $result['success'] && $attempt <= $max_retries );

			// Rate-limit sleep between calls (skip after last image)
			if ( $test_inter_call_sleep > 0 && $test_idx < count( $test_images ) - 1 ) {
				$this->debug_log( sprintf( 'Rate limit pause: %ds between calls', $test_inter_call_sleep ) );
				sleep( $test_inter_call_sleep );
			}
			
			$image_result = array(
				'id' => $attachment_id,
				'title' => $image_title,
				'url' => $image_url,
				'thumbnail' => $thumbnail_url,
				'success' => $result['success'],
			);
			
			if ( $result['success'] ) {
				$image_result['alt_text'] = $result['alt_text'];
				$successful++;
				$this->debug_log( sprintf( 'Success: %s', $result['alt_text'] ) );
			} else {
				$image_result['error'] = $result['error'];
				$errors[] = sprintf(
					/* translators: %1$s: Image title, %2$s: Error message */
					__( '%1$s: %2$s', 'auto-alt-tags' ),
					$image_title,
					$result['error']
				);
				$this->debug_log( sprintf( 'Error: %s', $result['error'] ) );
			}
			
			$results[] = $image_result;
		}
		
		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: %1$d: Successful count, %2$d: Total count */
				__( 'Test completed: %1$d of %2$d images processed successfully', 'auto-alt-tags' ),
				$successful,
				count( $test_images )
			),
			'results' => $results,
			'errors' => $errors,
			'provider' => $this->available_providers[ $current_provider ]['name'] ?? $current_provider,
			'model' => get_option( 'auto_alt_model_name', 'gemini-2.5-flash' ),
		) );
	}
	
	/**
	 * Generate alt tag for preview (without saving to database)
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Result array with success boolean and alt_text or error
	 */
	private function generate_alt_tag_preview( int $attachment_id ): array {
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
			
			// Security: Ensure file path is within uploads directory
			$image_path = realpath( $image_path );
			$basedir = realpath( $upload_dir['basedir'] );
			if ( strpos( $image_path, $basedir ) !== 0 ) {
				return array(
					'success' => false,
					'error'   => __( 'Invalid image path', 'auto-alt-tags' ),
				);
			}
			
			// Call AI API (but don't save to database)
			$alt_text = $this->call_ai_api( $image_path );
			
			if ( ! $alt_text ) {
				return array(
					'success' => false,
					'error'   => __( 'API request failed', 'auto-alt-tags' ),
				);
			}
			
			// Clean up the alt text - remove any leading/trailing quotes or asterisks
			$alt_text = trim( $alt_text, '"\'*' );
			$alt_text = preg_replace( '/^\*+|\*+$/', '', $alt_text );
			
			// Return without saving to database
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
	add_option( 'auto_alt_provider', 'gemini' );
	add_option( 'auto_alt_batch_size', 10 );
	add_option( 'auto_alt_image_size', 'medium' );
	add_option( 'auto_alt_model_name', 'gemini-2.5-flash' );
	add_option( 'auto_alt_debug_mode', false );
	add_option( 'auto_alt_custom_prompt', '' );
	add_option( 'auto_alt_gemini_api_key', '' );
	add_option( 'auto_alt_openai_api_key', '' );
	add_option( 'auto_alt_claude_api_key', '' );
	add_option( 'auto_alt_openrouter_api_key', '' );
} );

// Deactivation hook
register_deactivation_hook( __FILE__, function () {
	// Clean up transients
	delete_transient( 'auto_alt_offset' );
	delete_transient( 'auto_alt_success_count' );
	delete_transient( 'auto_alt_debug_logs' );
	
	// Clean up rate limit transients for all users
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_auto_alt_rate_limit_%'" );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_auto_alt_rate_limit_%'" );
} );
