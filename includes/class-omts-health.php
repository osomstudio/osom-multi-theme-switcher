<?php
/**
 * Rule Health Class
 *
 * Finds rules that can no longer do anything — a theme that is gone, content that
 * was deleted — and keeps the rule list clean when content disappears.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Health
 *
 * @since 1.3.0
 */
class OMTS_Health {

	/**
	 * Theme switcher instance.
	 *
	 * @var OMTS_Theme_Switcher
	 */
	private $theme_switcher;

	/**
	 * Rule types that point at a single item.
	 *
	 * @var array
	 */
	private $item_types = array(
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

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param OMTS_Theme_Switcher $theme_switcher Theme switcher instance.
	 */
	public function __construct( $theme_switcher ) {
		$this->theme_switcher = $theme_switcher;

		add_action( 'before_delete_post', array( $this, 'remove_rules_for_deleted_post' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Drop rules for content that is deleted for good.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id Post ID.
	 */
	public function remove_rules_for_deleted_post( $post_id ) {
		$this->theme_switcher->remove_rules_for_post( $post_id );
	}

	/**
	 * List rules that cannot work any more.
	 *
	 * @since 1.3.0
	 *
	 * @return array List of problems, each with index, rule and reason.
	 */
	public function get_problems() {
		$problems = array();

		foreach ( $this->theme_switcher->get_rules() as $index => $rule ) {
			$theme = wp_get_theme( $rule['theme'] );

			if ( ! $theme->exists() ) {
				$problems[] = array(
					'index'  => $index,
					'rule'   => $rule,
					'reason' => sprintf(
						/* translators: %s: Theme stylesheet */
						__( 'the theme "%s" is not installed — the main theme is used instead', 'osom-multi-theme-switcher' ),
						$rule['theme']
					),
				);
				continue;
			}

			if ( in_array( $rule['type'], $this->item_types, true ) && ! get_post( absint( $rule['value'] ) ) ) {
				$problems[] = array(
					'index'  => $index,
					'rule'   => $rule,
					'reason' => __( 'the content it points to no longer exists', 'osom-multi-theme-switcher' ),
				);
				continue;
			}

			if ( 'taxonomy' === $rule['type'] ) {
				$taxonomy = isset( $rule['taxonomy'] ) ? $rule['taxonomy'] : '';
				$term     = $taxonomy ? get_term( absint( $rule['value'] ), $taxonomy ) : null;

				if ( ! $term || is_wp_error( $term ) ) {
					$problems[] = array(
						'index'  => $index,
						'rule'   => $rule,
						'reason' => __( 'the term it points to no longer exists', 'osom-multi-theme-switcher' ),
					);
				}
			}
		}

		return $problems;
	}

	/**
	 * Remove every rule that cannot work any more.
	 *
	 * @since 1.3.0
	 *
	 * @return int Number of rules removed.
	 */
	public function remove_broken_rules() {
		$problems = $this->get_problems();

		if ( ! $problems ) {
			return 0;
		}

		$broken = wp_list_pluck( $problems, 'index' );
		$kept   = array();

		foreach ( $this->theme_switcher->get_rules() as $index => $rule ) {
			if ( ! in_array( $index, $broken, true ) ) {
				$kept[] = $rule;
			}
		}

		$this->theme_switcher->save_rules( array_values( $kept ) );

		return count( $broken );
	}

	/**
	 * Warn about broken rules where an administrator will see it.
	 *
	 * @since 1.3.0
	 */
	public function render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'appearance_page_osom-multi-theme-switcher' ), true ) ) {
			return;
		}

		$problems = $this->get_problems();

		if ( ! $problems ) {
			return;
		}

		$lines = array();

		foreach ( $problems as $problem ) {
			$lines[] = sprintf(
				/* translators: 1: Rule description, 2: Reason the rule cannot work */
				__( '%1$s — %2$s', 'osom-multi-theme-switcher' ),
				OMTS_Theme_Switcher::describe_rule( $problem['rule'] ),
				$problem['reason']
			);
		}

		echo '<div class="notice notice-warning omts-health-notice"><p><strong>';
		echo esc_html(
			sprintf(
				/* translators: %d: Number of rules */
				_n( '%d theme rule cannot work', '%d theme rules cannot work', count( $problems ), 'osom-multi-theme-switcher' ),
				count( $problems )
			)
		);
		echo '</strong></p><ul style="list-style:disc;margin-left:20px">';

		foreach ( $lines as $line ) {
			echo '<li>' . esc_html( $line ) . '</li>';
		}

		echo '</ul><p><button type="button" class="button omts-cleanup-rules" data-nonce="' . esc_attr( wp_create_nonce( 'omts_nonce' ) ) . '">';
		echo esc_html__( 'Remove these rules', 'osom-multi-theme-switcher' );
		echo '</button></p></div>';

		if ( 'dashboard' === $screen->id ) {
			// The cleanup button needs the plugin's admin script, which only the
			// settings screen loads; on the dashboard, link there instead.
			echo '<script>document.querySelectorAll(".omts-cleanup-rules").forEach(function(b){b.addEventListener("click",function(){window.location.href=' . wp_json_encode( admin_url( 'themes.php?page=osom-multi-theme-switcher' ) ) . ';});});</script>';
		}
	}
}
