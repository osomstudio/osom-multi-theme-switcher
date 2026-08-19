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
		add_action( 'wp_ajax_omts_update_rule', array( $this, 'ajax_update_rule' ) );
		add_action( 'wp_ajax_omts_toggle_rule', array( $this, 'ajax_toggle_rule' ) );
		add_action( 'wp_ajax_omts_reorder_rules', array( $this, 'ajax_reorder_rules' ) );
		add_action( 'wp_ajax_omts_restore_rule', array( $this, 'ajax_restore_rule' ) );
		add_action( 'wp_ajax_omts_test_url', array( $this, 'ajax_test_url' ) );
		add_action( 'wp_ajax_omts_cleanup_rules', array( $this, 'ajax_cleanup_rules' ) );
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

		$rules    = $this->theme_switcher->get_rules();
		$conflict = $this->find_conflict( $rule, $rules );

		if ( ! empty( $conflict['error'] ) ) {
			wp_send_json_error( $conflict['error'] );
		}

		// Add rule to existing rules.
		$rules[] = $rule;

		$this->theme_switcher->save_rules( $rules );

		wp_send_json_success(
			array(
				'index'   => count( $rules ) - 1,
				'row'     => $this->render_row( count( $rules ) - 1, $rule ),
				'warning' => isset( $conflict['warning'] ) ? $conflict['warning'] : '',
			)
		);
	}

	/**
	 * Render one rules table row.
	 *
	 * @since 1.3.0
	 *
	 * @param int   $index Rule position.
	 * @param array $rule  Rule data.
	 * @return string Row markup.
	 */
	private function render_row( $index, $rule ) {
		ob_start();
		$this->admin_page->render_rule_row( $index, $rule );

		return (string) ob_get_clean();
	}

	/**
	 * Look for rules that make a new rule pointless.
	 *
	 * An identical rule is refused outright; a broader rule placed earlier only
	 * produces a warning, because order can be changed afterwards.
	 *
	 * @since 1.3.0
	 *
	 * @param array $rule  Rule about to be saved.
	 * @param array $rules Existing rules.
	 * @return array Array with optional 'error' and 'warning' keys.
	 */
	private function find_conflict( $rule, $rules ) {
		$item_types = array(
			'page',
			'post',
			'cpt_item',
			'draft_page',
			'draft_post',
			'draft_cpt_item',
			'pending_page',
			'pending_post',
			'pending_cpt_item',
			'private_page',
			'private_post',
			'private_cpt_item',
			'future_page',
			'future_post',
			'future_cpt_item',
		);

		foreach ( $rules as $index => $existing ) {
			$same_taxonomy = ( ! isset( $rule['taxonomy'] ) && ! isset( $existing['taxonomy'] ) )
				|| ( isset( $rule['taxonomy'], $existing['taxonomy'] ) && $rule['taxonomy'] === $existing['taxonomy'] );

			if ( $existing['type'] === $rule['type'] && (string) $existing['value'] === (string) $rule['value'] && $same_taxonomy ) {
				return array(
					'error' => sprintf(
						/* translators: 1: Rule position, 2: Theme name */
						__( 'Rule %1$d already covers this target and points it at %2$s. Edit that rule instead of adding a second one.', 'osom-multi-theme-switcher' ),
						$index + 1,
						$this->theme_switcher->get_theme_name( $existing['theme'] )
					),
				);
			}
		}

		// A "whole post type" rule placed earlier already catches this single item.
		if ( in_array( $rule['type'], $item_types, true ) ) {
			$post = get_post( absint( $rule['value'] ) );

			if ( $post ) {
				foreach ( $rules as $index => $existing ) {
					if ( 'post_type' === $existing['type'] && $existing['value'] === $post->post_type ) {
						return array(
							'warning' => sprintf(
								/* translators: 1: Rule position, 2: Theme name */
								__( 'Heads up: rule %1$d already covers this whole content type and points it at %2$s. Because the first match wins, move the new rule above it to make it count.', 'osom-multi-theme-switcher' ),
								$index + 1,
								$this->theme_switcher->get_theme_name( $existing['theme'] )
							),
						);
					}
				}
			}
		}

		// An earlier address rule that also matches sub-paths swallows this one.
		if ( 'url' === $rule['type'] ) {
			$path = trim( (string) $rule['value'], '/' );

			foreach ( $rules as $index => $existing ) {
				if ( 'url' !== $existing['type'] ) {
					continue;
				}

				$existing_path = trim( (string) $existing['value'], '/' );
				$existing_mode = isset( $existing['match'] ) ? $existing['match'] : 'prefix';

				if ( 'exact' !== $existing_mode && '' !== $existing_path && 0 === strpos( $path, $existing_path . '/' ) ) {
					return array(
						'warning' => sprintf(
							/* translators: 1: Rule position, 2: Address */
							__( 'Heads up: rule %1$d covers "%2$s" and everything below it, so it matches this address first.', 'osom-multi-theme-switcher' ),
							$index + 1,
							$existing_path
						),
					);
				}
			}
		}

		return array();
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

					if ( ! $post || $post->post_type !== $object_type ) {
						$rule['value'] = '';
						break;
					}

					if ( 'publish' === $post->post_status ) {
						$rule['type'] = 'cpt_item';
					} else {
						$status_map   = array(
							'draft'   => 'draft_cpt_item',
							'pending' => 'pending_cpt_item',
							'private' => 'private_cpt_item',
							'future'  => 'future_cpt_item',
						);
						$rule['type'] = isset( $status_map[ $post->post_status ] ) ? $status_map[ $post->post_status ] : 'cpt_item';
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

				// Validate the term exists in the specified taxonomy.
				$term = get_term( $item_id, $object_type );
				if ( ! $term || is_wp_error( $term ) || $term->taxonomy !== $object_type ) {
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
				$match         = isset( $_POST['url_match'] ) ? sanitize_text_field( wp_unslash( $_POST['url_match'] ) ) : 'exact';
				$rule['match'] = 'prefix' === $match ? 'prefix' : 'exact';
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
		return OMTS_Theme_Switcher::get_rule_type_display( $type );
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
						'number'     => 500,
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

		if ( ! isset( $rules[ $index ] ) ) {
			wp_send_json_error( __( 'Rule not found', 'osom-multi-theme-switcher' ) );
		}

		// Keep the rule around briefly so deleting can be undone, instead of
		// guarding it with a confirmation dialog.
		set_transient(
			'omts_undo_' . get_current_user_id(),
			array(
				'index' => $index,
				'rule'  => $rules[ $index ],
			),
			HOUR_IN_SECONDS
		);

		array_splice( $rules, $index, 1 );
		$this->theme_switcher->save_rules( $rules );

		wp_send_json_success( array( 'canUndo' => true ) );
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
		return OMTS_Theme_Switcher::get_rule_target_display( $rule );
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

	/**
	 * AJAX handler: change the theme (and address settings) of an existing rule.
	 *
	 * @since 1.3.0
	 */
	public function ajax_update_rule() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
		$theme = isset( $_POST['theme'] ) ? sanitize_text_field( wp_unslash( $_POST['theme'] ) ) : '';
		$rules = $this->theme_switcher->get_rules();

		if ( ! isset( $rules[ $index ] ) ) {
			wp_send_json_error( __( 'Rule not found', 'osom-multi-theme-switcher' ) );
		}

		if ( empty( $theme ) || ! wp_get_theme( $theme )->exists() ) {
			wp_send_json_error( __( 'Theme does not exist', 'osom-multi-theme-switcher' ) );
		}

		$rules[ $index ]['theme'] = $theme;

		if ( 'url' === $rules[ $index ]['type'] ) {
			if ( isset( $_POST['value'] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST['value'] ) );

				if ( '' === trim( $value, '/' ) ) {
					wp_send_json_error( __( 'Enter an address or slug.', 'osom-multi-theme-switcher' ) );
				}

				$rules[ $index ]['value'] = $value;
			}

			if ( isset( $_POST['match'] ) ) {
				$match                    = sanitize_text_field( wp_unslash( $_POST['match'] ) );
				$rules[ $index ]['match'] = 'prefix' === $match ? 'prefix' : 'exact';
			}
		}

		$this->theme_switcher->save_rules( $rules );

		wp_send_json_success(
			array(
				'index' => $index,
				'row'   => $this->render_row( $index, $rules[ $index ] ),
			)
		);
	}

	/**
	 * AJAX handler: pause or resume a rule without deleting it.
	 *
	 * @since 1.3.0
	 */
	public function ajax_toggle_rule() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$index   = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
		$enabled = isset( $_POST['enabled'] ) && '1' === $_POST['enabled'];
		$rules   = $this->theme_switcher->get_rules();

		if ( ! isset( $rules[ $index ] ) ) {
			wp_send_json_error( __( 'Rule not found', 'osom-multi-theme-switcher' ) );
		}

		$rules[ $index ]['enabled'] = $enabled;

		$this->theme_switcher->save_rules( $rules );

		wp_send_json_success(
			array(
				'index'   => $index,
				'enabled' => $enabled,
				'row'     => $this->render_row( $index, $rules[ $index ] ),
			)
		);
	}

	/**
	 * AJAX handler: save a new rule order.
	 *
	 * Order is priority — the first rule that matches wins.
	 *
	 * @since 1.3.0
	 */
	public function ajax_reorder_rules() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$order = isset( $_POST['order'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['order'] ) ) : array();
		$rules = $this->theme_switcher->get_rules();

		if ( count( $order ) !== count( $rules ) ) {
			wp_send_json_error( __( 'The rule list changed in the meantime. Reload the page and try again.', 'osom-multi-theme-switcher' ) );
		}

		$reordered = array();

		foreach ( $order as $index ) {
			if ( ! isset( $rules[ $index ] ) ) {
				wp_send_json_error( __( 'The rule list changed in the meantime. Reload the page and try again.', 'osom-multi-theme-switcher' ) );
			}

			$reordered[] = $rules[ $index ];
		}

		$this->theme_switcher->save_rules( $reordered );

		wp_send_json_success();
	}

	/**
	 * AJAX handler: put back the rule that was just deleted.
	 *
	 * @since 1.3.0
	 */
	public function ajax_restore_rule() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$undo = get_transient( 'omts_undo_' . get_current_user_id() );

		if ( ! is_array( $undo ) || empty( $undo['rule'] ) ) {
			wp_send_json_error( __( 'There is nothing to restore.', 'osom-multi-theme-switcher' ) );
		}

		$rules = $this->theme_switcher->get_rules();
		$index = min( absint( $undo['index'] ), count( $rules ) );

		array_splice( $rules, $index, 0, array( $undo['rule'] ) );

		$this->theme_switcher->save_rules( $rules );
		delete_transient( 'omts_undo_' . get_current_user_id() );

		wp_send_json_success( array( 'index' => $index ) );
	}

	/**
	 * AJAX handler: work out which theme an address renders in.
	 *
	 * @since 1.3.0
	 */
	public function ajax_test_url() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( '' === trim( $url ) ) {
			wp_send_json_error( __( 'Enter an address to test.', 'osom-multi-theme-switcher' ) );
		}

		$result  = $this->theme_switcher->resolve_for_path( $url );
		$default = $this->theme_switcher->get_site_default_theme();
		$theme   = $result['theme'] ? $result['theme'] : $default;

		wp_send_json_success(
			array(
				'theme'     => $theme,
				'themeName' => $this->theme_switcher->get_theme_name( $theme ),
				'isDefault' => ! $result['theme'],
				'position'  => null === $result['index'] ? 0 : (int) $result['index'] + 1,
				'rule'      => $result['rule'] ? OMTS_Theme_Switcher::describe_rule( $result['rule'] ) : '',
				'path'      => $result['path'],
			)
		);
	}

	/**
	 * AJAX handler: drop rules that can no longer work.
	 *
	 * @since 1.3.0
	 */
	public function ajax_cleanup_rules() {
		check_ajax_referer( 'omts_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'osom-multi-theme-switcher' ) );
		}

		$plugin = osom_multi_theme_switcher();

		if ( ! $plugin->health ) {
			wp_send_json_error( __( 'Something went wrong. Please try again.', 'osom-multi-theme-switcher' ) );
		}

		$removed = $plugin->health->remove_broken_rules();

		wp_send_json_success( array( 'removed' => $removed ) );
	}
}
