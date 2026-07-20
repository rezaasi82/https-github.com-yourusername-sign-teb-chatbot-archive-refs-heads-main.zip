"use client";

import { motion, useMotionValue, useSpring } from "framer-motion";
import { MouseEvent, ReactNode, useRef } from "react";
import { cn } from "@/lib/utils";

type MagneticButtonProps = {
  children: ReactNode;
  className?: string;
  href?: string;
  variant?: "primary" | "ghost";
  strength?: number;
};

/**
 * Button that magnetically follows the cursor inside its hover zone
 * and springs back to rest when the cursor leaves.
 */
export default function MagneticButton({
  children,
  className,
  href = "#",
  variant = "primary",
  strength = 0.35,
}: MagneticButtonProps) {
  const ref = useRef<HTMLAnchorElement>(null);
  const x = useMotionValue(0);
  const y = useMotionValue(0);
  const springX = useSpring(x, { stiffness: 220, damping: 16, mass: 0.6 });
  const springY = useSpring(y, { stiffness: 220, damping: 16, mass: 0.6 });

  const onMouseMove = (e: MouseEvent<HTMLAnchorElement>) => {
    const rect = ref.current?.getBoundingClientRect();
    if (!rect) return;
    x.set((e.clientX - rect.left - rect.width / 2) * strength);
    y.set((e.clientY - rect.top - rect.height / 2) * strength);
  };

  const onMouseLeave = () => {
    x.set(0);
    y.set(0);
  };

  return (
    <motion.a
      ref={ref}
      href={href}
      onMouseMove={onMouseMove}
      onMouseLeave={onMouseLeave}
      style={{ x: springX, y: springY }}
      whileTap={{ scale: 0.96 }}
      className={cn(
        "group relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-full px-8 py-4 text-sm font-semibold tracking-wide transition-shadow duration-300",
        variant === "primary"
          ? "bg-gradient-to-r from-primary via-secondary to-accent text-white shadow-glow hover:shadow-glow-violet"
          : "glass text-white hover:bg-white/[0.08]",
        className
      )}
    >
      {/* moving shine sweep */}
      <span className="pointer-events-none absolute inset-0 overflow-hidden rounded-full">
        <span className="absolute top-0 h-full w-1/3 bg-gradient-to-r from-transparent via-white/30 to-transparent opacity-0 transition-opacity duration-300 group-hover:animate-shine group-hover:opacity-100" />
      </span>
      <span className="relative z-10 flex items-center gap-2">{children}</span>
    </motion.a>
  );
}
