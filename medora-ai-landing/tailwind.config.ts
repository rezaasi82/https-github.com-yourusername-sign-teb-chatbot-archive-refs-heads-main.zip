import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./app/**/*.{ts,tsx}",
    "./components/**/*.{ts,tsx}",
    "./lib/**/*.{ts,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        background: "#050816",
        primary: "#3B82F6",
        secondary: "#8B5CF6",
        accent: "#06B6D4",
        highlight: "#22D3EE",
      },
      fontFamily: {
        sans: ["var(--font-inter)", "system-ui", "-apple-system", "sans-serif"],
      },
      animation: {
        aurora: "aurora 18s ease-in-out infinite alternate",
        "aurora-slow": "aurora 26s ease-in-out infinite alternate-reverse",
        marquee: "marquee 45s linear infinite",
        "marquee-reverse": "marquee-reverse 45s linear infinite",
        shine: "shine 2.75s ease-in-out infinite",
        "spin-slow": "spin 14s linear infinite",
        "pulse-glow": "pulse-glow 4s ease-in-out infinite",
        "float-y": "float-y 6s ease-in-out infinite",
      },
      keyframes: {
        aurora: {
          "0%": { transform: "translate(-10%, -8%) rotate(0deg) scale(1)" },
          "50%": { transform: "translate(8%, 6%) rotate(30deg) scale(1.15)" },
          "100%": { transform: "translate(-6%, 10%) rotate(-20deg) scale(1.05)" },
        },
        marquee: {
          from: { transform: "translateX(0)" },
          to: { transform: "translateX(-50%)" },
        },
        "marquee-reverse": {
          from: { transform: "translateX(-50%)" },
          to: { transform: "translateX(0)" },
        },
        shine: {
          "0%": { transform: "translateX(-120%) skewX(-18deg)" },
          "60%, 100%": { transform: "translateX(240%) skewX(-18deg)" },
        },
        "pulse-glow": {
          "0%, 100%": { opacity: "0.55", transform: "scale(1)" },
          "50%": { opacity: "1", transform: "scale(1.08)" },
        },
        "float-y": {
          "0%, 100%": { transform: "translateY(0)" },
          "50%": { transform: "translateY(-14px)" },
        },
      },
      boxShadow: {
        glow: "0 0 40px -8px rgba(59, 130, 246, 0.45)",
        "glow-violet": "0 0 48px -10px rgba(139, 92, 246, 0.5)",
        "glow-cyan": "0 0 44px -10px rgba(6, 182, 212, 0.5)",
        card: "0 24px 60px -24px rgba(0, 0, 0, 0.65)",
        "inner-glass": "inset 0 1px 0 0 rgba(255,255,255,0.08)",
      },
      backgroundImage: {
        "grid-faint":
          "linear-gradient(to right, rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.04) 1px, transparent 1px)",
      },
    },
  },
  plugins: [],
};

export default config;
