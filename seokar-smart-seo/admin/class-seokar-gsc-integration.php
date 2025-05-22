<?php
/**
 * Seokar Google Search Console Integration Class.
 *
 * Handles OAuth authentication and fetching data from Google Search Console API.
 *
 * @package Seokar_Smart_SEO
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seokar_GSC_Integration {

	const GSC_API_BASE_URL = 'https://www.googleapis.com/webmasters/v3/sites/';
	const GSC_OAUTH_AUTH_URL = 'https://accounts.google.com/o/oauth2/auth';
	const GSC_OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_seokar_refresh_gsc_data', array( $this, 'ajax_refresh_gsc_data' ) );
		// Ensure this hook runs early to handle redirects before other output.
		add_action( 'admin_init', array( $this, 'handle_oauth_callback_on_load' ) );
	}

	/**
	 * Handle OAuth callback if it happens on admin_init.
	 */
	public function handle_oauth_callback_on_load() {
		if ( isset( $_GET['code'] ) && ! empty( $_GET['code'] ) && isset( $_GET['state'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['state'] ) ), 'seokar_gsc_oauth_state' ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				$this->handle_oauth_callback( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
				// Redirect to clean the URL.
				wp_safe_redirect( admin_url( 'options-general.php?page=seokar-settings' ) );
				exit;
			}
		}
	}

	/**
	 * Check if GSC is connected (has refresh token).
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return (bool) get_option( 'seokar_gsc_refresh_token' );
	}

	/**
	 * Get the authorization URL for Google Search Console.
	 *
	 * @return string|false The authorization URL or false on error.
	 */
	public function get_authorization_url() {
		$options = get_option( 'seokar_gsc_settings' );
		$client_id = $options['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return false;
		}

		$redirect_uri = admin_url( 'options-general.php?page=seokar-settings' );
		$scope = 'https://www.googleapis.com/auth/webmasters.readonly'; // Read-only access for Search Console.
		$state = wp_create_nonce( 'seokar_gsc_oauth_state' ); // Security nonce.

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'scope'         => $scope,
			'access_type'   => 'offline', // Request a refresh token.
			'prompt'        => 'consent', // Force consent screen.
			'state'         => $state,
		);

		return self::GSC_OAUTH_AUTH_URL . '?' . build_query( $params );
	}

	/**
	 * Handle the OAuth callback and exchange authorization code for tokens.
	 *
	 * @param string $code The authorization code from Google.
	 * @return bool True on success, false on failure.
	 */
	public function handle_oauth_callback( $code ) {
		$options = get_option( 'seokar_gsc_settings' );
		$client_id     = $options['client_id'] ?? '';
		$client_secret = $options['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			// error_log( 'Seokar GSC: Client ID or Secret not set.' );
			return false;
		}

		$redirect_uri = admin_url( 'options-general.php?page=seokar-settings' );

		$body = array(
			'code'          => $code,
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'redirect_uri'  => $redirect_uri,
			'grant_type'    => 'authorization_code',
		);

		$response = wp_remote_post(
			self::GSC_OAUTH_TOKEN_URL,
			array(
				'body'    => $body,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			// error_log( 'Seokar GSC: Token exchange error - ' . $response->get_error_message() );
			add_settings_error( 'seokar_gsc_notice', 'gsc_token_error', __( 'خطا در تبادل توکن با گوگل: ', 'seokar-smart-seo' ) . $response->get_error_message(), 'error' );
			return false;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $response_body['access_token'] ) && isset( $response_body['refresh_token'] ) ) {
			update_option( 'seokar_gsc_access_token', $response_body['access_token'] );
			update_option( 'seokar_gsc_refresh_token', $response_body['refresh_token'] );
			update_option( 'seokar_gsc_token_expiry', time() + $response_body['expires_in'] - 60 ); // 1 minute buffer.

			// Attempt to select property
			$sites = $this->get_gsc_sites( $response_body['access_token'] );
			if ( $sites ) {
				// Try to find the current site's URL among GSC properties.
				$current_site_url = home_url();
				$found_site = false;
				foreach ( $sites as $site ) {
					if ( strpos( $current_site_url, rtrim( $site, '/' ) ) !== false ) {
						update_option( 'seokar_gsc_site_url', $site );
						$found_site = true;
						break;
					}
				}
				if ( ! $found_site ) {
					// Fallback to the first available property if none matches, or prompt user to select.
					// For v1.0, let's just pick the first verified site. Future versions can add a selector.
					if ( ! empty( $sites ) ) {
						update_option( 'seokar_gsc_site_url', $sites[0] );
						add_settings_error( 'seokar_gsc_notice', 'gsc_site_match_warning', __( 'وب‌سایت شما در سرچ کنسول یافت نشد. اولین سایت موجود به صورت پیش‌فرض انتخاب شد. لطفاً بررسی کنید که آیا این سایت صحیح است.', 'seokar-smart-seo' ) . ' <strong>' . esc_html( $sites[0] ) . '</strong>', 'warning' );
					} else {
						add_settings_error( 'seokar_gsc_notice', 'gsc_no_sites', __( 'هیچ وب‌سایتی در سرچ کنسول گوگل شما یافت نشد. اطمینان حاصل کنید که یک وب‌سایت در سرچ کنسول ثبت کرده‌اید و تأیید شده است.', 'seokar-smart-seo' ), 'error' );
					}
				}
			} else {
				add_settings_error( 'seokar_gsc_notice', 'gsc_site_fetch_error', __( 'خطا در دریافت لیست وب‌سایت‌ها از سرچ کنسول. لطفاً اتصال را مجدداً برقرار کنید.', 'seokar-smart-seo' ), 'error' );
			}

			add_settings_error( 'seokar_gsc_notice', 'gsc_connected', __( 'با موفقیت به Google Search Console متصل شدید!', 'seokar-smart-seo' ), 'success' );
			return true;
		} else {
			// error_log( 'Seokar GSC: Token exchange failed - ' . wp_json_encode( $response_body ) );
			$error_message = $response_body['error_description'] ?? $response_body['error'] ?? __( 'خطای نامشخص در تبادل توکن.', 'seokar-smart-seo' );
			add_settings_error( 'seokar_gsc_notice', 'gsc_token_fail', __( 'اتصال به Google Search Console ناموفق بود: ', 'seokar-smart-seo' ) . $error_message, 'error' );
			return false;
		}
	}

	/**
	 * Refresh access token using refresh token.
	 *
	 * @return string|false The new access token or false on failure.
	 */
	private function refresh_access_token() {
		$options = get_option( 'seokar_gsc_settings' );
		$client_id     = $options['client_id'] ?? '';
		$client_secret = $options['client_secret'] ?? '';
		$refresh_token = get_option( 'seokar_gsc_refresh_token' );

		if ( empty( $client_id ) || empty( $client_secret ) || empty( $refresh_token ) ) {
			return false;
		}

		$body = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'refresh_token' => $refresh_token,
			'grant_type'    => 'refresh_token',
		);

		$response = wp_remote_post(
			self::GSC_OAUTH_TOKEN_URL,
			array(
				'body'    => $body,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			// error_log( 'Seokar GSC: Access token refresh error - ' . $response->get_error_message() );
			return false;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $response_body['access_token'] ) ) {
			update_option( 'seokar_gsc_access_token', $response_body['access_token'] );
			update_option( 'seokar_gsc_token_expiry', time() + $response_body['expires_in'] - 60 ); // 1 minute buffer.
			return $response_body['access_token'];
		} else {
			// error_log( 'Seokar GSC: Access token refresh failed - ' . wp_json_encode( $response_body ) );
			// If refresh token fails, likely invalid, disconnect.
			$this->disconnect();
			return false;
		}
	}

	/**
	 * Get a valid access token, refreshing it if necessary.
	 *
	 * @return string|false The access token or false if not available/cannot be refreshed.
	 */
	private function get_valid_access_token() {
		$access_token = get_option( 'seokar_gsc_access_token' );
		$token_expiry = get_option( 'seokar_gsc_token_expiry' );

		if ( ! $access_token || time() >= $token_expiry ) {
			$access_token = $this->refresh_access_token();
		}

		return $access_token;
	}

	/**
	 * Fetch a list of verified sites from GSC.
	 *
	 * @param string $access_token The access token.
	 * @return array|false List of sites or false on failure.
	 */
	private function get_gsc_sites( $access_token ) {
		$url = self::GSC_API_BASE_URL;
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			),
			'timeout' => 15,
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// error_log( 'Seokar GSC: Failed to fetch sites - ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_message( $response ) ) );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['siteEntry'] ) && is_array( $body['siteEntry'] ) ) {
			return array_column( $body['siteEntry'], 'siteUrl' );
		}

		return false;
	}

	/**
	 * Disconnect GSC integration by deleting all tokens.
	 */
	public function disconnect() {
		delete_option( 'seokar_gsc_access_token' );
		delete_option( 'seokar_gsc_refresh_token' );
		delete_option( 'seokar_gsc_token_expiry' );
		delete_option( 'seokar_gsc_site_url' );
		add_settings_error( 'seokar_gsc_notice', 'gsc_disconnected', __( 'اتصال به Google Search Console قطع شد.', 'seokar-smart-seo' ), 'info' );
	}

	/**
	 * Fetch GSC data (clicks, CTR, position) for a specific post.
	 *
	 * @param int  $post_id The ID of the post.
	 * @param bool $force_refresh Whether to force refresh from GSC API.
	 * @return array|false GSC data or false on failure.
	 */
	public function fetch_gsc_data_for_post( $post_id, $force_refresh = false ) {
		if ( ! self::is_connected() ) {
			return false;
		}

		$cached_data = get_post_meta( $post_id, '_seokar_gsc_data', true );
		// Cache for 24 hours (or if no data/force refresh requested)
		if ( ! $force_refresh && $cached_data && isset( $cached_data['timestamp'] ) && ( time() - $cached_data['timestamp'] < DAY_IN_SECONDS ) ) {
			return $cached_data;
		}

		$access_token = $this->get_valid_access_token();
		$site_url     = get_option( 'seokar_gsc_site_url' );
		$post_url     = get_permalink( $post_id );

		if ( ! $access_token || ! $site_url || ! $post_url ) {
			return false;
		}

		// GSC API requires exact match, so trim trailing slash if it exists on the property but not permalink.
		// Or ensure permalink matches property format.
		if ( substr( $site_url, -1 ) === '/' && substr( $post_url, -1 ) !== '/' ) {
			$post_url = rtrim( $post_url, '/' );
		} elseif ( substr( $site_url, -1 ) !== '/' && substr( $post_url, -1 ) === '/' ) {
			// This might be tricky if site URL is non-slash but post URL is slash.
			// Ideally, site_url should be exactly as registered in GSC.
			$post_url = trailingslashit( $post_url ); // Ensure consistency if GSC site is always slash.
		}

		// Define date range (e.g., last 90 days)
		$end_date   = wp_date( 'Y-m-d' );
		$start_date = wp_date( 'Y-m-d', strtotime( '-90 days' ) ); // Last 3 months.

		$request_body = array(
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'dimensions' => array( 'page' ),
			'dimensionFilterGroups' => array(
				array(
					'filters' => array(
						array(
							'dimension' => 'page',
							'expression' => $post_url,
							'operator' => 'EQUALS',
						),
					),
				),
			),
			'rowLimit'   => 1,
		);

		$url = self::GSC_API_BASE_URL . urlencode( $site_url ) . '/searchAnalytics/query';
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $request_body ),
			'method'  => 'POST',
			'timeout' => 20,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			// error_log( 'Seokar GSC: Search Analytics query error - ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code !== 200 ) {
			// error_log( 'Seokar GSC: Search Analytics API error ' . $response_code . ' - ' . wp_json_encode( $response_body ) );
			return false;
		}

		if ( isset( $response_body['rows'][0] ) ) {
			$row = $response_body['rows'][0];
			$data = array(
				'clicks'   => $row['clicks'],
				'ctr'      => $row['ctr'] * 100, // Convert to percentage
				'position' => $row['position'],
				'timestamp' => time(),
			);
			update_post_meta( $post_id, '_seokar_gsc_data', $data );
			return $data;
		} else {
			// No data found for this URL in the given period.
			$data = array(
				'clicks'   => 0,
				'ctr'      => 0,
				'position' => 0,
				'timestamp' => time(),
				'message'  => __( 'داده‌ای برای این URL در بازه زمانی اخیر یافت نشد.', 'seokar-smart-seo' ),
			);
			update_post_meta( $post_id, '_seokar_gsc_data', $data );
			return $data;
		}
	}

	/**
	 * AJAX handler for refreshing GSC data.
	 */
	public function ajax_refresh_gsc_data() {
		check_ajax_referer( 'seokar_refresh_report_nonce', '_wpnonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'اجازه دسترسی ندارید.', 'seokar-smart-seo' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( empty( $post_id ) ) {
			wp_send_json_error( __( 'شناسه نوشته نامعتبر.', 'seokar-smart-seo' ) );
		}

		$result = $this->fetch_gsc_data_for_post( $post_id, true ); // Force refresh

		if ( $result ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( __( 'خطا در دریافت داده‌های سرچ کنسول. لطفاً تنظیمات GSC را بررسی کنید.', 'seokar-smart-seo' ) );
		}
	}
}
