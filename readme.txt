=== SPM Interlinker ===
Contributors: spmdevelopment
Tags: internal linking, seo, ai, interlinking, contextual links, openrouter
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.5.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered internal linking for WordPress. Automatically create contextual internal links using OpenRouter API.

== Description ==

SPM Interlinker automatically creates internal links between your WordPress content using AI. It generates natural, contextual sentences with embedded links to related posts, boosting your SEO without manual effort.

**Key Features**

* AI-powered link generation using OpenRouter API
* Support for multiple AI models (Gemini, Claude, GPT)
* Non-destructive - links stored in meta, not content
* Bulk processing with background queue
* Works with posts, pages, and custom post types
* Optional external links to authoritative sources
* Cost-efficient with optimized prompts

**How It Works**

1. The plugin indexes keywords from your posts (titles, tags, categories)
2. When processing, AI generates a natural sentence mentioning related content
3. Keywords are automatically linked to corresponding posts
4. The sentence is displayed at the end of your content

**Cost Efficient**

* Gemini Flash recommended (~$0.001 per post)
* Configurable keyword limits to reduce API costs
* One-time processing - results are cached

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/spm-interlinker/`
2. Activate the plugin through the 'Plugins' menu
3. Go to 'SPM Interlinker' in the admin menu
4. Add your OpenRouter API key (get one at openrouter.ai/keys)
5. Configure settings and start processing

== Configuration ==

**General Tab**

* OpenRouter API Key - Required for AI features
* AI Model - Choose from Gemini, Claude, or GPT models
* Max Internal Links - Default: 3
* Max External Links - Default: 1

**Post Types Tab**

* Select which post types participate in interlinking

**Keyword Sources Tab**

* Post Title (with optional prefix stripping)
* Tags
* Categories
* Custom Field

**Advanced Tab**

* External Link Rel (dofollow/nofollow)
* Rebuild Keyword Index

**Process Posts Tab**

* View all posts and their processing status
* Process individually or in bulk
* Background processing via WP Cron
* Configurable batch size

== Frequently Asked Questions ==

= Do I need an API key? =

Yes, you need an OpenRouter API key to use this plugin. Sign up at openrouter.ai/keys (free tier available).

= Which AI model should I use? =

Gemini 2.0 Flash is recommended for the best balance of cost and quality (~$0.001 per post).

= Are links stored in my post content? =

No. Links are stored in post meta and displayed via the_content filter. Your original content is never modified.

= How do I remove links? =

Click "Remove Links" for individual posts, or deactivate the plugin to remove all links instantly.

= Can I edit the generated content? =

Yes. Each post has a meta box where you can edit the AI-generated sentence.

== Changelog ==

= 2.5.3 =
* Renamed plugin to SPM Interlinker
* Added automatic data migration
* Fixed JavaScript issues
* Added configurable batch size
* Added "Get API Key" button

= 2.1.0 =
* Added background queue processing
* Improved error handling

= 2.0.0 =
* Complete rewrite
* Non-destructive link storage
* Cost optimization

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.5.3 =
Plugin renamed to SPM Interlinker. Your existing data will be automatically migrated.
