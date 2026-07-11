# Medora Cloud Platform (Level 2)

سرویس بک‌اند مستقل برای مانیتورینگ ناوگان نصب‌های **Medora AI**، دریافت heartbeat، سرور لایسنس امضاشده، مانیتورینگ realtime و هشدار تلگرام. **بدون هیچ وابستگی خارجی** — فقط ماژول‌های داخلی Node.js (نسخه‌ی ۱۸+).

> این سرویس جدا از افزونه‌ی وردپرس اجرا می‌شود (مثلاً روی `cloud.medora.ai`). افزونه از طریق `SWC_Cloud_Client` به آن heartbeat می‌زند.

## اجرا

```bash
cd medora-cloud
cp .env.example .env          # مقادیر را پر کنید (به‌ویژه MEDORA_SECRET و ADMIN_TOKEN)
npm start                     # یا: node src/server.js
```

هیچ `npm install` لازم نیست (بدون dependency).

## اتصال افزونه

در وردپرس: **Medora AI → اتصال‌ها و خروجی → Medora Cloud**
- «فعال‌سازی» را بزنید.
- آدرس Cloud: `https://cloud.example.com/v1/heartbeat`
- «کلید امنیتی»: **دقیقاً برابر** `MEDORA_SECRET` باشد.

افزونه هنگام فعال‌سازی یک install و سپس روزانه یک heartbeat امضاشده می‌فرستد.

## امنیت
هر بدنه‌ی install/heartbeat با HMAC-SHA256 تأیید می‌شود — هدر `X-Medora-Sign: sha256=<hmac(rawBody, secret)>` دقیقاً مطابق افزونه؛ به‌همراه بررسی تازگی `X-Medora-Time` (ضد replay). پاسخِ لایسنس هم امضا می‌شود تا افزونه بتواند صحت آن را بررسی کند.

## Endpoints

| Method | Path | توضیح |
|---|---|---|
| POST | `/v1/install` | ثبت/به‌روزرسانی یک نصب (امضاشده) |
| POST | `/v1/heartbeat` | تله‌متری روزانه (امضاشده) |
| GET | `/v1/license/:domain` | وضعیت لایسنس امضاشده (پایه‌ی فاز ۸) |
| GET | `/v1/update/latest` | فید نسخه + checksum |
| POST | `/v1/alert` | رله‌ی هشدار داخلی (نیازمند `?token=ADMIN_TOKEN`) |
| GET | `/admin?token=…` | داشبورد مانیتورینگ realtime |
| GET | `/admin/stream?token=…` | جریان رویداد SSE |
| GET | `/healthz` | سلامت سرویس |

## تله‌متری ارسالی افزونه (بدون داده‌ی بیمار)
`domain` (هش)، `plugin_version`، `wp_version`، `php_version`، `locale`، `timezone`، `counts{messages,leads,bookings,active_24h}`، `license{status,active}`.

## داشبورد
`/admin?token=YOUR_ADMIN_TOKEN` — کارت‌های کل نصب‌ها/پیام‌ها/لیدها/رزرو/لایسنس‌های فعال، جدول نصب‌ها، کشورها، و **فید زنده‌ی رویدادها بدون رفرش** (SSE). هشدار تلگرام روی «نصب جدید» و «هشدارها»، و **گزارش روزانه‌ی خودکار** به تلگرام.

## مقیاس
ذخیره‌سازی پیش‌فرض یک فایل JSON است (مناسب چند هزار نصب). برای مقیاس بزرگ‌تر فقط `src/store.js` را با Postgres/Redis جایگزین کنید؛ بقیه‌ی کد فقط از طریق API آن ماژول با داده کار می‌کند.

## استقرار (نمونه با systemd)
```ini
[Service]
WorkingDirectory=/opt/medora-cloud
ExecStart=/usr/bin/node src/server.js
EnvironmentFile=/opt/medora-cloud/.env
Restart=always
```
پشت nginx با TLS قرار دهید (Let's Encrypt) و `cloud.medora.ai` را به آن اشاره دهید.
