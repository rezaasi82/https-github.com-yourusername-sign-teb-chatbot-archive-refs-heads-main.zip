# SEO Director AI — Technical Architecture

**Doc:** 2 of 7 · Spec v1.0.0

---

## 1. High-Level Architecture

```
┌─────────────────────────────  WordPress Site  ─────────────────────────────┐
│                                                                            │
│  React SPA (wp-admin page)  ── @wordpress/api-fetch ──►  REST API          │
│   Tailwind + Gutenberg comps                              sda/v1/*         │
│                                                              │             │
│                                     ┌────────────────────────┼──────────┐  │
│                                     │        Service Layer (DI)         │  │
│                                     │  Analyzers · HealthScore · Roadmap│  │
│                                     │  RootCause · Opportunities · Alerts│ │
│                                     └───────┬───────────────┬───────────┘  │
│                                             │               │              │
│   Action Scheduler (background jobs) ◄──────┘        Repositories          │
│    sync / analyze / audit / report                  (custom {p}sda_* tables)│
│         │                                                                  │
└─────────┼──────────────────────────────────────────────────────────────────┘
          │ HTTPS (wp_remote_*, OAuth, encrypted tokens)
          ▼
  Google Search Console API · GA4 Data API · PageSpeed Insights API
  OpenAI / Anthropic / Gemini APIs · License Server (api.seodirector.app)
  Agency Hub ◄── signed REST ── client sites
```

**Prefixes:** PHP namespace `SEODirector\`, DB table prefix `{wp_prefix}sda_`, REST namespace `sda/v1`, hook prefix `sda_`, option prefix `sda_`, text domain `seo-director-ai`.

**Core layering rule:** `Integrations → Repositories → Analyzers → AI → Presentation`. Data flows one way; the React app never talks to Google or AI providers directly — everything goes through the plugin REST API (keys never reach the browser).

## 2. Plugin Folder Structure (PSR-4)

```
seo-director-ai/
├── seo-director-ai.php            # Bootstrap: requirements check (PHP 8.2+, WP 6.4+), container boot
├── composer.json                  # PSR-4 autoload "SEODirector\\": "includes/"
├── uninstall.php                  # Honors "delete data on uninstall" setting
├── readme.txt                     # Marketplace/wp.org readme
├── includes/
│   ├── Core/
│   │   ├── Plugin.php             # Orchestrator: registers providers, boots modules
│   │   ├── Container.php          # Small PSR-11 DI container (constructor injection)
│   │   ├── Activator.php          # dbDelta schema, default options, capability setup
│   │   ├── Deactivator.php        # Unschedule jobs (keep data)
│   │   ├── Upgrader.php           # Versioned migrations (db_version option)
│   │   └── Capabilities.php       # manage_sda, view_sda_reports, manage_sda_clients
│   ├── Integrations/
│   │   ├── Google/
│   │   │   ├── OAuthClient.php    # Shared OAuth (PKCE, incremental scopes, refresh)
│   │   │   ├── TokenVault.php     # Encrypted token storage (see §7)
│   │   │   ├── SearchConsoleClient.php
│   │   │   ├── Analytics4Client.php
│   │   │   ├── PageSpeedClient.php
│   │   │   └── QuotaManager.php   # Per-API budgets, backoff, circuit breaker
│   │   └── Http/RetryingHttpClient.php  # wp_remote_* wrapper: retries, jitter, timeout policy
│   ├── Ai/
│   │   ├── AiProviderInterface.php      # complete(PromptEnvelope): AiResult
│   │   ├── Providers/{OpenAiProvider,ClaudeProvider,GeminiProvider}.php
│   │   ├── ProviderRouter.php           # selection + fallback chain + health
│   │   ├── PromptLibrary.php            # versioned prompts, per-language variants
│   │   ├── SchemaValidator.php          # strict JSON-schema validation of AI output
│   │   ├── TokenBudget.php              # per-feature monthly budgets, meter
│   │   └── InsightCache.php             # evidence-hash keyed cache (no re-spend)
│   ├── Data/
│   │   ├── Repository/                  # One repository per table (see DB doc)
│   │   ├── Rollup/RollupBuilder.php     # daily → weekly/monthly aggregates
│   │   ├── Retention/RetentionPolicy.php# pruning (raw 16 months, rollups forever)
│   │   └── UrlCanonicalizer.php         # GSC↔GA4↔WP URL joining
│   ├── Analysis/                        # ALL deterministic, unit-testable, no I/O
│   │   ├── TrendAnalyzer.php            # slopes, WoW/MoM/YoY, seasonality adjustment
│   │   ├── ChangepointDetector.php      # drop/jump date detection on series
│   │   ├── GrowthDetector.php           # winners
│   │   ├── DeclineDetector.php          # losers
│   │   ├── OpportunityDetector/         # one class per detector (9 detectors)
│   │   ├── RootCause/
│   │   │   ├── CauseCandidateEngine.php # evidence packets per candidate cause
│   │   │   └── CoreUpdateCalendar.php   # remotely-updatable update dates
│   │   ├── Cannibalization/ClusterAnalyzer.php
│   │   ├── InternalLinks/LinkGraphBuilder.php   # local WP content crawl
│   │   └── HealthScore/HealthScoreCalculator.php
│   ├── Roadmap/
│   │   ├── RoadmapGenerator.php         # opportunities+risks+AI → task list
│   │   └── TaskRepository.php
│   ├── Alerts/
│   │   ├── AlertEngine.php              # rule evaluation, dedup, auto-resolve
│   │   ├── Rules/*.php                  # one rule class per alert type
│   │   └── Channels/{Email,Webhook,Slack,Telegram}Channel.php
│   ├── Reports/
│   │   ├── ReportBuilder.php            # data assembly per template
│   │   ├── Renderers/{PdfRenderer,XlsxRenderer,CsvRenderer}.php   # bundled libs, no CDN
│   │   ├── GoogleExporter.php           # Sheets/Docs/Drive export
│   │   └── ReportScheduler.php          # the "every Saturday" pipeline
│   ├── Agency/
│   │   ├── HubController.php            # aggregate client-site data
│   │   ├── SiteConnector.php            # HMAC-signed site↔hub pairing
│   │   ├── WhiteLabel.php               # branding overrides (logo, colors, strings)
│   │   └── ClientAccess.php             # client-scoped read-only role
│   ├── Jobs/
│   │   ├── Scheduler.php                # Action Scheduler wrapper (WP-Cron fallback)
│   │   └── Handlers/                    # SyncGsc, SyncGa4, RunPsiAudit, RunAnalysis,
│   │                                    # GenerateInsights, EvaluateAlerts, WeeklyPipeline
│   ├── Rest/
│   │   ├── RestServiceProvider.php
│   │   └── Controllers/                 # see §5
│   ├── Admin/
│   │   ├── AdminMenu.php                # single top-level page mounting the SPA
│   │   ├── Assets.php                   # conditional enqueue: ONLY on our screens
│   │   └── SetupWizard.php              # first-run guided onboarding
│   ├── License/
│   │   ├── LicenseManager.php           # activate/deactivate/status, grace handling
│   │   ├── UpdateClient.php             # signed auto-updates from license server
│   │   └── FeatureGate.php              # edition→feature checks (single source of truth)
│   └── Compat/
│       ├── Multisite.php                # network activation, per-site data isolation
│       └── SeoPluginBridge.php          # read Yoast/RankMath meta where useful
├── src/                                 # React source (built → assets/dist)
├── assets/dist/                         # versioned build artifacts (wp_set_script_translations)
├── templates/reports/                   # PDF/HTML report templates (overridable)
├── languages/                           # .pot; fa_IR, ar, en_US shipped
└── tests/{Unit,Integration}/            # PHPUnit + wp-env; Analysis layer 90%+ coverage
```

## 3. Dependency Injection & Extensibility

- Lightweight PSR-11 container in `Core\Container`; all services constructor-injected; **no service locator in domain code, no god objects, no static state** (except the bootstrap accessor `sda()`).
- Every external boundary is an interface: `AiProviderInterface`, `GoogleClientInterface`, `AlertChannelInterface`, `ReportRendererInterface`, `LicenseClientInterface`. Third parties add providers/channels via filters: `sda_register_ai_providers`, `sda_register_alert_channels`, etc.
- **Action hooks:** `sda_sync_completed`, `sda_insight_generated`, `sda_alert_raised`, `sda_report_generated`, `sda_roadmap_updated`, `sda_license_status_changed`.
- **Filters:** `sda_health_score_weights`, `sda_opportunity_score`, `sda_ai_prompt`, `sda_report_sections`, `sda_alert_rules`.

## 4. Background Processing

- **Action Scheduler** (bundled, version-checked shared instance) is the job backbone; WP-Cron only triggers the queue runner. Every long operation is chunked (e.g., GSC backfill = one job per property × month).
- Job contract: idempotent, resumable (cursor in `sda_job_state`), bounded runtime (< 20 s per chunk), exponential backoff on API errors, dead-letter log surfaced in a **Sync Health** admin screen.
- Recurring schedule (defaults): GSC/GA4 incremental sync daily 03:00 site time · analysis pass after each sync · PSI audits weekly · alert evaluation hourly · weekly pipeline Saturday 07:00 · license check daily.

## 5. REST API (namespace `sda/v1`)

All routes: `permission_callback` with capability checks + nonce (`X-WP-Nonce`) for the SPA; agency hub routes use HMAC signatures instead. Rate limiting on expensive endpoints.

| Method | Route | Purpose | Capability |
|---|---|---|---|
| GET | `/overview` | dashboard bootstrap (health, trends, top opps/risks) | `view_sda_reports` |
| GET | `/metrics/search` | GSC series w/ dimensions + compare params | `view_sda_reports` |
| GET | `/metrics/analytics` | GA4 series | `view_sda_reports` |
| GET | `/metrics/vitals` | CWV history + latest audits | `view_sda_reports` |
| GET | `/winners` · `/losers` | movers with reasons/causes | `view_sda_reports` |
| GET/POST | `/opportunities` | list / re-scan | `manage_sda` (POST) |
| POST | `/insights/explain` | on-demand AI explanation for an entity | `manage_sda` |
| GET/POST/PATCH | `/roadmap` `/roadmap/tasks/{id}` | roadmap CRUD | `manage_sda` |
| GET/PATCH | `/alerts` `/alerts/{id}` | list / acknowledge / snooze | `manage_sda` |
| GET/POST | `/reports` | list / generate; GET `/reports/{id}/download` | `view_sda_reports` |
| POST/DELETE | `/connections/{service}` | OAuth connect/disconnect (GSC, GA4, PSI, AI) | `manage_sda` |
| GET/POST | `/settings` | settings read/write (sanitized per-field schema) | `manage_sda` |
| POST | `/license/activate` `/license/deactivate` · GET `/license/status` | licensing | `manage_sda` |
| GET | `/agency/sites` · POST `/agency/sites/pair` | hub management | `manage_sda_clients` |
| POST | `/hub/ingest` | client → hub metric push (HMAC-signed) | signature |

## 6. AI Engine Design

- **PromptEnvelope** = versioned prompt template id + evidence packet (structured JSON) + site context + target language + max tokens. Providers must return JSON matching a declared schema; `SchemaValidator` rejects/repairs (one retry with "fix to schema" instruction) before anything is stored or shown.
- **Determinism boundary:** numbers shown in the UI always come from the Analysis layer; AI output supplies *text fields only* (explanations, plans, titles). This prevents hallucinated metrics.
- **Cost controls:** insight cache keyed by `hash(prompt_version + evidence)`; batch mode groups 10 entities per call where schema allows; per-feature budget in `TokenBudget` with graceful "budget reached — insights resume next month or raise the cap" UI.
- Recommended default models (admin-overridable): Claude Sonnet (`claude-sonnet-5`) for analysis quality, GPT-4.1-mini / Gemini Flash tiers for bulk meta generation.

## 7. Security Architecture

| Area | Implementation |
|---|---|
| OAuth | Google-verified app, PKCE, `state` nonce, minimal scopes + incremental authorization (Drive scope only when exports enabled) |
| Secret storage | `TokenVault`: libsodium `crypto_secretbox` (AES-256-GCM via openssl fallback) with key derived from `SECURE_AUTH_KEY` + salt stored outside the row; never in plain options; masked in UI; excluded from exports |
| REST | capability + nonce on every route; `sanitize_callback`/`validate_callback` on every arg; strict output schemas |
| Output | `esc_html`/`esc_attr`/`wp_kses_post` everywhere in PHP-rendered fragments; React output escaped by default, `dangerouslySetInnerHTML` banned by lint rule |
| SQL | `$wpdb->prepare` exclusively; no dynamic table/column interpolation outside a whitelist |
| Rate limiting | transient-based sliding window on `/insights/explain`, `/opportunities` POST, license endpoints |
| SSRF | webhook URLs validated (`wp_http_validate_url` + block private ranges) |
| CSRF/clickjacking | admin SPA only in wp-admin; no front-end surface in v1 |
| Supply chain | composer deps vendored + scoped via PHP-Scoper (prevents version conflicts with other plugins); npm audit gate in CI |
| Privacy/GDPR | data stored locally; exporter/eraser integrations; documented data flows to Google/AI providers; "AI features off" mode fully supported |
| OWASP | checklist audit (A01–A10) as a release gate; see Marketplace doc |

## 8. Performance Budget

- **Zero frontend footprint:** the plugin enqueues *nothing* on the public site (admin-only product) — the strongest possible answer to "SEO plugins slow my site".
- Admin SPA: code-split per screen, < 250 KB gz initial chunk; charts lazy-loaded.
- Dashboard endpoints must serve from rollup tables only — hard rule: **no live Google API call in a web request**, ever; syncs are background-only.
- Query budget: overview endpoint ≤ 12 SQL queries, each indexed (verified by integration test with `SAVEQUERIES`).

## 9. Multisite & Compatibility

- Network activation supported; every table keyed by `site_id` (blog id); per-site connections and licenses; network-admin overview screen (Agency edition can treat network sites as clients).
- Compatibility floor: PHP 8.2+, WordPress 6.4+, MySQL 5.7+/MariaDB 10.4+. Declared in bootstrap with graceful admin-notice degradation, never a fatal.
