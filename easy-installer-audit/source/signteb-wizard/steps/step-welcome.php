<?php defined('ABSPATH') || exit; ?>
<div class="stwiz-welcome">
  <div class="stwiz-welcome__hero">
    <div class="stwiz-welcome__emoji">⚕️</div>
    <h1><?php esc_html_e('به SignTeb MedCore خوش آمدید', STWIZ_TEXT); ?></h1>
    <p><?php esc_html_e('این ویزارد شما را در ۵ مرحله ساده راهنمایی می‌کند تا وب‌سایت پزشکی حرفه‌ای خود را راه‌اندازی کنید.', STWIZ_TEXT); ?></p>
  </div>

  <div class="stwiz-welcome__features">
    <?php
    $features = [
      ['🎨', 'تنظیم برند و هویت بصری'],
      ['🏥', 'معرفی کلینیک و پزشکان'],
      ['📞', 'اتصال کانال‌های ارتباطی'],
      ['🖼️', 'نصب خودکار محتوای دمو'],
      ['🚀', 'آماده انتشار در ۵ دقیقه'],
    ];
    foreach ($features as [$icon, $text]) : ?>
    <div class="stwiz-feature">
      <span class="stwiz-feature__icon"><?php echo $icon; ?></span>
      <span><?php echo esc_html($text); ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="stwiz-welcome__req">
    <h3><?php esc_html_e('پیش‌نیازها', STWIZ_TEXT); ?></h3>
    <?php
    $checks = [
      ['PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, version_compare(PHP_VERSION,'8.1','>=')],
      ['WordPress ' . get_bloginfo('version'), version_compare(get_bloginfo('version'),'6.4','>=')],
      ['HTTPS', is_ssl()],
    ];
    foreach ($checks as [$label, $ok]) : ?>
    <div class="stwiz-req-item <?php echo $ok?'ok':'fail'; ?>">
      <?php echo $ok ? '✅' : '❌'; ?>
      <?php echo esc_html($label); ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
