<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_clients' ), 1 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'admin_bar' ) );
	}

	public static function menu() {
		add_menu_page( __( 'Client Dashboard', 'client-content-dashboard' ), __( 'Client Dashboard', 'client-content-dashboard' ), 'manage_options', 'client-content-dashboard', array( __CLASS__, 'page' ), 'dashicons-edit-page', 58 );
	}

	public static function register_settings() {
		register_setting( 'ccd_settings_group', 'ccd_settings', array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ), 'default' => array() ) );
	}

	public static function sanitize( $input ) {
		return array(
			'dashboard_page_id'   => isset( $input['dashboard_page_id'] ) ? absint( $input['dashboard_page_id'] ) : 0,
			'default_post_status' => isset( $input['default_post_status'] ) && in_array( $input['default_post_status'], array( 'draft', 'pending' ), true ) ? $input['default_post_status'] : 'draft',
			'hide_wp_admin'       => empty( $input['hide_wp_admin'] ) ? 0 : 1,
			'max_upload_mb'       => isset( $input['max_upload_mb'] ) ? max( 1, min( 100, absint( $input['max_upload_mb'] ) ) ) : 5,
			'max_gallery_images'  => isset( $input['max_gallery_images'] ) ? max( 1, min( 50, absint( $input['max_gallery_images'] ) ) ) : 8,
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = wp_parse_args( get_option( 'ccd_settings', array() ), array( 'dashboard_page_id' => 0, 'default_post_status' => 'draft', 'hide_wp_admin' => 1, 'max_upload_mb' => 5, 'max_gallery_images' => 8 ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Client Dashboard Settings', 'client-content-dashboard' ); ?></h1>
		<form method="post" action="options.php"><?php settings_fields( 'ccd_settings_group' ); ?>
		<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Dashboard Page', 'client-content-dashboard' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'ccd_settings[dashboard_page_id]', 'selected' => $s['dashboard_page_id'], 'show_option_none' => __( 'Select a page', 'client-content-dashboard' ) ) ); ?><p class="description"><?php esc_html_e( 'Place [client_content_dashboard] on this page.', 'client-content-dashboard' ); ?></p></td></tr>
		<tr><th><?php esc_html_e( 'Default Post Status', 'client-content-dashboard' ); ?></th><td><select name="ccd_settings[default_post_status]"><option value="draft" <?php selected( $s['default_post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'client-content-dashboard' ); ?></option><option value="pending" <?php selected( $s['default_post_status'], 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'client-content-dashboard' ); ?></option></select></td></tr>
		<tr><th><?php esc_html_e( 'Hide wp-admin', 'client-content-dashboard' ); ?></th><td><label><input type="checkbox" name="ccd_settings[hide_wp_admin]" value="1" <?php checked( $s['hide_wp_admin'], 1 ); ?>> <?php esc_html_e( 'Redirect Client Editors to the frontend dashboard', 'client-content-dashboard' ); ?></label></td></tr>
		<tr><th><?php esc_html_e( 'Maximum Upload Size', 'client-content-dashboard' ); ?></th><td><input type="number" min="1" max="100" name="ccd_settings[max_upload_mb]" value="<?php echo esc_attr( $s['max_upload_mb'] ); ?>"> MB</td></tr>
		<tr><th><?php esc_html_e( 'Maximum Gallery Images', 'client-content-dashboard' ); ?></th><td><input type="number" min="1" max="50" name="ccd_settings[max_gallery_images]" value="<?php echo esc_attr( $s['max_gallery_images'] ); ?>"></td></tr>
		</table><?php submit_button(); ?></form>
		<?php CCD_Client_Users::render(); ?>
		</div><?php
	}

	private static function is_client() {
		$user = wp_get_current_user();
		return in_array( 'client_editor', (array) $user->roles, true );
	}

	public static function redirect_clients() {
		if ( ! is_user_logged_in() || ! self::is_client() || wp_doing_ajax() || defined( 'DOING_CRON' ) ) { return; }
		$s = get_option( 'ccd_settings', array() );
		if ( empty( $s['hide_wp_admin'] ) ) { return; }
		$page_id = empty( $s['dashboard_page_id'] ) ? 0 : absint( $s['dashboard_page_id'] );
		wp_safe_redirect( $page_id ? get_permalink( $page_id ) : home_url( '/' ) );
		exit;
	}

	public static function admin_bar( $show ) {
		$s = get_option( 'ccd_settings', array() );
		return self::is_client() && ! empty( $s['hide_wp_admin'] ) ? false : $show;
	}
}
