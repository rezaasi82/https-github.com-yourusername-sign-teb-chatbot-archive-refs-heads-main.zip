# Medora AI — 3D Futuristic Landing Page

A premium, immersive 3D landing page for **Medora AI** (the intelligence layer for modern healthcare), built to 2026 SaaS standards: dark luxury theme, glassmorphism, aurora gradient mesh, WebGL 3D scenes, and scroll-driven motion throughout.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Next.js 14 (App Router) + React 18 + TypeScript |
| Styling | Tailwind CSS 3 (custom theme, glass utilities, keyframe animations) |
| Motion | Framer Motion 11 (reveals, stagger, accordion, 3D tilt) + GSAP ScrollTrigger |
| 3D | Three.js + React Three Fiber + Drei (AI orb, glass helix showcase) |
| Scroll | Lenis smooth scrolling, synced with GSAP ticker |
| Icons | Lucide React |
| Font | Inter via `next/font` (self-hosted, zero layout shift) |

## Sections

1. **Hero** — animated gradient headline, floating 3D AI orb (distorted icosahedron + orbit rings + sparkles), cursor-following glow, mouse parallax, count-up statistics, trust badges, magnetic CTAs.
2. **3D Showcase** — glass torus-knot "reasoning core" rotated by scroll progress, tilted by cursor, lit by animated spot/point lights over a reflective disc.
3. **Features** — bento grid of glass cards with cursor spotlight, glow borders, hover lift, icon animation, and on-hover particle fields.
4. **Product Demo** — floating dashboard mockup with perspective 3D tilt, `translateZ` depth layers, animated bar chart and live queue.
5. **Testimonials** — dual infinite marquee rows (pause on hover), floating glass cards, gradient avatars.
6. **Pricing** — gradient-border cards with 3D hover elevation and shine sweep; popular plan elevated.
7. **FAQ** — smooth Framer Motion accordion on blur-glass cards.
8. **Final CTA** — outward-drifting particle field, cursor-driven spotlight, magnetic gradient CTA.

## Performance & Accessibility

- 3D scenes are `dynamic(..., { ssr: false })` — zero WebGL cost on the server, streamed in after first paint.
- Canvas particle fields are dependency-free, DPR-capped, and **pause via IntersectionObserver** when off-screen.
- `prefers-reduced-motion` disables Lenis smoothing and all keyframe/reveal animation.
- Aurora background is pure CSS (GPU-composited blurred blobs) — no per-frame JS.
- Semantic landmarks (`header/nav/main/section/footer`), aria labels on icon buttons, `aria-expanded` on accordion, focusable CTAs.
- Full SEO metadata: Open Graph, Twitter cards, keywords, robots, `themeColor`.

## Getting Started

```bash
npm install
npm run dev     # http://localhost:3000
npm run build   # production build
```

## Structure

```
medora-ai-landing/
├── app/                 # layout (fonts/SEO), page, globals.css (theme + glass + keyframes)
├── components/
│   ├── providers/       # SmoothScroll (Lenis + GSAP sync)
│   ├── sections/        # Navbar, Hero, Showcase3D, Features, ProductDemo,
│   │                    # Testimonials, Pricing, FAQ, FinalCTA, Footer
│   ├── three/           # AIOrb, ShowcaseScene (R3F)
│   └── ui/              # Reveal, MagneticButton, SectionHeading,
│                        # AuroraBackground, Particles, CountUp
├── hooks/               # useMousePosition
└── lib/                 # data (all copy/content), utils
```

All copy lives in `lib/data.ts` — rebrand or localize the entire page from one file.
