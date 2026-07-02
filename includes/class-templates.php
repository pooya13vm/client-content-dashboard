<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Templates {
	public static function all() {
		$common = array(
			array( 'key' => 'title', 'label' => __( 'Title', 'client-content-dashboard' ), 'type' => 'text', 'required' => true, 'map' => 'post_title' ),
			array( 'key' => 'content', 'label' => __( 'Content', 'client-content-dashboard' ), 'type' => 'textarea', 'required' => true, 'map' => 'post_content' ),
			array( 'key' => 'featured_image', 'label' => __( 'Featured Image', 'client-content-dashboard' ), 'type' => 'image', 'map' => 'featured_image' ),
			array( 'key' => 'category', 'label' => __( 'Category', 'client-content-dashboard' ), 'type' => 'taxonomy', 'taxonomy' => 'category', 'map' => 'taxonomy' ),
			array( 'key' => 'seo_title', 'label' => __( 'SEO Title', 'client-content-dashboard' ), 'type' => 'text', 'map' => 'meta', 'meta_key' => '_ccd_seo_title' ),
			array( 'key' => 'meta_description', 'label' => __( 'Meta Description', 'client-content-dashboard' ), 'type' => 'textarea', 'map' => 'meta', 'meta_key' => '_ccd_meta_description' ),
		);

		$templates = array(
			'blog_article' => array( 'label' => __( 'Blog Article', 'client-content-dashboard' ), 'post_type' => 'post', 'fields' => $common ),
			'project' => array(
				'label' => __( 'Project / Case Study', 'client-content-dashboard' ),
				'post_type' => 'post',
				'fields' => array_merge( $common, array(
					array( 'key' => 'client_name', 'label' => __( 'Client Name', 'client-content-dashboard' ), 'type' => 'text', 'map' => 'meta', 'meta_key' => '_ccd_client_name' ),
					array( 'key' => 'project_url', 'label' => __( 'Project URL', 'client-content-dashboard' ), 'type' => 'url', 'map' => 'meta', 'meta_key' => '_ccd_project_url' ),
					array( 'key' => 'gallery', 'label' => __( 'Project Gallery', 'client-content-dashboard' ), 'type' => 'gallery', 'map' => 'meta', 'meta_key' => '_ccd_gallery' ),
				) ),
			),
			'announcement' => array(
				'label' => __( 'Announcement', 'client-content-dashboard' ),
				'post_type' => 'post',
				'fields' => array_merge( $common, array(
					array( 'key' => 'announcement_date', 'label' => __( 'Announcement Date', 'client-content-dashboard' ), 'type' => 'date', 'map' => 'meta', 'meta_key' => '_ccd_announcement_date' ),
				) ),
			),
		);

		return apply_filters( 'ccd_content_templates', $templates );
	}

	public static function get( $key ) {
		$templates = self::all();
		return isset( $templates[ $key ] ) ? $templates[ $key ] : null;
	}
}
