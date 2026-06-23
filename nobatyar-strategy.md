# نوبتیار (Nobatyar) — استراتژی محصول و معماری فنی پلاگین رزرو نوبت وردپرس

> سند استراتژیک کامل برای طراحی، توسعه و عرضه یک پلاگین رزرو نوبت مستقل (غیرپزشکی) برای بازار ایران.

---

## بخش ۱: نام‌گذاری محصول (Product Naming)

معیارها: برندپذیر، خنثی نسبت به صنعت، قابل تلفظ فارسی و انگلیسی، پتانسیل دامنه `.ir`/`.com`.

| نام پیشنهادی | منطق نام‌گذاری | یادداشت دامنه |
|---|---|---|
| **نوبتیار (Nobatyar)** ✅ انتخاب نهایی | "نوبت" + "یار" — کاملاً فارسی، معنای روشن (دستیار نوبت‌دهی) | `mynobatyar.ir` / `nobatyar.com` |
| وقتینو (Vaghtino) | "وقت" + پسوند مدرن "ino" — حس استارتاپی | `vaghtino.com` بررسی شود |
| رزرویار (Rezervyar) | از "رزرو" گرفته شده، برای سالن/رستوران | `rezervyar.ir` |
| نوبتچی (Nobatchi) | پسوند "-چی" محاوره‌ای و صمیمی | `nobatchi.com` / `nobatchi.ir` |
| بوکینو (Bookino) | هیبرید انگلیسی-فارسی | ریسک شباهت به Booking.com |
| آسان‌نوبت (AsanNobat) | تأکید بر سادگی نصب/استفاده | `asannobat.ir` |
| نوبت‌دهی پلاس | توصیفی و SEO-friendly، کمتر برندپذیر | `nobatdehiplus.ir` |
| پیمانک (Peymanak) | "پیمان" + پسوند کوچک‌ساز — منحصربه‌فرد | `peymanak.ir` |

### اقدامات فوری پس از انتخاب نام
- ثبت دامنه `mynobatyar.ir` و `nobatyar.com`
- چک نام در سامانه ثبت برند (مرکز مالکیت صنعتی)
- Namespace پایه کد: `Nobatyar\`
- شورت‌کد نهایی: `[nobatyar_booking]`
- پیشوند جدول دیتابیس: `nby_`

---

## بخش ۲: مجموعه ویژگی‌های هسته (MVP Core Feature Set)

### 2.1 Generalization Strategy (Doctor → Service Provider)

```
OLD CONCEPT            →  NEW CONCEPT
─────────────────────────────────────────────
Doctor                 →  Provider (سرویس‌دهنده)
Medical Specialty       →  Service Category (دسته خدمت)
شماره نظام پزشکی         →  provider_license_number (optional, generic, relabeled per business type)
Appointment              →  Booking (همان مفهوم، فقط عمومی‌سازی نام کلاس‌ها)
Doctor Schedule           →  Provider Availability
```

هر نوع کسب‌وکار یک مجموعه برچسب قابل تنظیم دارد (مثلاً ادمین سالن "Stylist" به‌جای "Provider" می‌بیند) — این یک **جدول Terminology/Labels** است، نه متن هاردکد، تا بتوان بعداً white-label per vertical ارائه داد.

### 2.2 لیست ویژگی‌ها (MVP)

**A. موتور بوکینگ هسته**
- بوکینگ چند سرویس‌دهنده/چند سرویس (`provider_id`, `service_id`, `duration`, `buffer_time`)
- چرخه وضعیت: `pending → confirmed → done / cancelled / no_show`
- تشخیص تعارض زمانی (عدم دوبل‌بوکینگ یک اسلات)
- بازه‌های زمانی قابل تنظیم (۱۵/۳۰/۴۵/۶۰ دقیقه) به ازای هر سرویس‌دهنده
- ساعات کاری + تعطیلات/استثناء به ازای هر سرویس‌دهنده

**B. تقویم جلالی (الزامی)**
- پیکر تاریخ جلالی کامل برای ادمین و کاربر نهایی
- ذخیره سمت سرور به میلادی (source of truth)، تبدیل جلالی فقط در لایه نمایش
- آگاهی از تعطیلات رسمی ایران (تاگل اختیاری)

**C. اطلاع‌رسانی**
- SMS (اصلی): ملی‌پیامک و کاوه‌نگار به‌عنوان دو provider اول، پشت یک `SmsProviderInterface`
- WhatsApp (ثانویه/اختیاری) — علامت‌گذاری به‌عنوان "تجربی" در MVP
- ایمیل (تاگل مستقل برای ادمین و مشتری)
- تریگرها: ایجاد بوکینگ، تأیید، یادآور (X ساعت قبل)، لغو

**D. پرداخت**
- زرین‌پال به‌عنوان گیت‌وی اصلی/پیش‌فرض
- آیدی‌پی یا نکست‌پی به‌عنوان ثانویه (پشت `PaymentGatewayInterface`)
- پشتیبانی: فقط پیش‌پرداخت یا پرداخت کامل، قابل تنظیم به ازای هر سرویس
- وضعیت پرداخت مستقل از وضعیت بوکینگ

**E. داشبورد ادمین**
- نمای کالندر (روز/هفته) — FullCalendar.js با پچ لوکال جلالی
- نمای لیست با فیلتر (وضعیت، سرویس‌دهنده، بازه تاریخ)
- اقدامات سریع: تأیید/لغو/تغییر زمان درجا
- گزارش‌گیری پایه: بوکینگ به ازای سرویس‌دهنده، نرخ no-show، درآمد

**F. فرانت‌اند مشتری**
- بلاک گوتنبرگ (عمومی‌سازی‌شده از بلاک نوبت‌دهی قبلی)
- شورت‌کد: `[nobatyar_booking]`
- مسیر گام‌به‌گام: انتخاب سرویس → انتخاب سرویس‌دهنده (یا "هر کس آزاد است") → انتخاب تاریخ/زمان (جلالی) → اطلاعات → (اختیاری) پیش‌پرداخت → تأیید

### 2.3 خارج از محدوده MVP (موکول به Pro/Agency)
- اتوماسیون کامل WhatsApp
- پشتیبانی چند شعبه
- پورتال ورود سرویس‌دهندگان (فاز ۲)
- بوکینگ پکیجی/اشتراکی (مثلاً "پکیج ۱۰ جلسه‌ای")

---

## بخش ۳: مدل لایسنس و درآمدزایی (Licensing & Monetization)

### 3.1 قیمت‌گذاری سالانه — Tiered (تومان)

| Tier | قیمت سالانه (تومان) | محدودیت سرویس‌دهنده | امکانات |
|---|---|---|---|
| **رایگان (Starter Free)** | ۰ | ۱ سرویس‌دهنده | شورت‌کد پایه، تقویم جلالی، بدون SMS، بدون پرداخت آنلاین، برندینگ "Powered by نوبتیار" |
| **حرفه‌ای (Pro)** | ۱,۹۹۰,۰۰۰ – ۲,۹۹۰,۰۰۰ | تا ۵ سرویس‌دهنده | SMS، پرداخت زرین‌پال، حذف برندینگ، گزارش‌گیری پایه |
| **تجاری (Business)** | ۴,۹۹۰,۰۰۰ – ۶,۹۹۰,۰۰۰ | تا ۲۰ سرویس‌دهنده | + WhatsApp (تجربی)، چند گیت‌وی پرداخت، یادآور خودکار، پشتیبانی اولویت‌دار |
| **آژانس (Agency)** | ۱۲,۰۰۰,۰۰۰+ | نامحدود + چند‌سایتی (۵-۱۰ دامنه) | White-label کامل، چند‌شعبه‌ای، دسترسی API، پشتیبانی اختصاصی |

> اعداد تخمینی‌اند و باید با تحقیق بازار رقبا کالیبره شوند.

### 3.2 رویکرد اعتبارسنجی لایسنس (بدون سرویس‌های تحریم‌شده)

**معماری: Self-hosted License Server** (`license.mynobatyar.ir`)

```
1. کاربر license_key را در تنظیمات افزونه وارد می‌کند
2. افزونه هر ۲۴ ساعت (cron) به /api/v1/license/validate درخواست می‌زند
   شامل: license_key, domain (hash‌شده), plugin_version
3. سرور پاسخ می‌دهد: { status, expires_at, tier }
4. پاسخ با HMAC امضا می‌شود (ضد tampering)
5. نتیجه محلی کش می‌شود — قطعی موقت سرور = قفل نشدن فوری مشتری
```

### 3.3 یادآوری تمدید و Enforcement

| فاز | زمان‌بندی | رفتار |
|---|---|---|
| یادآوری زودهنگام | ۳۰/۱۵/۷ روز قبل | ایمیل + SMS به ادمین |
| روز انقضا | روز ۰ | ایمیل + بنر هشدار در پنل |
| Grace Period | ۱۴ روز پس از انقضا | **Soft lock**: SMS و پرداخت آنلاین غیرفعال، بوکینگ پایه فعال |
| پس از Grace | روز ۱۴+ | **Full lock**: فرانت پیام غیرفعال نشان می‌دهد؛ داده‌ها هرگز حذف نمی‌شوند |

---

## بخش ۴: معماری تکنیکال (Technical Architecture)

### 4.1 ساختار پوشه (PSR-4، namespace `Nobatyar\`)

```
nobatyar-booking/
├── nobatyar-booking.php
├── composer.json
├── uninstall.php
│
├── includes/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   └── Autoloader.php
│   │
│   ├── Booking/
│   │   ├── BookingEngine.php
│   │   ├── BookingRepository.php
│   │   ├── BookingStatus.php
│   │   └── SlotCalculator.php
│   │
│   ├── Provider/
│   │   ├── Provider.php
│   │   ├── ProviderRepository.php
│   │   └── AvailabilityManager.php
│   │
│   ├── Service/
│   │   ├── Service.php
│   │   └── ServiceRepository.php
│   │
│   ├── Calendar/
│   │   ├── JalaliConverter.php
│   │   └── HolidayProvider.php
│   │
│   ├── Notifications/
│   │   ├── SmsProviderInterface.php
│   │   ├── Providers/
│   │   │   ├── KavehnegarProvider.php
│   │   │   └── MelipayamakProvider.php
│   │   ├── WhatsApp/
│   │   │   └── WhatsAppProvider.php
│   │   ├── EmailNotifier.php
│   │   └── NotificationDispatcher.php
│   │
│   ├── Payment/
│   │   ├── PaymentGatewayInterface.php
│   │   ├── Gateways/
│   │   │   ├── ZarinpalGateway.php
│   │   │   ├── IdPayGateway.php
│   │   │   └── NextPayGateway.php
│   │   └── TransactionRepository.php
│   │
│   ├── License/
│   │   ├── LicenseManager.php
│   │   ├── LicenseStatus.php
│   │   └── GracePeriodHandler.php
│   │
│   ├── Admin/
│   │   ├── Dashboard/
│   │   │   ├── CalendarView.php
│   │   │   └── ListView.php
│   │   ├── Settings/
│   │   │   └── SettingsPage.php
│   │   └── Reports/
│   │       └── ReportGenerator.php
│   │
│   ├── Frontend/
│   │   ├── Shortcode/
│   │   │   └── BookingShortcode.php
│   │   └── Block/
│   │       └── BookingBlock.php
│   │
│   ├── Rest/
│   │   └── Controllers/
│   │       ├── BookingController.php
│   │       ├── AvailabilityController.php
│   │       ├── PaymentController.php
│   │       └── LicenseController.php
│   │
│   └── Labels/
│       └── TerminologyMap.php
│
├── assets/
│   ├── js/
│   │   ├── admin-calendar.js
│   │   └── booking-form.js
│   └── css/
│
├── templates/
│
└── languages/
```

### 4.2 Database Schema

```sql
-- Providers
CREATE TABLE {prefix}nby_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(191) NOT NULL,
    label_override VARCHAR(50) NULL,
    license_field VARCHAR(100) NULL,
    avatar_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

-- Services
CREATE TABLE {prefix}nby_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(191) NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 30,
    buffer_minutes INT NOT NULL DEFAULT 0,
    price DECIMAL(12,2) NULL,
    deposit_amount DECIMAL(12,2) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

-- Provider <-> Service
CREATE TABLE {prefix}nby_provider_services (
    provider_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (provider_id, service_id)
);

-- Availability (recurring weekly)
CREATE TABLE {prefix}nby_availability (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    INDEX idx_provider (provider_id)
);

-- Availability Exceptions
CREATE TABLE {prefix}nby_availability_exceptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NULL,
    date DATE NOT NULL,
    is_full_day TINYINT(1) DEFAULT 1,
    start_time TIME NULL,
    end_time TIME NULL
);

-- Bookings
CREATE TABLE {prefix}nby_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NOT NULL,
    customer_name VARCHAR(191) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(191) NULL,
    booking_datetime DATETIME NOT NULL,
    status ENUM('pending','confirmed','done','cancelled','no_show') DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_provider_datetime (provider_id, booking_datetime),
    INDEX idx_status (status)
);

-- SMS Logs
CREATE TABLE {prefix}nby_sms_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NULL,
    provider_name VARCHAR(50) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    response_payload TEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL
);

-- Payment Transactions
CREATE TABLE {prefix}nby_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(30) NOT NULL,
    authority VARCHAR(100) NULL,
    amount DECIMAL(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL,
    raw_response TEXT NULL,
    created_at DATETIME NOT NULL,
    verified_at DATETIME NULL
);

-- License
CREATE TABLE {prefix}nby_license (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(64) NOT NULL,
    tier VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    last_validated_at DATETIME NULL,
    expires_at DATE NULL,
    domain_hash VARCHAR(64) NULL,
    UNIQUE KEY uniq_key (license_key)
);
```

### 4.3 WordPress Hooks/Actions + REST Endpoints

**Custom Action Hooks:**
```php
do_action('nobatyar_booking_created', $booking_id);
do_action('nobatyar_booking_status_changed', $booking_id, $old_status, $new_status);
do_action('nobatyar_payment_verified', $transaction_id, $booking_id);
do_action('nobatyar_license_status_changed', $old_status, $new_status);
apply_filters('nobatyar_available_slots', $slots, $provider_id, $date);
apply_filters('nobatyar_terminology_label', $default_label, $key, $context);
```

**WP Cron Events:**
```php
wp_schedule_event(time(), 'daily', 'nobatyar_license_check');
wp_schedule_event(time(), 'hourly', 'nobatyar_send_reminders');
```

**REST API Endpoints** (namespace `nobatyar/v1`):

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/nobatyar/v1/availability` | اسلات‌های آزاد برای provider/service/date |
| POST | `/nobatyar/v1/bookings` | ایجاد بوکینگ (عمومی، nonce-protected) |
| GET | `/nobatyar/v1/bookings/{id}` | دریافت یک بوکینگ |
| PATCH | `/nobatyar/v1/bookings/{id}/status` | تغییر وضعیت (ادمین) |
| POST | `/nobatyar/v1/payments/init` | شروع پرداخت |
| GET | `/nobatyar/v1/payments/callback` | تأیید پرداخت گیت‌وی |
| POST | `/nobatyar/v1/license/activate` | فعال‌سازی لایسنس روی دامنه |
| GET | `/nobatyar/v1/license/status` | چک داخلی وضعیت |

> endpoint های عمومی (availability, booking creation) باید rate-limited باشند (throttle بر اساس IP).

### 4.4 آنچه باید حذف یا عمومی‌سازی شود

| مورد در SignTeb Medical Core | اقدام |
|---|---|
| فیلد «شماره نظام پزشکی» | → ستون عمومی `license_field`، اختیاری، label قابل تغییر |
| namespace شامل `STMC_*` یا `SignTeb` | → بازنویسی کامل به `Nobatyar\*` — بدون باقی‌ماندن هیچ رفرنس |
| Shortcode `[stmc_appointment]` | → `[nobatyar_booking]` (با alias موقت برای backward-compat) |
| متن‌های هاردکد ("دکتر"، "بیمار"، "ویزیت") | → از طریق `TerminologyMap` به رشته‌های عمومی |
| فیلدهای دیتابیس مخصوص پزشکی | → حذف یا تبدیل به "Custom Field" عمومی |
| جدول‌های قدیمی `stmc_*` | → migration script یک‌باره به `nby_*` |

---

## بخش ۵: استراتژی Go-to-Market برای ایران

### 5.1 کانال‌های توزیع

| کانال | اولویت | منطق |
|---|---|---|
| فروش مستقیم + کانال تلگرام | ۱ | بالاترین حاشیه سود، کنترل رابطه مشتری برای تمدید |
| وردپرس‌فارسی / ایران‌تیکت | ۲ | دسترسی به ترافیک organic موجود، اما کمیسیون ۲۰-۳۰٪ |
| CodeCanyon (دسته فارسی) | ۳ (اختیاری) | بازار دیاسپورا/بین‌الملل؛ پرداخت PayPal/Payoneer محدودیت دارد |
| گروه‌های صنفی فیسبوک/اینستاگرام | ۴ | بازاریابی مستقیم به verticals، هزینه پایین |

### 5.2 تحلیل شکاف رقابتی

| ضعف رایج رقبا | فرصت نوبتیار |
|---|---|
| تقویم جلالی patch‌شده و باگ‌دار | تقویم جلالی به‌عنوان شهروند درجه‌یک معماری |
| یکپارچگی SMS محدود/دستی | چند provider پیش‌یکپارچه با تنظیم ساده |
| نبود پرداخت بومی built-in | زرین‌پال/آیدی‌پی در همان لایسنس |
| UI ترجمه‌ماشینی | متن فارسی native برای فرهنگ کسب‌وکار ایرانی |
| Enforcement خشن لایسنس | Grace Period شفاف |
| محدود به یک صنعت | Generic از ابتدا — بازار آدرس‌پذیر بزرگ‌تر |

### 5.3 استراتژی قیمت‌گذاری لانچ

| فاز | مدت | استراتژی |
|---|---|---|
| Early Bird | ۳۰-۴۵ روز اول | تخفیف ۳۰-۴۰٪ روی Pro/Business |
| استاندارد | پس از لانچ | بازگشت به قیمت کامل |
| افزایش تدریجی | سال‌های بعد | ۱۰-۱۵٪ سالانه برای مشتریان جدید؛ loyalty pricing برای موجود |

> از تخفیف عمیق دائمی پرهیز شود — ارزش ادراک‌شده محصول را در بازار ایران از بین می‌برد.

---

## بخش ۶: نقشه راه ساخت ۹۰ روزه

| فاز | بازه زمانی | خروجی کلیدی |
|---|---|---|
| ۱. معماری و Foundation | روز ۱-۱۰ | اسکلت پلاگین، دیتابیس، namespace |
| ۲. موتور بوکینگ هسته | روز ۱۱-۲۵ | CRUD کامل، تشخیص تعارض زمانی |
| ۳. تقویم جلالی | روز ۲۶-۳۵ | تقویم شمسی کامل (ادمین + فرانت) |
| ۴. یکپارچگی SMS | روز ۳۶-۴۵ | ملی‌پیامک + کاوه‌نگار فعال |
| ۵. گیت‌وی پرداخت | روز ۴۶-۵۸ | زرین‌پال کامل، آیدی‌پی/نکست‌پی پایه |
| ۶. سیستم لایسنس | روز ۵۹-۷۰ | License server + enforcement کامل |
| ۷. UI پنل ادمین | روز ۷۱-۸۲ | داشبورد، تنظیمات، گزارش پایه |
| ۸. آماده‌سازی لانچ | روز ۸۳-۹۰ | بسته‌بندی، اسناد، صفحه فروش، QA |

### تفکیک فازها

**فاز ۱ (روز ۱-۱۰):** ساختار namespace، `Activator.php` برای schema، Composer + کتابخانه Jalali، i18n.
⚠️ اگر migration از کد SignTeb موجود است، ۲-۳ روز اضافه برای audit کد قدیمی در نظر گرفته شود.

**فاز ۲ (روز ۱۱-۲۵):** `BookingEngine`, `SlotCalculator`, تشخیص تعارض، repositoryها، REST endpoints، شورت‌کد پایه.
Milestone: بوکینگ end-to-end بدون SMS/پرداخت/جلالی.

**فاز ۳ (روز ۲۶-۳۵):** `JalaliConverter`، پیکر جلالی فرانت/ادمین، `HolidayProvider`. مجزا از فاز ۲ به دلیل edge-case های جلالی (سال کبیسه شمسی).

**فاز ۴ (روز ۳۶-۴۵):** `SmsProviderInterface` + پیاده‌سازی دو provider، `NotificationDispatcher`، WP Cron یادآوری، `nby_sms_logs`.

**فاز ۵ (روز ۴۶-۵۸):** `PaymentGatewayInterface` + `ZarinpalGateway` کامل، `nby_transactions`، منطق deposit/full، تست sandbox.

**فاز ۶ (روز ۵۹-۷۰):** راه‌اندازی `license.mynobatyar.ir`، `LicenseManager`/`GracePeriodHandler`، cron اعتبارسنجی، تست سناریوهای کامل.

**فاز ۷ (روز ۷۱-۸۲):** `CalendarView`/`ListView`، `SettingsPage`، `ReportGenerator`، polish بصری (راست‌چین، فونت وزیرمتن).

**فاز ۸ (روز ۸۳-۹۰):** مستندات، صفحه فروش، آپلود مارکت‌پلیس + کانال تلگرام، QA نهایی روی staging، تگ نسخه v1.0.0.

### ⚠️ ریسک‌های کلی
- با یک نفر، ۹۰ روز فشرده است — واقع‌بینانه‌تر ۱۲۰-۱۳۰ روز برای کیفیت production-ready
- فاز ۵ (پرداخت) و فاز ۶ (لایسنس) بیشترین round-trip تست واقعی نیاز دارند — بافر زمانی اضافه در نظر گرفته شود
- داده تعطیلات رسمی نیاز به منبع قابل اعتماد و به‌روزرسانی سالانه دارد — این را به‌عنوان item عملیاتی مستمر بعد از لانچ هم ثبت کنید

---

## بخش ۷: تحلیل رقابتی و اصول طراحی برگرفته از ضعف رقبا

دو رقیب اصلی بازار بین‌المللی (که الگوی رفتاری مشابه در کلون‌های فارسی هم دیده می‌شود) بررسی شدند: **Bookly** (قدیمی‌ترین و پرنصب‌ترین، از ۲۰۱۴) و **Booknetic** (مدرن‌ترین رقیب جدی، با معماری SaaS-style).

### 7.1 ضعف‌های Bookly (بر اساس بازخورد کاربران واقعی)

| ضعف | شرح |
|---|---|
| مدل add-on تکه‌تکه | قابلیت‌های ضروری (Stripe، رزرو تکرارشونده، فیلد سفارشی، چندشعبه‌ای، پورتال مشتری/کارمند) همگی add-on مجزا و پولی هستند — کاربران آن را «نیش‌گاز قیمتی» می‌نامند |
| پشتیبانی کند | رتبه Customer Service در Capterra فقط 3.7/5؛ چرخه‌های رفت‌وبرگشت تیکت طولانی گزارش شده |
| مشکلات بعد از آپدیت وردپرس | شکایات پایداری حول رفتار پلاگین/add-on بعد از آپدیت‌های core وردپرس خوشه می‌بندند |
| سرعت صفحه پایین | کاربران افت محسوس سرعت سایت بعد از نصب را گزارش کرده‌اند |
| Lock-in لایسنس روی دامنه | کدها در Bookly Cloud ثبت می‌شوند؛ تغییر URL دامنه ثبت‌شده فقط با دخالت پشتیبانی ممکن است — توسعه local و انتقال به production را سخت می‌کند |
| اسلات‌های زمانی محدود | با انتخاب بازه ۱ ساعته، فقط اسلات‌های سر ساعت نشان داده می‌شود (مثلاً ۱۱:۳۰-۱۲:۳۰ دیده نمی‌شود) |
| عدم batch booking | برای رزرو چند بازه هم‌زمان باید هر بار دکمه "Book more" زده شود |

### 7.2 ضعف‌های Booknetic

| ضعف | شرح |
|---|---|
| همان الگوی add-on روی فیچر حیاتی | پرداخت، جلسات ویدیویی، همگام‌سازی کلندر، SMS پشت add-on های پولی Boostore هستند، نه در پلن پایه |
| بدون پلن رایگان | فقط دموی آنلاین موجود است، نسخه رایگان واقعی ندارد |
| منحنی یادگیری/راه‌اندازی | تنظیم فیچرهای پیشرفته (workflow، چندشعبه‌ای) زمان و تلاش بیشتری نسبت به رقبا می‌برد |
| سازگاری/مستندات بعد از سال اول | چون نسبتاً جدید است، مستندات و پشتیبانی بعد از سال اول می‌تواند ناهماهنگ باشد؛ رابط سفارشی با برخی تم‌ها تداخل می‌کند |
| شکایات refund/پشتیبانی | برخی کاربران از فشار برای تمدید پشتیبانی به‌جای حل مشکل گزارش داده‌اند |

### 7.3 نقشه تبدیل ضعف رقبا به اصل طراحی نوبتیار

| ضعف رقیب | اصل طراحی نوبتیار |
|---|---|
| فیچر حیاتی پشت add-on پولی جدا | SMS و زرین‌پال از همان لایسنس Pro/Business در دسترس باشند، نه add-on مجزا — برجسته‌سازی در صفحه فروش: «بدون هزینه پنهان add-on» |
| پشتیبانی کند، چرخه تیکت طولانی | کانال تلگرام پشتیبانی مستقیم با SLA مشخص (مثلاً پاسخ زیر ۲۴ ساعت) — اضافه به استراتژی GTM |
| منحنی یادگیری بالا برای کاربر غیرفنی | ویزارد نصب گام‌به‌گام فارسی (Setup Wizard) در فاز ۷ ساخت، نه فقط صفحه تنظیمات خام |
| اسلات‌های زمانی محدود/گیج‌کننده | `SlotCalculator` از ابتدا با گرانولاریتی واقعی پیاده‌سازی شود، نه فقط سر ساعت |
| Lock-in لایسنس روی دامنه | امکان self-service «انتقال لایسنس به دامنه جدید» (مثلاً ۱ بار در ماه، بدون تماس با پشتیبانی) در `LicenseManager` |
| بدون پلن رایگان واقعی (Booknetic) | پلن رایگان نوبتیار (بخش ۳.۱) حفظ شود — مزیت رقابتی حتی در مقابل بازار بین‌المللی |
| سرعت صفحه پایین بعد از نصب (Bookly) | الزام فنی غیرقابل‌مذاکره در فاز ۲: لود اسکریپت/استایل فقط در صفحات حاوی شورت‌کد (conditional asset loading) |
| عدم batch booking (Bookly) | امکان انتخاب و تأیید چند بازه زمانی در یک جلسه بوکینگ، از طراحی فرم فرانت‌اند فاز ۲ |

### 7.4 پیامد مستقیم روی سند فعلی

این تحلیل سه بخش قبلی را تقویت می‌کند، نه جایگزین آن‌ها:

- **بخش ۲ (فیچرها):** اصل «هیچ فیچر حیاتی پشت add-on پولی» باید به‌عنوان قانون طراحی محصول از روز اول ثبت شود، نه فقط یک گزینه قیمت‌گذاری
- **بخش ۴ (معماری):** `SlotCalculator` و conditional asset loading به‌عنوان acceptance criteria فاز ۲ اضافه شوند؛ متد self-service انتقال لایسنس به `LicenseManager` در فاز ۶ اضافه شود
- **بخش ۵ (GTM):** SLA پشتیبانی تلگرامی به‌عنوان یک پیام بازاریابی صریح («پشتیبانی فارسی زیر ۲۴ ساعت») در کنار تحلیل شکاف رقابتی موجود ادغام شود

---

*سند تهیه‌شده در تاریخ ۲ تیر ۱۴۰۵ (22 June 2026) — نوبتیار، نسخه استراتژی ۱.۱ (با تحلیل رقابتی Bookly/Booknetic)*
