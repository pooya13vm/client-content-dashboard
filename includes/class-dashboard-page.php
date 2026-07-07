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

		// Never replace or retarget a valid admin-selected page during automatic setup.
		if ( $configured && 'trash' !== $configured->post_status && ! $repair_configured ) { return $configured_id; }
		if ( $configured_id && ! $repair_configured ) { return 0; }

		$page = $configured ? $configured : get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
		if ( $page && 'trash' === $page->post_status && ! $repair_configured ) { $page = null; }
		$is_new = ! $page;
		$page_data = array(
			'post_type'    => 'page',
			'post_title'   => __( 'Client Dashboard', 'client-content-dashboard' ),
			'post_name'    => self::PAGE_SLUG,
			'post_content' => '[client_content_dashboard]',
			'post_status'  => 'publish',
		);

		if ( $page && ! $repair_configured ) {
			// Reuse a matching existing page without overwriting manual content or builder data.
			$page_id = $page->ID;
		} elseif ( $page ) {
			if ( 'trash' === $page->post_status ) { wp_untrash_post( $page->ID ); }
			$page_data['ID'] = $page->ID;
			$page_id = wp_update_post( $page_data, true );
		} else {
			$page_id = wp_insert_post( $page_data, true );
		}

		if ( is_wp_error( $page_id ) || ! $page_id ) { return 0; }
		if ( $is_new || $repair_configured ) {
			self::apply_clean_template( absint( $page_id ) );
			self::prepare_elementor_page( absint( $page_id ) );
		}
		$settings['dashboard_page_id'] = absint( $page_id );
		update_option( 'ccd_settings', $settings );
		return absint( $page_id );
	}

	private static function apply_clean_template( $page_id ) {
		$page = get_post( $page_id );
		if ( ! $page ) { return; }
		$templates = get_page_templates( $page, 'page' );
		if ( ! is_array( $templates ) ) { $templates = array(); }

		if ( self::elementor_active() ) {
			update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );
			return;
		}

		// Prefer Elementor's clean templates without requiring Elementor itself.
		$candidates = array( 'elementor_canvas', 'elementor_header_footer', 'full-width.php', 'blank.php', 'page-blank.php', 'canvas.php' );
		foreach ( $candidates as $candidate ) {
			if ( in_array( $candidate, $templates, true ) ) {
				update_post_meta( $page_id, '_wp_page_template', $candidate );
				return;
			}
		}

		// Some themes/plugins use different file values but recognizable labels.
		$label_preferences = array( 'elementor canvas', 'elementor full width', 'full width', 'blank', 'canvas' );
		foreach ( $label_preferences as $preference ) {
			foreach ( $templates as $label => $template ) {
				if ( false !== strpos( strtolower( $label ), $preference ) ) {
					update_post_meta( $page_id, '_wp_page_template', $template );
					return;
				}
			}
		}
		// With no clean registered template, retain the current/default template.
	}

	private static function elementor_active() {
		return did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' );
	}

	private static function prepare_elementor_page( $page_id ) {
		if ( ! self::elementor_active() ) { return; }
		$page = get_post( $page_id );
		$templates = $page ? get_page_templates( $page, 'page' ) : array();
		if ( is_array( $templates ) && in_array( 'elementor_canvas', $templates, true ) ) {
			update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );
		}

		$elementor_data = array(
			array(
				'id'       => substr( md5( 'ccd-dashboard-section' ), 0, 7 ),
				'elType'   => 'section',
				'settings' => array( 'content_width' => 'full', 'stretch_section' => 'section-stretched', 'gap' => 'no' ),
				'elements' => array(
					array(
						'id'       => substr( md5( 'ccd-dashboard-column' ), 0, 7 ),
						'elType'   => 'column',
						'settings' => array( '_column_size' => 100, '_inline_size' => null ),
						'elements' => array(
							array(
								'id'         => substr( md5( 'ccd-dashboard-shortcode' ), 0, 7 ),
								'elType'     => 'widget',
								'settings'   => array( 'shortcode' => '[client_content_dashboard]' ),
								'elements'   => array(),
								'widgetType' => 'shortcode',
							),
						),
					),
				),
			),
		);
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
		update_post_meta( $page_id, '_elementor_page_settings', array() );
		if ( defined( 'ELEMENTOR_VERSION' ) ) { update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION ); }
		delete_post_meta( $page_id, '_elementor_css' );

		if ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) && method_exists( \Elementor\Plugin::$instance->files_manager, 'clear_cache' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	private static function template_label( $page ) {
		if ( ! $page ) { return '—'; }
		$template = get_page_template_slug( $page );
		if ( ! $template || 'default' === $template ) { return __( 'Default Template', 'client-content-dashboard' ); }
		if ( 'elementor_canvas' === $template ) { return __( 'Elementor Canvas', 'client-content-dashboard' ); }
		if ( 'elementor_header_footer' === $template ) { return __( 'Elementor Full Width', 'client-content-dashboard' ); }
		$templates = get_page_templates( $page, 'page' );
		$label = is_array( $templates ) ? array_search( $template, $templates, true ) : false;
		return $label ? sprintf( '%1$s (%2$s)', $label, $template ) : $template;
	}

	public static function handle_recreate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the dashboard page.', 'client-content-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ccd_recreate_dashboard_page', 'ccd_dashboard_page_nonce' );
		$result = self::create_or_repair( true ) ? 'success' : 'failed';
		wp_safe_redirect( add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => 'settings', 'ccd_page_result' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function status() {
		$settings = get_option( 'ccd_settings', array() );
		$page_id = empty( $settings['dashboard_page_id'] ) ? 0 : absint( $settings['dashboard_page_id'] );
		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page ) { return array( 'state' => 'missing', 'page' => null ); }
		if ( 'trash' === $page->post_status ) { return array( 'state' => 'missing', 'page' => $page ); }
		return array( 'state' => 'exists', 'page' => $page );
	}

	public static function render_settings_rows() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$status = self::status();
		$result = isset( $_GET['ccd_page_result'] ) ? sanitize_key( wp_unslash( $_GET['ccd_page_result'] ) ) : '';
		$action_url = wp_nonce_url( add_query_arg( 'action', 'ccd_recreate_dashboard_page', admin_url( 'admin-post.php' ) ), 'ccd_recreate_dashboard_page', 'ccd_dashboard_page_nonce' );
		?>
		<?php if ( 'success' === $result ) : ?><tr><th></th><td><div class="notice notice-success inline"><p><?php esc_html_e( 'Dashboard page created or repaired.', 'client-content-dashboard' ); ?></p></div></td></tr><?php elseif ( 'failed' === $result ) : ?><tr><th></th><td><div class="notice notice-error inline"><p><?php esc_html_e( 'Dashboard page could not be created.', 'client-content-dashboard' ); ?></p></div></td></tr><?php endif; ?>
		<tr><th><?php esc_html_e( 'Current status', 'client-content-dashboard' ); ?></th><td><?php echo esc_html( $status['state'] ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Page title', 'client-content-dashboard' ); ?></th><td><?php echo $status['page'] ? esc_html( get_the_title( $status['page'] ) ) : '&mdash;'; ?></td></tr>
		<tr><th><?php esc_html_e( 'Page URL', 'client-content-dashboard' ); ?></th><td><?php if ( $status['page'] && 'exists' === $status['state'] ) : $url = get_permalink( $status['page'] ); ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a><?php else : ?>&mdash;<?php endif; ?></td></tr>
		<tr><th><?php esc_html_e( 'Page template', 'client-content-dashboard' ); ?></th><td><?php echo esc_html( self::template_label( $status['page'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Dashboard Page Action', 'client-content-dashboard' ); ?></th><td><p class="description"><?php esc_html_e( 'For the cleanest portal layout, Elementor Canvas is recommended when available.', 'client-content-dashboard' ); ?><br><?php esc_html_e( 'If Elementor is active, the dashboard page is prepared for Elementor Canvas automatically.', 'client-content-dashboard' ); ?></p><p><a class="button button-secondary" href="<?php echo esc_url( $action_url ); ?>"><?php esc_html_e( 'Create/Recreate Dashboard Page', 'client-content-dashboard' ); ?></a></p></td></tr><?php
	}
}
