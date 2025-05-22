<?php
/**
 * Seokar Admin Dashboard Class.
 *
 * Handles the main plugin dashboard page.
 *
 * @package Seokar_Smart_SEO
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seokar_Admin_Dashboard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_dashboard_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );
	}

	/**
	 * Add the dashboard page to the admin menu.
	 */
	public function add_dashboard_page() {
		add_menu_page(
			__( 'داشبورد سئوکار', 'seokar-smart-seo' ), // Page title
			__( 'سئوکار', 'seokar-smart-seo' ), // Menu title
			'manage_options', // Capability required
			'seokar-dashboard', // Menu slug
			array( $this, 'render_dashboard_page' ), // Callback function
			'dashicons-chart-line', // Icon URL or Dashicon
			50 // Position in the menu
		);
	}

	/**
	 * Enqueue admin styles and scripts for the dashboard.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_styles_scripts( $hook ) {
		if ( 'toplevel_page_seokar-dashboard' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'seokar-admin-dashboard-style', SEOKAR_PLUGIN_URL . 'admin/css/seokar-admin.css', array(), SEOKAR_PLUGIN_VERSION );
		// Add any necessary JS here if complex interactions are added later.
	}


	/**
	 * Render the dashboard page content.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'seokar-smart-seo' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'داشبورد سئوکار', 'seokar-smart-seo' ); ?></h1>
			<p class="description"><?php esc_html_e( 'خلاصه‌ای از وضعیت سئوی داخلی وب‌سایت شما.', 'seokar-smart-seo' ); ?></p>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content" style="position: relative;">
						<div class="meta-box-sortables ui-sortable">

							<?php $this->render_overall_seo_summary(); ?>
							<?php $this->render_posts_with_weak_seo(); ?>

						</div>
					</div>
					<div id="postbox-container-1" class="postbox-container">
						<div class="meta-box-sortables">
							<?php $this->render_quick_links(); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the overall SEO summary section.
	 */
	private function render_overall_seo_summary() {
		// Fetch counts for posts with analysis data.
		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_seokar_seo_report',
					'compare' => 'EXISTS',
				),
			),
		);
		$analyzed_posts_ids = get_posts( $args );
		$total_analyzed_posts = count( $analyzed_posts_ids );

		$posts_good_seo = 0;
		$posts_medium_seo = 0;
		$posts_weak_seo = 0;

		foreach ( $analyzed_posts_ids as $post_id ) {
			$report = get_post_meta( $post_id, '_seokar_seo_report', true );
			if ( $report && isset( $report['score'] ) ) {
				if ( $report['score'] >= 80 ) {
					$posts_good_seo++;
				} elseif ( $report['score'] >= 50 ) {
					$posts_medium_seo++;
				} else {
					$posts_weak_seo++;
				}
			}
		}

		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e( 'خلاصه وضعیت کلی سئو', 'seokar-smart-seo' ); ?></h2>
			<div class="inside">
				<p><?php printf( esc_html__( 'تعداد کل نوشته‌ها/برگه‌های تحلیل شده: %d', 'seokar-smart-seo' ), $total_analyzed_posts ); ?></p>
				<ul>
					<li><?php printf( esc_html__( 'نوشته‌های با سئوی عالی (۸۰%+): %d', 'seokar-smart-seo' ), $posts_good_seo ); ?></li>
					<li><?php printf( esc_html__( 'نوشته‌های با سئوی متوسط (۵۰%-۷۹%): %d', 'seokar-smart-seo' ), $posts_medium_seo ); ?></li>
					<li><?php printf( esc_html__( 'نوشته‌های با سئوی ضعیف (کمتر از ۵۰%): %d', 'seokar-smart-seo' ), $posts_weak_seo ); ?></li>
				</ul>
				<?php if ( $total_analyzed_posts > 0 ) : ?>
					<p>
						<strong><?php esc_html_e( 'میانگین امتیاز سئو برای تمام نوشته‌ها:', 'seokar-smart-seo' ); ?></strong>
						<?php
						$total_score = 0;
						foreach ( $analyzed_posts_ids as $post_id ) {
							$report = get_post_meta( $post_id, '_seokar_seo_report', true );
							if ( $report && isset( $report['score'] ) ) {
								$total_score += $report['score'];
							}
						}
						echo esc_html( $total_analyzed_posts > 0 ? round( $total_score / $total_analyzed_posts, 2 ) . '%' : 'N/A' );
						?>
					</p>
				<?php else : ?>
					<p class="notice notice-info">
						<?php esc_html_e( 'هیچ نوشته‌ای هنوز توسط سئوکار تحلیل نشده است. لطفا یک نوشته را ویرایش و ذخیره کنید تا تحلیل آغاز شود.', 'seokar-smart-seo' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the section for posts with weak SEO.
	 */
	private function render_posts_with_weak_seo() {
		$weak_seo_posts_args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 10, // Show top 10 weakest.
			'meta_key'       => '_seokar_seo_report',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_seokar_seo_report',
					'compare' => 'EXISTS',
				),
			),
		);

		$weak_posts_query = new WP_Query( $weak_seo_posts_args );
		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e( 'نوشته‌هایی با وضعیت سئوی ضعیف', 'seokar-smart-seo' ); ?></h2>
			<div class="inside">
				<?php if ( $weak_posts_query->have_posts() ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'عنوان نوشته', 'seokar-smart-seo' ); ?></th>
								<th><?php esc_html_e( 'امتیاز سئو', 'seokar-smart-seo' ); ?></th>
								<th><?php esc_html_e( 'مشکلات عمده', 'seokar-smart-seo' ); ?></th>
								<th><?php esc_html_e( 'اقدامات', 'seokar-smart-seo' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							while ( $weak_posts_query->have_posts() ) :
								$weak_posts_query->the_post();
								$post_id = get_the_ID();
								$report  = get_post_meta( $post_id, '_seokar_seo_report', true );
								$problems = array();

								if ( $report ) {
									if ( ! $report['H1_in_title'] && $report['focus_keyword'] ) {
										$problems[] = __( 'کلمه کلیدی در عنوان نیست', 'seokar-smart-seo' );
									}
									if ( $report['keyword_density'] < 0.5 || $report['keyword_density'] > 2.5 ) {
										$problems[] = __( 'چگالی کلمه کلیدی نامناسب', 'seokar-smart-seo' );
									}
									if ( ! $report['content_length_ok'] ) {
										$problems[] = __( 'محتوای کوتاه', 'seokar-smart-seo' );
									}
									if ( ! $report['images_alt_present'] ) {
										$problems[] = __( 'Alt تصاویر ناموجود/خالی', 'seokar-smart-seo' );
									}
									if ( $report['internal_links'] === 0 ) {
										$problems[] = __( 'بدون لینک داخلی', 'seokar-smart-seo' );
									}
									if ( $report['external_links'] === 0 ) {
										$problems[] = __( 'بدون لینک خارجی', 'seokar-smart-seo' );
									}
									if ( ! $report['schema_faq_present'] ) {
										$problems[] = __( 'فاقد Schema FAQ', 'seokar-smart-seo' );
									}
								}
								?>
								<tr>
									<td>
										<strong><?php the_title(); ?></strong>
										<div class="row-actions">
											<span class="edit"><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><?php esc_html_e( 'ویرایش', 'seokar-smart-seo' ); ?></a> | </span>
											<span class="view"><a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'مشاهده', 'seokar-smart-seo' ); ?></a></span>
										</div>
									</td>
									<td><?php echo isset( $report['score'] ) ? esc_html( round( $report['score'], 1 ) ) . '%' : 'N/A'; ?></td>
									<td><?php echo ! empty( $problems ) ? esc_html( implode( ', ', $problems ) ) : esc_html__( 'مشکل خاصی نیست', 'seokar-smart-seo' ); ?></td>
									<td><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-small"><?php esc_html_e( 'بهبود سئو', 'seokar-smart-seo' ); ?></a></td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<p class="notice notice-success">
						<?php esc_html_e( 'تبریک! هیچ نوشته‌ای با وضعیت سئوی ضعیف در ۱۰ مورد اخیر یافت نشد (یا هنوز داده‌ای برای تحلیل نیست).', 'seokar-smart-seo' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render quick links section.
	 */
	private function render_quick_links() {
		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e( 'لینک‌های سریع', 'seokar-smart-seo' ); ?></h2>
			<div class="inside">
				<ul>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'تمام نوشته‌ها', 'seokar-smart-seo' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'تمام برگه‌ها', 'seokar-smart-seo' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'options-general.php?page=seokar-settings' ) ); ?>"><?php esc_html_e( 'تنظیمات سرچ کنسول', 'seokar-smart-seo' ); ?></a></li>
					<li><a href="https://developers.google.com/search/docs/fundamentals/seo-starter-guide" target="_blank"><?php esc_html_e( 'راهنمای شروع سئو گوگل', 'seokar-smart-seo' ); ?></a></li>
				</ul>
			</div>
		</div>
		<?php
	}
}
