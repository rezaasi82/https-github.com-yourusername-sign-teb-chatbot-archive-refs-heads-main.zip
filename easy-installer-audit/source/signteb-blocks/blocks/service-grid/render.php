<?php
/**
 * SignTeb Blocks — Service Grid: Render
 */
defined( 'ABSPATH' ) || exit;

$columns   = max( 1, min( 4, (int) ( $attributes['columns'] ?? 3 ) ) );
$per_page  = (int) ( $attributes['postsPerPage'] ?? 6 );
$slug      = sanitize_key( $attributes['specialtySlug'] ?? '' );
$show_exc  = (bool) ( $attributes['showExcerpt']  ?? true );
$show_dur  = (bool) ( $attributes['showDuration'] ?? true );
$show_price= (bool) ( $attributes['showPrice']    ?? false );
$card_style= esc_attr( $attributes['cardStyle'] ?? 'glass' );
$section_title = esc_html( $attributes['title'] ?? '' );

$query_args = [
	'post_type'      => 'medical-service',
	'posts_per_page' => $per_page,
	'post_status'    => 'publish',
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
];

if ( $slug ) {
	$query_args['tax_query'] = [ [
		'taxonomy' => 'specialty',
		'field'    => 'slug',
		'terms'    => $slug,
	] ];
}

$services = new WP_Query( $query_args );

if ( ! $services->have_posts() ) {
	if ( is_admin() || defined( 'REST_REQUEST' ) ) {
		echo '<p style="color:#999;text-align:center;padding:2rem;">' . esc_html__( 'خدمتی یافت نشد. ابتدا از طریق پیشخوان خدمات پزشکی اضافه کنید.', 'signteb-blocks' ) . '</p>';
	}
	return;
}
?>
<section class="stmb-service-section">

  <?php if ( $section_title ) : ?>
  <div class="stmb-section-header">
    <h2 class="stmb-section-title"><?php echo $section_title; ?></h2>
  </div>
  <?php endif; ?>

  <div class="stmb-service-grid stmb-service-grid--<?php echo $columns; ?>col">
    <?php while ( $services->have_posts() ) : $services->the_post();
      $pid       = get_the_ID();
      $title     = get_the_title();
      $url       = get_permalink();
      $excerpt   = get_the_excerpt();
      $duration  = get_post_meta( $pid, 'stmc_service_duration', true );
      $price     = get_post_meta( $pid, 'stmc_service_price_from', true );
      $img       = get_the_post_thumbnail_url( $pid, 'stmc-service-thumb' );

      // Icon / emoji from meta or default
      $icon = get_post_meta( $pid, 'stmc_service_icon', true ) ?: '⚕️';
    ?>
    <article class="stmb-service-card stmb-service-card--<?php echo $card_style; ?>" itemscope itemtype="https://schema.org/MedicalProcedure">

      <?php if ( $img ) : ?>
      <a href="<?php echo esc_url( $url ); ?>" class="stmb-service-card__img-wrap" tabindex="-1" aria-hidden="true">
        <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="stmb-service-card__img" loading="lazy" decoding="async" width="400" height="250">
      </a>
      <?php else : ?>
      <div class="stmb-service-card__icon-wrap" aria-hidden="true">
        <span class="stmb-service-card__icon"><?php echo esc_html( $icon ); ?></span>
      </div>
      <?php endif; ?>

      <div class="stmb-service-card__body">
        <h3 class="stmb-service-card__title" itemprop="name">
          <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
        </h3>

        <?php if ( $show_exc && $excerpt ) : ?>
          <p class="stmb-service-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 16 ) ); ?></p>
        <?php endif; ?>

        <div class="stmb-service-card__meta">
          <?php if ( $show_dur && $duration ) : ?>
            <span class="stmb-service-card__meta-item">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <?php echo esc_html( $duration ); ?>
            </span>
          <?php endif; ?>
          <?php if ( $show_price && $price ) : ?>
            <span class="stmb-service-card__meta-item stmb-service-card__price">
              <?php esc_html_e( 'از:', 'signteb-blocks' ); ?> <?php echo esc_html( $price ); ?>
            </span>
          <?php endif; ?>
        </div>

        <a href="<?php echo esc_url( $url ); ?>" class="stmb-service-card__link" itemprop="url">
          <?php esc_html_e( 'اطلاعات بیشتر', 'signteb-blocks' ); ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
      </div>

    </article>
    <?php endwhile; wp_reset_postdata(); ?>
  </div>

</section>
<?php
