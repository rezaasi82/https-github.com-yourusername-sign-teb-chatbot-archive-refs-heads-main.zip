# AUDIT-LOG.md — نصب‌کننده آسان + قالب/افزونه‌های همراه (SignTeb MedCore Stack)

بررسی و سخت‌سازی کامل بسته `easy-installer` شامل نصب‌کننده خودکار وردپرس،
افزونه‌های `signteb-medical-core`، `signteb-blocks`، `signteb-wizard`، و قالب
`signteb-medcore`. این ممیزی توسط ۴ بررسی مستقل (نصب‌کننده به‌صورت دستی، سه
مؤلفه دیگر به‌صورت خودکار) طبق چک‌لیست WPCS + OWASP + سازگاری PHP 8.x انجام شد.

## خلاصه کلی

| مؤلفه | فایل‌های بررسی‌شده | فایل‌های اصلاح‌شده | Critical | High | Medium | Low | جمع یافته‌ها |
|---|---|---|---|---|---|---|---|
| easy-installer (نصب‌کننده) | ۲۰ | ۹ | ۲ | ۲ | ۳ | ۰ | ۷ |
| signteb-medical-core (پلاگین اصلی) | ۳۶ | ۴ | ۱ | ۱ | ۱ | ۲ | ۵ |
| signteb-blocks + signteb-wizard | ۴۹ | ۱۰ | ۲ | ۱ | ۳ | ۵ | ۱۱ |
| signteb-medcore (قالب) | ۲۳ | ۱۱ | ۱ | ۱ | ۵ | ۲ | ۹ |
| **جمع کل** | **۱۲۸** | **۳۴** | **۶** | **۵** | **۱۲** | **۹** | **۳۲** |

همه فایل‌های PHP ویرایش‌شده در تمام چهار مؤلفه با `php -l` (PHP 8.4.19) قبل و
بعد از اصلاح، بدون هیچ خطای syntax تأیید شدند.

## مهم‌ترین یافته‌های Critical (نیاز به توجه فوری در صورت استفاده از نسخه قبلی)

1. **`easy-installer/installer/bootstrap.php`** — پارامتر `?force=1` قفل امنیتی پس از پایان نصب را بدون هیچ احراز هویتی دور می‌زد.
2. **`easy-installer/installer/includes/class-wp-installer.php`** — رمز/نام دیتابیس بدون escape در `wp-config.php` جایگذاری می‌شد؛ می‌توانست منجر به کد PHP خراب/تزریق‌شده شود.
3. **`signteb-medical-core/includes/class-stmc-plugin.php`** — فراخوانی کلاس‌های ناموجود (`Meta\Service`, `Meta\Clinic`, `Meta\Disease`) باعث Fatal Error در **هر بار بارگذاری هر صفحه پیشخوان وردپرس** می‌شد — یعنی پلاگین در عمل کل wp-admin را خراب می‌کرد.
4. **`signteb-blocks/blocks/doctor-hero/render.php`** — تابع سراسری بدون گارد `function_exists`، باعث Fatal «Cannot redeclare function» با هر ≥۲ نمونه از این بلاک در یک صفحه (white-screen واقعی در production).
5. **`signteb-blocks/blocks/faq-accordion/render.php`** (و مشابه در `signteb-medical-core/includes/seo/class-stmc-seo-schema.php`) — الگوی «JSON-in-script-tag breakout»: `JSON_UNESCAPED_SLASHES` در JSON-LD چاپ‌شده داخل `<script>` امکان Stored XSS از طریق عنوان/محتوای قابل‌ویرایش را فراهم می‌کرد.
6. **`signteb-medcore` (قالب)** — تگ‌های `<?php ?>` در فایل‌های FSE (`templates/*.html`, `parts/*.html`) هرگز اجرا نمی‌شدند (این فایل‌ها توسط وردپرس به‌صورت متن خام پردازش می‌شوند، نه PHP) — نتیجه: کپی‌رایت فوتر، دکمه شناور واتساپ، و breadcrumbs در قالب اصلی سایت خراب/غیرفعال بودند.

---

# بخش ۱ — easy-installer/ (نصب‌کننده)

# خلاصه: easy-installer/ — فایل‌های بررسی‌شده: ۲۰ (PHP/JS/CSS، به‌جز zip های bundle شده) | فایل‌های اصلاح‌شده: ۹ | یافته‌ها: ۲ Critical، ۲ High، ۳ Medium، ۰ Low

## installer/bootstrap.php — خط ۲۲
- ❌ مشکل: قفل امنیتی پس از پایان نصب (`.installed`) با پارامتر `?force=1` در URL به‌طور کامل و بدون هیچ احراز هویتی قابل دور زدن بود.
- 🔍 دلیل: پارامتر `force` برای دیباگ داخلی در کد باقی مانده و مستند هم نشده بود؛ اما چون هیچ رمز/توکنی نمی‌خواست، هر بازدیدکننده‌ای با اضافه‌کردن `?force=1` می‌توانست نصب‌کننده را حتی بعد از قفل‌شدن دوباره اجرا کند (بازنویسی افزونه/قالب، فعال‌سازی مجدد، و در برخی شرایط حتی تلاش مجدد wp_install).
- ✅ اصلاح: شرط bypass کاملاً حذف شد؛ اکنون وجود فایل `.installed` بدون استثنا صفحه قفل را نمایش می‌دهد.
- ⚠️ ریسک قبل از اصلاح: Critical

## installer/bootstrap.php — خط ۱۰
- ❌ مشکل: `session_start()` بدون تنظیم پارامترهای امن کوکی صدا زده می‌شد، در حالی‌که این جلسه رمز عبور خام دیتابیس را در `$_SESSION['ezi_db']` نگه می‌دارد.
- 🔍 دلیل: تنظیمات پیش‌فرض PHP برای httponly/secure/samesite کوکی جلسه در همه هاست‌ها یکسان یا امن نیست.
- ✅ اصلاح: `session_set_cookie_params()` با `httponly: true`, `samesite: Lax`, و `secure` (بر اساس HTTPS) قبل از `session_start()` اضافه شد.
- ⚠️ ریسک قبل از اصلاح: Medium

## installer/includes/class-wp-installer.php — خط ۴۱-۴۹
- ❌ مشکل: مقادیر دیتابیس (نام، یوزرنیم، پسورد، هاست) بدون escape داخل رشته‌های تک‌کوتیشن `wp-config-sample.php` جایگذاری می‌شدند. اگر رمز عبور/نام دیتابیس شامل کاراکتر `'` یا `\` باشد (که در MySQL کاملاً مجاز است)، رشته PHP می‌شکند و باقی مقدار به‌صورت کد PHP خام در wp-config.php تزریق می‌شود.
- 🔍 دلیل: استفاده مستقیم از `strtr()` بدون escape، برخلاف خود هسته وردپرس که در `wp-admin/setup-config.php` از `addcslashes( $value, "\\'" )` برای همین منظور استفاده می‌کند.
- ✅ اصلاح: یک closure escape (`addcslashes` با `\\'`) قبل از جایگذاری هر مقدار در `$replacements` اضافه شد.
- ⚠️ ریسک قبل از اصلاح: Critical

## installer/includes/class-wp-installer.php — خط ۱۱۱ (و installer/steps/step-finish.php)
- ❌ مشکل: مقدار `siteurl`/`home` و لینک‌های صفحه پایان نصب مستقیماً از `$_SERVER['HTTP_HOST']` (که کاملاً توسط کلاینت قابل جعل است — Host Header Injection) ساخته می‌شدند، بدون هیچ اعتبارسنجی فرمت.
- 🔍 دلیل: اعتماد به هدر HTTP Host به‌جای مقداری که خود وب‌سرور تعیین می‌کند.
- ✅ اصلاح: تابع کمکی جدید `ezi_safe_host()` در helpers.php اضافه شد که Host را با یک الگوی نام‌میزبان معتبر (`[a-zA-Z0-9.\-]+(:\d+)?`) بررسی می‌کند و در صورت نامعتبر بودن، به `$_SERVER['SERVER_NAME']` بازمی‌گردد. هر دو محل استفاده به این تابع تغییر کردند.
- ⚠️ ریسک قبل از اصلاح: High

## diagnose.php — بعد از خط ۴۴ (بارگذاری موفق وردپرس)
- ❌ مشکل: این ابزار تشخیص خطا (که طبق مستندات فقط باید موقتاً روی سرور باشد) هیچ بررسی احراز هویت/دسترسی نداشت — هر بازدیدکننده‌ای می‌توانست لیست افزونه‌های فعال، مسیرهای کامل فایل روی سرور، و ۴۰ خط آخر `debug.log` (که می‌تواند اطلاعات حساس داشته باشد) را ببیند.
- 🔍 دلیل: فرض شده بود کاربر بلافاصله بعد از استفاده فایل را حذف می‌کند — در عمل این فایل‌ها اغلب فراموش و روی سرور باقی می‌مانند (یک الگوی شناخته‌شده در نشت اطلاعات وردپرسی).
- ✅ اصلاح: بعد از بارگذاری موفق وردپرس، بررسی `is_user_logged_in() && current_user_can( 'manage_options' )` اضافه شد؛ در غیر این صورت پیام «دسترسی غیرمجاز» نمایش داده و اجرا متوقف می‌شود.
- ⚠️ ریسک قبل از اصلاح: High

## installer/includes/.htaccess, installer/steps/.htaccess, installer/cli/.htaccess, installer/package/.htaccess
- ❌ مشکل: این چهار فایل فقط از سینتکس قدیمی Apache 2.2 (`Order Allow,Deny` / `Deny from all`) استفاده می‌کردند، در حالی‌که دو فایل دیگر همین بسته (`.htaccess` روت و `installer/.htaccess`) به‌درستی از الگوی دوگانه (`<IfModule mod_authz_core.c>Require all denied</IfModule>` + fallback قدیمی) استفاده می‌کنند. روی Apache 2.4 بدون ماژول mod_access_compat، این دایرکتیوها می‌توانند نادیده گرفته شوند یا خطای 500 ایجاد کنند — یعنی محافظت این پوشه‌های حساس (شامل state.json و اسکریپت‌های CLI) غیرقابل‌اتکا بود.
- 🔍 دلیل: عدم یکدستی بین فایل‌های .htaccess نوشته‌شده در زمان‌های مختلف.
- ✅ اصلاح: هر چهار فایل با همان الگوی دوگانه‌ی استفاده‌شده در بقیه بسته یکدست شدند.
- ⚠️ ریسک قبل از اصلاح: Medium

## installer/package/.htaccess — الگوی FilesMatch
- ❌ مشکل: این فایل فقط دسترسی مستقیم به `*.php` را مسدود می‌کرد؛ فایل‌های واقعی بسته (`theme.zip`, `plugin-1.zip`, `plugin-2.zip`, `plugin-3.zip`) از طریق URL مستقیم قابل دانلود بودند تا زمانی که مرحله «پاکسازی نهایی» آن‌ها را حذف کند.
- 🔍 دلیل: الگوی FilesMatch پسوند zip را شامل نمی‌شد.
- ✅ اصلاح: الگو به `\.(php|zip)$` تغییر کرد. این تغییر عملکرد نصب‌کننده را مختل نمی‌کند چون `ZipArchive`/PHP این فایل‌ها را مستقیماً از دیسک می‌خواند، نه از طریق HTTP.
- ⚠️ ریسک قبل از اصلاح: Medium

---

# بخش ۲ — signteb-medical-core (پلاگین اصلی)

# SignTeb Medical Core — Security & QA Audit Log

**خلاصه:** ۳۶ فایل PHP بررسی شد (۰ خطای syntax در `php -l` قبل و بعد از اصلاح) — ۴ فایل اصلاح شد — ۵ یافته: Critical: 1, High: 1, Medium: 1, Low: 2.

---

## includes/class-stmc-plugin.php — 92-99 (register_meta_boxes)
- ❌ مشکل: متد `register_meta_boxes()` مستقیماً `new Meta\Service()`, `new Meta\Clinic()`, و `new Meta\Disease()` را صدا می‌زد، در حالی‌که فقط کلاس `Meta\Doctor` در پروژه پیاده‌سازی شده و فایل‌های `class-stmc-meta-service.php`, `class-stmc-meta-clinic.php`, `class-stmc-meta-disease.php` اصلاً وجود ندارند. نتیجه: `Fatal error: Uncaught Error: Class "STMC\Meta\Service" not found` در هر بار بارگذاری هر صفحه‌ی پیشخوان وردپرس (چون `is_admin()` در هر ادمین صادق است) — کل پنل مدیریت پلاگین (و حتی سایر پلاگین‌ها/صفحات ادمین وردپرس در برخی حالت‌های لود) از کار می‌افتد.
- 🔍 دلیل: این کلاس‌ها در روند تعمیم/استخراج (generalization) از موتور پزشکی قبلی حذف یا هنوز پیاده‌سازی نشده‌اند، اما فراخوانی آن‌ها در `Plugin::register_meta_boxes()` بدون بررسی وجود کلاس باقی مانده — برخلاف الگوی صحیحی که در `register_post_types()` و `register_taxonomies()` همان فایل با `class_exists()` رعایت شده بود.
- ✅ اصلاح: `register_meta_boxes()` با همان الگوی حلقه + `class_exists( 'STMC\\' . $class )` که در `register_post_types()`/`register_taxonomies()` استفاده شده بازنویسی شد؛ حالا فقط کلاس‌هایی که واقعاً وجود دارند (`Meta\Doctor`) نمونه‌سازی می‌شوند و فقدان کلاس‌های دیگر باعث Fatal Error نمی‌شود.
- ⚠️ ریسک قبل از اصلاح: Critical

---

## includes/seo/class-stmc-seo-schema.php — 525-532 (print_schema)
- ❌ مشکل: خروجی JSON-LD با `wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )` مستقیماً و بدون هیچ escaping اضافه داخل `<script type="application/ld+json">...</script>` چاپ می‌شد. چون فلگ `JSON_UNESCAPED_SLASHES` کاراکتر `/` را escape نمی‌کند، اگر هرکدام از مقادیر ورودی به schema (مثلاً `post_excerpt` دستیِ یک نویسنده، یا فیلد `content` نظرات بیماران که در `reviews_schema_for_doctor()` استفاده می‌شود) رشته‌ی تحت‌اللفظی `</script>` را داشته باشد، تگ script زودتر از موعد بسته شده و امکان تزریق HTML/JS دلخواه (Stored XSS) برای همه‌ی بازدیدکنندگان صفحه فراهم می‌شود.
- 🔍 دلیل: استفاده از `JSON_UNESCAPED_SLASHES` برای خواناتر شدن URL‌ها در خروجی JSON، بدون در نظر گرفتن این‌که این فلگ محافظت پیش‌فرض PHP در برابر شکستن تگ `</script>` را غیرفعال می‌کند؛ برخلاف escaping معمول HTML (esc_html/esc_attr) که برای این context خاص (JSON داخل script tag) کافی نیست و باید جداگانه مدیریت شود.
- ✅ اصلاح: بعد از `wp_json_encode()`، رشته‌ی خروجی با `str_replace( '</', '<\/', $json )` پردازش می‌شود تا هر رخداد `</` (از جمله `</script>`) به `<\/` تبدیل شود و امکان شکستن زودهنگام تگ script از بین برود؛ همچنین یک بررسی `false === $json` برای جلوگیری از echo کردن `false` در صورت شکست encode اضافه شد.
- ⚠️ ریسک قبل از اصلاح: High

---

## includes/meta/class-stmc-meta-doctor.php — 400-408 (save)
- ❌ مشکل: در متد `save()`، هنگام sanitize کردن فیلدهای meta پزشک، دو نوع فیلد بدون فراخوانی `wp_unslash()` روی مقدار خام `$_POST` پردازش می‌شدند: نوع `url` (`esc_url_raw( $raw )`) و نوع `checkboxes`/`post_select` (`array_map( 'sanitize_text_field', $raw )`). چون وردپرس همیشه روی `$_POST`/`$_GET`/`$_COOKIE` به‌صورت خودکار `addslashes()` اعمال می‌کند (magic-quotes سازگاری قدیمی)، عدم فراخوانی `wp_unslash()` باعث می‌شود اگر مقدار ورودی حاوی کوتیشن یا بک‌اسلش باشد، بک‌اسلش‌های اضافه در دیتابیس ذخیره شوند (خرابی داده، نه لزوماً آسیب‌پذیری امنیتی مستقیم).
- 🔍 دلیل: در نوشتن `match` expression برای انواع مختلف فیلد، الگوی صحیح `sanitize_xxx( wp_unslash( $raw ) )` که در نوع‌های `textarea` و پیش‌فرض (`default`) رعایت شده بود، برای نوع‌های `url` و `checkboxes`/`post_select` فراموش شده بود.
- ✅ اصلاح: به `esc_url_raw()` مقدار `wp_unslash( $raw )` پاس داده شد و برای `checkboxes`/`post_select`، آرایه‌ی `$raw` قبل از `array_map()` با `wp_unslash()` unslash می‌شود (`array_map( 'sanitize_text_field', wp_unslash( $raw ) )`) تا با بقیه‌ی فیلدها سازگار و صحیح باشد.
- ⚠️ ریسک قبل از اصلاح: Medium

---

## includes/meta/class-stmc-meta-doctor.php — 359 (render_schema_preview)
- ❌ مشکل: برای تولید پیش‌نمایش Schema JSON-LD در ادمین از تابع بومی PHP یعنی `json_encode()` استفاده شده بود، نه `wp_json_encode()` استاندارد وردپرس.
- 🔍 دلیل: `json_encode()` خام PHP نسبت به `wp_json_encode()` محافظت‌های اضافه‌ی وردپرس (رفع مشکلات انکودینگ UTF-8 نامعتبر، پرچم‌های سازگار با تنظیمات locale پیش‌فرض، و فیلتر `wp_json_encode`) را ندارد و طبق WPCS استفاده‌ی مستقیم از آن در کد پلاگین توصیه نمی‌شود.
- ✅ اصلاح: فراخوانی به `wp_json_encode()` تغییر یافت (خروجی همچنان با `esc_html()` چاپ می‌شود، بدون تغییر در رفتار قابل مشاهده).
- ⚠️ ریسک قبل از اصلاح: Low

---

## includes/appointment/class-stmc-appointment-form.php — 42, 62, 67, 70, 79, 82, 94, 95, 109, 112, 121, 123, 134
- ❌ مشکل: مقدار `$doctor_id` (حاصل از `absint( $atts['doctor_id'] )`) در چندین جای HTML به‌صورت `echo $doctor_id;` بدون `esc_attr()` در attribute‌های `id`/`for`/`value`/`data-form` چاپ می‌شد.
- 🔍 دلیل: چون `absint()` همیشه یک عدد صحیح غیرمنفی برمی‌گرداند، این مورد از نظر امنیتی فعلاً قابل بهره‌برداری نیست، اما برخلاف الگوی escaping خروجی مورد انتظار WPCS/OWASP («هر خروجی دینامیک در HTML باید escape شود، صرف‌نظر از این‌که منبع آن فعلاً امن به‌نظر برسد») است و در صورت تغییر منطق تولید `doctor_id` در آینده ریسک واقعی ایجاد می‌کند.
- ✅ اصلاح: همه‌ی موارد `echo $doctor_id;` در این فایل به `echo esc_attr( $doctor_id );` تغییر یافت تا escaping دفاعی و مطابق استاندارد در همه‌ی attribute contextها رعایت شود؛ رفتار قابل مشاهده (خروجی HTML) بدون تغییر باقی می‌ماند چون مقدار همواره عددی است.
- ⚠️ ریسک قبل از اصلاح: Low

---

## نکات بررسی‌شده بدون یافته‌ی قابل‌اصلاح (برای شفافیت)
- تمام فایل‌های PHP دارای گارد `defined( 'ABSPATH' ) || exit;` هستند.
- تمام هندلرهای `admin_post_*` و `wp_ajax_*` دارای بررسی nonce (`check_admin_referer`/`check_ajax_referer`/`wp_verify_nonce`) و `current_user_can()` مناسب هستند (`class-stmc-admin-appointments.php`, `class-stmc-admin-availability.php`, `class-stmc-admin-reviews.php`, `class-stmc-admin-settings.php`, `class-stmc-appointment-ajax.php`, `class-stmc-reviews-ajax.php`, `class-stmc-meta-doctor.php` save، `class-stmc-seo-topic-cluster.php` save_cluster).
- تمام کوئری‌های `$wpdb` در هر ۳۶ فایل از `$wpdb->prepare()` یا متدهای safe (`insert`/`update`/`delete`/`replace` با فرمت مشخص) استفاده می‌کنند — هیچ SQL injection مستقیمی پیدا نشد.
- `Repository::update_status()` مقدار `status` را در برابر یک allow-list محدود (`pending`/`approved`/`rejected`) اعتبارسنجی می‌کند، پس sanitize_key ناقص در لایه‌ی بالاتر (admin-reviews.php) عملاً بی‌خطر است.
- Conditional asset loading رعایت شده: `Admin\Menu::enqueue_assets()` و `Meta\Doctor::enqueue_scripts()` فقط در صفحات مرتبط (`str_contains($hook,'stmc')` یا `get_current_screen()->post_type === 'doctor'`) استایل/اسکریپت inline اضافه می‌کنند.
- هیچ استفاده‌ای از `eval()`, `extract()`, `unserialize()` روی داده‌ی غیرقابل‌اعتماد، `create_function`، یا include/require دینامیک بر اساس ورودی کاربر پیدا نشد.
- هیچ کلید/رمز API هاردکد در کد یافت نشد (نام‌کاربری/رمز SMS از `get_option()` خوانده می‌شود).

---

# بخش ۳ — signteb-blocks + signteb-wizard

# SignTeb Blocks + SignTeb Setup Wizard — گزارش Audit امنیتی/کیفیت کد

**خلاصه:** ۴۹ فایل بررسی شد (signteb-blocks: ۳۸ فایل / signteb-wizard: ۱۱ فایل) — از این تعداد ۲۱ فایل PHP بودند. ۱۰ فایل اصلاح شد (signteb-blocks: ۷ فایل / signteb-wizard: ۳ فایل). ۱۱ finding ثبت شد: **Critical: 2** (هر دو در signteb-blocks) — **High: 1** (signteb-blocks) — **Medium: 3** (۱ در signteb-blocks، ۲ در signteb-wizard) — **Low: 5** (۴ در signteb-blocks، ۱ در signteb-wizard). همه فایل‌های ویرایش‌شده با `php -l` (PHP 8.4.19) بدون خطای syntax تأیید شدند.

---

## signteb-blocks/blocks/doctor-hero/render.php — 77
- ❌ مشکل: تابع سراسری `stmb_num()` بدون گارد `function_exists` تعریف شده بود. WordPress فایل `render.php` هر block را با `require` (نه `require_once`) برای هر instance از block لود می‌کند. اگر دو Doctor Hero (برای دو پزشک متفاوت) در یک صفحه قرار بگیرند، PHP تلاش می‌کند تابع را دوباره تعریف کند.
- 🔍 دلیل: عدم آگاهی از این‌که `WP_Block_Type::render()` برای فایل‌های `render` مشخص‌شده در `block.json` از `require` (نه `require_once`) استفاده می‌کند، پس هر کد top-level از جمله تعریف تابع در هر رندر دوباره اجرا می‌شود.
- ✅ اصلاح: تعریف تابع داخل `if ( ! function_exists( 'stmb_num' ) ) { ... }` قرار گرفت.
- ⚠️ ریسک قبل از اصلاح: Critical (Fatal error: Cannot redeclare function — کل صفحه با هر تعداد ≥۲ نمونه از این block کرش می‌کند، یک white-screen واقعی در production).

## signteb-blocks/blocks/faq-accordion/render.php — 103-107
- ❌ مشکل: خروجی JSON-LD schema.org (`FAQPage`) با فلگ `JSON_UNESCAPED_SLASHES` داخل تگ `<script type="application/ld+json">` چاپ می‌شد، در حالی که مقدار `name` مستقیماً از `get_the_title()` (بدون escape) می‌آمد. اگر عنوان یک سؤال متداول (پست نوع `medical-faq`) شامل رشته‌ی `</script><script>...</script>` باشد، چون کاراکتر `/` escape نمی‌شود (`\/`)، این رشته می‌تواند تگ `<script>` را واقعاً ببندد و کد جاوااسکریپت دلخواه در صفحه تزریق شود.
- 🔍 دلیل: استفاده از `JSON_UNESCAPED_SLASHES` برای «تمیزتر» شدن URL در JSON، بدون در نظر گرفتن این‌که خروجی داخل یک تگ `<script>` HTML چاپ می‌شود — الگوی شناخته‌شده XSS از نوع «JSON-in-script-tag breakout».
- ✅ اصلاح: فلگ `JSON_UNESCAPED_SLASHES` حذف شد (فقط `JSON_UNESCAPED_UNICODE` باقی ماند) تا `/` به‌صورت پیش‌فرض به `\/` تبدیل شود و امکان بستن زودهنگام تگ `<script>` از بین برود؛ کامنت توضیحی هم اضافه شد.
- ⚠️ ریسک قبل از اصلاح: Critical (Stored XSS از طریق عنوان/محتوای سؤال متداول — قابل اجرا توسط هر کاربری که اجازه ویرایش پست نوع `medical-faq` را دارد، روی صفحه‌ی هر بازدیدکننده).

## signteb-blocks/blocks/service-grid/render.php — 70
- ❌ مشکل: مقدار `$icon` (از `get_post_meta( $pid, 'stmc_service_icon', true )`) بدون هیچ escape ای مستقیماً echo می‌شد: `<span class="stmb-service-card__icon"><?php echo $icon; ?></span>`.
- 🔍 دلیل: فرض غلط که این فیلد همیشه فقط یک ایموجی ساده است؛ اما چون از post meta (قابل ویرایش توسط هر کاربری با دسترسی ویرایش پست `medical-service`) می‌آید، یک مقدار HTML/script دلخواه هم می‌تواند در آن ذخیره شود.
- ✅ اصلاح: به `echo esc_html( $icon );` تغییر یافت.
- ⚠️ ریسک قبل از اصلاح: High (Stored XSS ذخیره‌شده از طریق post meta قابل‌ویرایش، نمایش داده‌شده به تمام بازدیدکنندگان فرانت).

## signteb-blocks/blocks/medical-video/render.php — 47
- ❌ مشکل: مشابه مورد faq-accordion، `wp_json_encode` برای Schema `VideoObject` با `JSON_UNESCAPED_SLASHES` داخل `<script type="application/ld+json">` چاپ می‌شد. در این فایل مقادیر `$title`/`$desc` از قبل با `esc_html()` پاک‌سازی شده بودند (پس بهره‌برداری مستقیم دشوارتر است)، اما همچنان الگوی ناامنی است که هیچ لایه دفاعی در برابر breakout تگ script ندارد.
- 🔍 دلیل: کپی همان الگوی escape اشتباه از یک block دیگر (faq-accordion) بدون بازبینی امنیتی.
- ✅ اصلاح: `JSON_UNESCAPED_SLASHES` حذف شد؛ فقط `JSON_UNESCAPED_UNICODE` باقی ماند.
- ⚠️ ریسک قبل از اصلاح: Low (defense-in-depth — چون فیلدهای متنی از قبل esc_html شده بودند، بهره‌برداری مستقیم عملاً مسدود بود، اما رفع آن یک best-practice ضروری برای هر تغییر آینده در این فایل است).

## signteb-blocks/blocks/before-after-slider/render.php — 11
- ❌ مشکل: `$initial = (int) ( $attributes['initialPosition'] ?? 50 );` بدون محدودسازی به بازه‌ی منطقی ۰ تا ۱۰۰ بود، در حالی که مقدار مستقیماً در `style="width:...%"` و `style="right:...%"` استفاده می‌شود.
- 🔍 دلیل: `block.json` هیچ `enum`/min/max برای این attribute تعریف نکرده و کد سمت سرور به آن اعتماد کامل کرده بود.
- ✅ اصلاح: با `max( 0, min( 100, (int)(...) ) )` به بازه‌ی معتبر محدود شد (همان الگویی که سایر block‌ها مثل doctor-card-grid برای columns استفاده می‌کنند).
- ⚠️ ریسک قبل از اصلاح: Low (نه XSS چون مقدار int-cast شده، اما می‌تواند چیدمان بصری اسلایدر را در مقادیر خارج از بازه خراب کند).

## signteb-blocks/blocks/appointment-cta/render.php — 63, 101, 116, 122, 133, 139, 153, 156, 168, 173, 184, 188, 201
- ❌ مشکل: متغیر `$uid` (شناسه یکتای DOM ساخته‌شده با `wp_unique_id()`) در چندین جای فایل بدون `esc_attr()` مستقیماً در attribute های `id`/`for`/`data-form` چاپ می‌شد.
- 🔍 دلیل: چون `wp_unique_id()` همیشه خروجی امن (prefix + عدد) تولید می‌کند، توسعه‌دهنده escape را برای این خطوط لازم ندانسته بود؛ اما این یک الگوی ناسازگار با WPCS و در صورت تغییر منبع `$uid` در آینده (مثلاً افزودن پیشوند قابل‌تنظیم) خطرناک است.
- ✅ اصلاح: همه‌ی این موارد به `esc_attr( $uid )` تغییر یافتند تا با defense-in-depth مطابق WPCS باشند.
- ⚠️ ریسک قبل از اصلاح: Low (غیرقابل بهره‌برداری در وضعیت فعلی چون منبع مقدار امن است؛ صرفاً یک ریسک نگهداری/دفاع در عمق).

## signteb-blocks/blocks/appointment-cta/render.php — 12
- ❌ مشکل: `$success_msg = esc_attr( $attributes['successMsg'] ?? '' );` بود، اما این مقدار در خط ۵۵ داخل محتوای متنی یک `<p>` (نه یک attribute) echo می‌شود.
- 🔍 دلیل: انتخاب اشتباه تابع escape برای context خروجی (attribute-escaping به‌جای html-content-escaping).
- ✅ اصلاح: تابع به `esc_html()` تغییر یافت تا با context واقعی (متن داخل `<p>`) هم‌خوان باشد.
- ⚠️ ریسک قبل از اصلاح: Low (هر دو تابع در WordPress کاراکترهای خطرناک را encode می‌کنند، پس XSS واقعی رخ نمی‌داد؛ این یک اصلاح صحت/WPCS بود، نه یک آسیب‌پذیری قابل بهره‌برداری).

## signteb-blocks/includes/class-stmb-blocks.php — 52-59
- ❌ مشکل: `enqueue_shared_styles()` روی هوک `enqueue_block_assets` بدون هیچ شرطی اجرا می‌شد و `build/shared.css` را در **تمام صفحات فرانت سایت** (حتی صفحاتی که هیچ‌کدام از ۱۰ block این پلاگین را ندارند) لود می‌کرد؛ ضمناً پوشه `build/` در پکیج فعلی خالی است، یعنی این درخواست همیشه با ۴۰۴ مواجه می‌شود.
- 🔍 دلیل: استفاده از هوک عمومی `enqueue_block_assets` (که در فرانت مستقل از وجود واقعی block فایر می‌شود) به‌جای بررسی وجود block در محتوای صفحه.
- ✅ اصلاح: یک متد `current_page_has_block()` اضافه شد که با `has_block()` روی هر ۱۰ نام block پلاگین چک می‌کند؛ در فرانت فقط وقتی حداقل یکی از block‌ها در صفحه باشد، استایل لود می‌شود (در ادمین/ویرایشگر همیشه لود می‌شود چون پیش‌نمایش لازم است). علاوه بر این یک `file_exists()` guard اضافه شد تا وقتی فایل build هنوز تولید نشده، درخواست بی‌مورد ۴۰۴ ارسال نشود.
- ⚠️ ریسک قبل از اصلاح: Medium (نقض مستقیم اصل «Conditional asset loading» — دقیقاً همان ضعف Bookly که پروژه باید از آن پرهیز کند؛ افت سرعت روی تمام صفحات سایت + یک درخواست ۴۰۴ بی‌فایده در هر page load).

---

## signteb-wizard/includes/class-wizard-controller.php — 298-303 (متد ajax_check_plugins)
- ❌ مشکل: این AJAX handler فقط `check_ajax_referer()` را چک می‌کرد و فاقد بررسی `current_user_can( 'manage_options' )` بود؛ در حالی که سه handler دیگر (`ajax_save_step`, `ajax_import_demo`, `ajax_reset`) همگی این بررسی را دارند.
- 🔍 دلیل: فراموشی/ناهماهنگی در اعمال الگوی یکسان authorization روی همه‌ی endpoint های ادمین-محور wizard.
- ✅ اصلاح: بلوک `if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 ); }` بلافاصله بعد از `check_ajax_referer` اضافه شد (هم‌راستا با سه handler دیگر).
- ⚠️ ریسک قبل از اصلاح: Medium (نقض اصل «nonce به‌تنهایی authorization نیست» — نشت اطلاعات (نام و وضعیت فعال‌بودن پلاگین‌های نصب‌شده روی سایت) به هر کاربر لاگین‌شده‌ای که به هر طریقی nonce معتبر برای اکشن `stwiz_nonce` به‌دست بیاورد، نه فقط ادمین).

## signteb-wizard/signteb-setup-wizard.php — 36-42
- ❌ مشکل: هوک `admin_init` بدون هیچ شرطی transient ریدایرکت را چک و اجرا می‌کرد؛ فاقد بررسی `wp_doing_ajax()`, `wp_doing_cron()`, فعال‌سازی گروهی (`activate-multi`)، و `current_user_can()` بود.
- 🔍 دلیل: `admin_init` در تمام درخواست‌های ادمین از جمله `admin-ajax.php` فایر می‌شود؛ بدون گارد `wp_doing_ajax()`، اگر افزونه در همان پنجره‌ی ۳۰ ثانیه‌ای فعال‌سازی، یک درخواست AJAX دیگری (مثلاً از افزونه‌ای دیگر) اجرا شود، پاسخ آن با یک HTTP redirect جایگزین می‌شود و آن AJAX call را می‌شکند. همچنین بدون چک `current_user_can`، هر کاربری (حتی بدون دسترسی مدیریت) که در آن بازه به هر صفحه‌ی ادمین برود ریدایرکت می‌شود.
- ✅ اصلاح: گاردهای `wp_doing_ajax()`, `wp_doing_cron()`, `isset( $_GET['activate-multi'] )`, و `current_user_can( 'manage_options' )` قبل از `wp_safe_redirect` اضافه شدند.
- ⚠️ ریسک قبل از اصلاح: Medium (شکستن رفتار AJAX سایر افزونه‌ها/هسته در بازه‌ی فعال‌سازی + ریدایرکت ناخواسته برای کاربران غیرمدیر — یک باگ رفتاری شناخته‌شده در پلاگین‌های وردپرسی).

## signteb-wizard/steps/step-finish.php — 85
- ❌ مشکل: `esc_attr_e()` برای چاپ یک رشته‌ی ترجمه‌شده داخل یک رشته‌ی جاوااسکریپت (`confirm('...')`) استفاده شده بود؛ این تابع برای context attribute طراحی شده نه context جاوااسکریپت.
- 🔍 دلیل: انتخاب اشتباه تابع escape برای context خروجی — اگر ترجمه‌ی فارسی/رشته حاوی کاراکتر تک‌کوتیشن (`'`) باشد، `esc_attr_e` آن را به entity اچ‌تی‌ام‌ال (`&#039;`) تبدیل می‌کند که در متن دیالوگ `confirm()` به‌صورت رشته‌ی خام `&#039;` نمایش داده می‌شود (باگ نمایشی، نه یک آسیب‌پذیری امنیتی چون این تابع به‌طور اتفاقی از شکستن رشته جلوگیری می‌کرد).
- ✅ اصلاح: به `esc_js( __( '...', STWIZ_TEXT ) )` تغییر یافت که تابع صحیح WordPress برای escape کردن مقادیر داخل رشته‌های جاوااسکریپت inline است.
- ⚠️ ریسک قبل از اصلاح: Low (باگ نمایشی/صحت i18n، نه امنیتی؛ اصلاح شد چون مصداق دقیق چک‌لیست «missing/wrong output escaping» بود).

---

# بخش ۴ — signteb-medcore (قالب)

خلاصه: ۲۳ فایل بررسی شد (۸ فایل PHP، ۴ فایل JS، ۹ فایل HTML قالب/FSE، ۴ فایل CSS، ۱ theme.json) — ۱۱ فایل اصلاح شد (۷ PHP، ۳ JS، ۴ HTML). یافته‌ها بر اساس ریسک: Critical: 1 (که در ۴ فایل تکرار شده) · High: 1 · Medium: 5 · Low: 2

---

## inc/class-medcore-template-tags.php, parts/header.html, parts/footer.html, templates/single-doctor.html, templates/single-medical-service.html — چند خط (header.html:9, footer.html:67و91-102, single-doctor.html:14, single-medical-service.html:9)
- ❌ مشکل: تگ‌های `<?php ... ?>` به‌صورت خام داخل فایل‌های `templates/*.html` و `parts/*.html` (قالب‌های FSE) نوشته شده بودند — مثل `<?php echo date('Y'); ?>` در فوتر، `<?php stmc_breadcrumbs(); ?>` در دو قالب single، و ترجمه‌ی متن skip-link در هدر. این کدها هیچ‌وقت اجرا نمی‌شدند و عیناً به‌صورت متن خام PHP روی صفحه‌ی سایت چاپ می‌شدند (مثلاً کاربر واقعی به‌جای سال کپی‌رایت، متن `<?php echo date('Y'); ?>` را می‌دید). دکمه‌ی شناور واتساپ در فوتر هم به همین دلیل همیشه با لینک شکسته‌ی `https://wa.me/` نمایش داده می‌شد (چون رشته‌ی PHP اجرانشده هیچ رقمی نداشت که با `.replace(/[^0-9]/g,'')` استخراج شود).
- 🔍 دلیل: فایل‌های `templates/*.html` و `parts/*.html` در تم‌های Block/FSE توسط WordPress با `file_get_contents()` خوانده و به‌عنوان block markup پردازش می‌شوند (`_build_block_template_result_from_file()` → `get_the_block_template_html()`)؛ این فایل‌ها هرگز از موتور PHP (`include`/`require`) عبور نمی‌کنند، بنابراین تگ `<?php ?>` داخل آن‌ها صرفاً متن ساده تلقی می‌شود. به نظر می‌رسد این تم از یک نسخه‌ی کلاسیک PHP (header.php/footer.php) به ساختار FSE مهاجرت داده شده و این قطعه‌کدها بدون تبدیل، عیناً کپی شده‌اند.
- ✅ اصلاح: در `inc/class-medcore-template-tags.php` سه شورت‌کد جدید (`[stmc_breadcrumbs]`, `[stmc_whatsapp_float]`, `[stmc_copyright_year]`) روی هوک `init` ثبت شد که توابع template-tag موجود و از قبل امن (`stmc_breadcrumbs()`, `stmc_whatsapp_float()`) را از طریق output buffering فراخوانی می‌کنند. شورت‌کدها تنها راه رسمی WordPress برای اجرای PHP داخل قالب FSE هستند، چون `get_the_block_template_html()` پس از `do_blocks()` تابع `do_shortcode()` را روی کل خروجی اجرا می‌کند (همان الگویی که خود تم قبلاً برای `[stmc_review_form]` استفاده کرده بود). در فایل‌های html، تگ‌های PHP خراب با شورت‌کدهای متناظر جایگزین شدند؛ متن استاتیک skip-link هم مستقیماً به فارسی نوشته شد (چون ترجمه‌ی پویا در این‌جا امکان‌پذیر نیست). بلوک اسکریپت شکسته‌ی دکمه‌ی واتساپ در فوتر کاملاً حذف و با `[stmc_whatsapp_float]` (که خروجی امن و esc_url‌شده‌ی تابع template-tag موجود را برمی‌گرداند) جایگزین شد.
- ⚠️ ریسک قبل از اصلاح: Critical

## inc/class-medcore-nav-walker.php — خط ۴۰-۵۰ (`$atts['href']`)
- ❌ مشکل: در متد `start_el()`، مقدار `href` منو (که از `$item->url` می‌آید) با `esc_attr()` escape می‌شد، نه `esc_url()`. `esc_attr()` برای escape کردن یک رشته در کانتکست attribute است، نه sanitize کردن یک URL — پروتکل‌های خطرناک مثل `javascript:` را حذف نمی‌کند.
- 🔍 دلیل: در حلقه‌ی عمومی ساخت attribute های تگ `<a>`، همه‌ی attributeها (شامل href) با یک `esc_attr()` یکسان escape می‌شدند، بدون در نظر گرفتن این‌که href باید با تابع اختصاصی `esc_url()` پردازش شود (طبق استاندارد WordPress Coding Standards).
- ✅ اصلاح: در حلقه، برای attribute با کلید `href` مقدار با `esc_url()` و برای بقیه با `esc_attr()` escape می‌شود.
- ⚠️ ریسک قبل از اصلاح: Medium (دسترسی به ثبت/ویرایش منو نیازمند `edit_theme_options` است، اما همچنان یک نقض defense-in-depth و استاندارد امنیتی WPCS محسوب می‌شود)

## inc/helpers.php — تابع `stmc_get_breadcrumbs()` (حدود خط ۱۴۴-۱۶۱)
- ❌ مشکل: در شاخه‌ی `is_tax() || is_category() || is_tag()`، مقدار `get_queried_object()` بدون بررسی null/نوع مستقیماً در `$term->taxonomy` استفاده می‌شد. در حالت‌های لبه‌ای (query object نامعتبر) این باعث PHP Warning «Attempt to read property on null» می‌شود.
- 🔍 دلیل: فرض شده بود `get_queried_object()` در صفحات tax/category/tag همیشه یک شیء `WP_Term` معتبر برمی‌گرداند، در حالی که در برخی حالت‌های edge-case (مثل کوئری‌های دستکاری‌شده یا اجرای خیلی زودهنگام) می‌تواند `null` باشد.
- ✅ اصلاح: یک بررسی `instanceof WP_Term` قبل از استفاده از `$term` اضافه شد؛ در صورت نامعتبر بودن، تابع همان breadcrumbs جمع‌شده تا آن لحظه را برمی‌گرداند.
- ⚠️ ریسک قبل از اصلاح: Low

## inc/class-medcore-enqueue.php — خط ۱۱۰-۱۴۱ (appointment-form) و خط ۱۶۰-۱۶۸ (admin-meta)
- ❌ مشکل: `wp_enqueue_style()`/`wp_enqueue_script()` برای `css/appointment-form.css`, `js/appointment-form.js` و `css/admin-meta.css` بدون بررسی وجود فایل صدا زده می‌شدند، در حالی که این فایل‌ها اصلاً در پکیج تم وجود ندارند (تنها assets/css/{components,editor,main}.css و assets/js/{animations,before-after,main,navigation}.js موجودند). نتیجه: درخواست‌های شبکه‌ی ۴۰۴ بی‌فایده روی صفحات مرتبط (پروفایل پزشک، صفحات ادمین CPT) در هر بارگذاری.
- 🔍 دلیل: این فایل‌ها احتمالاً برای فیچرهای در حال توسعه (فرم نوبت‌دهی، متای ادمین) در نظر گرفته شده‌اند اما هنوز ساخته/اضافه نشده‌اند.
- ✅ اصلاح: قبل از هر enqueue، یک بررسی `file_exists( MEDCORE_DIR . '/assets/...' )` اضافه شد تا وقتی فایل واقعاً وجود ندارد، هیچ تگ `<script>`/`<link>` شکسته‌ای در خروجی HTML چاپ نشود؛ به محض افزودن فایل‌های واقعی توسط توسعه‌دهنده، enqueue بدون تغییر دیگری فعال خواهد شد (رفتار قبلی حفظ شده، فقط از 404 جلوگیری می‌شود).
- ⚠️ ریسک قبل از اصلاح: Medium (بهداشت enqueue / کارایی، بدون ریسک امنیتی مستقیم)

## inc/class-medcore-customizer.php — خط ۸۱-۸۹ (`preview_js()`)
- ❌ مشکل: مشابه مورد قبل، `wp_enqueue_script('stmc-customizer-preview', ... 'js/customizer-preview.js', ...)` بدون بررسی وجود فایل صدا زده می‌شد؛ این فایل در پکیج تم وجود ندارد.
- 🔍 دلیل: فایل پیش‌نمایش زنده‌ی Customizer هنوز ساخته نشده اما هوک آن از قبل ثبت شده بود.
- ✅ اصلاح: یک `file_exists()` guard در ابتدای متد اضافه شد که در نبود فایل، enqueue را متوقف می‌کند.
- ⚠️ ریسک قبل از اصلاح: Low-Medium

## assets/js/animations.js — خط ۱۹-۳۰ و ۶۶-۶۸ (قبل از اصلاح)
- ❌ مشکل: کلاس‌های `.stmc-doctor-card`, `.stmc-card`, `.stmc-glass-card` هم در `main.js` (بخش «Intersection Observer: Fade-in on scroll») و هم در `animations.js` (آرایه‌ی `ANIMATE_TARGETS` + پویش جداگانه‌ی `.will-animate`) observe می‌شدند. چون هر دو اسکریپت هم‌زمان روی هر صفحه enqueue می‌شوند، این المان‌ها با دو `IntersectionObserver` کاملاً مستقل رصد می‌شدند — کار تکراری غیرضروری روی هر اسکرول. علاوه بر این، متغیر `ANIMATE_TARGETS` در کد اصلی تعریف شده بود اما هرگز استفاده نمی‌شد (کد مرده) و به‌جایش یک selector هاردکد `'.will-animate'` جدا استفاده می‌شد.
- 🔍 دلیل: دو سیستم «fade-in on scroll» مستقل (یکی در main.js برای کلاس‌های stmc-*، دیگری در animations.js که در اصل برای کلاس‌های قدیمی‌تر stmb-* نوشته شده بود) بدون هماهنگی به یک selector مشترک اضافه شده‌اند.
- ✅ اصلاح: کلاس‌های همپوشان (`.stmc-doctor-card`, `.stmc-card`, `.stmc-glass-card`, `.will-animate`) از `ANIMATE_TARGETS` در animations.js حذف شدند (این‌ها منحصراً توسط observer خود main.js پوشش داده می‌شوند). پویش جداگانه‌ی سراسری `.will-animate` هم حذف و با استفاده‌ی واقعی از متغیر `ANIMATE_TARGETS` (با گارد `classList.contains('will-animate')` برای جلوگیری از رصد دوباره‌ی المان‌های grid که قبلاً observe شده‌اند) جایگزین شد.
- ⚠️ ریسک قبل از اصلاح: Medium (کارایی/تکرار کد، بدون خرابی بصری قابل مشاهده چون هر دو observer فقط همان کلاس `is-visible` را اضافه می‌کردند)

## assets/js/before-after.js — کل فایل (خط ۹-۳۵ قبل از اصلاح)
- ❌ مشکل: داخل `forEach` روی همه‌ی المان‌های `.stmc-before-after`، دو listener سطح `window` (`mousemove` و `mouseup`) برای هر اسلایدر جداگانه ثبت می‌شدند. اگر بیش از یک اسلایدر before/after در یک صفحه وجود داشته باشد (مثلاً چند خدمت پزشکی با تصاویر قبل/بعد)، چندین listener تکراری روی `window` انباشته می‌شود که هرگز پاک‌سازی نمی‌شوند — دقیقاً الگوی «duplicate event listener که می‌تواند چندبار اجرا شود».
- 🔍 دلیل: منطق drag-state (`dragging`) به‌صورت closure محلی داخل هر تکرار حلقه تعریف شده بود، به‌جای یک state مشترک در سطح ماژول؛ این باعث شد هر اسلایدر مجبور به ثبت listenerهای global خودش شود.
- ✅ اصلاح: کد بازنویسی شد تا یک متغیر مشترک `activeSlider` در سطح IIFE، اسلایدر «در حال درگ» را نگه دارد و فقط یک‌بار (خارج از حلقه) `window.addEventListener('mousemove', ...)` و `window.addEventListener('mouseup', ...)` ثبت شود. رفتار بصری/تعاملی برای کاربر کاملاً یکسان است.
- ⚠️ ریسک قبل از اصلاح: Medium (کارایی/مصرف حافظه؛ بدون خرابی مشهود در حالت تک‌اسلایدر، اما در صفحات با چند اسلایدر باعث افت کارایی و رفتار غیرقابل‌پیش‌بینی هنگام drag هم‌زمان می‌شد)

## functions.php, inc/class-medcore-setup.php, inc/class-medcore-customizer.php, inc/helpers.php, inc/class-medcore-enqueue.php, inc/class-medcore-template-tags.php, inc/class-medcore-block-patterns.php — ۶۲ فراخوانی توابع ترجمه (`__()`, `_e()`, `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_attr_e()`)
- ❌ مشکل: تمام فراخوانی‌های توابع ترجمه در سراسر تم، به‌جای رشته‌ی literal، از ثابت PHP `MEDCORE_TEXT` به‌عنوان آرگومان text-domain استفاده می‌کردند (مثلاً `esc_html__('متن', MEDCORE_TEXT)`). ابزارهای استاندارد استخراج رشته‌ی WordPress (`wp i18n make-pot`, GlotPress, Theme Check) فقط zورودی literal string را برای domain می‌شناسند و نمی‌توانند مقدار یک ثابت را در زمان build resolve کنند؛ در نتیجه هیچ‌کدام از رشته‌های این تم در فایل `.pot` تولیدی استخراج نمی‌شدند، در حالی‌که style.css این تم را «translation-ready» اعلام کرده است. این یک نقض شناخته‌شده و مستند‌شده‌ی قوانین i18n در WPCS/Theme Check است.
- 🔍 دلیل: استفاده از ثابت به‌جای رشته‌ی مستقیم برای «تمیزتر» به‌نظر رسیدن کد، بدون توجه به این‌که ابزارهای استخراج رشته صرفاً پارس استاتیک انجام می‌دهند و مقدار ثابت‌ها را نمی‌دانند.
- ✅ اصلاح: تمام ۶۲ فراخوانی (در ۷ فایل) به رشته‌ی literal `'signteb-medcore'` تغییر یافتند (مقدار دقیقاً همان چیزی است که ثابت قبلاً نگه می‌داشت، پس هیچ تغییر رفتاری در runtime رخ نداده). فراخوانی `define('MEDCORE_TEXT', 'signteb-medcore')` و `load_theme_textdomain(MEDCORE_TEXT, ...)` بدون تغییر باقی ماندند چون این دو خارج از الزام i18n string-extraction هستند.
- ⚠️ ریسک قبل از اصلاح: High (نقض مستقیم استاندارد WPCS i18n که می‌تواند باعث رد شدن تم در بازبینی مارکت‌پلیس/WordPress.org شود؛ بدون ریسک امنیتی، اما اثر عملکردی واقعی روی قابلیت ترجمه‌شدن تم دارد)

---

### یادداشت‌های اضافی (مشاهده شد، بدون تغییر کد — خارج از حیطه‌ی «رفع باگ کمینه»)

- **inc/class-medcore-nav-walker.php (کل فایل) و بخش «Dropdown Menus» در assets/js/navigation.js (خط ۱۴۱-۲۱۲):** کلاس `MedCore_Nav_Walker` هرگز از طریق `wp_nav_menu(['walker' => ...])` استفاده نمی‌شود؛ هدر/فوتر واقعی (`parts/header.html`, `parts/footer.html`) از بلوک هسته‌ای `wp:navigation` استفاده می‌کنند که خروجی‌اش کلاس `menu-item-has-children` ندارد. در نتیجه، بخش dropdown در `navigation.js` (که به‌دنبال `.menu-item-has-children` می‌گردد) عملاً کد مرده است — بدون خطا اما بدون اثر. هیچ CSS هم برای `.sub-menu` (نمایش/عدم‌نمایش) در `main.css`/`components.css` تعریف نشده. رفع کامل این مورد نیازمند تصمیم طراحی (کدام سیستم منو نگه داشته شود) است، نه صرفاً رفع باگ، پس خارج از حیطه‌ی این ممیزی نگه داشته شد.
- **assets/images/ و آیکون‌ها:** توابع `stmc_svg()` و `stmc_get_thumbnail_url()` به مسیر `assets/images/...` ارجاع می‌دهند، اما این پوشه اصلاً در پکیج فعلی تم وجود ندارد. چون این خروجی‌ها صرفاً یک URL رشته‌ای (نه enqueue) هستند و مرورگر با `<img>` شکسته graceful-degrade می‌کند، اصلاح نشد؛ صرفاً به‌عنوان یادداشت packaging ثبت می‌شود.
- **`private bool $is_medical_cpt` در class-medcore-enqueue.php:** مقداردهی می‌شود اما هیچ‌جا خوانده نمی‌شود (dead property) — نیاز به تصمیم توسعه‌دهنده دارد (حذف یا استفاده)، صرفاً یادداشت شد.
