/**
 * DateProposalApp — یک اپ تک‌کامپوننتی، موبایل‌فرست، رمانتیک و بازیگوش 💖
 *
 * وابستگی‌ها: react, framer-motion, tailwindcss
 * (کانفتی، تقویم جلالی و افکت صوتی همگی بومی پیاده‌سازی شده‌اند — بدون پکیج اضافه)
 *
 *   import DateProposalApp from "./DateProposalApp";
 *   export default function App() { return <DateProposalApp />; }
 */

import React, {
  useState,
  useEffect,
  useMemo,
  useRef,
  useCallback,
} from "react";
import { motion, AnimatePresence } from "framer-motion";

/* ────────────────────────────────────────────────────────────────────────────
   تایپوگرافی فارسی — وزیرمتن
   ──────────────────────────────────────────────────────────────────────────── */

/**
 * نسخه‌ی variable وزیرمتن: یک فایل ~۱۱۱KB که کل وزن‌های ۱۰۰ تا ۹۰۰ را پوشش
 * می‌دهد (این کامپوننت از ۴۰۰ تا ۹۰۰ استفاده می‌کند) — یعنی فقط یک درخواست
 * شبکه، نه شش تا.
 *
 * برای میزبانی محلی/آفلاین: `npm i vazirmatn` و بعد
 * `<DateProposalApp fontUrl={new URL('vazirmatn/fonts/webfonts/Vazirmatn[wght].woff2', import.meta.url).href} />`
 * یا هر آدرس دیگری از سرور خودت. با `fontUrl={null}` تزریق فونت خاموش می‌شود.
 */
const VAZIRMATN_WOFF2 =
  "https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/fonts/webfonts/Vazirmatn%5Bwght%5D.woff2";

const FONT_STACK =
  "Vazirmatn, 'Vazir', 'IRANSans', 'IRANYekan', 'Segoe UI', Tahoma, system-ui, sans-serif";

/** یک‌بار @font-face را به head تزریق می‌کند (بعد از unmount حذف نمی‌شود تا فونت دوباره fetch نشود) */
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

/* ────────────────────────────────────────────────────────────────────────────
   ابزارهای کوچک
   ──────────────────────────────────────────────────────────────────────────── */

const PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹";
const fa = (n) => String(n).replace(/\d/g, (d) => PERSIAN_DIGITS[d]);
const pad2 = (n) => String(n).padStart(2, "0");
const rand = (min, max) => Math.random() * (max - min) + min;
const pick = (arr) => arr[Math.floor(Math.random() * arr.length)];

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

/** ۱۴ روز آینده به‌صورت کارت‌های قابل انتخاب */
const buildDays = () => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Array.from({ length: 14 }, (_, i) => {
    const d = new Date(today);
    d.setDate(today.getDate() + i);
    return {
      id: i,
      date: d,
      weekday: jalali(d, { weekday: "long" }),
      day: jalali(d, { day: "numeric" }),
      month: jalali(d, { month: "long" }),
      badge: i === 0 ? "امروز" : i === 1 ? "فردا" : null,
      isWeekend: d.getDay() === 4 || d.getDay() === 5, // پنجشنبه / جمعه
    };
  });
};

const HOURS = Array.from({ length: 16 }, (_, i) => i + 8); // ۸ تا ۲۳
const MINUTES = [0, 4, 15, 30, 45];

const FOODS = [
  { id: "pizza", emoji: "🍕", label: "پیتزا داغ", hint: "پنیرش کش بیاد تا آسمون", tone: "from-orange-300/70 to-rose-300/70" },
  { id: "coffee", emoji: "☕", label: "قهوه و دسر", hint: "چیل و دنج", tone: "from-amber-300/70 to-pink-300/70" },
  { id: "kabab", emoji: "🍢", label: "کوبیده مشتی", hint: "با دوغ، وگرنه نه", tone: "from-red-300/70 to-fuchsia-300/70" },
  { id: "pasta", emoji: "🍝", label: "پاستا آلفردو", hint: "خامه‌ای و لاکچری", tone: "from-yellow-300/70 to-purple-300/70" },
];

const NO_TAUNTS = [
  "بیخیال داداش، این خرابه 😜 فقط «آره» کار میده",
  "جانم؟ بازم زدی؟ 🙈 گفتم که کار نمیده",
  "هی بزن، تهش که هیچی 😏",
  "دیدی؟ در رفت 😂 ولش کن دیگه",
  "اوکی بردی 🥲 کردمش «آره»، دیگه کِرکِر نکن 😈",
];

const MOODS = {
  curious: { face: "🥺", line: "خب؟ چی میگی؟ 👀", glow: "bg-rose-300/60" },
  shy: { face: "🙈", line: "اوففف خجالت کشیدم", glow: "bg-purple-300/60" },
  love: { face: "🥰", line: "ایوللل همینه 🔥", glow: "bg-pink-400/60" },
  mischief: { face: "😈", line: "گفتم که فقط «آره» تو مرامه", glow: "bg-fuchsia-400/60" },
  think: { face: "🤔", line: "خب کِی بیکاری؟", glow: "bg-indigo-300/60" },
  excited: { face: "😍", line: "اوکی ترکوندی، بریم بعدی", glow: "bg-rose-400/60" },
  yum: { face: "😋", line: "از الان ضعف کردم...", glow: "bg-amber-300/60" },
  star: { face: "🤩", line: "سلیقت خفنه به خدا", glow: "bg-violet-400/60" },
  party: { face: "🥳", line: "قرارمون سِت شد رسماً", glow: "bg-pink-500/60" },
};

/* ────────────────────────────────────────────────────────────────────────────
   شبیه‌ساز افکت صوتی + هپتیک (بدون فایل صوتی، با WebAudio)
   ──────────────────────────────────────────────────────────────────────────── */

function useFx() {
  const ctxRef = useRef(null);

  const play = useCallback((kind = "pop") => {
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
  }, []);

  const buzz = useCallback((pattern = 12) => {
    try {
      navigator.vibrate?.(pattern);
    } catch {
      /* هپتیک اختیاری است */
    }
  }, []);

  return { play, buzz };
}

/* ────────────────────────────────────────────────────────────────────────────
   پس‌زمینه: قلب‌های شناور + ذرات درخشان
   ──────────────────────────────────────────────────────────────────────────── */

function AmbientBackdrop() {
  const hearts = useMemo(
    () =>
      Array.from({ length: 14 }, (_, i) => ({
        id: i,
        char: pick(["💖", "💗", "💞", "🩷", "❤️", "💘"]),
        left: rand(2, 94),
        size: rand(14, 30),
        delay: rand(0, 9),
        duration: rand(11, 20),
        drift: rand(-40, 40),
      })),
    []
  );

  const sparks = useMemo(
    () =>
      Array.from({ length: 26 }, (_, i) => ({
        id: i,
        left: rand(0, 100),
        top: rand(0, 100),
        size: rand(2, 5),
        delay: rand(0, 5),
        duration: rand(2.4, 5.5),
      })),
    []
  );

  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      {/* لکه‌های رنگی محو (پاستل / غروب) */}
      <motion.div
        className="absolute -top-24 -right-16 h-72 w-72 rounded-full bg-rose-300/50 blur-3xl"
        animate={{ scale: [1, 1.18, 1], x: [0, 24, 0], y: [0, 18, 0] }}
        transition={{ duration: 14, repeat: Infinity, ease: "easeInOut" }}
      />
      <motion.div
        className="absolute top-1/3 -left-24 h-80 w-80 rounded-full bg-violet-300/50 blur-3xl"
        animate={{ scale: [1.1, 1, 1.1], x: [0, -20, 0], y: [0, -26, 0] }}
        transition={{ duration: 17, repeat: Infinity, ease: "easeInOut" }}
      />
      <motion.div
        className="absolute -bottom-28 right-1/4 h-72 w-72 rounded-full bg-amber-200/60 blur-3xl"
        animate={{ scale: [1, 1.22, 1], y: [0, -22, 0] }}
        transition={{ duration: 19, repeat: Infinity, ease: "easeInOut" }}
      />

      {/* ذرات درخشان */}
      {sparks.map((s) => (
        <motion.span
          key={s.id}
          className="absolute rounded-full bg-white shadow-[0_0_8px_2px_rgba(255,255,255,0.8)]"
          style={{ left: `${s.left}%`, top: `${s.top}%`, width: s.size, height: s.size }}
          animate={{ opacity: [0, 1, 0], scale: [0.4, 1.3, 0.4] }}
          transition={{ duration: s.duration, delay: s.delay, repeat: Infinity, ease: "easeInOut" }}
        />
      ))}

      {/* قلب‌های شناور */}
      {hearts.map((h) => (
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

/* ────────────────────────────────────────────────────────────────────────────
   کانفتی (Canvas، بومی — بدون canvas-confetti)
   ──────────────────────────────────────────────────────────────────────────── */

function ConfettiCanvas({ fire }) {
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

    const colors = ["#f472b6", "#fb7185", "#c084fc", "#a78bfa", "#fbbf24", "#fda4af", "#f0abfc", "#ffffff"];

    const burst = (ox, oy, count) => {
      for (let i = 0; i < count; i++) {
        const angle = rand(-Math.PI, 0) + rand(-0.4, 0.4);
        const speed = rand(4, 12);
        partsRef.current.push({
          x: ox,
          y: oy,
          vx: Math.cos(angle) * speed,
          vy: Math.sin(angle) * speed,
          w: rand(5, 11),
          h: rand(7, 15),
          rot: rand(0, Math.PI * 2),
          vr: rand(-0.28, 0.28),
          color: pick(colors),
          life: rand(90, 190),
          age: 0,
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
        p.vy += 0.16; // جاذبه
        p.vx *= 0.992;
        p.vy *= 0.992;
        p.x += p.vx;
        p.y += p.vy;
        p.rot += p.vr;

        if (p.age > p.life || p.y > canvas.clientHeight + 60) {
          parts.splice(i, 1);
          continue;
        }

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
  }, [fire]);

  return <canvas ref={canvasRef} className="pointer-events-none absolute inset-0 z-30 h-full w-full" />;
}

/* ────────────────────────────────────────────────────────────────────────────
   آواتار واکنشی
   ──────────────────────────────────────────────────────────────────────────── */

function ReactionAvatar({ mood, compact = false }) {
  const m = MOODS[mood] || MOODS.curious;
  const ring = compact ? "h-[62px] w-[62px]" : "h-[86px] w-[86px]";
  const halo = compact ? "h-16 w-16" : "h-24 w-24";
  const face = compact ? "text-[32px]" : "text-[44px]";
  return (
    <div className={`relative grid place-items-center ${compact ? "mb-2" : "mb-3"}`}>
      <div className="relative grid place-items-center">
        <motion.div
          className={`absolute rounded-full blur-2xl ${halo} ${m.glow}`}
          animate={{ scale: [1, 1.25, 1], opacity: [0.55, 0.95, 0.55] }}
          transition={{ duration: 2.8, repeat: Infinity, ease: "easeInOut" }}
        />
        <motion.div
          className={`relative grid ${ring} place-items-center rounded-full border border-white/70 bg-white/40 shadow-[0_10px_30px_-8px_rgba(190,80,170,0.55)] backdrop-blur-xl`}
          animate={{ y: [0, -6, 0], rotate: [-2.5, 2.5, -2.5] }}
          transition={{ duration: 4.5, repeat: Infinity, ease: "easeInOut" }}
        >
          <AnimatePresence mode="popLayout" initial={false}>
            <motion.span
              key={m.face}
              initial={{ scale: 0, rotate: -50, opacity: 0 }}
              animate={{ scale: 1, rotate: 0, opacity: 1 }}
              exit={{ scale: 0, rotate: 50, opacity: 0 }}
              transition={{ type: "spring", stiffness: 420, damping: 15 }}
              className={`select-none ${face} leading-none drop-shadow-sm`}
            >
              {m.face}
            </motion.span>
          </AnimatePresence>
        </motion.div>
      </div>

      <AnimatePresence mode="wait" initial={false}>
        <motion.p
          key={m.line}
          initial={{ opacity: 0, y: 8 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -8 }}
          transition={{ duration: 0.25 }}
          className={`rounded-full bg-white/40 px-3 py-1 font-medium text-fuchsia-800/80 backdrop-blur-md ${
            compact ? "mt-2 text-[10.5px]" : "mt-3 text-[11px]"
          }`}
        >
          {m.line}
        </motion.p>
      </AnimatePresence>
    </div>
  );
}

/* ────────────────────────────────────────────────────────────────────────────
   نوار پیشرفت
   ──────────────────────────────────────────────────────────────────────────── */

function ProgressRail({ step, total }) {
  return (
    <div className="mb-5 w-full">
      <div className="relative h-1.5 w-full overflow-hidden rounded-full bg-white/45">
        <motion.div
          className="h-full rounded-full bg-gradient-to-l from-rose-400 via-fuchsia-500 to-violet-500"
          initial={false}
          animate={{ width: `${(step / total) * 100}%` }}
          transition={{ type: "spring", stiffness: 120, damping: 20 }}
        />
      </div>
      <div className="mt-2.5 flex flex-row-reverse items-center justify-center gap-2">
        {Array.from({ length: total }, (_, i) => i + 1).map((i) => (
          <motion.span
            key={i}
            className={`block rounded-full ${
              i <= step ? "bg-gradient-to-br from-fuchsia-500 to-rose-400" : "bg-white/60"
            }`}
            animate={{
              width: i === step ? 22 : 7,
              height: 7,
              opacity: i <= step ? 1 : 0.7,
            }}
            transition={{ type: "spring", stiffness: 380, damping: 26 }}
          />
        ))}
      </div>
    </div>
  );
}

/* ────────────────────────────────────────────────────────────────────────────
   دکمه‌ی «نه» بازیگوش
   ──────────────────────────────────────────────────────────────────────────── */

function PlayfulNoButton({ tries, onProvoke, onSurrender, converted, offset }) {
  const [tipOpen, setTipOpen] = useState(false);
  const closeTimer = useRef(null);
  const convertedAt = useRef(0);

  // همان تپی که دکمه را «تبدیل» می‌کند نباید بلافاصله فرم را هم ثبت کند،
  // وگرنه کاربر لحظه‌ی تبدیل‌شدن «نه» به «آره» را اصلاً نمی‌بیند.
  useEffect(() => {
    if (converted) convertedAt.current = Date.now();
  }, [converted]);

  const showTip = useCallback(() => {
    setTipOpen(true);
    clearTimeout(closeTimer.current);
    closeTimer.current = setTimeout(() => setTipOpen(false), 2200);
  }, []);

  useEffect(() => () => clearTimeout(closeTimer.current), []);

  /* «منطقه‌ی شکار»: چون دکمه فرار می‌کند، اگر کاربر خطا هم برود باز هم
     یک تلاش ثبت می‌شود. بدون این، روی موبایل (که hover ندارد) ممکن بود
     کاربر هیچ‌وقت به مرحله‌ی تبدیل‌شدن دکمه نرسد. */
  const pokeZone = () => {
    if (converted) return;
    onProvoke();
    showTip();
  };

  // طعنه‌ی مربوط به همان تلاشی که همین الان انجام شد (tries از ۱ شروع می‌شود)
  const taunt = NO_TAUNTS[Math.min(Math.max(tries - 1, 0), NO_TAUNTS.length - 1)];

  return (
    <div
      onPointerDown={pokeZone}
      className={`relative flex items-center justify-center ${converted ? "" : "h-[62px] touch-manipulation"}`}
    >
      <AnimatePresence>
        {/* بعد از تبدیل هم نشان داده می‌شود، وگرنه جمله‌ی «تسلیم» هیچ‌وقت دیده نمی‌شد */}
        {tipOpen && (
          <motion.div
            initial={{ opacity: 0, y: 10, scale: 0.85 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 6, scale: 0.9 }}
            transition={{ type: "spring", stiffness: 400, damping: 22 }}
            /* زیر دکمه قرار می‌گیرد تا دکمه‌ی «آره» را نپوشاند */
            className="absolute -bottom-11 z-20 max-w-[92%] rounded-2xl border border-white/60 bg-white/85 px-3.5 py-2 text-center text-[11.5px] font-semibold text-fuchsia-700 shadow-lg backdrop-blur-xl"
          >
            {taunt}
            <span className="absolute -top-1 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-l border-t border-white/60 bg-white/85" />
          </motion.div>
        )}
      </AnimatePresence>

      <motion.button
        type="button"
        aria-disabled={!converted}
        onHoverStart={() => {
          if (converted) return;
          onProvoke();
          showTip();
        }}
        onFocus={showTip}
        onClick={(e) => {
          e.preventDefault();
          // حالت غیرفعال از طریق onPointerDown روی منطقه‌ی شکار مدیریت می‌شود
          if (!converted) return;
          if (Date.now() - convertedAt.current < 900) return; // لحظه‌ی تبدیل را نبلع
          onSurrender();
        }}
        animate={{
          x: offset.x,
          y: offset.y,
          rotate: converted ? 0 : offset.x / 14,
          scale: converted ? 1.04 : 1,
        }}
        whileTap={{ scale: converted ? 0.95 : 1 }}
        transition={{ type: "spring", stiffness: 260, damping: 14, mass: 0.7 }}
        className={
          converted
            ? "relative w-full rounded-2xl bg-gradient-to-l from-rose-400 to-fuchsia-500 px-6 py-3 text-base font-extrabold text-white shadow-[0_10px_30px_-10px_rgba(219,39,119,0.9)]"
            : // در حالت فرار عرض کمتر است تا جابه‌جایی از لبه‌ی کارت بیرون نزند
              "absolute w-[62%] cursor-not-allowed rounded-2xl border border-white/50 bg-white/25 px-4 py-3 text-base font-bold text-slate-400/90 shadow-inner backdrop-blur-md"
        }
      >
        {converted ? "آره دیگه 💖" : "نچ ❌"}
        {!converted && (
          <span className="mr-2 align-middle text-[10px] font-medium text-slate-400/80">(خرابه)</span>
        )}
      </motion.button>
    </div>
  );
}

/* ────────────────────────────────────────────────────────────────────────────
   انتخابگر ساعت (چرخ اسکرولی)
   ──────────────────────────────────────────────────────────────────────────── */

function Wheel({ values, value, onChange, format, label }) {
  const boxRef = useRef(null);
  const itemRefs = useRef({});

  useEffect(() => {
    const box = boxRef.current;
    const el = itemRefs.current[value];
    if (!box || !el) return;
    box.scrollTo({
      top: el.offsetTop - box.clientHeight / 2 + el.clientHeight / 2,
      behavior: "smooth",
    });
  }, [value]);

  return (
    <div className="flex-1">
      <p className="mb-1.5 text-center text-[10px] font-semibold text-fuchsia-700/70">{label}</p>
      <div className="relative">
        {/* هایلایت خانه‌ی وسط */}
        <div className="pointer-events-none absolute inset-x-1 top-1/2 z-10 h-10 -translate-y-1/2 rounded-xl border border-fuchsia-300/70 bg-white/30" />
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
                  animate={{
                    scale: active ? 1.35 : 0.92,
                    opacity: active ? 1 : 0.38,
                    fontWeight: active ? 800 : 500,
                  }}
                  transition={{ type: "spring", stiffness: 340, damping: 24 }}
                  className={active ? "text-fuchsia-700" : "text-slate-600"}
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

/* ────────────────────────────────────────────────────────────────────────────
   دکمه‌ی اصلی (Glow + Pulse)
   ──────────────────────────────────────────────────────────────────────────── */

function PrimaryButton({ children, onClick, disabled, pulse = false, hint }) {
  return (
    <div className="w-full">
      <div className="relative">
        {pulse && !disabled && (
          <motion.span
            className="absolute inset-0 rounded-2xl bg-gradient-to-l from-rose-400 to-fuchsia-500 blur-lg"
            animate={{ opacity: [0.45, 0.9, 0.45], scale: [0.97, 1.06, 0.97] }}
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
              : "relative w-full rounded-2xl bg-gradient-to-l from-rose-400 via-pink-500 to-fuchsia-600 px-6 py-3.5 text-base font-extrabold text-white shadow-[0_14px_36px_-12px_rgba(219,39,119,0.95)]"
          }
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
            className="mt-2 text-center text-[11px] font-medium text-fuchsia-700/60"
          >
            {hint}
          </motion.p>
        )}
      </AnimatePresence>
    </div>
  );
}

/* ────────────────────────────────────────────────────────────────────────────
   کامپوننت اصلی
   ──────────────────────────────────────────────────────────────────────────── */

export default function DateProposalApp({ fontUrl = VAZIRMATN_WOFF2 }) {
  useVazirmatn(fontUrl);
  const { play, buzz } = useFx();

  const [step, setStep] = useState(1);
  const [dir, setDir] = useState(1);
  const [mood, setMood] = useState("curious");

  // مرحله ۱ — دکمه‌ی «نه»
  const [noTries, setNoTries] = useState(0);
  const [noOffset, setNoOffset] = useState({ x: 0, y: 0 });
  const noConverted = noTries >= NO_TAUNTS.length;

  // مرحله ۲ — تاریخ و ساعت
  const days = useMemo(buildDays, []);
  const [dayId, setDayId] = useState(null);
  const [hour, setHour] = useState(20);
  const [minute, setMinute] = useState(4);
  const [timeTouched, setTimeTouched] = useState(false);

  // مرحله ۳ — غذا و اکسترا
  const [foods, setFoods] = useState([]);
  const [extra, setExtra] = useState(false);

  // مرحله ۴
  const [copied, setCopied] = useState(false);

  const selectedDay = dayId === null ? null : days.find((d) => d.id === dayId);
  const timeLabel = `${fa(pad2(hour))}:${fa(pad2(minute))}`;
  const dateLabel = selectedDay
    ? `${selectedDay.weekday} ${selectedDay.day} ${selectedDay.month}`
    : "—";
  const foodLabels = FOODS.filter((f) => foods.includes(f.id));

  const go = useCallback(
    (next, nextMood) => {
      setDir(next > step ? 1 : -1);
      setStep(next);
      if (nextMood) setMood(nextMood);
      play("swoosh");
      buzz([10, 40, 14]);
    },
    [step, play, buzz]
  );

  /* ── مرحله ۱: تعامل با دکمه‌ی «نه» ──
     چون دکمه زیر نشانگر جابه‌جا می‌شود، hover پشت‌سرهم شلیک می‌کند؛
     با throttle مطمئن می‌شویم هر «تلاش» عمداً و با فاصله ثبت شود. */
  const lastProvokeRef = useRef(0);

  const provokeNo = useCallback(() => {
    const now = Date.now();
    if (now - lastProvokeRef.current < 500) return;
    lastProvokeRef.current = now;

    const next = Math.min(noTries + 1, NO_TAUNTS.length);
    if (next === noTries) return;
    setNoTries(next);

    if (next === 1) {
      setMood("shy");
      play("tick");
      buzz(8);
    } else if (next >= NO_TAUNTS.length) {
      setMood("mischief");
      setNoOffset({ x: 0, y: 0 });
      play("yay");
      buzz([15, 60, 15, 60, 25]);
    } else {
      setMood("mischief");
      // در محدوده‌ی کارت نگه داشته می‌شود تا هیچ‌وقت غیرقابل‌لمس نشود
      setNoOffset({ x: rand(-56, 56), y: rand(-22, 22) });
      play("swoosh");
      buzz([12, 30, 12]);
    }
  }, [noTries, play, buzz]);

  const sayYes = () => {
    play("yay");
    buzz([18, 50, 18]);
    go(2, "think");
  };

  /* ── مرحله ۳: انتخاب غذا ── */
  const toggleFood = (id) => {
    play("pop");
    buzz(10);
    const next = foods.includes(id) ? foods.filter((f) => f !== id) : [...foods, id];
    setFoods(next);
    setMood(next.length >= 2 ? "star" : next.length === 1 ? "yum" : "think");
  };

  /* ── متن اشتراک‌گذاری ── */
  const shareText = useMemo(() => {
    const menu = foodLabels.length ? foodLabels.map((f) => `${f.emoji} ${f.label}`).join("، ") : "سورپرایز 🤫";
    return [
      "قرارمون سِت شد رسماً 💌",
      "",
      `📅 کِی: ${dateLabel}`,
      `⏰ ساعت: ${timeLabel}`,
      `🍽️ چی بزنیم: ${menu}`,
      extra ? "🎬 اکسترا: سینما / دور دور ✅" : "🎬 اکسترا: فعلاً بیخیال",
      "",
      `🚗 ساعت ${timeLabel} دم درم، لفتش نده! 💖`,
    ].join("\n");
  }, [dateLabel, timeLabel, foodLabels, extra]);

  const shareToTelegram = () => {
    play("yay");
    buzz([20, 40, 20]);
    const url = `https://t.me/share/url?url=${encodeURIComponent("https://t.me/")}&text=${encodeURIComponent(shareText)}`;
    try {
      if (navigator.share) {
        navigator.share({ title: "قرارمون 💖", text: shareText }).catch(() => window.open(url, "_blank"));
      } else {
        window.open(url, "_blank", "noopener,noreferrer");
      }
    } catch {
      window.open(url, "_blank", "noopener,noreferrer");
    }
  };

  const copyText = async () => {
    try {
      await navigator.clipboard.writeText(shareText);
      setCopied(true);
      play("pop");
      buzz(12);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  };

  const restart = () => {
    setStep(1);
    setDir(-1);
    setMood("curious");
    setNoTries(0);
    setNoOffset({ x: 0, y: 0 });
    setDayId(null);
    setHour(20);
    setMinute(4);
    setTimeTouched(false);
    setFoods([]);
    setExtra(false);
  };

  /* ── انیمیشن انتقال مراحل ── */
  const variants = {
    enter: (d) => ({ opacity: 0, x: d > 0 ? -60 : 60, scale: 0.94, filter: "blur(6px)" }),
    center: { opacity: 1, x: 0, scale: 1, filter: "blur(0px)" },
    exit: (d) => ({ opacity: 0, x: d > 0 ? 60 : -60, scale: 0.94, filter: "blur(6px)" }),
  };
  const transition = { type: "spring", stiffness: 260, damping: 28, mass: 0.8 };

  const stepMeta = {
    1: { title: "پایه‌ای بریم سر قرار؟", sub: "یه سوال ساده‌ست فقط 🙂 ولی خب سیستم گزینه «نه» رو ساپورت نمیکنه 😜" },
    2: { title: "کِی بیکاری؟", sub: "یه روز و ساعت توپ بزن، بقیه‌ش با من" },
    3: { title: "پلنمون چی باشه؟", sub: "منو دست توئه رفیق! چی بزنیم؟" },
    4: { title: "دمت گرم که نگفتی نه!", sub: "(هرچند راه دیگه‌ای هم نداشتی 😈💖)" },
  }[step];

  return (
    <div
      dir="rtl"
      lang="fa"
      className="relative min-h-screen w-full overflow-hidden bg-gradient-to-br from-rose-100 via-fuchsia-100 to-indigo-200 px-4 py-8"
      style={{
        fontFamily: FONT_STACK,
      }}
    >
      <AmbientBackdrop />

      {/* ظرف موبایل — کارت وسط‌چین */}
      <div className="relative z-10 mx-auto flex w-full max-w-sm flex-col items-center">
        <motion.div
          initial={{ opacity: 0, y: 28, scale: 0.96 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          transition={{ type: "spring", stiffness: 180, damping: 22 }}
          className="relative w-full overflow-hidden rounded-[2rem] border border-white/60 bg-white/25 p-5 pb-6 shadow-[0_24px_60px_-20px_rgba(155,60,150,0.55)] backdrop-blur-2xl"
        >
          {/* درخشش لبه‌ی شیشه */}
          <div className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-white/50 to-transparent" />

          {step === 4 && <ConfettiCanvas fire={step} />}

          <div className="relative z-20">
            <ProgressRail step={step} total={4} />
            {/* در مراحل میانی فشرده‌تر می‌شود تا دکمه‌ی اصلی زیر خط تا نرود */}
            <ReactionAvatar mood={mood} compact={step === 2 || step === 3} />

            <AnimatePresence mode="wait" initial={false}>
              <motion.div
                key={`h-${step}`}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -10 }}
                transition={{ duration: 0.25 }}
                className={step === 2 || step === 3 ? "mb-3.5 text-center" : "mb-5 text-center"}
              >
                <h1
                  className={`font-black leading-snug text-fuchsia-950/90 ${
                    step === 2 || step === 3 ? "text-[19px]" : "text-[22px]"
                  }`}
                >
                  {stepMeta.title}
                </h1>
                <p
                  className={`mx-auto max-w-[19rem] text-fuchsia-900/60 ${
                    step === 2 || step === 3 ? "mt-1 text-[11.5px] leading-5" : "mt-1.5 text-[12.5px] leading-6"
                  }`}
                >
                  {stepMeta.sub}
                </p>
              </motion.div>
            </AnimatePresence>

            <AnimatePresence mode="wait" custom={dir} initial={false}>
              {/* ───────────── مرحله ۱ ───────────── */}
              {step === 1 && (
                <motion.div key="step1" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-3">
                  <motion.div
                    onHoverStart={() => setMood("love")}
                    onHoverEnd={() => setMood(noTries > 0 ? "mischief" : "curious")}
                  >
                    <PrimaryButton onClick={sayYes} pulse>
                      آره دیگه 💖
                    </PrimaryButton>
                  </motion.div>

                  <PlayfulNoButton
                    tries={noTries}
                    converted={noConverted}
                    offset={noOffset}
                    onProvoke={provokeNo}
                    onSurrender={sayYes}
                  />

                  <p className="mt-1 text-center text-[10.5px] text-fuchsia-900/40">
                    پ.ن: دکمه «نه» تو نسخه بتاست... تا ابد 🤭
                  </p>
                </motion.div>
              )}

              {/* ───────────── مرحله ۲ ───────────── */}
              {step === 2 && (
                <motion.div key="step2" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-3">
                  {/* کارت‌های روز */}
                  <div>
                    <p className="mb-2 text-[11px] font-bold text-fuchsia-800/70">📅 کدوم روز؟</p>
                    {/* pt برای اینکه نشان «امروز/فردا» بالای کارت بریده نشود */}
                    <div className="-mx-1 flex snap-x snap-mandatory gap-2.5 overflow-x-auto px-1 pb-2 pt-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                      {days.map((d) => {
                        const active = d.id === dayId;
                        return (
                          <motion.button
                            key={d.id}
                            type="button"
                            onClick={() => {
                              setDayId(d.id);
                              setMood("excited");
                              play("pop");
                              buzz(10);
                            }}
                            whileTap={{ scale: 0.93 }}
                            animate={{ scale: active ? 1.06 : 1, y: active ? -3 : 0 }}
                            transition={{ type: "spring", stiffness: 400, damping: 24 }}
                            className={`relative flex min-w-[66px] shrink-0 snap-center flex-col items-center gap-0.5 rounded-2xl border px-2.5 py-2.5 backdrop-blur-md ${
                              active
                                ? "border-fuchsia-400/80 bg-gradient-to-b from-fuchsia-500/90 to-rose-400/90 text-white shadow-[0_12px_26px_-12px_rgba(219,39,119,0.95)]"
                                : "border-white/60 bg-white/35 text-fuchsia-900/70"
                            }`}
                          >
                            {d.badge && (
                              <span className={`absolute -top-1.5 rounded-full px-1.5 py-px text-[8.5px] font-bold ${active ? "bg-white text-fuchsia-600" : "bg-fuchsia-500 text-white"}`}>
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
                  </div>

                  {/* چرخ ساعت */}
                  <div>
                    <p className="mb-2 text-[11px] font-bold text-fuchsia-800/70">⏰ ساعت چند؟</p>
                    <div className="flex items-center gap-2 rounded-3xl border border-white/50 bg-white/20 p-2.5 backdrop-blur-md">
                      <Wheel
                        label="ساعت"
                        values={HOURS}
                        value={hour}
                        format={(v) => fa(pad2(v))}
                        onChange={(v) => {
                          setHour(v);
                          setTimeTouched(true);
                          setMood("excited");
                          play("tick");
                          buzz(6);
                        }}
                      />
                      <motion.span
                        className="pb-1 text-2xl font-black text-fuchsia-600"
                        animate={{ opacity: [1, 0.25, 1] }}
                        transition={{ duration: 1.4, repeat: Infinity }}
                      >
                        :
                      </motion.span>
                      <Wheel
                        label="دقیقه"
                        values={MINUTES}
                        value={minute}
                        format={(v) => fa(pad2(v))}
                        onChange={(v) => {
                          setMinute(v);
                          setTimeTouched(true);
                          setMood("excited");
                          play("tick");
                          buzz(6);
                        }}
                      />
                    </div>
                    <p className="mt-2 text-center text-[11px] font-bold text-fuchsia-700/80">
                      سِت شد: <span className="text-fuchsia-900">{dateLabel !== "—" ? dateLabel : "روز؟"}</span>
                      {" — "}
                      <span className="tabular-nums text-fuchsia-900">{timeLabel}</span>
                    </p>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => go(1, "curious")}
                      className="shrink-0 rounded-2xl border border-white/60 bg-white/35 px-4 py-3.5 text-sm font-bold text-fuchsia-800/70 backdrop-blur-md"
                    >
                      ↩
                    </button>
                    <PrimaryButton
                      onClick={() => go(3, "yum")}
                      disabled={dayId === null || !timeTouched}
                      hint={dayId === null ? "اول روزو بزن 😊" : "ساعتم بزن دیگه (رو عدد بزن) ⏰"}
                    >
                      بریم بعدی ✨
                    </PrimaryButton>
                  </div>
                </motion.div>
              )}

              {/* ───────────── مرحله ۳ ───────────── */}
              {step === 3 && (
                <motion.div key="step3" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-4">
                  <div className="grid grid-cols-2 gap-2.5">
                    {FOODS.map((f, i) => {
                      const active = foods.includes(f.id);
                      return (
                        <motion.button
                          key={f.id}
                          type="button"
                          onClick={() => toggleFood(f.id)}
                          initial={{ opacity: 0, y: 14 }}
                          animate={{ opacity: 1, y: 0 }}
                          transition={{ delay: i * 0.06, type: "spring", stiffness: 300, damping: 22 }}
                          whileTap={{ scale: 0.94 }}
                          whileHover={{ y: -3 }}
                          className={`relative overflow-hidden rounded-2xl border p-3 text-right backdrop-blur-md transition-shadow ${
                            active
                              ? "border-fuchsia-400 bg-gradient-to-br " + f.tone + " shadow-[0_14px_30px_-14px_rgba(219,39,119,0.9)] ring-2 ring-fuchsia-400/70"
                              : "border-white/60 bg-white/30"
                          }`}
                        >
                          <AnimatePresence>
                            {active && (
                              <motion.span
                                initial={{ scale: 0, rotate: -90 }}
                                animate={{ scale: 1, rotate: 0 }}
                                exit={{ scale: 0 }}
                                className="absolute left-2 top-2 grid h-5 w-5 place-items-center rounded-full bg-white text-[10px] font-black text-fuchsia-600 shadow"
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
                            {f.emoji}
                          </motion.span>
                          <span className="mt-1.5 block text-[13px] font-extrabold text-fuchsia-950/85">{f.label}</span>
                          <span className="block text-[10px] text-fuchsia-900/50">{f.hint}</span>
                        </motion.button>
                      );
                    })}
                  </div>

                  {/* سوییچ اکسترا */}
                  <motion.button
                    type="button"
                    onClick={() => {
                      const next = !extra;
                      setExtra(next);
                      setMood(next ? "star" : foods.length ? "yum" : "think");
                      play("pop");
                      buzz([10, 30, 10]);
                    }}
                    whileTap={{ scale: 0.98 }}
                    className={`flex w-full items-center justify-between rounded-2xl border p-3 backdrop-blur-md ${
                      extra ? "border-fuchsia-400/80 bg-fuchsia-500/15" : "border-white/60 bg-white/30"
                    }`}
                  >
                    <span className="text-right">
                      <span className="block text-[12.5px] font-extrabold text-fuchsia-950/85">
                        سینما / دور دور هم باشه؟ 🎬🚶
                      </span>
                      <span className="block text-[10px] text-fuchsia-900/50">
                        {extra ? "ایول! شب کش میاد 🌙" : "فعلاً فقط شیکم 😅"}
                      </span>
                    </span>
                    <span
                      className={`relative flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors ${
                        extra ? "bg-gradient-to-l from-rose-400 to-fuchsia-600" : "bg-white/70"
                      }`}
                    >
                      <motion.span
                        layout
                        transition={{ type: "spring", stiffness: 600, damping: 32 }}
                        className={`h-5 w-5 rounded-full bg-white shadow ${extra ? "mr-auto" : "ml-auto"}`}
                      />
                    </span>
                  </motion.button>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => go(2, "think")}
                      className="shrink-0 rounded-2xl border border-white/60 bg-white/35 px-4 py-3.5 text-sm font-bold text-fuchsia-800/70 backdrop-blur-md"
                    >
                      ↩
                    </button>
                    <PrimaryButton
                      onClick={() => go(4, "party")}
                      disabled={foods.length === 0}
                      hint="حداقل یدونه بزن، گشنه که نمیشه رفت 😅"
                      pulse
                    >
                      قطعیش کن و بفرست 💌
                    </PrimaryButton>
                  </div>
                </motion.div>
              )}

              {/* ───────────── مرحله ۴ ───────────── */}
              {step === 4 && (
                <motion.div key="step4" custom={dir} variants={variants} initial="enter" animate="center" exit="exit" transition={transition} className="flex flex-col gap-4">
                  {/* بلیط / رسید */}
                  <motion.div
                    initial={{ opacity: 0, y: 24, rotateX: -12 }}
                    animate={{ opacity: 1, y: 0, rotateX: 0 }}
                    transition={{ delay: 0.15, type: "spring", stiffness: 200, damping: 20 }}
                    className="relative rounded-2xl border border-white/70 bg-white/65 p-4 shadow-[0_16px_40px_-18px_rgba(155,60,150,0.7)] backdrop-blur-xl"
                    style={{
                      WebkitMaskImage:
                        "radial-gradient(circle at 0% 62%, transparent 9px, #000 10px), radial-gradient(circle at 100% 62%, transparent 9px, #000 10px)",
                      maskImage:
                        "radial-gradient(circle at 0% 62%, transparent 9px, #000 10px), radial-gradient(circle at 100% 62%, transparent 9px, #000 10px)",
                      WebkitMaskComposite: "source-in",
                      maskComposite: "intersect",
                    }}
                  >
                    <div className="flex items-center justify-between border-b border-dashed border-fuchsia-300/70 pb-2.5">
                      <span className="text-[11px] font-black tracking-wide text-fuchsia-700">🎟️ بلیط قرار</span>
                      <span className="rounded-full bg-fuchsia-100 px-2 py-0.5 text-[9.5px] font-bold text-fuchsia-700">
                        کد: {fa(`${pad2(hour)}${pad2(minute)}`)}-{fa(String(foods.length))}
                        {extra ? "X" : ""}
                      </span>
                    </div>

                    <dl className="space-y-2.5 pt-3 text-[12.5px]">
                      <div className="flex items-start justify-between gap-3">
                        <dt className="shrink-0 text-fuchsia-900/55">📅 کِی</dt>
                        <dd className="text-left font-extrabold text-fuchsia-950">
                          {dateLabel}
                          <span className="mr-1.5 tabular-nums">— {timeLabel}</span>
                        </dd>
                      </div>
                      <div className="flex items-start justify-between gap-3">
                        <dt className="shrink-0 text-fuchsia-900/55">🍽️ چی بزنیم</dt>
                        <dd className="text-left font-extrabold text-fuchsia-950">
                          {foodLabels.length ? foodLabels.map((f) => `${f.emoji} ${f.label}`).join(" + ") : "سورپرایز 🤫"}
                        </dd>
                      </div>
                      <div className="flex items-start justify-between gap-3">
                        <dt className="shrink-0 text-fuchsia-900/55">🎬 اکسترا</dt>
                        <dd className="text-left font-extrabold text-fuchsia-950">
                          {extra ? "سینما / دور دور ✅" : "بیخیال، فقط همین"}
                        </dd>
                      </div>
                      <div className="border-t border-dashed border-fuchsia-300/70 pt-2.5">
                        <div className="flex items-start justify-between gap-3">
                          <dt className="shrink-0 text-fuchsia-900/55">🚗 وضعیت</dt>
                          <dd className="text-left font-extrabold text-rose-600">
                            ساعت <span className="tabular-nums">{timeLabel}</span> دم درم، لفتش نده!
                          </dd>
                        </div>
                      </div>
                    </dl>

                    <motion.div
                      className="mt-3 h-1 rounded-full bg-gradient-to-l from-rose-400 via-fuchsia-500 to-violet-500"
                      initial={{ scaleX: 0 }}
                      animate={{ scaleX: 1 }}
                      transition={{ delay: 0.5, duration: 0.7 }}
                      style={{ transformOrigin: "right" }}
                    />
                  </motion.div>

                  <PrimaryButton onClick={shareToTelegram} pulse>
                    بفرست تو تلگرام 📲
                  </PrimaryButton>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={copyText}
                      className="flex-1 rounded-2xl border border-white/60 bg-white/40 px-4 py-2.5 text-[12px] font-bold text-fuchsia-800 backdrop-blur-md"
                    >
                      {copied ? "کپی شد ✅" : "کپی کن / اسکرین‌شات 📋"}
                    </button>
                    <button
                      type="button"
                      onClick={restart}
                      className="rounded-2xl border border-white/60 bg-white/40 px-4 py-2.5 text-[12px] font-bold text-fuchsia-800/70 backdrop-blur-md"
                    >
                      از اول 🔁
                    </button>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </div>
        </motion.div>

        <motion.p
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.8 }}
          className="mt-4 text-center text-[10.5px] text-fuchsia-900/40"
        >
          ساخته شده با کلی 💖 و یه ذره 😈
        </motion.p>
      </div>
    </div>
  );
}
