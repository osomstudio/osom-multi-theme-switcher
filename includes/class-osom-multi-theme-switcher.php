<?php
/**
 * Main Plugin Class
 *
 * Initializes and coordinates all plugin components.
 *
 * @package Osom_Multi_Theme_Switcher
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Osom_Multi_Theme_Switcher
 *
 * @since 1.0.0
 */
class Osom_Multi_Theme_Switcher {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.2';

	/**
	 * Single instance of the class.
	 *
	 * @var Osom_Multi_Theme_Switcher
	 */
	private static $instance = null;

	/**
	 * Theme switcher instance.
	 *
	 * @var OMTS_Theme_Switcher
	 */
	public $theme_switcher;

	/**
	 * Admin page instance.
	 *
	 * @var OMTS_Admin_Page
	 */
	public $admin_page;

	/**
	 * Admin bar instance.
	 *
	 * @var OMTS_Admin_Bar
	 */
	public $admin_bar;

	/**
	 * AJAX handler instance.
	 *
	 * @var OMTS_Ajax_Handler
	 */
	public $ajax_handler;

	/**
	 * ACF loader instance.
	 *
	 * @var OMTS_ACF_Loader
	 */
	public $acf_loader;

	/**
	 * Get single instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Osom_Multi_Theme_Switcher
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
	}

	/**
	 * Load required dependencies.
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-omts-theme-switcher.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-omts-admin-page.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-omts-admin-bar.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-omts-ajax-handler.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-omts-acf-loader.php';
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		// Initialize theme switcher (core functionality).
		$this->theme_switcher = new OMTS_Theme_Switcher();

		// Initialize ACF loader (loads ACF JSON from all themes).
		$this->acf_loader = new OMTS_ACF_Loader();

		// Initialize admin components.
		if ( is_admin() ) {
			$this->admin_page   = new OMTS_Admin_Page( $this->theme_switcher );
			$this->ajax_handler = new OMTS_Ajax_Handler( $this->theme_switcher, $this->admin_page );
		}

		// Initialize admin bar (shown on both admin and frontend).
		$this->admin_bar = new OMTS_Admin_Bar( $this->theme_switcher );
	}

	/**
	 * Get plugin version.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}
}
