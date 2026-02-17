<?php
/**
 * Status Sync Class
 *
 * Automatically synchronizes theme rules when post status changes.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Status_Sync
 *
 * Handles automatic rule type updates when post/page status changes.
 *
 * @since 1.1.0
 */
class OMTS_Status_Sync {

	/**
	 * Theme switcher instance.
	 *
	 * @var OMTS_Theme_Switcher
	 */
	private $theme_switcher;

	/**
	 * Mapping of WordPress statuses to rule type suffixes for pages.
	 *
	 * @var array
	 */
	private $page_status_map = array(
		'publish' => 'page',
		'draft'   => 'draft_page',
		'pending' => 'pending_page',
		'private' => 'private_page',
		'future'  => 'future_page',
	);

	/**
	 * Mapping of WordPress statuses to rule type suffixes for posts.
	 *
	 * @var array
	 */
	private $post_status_map = array(
		'publish' => 'post',
		'draft'   => 'draft_post',
		'pending' => 'pending_post',
		'private' => 'private_post',
		'future'  => 'future_post',
	);

	/**
	 * Mapping of WordPress statuses to rule type suffixes for CPT items.
	 *
	 * @var array
	 */
	private $cpt_item_status_map = array(
		'publish' => 'cpt_item',
		'draft'   => 'draft_cpt_item',
		'pending' => 'pending_cpt_item',
		'private' => 'private_cpt_item',
		'future'  => 'future_cpt_item',
	);

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param OMTS_Theme_Switcher $theme_switcher Theme switcher instance.
	 */
	public function __construct( $theme_switcher ) {
		$this->theme_switcher = $theme_switcher;
		$this->init();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.1.0
	 */
	private function init() {
		add_action( 'transition_post_status', array( $this, 'sync_rules_on_status_change' ), 10, 3 );
	}

	/**
	 * Synchronize rules when post status changes.
	 *
	 * @since 1.1.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public function sync_rules_on_status_change( $new_status, $old_status, $post ) {
		// Skip if status hasn't changed.
		if ( $new_status === $old_status ) {
			return;
		}

		// Skip revisions.
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		// Skip autosaves.
		if ( wp_is_post_autosave( $post ) ) {
			return;
		}

		$post_type = get_post_type( $post );

		// Check if post type is supported.
		if ( ! $this->is_supported_post_type( $post_type ) ) {
			return;
		}

		$rules   = $this->theme_switcher->get_rules();
		$updated = false;

		// Find rules matching this post.
		foreach ( $rules as $index => $rule ) {
			if ( ! $this->rule_matches_post( $rule, $post->ID, $post_type, $old_status ) ) {
				continue;
			}

			// Handle trash status - remove the rule.
			if ( 'trash' === $new_status ) {
				unset( $rules[ $index ] );
				$updated = true;
				continue;
			}

			// Get new rule type for the new status.
			$new_rule_type = $this->get_rule_type_for_status( $new_status, $post_type );

			if ( $new_rule_type ) {
				$rules[ $index ]['type'] = $new_rule_type;
				$updated                 = true;
			} else {
				// If no valid rule type for new status (e.g., CPT going to publish), remove the rule.
				unset( $rules[ $index ] );
				$updated = true;
			}
		}

		if ( $updated ) {
			// Re-index array after potential unsets.
			$rules = array_values( $rules );
			$this->theme_switcher->save_rules( $rules );
		}
	}

	/**
	 * Check if a rule matches a specific post.
	 *
	 * @since 1.1.0
	 *
	 * @param array  $rule       Rule to check.
	 * @param int    $post_id    Post ID.
	 * @param string $post_type  Post type.
	 * @param string $old_status Old post status.
	 * @return bool Whether the rule matches the post.
	 */
	private function rule_matches_post( $rule, $post_id, $post_type, $old_status ) {

		if ( ! is_array( $rule ) || ! isset( $rule['value'], $rule['type'] ) ) {
			return false;
		}

		// Check if rule value matches post ID.
		if ( absint( $rule['value'] ) !== $post_id ) {
			return false;
		}

		// Get expected rule type for this post type and old status.
		$expected_rule_type = $this->get_rule_type_for_status( $old_status, $post_type );

		if ( $expected_rule_type && $rule['type'] === $expected_rule_type ) {
			return true;
		}

		// For CPT, also check dynamic format: {status}_{post_type}.
		if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			$dynamic_type = $old_status . '_' . $post_type;
			if ( $rule['type'] === $dynamic_type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get rule type for a given status and post type.
	 *
	 * @since 1.1.0
	 *
	 * @param string $status    Post status.
	 * @param string $post_type Post type.
	 * @return string|false Rule type or false if not supported.
	 */
	private function get_rule_type_for_status( $status, $post_type ) {
		if ( 'page' === $post_type ) {
			return isset( $this->page_status_map[ $status ] ) ? $this->page_status_map[ $status ] : false;
		}

		if ( 'post' === $post_type ) {
			return isset( $this->post_status_map[ $status ] ) ? $this->post_status_map[ $status ] : false;
		}

		// For CPT items, use the cpt_item status map.
		return isset( $this->cpt_item_status_map[ $status ] ) ? $this->cpt_item_status_map[ $status ] : false;
	}

	/**
	 * Check if post type is supported for rule synchronization.
	 *
	 * @since 1.1.0
	 *
	 * @param string $post_type Post type to check.
	 * @return bool Whether post type is supported.
	 */
	private function is_supported_post_type( $post_type ) {
		// Always support page and post.
		if ( in_array( $post_type, array( 'page', 'post' ), true ) ) {
			return true;
		}

		// Support all public custom post types.
		$post_type_obj = get_post_type_object( $post_type );

		if ( ! $post_type_obj ) {
			return false;
		}

		return $post_type_obj->public;
	}
}
