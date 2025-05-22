<?php
/**
 * Plugin Name: افزونه سئوکار – تحلیلگر هوشمند سئوی داخلی
 * Plugin URI: https://example.com/seokar-smart-seo
 * Description: یک افزونه قدرتمند برای تحلیل و بهبود سئوی داخلی (on-page SEO) و لینک‌سازی داخلی نوشته‌های وردپرس با اتصال به سرچ کنسول گوگل.
 * Version: 1.0.0
 * Author: Your Name/Company Name
 * Author URI: https://example.com
 * Text Domain: seokar-smart-seo
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define constants for plugin paths and URLs.
 */
if ( ! defined( 'SEOKAR_PLUGIN_VERSION' ) ) {
	define( 'SEOKAR_PLUGIN_VERSION', '1.0.0' );
}
if ( ! defined( 'SEOKAR_PLUGIN_DIR' ) ) {
	define( 'SEOKAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SEOKAR_PLUGIN_URL' ) ) {
	define( 'SEOKAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * The core plugin class.
 */
class Seokar_Smart_SEO {

	/**
	 * Instance of the plugin.
	 *
	 * @var Seokar_Smart_SEO
	 */
	protected static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_hooks();
		$this->includes();
		$this->instantiate_classes();
	}

	/**
	 * Get the singleton instance of the plugin.
	 *
	 * @return Seokar_Smart_SEO
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Define activation and deactivation hooks.
	 */
	private function define_hooks() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Include necessary files.
	 */
	private function includes() {
		// Core analysis classes
		require_once SEOKAR_PLUGIN_DIR . 'includes/class-seokar-utilities.php';
		require_once SEOKAR_PLUGIN_DIR . 'includes/class-seokar-seo-analyzer.php';
		require_once SEOKAR_PLUGIN_DIR . 'includes/class-seokar-link-analyzer.php';

		// Admin classes
		if ( is_admin() ) {
			require_once SEOKAR_PLUGIN_DIR . 'admin/class-seokar-admin-dashboard.php';
			require_once SEOKAR_PLUGIN_DIR . 'admin/class-seokar-admin-metabox.php';
			require_once SEOKAR_PLUGIN_DIR . 'admin/class-seokar-admin-settings.php';
			require_once SEOKAR_PLUGIN_DIR . 'admin/class-seokar-gsc-integration.php';
		}
	}

	/**
	 * Instantiate main classes.
	 */
	private function instantiate_classes() {
		if ( is_admin() ) {
			new Seokar_Admin_Dashboard();
			new Seokar_Admin_Metabox();
			new Seokar_Admin_Settings();
			new Seokar_GSC_Integration();
		}
	}

	/**
	 * Load plugin textdomain for translation.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'seokar-smart-seo', false, basename( SEOKAR_PLUGIN_DIR ) . '/languages/' );
	}

	/**
	 * Runs on plugin activation.
	 */
	public function activate() {
		// Flush rewrite rules to ensure dashboard permalinks work.
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public function deactivate() {
		// Nothing specific needed on deactivation for now, but good to have.
	}
}

/**
 * Initialize the plugin.
 *
 * @return Seokar_Smart_SEO
 */
function seokar_run_smart_seo() {
	return Seokar_Smart_SEO::get_instance();
}

// Start the plugin.
seokar_run_smart_seo();
