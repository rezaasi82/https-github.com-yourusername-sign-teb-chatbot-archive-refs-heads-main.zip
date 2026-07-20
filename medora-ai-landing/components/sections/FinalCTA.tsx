"use client";

import { ArrowRight } from "lucide-react";
import MagneticButton from "@/components/ui/MagneticButton";
import Particles from "@/components/ui/Particles";
import Reveal from "@/components/ui/Reveal";
import { useMousePosition } from "@/hooks/useMousePosition";

export default function FinalCTA() {
  const mouse = useMousePosition();

  return (
    <section id="cta" className="relative overflow-hidden py-32 md:py-44">
      {/* particle explosion backdrop */}
      <div className="absolute inset-0">
        <Particles quantity={110} explode />
      </div>

      {/* dynamic spotlight following the cursor */}
      <div
        aria-hidden
        className="pointer-events-none absolute h-[46rem] w-[46rem] rounded-full transition-transform duration-700 ease-out will-change-transform"
        style={{
          left: "50%",
          top: "50%",
          transform: `translate(calc(-50% + ${mouse.x * 160}px), calc(-50% + ${mouse.y * 160}px))`,
          background:
            "radial-gradient(circle, rgba(59,130,246,0.22) 0%, rgba(139,92,246,0.12) 40%, transparent 70%)",
        }}
      />

      <div className="section-shell relative z-10 flex flex-col items-center text-center">
        <Reveal>
          <span className="glass inline-flex items-center rounded-full px-4 py-1.5 text-xs font-medium uppercase tracking-[0.2em] text-highlight">
            The future won&apos;t wait
          </span>
        </Reveal>
        <Reveal delay={0.1}>
          <h2 className="mt-6 max-w-4xl text-4xl font-bold leading-[1.06] tracking-tight sm:text-6xl md:text-7xl">
            Give every clinician a<br />
            <span className="text-gradient">superhuman colleague</span>
          </h2>
        </Reveal>
        <Reveal delay={0.2}>
          <p className="mt-6 max-w-xl text-lg text-white/60">
            Join 240+ health systems already practicing tomorrow&apos;s
            medicine. Live in days — not quarters.
          </p>
        </Reveal>
        <Reveal delay={0.3}>
          <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
            <MagneticButton href="#" className="px-10 py-5 text-base">
              Start your free trial
              <ArrowRight className="h-5 w-5 transition-transform duration-300 group-hover:translate-x-1" />
            </MagneticButton>
            <MagneticButton href="#" variant="ghost" className="px-10 py-5 text-base">
              Book a live demo
            </MagneticButton>
          </div>
        </Reveal>
        <Reveal delay={0.4}>
          <p className="mt-6 text-xs text-white/40">
            No credit card required · HIPAA-ready from day one · Cancel anytime
          </p>
        </Reveal>
      </div>
    </section>
  );
}
