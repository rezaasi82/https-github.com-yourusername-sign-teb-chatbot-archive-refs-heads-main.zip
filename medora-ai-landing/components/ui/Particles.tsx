"use client";

import { useEffect, useRef } from "react";

type Particle = {
  x: number;
  y: number;
  vx: number;
  vy: number;
  size: number;
  alpha: number;
  hue: number;
};

type ParticlesProps = {
  className?: string;
  quantity?: number;
  /** When true, particles drift outward from center like a slow explosion. */
  explode?: boolean;
};

/**
 * Lightweight canvas particle field. No dependencies, DPR-aware,
 * pauses automatically when scrolled off-screen via IntersectionObserver.
 */
export default function Particles({
  className,
  quantity = 60,
  explode = false,
}: ParticlesProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    let raf = 0;
    let running = false;
    let particles: Particle[] = [];
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    const resize = () => {
      const { clientWidth: w, clientHeight: h } = canvas;
      canvas.width = w * dpr;
      canvas.height = h * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    const spawn = (): Particle => {
      const w = canvas.clientWidth;
      const h = canvas.clientHeight;
      if (explode) {
        const angle = Math.random() * Math.PI * 2;
        const speed = 0.15 + Math.random() * 0.5;
        return {
          x: w / 2 + (Math.random() - 0.5) * 80,
          y: h / 2 + (Math.random() - 0.5) * 80,
          vx: Math.cos(angle) * speed,
          vy: Math.sin(angle) * speed,
          size: 0.8 + Math.random() * 1.8,
          alpha: 0.9,
          hue: 200 + Math.random() * 80,
        };
      }
      return {
        x: Math.random() * w,
        y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.25,
        vy: -0.1 - Math.random() * 0.3,
        size: 0.6 + Math.random() * 1.6,
        alpha: 0.15 + Math.random() * 0.5,
        hue: 200 + Math.random() * 80,
      };
    };

    const init = () => {
      particles = Array.from({ length: quantity }, spawn);
    };

    const tick = () => {
      if (!running) return;
      const w = canvas.clientWidth;
      const h = canvas.clientHeight;
      ctx.clearRect(0, 0, w, h);

      for (let i = 0; i < particles.length; i++) {
        const p = particles[i];
        p.x += p.vx;
        p.y += p.vy;
        if (explode) p.alpha -= 0.004;

        const dead =
          p.x < -10 || p.x > w + 10 || p.y < -10 || p.y > h + 10 || p.alpha <= 0;
        if (dead) particles[i] = spawn();

        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fillStyle = `hsla(${p.hue}, 90%, 70%, ${Math.max(p.alpha, 0)})`;
        ctx.fill();
      }
      raf = requestAnimationFrame(tick);
    };

    const start = () => {
      if (running) return;
      running = true;
      raf = requestAnimationFrame(tick);
    };
    const stop = () => {
      running = false;
      cancelAnimationFrame(raf);
    };

    resize();
    init();

    const observer = new IntersectionObserver(
      ([entry]) => (entry.isIntersecting ? start() : stop()),
      { threshold: 0 }
    );
    observer.observe(canvas);
    window.addEventListener("resize", resize);

    return () => {
      stop();
      observer.disconnect();
      window.removeEventListener("resize", resize);
    };
  }, [quantity, explode]);

  return (
    <canvas
      ref={canvasRef}
      aria-hidden
      className={className}
      style={{ width: "100%", height: "100%" }}
    />
  );
}
