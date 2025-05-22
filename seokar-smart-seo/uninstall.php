<?php
/**
 * Uninstall Plugin.
 *
 * Cleans up options and post meta created by Seokar Smart SEO plugin.
 *
 * @package Seokar_Smart_SEO
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data.
 */
function seokar_uninstall_cleanup() {
	// Delete plugin options.
	delete_option( 'seokar_gsc_settings' );
	delete_option( 'seokar_gsc_access_token' );
	delete_option( 'seokar_gsc_refresh_token' );
	delete_option( 'seokar_gsc_token_expiry' );
	delete_option( 'seokar_gsc_site_url' );

	// Delete post meta data.
	// This can be resource intensive on large sites. Consider running as a background process for very large sites.
	$args = array(
		'post_type'      => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => '_seokar_seo_report',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_seokar_gsc_data',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_seokar_focus_keyword',
				'compare' => 'EXISTS',
			),
		),
	);
	$posts_to_clean = get_posts( $args );

	if ( $posts_to_clean ) {
		foreach ( $posts_to_clean as $post_id ) {
			delete_post_meta( $post_id, '_seokar_seo_report' );
			delete_post_meta( $post_id, '_seokar_gsc_data' );
			delete_post_meta( $post_id, '_seokar_focus_keyword' );
		}
	}
}

// Run the cleanup function.
seokar_uninstall_cleanup();
