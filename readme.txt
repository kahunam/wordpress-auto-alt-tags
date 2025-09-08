=== Auto Alt Tag Generator ===
Contributors: kahunam
Tags: alt tags, accessibility, seo, images, ai, gemini, openai, claude, media library, automation, wcag, ada compliance
Requires at least: 4.1
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate descriptive alt tags for images using AI (Gemini, OpenAI, Claude, OpenRouter). Improve accessibility and SEO with one click.

== Description ==

**Auto Alt Tag Generator** uses advanced AI to automatically generate descriptive, SEO-friendly alt tags for your WordPress media library images. Supporting multiple AI providers (Google Gemini, OpenAI, Claude, OpenRouter), this plugin helps improve your website's accessibility compliance and search engine optimization.

= Key Features =

* **Multiple AI Providers**: Choose from Gemini (default), OpenAI GPT-4, Claude, or OpenRouter
* **Batch Processing**: Generate alt tags for hundreds of images with progress tracking
* **Cost Optimization**: Uses smaller image sizes (configurable) to minimize API costs
* **Smart Processing**: Skip images that already have alt tags
* **Preview Mode**: Test with 5 images before processing entire library
* **WP-CLI Support**: Automate alt tag generation via command line
* **Debug Mode**: Detailed logging for troubleshooting
* **Custom Prompts**: Customize AI instructions for your specific needs
* **Resume Capability**: Automatically resume interrupted batch processing

= Supported AI Providers =

* **Google Gemini** - Fast, cost-effective, supports latest models including gemini-2.0-flash
* **OpenAI** - GPT-4 Vision and GPT-4o models
* **Anthropic Claude** - Claude 3 models with vision capabilities
* **OpenRouter** - Access multiple AI models through one API

= Benefits =

* **Improved Accessibility**: Meet WCAG 2.1 and ADA compliance requirements
* **Better SEO**: Search engines use alt tags to understand image content
* **Time Savings**: Automate hours of manual alt tag writing
* **Consistent Quality**: AI generates descriptive, contextual alt tags
* **Flexible Configuration**: Choose models, batch sizes, and prompts

= WP-CLI Commands =

Generate alt tags from the command line:

`wp auto-alt generate --limit=50 --dry-run`
`wp auto-alt stats`
`wp auto-alt test-api`

= Privacy & Security =

* API keys stored securely in WordPress database
* Images processed are sent to your chosen AI provider's API
* No data is stored or collected by the plugin author
* All API communications use secure HTTPS
* Supports configuration via wp-config.php for enhanced security

== Installation ==

1. Upload the `auto-alt-tags` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Media → Auto Alt Tags
4. Enter your API key for your chosen provider
5. Configure settings (model, batch size, image size)
6. Click "Test First 5 Images" to preview
7. Click "Start Processing" to generate alt tags

= API Key Setup =

**Option 1: Via Plugin Settings**
1. Go to Media → Auto Alt Tags
2. Select your AI provider
3. Enter your API key
4. Click "Test API Connection"

**Option 2: Via wp-config.php (Recommended for production)**
Add to your wp-config.php:
`define('GEMINI_API_KEY', 'your-api-key-here');`
`define('OPENAI_API_KEY', 'your-api-key-here');`
`define('CLAUDE_API_KEY', 'your-api-key-here');`
`define('OPENROUTER_API_KEY', 'your-api-key-here');`

= Getting API Keys =

* **Gemini**: [Get API Key](https://makersuite.google.com/app/apikey)
* **OpenAI**: [Get API Key](https://platform.openai.com/api-keys)
* **Claude**: [Get API Key](https://console.anthropic.com/settings/keys)
* **OpenRouter**: [Get API Key](https://openrouter.ai/keys)

== Frequently Asked Questions ==

= Is this plugin free? =

Yes, the plugin is completely free. You only pay for the AI API usage based on your chosen provider's pricing.

= Which AI provider should I choose? =

* **Gemini**: Best balance of cost and quality (recommended)
* **OpenAI**: Excellent quality, higher cost
* **Claude**: Great for detailed descriptions
* **OpenRouter**: Access to multiple models

= How much does it cost to generate alt tags? =

Costs vary by provider and model. With Gemini 2.0 Flash (default):
* Approximately $0.01-0.02 per 100 images
* Using medium-sized images (default) reduces costs by 70-90%

= Can I customize the alt tag generation? =

Yes! You can:
* Choose different AI models
* Adjust batch processing size
* Select image size for processing
* Write custom prompts for specific requirements

= What happens if processing is interrupted? =

The plugin automatically saves progress and can resume from where it stopped. Just click "Start Processing" again.

= Will this overwrite existing alt tags? =

No, by default the plugin only processes images without alt tags. Existing alt tags are preserved.

= Is my data safe? =

Yes. Images are only sent to your chosen AI provider's API for processing. No data is collected or stored by the plugin author.

= Can I use this with WP-CLI? =

Yes! The plugin includes full WP-CLI support:
`wp auto-alt generate --limit=100`
`wp auto-alt stats`
`wp auto-alt test-api`

= What image formats are supported? =

The plugin supports all image formats that WordPress supports: JPEG, PNG, GIF, WebP, and AVIF.

= How can I test before processing all images? =

Use the "Test First 5 Images" button to preview alt tag generation before processing your entire library.

== Screenshots ==

1. Main plugin interface showing statistics and processing controls
2. Settings panel with AI provider selection and configuration
3. Batch processing in progress with real-time updates
4. Debug log showing detailed processing information
5. WP-CLI commands in action
6. Generated alt tags in Media Library

== Changelog ==

= 1.2.0 =
* Added support for multiple AI providers (OpenAI, Claude, OpenRouter)
* Introduced provider selection interface
* Added model selection for each provider
* Improved error handling and provider-specific testing
* Enhanced debug logging for all providers
* Updated documentation for multi-provider support

= 1.1.2 =
* Improved prompt to avoid sensitive information in alt text
* Better handling of decorative images
* Enhanced description quality

= 1.1.1 =
* Added custom prompt support
* Introduced multiple Gemini model options
* Added gemini-2.0-flash-exp model support
* Improved settings interface
* Enhanced error messages

= 1.1.0 =
* Added cost-saving image size selection
* Implemented resume capability for interrupted processing
* Added progress percentage display
* Improved error handling and recovery
* Enhanced debug logging

= 1.0.5 =
* Added comprehensive test mode
* Improved API error handling
* Enhanced progress tracking
* Better memory management for large libraries

= 1.0.4 =
* Added WP-CLI support
* Implemented dry-run mode
* Added statistics command
* Improved batch processing

= 1.0.3 =
* Added debug mode toggle
* Improved error messages
* Fixed timeout issues
* Enhanced API response handling

= 1.0.2 =
* Improved batch processing
* Added stop/resume functionality
* Better progress indication
* Fixed memory leaks

= 1.0.1 =
* Enhanced UI/UX
* Added real-time progress updates
* Improved error handling
* Fixed AJAX issues

= 1.0.0 =
* Initial release
* Gemini API integration
* Batch processing support
* Basic settings panel

== Upgrade Notice ==

= 1.2.0 =
Major update! Now supports multiple AI providers including OpenAI, Claude, and OpenRouter. Upgrade to access more AI models and providers.

= 1.1.2 =
Important update for better alt text quality and sensitive content handling.

= 1.1.1 =
Adds custom prompt support and new Gemini models for better flexibility.

= 1.1.0 =
Significant performance improvements and cost optimizations. Recommended update for all users.

== Additional Information ==

= System Requirements =

* WordPress 4.1 or higher
* PHP 7.4 or higher
* Active internet connection
* Valid API key from supported provider

= Support =

For support, feature requests, or bug reports, please visit our [GitHub repository](https://github.com/kahunam/wordpress-auto-alt-tags).

= Contributing =

We welcome contributions! Please see our [Contributing Guidelines](https://github.com/kahunam/wordpress-auto-alt-tags/blob/main/CONTRIBUTING.md).

= License =

This plugin is licensed under GPL v2 or later. You are free to use, modify, and distribute it under the terms of the GPL license.