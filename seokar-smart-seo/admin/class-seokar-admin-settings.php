<?php
/**
 * Seokar Admin Settings Class.
 *
 * Handles the plugin's settings page for Google Search Console integration.
 *
 * @package Seokar_Smart_SEO
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seokar_Admin_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page to the admin menu.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'تنظیمات سئوکار', 'seokar-smart-seo' ), // Page title
			__( 'سئوکار', 'seokar-smart-seo' ), // Menu title
			'manage_options', // Capability required
			'seokar-settings', // Menu slug
			array( $this, 'render_settings_page' ) // Callback function
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'seokar_settings_group', // Option group
			'seokar_gsc_settings',   // Option name
			array( $this, 'sanitize_gsc_settings' ) // Sanitize callback
		);

		add_settings_section(
			'seokar_gsc_section', // ID
			__( 'اتصال به Google Search Console', 'seokar-smart-seo' ), // Title
			array( $this, 'gsc_section_callback' ), // Callback
			'seokar-settings' // Page
		);

		add_settings_field(
			'seokar_gsc_client_id', // ID
			__( 'Client ID', 'seokar-smart-seo' ), // Title
			array( $this, 'client_id_callback' ), // Callback
			'seokar-settings', // Page
			'seokar_gsc_section' // Section
		);

		add_settings_field(
			'seokar_gsc_client_secret', // ID
			__( 'Client Secret', 'seokar-smart-seo' ), // Title
			array( $this, 'client_secret_callback' ), // Callback
			'seokar-settings', // Page
			'seokar_gsc_section' // Section
		);
	}

	/**
	 * Sanitize GSC settings.
	 *
	 * @param array $input The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_gsc_settings( $input ) {
		$new_input = array();
		if ( isset( $input['client_id'] ) ) {
			$new_input['client_id'] = sanitize_text_field( $input['client_id'] );
		}
		if ( isset( $input['client_secret'] ) ) {
			$new_input['client_secret'] = sanitize_text_field( $input['client_secret'] );
		}
		return $new_input;
	}

	/**
	 * GSC section callback.
	 */
	public function gsc_section_callback() {
		echo '<p>' . esc_html__( 'برای اتصال سئوکار به سرچ کنسول گوگل، باید یک پروژه در Google Cloud Console ایجاد کرده و Client ID و Client Secret خود را اینجا وارد کنید.', 'seokar-smart-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'مطمئن شوید که "URL بازگشت مجدد" (Authorized redirect URIs) در پروژه Google Cloud شما روی این مقدار تنظیم شده باشد:', 'seokar-smart-seo' ) . ' <code>' . esc_url( admin_url( 'options-general.php?page=seokar-settings' ) ) . '</code></p>';
	}

	/**
	 * Client ID field callback.
	 */
	public function client_id_callback() {
		$options = get_option( 'seokar_gsc_settings' );
		echo '<input type="text" id="seokar_gsc_client_id" name="seokar_gsc_settings[client_id]" value="' . esc_attr( $options['client_id'] ?? '' ) . '" class="regular-text" />';
	}

	/**
	 * Client Secret field callback.
	 */
	public function client_secret_callback() {
		$options = get_option( 'seokar_gsc_settings' );
		echo '<input type="text" id="seokar_gsc_client_secret" name="seokar_gsc_settings[client_secret]" value="' . esc_attr( $options['client_secret'] ?? '' ) . '" class="regular-text" />';
	}

	/**
	 * Render the settings page content.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'seokar-smart-seo' ) );
		}

		$gsc_integration = new Seokar_GSC_Integration();
		$gsc_connected   = $gsc_integration->is_connected();
		$site_url        = get_option( 'seokar_gsc_site_url' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'تنظیمات افزونه سئوکار', 'seokar-smart-seo' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'seokar_settings_group' ); ?>
				<?php do_settings_sections( 'seokar-settings' ); ?>
				<?php submit_button( __( 'ذخیره تنظیمات', 'seokar-smart-seo' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'وضعیت اتصال Google Search Console', 'seokar-smart-seo' ); ?></h2>
			<?php if ( $gsc_connected ) : ?>
				<p class="notice notice-success">
					<?php printf( esc_html__( 'شما به Google Search Console متصل هستید. وب‌سایت متصل: %s', 'seokar-smart-seo' ), '<strong>' . esc_html( $site_url ) . '</strong>' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'seokar_disconnect_gsc', 'seokar_gsc_disconnect_nonce' ); ?>
					<input type="hidden" name="seokar_action" value="disconnect_gsc" />
					<?php submit_button( __( 'قطع اتصال از سرچ کنسول', 'seokar-smart-seo' ), 'delete' ); ?>
				</form>
			<?php else : ?>
				<p class="notice notice-warning"><?php esc_html_e( 'شما به Google Search Console متصل نیستید.', 'seokar-smart-seo' ); ?></p>
				<?php
				$client_id     = get_option( 'seokar_gsc_settings' )['client_id'] ?? '';
				$client_secret = get_option( 'seokar_gsc_settings' )['client_secret'] ?? '';

				if ( ! empty( $client_id ) && ! empty( $client_secret ) ) {
					$auth_url = $gsc_integration->get_authorization_url();
					if ( $auth_url ) {
						echo '<p><a href="' . esc_url( $auth_url ) . '" class="button button-primary">' . esc_html__( 'اتصال به Google Search Console', 'seokar-smart-seo' ) . '</a></p>';
					} else {
						echo '<p class="notice notice-error">' . esc_html__( 'خطا در ایجاد URL احراز هویت. Client ID یا Client Secret را بررسی کنید.', 'seokar-smart-seo' ) . '</p>';
					}
				} else {
					echo '<p>' . esc_html__( 'برای اتصال، لطفاً ابتدا Client ID و Client Secret را در بالا وارد و ذخیره کنید.', 'seokar-smart-seo' ) . '</p>';
				}
			endif;

			// Handle OAuth callback (if redirected from Google)
			if ( isset( $_GET['code'] ) && ! empty( $_GET['code'] ) && isset( $_GET['state'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['state'] ) ), 'seokar_gsc_oauth_state' ) ) {
				$gsc_integration->handle_oauth_callback( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
			}

			// Handle disconnect request
			if ( isset( $_POST['seokar_action'] ) && $_POST['seokar_action'] === 'disconnect_gsc' ) {
				if ( ! isset( $_POST['seokar_gsc_disconnect_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seokar_gsc_disconnect_nonce'] ) ), 'seokar_disconnect_gsc' ) ) {
					wp_die( esc_html__( 'خطای امنیتی.', 'seokar-smart-seo' ) );
				}
				$gsc_integration->disconnect();
				echo '<meta http-equiv="refresh" content="0; url=' . esc_url( admin_url( 'options-general.php?page=seokar-settings' ) ) . '">'; // Refresh page
				exit;
			}
			?>
		</div>
		<?php
	}
}
