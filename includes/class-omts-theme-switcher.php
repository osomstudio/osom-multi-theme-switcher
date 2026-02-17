<?php
/**
 * Theme Switcher Class
 *
 * Handles theme switching logic for both frontend and admin.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Theme_Switcher
 *
 * @since 1.0.0
 */
class OMTS_Theme_Switcher {

	/**
	 * Option name for storing theme rules.
	 *
	 * @var string
	 */
	private $option_name = 'omts_theme_rules';

	/**
	 * Option name for storing theme REST prefix mappings.
	 *
	 * @var string
	 */
	private $rest_prefix_option_name = 'omts_theme_rest_prefixes';

	/**
	 * Option name for storing per-theme CPT/taxonomy registry.
	 *
	 * @var string
	 */
	private $theme_registry_option = 'omts_theme_object_registry';

	/**
	 * Flag to prevent recursion in filter_rest_url_prefix.
	 *
	 * @var bool
	 */
	private $filtering_rest_prefix = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Use setup_theme hook to switch theme early, before functions.php is loaded
		add_action( 'setup_theme', array( $this, 'setup_theme_switch' ), 1 );

		add_filter( 'template', array( $this, 'switch_theme_template' ) );
		add_filter( 'stylesheet', array( $this, 'switch_theme_stylesheet' ) );
		add_filter( 'pre_option_template', array( $this, 'switch_theme_template' ) );
		add_filter( 'pre_option_stylesheet', array( $this, 'switch_theme_stylesheet' ) );
		add_filter( 'rest_url_prefix', array( $this, 'filter_rest_url_prefix' ) );
		add_action( 'init', array( $this, 'add_custom_rest_rewrite_rules' ), 1 );
		add_action( 'init', array( $this, 'reregister_missing_cpts' ), 998 );
		add_action( 'init', array( $this, 'capture_theme_objects' ), 999 );
		add_filter( 'rest_pre_dispatch', array( $this, 'set_rest_theme_early' ), 1, 3 );
	}

	/**
	 * Setup theme switch early in the WordPress lifecycle.
	 *
	 * This runs on setup_theme hook before functions.php is loaded,
	 * ensuring the correct theme's functions.php is loaded.
	 *
	 * @since 1.0.2
	 */
	public function setup_theme_switch() {
		$theme = $this->get_theme_for_current_request( true );

		if ( ! $theme ) {
			return;
		}

		$theme_obj = wp_get_theme( $theme );
		if ( ! $theme_obj->exists() ) {
			return;
		}

		add_filter(
			'option_template',
			function() use ( $theme ) {
				return $theme;
			},
			1
		);

		add_filter(
			'option_stylesheet',
			function() use ( $theme ) {
				return $theme;
			},
			1
		);
	}

	/**
	 * Switch theme template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template Current template.
	 * @return string Modified template.
	 */
	public function switch_theme_template( $template ) {
		$theme = $this->get_theme_for_current_request();

		if ( $theme ) {
			$theme_obj = wp_get_theme( $theme );
			if ( ! $theme_obj->exists() ) {
				return $template;
			}
		}

		return $theme ? $theme : $template;
	}

	/**
	 * Switch theme stylesheet.
	 *
	 * @since 1.0.0
	 *
	 * @param string $stylesheet Current stylesheet.
	 * @return string Modified stylesheet.
	 */
	public function switch_theme_stylesheet( $stylesheet ) {
		$theme = $this->get_theme_for_current_request();

		if ( $theme ) {
			$theme_obj = wp_get_theme( $theme );
			if ( ! $theme_obj->exists() ) {
				return $stylesheet;
			}
		}

		return $theme ? $theme : $stylesheet;
	}

	/**
	 * Check if current request is a preview request.
	 *
	 * @since 1.0.2
	 *
	 * @return bool Whether this is a preview request.
	 */
	private function is_preview_request() {
		return isset( $_GET['preview'] ) && 'true' === $_GET['preview'];
	}

	/**
	 * Get post ID from preview request.
	 *
	 * @since 1.0.2
	 *
	 * @return int Post ID or 0 if not found.
	 */
	private function get_preview_post_id() {
		if ( isset( $_GET['p'] ) ) {
			return absint( $_GET['p'] );
		}
		if ( isset( $_GET['page_id'] ) ) {
			return absint( $_GET['page_id'] );
		}
		return 0;
	}

	/**
	 * Determine which theme to use for current request.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $early Whether this is being called early (before WP_Query is available).
	 * @return string|false Theme slug or false.
	 */
	private function get_theme_for_current_request( $early = false ) {
		// Check for REST API requests first (highest priority).
		// This must come before is_admin() check because REST requests from the editor are admin requests.
		if ( $this->is_rest_request() ) {
			$rest_theme = $this->get_theme_for_rest_route();
			if ( $rest_theme ) {
				return $rest_theme;
			}
		}

		// Check for AJAX requests from block editor (e.g., ACF block fetching).
		if ( is_admin() && wp_doing_ajax() ) {
			$ajax_theme = $this->get_theme_for_ajax_request();
			if ( $ajax_theme ) {
				return $ajax_theme;
			}
		}

		// Check if we're in admin area (NOT AJAX, actual admin pages).
		if ( is_admin() && ! wp_doing_ajax() ) {
			$admin_theme = $this->get_admin_theme_preference();
			if ( $admin_theme ) {
				return $admin_theme;
			}
			// In admin but no preference set - return false to use default theme.
			return false;
		}

		// Check for preview requests.
		if ( $this->is_preview_request() ) {
			$post_id = $this->get_preview_post_id();
			if ( $post_id ) {
				global $wpdb;
				$post = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT post_status, post_type FROM {$wpdb->posts} WHERE ID = %d",
						$post_id
					)
				);

				if ( $post ) {
					// Construct rule type based on status.
					$rule_type = $post->post_status . '_' . $post->post_type;

					// Check if any rules match this type and ID.
					$rules = $this->get_rules();
					foreach ( $rules as $rule ) {
						if ( $rule['type'] === $rule_type && absint( $rule['value'] ) === $post_id ) {
							return $rule['theme'];
						}
					}
				}
			}
		}

		// For frontend or AJAX requests, check frontend rules only.
		if ( ! is_admin() ) {
			$rules = $this->get_rules();

			if ( empty( $rules ) ) {
				return false;
			}

			// Special handling for ?page_id or ?p parameters (scheduled/draft previews).
			if ( isset( $_GET['page_id'] ) || isset( $_GET['p'] ) ) {
				$theme = $this->match_preview_post_against_rules( $rules );
				if ( $theme ) {
					return $theme;
				}
			}

			foreach ( $rules as $rule ) {
				if ( $this->rule_matches( $rule, $early ) ) {
					return $rule['theme'];
				}
			}
		}

		return false;
	}

	/**
	 * Check if the WordPress query has run and conditional tags are available.
	 *
	 * Conditional query tags like is_page(), is_single(), etc. only work
	 * after the main query has been parsed.
	 *
	 * @since 1.0.4
	 *
	 * @return bool Whether conditional query tags can be used.
	 */
	private function is_query_ready() {
		global $wp_query;

		if ( did_action( 'wp' ) ) {
			return true;
		}

		if ( isset( $wp_query ) && $wp_query instanceof WP_Query && ! empty( $wp_query->query ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a rule matches the current request.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rule  Rule to check.
	 * @param bool  $early Whether this is being called early (before WP_Query is available).
	 * @return bool Whether rule matches.
	 */
	private function rule_matches( $rule, $early = false ) {
		if ( ! $early && ! $this->is_query_ready() ) {
			$early = true;
		}

		// If called early, we can only check URL-based rules
		if ( $early ) {
			if ( 'url' === $rule['type'] ) {
				$request     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
				$current_url = trim( $request, '/' );
				$rule_url    = trim( $rule['value'], '/' );
				return ( $current_url === $rule_url ) || ( 0 === strpos( $current_url, $rule_url ) );
			}
			if ( 'page' === $rule['type'] ) {
				return $this->match_page_early( $rule['value'] );
			}

			if ( 'post' === $rule['type'] ) {
				return $this->match_post_early( $rule['value'] );
			}
			if ( 'draft_page' === $rule['type'] ) {
				return $this->match_draft_page_early( $rule['value'] );
			}
			if ( 'draft_post' === $rule['type'] ) {
				return $this->match_draft_post_early( $rule['value'] );
			}
			if ( 'pending_page' === $rule['type'] ) {
				return $this->match_pending_page_early( $rule['value'] );
			}
			if ( 'pending_post' === $rule['type'] ) {
				return $this->match_pending_post_early( $rule['value'] );
			}
			if ( 'private_page' === $rule['type'] ) {
				return $this->match_private_page_early( $rule['value'] );
			}
			if ( 'private_post' === $rule['type'] ) {
				return $this->match_private_post_early( $rule['value'] );
			}
			if ( 'future_page' === $rule['type'] ) {
				return $this->match_future_page_early( $rule['value'] );
			}
			if ( 'future_post' === $rule['type'] ) {
				return $this->match_future_post_early( $rule['value'] );
			}
			if ( 'cpt_item' === $rule['type'] ) {
				return $this->match_cpt_item_early( $rule['value'], 'publish' );
			}
			if ( 'draft_cpt_item' === $rule['type'] ) {
				return $this->match_cpt_item_early( $rule['value'], 'draft' );
			}
			if ( 'pending_cpt_item' === $rule['type'] ) {
				return $this->match_cpt_item_early( $rule['value'], 'pending' );
			}
			if ( 'private_cpt_item' === $rule['type'] ) {
				return $this->match_cpt_item_early( $rule['value'], 'private' );
			}
			if ( 'future_cpt_item' === $rule['type'] ) {
				return $this->match_cpt_item_early( $rule['value'], 'future' );
			}
			if ( 'category' === $rule['type'] ) {
				return $this->match_category_early( $rule['value'] );
			}
			if ( 'tag' === $rule['type'] ) {
				return $this->match_tag_early( $rule['value'] );
			}
			if ( 'taxonomy' === $rule['type'] ) {
				$taxonomy = isset( $rule['taxonomy'] ) ? $rule['taxonomy'] : '';
				return $taxonomy ? $this->match_taxonomy_early( $rule['value'], $taxonomy, $rule ) : false;
			}
			if ( 'post_type' === $rule['type'] ) {
				return $this->match_post_type_early( $rule );
			}
			return false;
		}

		switch ( $rule['type'] ) {
			case 'page':
				return is_page( $rule['value'] );

			case 'post':
				return is_single( $rule['value'] );

			case 'post_type':
				return is_singular( $rule['value'] ) || is_post_type_archive( $rule['value'] );

			case 'url':
				$request     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
				$current_url = trim( $request, '/' );
				$rule_url    = trim( $rule['value'], '/' );
				return ( $current_url === $rule_url ) || ( 0 === strpos( $current_url, $rule_url ) );

			case 'category':
				return is_category( $rule['value'] ) || ( is_single() && in_category( $rule['value'] ) );

			case 'tag':
				return is_tag( $rule['value'] ) || ( is_single() && has_tag( $rule['value'] ) );

			case 'taxonomy':
				$taxonomy = isset( $rule['taxonomy'] ) ? $rule['taxonomy'] : '';
				if ( ! $taxonomy ) {
					return false;
				}
				return is_tax( $taxonomy, $rule['value'] ) || ( is_singular() && has_term( $rule['value'], $taxonomy ) );

			case 'draft_page':
			case 'pending_page':
			case 'private_page':
			case 'future_page':
				// For draft/pending/private/future pages, check if viewing that specific page.
				$post = get_post( $rule['value'] );
				return $post && is_page( $rule['value'] );

			case 'draft_post':
			case 'pending_post':
			case 'private_post':
			case 'future_post':
				// For draft/pending/private/future posts, check if viewing that specific post.
				$post = get_post( $rule['value'] );
				return $post && is_single( $rule['value'] );

			case 'cpt_item':
				return is_singular() && get_queried_object_id() === absint( $rule['value'] );

			case 'draft_cpt_item':
			case 'pending_cpt_item':
			case 'private_cpt_item':
			case 'future_cpt_item':
				$post = get_post( $rule['value'] );
				return $post && is_singular() && get_queried_object_id() === absint( $rule['value'] );

			default:
				return false;
		}
	}

	/**
	 * Get all saved rules.
	 *
	 * @since 1.0.0
	 *
	 * @return array Rules array.
	 */
	public function get_rules() {
		return get_option( $this->option_name, array() );
	}

	/**
	 * Save rules.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rules Rules to save.
	 * @return bool Whether save was successful.
	 */
	public function save_rules( $rules ) {
		return update_option( $this->option_name, $rules );
	}

	/**
	 * Get admin theme preference for current user.
	 *
	 * @since 1.0.0
	 *
	 * @return string Theme slug or empty string.
	 */
	public function get_admin_theme_preference() {
		return get_user_meta( get_current_user_id(), 'omts_admin_theme', true );
	}

	/**
	 * Set admin theme preference for current user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $theme Theme slug.
	 */
	public function set_admin_theme_preference( $theme ) {
		if ( empty( $theme ) ) {
			delete_user_meta( get_current_user_id(), 'omts_admin_theme' );
		} else {
			update_user_meta( get_current_user_id(), 'omts_admin_theme', $theme );
		}
	}

	/**
	 * Get theme name from stylesheet.
	 *
	 * @since 1.0.0
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return string Theme name.
	 */
	public function get_theme_name( $stylesheet ) {
		$theme = wp_get_theme( $stylesheet );
		return $theme->exists() ? $theme->get( 'Name' ) : $stylesheet;
	}

	/**
	 * Check if current request is a REST API request.
	 *
	 * @since 1.0.1
	 *
	 * @return bool Whether current request is REST.
	 */
	private function is_rest_request() {
		// Check if REST_REQUEST constant is defined.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Check for any REST prefix (default or custom) in the URL.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			// Check default REST prefix.
			$default_prefix = rest_get_url_prefix();
			if ( false !== strpos( $request_uri, '/' . $default_prefix . '/' ) ) {
				return true;
			}

			// Check custom REST prefixes.
			$prefix_mappings = $this->get_theme_rest_prefixes();
			foreach ( $prefix_mappings as $mapping ) {
				if ( ! empty( $mapping['prefix'] ) && false !== strpos( $request_uri, '/' . $mapping['prefix'] . '/' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get theme for AJAX request from block editor.
	 *
	 * Handles AJAX requests like ACF block fetching that include post context.
	 *
	 * @since 1.0.3
	 *
	 * @return string|false Theme slug or false.
	 */
	private function get_theme_for_ajax_request() {
		// Get post ID from AJAX request.
		$post_id = 0;

		// Check for post_id in POST data (common in ACF AJAX requests).
		if ( isset( $_POST['post_id'] ) ) {
			$post_id = absint( $_POST['post_id'] );
		}

		// Check for postId in context (ACF blocks).
		if ( ! $post_id && isset( $_POST['context'] ) ) {
			if ( is_array( $_POST['context'] ) && isset( $_POST['context']['postId'] ) ) {
				$post_id = absint( $_POST['context']['postId'] );
			}
		}

		// Check for id parameter.
		if ( ! $post_id && isset( $_POST['id'] ) ) {
			$post_id = absint( $_POST['id'] );
		}

		// Check for postId in GET parameters.
		if ( ! $post_id && isset( $_GET['postId'] ) ) {
			$post_id = absint( $_GET['postId'] );
		}

		// Check referer for post context.
		if ( ! $post_id && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			if ( preg_match( '/[?&]post=(\d+)/', $referer, $matches ) ) {
				$post_id = absint( $matches[1] );
			}
		}

		if ( ! $post_id ) {
			return false;
		}

		// Get post data.
		global $wpdb;
		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_status, post_type FROM {$wpdb->posts} WHERE ID = %d",
				$post_id
			)
		);

		if ( ! $post ) {
			return false;
		}

		// Check rules that match this post.
		$rules = $this->get_rules();
		foreach ( $rules as $rule ) {
			// Check for specific post/page rules.
			if ( $rule['type'] === 'post' && absint( $rule['value'] ) === $post_id ) {
				return $rule['theme'];
			}
			if ( $rule['type'] === 'page' && absint( $rule['value'] ) === $post_id ) {
				return $rule['theme'];
			}

			// Check for status-based rules (draft, pending, etc).
			$rule_type = $post->post_status . '_' . $post->post_type;
			if ( $rule['type'] === $rule_type && absint( $rule['value'] ) === $post_id ) {
				return $rule['theme'];
			}

			// Check for post type rules.
			if ( $rule['type'] === 'post_type' && $rule['value'] === $post->post_type ) {
				return $rule['theme'];
			}
		}

		// If no specific rule matches, use admin theme preference.
		return $this->get_admin_theme_preference();
	}

	/**
	 * Get theme for current REST route.
	 *
	 * @since 1.0.1
	 *
	 * @return string|false Theme slug or false.
	 */
	private function get_theme_for_rest_route() {
		// Check for custom REST prefix mappings.
		return $this->get_theme_by_rest_prefix();
	}

	/**
	 * Get theme based on custom REST prefix in URL.
	 *
	 * @since 1.0.1
	 *
	 * @return string|false Theme slug or false.
	 */
	private function get_theme_by_rest_prefix() {
		$request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$prefix_mappings = $this->get_theme_rest_prefixes();

		if ( empty( $prefix_mappings ) ) {
			return false;
		}

		foreach ( $prefix_mappings as $mapping ) {
			$custom_prefix = trim( $mapping['prefix'], '/' );
			if ( ! empty( $custom_prefix ) && false !== strpos( $request_uri, '/' . $custom_prefix . '/' ) ) {
				return $mapping['theme'];
			}
		}

		return false;
	}

	/**
	 * Filter REST URL prefix based on current theme.
	 *
	 * @since 1.0.1
	 *
	 * @param string $prefix Current REST prefix.
	 * @return string Modified REST prefix.
	 */
	public function filter_rest_url_prefix( $prefix ) {
		// Prevent recursion.
		if ( $this->filtering_rest_prefix ) {
			return $prefix;
		}

		$this->filtering_rest_prefix = true;

		try {
			// Get the currently active theme.
			$current_theme = '';

			// Check if we're in admin and user has an admin theme preference.
			if ( is_admin() && ! wp_doing_ajax() ) {
				$admin_theme = $this->get_admin_theme_preference();
				if ( $admin_theme ) {
					$current_theme = $admin_theme;
				}
			}

			// If no admin theme, use the current WordPress theme.
			if ( ! $current_theme ) {
				$current_theme = get_stylesheet();
			}

			// Check if this theme has a custom REST prefix configured.
			$prefix_mappings = $this->get_theme_rest_prefixes();
			if ( is_array( $prefix_mappings ) ) {
				foreach ( $prefix_mappings as $mapping ) {
					if ( isset( $mapping['theme'] ) && $mapping['theme'] === $current_theme && ! empty( $mapping['prefix'] ) ) {
						$this->filtering_rest_prefix = false;
						return $mapping['prefix'];
					}
				}
			}

			$this->filtering_rest_prefix = false;
			return $prefix;
		} catch ( Exception $e ) {
			$this->filtering_rest_prefix = false;
			error_log( 'OMTS filter_rest_url_prefix error: ' . $e->getMessage() );
			return $prefix;
		}
	}

	/**
	 * Get all theme REST prefix mappings.
	 *
	 * @since 1.0.1
	 *
	 * @return array Theme REST prefix mappings.
	 */
	public function get_theme_rest_prefixes() {
		return get_option( $this->rest_prefix_option_name, array() );
	}

	/**
	 * Save theme REST prefix mappings.
	 *
	 * @since 1.0.1
	 *
	 * @param array $prefixes Theme REST prefix mappings.
	 * @return bool Whether save was successful.
	 */
	public function save_theme_rest_prefixes( $prefixes ) {
		return update_option( $this->rest_prefix_option_name, $prefixes );
	}

	/**
	 * Add rewrite rules for custom REST API prefixes.
	 *
	 * @since 1.0.1
	 */
	public function add_custom_rest_rewrite_rules() {
		$prefix_mappings = $this->get_theme_rest_prefixes();

		if ( empty( $prefix_mappings ) ) {
			return;
		}

		foreach ( $prefix_mappings as $mapping ) {
			if ( ! empty( $mapping['prefix'] ) ) {
				$custom_prefix = trim( $mapping['prefix'], '/' );
				add_rewrite_rule(
					'^' . $custom_prefix . '/?(.*)$',
					'index.php?rest_route=/$matches[1]',
					'top'
				);
			}
		}
	}

	/**
	 * Set theme early for REST requests based on prefix.
	 *
	 * @since 1.0.1
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed
	 */
	public function set_rest_theme_early( $result, $server, $request ) {
		// This runs early in REST processing, ensuring theme is set before any REST logic runs.
		return $result;
	}

	/**
	 * Match page rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int|string $page_identifier Page ID or slug.
	 * @return bool Whether current URL matches the page.
	 */
	private function match_page_early( $page_identifier ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		// Get the URL path without query string
		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		// If numeric, it's a page ID - look up the post_name
		if ( is_numeric( $page_identifier ) ) {
			$page_id = absint( $page_identifier );

			// Special handling for front page (homepage)
			$page_on_front = get_option( 'page_on_front' );
			if ( $page_on_front && absint( $page_on_front ) === $page_id && '' === $path ) {
				return true;
			}

			$post = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT post_name, post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'page' AND post_status = 'publish'",
					$page_id
				)
			);

			if ( ! $post ) {
				return false;
			}

			$slug = $post->post_name;

			// Build full path including parent pages if any (only published parents)
			if ( $post->post_parent ) {
				$parent_path = $this->get_page_full_path( $post->post_parent, $wpdb );

				// If parent path is empty (unpublished parent), don't match this page
				if ( '' === $parent_path ) {
					return false;
				}

				$slug = $parent_path . '/' . $slug;
			}
		} else {
			// It's a slug
			$slug = trim( $page_identifier, '/' );
		}

		// Match the path
		return $path === $slug;
	}

	/**
	 * Get full page path including parent pages.
	 *
	 * Only includes published parent pages in the path.
	 * If a parent is not published, returns empty string to indicate invalid path.
	 *
	 * @since 1.0.2
	 *
	 * @param int    $page_id Page ID.
	 * @param object $wpdb    WordPress database object.
	 * @return string Full page path, or empty string if any parent is not published.
	 */
	private function get_page_full_path( $page_id, $wpdb ) {
		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_name, post_parent, post_status FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'page'",
				$page_id
			)
		);

		// If parent doesn't exist or is not published, return empty string
		if ( ! $post || 'publish' !== $post->post_status ) {
			return '';
		}

		// If this page has a parent, recursively get the full path
		if ( $post->post_parent ) {
			$parent_path = $this->get_page_full_path( $post->post_parent, $wpdb );

			// If parent path is empty (parent not published), return empty string
			if ( '' === $parent_path ) {
				return '';
			}

			return $parent_path . '/' . $post->post_name;
		}

		return $post->post_name;
	}

	/**
	 * Match post rule early by querying database directly.
	 *
	 * Uses exact segment matching to avoid false positives. The slug must match
	 * a complete path segment (e.g., "test" will not match "latest-news").
	 *
	 * @since 1.0.2
	 *
	 * @param int|string $post_identifier Post ID or slug.
	 * @return bool Whether current URL matches the post.
	 */
	private function match_post_early( $post_identifier ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		// Get the URL path without query string
		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		// If numeric, it's a post ID - look up the post_name
		if ( is_numeric( $post_identifier ) ) {
			$post_id = absint( $post_identifier );
			$slug    = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_name FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post' AND post_status = 'publish'",
					$post_id
				)
			);

			if ( ! $slug ) {
				return false;
			}
		} else {
			// It's a slug
			$slug = trim( $post_identifier, '/' );
		}

		// Split path into segments and check for exact match
		// This prevents false positives (e.g., "test" matching "latest-news" or "contest")
		$path_segments = explode( '/', $path );

		// Check if the slug appears as a complete path segment
		// Typically posts have the slug as the last segment, but we check all segments
		// to support various permalink structures
		foreach ( $path_segments as $segment ) {
			if ( $segment === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match draft page rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $page_id Page ID.
	 * @return bool Whether current URL matches the draft page.
	 */
	private function match_draft_page_early( $page_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$page_id       = absint( $page_id );
		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_name, post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'page' AND post_status = 'draft'",
				$page_id
			)
		);

		if ( ! $post ) {
			return false;
		}

		$slug = $post->post_name;

		if ( $post->post_parent ) {
			$parent_path = $this->get_page_full_path( $post->post_parent, $wpdb );
			if ( '' !== $parent_path ) {
				$slug = $parent_path . '/' . $slug;
			}
		}

		return $path === $slug;
	}

	/**
	 * Match draft post rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether current URL matches the draft post.
	 */
	private function match_draft_post_early( $post_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$post_id = absint( $post_id );
		$slug    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_name FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post' AND post_status = 'draft'",
				$post_id
			)
		);

		if ( ! $slug ) {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );
		$path_segments = explode( '/', $path );

		foreach ( $path_segments as $segment ) {
			if ( $segment === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match pending page rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $page_id Page ID.
	 * @return bool Whether current URL matches the pending page.
	 */
	private function match_pending_page_early( $page_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$page_id       = absint( $page_id );
		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_name, post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'page' AND post_status = 'pending'",
				$page_id
			)
		);

		if ( ! $post ) {
			return false;
		}

		$slug = $post->post_name;

		if ( $post->post_parent ) {
			$parent_path = $this->get_page_full_path( $post->post_parent, $wpdb );
			if ( '' !== $parent_path ) {
				$slug = $parent_path . '/' . $slug;
			}
		}

		return $path === $slug;
	}

	/**
	 * Match pending post rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether current URL matches the pending post.
	 */
	private function match_pending_post_early( $post_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$post_id = absint( $post_id );
		$slug    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_name FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post' AND post_status = 'pending'",
				$post_id
			)
		);

		if ( ! $slug ) {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );
		$path_segments = explode( '/', $path );

		foreach ( $path_segments as $segment ) {
			if ( $segment === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match private page rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $page_id Page ID.
	 * @return bool Whether current URL matches the private page.
	 */
	private function match_private_page_early( $page_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$page_id       = absint( $page_id );
		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_name, post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'page' AND post_status = 'private'",
				$page_id
			)
		);

		if ( ! $post ) {
			return false;
		}

		$slug = $post->post_name;

		if ( $post->post_parent ) {
			$parent_path = $this->get_page_full_path( $post->post_parent, $wpdb );
			if ( '' !== $parent_path ) {
				$slug = $parent_path . '/' . $slug;
			}
		}

		return $path === $slug;
	}

	/**
	 * Match private post rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether current URL matches the private post.
	 */
	private function match_private_post_early( $post_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$post_id = absint( $post_id );
		$slug    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_name FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post' AND post_status = 'private'",
				$post_id
			)
		);

		if ( ! $slug ) {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );
		$path_segments = explode( '/', $path );

		foreach ( $path_segments as $segment ) {
			if ( $segment === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match scheduled page rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $page_id Page ID.
	 * @return bool Whether current URL matches the scheduled page.
	 */
	private function match_future_page_early( $page_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$page_id       = absint( $page_id );
		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_name, post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'page' AND post_status = 'future'",
				$page_id
			)
		);

		if ( ! $post ) {
			return false;
		}

		$slug = $post->post_name;

		if ( $post->post_parent ) {
			$parent_path = $this->get_page_full_path( $post->post_parent, $wpdb );
			if ( '' !== $parent_path ) {
				$slug = $parent_path . '/' . $slug;
			}
		}

		return $path === $slug;
	}

	/**
	 * Match scheduled post rule early by querying database directly.
	 *
	 * @since 1.0.2
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether current URL matches the scheduled post.
	 */
	private function match_future_post_early( $post_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$post_id = absint( $post_id );
		$slug    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_name FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'post' AND post_status = 'future'",
				$post_id
			)
		);

		if ( ! $slug ) {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );
		$path_segments = explode( '/', $path );

		foreach ( $path_segments as $segment ) {
			if ( $segment === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match category rule early by checking URL against category base.
	 *
	 * @since 1.2.0
	 *
	 * @param int $term_id Category term ID.
	 * @return bool Whether current URL matches the category.
	 */
	private function match_category_early( $term_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$term_id = absint( $term_id );
		$term    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.slug FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				WHERE t.term_id = %d AND tt.taxonomy = 'category'",
				$term_id
			)
		);

		if ( ! $term ) {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		$category_base = get_option( 'category_base' );
		if ( empty( $category_base ) ) {
			$category_base = 'category';
		}

		// Check if URL matches pattern: {category_base}/{slug}
		$expected_path = trim( $category_base, '/' ) . '/' . $term->slug;

		return $path === $expected_path || 0 === strpos( $path, $expected_path . '/' );
	}

	/**
	 * Match tag rule early by checking URL against tag base.
	 *
	 * @since 1.2.0
	 *
	 * @param int $term_id Tag term ID.
	 * @return bool Whether current URL matches the tag.
	 */
	private function match_tag_early( $term_id ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$term_id = absint( $term_id );
		$term    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.slug FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				WHERE t.term_id = %d AND tt.taxonomy = 'post_tag'",
				$term_id
			)
		);

		if ( ! $term ) {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		$tag_base = get_option( 'tag_base' );
		if ( empty( $tag_base ) ) {
			$tag_base = 'tag';
		}

		// Check if URL matches pattern: {tag_base}/{slug}
		$expected_path = trim( $tag_base, '/' ) . '/' . $term->slug;

		return $path === $expected_path || 0 === strpos( $path, $expected_path . '/' );
	}

	/**
	 * Match custom taxonomy rule early by checking URL against taxonomy rewrite slug.
	 *
	 * Falls back to a stored rewrite_slug on the rule when get_taxonomy() is not
	 * available yet (e.g., during setup_theme before taxonomies are registered).
	 *
	 * @since 1.2.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $rule     Rule array with optional rewrite_slug.
	 * @return bool Whether current URL matches the taxonomy term.
	 */
	private function match_taxonomy_early( $term_id, $taxonomy, $rule = array() ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$term_id = absint( $term_id );
		$term    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.slug FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
				WHERE t.term_id = %d AND tt.taxonomy = %s",
				$term_id,
				$taxonomy
			)
		);

		if ( ! $term ) {
			return false;
		}

		// Try get_taxonomy() first (available after init).
		$rewrite_slug = '';
		$tax_obj      = get_taxonomy( $taxonomy );
		if ( $tax_obj && isset( $tax_obj->rewrite['slug'] ) ) {
			$rewrite_slug = $tax_obj->rewrite['slug'];
		} elseif ( ! empty( $rule['rewrite_slug'] ) ) {
			// Fallback to stored slug (works during setup_theme before taxonomies are registered).
			$rewrite_slug = $rule['rewrite_slug'];
		} else {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		$rewrite_slug  = trim( $rewrite_slug, '/' );
		$expected_path = $rewrite_slug . '/' . $term->slug;

		return $path === $expected_path || 0 === strpos( $path, $expected_path . '/' );
	}

	/**
	 * Match post type rule early by checking URL against stored slugs.
	 *
	 * Uses slugs stored in the rule at save time, because CPTs may not be
	 * registered yet during setup_theme hook.
	 *
	 * @since 1.2.0
	 *
	 * @param array $rule Rule array with optional archive_slug and rewrite_slug.
	 * @return bool Whether current URL matches the post type.
	 */
	private function match_post_type_early( $rule ) {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );

		// First try stored slugs (works even before CPT is registered).
		if ( ! empty( $rule['archive_slug'] ) ) {
			$archive_slug = trim( $rule['archive_slug'], '/' );
			if ( $path === $archive_slug || 0 === strpos( $path, $archive_slug . '/' ) ) {
				return true;
			}
		}

		if ( ! empty( $rule['rewrite_slug'] ) ) {
			$rewrite_slug = trim( $rule['rewrite_slug'], '/' );
			if ( 0 === strpos( $path, $rewrite_slug . '/' ) ) {
				return true;
			}
		}

		// Fallback: try get_post_type_object if available (e.g., late calls).
		$post_type     = $rule['value'];
		$post_type_obj = get_post_type_object( $post_type );
		if ( $post_type_obj ) {
			if ( $post_type_obj->has_archive ) {
				$archive_slug = true === $post_type_obj->has_archive ? $post_type : $post_type_obj->has_archive;
				$archive_slug = trim( $archive_slug, '/' );
				if ( $path === $archive_slug || 0 === strpos( $path, $archive_slug . '/' ) ) {
					return true;
				}
			}
			if ( isset( $post_type_obj->rewrite['slug'] ) ) {
				$rewrite_slug = trim( $post_type_obj->rewrite['slug'], '/' );
				if ( 0 === strpos( $path, $rewrite_slug . '/' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Match CPT item rule early by querying database directly.
	 *
	 * @since 1.2.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $post_status Expected post status.
	 * @return bool Whether current URL matches the CPT item.
	 */
	private function match_cpt_item_early( $post_id, $post_status = 'publish' ) {
		global $wpdb;

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$post_id = absint( $post_id );
		$post    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_name, post_type, post_status FROM {$wpdb->posts} WHERE ID = %d AND post_status = %s",
				$post_id,
				$post_status
			)
		);

		if ( ! $post ) {
			return false;
		}

		// Skip built-in page/post types — they have their own matchers.
		if ( in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			return false;
		}

		$slug          = $post->post_name;
		$sanitized_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_uri   = parse_url( $sanitized_uri, PHP_URL_PATH );
		$path          = trim( $request_uri, '/' );
		$path_segments = explode( '/', $path );

		foreach ( $path_segments as $segment ) {
			if ( $segment === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Match preview post against rules.
	 *
	 * Checks for page/post ID rules, status-based rules (draft_page, etc.),
	 * and URL rules.
	 *
	 * @since 1.0.3
	 *
	 * @param array $rules Theme switching rules.
	 * @return string|false Theme slug or false if no match.
	 */
	private function match_preview_post_against_rules( $rules ) {
		$post_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : absint( $_GET['p'] );

		if ( ! $post_id ) {
			return false;
		}

		global $wpdb;
		$post = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT post_name, post_parent, post_type, post_status FROM {$wpdb->posts} WHERE ID = %d",
				$post_id
			)
		);

		if ( ! $post ) {
			return false;
		}

		// First, check for direct page/post ID rules and status-based rules.
		foreach ( $rules as $rule ) {
			// Check for direct page rule (page type with matching ID).
			if ( 'page' === $rule['type'] && 'page' === $post->post_type && absint( $rule['value'] ) === $post_id ) {
				return $rule['theme'];
			}

			// Check for direct post rule (post type with matching ID).
			if ( 'post' === $rule['type'] && 'post' === $post->post_type && absint( $rule['value'] ) === $post_id ) {
				return $rule['theme'];
			}

			// Check for status-based rules (draft_page, pending_page, private_page, future_page, etc.).
			$status_type = $post->post_status . '_' . $post->post_type;
			if ( $rule['type'] === $status_type && absint( $rule['value'] ) === $post_id ) {
				return $rule['theme'];
			}

			// Check for CPT item rules.
			if ( in_array( $rule['type'], array( 'cpt_item', 'draft_cpt_item', 'pending_cpt_item', 'private_cpt_item', 'future_cpt_item' ), true ) && absint( $rule['value'] ) === $post_id ) {
				return $rule['theme'];
			}
		}

		// Then, check URL rules.
		if ( 'page' === $post->post_type ) {
			return $this->match_page_preview_against_rules( $post, $rules, $wpdb );
		} elseif ( 'post' === $post->post_type ) {
			return $this->match_post_preview_against_rules( $post, $rules );
		}

		return false;
	}

	/**
	 * Match page preview against URL rules.
	 *
	 * @since 1.0.3
	 *
	 * @param object $post Post object from database.
	 * @param array  $rules Theme switching rules.
	 * @param object $wpdb WordPress database object.
	 * @return string|false Theme slug or false if no match.
	 */
	private function match_page_preview_against_rules( $post, $rules, $wpdb ) {
		$post_path = $post->post_name;

		if ( $post->post_parent ) {
			$parent_path = $this->get_page_full_path( $post->post_parent, $wpdb );
			// If parent path cannot be resolved, bail out to avoid partial matches
			if ( '' === $parent_path ) {
				return '';
			}
			$post_path = $parent_path . '/' . $post_path;
		}

		$post_path_normalized = trim( $post_path, '/' );

		foreach ( $rules as $rule ) {
			if ( 'url' === $rule['type'] ) {
				$rule_url = trim( $rule['value'], '/' );

				// Check for exact full path match only (no segment fallback for pages)
				if ( $post_path_normalized === $rule_url ) {
					return $rule['theme'];
				}
			}
		}

		return false;
	}

	/**
	 * Match post preview against URL rules.
	 *
	 * @since 1.0.3
	 *
	 * @param object $post Post object from database.
	 * @param array  $rules Theme switching rules.
	 * @return string|false Theme slug or false if no match.
	 */
	private function match_post_preview_against_rules( $post, $rules ) {
		$post_path            = $post->post_name;
		$post_path_normalized = trim( $post_path, '/' );

		foreach ( $rules as $rule ) {
			if ( 'url' === $rule['type'] ) {
				$rule_url          = trim( $rule['value'], '/' );
				$rule_url_segments = explode( '/', $rule_url );

				// Check for exact full path match
				if ( $post_path_normalized === $rule_url ) {
					return $rule['theme'];
				}

				// Check if post path appears in rule's segments
				if ( in_array( $post_path_normalized, $rule_url_segments, true ) ) {
					return $rule['theme'];
				}
			}
		}

		return false;
	}

	/**
	 * Capture registered CPTs and taxonomies for the currently active theme.
	 *
	 * Runs on `init` at priority 999 (after all CPTs/taxonomies are registered).
	 * Stores full registration args so CPTs can be re-registered when a different
	 * theme is active.
	 *
	 * @since 1.2.0
	 */
	public function capture_theme_objects() {
		// Get the currently active theme (may be the switched theme due to our rules).
		// We capture CPTs/taxonomies under this theme key so they can be
		// re-registered when a different theme is active via switching rules.
		$default_theme = get_option( 'stylesheet' );
		$registry      = get_option( $this->theme_registry_option, array() );

		// Collect public CPTs (excluding built-in page/post/attachment).
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$cpt_data   = array();
		foreach ( $post_types as $pt ) {
			if ( in_array( $pt->name, array( 'page', 'post', 'attachment' ), true ) ) {
				continue;
			}
			// Store args needed to re-register this CPT.
			$cpt_data[ $pt->name ] = array(
				'label'        => $pt->label,
				'labels'       => (array) $pt->labels,
				'public'       => $pt->public,
				'has_archive'  => $pt->has_archive,
				'rewrite'      => $pt->rewrite,
				'supports'     => get_all_post_type_supports( $pt->name ),
				'menu_icon'    => $pt->menu_icon,
				'show_in_rest' => $pt->show_in_rest,
				'rest_base'    => $pt->rest_base,
				'taxonomies'   => get_object_taxonomies( $pt->name ),
			);
		}

		// Collect public taxonomies with registration args.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$tax_data   = array();
		foreach ( $taxonomies as $tax ) {
			if ( in_array( $tax->name, array( 'category', 'post_tag', 'post_format' ), true ) ) {
				continue;
			}
			$tax_data[ $tax->name ] = array(
				'label'        => $tax->label,
				'labels'       => (array) $tax->labels,
				'public'       => $tax->public,
				'hierarchical' => $tax->hierarchical,
				'rewrite'      => $tax->rewrite,
				'show_in_rest' => $tax->show_in_rest,
				'rest_base'    => $tax->rest_base,
				'object_type'  => $tax->object_type,
			);
		}

		$new_entry = array(
			'post_types' => $cpt_data,
			'taxonomies' => $tax_data,
		);

		// Only update if data changed to avoid unnecessary DB writes.
		if ( ! isset( $registry[ $default_theme ] ) || $registry[ $default_theme ] !== $new_entry ) {
			$registry[ $default_theme ] = $new_entry;
			update_option( $this->theme_registry_option, $registry, false );
		}
	}

	/**
	 * Re-register CPTs and taxonomies that are missing in the current theme
	 * but are referenced by switching rules.
	 *
	 * Runs on `init` at priority 998, just before capture (999).
	 * This ensures that when an alternative theme is active (via our rules),
	 * CPTs from the original theme are still available so WordPress can
	 * parse URLs and serve content correctly.
	 *
	 * @since 1.2.0
	 */
	public function reregister_missing_cpts() {
		$registry = get_option( $this->theme_registry_option, array() );

		if ( empty( $registry ) ) {
			return;
		}

		// Collect all CPT slugs and taxonomy slugs referenced in rules.
		$rules        = $this->get_rules();
		$needed_cpts  = array();
		$needed_taxes = array();

		foreach ( $rules as $rule ) {
			if ( 'post_type' === $rule['type'] ) {
				$needed_cpts[ $rule['value'] ] = true;
			}
			if ( in_array( $rule['type'], array( 'cpt_item', 'draft_cpt_item', 'pending_cpt_item', 'private_cpt_item', 'future_cpt_item' ), true ) ) {
				if ( ! empty( $rule['post_type'] ) ) {
					$needed_cpts[ $rule['post_type'] ] = true;
				}
			}
			if ( 'taxonomy' === $rule['type'] && ! empty( $rule['taxonomy'] ) ) {
				$needed_taxes[ $rule['taxonomy'] ] = true;
			}
		}

		if ( empty( $needed_cpts ) && empty( $needed_taxes ) ) {
			return;
		}

		// Find CPT/taxonomy args from any theme in the registry.
		foreach ( $needed_cpts as $cpt_slug => $_ ) {
			if ( post_type_exists( $cpt_slug ) ) {
				continue;
			}

			// Search all themes in registry for this CPT's args.
			$args = $this->find_cpt_args_in_registry( $cpt_slug, $registry );
			if ( $args ) {
				register_post_type( $cpt_slug, $args );
			}
		}

		foreach ( $needed_taxes as $tax_slug => $_ ) {
			if ( taxonomy_exists( $tax_slug ) ) {
				continue;
			}

			$tax_info = $this->find_taxonomy_args_in_registry( $tax_slug, $registry );
			if ( $tax_info ) {
				register_taxonomy( $tax_slug, $tax_info['object_type'], $tax_info['args'] );
			}
		}
	}

	/**
	 * Find CPT registration args from any theme in the registry.
	 *
	 * @since 1.2.0
	 *
	 * @param string $cpt_slug CPT slug to find.
	 * @param array  $registry Full theme object registry.
	 * @return array|false Registration args or false if not found.
	 */
	private function find_cpt_args_in_registry( $cpt_slug, $registry ) {
		foreach ( $registry as $theme_data ) {
			if ( isset( $theme_data['post_types'][ $cpt_slug ] ) ) {
				$stored = $theme_data['post_types'][ $cpt_slug ];
				return array(
					'label'        => $stored['label'],
					'labels'       => $stored['labels'],
					'public'       => $stored['public'],
					'has_archive'  => $stored['has_archive'],
					'rewrite'      => $stored['rewrite'],
					'supports'     => array_keys( array_filter( $stored['supports'] ) ),
					'menu_icon'    => $stored['menu_icon'],
					'show_in_rest' => $stored['show_in_rest'],
					'rest_base'    => $stored['rest_base'],
					'taxonomies'   => $stored['taxonomies'],
				);
			}
		}
		return false;
	}

	/**
	 * Find taxonomy registration args from any theme in the registry.
	 *
	 * @since 1.2.0
	 *
	 * @param string $tax_slug Taxonomy slug to find.
	 * @param array  $registry Full theme object registry.
	 * @return array|false Array with 'object_type' and 'args' keys, or false if not found.
	 */
	private function find_taxonomy_args_in_registry( $tax_slug, $registry ) {
		foreach ( $registry as $theme_data ) {
			if ( isset( $theme_data['taxonomies'][ $tax_slug ] ) ) {
				$stored = $theme_data['taxonomies'][ $tax_slug ];
				return array(
					'object_type' => $stored['object_type'],
					'args'        => array(
						'label'        => $stored['label'],
						'labels'       => $stored['labels'],
						'public'       => $stored['public'],
						'hierarchical' => $stored['hierarchical'],
						'rewrite'      => $stored['rewrite'],
						'show_in_rest' => $stored['show_in_rest'],
						'rest_base'    => $stored['rest_base'],
					),
				);
			}
		}
		return false;
	}
}
