# SEO Director AI — Software Specification Suite

> **Status:** Implementation in progress — Phase 0 (Foundation) + core of Phases 1–3 landed in the plugin root (`../includes`, `../assets`). See the roadmap doc for phase scope.
> **Version:** 1.0.0-spec · **Date:** 2026-07-14 · **Code started:** 2026-07-15

**SEO Director AI** is a premium, AI-powered SEO management platform for WordPress that acts as a *virtual SEO Director* — it does not merely display data; it **monitors, analyzes, prioritizes, explains, and recommends actions**.

## Document Index

| # | Document | Covers deliverables |
|---|----------|--------------------|
| 1 | [Product Requirements Document (PRD)](01-prd.md) | Product vision, personas, all core modules, feature matrix, MVP / PRO / Agency / Enterprise versions |
| 2 | [Technical Architecture](02-technical-architecture.md) | Complete product architecture, plugin folder structure, API integration layer, AI engine, background processing, security |
| 3 | [Database Design](03-database-schema.md) | Full custom-table schema, indexing strategy, retention & rollups |
| 4 | [UI/UX Plan](04-ui-ux-plan.md) | Admin dashboard wireframes, React component structure, design system, RTL & dark mode |
| 5 | [Licensing & Monetization Strategy](05-licensing-monetization.md) | Licensing architecture, pricing tiers, SaaS expansion plan |
| 6 | [Marketplace Strategy](06-marketplace-strategy.md) | CodeCanyon / ThemeForest / RTL Theme / Zhaket approval readiness, GTM |
| 7 | [Development Roadmap](07-development-roadmap.md) | Phased build plan: MVP → PRO → Agency → Enterprise |

## One-paragraph product thesis

Most SEO plugins answer *"what are my numbers?"*. SEO Director AI answers *"**what happened, why, and what should I do next — in what order?**"*. It fuses Google Search Console, GA4, and PageSpeed Insights data inside custom WordPress tables, runs a deterministic signal-analysis layer on top, and uses a pluggable AI layer (OpenAI / Claude / Gemini) to explain causes, score confidence, rank opportunities by ROI, and generate weekly/monthly/quarterly roadmaps — with full agency/white-label support and automated stakeholder reporting.

## Non-negotiable engineering principles

1. **Deterministic first, AI second.** Growth/decline detection, opportunity scoring, and health scores are computed by deterministic analyzers on stored data. The AI layer *explains and plans*; it never invents metrics. This keeps the product useful even with zero AI credits and keeps AI costs bounded.
2. **Own the data.** All API data lands in custom tables (never post meta / options blobs), pre-aggregated into daily rollups so dashboards render from local SQL, not live API calls.
3. **Fail soft.** Every external dependency (Google APIs, AI providers, license server) has cached fallbacks and grace behavior. The dashboard never white-screens because a token expired.
4. **Marketplace-approval-ready from day one.** WPCS + PHPStan level 6, escaping/sanitization everywhere, no PHP short tags, no external CDN assets, GPL-compatible split (PHP GPL, SaaS backend proprietary).
5. **RTL and Persian/Arabic are first-class citizens** — not an afterthought (target markets include Zhaket and RTL Theme).
