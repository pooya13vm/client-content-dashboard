<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Plugin Update Checker adapter and admin status controls. */
class CCD_Updater {
	const DEFAULT_REPOSITORY = 'https://github.com/pooya13vm/client-content-dashboard';
	const PLUGIN_SLUG = 'client-content-dashboard';
	const ENGINE_VERSION = 'puc-5.7';

	private static $checker = null;
	private static $repository = '';
	private static $library_active = false;

	public static function init( $plugin_file ) {
		$repository = self::repository_url();
		self::$repository = is_string( $repository ) ? untrailingslashit( $repository ) : '';
		add_action( 'admin_post_ccd_refresh_updates', array( __CLASS__, 'handle_refresh' ) );

		$bootstrap = CCD_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! self::$repository || ! file_exists( $bootstrap ) ) { return; }
		require_once $bootstrap;

		$factory = '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';
		if ( ! class_exists( $factory ) ) { return; }

		self::$checker = $factory::buildUpdateChecker(
			self::$repository,
			$plugin_file,
			self::PLUGIN_SLUG
		);
		self::$checker->setBranch( 'main' );
		self::$library_active = true;
		self::maybe_clear_legacy_state();
	}

	public static function repository_url() {
		$repository = defined( 'CCD_GITHUB_REPOSITORY' ) ? CCD_GITHUB_REPOSITORY : self::DEFAULT_REPOSITORY;
		return apply_filters( 'ccd_github_repository_url', $repository );
	}

	private static function maybe_clear_legacy_state() {
		if ( self::ENGINE_VERSION === get_site_option( 'ccd_updater_engine' ) ) { return; }
		delete_site_transient( 'ccd_github_release_' . md5( self::$repository ) );
		delete_site_option( 'ccd_github_diagnostics' );
		delete_site_option( 'ccd_github_last_check' );
		delete_site_transient( 'update_plugins' );
		update_site_option( 'ccd_updater_engine', self::ENGINE_VERSION );
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to check plugin updates.', 'client-content-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ccd_refresh_updates', 'ccd_update_nonce' );

		$result = 'failed';
		if ( self::$library_active && self::$checker ) {
			self::$checker->resetUpdateState();
			delete_site_transient( 'update_plugins' );
			self::$checker->checkForUpdates();
			if ( function_exists( 'wp_update_plugins' ) ) { wp_update_plugins(); }
			update_site_option( 'ccd_puc_last_manual_check', time() );
			$result = 'success';
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => 'tools', 'ccd_updater_result' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

}
