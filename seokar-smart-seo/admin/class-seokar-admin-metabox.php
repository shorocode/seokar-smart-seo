<?php
/**
 * Seokar Admin Metabox Class.
 *
 * Handles the custom metabox in post edit screen for SEO analysis.
 *
 * @package Seokar_Smart_SEO
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seokar_Admin_Metabox {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_seo_metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox_data' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_metabox_scripts' ) );
	}

	/**
	 * Enqueue scripts for the metabox.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_metabox_scripts( $hook ) {
		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			wp_enqueue_script( 'seokar-metabox-script', SEOKAR_PLUGIN_URL . 'admin/js/seokar-metabox.js', array( 'jquery' ), SEOKAR_PLUGIN_VERSION, true );
			wp_localize_script(
				'seokar-metabox-script',
				'seokar_ajax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'seokar_refresh_report_nonce' ),
				)
			);
			wp_enqueue_style( 'seokar-metabox-style', SEOKAR_PLUGIN_URL . 'admin/css/seokar-admin.css', array(), SEOKAR_PLUGIN_VERSION );
		}
	}

	/**
	 * Add the SEO report metabox to the post edit screen.
	 */
	public function add_seo_metabox() {
		$post_types = get_post_types( array( 'public' => true ), 'names' ); // Get all public post types
		$exclude_types = array( 'attachment', 'product', 'shop_order', 'shop_coupon' ); // Exclude some common types.
		$post_types    = array_diff( $post_types, $exclude_types );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'seokar_seo_report',
				__( 'گزارش سئوکار – تحلیلگر هوشمند سئوی داخلی', 'seokar-smart-seo' ),
				array( $this, 'render_metabox_content' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the content of the SEO report metabox.
	 *
	 * @param WP_Post $post The current post object.
	 */
	public function render_metabox_content( $post ) {
		wp_nonce_field( 'seokar_metabox_save', 'seokar_metabox_nonce' );

		$focus_keyword = get_post_meta( $post->ID, '_seokar_focus_keyword', true );
		$seo_report    = get_post_meta( $post->ID, '_seokar_seo_report', true );
		$gsc_data      = get_post_meta( $post->ID, '_seokar_gsc_data', true );
		$incoming_links = Seokar_Link_Analyzer::get_incoming_links( $post->ID );
		$link_suggestions = Seokar_Link_Analyzer::suggest_internal_links( $post->ID, 3 );

		// If no report or not fresh, generate a new one.
		if ( ! $seo_report || ( isset( $seo_report['timestamp'] ) && ( time() - $seo_report['timestamp'] > DAY_IN_SECONDS ) ) ) {
			$seo_report = Seokar_SEO_Analyzer::analyze_post( $post->ID, $focus_keyword );
			update_post_meta( $post->ID, '_seokar_seo_report', array_merge( $seo_report, array( 'timestamp' => time() ) ) );
		}

		?>
		<div class="seokar-metabox">
			<div class="seokar-field-group">
				<label for="seokar_focus_keyword"><strong><?php esc_html_e( 'کلمه کلیدی فوکوس:', 'seokar-smart-seo' ); ?></strong></label>
				<input type="text" id="seokar_focus_keyword" name="seokar_focus_keyword" value="<?php echo esc_attr( $focus_keyword ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'کلمه کلیدی اصلی این نوشته را وارد کنید...', 'seokar-smart-seo' ); ?>">
				<p class="description"><?php esc_html_e( 'این کلمه کلیدی برای تحلیل چگالی کلمات و حضور آن در عنوان استفاده می‌شود.', 'seokar-smart-seo' ); ?></p>
			</div>

			<div class="seokar-report-section">
				<h3><?php esc_html_e( 'خلاصه سئوی داخلی', 'seokar-smart-seo' ); ?></h3>
				<?php if ( $seo_report && isset( $seo_report['success'] ) && ! $seo_report['success'] ) : ?>
					<p class="notice notice-warning"><?php echo esc_html( $seo_report['message'] ); ?></p>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'امتیاز سئو (تخمینی):', 'seokar-smart-seo' ); ?></strong>
						<span style="font-size: 1.1em; font-weight: bold; color: <?php echo esc_attr( $seo_report['score'] >= 80 ? 'green' : ( $seo_report['score'] >= 50 ? 'orange' : 'red' ) ); ?>;">
							<?php echo esc_html( round( $seo_report['score'], 1 ) ); ?>%
						</span>
					</p>
					<ul class="seokar-checklist">
						<li>
							<?php Seokar_SEO_Analyzer::display_status_icon( $seo_report['H1_in_title'] ); ?>
							<?php esc_html_e( 'کلمه کلیدی فوکوس در عنوان نوشته', 'seokar-smart-seo' ); ?>
							<?php echo $seo_report['H1_in_title'] && ! empty( $focus_keyword ) ? '<small>(' . esc_html( $focus_keyword ) . ')</small>' : ''; ?>
						</li>
						<li>
							<?php Seokar_SEO_Analyzer::display_status_icon( $seo_report['keyword_density'] >= 0.5 && $seo_report['keyword_density'] <= 2.5 ); ?>
							<?php esc_html_e( 'چگالی کلمات کلیدی مناسب', 'seokar-smart-seo' ); ?>
							<?php echo ! empty( $focus_keyword ) ? '<small>(' . esc_html( round( $seo_report['keyword_density'], 2 ) ) . '%)</small>' : '<small>' . esc_html__( 'کلمه کلیدی فوکوس تعریف نشده است.', 'seokar-smart-seo' ) . '</small>'; ?>
						</li>
						<li>
							<?php Seokar_SEO_Analyzer::display_status_icon( $seo_report['content_length_ok'] ); ?>
							<?php esc_html_e( 'طول محتوا کافی', 'seokar-smart-seo' ); ?>
						</li>
						<li>
							<?php Seokar_SEO_Analyzer::display_status_icon( $seo_report['images_alt_present'] ); ?>
							<?php esc_html_e( 'تمام تصاویر دارای Alt Text هستند', 'seokar-smart-seo' ); ?>
						</li>
						<li>
							<?php Seokar_SEO_Analyzer::display_status_icon( $seo_report['internal_links'] > 0 ); ?>
							<?php printf( esc_html__( 'وجود لینک‌های داخلی (%d)', 'seokar-smart-seo' ), absint( $seo_report['internal_links'] ) ); ?>
						</li>
						<li>
							<?php Seokar_SEO_Analyzer::display_status_icon( $seo_report['external_links'] > 0 ); ?>
							<?php printf( esc_html__( 'وجود لینک‌های خارجی (%d)', 'seokar-smart-seo' ), absint( $seo_report['external_links'] ) ); ?>
						</li>
						<li>
							<?php Seokar_SEO_Analyzer::display_status_icon( $seo_report['schema_faq_present'] ); ?>
							<?php esc_html_e( 'وجود Schema FAQ', 'seokar-smart-seo' ); ?>
						</li>
					</ul>

					<?php if ( ! empty( $seo_report['suggestions'] ) ) : ?>
						<h4><?php esc_html_e( 'پیشنهادات برای بهبود:', 'seokar-smart-seo' ); ?></h4>
						<ul class="seokar-suggestions">
							<?php foreach ( $seo_report['suggestions'] as $suggestion ) : ?>
								<li>● <?php echo esc_html( $suggestion ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="notice notice-success"><?php esc_html_e( 'محتوای شما از نظر سئوی داخلی در وضعیت خوبی قرار دارد!', 'seokar-smart-seo' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="seokar-report-section">
				<h3><?php esc_html_e( 'لینک‌سازی داخلی', 'seokar-smart-seo' ); ?></h3>
				<h4><?php esc_html_e( 'لینک‌های ورودی به این نوشته:', 'seokar-smart-seo' ); ?></h4>
				<?php if ( ! empty( $incoming_links ) ) : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'نوشته لینک‌دهنده', 'seokar-smart-seo' ); ?></th>
								<th><?php esc_html_e( 'Anchor Text', 'seokar-smart-seo' ); ?></th>
								<th><?php esc_html_e( 'اقدامات', 'seokar-smart-seo' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $incoming_links as $link ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( $link['post_edit_link'] ); ?>"><?php echo esc_html( $link['post_title'] ); ?></a></td>
									<td><?php echo esc_html( $link['anchor_text'] ); ?></td>
									<td><a href="<?php echo esc_url( $link['post_edit_link'] ); ?>" class="button button-small"><?php esc_html_e( 'ویرایش', 'seokar-smart-seo' ); ?></a></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="notice notice-warning"><?php esc_html_e( 'هیچ نوشته دیگری به این نوشته لینک نداده است. برای تقویت لینک‌سازی داخلی، به این نوشته لینک دهید.', 'seokar-smart-seo' ); ?></p>
				<?php endif; ?>

				<h4><?php esc_html_e( 'پیشنهاد لینک‌های داخلی مرتبط:', 'seokar-smart-seo' ); ?></h4>
				<?php if ( ! empty( $link_suggestions ) ) : ?>
					<ul>
						<?php foreach ( $link_suggestions as $suggestion ) : ?>
							<li><a href="<?php echo esc_url( $suggestion['url'] ); ?>" target="_blank"><?php echo esc_html( $suggestion['title'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
					<p class="description"><?php esc_html_e( 'می‌توانید از این نوشته‌ها به عنوان منبع یا مکمل در محتوای فعلی لینک دهید.', 'seokar-smart-seo' ); ?></p>
				<?php else : ?>
					<p class="notice notice-info"><?php esc_html_e( 'در حال حاضر پیشنهاد لینک داخلی مرتبطی یافت نشد.', 'seokar-smart-seo' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="seokar-report-section">
				<h3><?php esc_html_e( 'آمار Google Search Console', 'seokar-smart-seo' ); ?></h3>
				<?php if ( Seokar_GSC_Integration::is_connected() ) : ?>
					<?php if ( ! empty( $gsc_data ) && isset( $gsc_data['clicks'] ) ) : ?>
						<p><strong><?php esc_html_e( 'کلیک‌ها:', 'seokar-smart-seo' ); ?></strong> <?php echo absint( $gsc_data['clicks'] ); ?></p>
						<p><strong><?php esc_html_e( 'CTR:', 'seokar-smart-seo' ); ?></strong> <?php echo esc_html( round( $gsc_data['ctr'], 2 ) ); ?>%</p>
						<p><strong><?php esc_html_e( 'میانگین Position:', 'seokar-smart-seo' ); ?></strong> <?php echo esc_html( round( $gsc_data['position'], 2 ) ); ?></p>
						<p><small><?php printf( esc_html__( 'آخرین به‌روزرسانی: %s', 'seokar-smart-seo' ), esc_html( human_time_diff( $gsc_data['timestamp'], current_time( 'timestamp' ) ) ) . ' ' . __( 'پیش', 'seokar-smart-seo' ) ); ?></small></p>
						<button type="button" class="button button-secondary seokar-refresh-gsc-data" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'تازه‌سازی داده‌های سرچ کنسول', 'seokar-smart-seo' ); ?></button>
						<span class="seokar-refresh-gsc-status"></span>
					<?php else : ?>
						<p class="notice notice-info"><?php esc_html_e( 'داده‌های Google Search Console برای این نوشته هنوز دریافت نشده است. لطفاً نوشته را یک بار ذخیره کنید یا روی دکمه "تازه‌سازی داده‌های سرچ کنسول" کلیک کنید.', 'seokar-smart-seo' ); ?></p>
						<button type="button" class="button button-secondary seokar-refresh-gsc-data" data-post-id="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'تازه‌سازی داده‌های سرچ کنسول', 'seokar-smart-seo' ); ?></button>
						<span class="seokar-refresh-gsc-status"></span>
					<?php endif; ?>
				<?php else : ?>
					<p class="notice notice-warning"><?php esc_html_e( 'برای مشاهده آمار Google Search Console، ابتدا باید افزونه را به حساب خود متصل کنید.', 'seokar-smart-seo' ); ?></p>
					<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=seokar-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'اتصال به سرچ کنسول', 'seokar-smart-seo' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save metabox data.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_metabox_data( $post_id ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['seokar_metabox_nonce'] ) ) {
			return $post_id;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['seokar_metabox_nonce'] ) ), 'seokar_metabox_save' ) ) {
			return $post_id;
		}

		// Check if the current user has permission to edit the post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Sanitize and save focus keyword.
		$focus_keyword = isset( $_POST['seokar_focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['seokar_focus_keyword'] ) ) : '';
		update_post_meta( $post_id, '_seokar_focus_keyword', $focus_keyword );

		// Re-analyze SEO and save report.
		$seo_report = Seokar_SEO_Analyzer::analyze_post( $post_id, $focus_keyword );
		update_post_meta( $post_id, '_seokar_seo_report', array_merge( $seo_report, array( 'timestamp' => time() ) ) );

		// Fetch GSC data if connected.
		if ( Seokar_GSC_Integration::is_connected() ) {
			$gsc_integration = new Seokar_GSC_Integration();
			$gsc_data_status = $gsc_integration->fetch_gsc_data_for_post( $post_id, true ); // true to force refresh.
			// Error handling for GSC data can be improved if needed.
		}
	}
}
