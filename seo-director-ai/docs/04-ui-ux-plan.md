# SEO Director AI — UI/UX Plan

**Doc:** 4 of 7 · Spec v1.0.0

---

## 1. Design Principles

1. **Directive, not descriptive.** Every screen leads with a verdict and a next action; charts are supporting evidence, not the headline.
2. **Modern SaaS inside wp-admin.** Full-bleed React SPA on our admin page (wp-admin chrome retained for trust/marketplace compliance), card-based layout, generous whitespace, skeleton loaders — never spinners over blank screens.
3. **Light + Dark mode** (follows admin scheme by default, user-overridable). All colors are design tokens; the white-label palette swaps tokens, not components.
4. **RTL first-class:** entire layout mirrors via CSS logical properties (`margin-inline-start`, not `margin-left`); numerals and dates localized (Jalali calendar display for fa_IR); Persian/Arabic UI fonts bundled (Vazirmatn / IBM Plex Arabic), latin fallback system stack.
5. **Accessibility:** WCAG 2.1 AA — keyboard-complete navigation, focus rings, `aria-live` for async updates, chart data available as accessible tables, contrast-checked palettes in both themes.
6. **Trust the numbers:** every AI-generated text is visually tagged (✦ AI badge) and links to its evidence; deterministic numbers never carry the badge.

## 2. Information Architecture

```
SEO Director (top-level admin menu)
├── Overview            (default)
├── Winners & Losers
├── Opportunities
├── Root Cause          (opens contextually from Losers/Alerts too)
├── Roadmap
├── Alerts              (badge count in menu)
├── Content Strategist
├── Reports
├── Agency              (Agency edition: Sites grid, White Label, Clients)
└── Settings            (Connections · AI Provider · Alerts · Reports · License · Advanced)
```

First-run replaces everything with the **Setup Wizard** (5 steps): Welcome → Connect Google (GSC + GA4 in one OAuth) → Choose AI provider (or "skip — no AI mode") → Site profile (niche, language, goals) → Backfill starts (progress screen with "come back tomorrow" honesty + email-me-when-ready).

## 3. Wireframes (key screens)

### 3.1 Overview

```
┌────────────────────────────────────────────────────────────────────────────┐
│ SEO Director        [period: Last 28 days ▾] [vs previous ▾]   [☾] [⟳ Sync]│
├──────────────┬─────────────────────────────────────────────────────────────┤
│  HEALTH      │  ORGANIC TRAFFIC                                     [GSC]  │
│   ┌─────┐    │   clicks ▁▂▃▅▆▅▇  8,412  ▲ +12.4%                           │
│   │ 72  │    │   impressions / CTR / position tabs                         │
│   │ 🟡  │    │                                                             │
│   └─────┘    │─────────────────────────────────────────────────────────────│
│  ▲ +4 vs LM  │  ✦ WEEKLY SUMMARY                                           │
│  [Why 72? ▸] │  "Clicks grew 12% driven by 3 recovering keywords…"         │
│  8 sub-scores│  [Read monthly summary ▸]                                   │
├──────────────┴──────────────────────────┬──────────────────────────────────┤
│  TOP OPPORTUNITIES               [all ▸]│  TOP RISKS                [all ▸]│
│  1. "قیمت …" pos 4.2 → est. +480 clicks │  ⛔ /pricing lost 38% clicks     │
│     [Add to roadmap] [✦ Explain]        │     Cause: cannibalization (87%) │
│  2. /guide low CTR at pos 3 …           │  ⚠ INP poor on /checkout mobile  │
│  3. FAQ opportunity on /faq …           │  ⚠ 12 pages dropped from top 10  │
└─────────────────────────────────────────┴──────────────────────────────────┘
```

### 3.2 Winners & Losers

Two-tab table screen. Columns (Winners): Keyword/Page · Clicks Δ · Growth % · Position Δ · **✦ Reason for growth** · **Recommended next action** · [Add to roadmap]. Losers add: **Estimated cause (confidence %)** · Priority chip · **Suggested fix** · [Full root cause ▸]. Row click opens a right-side drawer: entity time-series, related queries/pages, insight history.

### 3.3 Root Cause drawer/screen

```
┌ /pricing — clicks −38% (May 12 → today) ────────────────────────────┐
│ [chart: clicks + position, changepoint marker at May 14]           │
│ ✦ RANKED CAUSES                                                    │
│ #1 Cannibalization with /blog/pricing-guide      ████████░ 87%     │
│     Evidence: both URLs alternate for 14 queries · [show queries]  │
│     Fix plan: consolidate → 301 → update internal links  [→ task]  │
│ #2 Core update impact (June 2026)                ███████░░ 71%     │
│ #3 Content decay (last updated 14 mo ago)        ████░░░░░ 45%     │
└────────────────────────────────────────────────────────────────────┘
```

### 3.4 Roadmap

Kanban (To do / In progress / Done) + list toggle; scope tabs Weekly/Monthly/Quarterly; each card shows Impact◆ Difficulty◆ Est-hours ◆ Owner avatar ◆ Expected result; "✦ Regenerate roadmap" respects done/dismissed history. Completed tasks later show **measured result** ("+310 clicks since completion").

### 3.5 Agency dashboard

Grid of client-site cards: health dial, 28-day sparkline, active alert count by severity, last sync, [Open] [Report]. Toolbar: search, sort by health/alerts, "Generate all monthly reports". White Label settings: logo upload, 2-color palette, product name override, report footer, live report preview.

## 4. React Application Structure

Stack: **React 18 + TypeScript**, `@wordpress/element`-compatible build, **Tailwind CSS** (prefixed `sda-`, preflight scoped to our root to avoid bleeding into wp-admin), **@wordpress/components** where native UX is expected (modals, notices), **Chart.js 4** via thin wrapper, Zustand for local state + **TanStack Query** for all `sda/v1` data (caching, retries, optimistic updates), `@wordpress/i18n` for strings, Vite build → `assets/dist` with `wp_set_script_translations`.

```
src/
├── app/
│   ├── App.tsx                 # router (hash-based), theme + RTL providers
│   ├── routes.tsx
│   └── providers/{QueryProvider,ThemeProvider,LicenseGateProvider}.tsx
├── api/                        # typed client per REST controller + zod schemas
├── stores/                     # ui.store.ts, settings.store.ts
├── components/
│   ├── ui/                     # Button, Card, Badge, Tabs, Drawer, DataTable,
│   │   │                       # StatTile, TrendSpark, ScoreDial, EmptyState,
│   │   │                       # SkeletonLoader, AiBadge, ConfidenceBar
│   ├── charts/                 # TimeSeriesChart, ComparisonChart, DonutChart (lazy)
│   └── layout/                 # Shell, Sidebar, Topbar, PeriodPicker, SyncStatus
├── features/
│   ├── overview/               # OverviewPage, HealthScoreCard, SummaryCard,
│   │                           # OpportunityList, RiskList
│   ├── movers/                 # WinnersLosersPage, MoverTable, EntityDrawer
│   ├── opportunities/          # OpportunitiesPage, DetectorFilter, OpportunityCard
│   ├── rootcause/              # RootCausePanel, CauseCard, EvidenceViewer
│   ├── roadmap/                # RoadmapPage, KanbanBoard, TaskCard, TaskEditor
│   ├── alerts/                 # AlertsPage, AlertRow, RuleSettings
│   ├── content/                # StrategistPage, TopicClusterMap, MetaEditor(SERP preview)
│   ├── reports/                # ReportsPage, ReportBuilder, SchedulePicker
│   ├── agency/                 # SitesGrid, SiteCard, WhiteLabelSettings, PairSiteModal
│   ├── settings/               # ConnectionsPanel, AiProviderPanel, LicensePanel…
│   └── onboarding/             # WizardShell + 5 step components
└── styles/tokens.css           # design tokens: colors, spacing, both themes
```

Component conventions: feature components fetch via hooks (`useOverview()`, `useMovers()`); `ui/` components are pure/presentational; every async view ships loading-skeleton + error + empty states designed explicitly (empty states teach: "No opportunities yet — data backfill is 60% done").

## 5. Design Tokens (excerpt)

| Token | Light | Dark |
|---|---|---|
| `--sda-bg` | #F6F7F9 | #101318 |
| `--sda-surface` | #FFFFFF | #1A1F27 |
| `--sda-primary` | #2563EB | #60A5FA |
| `--sda-positive` | #0F9D58-family (AA-checked) | adjusted |
| `--sda-negative` | #DC2626 | #F87171 |
| `--sda-warning` | #D97706 | #FBBF24 |
| severity chips | critical/high/medium/low mapped to negative/warning/primary/neutral | same |

Charts use a colorblind-safe categorical palette; growth/decline additionally encoded with ▲/▼ glyphs (never color alone).
