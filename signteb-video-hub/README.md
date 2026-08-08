# SignTeb Video Hub — مرکز ویدئوی پزشکی

افزونه‌ی اختصاصی وردپرس برای مدیریت، بهینه‌سازی و نمایش ویدئوهای پزشکی. کانال آپارات (یا یوتیوب) را وصل می‌کنید، افزونه ویدئوها را خودکار می‌گیرد، با هوش مصنوعی خلاصه و سوالات متداول و لینک داخلی می‌سازد، اسکیمای ویدئویی و نقشه سایت ویدئو تولید می‌کند و همه را در یک رابط شیشه‌ای RTL نمایش می‌دهد.

بخشی از اکوسیستم **SIGNTEB MEDCORE** — مستقل کار می‌کند و به هیچ افزونه‌ی دیگری وابسته نیست.

---

## ویژگی‌ها

| # | قابلیت | توضیح |
|---|---|---|
| ۱ | اتصال به آپارات | فقط شناسه کانال (`mychannel`) کافی است؛ عنوان، تصویر، مدت، تاریخ و کد نمایش خودکار دریافت می‌شود |
| ۲ | همگام‌سازی خودکار | هر ۶ / ۱۲ / ۲۴ ساعت، با تشخیص تغییر (ویدئوی بدون تغییر دوباره نوشته نمی‌شود) |
| ۳ | خلاصه هوشمند | خلاصه، نکات مهم و سوالات متداول با یک درخواست به مدل — سرویس‌دهنده: GapGPT (قابل استفاده از ایران)، Anthropic، Google Gemini، یا هر درگاه سازگار با OpenAI |
| ۴ | لینک‌سازی داخلی | مدل فقط از فهرست آدرس‌های واقعی سایت انتخاب می‌کند — لینک ساختگی از نظر ساختاری غیرممکن است |
| ۵ | تولید اسکیما | `VideoObject`, `MedicalWebPage`, `ItemList`, `FAQPage`, `BreadcrumbList`, `Physician` در یک `@graph` |
| ۶ | ویجت المنتور | «ویدئوهای ساین‌طب» و «مرکز هوشمند پزشکی» به‌صورت Drag & Drop |
| ۷ | فیلتر Ajax | فیلتر موضوعی بدون رفرش صفحه |
| ۸ | جستجوی زنده | جستجو با تأخیر ۳۰۰ میلی‌ثانیه، بدون jQuery |
| ۹ | پیشنهاد مقاله | پیش‌نویس مقاله ~۸۰۰ کلمه‌ای از روی هر ویدئو (همیشه پیش‌نویس، هرگز منتشرشده) |
| ۱۰ | سازگاری | RankMath، Yoast، SEOPress، LiteSpeed، WP Rocket، W3TC، WP Super Cache، Autoptimize، Cloudflare |
| ۱۱ | آمار | نمایش، کلیک، CTR، پخش، مدت تماشا — بدون ذخیره IP یا شناسه کاربر |
| ۱۲ | نقشه سایت ویدئو | `/video-sitemap.xml` زنده، معرفی‌شده در `robots.txt` |
| ۱۳ | Google Indexing API | احراز هویت JWT با سرویس‌اکانت، صف‌بندی برای رعایت سهمیه روزانه |
| ۱۴ | شبکه‌های اجتماعی | OpenGraph + Twitter Player Card (تلگرام و واتس‌اپ هم از OG می‌خوانند) |
| ۱۵ | طراحی | Apple Style، Glassmorphism، Dark Mode، RTL کامل، احترام به `prefers-reduced-motion` |
| ۱۶ | داشبورد | تعداد ویدئو، آخرین همگام‌سازی، وضعیت API، کش، آخرین خطاها، وضعیت گوگل |
| ۱۷ | صفحات اختصاصی | `/videos/` و `/videos/{موضوع}/` — موضوعات پیش‌فرض روی فعال‌سازی ساخته می‌شوند |
| ۱۸ | مرکز هوشمند پزشکی | مقاله، ویدئو، سوال، پزشک، خدمات و رزرو نوبت مرتبط در انتهای هر صفحه |

---

## نصب

1. پوشه‌ی `signteb-video-hub` را در `wp-content/plugins/` کپی کنید.
2. افزونه را **فعال** کنید — جدول‌ها، موضوعات پیش‌فرض و زمان‌بندی cron خودکار ساخته می‌شوند.
3. به **ویدئو هاب ← تنظیمات** بروید و شناسه کانال آپارات را وارد کنید.
4. دکمه‌ی **تست اتصال** را بزنید، سپس در داشبورد **همگام‌سازی دستی** را اجرا کنید.
5. (اختیاری) کلید API هوش مصنوعی را وارد و «تولید محتوای هوشمند» را فعال کنید.

نیازمندی‌ها: WordPress 5.8+، PHP 8.1+.

---

## شورت‌کدها

```
[signteb_video_gallery]                             آرشیو کامل — همه ویدئوها با کادر لوکس متحرک
[signteb_videos]                                    فهرست با فیلتر و جستجو
[signteb_videos layout="carousel" per_page="8"]     کاروسل
[signteb_videos topic="liver" show_search="no"]     فقط یک موضوع
[signteb_videos orderby="popular"]                  محبوب‌ترین (بر اساس پخش ۳۰ روز)
[signteb_video id="123"]                            فقط پخش‌کننده یک ویدئو
[signteb_medical_hub]                               بلوک مرکز هوشمند پزشکی
```

`layout`: `grid` | `list` | `carousel` | `slider`
`orderby`: `date` | `popular` | `title` | `duration` | `random`
`style`: خالی (ساده) | `luxe` (حلقه‌ی رنگی متحرک دور کارت — پیش‌فرض گالری)

---

## معماری

```
signteb-video-hub/
├── includes/
│   ├── Core/        Plugin, Activator, Settings, Encryption, PostType, VideoMeta, Logger
│   ├── Api/         VideoSourceInterface + Aparat/ + Youtube/ + SourceManager
│   ├── Sync/        SyncManager (upsert با تشخیص تغییر)
│   ├── Ai/          AiProviderInterface + Providers/{GapGpt,Anthropic,OpenAi} + generators
│   ├── Cron/        Scheduler (۶/۱۲/۲۴ ساعت) + AiWorker (صف)
│   ├── Cache/       CacheManager (کش داخلی + پاک‌سازی کش صفحه)
│   ├── Schema/      SchemaGenerator + SeoCompat
│   ├── Seo/         VideoSitemap, SocialMeta, IndexingClient, IndexingQueue
│   ├── Db/          Schema + Video/Analytics/SyncLog/AiQueue repositories
│   ├── Front/       Assets, Renderer, Shortcodes, MedicalHub, TemplateLoader
│   ├── Rest/        Controllers/{Video, Analytics, Admin}
│   ├── Elementor/   ElementorBridge
│   ├── Widgets/     VideoGridWidget, MedicalHubWidget
│   ├── Admin/       AdminMenu, Dashboard/Settings/Analytics pages, VideoMetaBox
│   └── Helpers/     Format, Json
├── assets/{css,js}/
├── templates/
├── tests/
└── uninstall.php
```

**اصول:**

- هر دامنه interface-driven است — افزودن منبع ویدئو یا سرویس‌دهنده‌ی هوش مصنوعی جدید فقط یک کلاس می‌خواهد.
- **بارگذاری شرطی assets**: CSS/JS فقط در صفحاتی که واقعاً کامپوننت ویدئو دارند لود می‌شود.
- **پخش‌کننده‌ی facade**: iframe منبع فقط بعد از کلیک کاربر تزریق می‌شود.
- کلیدهای API با AES-256-CBC (کلید مشتق‌شده از salt های وردپرس) رمزنگاری می‌شوند.
- حذف افزونه جدول‌ها و تنظیمات را پاک می‌کند اما **ویدئوهای وارد‌شده را نگه می‌دارد**.

---

## داده‌ها

**نوع نوشته:** `stvh_video` — تک‌ویدئو در `/video/{slug}/`، آرشیو در `/videos/`
**دسته‌بندی:** `stvh_topic` — موضوعات در `/videos/{topic}/`

**جدول‌ها:** `{prefix}stvh_analytics`, `{prefix}stvh_sync_log`, `{prefix}stvh_ai_queue`

---

## REST API

پیشوند: `signteb-video/v1`

| متد | مسیر | دسترسی |
|---|---|---|
| GET | `/videos` | عمومی (فیلتر و جستجو) |
| POST | `/track` | عمومی (throttle شده) |
| POST | `/sync` | `manage_options` |
| POST | `/test-connection` | `manage_options` |
| POST | `/ai/generate`، `/ai/queue`، `/ai/article` | `manage_options` |
| POST | `/cache/purge` | `manage_options` |
| POST | `/indexing/ping` | `manage_options` |

---

## هوک‌ها

**اکشن:** `stvh_video_imported`, `stvh_video_updated`, `stvh_ai_summary_generated`, `stvh_ai_links_generated`, `stvh_ai_article_generated`, `stvh_cache_purged`, `stvh_settings_saved`

**فیلتر:** `stvh_video_sources`, `stvh_ai_provider`, `stvh_ai_system_prompt`, `stvh_internal_link_candidates`, `stvh_topic_keywords`, `stvh_schema_graph`, `stvh_social_meta_tags`, `stvh_load_assets`, `stvh_auto_single_layout`, `stvh_seo_plugin_owns_page_schema`, `stvh_seo_plugin_owns_social_meta`

---

## تست

```bash
php tests/smoke-test.php
```

تست‌های بدون وابستگی روی لایه‌ی منطق خالص: تبدیل مدت زمان بین سه فرمت منبع، استخراج JSON از پاسخ پرحرف مدل، و هش تشخیص تغییر در همگام‌سازی.

---

## نکات پزشکی

محتوای تولیدشده توسط هوش مصنوعی **همیشه** باید پیش از انتشار توسط پزشک بازبینی شود. System prompt به‌صورت پیش‌فرض تشخیص قطعی، دوز دارو و ادعای درمان را ممنوع می‌کند، اما این جایگزین بازبینی انسانی نیست. پیشنهاد مقاله همیشه به‌صورت **پیش‌نویس** ذخیره می‌شود و هرگز خودکار منتشر نمی‌شود.
