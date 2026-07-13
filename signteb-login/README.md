# SignTeb Login — صفحه ورود اختصاصی

صفحه ورود وردپرس (`wp-login.php`) با طراحی اختصاصی برای سایت‌هایی که **SignTeb.com** طراحی می‌کند: تم تیره الهام‌گرفته از GitHub Dark، رنگ سبز دریایی برند SignTeb، لوگوی صلیب پزشکی + استتوسکوپ، ایکون‌های شناور و امضای «طراحی و توسعه: SignTeb.com».

پلاگین کاملاً مستقل است و به هیچ پلاگین دیگری وابسته نیست.

## نصب روی سایت مشتری

1. پوشه `signteb-login` را در `wp-content/plugins/` کپی کنید.
2. از پیشخوان وردپرس، پلاگین **SignTeb Login** را فعال کنید.
3. تمام — صفحه ورود بلافاصله طرح جدید را می‌گیرد. نام سایت مشتری خودکار از **تنظیمات ← همگانی** خوانده و در پیام خوش‌آمد نمایش داده می‌شود.

## سفارشی‌سازی per-client

| کار | روش |
|---|---|
| خاموش‌کردن کل طرح | `wp option update signteb_login_enabled 0` یا فیلتر `signteb_login_enabled` |
| حذف امضای SignTeb.com | `wp option update signteb_login_show_credit 0` یا فیلتر `signteb_login_show_credit` |

مثال با فیلتر (در `functions.php` قالب):

```php
add_filter('signteb_login_show_credit', '__return_false');
```

## نکات فنی

- CSS فقط در صفحه ورود لود می‌شود؛ هیچ اثری روی سرعت بقیه سایت ندارد.
- همه قواعد به `body.signteb-login` محدودند؛ فرم re-auth داخل iframe (interim login) ظاهر استاندارد وردپرس را حفظ می‌کند.
- ایکون‌های شناور `aria-hidden` و `pointer-events: none` هستند و در حالت `prefers-reduced-motion` ثابت می‌شوند.
- موقعیت‌ها با logical properties تعریف شده‌اند و در RTL/LTR هر دو درست کار می‌کنند.
- فرم‌های فراموشی رمز، بازنشانی رمز و ثبت‌نام هم همین استایل را می‌گیرند.
