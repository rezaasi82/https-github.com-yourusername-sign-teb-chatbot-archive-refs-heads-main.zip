<?php
defined('ABSPATH') || exit;
$url   = esc_url($attributes['videoUrl']    ?? '');
$title = esc_html($attributes['title']       ?? '');
$desc  = esc_html($attributes['description'] ?? '');
$thumb = esc_url($attributes['thumbUrl']     ?? '');
$schema= (bool)($attributes['autoSchema']   ?? true);

// نکته: بلوک همیشه یک «پوسترِ زیبا با دکمه‌ی پخش» نشان می‌دهد — حتی اگر هنوز
// آدرس ویدیویی وارد نشده باشد. این‌طور در دموها یک سکشن ویدیوی کامل دیده می‌شود
// و کاربر فقط کافی است آدرس یوتیوب/آپارات/ویمئوی خودش را در تنظیماتِ بلوک
// (المنتور یا ویرایشگر) وارد کند تا با کلیک پخش شود.

// Extract embed URL (YouTube / Vimeo / آپارات)
$embed = '';
if ($url) {
  if (strpos($url,'youtube') !== false || strpos($url,'youtu.be') !== false) {
    preg_match('/(?:v=|youtu\.be\/)([^&\?\/]+)/', $url, $m);
    if (!empty($m[1])) $embed = 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1&rel=0';
  } elseif (strpos($url,'vimeo') !== false) {
    preg_match('/vimeo\.com\/(\d+)/', $url, $m);
    if (!empty($m[1])) $embed = 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1';
  } elseif (strpos($url,'aparat.com') !== false) {
    preg_match('#aparat\.com/v/([A-Za-z0-9]+)#', $url, $m);
    if (!empty($m[1])) $embed = 'https://www.aparat.com/video/video/embed/videohash/'.$m[1].'/vt/frame';
  }
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
<?php if ($schema && $title && $url) : ?>
<script type="application/ld+json">
<?php // JSON_UNESCAPED_SLASHES استفاده نمی‌شود چون خروجی داخل تگ <script> است و "/" باید \/ بماند تا "</script>" نتواند تگ را ببندد.
echo wp_json_encode(['@context'=>'https://schema.org','@type'=>'VideoObject','name'=>$title,'description'=>$desc,'contentUrl'=>$url,'thumbnailUrl'=>$thumb,'uploadDate'=>get_the_date('c')],JSON_UNESCAPED_UNICODE); ?>
</script>
<?php endif;
