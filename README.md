# WordPress Auto Alt Tags

🖼️ **Automatically generate alt tags for WordPress images using Google's Gemini API**

A powerful WordPress plugin that intelligently generates descriptive alt text for images using AI, with batch processing, cost optimization, and WP-CLI support.

## ✨ Features

- **🤖 AI-Powered Alt Text Generation** - Uses Gemini models for accurate, descriptive alt tags
- **⚡ Batch Processing** - Process multiple images efficiently without timeout issues  
- **💰 Cost Optimized** - Uses existing WordPress thumbnails to save API credits (up to 80% savings)
- **📊 Progress Tracking** - Real-time progress updates with detailed statistics
- **🔄 Resume Capability** - Can restart where it left off if interrupted
- **💻 WP-CLI Support** - Command-line interface for developers and automation
- **🛡️ Error Handling** - Robust error handling with detailed debug logging
- **🎯 Smart Targeting** - Only processes images without existing alt text
- **🐛 Debug Mode** - Built-in debugging with real-time logs for troubleshooting

## 🚀 Installation

### Method 1: Manual Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/auto-alt-tags/`
3. Activate the plugin through WordPress admin
4. Configure your Gemini API key

### Method 2: Git Clone

```bash
cd wp-content/plugins/
git clone https://github.com/kahunam/wordpress-auto-alt-tags.git auto-alt-tags
```

## ⚙️ Configuration

### Get Your Gemini API Key

1. Visit [Google AI Studio](https://aistudio.google.com/app/apikey)
2. Create an account and generate an API key
3. Note: Gemini models have generous free tier limits

### Configure the API Key

**Option A: wp-config.php (Recommended)**
```php
define('GEMINI_API_KEY', 'your-api-key-here');
```

**Option B: WordPress Admin**
- Go to Media → Auto Alt Tags
- Enter your API key in the settings section

**Option C: WP-CLI**
```bash
wp option update auto_alt_gemini_api_key "your-api-key-here"
```

## 📖 Usage

### WordPress Admin Interface

1. Navigate to **Media → Auto Alt Tags**
2. Review the statistics dashboard
3. Click **"Test API Connection"** to verify your setup
4. Click **"Start Auto-Tagging Images"**
5. Monitor progress in real-time
6. View processing results and any errors in the debug log

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
| **Gemini API Key** | Your Google AI Studio API key | None |
| **Gemini Model** | Choose from available Gemini models | gemini-2.0-flash |
| **Batch Size** | Images processed per batch (1-50) | 10 |
| **Image Size for API** | WordPress thumbnail size to use | medium |
| **Debug Mode** | Enable detailed logging | Off |

### Available Gemini Models

- **Gemini 2.0 Flash** - Recommended: Fast & efficient
- **Gemini 1.5 Flash** - Previous generation, still capable
- **Gemini 1.5 Flash 8B** - Smallest model, fastest processing
- **Gemini 1.5 Pro** - Most capable, highest quality

## 🎯 Cost Optimization

The plugin includes several features to minimize API costs:

- **WordPress Thumbnails**: Uses existing thumbnail sizes instead of full images (saves ~80% on credits)
- **Smart Filtering**: Only processes images without existing alt text
- **Batch Processing**: Prevents API rate limiting with configurable delays
- **Efficient Models**: Defaults to Gemini 2.0 Flash (most cost-effective option)

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

### API Details

- **Endpoint**: Google Gemini API v1beta
- **Image Format**: Any format supported by WordPress
- **Max Tokens**: 50 (optimized for alt text)
- **Temperature**: 0.3 (balanced creativity/consistency)

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

**"Gemini API key not configured"**
- Ensure your API key is properly set in wp-config.php or admin settings

**"Failed to get image URL"**
- Check that WordPress has generated thumbnails for your images
- Run `wp media regenerate` to create missing thumbnails

**"API request failed"**
- Verify your API key is valid
- Check server has outbound HTTPS access
- Enable debug mode to see detailed error messages

**Processing stops unexpectedly**
- Use WP-CLI for large batches to avoid browser timeouts
- Check PHP memory and execution time limits

### Debug Mode

Enable debug mode in the plugin settings to see detailed logs:
- Real-time API requests and responses
- Processing status for each image
- Detailed error messages
- Performance metrics

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
- WordPress community for excellent documentation
- Contributors who help improve this plugin

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/kahunam/wordpress-auto-alt-tags/issues)
- **Documentation**: [Wiki](https://github.com/kahunam/wordpress-auto-alt-tags/wiki)
- **WordPress Plugin Directory**: Coming soon

---

**Made with ♥️ for the WordPress community**
