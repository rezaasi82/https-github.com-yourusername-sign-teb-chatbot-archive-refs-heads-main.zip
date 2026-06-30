# SignTeb AI Chat — دستیار چت هوشمند پزشکی

یک افزونه‌ی **مستقل وردپرس** که روی **هر سایت پزشکی با هر قالبی** نصب می‌شود و یک چت‌بات هوشمند به سایت اضافه می‌کند تا بازدیدکننده را به **بیمار رزرو‌شده** تبدیل کند. هر پاسخ در نهایت کاربر را به‌سمت یک اقدام (رزرو نوبت، واتس‌اپ، یا تماس) هدایت می‌کند.

برای استفاده **هیچ پیش‌نیاز یا اکوسیستم خاصی لازم نیست** — کافی است افزونه را نصب کنید، کلید API را وارد کنید و پروفایل کلینیک را پر کنید. اگر افزونه‌ی اختیاری **SignTeb Medical Core** روی همان سایت فعال باشد، چت‌بات به‌صورت خودکار پزشکان/خدمات/اطلاعات تماس را زنده از آن می‌خواند؛ در غیر این صورت از تنظیمات داخلی خودش استفاده می‌کند.

### قابل فروش / White-Label

- نام دستیار، آواتار، رنگ‌ها، پیام‌ها و متن امضای پایین ویجت همگی از پنل قابل تغییرند (هیچ برندینگی هاردکد نیست — امضای پایین کاملاً قابل حذف است).
- پنل مدیریت چندزبانه: فارسی + عربی + انگلیسی (`languages/` شامل `.pot` و `.po/.mo` برای ar و en).
- کلید فعال‌سازی **مستقل برای هر دامنه**، بدون وابستگی به هیچ اکانت بیرونی.
- **حالت آزمایشی**: تا ۵۰ پیام رایگان پیش از فعال‌سازی لایسنس (قابل تنظیم با فیلتر `stmc_chat_trial_limit`).

---

## ویژگی‌ها

- 🤖 موتور گفتگوی مبتنی بر **Anthropic Claude** با لایه‌ی انتزاع provider (قابلیت افزودن OpenAI به‌عنوان fallback)
- 🧠 **System Prompt پویا**: نام کلینیک، تخصص، خدمات و قیمت‌های زنده از دیتابیس
- 🛡️ **لایه ایمنی پزشکی** غیرقابل‌حذف: بدون تشخیص/تجویز، با disclaimer خودکار و توقف فوری در موارد اورژانسی
- 💬 ویجت شناور **بدون jQuery** (Vanilla JS)، RTL کامل، Glassmorphism سرمه‌ای/طلایی، تمام‌صفحه در موبایل، افکت تایپ
- 🎯 کارت **CTA inline** هنگام تشخیص نیت رزرو
- 📊 پنل مدیریت: تاریخچه مکالمات، لاگ لید، نرخ تبدیل، پرتکرارترین پرسش‌ها (سیگنال شکاف محتوایی برای سئو)
- 🔐 ذخیره **رمزنگاری‌شده** کلید API (نه plaintext)
- 🌐 سازگار با **Polylang** و چندزبانه (فارسی/عربی/انگلیسی، تشخیص خودکار زبان)
- ⚡ سازگار با هاست‌های ایرانی: `wp_remote_post` با timeout کوتاه، fallback به `admin-ajax`، JSON محافظت‌شده با `ob_start`
- 🏷️ معماری لایسنس سالانه آماده از روز اول

---

## نصب

1. پوشه‌ی `signteb-ai-chat` را در `wp-content/plugins/` کپی کنید (یا فایل ZIP را از مسیر **افزونه‌ها ← افزودن ← بارگذاری** آپلود کنید).
2. افزونه را **فعال** کنید — جدول‌های دیتابیس به‌صورت خودکار ساخته می‌شوند.
3. به منوی **SignTeb ← تنظیمات** بروید.
4. کلید API خود را وارد کنید و در صورت غیرفعال‌بودن Medical Core، **پروفایل کسب‌وکار** (نام کلینیک، تلفن، واتس‌اپ، خدمات، لینک رزرو) را کامل کنید.
5. تمام! ویجت در گوشه‌ی سایت ظاهر می‌شود.

---

## نیازمندی‌ها

- WordPress 5.8+
- PHP 8.1+
- کلید API از Anthropic (یا OpenAI برای fallback)

---

## معماری (خلاصه)

```
signteb-ai-chat/
├── signteb-ai-chat.php          # Bootstrap + هدر افزونه
├── uninstall.php
├── includes/
│   ├── Core/          # Autoloader, Plugin, Activator, Deactivator, Settings, Encryption, JsonGuard
│   ├── Database/      # Schema + Conversation/Message Repositories
│   ├── AI/            # AIManager, SystemPromptBuilder, LanguageDetector, CtaDetector, Providers/{Anthropic,OpenAI}
│   ├── Safety/        # MedicalSafetyFilter (لایه post-processing)
│   ├── Integration/   # MedicalCoreBridge (decoupled-but-integratable)
│   ├── RateLimit/     # RateLimiter
│   ├── License/       # LicenseManager
│   ├── Rest/          # ChatController + Sanitizer
│   ├── Ajax/          # ChatAjaxHandler (fallback)
│   ├── Frontend/      # Widget (conditional asset loading)
│   └── Admin/         # AdminMenu, SettingsPage, ConversationsPage, StatsPage + views/
├── assets/{js,css}/
└── templates/widget.php
```

تمام دامنه‌ها interface-driven و مستقل‌اند؛ افزودن provider جدید AI فقط یک کلاس جدید نیاز دارد.

---

## هوک‌های توسعه‌دهنده

| نوع | نام | توضیح |
|---|---|---|
| filter | `stmc_chat_system_prompt` | تغییر system prompt نهایی |
| filter | `stmc_chat_should_render` | کنترل نمایش ویجت |
| filter | `stmc_chat_trial_limit` | تعداد پیام رایگان نسخه آزمایشی (پیش‌فرض ۵۰) |
| filter | `stmc_chat_license_is_valid` | منطق اعتبارسنجی لایسنس |
| action | `stmc_chat_lead_detected` | هنگام تشخیص لید (`$conversation_id, $cta_type`) |
| action | `stmc_chat_license_status_changed` | تغییر وضعیت لایسنس |

برای اتصال Medical Core می‌توانید فیلتر `stmc_get_clinic_nap` را پیاده‌سازی کنید تا NAP زنده برگردد.

---

## امنیت

- nonce روی همه‌ی endpointها (REST `wp_rest` + admin-ajax `stmc_chat_nonce`)
- sanitize/escape کامل ورودی و خروجی
- rate limiting per-IP/session
- کلید API رمزنگاری‌شده با AES-256-CBC بر پایه‌ی saltهای وردپرس
- بدون استفاده از `exec()`/`proc_open()`

---

## لایسنس

GPL-2.0-or-later
