<?php
/**
 * Admin Bar Class
 *
 * Handles the admin bar theme switcher menu.
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_scripts' ) );
	}

	/**
	 * Add admin bar menu for theme switching.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show in admin area.
		if ( ! is_admin() ) {
			return;
		}

		$themes              = wp_get_themes();
		$current_admin_theme = $this->theme_switcher->get_admin_theme_preference();
		$active_theme        = $current_admin_theme ? $current_admin_theme : get_option( 'stylesheet' );
		$active_theme_name   = $this->theme_switcher->get_theme_name( $active_theme );

		// Add parent menu.
		$wp_admin_bar->add_node(
			array(
				'id'    => 'omts-admin-theme-switcher',
				'title' => '<span class="ab-icon dashicons dashicons-admin-appearance"></span><span class="ab-label">' . esc_html__( 'Dashboard theme: ', 'osom-multi-theme-switcher' ) . esc_html( $active_theme_name ) . '</span>',
				'href'  => '#',
				'meta'  => array(
					'class' => 'omts-admin-bar-menu',
				),
			)
		);

		// Add each theme as a submenu item.
		foreach ( $themes as $theme_slug => $theme_obj ) {
			$is_current = ( $active_theme === $theme_slug );
			$title      = $is_current ? '<span class="omts-current-indicator">✓</span> ' . esc_html( $theme_obj->get( 'Name' ) ) : esc_html( $theme_obj->get( 'Name' ) );
			$nonce      = wp_create_nonce( 'omts_admin_theme_nonce' );

			$wp_admin_bar->add_node(
				array(
					'parent' => 'omts-admin-theme-switcher',
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
			'1.0.0'
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
			'1.2.1',
			true
		);

		wp_localize_script(
			'omts-admin-bar-js',
			'omtsAdminBar',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'omts_admin_theme_nonce' ),
			)
		);
	}
}
