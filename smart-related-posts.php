<?php
/**
 * Plugin Name: Smart Related Posts
 * Description: Related article cards ranked by shared categories and tags.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 * Text Domain: smart-related-posts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SRP_VERSION', '1.1.0' );
define( 'SRP_PATH', plugin_dir_path( __FILE__ ) );
require_once SRP_PATH . 'includes/admin.php';
require_once SRP_PATH . 'includes/related-posts.php';
require_once SRP_PATH . 'includes/render.php';

add_action( 'admin_init', 'srp_register_settings' );
add_action( 'admin_menu', 'srp_admin_menu' );
add_action( 'init', 'srp_register_shortcodes', PHP_INT_MAX );
add_action( 'wp_enqueue_scripts', 'srp_enqueue_styles' );

function srp_enqueue_styles() {
	wp_enqueue_style( 'srp-related-posts', plugins_url( 'assets/related-posts.css', __FILE__ ), array(), SRP_VERSION );
}

function srp_register_shortcodes() {
	add_shortcode( 'srp_related_posts', 'srp_shortcode' );
	if ( ! shortcode_exists( 'related_posts' ) ) {
		add_shortcode( 'related_posts', 'srp_shortcode' );
	} elseif ( 'srp_shortcode' !== $GLOBALS['shortcode_tags']['related_posts'] ) {
		add_action( 'admin_notices', 'srp_shortcode_notice' );
	}
}

function srp_shortcode_notice() {
	if ( current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Another plugin uses [related_posts]. Use [srp_related_posts] for Smart Related Posts.', 'smart-related-posts' ) . '</p></div>';
	}
}
