<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Dashboard_Page {
	const PAGE_SLUG = 'client-dashboard';

	public static function init() {
		add_action( 'admin_post_ccd_recreate_dashboard_page', array( __CLASS__, 'handle_recreate' ) );
	}

	public static function create_on_activation() {
		$settings = get_option( 'ccd_settings', array() );
		if ( ! empty( $settings['dashboard_page_id'] ) ) { return; }
		self::create_or_repair( false );
	}

	private static function create_or_repair( $repair_configured ) {
		$settings = get_option( 'ccd_settings', array() );
		$configured_id = empty( $settings['dashboard_page_id'] ) ? 0 : absint( $settings['dashboard_page_id'] );
		$configured = $configured_id ? get_post( $configured_id ) : null;

		// Never replace a valid page selected by an administrator.
		if ( $configured && 'trash' !== $configured->post_status ) { return $configured_id; }
		if ( $configured_id && ! $repair_configured ) { return 0; }

		$page = $configured && 'trash' === $configured->post_status ? $configured : get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
		$page_data = array(
			'post_type'    => 'page',
			'post_title'   => __( 'Client Dashboard', 'client-content-dashboard' ),
			'post_name'    => self::PAGE_SLUG,
			'post_content' => '[client_content_dashboard]',
			'post_status'  => 'publish',
		);

		if ( $page ) {
			if ( 'trash' === $page->post_status ) { wp_untrash_post( $page->ID ); }
			$page_data['ID'] = $page->ID;
			$page_id = wp_update_post( $page_data, true );
		} else {
			$page_id = wp_insert_post( $page_data, true );
		}

		if ( is_wp_error( $page_id ) || ! $page_id ) { return 0; }
		$settings['dashboard_page_id'] = absint( $page_id );
		update_option( 'ccd_settings', $settings );
		return absint( $page_id );
	}

	public static function handle_recreate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the dashboard page.', 'client-content-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ccd_recreate_dashboard_page', 'ccd_dashboard_page_nonce' );
		$result = self::create_or_repair( true ) ? 'success' : 'failed';
		wp_safe_redirect( add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => 'tools', 'ccd_page_result' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function status() {
		$settings = get_option( 'ccd_settings', array() );
		$page_id = empty( $settings['dashboard_page_id'] ) ? 0 : absint( $settings['dashboard_page_id'] );
		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page ) { return array( 'state' => 'missing', 'page' => null ); }
		if ( 'trash' === $page->post_status ) { return array( 'state' => 'trashed', 'page' => $page ); }
		return array( 'state' => 'exists', 'page' => $page );
	}

	public static function render_tools() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$status = self::status();
		$result = isset( $_GET['ccd_page_result'] ) ? sanitize_key( wp_unslash( $_GET['ccd_page_result'] ) ) : '';
		?>
		<h2><?php esc_html_e( 'Dashboard Page Status', 'client-content-dashboard' ); ?></h2>
		<?php if ( 'success' === $result ) : ?><div class="notice notice-success inline"><p><?php esc_html_e( 'Dashboard page created or repaired.', 'client-content-dashboard' ); ?></p></div><?php elseif ( 'failed' === $result ) : ?><div class="notice notice-error inline"><p><?php esc_html_e( 'Dashboard page could not be created.', 'client-content-dashboard' ); ?></p></div><?php endif; ?>
		<table class="widefat striped" style="max-width:760px"><tbody>
		<tr><th><?php esc_html_e( 'Current status', 'client-content-dashboard' ); ?></th><td><?php echo esc_html( $status['state'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Page title', 'client-content-dashboard' ); ?></th><td><?php echo $status['page'] ? esc_html( get_the_title( $status['page'] ) ) : '&mdash;'; ?></td></tr>
		<tr><th><?php esc_html_e( 'Page URL', 'client-content-dashboard' ); ?></th><td><?php if ( $status['page'] && 'trashed' !== $status['state'] ) : $url = get_permalink( $status['page'] ); ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a><?php else : ?>&mdash;<?php endif; ?></td></tr>
		</tbody></table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ccd_recreate_dashboard_page">
		<?php wp_nonce_field( 'ccd_recreate_dashboard_page', 'ccd_dashboard_page_nonce' ); ?>
		<?php submit_button( __( 'Create/Recreate Dashboard Page', 'client-content-dashboard' ), 'secondary' ); ?>
		</form><?php
	}
}
