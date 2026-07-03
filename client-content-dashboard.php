<?php
/**
 * Plugin Name: Client Content Dashboard
 * Description: A simplified frontend dashboard for clients to create and manage structured content.
 * Version: 0.3.3
 * Author: Client Content Dashboard
 * Text Domain: client-content-dashboard
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ccd_plugin_headers = get_file_data( __FILE__, array( 'version' => 'Version' ), 'plugin' );
define( 'CCD_VERSION', $ccd_plugin_headers['version'] );
define( 'CCD_FILE', __FILE__ );
define( 'CCD_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCD_URL', plugin_dir_url( __FILE__ ) );

require_once CCD_DIR . 'includes/class-activator.php';
require_once CCD_DIR . 'includes/class-dashboard-page.php';
require_once CCD_DIR . 'includes/class-templates.php';
require_once CCD_DIR . 'includes/class-admin.php';
require_once CCD_DIR . 'includes/class-client-users.php';
require_once CCD_DIR . 'includes/class-dashboard.php';
require_once CCD_DIR . 'includes/class-article-display.php';
require_once CCD_DIR . 'includes/class-updater.php';

register_activation_hook( __FILE__, array( 'CCD_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CCD_Activator', 'deactivate' ) );

function ccd_boot_plugin() {
	CCD_Activator::maybe_upgrade_role();
	CCD_Admin::init();
	CCD_Dashboard_Page::init();
	CCD_Client_Users::init();
	CCD_Dashboard::init();
	CCD_Article_Display::init();
	CCD_Updater::init( CCD_FILE );
}
add_action( 'plugins_loaded', 'ccd_boot_plugin' );
