# پیلار ۲ — «طراحی سایت پزشکی» | signteb.com

اجرای Master Prompt ۲/۴. صفحه پیلار تجاری روی `/tarahi-site-pezeshki/` (بررسی شد: ۴۰۴ و آزاد).

| فایل | محتوا |
|---|---|
| `technical-audit.md` | **اول این را بخوانید** — یافته‌ای که ممکن است تصمیم شما را عوض کند |
| `content.md` | متن نهایی فارسی (~۱٬۲۰۰ کلمه) + متادیتا |
| `gutenberg.html` | مارک‌آپ بلوکی آماده الصاق |
| `schema.json` | JSON-LD — `Service` + `FAQPage` |

---

## ⚠️ سه چیزی که قبل از شروع باید بدانید

### ۱. بخش عمده‌ای از این Master Prompt از قبل ساخته شده

`/medical-website-design-examples/` صرفاً یک گالری ساده نیست — از قبل **گالری فیلترشونده با ۵ فیلتر و ۲۱ پروژه**، **بخش مزایای MedCore**، **فرآیند کار ۵ مرحله‌ای**، **کیس‌استادی کلینیک چندتخصصی** و **تستیمونیال ستاره‌دار** دارد.

یعنی بندهای ۳، ۴ و ۵ از ساختار محتوایی Master Prompt عملاً موجودند. اگر صفحه جدید آن‌ها را از نو بسازد، دو صفحه رقیب هم می‌شوند.

**دو گزینه دارید** (شرح کامل در `technical-audit.md` بند ۱):
- **گزینه A:** صفحه جدید نسازید؛ همان صفحه نمونه‌کار را به پیلار تبدیل کنید. کم‌ریسک‌ترین.
- **گزینه B:** صفحه جدید = فروش، صفحه نمونه‌کار = اثبات. **این پکیج بر اساس گزینه B نوشته شده.**

### ۲. عدد CPT در Master Prompt اشتباه است

Master Prompt بند ۱ نوشته «۹ CPT». شمارش مستقیم از کد عدد **۸** می‌دهد. چون این صفحه ادعای برتری فنی می‌کند، در `content.md` فقط اعداد راستی‌آزمایی‌شده به‌کار رفته: **۸ نوع محتوا، ۱۰ نوع Schema، ۱۰ بلوک آماده**.

### ۳. فرآیند کار ۵ مرحله‌ای است، نه ۴

Master Prompt بند ۴ فرآیند ۴ مرحله‌ای خواسته، ولی صفحه نمونه‌کار از قبل **۵ مرحله** منتشر کرده. در متن از همان ۵ مرحله استفاده شد تا سایت با خودش تناقض نداشته باشد.

---

## چک‌لیست انتشار

**تصمیم اولیه**
- [ ] گزینه A یا B انتخاب شود (`technical-audit.md` بند ۱)
- [ ] اگر B: قواعد چهارگانه ضد-cannibalization پذیرفته شود

**ساخت صفحه**
- [ ] صفحه با اسلاگ `tarahi-site-pezeshki` ساخته شود
- [ ] محتوا از `gutenberg.html` یا نگاشت Elementor پیاده شود
- [ ] Title و Meta Description از `content.md` در Rank Math وارد شود
- [ ] گره `Service` از `schema.json` اضافه شود (`_README` حذف شود)
- [ ] بخش نمونه‌کار فقط **۶ کارت teaser** باشد — گالری بازسازی نشود
- [ ] یادداشت‌های «پس از انتشار حذف شود» پاک شوند
- [ ] لینک صفحه پکیج‌ها (`href="#"`) پس از انتشار `page-03-pricing` تکمیل شود

**لینک‌سازی** (`technical-audit.md` بند ۵)
- [ ] کارت «طراحی سایت پزشکی» هوم‌پیج به این صفحه لینک شود
- [ ] آیتم منوی «طراحی سایت پزشکی» با **https** به این صفحه وصل شود (الان به آرشیو دسته با http می‌رود)
- [ ] لینک دوطرفه با `/medical-website-design-examples/`
- [ ] Cross-sell دوطرفه با `/seo-pezeshki/`
- [ ] لینک به `/clinic-appointment-scheduling-sign-teb/`
- [ ] `/category/طراحی-سایت-پزشکی/` روی `noindex, follow` تنظیم شود

**پس از انتشار**
```bash
curl -s https://signteb.com/tarahi-site-pezeshki/ | grep -c '<link rel="canonical"'      # باید ۱ باشد
curl -s https://signteb.com/tarahi-site-pezeshki/ | grep -o '"@type":"FAQPage"' | wc -l   # باید ۱ باشد
```
- [ ] Google Rich Results Test بدون هشدار `Duplicate field`

---

## نگاشت Elementor

| بخش `content.md` | ویجت |
|---|---|
| Hero | Heading (H1) + Text Editor + Button ×۲ |
| چرا سایت‌ساز رایگان کافی نیست | Heading + چهار Icon Box |
| مزیت فنی MedCore | چهار Icon Box با اعداد ۸ / ۱۰ / — / ۱۰ |
| نمونه‌کار teaser | Image Box ×۶ در گرید + Button |
| فرآیند ۵ مرحله‌ای | Text Editor کوتاه + لینک (تکرار نشود) |
| هزینه | Heading + Text Editor + Button |
| FAQ | Accordion (نمایش) — اسکیما از Rank Math |
| CTA پایانی | Container + Form + دکمه واتساپ + تلفن دفتر |

**نکته عملکردی:** Revslider را در این صفحه غیرفعال کنید و در Perfmatters اسکریپت‌های WooCommerce را برای این URL خاموش کنید.
