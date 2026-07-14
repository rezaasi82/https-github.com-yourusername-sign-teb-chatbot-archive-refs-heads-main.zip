# SEO Director AI — Development Roadmap

**Doc:** 7 of 7 · Spec v1.0.0

Phased plan: **MVP → PRO → Agency → Enterprise**, in 2-week sprints. Each phase ends releasable. Estimates assume 1 senior full-stack WP dev + 1 React dev (adjust linearly).

---

## Phase 0 — Foundation (Sprints 1–2)

- Repo, CI (PHPCS/PHPStan/PHPUnit/vite build/plugin-check), coding standards, PR gates
- Bootstrap, DI container, Activator/Upgrader with full schema (DB doc), Capabilities
- Action Scheduler integration + job framework (idempotent chunked handlers, `sda_job_state`)
- `TokenVault`, `RetryingHttpClient`, `QuotaManager`
- React shell: SPA mount, routing, theme/RTL providers, design tokens, DataTable/Card/Chart primitives
- **Exit criteria:** empty dashboard renders in light/dark/RTL; a demo chunked job survives interruption and resumes.

## Phase 1 — Data In (Sprints 3–5)

- Google OAuth (PKCE, shared client, incremental scopes) + connection health screen
- GSC client + backfill (16 months, chunked) + daily incremental sync + restatement window
- GA4 client + sync; URL canonicalizer + GSC↔GA4 join; rollup builder + retention pruning
- PSI client + weekly audit queue
- Setup Wizard v1 (connect flows + backfill progress) · Demo-data mode
- **Exit criteria:** real property fully backfilled; overview endpoint serves entirely from rollups; sync survives token revocation gracefully.

## Phase 2 — Deterministic Intelligence (Sprints 6–8)

- TrendAnalyzer, ChangepointDetector, Growth/DeclineDetector, seasonality adjustment
- Health Score calculator + history + "why is my score X" breakdown
- Opportunity detectors: striking-distance, low-CTR, near-top3/page1 (MVP trio)
- Overview, Winners/Losers screens (without AI text), Alert Engine core rules (traffic drop, keyword loss, indexing) + email channel
- **Exit criteria:** on a 16-month fixture dataset, detectors reproduce hand-verified expected outputs (golden tests); health score stable ±2 across reruns.

## Phase 3 — AI Layer (Sprints 9–10)

- Provider adapters (OpenAI/Claude/Gemini), router + fallback, PromptLibrary (EN/FA/AR), SchemaValidator, TokenBudget, InsightCache
- Growth/decline explanations, weekly/monthly summaries, Root Cause engine (evidence packets → ranked causes with confidence) + Root Cause UI
- Roadmap Engine v1 (monthly) + Roadmap screen (kanban)
- **Exit criteria:** AI output always schema-valid or gracefully absent; zero AI spend on cache hits; full product usable with AI disabled.

## 🚢 **MVP RELEASE (v1.0)** — end of Sprint 10

Starter edition scope per PRD feature matrix. Soft-launch on own store → CodeCanyon submission.

## Phase 4 — PRO (Sprints 11–13)

- Remaining 6 opportunity detectors (snippet, FAQ, schema, video, internal-link graph, local)
- Cannibalization cluster analyzer; Core-update calendar; full root-cause taxonomy
- Content Strategist (pillar/cluster maps, topics, FAQs, meta generator with SERP preview, refresh plans, gap analysis)
- Reports: PDF/XLSX/CSV renderers, Google Sheets/Docs/Drive export, Saturday automation pipeline + run log
- Alert channels: webhook/Slack/Telegram; weekly & quarterly roadmaps
- Licensing engine + editions + FeatureGate + update client + customer portal integration
- **Release v1.5 (PRO)** → Zhaket/RTL Theme submissions (FA translation + docs complete here)

## Phase 5 — Agency (Sprints 14–16)

- Hub ↔ site pairing (HMAC), snapshot push, sites grid dashboard
- White label engine (branding tokens, report theming, string overrides), client read-only role
- Per-client report scheduling + branded delivery; multisite network screen
- **Release v2.0 (Agency)**

## Phase 6 — Hardening & Lite (Sprints 17–18)

- Performance passes (query budgets, SPA code-split audit), security re-audit, i18n audit
- WordPress.org **Lite** build (GSC overview + health score), upgrade funnel
- Marketplace launch assets: demo instance, video, docs site

## Phase 7 — Enterprise / SaaS (post-launch, gated on traction)

- Hosted hub (S1) → managed AI keys (S2) → full SaaS onboarding without WP (S3), per Monetization doc §3
- Enterprise features: SERP-API-enriched root cause, task sync (Jira/Trello), custom report templates, brand-voice profiles, SLA alerting

---

## Risk Register (top 5)

| Risk | Impact | Mitigation |
|---|---|---|
| Google OAuth app verification delays (sensitive scopes) | blocks launch | start verification in Phase 1 week 1; wizard supports user-provided OAuth client as fallback |
| GSC data volume on huge sites | DB bloat, slow syncs | top-N caps + rollups (designed in), load-test with 1M-row fixtures in Phase 1 |
| AI cost surprises for BYO-key users | refunds, bad reviews | TokenBudget meter + estimates shown *before* enabling auto-explain |
| Envato soft-rejects | timeline slip | WPCS/escaping gates in CI from Phase 0; pre-submission third-party code review |
| Shared-host cron unreliability | stale data | Action Scheduler + external-cron instructions + staleness detection banner with one-click diagnosis |

## Definition of Done (every sprint)

Code reviewed · unit + integration tests green · PHPCS/PHPStan clean · strings translatable · RTL + dark-mode checked on touched screens · docs updated · changelog entry.
