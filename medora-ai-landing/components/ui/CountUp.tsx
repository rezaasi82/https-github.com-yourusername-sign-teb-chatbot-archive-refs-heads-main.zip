"use client";

import { animate, useInView, useMotionValue } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import { toFaDigits } from "@/lib/utils";

type CountUpProps = {
  value: number;
  suffix?: string;
  duration?: number;
  decimals?: number;
};

/** Number that counts up from 0 when it scrolls into view. */
export default function CountUp({
  value,
  suffix = "",
  duration = 1.8,
  decimals,
}: CountUpProps) {
  const ref = useRef<HTMLSpanElement>(null);
  const inView = useInView(ref, { once: true, margin: "-40px" });
  const motionValue = useMotionValue(0);
  const [display, setDisplay] = useState("۰");
  const places = decimals ?? (Number.isInteger(value) ? 0 : 1);

  useEffect(() => {
    if (!inView) return;
    const controls = animate(motionValue, value, {
      duration,
      ease: [0.16, 1, 0.3, 1],
      onUpdate: (v) => setDisplay(toFaDigits(v.toFixed(places))),
    });
    return controls.stop;
  }, [inView, value, duration, places, motionValue]);

  return (
    <span ref={ref}>
      {display}
      {suffix}
    </span>
  );
}
