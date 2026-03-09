# SPM Interlinker

**AI-Powered Internal Linking for WordPress**

Automatically create contextual internal links across your WordPress content using AI. Boost your SEO with intelligent, natural-sounding link suggestions powered by OpenRouter API.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-2.5.3-orange.svg)](https://github.com/gr8xpert/SEO-Internal-Linking-WP-Plugin)

---

## Features

### AI-Powered Link Generation
- Generates natural, contextual sentences with embedded internal links
- Uses OpenRouter API with support for multiple AI models (Gemini, Claude, GPT)
- Smart keyword matching from your existing content

### Cost Efficient
- Optimized prompts to minimize API token usage
- Gemini Flash recommended (~$0.001 per post)
- Configurable keyword limits to reduce costs

### Non-Destructive
- Links stored in post meta, not in post content
- Displayed via `the_content` filter at runtime
- Easy removal - just click "Remove Links" or deactivate plugin

### Bulk Processing
- Process all posts with one click
- Background queue processing via WP Cron
- Configurable batch size (1-20 posts per run)
- Progress tracking with ETA

### Flexible Configuration
- Works with posts, pages, and custom post types
- Multiple keyword sources: titles, tags, categories, custom fields
- Optional external links to authoritative sources (Wikipedia)
- Dofollow/nofollow control for external links

---

## Installation

### From GitHub

1. Download the latest release or clone this repository
2. Upload the contents to `/wp-content/plugins/spm-interlinker/`
3. Activate the plugin through the WordPress Plugins menu
4. Go to **SPM Interlinker** in the admin menu
5. Add your OpenRouter API key and configure settings

### File Structure

```
spm-interlinker/
├── assets/
│   ├── admin.css
│   └── admin.js
├── includes/
│   ├── class-ai-engine.php
│   ├── class-indexer.php
│   ├── class-meta-box.php
│   ├── class-processor.php
│   ├── class-queue.php
│   ├── class-settings.php
│   └── class-url-validator.php
├── readme.txt
├── spm-interlinker.php
└── uninstall.php
```

---

## Configuration

### 1. API Setup

1. Get an API key from [OpenRouter.ai](https://openrouter.ai/keys)
2. Go to **SPM Interlinker > General**
3. Enter your API key and click **Test API** to verify

### 2. Select AI Model

| Model | Cost | Speed | Recommendation |
|-------|------|-------|----------------|
| Gemini 2.0 Flash | ~$0.001/post | Fast | **Recommended** |
| Gemini 1.5 Flash | ~$0.001/post | Fast | Budget option |
| Claude 3 Haiku | ~$0.005/post | Fast | Good quality |
| GPT-4o Mini | ~$0.003/post | Fast | Alternative |
| Claude Sonnet 4 | ~$0.10/post | Medium | High quality |
| Claude Sonnet 4.5 | ~$0.25/post | Medium | Premium quality |

### 3. Post Types

Select which post types should participate in interlinking:
- Posts
- Pages
- Custom Post Types (any registered CPT)

### 4. Keyword Sources

Configure how keywords are extracted from your content:

- **Post Title** - Use the post title as a keyword (with optional prefix stripping)
- **Tags** - Extract keywords from post tags
- **Categories** - Extract keywords from categories (excludes "Uncategorized")
- **Custom Field** - Extract from a specific meta field

### 5. Link Settings

- **Max Internal Links** - Number of internal links per post (default: 3)
- **Max External Links** - Number of external links per post (default: 1)
- **External Link Rel** - Choose dofollow or nofollow for external links

---

## Usage

### Processing Posts

1. Go to **SPM Interlinker > Process Posts**
2. Choose a processing method:
   - **Process** - Process individual posts one by one
   - **Process All (Browser)** - Process all unprocessed posts sequentially
   - **Start Background Processing** - Use WP Cron for large sites

### Viewing Results

After processing, each post will have:
- A status indicator (Processed/Not Processed)
- Link count showing how many links were added
- The generated content visible in the post's meta box

### Editing Generated Content

1. Edit any post
2. Find the **SPM Interlinker** meta box
3. Edit the generated HTML directly
4. Save the post

### Removing Links

- **Single Post**: Click "Remove Links" in the Process Posts table
- **All Posts**: Deactivate the plugin (links are not stored in content)

---

## How It Works

1. **Keyword Indexing** - The plugin builds an index mapping keywords to post URLs
2. **AI Generation** - When processing, AI generates a natural sentence mentioning related posts
3. **Link Injection** - Keywords in the sentence are automatically linked to their corresponding posts
4. **Runtime Display** - The generated sentence is appended to post content via `the_content` filter

### Example Output

For a post about "Luxury Villas in Marbella", the plugin might generate:

> If you're exploring the Costa del Sol, you might also be interested in properties in [Estepona](https://example.com/estepona), [Benahavis](https://example.com/benahavis), and [Puerto Banus](https://example.com/puerto-banus). Learn more about the [Marbella](https://en.wikipedia.org/wiki/Marbella) region.

---

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenRouter API key (free tier available)

---

## Changelog

### 2.5.3
- Renamed plugin to SPM Interlinker
- Added automatic data migration from previous version
- Fixed JavaScript variable collision bug
- Fixed character encoding issues
- Removed BOM from all files
- Added configurable batch size for cron processing
- Added "Get API Key" button

### 2.1.0
- Added background queue processing
- Improved JSON parsing
- Better error messages

### 2.0.0
- Complete rewrite of AI engine
- Non-destructive link storage (post meta instead of content)
- Cost optimization with keyword limits

### 1.0.0
- Initial release

---

## Support

For issues and feature requests, please use the [GitHub Issues](https://github.com/gr8xpert/SEO-Internal-Linking-WP-Plugin/issues) page.

---

## License

This project is licensed under the GPLv2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

---

## Credits

Developed with AI assistance from Claude (Anthropic).
