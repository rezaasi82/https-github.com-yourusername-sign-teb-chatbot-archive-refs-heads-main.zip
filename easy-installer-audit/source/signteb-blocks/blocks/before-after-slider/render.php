<?php
/**
 * SignTeb Blocks — Before/After Slider: Render
 */
defined( 'ABSPATH' ) || exit;

$before_url = esc_url( $attributes['beforeImageUrl'] ?? '' );
$after_url  = esc_url( $attributes['afterImageUrl']  ?? '' );
$before_lbl = esc_html( $attributes['beforeLabel']   ?? __( 'قبل', 'signteb-blocks' ) );
$after_lbl  = esc_html( $attributes['afterLabel']    ?? __( 'بعد', 'signteb-blocks' ) );
$initial    = max( 0, min( 100, (int) ( $attributes['initialPosition'] ?? 50 ) ) );
$ratio      = esc_attr( $attributes['aspectRatio'] ?? '3/2' );
$caption    = esc_html( $attributes['caption'] ?? '' );

if ( ! $before_url || ! $after_url ) {
	if ( is_admin() || defined( 'REST_REQUEST' ) ) {
		echo '<div style="padding:2rem;text-align:center;color:#999;border:2px dashed #ccc;border-radius:12px;">';
		esc_html_e( 'تصویر قبل و بعد را از پانل تنظیمات انتخاب کنید.', 'signteb-blocks' );
		echo '</div>';
	}
	return;
}

$uid = 'stmb-ba-' . wp_unique_id();
?>
<figure class="stmb-ba-wrap" aria-label="<?php esc_attr_e( 'تصویر قبل و بعد', 'signteb-blocks' ); ?>">
  <div
    class="stmb-ba"
    id="<?php echo esc_attr( $uid ); ?>"
    data-initial="<?php echo $initial; ?>"
    role="img"
    aria-label="<?php esc_attr_e( 'اسلایدر قبل و بعد از درمان', 'signteb-blocks' ); ?>"
    style="aspect-ratio:<?php echo $ratio; ?>;"
  >
    <!-- After image (full width background) -->
    <div class="stmb-ba__after">
      <img src="<?php echo $after_url; ?>" alt="<?php esc_attr_e( 'بعد از درمان', 'signteb-blocks' ); ?>" class="stmb-ba__img" loading="lazy" decoding="async">
      <span class="stmb-ba__label stmb-ba__label--after" aria-hidden="true"><?php echo $after_lbl; ?></span>
    </div>

    <!-- Before image (clipped left portion) -->
    <div class="stmb-ba__before" style="width:<?php echo $initial; ?>%">
      <img src="<?php echo $before_url; ?>" alt="<?php esc_attr_e( 'قبل از درمان', 'signteb-blocks' ); ?>" class="stmb-ba__img" loading="lazy" decoding="async">
      <span class="stmb-ba__label stmb-ba__label--before" aria-hidden="true"><?php echo $before_lbl; ?></span>
    </div>

    <!-- Divider handle -->
    <div class="stmb-ba__handle" style="right:<?php echo $initial; ?>%" aria-hidden="true">
      <div class="stmb-ba__handle-line"></div>
      <div class="stmb-ba__handle-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
    </div>

  </div>
  <?php if ( $caption ) : ?>
  <figcaption class="stmb-ba__caption"><?php echo $caption; ?></figcaption>
  <?php endif; ?>
</figure>
<?php
