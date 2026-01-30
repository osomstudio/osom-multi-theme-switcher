<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for the plugin.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Ajax_Handler
 *
 * @since 1.0.0
 */
class OMTS_Ajax_Handler {

	/**
	 * Theme switcher instance.
	 *
	 * @var OMTS_Theme_Switcher
	 */
	private $theme_switcher;

	/**
	 * Admin page instance.
	 *
	 * @var OMTS_Admin_Page
	 */
	private $admin_page;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OMTS_Theme_Switcher $theme_switcher Theme switcher instance.
	 * @param OMTS_Admin_Page     $admin_page     Admin page instance.
	 */
	public function __construct( $theme_switcher, $admin_page ) {
		$this->theme_switcher = $theme_switcher;
		$this->admin_page     = $admin_page;

		add_action( 'wp_ajax_omts_save_rules', array( $this, 'ajax_save_rules' ) );
		add_action( 'wp_ajax_omts_delete_rule', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_omts_switch_admin_theme', array( $this, 'ajax_switch_admin_theme' ) );
		add_action( 'wp_ajax_omts_save_rest_prefix', array( $this, 'ajax_save_rest_prefix' ) );
		add_action( 'wp_ajax_omts_delete_rest_prefix', array( $this, 'ajax_delete_rest_prefix' ) );
	}

	/**
	 * AJAX handler: Save rules.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_rules() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$rule_type = isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : '';
		$theme     = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : '';

		if ( empty( $theme ) ) {
			wp_send_json_error( __( 'Please select a theme', 'osom-multi-theme-switcher' ) );
		}

		$rule = array(
			'type'  => $rule_type,
			'theme' => $theme,
		);

		// Get the value based on rule type.
		switch ( $rule_type ) {
			case 'page':
				$rule['value'] = isset( $_POST['page_id'] ) ? intval( $_POST['page_id'] ) : 0;
				break;
			case 'post':
				$rule['value'] = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
				break;
			case 'post_type':
				$rule['value'] = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';
				break;
			case 'draft_page':
				$rule['value'] = isset( $_POST['page_id'] ) ? intval( $_POST['page_id'] ) : 0;
				break;
			case 'draft_post':
				$rule['value'] = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
				break;
			case 'pending_page':
				$rule['value'] = isset( $_POST['page_id'] ) ? intval( $_POST['page_id'] ) : 0;
				break;
			case 'pending_post':
				$rule['value'] = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
				break;
			case 'private_page':
				$rule['value'] = isset( $_POST['page_id'] ) ? intval( $_POST['page_id'] ) : 0;
				break;
			case 'private_post':
				$rule['value'] = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
				break;
			case 'future_page':
				$rule['value'] = isset( $_POST['page_id'] ) ? intval( $_POST['page_id'] ) : 0;
				break;
			case 'future_post':
				$rule['value'] = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
				break;
			case 'url':
				$rule['value'] = isset( $_POST['custom_url'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_url'] ) ) : '';
				break;
			case 'category':
				$rule['value'] = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
				break;
			case 'tag':
				$rule['value'] = isset( $_POST['tag_id'] ) ? intval( $_POST['tag_id'] ) : 0;
				break;
		}

		if ( empty( $rule['value'] ) ) {
			wp_send_json_error( __( 'Please select or enter a valid value', 'osom-multi-theme-switcher' ) );
		}

		// Add rule to existing rules.
		$rules   = $this->theme_switcher->get_rules();
		$rules[] = $rule;

		$this->theme_switcher->save_rules( $rules );

		wp_send_json_success(
			array(
				'rule'           => $rule,
				'index'          => count( $rules ) - 1,
				'target_display' => $this->get_rule_target_display( $rule ),
				'theme_name'     => $this->theme_switcher->get_theme_name( $theme ),
			)
		);
	}

	/**
	 * AJAX handler: Delete rule.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_rule() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
		$rules = $this->theme_switcher->get_rules();

		if ( isset( $rules[ $index ] ) ) {
			array_splice( $rules, $index, 1 );
			$this->theme_switcher->save_rules( $rules );
			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Rule not found', 'osom-multi-theme-switcher' ) );
		}
	}

	/**
	 * AJAX handler: Switch admin theme.
	 *
	 * @since 1.0.0
	 */
	public function ajax_switch_admin_theme() {
		check_ajax_referer( 'omts_admin_theme_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$theme = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : '';

		// Validate theme exists.
		if ( ! empty( $theme ) ) {
			$theme_obj = wp_get_theme( $theme );
			if ( ! $theme_obj->exists() ) {
				wp_send_json_error( __( 'Theme does not exist', 'osom-multi-theme-switcher' ) );
			}
		}

		$this->theme_switcher->set_admin_theme_preference( $theme );

		wp_send_json_success(
			array(
				'theme'      => $theme,
				'theme_name' => empty( $theme ) ? wp_get_theme()->get( 'Name' ) : $this->theme_switcher->get_theme_name( $theme ),
			)
		);
	}

	/**
	 * Get display name for rule target.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rule Rule array.
	 * @return string Display name.
	 */
	private function get_rule_target_display( $rule ) {
		switch ( $rule['type'] ) {
			case 'page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Page (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Post (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'post_type':
				$post_type_obj = get_post_type_object( $rule['value'] );
				return $post_type_obj ? $post_type_obj->label : $rule['value'];

			case 'url':
				return $rule['value'];

			case 'category':
				$category = get_category( $rule['value'] );
				return $category ? $category->name : __( 'Unknown Category', 'osom-multi-theme-switcher' );

			case 'tag':
				$tag = get_tag( $rule['value'] );
				return $tag ? $tag->name : __( 'Unknown Tag', 'osom-multi-theme-switcher' );

			case 'draft_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Draft)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Draft Page (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'draft_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Draft)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Draft Post (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'pending_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Pending)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Pending Page (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'pending_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Pending)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Pending Post (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'private_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Private)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Private Page (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'private_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Private)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Private Post (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'future_page':
				$page = get_post( $rule['value'] );
				return $page ? $page->post_title . ' (Scheduled)' : sprintf(
					/* translators: %d: Page ID */
					__( 'Unknown Scheduled Page (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'future_post':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title . ' (Scheduled)' : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Scheduled Post (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			default:
				return $rule['value'];
		}
	}

	/**
	 * AJAX handler: Save REST prefix mapping.
	 *
	 * @since 1.0.1
	 */
	public function ajax_save_rest_prefix() {
		try {
			check_ajax_referer( 'omts_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
			}

			$theme  = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : '';
			$prefix = isset( $_POST['prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['prefix'] ) ) : '';

			if ( empty( $theme ) ) {
				wp_send_json_error( __( 'Please select a theme', 'osom-multi-theme-switcher' ) );
			}

			// Validate theme exists.
			$theme_obj = wp_get_theme( $theme );
			if ( ! $theme_obj->exists() ) {
				wp_send_json_error( __( 'Theme does not exist', 'osom-multi-theme-switcher' ) );
			}

			// Sanitize prefix - remove slashes and special characters.
			$prefix = trim( $prefix, '/' );
			$prefix = preg_replace( '/[^a-z0-9\-_]/i', '', $prefix );

			// Get existing prefixes.
			$prefixes = $this->theme_switcher->get_theme_rest_prefixes();
			if ( ! is_array( $prefixes ) ) {
				$prefixes = array();
			}

			// Check if theme already has a prefix configured and update it.
			$found = false;
			foreach ( $prefixes as $index => $mapping ) {
				if ( isset( $mapping['theme'] ) && $mapping['theme'] === $theme ) {
					$prefixes[ $index ]['prefix'] = $prefix;
					$found                         = true;
					break;
				}
			}

			// If not found, add new mapping.
			if ( ! $found ) {
				$prefixes[] = array(
					'theme'  => $theme,
					'prefix' => $prefix,
				);
			}

			$this->theme_switcher->save_theme_rest_prefixes( $prefixes );

			// Flush rewrite rules to register the new custom REST prefix.
			flush_rewrite_rules();

			$display_prefix = ! empty( $prefix ) ? $prefix : 'wp-json';
			$example_url    = home_url( '/' . $display_prefix . '/namespace/endpoint' );

			wp_send_json_success(
				array(
					'theme'          => $theme,
					'prefix'         => $prefix,
					'theme_name'     => $this->theme_switcher->get_theme_name( $theme ),
					'display_prefix' => $display_prefix,
					'example_url'    => $example_url,
					'prefixes'       => $prefixes,
				)
			);
		} catch ( Exception $e ) {
			error_log( 'OMTS REST Prefix Save Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'An error occurred: ', 'osom-multi-theme-switcher' ) . $e->getMessage() );
		}
	}

	/**
	 * AJAX handler: Delete REST prefix mapping.
	 *
	 * @since 1.0.1
	 */
	public function ajax_delete_rest_prefix() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$index    = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
		$prefixes = $this->theme_switcher->get_theme_rest_prefixes();

		if ( isset( $prefixes[ $index ] ) ) {
			array_splice( $prefixes, $index, 1 );
			$this->theme_switcher->save_theme_rest_prefixes( $prefixes );

			// Flush rewrite rules to remove the custom REST prefix.
			flush_rewrite_rules();

			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'REST prefix mapping not found', 'osom-multi-theme-switcher' ) );
		}
	}
}
