# CLAUDE.md — نوبتیار (Nobatyar) Booking Plugin

> این فایل context پروژه برای Claude Code است. هر بار کار روی این پروژه شروع می‌شود، این فایل باید خوانده شود.

## خلاصه پروژه

پلاگین وردپرس مستقل (غیرپزشکی) برای رزرو نوبت — قابل فروش به سالن، باشگاه، مشاوره حقوقی، آموزشگاه، کلینیک زیبایی و هر کسب‌وکار محلی نوبت‌محور. استخراج‌شده و عمومی‌سازی‌شده از یک موتور بوکینگ پزشکی موجود (SignTeb Medical Core).

**نام:** نوبتیار (Nobatyar) — namespace پایه: `Nobatyar\` — پیشوند جدول دیتابیس: `nby_` — شورت‌کد: `[nobatyar_booking]`

**قانون طلایی محصول:** هیچ فیچر حیاتی (SMS، پرداخت، تقویم جلالی) پشت add-on پولی جداگانه نباشد — این مستقیماً ضعف اصلی هر دو رقیب اصلی بازار (Bookly، Booknetic) است که نوبتیار باید از آن پرهیز کند.

---

## Generalization Rules (از کد پزشکی قدیمی)

| Old (Medical) | New (Generic) |
|---|---|
| Doctor | Provider (سرویس‌دهنده) — قابل override به‌عنوان "Stylist"/"Consultant"/... |
| Medical Specialty | Service Category |
| شماره نظام پزشکی | `license_field` — عمومی، اختیاری، label قابل تغییر |
| Appointment | Booking |
| `STMC_*` / `SignTeb` namespace | `Nobatyar\*` — **بدون هیچ رفرنس باقی‌مانده** |
| `[stmc_appointment]` | `[nobatyar_booking]` (با alias موقت در صورت migration) |
| متن هاردکد ("دکتر"، "بیمار"، "ویزیت") | از طریق `TerminologyMap`, قابل override در تنظیمات |
| `stmc_*` جدول‌ها | migration یک‌باره به `nby_*` |

---

## Folder Structure (PSR-4, namespace `Nobatyar\`)

```
nobatyar-booking/
├── nobatyar-booking.php          # Bootstrap
├── composer.json
├── uninstall.php
├── includes/
│   ├── Core/            # Plugin.php, Activator.php, Deactivator.php, Autoloader.php
│   ├── Booking/         # BookingEngine, BookingRepository, BookingStatus, SlotCalculator
│   ├── Provider/        # Provider, ProviderRepository, AvailabilityManager
│   ├── Service/         # Service, ServiceRepository
│   ├── Calendar/        # JalaliConverter, HolidayProvider
│   ├── Notifications/   # SmsProviderInterface + Providers/{Kavehnegar,Melipayamak}, WhatsApp/, EmailNotifier, NotificationDispatcher
│   ├── Payment/         # PaymentGatewayInterface + Gateways/{Zarinpal,IdPay,NextPay}, TransactionRepository
│   ├── License/         # LicenseManager, LicenseStatus, GracePeriodHandler
│   ├── Admin/           # Dashboard/{CalendarView,ListView}, Settings/SettingsPage, Reports/ReportGenerator
│   ├── Frontend/        # Shortcode/BookingShortcode, Block/BookingBlock
│   ├── Rest/Controllers/ # Booking, Availability, Payment, License
│   └── Labels/          # TerminologyMap
├── assets/{js,css}/
├── templates/
└── languages/
```

**اصل معماری:** هر دومین (Booking, Provider, Payment, Notifications) interface-driven و مستقل — افزودن گیت‌وی/SMS provider جدید فقط یک کلاس جدید نیاز دارد، نه تغییر در core.

---

## Database Schema (MySQL, prefix `{wp_prefix}nby_`)

جدول‌های اصلی: `providers`, `services`, `provider_services` (M2M), `availability`, `availability_exceptions`, `bookings`, `sms_logs`, `transactions`, `license`.

نکات کلیدی schema:
- `bookings.booking_datetime` همیشه **میلادی** ذخیره می‌شود (source of truth) — تبدیل جلالی فقط در لایه نمایش
- `bookings.status` ENUM: `pending, confirmed, done, cancelled, no_show`
- ایندکس‌های لازم: `idx_provider_datetime (provider_id, booking_datetime)`, `idx_status (status)`
- `license.domain_hash` برای اعتبارسنجی دامنه، نه دامنه خام (privacy + امنیت)

> Schema کامل SQL در سند اصلی پروژه (`nobatyar-strategy.md`) بخش ۴.۲ موجود است — قبل از `Activator.php` آن را مرور کن.

---

## REST API (namespace `nobatyar/v1`)

| Method | Endpoint | نکته |
|---|---|---|
| GET | `/availability` | پارامتر provider_id + service_id + date؛ پاسخ باید گرانولاریتی واقعی اسلات بدهد (نه فقط سر ساعت — ضعف شناخته‌شده Bookly) |
| POST | `/bookings` | عمومی، nonce-protected، rate-limited (IP throttle) |
| GET/PATCH | `/bookings/{id}` | ادمین auth برای PATCH |
| POST | `/payments/init` | شروع پرداخت، ریدایرکت زرین‌پال |
| GET | `/payments/callback` | تأیید گیت‌وی |
| POST | `/license/activate` | فعال‌سازی روی دامنه |
| GET | `/license/status` | چک داخلی |

Action hooks: `nobatyar_booking_created`, `nobatyar_booking_status_changed`, `nobatyar_payment_verified`, `nobatyar_license_status_changed`. فیلترها: `nobatyar_available_slots`, `nobatyar_terminology_label`.

Cron: `nobatyar_license_check` (daily), `nobatyar_send_reminders` (hourly).

---

## License Model (بدون سرویس‌های تحریم‌شده)

سرور مستقل خارجی: `license.mynobatyar.ir` — اعتبارسنجی روزانه (cron) با HMAC-signed response، کش محلی برای جلوگیری از قفل فوری در صورت قطعی سرور.

**فلوی Enforcement:**
```
انقضا (روز 0) → یادآوری ایمیل/SMS
   ↓
Grace Period (14 روز) → Soft lock: SMS و پرداخت غیرفعال، بوکینگ پایه فعال
   ↓
بعد از Grace → Full lock: فرانت پیام غیرفعال نشان می‌دهد؛ داده‌ها هرگز حذف نمی‌شوند
```

**باید پیاده‌سازی شود (نقطه ضعف رقیب که نوبتیار باید حل کند):** امکان self-service «انتقال لایسنس به دامنه جدید» (مثلاً ۱ بار در ماه، بدون نیاز به تماس با پشتیبانی) — Bookly این را ندارد و کاربران از آن شاکی‌اند.

---

## اصول طراحی برگرفته از ضعف رقبا (Non-negotiable Acceptance Criteria)

این‌ها باید در کد رعایت شوند، نه فقط در استراتژی:

1. **Conditional asset loading** — JS/CSS فقط در صفحات حاوی شورت‌کد لود شود (ضعف Bookly: افت سرعت سایت بعد از نصب)
2. **SlotCalculator با گرانولاریتی واقعی** — نه فقط اسلات‌های سر ساعت (ضعف Bookly: با بازه ۱ ساعته فقط اسلات‌های ساعت کامل نشان می‌دهد)
3. **Batch booking** — امکان انتخاب/تأیید چند بازه در یک جلسه (ضعف Bookly: هر بار باید دکمه جدید زده شود)
4. **هیچ فیچر حیاتی پشت add-on پولی مجزا** — SMS و زرین‌پال در همان لایسنس Pro/Business (ضعف هر دو رقیب: پرداخت/SMS/کلندرسینک add-on پولی است)
5. **Setup Wizard فارسی گام‌به‌گام** — نه فقط صفحه تنظیمات خام (ضعف Booknetic: منحنی یادگیری بالا برای کاربر غیرفنی)
6. **انتقال لایسنس self-service** — بدون دخالت پشتیبانی (ضعف Bookly: lock-in دامنه)
7. **تقویم جلالی به‌عنوان شهروند درجه‌یک از روز اول** — نه افترتاوت/patch روی کتابخانه میلادی

---

## فاز فعلی پروژه

نقشه راه کامل ۹۰ روزه (۸ فاز) در سند اصلی (`nobatyar-strategy.md` بخش ۶) موجود است. فاز فعلی: **[به‌روزرسانی کن وقتی شروع شد]**

ترتیب فازها: Foundation → Booking Engine → Jalali Calendar → SMS → Payment → License → Admin UI → Marketplace Launch.

---

## ارجاع

سند کامل استراتژی/معماری (تمام ۷ بخش، شامل جدول‌های قیمت‌گذاری، تحلیل GTM، و تحلیل رقابتی کامل Bookly/Booknetic) در فایل جداگانه `nobatyar-strategy.md` موجود است — برای جزئیات بیشتر از هر بخش به آن مراجعه کن.
