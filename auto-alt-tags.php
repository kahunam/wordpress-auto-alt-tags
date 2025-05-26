<?php
/**
 * Plugin Name: Auto Alt Tag Generator
 * Plugin URI: https://github.com/kahunam/wordpress-auto-alt-tags
 * Description: Automatically generates alt tags for images using Google's Gemini Flash 2.5 API. Includes batch processing, cost optimization, and WP-CLI support.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/kahunam
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-alt-tags
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 *
 * @package AutoAltTags
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AUTO_ALT_TAGS_VERSION', '1.0.0');
define('AUTO_ALT_TAGS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTO_ALT_TAGS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTO_ALT_TAGS_PLUGIN_FILE', __FILE__);

class AutoAltTagGenerator {
    
    private $gemini_api_key;
    private $batch_size = 10; // Process 10 images at a time
    private $max_image_size = 512; // Resize images to max 512px to save API costs
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_process_alt_tags', array($this, 'ajax_process_alt_tags'));
        add_action('wp_ajax_get_progress', array($this, 'ajax_get_progress'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Get API key from wp_options or wp-config.php
        $this->gemini_api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : get_option('auto_alt_gemini_api_key');
        
        // Load WP-CLI command if available
        if (defined('WP_CLI') && WP_CLI) {
            require_once AUTO_ALT_TAGS_PLUGIN_DIR . 'includes/class-wp-cli-command.php';
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'media_page_auto-alt-tags') {
            return;
        }
        
        wp_enqueue_script(
            'auto-alt-tags-admin',
            AUTO_ALT_TAGS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AUTO_ALT_TAGS_VERSION,
            true
        );
        
        wp_localize_script('auto-alt-tags-admin', 'autoAltAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('auto_alt_nonce')
        ));
        
        wp_enqueue_style(
            'auto-alt-tags-admin',
            AUTO_ALT_TAGS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AUTO_ALT_TAGS_VERSION
        );
    }
    
    public function register_settings() {
        register_setting('auto_alt_tags_settings', 'auto_alt_gemini_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        register_setting('auto_alt_tags_settings', 'auto_alt_batch_size', array(
            'sanitize_callback' => 'absint',
            'default' => 10
        ));
        
        register_setting('auto_alt_tags_settings', 'auto_alt_max_image_size', array(
            'sanitize_callback' => 'absint',
            'default' => 512
        ));
    }
    
    public function add_admin_menu() {
        add_media_page(
            __('Auto Alt Tags', 'auto-alt-tags'),
            __('Auto Alt Tags', 'auto-alt-tags'), 
            'manage_options',
            'auto-alt-tags',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        // Get statistics
        $stats = $this->get_image_statistics();
        
        ?>
        <div class="wrap auto-alt-tags-admin">
            <h1><?php _e('Auto Alt Tag Generator', 'auto-alt-tags'); ?></h1>
            
            <div class="auto-alt-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['total']); ?></h3>
                        <p><?php _e('Total Images', 'auto-alt-tags'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['with_alt']); ?></h3>
                        <p><?php _e('With Alt Tags', 'auto-alt-tags'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo number_format($stats['without_alt']); ?></h3>
                        <p><?php _e('Need Alt Tags', 'auto-alt-tags'); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $stats['percentage']; ?>%</h3>
                        <p><?php _e('Coverage', 'auto-alt-tags'); ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (!$this->gemini_api_key): ?>
                <div class="notice notice-warning">
                    <p><?php _e('Please configure your Gemini API key in the settings below before using the auto-tagging feature.', 'auto-alt-tags'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="auto-alt-controls">
                <div class="control-section">
                    <h2><?php _e('Generate Alt Tags', 'auto-alt-tags'); ?></h2>
                    
                    <div id="alt-tag-progress" style="display: none;">
                        <div class="progress-container">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%;"></div>
                            </div>
                            <div class="progress-info">
                                <span id="progress-text"><?php _e('Processing...', 'auto-alt-tags'); ?></span>
                                <span id="progress-percentage">0%</span>
                            </div>
                        </div>
                        <button id="stop-processing" class="button button-secondary">
                            <?php _e('Stop Processing', 'auto-alt-tags'); ?>
                        </button>
                    </div>
                    
                    <div id="control-buttons">
                        <button id="start-processing" class="button button-primary" <?php echo !$this->gemini_api_key ? 'disabled' : ''; ?>>
                            <?php _e('Start Auto-Tagging Images', 'auto-alt-tags'); ?>
                        </button>
                        
                        <button id="refresh-stats" class="button button-secondary">
                            <?php _e('Refresh Statistics', 'auto-alt-tags'); ?>
                        </button>
                    </div>
                    
                    <div id="processing-log"></div>
                </div>
                
                <div class="settings-section">
                    <h2><?php _e('Settings', 'auto-alt-tags'); ?></h2>
                    
                    <form method="post" action="options.php">
                        <?php settings_fields('auto_alt_tags_settings'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="auto_alt_gemini_api_key"><?php _e('Gemini API Key', 'auto-alt-tags'); ?></label>
                                </th>
                                <td>
                                    <input type="password" 
                                           id="auto_alt_gemini_api_key" 
                                           name="auto_alt_gemini_api_key" 
                                           value="<?php echo esc_attr(get_option('auto_alt_gemini_api_key', '')); ?>" 
                                           class="regular-text" 
                                           autocomplete="new-password" />
                                    <p class="description">
                                        <?php printf(
                                            __('Get your API key from <a href="%s" target="_blank">Google AI Studio</a>', 'auto-alt-tags'),
                                            'https://ai.google.dev/'
                                        ); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="auto_alt_batch_size"><?php _e('Batch Size', 'auto-alt-tags'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="auto_alt_batch_size" 
                                           name="auto_alt_batch_size" 
                                           value="<?php echo esc_attr(get_option('auto_alt_batch_size', 10)); ?>" 
                                           min="1" 
                                           max="50" 
                                           class="small-text" />
                                    <p class="description">
                                        <?php _e('Number of images to process in each batch (1-50)', 'auto-alt-tags'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row">
                                    <label for="auto_alt_max_image_size"><?php _e('Max Image Size (px)', 'auto-alt-tags'); ?></label>
                                </th>
                                <td>
                                    <input type="number" 
                                           id="auto_alt_max_image_size" 
                                           name="auto_alt_max_image_size" 
                                           value="<?php echo esc_attr(get_option('auto_alt_max_image_size', 512)); ?>" 
                                           min="256" 
                                           max="2048" 
                                           step="256" 
                                           class="small-text" />
                                    <p class="description">
                                        <?php _e('Maximum image size sent to API (smaller = lower costs)', 'auto-alt-tags'); ?>
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
    
    public function ajax_process_alt_tags() {
        check_ajax_referer('auto_alt_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'auto-alt-tags'));
        }
        
        if (!$this->gemini_api_key) {
            wp_send_json_error(__('Gemini API key not configured', 'auto-alt-tags'));
        }
        
        // Get images without alt text
        $images_without_alt = $this->get_images_without_alt();
        $total_images = count($images_without_alt);
        
        if ($total_images == 0) {
            wp_send_json_success(array(
                'completed' => true,
                'message' => __('No images need alt tags', 'auto-alt-tags'),
                'progress' => 100
            ));
        }
        
        // Get current batch offset
        $current_offset = get_transient('auto_alt_offset') ?: 0;
        $batch_size = get_option('auto_alt_batch_size', $this->batch_size);
        $batch = array_slice($images_without_alt, $current_offset, $batch_size);
        
        if (empty($batch)) {
            // Processing complete
            delete_transient('auto_alt_offset');
            wp_send_json_success(array(
                'completed' => true,
                'message' => __('All images processed', 'auto-alt-tags'),
                'progress' => 100
            ));
        }
        
        // Process current batch
        $processed = 0;
        $errors = array();
        
        foreach ($batch as $attachment_id) {
            $result = $this->generate_alt_tag($attachment_id);
            if ($result['success']) {
                $processed++;
            } else {
                $errors[] = sprintf(
                    __('Image ID %d: %s', 'auto-alt-tags'), 
                    $attachment_id, 
                    $result['error']
                );
            }
        }
        
        // Update offset
        $new_offset = $current_offset + $batch_size;
        set_transient('auto_alt_offset', $new_offset, HOUR_IN_SECONDS);
        
        // Calculate progress
        $progress = min(100, ($new_offset / $total_images) * 100);
        
        wp_send_json_success(array(
            'completed' => $new_offset >= $total_images,
            'message' => sprintf(
                __('Processed %d/%d images. %d successful.', 'auto-alt-tags'),
                min($new_offset, $total_images),
                $total_images,
                $processed
            ),
            'progress' => round($progress, 1),
            'errors' => $errors
        ));
    }
    
    private function get_image_statistics() {
        global $wpdb;
        
        // Total images
        $total_images = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type LIKE 'image/%'
        ");
        
        // Images with alt tags
        $images_with_alt = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND pm.meta_key = '_wp_attachment_image_alt'
            AND pm.meta_value != ''
        ");
        
        $images_without_alt = $total_images - $images_with_alt;
        $percentage = $total_images > 0 ? round(($images_with_alt / $total_images) * 100, 1) : 0;
        
        return array(
            'total' => (int) $total_images,
            'with_alt' => (int) $images_with_alt,
            'without_alt' => (int) $images_without_alt,
            'percentage' => $percentage
        );
    }
    
    private function get_images_without_alt() {
        global $wpdb;
        
        $query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
            ORDER BY p.ID ASC
        ";
        
        return $wpdb->get_col($query);
    }
    
    private function generate_alt_tag($attachment_id) {
        try {
            // Get image URL and create smaller version
            $resized_image = $this->create_small_image($attachment_id);
            
            if (!$resized_image) {
                return array('success' => false, 'error' => __('Failed to create resized image', 'auto-alt-tags'));
            }
            
            // Call Gemini API
            $alt_text = $this->call_gemini_api($resized_image);
            
            // Clean up temporary file
            if (file_exists($resized_image)) {
                unlink($resized_image);
            }
            
            if (!$alt_text) {
                return array('success' => false, 'error' => __('API request failed', 'auto-alt-tags'));
            }
            
            // Save alt text
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($alt_text));
            
            return array('success' => true, 'alt_text' => $alt_text);
            
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    private function create_small_image($attachment_id) {
        $image_path = get_attached_file($attachment_id);
        
        if (!$image_path || !file_exists($image_path)) {
            return false;
        }
        
        // Load image
        $image_editor = wp_get_image_editor($image_path);
        
        if (is_wp_error($image_editor)) {
            return false;
        }
        
        // Get current dimensions
        $current_size = $image_editor->get_size();
        $max_size = get_option('auto_alt_max_image_size', $this->max_image_size);
        
        // Only resize if larger than max_size
        if ($current_size['width'] > $max_size || $current_size['height'] > $max_size) {
            $image_editor->resize($max_size, $max_size, false);
        }
        
        // Create temporary file
        $temp_file = wp_tempnam('auto-alt-');
        $temp_file .= '.jpg';
        
        // Save resized image
        $saved = $image_editor->save($temp_file, 'image/jpeg');
        
        if (is_wp_error($saved)) {
            return false;
        }
        
        return $temp_file;
    }
    
    private function call_gemini_api($image_path) {
        if (!$this->gemini_api_key) {
            error_log('Auto Alt Tags: Gemini API key not configured');
            return false;
        }
        
        // Convert image to base64
        $image_data = file_get_contents($image_path);
        if ($image_data === false) {
            return false;
        }
        
        $base64_image = base64_encode($image_data);
        
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . $this->gemini_api_key;
        
        $payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array(
                            'text' => 'Generate a concise, descriptive alt text for this image. Focus on the main subject and important details. Keep it under 125 characters and avoid phrases like "image of" or "picture of". Be specific and helpful for screen readers.'
                        ),
                        array(
                            'inline_data' => array(
                                'mime_type' => 'image/jpeg',
                                'data' => $base64_image
                            )
                        )
                    )
                )
            ),
            'generationConfig' => array(
                'maxOutputTokens' => 50,
                'temperature' => 0.3
            )
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('Auto Alt Tags: Gemini API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        }
        
        error_log('Auto Alt Tags: Unexpected Gemini API response: ' . $body);
        return false;
    }
}

// Initialize the plugin
new AutoAltTagGenerator();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create any necessary database tables or options here
    add_option('auto_alt_batch_size', 10);
    add_option('auto_alt_max_image_size', 512);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up transients
    delete_transient('auto_alt_offset');
});
