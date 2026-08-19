# Osom Multi Theme Switcher

Run different themes for selected pages, posts, terms or addresses while the rest of the site
stays on your main theme — and see, everywhere in the admin, which theme handles what.

## How it works

1. Your site runs on one **main theme**.
2. Every **rule** is an exception: it points a page, a content type, a term or an address at a different theme.
3. Rules are checked from top to bottom — **the first rule that matches wins**. Drag rules to change the order.
4. The theme switcher in the admin bar changes **your dashboard view only**. Visitors never see it.

## Features

### Rules

- **Rule types**: page, post, custom post type (all items or a single one), category, tag, custom taxonomy, address (URL or slug)
- **Order is priority** — drag and drop, with the position shown in the list
- **Address rules** match either the exact address or the address and everything below it
- **Edit** a rule instead of deleting and recreating it
- **Pause** a rule without deleting it — the fastest way to check whether it causes a problem
- **Conflict detection**: an identical rule is refused, and a broader rule that would win first raises a warning
- **Undo** after deleting a rule, instead of a confirmation dialog
- **Status sync**: rules follow the item when it moves between draft, pending, private, scheduled and published
- **Self-cleaning**: rules pointing at deleted content or a missing theme are reported and can be removed in one click

### Seeing which theme is in play

- **Editor sidebar panel** "Theme for this item" — the theme the item renders in and the rule behind it
- **Mismatch warning** in the editor when the dashboard runs a different theme than the item will use, with a one-click fix
- **The editor follows the rule** — an item opens in the theme it renders in, so blocks and fields match the frontend
- **Theme column** on the pages/posts lists, a filter for items with a rule, and a bulk "Assign theme" action
- **Frontend admin bar**: the theme that rendered the page, the rule that decided it, the resolved stylesheet/template and how the match was made
- **Address tester** on the settings screen: paste a URL, get the theme and the rule that matched

### Blocks and fields across themes

- ACF field groups are loaded from **every** theme, so the editor works whichever theme is active
- ACF blocks are grouped in the inserter by the theme they come from, plus a **shared** category for blocks that exist in several themes
- The ACF field group list shows which theme each group comes from
- Field groups that use the same key in more than one theme are reported — otherwise one silently overwrites the other

### Other

- **Dashboard theme switcher** (per user) to reach the settings pages of a theme that is not active site-wide
- **CPT registry** so URL matching keeps working when a different theme is active
- **REST API prefixes** per theme (advanced), for themes with clashing REST routes
- Fully translatable; ships with a Polish translation

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate it through the Plugins screen
3. Go to **Appearance → Theme Switcher**

## Usage

### Adding a rule

1. **Appearance → Theme Switcher**
2. Choose what the rule applies to (page, post, content type, term, address)
3. Choose the target
4. Choose the theme
5. **Add rule**

Rules are checked from the top. If a rule never seems to fire, check whether an earlier rule already
covers the same content — the settings screen warns about this when you add one.

### Checking what a URL will do

Paste the address into **Test an address**. The answer names the theme and the rule that matched,
including its position in the list.

### Switching the dashboard theme

Use the admin bar menu. It applies to your user only and never changes what visitors see; it exists so
you can reach theme settings pages of a theme that is not active site-wide. "Back to the main theme"
returns you to the site's theme.

## Technical details

Theme switching runs through:

- `setup_theme` — resolves the theme before `functions.php` is loaded
- `template` / `stylesheet` and `pre_option_template` / `pre_option_stylesheet` filters
- Early URL matching against the database, for requests handled before `WP_Query` exists

Rules live in the `omts_theme_rules` option. The per-user dashboard theme lives in the `omts_admin_theme`
user meta.

### Filters

| Filter | Purpose |
| --- | --- |
| `omts_block_template_dirs` | Directories, relative to a theme, scanned for block templates (default `views/blocks`) |
| `omts_shared_block_prefix` | Prefix added to blocks that exist in several themes (default `[Shared] `) |
| `omts_editor_follows_rule` | Whether opening an item switches the editor to the item's theme (default `true`) |

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## License

GPL v2 or later

## Changelog

### 1.3.0

**Understanding what the plugin is doing**

- Added a "How this plugin works" panel and contextual help to the settings screen
- Renamed the form fields ("Rule Object" / "Rule Item" are now named after what they select)
- The admin bar switcher now says it changes the dashboard view only, and offers "Back to the main theme"
- Replaced every `alert()` / `confirm()` with inline admin notices; deleting a rule can be undone
- Added translation files (`.pot`, Polish) and `load_plugin_textdomain()` — the text domain was never actually loaded before

**Seeing the theme in context**

- Added the editor sidebar panel "Theme for this item"
- Added a mismatch warning in the editor with a one-click switch
- The editor now opens an item in the theme its rule points to, so blocks and fields match the frontend (filterable with `omts_editor_follows_rule`)
- Added a Theme column, a rule filter and a bulk "Assign theme" action to the post lists
- The frontend admin bar now reports the theme, the matching rule and how the match was made

**Rules**

- Added the address tester
- Rule order is now visible and can be changed by dragging
- Added conflict detection when saving a rule
- Address rules can match the exact address only (new default) or include sub-paths (previous behaviour, kept for existing rules)
- Rules can be edited and paused
- Broken rules are reported and can be removed in one click; rules are dropped when their content is deleted
- Fixed address rules being matched against the query string as well as the path

**Blocks and fields**

- ACF blocks are grouped by theme in the inserter, with a shared category
- The ACF field group list shows the source theme and reports key collisions between themes

**Housekeeping**

- One version constant across the plugin — asset URLs are versioned again, so browsers stop serving stale CSS/JS
- Removed a no-op `rest_pre_dispatch` hook
- REST prefix settings moved under "Advanced"
- Removed the duplicated rule-display code from the admin page and the AJAX handler

### 1.2.3

- Added a guard against infinite `pre_option` recursion, fixing child theme setups

### 1.2.2

- Fix bug with object selectors

### 1.2.1

- Added custom post type support (all items or individual items)
- Added custom taxonomy support
- Added category and tag rule types
- Added CPT registry for cross-theme URL matching
- Added status sync - rules auto-update when post status changes
- Added early matching for all rule types (before WP_Query)
- Added cascading selectors in admin UI

### 1.0.0

- Initial release
