<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CCD_Article_Display {
	private static $article = null;
	private static $rendering_article = false;

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'content' ), 20 );
		add_filter( 'get_the_categories', array( __CLASS__, 'categories' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'prepare_template_page' ), 20 );
		add_shortcode( 'ccd_article', array( __CLASS__, 'shortcode' ) );
		add_action( 'admin_post_ccd_create_article_template', array( __CLASS__, 'create_template_page' ) );
	}

	private static function use_clean_layout() {
		if ( ! is_singular( 'post' ) ) { return false; }
		$settings = get_option( 'ccd_settings', array() );
		$layout = isset( $settings['article_display_layout'] ) ? $settings['article_display_layout'] : 'clean';
		if ( 'clean' !== $layout ) { return false; }
		return '' !== get_post_meta( get_queried_object_id(), '_ccd_content_template', true );
	}

	public static function content( $content ) {
		if ( ! self::use_clean_layout() || ! in_the_loop() || ! is_main_query() ) { return $content; }
		if ( false !== strpos( $content, 'class="ccd-public-article"' ) ) { return $content; }
		return '<div class="ccd-public-article">' . $content . '</div>';
	}

	public static function categories( $categories, $post_id ) {
		if ( ! self::use_clean_layout() || (int) $post_id !== get_queried_object_id() ) { return $categories; }
		$default_category = absint( get_option( 'default_category' ) );
		return array_values( array_filter( $categories, function( $category ) use ( $default_category ) { return (int) $category->term_id !== $default_category; } ) );
	}

	public static function assets() {
		if ( self::use_clean_layout() || self::$article ) {
			wp_enqueue_style( 'ccd-clean-article', CCD_URL . 'assets/css/article.css', array(), CCD_VERSION );
		}
	}

	public static function prepare_template_page() {
		if ( ! is_singular( 'post' ) ) { return; }
		$settings = get_option( 'ccd_settings', array() );
		if ( empty( $settings['article_display_layout'] ) || 'template' !== $settings['article_display_layout'] ) { return; }
		$article = get_queried_object();
		if ( ! $article instanceof WP_Post || '' === get_post_meta( $article->ID, '_ccd_content_template', true ) ) { return; }
		$page_id = empty( $settings['article_template_page_id'] ) ? 0 : absint( $settings['article_template_page_id'] );
		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) { return; }

		self::$article = $article;
		global $wp_query, $post;
		$post = $page;
		$wp_query->posts = array( $page );
		$wp_query->post = $page;
		$wp_query->post_count = 1;
		$wp_query->queried_object = $page;
		$wp_query->queried_object_id = $page->ID;
		$wp_query->is_single = false;
		$wp_query->is_page = true;
		$wp_query->is_singular = true;
		setup_postdata( $page );
	}

	public static function shortcode() {
		if ( self::$rendering_article ) { return ''; }
		if ( ! self::$article instanceof WP_Post ) {
			return current_user_can( 'manage_options' ) ? '<p class="ccd-article-context-note">' . esc_html__( 'The article preview appears here when this page is used as the Client Content Dashboard article template.', 'client-content-dashboard' ) . '</p>' : '';
		}
		$article = self::$article;
		$default_category = absint( get_option( 'default_category' ) );
		$categories = array_filter( get_the_category( $article->ID ), function( $category ) use ( $default_category ) { return (int) $category->term_id !== $default_category; } );
		$category_links = array();
		foreach ( $categories as $category ) { $category_links[] = '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '">' . esc_html( $category->name ) . '</a>'; }
		global $post;
		$template_page = $post;
		$post = $article;
		setup_postdata( $article );
		self::$rendering_article = true;
		$article_content = apply_filters( 'the_content', $article->post_content );
		self::$rendering_article = false;
		ob_start();
		?><article class="ccd-template-article"><header class="ccd-template-article__header"><h1><?php echo esc_html( get_the_title( $article ) ); ?></h1><div class="ccd-template-article__meta"><time datetime="<?php echo esc_attr( get_the_date( 'c', $article ) ); ?>"><?php echo esc_html( get_the_date( '', $article ) ); ?></time><?php if ( $category_links ) : ?><span aria-hidden="true">·</span><span><?php echo wp_kses_post( implode( ', ', $category_links ) ); ?></span><?php endif; ?></div></header><?php if ( has_post_thumbnail( $article ) ) : ?><figure class="ccd-template-article__image"><?php echo get_the_post_thumbnail( $article, 'large' ); ?></figure><?php endif; ?><div class="ccd-public-article"><?php echo $article_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered WordPress post content. ?></div></article><?php
		$output = ob_get_clean();
		$post = $template_page;
		setup_postdata( $template_page );
		return $output;
	}

	public static function create_template_page() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You are not allowed to create template pages.', 'client-content-dashboard' ), '', array( 'response' => 403 ) ); }
		check_admin_referer( 'ccd_create_article_template', 'ccd_article_template_nonce' );
		$settings = get_option( 'ccd_settings', array() );
		$page_id = empty( $settings['article_template_page_id'] ) ? 0 : absint( $settings['article_template_page_id'] );
		$page = $page_id ? get_post( $page_id ) : null;
		if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
			$existing = get_page_by_path( 'article-template', OBJECT, 'page' );
			if ( $existing && 'trash' !== $existing->post_status ) { $page_id = $existing->ID; }
			else { $page_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => __( 'Article Template', 'client-content-dashboard' ), 'post_name' => 'article-template', 'post_content' => '[ccd_article]', 'post_status' => 'publish' ), true ); }
		}
		if ( is_wp_error( $page_id ) || ! $page_id ) { $result = 'failed'; }
		else { $settings['article_template_page_id'] = absint( $page_id ); $settings['article_display_layout'] = 'template'; update_option( 'ccd_settings', $settings ); $result = 'success'; }
		wp_safe_redirect( add_query_arg( array( 'page' => 'client-content-dashboard', 'tab' => 'tools', 'ccd_article_template_result' => $result ), admin_url( 'admin.php' ) ) );
		exit;
	}

}
