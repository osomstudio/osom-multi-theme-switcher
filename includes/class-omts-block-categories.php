<?php
/**
 * Block Categories Class
 *
 * Groups ACF blocks in the inserter by the theme they come from, and marks the
 * ones that exist in more than one theme.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Block_Categories
 *
 * @since 1.3.0
 */
class OMTS_Block_Categories {

	/**
	 * Category slug for blocks that exist in several themes.
	 *
	 * @var string
	 */
	const SHARED_CATEGORY = 'omts-shared';

	/**
	 * Cached map of theme stylesheet => block slugs.
	 *
	 * @var array|null
	 */
	private $block_map = null;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		add_filter( 'block_categories_all', array( $this, 'register_categories' ), 20 );
		add_filter( 'acf/register_block_type_args', array( $this, 'assign_block_category' ) );
		add_action( 'switch_theme', array( $this, 'flush_cache' ) );
		add_action( 'upgrader_process_complete', array( $this, 'flush_cache' ) );
	}

	/**
	 * Build a map of which theme provides which block template.
	 *
	 * @since 1.3.0
	 *
	 * @return array Map of stylesheet => array of block slugs.
	 */
	public function get_block_map() {
		if ( null !== $this->block_map ) {
			return $this->block_map;
		}

		$cached = get_transient( 'omts_theme_block_map' );

		if ( is_array( $cached ) ) {
			$this->block_map = $cached;
			return $this->block_map;
		}

		/**
		 * Filter the directories, relative to a theme root, scanned for block templates.
		 *
		 * @since 1.3.0
		 *
		 * @param array $dirs Relative directory paths.
		 */
		$dirs = apply_filters( 'omts_block_template_dirs', array( 'views/blocks' ) );
		$map  = array();

		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$slugs = array();

			foreach ( $dirs as $dir ) {
				$path = $theme->get_stylesheet_directory() . '/' . trim( $dir, '/' );

				if ( ! is_dir( $path ) ) {
					continue;
				}

				foreach ( (array) glob( $path . '/*.twig' ) as $file ) {
					$slugs[] = basename( $file, '.twig' );
				}
			}

			if ( $slugs ) {
				$map[ $stylesheet ] = array_values( array_unique( $slugs ) );
			}
		}

		set_transient( 'omts_theme_block_map', $map, DAY_IN_SECONDS );

		$this->block_map = $map;

		return $this->block_map;
	}

	/**
	 * Drop the cached block map.
	 *
	 * @since 1.3.0
	 */
	public function flush_cache() {
		$this->block_map = null;
		delete_transient( 'omts_theme_block_map' );
	}

	/**
	 * Themes that ship at least one block template.
	 *
	 * @since 1.3.0
	 *
	 * @return array Stylesheets.
	 */
	private function get_themes_with_blocks() {
		return array_keys( $this->get_block_map() );
	}

	/**
	 * Category slug for a theme.
	 *
	 * @since 1.3.0
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return string Category slug.
	 */
	public static function get_theme_category_slug( $stylesheet ) {
		return 'omts-theme-' . sanitize_key( $stylesheet );
	}

	/**
	 * Register one inserter category per theme, plus the shared one.
	 *
	 * @since 1.3.0
	 *
	 * @param array $categories Existing categories.
	 * @return array Categories with the theme ones prepended.
	 */
	public function register_categories( $categories ) {
		$themes = $this->get_themes_with_blocks();

		if ( count( $themes ) < 1 ) {
			return $categories;
		}

		$new = array();

		foreach ( $themes as $stylesheet ) {
			$theme = wp_get_theme( $stylesheet );

			$new[] = array(
				'slug'  => self::get_theme_category_slug( $stylesheet ),
				'title' => sprintf(
					/* translators: %s: Theme name */
					__( 'Blocks: %s', 'osom-multi-theme-switcher' ),
					$theme->exists() ? $theme->get( 'Name' ) : $stylesheet
				),
				'icon'  => null,
			);
		}

		$new[] = array(
			'slug'  => self::SHARED_CATEGORY,
			'title' => __( 'Blocks: shared by several themes', 'osom-multi-theme-switcher' ),
			'icon'  => null,
		);

		return array_merge( $new, $categories );
	}

	/**
	 * Put every ACF block into the category of the theme it came from.
	 *
	 * Blocks are registered from the active theme, so that theme is the source.
	 * A block whose template also exists in another theme is marked as shared, so
	 * editors can tell "this exists everywhere" from "this belongs to this theme".
	 *
	 * @since 1.3.0
	 *
	 * @param array $args Block registration arguments.
	 * @return array Modified arguments.
	 */
	public function assign_block_category( $args ) {
		if ( empty( $args['name'] ) ) {
			return $args;
		}

		$map    = $this->get_block_map();
		$source = $this->get_source_theme( $args['name'], $map );

		if ( ! $source ) {
			return $args;
		}

		$owners = 0;

		foreach ( $map as $slugs ) {
			if ( in_array( $args['name'], $slugs, true ) ) {
				$owners++;
			}
		}

		if ( $owners > 1 ) {
			$args['category'] = self::SHARED_CATEGORY;

			/**
			 * Filter the prefix added to blocks that exist in several themes.
			 *
			 * @since 1.3.0
			 *
			 * @param string $prefix Prefix, including the trailing space.
			 */
			$prefix = apply_filters( 'omts_shared_block_prefix', __( '[Shared] ', 'osom-multi-theme-switcher' ) );

			if ( ! empty( $args['title'] ) && $prefix && 0 !== strpos( $args['title'], $prefix ) ) {
				$args['title'] = $prefix . $args['title'];
			}
		} else {
			$args['category'] = self::get_theme_category_slug( $source );
		}

		return $args;
	}

	/**
	 * Which theme provides a block template.
	 *
	 * @since 1.3.0
	 *
	 * @param string $block_slug Block slug.
	 * @param array  $map        Theme block map.
	 * @return string|false Stylesheet or false.
	 */
	private function get_source_theme( $block_slug, $map ) {
		// locate_template() checks the child theme first, then the parent.
		foreach ( array( get_stylesheet(), get_template() ) as $stylesheet ) {
			if ( isset( $map[ $stylesheet ] ) && in_array( $block_slug, $map[ $stylesheet ], true ) ) {
				return $stylesheet;
			}
		}

		return false;
	}
}
