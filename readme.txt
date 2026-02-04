=== Osom Multi Theme Switcher ===
Contributors: osomstudio, bartosznowak, tomziel, kamiljanq, rainkom
Tags: theme switcher, multiple themes, theme per page, conditional themes
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.2
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Use different themes for specific pages, posts, or URLs while keeping your main theme active site-wide.

== Description ==

Osom Multi Theme Switcher allows WordPress administrators to apply different themes to specific pages, posts, post types, categories, tags, or custom URLs without changing the main active theme.

**Perfect for:**

* Landing pages that need a unique design
* WooCommerce stores using a specialized shop theme
* Membership sites with different themed sections
* Testing themes on specific pages before full deployment
* Agencies managing multi-purpose WordPress sites

= Features =

* **Multiple Rule Types** - Create rules based on pages, posts, post types, custom URLs, categories, or tags
* **Admin Dashboard Theme Switcher** - Switch between themes in the WordPress admin area to access theme-specific settings
* **Per-User Admin Theme** - Each admin user can view the dashboard with their preferred theme without affecting others
* **Draft & Scheduled Support** - Apply themes to draft, pending, private, and scheduled content
* **REST API Support** - Configure custom REST API prefixes per theme
* **ACF Compatible** - Loads Advanced Custom Fields JSON from all theme directories
* **No Coding Required** - Configure everything through the intuitive WordPress admin panel
* **Real-time Updates** - Add and remove rules instantly with AJAX

= How It Works =

1. Install and activate the plugin
2. Go to Appearance > Theme Switcher
3. Select a rule type (Page, Post, Category, etc.)
4. Choose the specific content to target
5. Select the alternative theme
6. Click Add Rule

The plugin hooks into WordPress early in the loading process to ensure the correct theme's functions.php is loaded, providing full theme compatibility.

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

== Screenshots ==

1. Admin settings page - Add new theme rules and manage existing ones
2. Active theme rules table - View and delete your configured rules
3. Admin bar theme switcher - Quickly switch themes in the WordPress dashboard
4. Rule type selection - Choose from pages, posts, post types, URLs, categories, or tags
5. REST API prefix configuration - Set custom REST prefixes per theme

== Changelog ==

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
