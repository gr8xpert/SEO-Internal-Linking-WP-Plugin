=== RS Smart Interlinker ===
Contributors: rsdevelopment
Tags: internal linking, seo, ai, claude, interlinking, contextual links
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically interlinks content across post types using AI-powered contextual linking via Claude AI.

== Description ==

RS Smart Interlinker is a powerful WordPress plugin that automatically creates internal links between your content using a two-phase approach:

**Phase 1 - Natural Keyword Matching (No API Required)**
* Scans paragraph text for matches against other posts' keywords
* Links the first occurrence of each keyword match only
* Handles possessives and whole-word matching
* Skips headings, existing links, images, shortcodes, and code blocks

**Phase 2 - AI-Powered Top-Up (Claude via OpenRouter)**
* Only fires if Phase 1 produced fewer than configured max links
* Generates natural, contextual sentences with additional internal links
* Optionally includes one authoritative external link
* Validates external URLs before caching
* Caches responses permanently for optimal performance

**Key Features**
* Works with any combination of post types (posts, pages, CPTs)
* Cross-post-type linking enabled
* Runtime injection via the_content filter - nothing stored in database
* Per-post keyword override capability
* Comprehensive settings with tabbed interface
* Preview mode for testing before deployment
* Instant removal by deactivating plugin

== Installation ==

1. Upload the `rs-smart-interlinker` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'RS Interlinker' in the admin menu
4. Configure your OpenRouter API key and settings
5. Select which post types should participate in interlinking

== Configuration ==

**General Tab**
* OpenRouter API Key - Required for AI features
* AI Model - Default: anthropic/claude-sonnet-4.5
* Max Internal Links Per Page - Default: 3
* Max External Links Per Page - Default: 1
* Enable External Linking - Toggle on/off

**Post Types Tab**
* Select which post types participate in the interlinking pool

**Keyword Sources Tab**
* Post Title (with prefix stripping)
* Tags
* Categories
* Custom Field

**Advanced Tab**
* External Link Rel (dofollow/nofollow)
* Clear All Cache button
* Rebuild Index button

== Frequently Asked Questions ==

= Do I need an OpenRouter API key? =

The API key is only required for Phase 2 AI-powered linking. Phase 1 natural keyword matching works without any API.

= Are links stored in my post content? =

No. All links are injected at runtime via the_content filter. Deactivating the plugin instantly removes all injected links.

= Can I override keywords for specific posts? =

Yes. Each post has a meta box where you can specify custom keywords that override automatic extraction.

= How do I refresh AI-generated content? =

Go to Advanced tab and click "Clear All Cache". The AI will regenerate content on the next page view.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of RS Smart Interlinker.
