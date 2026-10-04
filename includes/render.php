<?php
/** Shortcode input and card preparation. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function srp_shortcode( $atts = array() ) {
	$atts = is_array( $atts ) ? $atts : array();
	$settings = srp_get_settings();
	foreach ( array( 'count', 'layout', 'match', 'show_excerpt' ) as $key ) {
		if ( array_key_exists( $key, $atts ) ) {
			$value = srp_validate_setting( $key, $atts[ $key ] );
			if ( null !== $value ) {
				$settings[ $key ] = $value;
			}
		}
	}
	if ( array_key_exists( 'post_id', $atts ) ) {
		$id = is_scalar( $atts['post_id'] ) && ! is_bool( $atts['post_id'] ) ? filter_var( $atts['post_id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		if ( false === $id ) {
			return '';
		}
	} else {
		$id = in_the_loop() ? get_the_ID() : ( is_singular( $settings['post_types'] ) ? get_queried_object_id() : 0 );
	}
	return $id && srp_is_public_post( get_post( $id ) ) ? srp_render_related_posts( $id, $settings ) : '';
}

function srp_render_related_posts( $source_id, $settings ) {
	$ids = srp_get_related_ids( $source_id, $settings['count'], $settings['match'], $settings['order'] );
	$cards = array();
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! srp_is_public_post( $post ) ) {
			continue;
		}
		$title = wp_strip_all_tags( get_the_title( $id ) );
		$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
		// Remove shortcodes without executing them or the_content filters.
		$text = wp_strip_all_tags( strip_shortcodes( $text ), true );
		$cards[] = array(
			'id' => $id,
			'title' => '' !== trim( $title ) ? $title : __( 'Untitled article', 'smart-related-posts' ),
			'url' => get_permalink( $id ),
			'image' => $settings['show_image'] ? get_the_post_thumbnail( $id, 'medium_large', array( 'class' => 'srp-image', 'alt' => '', 'loading' => 'lazy' ) ) : '',
			'excerpt' => $settings['show_excerpt'] ? wp_trim_words( $text, $settings['excerpt_length'] ) : '',
		);
	}
	if ( ! $cards ) {
		return '';
	}
	$section_id = wp_unique_id( 'srp-heading-' );
	$styles = '--srp-accent: ' . $settings['accent_color'] . '; --srp-card-background: ' . $settings['card_background'] . '; --srp-radius: ' . $settings['corner_radius'] . 'px; --srp-card-text: ' . srp_card_text_color( $settings['card_background'] ) . ';';
	ob_start();
	include SRP_PATH . 'templates/related-posts.php';
	return ob_get_clean();
}

/** Keep card text readable on custom backgrounds, retaining the default dark text. */
function srp_card_text_color( $background ) {
	$channels = array_map( function ( $hex ) {
		$value = hexdec( $hex ) / 255;
		return $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}, str_split( substr( $background, 1 ), 2 ) );
	$luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	// 0.067642 is the default text luminance (#242424) plus 0.05.
	if ( ( $luminance + 0.05 ) / 0.067642 >= 4.5 ) {
		return '#242424';
	}
	return 1.05 / ( $luminance + 0.05 ) >= 4.5 ? '#ffffff' : '#000000';
}
