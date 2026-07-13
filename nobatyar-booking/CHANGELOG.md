# Changelog

تمام تغییرات قابل توجه این پروژه در این فایل ثبت می‌شود.

## [1.0.0] - 2026-06-23

### اضافه شد

- **موتور رزرو (Booking Engine)**: `BookingEngine`, `BookingRepository`, `SlotCalculator` با محاسبه اسلات‌های واقعی (گرانولاریتی دقیق، نه فقط سر ساعت) و جلوگیری از تداخل زمانی
- **سرویس‌دهنده‌ها و خدمات**: `ProviderRepository`, `ServiceRepository`, `AvailabilityManager` با رابطه چندبه‌چند بین سرویس‌دهنده و خدمت
- **تقویم جلالی**: `JalaliConverter` (تبدیل دوطرفه میلادی/شمسی) و `HolidayProvider` به‌عنوان شهروند درجه‌یک سیستم
- **اطلاع‌رسانی پیامکی**: معماری مبتنی بر `SmsProviderInterface` با پشتیبانی کاوه‌نگار و ملی‌پیامک، به‌همراه `EmailNotifier` و `NotificationDispatcher`
- **پرداخت**: معماری مبتنی بر `PaymentGatewayInterface` با پشتیبانی زرین‌پال، آیدی‌پی، نکست‌پی و `TransactionRepository`
- **لایسنس**: `LicenseManager` با اعتبارسنجی HMAC-signed روزانه، `GracePeriodHandler` (۱۴ روز Grace بدون حذف داده)، و امکان انتقال لایسنس self-service
- **REST API**: کنترلرهای `Booking`, `Availability`, `Payment`, `License` زیر namespace `nobatyar/v1`
- **پنل مدیریت**:
  - `ListView` — لیست نوبت‌ها با فیلتر و تغییر وضعیت محافظت‌شده با nonce
  - `CalendarView` — نمایش تقویمی ماهانه جلالی با شمارش نوبت‌های هر روز
  - `ReportGenerator` — گزارش تجمیعی وضعیت‌ها و درآمد (فقط تراکنش‌های موفق)
  - `SettingsPage` — ویزارد تنظیمات ۵ مرحله‌ای فارسی (اصطلاحات، پیامک، پرداخت، لایسنس، خلاصه)
  - `AdminMenu` — یکپارچه‌سازی با بارگذاری شرطی منابع CSS/JS
- **اصطلاحات قابل تغییر**: `TerminologyMap` برای override نام‌های "سرویس‌دهنده"، "مشتری" و غیره بدون ترجمه دستی
- مستندات راه‌اندازی (`readme.txt`) و این فایل تغییرات
