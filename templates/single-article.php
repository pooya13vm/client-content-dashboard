<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main class="ccd-public-article" id="primary">
	<?php while ( have_posts() ) : the_post(); ?>
	<article <?php post_class( 'ccd-public-article__inner' ); ?>>
		<header class="ccd-public-article__header">
			<h1><?php the_title(); ?></h1>
			<div class="ccd-public-article__meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<?php $categories = get_the_category_list( ', ' ); if ( $categories ) : ?><span aria-hidden="true">·</span><span><?php echo wp_kses_post( $categories ); ?></span><?php endif; ?>
			</div>
		</header>
		<?php if ( has_post_thumbnail() ) : ?><figure class="ccd-public-article__image"><?php the_post_thumbnail( 'large' ); ?></figure><?php endif; ?>
		<div class="ccd-public-article__content"><?php the_content(); ?></div>
	</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
