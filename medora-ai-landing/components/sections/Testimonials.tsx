"use client";

import { Quote } from "lucide-react";
import SectionHeading from "@/components/ui/SectionHeading";
import { TESTIMONIALS } from "@/lib/data";
import { cn } from "@/lib/utils";

function TestimonialCard({
  testimonial,
}: {
  testimonial: (typeof TESTIMONIALS)[number];
}) {
  return (
    <figure className="glass group relative w-[340px] shrink-0 rounded-3xl p-6 transition-all duration-300 hover:-translate-y-1 hover:border-white/20 sm:w-[400px]">
      <Quote className="absolute right-5 top-5 h-6 w-6 text-white/10 transition-colors duration-300 group-hover:text-primary/40" />
      <blockquote className="text-sm leading-relaxed text-white/70">
        «{testimonial.quote}»
      </blockquote>
      <figcaption className="mt-5 flex items-center gap-3">
        <span
          className={cn(
            "flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br text-xs font-bold text-white shadow-glow transition-transform duration-300 group-hover:scale-110",
            testimonial.hue
          )}
        >
          {testimonial.initials}
        </span>
        <div>
          <p className="text-sm font-semibold">{testimonial.name}</p>
          <p className="text-xs text-white/45">{testimonial.role}</p>
        </div>
      </figcaption>
    </figure>
  );
}

function MarqueeRow({
  items,
  reverse = false,
}: {
  items: typeof TESTIMONIALS;
  reverse?: boolean;
}) {
  return (
    <div className="group/row relative flex overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_8%,black_92%,transparent)]">
      <div
        className={cn(
          "flex w-max gap-5 pe-5 [animation-play-state:running] group-hover/row:[animation-play-state:paused]",
          reverse ? "animate-marquee-reverse" : "animate-marquee"
        )}
      >
        {[...items, ...items].map((testimonial, i) => (
          <TestimonialCard key={`${testimonial.name}-${i}`} testimonial={testimonial} />
        ))}
      </div>
    </div>
  );
}

export default function Testimonials() {
  const firstRow = TESTIMONIALS.slice(0, 3);
  const secondRow = TESTIMONIALS.slice(3);

  return (
    <section className="relative overflow-hidden py-28 md:py-36">
      <div
        aria-hidden
        className="absolute right-0 top-1/3 h-[45vh] w-[35vw] rounded-full bg-secondary/10 blur-[130px]"
      />
      <div className="section-shell relative z-10">
        <SectionHeading
          eyebrow="محبوب پزشکان"
          title="مورد اعتماد، آنجا که"
          highlight="بیشترین اهمیت را دارد"
          description="از مطب‌های تک‌نفرهٔ پزشک خانواده تا شبکه‌های ۱۲بیمارستانی — از زبان کسانی بشنوید که روپوش سفید بر تن دارند."
        />
      </div>
      <div className="relative z-10 flex flex-col gap-5">
        <MarqueeRow items={firstRow} />
        <MarqueeRow items={secondRow} reverse />
      </div>
    </section>
  );
}
