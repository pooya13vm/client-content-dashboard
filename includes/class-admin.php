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
		<div class="ccd-admin-intro"><h2><?php esc_html_e( 'Set up your Client Content Dashboard', 'client-content-dashboard' ); ?></h2><p><?php esc_html_e( 'Create a frontend dashboard for client users, then choose how published articles should appear on your site.', 'client-content-dashboard' ); ?></p><ol><li><?php esc_html_e( 'Choose or create the Dashboard Page.', 'client-content-dashboard' ); ?></li><li><?php esc_html_e( 'Configure upload limits.', 'client-content-dashboard' ); ?></li><li><?php esc_html_e( 'Choose the Article Display Layout.', 'client-content-dashboard' ); ?></li><li><?php esc_html_e( 'If using Template Page, create a page and place [ccd_article] where the article should appear.', 'client-content-dashboard' ); ?></li><li><?php esc_html_e( 'Create client users in the Client Users tab.', 'client-content-dashboard' ); ?></li></ol></div>
		<form method="post" action="options.php"><?php settings_fields( 'ccd_settings_group' ); ?>
		<div class="ccd-settings-section"><h2><?php esc_html_e( 'Dashboard Page', 'client-content-dashboard' ); ?></h2>
		<table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Dashboard Page', 'client-content-dashboard' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'ccd_settings[dashboard_page_id]', 'selected' => $s['dashboard_page_id'], 'show_option_none' => __( 'Select a page', 'client-content-dashboard' ) ) ); ?><?php $dashboard_page = get_post( absint( $s['dashboard_page_id'] ) ); if ( $dashboard_page && 'trash' !== $dashboard_page->post_status ) : ?> <a href="<?php echo esc_url( get_permalink( $dashboard_page ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Dashboard', 'client-content-dashboard' ); ?></a><?php endif; ?><p class="description"><?php esc_html_e( 'Place [client_content_dashboard] on this page.', 'client-content-dashboard' ); ?></p></td></tr>
		<?php CCD_Dashboard_Page::render_settings_rows(); ?>
		</table></div>
		<div class="ccd-settings-section"><h2><?php esc_html_e( 'Upload Limits', 'client-content-dashboard' ); ?></h2><table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Maximum Upload Size', 'client-content-dashboard' ); ?></th><td><input type="number" min="1" max="100" name="ccd_settings[max_upload_mb]" value="<?php echo esc_attr( $s['max_upload_mb'] ); ?>"> MB</td></tr>
		<tr><th><?php esc_html_e( 'Maximum Gallery Images', 'client-content-dashboard' ); ?></th><td><input type="number" min="1" max="50" name="ccd_settings[max_gallery_images]" value="<?php echo esc_attr( $s['max_gallery_images'] ); ?>"></td></tr>
		</table></div>
		<div class="ccd-settings-section"><h2><?php esc_html_e( 'Article Display', 'client-content-dashboard' ); ?></h2><table class="form-table" role="presentation">
		<tr><th><?php esc_html_e( 'Article Display Layout', 'client-content-dashboard' ); ?></th><td><select id="ccd-article-display-layout" name="ccd_settings[article_display_layout]"><option value="theme" <?php selected( $s['article_display_layout'], 'theme' ); ?>><?php esc_html_e( 'Use Theme Default', 'client-content-dashboard' ); ?></option><option value="clean" <?php selected( $s['article_display_layout'], 'clean' ); ?>><?php esc_html_e( 'Use Plugin Clean Layout', 'client-content-dashboard' ); ?></option><option value="template" <?php selected( $s['article_display_layout'], 'template' ); ?>><?php esc_html_e( 'Use Template Page', 'client-content-dashboard' ); ?></option></select><p class="description"><strong><?php esc_html_e( 'Use Theme Default:', 'client-content-dashboard' ); ?></strong> <?php esc_html_e( 'Use your theme or site builder’s normal Single Post design.', 'client-content-dashboard' ); ?><br><strong><?php esc_html_e( 'Use Plugin Clean Layout:', 'client-content-dashboard' ); ?></strong> <?php esc_html_e( 'Use a simple built-in article layout when your theme does not provide one.', 'client-content-dashboard' ); ?><br><strong><?php esc_html_e( 'Use Template Page:', 'client-content-dashboard' ); ?></strong> <?php esc_html_e( 'Use a normal WordPress page as your article design. Place [ccd_article] inside that page.', 'client-content-dashboard' ); ?></p></td></tr>
		<tr id="ccd-article-template-page-row"<?php echo 'template' !== $s['article_display_layout'] ? ' style="display:none"' : ''; ?>><th><?php esc_html_e( 'Article Template Page', 'client-content-dashboard' ); ?></th><td><?php wp_dropdown_pages( array( 'name' => 'ccd_settings[article_template_page_id]', 'selected' => $s['article_template_page_id'], 'show_option_none' => __( 'Select a page', 'client-content-dashboard' ) ) ); ?><p class="description"><?php esc_html_e( 'Create a normal WordPress page with your preferred layout/header/footer, then place [ccd_article] where the article should appear.', 'client-content-dashboard' ); ?></p></td></tr>
		</table></div><?php submit_button(); ?></form><script>document.addEventListener('DOMContentLoaded',function(){var s=document.getElementById('ccd-article-display-layout'),r=document.getElementById('ccd-article-template-page-row');if(!s||!r)return;function t(){r.style.display=s.value==='template'?'':'none';}s.addEventListener('change',t);t();});</script>
		<?php elseif ( 'client-users' === $tab ) : CCD_Client_Users::render(); ?>
		<?php endif; ?>
		<footer class="ccd-admin-credit"><p><?php esc_html_e( 'Client Content Dashboard by', 'client-content-dashboard' ); ?> <a href="https://www.pooyavaghef.com/" target="_blank" rel="noopener noreferrer">Pooya Vaghef</a></p></footer>
		<style>.ccd-admin-intro,.ccd-settings-section{max-width:960px;margin-top:20px;padding:20px 24px;border:1px solid #dcdcde;border-radius:4px;background:#fff}.ccd-admin-intro h2,.ccd-settings-section>h2{margin-top:0}.ccd-admin-intro ol{margin:14px 0 0 20px}.ccd-admin-intro li{margin-bottom:6px}.ccd-settings-section .form-table{margin-top:0}.ccd-admin-credit{max-width:960px;margin:24px 0;color:#646970}.ccd-admin-credit a{text-decoration:none}</style>
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
