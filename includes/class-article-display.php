<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Article_Display {
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		add_filter( 'get_the_categories', array( __CLASS__, 'categories' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	private static function use_clean_layout() {
		if ( ! is_singular( 'post' ) ) { return false; }
		$settings = get_option( 'ccd_settings', array() );
		$layout = isset( $settings['article_display_layout'] ) ? $settings['article_display_layout'] : 'clean';
		if ( 'clean' !== $layout ) { return false; }
		return '' !== get_post_meta( get_queried_object_id(), '_ccd_content_template', true );
	}

	public static function content( $content ) {
		if ( ! self::use_clean_layout() || ! in_the_loop() || ! is_main_query() ) { return $content; }
		if ( false !== strpos( $content, 'class="ccd-public-article"' ) ) { return $content; }
		return '<div class="ccd-public-article">' . $content . '</div>';
	}

	public static function categories( $categories, $post_id ) {
		if ( ! self::use_clean_layout() || (int) $post_id !== get_queried_object_id() ) { return $categories; }
		$default_category = absint( get_option( 'default_category' ) );
		return array_values( array_filter( $categories, function( $category ) use ( $default_category ) { return (int) $category->term_id !== $default_category; } ) );
	}

	public static function assets() {
		if ( self::use_clean_layout() ) {
			wp_enqueue_style( 'ccd-clean-article', CCD_URL . 'assets/css/article.css', array(), CCD_VERSION );
		}
	}
}
