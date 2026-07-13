<?php
defined('ABSPATH') || exit;
$installed = get_option('stwiz_demo_installed', '');

// دموها به‌صورت پویا از رجیستری خوانده می‌شوند (افزودن دمو = افزودن پوشه).
$registry = new \SignTeb\Wizard\Setup\DemoRegistry();
$demos    = [];
foreach ($registry->all() as $id => $def) {
  $demos[$id] = [
    'icon'  => $def['icon'] ?? '🏥',
    'title' => $def['title'] ?? $id,
    'desc'  => $def['description'] ?? '',
    'pages' => $def['pages_count'] ?? '',
    'lang'  => $def['lang'] ?? 'فارسی',
    'color' => $def['color'] ?? '#1a56db',
  ];
}
?>
<div class="stwiz-demo-step" data-step="demo">
  <h2><?php esc_html_e('انتخاب دمو محتوا', STWIZ_TEXT); ?></h2>
  <p class="stwiz-step-desc"><?php esc_html_e('یک قالب دمو انتخاب کنید. صفحات، منوها، و محتوای نمونه به صورت خودکار ایجاد می‌شوند.', STWIZ_TEXT); ?></p>

  <div class="stwiz-demo-grid">
    <?php foreach ($demos as $key => $demo) :
      $is_installed = ($installed === $key);
    ?>
    <div class="stwiz-demo-card <?php echo $is_installed ? 'is-installed' : ''; ?>" data-demo="<?php echo esc_attr($key); ?>">
      <div class="stwiz-demo-card__accent" style="background:<?php echo esc_attr($demo['color']); ?>"></div>
      <div class="stwiz-demo-card__icon"><?php echo $demo['icon']; ?></div>
      <h3 class="stwiz-demo-card__title"><?php echo esc_html($demo['title']); ?></h3>
      <p class="stwiz-demo-card__desc"><?php echo esc_html($demo['desc']); ?></p>
      <div class="stwiz-demo-card__meta">
        <span><?php echo esc_html($demo['pages']); ?></span>
        <span><?php echo esc_html($demo['lang']); ?></span>
      </div>
      <button
        type="button"
        class="stwiz-btn stwiz-demo-install-btn <?php echo $is_installed ? 'stwiz-btn--ghost' : 'stwiz-btn--primary'; ?>"
        data-demo="<?php echo esc_attr($key); ?>"
      >
        <?php if ($is_installed) : ?>
          ↻ <?php esc_html_e('راه‌اندازی مجدد', STWIZ_TEXT); ?>
        <?php else : ?>
          <?php esc_html_e('انتخاب و راه‌اندازی خودکار', STWIZ_TEXT); ?>
        <?php endif; ?>
      </button>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="stwiz-demo-status" id="demo-status" hidden>
    <div class="stwiz-demo-progress">
      <div class="stwiz-spinner-lg" aria-hidden="true"></div>
      <p id="demo-status-msg"><?php esc_html_e('در حال نصب دمو...', STWIZ_TEXT); ?></p>
    </div>
  </div>

  <p class="stwiz-demo-skip">
    <?php esc_html_e('ترجیح می‌دهید بدون دمو شروع کنید؟', STWIZ_TEXT); ?>
    <strong><?php esc_html_e('این مرحله اختیاری است.', STWIZ_TEXT); ?></strong>
  </p>
</div>

<script>
(function() {
  // انتخاب دمو → رفتن به صفحه‌ی راه‌اندازی خودکار (اجرای ۸ تسک با نوار پیشرفت)
  var installBase = <?php echo wp_json_encode( admin_url( 'admin.php?page=signteb-wizard&step=install' ) ); ?>;
  document.querySelectorAll('.stwiz-demo-install-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var demo = btn.dataset.demo;
      document.querySelectorAll('.stwiz-demo-card').forEach(c => c.style.opacity = '0.5');
      window.location.href = installBase + '&demo=' + encodeURIComponent(demo);
    });
  });
})();
</script>
