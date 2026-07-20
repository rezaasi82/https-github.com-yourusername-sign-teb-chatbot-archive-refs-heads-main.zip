import { cn } from "@/lib/utils";

/**
 * Animated aurora / gradient-mesh backdrop. Pure CSS (GPU-composited
 * transforms on blurred radial blobs) so it costs almost nothing at runtime.
 */
export default function AuroraBackground({ className }: { className?: string }) {
  return (
    <div
      aria-hidden
      className={cn(
        "pointer-events-none absolute inset-0 overflow-hidden",
        className
      )}
    >
      <div className="absolute -top-1/4 left-1/2 h-[70vh] w-[70vw] -translate-x-1/2 rounded-full bg-primary/25 blur-[130px] animate-aurora" />
      <div className="absolute top-1/3 -left-1/4 h-[55vh] w-[45vw] rounded-full bg-secondary/20 blur-[120px] animate-aurora-slow" />
      <div className="absolute -bottom-1/4 right-[-10%] h-[60vh] w-[45vw] rounded-full bg-accent/15 blur-[140px] animate-aurora" />
      {/* faint grid for depth */}
      <div className="absolute inset-0 bg-grid-faint [background-size:72px_72px] [mask-image:radial-gradient(ellipse_at_center,black_35%,transparent_75%)]" />
    </div>
  );
}
