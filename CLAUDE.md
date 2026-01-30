# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

This WordPress plugin has no build process - it uses pure PHP/JavaScript without compilation steps.

### Testing Commands
```bash
# Test WP-CLI commands (requires WordPress environment)
wp auto-alt stats
wp auto-alt test-api
wp auto-alt generate --dry-run

# Test plugin in WordPress admin at: Media → Auto Alt Tags
```

### WordPress Environment Setup
```bash
# Install in WordPress plugins directory
ln -s /path/to/repo /path/to/wordpress/wp-content/plugins/auto-alt-tags
```

## Architecture Overview

### Core Components

**Main Plugin Class** (`auto-alt-tags.php:37-853`)
- Single-class architecture in `AutoAltTagGenerator`
- Handles WordPress integration, admin UI, AJAX endpoints
- Uses WordPress hooks system for initialization and admin interface

**WP-CLI Integration** (`includes/class-wp-cli-command.php`)
- Separate `Auto_Alt_CLI_Command` class for command-line operations
- Batch processing with progress bars and detailed output
- Commands: `generate`, `stats`, `test-api`

**Admin Interface** (`assets/js/admin.js`)
- jQuery-based AJAX processing with real-time progress updates
- Batch processing with stop/resume capability
- Debug logging and error handling

### API Integration Pattern

**Gemini API Communication**
- Uses `wp_remote_post()` for HTTP requests (WordPress standard)
- Base64 image encoding for API transmission
- Configurable models: gemini-2.5-flash (default), gemini-2.5-flash-lite, gemini-3-flash-preview, gemini-3-pro-preview
- Custom prompt support with smart defaults

**Processing Architecture**
- Batch processing with WordPress transients for state management
- Cost optimization via existing WordPress thumbnail sizes
- Resume capability after interruption using `auto_alt_offset` transient
- Real-time progress tracking via AJAX polling

### Data Storage

- **Alt text**: WordPress `postmeta` table using `_wp_attachment_image_alt` key
- **Settings**: WordPress `wp_options` table with `auto_alt_` prefix
- **Progress tracking**: WordPress transients (`auto_alt_offset`, `auto_alt_debug_logs`)
- **No custom database tables required**

### Security Implementation

- Nonce verification on all AJAX requests using `auto_alt_nonce`
- User capability checking (`manage_options`) before operations
- Input sanitization with WordPress functions (`sanitize_text_field`, `sanitize_textarea_field`)
- SQL injection protection via `$wpdb->prepare()`
- Output escaping with `esc_html`, `esc_attr`, `esc_textarea`

## Configuration Methods

### API Key Setup (Priority Order)
1. **wp-config.php**: `define('GEMINI_API_KEY', 'your-key');` (recommended)
2. **WP-CLI**: `wp option update auto_alt_gemini_api_key "your-key"`
3. **WordPress Admin**: Media → Auto Alt Tags settings

### Available Settings
- `auto_alt_gemini_api_key`: API key storage
- `auto_alt_model_name`: Gemini model selection (default: gemini-2.5-flash)
- `auto_alt_batch_size`: Processing batch size (1-50, default: 10)
- `auto_alt_image_size`: WordPress thumbnail size for API calls (default: medium)
- `auto_alt_debug_mode`: Debug logging toggle
- `auto_alt_custom_prompt`: Override default AI prompt

## Development Patterns

### WordPress Standards Compliance
- Follows WordPress Coding Standards with proper indentation (tabs)
- Uses WordPress hooks system (`add_action`, `add_filter`)
- Internationalization ready with `auto-alt-tags` text domain
- Proper plugin structure with activation/deactivation hooks

### Error Handling Strategy
- Try-catch blocks for API operations
- WordPress `is_wp_error()` checking for core functions
- Graceful degradation with user-friendly error messages
- Debug mode with transient-based log storage for troubleshooting

### AJAX Processing Pattern
- Security-first approach with nonce and capability verification
- Stateless batch processing with transient state management
- Real-time progress updates using percentage calculations
- Error collection and display in debug interface

## Plugin Integration Points

### WordPress Core Integration
- Admin menu under Media section
- Settings API registration with sanitization callbacks
- Uses WordPress image handling functions (`wp_get_attachment_image_src`, `get_attached_file`)
- Leverages WordPress HTTP API (`wp_remote_post`) for external requests

### WP-CLI Integration
- Automatic command registration when WP-CLI is available
- Progress bars using `\WP_CLI\Utils\make_progress_bar`
- Formatted output with `\WP_CLI\Utils\format_items`
- Comprehensive command documentation with examples

## Testing Approach

### Manual Testing Scenarios (from CONTRIBUTING.md)
- Different WordPress versions (5.0+) and PHP versions (7.4+)
- Multiple browsers and devices for admin interface
- Large media libraries for performance testing
- API error scenarios (invalid keys, network issues)
- Different image formats and sizes
- Fresh installation vs. partial processing scenarios

### Debug Features
- Real-time logging in admin interface when debug mode enabled
- Console logging in JavaScript for browser debugging
- WordPress error log integration with `[Auto Alt Tags]` prefix
- API request/response logging for troubleshooting

## Important Code Locations

- Main plugin initialization: `auto-alt-tags.php:856`
- AJAX handlers: `auto-alt-tags.php:414-582`
- Gemini API communication: `auto-alt-tags.php:745-827`
- Database queries: `auto-alt-tags.php:607-669`
- WP-CLI command definitions: `includes/class-wp-cli-command.php:49-292`
- Admin JavaScript processing: `assets/js/admin.js:74-136`