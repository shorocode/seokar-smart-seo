<?php
/**
 * Seokar SEO Analyzer Class.
 *
 * Handles the on-page SEO analysis of posts.
 *
 * @package Seokar_Smart_SEO
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seokar_SEO_Analyzer {

	/**
	 * Analyzes a given post for on-page SEO factors.
	 *
	 * @param int    $post_id The ID of the post to analyze.
	 * @param string $focus_keyword The focus keyword for analysis (optional).
	 * @return array The SEO analysis report.
	 */
	public static function analyze_post( $post_id, $focus_keyword = '' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'message' => __( 'Post not found for analysis.', 'seokar-smart-seo' ),
			);
		}

		$report = array(
			'H1_in_title'         => false,
			'keyword_density'     => 0,
			'content_length_ok'   => false,
			'images_alt_present'  => true,
			'internal_links'      => 0,
			'external_links'      => 0,
			'schema_faq_present'  => false,
			'suggestions'         => array(),
			'focus_keyword'       => $focus_keyword,
		);

		$post_title   = get_the_title( $post_id );
		$post_content = $post->post_content;
		$clean_content = wp_strip_all_tags( $post_content );
		$content_words = str_word_count( $clean_content );

		// 1. H1 and Keyword in Title
		$report['H1_in_title'] = true; // Assuming H1 is always the title by default in WordPress themes.
		if ( ! empty( $focus_keyword ) ) {
			if ( stripos( $post_title, $focus_keyword ) === false ) {
				$report['H1_in_title'] = false; // More specifically, keyword in title check.
				$report['suggestions'][] = sprintf(
					__( 'کلمه کلیدی فوکوس ("%s") در عنوان نوشته یافت نشد. سعی کنید آن را در عنوان قرار دهید.', 'seokar-smart-seo' ),
					esc_html( $focus_keyword )
				);
			}
		} else {
			// If no focus keyword, suggest adding one.
			$report['suggestions'][] = __( 'کلمه کلیدی فوکوس برای این نوشته تعریف نشده است. آن را در فیلد بالا وارد کنید تا تحلیل دقیق‌تری انجام شود.', 'seokar-smart-seo' );
		}

		// 2. Keyword Density
		if ( ! empty( $focus_keyword ) && $content_words > 0 ) {
			$keyword_count = substr_count( strtolower( $clean_content ), strtolower( $focus_keyword ) );
			$density       = ( $keyword_count / $content_words ) * 100;
			$report['keyword_density'] = round( $density, 2 );

			if ( $density < 0.5 ) { // A general low threshold
				$report['suggestions'][] = sprintf(
					__( 'چگالی کلمه کلیدی ("%s") شما پایین است (%.2f%%). سعی کنید کلمه کلیدی را به صورت طبیعی در محتوا تکرار کنید.', 'seokar-smart-seo' ),
					esc_html( $focus_keyword ),
					$density
				);
			} elseif ( $density > 2.5 ) { // A general high threshold
				$report['suggestions'][] = sprintf(
					__( 'چگالی کلمه کلیدی ("%s") شما بالا است (%.2f%%). مراقب Keyword Stuffing باشید و کلمات کلیدی مترادف را به کار ببرید.', 'seokar-smart-seo' ),
					esc_html( $focus_keyword ),
					$density
				);
			}
		}

		// 3. Content Length
		$min_content_length = 300; // Minimum words
		if ( $content_words >= $min_content_length ) {
			$report['content_length_ok'] = true;
		} else {
			$report['suggestions'][] = sprintf(
				__( 'طول محتوای شما (%d کلمه) کوتاه است. برای سئوی بهتر، محتوای خود را به حداقل %d کلمه افزایش دهید.', 'seokar-smart-seo' ),
				$content_words,
				$min_content_length
			);
		}

		// 4. Alt Text for Images
		preg_match_all( '/<img[^>]*src="([^"]*)"[^>]*>/i', $post_content, $img_tags );
		foreach ( $img_tags[0] as $img_tag ) {
			if ( strpos( $img_tag, 'alt="' ) === false || preg_match( '/alt=""/', $img_tag ) ) {
				$report['images_alt_present'] = false;
				$report['suggestions'][] = __( 'برخی از تصاویر فاقد ویژگی Alt Text یا دارای Alt Text خالی هستند. Alt Text مناسب به سئو تصاویر و دسترس‌پذیری کمک می‌کند.', 'seokar-smart-seo' );
				break;
			}
		}


		// 5. Internal and External Links
		$links_data = Seokar_Utilities::extract_links_from_content( $post_content, $post_id );
		$report['internal_links'] = count( $links_data['internal'] );
		$report['external_links'] = count( $links_data['external'] );

		if ( $report['internal_links'] === 0 ) {
			$report['suggestions'][] = __( 'این نوشته فاقد لینک داخلی است. لینک دادن به نوشته‌های مرتبط داخلی برای سئو و تجربه کاربری حیاتی است.', 'seokar-smart-seo' );
		}
		if ( $report['external_links'] === 0 ) {
			$report['suggestions'][] = __( 'این نوشته فاقد لینک خارجی معتبر است. لینک دادن به منابع خارجی معتبر می‌تواند به اعتبار محتوای شما کمک کند.', 'seokar-smart-seo' );
		}

		// 6. Schema FAQ
		if ( Seokar_Utilities::has_schema_faq( $post_id ) ) {
			$report['schema_faq_present'] = true;
		} else {
			$report['suggestions'][] = __( 'تگ‌های Schema FAQ در این نوشته یافت نشد. اگر پرسش و پاسخی دارید، با افزودن FAQ Schema می‌توانید در نتایج جستجو برجسته‌تر شوید.', 'seokar-smart-seo' );
		}

		// Overall score calculation (simple for v1.0)
		$score = 0;
		if ( $report['H1_in_title'] ) $score++;
		if ( $report['keyword_density'] >= 0.5 && $report['keyword_density'] <= 2.5 ) $score++;
		if ( $report['content_length_ok'] ) $score++;
		if ( $report['images_alt_present'] ) $score++;
		if ( $report['internal_links'] > 0 ) $score++;
		if ( $report['external_links'] > 0 ) $score++;
		if ( $report['schema_faq_present'] ) $score++;

		$report['score'] = ( $score / 7 ) * 100; // Max 7 points.

		return $report;
	}

	/**
	 * Displays a visual indicator (check/cross) based on a boolean value.
	 *
	 * @param bool $status The boolean status.
	 */
	public static function display_status_icon( $status ) {
		if ( $status ) {
			echo '<span style="color: green; font-size: 1.2em;" title="' . esc_attr__( 'Passed', 'seokar-smart-seo' ) . '">✔</span>'; // Checkmark
		} else {
			echo '<span style="color: red; font-size: 1.2em;" title="' . esc_attr__( 'Failed', 'seokar-smart-seo' ) . '">✘</span>'; // Cross
		}
	}
}
