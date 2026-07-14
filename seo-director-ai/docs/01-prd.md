# SEO Director AI — Product Requirements Document (PRD)

**Doc:** 1 of 7 · Spec v1.0.0

---

## 1. Product Vision

Most SEO plugins show data. SEO Director AI explains:

- **What happened** (traffic/keyword/page movement, detected from stored time-series)
- **Why it happened** (root-cause analysis with confidence scores)
- **What should be done next** (prioritized, ROI-ranked action roadmap)
- **Which pages require attention** (risk detection)
- **Which keywords are closest to growth** (opportunity detection)

It behaves like an **SEO Director**, not a reporting tool: it holds the strategy, assigns priorities, and reports to stakeholders automatically.

### 1.1 Positioning statement

> For site owners, in-house marketers, and agencies who are drowning in disconnected SEO dashboards, **SEO Director AI** is a WordPress-native SEO management platform that turns Search Console, GA4, and Core Web Vitals data into an explained, prioritized action plan — unlike Rank Math Analytics, Site Kit, or standalone dashboards, which stop at charts.

### 1.2 Explicit non-goals (v1)

- Not a rank tracker with its own SERP crawler (we use GSC position data; SERP APIs are an Enterprise-phase option).
- Not an on-page content editor / TinyMCE SEO scorer (Yoast/Rank Math coexist; we integrate, not replace).
- Not a backlink index (backlink signals arrive later via optional third-party connectors).
- No AI auto-publishing. AI generates drafts and plans; humans approve.

---

## 2. Personas

| Persona | Profile | Primary jobs-to-be-done | Key screens |
|---|---|---|---|
| **Solo Site Owner** ("Sara") | Runs a WooCommerce/content site, non-technical, 30 min/week for SEO | "Tell me the 3 things to do this week and why" | Overview, Roadmap, Alerts |
| **In-house Marketer** ("Mehdi") | Marketing generalist at an SME, reports to a manager | "Prove SEO progress; explain drops before the boss asks" | Winners/Losers, Reports, Health Score |
| **SEO Specialist** ("Dana") | Technical, wants depth and export | "Find every position 4–20 opportunity; diagnose cannibalization" | Opportunities, Root Cause, raw data drill-downs |
| **Agency Owner** ("Arman") | Manages 15–200 client sites, white-label reporting is billable | "Monitor all clients from one screen; branded PDF every month, zero manual work" | Agency Dashboard, White Label, Automated Reports |

---

## 3. Core Modules — Functional Requirements

### Module 1 — Google Search Console Integration

| ID | Requirement | Priority |
|----|-------------|----------|
| GSC-01 | OAuth 2.0 connect flow (Google-verified app; PKCE; tokens encrypted at rest) | MVP |
| GSC-02 | Property picker incl. domain properties; multi-property per site (Agency) | MVP |
| GSC-03 | Collect clicks, impressions, CTR, avg position — by **query, page, country, device, date** | MVP |
| GSC-04 | Backfill up to 16 months of history on first connect (chunked background jobs) | MVP |
| GSC-05 | Daily incremental sync (GSC data lags ~2 days; sync window handles late data restatement) | MVP |
| GSC-06 | Comparisons: day/week/month/quarter/year, plus custom ranges, period-over-period and year-over-year | MVP |
| GSC-07 | URL Inspection API for indexation status of priority pages (quota-aware queue) | PRO |
| GSC-08 | Sitemaps + coverage error ingestion for the Alert Center | PRO |

**Data-volume control (critical):** raw GSC rows explode combinatorially (query × page × country × device). Strategy: store **top-N dimensioned rows per day** (configurable, default 5,000 query rows + 2,000 page rows) plus **exact site-level daily totals**, and pre-computed weekly/monthly rollups. See Database Design §4.

### Module 2 — Google Analytics 4 Integration

| ID | Requirement | Priority |
|----|-------------|----------|
| GA4-01 | OAuth against Analytics Data API; property picker | MVP |
| GA4-02 | Collect sessions, totalUsers, engagement rate, conversions/key events, by landing page + session default channel group | MVP |
| GA4-03 | **Organic-focused views**: everything filterable to `Organic Search` channel | MVP |
| GA4-04 | Landing-page ↔ GSC-page join (canonicalized URL matching) to unify "page performance" | MVP |
| GA4-05 | Event/conversion trend ingestion for revenue-aware prioritization | PRO |

### Module 3 — PageSpeed Insights Integration

| ID | Requirement | Priority |
|----|-------------|----------|
| PSI-01 | PSI API (site API key or plugin-provided pooled key): LCP, CLS, INP, TTFB, mobile & desktop scores; lab + CrUX field data when available | MVP |
| PSI-02 | Scheduled audits of home + top-20 traffic pages (weekly), quota-aware queue | MVP |
| PSI-03 | Per-audit actionable recommendations mapped to WordPress-specific fixes (e.g., "LCP image not preloaded → enable preload in your optimization plugin / theme") | MVP |
| PSI-04 | CWV trend history + regression alerts ("INP degraded on /checkout after 2026-06-10") | PRO |

### Module 4 — AI SEO Intelligence Engine

**Provider abstraction:** admin chooses **OpenAI / Anthropic Claude / Google Gemini** (BYO API key; SaaS-proxied keys in the hosted plan). Uniform interface, per-provider adapters, model pinning + fallback chain, hard monthly token budget with admin-visible meter.

**Pipeline (deterministic → AI):**

1. **Signal layer (no AI):** analyzers compute growth/decline series, changepoints, opportunity candidates, cannibalization clusters, CWV regressions. Output = structured JSON "evidence packets".
2. **AI layer:** evidence packets + site context (niche, language, goals) → provider → structured response (strict JSON schema): explanation, ranked causes with confidence, recommended actions with impact/effort estimates.
3. **Persistence:** every AI insight stored with the evidence snapshot that produced it (auditability + re-render without re-spend).

| ID | Requirement | Priority |
|----|-------------|----------|
| AI-01 | Growth detection & explanation (keywords, pages, traffic) with recommendations | MVP |
| AI-02 | Decline detection, root-cause candidates, recovery plan | MVP |
| AI-03 | Confidence score per cause (calibrated: evidence-strength heuristics weight the AI's stated confidence) | MVP |
| AI-04 | Output language follows site/admin locale — **Persian, Arabic, English** first-class | MVP |
| AI-05 | Cost governor: caching, batch windows, per-feature token budgets, "explain on demand" vs "auto-explain top 10" modes | MVP |
| AI-06 | Provider health fallback (Claude → OpenAI → Gemini order configurable) | PRO |

### Module 5 — SEO Health Score

Composite **0–100** score, recomputed daily, with full sub-score transparency ("why is my score 62?").

| Component | Weight (default) | Source |
|---|---|---|
| Ranking strength (weighted avg position of top queries) | 20 | GSC |
| CTR vs expected-by-position curve | 15 | GSC |
| Core Web Vitals (field data preferred) | 15 | PSI/CrUX |
| Indexation health (indexed ratio, coverage errors) | 10 | GSC |
| Content freshness (decaying pages ratio) | 10 | GSC + WP post dates |
| Internal linking (orphan pages, link depth of money pages) | 10 | Local crawl of WP content |
| Growth trend (28-day clicks slope) | 10 | GSC |
| Traffic trend (organic sessions slope) | 10 | GA4 |

Status bands: **Green ≥ 75 · Yellow 50–74 · Red < 50**. Weights filterable via `sda_health_score_weights`. Every sub-score links to the module that improves it.

---

## 4. Intelligence Features

### 4.1 Executive Dashboard

Sections (see UI/UX Plan for wireframes):

- **Overview:** Health Score dial + trend, organic traffic chart, Top 3 Opportunities, Top 3 Risks, Weekly & Monthly summary cards (AI-written, 3 sentences max each).
- **Winners:** top growing keywords & pages — growth %, *reason for growth*, recommended next action.
- **Losers:** top losing keywords & pages — estimated cause, priority level, suggested fix.

### 4.2 Root Cause Analysis Engine

For every material decline, investigate and score (evidence-based candidate generation, AI-ranked):

| Candidate cause | Primary evidence signals |
|---|---|
| CTR drop (SERP feature / title issue) | position stable, CTR down |
| Ranking drop | position series changepoint |
| Competitor growth | position down while impressions stable/up |
| Content decay | age since update, gradual multi-quarter slope |
| Internal linking issues | orphaned/de-linked page detected in local link graph |
| Technical SEO issue | coverage errors, noindex/canonical changes, CWV regression |
| Search intent mismatch | impressions up + CTR down + position volatile |
| Google Core Update impact | drop date within ±5 days of known update (maintained update calendar, remotely updatable) |
| Cannibalization | ≥2 URLs alternating for same query cluster |

Output format (always): ranked causes, e.g. `Cause #1 — Cannibalization between /guide and /blog/guide-2 · Confidence 87%`, each with the evidence shown and a fix plan.

### 4.3 Opportunity Detector

Automatic detectors (each yields scored, deduplicated opportunity records):

1. Keywords ranking **4–20** (striking distance), sorted by `impressions × position-gain headroom`
2. **High impressions + low CTR** pages (CTR below position-expected curve by >35%)
3. Pages close to **Top 3** (positions 4–6) and close to **Page 1** (positions 11–15)
4. **Featured snippet** opportunities (position 1–5 informational queries w/o snippet ownership signal)
5. **FAQ** opportunities (question-form queries clustering on a page)
6. **Schema** opportunities (page type detected without matching structured data)
7. **Video SEO** opportunities (video-intent queries landing on non-video pages)
8. **Internal linking** opportunities (high-authority pages not linking to striking-distance targets)
9. **Local SEO** opportunities (geo-modified queries, GBP-related patterns)

Each opportunity carries: **estimated impact (traffic delta), difficulty, freshness, and a one-click "add to roadmap"**.

### 4.4 AI Content Strategist

Generates (drafts, never auto-publish): pillar pages, cluster content maps, blog topics, FAQs, meta titles & descriptions (with SERP-pixel-width preview), content refresh plans, content gap analysis (vs. query data the site earns impressions for but has no dedicated page). Languages: **Persian / Arabic / English**, honoring RTL and local search behavior.

### 4.5 Roadmap Engine

Generates weekly / monthly / quarterly roadmaps. Every task record: **Task · Impact (1–10) · Difficulty (1–10) · Estimated hours · Priority (computed = impact ÷ effort, editable) · Category · Owner · Expected result**. Tasks have statuses (todo/in-progress/done/dismissed) and completion feeds back into the next roadmap generation ("done last month, measured result: +X clicks").

### 4.6 Alert Center

| Alert | Trigger example | Default priority |
|---|---|---|
| Traffic drop | organic clicks −25% WoW (seasonality-adjusted) | Critical |
| Keyword loss | top-10 keyword exits top 20 | High |
| CTR drop | site CTR −20% with stable position | High |
| CWV issue | any CWV metric flips to "poor" on a top page | High |
| Indexing problem | indexed count −10% or key page deindexed | Critical |
| Coverage errors | new error class in GSC coverage | Medium |
| Manual action | GSC manual action detected | Critical |
| Ranking loss | avg position of tracked group +3 | Medium |
| Critical SEO issue | e.g., sitewide noindex detected locally | Critical |

Levels: **Critical / High / Medium / Low**. Channels: in-dashboard, email, webhook (PRO), Slack/Telegram (PRO). Dedup + snooze + auto-resolve when the condition clears.

### 4.7 Agency Mode

- **Unlimited client websites** (per license tier) monitored from one **central dashboard** (hub-and-spoke: each client site runs the plugin; the hub aggregates via signed REST).
- Multi-site monitoring grid: per-site health score, alert counts, trend sparkline.
- **White Label:** rebrand plugin name/logo/colors in wp-admin, remove vendor mentions in reports, custom report domain support (CNAME for hosted report links — SaaS phase).
- Client-scoped users: a client login sees only their site's read-only reports.

### 4.8 Client Reports & Automated Reporting

- Weekly / monthly / quarterly reports; exports: **PDF, Excel (xlsx), CSV, Google Sheets, Google Docs, Google Drive** (Drive/Docs/Sheets via the already-connected Google OAuth, with incremental scopes requested only when enabled).
- **Every Saturday** (configurable day/time/timezone) the automation pipeline runs: fetch latest data → analyze trends → detect opportunities → detect risks → update roadmap → generate report → email stakeholders. Implemented as an Action Scheduler chain with per-step retry and a visible run log.

---

## 5. Feature Matrix by Edition

| Feature | **MVP (v1.0)** | **PRO** | **Agency** | **Enterprise (future)** |
|---|:--:|:--:|:--:|:--:|
| GSC + GA4 + PSI integration | ✅ | ✅ | ✅ | ✅ |
| Executive dashboard, Winners/Losers | ✅ | ✅ | ✅ | ✅ |
| SEO Health Score | ✅ | ✅ | ✅ | ✅ |
| AI explanations (BYO key) | ✅ (top-10 auto) | ✅ unlimited* | ✅ | ✅ + pooled keys |
| Opportunity Detector | core 3 detectors | all 9 | all 9 | all 9 + custom |
| Root Cause Engine | basic causes | full + confidence | full | full + SERP API signals |
| Roadmap Engine | monthly | weekly/monthly/quarterly | + owner assignment | + task API/Jira sync |
| Alert Center | in-dash + email | + webhook/Slack/Telegram | + per-client routing | + SLA policies |
| Content Strategist | meta + topics | full | full | full + brand voice profiles |
| Reports | PDF monthly | all formats/schedules | white-label + client portal | custom templates |
| Agency central dashboard | — | — | ✅ | ✅ |
| White label | — | — | ✅ | ✅ + custom domain |
| Multisite support | ✅ | ✅ | ✅ | ✅ |
| SaaS hosted option | — | — | — | ✅ |

\* subject to fair-use token budget when using vendor-proxied keys.

## 6. Success Metrics

- Activation: % of installs completing OAuth within 24h (target ≥ 60%).
- Weekly retention of dashboard opens (target ≥ 45% at week 4).
- "Action taken" rate: % of AI recommendations marked done/dismissed (engagement proxy).
- Marketplace: ≥ 4.6★ average; refund rate < 5%.
- Support load: < 0.15 tickets per sale in month 1 (setup wizard quality proxy).
