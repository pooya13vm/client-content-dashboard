<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Article_Display {
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	private static function use_clean_layout() {
		if ( ! is_singular( 'post' ) ) { return false; }
		$settings = get_option( 'ccd_settings', array() );
		if ( empty( $settings['article_display_layout'] ) || 'clean' !== $settings['article_display_layout'] ) { return false; }
		return '' !== get_post_meta( get_queried_object_id(), '_ccd_content_template', true );
	}

	public static function template( $template ) {
		if ( ! self::use_clean_layout() ) { return $template; }
		$clean_template = CCD_DIR . 'templates/single-article.php';
		return file_exists( $clean_template ) ? $clean_template : $template;
	}

	public static function assets() {
		if ( self::use_clean_layout() ) {
			wp_enqueue_style( 'ccd-clean-article', CCD_URL . 'assets/css/article.css', array(), CCD_VERSION );
		}
	}
}
