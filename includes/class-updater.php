<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Lightweight GitHub releases/tags updater using WordPress's native update UI. */
class CCD_Updater {
	private static $plugin_file;
	private static $repository;
	private static $slug;

	public static function init( $plugin_file, $repository ) {
		if ( ! $repository || false === filter_var( $repository, FILTER_VALIDATE_URL ) ) { return; }
		self::$plugin_file = $plugin_file;
		self::$repository = untrailingslashit( $repository );
		self::$slug = plugin_basename( $plugin_file );
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_folder' ), 10, 4 );
	}

	private static function release() {
		$key = 'ccd_github_release_' . md5( self::$repository );
		$cached = get_site_transient( $key );
		if ( false !== $cached ) { return $cached; }
		$path = (string) wp_parse_url( self::$repository, PHP_URL_PATH );
		$response = wp_remote_get( 'https://api.github.com/repos' . $path . '/releases/latest', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$response = wp_remote_get( 'https://api.github.com/repos' . $path . '/tags?per_page=1', array( 'timeout' => 10, 'headers' => array( 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ) ) );
			$tags = json_decode( wp_remote_retrieve_body( $response ), true );
			$data = ! empty( $tags[0] ) ? array( 'tag_name' => $tags[0]['name'], 'zipball_url' => $tags[0]['zipball_url'], 'html_url' => self::$repository . '/tags', 'body' => '' ) : array();
		} else { $data = json_decode( wp_remote_retrieve_body( $response ), true ); }
		set_site_transient( $key, $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	public static function check( $transient ) {
		if ( empty( $transient->checked[ self::$slug ] ) ) { return $transient; }
		$release = self::release();
		$version = ! empty( $release['tag_name'] ) ? ltrim( $release['tag_name'], 'vV' ) : '';
		if ( $version && version_compare( $version, $transient->checked[ self::$slug ], '>' ) ) {
			$transient->response[ self::$slug ] = (object) array( 'slug' => dirname( self::$slug ), 'plugin' => self::$slug, 'new_version' => $version, 'url' => $release['html_url'], 'package' => $release['zipball_url'] );
		}
		return $transient;
	}

	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( self::$slug ) !== $args->slug ) { return $result; }
		$release = self::release();
		if ( ! $release ) { return $result; }
		return (object) array( 'name' => 'Client Content Dashboard', 'slug' => dirname( self::$slug ), 'version' => ltrim( $release['tag_name'], 'vV' ), 'homepage' => self::$repository, 'download_link' => $release['zipball_url'], 'sections' => array( 'description' => 'Frontend structured content dashboard.', 'changelog' => wp_kses_post( nl2br( $release['body'] ) ) ) );
	}

	public static function normalize_folder( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || self::$slug !== $hook_extra['plugin'] ) { return $source; }
		$target = trailingslashit( $remote_source ) . dirname( self::$slug );
		if ( untrailingslashit( $source ) !== untrailingslashit( $target ) ) {
			global $wp_filesystem;
			if ( $wp_filesystem->move( $source, $target, true ) ) { return trailingslashit( $target ); }
		}
		return $source;
	}
}
