<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Templates {
	public static function all() {
		$templates = array(
			'article' => array(
				'label'     => __( 'Article', 'client-content-dashboard' ),
				'post_type' => 'post',
				'fields'    => array(
					array( 'key' => 'title', 'label' => __( 'Title', 'client-content-dashboard' ), 'type' => 'text', 'required' => true, 'map' => 'post_title' ),
					array( 'key' => 'content', 'label' => __( 'Content', 'client-content-dashboard' ), 'type' => 'textarea', 'required' => true, 'map' => 'post_content' ),
					array( 'key' => 'featured_image', 'label' => __( 'Featured Image', 'client-content-dashboard' ), 'type' => 'image', 'map' => 'featured_image' ),
					array( 'key' => 'category', 'label' => __( 'Category', 'client-content-dashboard' ), 'type' => 'taxonomy', 'taxonomy' => 'category', 'map' => 'taxonomy' ),
					array( 'key' => 'seo_title', 'label' => __( 'SEO Title', 'client-content-dashboard' ), 'type' => 'text', 'map' => 'meta', 'meta_key' => '_ccd_seo_title' ),
					array( 'key' => 'meta_description', 'label' => __( 'Meta Description', 'client-content-dashboard' ), 'type' => 'textarea', 'map' => 'meta', 'meta_key' => '_ccd_meta_description' ),
				),
			),
		);

		return apply_filters( 'ccd_content_templates', $templates );
	}

	public static function get( $key ) {
		$templates = self::all();
		return isset( $templates[ $key ] ) ? $templates[ $key ] : null;
	}
}
