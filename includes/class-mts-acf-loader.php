<?php
/**
 * ACF JSON Loader for Multi-Theme Setup
 *
 * Handles loading ACF JSON files from multiple themes.
 *
 * @package Multi_Theme_Switcher
 * @since   1.0.1
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MTS_ACF_Loader
 *
 * @since 1.0.1
 */
class MTS_ACF_Loader {

	/**
	 * Constructor.
	 *
	 * @since 1.0.1
	 */
	public function __construct() {
		// Only initialize if ACF is active
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		add_filter( 'acf/settings/load_json', array( $this, 'add_acf_json_load_points' ) );
	}

	/**
	 * Add multiple ACF JSON load points for all themes.
	 *
	 * This ensures ACF fields are loaded from all themes,
	 * regardless of which theme is currently active.
	 *
	 * @since 1.0.1
	 *
	 * @param array $paths Existing ACF JSON load paths.
	 * @return array Modified paths with additional theme paths.
	 */
	public function add_acf_json_load_points( $paths ) {
		// Get all themes
		$themes = wp_get_themes();
		
		foreach ( $themes as $theme_slug => $theme ) {
			// Build path using theme's stylesheet directory
			$acf_json_path = $theme->get_stylesheet_directory() . '/acf-json';
			
			// Only add path if acf-json directory exists and is not already in paths
			if ( is_dir( $acf_json_path ) && ! in_array( $acf_json_path, $paths, true ) ) {
				$paths[] = $acf_json_path;
			}
		}
		
		return $paths;
	}
}
