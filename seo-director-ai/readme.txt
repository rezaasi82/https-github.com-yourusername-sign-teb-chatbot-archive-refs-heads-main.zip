=== SEO Director AI ===
Contributors: seodirector
Tags: seo, search console, analytics, ai, reports
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO management platform: monitors, analyzes, prioritizes, explains, and recommends — your virtual SEO Director inside WordPress.

== Description ==

Most SEO plugins answer "what are my numbers?". SEO Director AI answers "what happened, why, and what should I do next — in what order?".

* **Executive dashboard** — SEO health score, clicks/impressions/CTR/position trends, top pages, all served from local rollup tables (never a live API call in a web request).
* **Search Console intelligence** — daily sync with restatement window, exact site totals plus top-N query/page detail.
* **Deterministic analysis** — trend slopes, week-over-week / month-over-month changes, changepoint detection, opportunity detectors (striking distance, low CTR).
* **AI insights (optional, BYO key)** — Claude / OpenAI / Gemini explain causes and generate plans. Deterministic first, AI second: the AI never invents metrics. Strict JSON-schema validation, monthly token budget, evidence-hash caching.
* **Zero frontend footprint** — nothing is enqueued on the public site, ever.
* **Security hardened** — encrypted token vault (libsodium/AES-256-GCM), PKCE OAuth, capability + nonce checks on every REST route, prepared statements everywhere.
* **Multisite ready, RTL ready, translation ready.**

== Installation ==

1. Upload the plugin and activate it.
2. Open **SEO Director → Settings** and set your Google OAuth Client ID.
3. Connect Google Search Console under **Connections** and run a sync.
4. (Optional) Add an AI provider key to enable explanations and roadmaps.

== Changelog ==

= 1.0.0 =
* Initial release: foundation, Search Console sync, health score, opportunity detection, AI provider layer, admin dashboard.
