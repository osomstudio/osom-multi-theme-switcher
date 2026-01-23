<?php
/**
 * Plugin Name:       Multi Theme Switcher
 * Plugin URI:        https://github.com/osomstudio/multi-theme-switcher
 * Description:       Allows you to use different themes for specific pages, posts, or URLs while keeping a main theme active.
 * Version:           1.0.1
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Author:            OsomStudio, bartnovak, tomzielinski, Gudis24, rainkom
 * Author URI:        https://osomstudio.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       multi-theme-switcher
 *
 * @package Multi_Theme_Switcher
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if ( ! defined( 'MTS_VERSION' ) ) {
	define( 'MTS_VERSION', '1.0.1' );
}

if ( ! defined( 'MTS_PLUGIN_DIR' ) ) {
	define( 'MTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MTS_PLUGIN_URL' ) ) {
	define( 'MTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'MTS_PLUGIN_FILE' ) ) {
	define( 'MTS_PLUGIN_FILE', __FILE__ );
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 *
 * @return Multi_Theme_Switcher
 */
function multi_theme_switcher() {
	require_once MTS_PLUGIN_DIR . 'includes/class-multi-theme-switcher.php';
	return Multi_Theme_Switcher::get_instance();
}

// Initialize the plugin.
multi_theme_switcher();
