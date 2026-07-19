/**
 * Bilingual UI (English/Persian). The active language follows the WordPress
 * admin locale delivered in sdaBoot — no separate toggle to keep in sync.
 * `t()` looks the English source string up in the Persian dictionary and
 * falls back to English, so untranslated strings degrade gracefully instead
 * of showing keys. RTL layout is already handled by CSS logical properties.
 */

import { boot } from './api/client';

const FA: Record<string, string> = {
  // Shell / navigation
  'Overview': 'نمای کلی',
  'Winners & Losers': 'برنده‌ها و بازنده‌ها',
  'Opportunities': 'فرصت‌ها',
  'Content': 'محتوا',
  'Roadmap': 'نقشه راه',
  'Alerts': 'هشدارها',
  'Reports': 'گزارش‌ها',
  'Agency': 'آژانس',
  'Settings': 'تنظیمات',
  'Toggle theme': 'تغییر پوسته',
  'Upgrade': 'ارتقا',

  // Common
  'Save': 'ذخیره',
  'Saved': 'ذخیره شد',
  'Saving…': 'در حال ذخیره…',
  'Connect': 'اتصال',
  'Disconnect': 'قطع اتصال',
  'Connected': 'متصل',
  'Not connected': 'متصل نیست',
  'Loading…': 'در حال بارگذاری…',
  'Unknown error': 'خطای نامشخص',
  'Set up ▸': 'راه‌اندازی ▸',
  'done': 'انجام شد',

  // Error boundary
  'SEO Director AI could not start': 'SEO Director AI اجرا نشد',
  'Please copy this message (and your browser console output) to support. Reloading the page may help if this was a temporary network error.':
    'لطفاً این پیام (و خروجی کنسول مرورگر) را برای پشتیبانی ارسال کنید. اگر خطای موقت شبکه بوده، بارگذاری مجدد صفحه ممکن است مشکل را حل کند.',

  // Upgrade funnel
  'Upgrade to': 'ارتقا به',
  'This is available on the {plan} plan and above.': 'این قابلیت در پلن {plan} و بالاتر در دسترس است.',
  'is not available on your plan': 'در پلن فعلی شما در دسترس نیست',

  // Overview page
  'You’re viewing sample data': 'شما در حال مشاهدهٔ دادهٔ نمونه هستید',
  'This is a preview so you can see what SEO Director AI does. Connect Google Search Console in': 'این یک پیش‌نمایش است تا کارکرد SEO Director AI را ببینید. برای جایگزینی با آمار واقعی سایت، سرچ‌کنسول گوگل را در',
  'to replace it with your site’s real numbers.': 'متصل کنید.',
  'Get set up': 'راه‌اندازی اولیه',
  'SEO Health Score': 'امتیاز سلامت سئو',
  'No score yet': 'هنوز امتیازی نیست',
  'Scores appear after the first analysis pass over synced data.': 'امتیاز پس از نخستین تحلیل روی داده‌های همگام‌شده نمایش داده می‌شود.',
  'Weekly Summary': 'خلاصهٔ هفتگی',
  'Organic Traffic': 'ترافیک ارگانیک',
  'No traffic data': 'دادهٔ ترافیک موجود نیست',
  'Select a property in Settings to start syncing.': 'برای شروع همگام‌سازی، یک property در تنظیمات انتخاب کنید.',
  'Connect Google in Settings to begin.': 'برای شروع، گوگل را در تنظیمات متصل کنید.',
  'Top Opportunities': 'فرصت‌های برتر',
  'Opportunities appear after the first analysis run.': 'فرصت‌ها پس از نخستین اجرای تحلیل نمایش داده می‌شوند.',
  'All opportunities ▸': 'همهٔ فرصت‌ها ▸',
  'Top Risks': 'ریسک‌های مهم',
  'No active risks detected.': 'ریسک فعالی شناسایی نشده است.',
  'All alerts ▸': 'همهٔ هشدارها ▸',
  'Could not load the dashboard': 'داشبورد بارگذاری نشد',
  'clicks/mo': 'کلیک/ماه',
  'Search Console': 'سرچ‌کنسول',
  'Analytics 4': 'آنالیتیکس ۴',
  'PageSpeed': 'پیج‌اسپید',
  'AI': 'هوش مصنوعی',

  // Alerts page
  'Alert Center': 'مرکز هشدار',
  'No alerts': 'هشداری نیست',
  'New alerts appear here when traffic drops, rankings slip, or Core Web Vitals regress.':
    'هشدارهای جدید هنگام افت ترافیک، سقوط رتبه یا پسرفت Core Web Vitals اینجا نمایش داده می‌شوند.',
  'Acknowledge': 'تأیید',
  'Resolve': 'رفع شد',
  'Severity': 'شدت',
  'Message': 'پیام',
  'Raised': 'زمان ایجاد',
  'Status': 'وضعیت',
  'Actions': 'اقدام‌ها',

  // Settings page (section titles + key labels)
  'Google (Search Console + Analytics)': 'گوگل (سرچ‌کنسول + آنالیتیکس)',
  'AI Provider': 'ارائه‌دهندهٔ هوش مصنوعی',
  'Alert channels': 'کانال‌های هشدار',
  'Automatic updates': 'به‌روزرسانی خودکار',
  'Update automatically when a new version is released': 'با انتشار نسخهٔ جدید، به‌صورت خودکار به‌روزرسانی شود',
  'License': 'لایسنس',
  'Email': 'ایمیل',
  'Webhook URL': 'آدرس Webhook',
  'Slack webhook URL': 'آدرس وب‌هوک اسلک',
  'Telegram bot token': 'توکن ربات تلگرام',
  'Telegram chat id': 'شناسهٔ چت تلگرام',
  'Bale bot token': 'توکن ربات بله',
  'Bale chat id': 'شناسهٔ چت بله',
  'Bale (بله) messenger': 'پیام‌رسان بله',
  'Google Ads': 'گوگل ادز',
  'Developer token': 'توکن توسعه‌دهنده',
  'Customer ID': 'شناسهٔ مشتری',
  'Google Ads needs a developer token and the account’s customer id; it uses the same Google connection above.':
    'گوگل ادز به توکن توسعه‌دهنده و شناسهٔ مشتری اکانت نیاز دارد؛ از همان اتصال گوگل بالا استفاده می‌کند.',
  'Google Business Profile and Google Ads use the same Google connection — reconnect Google to grant the new permissions.':
    'پروفایل کسب‌وکار گوگل و گوگل ادز از همان اتصال گوگل استفاده می‌کنند — برای اعطای دسترسی‌های جدید، گوگل را دوباره متصل کنید.',
  'Email always works. Webhook, Slack, Telegram and Bale (بله) require a Pro license.':
    'ایمیل همیشه فعال است. وب‌هوک، اسلک، تلگرام و بله نیازمند لایسنس Pro هستند.',
  'Alert email': 'ایمیل هشدار',
  'Slack incoming webhook': 'وب‌هوک ورودی اسلک',
  'Telegram chat ID': 'شناسهٔ چت تلگرام',
  'White label': 'برند اختصاصی (White label)',
  'Agency (client mode)': 'آژانس (حالت کلاینت)',
  'Enterprise': 'سازمانی (Enterprise)',
  'Search Console Property': 'پراپرتی سرچ‌کنسول',
  'Analytics 4 Property': 'پراپرتی آنالیتیکس ۴',
  'PageSpeed Insights': 'پیج‌اسپید اینسایتس',
  'AI — Anthropic Claude': 'هوش مصنوعی — Claude',
  'AI — OpenAI': 'هوش مصنوعی — OpenAI',
  'AI — Google Gemini': 'هوش مصنوعی — Gemini',
  'AI — GapGPT (گپ‌جی‌پی‌تی)': 'هوش مصنوعی — گپ‌جی‌پی‌تی (GapGPT)',
  'Could not load connection state': 'وضعیت اتصال‌ها بارگذاری نشد',
  'Active': 'فعال',
  'Resolved': 'رفع‌شده',
  'Failed to load': 'بارگذاری ناموفق بود',
  'No active alerts': 'هشدار فعالی نیست',
  'No resolved alerts': 'هشدار رفع‌شده‌ای نیست',
  'All monitored conditions are healthy.': 'همهٔ شرایط پایش‌شده سالم هستند.',
  'Ack': 'تأیید',
};

/** True when the wp-admin locale is Persian (fa_IR, fa_AF, …). */
export function isFa(): boolean {
  try {
    return boot().locale.toLowerCase().startsWith('fa');
  } catch {
    return false;
  }
}

/** Translate an English source string; falls back to the input untouched. */
export function t(source: string): string {
  return isFa() ? (FA[source] ?? source) : source;
}
