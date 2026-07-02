<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Client_Users {
	public static function init() {
		add_action( 'admin_post_ccd_create_client_user', array( __CLASS__, 'create' ) );
		add_action( 'wp_login', array( __CLASS__, 'track_login' ), 10, 2 );
	}

	public static function track_login( $user_login, $user ) {
		if ( $user instanceof WP_User && in_array( 'client_editor', (array) $user->roles, true ) ) {
			update_user_meta( $user->ID, '_ccd_last_login', time() );
		}
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
		<h2><?php esc_html_e( 'Create Client User', 'client-content-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'Client users can log into the frontend dashboard. They are restricted from the normal WordPress admin area.', 'client-content-dashboard' ); ?></p>
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
		</form>
		<hr><h2><?php esc_html_e( 'Existing Client Users', 'client-content-dashboard' ); ?></h2>
		<p><?php esc_html_e( 'Only users assigned the custom Client Editor role are shown below.', 'client-content-dashboard' ); ?></p>
		<?php self::render_users_table(); ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Existing users with another role are not changed automatically. Converting selected users to Client Editor can be added in a future version.', 'client-content-dashboard' ); ?></p></div><?php
	}

	private static function article_count( $user_id ) {
		$query = new WP_Query( array( 'author' => absint( $user_id ), 'post_type' => 'post', 'post_status' => array( 'draft', 'pending', 'publish', 'private', 'future' ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_ccd_content_template', 'no_found_rows' => false ) );
		return absint( $query->found_posts );
	}

	private static function render_users_table() {
		$users = get_users( array( 'role' => 'client_editor', 'orderby' => 'display_name', 'order' => 'ASC' ) );
		if ( ! $users ) { echo '<p>' . esc_html__( 'No client users found.', 'client-content-dashboard' ) . '</p>'; return; }
		?>
		<table class="widefat striped"><thead><tr>
		<th><?php esc_html_e( 'Name', 'client-content-dashboard' ); ?></th><th><?php esc_html_e( 'Username', 'client-content-dashboard' ); ?></th><th><?php esc_html_e( 'Email', 'client-content-dashboard' ); ?></th><th><?php esc_html_e( 'Articles', 'client-content-dashboard' ); ?></th><th><?php esc_html_e( 'Last Login', 'client-content-dashboard' ); ?></th><th><?php esc_html_e( 'Status / Role', 'client-content-dashboard' ); ?></th><th><?php esc_html_e( 'Actions', 'client-content-dashboard' ); ?></th>
		</tr></thead><tbody><?php foreach ( $users as $user ) : $last_login = absint( get_user_meta( $user->ID, '_ccd_last_login', true ) ); ?>
		<tr><td><?php echo esc_html( $user->display_name ? $user->display_name : $user->user_login ); ?></td><td><strong><?php echo esc_html( $user->user_login ); ?></strong></td><td><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></td><td><?php echo esc_html( (string) self::article_count( $user->ID ) ); ?></td><td><?php echo $last_login ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_login ) ) : esc_html__( 'Not tracked', 'client-content-dashboard' ); ?></td><td><?php esc_html_e( 'Client Editor', 'client-content-dashboard' ); ?></td><td><?php if ( current_user_can( 'edit_user', $user->ID ) ) : ?><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php esc_html_e( 'Edit User', 'client-content-dashboard' ); ?></a><?php else : ?>&mdash;<?php endif; ?><span class="description" style="display:block"><?php esc_html_e( 'Password reset: coming later', 'client-content-dashboard' ); ?></span></td></tr>
		<?php endforeach; ?></tbody></table><?php
	}
}
