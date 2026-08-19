<?php
/**
 * ACF Admin Class
 *
 * Shows which theme each ACF field group comes from, and warns about groups that
 * share a key across themes — where one silently overwrites the other.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_ACF_Admin
 *
 * @since 1.3.0
 */
class OMTS_ACF_Admin {

	/**
	 * Cached map of field group key => themes providing it.
	 *
	 * @var array|null
	 */
	private $group_map = null;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		if ( ! class_exists( 'ACF' ) ) {
			return;
		}

		add_filter( 'manage_acf-field-group_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_acf-field-group_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'render_collision_notice' ) );
		add_action( 'admin_head', array( $this, 'render_column_styles' ) );
	}

	/**
	 * Map every field group key to the themes that define it.
	 *
	 * @since 1.3.0
	 *
	 * @return array Map of key => array of stylesheets.
	 */
	public function get_group_map() {
		if ( null !== $this->group_map ) {
			return $this->group_map;
		}

		$cached = get_transient( 'omts_acf_group_map' );

		if ( is_array( $cached ) ) {
			$this->group_map = $cached;
			return $this->group_map;
		}

		$map = array();

		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$dir = $theme->get_stylesheet_directory() . '/acf-json';

			if ( ! is_dir( $dir ) ) {
				continue;
			}

			foreach ( (array) glob( $dir . '/*.json' ) as $file ) {
				$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

				if ( ! is_array( $data ) || empty( $data['key'] ) ) {
					continue;
				}

				$key = $data['key'];

				if ( ! isset( $map[ $key ] ) ) {
					$map[ $key ] = array();
				}

				$map[ $key ][] = $stylesheet;
			}
		}

		set_transient( 'omts_acf_group_map', $map, DAY_IN_SECONDS );

		$this->group_map = $map;

		return $this->group_map;
	}

	/**
	 * Add the theme column to the field group list.
	 *
	 * @since 1.3.0
	 *
	 * @param array $columns Existing columns.
	 * @return array Columns with the theme column added.
	 */
	public function add_column( $columns ) {
		$columns['omts_theme'] = __( 'Theme', 'osom-multi-theme-switcher' );

		return $columns;
	}

	/**
	 * Render the theme column.
	 *
	 * @since 1.3.0
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Field group post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'omts_theme' !== $column ) {
			return;
		}

		$post = get_post( $post_id );
		$map  = $this->get_group_map();
		$key  = $post ? $post->post_name : '';

		if ( ! $key || empty( $map[ $key ] ) ) {
			printf(
				'<span class="omts-acf-theme omts-acf-theme--db">%s</span>',
				esc_html__( 'Database only', 'osom-multi-theme-switcher' )
			);
			return;
		}

		$names = array();

		foreach ( $map[ $key ] as $stylesheet ) {
			$theme   = wp_get_theme( $stylesheet );
			$names[] = $theme->exists() ? $theme->get( 'Name' ) : $stylesheet;
		}

		printf(
			'<span class="omts-acf-theme%s">%s</span>',
			count( $names ) > 1 ? ' omts-acf-theme--conflict' : '',
			esc_html( implode( ', ', $names ) )
		);
	}

	/**
	 * Warn about field groups defined with the same key in several themes.
	 *
	 * @since 1.3.0
	 */
	public function render_collision_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-acf-field-group' !== $screen->id ) {
			return;
		}

		$collisions = array();

		foreach ( $this->get_group_map() as $key => $themes ) {
			if ( count( $themes ) > 1 ) {
				$collisions[ $key ] = $themes;
			}
		}

		if ( ! $collisions ) {
			return;
		}

		$lines = array();

		foreach ( $collisions as $key => $themes ) {
			$lines[] = sprintf( '%s (%s)', $key, implode( ', ', $themes ) );
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong></p><p>%s</p><p>%s</p></div>',
			esc_html__( 'Osom Multi Theme Switcher: field groups collide across themes', 'osom-multi-theme-switcher' ),
			esc_html__( 'These field group keys are defined in more than one theme. Only one definition is loaded, so fields from the other theme are silently ignored:', 'osom-multi-theme-switcher' ),
			esc_html( implode( ' · ', $lines ) )
		);
	}

	/**
	 * Style the theme column.
	 *
	 * @since 1.3.0
	 */
	public function render_column_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-acf-field-group' !== $screen->id ) {
			return;
		}

		echo '<style>.omts-acf-theme{display:inline-block;padding:2px 8px;border-radius:2px;background:#f0f0f1;font-size:12px}.omts-acf-theme--db{color:#646970;background:transparent;padding-left:0}.omts-acf-theme--conflict{background:#fcf0f1;color:#8a2424}</style>';
	}
}
