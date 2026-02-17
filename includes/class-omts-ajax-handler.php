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
		add_action( 'wp_ajax_omts_get_rule_objects', array( $this, 'ajax_get_rule_objects' ) );
		add_action( 'wp_ajax_omts_get_rule_items', array( $this, 'ajax_get_rule_items' ) );
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

		// Build rule from the cascading selector data.
		$rule = $this->build_rule_from_request( $rule_type, $theme );

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
				'type_display'   => $this->get_rule_type_display( $rule['type'] ),
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
	 * Build rule array from the cascading selector request data.
	 *
	 * @since 1.2.0
	 *
	 * @param string $rule_type Rule type from the form.
	 * @param string $theme     Theme slug.
	 * @return array Rule array.
	 */
	private function build_rule_from_request( $rule_type, $theme ) {
		$rule = array(
			'type'  => $rule_type,
			'theme' => $theme,
		);

		switch ( $rule_type ) {
			case 'page':
			case 'post':
				$item_id = isset( $_POST['item_id'] ) ? intval( $_POST['item_id'] ) : 0;
				if ( $item_id ) {
					$post = get_post( $item_id );
					if ( $post ) {
						$status = $post->post_status;
						if ( 'publish' !== $status ) {
							$status_map = array(
								'draft'   => 'draft_',
								'pending' => 'pending_',
								'private' => 'private_',
								'future'  => 'future_',
							);
							if ( isset( $status_map[ $status ] ) ) {
								$rule['type'] = $status_map[ $status ] . $rule_type;
							}
						}
					}
				}
				$rule['value'] = $item_id;
				break;

			case 'custom_post_type':
				$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( wp_unslash( $_POST['object_type'] ) ) : '';
				$item_id     = isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_id'] ) ) : '';

				if ( empty( $object_type ) ) {
					$rule['value'] = '';
					break;
				}

				if ( '__all__' === $item_id ) {
					// "All" option — store as post_type rule.
					$rule['type']  = 'post_type';
					$rule['value'] = $object_type;

					// Store URL slugs for early matching (before CPT is registered).
					$pt_obj = get_post_type_object( $object_type );
					if ( $pt_obj ) {
						if ( $pt_obj->has_archive ) {
							$rule['archive_slug'] = true === $pt_obj->has_archive ? $object_type : $pt_obj->has_archive;
						}
						if ( is_array( $pt_obj->rewrite ) && isset( $pt_obj->rewrite['slug'] ) ) {
							$rule['rewrite_slug'] = $pt_obj->rewrite['slug'];
						}
					}
				} else {
					// Individual CPT item.
					$item_id = intval( $item_id );
					$post    = get_post( $item_id );
					if ( $post && 'publish' === $post->post_status ) {
						$rule['type'] = 'cpt_item';
					} elseif ( $post ) {
						$status_map   = array(
							'draft'   => 'draft_cpt_item',
							'pending' => 'pending_cpt_item',
							'private' => 'private_cpt_item',
							'future'  => 'future_cpt_item',
						);
						$rule['type'] = isset( $status_map[ $post->post_status ] ) ? $status_map[ $post->post_status ] : 'cpt_item';
					} else {
						$rule['type'] = 'cpt_item';
					}
					$rule['value']     = $item_id;
					$rule['post_type'] = $object_type;
				}
				break;

			case 'taxonomy':
				$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( wp_unslash( $_POST['object_type'] ) ) : '';
				$item_id     = isset( $_POST['item_id'] ) ? intval( $_POST['item_id'] ) : 0;

				if ( empty( $object_type ) ) {
					$rule['value'] = '';
					break;
				}

				if ( 'category' === $object_type ) {
					$rule['type'] = 'category';
				} elseif ( 'post_tag' === $object_type ) {
					$rule['type'] = 'tag';
				} else {
					$rule['type']     = 'taxonomy';
					$rule['taxonomy'] = $object_type;

					// Store rewrite slug for early matching (before taxonomy is registered).
					$tax_obj = get_taxonomy( $object_type );
					if ( $tax_obj && is_array( $tax_obj->rewrite ) && isset( $tax_obj->rewrite['slug'] ) ) {
						$rule['rewrite_slug'] = $tax_obj->rewrite['slug'];
					}
				}
				$rule['value'] = $item_id;
				break;

			case 'url':
				$rule['value'] = isset( $_POST['custom_url'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_url'] ) ) : '';
				break;

			default:
				$rule['value'] = '';
				break;
		}

		return $rule;
	}

	/**
	 * Get human-readable display name for rule type.
	 *
	 * @since 1.2.0
	 *
	 * @param string $type Rule type.
	 * @return string Display name.
	 */
	private function get_rule_type_display( $type ) {
		$type_map = array(
			'page'             => __( 'Page', 'osom-multi-theme-switcher' ),
			'post'             => __( 'Post', 'osom-multi-theme-switcher' ),
			'post_type'        => __( 'Custom Post Type', 'osom-multi-theme-switcher' ),
			'url'              => __( 'Custom URL', 'osom-multi-theme-switcher' ),
			'category'         => __( 'Category', 'osom-multi-theme-switcher' ),
			'tag'              => __( 'Tag', 'osom-multi-theme-switcher' ),
			'taxonomy'         => __( 'Taxonomy', 'osom-multi-theme-switcher' ),
			'cpt_item'         => __( 'CPT Item', 'osom-multi-theme-switcher' ),
			'draft_page'       => __( 'Page', 'osom-multi-theme-switcher' ),
			'draft_post'       => __( 'Post', 'osom-multi-theme-switcher' ),
			'pending_page'     => __( 'Page', 'osom-multi-theme-switcher' ),
			'pending_post'     => __( 'Post', 'osom-multi-theme-switcher' ),
			'private_page'     => __( 'Page', 'osom-multi-theme-switcher' ),
			'private_post'     => __( 'Post', 'osom-multi-theme-switcher' ),
			'future_page'      => __( 'Page', 'osom-multi-theme-switcher' ),
			'future_post'      => __( 'Post', 'osom-multi-theme-switcher' ),
			'draft_cpt_item'   => __( 'CPT Item', 'osom-multi-theme-switcher' ),
			'pending_cpt_item' => __( 'CPT Item', 'osom-multi-theme-switcher' ),
			'private_cpt_item' => __( 'CPT Item', 'osom-multi-theme-switcher' ),
			'future_cpt_item'  => __( 'CPT Item', 'osom-multi-theme-switcher' ),
		);

		return isset( $type_map[ $type ] ) ? $type_map[ $type ] : ucfirst( str_replace( '_', ' ', $type ) );
	}

	/**
	 * AJAX handler: Get rule objects for cascading selector.
	 *
	 * @since 1.2.0
	 */
	public function ajax_get_rule_objects() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$rule_type = isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : '';
		$objects   = array();

		switch ( $rule_type ) {
			case 'custom_post_type':
				$post_types = get_post_types( array( 'public' => true ), 'objects' );
				foreach ( $post_types as $pt ) {
					if ( in_array( $pt->name, array( 'page', 'post', 'attachment' ), true ) ) {
						continue;
					}
					$objects[] = array(
						'value' => $pt->name,
						'label' => $pt->label,
					);
				}
				break;

			case 'taxonomy':
				$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
				foreach ( $taxonomies as $tax ) {
					$objects[] = array(
						'value' => $tax->name,
						'label' => $tax->label,
					);
				}
				break;
		}

		wp_send_json_success( array( 'objects' => $objects ) );
	}

	/**
	 * AJAX handler: Get rule items for cascading selector.
	 *
	 * @since 1.2.0
	 */
	public function ajax_get_rule_items() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$rule_type   = isset( $_POST['rule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_type'] ) ) : '';
		$object_type = isset( $_POST['object_type'] ) ? sanitize_text_field( wp_unslash( $_POST['object_type'] ) ) : '';
		$items       = array();

		$all_statuses  = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$status_labels = array(
			'draft'   => __( 'Draft', 'osom-multi-theme-switcher' ),
			'pending' => __( 'Pending', 'osom-multi-theme-switcher' ),
			'private' => __( 'Private', 'osom-multi-theme-switcher' ),
			'future'  => __( 'Scheduled', 'osom-multi-theme-switcher' ),
		);

		switch ( $rule_type ) {
			case 'page':
				$pages = get_posts(
					array(
						'post_type'   => 'page',
						'post_status' => $all_statuses,
						'numberposts' => 500,
						'orderby'     => 'title',
						'order'       => 'ASC',
					)
				);
				foreach ( $pages as $page ) {
					$label = $page->post_title;
					if ( 'publish' !== $page->post_status && isset( $status_labels[ $page->post_status ] ) ) {
						$label = '(' . $status_labels[ $page->post_status ] . ') ' . $label;
					}
					$items[] = array(
						'value' => $page->ID,
						'label' => $label,
					);
				}
				break;

			case 'post':
				$posts = get_posts(
					array(
						'post_type'   => 'post',
						'post_status' => $all_statuses,
						'numberposts' => 500,
						'orderby'     => 'title',
						'order'       => 'ASC',
					)
				);
				foreach ( $posts as $post ) {
					$label = $post->post_title;
					if ( 'publish' !== $post->post_status && isset( $status_labels[ $post->post_status ] ) ) {
						$label = '(' . $status_labels[ $post->post_status ] . ') ' . $label;
					}
					$items[] = array(
						'value' => $post->ID,
						'label' => $label,
					);
				}
				break;

			case 'custom_post_type':
				if ( empty( $object_type ) ) {
					break;
				}

				$post_type_obj = get_post_type_object( $object_type );
				$all_label     = $post_type_obj
					? sprintf(
						/* translators: %s: Post type label */
						__( 'All %s', 'osom-multi-theme-switcher' ),
						$post_type_obj->label
					)
					: __( 'All', 'osom-multi-theme-switcher' );

				$items[] = array(
					'value' => '__all__',
					'label' => $all_label,
				);

				$posts = get_posts(
					array(
						'post_type'   => $object_type,
						'post_status' => $all_statuses,
						'numberposts' => 500,
						'orderby'     => 'title',
						'order'       => 'ASC',
					)
				);
				foreach ( $posts as $post ) {
					$label = $post->post_title;
					if ( 'publish' !== $post->post_status && isset( $status_labels[ $post->post_status ] ) ) {
						$label = '(' . $status_labels[ $post->post_status ] . ') ' . $label;
					}
					$items[] = array(
						'value' => $post->ID,
						'label' => $label,
					);
				}
				break;

			case 'taxonomy':
				if ( empty( $object_type ) ) {
					break;
				}

				$terms = get_terms(
					array(
						'taxonomy'   => $object_type,
						'hide_empty' => false,
						'orderby'    => 'name',
						'order'      => 'ASC',
					)
				);

				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$items[] = array(
							'value' => $term->term_id,
							'label' => $term->name,
						);
					}
				}
				break;
		}

		wp_send_json_success( array( 'items' => $items ) );
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
				return $post_type_obj
					? sprintf(
						/* translators: %s: Post type label */
						__( 'All %s', 'osom-multi-theme-switcher' ),
						$post_type_obj->label
					)
					: $rule['value'];

			case 'url':
				return $rule['value'];

			case 'category':
				$category = get_category( $rule['value'] );
				return $category ? $category->name : __( 'Unknown Category', 'osom-multi-theme-switcher' );

			case 'tag':
				$tag = get_tag( $rule['value'] );
				return $tag ? $tag->name : __( 'Unknown Tag', 'osom-multi-theme-switcher' );

			case 'taxonomy':
				$taxonomy = isset( $rule['taxonomy'] ) ? $rule['taxonomy'] : '';
				$term     = get_term( $rule['value'], $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$tax_obj   = get_taxonomy( $taxonomy );
					$tax_label = $tax_obj ? $tax_obj->label : $taxonomy;
					return $term->name . ' (' . $tax_label . ')';
				}
				return __( 'Unknown Term', 'osom-multi-theme-switcher' );

			case 'cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Item (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'draft_page':
			case 'draft_post':
			case 'draft_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Draft) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Draft (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'pending_page':
			case 'pending_post':
			case 'pending_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Pending) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Pending (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'private_page':
			case 'private_post':
			case 'private_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Private) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Private (ID: %d)', 'osom-multi-theme-switcher' ),
					$rule['value']
				);

			case 'future_page':
			case 'future_post':
			case 'future_cpt_item':
				$post = get_post( $rule['value'] );
				return $post ? '(Scheduled) ' . $post->post_title : sprintf(
					/* translators: %d: Post ID */
					__( 'Unknown Scheduled (ID: %d)', 'osom-multi-theme-switcher' ),
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
					$found                        = true;
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
