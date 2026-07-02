<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Lightweight GitHub releases/tags updater using WordPress's native update UI. */
class CCD_Updater {
	const DEFAULT_REPOSITORY = 'https://github.com/pooya13vm/client-content-dashboard';
	const CACHE_TTL = 21600;

	private static $plugin_file = '';
	private static $repository = '';
	private static $slug = '';

	public static function init( $plugin_file ) {
		self::$plugin_file = $plugin_file;
		self::$slug = plugin_basename( $plugin_file );
		$repository = self::repository_url();
		self::$repository = is_string( $repository ) ? untrailingslashit( $repository ) : '';

		add_action( 'admin_post_ccd_refresh_updates', array( __CLASS__, 'handle_refresh' ) );
		if ( ! self::valid_repository() ) { return; }

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_folder' ), 10, 4 );
	}

	public static function repository_url() {
		$repository = defined( 'CCD_GITHUB_REPOSITORY' ) ? CCD_GITHUB_REPOSITORY : self::DEFAULT_REPOSITORY;
		return apply_filters( 'ccd_github_repository_url', $repository );
	}

	private static function valid_repository() {
		return self::$repository && false !== filter_var( self::$repository, FILTER_VALIDATE_URL );
	}

	private static function normalize_version( $version ) {
		return preg_replace( '/^[vV]/', '', trim( (string) $version ) );
	}

	private static function valid_version( $version ) {
		return (bool) preg_match( '/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version );
	}

	private static function cache_key() {
		return 'ccd_github_release_' . md5( self::$repository );
	}

	private static function api_base() {
		$path = trim( (string) wp_parse_url( self::$repository, PHP_URL_PATH ), '/' );
		return 'https://api.github.com/repos/' . $path;
	}

	private static function request( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Client-Content-Dashboard/' . CCD_VERSION . '; ' . home_url( '/' ),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'status' => 0, 'data' => array(), 'error' => $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		$error = '';
		if ( 200 !== $status ) {
			$error = is_array( $decoded ) && ! empty( $decoded['message'] ) ? sanitize_text_field( $decoded['message'] ) : sprintf( __( 'GitHub returned HTTP status %d.', 'client-content-dashboard' ), $status );
		} elseif ( ! is_array( $decoded ) ) {
			$error = __( 'GitHub returned an invalid JSON response.', 'client-content-dashboard' );
		}
		return array( 'status' => $status, 'data' => is_array( $decoded ) ? $decoded : array(), 'error' => $error );
	}

	private static function select_release( $releases ) {
		$valid = array();
		$allow_prereleases = (bool) apply_filters( 'ccd_allow_prerelease_updates', false );
		foreach ( $releases as $release ) {
			if ( ! is_array( $release ) || empty( $release['tag_name'] ) || ! empty( $release['draft'] ) || ( ! $allow_prereleases && ! empty( $release['prerelease'] ) ) ) { continue; }
			$version = self::normalize_version( $release['tag_name'] );
			if ( ! self::valid_version( $version ) ) { continue; }
			$release['_ccd_version'] = $version;
			$valid[] = $release;
		}
		usort( $valid, function( $a, $b ) { return version_compare( $b['_ccd_version'], $a['_ccd_version'] ); } );
		return empty( $valid ) ? array() : $valid[0];
	}

	private static function select_tag( $tags ) {
		$valid = array();
		foreach ( $tags as $tag ) {
			if ( ! is_array( $tag ) || empty( $tag['name'] ) ) { continue; }
			$tag['_ccd_version'] = self::normalize_version( $tag['name'] );
			if ( self::valid_version( $tag['_ccd_version'] ) ) { $valid[] = $tag; }
		}
		usort( $valid, function( $a, $b ) { return version_compare( $b['_ccd_version'], $a['_ccd_version'] ); } );
		return empty( $valid ) ? array() : $valid[0];
	}

	private static function release_package( $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && preg_match( '/\.zip(?:\?.*)?$/i', $asset['browser_download_url'] ) ) { return esc_url_raw( $asset['browser_download_url'] ); }
			}
		}
		return ! empty( $release['zipball_url'] ) ? esc_url_raw( $release['zipball_url'] ) : '';
	}

	private static function latest() {
		if ( ! self::valid_repository() ) { return array(); }
		$cached = get_site_transient( self::cache_key() );
		if ( false !== $cached && is_array( $cached ) ) { return $cached; }

		$errors = array();
		$http_status = 0;
		$release_response = self::request( self::api_base() . '/releases?per_page=100' );
		$http_status = $release_response['status'];
		if ( $release_response['error'] ) { $errors[] = $release_response['error']; }
		$release = 200 === $release_response['status'] ? self::select_release( $release_response['data'] ) : array();

		if ( $release ) {
			$data = array(
				'raw_tag' => (string) $release['tag_name'],
				'version' => (string) $release['_ccd_version'],
				'package' => self::release_package( $release ),
				'url'     => ! empty( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : self::$repository . '/releases',
				'body'    => ! empty( $release['body'] ) ? (string) $release['body'] : '',
			);
		} else {
			$tag_response = self::request( self::api_base() . '/tags?per_page=100' );
			$http_status = $tag_response['status'];
			if ( $tag_response['error'] ) { $errors[] = $tag_response['error']; }
			$tag = 200 === $tag_response['status'] ? self::select_tag( $tag_response['data'] ) : array();
			$data = $tag ? array(
				'raw_tag' => (string) $tag['name'],
				'version' => (string) $tag['_ccd_version'],
				'package' => ! empty( $tag['zipball_url'] ) ? esc_url_raw( $tag['zipball_url'] ) : self::$repository . '/archive/refs/tags/' . rawurlencode( $tag['name'] ) . '.zip',
				'url'     => self::$repository . '/tags',
				'body'    => '',
			) : array();
		}

		$diagnostics = array( 'last_check' => time(), 'http_status' => $http_status, 'error' => implode( ' ', array_unique( $errors ) ) );
		update_site_option( 'ccd_github_diagnostics', $diagnostics );
		set_site_transient( self::cache_key(), $data, self::CACHE_TTL );
		return $data;
	}

	public static function check( $transient ) {
		if ( ! is_object( $transient ) ) { $transient = new stdClass(); }
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) { $transient->response = array(); }
		$latest = self::latest();
		$remote_version = ! empty( $latest['version'] ) ? self::normalize_version( $latest['version'] ) : '';
		$installed_version = ! empty( $transient->checked[ self::$slug ] ) ? $transient->checked[ self::$slug ] : CCD_VERSION;

		if ( $remote_version && ! empty( $latest['package'] ) && version_compare( $remote_version, $installed_version, '>' ) ) {
			$transient->response[ self::$slug ] = (object) array(
				'slug'        => dirname( self::$slug ),
				'plugin'      => self::$slug,
				'new_version' => $remote_version,
				'url'         => $latest['url'],
				'package'     => $latest['package'],
			);
		}
		return $transient;
	}

	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( self::$slug ) !== $args->slug ) { return $result; }
		$latest = self::latest();
		if ( empty( $latest['version'] ) ) { return $result; }
		return (object) array(
			'name'          => 'Client Content Dashboard',
			'slug'          => dirname( self::$slug ),
			'version'       => $latest['version'],
			'homepage'      => self::$repository,
			'download_link' => $latest['package'],
			'sections'      => array( 'description' => 'Frontend structured content dashboard.', 'changelog' => wp_kses_post( nl2br( $latest['body'] ) ) ),
		);
	}

	public static function diagnostics() {
		$latest = self::latest();
		$request = get_site_option( 'ccd_github_diagnostics', array() );
		$version = ! empty( $latest['version'] ) ? self::normalize_version( $latest['version'] ) : '';
		return array(
			'installed_version' => CCD_VERSION,
			'repository'        => self::$repository,
			'raw_tag'           => ! empty( $latest['raw_tag'] ) ? $latest['raw_tag'] : '',
			'latest_version'    => $version,
			'last_check'        => ! empty( $request['last_check'] ) ? absint( $request['last_check'] ) : 0,
			'update_available'  => $version && version_compare( $version, CCD_VERSION, '>' ),
			'package'           => ! empty( $latest['package'] ) ? $latest['package'] : '',
			'plugin_basename'   => self::$slug,
			'http_status'       => isset( $request['http_status'] ) ? absint( $request['http_status'] ) : 0,
			'error'             => ! empty( $request['error'] ) ? $request['error'] : '',
		);
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to check plugin updates.', 'client-content-dashboard' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'ccd_refresh_updates', 'ccd_update_nonce' );
		if ( self::$repository ) { delete_site_transient( self::cache_key() ); }
		delete_site_option( 'ccd_github_diagnostics' );
		delete_site_option( 'ccd_github_last_check' ); // Remove the legacy diagnostic option too.
		delete_site_transient( 'update_plugins' );
		$latest = self::latest();
		if ( function_exists( 'wp_update_plugins' ) ) { wp_update_plugins(); }
		$result = ! empty( $latest['version'] ) && ! empty( $latest['package'] ) ? 'success' : 'failed';
		wp_safe_redirect( add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => 'tools', 'ccd_updater_result' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_tools_section() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$status = self::diagnostics();
		$result = isset( $_GET['ccd_updater_result'] ) ? sanitize_key( wp_unslash( $_GET['ccd_updater_result'] ) ) : '';
		?>
		<hr><h2><?php esc_html_e( 'Updater Status', 'client-content-dashboard' ); ?></h2>
		<?php if ( 'success' === $result ) : ?><div class="notice notice-success inline"><p><?php esc_html_e( 'Update cache cleared and GitHub checked successfully.', 'client-content-dashboard' ); ?></p></div><?php elseif ( 'failed' === $result ) : ?><div class="notice notice-error inline"><p><?php esc_html_e( 'The update cache was cleared, but no downloadable GitHub release or tag could be detected.', 'client-content-dashboard' ); ?></p></div><?php endif; ?>
		<table class="widefat striped" style="max-width:900px"><tbody>
		<tr><th><?php esc_html_e( 'Installed plugin version', 'client-content-dashboard' ); ?></th><td><?php echo esc_html( $status['installed_version'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'GitHub repository', 'client-content-dashboard' ); ?></th><td><?php echo $status['repository'] ? '<a href="' . esc_url( $status['repository'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $status['repository'] ) . '</a>' : esc_html__( 'Not configured', 'client-content-dashboard' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Raw latest GitHub tag', 'client-content-dashboard' ); ?></th><td><?php echo $status['raw_tag'] ? esc_html( $status['raw_tag'] ) : esc_html__( 'Not detected', 'client-content-dashboard' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Normalized latest version', 'client-content-dashboard' ); ?></th><td><?php echo $status['latest_version'] ? esc_html( $status['latest_version'] ) : esc_html__( 'Not detected', 'client-content-dashboard' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Update available by version_compare', 'client-content-dashboard' ); ?></th><td><?php echo $status['update_available'] ? esc_html__( 'Yes', 'client-content-dashboard' ) : esc_html__( 'No', 'client-content-dashboard' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Package URL', 'client-content-dashboard' ); ?></th><td><?php echo $status['package'] ? '<a href="' . esc_url( $status['package'] ) . '">' . esc_html( $status['package'] ) . '</a>' : '&mdash;'; ?></td></tr>
		<tr><th><?php esc_html_e( 'WordPress update key', 'client-content-dashboard' ); ?></th><td><code><?php echo esc_html( $status['plugin_basename'] ); ?></code></td></tr>
		<tr><th><?php esc_html_e( 'Last GitHub check', 'client-content-dashboard' ); ?></th><td><?php echo $status['last_check'] ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['last_check'] ) ) : esc_html__( 'Not available', 'client-content-dashboard' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Last GitHub HTTP status', 'client-content-dashboard' ); ?></th><td><?php echo esc_html( (string) $status['http_status'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Last GitHub error', 'client-content-dashboard' ); ?></th><td><?php echo $status['error'] ? esc_html( $status['error'] ) : esc_html__( 'None', 'client-content-dashboard' ); ?></td></tr>
		</tbody></table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ccd_refresh_updates"><?php wp_nonce_field( 'ccd_refresh_updates', 'ccd_update_nonce' ); ?><?php submit_button( __( 'Clear Update Cache / Check Now', 'client-content-dashboard' ), 'secondary' ); ?></form><?php
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
