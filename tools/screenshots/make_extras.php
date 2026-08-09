<?php
// Two small landing images: the admin-bar notification strip, and the teaser bubble.
define('ABSPATH', '/wp/');
define('PZK_DIR', dirname(__DIR__, 2) . '/pezhkam/');
define('PZK_VERSION', '1.0.0');
function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
require PZK_DIR . 'includes/admin/class-icon.php';
use Pezhkam\Admin\Icon;

$count = '۳';
$label = sprintf('%s گفتگوی جدید', '<span class="pzk-ab-count">' . $count . '</span>');
$node  = Icon::svg('chat', 'pzk-ab-ico') . ' ' . $label;

// Mirrors the inline CSS in class-chat-notifier.php, on a WordPress admin bar.
$css = '
body{margin:0;font-family:"Vazirmatn",Tahoma,sans-serif;background:#f0f0f1;}
#wpadminbar{height:32px;background:#1d2327;color:#f0f0f1;display:flex;align-items:center;
  padding:0 14px;font-size:13px;direction:rtl;gap:22px;}
#wpadminbar .item{color:#f0f0f1;opacity:.8;display:inline-flex;align-items:center;gap:6px;}
#wp-admin-bar-pzk-chats{display:inline-flex;align-items:center;gap:4px;color:#f0f0f1;}
#wp-admin-bar-pzk-chats .pzk-ab-count{display:inline-block;min-width:18px;height:18px;line-height:18px;
  margin:0 2px;padding:0 5px;border-radius:9px;text-align:center;background:#d63638;color:#fff;
  font-size:11px;font-weight:700;}
#wp-admin-bar-pzk-chats .pzk-ab-ico{width:16px;height:16px;vertical-align:-3px;margin-inline-end:3px;}
.below{padding:26px 20px;color:#50575e;font-size:13px;}
';

echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
   . '<link rel="stylesheet" href="assets/fonts/font.css"><style>' . $css . '</style></head><body>'
   . '<div id="wpadminbar">'
   . '<span class="item">پیشخوان</span>'
   . '<span class="item">کلینیک نمونه سلامت</span>'
   . '<span id="wp-admin-bar-pzk-chats">' . $node . '</span>'
   . '</div>'
   . '<div class="below">اعلان گفتگوی جدید، در نوار بالای پیشخوان وردپرس روی هر صفحه‌ای دیده می‌شود.</div>'
   . '</body></html>';
