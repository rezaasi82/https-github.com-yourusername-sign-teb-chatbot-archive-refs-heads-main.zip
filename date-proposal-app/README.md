# Date Proposal App 💖

یک اپ تک‌کامپوننتی React برای دعوت به قرار — موبایل‌فرست، RTL، با گلس‌مورفیسم، گرادیان‌های پاستلی و انیمیشن‌های Framer Motion.

فایل اصلی: [`DateProposalApp.jsx`](./DateProposalApp.jsx) — همه‌چیز در یک کامپوننت.

## نصب

```bash
npm i react react-dom framer-motion
npm i -D tailwindcss @tailwindcss/vite
```

```jsx
import DateProposalApp from "./DateProposalApp";

export default function App() {
  return <DateProposalApp />;
}
```

## فونت فارسی (اختیاری)

کامپوننت هیچ فونتی را از شبکه لود نمی‌کند و روی استک `Vazirmatn → IRANSans → Tahoma` می‌افتد.
برای ظاهر بهتر، Vazirmatn را در `index.html` خودت اضافه کن:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
```

## بدون وابستگی اضافه

سه چیزی که معمولاً پکیج جدا می‌خواهند، اینجا بومی پیاده‌سازی شده‌اند:

| قابلیت | پیاده‌سازی |
|---|---|
| کانفتی | Canvas + `requestAnimationFrame` (بدون `canvas-confetti`) |
| تقویم جلالی | `Intl.DateTimeFormat('fa-IR-u-ca-persian')` (بدون `moment-jalaali`) |
| افکت صوتی | نوسان‌ساز WebAudio + `navigator.vibrate` (بدون فایل صوتی) |

## جریان اپ

۱. سوال + دکمه‌ی «نه»ی بازیگوش → ۲. انتخاب روز و ساعت → ۳. منو و اکسترا → ۴. جشن، بلیط قرار و اشتراک‌گذاری تلگرام.

## نکته‌ی طراحی: دکمه‌ی «نه»

دکمه ابتدا خاکستری و «خراب» است، با تولتیپ طعنه‌آمیز؛ بعد فرار می‌کند و در تلاش پنجم تسلیم می‌شود و به «آره 💖» تبدیل می‌شود.

دو نکته‌ی ظریف که در پیاده‌سازی رعایت شده:

- **روی موبایل hover وجود ندارد.** اگر منطق فقط به hover و کلیکِ دقیق روی دکمه‌ی متحرک وابسته باشد، کاربر موبایل ممکن است هیچ‌وقت به مرحله‌ی تبدیل نرسد. برای همین یک «منطقه‌ی شکار» با ارتفاع ثابت دور دکمه هست که تپِ خطا رفته را هم به‌عنوان تلاش حساب می‌کند.
- **تپِ تبدیل‌کننده، فرم را ثبت نمی‌کند.** بعد از تبدیل، ۹۰۰ms کلیک نادیده گرفته می‌شود تا کاربر لحظه‌ی «نه → آره» را ببیند.

جابه‌جایی دکمه هم عمداً داخل عرض کارت محدود شده تا زیر `overflow-hidden` بریده و غیرقابل‌لمس نشود.
