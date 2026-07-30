---
name: rtl-plugin-standards
description: |
  استانداردهای اجباری این مخزن برای هر پلاگین وردپرس: راست‌چین (RTL) و فارسی‌محور بودن،
  و درج «رضا آسیابی» به‌عنوان سازنده در تمام فیلدهای متادیتا (Author هدر پلاگین، readme.txt،
  composer.json، package.json، داک‌بلاک‌ها، صفحه «درباره»).
  Mandatory conventions for every WordPress plugin in this repo — RTL/Persian-first UI and
  "رضا آسیابی (Reza Asiabi)" as the author/creator in all metadata.
  Use this skill whenever building a new plugin, scaffolding a plugin main file or header block,
  updating or refactoring an existing plugin (nobatyar-booking, signteb-ai-chat, signteb-web-chat,
  or any new one), writing or editing plugin CSS/templates/admin screens/settings pages/readme.txt,
  adding user-facing strings, or preparing a plugin for release or the marketplace — even if the
  user does not say "RTL", "راست‌چین", or "author" out loud. Assume it applies by default.
---

# استاندارد پلاگین‌های راست‌چین — سازنده: رضا آسیابی

دو قانون این مخزن که هیچ‌وقت با «فعلاً بعداً درستش می‌کنیم» کنار گذاشته نمی‌شوند:

1. **راست‌چین و فارسی، از اولین خط کد.** مخاطب این پلاگین‌ها کسب‌وکار ایرانی است؛ RTL یک
   تم یا patch پایانی نیست، جهت پیش‌فرض طراحی است.
2. **سازنده = رضا آسیابی.** نام برند (نوبتیار، SignTeb، …) در `Plugin Name` و `Plugin URI`
   می‌ماند، اما فیلد سازنده/نویسنده در همه‌جا «رضا آسیابی» است.

هر دو قانون برای پلاگین **جدید** و **به‌روزرسانی** پلاگین موجود یکسان اعمال می‌شود. وقتی روی
فایلی کار می‌کنی که این قانون را نقض کرده (مثلاً `Author: SignTeb`)، همان‌جا اصلاحش کن — نه
به‌عنوان یک تسک جدا.

## ۱. اعتبار سازنده (Authorship)

جاهایی که باید نام سازنده درج شود، به‌ترتیب اهمیت:

| محل | مقدار |
|---|---|
| هدر فایل اصلی پلاگین | `Author: رضا آسیابی (Reza Asiabi)` |
| `Author URI` | دامنه برند همان پلاگین (تغییرش نده اگر از قبل درست است) |
| `readme.txt` | `Contributors: rezaasiabi` + خط «سازنده: رضا آسیابی» در توضیحات |
| `composer.json` / `package.json` | `"authors": [{"name": "Reza Asiabi", "email": "reza.asi1982@gmail.com"}]` |
| داک‌بلاک کلاس‌های اصلی | `@author رضا آسیابی` |
| صفحه «درباره»/فوتر تنظیمات | «طراحی و توسعه: رضا آسیابی» |

قالب هدر استاندارد — فارسی برای انسان، انگلیسی برای فیلدهای فنی:

```php
<?php
/**
 * Plugin Name:       نوبتیار (Nobatyar)
 * Plugin URI:        https://mynobatyar.ir
 * Description:       توضیح یک‌خطی فارسی از کاری که پلاگین برای صاحب کسب‌وکار انجام می‌دهد.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            رضا آسیابی (Reza Asiabi)
 * Author URI:        https://mynobatyar.ir
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nobatyar-booking
 * Domain Path:       /languages
 */

if (! defined('ABSPATH')) {
    exit;
}
```

نام لاتین در پرانتز می‌ماند چون بعضی ابزارهای وردپرس و مارکت‌پلیس‌ها با فیلد تمام‌فارسی
بدرفتاری می‌کنند؛ حذفش نکن.

## ۲. راست‌چینی که واقعاً کار می‌کند

**دامنه‌ی جهت را محدود کن، نه سراسری.** پلاگین مهمان سایت کاربر است؛ `body { direction: rtl }`
یا `!important` روی استایل قالب، سایت میزبان را خراب می‌کند. جهت را روی ریشه‌ی خود پلاگین بگذار
و بقیه چیزها ارث ببرند — دقیقاً الگویی که `nobatyar-booking/assets/css/booking-form.css` دارد:

```css
.nby-wrap {
    direction: rtl;
    text-align: right;
    font-family: Vazirmatn, "Vazir", Tahoma, system-ui, sans-serif;
    box-sizing: border-box;
}
.nby-wrap *, .nby-wrap *::before, .nby-wrap *::after { box-sizing: inherit; }
```

قواعد عملی:

- **از property های منطقی استفاده کن**: `margin-inline-start`، `padding-inline-end`،
  `inset-inline-start`، `border-start-start-radius`. اینها خودشان با جهت می‌چرخند، پس نیازی به
  نوشتن دو نسخه‌ی LTR/RTL نیست. `margin-left/right` فقط جایی که واقعاً فیزیکی است (مثل آیکون
  ثابت) مجاز است.
- **جزیره‌های LTR را جدا کن**: شماره تلفن، ایمیل، URL، شورت‌کد، کد رهگیری، مبلغ با واحد لاتین →
  `direction: ltr; text-align: left;` روی همان عنصر (نمونه: خطوط ۱۸۴ و ۳۵۵ همان فایل CSS).
  متن فارسی با یک شماره‌ی چسبیده به آن بدون این کار به‌هم می‌ریزد.
- **فونت را باندل کن، از CDN نگیر.** Google Fonts و CDN های مشابه از ایران قابل‌اتکا نیستند.
  الگوی `signteb-web-chat/assets/fonts/font.css` را نگاه کن: فونت self-hosted فقط وقتی enqueue
  می‌شود که فایلش موجود باشد، وگرنه fallback به استک سیستمی
  `Vazirmatn, "Vazir", Tahoma, system-ui, sans-serif`.
- **برای صفحات ادمین**، وردپرس خودش با `is_rtl()` کلاس `rtl` روی `<body>` می‌گذارد؛ استایل
  ادمینت را با آن هماهنگ کن و در صورت داشتن نسخه‌ی RTL جدا از
  `wp_style_add_data($handle, 'rtl', 'replace')` استفاده کن تا وردپرس فایل `-rtl.css` را
  خودش انتخاب کند.
- **لود شرطی asset ها** (قانون موجود پروژه در `CLAUDE.md`): CSS/JS فقط در صفحه‌ای که شورت‌کد
  یا ویجت حاضر است enqueue شود.

## ۳. زبان رابط کاربری

فارسی زبان **مبدأ** است، نه ترجمه‌ی بعدی. هر رشته‌ی قابل‌مشاهده باید:

- از توابع i18n با text domain خود پلاگین رد شود:
  `esc_html_e('ذخیره تنظیمات', 'nobatyar-booking')`، `__()`، `esc_attr__()`.
- `placeholder`، `aria-label`، `title`، پیام‌های خطای REST و متن ایمیل/SMS را هم شامل شود —
  اینها همیشه جا می‌مانند.
- تاریخ‌ها در لایه‌ی نمایش جلالی باشند (منبع حقیقت در دیتابیس میلادی می‌ماند؛ `JalaliConverter`).
- `.pot` بعد از افزودن رشته‌های جدید در `languages/` به‌روز شود.

اصطلاحات دامنه (Provider/Booking/…) از `TerminologyMap` بیاید تا کاربر بتواند «سرویس‌دهنده» را
به «آرایشگر» یا «مشاور» تغییر دهد — متن هاردکد نزن.

## ۴. بررسی پایانی

بعد از هر تغییر، اسکریپت بررسی را اجرا کن:

```bash
bash .claude/skills/rtl-plugin-standards/scripts/check_rtl_credits.sh
```

خروجی، هر پلاگین را با سه معیار بررسی می‌کند: درج «رضا آسیابی» در هدر، وجود `direction: rtl`
در CSS، و نبود فونت CDN. `FAIL` را قبل از commit برطرف کن.

اگر اسکریپت چیزی را می‌گیرد که در آن مورد خاص عمداً استثناست (مثلاً یک پلاگین کاملاً بدون UI)،
در پیام commit یا پاسخ به کاربر توضیح بده — استثنا را ساکت رد نکن.

## ضدالگوها

| نکن | چرا |
|---|---|
| `body { direction: rtl; }` یا `* { text-align: right }` | قالب و بقیه پلاگین‌های سایت کاربر را می‌شکند |
| `margin-left: 12px` برای فاصله‌ی منطقی | در RTL معکوس دیده می‌شود؛ `margin-inline-start` بگذار |
| `@import` فونت از Google Fonts | از ایران لود نمی‌شود، صفحه با فونت شکسته می‌ماند |
| `Author: Nobatyar` / `Author: SignTeb` | نام برند است نه سازنده؛ سازنده «رضا آسیابی» است |
| متن انگلیسی در UI («Save», «Loading…») | کاربر نهایی فارسی‌زبان است |
| راست‌چین‌کردن چیدمان یا متن با `transform: scaleX(-1)` | متن را آینه‌ای و ناخوانا می‌کند (آینه‌کردن یک آیکون جهت‌دار مثل فلش ارسال اشکالی ندارد) |
