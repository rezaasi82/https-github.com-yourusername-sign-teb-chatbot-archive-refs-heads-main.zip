# Medora AI — خلاصه‌ی پروژه برای کلود

> این فایل را در ابتدای هر چت جدید با کلود paste کنید تا کامل در جریان پروژه باشد.

## پلاگین چیست؟

**Medora AI** (اسلاگ: `signteb-web-chat`، نسخه فعلی: **3.15.2**) — افزونه‌ی وردپرس مستقل و تجاری «دستیار هوشمند جذب و راهنمایی بیماران» برای پزشکان و کلینیک‌ها. ویجت چت هوش مصنوعی + CRM لید + پیامک/پیام‌رسان + داشبورد SaaS. توسعه‌دهنده: رضا آسیابی — signteb.com.

## معماری و قراردادهای کد (تغییر نده!)

- پیشوند همه‌ی کلاس‌ها/آپشن‌ها/جدول‌ها: `SWC_` / `swc_` — اسلاگ پوشه `signteb-web-chat` (برندینگ فقط در متن‌های نمایشی «Medora AI» است؛ هرگز rename نشود چون نصب‌های موجود می‌شکنند)
- Autoloader نگاشت صریح کلاس→فایل در `includes/class-autoloader.php` (هر کلاس جدید باید آنجا ثبت شود)
- یک کلاس = یک فایل `class-*.php`؛ بدون Composer؛ بدون jQuery در فرانت (ویجت vanilla JS)
- امنیت: nonce + capability در همه‌ی AJAX/REST، prepared SQL، کلیدها با `SWC_Encryption` (AES-256-GCM) رمزنگاری می‌شوند، rate-limit و قفل brute-force (`SWC_Security`)، لاگ امنیتی (`SWC_Audit_Log`)
- لود مشروط assetها؛ hook منوی والد `swc-chat` باید قبل از زیرمنوها ثبت شود (priority 20 برای زیرمنوها)
- ترتیب ارسال به گیت‌وی‌ها: `wp_remote_*` با timeout 22s
- نسخه در دو جا: هدر پلاگین + `SWC_VERSION`

## دامنه‌های اصلی (پوشه includes/)

| دامنه | کلاس‌های کلیدی |
|---|---|
| AI | `SWC_AI_Manager`, `SWC_Provider_Factory`, providers: Anthropic / OpenAI / GapGPT + لایه ایمنی پزشکی غیرقابل‌حذف |
| فرانت | `SWC_Widget` (حباب شناور + حالت inline)، `SWC_Chat_Shortcode` = `[medora_chat]` برای سایدبار |
| دیتابیس | `SWC_Schema` (جدول‌های swc_conversations/messages/events/sync_logs/analytics/jobs/branches/audit_logs)، Repositoryها |
| CRM | `SWC_Lead_CRM` (پایپ‌لاین ۷مرحله‌ای)، بورد کانبان `SWC_Crm_Board`، ارجاع لید (ایمیل wp_mail + پیامک پنل + دیپ‌لینک پیام‌رسان‌ها) |
| پیامک | `SWC_Sms_Manager` + پنل‌ها: کاوه‌نگار، ملی‌پیامک (دو حالته: user/pass قدیمی یا توکن کنسول)، SMS.ir، قاصدک، Custom برای خارجی — ۴ قالب قابل‌ویرایش + «کد الگو» برای خط خدماتی اشتراکی + متن لغو {optout} + شماره همکاران |
| پیام‌رسان | `SWC_Messenger_Notifier` — اعلان خودکار لید به گروه بله/تلگرام (Bot API) |
| اعلان ادمین | `SWC_Chat_Notifier` — حباب نوار ادمین (Heartbeat زنده) + نوتیس پیشخوان + بج منو |
| خروجی | PDF / Webhook امضاشده / Google Sheets (Apps Script) |
| SaaS | داشبورد پریمیوم (درآمد/قیف/کانبان)، چندشعبه‌ای، مرکز سئو (تحلیل گفتگو→ایده محتوا)، rollup آمار + صف پس‌زمینه |
| لایسنس | `SWC_License_Manager` (state: active/grace/locked/trial، امضای HMAC، fail-open) + `SWC_Updater` (آپدیت خودکار) + سرور Node مستقل در `medora-cloud/` |

## ویجت — رفتارهای مهم

- Teaser (پیام دعوت): خودکار **فقط یک‌بار برای هر بازدیدکننده** (localStorage `swc_teaser_shown`) + با هاور روی آیکون؛ عرض ثابت 232px؛ صدای چایم دونتی WebAudio (پس از اولین تعامل کاربر — سیاست autoplay مرورگرها)
- فرم لید (نام+موبایل) دومرحله‌ای، امتیازدهی hot/warm/cold، خلاصه خودکار، کارت CTA رزرو + کانال‌ها (واتساپ/تماس/بله)
- ترنسپورت: REST (`signteb-web-chat/v1/message`) با فالبک admin-ajax
- کیبورد موبایل با visualViewport هندل شده؛ قانون طلایی CSS: هر عنصر با `[hidden]` باید `display:none !important` داشته باشد (سه بار این باگ را داشتیم)

## گردش کار توسعه

- برنچ: `claude/signteb-web-chat-plugin-3e4a9w`
- بعد از هر تغییر: lint همه‌ی PHP (`php -l`) و JS (`node --check`)، تست روی وردپرس واقعی (SQLite + Playwright)، bump نسخه، commit+push، ساخت `signteb-web-chat.zip` و تحویل
- کد نباید نشانه‌ای از ساخت با AI داشته باشد؛ کامنت‌ها انگلیسیِ حرفه‌ای، متن‌های UI فارسی با text-domain `signteb-web-chat`

## وضعیت فعلی

همه‌ی فازهای نقشه راه (P0–P8) + ده‌ها فیچر تکمیلی انجام شده. آخرین کارها: پشتیبانی خط خدماتی اشتراکی (بدون وابستگی به شماره خط)، دومَده شدن ملی‌پیامک، فیکس عرض حباب تیزر.
