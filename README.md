# RS Smart Interlinker

A powerful WordPress plugin that automatically interlinks content across post types using AI-powered contextual linking via Claude AI (OpenRouter).

## Features

- **AI-Powered Linking**: Uses Claude AI to generate natural, contextual sentences with internal links
- **Smart Keyword Indexing**: Automatically extracts keywords from post titles, tags, categories, or custom fields
- **Cross Post-Type Linking**: Link between any combination of post types (posts, pages, CPTs)
- **External Authority Links**: Optionally includes authoritative external links (government sites, Wikipedia, etc.)
- **Page Builder Safe**: Works with Divi, Elementor, and other page builders without breaking their editors
- **Non-Destructive**: Links are stored in post meta and displayed via filter - original content untouched
- **Easy Management**: Process posts individually or in bulk, remove links with one click
- **SEO Friendly**: DoFollow/NoFollow options for external links

## How It Works

1. **Index Building**: Plugin scans selected post types and builds a keyword-to-URL index
2. **AI Generation**: When you process a post, Claude AI generates a natural sentence mentioning related pages
3. **Safe Storage**: The generated sentence is stored in post meta (not in post_content)
4. **Runtime Display**: Links are appended to content via `the_content` filter
5. **Easy Removal**: Remove links anytime - just deletes meta, no content modification needed

## Installation

1. Download or clone this repository
2. Upload the `rs-smart-interlinker` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to **RS Interlinker** in the admin menu to configure

## Configuration

### General Tab

| Setting | Description |
|---------|-------------|
| OpenRouter API Key | Your API key from [OpenRouter](https://openrouter.ai) |
| AI Model | Model to use (default: `anthropic/claude-sonnet-4.5`) |
| Max Internal Links | Maximum internal links per post (default: 3) |
| Max External Links | Maximum external authority links (default: 1) |
| Enable External Linking | Toggle external links on/off |

### Post Types Tab

Select which post types should participate in the interlinking pool. Cross-post-type linking is enabled by default.

### Keyword Sources Tab

Configure how keywords are extracted from posts:

| Source | Description |
|--------|-------------|
| Post Title | Extract keyword from title (with optional prefix stripping) |
| Tags | Use post tags as keywords |
| Categories | Use categories as keywords |
| Custom Field | Use a specific meta field for keywords |

**Prefix Stripping Example:**
- Title: "Properties for sale in Marbella"
- Prefix to strip: "Properties for sale in "
- Extracted keyword: "Marbella"

### Advanced Tab

- **External Link Rel**: Choose DoFollow or NoFollow for external links
- **Rebuild Index**: Re-scan all posts and rebuild the keyword index

### Process Posts Tab

- View all posts with their processing status
- **Process**: Generate AI links for a post
- **Remove Links**: Remove all generated links from a post
- **Process All**: Bulk process all unprocessed posts

## Per-Post Keywords

Each post has a meta box where you can specify custom keywords that override automatic extraction:

1. Edit any post in a selected post type
2. Find "RS Smart Interlinker — Custom Keywords" meta box
3. Enter comma-separated keywords
4. Save the post

## API Requirements

This plugin requires an [OpenRouter](https://openrouter.ai) API key to generate AI-powered links.

1. Sign up at [OpenRouter](https://openrouter.ai)
2. Generate an API key
3. Add credits to your account
4. Enter the API key in plugin settings

**Supported Models:**
- `anthropic/claude-sonnet-4.5` (recommended)
- `anthropic/claude-sonnet-4`
- Any other OpenRouter-supported model

## Page Builder Compatibility

This plugin is designed to work safely with page builders:

| Page Builder | Compatibility |
|--------------|---------------|
| Divi | ✅ Full support |
| Elementor | ✅ Full support |
| WPBakery | ✅ Full support |
| Gutenberg | ✅ Full support |
| Classic Editor | ✅ Full support |

**Why it's safe:** The plugin stores generated content in post meta and displays it via `the_content` filter. It never modifies your actual post content or page builder structures.

## File Structure

```
rs-smart-interlinker/
├── rs-smart-interlinker.php      # Main plugin file
├── uninstall.php                  # Cleanup on uninstall
├── readme.txt                     # WordPress readme
├── README.md                      # This file
├── includes/
│   ├── class-settings.php         # Admin settings & UI
│   ├── class-processor.php        # Post processing & display
│   ├── class-indexer.php          # Keyword index management
│   ├── class-ai-engine.php        # OpenRouter API integration
│   ├── class-url-validator.php    # External URL validation
│   └── class-meta-box.php         # Per-post keyword override
└── assets/
    ├── admin.css                  # Admin styles
    └── admin.js                   # Admin JavaScript
```

## Hooks & Filters

| Hook | Type | Description |
|------|------|-------------|
| `the_content` | filter | Appends AI-generated sentence (priority 9999) |
| `save_post` | action | Updates index when posts are saved |
| `delete_post` | action | Removes posts from index |

## Database Storage

| Option/Meta | Description |
|-------------|-------------|
| `rs_interlinker_options` | Plugin settings |
| `rs_interlinker_index` | Keyword-to-URL index |
| `_rs_interlinker_keywords` | Per-post custom keywords |
| `_rs_interlinker_processed` | Processing timestamp |
| `_rs_interlinker_added_html` | Generated HTML sentence |
| `_rs_interlinker_links_count` | Number of links added |

## Example Output

For a page titled "Properties for sale in Marbella", the plugin might generate:

> Buyers exploring Marbella often also consider [properties for sale in Estepona](https://example.com/estepona) for its charming old town, [properties for sale in Benahavis](https://example.com/benahavis) for its mountain views, and [properties for sale in Nueva Andalucia](https://example.com/nueva-andalucia) for its golf courses; for more information about the area, visit the [official Marbella tourism website](https://www.marbella.es).

## SEO Notes

- **Google sees the links**: Links are rendered on the live page, which is what search engines crawl
- **RankMath/Yoast warnings**: These tools analyze `post_content`, not filtered output. Warnings about missing links are cosmetic - the links ARE on the page
- **External links**: Validated via HEAD request before being added
- **DoFollow by default**: External links are DoFollow unless you change the setting

## Uninstallation

When you uninstall (delete) the plugin:
- All plugin options are removed
- All post meta created by the plugin is removed
- The keyword index is removed

**Note:** Deactivating (not deleting) the plugin simply stops displaying the links. All data is preserved for reactivation.

## Changelog

### 2.0.0
- Complete rebuild with page-builder-safe approach
- Links stored in post meta instead of post_content
- Added Process Posts admin tab
- Added bulk processing capability
- Added individual post processing/removal
- Improved Divi/Elementor compatibility

### 1.0.0
- Initial release

## License

GPL v2 or later

## Credits

- Built with [Claude AI](https://anthropic.com) via [OpenRouter](https://openrouter.ai)
- Developed for WordPress

## Support

For issues and feature requests, please use the [GitHub Issues](https://github.com/gr8xpert/SEO-Internal-Linking-WP-Plugin/issues) page.
