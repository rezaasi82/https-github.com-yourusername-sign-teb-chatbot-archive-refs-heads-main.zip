=== SEO Director AI ===
Contributors: seodirector
Tags: seo, search console, analytics, ai, core web vitals
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI-powered SEO management platform that monitors, analyzes, prioritizes, explains, and recommends actions — a virtual SEO Director inside WordPress.

== Description ==

Most SEO plugins show data. SEO Director AI explains what happened, why it happened, and what to do next — with prioritized, ROI-ranked action plans.

* Google Search Console, Google Analytics 4, and PageSpeed Insights integration
* SEO Health Score with transparent sub-scores
* Winners & Losers with AI-explained causes and confidence scores
* Opportunity detection (striking-distance keywords, low-CTR pages, snippets, FAQs, schema, internal links)
* Weekly / monthly / quarterly roadmaps with impact, difficulty, and priority
* Alert Center for traffic drops, ranking losses, CWV regressions, and indexing problems
* Automated stakeholder reports (PDF, Excel, CSV, Google Sheets/Docs/Drive)
* Agency mode with white-label branding
* RTL-first UI with full Persian, Arabic, and English support
* Dark and light mode

== External services ==

This plugin connects to the following external services, only after you explicitly connect them:

* Google Search Console API, Google Analytics Data API, and PageSpeed Insights API — to fetch your own site's performance data (Google's terms: https://policies.google.com/terms)
* Your chosen AI provider (OpenAI, Anthropic Claude, or Google Gemini) — to generate explanations and recommendations from aggregated metrics you choose to analyze
* The SEO Director license server — for license activation and update delivery

No data is sent anywhere until you connect a service. Disconnecting stops all traffic to that service.

== Installation ==

1. Upload the plugin and activate it.
2. Open "SEO Director" in the admin menu and follow the setup wizard.

== Changelog ==

= 0.8.0 =
* Onboarding: a fresh install now shows a clearly-labelled sample dashboard (health, traffic, opportunities, risks) instead of empty panels, so buyers see the plugin's value immediately. Sample data disappears automatically after the first real sync.
* Setup checklist on the dashboard tracks connecting Google, choosing a property, adding an AI provider, and the first sync.
* New demo_mode setting (auto | off) to control the sample-data experience.

= 0.7.0 =
* Enterprise: brand-voice profiles (tone, audience, style notes, words to avoid) injected into every AI generation, so explanations and copy match your house style.
* Enterprise: SLA alerting — critical/high alerts left open past a configurable threshold escalate once to all channels.
* Enterprise: task sync — push roadmap tasks to Jira or Trello with one click; idempotent so re-syncing never duplicates.
* Enterprise: SERP-enriched root cause — an optional SerpApi lookup adds SERP-feature causes (AI overview, ads, snippets) to declining-query analysis.
* New Enterprise settings panel and a per-feature upsell for locked capabilities.

= 0.6.0 =
* Lite build: a free WordPress.org edition (GSC overview + health score only), gated by the SDA_LITE build flag, with an in-dashboard upgrade funnel to the paid plans.
* Upgrade funnel: locked features now show a clear upsell in place of the screen, plus an edition badge with an upgrade link in the toolbar.
* Performance: the admin app is code-split per screen, cutting the initial bundle by ~20% — the rest load on navigation.
* i18n: full translation template at languages/seo-director-ai.pot, regenerated with bin/make-pot.php.
* Hardening: settings API secret redaction, "silence is golden" directory stubs, client-role cleanup on uninstall, and a documented security review (docs/08-security-hardening.md).

= 0.5.0 =
* Agency Hub: pair client sites over HMAC-signed snapshot push, with a sites grid showing each client's health, 28-day traffic, and active alerts at a glance. Pairing keys are shown once and stored encrypted.
* Client mode: a daily job pushes a compact, sanitized snapshot to the configured hub; no-op unless a hub URL and pairing key are set.
* White-label engine: brand name, logo, primary color, "powered by" toggle, and terminology overrides for reselling agencies (Agency license).
* Client read-only role (SEO Client) for giving clients dashboard/report access without settings control.
* Security: settings API no longer echoes secret fields (pairing key, license secret) — only a "which secrets are set" flag; snapshot ingest is replay-protected and whitelisted.

= 0.4.0 =
* Licensing engine: self-service activation/deactivation, editions (Starter/Pro/Agency/Enterprise), FeatureGate, 14-day grace period, and 21-day server-outage tolerance — Pro features pause after grace but data always stays readable.
* Full opportunity set: featured-snippet, FAQ, and schema detectors plus keyword cannibalization and internal-link ratio analysis (Pro).
* Alert channels: webhook, Slack, and Telegram delivery alongside email (Pro), with SSRF-validated webhook targets.
* Content Strategist: AI meta title/description generation with SERP pixel sizing, and content gap analysis (Pro).
* Automated reports: HTML and CSV renderers, weekly/monthly/quarterly builder, scheduled delivery, and a downloadable report log.

= 0.3.0 =
* AI layer: pluggable OpenAI/Claude/Gemini providers with fallback chain, token budget, evidence-hash cache, and strict JSON schema validation.
* Root-cause engine (deterministic candidates + AI ranking with confidence), on-demand explanations, roadmap generator, and AI weekly summaries.

= 0.2.0 =
* Deterministic intelligence: trend/changepoint analyzers, SEO Health Score, Winners/Losers, opportunity detectors, and the Alert Center.

= 0.1.0 =
* Phase 0 foundation: plugin core, database schema, job framework, REST API scaffold, React dashboard shell.
