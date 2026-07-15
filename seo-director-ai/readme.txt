=== SEO Director AI ===
Contributors: seodirector
Tags: seo, search console, analytics, ai, core web vitals
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.4.0
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
