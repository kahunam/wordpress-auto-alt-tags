# WordPress Auto Alt Tags

🖼️ **Automatically generate alt tags for WordPress images using multiple AI providers**

A powerful WordPress plugin that intelligently generates descriptive alt text for images using AI APIs (Gemini, OpenAI, Claude, OpenRouter), with batch processing, cost optimization, real-time API key testing, and WP-CLI support.

## ✨ Features

- **🌐 Multiple AI Providers** - Support for Gemini, OpenAI, Claude, and OpenRouter APIs
- **🔑 Real-Time API Key Testing** - Test API keys instantly with visual feedback (✓ Valid / ✗ Invalid)
- **🤖 AI-Powered Alt Text Generation** - Uses advanced AI models for accurate, descriptive alt tags
- **⚡ Batch Processing** - Process multiple images efficiently without timeout issues  
- **💰 Cost Optimized** - Uses existing WordPress thumbnails to save API credits (up to 80% savings)
- **📊 Progress Tracking** - Real-time progress updates with detailed statistics
- **🔄 Resume Capability** - Can restart where it left off if interrupted
- **💻 WP-CLI Support** - Command-line interface for developers and automation
- **🛡️ Error Handling** - Robust error handling with detailed debug logging
- **🎯 Smart Targeting** - Only processes images without existing alt text
- **🐛 Debug Mode** - Built-in debugging with real-time logs for troubleshooting
- **✏️ Custom Prompts** - Override the default prompt with your own instructions
- **🔧 Platform Agnostic** - Easy provider switching with dynamic model selection

## 🚀 Installation

### Method 1: Manual Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/auto-alt-tags/`
3. Activate the plugin through WordPress admin
4. Configure your preferred AI provider and API key

### Method 2: Git Clone

```bash
cd wp-content/plugins/
git clone https://github.com/kahunam/wordpress-auto-alt-tags.git auto-alt-tags
```

## ⚙️ Configuration

### Supported AI Providers

Choose from multiple AI providers based on your needs:

#### Google Gemini (Recommended)
- **Get API Key**: [Google AI Studio](https://aistudio.google.com/app/apikey)
- **Benefits**: Generous free tier, very cost-effective, excellent image analysis
- **Models**: Gemini 2.0 Flash, Gemini 1.5 Flash, Gemini 1.5 Pro

#### OpenAI
- **Get API Key**: [OpenAI Platform](https://platform.openai.com/api-keys)
- **Benefits**: High-quality text generation, reliable service
- **Models**: GPT-4o, GPT-4o Mini, GPT-4 Turbo, GPT-4 Vision

#### Anthropic Claude
- **Get API Key**: [Anthropic Console](https://console.anthropic.com/)
- **Benefits**: Excellent reasoning, safety-focused responses
- **Models**: Claude 3.5 Sonnet, Claude 3.5 Haiku, Claude 3 Opus

#### OpenRouter
- **Get API Key**: [OpenRouter](https://openrouter.ai/keys)
- **Benefits**: Access to multiple models through one API, competitive pricing
- **Models**: Claude 3.5 Sonnet, GPT-4o, Gemini Pro via OpenRouter

### Configure Your API Key

**Option A: WordPress Admin (Recommended)**
1. Go to **Media → Auto Alt Tags**
2. Select your preferred AI provider from the dropdown
3. Enter your API key in the corresponding field
4. Click **"Test Key"** to verify it works instantly
5. Save your settings

**Option B: wp-config.php (Gemini only - for backwards compatibility)**
```php
define('GEMINI_API_KEY', 'your-api-key-here');
```

**Option C: WP-CLI**
```bash
# For Gemini
wp option update auto_alt_gemini_api_key "your-api-key-here"

# For OpenAI
wp option update auto_alt_openai_api_key "your-api-key-here"

# For Claude
wp option update auto_alt_claude_api_key "your-api-key-here"

# For OpenRouter
wp option update auto_alt_openrouter_api_key "your-api-key-here"

# Set the active provider
wp option update auto_alt_provider "gemini"
```

## 📖 Usage

### WordPress Admin Interface

1. Navigate to **Media → Auto Alt Tags**
2. Review the statistics dashboard
3. Select your AI provider and enter your API key
4. Click **"Test Key"** to verify your API key works (✓ Valid / ✗ Invalid feedback)
5. Optionally click **"Test on First 5 Images"** to preview results
6. Click **"Start Auto-Tagging All Images"** to process your entire library
7. Monitor progress in real-time with detailed statistics
8. View processing results and any errors in the debug log

### WP-CLI Commands

First, ensure the plugin is activated:

```bash
# View statistics
wp auto-alt stats

# Generate alt tags for all images without them
wp auto-alt generate

# Process with custom batch size
wp auto-alt generate --batch-size=5

# Dry run to see what would be processed
wp auto-alt generate --dry-run

# Limit number of images processed
wp auto-alt generate --limit=100
```

## 📋 Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **AI Provider** | Choose your preferred AI service | Gemini |
| **API Key** | Provider-specific API key with real-time testing | None |
| **AI Model** | Model selection (updates based on provider) | gemini-2.0-flash |
| **Batch Size** | Images processed per batch (1-50) | 10 |
| **Image Size for API** | WordPress thumbnail size to use | medium |
| **Custom Prompt** | Override the default prompt (optional) | See below |
| **Debug Mode** | Enable detailed logging | Off |

### Available Models by Provider

#### Gemini Models
- **Gemini 2.0 Flash** - Recommended: Fast & efficient, very cost-effective
- **Gemini 1.5 Flash** - Previous generation, still capable
- **Gemini 1.5 Flash 8B** - Smallest model, fastest processing
- **Gemini 1.5 Pro** - Most capable, highest quality

#### OpenAI Models
- **GPT-4o** - Latest vision-capable model
- **GPT-4o Mini** - Cost-effective option
- **GPT-4 Turbo** - Most capable
- **GPT-4 Vision Preview** - Vision-specific model

#### Claude Models
- **Claude 3.5 Sonnet** - Latest and most capable
- **Claude 3.5 Haiku** - Fast and efficient
- **Claude 3 Opus** - Most powerful reasoning

#### OpenRouter Models
- **Claude 3.5 Sonnet via OpenRouter** - Access Claude through OpenRouter
- **GPT-4o via OpenRouter** - Access OpenAI through OpenRouter
- **GPT-4o Mini via OpenRouter** - Cost-effective OpenAI access
- **Gemini Pro 1.5 via OpenRouter** - Access Gemini through OpenRouter

### Default Prompt

The default prompt used for generating alt text is:
> "Generate a concise, descriptive alt text for this image. Focus on the main subject and important details. Keep it under 125 characters and avoid phrases like 'image of' or 'picture of'. Be specific and helpful for screen readers."

You can override this with your own prompt in the settings.

## 🎯 Cost Optimization

The plugin includes several features to minimize API costs:

- **WordPress Thumbnails**: Uses existing thumbnail sizes instead of full images (saves ~80% on credits)
- **Smart Filtering**: Only processes images without existing alt text
- **Batch Processing**: Prevents API rate limiting with configurable delays
- **Efficient Models**: Defaults to cost-effective options (Gemini 2.0 Flash recommended)
- **Provider Choice**: Compare costs across different AI providers
- **Test First**: Use "Test on First 5 Images" to estimate costs before batch processing

### Cost Comparison Tips

- **Gemini Flash models** are recommended as they are very cost-effective
- **OpenAI GPT-4o Mini** offers good balance of cost and quality
- **Claude 3.5 Haiku** is the fastest and most economical Claude option
- **OpenRouter** may offer competitive pricing for multiple providers

## 📈 Statistics Dashboard

The admin interface provides comprehensive statistics:

- **Total Images**: Count of all images in media library
- **With Alt Tags**: Images that already have alt text
- **Need Alt Tags**: Images missing alt text  
- **Coverage Percentage**: Overall completion percentage

## 🛠️ Technical Details

### Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Extensions**: cURL (for API calls)
- **Permissions**: Standard WordPress media permissions

### Plugin Information

- **Version**: 1.2.0
- **Author**: Kahunam
- **License**: GPL v2 or later

### API Details

- **Supported APIs**: Gemini v1beta, OpenAI v1, Claude v1, OpenRouter v1
- **Image Format**: Any format supported by WordPress
- **Max Tokens**: 50 (optimized for alt text)
- **Temperature**: Low values for consistency (varies by provider)

### File Structure

```
auto-alt-tags/
├── auto-alt-tags.php            # Main plugin file
├── includes/
│   └── class-wp-cli-command.php  # WP-CLI commands
├── assets/
│   └── js/
│       └── admin.js             # Admin interface JavaScript
├── languages/                   # Translation files
└── README.md                    # This file
```

## 🔧 Troubleshooting

### Common Issues

**"[Provider] API key not configured"**
- Ensure your API key is properly set for the selected provider
- Use the "Test Key" button to verify your API key works
- Check that you've selected the correct provider in the dropdown

**"API key not valid" (✗ Invalid feedback)**
- Double-check your API key for typos
- Verify the key has proper permissions for your selected provider
- Ensure your provider account has sufficient credits/quota

**"Failed to get image URL"**
- Check that WordPress has generated thumbnails for your images
- Run `wp media regenerate` to create missing thumbnails

**"API request failed"**
- Use the "Test Key" button to diagnose the specific issue
- Check server has outbound HTTPS access to API endpoints
- Enable debug mode to see detailed error messages
- Verify your provider account has available credits

**Processing stops unexpectedly**
- Use WP-CLI for large batches to avoid browser timeouts
- Check PHP memory and execution time limits
- Try switching to a different AI provider if one is experiencing issues

### Debug Mode

Enable debug mode in the plugin settings to see detailed logs:
- Real-time API requests and responses
- Processing status for each image
- Detailed error messages
- Performance metrics

### API Key Testing

The plugin includes real-time API key testing:
- **Instant Validation**: Test keys immediately with visual feedback
- **Provider-Specific Testing**: Each provider has tailored test endpoints
- **Visual Indicators**: Green ✓ for valid keys, red ✗ for invalid ones
- **Error Details**: Specific error messages when keys fail
- **No Guesswork**: Know immediately if your setup works before processing images

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Setup

1. Clone the repository
2. Set up a local WordPress environment
3. Install the plugin in development mode
4. Make your changes and test thoroughly

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Google AI Studio for the Gemini API
- OpenAI for GPT models and vision capabilities
- Anthropic for Claude's advanced reasoning
- OpenRouter for multi-provider API access
- WordPress community for excellent documentation
- Contributors who help improve this plugin

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/kahunam/wordpress-auto-alt-tags/issues)
- **Documentation**: [Wiki](https://github.com/kahunam/wordpress-auto-alt-tags/wiki)
- **WordPress Plugin Directory**: Coming soon

---

**Made with ♥️ for the WordPress community**
