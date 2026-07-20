"use client";

import { motion, type Variants } from "framer-motion";
import { ArrowLeft, PlayCircle, ShieldCheck } from "lucide-react";
import dynamic from "next/dynamic";
import AuroraBackground from "@/components/ui/AuroraBackground";
import CountUp from "@/components/ui/CountUp";
import MagneticButton from "@/components/ui/MagneticButton";
import Particles from "@/components/ui/Particles";
import { useMousePosition } from "@/hooks/useMousePosition";
import { HERO_STATS, TRUST_BADGES } from "@/lib/data";

const AIOrb = dynamic(() => import("@/components/three/AIOrb"), {
  ssr: false,
  loading: () => (
    <div className="h-full w-full animate-pulse-glow rounded-full bg-primary/10 blur-3xl" />
  ),
});

const container: Variants = {
  hidden: {},
  visible: { transition: { staggerChildren: 0.12, delayChildren: 0.15 } },
};

const item: Variants = {
  hidden: { opacity: 0, y: 32 },
  visible: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.8, ease: [0.21, 0.65, 0.28, 0.99] },
  },
};

export default function Hero() {
  const mouse = useMousePosition();

  return (
    <section className="noise relative flex min-h-screen items-center overflow-hidden pt-28 md:pt-24">
      <AuroraBackground />
      <div className="absolute inset-0">
        <Particles quantity={45} className="opacity-70" />
      </div>

      {/* cursor-following glow */}
      <div
        aria-hidden
        className="pointer-events-none absolute h-[38rem] w-[38rem] rounded-full bg-primary/12 blur-[110px] transition-transform duration-500 ease-out will-change-transform"
        style={{
          left: "50%",
          top: "40%",
          transform: `translate(calc(-50% + ${mouse.x * 120}px), calc(-50% + ${mouse.y * 120}px))`,
        }}
      />

      <div className="section-shell relative z-10 grid items-center gap-14 py-16 lg:grid-cols-[1.05fr_0.95fr] lg:gap-6">
        <motion.div
          variants={container}
          initial="hidden"
          animate="visible"
          className="flex flex-col items-start gap-7"
        >
          <motion.span
            variants={item}
            className="glass inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-medium tracking-wide text-white/80"
          >
            <span className="relative flex h-2 w-2">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-highlight opacity-70" />
              <span className="relative inline-flex h-2 w-2 rounded-full bg-highlight" />
            </span>
            معرفی موتور استدلال مدورا نسخهٔ ۳ — هم‌اکنون فعال
          </motion.span>

          <motion.h1
            variants={item}
            className="text-5xl font-bold leading-[1.15] sm:text-6xl xl:text-7xl"
          >
            لایهٔ هوشمندِ
            <br />
            <span className="text-gradient">پزشکیِ مدرن</span>
          </motion.h1>

          <motion.p
            variants={item}
            className="max-w-xl text-lg leading-relaxed text-white/60"
          >
            مدورا گوش می‌دهد، استدلال می‌کند و مستند می‌سازد — تا پزشک به طبابت
            برگردد. سریع‌تر تشخیص دهید، در چند ثانیه پرونده بنویسید و به هر
            بیمار همراهی ۲۴ساعته بدهید که هرگز نمی‌خوابد.
          </motion.p>

          <motion.div variants={item} className="flex flex-wrap items-center gap-4">
            <MagneticButton href="#cta">
              شروع رایگان
              <ArrowLeft className="h-4 w-4 transition-transform duration-300 group-hover:-translate-x-1" />
            </MagneticButton>
            <MagneticButton href="#demo" variant="ghost">
              <PlayCircle className="h-4 w-4 text-highlight" />
              تماشای دمو
            </MagneticButton>
          </motion.div>

          <motion.div
            variants={item}
            className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-white/45"
          >
            {TRUST_BADGES.map((badge) => (
              <span key={badge} className="inline-flex items-center gap-1.5">
                <ShieldCheck className="h-3.5 w-3.5 text-highlight/80" />
                {badge}
              </span>
            ))}
          </motion.div>

          <motion.dl
            variants={item}
            className="mt-2 grid w-full grid-cols-2 gap-x-8 gap-y-6 border-t border-white/10 pt-7 sm:grid-cols-4"
          >
            {HERO_STATS.map((stat) => (
              <div key={stat.label}>
                <dt className="sr-only">{stat.label}</dt>
                <dd className="text-2xl font-bold text-white sm:text-3xl">
                  <CountUp value={stat.value} suffix={stat.suffix} />
                </dd>
                <p className="mt-1 text-xs leading-snug text-white/50">
                  {stat.label}
                </p>
              </div>
            ))}
          </motion.dl>
        </motion.div>

        {/* 3D orb with parallax layers */}
        <motion.div
          initial={{ opacity: 0, scale: 0.85 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 1.2, delay: 0.35, ease: [0.21, 0.65, 0.28, 0.99] }}
          className="relative mx-auto aspect-square w-full max-w-[560px]"
          style={{
            transform: `translate(${mouse.x * -18}px, ${mouse.y * -14}px)`,
          }}
        >
          <div className="absolute inset-8 rounded-full bg-primary/15 blur-[90px] animate-pulse-glow" />
          <AIOrb />
          {/* floating glass chips around the orb */}
          <div className="glass absolute start-0 top-[18%] hidden animate-float-y rounded-2xl px-4 py-3 text-xs sm:block">
            <p className="font-semibold text-highlight">یادداشت امضا شد ✓</p>
            <p className="mt-0.5 text-white/55">SOAP · ۴۷ ثانیه</p>
          </div>
          <div
            className="glass absolute bottom-[14%] end-0 hidden animate-float-y rounded-2xl px-4 py-3 text-xs sm:block"
            style={{ animationDelay: "-2.5s" }}
          >
            <p className="font-semibold text-secondary">تشخیص افتراقی آماده است</p>
            <p className="mt-0.5 text-white/55">۳ گزینه · اطمینان ۹۸٪</p>
          </div>
        </motion.div>
      </div>

      {/* bottom fade into next section */}
      <div className="pointer-events-none absolute inset-x-0 bottom-0 h-40 bg-gradient-to-b from-transparent to-background" />
    </section>
  );
}
