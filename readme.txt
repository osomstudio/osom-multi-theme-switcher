=== Osom Multi Theme Switcher ===
Contributors: osomstudio, bartosznowak, tomziel, kamiljanq, rainkom
Tags: multiple themes, theme switcher, theme per page, woocommerce theme, landing page theme, multi theme, conditional theme, theme rules
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.2.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use different themes for specific pages, posts, or URLs while keeping your main theme active site-wide.

== Description ==

Osom Multi Theme Switcher lets you run multiple WordPress themes on a single site — assigning different themes to specific pages, posts, post types, categories, tags, or custom URLs.

Built by [Osom Studio](https://www.osomstudio.com), a WordPress & WooCommerce agency with 10+ years of experience managing complex multi-theme setups for clients.

We built this plugin because we kept solving the same problem for clients: one WordPress installation, multiple designs. Landing pages that need a completely different look. A WooCommerce store that runs a separate theme from the corporate site. A membership area with its own design system.

Instead of hacking theme conditionals into functions.php every time, we packaged our solution into a plugin.

= When you need this =

* **Landing pages** with a unique design — without touching your main theme
* **WooCommerce stores** running a dedicated shop theme alongside a corporate theme
* **Membership or gated sections** with a separate visual identity
* **Theme testing** — preview a new theme on specific pages before switching site-wide
* **Agencies managing multi-brand WordPress installations** from a single dashboard

= Key features =

* **Flexible rules** — assign themes by page, post, post type, custom URL, category, or tag
* **Full theme loading** — the alternative theme's functions.php loads completely, so page builders (Elementor, Divi, Beaver Builder) and custom functionality work as expected
* **Admin theme switcher** — access settings for any installed theme directly from the admin bar
* **Per-user admin theme** — each administrator can use their preferred dashboard theme independently
* **Draft & scheduled support** — apply themes to unpublished content for preview and staging
* **REST API support** — configure custom REST API prefixes per theme
* **ACF compatible** — loads Advanced Custom Fields JSON from all active theme directories
* **No code required** — set up everything through the WordPress admin panel

= How it works =

1. Install and activate the plugin
2. Go to **Appearance > Theme Switcher**
3. Select a rule type (Page, Post, Post Type, Category, Tag, or Custom URL)
4. Choose the content to target
5. Pick the alternative theme
6. Click **Add Rule** — changes apply immediately

The plugin hooks into WordPress on the `setup_theme` action, before any theme code runs. This ensures full compatibility with theme features, widgets, customizer settings, and page builders.

== Installation ==

1. Upload the `osom-multi-theme-switcher` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Appearance > Theme Switcher to configure your theme rules

== Frequently Asked Questions ==

= Does this affect my site's main theme? =

No. Your main active theme remains unchanged for all pages that don't have a specific rule. Only the pages, posts, or URLs you configure will use an alternative theme.

= Can each admin user have their own dashboard theme? =

Yes. The admin bar theme switcher stores preferences per user. Each administrator can view the WordPress dashboard with their preferred theme without affecting other users or the frontend.

= Does the alternative theme's functions.php load? =

Yes. The plugin switches themes early in the WordPress loading process (on the `setup_theme` hook), ensuring the alternative theme's functions.php and all its features load correctly.

= Will this work with my page builder? =

Yes. Since the full theme is loaded (not just styles), page builders like Elementor, Divi, and Beaver Builder work correctly with the alternative theme.

= Can I apply a theme to all products in WooCommerce? =

Yes. Use the "Post Type" rule type and select "Products" to apply a theme to all WooCommerce products and the shop archive.

= Does it work with caching plugins? =

Most caching plugins work correctly. However, if you experience issues, you may need to exclude pages with alternative themes from the cache or use a caching plugin that supports conditional caching.

= Can I use this for A/B testing themes? =

While not designed specifically for A/B testing, you can apply different themes to specific URLs and manually direct traffic to test user preferences.

= Is this plugin compatible with WordPress multisite? =

The plugin is designed for single-site WordPress installations. For multisite setups, WordPress already provides per-site theme management. If you have a specific multisite use case, let us know through the support forum.

== Screenshots ==

1. Admin settings page - Add new theme rules and manage existing ones
2. Rule type selection - Choose from pages, posts, post types, URLs, categories, or tags
3. Admin bar theme switcher - Quickly switch themes in the WordPress dashboard
4. Side-by-side comparison of themes before/after theme switch

= Built by Osom Studio =

We're a WordPress & WooCommerce agency that builds, audits, and maintains complex WordPress sites. This plugin was born from real client projects — not as a side experiment, but as a tool we use in production.

If you find a bug or have a feature request, [let us know on GitHub](https://github.com/osomstudio/osom-multi-theme-switcher) or through the support forum.

== Changelog ==

= 1.2.2 =
* Fix bug with object selectors

= 1.2.1 =
* Added cascading selector UI for adding theme rules (Custom Post Type, Taxonomy support)
* Added automatic status synchronization (OMTS_Status_Sync) — rules update when post status changes
* Added CPT/taxonomy registry for re-registering missing CPTs across themes (OMTS_Theme_Switcher)
* Added early URL matching for categories, tags, custom taxonomies, post types, and CPT items
* Added is_query_ready() guard to prevent rule matching before WP_Query is available
* Added theme existence validation before switching template/stylesheet
* Added get_rule_type_display() shared static method for consistent rule type labels
* Added new AJAX handlers: omts_get_rule_objects, omts_get_rule_items

= 1.0.3 =
* readme.txt and screenshot changes

= 1.0.1 =
* Removed deprecated load_plugin_textdomain() call - translations are now handled automatically by WordPress.org
* Removed Domain Path header pointing to non-existent languages folder

= 1.0.0 =
* Initial release
* Support for pages, posts, post types, custom URLs, categories, and tags
* Support for draft, pending, private, and scheduled content
* AJAX-powered admin interface for real-time rule management
* Admin dashboard theme switcher in the top admin bar
* Per-user admin theme preferences stored in user meta
* REST API custom prefix support per theme
* ACF JSON loading from all installed themes
* Early theme switching via setup_theme hook for full compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Osom Multi Theme Switcher.
