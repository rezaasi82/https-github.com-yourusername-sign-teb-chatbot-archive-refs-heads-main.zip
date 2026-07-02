<?php
/**
 * SignTeb Blocks — FAQ Accordion: Render
 */
defined( 'ABSPATH' ) || exit;

$title         = esc_html( $attributes['title']       ?? __( 'سؤالات متداول', 'signteb-blocks' ) );
$slug          = sanitize_key( $attributes['specialtySlug'] ?? '' );
$per_page      = (int) ( $attributes['postsPerPage']  ?? 8 );
$theme         = esc_attr( $attributes['theme']       ?? 'light' );
$allow_multi   = (bool) ( $attributes['allowMultiple'] ?? false );
$gen_schema    = (bool) ( $attributes['generateSchema'] ?? true );

// Pull from medical-faq CPT
$query_args = [
	'post_type'      => 'medical-faq',
	'posts_per_page' => $per_page,
	'post_status'    => 'publish',
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
];

if ( $slug ) {
	$query_args['tax_query'] = [ [ 'taxonomy' => 'specialty', 'field' => 'slug', 'terms' => $slug ] ];
}

$faqs = new WP_Query( $query_args );

if ( ! $faqs->have_posts() ) {
	if ( is_admin() || defined( 'REST_REQUEST' ) ) {
		echo '<p style="color:#999;text-align:center;padding:2rem;">' . esc_html__( 'سؤالی یافت نشد. از منوی «سؤالات متداول» اضافه کنید.', 'signteb-blocks' ) . '</p>';
	}
	return;
}

$uid = 'stmb-faq-' . wp_unique_id();

// Collect items for schema
$schema_items = [];
?>
<section class="stmb-faq-section stmb-faq-section--<?php echo $theme; ?>" aria-label="<?php echo $title; ?>">

  <?php if ( $title ) : ?>
  <div class="stmb-faq-header">
    <h2 class="stmb-faq-title"><?php echo $title; ?></h2>
  </div>
  <?php endif; ?>

  <div
    class="stmb-faq-list"
    id="<?php echo esc_attr( $uid ); ?>"
    data-multiple="<?php echo $allow_multi ? 'true' : 'false'; ?>"
  >
    <?php $index = 0; while ( $faqs->have_posts() ) : $faqs->the_post(); $index++;
      $q      = get_the_title();
      $a_html = get_the_content();
      $a_text = wp_strip_all_tags( $a_html );
      $item_id = $uid . '-item-' . $index;
      $panel_id= $uid . '-panel-' . $index;

      if ( $gen_schema ) {
        $schema_items[] = [
          '@type'          => 'Question',
          'name'           => $q,
          'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $a_text ],
        ];
      }
    ?>
    <div class="stmb-faq-item" id="<?php echo esc_attr( $item_id ); ?>">

      <button
        class="stmb-faq-btn"
        type="button"
        aria-expanded="false"
        aria-controls="<?php echo esc_attr( $panel_id ); ?>"
      >
        <span class="stmb-faq-btn__text"><?php echo esc_html( $q ); ?></span>
        <span class="stmb-faq-btn__icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </span>
      </button>

      <div
        class="stmb-faq-panel"
        id="<?php echo esc_attr( $panel_id ); ?>"
        role="region"
        aria-labelledby="<?php echo esc_attr( $item_id ); ?>"
        hidden
      >
        <div class="stmb-faq-panel__content">
          <?php echo wp_kses_post( $a_html ); ?>
        </div>
      </div>

    </div>
    <?php endwhile; wp_reset_postdata(); ?>
  </div>

</section>

<?php if ( $gen_schema && ! empty( $schema_items ) ) : ?>
<script type="application/ld+json">
<?php
// نکته امنیتی: JSON_UNESCAPED_SLASHES عمداً استفاده نمی‌شود چون این JSON داخل
// تگ <script> چاپ می‌شود. عنوان/متن سؤالات از پست (get_the_title/get_the_content)
// می‌آید و می‌تواند شامل "</script>" باشد؛ بدون escape شدن "/" به "\/"، این رشته
// می‌تواند تگ script را ببندد و کد دلخواه تزریق کند (XSS ذخیره‌شده).
echo wp_json_encode( [
  '@context'   => 'https://schema.org',
  '@type'      => 'FAQPage',
  'mainEntity' => $schema_items,
], JSON_UNESCAPED_UNICODE ); ?>
</script>
<?php endif;
