<?php
defined('ABSPATH') || exit;
$url   = esc_url($attributes['videoUrl']    ?? '');
$title = esc_html($attributes['title']       ?? '');
$desc  = esc_html($attributes['description'] ?? '');
$thumb = esc_url($attributes['thumbUrl']     ?? '');
$schema= (bool)($attributes['autoSchema']   ?? true);

if (!$url) {
  if (is_admin() || defined('REST_REQUEST')) echo '<div style="padding:2rem;text-align:center;color:#999;border:2px dashed #ccc;border-radius:12px;">' . esc_html__('آدرس ویدیو را وارد کنید.','signteb-blocks') . '</div>';
  return;
}

// Extract embed URL
$embed = '';
if (strpos($url,'youtube') !== false || strpos($url,'youtu.be') !== false) {
  preg_match('/(?:v=|youtu\.be\/)([^&\?\/]+)/', $url, $m);
  if (!empty($m[1])) $embed = 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1&rel=0';
} elseif (strpos($url,'vimeo') !== false) {
  preg_match('/vimeo\.com\/(\d+)/', $url, $m);
  if (!empty($m[1])) $embed = 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1';
}

$uid = 'stmb-vid-'.wp_unique_id();
?>
<figure class="stmb-video-wrap" id="<?php echo esc_attr($uid); ?>">
  <div class="stmb-video"
    data-embed="<?php echo esc_attr($embed ?: $url); ?>"
    data-url="<?php echo esc_attr($url); ?>"
    role="button"
    tabindex="0"
    aria-label="<?php echo $title ?: esc_attr__('پخش ویدیو','signteb-blocks'); ?>"
  >
    <?php if ($thumb) : ?>
      <img src="<?php echo $thumb; ?>" alt="<?php echo $title; ?>" class="stmb-video__thumb" loading="lazy" decoding="async">
    <?php else : ?>
      <div class="stmb-video__thumb-placeholder" aria-hidden="true"></div>
    <?php endif; ?>
    <button class="stmb-video__play" aria-label="<?php esc_attr_e('پخش ویدیو','signteb-blocks'); ?>">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><polygon points="5 3 19 12 5 21 5 3"/></svg>
    </button>
  </div>
  <?php if ($title) : ?><figcaption class="stmb-video__caption"><strong><?php echo $title; ?></strong><?php if ($desc) echo ' — '.$desc; ?></figcaption><?php endif; ?>
</figure>
<?php if ($schema && $title) : ?>
<script type="application/ld+json">
<?php // JSON_UNESCAPED_SLASHES استفاده نمی‌شود چون خروجی داخل تگ <script> است و "/" باید \/ بماند تا "</script>" نتواند تگ را ببندد.
echo wp_json_encode(['@context'=>'https://schema.org','@type'=>'VideoObject','name'=>$title,'description'=>$desc,'contentUrl'=>$url,'thumbnailUrl'=>$thumb,'uploadDate'=>get_the_date('c')],JSON_UNESCAPED_UNICODE); ?>
</script>
<?php endif;
