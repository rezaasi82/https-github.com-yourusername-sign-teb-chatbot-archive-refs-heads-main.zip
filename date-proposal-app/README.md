# Date Proposal App 💖

یک اپ تک‌کامپوننتی React برای دعوت به قرار — موبایل‌فرست، RTL، با گلس‌مورفیسم، گرادیان‌های پاستلی و انیمیشن‌های Framer Motion.

لحن متن‌ها عمداً محاوره‌ای و نسل‌زدی است («پایه‌ای بریم سر قرار؟»، «چیل و دنج»، «دور دور»، «لفتش نده»).

فایل اصلی: [`DateProposalApp.jsx`](./DateProposalApp.jsx) — همه‌چیز در یک کامپوننت.

## راه‌اندازی از صفر

این مراحل عیناً تست شده‌اند (Vite 8 + React 19 + Tailwind 4). از هر پوشه‌ای:

```bash
npm create vite@latest my-date -- --template react
cd my-date
npm install
npm i framer-motion
npm i -D tailwindcss @tailwindcss/vite
```

بعد `DateProposalApp.jsx` را در `src/` بگذار و این چهار فایل را ویرایش کن:

**`vite.config.js`** — پلاگین Tailwind را اضافه کن:

```js
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({ plugins: [react(), tailwindcss()] })
```

**`src/index.css`** — کل محتوا فقط همین یک خط (بقیه‌اش را پاک کن):

```css
@import "tailwindcss";
```

**`src/App.jsx`**:

```jsx
import DateProposalApp from './DateProposalApp.jsx'

export default function App() {
  return <DateProposalApp />
}
```

**`index.html`** — برای RTL و عنوان تب:

```html
<html lang="fa" dir="rtl">
...
<title>پایه‌ای بریم سر قرار؟ 💖</title>
```

> `src/App.css` را پاک کن — استایل پیش‌فرض Vite با لایه‌بندی کامپوننت قوز بالا قوز می‌کند.

```bash
npm run dev     # http://localhost:5173
```

## انتشار (که بفرستیش براش)

```bash
npm run build   # خروجی در dist/
```

`dist/` یک سایت استاتیک ساده است، پس هر جایی بالا می‌رود:

- **Vercel / Netlify** — ریپو را وصل کن، خودش `vite` را می‌شناسد. صفر کانفیگ.
- **GitHub Pages** — چون زیر `/<repo>/` سرو می‌شود باید `base` را ست کنی، وگرنه صفحه سفید می‌آید:
  ```js
  export default defineConfig({ base: '/my-date/', plugins: [react(), tailwindcss()] })
  ```

از موبایل هم بی‌مشکل باز می‌شود؛ کل UI موبایل‌فرست است و در ویوپورت ۳۹۰px تست شده.

## فونت وزیرمتن

کامپوننت خودش `@font-face` وزیرمتن را یک‌بار به `<head>` تزریق می‌کند — نیازی به دست‌زدن به `index.html` نیست.

نسخه‌ی **variable** استفاده می‌شود: یک فایل ~۱۱۱KB که کل وزن‌های ۱۰۰ تا ۹۰۰ را می‌دهد
(کامپوننت از ۴۰۰ تا ۹۰۰ استفاده می‌کند) — یعنی یک درخواست شبکه به‌جای شش تا.

با `font-display: swap`؛ اگر فونت لود نشود متن بلافاصله با استک
`Vazirmatn → Vazir → IRANSans → IRANYekan → Tahoma` رندر می‌شود و چیزی نمی‌شکند.

### میزبانی محلی (آفلاین / پروداکشن)

```bash
npm i vazirmatn
```

```jsx
import fontUrl from "vazirmatn/fonts/webfonts/Vazirmatn[wght].woff2?url"; // Vite

<DateProposalApp fontUrl={fontUrl} />
```

`fontUrl={null}` تزریق فونت را کامل خاموش می‌کند (اگر خودت گلوبال ست کرده‌ای).

## بدون وابستگی اضافه

سه چیزی که معمولاً پکیج جدا می‌خواهند، اینجا بومی پیاده‌سازی شده‌اند:

| قابلیت | پیاده‌سازی |
|---|---|
| کانفتی | Canvas + `requestAnimationFrame` (بدون `canvas-confetti`) |
| تقویم جلالی | `Intl.DateTimeFormat('fa-IR-u-ca-persian')` (بدون `moment-jalaali`) |
| افکت صوتی | نوسان‌ساز WebAudio + `navigator.vibrate` (بدون فایل صوتی) |
| فونت | `@font-face` تزریق‌شده از خود کامپوننت (بدون تغییر در `index.html`) |

## جریان اپ

۱. سوال + دکمه‌ی «نه»ی بازیگوش → ۲. انتخاب روز و ساعت → ۳. منو و اکسترا → ۴. جشن، بلیط قرار و اشتراک‌گذاری تلگرام.

## نکته‌ی طراحی: دکمه‌ی «نه»

دکمه ابتدا خاکستری و «خرابه» است، با تولتیپ طعنه‌آمیز؛ بعد فرار می‌کند و در تلاش پنجم تسلیم می‌شود و به «آره دیگه 💖» تبدیل می‌شود.

پنج طعنه به‌ترتیب نشان داده می‌شوند و آخری («اوکی بردی 🥲 کردمش «آره»») دقیقاً لحظه‌ی تبدیل می‌آید.

دو نکته‌ی ظریف که در پیاده‌سازی رعایت شده:

- **روی موبایل hover وجود ندارد.** اگر منطق فقط به hover و کلیکِ دقیق روی دکمه‌ی متحرک وابسته باشد، کاربر موبایل ممکن است هیچ‌وقت به مرحله‌ی تبدیل نرسد. برای همین یک «منطقه‌ی شکار» با ارتفاع ثابت دور دکمه هست که تپِ خطا رفته را هم به‌عنوان تلاش حساب می‌کند.
- **تپِ تبدیل‌کننده، فرم را ثبت نمی‌کند.** بعد از تبدیل، ۹۰۰ms کلیک نادیده گرفته می‌شود تا کاربر لحظه‌ی «نچ → آره» را ببیند.

جابه‌جایی دکمه هم عمداً داخل عرض کارت محدود شده تا زیر `overflow-hidden` بریده و غیرقابل‌لمس نشود.
