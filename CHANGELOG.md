# Changelog

All notable changes to the Auto Alt Tags plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-01-30

### Changed
- **Updated Gemini Models** - Migrated to current supported models
  - Removed deprecated/retired models: `gemini-2.0-flash`, `gemini-1.5-flash`, `gemini-1.5-flash-8b`, `gemini-1.5-pro`
  - Added new models: `gemini-2.5-flash` (default), `gemini-2.5-flash-lite`, `gemini-3-flash-preview`, `gemini-3-pro-preview`
  - Default model changed from `gemini-2.0-flash` to `gemini-2.5-flash`
- **Documentation Updates** - Updated all documentation to reflect new model names

### Notes
- Gemini 1.5 models have been retired by Google and return 404 errors
- Gemini 2.0 Flash is deprecated and scheduled for shutdown on March 31, 2026
- Gemini 2.5 Flash is the recommended model for production use
- Gemini 3 models are available in preview for advanced vision capabilities

## [1.1.0] - 2025-05-27

### Added
- **Debug Mode** - Built-in debugging with real-time logs for troubleshooting
  - Toggle in settings to enable/disable debug logging
  - Real-time log display in admin interface
  - Detailed API request and response logging
  - Error tracking with timestamps
- **API Test Button** - Test your API connection before processing images
  - Verifies API key validity
  - Checks model availability
  - Displays test response
- **Model Selection** - Choose from available Gemini models
  - Gemini 2.5 Flash (recommended)
  - Gemini 2.5 Flash Lite (cost-effective)
  - Gemini 3 Flash Preview (latest)
  - Gemini 3 Pro Preview (most capable)
- **WordPress Thumbnail Support** - Use existing WordPress image sizes
  - Dropdown to select from registered image sizes
  - Eliminates custom image resizing code
  - Significant cost savings by using smaller images

### Changed
- **Updated API Models** - Migrated from non-existent models to valid ones
  - Removed: `gemini-2.5-flash-preview-05-20` (doesn't exist)
  - Default: `gemini-2.0-flash` (current recommended model)
- **Improved UI** - Now uses WordPress default admin components
  - Removed all custom CSS requirements
  - Uses native WordPress `.card` components
  - Standard WordPress buttons and form tables
  - Native progress element for better compatibility
- **Better Error Handling** - Enhanced error messages and debugging
  - More descriptive error messages
  - API response code logging
  - Detailed debug information when enabled

### Fixed
- **API Request Issues** - Fixed model endpoint and authentication
  - Corrected API URL structure
  - Proper v1beta endpoint usage
  - Valid model names
- **Dependencies** - Removed unnecessary custom code
  - No longer uses `wp_get_image_editor()` for resizing
  - Uses `wp_get_attachment_image_src()` for thumbnails
  - Cleaner, more maintainable codebase

### Security
- Enhanced input validation and sanitization
- Improved nonce verification
- Better error logging without exposing sensitive data

## [1.0.1] - 2025-05-26

### 🚀 Updated to 2025 Standards

**Major API Update:**
- **Upgraded to Gemini 2.5 Flash** (`gemini-2.5-flash-preview-05-20`) - the latest and most efficient model
- **20-30% better efficiency** with improved token usage and performance
- **Enhanced reasoning capabilities** while maintaining speed and cost efficiency

**WordPress 2025 Compliance:**
- **PHP 7.4+ type declarations** added throughout codebase for better performance and reliability
- **Enhanced security measures** with improved nonce verification and capability checks
- **Better accessibility** with proper ARIA labels and semantic markup
- **Plugin Check compatibility** - ready for automated WordPress.org reviews

**Code Quality Improvements:**
- **Prepared statements** for all database queries to prevent SQL injection
- **Enhanced error handling** with detailed logging and user-friendly messages
- **Better internationalization** with proper text domain usage
- **WordPress file functions** (`wp_delete_file`, `wp_tempnam`) instead of native PHP functions
- **Proper escaping** and sanitization throughout the admin interface

**Developer Experience:**
- **Comprehensive PHPDoc** comments for all methods
- **Type hints** for parameters and return values where supported
- **Better code organization** with logical method grouping
- **Consistent naming conventions** following WordPress standards

### Fixed
- Updated deprecated `unlink()` calls to use WordPress `wp_delete_file()`
- Improved temporary file handling with WordPress-native functions
- Enhanced AJAX error handling with proper JSON responses
- Better validation for user inputs and API responses

### Security
- Strengthened nonce verification across all AJAX endpoints
- Enhanced user capability checking before sensitive operations
- Improved input sanitization and output escaping
- More robust error logging without exposing sensitive information

## [1.0.0] - 2025-05-26

### Added
- **Initial release** of the WordPress Auto Alt Tags plugin
- **AI-powered alt text generation** using Google's Gemini Flash 2.5 API
- **WordPress admin interface** with statistics dashboard and real-time progress tracking
- **Batch processing system** with configurable batch sizes (1-50 images)
- **Cost optimization features**:
  - Automatic image resizing to save API credits (default 512px max)
  - Smart filtering to only process images without existing alt text
  - Configurable image size limits
- **WP-CLI support** with comprehensive command-line interface:
  - `wp auto-alt generate` - Generate alt tags
  - `wp auto-alt stats` - View statistics
  - `wp auto-alt test-api` - Test API connection
  - Support for dry-run, verbose output, batch size control, and limits
- **Progress tracking and monitoring**:
  - Real-time progress bars in admin interface
  - Detailed processing logs
  - Error handling with detailed reporting
  - Resume capability after interruption
- **Modern admin interface**:
  - Responsive design for mobile and desktop
  - Statistics cards with gradient styling
  - Keyboard shortcuts (Ctrl+Enter to start, Escape to stop, R to refresh)
  - Loading animations and visual feedback
- **Developer-friendly features**:
  - Comprehensive error logging
  - Debug mode support
  - Proper WordPress hooks and filters
  - Clean code architecture with separation of concerns
- **Security and reliability**:
  - Proper nonce verification for AJAX requests
  - User capability checking
  - Input sanitization and validation
  - Timeout handling for long-running processes
- **Documentation and support**:
  - Comprehensive README with installation and usage guides
  - Inline code documentation
  - Troubleshooting section
  - API key configuration instructions

### Technical Details
- **WordPress compatibility**: 5.0+ 
- **PHP compatibility**: 7.4+
- **Required extensions**: cURL, GD or ImageMagick
- **API integration**: Gemini Flash 2.5 via Google AI Studio
- **Image processing**: Automatic JPEG conversion and resizing
- **Storage**: Uses WordPress postmeta for alt text storage
- **Architecture**: Object-oriented PHP with proper WordPress coding standards

### Settings and Configuration
- **API Key Management**: Supports wp-config.php constants and admin settings
- **Batch Size Control**: 1-50 images per batch (default: 10)
- **Image Size Optimization**: 256-2048px maximum (default: 512px)
- **Processing Controls**: Start/stop functionality with progress monitoring

## [Unreleased]

### Planned Features
- **WordPress.org plugin directory submission**
- **Internationalization (i18n) support** with translation files
- **Advanced filtering options**:
  - Process by date range
  - Filter by image size or file type
  - Exclude specific directories or patterns
- **Enhanced API support**:
  - Support for additional AI providers (OpenAI GPT-4V, Claude Vision)
  - API key rotation for high-volume usage
  - Rate limiting and quota management
  - Integration with Gemini 2.5 Pro Deep Think for complex images
- **Bulk operations**:
  - Export/import alt text as CSV
  - Bulk edit existing alt text
  - Find and replace functionality
- **Performance improvements**:
  - Background processing with WordPress cron
  - Queue management for large media libraries
  - Memory optimization for resource-constrained hosting
- **Advanced features**:
  - Custom prompts for specific image types
  - Alt text quality scoring and suggestions
  - Integration with popular page builders
  - SEO analysis and recommendations
  - Machine learning-based alt text improvement suggestions

### Bug Fixes and Improvements
- Performance optimizations for large media libraries
- Enhanced error handling and recovery
- Improved mobile interface responsiveness
- Better API timeout management
- Advanced thinking budget controls for Gemini 2.5 models

---

## 🔄 Migration Notes

### Upgrading from 1.0.1 to 1.1.0
- **No breaking changes** - the update is fully backward compatible
- **Model change**: The default model has changed to `gemini-2.5-flash` (current recommended model)
- **Settings update**: Visit the settings page to select your preferred model
- **UI improvements**: Custom CSS is no longer needed and can be removed

### Upgrading from 1.0.0 to 1.0.1
- **No breaking changes** - the update is fully backward compatible
- **API improvements** will automatically use the more efficient Gemini 2.5 Flash model
- **Enhanced security** provides better protection without affecting functionality
- **Performance gains** will be immediately apparent with reduced token usage

## 🛠️ Development & Contributing

We welcome contributions! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

### Development Setup
1. Clone the repository
2. Set up a local WordPress development environment
3. Install the plugin in development mode
4. Configure your Gemini API key
5. Make your changes and test thoroughly

### Reporting Issues
Please use the [GitHub Issues](https://github.com/kahunam/wordpress-auto-alt-tags/issues) page to report bugs or request features. Include:
- WordPress version
- PHP version  
- Plugin version
- Steps to reproduce the issue
- Any error messages

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.
