import Reveal from "./Reveal";
import { cn } from "@/lib/utils";

type SectionHeadingProps = {
  eyebrow: string;
  title: string;
  highlight?: string;
  description?: string;
  align?: "center" | "left";
};

export default function SectionHeading({
  eyebrow,
  title,
  highlight,
  description,
  align = "center",
}: SectionHeadingProps) {
  return (
    <div
      className={cn(
        "mb-14 flex flex-col gap-4 md:mb-20",
        align === "center" ? "items-center text-center" : "items-start text-start"
      )}
    >
      <Reveal>
        <span className="glass inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-medium text-highlight">
          <span className="h-1.5 w-1.5 rounded-full bg-highlight shadow-glow-cyan" />
          {eyebrow}
        </span>
      </Reveal>
      <Reveal delay={0.08}>
        <h2 className="max-w-3xl text-4xl font-bold leading-[1.08] tracking-tight sm:text-5xl md:text-6xl">
          {title}{" "}
          {highlight ? <span className="text-gradient">{highlight}</span> : null}
        </h2>
      </Reveal>
      {description ? (
        <Reveal delay={0.16}>
          <p className="max-w-2xl text-base leading-relaxed text-white/60 md:text-lg">
            {description}
          </p>
        </Reveal>
      ) : null}
    </div>
  );
}
