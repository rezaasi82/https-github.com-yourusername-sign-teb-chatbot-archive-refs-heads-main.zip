# پیلار ۱ — «سئو پزشکی» | signteb.com

اجرای Master Prompt ۱/۴. صفحه پیلار تجاری برای کلمه «سئو پزشکی» روی `/seo-pezeshki/`.

## فایل‌ها

| فایل | محتوا |
|---|---|
| `content.md` | متن نهایی فارسی (~۱٬۴۵۰ کلمه) + متادیتا + نقشه کلمات کلیدی |
| `gutenberg.html` | مارک‌آپ بلوکی آماده الصاق در «ویرایشگر کد» وردپرس |
| `schema.json` | JSON-LD — فقط گره‌هایی که Rank Math نمی‌سازد |
| `technical-audit.md` | **ابتدا این را بخوانید** — ممیزی سایت زنده، تصمیم اسکیما، cannibalization، نقشه لینک |

---

## ⚠️ دو موضوع که قبل از شروع باید بدانید

### ۱. فرض Master Prompt درباره استک، برای signteb.com درست نیست

Master Prompt نوشته صفحه روی **MedCore + Gutenberg** ساخته می‌شود. بررسی HTML زنده نشان داد signteb.com روی **قالب Xtra + Elementor Pro + Rank Math** کار می‌کند و MedCore و `signteb-blocks` روی آن **فعال نیستند** (صفر ارجاع).

MedCore استک سایت‌های **مشتری** است (مثل drhzamani.com)، نه سایت خود ساین‌طب.

**پیامد:** قانون «MedCore خودکار FAQPage می‌سازد» اینجا اعمال نمی‌شود. مرجع اسکیما در این صفحه **Rank Math** است. قاعده «فقط یک منبع FAQPage» همچنان پابرجاست، ولی طرف‌های درگیر عوض شده‌اند — `technical-audit.md` بخش ۲.

به‌همین‌دلیل هر دو مسیر پیاده‌سازی تحویل داده شده: `gutenberg.html` طبق درخواست اصلی، و نگاشت Elementor پایین همین فایل برای استک واقعی.

### ۲. 🔴 صفحه‌ای که قرار است به آن لینک دهیم، باگ ایندکسینگ دارد

`/seo-medicine-case-study/` — همان صفحه‌ای که Master Prompt آن را «اثبات اعتبار» این پیلار تعیین کرده — **دو تگ canonical متناقض** دارد و دومی به `/seo-portfolio/` اشاره می‌کند که **۴۰۴** است. ضمناً همان صفحه `WebSite` را ۳ بار و `Organization` را ۲ بار در اسکیما تکرار می‌کند.

**این باگ اولویتش از خودِ صفحه پیلار بالاتر است.** لینک دادن به صفحه‌ای که ممکن است از ایندکس خارج شود، ارزش اثباتی پیلار را از بین می‌برد. جزئیات و اقدام اصلاحی: `technical-audit.md` بخش ۱.

---

## چک‌لیست انتشار

**قبل از ساخت صفحه**
- [ ] باگ canonical در `/seo-medicine-case-study/` رفع شود (`technical-audit.md` §۱)
- [ ] اعداد بخش «نتایج واقعی» با گزارش تازه GSC راستی‌آزمایی شوند (`technical-audit.md` §۴)
- [ ] تصمیم مسیر A یا B برای FAQPage گرفته شود (`technical-audit.md` §۲)

**ساخت صفحه**
- [ ] صفحه با اسلاگ `seo-pezeshki` ساخته شود (بررسی شد: آزاد است)
- [ ] محتوا از `gutenberg.html` یا نگاشت Elementor پیاده شود
- [ ] Title و Meta Description از `content.md` در Rank Math وارد شود
- [ ] گره `Service` از `schema.json` اضافه شود (کلید `_README` حذف شود)
- [ ] یادداشت‌های «پس از انتشار حذف شود» از داخل صفحه پاک شوند
- [ ] لینک صفحه پکیج‌ها (`href="#"`) پس از آماده‌شدن Master Prompt ۳ تکمیل شود

**لینک‌سازی** (`technical-audit.md` §۵)
- [ ] کارت «تبلیغات و جذب بیمار آنلاین» در هوم‌پیج به این صفحه لینک شود (الان به `/برندینگ-دیجیتال-پزشکی/` می‌رود)
- [ ] آیتم «سئو پزشکی» به زیرمنوی «دیجیتال مارکتینگ» اضافه شود
- [ ] لینک دوطرفه با `/seo-medicine-case-study/` برقرار شود
- [ ] لینک از `/anti-sanction-seo-continuity/` و `/healthcare-seo-optimization-strategies/` اضافه شود
- [ ] Title و H1 پست `/healthcare-seo-optimization-strategies/` برای رفع cannibalization اصلاح شود (`technical-audit.md` §۳)

**پس از انتشار**
```bash
curl -s https://signteb.com/seo-pezeshki/ | grep -c '<link rel="canonical"'      # باید ۱ باشد
curl -s https://signteb.com/seo-pezeshki/ | grep -o '"@type":"FAQPage"' | wc -l   # باید ۱ باشد
```
- [ ] Google Rich Results Test بدون هشدار `Duplicate field`
- [ ] ثبت URL در GSC و ساخت فیلتر ذخیره‌شده برای رصد

---

## نگاشت Elementor (استک واقعی سایت)

| بخش `content.md` | ویجت Elementor |
|---|---|
| Hero | Heading (H1) + Text Editor + Button ×۲ (داخل یک Container) |
| چرا فرق دارد | Heading (H2) + سه Heading (H3) + Text Editor |
| فرآیند ۵ مرحله‌ای | Icon List یا پنج Icon Box در Container گریدی |
| نتایج واقعی | Text Editor + Counter (برای ۹۹۳ / ۹۱۳ / ۳۱٬۳۴۸) + Button |
| سئو محلی | Heading + Text Editor |
| Cluster Hub | Icon Box ×۵ — **موارد «به‌زودی» بدون لینک** |
| هزینه | Heading + Text Editor + Button |
| FAQ | Accordion (فقط نمایش — اسکیما از Rank Math) |
| CTA پایانی | Container + Heading + Form + Button واتساپ |

**نکته عملکردی:** صفحه باید سبک بماند. از Revslider در این صفحه استفاده نکنید و در Perfmatters اسکریپت‌های غیرلازم (WooCommerce، Revslider) را برای این URL غیرفعال کنید.
