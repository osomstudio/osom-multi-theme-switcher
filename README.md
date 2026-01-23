# Multi Theme Switcher

A WordPress plugin that allows you to use different themes for specific pages, posts, or URLs while keeping your main theme active site-wide.

## Features

- **Multiple Rule Types**: Create rules based on:
  - Individual Pages
  - Individual Posts
  - Post Types (e.g., all Products)
  - Custom URLs/Slugs
  - Categories
  - Tags

- **Admin Dashboard Theme Switcher**: Switch between themes in the WordPress admin area from the top admin bar to access theme-specific settings
- **Easy-to-Use Dashboard**: Intuitive admin interface under Appearance > Theme Switcher
- **No Coding Required**: Configure everything through the WordPress admin panel
- **Real-time Updates**: Add and remove rules instantly with AJAX
- **Per-User Admin Theme**: Each admin user can view the dashboard with their preferred theme
- **Compatible**: Works with any WordPress theme

## Installation

1. Upload the `multi-theme-switcher` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Appearance > Theme Switcher to configure your rules

## Usage

### Switching Themes in Admin Dashboard

1. Look at the **top admin bar** in your WordPress dashboard
2. You'll see a menu item with a theme icon and the current theme name (e.g., "Theme: Astra")
3. Click on it to see all installed themes
4. Click any theme to instantly switch to it in the admin area
5. The page will reload with the selected theme applied to the dashboard
6. To return to your site's default theme, select it from the dropdown (marked as "Site Default")

**Why use this?** Many themes add their own settings pages to the WordPress admin. By switching themes in the dashboard, you can access and configure settings for any installed theme without activating it site-wide.

**Important Notes:**
- This is per-user, so each admin can choose their preferred dashboard theme without affecting other users
- **The frontend (visitor-facing site) always uses your main active theme** - this admin switcher only affects what you see in the WordPress dashboard
- Theme-specific admin pages, customizer options, and settings panels will be available for the selected theme
- Your actual site visitors will never see the admin theme selection - they only see the main theme or frontend rules you configure

### Adding a Theme Rule

1. Navigate to **Appearance > Theme Switcher** in your WordPress admin
2. Select the **Rule Type** (Page, Post, Post Type, etc.)
3. Choose the specific target (e.g., which page or post)
4. Select the **Alternative Theme** you want to use
5. Click **Add Rule**

### Rule Types Explained

- **Page**: Apply a theme to a specific page
- **Post**: Apply a theme to a specific blog post
- **Post Type**: Apply a theme to all posts of a certain type (e.g., all WooCommerce products)
- **Custom URL/Slug**: Apply a theme to a custom URL or slug (e.g., `/special-landing` or `about-us`)
- **Category**: Apply a theme to all posts in a category or the category archive page
- **Tag**: Apply a theme to all posts with a tag or the tag archive page

### Deleting Rules

Simply click the **Delete** button next to any rule in the Active Theme Rules table.

## Examples

### Example 1: Different Theme for About Page
- Rule Type: Page
- Select Page: About Us
- Alternative Theme: Twenty Twenty-Five

### Example 2: Special Theme for Products
- Rule Type: Post Type
- Select Post Type: Products
- Alternative Theme: Storefront

### Example 3: Custom Landing Page
- Rule Type: Custom URL/Slug
- Custom URL: /promo
- Alternative Theme: Landing Theme

## Technical Details

The plugin uses WordPress filters to switch themes:
- `template` filter - Changes the parent theme
- `stylesheet` filter - Changes the child theme/stylesheet

Rules are stored in the WordPress options table and checked on every page load.

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Support

For issues, questions, or contributions, please visit the plugin repository.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Support for pages, posts, post types, URLs, categories, and tags
- AJAX-powered admin interface
- Dynamic rule management
- Admin dashboard theme switcher in top admin bar
- Per-user admin theme preferences
- Access theme-specific settings without activating themes site-wide
