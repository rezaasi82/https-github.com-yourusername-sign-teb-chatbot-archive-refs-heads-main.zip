<?php
/**
 * SignTeb Blocks — Testimonials Slider: Render
 *
 * می‌خواند از جدول stmc_reviews (از طریق Repository در پلاگین Medical Core)
 * — فقط نظرات با وضعیت approved نمایش داده می‌شوند.
 *
 * نکته: نیاز به پلاگین SignTeb Medical Core فعال دارد.
 */
defined( 'ABSPATH' ) || exit;

$cols       = max(1, min(4, (int)($attributes['columns'] ?? 3)));
$per_page   = (int)($attributes['perPage']    ?? 6);
$doc_id     = (int)($attributes['doctorId']   ?? 0);
$title      = esc_html($attributes['title']   ?? __('نظرات بیماران','signteb-blocks'));
$theme      = esc_attr($attributes['theme']   ?? 'light');
$show_stars = (bool)($attributes['showRating'] ?? true);

// این بلوک به Repository پلاگین Medical Core وابسته است
if ( ! class_exists( '\STMC\Reviews\Repository' ) ) {
	if ( is_admin() || defined( 'REST_REQUEST' ) ) {
		echo '<p style="padding:1rem;color:#999;">' . esc_html__( 'برای نمایش نظرات، پلاگین SignTeb Medical Core را فعال کنید.', 'signteb-blocks' ) . '</p>';
	}
	return;
}

$reviews = ( new \STMC\Reviews\Repository() )->get_approved( $doc_id, $per_page );

if ( empty( $reviews ) ) {
	if ( is_admin() || defined( 'REST_REQUEST' ) ) {
		echo '<p style="padding:1rem;color:#999;">' . esc_html__( 'هنوز نظر تأیید شده‌ای برای نمایش وجود ندارد.', 'signteb-blocks' ) . '</p>';
	}
	return;
}
?>
<section class="stmb-testimonials stmb-testimonials--<?php echo $theme; ?>">
  <?php if ($title) : ?><div class="stmb-testimonials__header"><h2 class="stmb-testimonials__title"><?php echo $title; ?></h2></div><?php endif; ?>
  <div class="stmb-testimonials__grid stmb-testimonials--<?php echo $cols; ?>col">
    <?php foreach ( $reviews as $review ) :
      $author    = esc_html( $review->reviewer_name ?: __( 'بیمار', 'signteb-blocks' ) );
      $city      = esc_html( $review->reviewer_city ?? '' );
      $rating    = max( 1, min( 5, (int) $review->rating ) );
      $treatment = esc_html( $review->treatment ?? '' );
      $content   = wp_kses_post( wp_trim_words( (string) $review->content, 30 ) );
    ?>
    <div class="stmb-review-card" itemscope itemtype="https://schema.org/Review">
      <?php if ($show_stars) : ?>
      <div class="stmb-review-card__stars" aria-label="<?php echo $rating; ?> ستاره از ۵">
        <?php for($i=1;$i<=5;$i++) echo '<span class="stmb-star' . ($i<=$rating?' stmb-star--on':'') . '" aria-hidden="true">★</span>'; ?>
      </div>
      <?php endif; ?>
      <blockquote class="stmb-review-card__text" itemprop="reviewBody">
        <?php echo $content; ?>
      </blockquote>
      <?php if ($treatment) : ?>
        <p class="stmb-review-card__treatment"><?php echo $treatment; ?></p>
      <?php endif; ?>
      <footer class="stmb-review-card__footer" itemprop="author" itemscope itemtype="https://schema.org/Person">
        <div class="stmb-review-card__avatar stmb-review-card__avatar--placeholder" aria-hidden="true"><?php echo mb_substr($author,0,1); ?></div>
        <div>
          <div class="stmb-review-card__name" itemprop="name"><?php echo $author; ?></div>
          <?php if ($city) : ?><div class="stmb-review-card__city"><?php echo $city; ?></div><?php endif; ?>
        </div>
      </footer>
      <meta itemprop="ratingValue" content="<?php echo $rating; ?>">
      <meta itemprop="bestRating"  content="5">
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php
