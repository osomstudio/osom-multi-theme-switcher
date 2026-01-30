# Osom Multi Theme Switcher - Architecture

## Overview

The plugin follows WordPress coding standards and uses an object-oriented architecture with clear separation of concerns.

## File Structure

```
osom-multi-theme-switcher/
├── osom-multi-theme-switcher.php         # Main plugin file (bootstrap)
├── index.php                             # Security file
├── README.md                             # User documentation
├── ARCHITECTURE.md                       # This file
├── assets/                               # Frontend assets
│   ├── admin-style.css                   # Admin page styles
│   ├── admin-script.js                   # Admin page JavaScript
│   ├── admin-bar-style.css               # Admin bar styles
│   └── admin-bar-script.js               # Admin bar JavaScript
└── includes/                             # PHP classes
    ├── class-osom-multi-theme-switcher.php   # Main plugin class
    ├── class-omts-theme-switcher.php         # Theme switching logic
    ├── class-omts-admin-page.php             # Admin settings page
    ├── class-omts-admin-bar.php              # Admin bar menu
    ├── class-omts-ajax-handler.php           # AJAX request handlers
    └── class-omts-acf-loader.php             # ACF JSON loader
```

## Class Responsibilities

### Osom_Multi_Theme_Switcher
**File:** `includes/class-osom-multi-theme-switcher.php`

Main plugin class that initializes and coordinates all components.

**Responsibilities:**
- Load dependencies
- Initialize plugin components
- Provide singleton instance
- Define plugin constants

**Methods:**
- `get_instance()` - Returns singleton instance
- `load_dependencies()` - Loads all required class files
- `init_components()` - Initializes all plugin components

---

### OMTS_Theme_Switcher
**File:** `includes/class-omts-theme-switcher.php`

Core theme switching functionality.

**Responsibilities:**
- Apply theme filters
- Manage theme rules (CRUD operations)
- Check if rules match current request
- Handle admin theme preferences

**Methods:**
- `switch_theme_template()` - Filter for template theme
- `switch_theme_stylesheet()` - Filter for stylesheet theme
- `get_theme_for_current_request()` - Determines which theme to use
- `rule_matches()` - Checks if a rule matches current request
- `get_rules()` - Retrieves all theme rules
- `save_rules()` - Saves theme rules
- `get_admin_theme_preference()` - Gets user's admin theme preference
- `set_admin_theme_preference()` - Sets user's admin theme preference
- `get_theme_name()` - Gets theme display name

---

### OMTS_Admin_Page
**File:** `includes/class-omts-admin-page.php`

Manages the admin settings page interface.

**Responsibilities:**
- Register admin menu page
- Render admin settings interface
- Enqueue admin page assets
- Display theme rules table

**Methods:**
- `add_admin_menu()` - Registers admin menu
- `enqueue_admin_scripts()` - Loads CSS/JS for admin page
- `render_admin_page()` - Renders the settings page
- `render_page_row()` - Renders page selection field
- `render_post_row()` - Renders post selection field
- `render_post_type_row()` - Renders post type selection field
- `render_url_row()` - Renders URL input field
- `render_category_row()` - Renders category selection field
- `render_tag_row()` - Renders tag selection field
- `get_rule_target_display()` - Formats rule target for display

---

### OMTS_Admin_Bar
**File:** `includes/class-omts-admin-bar.php`

Handles the WordPress admin bar theme switcher menu.

**Responsibilities:**
- Add theme switcher to admin bar
- Enqueue admin bar assets
- Display current active theme

**Methods:**
- `add_admin_bar_menu()` - Adds menu to admin bar
- `enqueue_admin_bar_styles()` - Loads admin bar CSS
- `enqueue_admin_bar_scripts()` - Loads admin bar JavaScript

---

### OMTS_Ajax_Handler
**File:** `includes/class-omts-ajax-handler.php`

Processes all AJAX requests.

**Responsibilities:**
- Handle rule creation
- Handle rule deletion
- Handle admin theme switching
- Validate and sanitize AJAX data

**Methods:**
- `ajax_save_rules()` - Processes new rule creation
- `ajax_delete_rule()` - Processes rule deletion
- `ajax_switch_admin_theme()` - Processes admin theme change
- `get_rule_target_display()` - Formats rule target for AJAX response

---

### OMTS_ACF_Loader
**File:** `includes/class-omts-acf-loader.php`

Handles loading ACF JSON files from multiple themes.

**Responsibilities:**
- Add ACF JSON load points for all themes
- Ensure ACF fields are available regardless of active theme

**Methods:**
- `add_acf_json_load_points()` - Adds ACF JSON paths for all themes

---

## WordPress Coding Standards

The plugin follows these WordPress coding standards:

### Naming Conventions
- **Classes:** PascalCase with underscores (e.g., `OMTS_Admin_Page`)
- **Methods:** snake_case (e.g., `get_admin_theme_preference()`)
- **Variables:** snake_case (e.g., `$theme_switcher`)
- **Constants:** UPPERCASE with underscores (e.g., `OMTS_VERSION`)

### Documentation
- Every class has a file-level DocBlock
- Every method has a DocBlock with `@since`, `@param`, and `@return` tags
- Inline comments for complex logic

### Security
- All AJAX handlers use `check_ajax_referer()`
- All user inputs are sanitized with `sanitize_text_field()`, `intval()`, etc.
- All outputs are escaped with `esc_html()`, `esc_attr()`, `esc_js()`
- Direct file access prevented with `ABSPATH` checks

### Internationalization
- All user-facing strings wrapped in translation functions
- Text domain: `osom-multi-theme-switcher`
- Translation functions: `__()`, `esc_html__()`, `esc_attr__()`

### Code Organization
- Single Responsibility Principle - each class has one primary purpose
- Dependency Injection - dependencies passed via constructor
- Singleton Pattern - main plugin class uses singleton
- Hook Priority - admin bar menu added at priority 100

## Data Flow

### Frontend Theme Switching
1. User visits a page
2. WordPress loads active theme
3. `OMTS_Theme_Switcher` filters intercept theme loading
4. Rules are checked against current request
5. If match found, alternative theme is loaded

### Admin Theme Switching
1. User clicks theme in admin bar
2. JavaScript sends AJAX request
3. `OMTS_Ajax_Handler` validates and saves preference
4. Page reloads
5. `OMTS_Theme_Switcher` applies admin theme preference

### Rule Management
1. User fills form on settings page
2. JavaScript validates input
3. AJAX request sent to `OMTS_Ajax_Handler`
4. Rule saved to WordPress options
5. Table updated via JavaScript

## Performance Considerations

- Rules stored in single option (minimizes database queries)
- Admin components only loaded when `is_admin()`
- Assets only enqueued on relevant pages
- Singleton pattern prevents multiple initializations
- Early returns in conditionals reduce unnecessary processing

## Extensibility

The plugin can be extended through:

1. **Filters:**
   - `template` - Modify template theme
   - `stylesheet` - Modify stylesheet theme

2. **Actions:**
   - All WordPress standard hooks available

3. **Direct Access:**
   - `osom_multi_theme_switcher()` - Returns plugin instance
   - Access any component via the main instance

Example:
```php
$plugin = osom_multi_theme_switcher();
$rules = $plugin->theme_switcher->get_rules();
```

## Future Enhancements

Potential areas for expansion:

- Import/Export functionality
- Multisite support
- User role-based theme switching
- Time-based theme rules
- A/B testing capabilities
- Theme preview mode
