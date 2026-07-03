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
				'publish_posts'          => true,
			)
		);

		if ( false === get_option( 'ccd_settings' ) ) {
			add_option( 'ccd_settings', array(
				'dashboard_page_id'   => 0,
				'default_post_status' => 'draft',
				'hide_wp_admin'       => 1,
				'max_upload_mb'       => 5,
				'max_gallery_images'  => 8,
				'article_display_layout' => 'clean',
			) );
		}

		CCD_Dashboard_Page::create_on_activation();
		self::maybe_upgrade_role();
	}

	public static function maybe_upgrade_role() {
		if ( '0.2.5' === get_option( 'ccd_role_schema_version' ) ) { return; }
		$role = get_role( 'client_editor' );
		if ( ! $role ) {
			add_role( 'client_editor', __( 'Client Editor', 'client-content-dashboard' ), array() );
			$role = get_role( 'client_editor' );
		}
		if ( $role ) {
			$allowed = array( 'read', 'upload_files', 'edit_posts', 'edit_published_posts', 'publish_posts' );
			foreach ( $allowed as $capability ) { $role->add_cap( $capability, true ); }
			$denied = array( 'edit_pages', 'edit_published_pages', 'edit_others_pages', 'edit_others_posts', 'delete_posts', 'delete_published_posts', 'delete_others_posts', 'delete_pages', 'delete_published_pages', 'delete_others_pages', 'manage_options', 'manage_categories', 'edit_users', 'promote_users', 'create_users', 'delete_users', 'list_users', 'activate_plugins', 'edit_plugins', 'install_plugins', 'update_plugins', 'edit_theme_options', 'switch_themes' );
			foreach ( $denied as $capability ) { $role->remove_cap( $capability ); }
		}
		update_option( 'ccd_role_schema_version', '0.2.5', false );
	}

	public static function deactivate() {
		// Preserve the role and content so reactivation is non-destructive.
	}
}
