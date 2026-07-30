/**
 * InviteCard — کارت دعوت تک‌کامپوننتی، موبایل‌فرست، RTL 💌
 *
 * ۱۰ قالب آماده (عشقی، دوستی، تولد، دورهمی، عروسی، عقد، سالگرد، کاری، جلسه، سوگ)
 * با رنگ‌بندی قابل تغییر توسط کاربر، اشتراک‌گذاری تلگرام/واتس‌اپ، و لوکیشن روی
 * نشان / گوگل‌مپ / ویز.
 *
 * وابستگی‌ها: react, framer-motion, tailwindcss
 * (کانفتی، تقویم جلالی و افکت صوتی بومی پیاده‌سازی شده‌اند — بدون پکیج اضافه)
 *
 *   import InviteCard from "./DateProposalApp";
 *   export default function App() { return <InviteCard />; }
 *
 * ── نکته‌ی مهم طراحی ──────────────────────────────────────────────────────
 * قالب فقط رنگ عوض نمی‌کند، رفتار را هم عوض می‌کند. «سوگ» نباید کانفتی، قلب
 * شناور یا دکمه‌ی فراری داشته باشد؛ در «عروسی/عقد/جلسه/سوگ» تاریخ را فرستنده
 * تعیین می‌کند نه مخاطب؛ و هرجا شوخیِ دکمه‌ی «نه» خاموش است، «نه» باید واقعاً
 * کار کند و به پاسخ محترمانه‌ی رد برسد.
 */

import React, {
  useState,
  useEffect,
  useMemo,
  useRef,
  useCallback,
} from "react";
import { motion, AnimatePresence } from "framer-motion";

/* ════════════════════════════════════════════════════════════════════════════
   تایپوگرافی فارسی — وزیرمتن
   ════════════════════════════════════════════════════════════════════════════ */

/**
 * نسخه‌ی variable: یک فایل ~۱۱۱KB که کل وزن‌های ۱۰۰ تا ۹۰۰ را می‌دهد.
 * میزبانی محلی: `npm i vazirmatn` و بعد fontUrl را به مسیر خودت بده.
 * با fontUrl={null} تزریق فونت خاموش می‌شود.
 */
const VAZIRMATN_WOFF2 =
  "https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2";

const FONT_STACK =
  "Vazirmatn, 'Vazir', 'IRANSans', 'IRANYekan', 'Segoe UI', Tahoma, system-ui, sans-serif";

function useVazirmatn(src) {
  useEffect(() => {
    if (!src || typeof document === "undefined") return;
    if (document.getElementById("vazirmatn-face")) return;
    const style = document.createElement("style");
    style.id = "vazirmatn-face";
    style.textContent =
      "@font-face{font-family:'Vazirmatn';" +
      `src:url("${src}") format("woff2-variations"),url("${src}") format("woff2");` +
      "font-weight:100 900;font-style:normal;font-display:swap;}";
    document.head.appendChild(style);
  }, [src]);
}

/* ════════════════════════════════════════════════════════════════════════════
   ابزارهای کوچک
   ════════════════════════════════════════════════════════════════════════════ */

const PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹";
const fa = (n) => String(n).replace(/\d/g, (d) => PERSIAN_DIGITS[d]);
const pad2 = (n) => String(n).padStart(2, "0");
const rand = (min, max) => Math.random() * (max - min) + min;
const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];
const clip = (v, n) => String(v ?? "").trim().slice(0, n);

/** فرمت تاریخ با تقویم جلالی — بومی مرورگر، بدون کتابخانه */
const jalali = (date, opts) => {
  try {
    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", opts).format(date);
  } catch {
    try {
      return new Intl.DateTimeFormat("fa-IR", opts).format(date);
    } catch {
      return date.toLocaleDateString();
    }
  }
};

/** ۳۰ روز آینده به‌صورت کارت‌های قابل انتخاب */
const buildDays = (count = 30) => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Array.from({ length: count }, (_, i) => {
    const d = new Date(today);
    d.setDate(today.getDate() + i);
    return {
      id: i,
      date: d,
      weekday: jalali(d, { weekday: "long" }),
      day: jalali(d, { day: "numeric" }),
      month: jalali(d, { month: "long" }),
      badge: i === 0 ? "امروز" : i === 1 ? "فردا" : null,
      isWeekend: d.getDay() === 4 || d.getDay() === 5,
    };
  });
};

const HOURS = Array.from({ length: 24 }, (_, i) => i);
const MINUTES = [0, 15, 30, 45];

/* ════════════════════════════════════════════════════════════════════════════
   پالت‌های رنگی — کاربر می‌تواند انتخاب کند یا رنگ دلخواه بدهد
   ════════════════════════════════════════════════════════════════════════════ */

const PALETTES = {
  sunset: { name: "غروب", bg: ["#ffe4e6", "#fae8ff", "#e0e7ff"], accent: "#fb7185", accent2: "#c026d3", ink: "#4a044e", muted: "#a21caf", glass: 0.25 },
  rose: { name: "رُز", bg: ["#fff1f2", "#ffe4e6", "#fecdd3"], accent: "#f43f5e", accent2: "#be123c", ink: "#4c0519", muted: "#9f1239", glass: 0.3 },
  gold: { name: "طلایی", bg: ["#fef9c3", "#fde68a", "#fed7aa"], accent: "#d97706", accent2: "#b45309", ink: "#451a03", muted: "#92400e", glass: 0.3 },
  mint: { name: "نعنایی", bg: ["#ecfdf5", "#d1fae5", "#cffafe"], accent: "#10b981", accent2: "#0d9488", ink: "#022c22", muted: "#047857", glass: 0.3 },
  ocean: { name: "دریا", bg: ["#e0f2fe", "#dbeafe", "#e0e7ff"], accent: "#0ea5e9", accent2: "#4f46e5", ink: "#0c1e3e", muted: "#1d4ed8", glass: 0.3 },
  lavender: { name: "یاسی", bg: ["#f5f3ff", "#ede9fe", "#e0e7ff"], accent: "#8b5cf6", accent2: "#6366f1", ink: "#2e1065", muted: "#6d28d9", glass: 0.3 },
  peach: { name: "هلویی", bg: ["#fff7ed", "#ffedd5", "#fee2e2"], accent: "#fb923c", accent2: "#f43f5e", ink: "#431407", muted: "#c2410c", glass: 0.3 },
  slate: { name: "رسمی", bg: ["#f1f5f9", "#e2e8f0", "#dbeafe"], accent: "#475569", accent2: "#1e40af", ink: "#0f172a", muted: "#475569", glass: 0.35 },
  sage: { name: "زیتونی", bg: ["#f7f7f2", "#e7e5e4", "#e2e8f0"], accent: "#57534e", accent2: "#3f6212", ink: "#1c1917", muted: "#57534e", glass: 0.35 },
  ash: { name: "خاکستری", bg: ["#f8fafc", "#eceff3", "#e2e5ea"], accent: "#64748b", accent2: "#334155", ink: "#1e293b", muted: "#475569", glass: 0.4 },
};

const PALETTE_IDS = Object.keys(PALETTES);

/** hex معتبر؟ (ورودی کاربر را کور باور نکن) */
const isHex = (v) => /^#[0-9a-fA-F]{6}$/.test(String(v || ""));

/** رنگ دلخواه کاربر روی پالت پایه سوار می‌شود */
const resolvePalette = (paletteId, customAccent) => {
  const base = PALETTES[paletteId] || PALETTES.sunset;
  if (!isHex(customAccent)) return base;
  return { ...base, accent: customAccent, accent2: shade(customAccent, -22) };
};

/** روشن/تیره کردن hex برای ساختن رنگ دوم گرادیان */
function shade(hex, percent) {
  const n = parseInt(hex.slice(1), 16);
  const amt = Math.round(2.55 * percent);
  const clamp = (v) => Math.max(0, Math.min(255, v));
  const r = clamp((n >> 16) + amt);
  const g = clamp(((n >> 8) & 0xff) + amt);
  const b = clamp((n & 0xff) + amt);
  return "#" + (0x1000000 + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

/* ════════════════════════════════════════════════════════════════════════════
   قالب‌ها — رنگ، متن و «رفتار»
   ════════════════════════════════════════════════════════════════════════════

   playful   : شوخیِ دکمه‌ی فراری «نه». خاموش ⇒ «نه» واقعاً رد می‌کند.
   celebrate : کانفتی + قلب‌های شناور.
   fixedWhen : تاریخ و ساعت را فرستنده تعیین می‌کند (مخاطب فقط تأیید می‌کند).
   options   : گزینه‌های مرحله‌ی میانی. null ⇒ آن مرحله کلاً حذف می‌شود.
   ════════════════════════════════════════════════════════════════════════════ */

const TEMPLATES = {
  eshghi: {
    id: "eshghi", name: "عشقی", icon: "💖", palette: "sunset",
    playful: true, celebrate: true, fixedWhen: false,
    hearts: ["💖", "💗", "💞", "🩷", "❤️", "💘"],
    moods: { idle: "🥺", shy: "🙈", love: "🥰", sly: "😈", think: "🤔", glad: "😍", pickA: "😋", pickB: "🤩", done: "🥳" },
    lines: { idle: "خب؟ چی میگی؟ 👀", shy: "اوففف خجالت کشیدم", love: "ایوللل همینه 🔥", sly: "گفتم که فقط «آره» تو مرامه", think: "خب کِی بیکاری؟", glad: "اوکی ترکوندی، بریم بعدی", pickA: "از الان ضعف کردم...", pickB: "سلیقت خفنه به خدا", done: "قرارمون سِت شد رسماً" },
    q: "پایه‌ای بریم سر قرار؟",
    sub: "یه سوال ساده‌ست فقط 🙂 ولی خب سیستم گزینه «نه» رو ساپورت نمیکنه 😜",
    yes: "آره دیگه 💖", no: "نچ ❌",
    whenTitle: "کِی بیکاری؟", whenSub: "یه روز و ساعت توپ بزن، بقیه‌ش با من",
    optTitle: "پلنمون چی باشه؟", optSub: "منو دست توئه رفیق! چی بزنیم؟",
    options: [
      { id: "pizza", emoji: "🍕", label: "پیتزا داغ", hint: "پنیرش کش بیاد تا آسمون" },
      { id: "coffee", emoji: "☕", label: "قهوه و دسر", hint: "چیل و دنج" },
      { id: "kabab", emoji: "🍢", label: "کوبیده مشتی", hint: "با دوغ، وگرنه نه" },
      { id: "pasta", emoji: "🍝", label: "پاستا آلفردو", hint: "خامه‌ای و لاکچری" },
    ],
    extra: { extraLabel: "✨ اکسترا", label: "سینما / دور دور هم باشه؟ 🎬🚶", on: "ایول! شب کش میاد 🌙", off: "فعلاً فقط شیکم 😅", yes: "سینما / دور دور ✅", nope: "بیخیال، فقط همین" },
    doneTitle: "دمت گرم که نگفتی نه!", doneSub: "(هرچند راه دیگه‌ای هم نداشتی 😈💖)",
    ticket: "🎟️ بلیط قرار", whenLabel: "📅 کِی", optLabel: "🍽️ چی بزنیم",
    statusLabel: "🚗 وضعیت",
    status: (t) => `ساعت ${t} دم درم، لفتش نده!`,
    shareLead: "قرارمون سِت شد رسماً 💌",
  },

  doosti: {
    id: "doosti", name: "دوستی", icon: "🤙", palette: "mint",
    playful: true, celebrate: true, fixedWhen: false,
    hearts: ["✨", "🎉", "🤙", "😎", "💫"],
    moods: { idle: "😃", shy: "😅", love: "😎", sly: "😏", think: "🤔", glad: "🤩", pickA: "😋", pickB: "🔥", done: "🥳" },
    lines: { idle: "خب؟ پایه‌ای؟", shy: "نگو نه دیگه 😅", love: "ایول رفیق 😎", sly: "این دکمه کار نمیده داداش", think: "کِی وقتت آزاده؟", glad: "اوکی، بریم بعدی", pickA: "خوبه خوبه", pickB: "چه انتخابی 🔥", done: "قرار گذاشتیم!" },
    q: "بزنیم بیرون؟",
    sub: "خیلی وقته ندیدمت... یه دوری بزنیم؟",
    yes: "پایه‌ام 🤙", no: "نچ ❌",
    whenTitle: "کِی بیکاری؟", whenSub: "یه روز و ساعت انتخاب کن",
    optTitle: "کجا بریم؟", optSub: "هرچی تو بگی",
    options: [
      { id: "cafe", emoji: "☕", label: "کافه", hint: "بشینیم گپ بزنیم" },
      { id: "resto", emoji: "🍽️", label: "رستوران", hint: "شیکم‌گردی" },
      { id: "park", emoji: "🌳", label: "پارک و پیاده‌روی", hint: "هوا بخوریم" },
      { id: "game", emoji: "🎮", label: "بازی و تفریح", hint: "بولینگ، بیلیارد، هرچی" },
    ],
    extra: { extraLabel: "✨ اکسترا", label: "فیلم هم ببینیم؟ 🎬", on: "آره! 🍿", off: "فعلاً نه", yes: "فیلم ✅", nope: "بدون فیلم" },
    doneTitle: "قرارمون شد!", doneSub: "دیر نکنیا 😄",
    ticket: "🎟️ قرار دوستانه", whenLabel: "📅 کِی", optLabel: "📍 کجا",
    statusLabel: "🚗 وضعیت",
    status: (t) => `ساعت ${t} می‌بینمت!`,
    shareLead: "قرارمون شد 🤙",
  },

  tavallod: {
    id: "tavallod", name: "تولد", icon: "🎂", palette: "peach",
    playful: true, celebrate: true, fixedWhen: true,
    hearts: ["🎈", "🎊", "🎁", "🥳", "✨", "🎂"],
    moods: { idle: "🎂", shy: "🙈", love: "🥳", sly: "😜", think: "🎈", glad: "🤩", pickA: "🎁", pickB: "🎉", done: "🥳" },
    lines: { idle: "میای دیگه؟ 🎈", shy: "بیا دیگه 🙈", love: "ایوللل 🥳", sly: "نه نداریم تو تولد!", think: "چی میاری؟", glad: "عالی شد", pickA: "دمت گرم 🎁", pickB: "چه انتخابی 🎉", done: "می‌بینمت!" },
    q: "تولدمه، میای؟",
    sub: "بدون تو که نمی‌چسبه 🎈",
    yes: "حتماً میام 🎉", no: "نچ ❌",
    optTitle: "چی بیاری؟", optSub: "اختیاری‌هاست، خالی هم بیای خوشحالم 😄",
    options: [
      { id: "cake", emoji: "🎂", label: "کیک", hint: "شکلاتی لطفاً" },
      { id: "gift", emoji: "🎁", label: "کادو", hint: "سورپرایز باشه" },
      { id: "drink", emoji: "🥤", label: "نوشیدنی", hint: "خنک" },
      { id: "snack", emoji: "🍿", label: "تنقلات", hint: "چیپس و پفک" },
    ],
    extra: { extraLabel: "👥 همراه", label: "همراه میاری؟ 👥", on: "آره، یه نفر دیگه هم هست", off: "تنها میام", yes: "با همراه ✅", nope: "تنها" },
    doneTitle: "می‌بینمت! 🥳", doneSub: "دیر نکنیا",
    ticket: "🎟️ کارت دعوت تولد", whenLabel: "📅 کِی", optLabel: "🎁 میاری",
    statusLabel: "🎉 یادآوری",
    status: (t) => `ساعت ${t} منتظرتم!`,
    shareLead: "میام تولدت 🎉",
  },

  doorehami: {
    id: "doorehami", name: "دورهمی", icon: "🍉", palette: "gold",
    playful: true, celebrate: true, fixedWhen: true,
    hearts: ["🍉", "☕", "✨", "🎶"],
    moods: { idle: "😊", shy: "😅", love: "🤗", sly: "😏", think: "🤔", glad: "😍", pickA: "😋", pickB: "🤩", done: "🥳" },
    lines: { idle: "هستی دیگه؟", shy: "بیا دیگه 😅", love: "ایول 🤗", sly: "نه که نداریم!", think: "چی میاری؟", glad: "عالی", pickA: "دمت گرم", pickB: "چه انتخابی", done: "منتظرتم!" },
    q: "دورهمی داریم، هستی؟",
    sub: "یه جمع کوچیک و صمیمی",
    yes: "هستم 🙌", no: "نچ ❌",
    optTitle: "چی بیاری؟", optSub: "هرکی یه چیزی — هماهنگ باشیم",
    options: [
      { id: "fruit", emoji: "🍉", label: "میوه", hint: "" },
      { id: "dessert", emoji: "🍰", label: "دسر", hint: "" },
      { id: "drink", emoji: "🥤", label: "نوشیدنی", hint: "" },
      { id: "snack", emoji: "🥜", label: "تنقلات", hint: "" },
    ],
    extra: { extraLabel: "👥 همراه", label: "همراه میاری؟ 👥", on: "آره", off: "تنها میام", yes: "با همراه ✅", nope: "تنها" },
    doneTitle: "منتظرتم! 🎉", doneSub: "",
    ticket: "🎟️ دورهمی", whenLabel: "📅 کِی", optLabel: "🧺 میاری",
    statusLabel: "🎉 یادآوری",
    status: (t) => `ساعت ${t} منتظرتم`,
    shareLead: "هستم برای دورهمی 🙌",
  },

  aroosi: {
    id: "aroosi", name: "عروسی", icon: "💍", palette: "gold",
    playful: false, celebrate: true, fixedWhen: true,
    hearts: ["💍", "💐", "✨", "🤍"],
    moods: { idle: "💍", shy: "🤍", love: "🥰", sly: "🙂", think: "💐", glad: "😊", pickA: "🌸", pickB: "✨", done: "🎊" },
    lines: { idle: "خوشحال می‌شیم کنارمون باشی", shy: "", love: "چه عالی 🥰", sly: "", think: "", glad: "ممنون از شما", pickA: "", pickB: "", done: "منتظر حضورتون هستیم" },
    q: "به عروسیمون دعوتید 💍",
    sub: "خوشحال می‌شیم در این روز خاص کنارمون باشید",
    yes: "حتماً میام 💐", no: "متأسفانه نمی‌تونم",
    optTitle: "", optSub: "",
    options: null,
    extra: { extraLabel: "👥 همراه", label: "با همراه تشریف میارید؟ 👥", on: "بله، با همراه", off: "تنها", yes: "با همراه ✅", nope: "تنها" },
    doneTitle: "منتظر حضورتون هستیم 💐", doneSub: "حضور شما باعث افتخار ماست",
    ticket: "💌 کارت دعوت عروسی", whenLabel: "📅 تاریخ مراسم", optLabel: "",
    statusLabel: "⏰ زمان‌بندی",
    status: (t) => `مراسم رأس ساعت ${t} آغاز می‌شود`,
    shareLead: "در مراسم عروسیتون شرکت می‌کنم 💐",
    declineTitle: "ممنون که خبر دادید 🤍", declineSub: "جای شما خالی خواهد بود",
  },

  aghd: {
    id: "aghd", name: "عقد", icon: "💐", palette: "lavender",
    playful: false, celebrate: true, fixedWhen: true,
    hearts: ["💐", "🤍", "✨"],
    moods: { idle: "💐", shy: "🤍", love: "🥰", sly: "🙂", think: "🌸", glad: "😊", pickA: "🌸", pickB: "✨", done: "🎊" },
    lines: { idle: "مشتاق دیدارتون هستیم", shy: "", love: "چه خوب 🥰", sly: "", think: "", glad: "سپاسگزاریم", pickA: "", pickB: "", done: "منتظرتون هستیم" },
    q: "به جشن عقدمون دعوتید 💐",
    sub: "حضور گرمتون به مراسم ما رونق می‌ده",
    yes: "حتماً میام 🌸", no: "متأسفانه نمی‌تونم",
    optTitle: "", optSub: "",
    options: null,
    extra: { extraLabel: "👥 همراه", label: "با همراه تشریف میارید؟ 👥", on: "بله، با همراه", off: "تنها", yes: "با همراه ✅", nope: "تنها" },
    doneTitle: "منتظرتون هستیم 💐", doneSub: "",
    ticket: "💌 کارت دعوت عقد", whenLabel: "📅 تاریخ مراسم", optLabel: "",
    statusLabel: "⏰ زمان‌بندی",
    status: (t) => `مراسم ساعت ${t} برگزار می‌شود`,
    shareLead: "در جشن عقدتون شرکت می‌کنم 💐",
    declineTitle: "ممنون که خبر دادید 🤍", declineSub: "جای شما خالی خواهد بود",
  },

  salgard: {
    id: "salgard", name: "سالگرد", icon: "🥂", palette: "rose",
    playful: false, celebrate: true, fixedWhen: true,
    hearts: ["🥂", "💖", "✨", "🌹"],
    moods: { idle: "🥂", shy: "🙈", love: "🥰", sly: "🙂", think: "🌹", glad: "😍", pickA: "🍽️", pickB: "✨", done: "🎉" },
    lines: { idle: "یه شب خاص در پیشه", shy: "", love: "عالیه 🥰", sly: "", think: "", glad: "چه خوب", pickA: "", pickB: "", done: "می‌بینمت 🥂" },
    q: "سالگردمونه، جشن بگیریم؟",
    sub: "یه شب فقط برای خودمون 🥂",
    yes: "حتماً 🥂", no: "این بار نمی‌تونم",
    optTitle: "برنامه چی باشه؟", optSub: "",
    options: [
      { id: "dinner", emoji: "🍽️", label: "شام بیرون", hint: "" },
      { id: "home", emoji: "🕯️", label: "شام خونه", hint: "دنج‌تر" },
      { id: "trip", emoji: "🚗", label: "سفر کوتاه", hint: "" },
      { id: "cinema", emoji: "🎬", label: "سینما", hint: "" },
    ],
    extra: null,
    doneTitle: "می‌بینمت 🥂", doneSub: "",
    ticket: "💌 سالگرد", whenLabel: "📅 کِی", optLabel: "✨ برنامه",
    statusLabel: "⏰ زمان‌بندی",
    status: (t) => `ساعت ${t} منتظرتم`,
    shareLead: "سالگردمون مبارک 🥂",
    declineTitle: "باشه، ایرادی نداره 🤍", declineSub: "",
  },

  kari: {
    id: "kari", name: "کاری", icon: "💼", palette: "slate",
    playful: false, celebrate: false, fixedWhen: false,
    hearts: null,
    moods: { idle: "🙂", shy: "🙂", love: "👍", sly: "🙂", think: "📅", glad: "✅", pickA: "📌", pickB: "✅", done: "🤝" },
    lines: { idle: "در خدمتم", shy: "", love: "عالی", sly: "", think: "چه زمانی مناسبه؟", glad: "ثبت شد", pickA: "", pickB: "", done: "هماهنگ شد" },
    q: "یه قرار کاری بذاریم؟",
    sub: "زمان مناسب خودتون رو انتخاب کنید",
    yes: "بله، هماهنگ کنیم ✅", no: "الان نه",
    whenTitle: "چه زمانی مناسبه؟", whenSub: "روز و ساعت دلخواهتون رو انتخاب کنید",
    optTitle: "جلسه چطور برگزار بشه؟", optSub: "",
    options: [
      { id: "inperson", emoji: "🏢", label: "حضوری", hint: "" },
      { id: "online", emoji: "💻", label: "آنلاین", hint: "لینک بعداً" },
      { id: "phone", emoji: "📞", label: "تلفنی", hint: "" },
      { id: "cafe", emoji: "☕", label: "کافه", hint: "غیررسمی‌تر" },
    ],
    extra: null,
    doneTitle: "هماهنگ شد ✅", doneSub: "",
    ticket: "📋 قرار کاری", whenLabel: "📅 زمان", optLabel: "📍 نحوه برگزاری",
    statusLabel: "⏰ یادآوری",
    status: (t) => `ساعت ${t} در خدمتم`,
    shareLead: "قرار کاری تأیید شد ✅",
    declineTitle: "متوجه شدم", declineSub: "زمان دیگه‌ای هماهنگ می‌کنیم",
  },

  jalase: {
    id: "jalase", name: "جلسه", icon: "📋", palette: "ocean",
    playful: false, celebrate: false, fixedWhen: true,
    hearts: null,
    moods: { idle: "📋", shy: "🙂", love: "👍", sly: "🙂", think: "📅", glad: "✅", pickA: "📌", pickB: "✅", done: "🤝" },
    lines: { idle: "حضورتون رو اعلام کنید", shy: "", love: "ثبت شد", sly: "", think: "", glad: "ثبت شد", pickA: "", pickB: "", done: "حضور شما ثبت شد" },
    q: "دعوت به جلسه",
    sub: "لطفاً حضور یا عدم حضورتون رو اعلام کنید",
    yes: "حضور دارم ✅", no: "نمی‌تونم شرکت کنم",
    optTitle: "نحوه‌ی حضور", optSub: "",
    options: [
      { id: "inperson", emoji: "🏢", label: "حضوری", hint: "" },
      { id: "online", emoji: "💻", label: "آنلاین", hint: "" },
    ],
    extra: null,
    doneTitle: "حضور شما ثبت شد ✅", doneSub: "",
    ticket: "📋 کارت جلسه", whenLabel: "📅 زمان جلسه", optLabel: "💼 نحوه حضور",
    statusLabel: "⏰ یادآوری",
    status: (t) => `جلسه رأس ساعت ${t} آغاز می‌شود`,
    shareLead: "حضورم در جلسه تأیید شد ✅",
    declineTitle: "عدم حضور ثبت شد", declineSub: "ممنون که اطلاع دادید",
  },

  sog: {
    id: "sog", name: "سوگ", icon: "🕊️", palette: "ash",
    playful: false, celebrate: false, fixedWhen: true,
    hearts: null,
    moods: { idle: "🕊️", shy: "🕊️", love: "🤍", sly: "🕊️", think: "🕊️", glad: "🤍", pickA: "🤍", pickB: "🤍", done: "🤍" },
    lines: { idle: "", shy: "", love: "", sly: "", think: "", glad: "", pickA: "", pickB: "", done: "" },
    q: "مراسم یادبود",
    sub: "با نهایت تأسف، از شما دعوت می‌شود در مراسم یادبود شرکت بفرمایید.",
    yes: "حضور خواهم داشت 🤍", no: "متأسفانه نمی‌توانم",
    optTitle: "", optSub: "",
    options: null,
    extra: null,
    doneTitle: "حضور شما مایه‌ی تسلی است 🤍", doneSub: "",
    ticket: "🕊️ اطلاعات مراسم", whenLabel: "📅 زمان مراسم", optLabel: "",
    statusLabel: "🕊️ یادآوری",
    status: (t) => `مراسم ساعت ${t} برگزار می‌شود`,
    shareLead: "در مراسم شرکت خواهم کرد 🤍",
    declineTitle: "تسلیت عرض می‌کنم 🤍", declineSub: "متأسفانه امکان حضور ندارم",
  },
};

const TEMPLATE_IDS = Object.keys(TEMPLATES);
const getTemplate = (id) => TEMPLATES[id] || TEMPLATES.eshghi;

/* ════════════════════════════════════════════════════════════════════════════
   کانفیگ در URL (hash) — بدون بک‌اند
   ════════════════════════════════════════════════════════════════════════════ */

const b64urlEncode = (str) => {
  const bytes = new TextEncoder().encode(str);
  let bin = "";
  bytes.forEach((b) => (bin += String.fromCharCode(b)));
  return btoa(bin).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
};

const b64urlDecode = (s) => {
  const base = s.replace(/-/g, "+").replace(/_/g, "/");
  const bin = atob(base + "=".repeat((4 - (base.length % 4)) % 4));
  return new TextDecoder().decode(Uint8Array.from(bin, (c) => c.charCodeAt(0)));
};

const cleanHandle = (v) => clip(v, 40).replace(/^@+/, "").replace(/\s+/g, "");

/** موبایل ایران را به فرمت بین‌المللی می‌برد: ۰۹۱۲… → ۹۸۹۱۲… */
const normalizePhone = (raw) => {
  let d = String(raw ?? "").replace(/\D/g, "");
  if (!d) return "";
  if (d.startsWith("00")) d = d.slice(2);
  else if (d.startsWith("0")) d = "98" + d.slice(1);
  else if (d.length === 10 && d.startsWith("9")) d = "98" + d;
  return d.slice(0, 15);
};

/** از متن یا لینک نقشه، مختصات را بیرون می‌کشد (گوگل‌مپ، نشان، ویز، یا خام) */
const parseCoords = (s) => {
  const m = String(s ?? "").match(/(-?\d{1,3}\.\d{3,})[,\s/]+(-?\d{1,3}\.\d{3,})/);
  if (!m) return null;
  const lat = parseFloat(m[1]);
  const lng = parseFloat(m[2]);
  if (Math.abs(lat) > 90 || Math.abs(lng) > 180) return null;
  return { lat: m[1], lng: m[2] };
};

const EMPTY_CFG = {
  tpl: "eshghi", to: "", from: "", question: "", note: "",
  telegram: "", whatsapp: "",
  palette: "", accent: "",
  locName: "", lat: "", lng: "",
  day: "", hour: "", minute: "",
};

/** کلیدها تک/دوحرفی‌اند تا لینک کوتاه بماند */
const encodeConfig = (c) => {
  const p = {};
  if (c.tpl && c.tpl !== "eshghi") p.k = c.tpl;
  if (c.to) p.t = clip(c.to, 60);
  if (c.from) p.f = clip(c.from, 60);
  if (c.question) p.q = clip(c.question, 140);
  if (c.note) p.n = clip(c.note, 300);
  if (c.telegram) p.g = cleanHandle(c.telegram);
  if (c.whatsapp) p.w = normalizePhone(c.whatsapp);
  if (c.palette) p.p = clip(c.palette, 20);
  if (isHex(c.accent)) p.a = c.accent;
  if (c.locName) p.l = clip(c.locName, 120);
  if (c.lat && c.lng) { p.x = String(c.lat); p.y = String(c.lng); }
  if (c.day !== "" && c.day != null) p.d = Number(c.day);
  if (c.hour !== "" && c.hour != null) p.h = Number(c.hour);
  if (c.minute !== "" && c.minute != null) p.m = Number(c.minute);
  return b64urlEncode(JSON.stringify(p));
};

const numOr = (v, fallback) => (Number.isFinite(Number(v)) ? Number(v) : fallback);

const decodeConfig = (raw) => {
  try {
    const o = JSON.parse(b64urlDecode(raw));
    if (!o || typeof o !== "object" || Array.isArray(o)) return null;
    const tpl = TEMPLATES[o.k] ? o.k : "eshghi";
    return {
      tpl,
      to: clip(o.t, 60),
      from: clip(o.f, 60),
      question: clip(o.q, 140),
      note: clip(o.n, 300),
      telegram: cleanHandle(o.g),
      whatsapp: normalizePhone(o.w),
      palette: PALETTES[o.p] ? o.p : "",
      accent: isHex(o.a) ? o.a : "",
      locName: clip(o.l, 120),
      lat: clip(o.x, 24),
      lng: clip(o.y, 24),
      day: o.d == null ? "" : numOr(o.d, ""),
      hour: o.h == null ? "" : numOr(o.h, ""),
      minute: o.m == null ? "" : numOr(o.m, ""),
    };
  } catch {
    return null; // لینک خراب → صفحه‌ی ساخت، نه صفحه‌ی سفید
  }
};

const readHashConfig = () => {
  if (typeof window === "undefined") return null;
  const m = window.location.hash.match(/[#&]c=([^&]+)/);
  return m ? decodeConfig(m[1]) : null;
};

const buildLink = (c) => {
  if (typeof window === "undefined") return "";
  const { origin, pathname } = window.location;
  return `${origin}${pathname}#c=${encodeConfig(c)}`;
};

/* ── لینک‌های نقشه ─────────────────────────────────────────────────────────
   نشان اپ بومی ایرانی است و اول می‌آید؛ ویز و گوگل‌مپ هم برای کسانی که
   آن‌ها را دارند. اگر مختصات نباشد، از جستجوی متنی استفاده می‌شود. */
const mapLinks = (cfg) => {
  const hasCoord = cfg.lat && cfg.lng;
  if (!hasCoord && !cfg.locName) return null;
  const q = hasCoord ? `${cfg.lat},${cfg.lng}` : cfg.locName;
  return [
    {
      id: "neshan", label: "نشان", emoji: "🧭",
      href: hasCoord
        ? `https://neshan.org/maps/@${cfg.lat},${cfg.lng},16z`
        : `https://neshan.org/maps/search/${encodeURIComponent(cfg.locName)}`,
    },
    {
      id: "waze", label: "ویز", emoji: "🚗",
      href: hasCoord
        ? `https://waze.com/ul?ll=${cfg.lat},${cfg.lng}&navigate=yes`
        : `https://waze.com/ul?q=${encodeURIComponent(cfg.locName)}`,
    },
    {
      id: "google", label: "گوگل‌مپ", emoji: "🗺️",
      href: `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(q)}`,
    },
  ];
};

/* ════════════════════════════════════════════════════════════════════════════
   افکت صوتی + هپتیک (بدون فایل صوتی)
   ════════════════════════════════════════════════════════════════════════════ */

function useFx(enabled = true) {
  const ctxRef = useRef(null);

  const play = useCallback(
    (kind = "pop") => {
      if (!enabled) return;
      try {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        if (!ctxRef.current) ctxRef.current = new AC();
        const ctx = ctxRef.current;
        if (ctx.state === "suspended") ctx.resume();

        const seq =
          {
            pop: [[880, 0.16]],
            tick: [[520, 0.1]],
            swoosh: [[300, 0.12], [200, 0.1]],
            yay: [[523, 0.18], [659, 0.18], [784, 0.18], [1047, 0.28]],
          }[kind] || [[660, 0.15]];

        const t0 = ctx.currentTime;
        seq.forEach(([freq, dur], i) => {
          const start = t0 + i * 0.085;
          const osc = ctx.createOscillator();
          const gain = ctx.createGain();
          osc.type = kind === "swoosh" ? "triangle" : "sine";
          osc.frequency.setValueAtTime(freq, start);
          gain.gain.setValueAtTime(0.0001, start);
          gain.gain.exponentialRampToValueAtTime(0.12, start + 0.02);
          gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
          osc.connect(gain);
          gain.connect(ctx.destination);
          osc.start(start);
          osc.stop(start + dur + 0.05);
        });
      } catch {
        /* صدا اختیاری است */
      }
    },
    [enabled]
  );

  const buzz = useCallback(
    (pattern = 12) => {
      if (!enabled) return;
      try {
        navigator.vibrate?.(pattern);
      } catch {
        /* هپتیک اختیاری است */
      }
    },
    [enabled]
  );

  return { play, buzz };
}

/* ════════════════════════════════════════════════════════════════════════════
   پس‌زمینه — در قالب‌های سنگین (سوگ/کاری) آرام و بدون ایموجی شناور
   ════════════════════════════════════════════════════════════════════════════ */

function AmbientBackdrop({ hearts, festive }) {
  const floats = useMemo(() => {
    if (!hearts || !hearts.length) return [];
    return Array.from({ length: 14 }, (_, i) => ({
      id: i,
      char: pick(hearts),
      left: rand(2, 94),
      size: rand(14, 30),
      delay: rand(0, 9),
      duration: rand(11, 20),
      drift: rand(-40, 40),
    }));
  }, [hearts]);

  const sparks = useMemo(
    () =>
      Array.from({ length: festive ? 26 : 10 }, (_, i) => ({
        id: i,
        left: rand(0, 100),
        top: rand(0, 100),
        size: rand(2, 5),
        delay: rand(0, 5),
        duration: rand(2.4, 5.5),
      })),
    [festive]
  );

  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      <motion.div
        className="absolute -top-24 -right-16 h-72 w-72 rounded-full blur-3xl"
        style={{ background: "var(--accent)", opacity: 0.28 }}
        animate={{ scale: [1, 1.18, 1], x: [0, 24, 0], y: [0, 18, 0] }}
        transition={{ duration: 14, repeat: Infinity, ease: "easeInOut" }}
      />
      <motion.div
        className="absolute top-1/3 -left-24 h-80 w-80 rounded-full blur-3xl"
        style={{ background: "var(--accent2)", opacity: 0.24 }}
        animate={{ scale: [1.1, 1, 1.1], x: [0, -20, 0], y: [0, -26, 0] }}
        transition={{ duration: 17, repeat: Infinity, ease: "easeInOut" }}
      />
      <motion.div
        className="absolute -bottom-28 right-1/4 h-72 w-72 rounded-full blur-3xl"
        style={{ background: "var(--bg3)", opacity: 0.6 }}
        animate={{ scale: [1, 1.22, 1], y: [0, -22, 0] }}
        transition={{ duration: 19, repeat: Infinity, ease: "easeInOut" }}
      />

      {sparks.map((s) => (
        <motion.span
          key={s.id}
          className="absolute rounded-full bg-white shadow-[0_0_8px_2px_rgba(255,255,255,0.8)]"
          style={{ left: `${s.left}%`, top: `${s.top}%`, width: s.size, height: s.size }}
          animate={{ opacity: [0, festive ? 1 : 0.5, 0], scale: [0.4, 1.3, 0.4] }}
          transition={{ duration: s.duration, delay: s.delay, repeat: Infinity, ease: "easeInOut" }}
        />
      ))}

      {floats.map((h) => (
        <motion.span
          key={h.id}
          className="absolute select-none"
          style={{ left: `${h.left}%`, bottom: -50, fontSize: h.size }}
          animate={{ y: [0, -820], x: [0, h.drift, 0], opacity: [0, 0.85, 0], rotate: [0, h.drift > 0 ? 22 : -22, 0] }}
          transition={{ duration: h.duration, delay: h.delay, repeat: Infinity, ease: "linear" }}
        >
          {h.char}
        </motion.span>
      ))}
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════════════════
   کانفتی (Canvas بومی) — فقط در قالب‌های جشن
   ════════════════════════════════════════════════════════════════════════════ */

function ConfettiCanvas({ fire, colors }) {
  const canvasRef = useRef(null);
  const rafRef = useRef(0);
  const partsRef = useRef([]);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    const resize = () => {
      const { clientWidth: w, clientHeight: h } = canvas;
      canvas.width = w * dpr;
      canvas.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    window.addEventListener("resize", resize);

    const burst = (ox, oy, count) => {
      for (let i = 0; i < count; i++) {
        const angle = rand(-Math.PI, 0) + rand(-0.4, 0.4);
        const speed = rand(4, 12);
        partsRef.current.push({
          x: ox, y: oy,
          vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed,
          w: rand(5, 11), h: rand(7, 15),
          rot: rand(0, Math.PI * 2), vr: rand(-0.28, 0.28),
          color: pick(colors), life: rand(90, 190), age: 0,
          shape: Math.random() > 0.72 ? "circle" : "rect",
        });
      }
    };

    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    const shots = [
      [0, w * 0.5, h * 0.45, 90],
      [180, w * 0.12, h * 0.55, 55],
      [340, w * 0.88, h * 0.55, 55],
      [700, w * 0.5, h * 0.3, 70],
      [1200, w * 0.3, h * 0.6, 45],
      [1250, w * 0.7, h * 0.6, 45],
    ];
    const timers = shots.map(([delay, x, y, n]) => setTimeout(() => burst(x, y, n), delay));

    const tick = () => {
      ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
      const parts = partsRef.current;
      for (let i = parts.length - 1; i >= 0; i--) {
        const p = parts[i];
        p.age += 1;
        p.vy += 0.16;
        p.vx *= 0.992;
        p.vy *= 0.992;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.vr;
        if (p.age > p.life || p.y > canvas.clientHeight + 60) { parts.splice(i, 1); continue; }
        ctx.save();
        ctx.globalAlpha = Math.max(0, 1 - p.age / p.life);
        ctx.translate(p.x, p.y);
        ctx.rotate(p.rot);
        ctx.fillStyle = p.color;
        if (p.shape === "circle") {
          ctx.beginPath();
          ctx.arc(0, 0, p.w / 2, 0, Math.PI * 2);
          ctx.fill();
        } else {
          ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h * Math.abs(Math.cos(p.rot)));
        }
        ctx.restore();
      }
      rafRef.current = requestAnimationFrame(tick);
    };
    rafRef.current = requestAnimationFrame(tick);

    return () => {
      timers.forEach(clearTimeout);
      cancelAnimationFrame(rafRef.current);
      window.removeEventListener("resize", resize);
      partsRef.current = [];
    };
  }, [fire, colors]);

  return <canvas ref={canvasRef} className="pointer-events-none absolute inset-0 z-30 h-full w-full" />;
}

/* ════════════════════════════════════════════════════════════════════════════
   اجزای رابط
   ════════════════════════════════════════════════════════════════════════════ */

function ReactionAvatar({ face, line, compact = false }) {
  const ring = compact ? "h-[62px] w-[62px]" : "h-[86px] w-[86px]";
  const halo = compact ? "h-16 w-16" : "h-24 w-24";
  const size = compact ? "text-[32px]" : "text-[44px]";
  return (
    <div className={`relative grid place-items-center ${compact ? "mb-2" : "mb-3"}`}>
      <div className="relative grid place-items-center">
        <motion.div
          className={`absolute rounded-full blur-2xl ${halo}`}
          style={{ background: "var(--accent)", opacity: 0.45 }}
          animate={{ scale: [1, 1.25, 1], opacity: [0.3, 0.6, 0.3] }}
          transition={{ duration: 2.8, repeat: Infinity, ease: "easeInOut" }}
        />
        <motion.div
          className={`relative grid ${ring} place-items-center rounded-full border border-white/70 bg-white/40 shadow-lg backdrop-blur-xl`}
          animate={{ y: [0, -6, 0], rotate: [-2.5, 2.5, -2.5] }}
          transition={{ duration: 4.5, repeat: Infinity, ease: "easeInOut" }}
        >
          <AnimatePresence mode="popLayout" initial={false}>
            <motion.span
              key={face}
              initial={{ scale: 0, rotate: -50, opacity: 0 }}
              animate={{ scale: 1, rotate: 0, opacity: 1 }}
              exit={{ scale: 0, rotate: 50, opacity: 0 }}
              transition={{ type: "spring", stiffness: 420, damping: 15 }}
              className={`select-none ${size} leading-none drop-shadow-sm`}
            >
              {face}
            </motion.span>
          </AnimatePresence>
        </motion.div>
      </div>

      <AnimatePresence mode="wait" initial={false}>
        {line ? (
          <motion.p
            key={line}
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -8 }}
            transition={{ duration: 0.25 }}
            className={`rounded-full bg-white/45 px-3 py-1 font-medium backdrop-blur-md ${compact ? "mt-2 text-[10.5px]" : "mt-3 text-[11px]"}`}
            style={{ color: "var(--muted)" }}
          >
            {line}
          </motion.p>
        ) : null}
      </AnimatePresence>
    </div>
  );
}

function ProgressRail({ step, total }) {
  if (total < 2) return null;
  return (
    <div className="mb-5 w-full">
      <div className="relative h-1.5 w-full overflow-hidden rounded-full bg-white/50">
        <motion.div
          className="h-full rounded-full"
          style={{ backgroundImage: "linear-gradient(to left, var(--accent), var(--accent2))" }}
          initial={false}
          animate={{ width: `${(step / total) * 100}%` }}
          transition={{ type: "spring", stiffness: 120, damping: 20 }}
        />
      </div>
      <div className="mt-2.5 flex flex-row-reverse items-center justify-center gap-2">
        {Array.from({ length: total }, (_, i) => i + 1).map((i) => (
          <motion.span
            key={i}
            className="block rounded-full"
            style={{
              backgroundImage: i <= step ? "linear-gradient(to bottom left, var(--accent2), var(--accent))" : "none",
              backgroundColor: i <= step ? undefined : "rgba(255,255,255,0.65)",
            }}
            animate={{ width: i === step ? 22 : 7, height: 7, opacity: i <= step ? 1 : 0.7 }}
            transition={{ type: "spring", stiffness: 380, damping: 26 }}
          />
        ))}
      </div>
    </div>
  );
}

function PrimaryButton({ children, onClick, disabled, pulse = false, hint }) {
  return (
    <div className="w-full">
      <div className="relative">
        {pulse && !disabled && (
          <motion.span
            className="absolute inset-0 rounded-2xl blur-lg"
            style={{ backgroundImage: "linear-gradient(to left, var(--accent), var(--accent2))" }}
            animate={{ opacity: [0.4, 0.8, 0.4], scale: [0.97, 1.06, 0.97] }}
            transition={{ duration: 1.9, repeat: Infinity, ease: "easeInOut" }}
          />
        )}
        <motion.button
          type="button"
          onClick={disabled ? undefined : onClick}
          aria-disabled={disabled}
          whileHover={disabled ? {} : { scale: 1.03 }}
          whileTap={disabled ? {} : { scale: 0.96 }}
          animate={pulse && !disabled ? { scale: [1, 1.022, 1] } : { scale: 1 }}
          transition={{ duration: 1.9, repeat: pulse && !disabled ? Infinity : 0, ease: "easeInOut" }}
          className={
            disabled
              ? "relative w-full cursor-not-allowed rounded-2xl border border-white/50 bg-white/30 px-6 py-3.5 text-base font-extrabold text-slate-400 backdrop-blur-md"
              : "relative w-full rounded-2xl px-6 py-3.5 text-base font-extrabold text-white shadow-lg"
          }
          style={disabled ? undefined : { backgroundImage: "linear-gradient(to left, var(--accent), var(--accent2))" }}
        >
          {children}
        </motion.button>
      </div>
      <AnimatePresence>
        {disabled && hint && (
          <motion.p
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: "auto" }}
            exit={{ opacity: 0, height: 0 }}
            className="mt-2 text-center text-[11px] font-medium"
            style={{ color: "var(--muted)", opacity: 0.75 }}
          >
            {hint}
          </motion.p>
        )}
      </AnimatePresence>
    </div>
  );
}

function GhostButton({ children, onClick, className = "" }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`rounded-2xl border border-white/60 bg-white/40 px-4 py-2.5 text-[12px] font-bold backdrop-blur-md ${className}`}
      style={{ color: "var(--muted)" }}
    >
      {children}
    </button>
  );
}

/** دکمه‌ی «نه» بازیگوش — فقط وقتی قالب playful باشد */
const NO_TAUNTS = [
  "بیخیال داداش، این خرابه 😜 فقط «آره» کار میده",
  "جانم؟ بازم زدی؟ 🙈 گفتم که کار نمیده",
  "هی بزن، تهش که هیچی 😏",
  "دیدی؟ در رفت 😂 ولش کن دیگه",
  "اوکی بردی 🥲 کردمش «آره»، دیگه کِرکِر نکن 😈",
];

function PlayfulNoButton({ tries, onProvoke, onSurrender, converted, offset, label, yesLabel }) {
  const [tipOpen, setTipOpen] = useState(false);
  const closeTimer = useRef(null);
  const convertedAt = useRef(0);

  const showTip = useCallback(() => {
    setTipOpen(true);
    clearTimeout(closeTimer.current);
    closeTimer.current = setTimeout(() => setTipOpen(false), 2200);
  }, []);

  useEffect(() => () => clearTimeout(closeTimer.current), []);
  useEffect(() => { if (converted) convertedAt.current = Date.now(); }, [converted]);

  /* «منطقه‌ی شکار»: دکمه فرار می‌کند، پس تپِ خطا رفته هم باید حساب شود —
     وگرنه روی موبایل (که hover ندارد) کاربر شاید هیچ‌وقت به تبدیل نرسد. */
  const pokeZone = () => {
    if (converted) return;
    onProvoke();
    showTip();
  };

  const taunt = NO_TAUNTS[Math.min(Math.max(tries - 1, 0), NO_TAUNTS.length - 1)];

  return (
    <div
      onPointerDown={pokeZone}
      className={`relative flex items-center justify-center ${converted ? "" : "h-[62px] touch-manipulation"}`}
    >
      <AnimatePresence>
        {tipOpen && (
          <motion.div
            initial={{ opacity: 0, y: 10, scale: 0.85 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 6, scale: 0.9 }}
            transition={{ type: "spring", stiffness: 400, damping: 22 }}
            className="absolute -bottom-11 z-20 max-w-[92%] rounded-2xl border border-white/60 bg-white/85 px-3.5 py-2 text-center text-[11.5px] font-semibold shadow-lg backdrop-blur-xl"
            style={{ color: "var(--muted)" }}
          >
            {taunt}
            <span className="absolute -top-1 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-l border-t border-white/60 bg-white/85" />
          </motion.div>
        )}
      </AnimatePresence>

      <motion.button
        type="button"
        aria-disabled={!converted}
        onHoverStart={() => { if (!converted) { onProvoke(); showTip(); } }}
        onFocus={showTip}
        onClick={(e) => {
          e.preventDefault();
          if (!converted) return;
          if (Date.now() - convertedAt.current < 900) return; // لحظه‌ی تبدیل را نبلع
          onSurrender();
        }}
        animate={{ x: offset.x, y: offset.y, rotate: converted ? 0 : offset.x / 14, scale: converted ? 1.04 : 1 }}
        whileTap={{ scale: converted ? 0.95 : 1 }}
        transition={{ type: "spring", stiffness: 260, damping: 14, mass: 0.7 }}
        className={
          converted
            ? "relative w-full rounded-2xl px-6 py-3 text-base font-extrabold text-white shadow-lg"
            : "absolute w-[62%] cursor-not-allowed rounded-2xl border border-white/50 bg-white/25 px-4 py-3 text-base font-bold text-slate-400/90 shadow-inner backdrop-blur-md"
        }
        style={converted ? { backgroundImage: "linear-gradient(to left, var(--accent), var(--accent2))" } : undefined}
      >
        {converted ? yesLabel : label}
        {!converted && <span className="mr-2 align-middle text-[10px] font-medium text-slate-400/80">(خرابه)</span>}
      </motion.button>
    </div>
  );
}

function Wheel({ values, value, onChange, format, label }) {
  const boxRef = useRef(null);
  const itemRefs = useRef({});

  useEffect(() => {
    const box = boxRef.current;
    const el = itemRefs.current[value];
    if (!box || !el) return;
    box.scrollTo({ top: el.offsetTop - box.clientHeight / 2 + el.clientHeight / 2, behavior: "smooth" });
  }, [value]);

  return (
    <div className="flex-1">
      <p className="mb-1.5 text-center text-[10px] font-semibold" style={{ color: "var(--muted)", opacity: 0.75 }}>{label}</p>
      <div className="relative">
        <div
          className="pointer-events-none absolute inset-x-1 top-1/2 z-10 h-10 -translate-y-1/2 rounded-xl border bg-white/30"
          style={{ borderColor: "var(--accent)" }}
        />
        <div
          ref={boxRef}
          className="h-28 snap-y snap-mandatory overflow-y-auto rounded-2xl border border-white/50 bg-white/25 py-9 backdrop-blur-md [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {values.map((v) => {
            const active = v === value;
            return (
              <button
                key={v}
                type="button"
                ref={(n) => (itemRefs.current[v] = n)}
                onClick={() => onChange(v)}
                className="flex h-10 w-full snap-center items-center justify-center"
              >
                <motion.span
                  animate={{ scale: active ? 1.35 : 0.92, opacity: active ? 1 : 0.38, fontWeight: active ? 800 : 500 }}
                  transition={{ type: "spring", stiffness: 340, damping: 24 }}
                  style={{ color: active ? "var(--accent2)" : "var(--ink)" }}
                >
                  {format(v)}
                </motion.span>
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}

/** کارت‌های روز — در صفحه‌ی ساخت و در جریان دعوت مشترک است */
function DayStrip({ days, value, onPick }) {
  return (
    <div className="-mx-1 flex snap-x snap-mandatory gap-2.5 overflow-x-auto px-1 pb-2 pt-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      {days.map((d) => {
        const active = d.id === value;
        return (
          <motion.button
            key={d.id}
            type="button"
            onClick={() => onPick(d.id)}
            whileTap={{ scale: 0.93 }}
            animate={{ scale: active ? 1.06 : 1, y: active ? -3 : 0 }}
            transition={{ type: "spring", stiffness: 400, damping: 24 }}
            className={`relative flex min-w-[66px] shrink-0 snap-center flex-col items-center gap-0.5 rounded-2xl border px-2.5 py-2.5 backdrop-blur-md ${
              active ? "border-transparent text-white shadow-lg" : "border-white/60 bg-white/35"
            }`}
            style={
              active
                ? { backgroundImage: "linear-gradient(to bottom, var(--accent2), var(--accent))" }
                : { color: "var(--muted)" }
            }
          >
            {d.badge && (
              <span
                className="absolute -top-1.5 rounded-full px-1.5 py-px text-[8.5px] font-bold"
                style={active ? { background: "#fff", color: "var(--accent2)" } : { background: "var(--accent)", color: "#fff" }}
              >
                {d.badge}
              </span>
            )}
            <span className="mt-1 text-[10.5px] opacity-80">{d.weekday}</span>
            <span className="text-xl font-black leading-tight">{d.day}</span>
            <span className="text-[9.5px] opacity-75">{d.month}</span>
            {d.isWeekend && <span className="text-[9px]">🌙</span>}
          </motion.button>
        );
      })}
    </div>
  );
}

function TimeWheels({ hour, minute, onHour, onMinute }) {
  return (
    <div className="flex items-center gap-2 rounded-3xl border border-white/50 bg-white/20 p-2.5 backdrop-blur-md">
      <Wheel label="ساعت" values={HOURS} value={hour} format={(v) => fa(pad2(v))} onChange={onHour} />
      <motion.span
        className="pb-1 text-2xl font-black"
        style={{ color: "var(--accent2)" }}
        animate={{ opacity: [1, 0.25, 1] }}
        transition={{ duration: 1.4, repeat: Infinity }}
      >
        :
      </motion.span>
      <Wheel label="دقیقه" values={MINUTES} value={minute} format={(v) => fa(pad2(v))} onChange={onMinute} />
    </div>
  );
}

function Field({ label, hint, value, onChange, placeholder, maxLength, multiline, ltr }) {
  const cls =
    "w-full rounded-2xl border border-white/60 bg-white/45 px-3.5 py-2.5 text-[12.5px] outline-none backdrop-blur-md transition placeholder:opacity-40 focus:bg-white/70";
  return (
    <label className="block">
      <span className="mb-1.5 block text-[11px] font-bold" style={{ color: "var(--muted)" }}>{label}</span>
      {multiline ? (
        <textarea
          rows={2} value={value} maxLength={maxLength} placeholder={placeholder}
          onChange={(e) => onChange(e.target.value)}
          className={cls + " resize-none"} style={{ color: "var(--ink)" }}
        />
      ) : (
        <input
          type="text" value={value} maxLength={maxLength} placeholder={placeholder}
          onChange={(e) => onChange(e.target.value)} dir={ltr ? "ltr" : "rtl"}
          className={cls + (ltr ? " text-left" : "")} style={{ color: "var(--ink)" }}
        />
      )}
      {hint && <span className="mt-1 block text-[10px]" style={{ color: "var(--muted)", opacity: 0.6 }}>{hint}</span>}
    </label>
  );
}

/** بلوک دکمه‌های نقشه */
function MapButtons({ cfg }) {
  const links = mapLinks(cfg);
  if (!links) return null;
  return (
    <div className="rounded-2xl border border-white/60 bg-white/45 p-3 backdrop-blur-md">
      <p className="mb-2 text-[11px] font-bold" style={{ color: "var(--muted)" }}>
        📍 {cfg.locName || "موقعیت مکانی"}
      </p>
      <div className="flex items-center gap-2">
        {links.map((l) => (
          <a
            key={l.id}
            href={l.href}
            target="_blank"
            rel="noopener noreferrer"
            className="flex flex-1 flex-col items-center gap-0.5 rounded-xl border border-white/60 bg-white/50 py-2 text-[11px] font-bold backdrop-blur-md"
            style={{ color: "var(--muted)" }}
          >
            <span className="text-lg leading-none">{l.emoji}</span>
            {l.label}
          </a>
        ))}
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════════════════════
   پوسته — متغیرهای رنگ اینجا ست می‌شوند
   ════════════════════════════════════════════════════════════════════════════ */

function Shell({ palette, hearts, festive, children }) {
  const vars = {
    "--bg1": palette.bg[0],
    "--bg2": palette.bg[1],
    "--bg3": palette.bg[2],
    "--accent": palette.accent,
    "--accent2": palette.accent2,
    "--ink": palette.ink,
    "--muted": palette.muted,
    fontFamily: FONT_STACK,
    backgroundImage: "linear-gradient(135deg, var(--bg1), var(--bg2), var(--bg3))",
  };
  return (
    <div dir="rtl" lang="fa" className="relative min-h-screen w-full overflow-hidden px-4 py-8" style={vars}>
      <AmbientBackdrop hearts={hearts} festive={festive} />
      <div className="relative z-10 mx-auto flex w-full max-w-sm flex-col items-center">{children}</div>
    </div>
  );
}

function GlassCard({ children, glass = 0.25 }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 28, scale: 0.96 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      transition={{ type: "spring", stiffness: 180, damping: 22 }}
      className="relative w-full overflow-hidden rounded-[2rem] border border-white/60 p-5 pb-6 shadow-[0_24px_60px_-20px_rgba(60,40,80,0.45)] backdrop-blur-2xl"
      style={{ background: `rgba(255,255,255,${glass})` }}
    >
      <div className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-white/50 to-transparent" />
      {children}
    </motion.div>
  );
}

/* ════════════════════════════════════════════════════════════════════════════
   صفحه‌ی ساخت کارت
   ════════════════════════════════════════════════════════════════════════════ */

function CreatorScreen({ draft, setDraft, onPreview }) {
  const tpl = getTemplate(draft.tpl);
  const { play, buzz } = useFx();
  const [copied, setCopied] = useState(false);
  const [locInput, setLocInput] = useState("");
  const days = useMemo(() => buildDays(30), []);
  const link = useMemo(() => buildLink(draft), [draft]);
  const set = (k) => (v) => setDraft((d) => ({ ...d, [k]: v }));

  const paletteId = draft.palette || tpl.palette;

  /* وقتی قالب عوض می‌شود، پالت پیش‌فرضِ همان قالب می‌آید — مگر کاربر
     خودش دستی رنگی انتخاب کرده باشد. */
  const chooseTemplate = (id) => {
    play("pop");
    buzz(10);
    setDraft((d) => ({ ...d, tpl: id, palette: "", accent: "" }));
  };

  const applyLocation = (raw) => {
    setLocInput(raw);
    const c = parseCoords(raw);
    setDraft((d) => ({
      ...d,
      lat: c ? c.lat : "",
      lng: c ? c.lng : "",
      locName: c ? d.locName : clip(raw, 120),
    }));
  };

  const copyLink = async () => {
    try {
      await navigator.clipboard.writeText(link);
      setCopied(true);
      play("pop");
      buzz(12);
      setTimeout(() => setCopied(false), 2200);
    } catch {
      setCopied(false);
    }
  };

  const inviteMsg = draft.to ? `${draft.to} جان، این رو برات فرستادم 👇` : "این رو برات فرستادم 👇";

  const shareTelegram = () => {
    play("yay");
    window.open(
      `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(inviteMsg)}`,
      "_blank", "noopener,noreferrer"
    );
  };

  const shareWhatsapp = () => {
    play("yay");
    const phone = normalizePhone(draft.whatsapp);
    const base = phone ? `https://wa.me/${phone}` : "https://wa.me/";
    window.open(`${base}?text=${encodeURIComponent(inviteMsg + "\n" + link)}`, "_blank", "noopener,noreferrer");
  };

  return (
    <>
      <GlassCard glass={PALETTES[paletteId]?.glass ?? 0.25}>
        <div className="relative z-20">
          <ReactionAvatar face={tpl.icon} line="" compact />

          <div className="mb-4 text-center">
            <h1 className="text-[20px] font-black leading-snug" style={{ color: "var(--ink)" }}>کارتت رو بساز 💌</h1>
            <p className="mx-auto mt-1 max-w-[19rem] text-[11.5px] leading-5" style={{ color: "var(--muted)", opacity: 0.8 }}>
              قالب و رنگ رو انتخاب کن، پرش کن، لینکو بفرست. هیچی جایی ذخیره نمیشه 🔒
            </p>
          </div>

          {/* ── قالب‌ها ── */}
          <p className="mb-2 text-[11px] font-bold" style={{ color: "var(--muted)" }}>🎨 قالب</p>
          <div className="-mx-1 mb-4 grid grid-cols-5 gap-1.5 px-1">
            {TEMPLATE_IDS.map((id) => {
              const t = TEMPLATES[id];
              const active = id === draft.tpl;
              return (
                <motion.button
                  key={id}
                  type="button"
                  onClick={() => chooseTemplate(id)}
                  whileTap={{ scale: 0.92 }}
                  animate={{ scale: active ? 1.05 : 1 }}
                  className={`flex flex-col items-center gap-0.5 rounded-xl border px-1 py-2 text-[9.5px] font-bold backdrop-blur-md ${
                    active ? "border-transparent text-white shadow-md" : "border-white/60 bg-white/40"
                  }`}
                  style={
                    active
                      ? { backgroundImage: "linear-gradient(to bottom, var(--accent2), var(--accent))" }
                      : { color: "var(--muted)" }
                  }
                >
                  <span className="text-base leading-none">{t.icon}</span>
                  {t.name}
                </motion.button>
              );
            })}
          </div>

          {/* ── رنگ‌بندی ── */}
          <p className="mb-2 text-[11px] font-bold" style={{ color: "var(--muted)" }}>🎨 رنگ‌بندی</p>
          <div className="mb-2 flex flex-wrap gap-1.5">
            {PALETTE_IDS.map((id) => {
              const p = PALETTES[id];
              const active = id === paletteId && !isHex(draft.accent);
              return (
                <button
                  key={id}
                  type="button"
                  title={p.name}
                  onClick={() => { play("tick"); setDraft((d) => ({ ...d, palette: id, accent: "" })); }}
                  className={`h-7 w-7 rounded-full border-2 transition ${active ? "scale-110" : "border-white/70"}`}
                  style={{
                    backgroundImage: `linear-gradient(135deg, ${p.accent}, ${p.accent2})`,
                    borderColor: active ? p.ink : "rgba(255,255,255,0.7)",
                  }}
                />
              );
            })}
          </div>
          <label className="mb-4 flex items-center gap-2 rounded-2xl border border-white/60 bg-white/40 px-3 py-2 backdrop-blur-md">
            <span className="text-[11px] font-bold" style={{ color: "var(--muted)" }}>رنگ دلخواه</span>
            <input
              type="color"
              value={isHex(draft.accent) ? draft.accent : PALETTES[paletteId].accent}
              onChange={(e) => setDraft((d) => ({ ...d, accent: e.target.value }))}
              className="h-7 w-12 cursor-pointer rounded border-0 bg-transparent p-0"
            />
            {isHex(draft.accent) && (
              <button
                type="button"
                onClick={() => setDraft((d) => ({ ...d, accent: "" }))}
                className="mr-auto text-[10.5px] font-bold underline"
                style={{ color: "var(--muted)" }}
              >
                برگرد به پیش‌فرض
              </button>
            )}
          </label>

          {/* ── متن‌ها ── */}
          <div className="flex flex-col gap-3">
            <Field label="اسم مخاطب" value={draft.to} onChange={set("to")} placeholder="مثلاً سارا" maxLength={60} />
            <Field label="اسم خودت" value={draft.from} onChange={set("from")} placeholder="مثلاً رضا" maxLength={60} />
            <Field
              label="سوال / عنوان (اختیاری)"
              value={draft.question}
              onChange={set("question")}
              placeholder={tpl.q}
              maxLength={140}
              hint="خالی بذاری، متن پیش‌فرض قالب می‌آید"
            />
            <Field
              label="پیام شخصی (اختیاری)"
              value={draft.note}
              onChange={set("note")}
              placeholder="یه جمله که آخر کارت بیاد"
              maxLength={300}
              multiline
            />
          </div>

          {/* ── تاریخ و ساعت: فقط قالب‌هایی که فرستنده تعیین می‌کند ── */}
          {tpl.fixedWhen && (
            <div className="mt-4">
              <p className="mb-1 text-[11px] font-bold" style={{ color: "var(--muted)" }}>📅 تاریخ و ساعت مراسم</p>
              <p className="mb-1 text-[10px]" style={{ color: "var(--muted)", opacity: 0.6 }}>
                در این قالب زمان را شما تعیین می‌کنید، نه مخاطب
              </p>
              <DayStrip days={days} value={draft.day === "" ? null : Number(draft.day)} onPick={(id) => { play("pop"); setDraft((d) => ({ ...d, day: id })); }} />
              <TimeWheels
                hour={draft.hour === "" ? 18 : Number(draft.hour)}
                minute={draft.minute === "" ? 0 : Number(draft.minute)}
                onHour={(v) => { play("tick"); setDraft((d) => ({ ...d, hour: v })); }}
                onMinute={(v) => { play("tick"); setDraft((d) => ({ ...d, minute: v })); }}
              />
            </div>
          )}

          {/* ── لوکیشن ── */}
          <div className="mt-4 flex flex-col gap-3">
            <Field
              label="📍 موقعیت مکانی (اختیاری)"
              value={locInput || draft.locName}
              onChange={applyLocation}
              placeholder="آدرس، یا لینک نشان/گوگل‌مپ، یا 35.7,51.4"
              maxLength={160}
              hint={
                draft.lat && draft.lng
                  ? `✅ مختصات خوانده شد: ${draft.lat}, ${draft.lng}`
                  : "لینک نقشه رو پیست کنی، مختصاتش خودکار درمیاد"
              }
            />
            {draft.lat && draft.lng && (
              <Field label="اسم محل (اختیاری)" value={draft.locName} onChange={set("locName")} placeholder="مثلاً باغ تالار ..." maxLength={120} />
            )}
          </div>

          {/* ── راه‌های تماس ── */}
          <div className="mt-4 flex flex-col gap-3">
            <Field
              label="آیدی تلگرامت (اختیاری)"
              value={draft.telegram}
              onChange={set("telegram")}
              placeholder="@username"
              maxLength={40}
              ltr
              hint="بذاری، جواب مخاطب مستقیم برات میاد"
            />
            <Field
              label="شماره واتس‌اپت (اختیاری)"
              value={draft.whatsapp}
              onChange={set("whatsapp")}
              placeholder="09121234567"
              maxLength={20}
              ltr
              hint={draft.whatsapp ? `به این شکل فرستاده می‌شود: +${normalizePhone(draft.whatsapp)}` : "با ۰ شروع کن، خودش به فرمت بین‌المللی می‌بره"}
            />
          </div>

          {/* ── لینک ── */}
          <div className="mt-4 rounded-2xl border border-white/60 bg-white/45 p-3 backdrop-blur-md">
            <p className="mb-1.5 text-[10.5px] font-bold" style={{ color: "var(--muted)" }}>🔗 لینکت آماده‌ست</p>
            <p dir="ltr" className="truncate text-left text-[10.5px]" style={{ color: "var(--muted)", opacity: 0.7 }} title={link}>
              {link}
            </p>
          </div>

          <div className="mt-3 flex flex-col gap-2">
            <PrimaryButton onClick={copyLink} pulse>
              {copied ? "کپی شد ✅" : "کپی لینک 📋"}
            </PrimaryButton>
            <div className="flex items-center gap-2">
              <button
                type="button" onClick={shareTelegram}
                className="flex-1 rounded-2xl border border-white/60 bg-white/50 px-3 py-2.5 text-[12px] font-bold backdrop-blur-md"
                style={{ color: "#0088cc" }}
              >
                تلگرام ✈️
              </button>
              <button
                type="button" onClick={shareWhatsapp}
                className="flex-1 rounded-2xl border border-white/60 bg-white/50 px-3 py-2.5 text-[12px] font-bold backdrop-blur-md"
                style={{ color: "#128C7E" }}
              >
                واتس‌اپ 💬
              </button>
              <GhostButton onClick={onPreview} className="flex-1">پیش‌نمایش 👀</GhostButton>
            </div>
          </div>
        </div>
      </GlassCard>

      <p className="mt-4 text-center text-[10.5px]" style={{ color: "var(--muted)", opacity: 0.5 }}>
        ساخته شده با کلی 💖 و یه ذره 😈
      </p>
    </>
  );
}

/* ════════════════════════════════════════════════════════════════════════════
   جریان دعوت (چیزی که مخاطب می‌بیند)
   ════════════════════════════════════════════════════════════════════════════ */

function ProposalFlow({ cfg, onEdit }) {
  const tpl = getTemplate(cfg.tpl);
  const { play, buzz } = useFx(tpl.celebrate || tpl.playful);

  /* شکل جریان از روی قالب ساخته می‌شود:
     قالب‌های fixedWhen مرحله‌ی «کِی» ندارند، و اگر options نداشته باشند
     مرحله‌ی گزینه‌ها هم کلاً حذف می‌شود. */
  const steps = useMemo(() => {
    const s = ["invite"];
    if (!tpl.fixedWhen) s.push("when");
    if (tpl.options) s.push("options");
    s.push("done");
    return s;
  }, [tpl]);

  const [idx, setIdx] = useState(0);
  const [dir, setDir] = useState(1);
  const [declined, setDeclined] = useState(false);
  const [mood, setMood] = useState("idle");

  const [noTries, setNoTries] = useState(0);
  const [noOffset, setNoOffset] = useState({ x: 0, y: 0 });
  const noConverted = noTries >= NO_TAUNTS.length;
  const lastProvokeRef = useRef(0);

  const days = useMemo(() => buildDays(30), []);
  const [dayId, setDayId] = useState(null);
  const [hour, setHour] = useState(20);
  const [minute, setMinute] = useState(0);
  const [timeTouched, setTimeTouched] = useState(false);

  const [picks, setPicks] = useState([]);
  const [extra, setExtra] = useState(false);
  const [copied, setCopied] = useState(false);
  const [pasteHint, setPasteHint] = useState(false);

  const current = steps[idx];
  const stepNo = idx + 1;

  /* تاریخ: یا فرستنده تعیین کرده، یا مخاطب انتخاب می‌کند */
  const senderDay = tpl.fixedWhen && cfg.day !== "" ? days[Number(cfg.day)] : null;
  const chosenDay = tpl.fixedWhen ? senderDay : dayId === null ? null : days[dayId];
  const shownHour = tpl.fixedWhen ? (cfg.hour === "" ? 18 : Number(cfg.hour)) : hour;
  const shownMinute = tpl.fixedWhen ? (cfg.minute === "" ? 0 : Number(cfg.minute)) : minute;

  const timeLabel = `${fa(pad2(shownHour))}:${fa(pad2(shownMinute))}`;
  const dateLabel = chosenDay ? `${chosenDay.weekday} ${chosenDay.day} ${chosenDay.month}` : "—";
  const pickLabels = tpl.options ? tpl.options.filter((o) => picks.includes(o.id)) : [];

  const go = useCallback(
    (next, nextMood) => {
      setDir(next > idx ? 1 : -1);
      setIdx(next);
      if (nextMood) setMood(nextMood);
      play("swoosh");
      buzz([10, 40, 14]);
    },
    [idx, play, buzz]
  );

  const accept = () => {
    play("yay");
    buzz([18, 50, 18]);
    go(1, tpl.fixedWhen ? (tpl.options ? "pickA" : "done") : "think");
  };

  const decline = () => {
    play("tick");
    buzz(10);
    setDeclined(true);
  };

  /* throttle چون دکمه زیر نشانگر جابه‌جا می‌شود و hover پشت‌سرهم شلیک می‌کند */
  const provokeNo = useCallback(() => {
    const now = Date.now();
    if (now - lastProvokeRef.current < 500) return;
    lastProvokeRef.current = now;
    const next = Math.min(noTries + 1, NO_TAUNTS.length);
    if (next === noTries) return;
    setNoTries(next);
    if (next === 1) { setMood("shy"); play("tick"); buzz(8); }
    else if (next >= NO_TAUNTS.length) { setMood("sly"); setNoOffset({ x: 0, y: 0 }); play("yay"); buzz([15, 60, 15, 60, 25]); }
    else { setMood("sly"); setNoOffset({ x: rand(-56, 56), y: rand(-22, 22) }); play("swoosh"); buzz([12, 30, 12]); }
  }, [noTries, play, buzz]);

  const togglePick = (id) => {
    play("pop");
    buzz(10);
    const next = picks.includes(id) ? picks.filter((p) => p !== id) : [...picks, id];
    setPicks(next);
    setMood(next.length >= 2 ? "pickB" : next.length === 1 ? "pickA" : "think");
  };

  /* ── متن پاسخ ── */
  const answerText = useMemo(() => {
    const lines = [
      cfg.to && !tpl.fixedWhen ? `${cfg.to} جان، ${tpl.shareLead}` : tpl.shareLead,
      "",
      `📅 ${tpl.whenLabel.replace(/^\S+\s/, "")}: ${dateLabel} — ${timeLabel}`,
    ];
    if (tpl.options) {
      const menu = pickLabels.length ? pickLabels.map((o) => `${o.emoji} ${o.label}`).join("، ") : "—";
      lines.push(`${tpl.optLabel}: ${menu}`);
    }
    if (tpl.extra) lines.push(`✨ ${extra ? tpl.extra.yes : tpl.extra.nope}`);
    if (cfg.locName || (cfg.lat && cfg.lng)) lines.push(`📍 ${cfg.locName || `${cfg.lat},${cfg.lng}`}`);
    lines.push("", tpl.status(timeLabel));
    if (cfg.from) lines.push(`— ${cfg.from}`);
    return lines.filter((l, i, a) => !(l === "" && a[i - 1] === "")).join("\n");
  }, [cfg, tpl, dateLabel, timeLabel, pickLabels, extra]);

  const declineText = useMemo(
    () => `${tpl.declineTitle || "متأسفانه نمی‌تونم"}${cfg.from ? `\n— برای ${cfg.from}` : ""}`,
    [tpl, cfg.from]
  );

  const replyBody = declined ? declineText : answerText;

  const sendTelegram = async () => {
    play("yay");
    buzz([20, 40, 20]);
    if (cfg.telegram) {
      // تلگرام اجازه‌ی پیش‌پرکردن پیام برای یک کاربر مشخص را نمی‌دهد
      try {
        await navigator.clipboard.writeText(replyBody);
        setPasteHint(true);
        setTimeout(() => setPasteHint(false), 6000);
      } catch { /* بی‌خیال، چت را باز می‌کنیم */ }
      window.open(`https://t.me/${encodeURIComponent(cfg.telegram)}`, "_blank", "noopener,noreferrer");
      return;
    }
    window.open(
      `https://t.me/share/url?url=${encodeURIComponent("https://t.me/")}&text=${encodeURIComponent(replyBody)}`,
      "_blank", "noopener,noreferrer"
    );
  };

  const sendWhatsapp = () => {
    play("yay");
    buzz([20, 40, 20]);
    // برخلاف تلگرام، wa.me متن را برای شماره‌ی مشخص هم پیش‌پر می‌کند
    const base = cfg.whatsapp ? `https://wa.me/${cfg.whatsapp}` : "https://wa.me/";
    window.open(`${base}?text=${encodeURIComponent(replyBody)}`, "_blank", "noopener,noreferrer");
  };

  const copyAnswer = async () => {
    try {
      await navigator.clipboard.writeText(replyBody);
      setCopied(true);
      play("pop");
      buzz(12);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  };

  const variants = {
    enter: (d) => ({ opacity: 0, x: d > 0 ? -60 : 60, scale: 0.94, filter: "blur(6px)" }),
    center: { opacity: 1, x: 0, scale: 1, filter: "blur(0px)" },
    exit: (d) => ({ opacity: 0, x: d > 0 ? 60 : -60, scale: 0.94, filter: "blur(6px)" }),
  };
  const transition = { type: "spring", stiffness: 260, damping: 28, mass: 0.8 };

  /* در قالب‌های رسمی اسم را به تیتر نمی‌چسبانیم — «سارا، مراسم یادبود» غلط
     است؛ خطاب باید سطر جدا باشد: «سارا عزیز،» بعد «مراسم یادبود». */
  const greeting = current === "invite" && !tpl.playful && cfg.to ? `${cfg.to} عزیز،` : "";

  const title =
    current === "invite"
      ? cfg.question || (tpl.playful && cfg.to ? `${cfg.to}، ${tpl.q}` : tpl.q)
      : current === "when" ? tpl.whenTitle
      : current === "options" ? tpl.optTitle
      : cfg.to && tpl.playful ? `${tpl.doneTitle.replace(/!$/, "")}، ${cfg.to}!` : tpl.doneTitle;

  const sub =
    current === "invite" ? tpl.sub
      : current === "when" ? tpl.whenSub
      : current === "options" ? tpl.optSub
      : tpl.doneSub;

  const compact = current === "when" || current === "options";
  const confettiColors = useMemo(() => {
    const p = resolvePalette(cfg.palette || tpl.palette, cfg.accent);
    return [p.accent, p.accent2, shade(p.accent, 25), shade(p.accent2, 25), "#ffffff", p.bg[1]];
  }, [cfg.palette, cfg.accent, tpl.palette]);

  /* ── مسیر رد کردن دعوت ── */
  if (declined) {
    return (
      <>
        <GlassCard>
          <div className="relative z-20">
            <ReactionAvatar face="🤍" line="" />
            <div className="mb-4 text-center">
              <h1 className="text-[20px] font-black leading-snug" style={{ color: "var(--ink)" }}>
                {tpl.declineTitle || "ممنون که خبر دادی"}
              </h1>
              {tpl.declineSub && (
                <p className="mx-auto mt-1.5 max-w-[19rem] text-[12px] leading-6" style={{ color: "var(--muted)", opacity: 0.8 }}>
                  {tpl.declineSub}
                </p>
              )}
            </div>
            <div className="flex flex-col gap-2">
              <PrimaryButton onClick={sendTelegram}>
                {cfg.telegram ? (<>خبر بده به <bdi dir="ltr">@{cfg.telegram}</bdi> ✈️</>) : "بفرست تو تلگرام ✈️"}
              </PrimaryButton>
              <div className="flex items-center gap-2">
                <button
                  type="button" onClick={sendWhatsapp}
                  className="flex-1 rounded-2xl border border-white/60 bg-white/50 px-3 py-2.5 text-[12px] font-bold backdrop-blur-md"
                  style={{ color: "#128C7E" }}
                >
                  واتس‌اپ 💬
                </button>
                <GhostButton onClick={() => { setDeclined(false); setIdx(0); }} className="flex-1">
                  برگرد ↩
                </GhostButton>
              </div>
            </div>
          </div>
        </GlassCard>
        {onEdit && <GhostButton onClick={onEdit} className="mt-3">↩ برگرد به ویرایش</GhostButton>}
      </>
    );
  }

  return (
    <>
      <GlassCard glass={PALETTES[cfg.palette || tpl.palette]?.glass ?? 0.25}>
        {current === "done" && tpl.celebrate && <ConfettiCanvas fire={idx} colors={confettiColors} />}

        <div className="relative z-20">
          <ProgressRail step={stepNo} total={steps.length} />
          <ReactionAvatar face={tpl.moods[mood] || tpl.icon} line={tpl.lines[mood] || ""} compact={compact} />

          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={`h-${current}`}
              initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.25 }}
              className={compact ? "mb-3.5 text-center" : "mb-5 text-center"}
            >
              {greeting && (
                <p className="mb-1 text-[12.5px] font-bold" style={{ color: "var(--muted)" }}>{greeting}</p>
              )}
              <h1 className={`font-black leading-snug ${compact ? "text-[19px]" : "text-[21px]"}`} style={{ color: "var(--ink)" }}>
                {title}
              </h1>
              {sub && (
                <p
                  className={`mx-auto max-w-[19rem] ${compact ? "mt-1 text-[11.5px] leading-5" : "mt-1.5 text-[12.5px] leading-6"}`}
                  style={{ color: "var(--muted)", opacity: 0.8 }}
                >
                  {sub}
                </p>
              )}
            </motion.div>
          </AnimatePresence>

          <AnimatePresence mode="wait" custom={dir} initial={false}>
            {/* ───────── دعوت ───────── */}
            {current === "invite" && (
              <motion.div key="invite" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-3">
                {/* در قالب‌های زمان‌ثابت، مخاطب باید قبل از تأیید بداند کِی و کجاست */}
                {tpl.fixedWhen && chosenDay && (
                  <div className="rounded-2xl border border-white/60 bg-white/45 p-3 text-center backdrop-blur-md">
                    <p className="text-[12.5px] font-extrabold" style={{ color: "var(--ink)" }}>
                      📅 {dateLabel} — <span className="tabular-nums">{timeLabel}</span>
                    </p>
                  </div>
                )}
                {tpl.fixedWhen && <MapButtons cfg={cfg} />}

                <motion.div onHoverStart={() => setMood("love")} onHoverEnd={() => setMood(noTries > 0 ? "sly" : "idle")}>
                  <PrimaryButton onClick={accept} pulse={tpl.celebrate}>{tpl.yes}</PrimaryButton>
                </motion.div>

                {tpl.playful ? (
                  <>
                    <PlayfulNoButton
                      tries={noTries} converted={noConverted} offset={noOffset}
                      onProvoke={provokeNo} onSurrender={accept}
                      label={tpl.no} yesLabel={tpl.yes}
                    />
                    <p className="mt-1 text-center text-[10.5px]" style={{ color: "var(--muted)", opacity: 0.45 }}>
                      پ.ن: دکمه «نه» تو نسخه بتاست... تا ابد 🤭
                    </p>
                  </>
                ) : (
                  <button
                    type="button"
                    onClick={decline}
                    className="w-full rounded-2xl border border-white/60 bg-white/35 px-6 py-3 text-[13px] font-bold backdrop-blur-md"
                    style={{ color: "var(--muted)" }}
                  >
                    {tpl.no}
                  </button>
                )}
              </motion.div>
            )}

            {/* ───────── انتخاب زمان ───────── */}
            {current === "when" && (
              <motion.div key="when" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-3">
                <div>
                  <p className="mb-2 text-[11px] font-bold" style={{ color: "var(--muted)" }}>📅 کدوم روز؟</p>
                  <DayStrip days={days} value={dayId} onPick={(id) => { setDayId(id); setMood("glad"); play("pop"); buzz(10); }} />
                </div>
                <div>
                  <p className="mb-2 text-[11px] font-bold" style={{ color: "var(--muted)" }}>⏰ ساعت چند؟</p>
                  <TimeWheels
                    hour={hour} minute={minute}
                    onHour={(v) => { setHour(v); setTimeTouched(true); setMood("glad"); play("tick"); buzz(6); }}
                    onMinute={(v) => { setMinute(v); setTimeTouched(true); setMood("glad"); play("tick"); buzz(6); }}
                  />
                  <p className="mt-2 text-center text-[11px] font-bold" style={{ color: "var(--muted)" }}>
                    سِت شد: <span style={{ color: "var(--ink)" }}>{dateLabel !== "—" ? dateLabel : "روز؟"}</span>
                    {" — "}
                    <span className="tabular-nums" style={{ color: "var(--ink)" }}>{timeLabel}</span>
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <GhostButton onClick={() => go(idx - 1, "idle")} className="shrink-0 py-3.5">↩</GhostButton>
                  <PrimaryButton
                    onClick={() => go(idx + 1, tpl.options ? "pickA" : "done")}
                    disabled={dayId === null || !timeTouched}
                    hint={dayId === null ? "اول روزو بزن 😊" : "ساعتم بزن دیگه (رو عدد بزن) ⏰"}
                  >
                    بریم بعدی ✨
                  </PrimaryButton>
                </div>
              </motion.div>
            )}

            {/* ───────── گزینه‌ها ───────── */}
            {current === "options" && (
              <motion.div key="options" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-4">
                <div className="grid grid-cols-2 gap-2.5">
                  {tpl.options.map((o, i) => {
                    const active = picks.includes(o.id);
                    return (
                      <motion.button
                        key={o.id}
                        type="button"
                        onClick={() => togglePick(o.id)}
                        initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: i * 0.06, type: "spring", stiffness: 300, damping: 22 }}
                        whileTap={{ scale: 0.94 }} whileHover={{ y: -3 }}
                        className={`relative overflow-hidden rounded-2xl border p-3 text-right backdrop-blur-md ${
                          active ? "border-transparent shadow-lg ring-2" : "border-white/60 bg-white/30"
                        }`}
                        style={
                          active
                            ? { backgroundImage: "linear-gradient(to bottom left, var(--accent), var(--accent2))", "--tw-ring-color": "var(--accent)" }
                            : undefined
                        }
                      >
                        <AnimatePresence>
                          {active && (
                            <motion.span
                              initial={{ scale: 0, rotate: -90 }} animate={{ scale: 1, rotate: 0 }} exit={{ scale: 0 }}
                              className="absolute left-2 top-2 grid h-5 w-5 place-items-center rounded-full bg-white text-[10px] font-black shadow"
                              style={{ color: "var(--accent2)" }}
                            >
                              ✓
                            </motion.span>
                          )}
                        </AnimatePresence>
                        <motion.span
                          className="block text-3xl"
                          animate={active ? { scale: [1, 1.25, 1], rotate: [0, -8, 8, 0] } : { scale: 1 }}
                          transition={{ duration: 0.5 }}
                        >
                          {o.emoji}
                        </motion.span>
                        <span className="mt-1.5 block text-[13px] font-extrabold" style={{ color: active ? "#fff" : "var(--ink)" }}>
                          {o.label}
                        </span>
                        {o.hint && (
                          <span className="block text-[10px]" style={{ color: active ? "rgba(255,255,255,0.85)" : "var(--muted)", opacity: active ? 1 : 0.6 }}>
                            {o.hint}
                          </span>
                        )}
                      </motion.button>
                    );
                  })}
                </div>

                {tpl.extra && (
                  <motion.button
                    type="button"
                    onClick={() => {
                      const next = !extra;
                      setExtra(next);
                      setMood(next ? "pickB" : picks.length ? "pickA" : "think");
                      play("pop");
                      buzz([10, 30, 10]);
                    }}
                    whileTap={{ scale: 0.98 }}
                    className="flex w-full items-center justify-between rounded-2xl border p-3 backdrop-blur-md"
                    style={
                      extra
                        ? { borderColor: "var(--accent)", background: "rgba(255,255,255,0.5)" }
                        : { borderColor: "rgba(255,255,255,0.6)", background: "rgba(255,255,255,0.3)" }
                    }
                  >
                    <span className="text-right">
                      <span className="block text-[12.5px] font-extrabold" style={{ color: "var(--ink)" }}>{tpl.extra.label}</span>
                      <span className="block text-[10px]" style={{ color: "var(--muted)", opacity: 0.65 }}>
                        {extra ? tpl.extra.on : tpl.extra.off}
                      </span>
                    </span>
                    <span
                      className="relative flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors"
                      style={extra ? { backgroundImage: "linear-gradient(to left, var(--accent), var(--accent2))" } : { background: "rgba(255,255,255,0.7)" }}
                    >
                      <motion.span
                        layout transition={{ type: "spring", stiffness: 600, damping: 32 }}
                        className={`h-5 w-5 rounded-full bg-white shadow ${extra ? "mr-auto" : "ml-auto"}`}
                      />
                    </span>
                  </motion.button>
                )}

                <div className="flex items-center gap-2">
                  <GhostButton onClick={() => go(idx - 1, "think")} className="shrink-0 py-3.5">↩</GhostButton>
                  <PrimaryButton
                    onClick={() => go(idx + 1, "done")}
                    disabled={picks.length === 0}
                    hint="حداقل یدونه انتخاب کن 😅"
                    pulse={tpl.celebrate}
                  >
                    ثبت نهایی 💌
                  </PrimaryButton>
                </div>
              </motion.div>
            )}

            {/* ───────── کارت نهایی ───────── */}
            {current === "done" && (
              <motion.div key="done" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-4">
                <motion.div
                  initial={{ opacity: 0, y: 24 }} animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.15, type: "spring", stiffness: 200, damping: 20 }}
                  className="relative rounded-2xl border border-white/70 bg-white/70 p-4 shadow-lg backdrop-blur-xl"
                >
                  <div className="flex items-center justify-between border-b border-dashed pb-2.5" style={{ borderColor: "var(--accent)" }}>
                    <span className="text-[11px] font-black tracking-wide" style={{ color: "var(--accent2)" }}>{tpl.ticket}</span>
                    <span className="rounded-full px-2 py-0.5 text-[9.5px] font-bold" style={{ background: "rgba(255,255,255,0.7)", color: "var(--muted)" }}>
                      کد: {fa(`${pad2(shownHour)}${pad2(shownMinute)}`)}
                    </span>
                  </div>

                  <dl className="space-y-2.5 pt-3 text-[12.5px]">
                    <div className="flex items-start justify-between gap-3">
                      <dt className="shrink-0" style={{ color: "var(--muted)", opacity: 0.75 }}>{tpl.whenLabel}</dt>
                      <dd className="text-left font-extrabold" style={{ color: "var(--ink)" }}>
                        {dateLabel}
                        <span className="mr-1.5 tabular-nums">— {timeLabel}</span>
                      </dd>
                    </div>
                    {tpl.options && (
                      <div className="flex items-start justify-between gap-3">
                        <dt className="shrink-0" style={{ color: "var(--muted)", opacity: 0.75 }}>{tpl.optLabel}</dt>
                        <dd className="text-left font-extrabold" style={{ color: "var(--ink)" }}>
                          {pickLabels.length ? pickLabels.map((o) => `${o.emoji} ${o.label}`).join(" + ") : "—"}
                        </dd>
                      </div>
                    )}
                    {tpl.extra && (
                      <div className="flex items-start justify-between gap-3">
                        <dt className="shrink-0" style={{ color: "var(--muted)", opacity: 0.75 }}>{tpl.extra.extraLabel || "✨ اکسترا"}</dt>
                        <dd className="text-left font-extrabold" style={{ color: "var(--ink)" }}>
                          {extra ? tpl.extra.yes : tpl.extra.nope}
                        </dd>
                      </div>
                    )}
                    {cfg.from && (
                      <div className="flex items-start justify-between gap-3">
                        <dt className="shrink-0" style={{ color: "var(--muted)", opacity: 0.75 }}>💌 از طرف</dt>
                        <dd className="text-left font-extrabold" style={{ color: "var(--ink)" }}>{cfg.from}</dd>
                      </div>
                    )}
                    <div className="border-t border-dashed pt-2.5" style={{ borderColor: "var(--accent)" }}>
                      <div className="flex items-start justify-between gap-3">
                        <dt className="shrink-0" style={{ color: "var(--muted)", opacity: 0.75 }}>{tpl.statusLabel || "🚗 وضعیت"}</dt>
                        <dd className="text-left font-extrabold" style={{ color: "var(--accent2)" }}>{tpl.status(timeLabel)}</dd>
                      </div>
                    </div>
                  </dl>

                  <motion.div
                    className="mt-3 h-1 rounded-full"
                    style={{ backgroundImage: "linear-gradient(to left, var(--accent), var(--accent2))", transformOrigin: "right" }}
                    initial={{ scaleX: 0 }} animate={{ scaleX: 1 }} transition={{ delay: 0.5, duration: 0.7 }}
                  />
                </motion.div>

                <MapButtons cfg={cfg} />

                {cfg.note && (
                  <motion.div
                    initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.35 }}
                    className="rounded-2xl border border-white/60 bg-white/45 px-4 py-3 text-center backdrop-blur-md"
                  >
                    <p className="text-[12px] leading-6" style={{ color: "var(--ink)" }}>«{cfg.note}»</p>
                    {cfg.from && <p className="mt-1 text-[10.5px] font-bold" style={{ color: "var(--muted)" }}>— {cfg.from}</p>}
                  </motion.div>
                )}

                <PrimaryButton onClick={sendTelegram} pulse={tpl.celebrate}>
                  {/* bdi لازم است وگرنه @ در متن راست‌به‌چپ به سمت اشتباه می‌پرد */}
                  {cfg.telegram ? (<>جوابو بفرست به <bdi dir="ltr">@{cfg.telegram}</bdi> ✈️</>) : "بفرست تو تلگرام ✈️"}
                </PrimaryButton>

                <AnimatePresence>
                  {pasteHint && (
                    <motion.p
                      initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: "auto" }} exit={{ opacity: 0, height: 0 }}
                      className="-mt-2 text-center text-[11px] font-bold" style={{ color: "var(--muted)" }}
                    >
                      متن کپی شد ✅ تو چت فقط پیستش کن
                    </motion.p>
                  )}
                </AnimatePresence>

                <div className="flex items-center gap-2">
                  <button
                    type="button" onClick={sendWhatsapp}
                    className="flex-1 rounded-2xl border border-white/60 bg-white/50 px-3 py-2.5 text-[12px] font-bold backdrop-blur-md"
                    style={{ color: "#128C7E" }}
                  >
                    واتس‌اپ 💬
                  </button>
                  <button
                    type="button" onClick={copyAnswer}
                    className="flex-1 rounded-2xl border border-white/60 bg-white/40 px-3 py-2.5 text-[12px] font-bold backdrop-blur-md"
                    style={{ color: "var(--muted)" }}
                  >
                    {copied ? "کپی شد ✅" : "کپی 📋"}
                  </button>
                </div>
              </motion.div>
            )}
          </AnimatePresence>
        </div>
      </GlassCard>

      {/* فقط در پیش‌نمایش — مخاطب این را نمی‌بیند */}
      {onEdit && <GhostButton onClick={onEdit} className="mt-3">↩ برگرد به ویرایش</GhostButton>}

      <p className="mt-4 text-center text-[10.5px]" style={{ color: "var(--muted)", opacity: 0.5 }}>
        ساخته شده با کلی 💖 و یه ذره 😈
      </p>
    </>
  );
}

/* ════════════════════════════════════════════════════════════════════════════
   ورودی اپ
   ════════════════════════════════════════════════════════════════════════════ */

export default function DateProposalApp({ fontUrl = VAZIRMATN_WOFF2 }) {
  useVazirmatn(fontUrl);

  const [cfg, setCfg] = useState(readHashConfig);
  const [draft, setDraft] = useState(() => ({ ...EMPTY_CFG, ...(readHashConfig() || {}) }));
  const [previewing, setPreviewing] = useState(false);

  useEffect(() => {
    const onHash = () => {
      const next = readHashConfig();
      setCfg(next);
      if (!next) setPreviewing(false);
    };
    window.addEventListener("hashchange", onHash);
    return () => window.removeEventListener("hashchange", onHash);
  }, []);

  const preview = () => {
    setPreviewing(true);
    window.location.hash = `c=${encodeConfig(draft)}`;
  };

  const backToEditor = () => {
    setPreviewing(false);
    window.history.replaceState(null, "", window.location.pathname + window.location.search);
    setCfg(null);
  };

  const active = cfg || draft;
  const tpl = getTemplate(active.tpl);
  const palette = resolvePalette(active.palette || tpl.palette, active.accent);

  return (
    <Shell palette={palette} hearts={tpl.hearts} festive={tpl.celebrate}>
      {cfg ? (
        <ProposalFlow key={encodeConfig(cfg)} cfg={cfg} onEdit={previewing ? backToEditor : undefined} />
      ) : (
        <CreatorScreen draft={draft} setDraft={setDraft} onPreview={preview} />
      )}
    </Shell>
  );
}
