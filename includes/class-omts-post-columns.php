<?php
/**
 * Post List Columns Class
 *
 * Shows the assigned theme on the pages/posts list screens and lets editors
 * assign a theme to several items at once.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Post_Columns
 *
 * @since 1.3.0
 */
class OMTS_Post_Columns {

	/**
	 * Theme switcher instance.
	 *
	 * @var OMTS_Theme_Switcher
	 */
	private $theme_switcher;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param OMTS_Theme_Switcher $theme_switcher Theme switcher instance.
	 */
	public function __construct( $theme_switcher ) {
		$this->theme_switcher = $theme_switcher;

		add_action( 'admin_init', array( $this, 'register_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filter' ) );
		add_action( 'admin_notices', array( $this, 'render_bulk_notice' ) );
	}

	/**
	 * Hook the column, bulk actions and filter into every supported post type.
	 *
	 * @since 1.3.0
	 */
	public function register_columns() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( $this->get_supported_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "bulk_actions-edit-{$post_type}", array( $this, 'add_bulk_actions' ) );
			add_filter( "handle_bulk_actions-edit-{$post_type}", array( $this, 'handle_bulk_action' ), 10, 3 );
		}
	}

	/**
	 * Post types that can carry a theme rule.
	 *
	 * @since 1.3.0
	 *
	 * @return array Post type slugs.
	 */
	private function get_supported_post_types() {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);

		unset( $post_types['attachment'] );

		return $post_types;
	}

	/**
	 * Add the theme column.
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
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'omts_theme' !== $column ) {
			return;
		}

		$match = $this->theme_switcher->get_rule_for_post( $post_id );

		if ( ! $match ) {
			printf(
				'<span class="omts-column-default">%s</span>',
				esc_html__( 'Main theme', 'osom-multi-theme-switcher' )
			);
			return;
		}

		printf(
			'<span class="omts-column-theme">%s</span><br><span class="omts-column-rule">%s</span>',
			esc_html( $this->theme_switcher->get_theme_name( $match['rule']['theme'] ) ),
			esc_html( OMTS_Theme_Switcher::get_rule_type_display( $match['rule']['type'] ) )
		);
	}

	/**
	 * Add "assign theme" bulk actions.
	 *
	 * @since 1.3.0
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array Bulk actions with theme assignment added.
	 */
	public function add_bulk_actions( $actions ) {
		foreach ( wp_get_themes() as $slug => $theme ) {
			$actions[ 'omts_assign_' . $slug ] = sprintf(
				/* translators: %s: Theme name */
				__( 'Assign theme: %s', 'osom-multi-theme-switcher' ),
				$theme->get( 'Name' )
			);
		}

		$actions['omts_assign_none'] = __( 'Assign theme: main theme (removes rule)', 'osom-multi-theme-switcher' );

		return $actions;
	}

	/**
	 * Apply the bulk action.
	 *
	 * @since 1.3.0
	 *
	 * @param string $redirect_to Redirect URL.
	 * @param string $action      Bulk action name.
	 * @param array  $post_ids    Selected post IDs.
	 * @return string Redirect URL.
	 */
	public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 0 !== strpos( $action, 'omts_assign_' ) || ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		$theme = substr( $action, strlen( 'omts_assign_' ) );

		if ( 'none' === $theme ) {
			$theme = '';
		} elseif ( ! wp_get_theme( $theme )->exists() ) {
			return $redirect_to;
		}

		$changed = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $this->theme_switcher->assign_theme_to_post( $post_id, $theme ) ) {
				$changed++;
			}
		}

		return add_query_arg( 'omts_assigned', $changed, $redirect_to );
	}

	/**
	 * Confirm what the bulk action did.
	 *
	 * @since 1.3.0
	 */
	public function render_bulk_notice() {
		if ( ! isset( $_GET['omts_assigned'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$count = absint( $_GET['omts_assigned'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: Number of items */
					_n( 'Theme updated for %d item.', 'Theme updated for %d items.', $count, 'osom-multi-theme-switcher' ),
					$count
				)
			)
		);
	}

	/**
	 * Render the "only items with a rule" filter.
	 *
	 * @since 1.3.0
	 *
	 * @param string $post_type Current post type.
	 */
	public function render_filter( $post_type ) {
		if ( ! current_user_can( 'manage_options' ) || ! in_array( $post_type, $this->get_supported_post_types(), true ) ) {
			return;
		}

		$current = isset( $_GET['omts_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['omts_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<select name="omts_filter">
			<option value=""><?php esc_html_e( 'All themes', 'osom-multi-theme-switcher' ); ?></option>
			<option value="rule" <?php selected( $current, 'rule' ); ?>>
				<?php esc_html_e( 'Only items with a theme rule', 'osom-multi-theme-switcher' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Limit the list to items that have a rule.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Query $query Current query.
	 */
	public function apply_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! isset( $_GET['omts_filter'] ) || 'rule' !== $_GET['omts_filter'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$ids = array();

		foreach ( $this->theme_switcher->get_rules() as $rule ) {
			if ( ! empty( $rule['value'] ) && is_numeric( $rule['value'] ) ) {
				$ids[] = absint( $rule['value'] );
			}
		}

		// 0 keeps the list empty when no rule targets a single item.
		$query->set( 'post__in', $ids ? $ids : array( 0 ) );
	}
}
