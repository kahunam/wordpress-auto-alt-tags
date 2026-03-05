=== Auto Alt Tags ===
Contributors: kahunam
Tags: alt text, accessibility, AI, images, SEO
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generates descriptive alt tags for WordPress images using AI (Gemini, OpenAI, Claude, OpenRouter).

== Description ==

Auto Alt Tags uses AI to automatically generate descriptive, accessible alt text for images in your WordPress media library. Supports Google Gemini, OpenAI, Anthropic Claude, and OpenRouter.

**Features:**

* Multiple AI providers: Gemini, OpenAI, Claude, OpenRouter
* Auto-generation on upload (background WP Cron processing)
* Batch processing with real-time progress tracking
* Resume capability if interrupted
* Media Library column showing alt text status per image
* Filter Media Library by alt text presence
* Per-image regenerate button
* CSV export and import for bulk editing
* WP-CLI support (`wp auto-alt generate`, `wp auto-alt stats`, `wp auto-alt retry-failed`)
* Debug mode with real-time logs
* Custom prompt support
* Rate limit awareness per provider

== Installation ==

1. Upload the plugin to `/wp-content/plugins/auto-alt-tags/`
2. Activate the plugin through the WordPress Plugins screen
3. Go to **Media → Auto Alt Tags**
4. Select your AI provider and enter your API key
5. Click **Test Key** to verify your setup
6. Click **Start Auto-Tagging All Images**

== Frequently Asked Questions ==

= Which AI provider should I use? =

Google Gemini is recommended. It offers a generous free tier and excellent image analysis.

= Does it overwrite existing alt text? =

No. The plugin only processes images that do not already have alt text.

= Can I run it from the command line? =

Yes. Use `wp auto-alt generate` for bulk processing, `wp auto-alt stats` for statistics, and `wp auto-alt retry-failed` to re-queue failed images.

= Will it automatically process images I upload? =

Yes, when "Auto-generate on Upload" is enabled (default), new images are queued and processed in the background every 5 minutes.

== Changelog ==

= 1.1.0 =
* Added: Auto-generation on upload via WP Cron background processing
* Added: Alt Text column in Media Library list view
* Added: Filter Media Library by alt text status
* Added: Per-image Regenerate button
* Added: CSV export and import
* Added: uninstall.php for clean removal
* Added: Translation-ready POT file

= 1.0.1 =
* Updated Gemini models to current supported versions

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
Adds background auto-generation on upload and Media Library integration. No breaking changes.
