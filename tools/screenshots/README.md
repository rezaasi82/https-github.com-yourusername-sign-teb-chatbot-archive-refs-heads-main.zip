# بازتولید اسکرین‌شات‌ها

اسکرین‌شات‌های `marketing/screenshots/` و تصاویر `preview/images/` از **خودِ فایل‌های
view افزونه** ساخته می‌شوند، نه از یک ماکت جدا. بنابراین هر تغییری در رابط کاربری با یک
بار اجرای دوباره به تصاویر منتقل می‌شود و تصویر هیچ‌وقت از کد عقب نمی‌ماند.

این پوشه بیرون از `pezhkam/` است و در هیچ‌کدام از زیپ‌های تحویلی قرار نمی‌گیرد.

## چرا اینطور

وردپرس در محیط توسعهٔ این پروژه قابل نصب نیست. `render.php` به‌جای وردپرس، حداقلِ
توابع لازم را stub می‌کند (`esc_html`, `__`, `checked`, `number_format_i18n`, `$wpdb` و …)
و بعد فایل view واقعی را `require` می‌کند. کلاس‌های واقعی افزونه — از جمله
`\Pezhkam\Admin\Icon` و `\Pezhkam\Admin\PageHeader` — با اتولودر واقعی بار می‌شوند.

`data.php` فقط دادهٔ نمایشی می‌دهد: نام‌ها و شماره‌های ساختگی، بدون هیچ اطلاعات واقعی.

view‌هایی که wrapper خودشان را ندارند (`settings`، `dashboard`، `conversations-list`،
`conversation-single`) در تابع `demo_chrome()` داخل همان `.wrap.pzk-admin` + `PageHeader`
+ نوار تب پیچیده می‌شوند که کلاس والدشان تولید می‌کند.

## اجرا

```bash
cd tools/screenshots

# ۱) رندر HTML از view های واقعی
for v in premium-dashboard settings settings:integrations crm-board seo \
         branches dashboard conversations-list conversation-single widget widget-teaser; do
    php render.php "$v" > "out/${v/:/-}.html"
done
php make_extras.php > out/admin-notify.html

# ۲) دارایی‌ها را کنار HTML بگذارید (لینک‌های نسبی)
mkdir -p out/assets && cp -r ../../pezhkam/assets/{css,js,fonts} out/assets/

# ۳) اسکرین‌شات
node capture.js          # -> png/  برای marketing/screenshots
node preview-shots.js    # -> raw/  برای preview/images
```

سپس تصاویر `raw/` با Pillow به ابعاد ثابت `preview/images/` برازش داده می‌شوند
(هم‌عرض شدن، بعد برش از بالا یا وسط‌چین با پس‌زمینه `#f0f0f1`).

## نکته‌های عملی

- **Chromium** در `/opt/pw-browsers/chromium-1194/chrome-linux/chrome` و Playwright
  سراسری در `/opt/node22/lib/node_modules` است.
- `deviceScaleFactor: 2` می‌دهد تا خروجی رتینا باشد و با ابعاد قبلی بخواند.
- برای گرفتن ارتفاع درست، اول `scrollHeight` اندازه گرفته می‌شود و viewport به همان
  اندازه ست می‌شود؛ `fullPage: true` به‌تنهایی صفحات کوتاه را تا ارتفاع viewport با
  فضای خالی پر می‌کند.
- ویجت در حالت شورت‌کد کلاس `pzk-inline` می‌گیرد و قانون `.pzk-inline .pzk-panel`
  ارتفاع را روی ۵۲۰px قفل می‌کند؛ برای اینکه کل گفتگو در کادر بیفتد باید با **همان
  وزن سلکتور** بازنویسی شود، نه با `.pzk-panel` تنها.
- ویجت زیر عرض ۴۸۰px وارد برک‌پوینت موبایل می‌شود؛ برای نمای دسکتاپ viewport را
  پهن‌تر بگیرید.
- در دادهٔ نمایشی، شمارهٔ تلفن را فقط با رقم بنویسید. کاراکترهای خنثی مثل `•••` وسط
  عدد، در چیدمان راست‌به‌چپ ترتیب نمایش را به‌هم می‌ریزند.
