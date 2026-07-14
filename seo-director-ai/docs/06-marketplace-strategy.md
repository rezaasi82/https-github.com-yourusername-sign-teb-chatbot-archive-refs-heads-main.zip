# SEO Director AI — Marketplace Strategy & Approval Readiness

**Doc:** 6 of 7 · Spec v1.0.0

---

## 1. Target Marketplaces & Order of Attack

| # | Marketplace | Why / angle | Localization |
|---|---|---|---|
| 1 | **CodeCanyon (Envato)** | largest premium-plugin buyer pool; category "SEO" is crowded with checkers, empty of *directors* — differentiation is easy to demo | EN listing, EN/FA/AR product |
| 2 | **Zhaket** | Iranian WP market leader; Persian-first AI SEO product with Jalali-aware UI has effectively no direct competitor | full FA listing, IRR pricing, Persian support SLA |
| 3 | **RTL Theme** | second Iranian channel; same package as Zhaket | FA |
| 4 | **ThemeForest** | bundle angle: offered to theme authors as a recommended companion (not a standalone theme listing) | EN |
| 5 | **Own store** (Freemius/EDD or license server + portal) | full-margin channel, SaaS upsell path, EU VAT handled by MoR if Freemius | EN/FA/AR |

A **free lite version on WordPress.org** (GSC overview + health score only, no AI) is the top-of-funnel: rankings for "search console dashboard" searches, upgrade path in-product. Lite ships after the paid launch stabilizes (Phase 7 of roadmap).

## 2. Approval-Readiness Checklists

### 2.1 Envato (CodeCanyon/ThemeForest) hard requirements

- WPCS-clean (PHPCS `WordPress` ruleset zero errors), no PHP notices at `WP_DEBUG`, tested up to current WP.
- All output escaped, all input sanitized, nonces + capability checks on every state change (their #1 soft-reject).
- Prefix *everything* (`sda_` / `SEODirector\` / `sda-`), no generic function names, no `eval`, no obfuscated code.
- Bundled libs licensed GPL-compatible & listed; **no CDN-loaded assets**; composer deps scoped (PHP-Scoper) to survive their conflict checks.
- Complete documentation (HTML/PDF), demo video, realistic screenshots (from real demo data, not mockups).
- External-service disclosure: Google APIs, chosen AI provider, license server — all documented in readme + first-run consent screen (also a wp.org rule for the lite version).
- Support policy page + item support entitlement wired to purchase code.

### 2.2 Zhaket / RTL Theme

- Fully translated FA UI (not machine-translated), RTL-perfect screens (their reviewers test this hard), Jalali date display.
- Local docs + video in Persian; support in Persian within their ticket SLA.
- Works with Iranian hosting constraints: all Google/AI traffic goes through server-side calls with configurable proxy support (`WP_PROXY_*` honored) — document this.

### 2.3 Security/quality release gate (all channels)

PHPStan level 6+, PHPCS clean, PHPUnit green (Analysis layer ≥90% coverage), OWASP top-10 self-audit sheet, `plugin-check` (WP.org tool) clean, fresh-install + upgrade-path test matrix (PHP 8.2/8.3 × WP current/previous, single + multisite).

## 3. Listing & Launch Plan

- **Demo instance** with seeded realistic data (anonymized), reviewer login — reviewers and buyers must *see* Winners/Losers + root-cause screens without waiting for OAuth + backfill. (The seeded "demo mode" doubles as the in-product preview before connection.)
- Launch content: 3-minute director's-tour video, comparison page vs "dashboard plugins" (Site Kit, Rank Math Analytics) focused on *explains vs shows*, launch-window lifetime promo.
- Review-velocity tactic: post-purchase day-7 in-product NPS → happy users routed to marketplace review, unhappy routed to support.
- Update cadence promise: monthly minor releases (marketplace algorithms reward recency); public changelog + roadmap page.

## 4. Support & Docs Plan

- Docs site (EN/FA) generated from `/docs`: getting started, each module, troubleshooting OAuth, AI-provider setup guides, agency guide.
- In-product help: contextual "?" popovers per screen backed by the same docs; connection health screen turns the top 10 support tickets into self-service diagnostics (token expired, quota exceeded, cron not firing, Action Scheduler backlog).
