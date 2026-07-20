"use client";

import { useMotionValueEvent, useScroll } from "framer-motion";
import dynamic from "next/dynamic";
import { useRef, useState } from "react";
import Reveal from "@/components/ui/Reveal";
import SectionHeading from "@/components/ui/SectionHeading";

const ShowcaseScene = dynamic(
  () => import("@/components/three/ShowcaseScene"),
  {
    ssr: false,
    loading: () => (
      <div className="h-full w-full animate-pulse rounded-3xl bg-white/[0.03]" />
    ),
  }
);

const CALLOUTS = [
  {
    title: "هستهٔ استدلال عصبی",
    body: "مجموعه‌مدل‌های ترنسفورمر، آموزش‌دیده روی بیش از ۴۰ میلیون مطالعهٔ بالینی و ویزیت‌های بی‌نام‌شده.",
  },
  {
    title: "گراف زمینهٔ بلادرنگ",
    body: "هر آزمایش، یادداشت و علامت حیاتی به یک گراف زندهٔ بیمار متصل می‌شود که هوش مصنوعی روی آن استدلال می‌کند.",
  },
  {
    title: "شفاف و توضیح‌پذیر از پایه",
    body: "هر پیشنهاد همراه با منابع، میزان اطمینان و ردِّ شواهد تصویری ارائه می‌شود.",
  },
];

export default function Showcase3D() {
  const sectionRef = useRef<HTMLElement>(null);
  const [progress, setProgress] = useState(0);
  const { scrollYProgress } = useScroll({
    target: sectionRef,
    offset: ["start end", "end start"],
  });

  useMotionValueEvent(scrollYProgress, "change", (v) => setProgress(v));

  return (
    <section
      id="showcase"
      ref={sectionRef}
      className="noise relative overflow-hidden py-28 md:py-36"
    >
      <div
        aria-hidden
        className="absolute left-1/2 top-1/2 h-[60vh] w-[60vw] -translate-x-1/2 -translate-y-1/2 rounded-full bg-secondary/10 blur-[140px]"
      />
      <div className="section-shell relative z-10">
        <SectionHeading
          eyebrow="درون موتور"
          title="پزشکی، مدل‌شده در"
          highlight="سه بُعد"
          description="اسکرول کنید تا هستهٔ استدلال مدورا بچرخد؛ نشانگر را حرکت دهید تا آن را بکاوید — هر پیشنهادی که پزشکان شما می‌بینند، از همین‌جا آغاز می‌شود."
        />

        <div className="grid items-center gap-10 lg:grid-cols-[1fr_380px]">
          <Reveal scale={0.94} className="relative">
            <div className="glass relative aspect-[4/3] overflow-hidden rounded-3xl md:aspect-[16/9]">
              <ShowcaseScene progress={progress} />
              <div className="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-background/80 to-transparent" />
              <span className="glass absolute bottom-4 start-4 rounded-full px-3 py-1.5 text-[11px] text-white/60">
                WebGL زنده · بدون درگ، هدایت با اسکرول
              </span>
            </div>
          </Reveal>

          <div className="flex flex-col gap-4">
            {CALLOUTS.map((callout, i) => (
              <Reveal key={callout.title} delay={0.1 * i}>
                <div className="glass group rounded-2xl p-5 transition-all duration-300 hover:-translate-y-1 hover:border-primary/40 hover:shadow-glow">
                  <h3 className="font-semibold text-white">
                    <span className="me-2 text-gradient font-bold">
                      ۰{["۱", "۲", "۳"][i]}
                    </span>
                    {callout.title}
                  </h3>
                  <p className="mt-2 text-sm leading-relaxed text-white/55">
                    {callout.body}
                  </p>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
