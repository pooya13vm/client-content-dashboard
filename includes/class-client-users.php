<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Client_Users {
	public static function init() {
		add_action( 'admin_post_ccd_create_client_user', array( __CLASS__, 'create' ) );
	}

	public static function create() {
		if ( ! current_user_can( 'create_users' ) ) {
			wp_die( esc_html__( 'You are not allowed to create users.', 'client-content-dashboard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'ccd_create_client_user', 'ccd_client_user_nonce' );

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';

		$result = 'invalid';
		if ( $username && is_email( $email ) && $password ) {
			$user_id = wp_insert_user( array( 'user_login' => $username, 'user_email' => $email, 'user_pass' => $password, 'first_name' => $first_name, 'last_name' => $last_name, 'role' => 'client_editor' ) );
			if ( ! is_wp_error( $user_id ) ) { $result = 'success'; }
			elseif ( in_array( $user_id->get_error_code(), array( 'existing_user_login', 'existing_user_email' ), true ) ) { $result = 'exists'; }
			else { $result = 'failed'; }
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => 'client-users', 'ccd_user_result' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'create_users' ) ) { return; }
		$result = isset( $_GET['ccd_user_result'] ) ? sanitize_key( $_GET['ccd_user_result'] ) : '';
		$messages = array(
			'success' => array( 'success', __( 'Client user created successfully.', 'client-content-dashboard' ) ),
			'exists'  => array( 'error', __( 'That username or email is already in use.', 'client-content-dashboard' ) ),
			'invalid' => array( 'error', __( 'Enter a valid username, email address, and password.', 'client-content-dashboard' ) ),
			'failed'  => array( 'error', __( 'The client user could not be created.', 'client-content-dashboard' ) ),
		);
		?>
		<hr><h2><?php esc_html_e( 'Client Users', 'client-content-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'Create a WordPress user with the Client Editor role.', 'client-content-dashboard' ); ?></p>
		<?php if ( isset( $messages[ $result ] ) ) : ?><div class="notice notice-<?php echo esc_attr( $messages[ $result ][0] ); ?> inline"><p><?php echo esc_html( $messages[ $result ][1] ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ccd_create_client_user">
			<?php wp_nonce_field( 'ccd_create_client_user', 'ccd_client_user_nonce' ); ?>
			<table class="form-table" role="presentation">
			<tr><th><label for="ccd-username"><?php esc_html_e( 'Username', 'client-content-dashboard' ); ?></label></th><td><input id="ccd-username" class="regular-text" type="text" name="username" required autocomplete="off"></td></tr>
			<tr><th><label for="ccd-email"><?php esc_html_e( 'Email', 'client-content-dashboard' ); ?></label></th><td><input id="ccd-email" class="regular-text" type="email" name="email" required autocomplete="off"></td></tr>
			<tr><th><label for="ccd-password"><?php esc_html_e( 'Password', 'client-content-dashboard' ); ?></label></th><td><input id="ccd-password" class="regular-text" type="password" name="password" required autocomplete="new-password"></td></tr>
			<tr><th><label for="ccd-first-name"><?php esc_html_e( 'First Name', 'client-content-dashboard' ); ?></label></th><td><input id="ccd-first-name" class="regular-text" type="text" name="first_name"></td></tr>
			<tr><th><label for="ccd-last-name"><?php esc_html_e( 'Last Name', 'client-content-dashboard' ); ?></label></th><td><input id="ccd-last-name" class="regular-text" type="text" name="last_name"></td></tr>
			</table><?php submit_button( __( 'Create Client User', 'client-content-dashboard' ), 'secondary' ); ?>
		</form><?php
	}
}
