"use client";

import { motion, useScroll, useTransform } from "framer-motion";
import { ReactNode, useRef } from "react";

type ParallaxLayerProps = {
  children: ReactNode;
  className?: string;
  /**
   * Parallax depth. Positive values scroll slower than the page (background),
   * negative values scroll faster (foreground). 0.3 ≈ subtle, 1 ≈ dramatic.
   */
  speed?: number;
};

/**
 * Wraps content in a scroll-linked vertical parallax layer.
 * Uses Framer Motion motion values only — no React re-renders per frame.
 */
export default function ParallaxLayer({
  children,
  className,
  speed = 0.3,
}: ParallaxLayerProps) {
  const ref = useRef<HTMLDivElement>(null);
  const { scrollYProgress } = useScroll({
    target: ref,
    offset: ["start end", "end start"],
  });
  const y = useTransform(scrollYProgress, [0, 1], [speed * 140, speed * -140]);

  return (
    <motion.div ref={ref} style={{ y }} className={className}>
      {children}
    </motion.div>
  );
}
