<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Activator {
	public static function activate() {
		add_role(
			'client_editor',
			__( 'Client Editor', 'client-content-dashboard' ),
			array(
				'read'                   => true,
				'upload_files'           => true,
				'edit_posts'             => true,
				'edit_published_posts'   => true,
				'publish_posts'          => false,
				'delete_posts'           => true,
				'delete_published_posts' => false,
			)
		);

		if ( false === get_option( 'ccd_settings' ) ) {
			add_option( 'ccd_settings', array(
				'dashboard_page_id'   => 0,
				'default_post_status' => 'draft',
				'hide_wp_admin'       => 1,
				'max_upload_mb'       => 5,
				'max_gallery_images'  => 8,
			) );
		}
	}

	public static function deactivate() {
		// Preserve the role and content so reactivation is non-destructive.
	}
}
