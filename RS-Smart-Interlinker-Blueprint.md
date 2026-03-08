# RS Smart Interlinker — Plugin Blueprint

## Overview

A universal WordPress plugin that automatically interlinks content across any combination of post types using AI-powered contextual linking. It works in two phases: first scanning content for natural keyword matches (pure PHP), then using Claude AI via OpenRouter to generate natural contextual sentences that fill remaining link slots plus one authoritative external link. All links are injected at runtime via `the_content` filter — nothing is written to the database, so deactivating the plugin instantly removes all injected links.

---

## Core Behaviour

### Runtime Flow (fires on every page load via `the_content` filter)

**Phase 1 — Natural Keyword Scan (PHP only, no API call):**

- Check if the current post belongs to a selected post type — if not, skip entirely.
- Load the keyword-to-URL index (stored as a WP option).
- Regex scan paragraph text for matches against other posts' keywords.
- Link the FIRST occurrence of each keyword match only.
- Skip: self-links (never link a page to itself), content inside existing `<a>` tags, headings (`<h1>`–`<h6>`), `<img>` tags, shortcodes, and code blocks.
- Handle possessives (e.g. "Marbella's" should still match "Marbella").
- Use whole-word matching to avoid partial matches.
- Count how many internal links were inserted.

**Phase 2 — AI Top-Up + External Link (Claude via OpenRouter):**

- Only fires if Phase 1 produced fewer than the configured max internal links.
- Check the transient cache for this post ID — if cached, use the cached response.
- If no cache exists, call Claude via OpenRouter with:
  - The current post's location/topic name.
  - The full keyword-to-URL index of all other posts.
  - How many internal top-up links are needed (max minus Phase 1 count).
  - Instructions to write ONE natural sentence that includes the top-up internal links plus 1 authoritative external link.
  - Instructions to never link to competitor real estate agencies.
  - Instructions to return exact location/keyword names so PHP can wrap them in `<a>` tags.
- Validate the external URL via a HEAD request — must return HTTP 200.
  - If the URL is dead, re-prompt Claude once for an alternative.
  - If still dead, omit the external link and just use internal links.
- Cache the AI response as a WP transient with NO expiry (manual refresh only).

**Phase 3 — Injection:**

- Append the AI-generated sentence to the last `<p>` tag in the content.
- Internal links: standard `<a href="...">keyword</a>`.
- External links: `<a href="..." target="_blank" rel="noopener">anchor text</a>`.

---

## Settings Page (Admin → RS Smart Interlinker)

Create a tabbed settings page under the WordPress admin menu.

### General Tab

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| OpenRouter API Key | text input (password masked) | empty | Required for AI features |
| AI Model | text input | `anthropic/claude-sonnet-4.5` | OpenRouter model string |
| Max Internal Links Per Page | number input | 3 | Cap on internal links injected per post |
| Max External Links Per Page | number input | 1 | Cap on external authority links per post |
| Enable External Linking | checkbox | checked | Toggle external links on/off |

### Post Types Tab

- Dynamically list ALL registered public post types (posts, pages, and all CPTs).
- Each gets a checkbox.
- User ticks which post types participate in the interlinking pool.
- Cross-post-type linking is enabled — a `popular-locations` page can link to a `post` and vice versa.

### Keyword Sources Tab

Each source is independently toggleable:

| Source | Toggle | Config |
|--------|--------|--------|
| Post Title | checkbox (default: on) | Text input for prefix stripping pattern, e.g. "Properties for sale in " |
| Tags | checkbox (default: off) | — |
| Categories | checkbox (default: off) | — |
| Custom Field | checkbox (default: off) | Text input to specify the meta field key |

When multiple sources are enabled, all extracted keywords map to the same post URL. Manual override keywords (from the per-post meta box) always take priority.

### Advanced Tab

| Setting | Type | Description |
|---------|------|-------------|
| External Link Rel | select: dofollow / nofollow | Default: dofollow |
| Clear All Cache | button | Deletes all cached AI responses (transients) |
| Rebuild Index | button | Re-scans all posts and rebuilds keyword-to-URL map |

### Preview Tab

- Dropdown listing all posts from selected post types.
- On selecting a post, fires an AJAX request that runs the full Phase 1 + Phase 2 logic in dry-run mode.
- Displays:
  - Natural keyword matches found (keyword → target URL).
  - AI-generated sentence (if applicable).
  - External link suggestion + validation status.
- Nothing is cached or saved during preview — purely informational.

### Status Dashboard (shown on General tab)

- Total pages indexed.
- Total cached AI responses.
- Pages with natural matches vs pages needing AI top-up.

---

## Per-Post Meta Box

- Appears on the edit screen of all selected post types.
- Title: "RS Smart Interlinker — Custom Keywords"
- Single textarea field for comma-separated keywords.
- These keywords override auto-extracted keywords for this post.
- If left empty, auto-extraction is used.
- Saved as post meta: `_rs_interlinker_keywords`.

---

## Keyword Index

- Stored as a WP option: `rs_interlinker_index`.
- Structure: associative array mapping keywords (lowercase) to `['url' => '...', 'post_id' => 123]`.
- Rebuilt when:
  - User clicks "Rebuild Index" in settings.
  - A post in a selected post type is saved/updated/deleted (hook into `save_post` and `delete_post`).
- Keyword extraction logic:
  1. Check for manual override meta (`_rs_interlinker_keywords`) — if set, use those.
  2. Otherwise, extract from enabled sources (title with prefix stripping, tags, categories, custom field).
  3. All keywords stored lowercase for case-insensitive matching.

---

## AI Engine — OpenRouter Integration

### API Call

```
POST https://openrouter.ai/api/v1/chat/completions
Headers:
  Authorization: Bearer {API_KEY}
  Content-Type: application/json

Body:
{
  "model": "{configured_model}",
  "messages": [
    {
      "role": "system",
      "content": "You are an SEO content assistant. Generate natural, human-readable sentences for internal linking on websites."
    },
    {
      "role": "user",
      "content": "{constructed prompt}"
    }
  ],
  "max_tokens": 300
}
```

### Prompt Template

```
I have a webpage about "{current_post_title}".

I need you to write ONE natural sentence that I can append to the page content. This sentence must:

1. Mention exactly {n} of the following related pages by using their exact keyword name from the list below. Choose the most geographically or topically relevant ones:

{keyword_list — format: "keyword | URL" per line, excluding current post}

2. Include exactly 1 external link to an authoritative, non-competitor website relevant to "{current_post_title}". Good sources: official municipality/government sites, Wikipedia, established tourism or travel sites. NEVER link to real estate agency websites.

3. Sound natural and fit within a real estate or property website context.

Return your response in this exact JSON format:
{
  "sentence": "Your natural sentence here with {keyword} placeholders for internal links",
  "internal_links": [
    {"keyword": "exact keyword", "url": "URL from the list"},
    ...
  ],
  "external_link": {
    "anchor_text": "descriptive anchor text",
    "url": "https://..."
  }
}

Return ONLY the JSON, no markdown fences, no explanation.
```

### Response Handling

1. Parse JSON response.
2. In the sentence, replace each `{keyword}` with `<a href="URL">keyword</a>`.
3. Replace external link placeholder with `<a href="URL" target="_blank" rel="noopener">anchor text</a>`.
4. Validate external URL via `wp_remote_head()` — accept only HTTP 200.
5. If external URL fails, re-prompt once. If still fails, remove external link from sentence.
6. Cache final HTML sentence as transient: `rs_interlinker_ai_{post_id}` with no expiry.

---

## URL Validator

- Uses `wp_remote_head()` with a 5-second timeout.
- Accepts only HTTP 200 responses.
- Called once per external URL before caching.
- If validation fails, the AI is re-prompted once for an alternative URL.
- If the retry also fails, the external link is omitted from the cached sentence.

---

## File Structure

```
rs-smart-interlinker/
├── rs-smart-interlinker.php          Main plugin file (bootstrap, hooks, activation/deactivation)
├── includes/
│   ├── class-settings.php            Admin settings page with tabs, AJAX handlers
│   ├── class-indexer.php             Keyword extraction and index builder
│   ├── class-linker.php              the_content filter — Phase 1 regex + Phase 3 injection
│   ├── class-ai-engine.php           OpenRouter API calls, prompt building, response parsing, caching
│   ├── class-url-validator.php       HEAD request validation for external URLs
│   ├── class-meta-box.php            Per-post keyword override meta box
│   └── class-preview.php             Preview/dry-run AJAX handler
├── assets/
│   ├── admin.css                     Settings page styling
│   └── admin.js                      Preview AJAX, tab switching, UI interactions
└── readme.txt                        Plugin readme
```

---

## Hooks & Filters

| Hook | Type | Purpose |
|------|------|---------|
| `the_content` | filter | Main injection point — runs Phase 1 + 2 + 3 |
| `save_post` | action | Rebuild index entry when a post in selected CPTs is saved |
| `delete_post` | action | Remove index entry when a post is deleted |
| `admin_menu` | action | Register settings page |
| `admin_enqueue_scripts` | action | Load admin CSS/JS on plugin settings page only |
| `wp_ajax_rs_interlinker_preview` | action | AJAX handler for preview mode |
| `wp_ajax_rs_interlinker_clear_cache` | action | AJAX handler for cache clearing |
| `wp_ajax_rs_interlinker_rebuild_index` | action | AJAX handler for index rebuild |

---

## Important Rules

1. **Never link a page to itself.**
2. **Never modify the database** — all links are runtime-injected via `the_content` filter.
3. **Only link within `<p>` tags** — skip headings, existing links, images, shortcodes, code blocks.
4. **First occurrence only** — each keyword is linked maximum once per page.
5. **Whole-word matching** — "Marbella" should not match inside "MarbellaVista" but should match "Marbella's" (possessive).
6. **Case-insensitive matching** for keyword scanning.
7. **Longer keywords first** — when scanning, sort keywords by length descending so "Nueva Andalucia" matches before "Nueva" alone.
8. **External links always get** `target="_blank" rel="noopener"`.
9. **Cache AI responses permanently** — only refresh on manual "Clear Cache" action.
10. **Deactivating the plugin removes all injected links instantly** since nothing is stored in post content.

---

## Activation / Deactivation

**On Activation:**
- Create default options (empty API key, default model, max links = 3, max external = 1).
- Trigger initial index build.

**On Deactivation:**
- Optionally clear all transient caches (add a setting: "Clear cache on deactivation" checkbox).
- Do NOT delete settings/options (in case user reactivates).

**On Uninstall (uninstall.php):**
- Delete all plugin options.
- Delete all transients with prefix `rs_interlinker_`.
- Delete all post meta `_rs_interlinker_keywords`.
