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
		$existing = get_option( 'ccd_settings', array() );
		$settings = array(
			'dashboard_page_id'   => isset( $input['dashboard_page_id'] ) ? absint( $input['dashboard_page_id'] ) : 0,
			'max_upload_mb'       => isset( $input['max_upload_mb'] ) ? max( 1, min( 100, absint( $input['max_upload_mb'] ) ) ) : 5,
			'max_gallery_images'  => isset( $input['max_gallery_images'] ) ? max( 1, min( 50, absint( $input['max_gallery_images'] ) ) ) : 8,
			'article_display_layout' => isset( $input['article_display_layout'] ) && in_array( $input['article_display_layout'], array( 'theme', 'clean', 'template' ), true ) ? $input['article_display_layout'] : 'clean',
			'article_template_page_id' => isset( $input['article_template_page_id'] ) ? absint( $input['article_template_page_id'] ) : 0,
		);
		// Preserve obsolete values for backwards compatibility, but do not expose or use them.
		foreach ( array( 'default_post_status', 'hide_wp_admin' ) as $legacy_key ) {
			if ( array_key_exists( $legacy_key, $existing ) ) { $settings[ $legacy_key ] = $existing[ $legacy_key ]; }
		}
		return $settings;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		if ( ! in_array( $tab, array( 'settings', 'client-users' ), true ) ) { $tab = 'settings'; }
		$s = wp_parse_args( get_option( 'ccd_settings', array() ), array( 'dashboard_page_id' => 0, 'max_upload_mb' => 5, 'max_gallery_images' => 8, 'article_display_layout' => 'clean', 'article_template_page_id' => 0 ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Client Dashboard Settings', 'client-content-dashboard' ); ?></h1>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Client Dashboard sections', 'client-content-dashboard' ); ?>">
		<?php
		$tabs = array( 'settings' => __( 'Settings', 'client-content-dashboard' ), 'client-users' => __( 'Client Users', 'client-content-dashboard' ) );
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => $key ), admin_url( 'admin.php' ) );
			echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		?>
		</nav>
		<?php if ( 'settings' === $tab ) : ?>
		<form method="post" action="options.php"><?php settings_fields( 'ccd_settings_group' ); ?>
		<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Dashboard Page', 'client-content-dashboard' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'ccd_settings[dashboard_page_id]', 'selected' => $s['dashboard_page_id'], 'show_option_none' => __( 'Select a page', 'client-content-dashboard' ) ) ); ?><?php $dashboard_page = get_post( absint( $s['dashboard_page_id'] ) ); if ( $dashboard_page && 'trash' !== $dashboard_page->post_status ) : ?> <a href="<?php echo esc_url( get_permalink( $dashboard_page ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Dashboard', 'client-content-dashboard' ); ?></a><?php endif; ?><p class="description"><?php esc_html_e( 'Place [client_content_dashboard] on this page.', 'client-content-dashboard' ); ?></p></td></tr>
		<?php CCD_Dashboard_Page::render_settings_rows(); ?>
		<tr><th><?php esc_html_e( 'Maximum Upload Size', 'client-content-dashboard' ); ?></th><td><input type="number" min="1" max="100" name="ccd_settings[max_upload_mb]" value="<?php echo esc_attr( $s['max_upload_mb'] ); ?>"> MB</td></tr>
		<tr><th><?php esc_html_e( 'Maximum Gallery Images', 'client-content-dashboard' ); ?></th><td><input type="number" min="1" max="50" name="ccd_settings[max_gallery_images]" value="<?php echo esc_attr( $s['max_gallery_images'] ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Article Display Layout', 'client-content-dashboard' ); ?></th><td><select id="ccd-article-display-layout" name="ccd_settings[article_display_layout]"><option value="theme" <?php selected( $s['article_display_layout'], 'theme' ); ?>><?php esc_html_e( 'Use Theme Default', 'client-content-dashboard' ); ?></option><option value="clean" <?php selected( $s['article_display_layout'], 'clean' ); ?>><?php esc_html_e( 'Use Plugin Clean Layout', 'client-content-dashboard' ); ?></option><option value="template" <?php selected( $s['article_display_layout'], 'template' ); ?>><?php esc_html_e( 'Use Template Page', 'client-content-dashboard' ); ?></option></select><p class="description"><?php esc_html_e( 'Use Plugin Clean Layout to keep the active theme’s normal post page while improving article content readability.', 'client-content-dashboard' ); ?><br><?php esc_html_e( 'Use Theme Default if your theme or site builder already provides a good Single Post template.', 'client-content-dashboard' ); ?></p></td></tr>
		<tr id="ccd-article-template-page-row"<?php echo 'template' !== $s['article_display_layout'] ? ' style="display:none"' : ''; ?>><th><?php esc_html_e( 'Article Template Page', 'client-content-dashboard' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'ccd_settings[article_template_page_id]', 'selected' => $s['article_template_page_id'], 'show_option_none' => __( 'Select a page', 'client-content-dashboard' ) ) ); ?><p class="description"><?php esc_html_e( 'Create a normal WordPress page with your preferred layout/header/footer, then place [ccd_article] where the article should appear.', 'client-content-dashboard' ); ?></p></td></tr>
		</table><?php submit_button(); ?></form><script>document.addEventListener('DOMContentLoaded',function(){var s=document.getElementById('ccd-article-display-layout'),r=document.getElementById('ccd-article-template-page-row');if(!s||!r)return;function t(){r.style.display=s.value==='template'?'':'none';}s.addEventListener('change',t);t();});</script>
		<?php elseif ( 'client-users' === $tab ) : CCD_Client_Users::render(); ?>
		<?php endif; ?>
		</div><?php
	}

	private static function is_client() {
		$user = wp_get_current_user();
		return in_array( 'client_editor', (array) $user->roles, true );
	}

	public static function redirect_clients() {
		global $pagenow;
		$allowed_endpoints = array( 'admin-ajax.php', 'async-upload.php', 'media-upload.php' );
		if ( ! is_user_logged_in() || ! self::is_client() || wp_doing_ajax() || defined( 'DOING_CRON' ) || in_array( $pagenow, $allowed_endpoints, true ) ) { return; }
		$s = get_option( 'ccd_settings', array() );
		$page_id = empty( $s['dashboard_page_id'] ) ? 0 : absint( $s['dashboard_page_id'] );
		wp_safe_redirect( $page_id ? get_permalink( $page_id ) : home_url( '/' ) );
		exit;
	}

	public static function admin_bar( $show ) {
		return self::is_client() ? false : $show;
	}
}
