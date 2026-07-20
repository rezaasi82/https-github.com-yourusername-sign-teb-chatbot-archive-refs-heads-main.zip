"use client";

import { MouseEvent, useRef, useState } from "react";
import Particles from "@/components/ui/Particles";
import Reveal from "@/components/ui/Reveal";
import SectionHeading from "@/components/ui/SectionHeading";
import { FEATURES } from "@/lib/data";
import { cn } from "@/lib/utils";

const GLOW_COLORS = {
  primary: "rgba(59, 130, 246, 0.22)",
  violet: "rgba(139, 92, 246, 0.22)",
  cyan: "rgba(6, 182, 212, 0.22)",
} as const;

function FeatureCard({
  feature,
  index,
}: {
  feature: (typeof FEATURES)[number];
  index: number;
}) {
  const cardRef = useRef<HTMLDivElement>(null);
  const [spot, setSpot] = useState({ x: 50, y: 50, active: false });
  const Icon = feature.icon;

  const onMouseMove = (e: MouseEvent<HTMLDivElement>) => {
    const rect = cardRef.current?.getBoundingClientRect();
    if (!rect) return;
    setSpot({
      x: ((e.clientX - rect.left) / rect.width) * 100,
      y: ((e.clientY - rect.top) / rect.height) * 100,
      active: true,
    });
  };

  return (
    <Reveal
      delay={0.07 * index}
      className={cn(feature.size === "large" && "md:col-span-2")}
    >
      <div
        ref={cardRef}
        onMouseMove={onMouseMove}
        onMouseLeave={() => setSpot((s) => ({ ...s, active: false }))}
        className="glass group relative h-full overflow-hidden rounded-3xl p-7 transition-all duration-300 hover:-translate-y-1.5 hover:border-white/20 md:p-8"
      >
        {/* cursor spotlight + glow border */}
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 transition-opacity duration-300"
          style={{
            opacity: spot.active ? 1 : 0,
            background: `radial-gradient(420px circle at ${spot.x}% ${spot.y}%, ${GLOW_COLORS[feature.glow]}, transparent 65%)`,
          }}
        />
        {/* floating particles on hover */}
        <div className="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-500 group-hover:opacity-100">
          <Particles quantity={14} />
        </div>

        <div className="relative z-10 flex h-full flex-col">
          <span className="glass mb-5 inline-flex h-12 w-12 items-center justify-center rounded-2xl transition-transform duration-300 group-hover:scale-110 group-hover:rotate-[8deg]">
            <Icon className="h-6 w-6 text-highlight" />
          </span>
          <h3 className="text-xl font-semibold tracking-tight md:text-2xl">
            {feature.title}
          </h3>
          <p className="mt-3 text-sm leading-relaxed text-white/55 md:text-[15px]">
            {feature.description}
          </p>
        </div>
      </div>
    </Reveal>
  );
}

export default function Features() {
  return (
    <section id="features" className="relative py-28 md:py-36">
      <div className="section-shell">
        <SectionHeading
          eyebrow="Capabilities"
          title="Everything a clinic needs,"
          highlight="nothing it doesn't"
          description="Six systems, one platform. Each one designed with clinicians, validated in the wild, and shipped in the core product — never as a paid add-on."
        />
        <div className="grid gap-5 md:grid-cols-3 md:gap-6">
          {FEATURES.map((feature, i) => (
            <FeatureCard key={feature.title} feature={feature} index={i} />
          ))}
        </div>
      </div>
    </section>
  );
}
