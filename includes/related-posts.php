<?php
/** Bounded candidate selection and scoring; no presentation logic. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function srp_is_public_post( $post, $post_types = null ) {
	$post_types = $post_types ?? srp_get_settings()['post_types'];
	return $post instanceof WP_Post && isset( srp_public_post_types()[ $post->post_type ] ) && in_array( $post->post_type, $post_types, true ) && 'publish' === $post->post_status && '' === $post->post_password;
}

function srp_term_ids( $post_id, $taxonomy ) {
	$post = get_post( $post_id );
	if ( ! $post || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
		return array();
	}
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	$ids = array_unique( array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ) );
	return 'category' === $taxonomy ? array_values( array_diff( $ids, array( (int) get_option( 'default_category' ) ) ) ) : $ids;
}

function srp_get_related_ids( $source_id, $count = 3, $mode = 'both', $order = null ) {
	$settings = srp_get_settings();
	if ( ! srp_is_public_post( get_post( $source_id ), $settings['post_types'] ) ) {
		return array();
	}
	$count = srp_validate_setting( 'count', $count ) ?? 3;
	$mode  = srp_validate_setting( 'match', $mode ) ?? 'both';
	$order = srp_validate_setting( 'order', $order ) ?? $settings['order'];
	$signals = array();
	if ( 'tags' !== $mode ) {
		$signals['category'] = array( 'ids' => srp_term_ids( $source_id, 'category' ), 'weight' => 2 );
	}
	if ( 'categories' !== $mode ) {
		$signals['post_tag'] = array( 'ids' => srp_term_ids( $source_id, 'post_tag' ), 'weight' => 3 );
	}
	$tax_query = array( 'relation' => 'OR' );
	foreach ( $signals as $taxonomy => $signal ) {
		if ( $signal['ids'] ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $signal['ids'], 'include_children' => false );
		}
	}
	if ( 1 === count( $tax_query ) ) {
		return array();
	}
	$query = new WP_Query( array(
		'post_type' => $settings['post_types'], 'post_status' => 'publish', 'has_password' => false,
		'post__not_in' => array( (int) $source_id ), 'ignore_sticky_posts' => true,
		'posts_per_page' => 100, 'no_found_rows' => true,
		'orderby' => array( 'date' => 'DESC', 'ID' => 'ASC' ),
		'tax_query' => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Bounded taxonomy matching is the feature.
		'update_post_term_cache' => true, 'update_post_meta_cache' => false, 'suppress_filters' => false,
	) );
	$ranked = array();
	foreach ( $query->posts as $post ) {
		// Recheck safety in case a query filter changes the returned posts.
		if ( ! srp_is_public_post( $post, $settings['post_types'] ) || (int) $source_id === (int) $post->ID ) {
			continue;
		}
		$score = 0;
		foreach ( $signals as $taxonomy => $signal ) {
			$score += $signal['weight'] * count( array_intersect( $signal['ids'], srp_term_ids( $post->ID, $taxonomy ) ) );
		}
		if ( $score > 0 ) {
			$ranked[] = array( 'id' => $post->ID, 'score' => $score, 'date' => $post->post_date );
		}
	}
	if ( 'random' === $order ) {
		shuffle( $ranked );
	} else {
		usort( $ranked, function ( $a, $b ) {
			return ( $b['score'] <=> $a['score'] ) ?: ( strcmp( $b['date'], $a['date'] ) ?: ( $a['id'] <=> $b['id'] ) );
		} );
	}
	return array_slice( array_column( $ranked, 'id' ), 0, $count );
}
