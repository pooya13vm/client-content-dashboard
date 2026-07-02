<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Dashboard {
	private static $login_error = '';
	private static $editor_instance = 0;

	public static function init() {
		add_shortcode( 'client_content_dashboard', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_submission' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
	}

	public static function body_classes( $classes ) {
		if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'client_content_dashboard' ) ) {
			$classes[] = 'ccd-dashboard-page';
		}
		return $classes;
	}

	public static function assets() {
		if ( ! is_singular() || ! has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'client_content_dashboard' ) ) { return; }
		wp_enqueue_style( 'ccd-dashboard', CCD_URL . 'assets/css/dashboard.css', array(), CCD_VERSION );
		wp_enqueue_script( 'ccd-dashboard', CCD_URL . 'assets/js/dashboard.js', array(), CCD_VERSION, true );
		if ( self::can_access() ) {
			add_filter( 'wp_default_editor', array( __CLASS__, 'default_editor' ) );
			wp_enqueue_editor();
			if ( current_user_can( 'upload_files' ) ) { wp_enqueue_media(); }
		}
	}

	public static function default_editor() {
		return 'tinymce';
	}

	private static function is_client_editor() {
		return in_array( 'client_editor', (array) wp_get_current_user()->roles, true );
	}

	public static function handle_submission() {
		if ( ! empty( $_POST['ccd_action'] ) && 'login' === $_POST['ccd_action'] ) {
			self::handle_login();
			return;
		}
		if ( empty( $_POST['ccd_action'] ) || 'save_content' !== $_POST['ccd_action'] ) { return; }
		if ( ! self::can_access() ) { wp_die( esc_html__( 'You are not allowed to create content.', 'client-content-dashboard' ), '', array( 'response' => 403 ) ); }
		if ( empty( $_POST['ccd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccd_nonce'] ) ), 'ccd_save_content' ) ) { wp_die( esc_html__( 'Security check failed.', 'client-content-dashboard' ), 403 ); }

		$template_key = isset( $_POST['ccd_template'] ) ? sanitize_key( $_POST['ccd_template'] ) : '';
		$template = CCD_Templates::get( $template_key );
		if ( ! $template ) { wp_die( esc_html__( 'Invalid content template.', 'client-content-dashboard' ), 400 ); }

		$post_id = isset( $_POST['ccd_post_id'] ) ? absint( $_POST['ccd_post_id'] ) : 0;
		if ( $post_id && ! self::can_edit( $post_id ) ) { wp_die( esc_html__( 'You cannot edit this content.', 'client-content-dashboard' ), 403 ); }
		if ( $post_id && get_post_meta( $post_id, '_ccd_content_template', true ) !== $template_key ) { wp_die( esc_html__( 'Template mismatch.', 'client-content-dashboard' ), 400 ); }

		$data = array( 'post_type' => $template['post_type'] );
		foreach ( $template['fields'] as $field ) {
			if ( 'post_title' === $field['map'] || 'post_content' === $field['map'] ) {
				$value = isset( $_POST[ $field['key'] ] ) ? wp_unslash( $_POST[ $field['key'] ] ) : '';
				$data[ $field['map'] ] = 'post_content' === $field['map'] ? wp_kses_post( $value ) : sanitize_text_field( $value );
			}
		}
		if ( empty( $data['post_title'] ) || empty( $data['post_content'] ) ) { wp_die( esc_html__( 'Title and content are required.', 'client-content-dashboard' ), 400 ); }

		$settings = get_option( 'ccd_settings', array() );
		// The configured status applies to new submissions; edits retain their workflow state.
		$data['post_status'] = $post_id ? get_post_status( $post_id ) : ( isset( $settings['default_post_status'] ) && 'pending' === $settings['default_post_status'] ? 'pending' : 'draft' );
		$result = $post_id ? wp_update_post( array_merge( $data, array( 'ID' => $post_id ) ), true ) : wp_insert_post( $data, true );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ) ); }
		$post_id = (int) $result;
		update_post_meta( $post_id, '_ccd_content_template', $template_key );

		foreach ( $template['fields'] as $field ) {
			self::save_field( $post_id, $field, $settings );
		}
		$url = remove_query_arg( array( 'ccd_edit' ) );
		wp_safe_redirect( add_query_arg( 'ccd_saved', '1', $url ) );
		exit;
	}

	private static function handle_login() {
		if ( is_user_logged_in() ) { return; }
		if ( empty( $_POST['ccd_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ccd_login_nonce'] ) ), 'ccd_frontend_login' ) ) {
			self::$login_error = __( 'Security check failed. Please refresh the page and try again.', 'client-content-dashboard' );
			return;
		}

		$login = isset( $_POST['ccd_login'] ) ? sanitize_user( wp_unslash( $_POST['ccd_login'] ), false ) : '';
		$password = isset( $_POST['ccd_password'] ) ? (string) wp_unslash( $_POST['ccd_password'] ) : '';
		if ( '' === $login || '' === $password ) {
			self::$login_error = __( 'Enter your username or email and password.', 'client-content-dashboard' );
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => ! empty( $_POST['ccd_remember'] ),
			),
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			self::$login_error = __( 'The username/email or password is incorrect.', 'client-content-dashboard' );
			return;
		}

		wp_set_current_user( $user->ID );
		wp_safe_redirect( self::current_dashboard_url() );
		exit;
	}

	private static function current_dashboard_url() {
		$page_id = get_queried_object_id();
		return $page_id ? get_permalink( $page_id ) : home_url( '/' );
	}

	private static function can_access() {
		if ( ! is_user_logged_in() ) { return false; }
		$user = wp_get_current_user();
		$allowed_role = array_intersect( array( 'client_editor', 'administrator' ), (array) $user->roles );
		return ! empty( $allowed_role ) || current_user_can( 'edit_posts' );
	}

	private static function save_field( $post_id, $field, $settings ) {
		$key = $field['key'];
		if ( 'taxonomy' === $field['map'] ) {
			$term = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
			wp_set_object_terms( $post_id, $term ? array( $term ) : array(), $field['taxonomy'] );
		} elseif ( 'meta' === $field['map'] && 'gallery' !== $field['type'] ) {
			$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$value = 'url' === $field['type'] ? esc_url_raw( $value ) : sanitize_textarea_field( $value );
			update_post_meta( $post_id, $field['meta_key'], $value );
		} elseif ( 'image' === $field['type'] && ! empty( $_FILES[ $key ]['name'] ) ) {
			$attachment = self::upload( $key, $post_id, $settings );
			if ( $attachment ) { set_post_thumbnail( $post_id, $attachment ); }
		} elseif ( 'gallery' === $field['type'] && ! empty( $_FILES[ $key ]['name'][0] ) ) {
			$ids = array();
			$limit = empty( $settings['max_gallery_images'] ) ? 8 : absint( $settings['max_gallery_images'] );
			$count = min( count( $_FILES[ $key ]['name'] ), $limit );
			for ( $i = 0; $i < $count; $i++ ) {
				$_FILES['ccd_single_gallery'] = array( 'name' => $_FILES[ $key ]['name'][ $i ], 'type' => $_FILES[ $key ]['type'][ $i ], 'tmp_name' => $_FILES[ $key ]['tmp_name'][ $i ], 'error' => $_FILES[ $key ]['error'][ $i ], 'size' => $_FILES[ $key ]['size'][ $i ] );
				$id = self::upload( 'ccd_single_gallery', $post_id, $settings );
				if ( $id ) { $ids[] = $id; }
			}
			unset( $_FILES['ccd_single_gallery'] );
			if ( $ids ) { update_post_meta( $post_id, $field['meta_key'], $ids ); }
		}
	}

	private static function upload( $key, $post_id, $settings ) {
		$file = $_FILES[ $key ];
		$limit = ( empty( $settings['max_upload_mb'] ) ? 5 : absint( $settings['max_upload_mb'] ) ) * MB_IN_BYTES;
		if ( empty( $file['size'] ) || $file['size'] > $limit ) { return 0; }
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		if ( empty( $checked['type'] ) || 0 !== strpos( $checked['type'], 'image/' ) ) { return 0; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( $key, $post_id );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	private static function can_edit( $post_id ) {
		$post = get_post( $post_id );
		return $post && (int) $post->post_author === get_current_user_id() && current_user_can( 'edit_post', $post_id );
	}

	public static function shortcode() {
		if ( ! is_user_logged_in() ) { return self::render_login_form(); }
		if ( ! self::can_access() ) { return '<p class="ccd-permission-error">' . esc_html__( 'You do not have permission to access this dashboard.', 'client-content-dashboard' ) . '</p>'; }
		$edit_id = isset( $_GET['ccd_edit'] ) ? absint( $_GET['ccd_edit'] ) : 0;
		if ( $edit_id && ! self::can_edit( $edit_id ) ) { $edit_id = 0; }
		ob_start();
		?><div class="ccd-dashboard">
		<header class="ccd-dashboard-header">
			<h1><?php esc_html_e( 'Content Dashboard', 'client-content-dashboard' ); ?></h1>
			<p><?php esc_html_e( 'Create and manage content for your website.', 'client-content-dashboard' ); ?></p>
		</header>
		<?php if ( isset( $_GET['ccd_saved'] ) ) : ?><div class="ccd-notice"><?php esc_html_e( 'Content saved.', 'client-content-dashboard' ); ?></div><?php endif; ?>
		<section class="ccd-dashboard-section ccd-compose-section"><div class="ccd-section-heading"><h2><?php echo $edit_id ? esc_html__( 'Edit Content', 'client-content-dashboard' ) : esc_html__( 'Add New Content', 'client-content-dashboard' ); ?></h2><p><?php esc_html_e( 'Choose a template and add the details below.', 'client-content-dashboard' ); ?></p></div><?php self::render_form( $edit_id ); ?></section>
		<section class="ccd-dashboard-section ccd-content-section"><div class="ccd-section-heading"><h2><?php esc_html_e( 'My Content', 'client-content-dashboard' ); ?></h2><p><?php esc_html_e( 'Review and update content you have created.', 'client-content-dashboard' ); ?></p></div><?php self::render_list(); ?></section>
		</div><?php
		return ob_get_clean();
	}

	private static function render_login_form() {
		$login = isset( $_POST['ccd_login'] ) && isset( $_POST['ccd_action'] ) && 'login' === $_POST['ccd_action'] ? sanitize_user( wp_unslash( $_POST['ccd_login'] ), false ) : '';
		ob_start();
		?><div class="ccd-login-box">
		<h2><?php esc_html_e( 'Client Portal Login', 'client-content-dashboard' ); ?></h2>
		<div class="ccd-login-error" role="alert" aria-live="polite"><?php if ( self::$login_error ) { echo esc_html( self::$login_error ); } ?></div>
		<form class="ccd-login-form" method="post" action="">
			<?php wp_nonce_field( 'ccd_frontend_login', 'ccd_login_nonce' ); ?>
			<input type="hidden" name="ccd_action" value="login">
			<label><?php esc_html_e( 'Username or email', 'client-content-dashboard' ); ?><input type="text" name="ccd_login" value="<?php echo esc_attr( $login ); ?>" autocomplete="username" required></label>
			<label><?php esc_html_e( 'Password', 'client-content-dashboard' ); ?><input type="password" name="ccd_password" autocomplete="current-password" required></label>
			<label class="ccd-remember"><input type="checkbox" name="ccd_remember" value="1"> <?php esc_html_e( 'Remember me', 'client-content-dashboard' ); ?></label>
			<button type="submit"><?php esc_html_e( 'Log In', 'client-content-dashboard' ); ?></button>
		</form></div><?php
		return ob_get_clean();
	}

	private static function render_form( $post_id ) {
		$templates = CCD_Templates::all();
		$selected = $post_id ? get_post_meta( $post_id, '_ccd_content_template', true ) : array_key_first( $templates );
		$post = $post_id ? get_post( $post_id ) : null;
		$shared_fields = array();
		if ( isset( $templates[ $selected ]['fields'] ) ) {
			foreach ( $templates[ $selected ]['fields'] as $field ) {
				if ( in_array( $field['map'], array( 'post_title', 'post_content' ), true ) ) { $shared_fields[ $field['map'] ] = $field; }
			}
		}
		?><form class="ccd-form" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'ccd_save_content', 'ccd_nonce' ); ?><input type="hidden" name="ccd_action" value="save_content"><input type="hidden" name="ccd_post_id" value="<?php echo esc_attr( $post_id ); ?>">
		<label><?php esc_html_e( 'Content Template', 'client-content-dashboard' ); ?><select name="ccd_template" id="ccd-template" <?php disabled( (bool) $post_id ); ?>><?php foreach ( $templates as $key => $template ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>><?php echo esc_html( $template['label'] ); ?></option><?php endforeach; ?></select></label>
		<?php if ( $post_id ) : ?><input type="hidden" name="ccd_template" value="<?php echo esc_attr( $selected ); ?>"><?php endif; ?>
		<?php if ( isset( $shared_fields['post_title'] ) ) { self::render_field( $shared_fields['post_title'], $post, 'shared' ); } ?>
		<?php if ( isset( $shared_fields['post_content'] ) ) { self::render_field( $shared_fields['post_content'], $post, 'shared' ); } ?>
		<?php foreach ( $templates as $key => $template ) : ?><div class="ccd-template-fields" data-template="<?php echo esc_attr( $key ); ?>"<?php echo $selected !== $key ? ' hidden' : ''; ?>><?php foreach ( $template['fields'] as $field ) { if ( ! in_array( $field['map'], array( 'post_title', 'post_content' ), true ) ) { self::render_field( $field, $post, $key ); } } ?></div><?php endforeach; ?>
		<button class="ccd-submit" type="submit"><?php esc_html_e( 'Save Content', 'client-content-dashboard' ); ?></button></form><?php
	}

	private static function render_field( $field, $post, $template_key ) {
		$value = '';
		if ( $post && 'post_title' === $field['map'] ) { $value = $post->post_title; }
		elseif ( $post && 'post_content' === $field['map'] ) { $value = $post->post_content; }
		elseif ( $post && 'meta' === $field['map'] && 'gallery' !== $field['type'] ) { $value = get_post_meta( $post->ID, $field['meta_key'], true ); }
		$required = ! empty( $field['required'] ) ? ' required' : '';
		if ( 'post_content' === $field['map'] || 'rich_text' === $field['type'] ) {
			self::$editor_instance++;
			$editor_id = 'ccd_editor_' . sanitize_key( $template_key ) . '_' . sanitize_key( $field['key'] ) . '_' . self::$editor_instance;
			?><div class="ccd-field ccd-rich-text-field ccd-rich-editor"><label for="<?php echo esc_attr( $editor_id ); ?>"><?php echo esc_html( $field['label'] ); ?></label><?php
			wp_editor(
				$value,
				$editor_id,
				array(
					'textarea_name' => $field['key'],
					'media_buttons' => current_user_can( 'upload_files' ),
					'teeny'         => false,
					'quicktags'     => ! self::is_client_editor(),
					'editor_height' => 380,
					'tinymce'       => array(
						'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,undo,redo,removeformat',
						'toolbar2' => '',
					),
				)
			);
			echo '</div>';
			return;
		}
		?><label><?php echo esc_html( $field['label'] ); ?><?php
		if ( 'textarea' === $field['type'] ) : ?><textarea name="<?php echo esc_attr( $field['key'] ); ?>" rows="6"<?php echo esc_attr( $required ); ?>><?php echo esc_textarea( $value ); ?></textarea><?php
		elseif ( 'taxonomy' === $field['type'] ) : $terms = get_terms( array( 'taxonomy' => $field['taxonomy'], 'hide_empty' => false ) ); $current = $post ? wp_get_object_terms( $post->ID, $field['taxonomy'], array( 'fields' => 'ids' ) ) : array(); ?><select name="<?php echo esc_attr( $field['key'] ); ?>"><option value="0"><?php esc_html_e( 'None', 'client-content-dashboard' ); ?></option><?php if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { ?><option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $current, true ) ); ?>><?php echo esc_html( $term->name ); ?></option><?php } } ?></select><?php
		elseif ( in_array( $field['type'], array( 'image', 'gallery' ), true ) ) : ?><input type="file" name="<?php echo esc_attr( $field['key'] ); ?><?php echo 'gallery' === $field['type'] ? '[]' : ''; ?>" accept="image/*" <?php echo 'gallery' === $field['type'] ? 'multiple' : ''; ?>><?php
		else : ?><input type="<?php echo esc_attr( in_array( $field['type'], array( 'url', 'date' ), true ) ? $field['type'] : 'text' ); ?>" name="<?php echo esc_attr( $field['key'] ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo esc_attr( $required ); ?>><?php endif; ?></label><?php
	}

	private static function render_list() {
		$posts = get_posts( array( 'author' => get_current_user_id(), 'post_type' => 'post', 'post_status' => array( 'draft', 'pending', 'publish', 'private' ), 'posts_per_page' => 50, 'meta_key' => '_ccd_content_template' ) );
		if ( ! $posts ) { echo '<p class="ccd-empty-state">' . esc_html__( 'No content yet.', 'client-content-dashboard' ) . '</p>'; return; }
		echo '<div class="ccd-content-list">';
		foreach ( $posts as $post ) { $url = add_query_arg( 'ccd_edit', $post->ID ); echo '<div><strong>' . esc_html( get_the_title( $post ) ) . '</strong><span>' . esc_html( get_post_status_object( $post->post_status )->label ) . '</span><a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit', 'client-content-dashboard' ) . '</a></div>'; }
		echo '</div>';
	}
}
