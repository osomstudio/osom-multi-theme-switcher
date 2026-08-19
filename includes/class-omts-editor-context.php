<?php
/**
 * Editor Context Class
 *
 * Tells the block editor which theme the edited item renders in, and warns when
 * the editor is running a different theme than the frontend will use.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Editor_Context
 *
 * @since 1.3.0
 */
class OMTS_Editor_Context {

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

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'admin_notices', array( $this, 'render_classic_editor_notice' ) );
	}

	/**
	 * Build the theme context for a single item.
	 *
	 * @since 1.3.0
	 *
	 * @param int $post_id Post ID.
	 * @return array Context data.
	 */
	public function get_context( $post_id ) {
		$default_theme = $this->theme_switcher->get_site_default_theme();
		$match         = $this->theme_switcher->get_rule_for_post( $post_id, true );
		$rule_theme    = $match ? $match['rule']['theme'] : $default_theme;
		$editor_theme  = get_stylesheet();

		return array(
			'postId'        => $post_id,
			'ruleTheme'     => $rule_theme,
			'ruleThemeName' => $this->theme_switcher->get_theme_name( $rule_theme ),
			'ruleLabel'     => $match ? OMTS_Theme_Switcher::describe_rule( $match['rule'] ) : '',
			'hasRule'       => (bool) $match,
			'defaultTheme'  => $default_theme,
			'editorTheme'   => $editor_theme,
			'editorName'    => $this->theme_switcher->get_theme_name( $editor_theme ),
			'mismatch'      => $rule_theme !== $editor_theme,
			'canManage'     => current_user_can( 'manage_options' ),
			'rulesUrl'      => admin_url( 'themes.php?page=osom-multi-theme-switcher' ),
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'switchNonce'   => wp_create_nonce( 'omts_admin_theme_nonce' ),
		);
	}

	/**
	 * Enqueue the document sidebar panel.
	 *
	 * @since 1.3.0
	 */
	public function enqueue_editor_assets() {
		$post_id = get_the_ID();

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		wp_enqueue_script(
			'omts-editor-panel',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/editor-panel.js',
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-editor' ),
			OMTS_VERSION,
			true
		);

		wp_enqueue_style(
			'omts-editor-panel',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/editor-panel.css',
			array(),
			OMTS_VERSION
		);

		wp_localize_script( 'omts-editor-panel', 'omtsEditor', $this->get_context( $post_id ) );
		wp_set_script_translations( 'omts-editor-panel', 'osom-multi-theme-switcher', dirname( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Show the same information in the classic editor, which has no sidebar panel.
	 *
	 * @since 1.3.0
	 */
	public function render_classic_editor_notice() {
		global $pagenow;

		if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// The block editor gets the sidebar panel instead.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) ) {
			return;
		}

		$post_id = get_the_ID();

		if ( ! $post_id ) {
			return;
		}

		$context = $this->get_context( $post_id );

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html(
				$context['hasRule']
					? sprintf(
						/* translators: 1: Theme name, 2: Rule description */
						__( 'This item is displayed in the %1$s theme (rule: %2$s).', 'osom-multi-theme-switcher' ),
						$context['ruleThemeName'],
						$context['ruleLabel']
					)
					: sprintf(
						/* translators: %s: Theme name */
						__( 'This item is displayed in the main theme (%s). No rule applies to it.', 'osom-multi-theme-switcher' ),
						$context['ruleThemeName']
					)
			)
		);
	}
}
