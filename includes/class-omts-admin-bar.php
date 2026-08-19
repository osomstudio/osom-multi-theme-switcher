<?php
/**
 * Admin Bar Class
 *
 * Two jobs: switch the theme the dashboard runs in (per user), and tell you on the
 * frontend which theme rendered the page you are looking at, and why.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OMTS_Admin_Bar
 *
 * @since 1.0.0
 */
class OMTS_Admin_Bar {

	/**
	 * Theme switcher instance.
	 *
	 * @var OMTS_Theme_Switcher
	 */
	private $theme_switcher;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param OMTS_Theme_Switcher $theme_switcher Theme switcher instance.
	 */
	public function __construct( $theme_switcher ) {
		$this->theme_switcher = $theme_switcher;

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
		// The menu renders in both areas, so the stylesheet has to load in both.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
		// Switching only happens in the dashboard, so the script stays there.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_scripts' ) );
	}

	/**
	 * Add the admin bar menu.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( is_admin() ) {
			$this->add_dashboard_switcher( $wp_admin_bar );
			return;
		}

		$this->add_frontend_indicator( $wp_admin_bar );
	}

	/**
	 * Dashboard theme switcher, for reaching theme settings pages.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	private function add_dashboard_switcher( $wp_admin_bar ) {
		$preference   = $this->theme_switcher->get_admin_theme_preference();
		$site_default = $this->theme_switcher->get_site_default_theme();
		$active_theme = $preference ? $preference : $site_default;
		$active_name  = $this->theme_switcher->get_theme_name( $active_theme );
		$is_switched  = $active_theme !== $site_default;
		$nonce        = wp_create_nonce( 'omts_admin_theme_nonce' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'omts-admin-theme-switcher',
				'title' => '<span class="ab-icon dashicons dashicons-admin-appearance"></span><span class="ab-label">'
					. esc_html(
						sprintf(
							/* translators: %s: Theme name */
							__( 'Dashboard: %s', 'osom-multi-theme-switcher' ),
							$active_name
						)
					) . '</span>',
				'href'  => '#',
				'meta'  => array(
					'class' => 'omts-admin-bar-menu' . ( $is_switched ? ' omts-admin-bar-menu--switched' : '' ),
					'title' => __( 'Theme used by your dashboard view', 'osom-multi-theme-switcher' ),
				),
			)
		);

		// Three groups, so the caption, the choices and the links read as
		// separate things instead of one undifferentiated list.
		$wp_admin_bar->add_group(
			array(
				'parent' => 'omts-admin-theme-switcher',
				'id'     => 'omts-switcher-intro',
				'meta'   => array( 'class' => 'omts-adminbar-caption' ),
			)
		);

		$wp_admin_bar->add_group(
			array(
				'parent' => 'omts-admin-theme-switcher',
				'id'     => 'omts-switcher-themes',
			)
		);

		$wp_admin_bar->add_group(
			array(
				'parent' => 'omts-admin-theme-switcher',
				'id'     => 'omts-switcher-actions',
				'meta'   => array( 'class' => 'ab-sub-secondary' ),
			)
		);

		// Say plainly what this control does — the single biggest source of confusion.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'omts-switcher-intro',
				'id'     => 'omts-switcher-explainer',
				'title'  => '<span class="omts-adminbar-note">' . esc_html__( 'Applies to your dashboard only, never to visitors.', 'osom-multi-theme-switcher' ) . '</span>',
				'meta'   => array( 'class' => 'omts-adminbar-explainer' ),
			)
		);

		foreach ( $this->get_sorted_themes( $site_default ) as $theme_slug => $theme_obj ) {
			$is_current = ( $active_theme === $theme_slug );
			$name       = $theme_obj->get( 'Name' );
			$title      = '<span class="omts-theme-mark">' . ( $is_current ? '&#10003;' : '' ) . '</span>'
				. '<span class="omts-theme-name">' . esc_html( $name ) . '</span>';

			if ( $theme_slug === $site_default ) {
				// The leading space is deliberate: it keeps the words apart even
				// if the stylesheet fails to load.
				$title .= ' <span class="omts-theme-tag">' . esc_html__( 'main theme', 'osom-multi-theme-switcher' ) . '</span>';
			}

			$wp_admin_bar->add_node(
				array(
					'parent' => 'omts-switcher-themes',
					'id'     => 'omts-theme-' . $theme_slug,
					'title'  => $title,
					'href'   => '#',
					'meta'   => array(
						'class'   => 'omts-theme-option' . ( $is_current ? ' omts-current-theme' : '' ),
						'onclick' => 'omtsAdminBarSwitchTheme("' . esc_js( $theme_slug ) . '", "' . esc_js( $nonce ) . '"); return false;',
					),
				)
			);
		}

		if ( $is_switched ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'omts-switcher-actions',
					'id'     => 'omts-theme-reset',
					'title'  => esc_html__( 'Back to the main theme', 'osom-multi-theme-switcher' ),
					'href'   => '#',
					'meta'   => array(
						'class'   => 'omts-theme-reset',
						'onclick' => 'omtsAdminBarSwitchTheme("", "' . esc_js( $nonce ) . '"); return false;',
					),
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'parent' => 'omts-switcher-actions',
				'id'     => 'omts-manage-rules',
				'title'  => esc_html__( 'Manage theme rules', 'osom-multi-theme-switcher' ),
				'href'   => admin_url( 'themes.php?page=osom-multi-theme-switcher' ),
			)
		);
	}

	/**
	 * Installed themes with the site's main theme first, then the rest by name.
	 *
	 * @since 1.3.0
	 *
	 * @param string $site_default Stylesheet of the main theme.
	 * @return array Theme objects keyed by stylesheet.
	 */
	private function get_sorted_themes( $site_default ) {
		$themes = wp_get_themes();

		uksort(
			$themes,
			function ( $a, $b ) use ( $themes, $site_default ) {
				if ( $a === $site_default ) {
					return -1;
				}

				if ( $b === $site_default ) {
					return 1;
				}

				return strcasecmp( $themes[ $a ]->get( 'Name' ), $themes[ $b ]->get( 'Name' ) );
			}
		);

		return $themes;
	}

	/**
	 * Frontend indicator: which theme rendered this page, and why.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	private function add_frontend_indicator( $wp_admin_bar ) {
		$stylesheet = get_stylesheet();
		$template   = get_template();
		$match      = $this->theme_switcher->get_matched_rule();
		$is_rule    = (bool) $match;

		$wp_admin_bar->add_node(
			array(
				'id'    => 'omts-frontend-theme',
				'title' => '<span class="ab-icon dashicons dashicons-admin-appearance"></span><span class="ab-label">'
					. esc_html(
						sprintf(
							/* translators: %s: Theme name */
							__( 'Theme: %s', 'osom-multi-theme-switcher' ),
							$this->theme_switcher->get_theme_name( $stylesheet )
						)
					) . '</span>',
				'href'  => admin_url( 'themes.php?page=osom-multi-theme-switcher' ),
				'meta'  => array(
					'class' => 'omts-admin-bar-menu' . ( $is_rule ? ' omts-admin-bar-menu--switched' : '' ),
				),
			)
		);

		// One short line per fact — the admin bar keeps submenu items on a single
		// line, so anything long would overlap the item below it.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'omts-frontend-theme',
				'id'     => 'omts-frontend-source',
				'title'  => esc_html(
					$is_rule
						? sprintf(
							/* translators: 1: Rule position, 2: Rule description */
							__( 'Rule %1$d: %2$s', 'osom-multi-theme-switcher' ),
							(int) $match['index'] + 1,
							OMTS_Theme_Switcher::describe_rule( $match['rule'] )
						)
						: __( 'No rule matched — main theme', 'osom-multi-theme-switcher' )
				),
				'href'   => admin_url( 'themes.php?page=osom-multi-theme-switcher' ),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'omts-frontend-theme',
				'id'     => 'omts-frontend-manage',
				'title'  => esc_html__( 'Manage theme rules', 'osom-multi-theme-switcher' ),
				'href'   => admin_url( 'themes.php?page=osom-multi-theme-switcher' ),
			)
		);

		// Technical details go into a secondary group, the way core separates
		// "Edit My Profile" from the account details.
		$wp_admin_bar->add_group(
			array(
				'parent' => 'omts-frontend-theme',
				'id'     => 'omts-frontend-details',
				'meta'   => array( 'class' => 'ab-sub-secondary' ),
			)
		);

		$details = array(
			'stylesheet' => sprintf(
				/* translators: %s: Stylesheet directory */
				__( 'stylesheet: %s', 'osom-multi-theme-switcher' ),
				$stylesheet
			),
		);

		// The template only differs on child themes; skip the noise otherwise.
		if ( $template !== $stylesheet ) {
			$details['template'] = sprintf(
				/* translators: %s: Template directory */
				__( 'parent template: %s', 'osom-multi-theme-switcher' ),
				$template
			);
		}

		if ( $is_rule ) {
			$details['mode'] = sprintf(
				/* translators: %s: How the rule was matched */
				__( 'matched %s', 'osom-multi-theme-switcher' ),
				$this->get_mode_label( $match['mode'] )
			);
		}

		foreach ( $details as $key => $label ) {
			$wp_admin_bar->add_node(
				array(
					'parent' => 'omts-frontend-details',
					'id'     => 'omts-frontend-' . $key,
					'title'  => '<span class="omts-adminbar-note">' . esc_html( $label ) . '</span>',
				)
			);
		}
	}

	/**
	 * Human readable name for how a rule was matched.
	 *
	 * @since 1.3.0
	 *
	 * @param string $mode Match mode.
	 * @return string Label.
	 */
	private function get_mode_label( $mode ) {
		$labels = array(
			'early'   => __( 'by URL, before the query', 'osom-multi-theme-switcher' ),
			'query'   => __( 'by the WordPress query', 'osom-multi-theme-switcher' ),
			'preview' => __( 'preview request', 'osom-multi-theme-switcher' ),
			'editor'  => __( 'editor context', 'osom-multi-theme-switcher' ),
		);

		return isset( $labels[ $mode ] ) ? $labels[ $mode ] : $mode;
	}

	/**
	 * Enqueue admin bar styles.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_admin_bar_styles() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'omts-admin-bar-css',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin-bar-style.css',
			array(),
			OMTS_VERSION
		);
	}

	/**
	 * Enqueue admin bar scripts.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_admin_bar_scripts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_script(
			'omts-admin-bar-js',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin-bar-script.js',
			array( 'jquery' ),
			OMTS_VERSION,
			true
		);

		wp_localize_script(
			'omts-admin-bar-js',
			'omtsAdminBar',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'omts_admin_theme_nonce' ),
				'error'   => __( 'Could not switch the dashboard theme. Please try again.', 'osom-multi-theme-switcher' ),
			)
		);
	}
}
