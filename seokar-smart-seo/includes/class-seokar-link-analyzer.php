<?php
/**
 * Seokar Link Analyzer Class.
 *
 * Handles internal linking analysis and suggestions.
 *
 * @package Seokar_Smart_SEO
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Seokar_Link_Analyzer {

	/**
	 * Finds posts that link to the given post.
	 * Note: This can be resource intensive on large sites.
	 * Caching or a more advanced indexing solution might be needed for very large sites.
	 *
	 * @param int $post_id The ID of the post to check.
	 * @return array An array of posts linking to this post, with their anchor texts.
	 */
	public static function get_incoming_links( $post_id ) {
		$incoming_links = array();
		$post_url       = get_permalink( $post_id );

		if ( ! $post_url ) {
			return $incoming_links;
		}

		$args = array(
			'post_type'      => array( 'post', 'page' ), // Can be extended to other public post types.
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_seokar_seo_report', // Only query posts that have been analyzed by Seokar.
					'compare' => 'EXISTS',
				),
			),
		);

		$all_posts = get_posts( $args );

		foreach ( $all_posts as $linked_post_id ) {
			if ( $linked_post_id === $post_id ) {
				continue; // Skip self.
			}

			$content = get_post_field( 'post_content', $linked_post_id );
			preg_match_all( '/<a\s+(?:[^>]*?\s+)?href="([^"]*)"[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER );

			if ( ! empty( $matches ) ) {
				foreach ( $matches as $match ) {
					$url  = esc_url_raw( $match[1] );
					$text = wp_strip_all_tags( $match[2] );

					if ( $url === $post_url ) {
						$incoming_links[] = array(
							'post_id'     => $linked_post_id,
							'post_title'  => get_the_title( $linked_post_id ),
							'post_edit_link' => get_edit_post_link( $linked_post_id ),
							'anchor_text' => ! empty( $text ) ? $text : __( '[بدون متن لنگر]', 'seokar-smart-seo' ),
						);
					}
				}
			}
		}

		return $incoming_links;
	}

	/**
	 * Suggests related internal links based on common categories/tags.
	 *
	 * @param int $post_id The ID of the current post.
	 * @param int $limit The maximum number of suggestions.
	 * @return array An array of suggested related posts.
	 */
	public static function suggest_internal_links( $post_id, $limit = 5 ) {
		$suggestions = array();
		$current_post = get_post( $post_id );

		if ( ! $current_post || ! in_array( $current_post->post_type, array( 'post', 'page' ) ) ) {
			return $suggestions;
		}

		$args = array(
			'post_type'      => $current_post->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 2, // Fetch more to filter out current post and ensure variety.
			'post__not_in'   => array( $post_id ),
			'orderby'        => 'rand',
		);

		$tax_query = array( 'relation' => 'OR' );

		// Get categories
		$categories = wp_get_post_categories( $post_id );
		if ( ! empty( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $categories,
			);
		}

		// Get tags
		$tags = wp_get_post_tags( $post_id );
		if ( ! empty( $tags ) ) {
			$tag_ids = wp_list_pluck( $tags, 'term_id' );
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tag_ids,
			);
		}

		if ( count( $tax_query ) > 1 ) { // If any categories or tags are present.
			$args['tax_query'] = $tax_query;
		}

		$related_posts = get_posts( $args );

		foreach ( $related_posts as $related_post ) {
			$suggestions[] = array(
				'title' => get_the_title( $related_post->ID ),
				'url'   => get_permalink( $related_post->ID ),
			);
			if ( count( $suggestions ) >= $limit ) {
				break;
			}
		}

		return $suggestions;
	}
}
