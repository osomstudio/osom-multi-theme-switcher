<?php
/**
 * Plugin Name:       Osom Multi Theme Switcher
 * Plugin URI:        https://github.com/osomstudio/osom-multi-theme-switcher
 * Description:       Allows you to use different themes for specific pages, posts, or URLs while keeping a main theme active.
 * Version:           1.3.0
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Author:            OsomStudio, bartnovak, tomzielinski, Gudis24, rainkom
 * Author URI:        https://osomstudio.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       osom-multi-theme-switcher
 *
 * @package Osom_Multi_Theme_Switcher
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if ( ! defined( 'OMTS_VERSION' ) ) {
	define( 'OMTS_VERSION', '1.3.0' );
}

if ( ! defined( 'OMTS_PLUGIN_DIR' ) ) {
	define( 'OMTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'OMTS_PLUGIN_URL' ) ) {
	define( 'OMTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'OMTS_PLUGIN_FILE' ) ) {
	define( 'OMTS_PLUGIN_FILE', __FILE__ );
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 *
 * @return Osom_Multi_Theme_Switcher
 */
function osom_multi_theme_switcher() {
	require_once OMTS_PLUGIN_DIR . 'includes/class-osom-multi-theme-switcher.php';
	return Osom_Multi_Theme_Switcher::get_instance();
}

/**
 * Load the plugin's translations.
 *
 * Runs on init, because WordPress 6.7+ warns when a text domain is loaded before
 * that point.
 *
 * @since 1.3.0
 */
function omts_load_textdomain() {
	load_plugin_textdomain(
		'osom-multi-theme-switcher',
		false,
		dirname( plugin_basename( OMTS_PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'omts_load_textdomain' );

// Initialize the plugin.
osom_multi_theme_switcher();
