<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Lightweight GitHub releases/tags updater using WordPress's native update UI. */
class CCD_Updater {
	/** Built-in repository used out of the box when no optional override is set. */
	const DEFAULT_REPOSITORY = 'https://github.com/pooya13vm/client-content-dashboard';

	private static $plugin_file;
	private static $repository;
	private static $slug;

	public static function init( $plugin_file ) {
		self::$plugin_file = $plugin_file;
		self::$slug = plugin_basename( $plugin_file );
		$repository = self::repository_url();
		self::$repository = is_string( $repository ) ? untrailingslashit( $repository ) : '';
		add_action( 'admin_post_ccd_refresh_updates', array( __CLASS__, 'handle_refresh' ) );
		if ( ! self::$repository || false === filter_var( self::$repository, FILTER_VALIDATE_URL ) ) { return; }
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_folder' ), 10, 4 );
	}

	public static function repository_url() {
		// CCD_GITHUB_REPOSITORY is optional and overrides the built-in repository.
		$repository = defined( 'CCD_GITHUB_REPOSITORY' ) ? CCD_GITHUB_REPOSITORY : self::DEFAULT_REPOSITORY;

		// Developers may optionally override the resolved URL without editing plugin files.
		return apply_filters( 'ccd_github_repository_url', $repository );
	}

	private static function cache_key() {
		return 'ccd_github_release_' . md5( self::$repository );
	}

	private static function release() {
		if ( ! self::$repository || false === filter_var( self::$repository, FILTER_VALIDATE_URL ) ) { return array(); }
		$key = self::cache_key();
		$cached = get_site_transient( $key );
		if ( false !== $cached ) { return $cached; }
		$path = (string) wp_parse_url( self::$repository, PHP_URL_PATH );
		$response = wp_remote_get( 'https://api.github.com/repos' . $path . '/releases/latest', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ) ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$response = wp_remote_get( 'https://api.github.com/repos' . $path . '/tags?per_page=1', array( 'timeout' => 10, 'headers' => array( 'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) ) ) );
			$tags = json_decode( wp_remote_retrieve_body( $response ), true );
			$data = ! empty( $tags[0] ) ? array( 'tag_name' => $tags[0]['name'], 'zipball_url' => $tags[0]['zipball_url'], 'html_url' => self::$repository . '/tags', 'body' => '' ) : array();
		} else { $data = json_decode( wp_remote_retrieve_body( $response ), true ); }
		update_site_option( 'ccd_github_last_check', time() );
		set_site_transient( $key, $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	public static function diagnostics() {
		$release = self::release();
		$latest = ! empty( $release['tag_name'] ) ? ltrim( $release['tag_name'], 'vV' ) : '';
		return array(
			'installed_version' => CCD_VERSION,
			'repository'        => self::$repository,
			'latest_version'    => $latest,
			'last_check'        => absint( get_site_option( 'ccd_github_last_check', 0 ) ),
			'update_available'  => $latest && version_compare( $latest, CCD_VERSION, '>' ),
		);
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to check plugin updates.', 'client-content-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ccd_refresh_updates', 'ccd_update_nonce' );

		if ( self::$repository ) { delete_site_transient( self::cache_key() ); }
		delete_site_transient( 'update_plugins' );
		$release = self::release();
		if ( function_exists( 'wp_update_plugins' ) ) { wp_update_plugins(); }
		$result = ! empty( $release['tag_name'] ) ? 'success' : 'failed';
		wp_safe_redirect( add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => 'tools', 'ccd_updater_result' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_tools_section() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$status = self::diagnostics();
		$result = isset( $_GET['ccd_updater_result'] ) ? sanitize_key( wp_unslash( $_GET['ccd_updater_result'] ) ) : '';
		?>
		<hr><h2><?php esc_html_e( 'Updater Status', 'client-content-dashboard' ); ?></h2>
		<?php if ( 'success' === $result ) : ?><div class="notice notice-success inline"><p><?php esc_html_e( 'Update cache cleared and GitHub checked successfully.', 'client-content-dashboard' ); ?></p></div><?php elseif ( 'failed' === $result ) : ?><div class="notice notice-error inline"><p><?php esc_html_e( 'The update cache was cleared, but no GitHub release or tag could be detected.', 'client-content-dashboard' ); ?></p></div><?php endif; ?>
		<table class="widefat striped" style="max-width:760px"><tbody>
		<tr><th><?php esc_html_e( 'Installed plugin version', 'client-content-dashboard' ); ?></th><td><?php echo esc_html( $status['installed_version'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'GitHub repository', 'client-content-dashboard' ); ?></th><td><?php if ( $status['repository'] ) : ?><a href="<?php echo esc_url( $status['repository'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $status['repository'] ); ?></a><?php else : ?><?php esc_html_e( 'Not configured', 'client-content-dashboard' ); ?><?php endif; ?></td></tr>
		<tr><th><?php esc_html_e( 'Latest release/tag version', 'client-content-dashboard' ); ?></th><td><?php echo $status['latest_version'] ? esc_html( $status['latest_version'] ) : esc_html__( 'Not detected', 'client-content-dashboard' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Last update check', 'client-content-dashboard' ); ?></th><td><?php echo $status['last_check'] ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['last_check'] ) ) : esc_html__( 'Not available', 'client-content-dashboard' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Update available', 'client-content-dashboard' ); ?></th><td><?php echo $status['update_available'] ? esc_html__( 'Yes', 'client-content-dashboard' ) : esc_html__( 'No', 'client-content-dashboard' ); ?></td></tr>
		</tbody></table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ccd_refresh_updates">
		<?php wp_nonce_field( 'ccd_refresh_updates', 'ccd_update_nonce' ); ?>
		<?php submit_button( __( 'Clear Update Cache / Check Now', 'client-content-dashboard' ), 'secondary' ); ?>
		</form><?php
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
