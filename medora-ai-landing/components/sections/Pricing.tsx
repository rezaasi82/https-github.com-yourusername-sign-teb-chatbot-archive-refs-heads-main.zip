"use client";

import { Check, Sparkles } from "lucide-react";
import Reveal from "@/components/ui/Reveal";
import SectionHeading from "@/components/ui/SectionHeading";
import { PRICING_PLANS } from "@/lib/data";
import { cn } from "@/lib/utils";

function PricingCard({
  plan,
  index,
}: {
  plan: (typeof PRICING_PLANS)[number];
  index: number;
}) {
  return (
    <Reveal delay={0.1 * index} y={50} className="h-full">
      <div
        className={cn(
          "group relative flex h-full flex-col overflow-hidden rounded-3xl p-[1.5px] transition-transform duration-300 hover:-translate-y-2",
          plan.popular
            ? "bg-gradient-to-b from-primary via-secondary to-accent shadow-glow-violet lg:scale-[1.04]"
            : "bg-white/10 hover:bg-gradient-to-b hover:from-primary/50 hover:to-accent/40"
        )}
      >
        <div className="relative flex h-full flex-col rounded-[calc(1.5rem-1.5px)] bg-[#0a0f28]/95 p-7 backdrop-blur-xl md:p-8">
          {/* shine sweep across the whole card */}
          <span className="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]">
            <span className="absolute top-0 h-full w-1/4 bg-gradient-to-r from-transparent via-white/[0.07] to-transparent opacity-0 group-hover:animate-shine group-hover:opacity-100" />
          </span>

          {plan.popular ? (
            <span className="absolute end-6 top-6 inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-primary to-secondary px-3 py-1.5 text-[11px] font-semibold shadow-glow">
              <Sparkles className="h-3 w-3" />
              محبوب‌ترین
            </span>
          ) : null}

          <h3 className="text-lg font-semibold text-white/90">{plan.name}</h3>
          <p className="mt-1.5 text-sm text-white/50">{plan.description}</p>

          <div className="mt-6 flex items-end gap-2">
            {plan.price !== null ? (
              <>
                <span className="text-4xl font-bold sm:text-5xl">
                  {plan.price}
                </span>
                <span className="pb-1.5 text-xs text-white/45">
                  {plan.period}
                </span>
              </>
            ) : (
              <span className="text-4xl font-bold">بیایید صحبت کنیم</span>
            )}
          </div>

          <ul className="mt-7 flex flex-1 flex-col gap-3">
            {plan.features.map((feature) => (
              <li
                key={feature}
                className="flex items-start gap-2.5 text-sm text-white/65"
              >
                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-highlight/15">
                  <Check className="h-3 w-3 text-highlight" />
                </span>
                {feature}
              </li>
            ))}
          </ul>

          <a
            href="#cta"
            className={cn(
              "mt-8 block rounded-full py-3.5 text-center text-sm font-semibold transition-all duration-300",
              plan.popular
                ? "bg-gradient-to-r from-primary to-secondary shadow-glow hover:shadow-glow-violet"
                : "glass hover:bg-white/10"
            )}
          >
            {plan.cta}
          </a>
        </div>
      </div>
    </Reveal>
  );
}

export default function Pricing() {
  return (
    <section id="pricing" className="relative py-28 md:py-36">
      <div
        aria-hidden
        className="absolute left-0 top-1/4 h-[50vh] w-[35vw] rounded-full bg-primary/10 blur-[140px]"
      />
      <div className="section-shell relative z-10">
        <SectionHeading
          eyebrow="تعرفه‌ها"
          title="قیمت‌گذاری ساده،"
          highlight="نتایج جدی"
          description="همهٔ پلن‌ها هستهٔ کامل بالینی را دارند — مستندسازی، استدلال و چت بیمار. هیچ افزونهٔ پنهانی در کار نیست. ۳۰ روز آزمایش رایگان، لغو در هر زمان."
        />
        <div className="grid items-stretch gap-6 lg:grid-cols-3">
          {PRICING_PLANS.map((plan, i) => (
            <PricingCard key={plan.name} plan={plan} index={i} />
          ))}
        </div>
      </div>
    </section>
  );
}
