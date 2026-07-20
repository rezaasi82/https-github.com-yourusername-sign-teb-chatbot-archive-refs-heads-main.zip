"use client";

import { motion, useMotionValue, useSpring, useTransform } from "framer-motion";
import { MouseEvent, useRef } from "react";
import CountUp from "@/components/ui/CountUp";
import Reveal from "@/components/ui/Reveal";
import SectionHeading from "@/components/ui/SectionHeading";
import { DEMO_VITALS } from "@/lib/data";

const WAVE_BARS = [42, 68, 35, 82, 55, 90, 48, 73, 61, 88, 40, 66, 52, 79, 45];

/** Floating dashboard mockup with 3D perspective tilt that follows the cursor. */
export default function ProductDemo() {
  const wrapRef = useRef<HTMLDivElement>(null);
  const mx = useMotionValue(0.5);
  const my = useMotionValue(0.5);
  const rotateX = useSpring(useTransform(my, [0, 1], [9, -9]), {
    stiffness: 150,
    damping: 20,
  });
  const rotateY = useSpring(useTransform(mx, [0, 1], [-11, 11]), {
    stiffness: 150,
    damping: 20,
  });

  const onMouseMove = (e: MouseEvent<HTMLDivElement>) => {
    const rect = wrapRef.current?.getBoundingClientRect();
    if (!rect) return;
    mx.set((e.clientX - rect.left) / rect.width);
    my.set((e.clientY - rect.top) / rect.height);
  };

  const onMouseLeave = () => {
    mx.set(0.5);
    my.set(0.5);
  };

  return (
    <section id="demo" className="relative overflow-hidden py-28 md:py-36">
      <div
        aria-hidden
        className="absolute left-1/2 top-0 h-[40vh] w-[70vw] -translate-x-1/2 rounded-full bg-primary/10 blur-[130px]"
      />
      <div className="section-shell relative z-10">
        <SectionHeading
          eyebrow="دموی محصول"
          title="مرکز فرماندهی شما برای"
          highlight="هر ویزیت"
          description="یک قاب شیشه‌ای روی کل کلینیک: شاخص‌های زنده، یادداشت‌های پیش‌نویسِ هوش مصنوعی و صفی که خودش تریاژ می‌کند. با موس کج‌اش کنید — واقعی است."
        />

        <Reveal scale={0.93} y={60}>
          <div
            ref={wrapRef}
            onMouseMove={onMouseMove}
            onMouseLeave={onMouseLeave}
            className="mx-auto max-w-5xl"
            style={{ perspective: 1400 }}
          >
            <motion.div
              style={{ rotateX, rotateY, transformStyle: "preserve-3d" }}
              className="glass-strong relative rounded-3xl p-3 shadow-card md:p-4"
            >
              {/* window chrome */}
              <div className="flex items-center gap-2 px-3 py-2">
                <span className="h-3 w-3 rounded-full bg-red-400/70" />
                <span className="h-3 w-3 rounded-full bg-yellow-400/70" />
                <span className="h-3 w-3 rounded-full bg-green-400/70" />
                <span className="ms-3 rounded-md bg-white/5 px-3 py-1 text-[11px] text-white/40" dir="ltr">
                  app.medora.ai / dashboard
                </span>
              </div>

              <div className="grid gap-3 rounded-2xl bg-background/70 p-4 md:grid-cols-3 md:p-5">
                {/* vitals row */}
                <div className="md:col-span-3">
                  <div className="grid grid-cols-3 gap-3">
                    {DEMO_VITALS.map((vital) => {
                      const Icon = vital.icon;
                      return (
                        <div
                          key={vital.label}
                          className="glass rounded-2xl p-4"
                          style={{ transform: "translateZ(30px)" }}
                        >
                          <Icon className="h-4 w-4 text-highlight" />
                          <p className="mt-2 text-lg font-bold md:text-2xl">
                            {vital.value}
                          </p>
                          <p className="text-[11px] text-white/45 md:text-xs">
                            {vital.label}
                          </p>
                        </div>
                      );
                    })}
                  </div>
                </div>

                {/* live activity chart */}
                <div
                  className="glass rounded-2xl p-4 md:col-span-2"
                  style={{ transform: "translateZ(45px)" }}
                >
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold">ویزیت‌های امروز</p>
                    <span className="rounded-full bg-highlight/10 px-2.5 py-1 text-[11px] font-medium text-highlight">
                      زنده
                    </span>
                  </div>
                  <div className="mt-4 flex h-28 items-end gap-1.5 md:h-36">
                    {WAVE_BARS.map((height, i) => (
                      <motion.span
                        key={i}
                        initial={{ height: "8%" }}
                        whileInView={{ height: `${height}%` }}
                        viewport={{ once: true }}
                        transition={{
                          duration: 0.9,
                          delay: 0.35 + i * 0.05,
                          ease: [0.16, 1, 0.3, 1],
                        }}
                        className="flex-1 rounded-t-md bg-gradient-to-t from-primary/40 via-primary to-highlight"
                      />
                    ))}
                  </div>
                </div>

                {/* AI note feed */}
                <div
                  className="glass rounded-2xl p-4"
                  style={{ transform: "translateZ(45px)" }}
                >
                  <p className="text-sm font-semibold">صف یادداشت‌های هوشمند</p>
                  <ul className="mt-3 space-y-2.5">
                    {[
                      ["اتاق ۴ · ا. نادری", "امضا شد", "text-emerald-400"],
                      ["اتاق ۷ · ج. ملک", "در حال نگارش…", "text-highlight"],
                      ["اتاق ۲ · ل. کوستا", "بازبینی", "text-yellow-400"],
                      ["تله‌ویزیت · ر. آدامز", "در صف", "text-white/40"],
                    ].map(([who, status, color]) => (
                      <li
                        key={who}
                        className="flex items-center justify-between rounded-xl bg-white/[0.04] px-3 py-2 text-xs"
                      >
                        <span className="text-white/70">{who}</span>
                        <span className={`font-medium ${color}`}>{status}</span>
                      </li>
                    ))}
                  </ul>
                  <p className="mt-4 text-2xl font-bold">
                    <CountUp value={97.1} suffix="٪" decimals={1} />
                  </p>
                  <p className="text-[11px] text-white/45">
                    یادداشت‌های پذیرفته‌شده بدون ویرایش
                  </p>
                </div>
              </div>

              {/* reflection glow under the mockup */}
              <div
                aria-hidden
                className="absolute -bottom-10 left-1/2 h-16 w-4/5 -translate-x-1/2 rounded-full bg-primary/25 blur-3xl"
              />
            </motion.div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}
