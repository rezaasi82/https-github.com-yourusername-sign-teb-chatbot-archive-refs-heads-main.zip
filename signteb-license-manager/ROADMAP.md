# SignTeb License Manager (SLM) — Product Roadmap

> The complete SaaS licensing, subscription, update-delivery and API platform for all
> SignTeb products (MEDORA AI, SignBot, SignTeb SEO Dashboard, QRCODR, and every future
> product). Fully owned and branded by SignTeb — functionally comparable to Freemius /
> EDD Software Licensing / WP Rocket licensing, purpose-built for the Iran + UAE + GCC
> medical-technology market.

---

## Guiding Principles

1. **Product-agnostic core.** The licensing engine knows nothing about MEDORA or SignBot
   specifically — every product is a row in the `products` table. Unlimited future
   products require zero code changes.
2. **Clean Architecture / DDD.** Business logic lives in `app/Domain/*` bounded contexts
   (Licensing, Catalog, Billing, Customers, Updates, Usage, Support). Controllers are
   thin; Eloquent models carry no business rules beyond persistence concerns.
3. **Offline-tolerant clients.** Plugins cache signed validation responses; a licensing
   server outage must never hard-lock a paying doctor's website. Grace-period logic is a
   first-class state machine, not an afterthought.
4. **Sanctions-aware payments.** Zarinpal + NextPay for Iran, Stripe for UAE/GCC, plus
   manual invoice / bank transfer. Payment gateway is an interface; region routing is
   configuration.
5. **Tri-lingual, RTL-first.** Persian, Arabic and English are all first-class in the
   customer portal and transactional messages. Admin (Filament) ships English + Persian.
6. **Self-service everything.** Domain transfer, deactivation, invoice download, and
   renewals never require a support ticket.

---

## Phase Plan (12 weeks to GA)

### Phase 0 — Foundation (Week 1)
- Repository, Docker environment (PHP 8.3 / Laravel 12, MySQL 8, Redis, Nginx), CI pipeline.
- Base Laravel skeleton, code-style (Pint), static analysis (PHPStan level 8), test harness (Pest).
- Domain folder structure, config (`config/slm.php`), environments (.env matrix: local / staging / prod).
- **Exit criteria:** `docker compose up` serves a health endpoint; CI green on an empty test suite.

### Phase 1 — Catalog & Customers (Week 2)
- Module 2 (Products) and Module 1 (Customers): migrations, models, Filament resources.
- Customer types: individual doctor, clinic, hospital, company, startup — via `customer_type`.
- Product versions table with release channel (stable/beta) and semver ordering.
- **Exit criteria:** admin can CRUD products, versions and customers in Filament.

### Phase 2 — Licensing Core (Weeks 3–4) ← *the heart of the platform*
- Module 3: license key generation (`MED-XXXX-XXXX-XXXX` format, product-prefixed,
  cryptographically random, checksum digit), full lifecycle state machine
  (pending → active → expired / suspended / revoked), domain locking, activation limits,
  IP + device fingerprint tracking, transfer flow, immutable audit log (`license_events`).
- Public plugin API: `/activate`, `/validate`, `/deactivate` with HMAC-signed responses.
- Rate limiting + replay protection on all public endpoints.
- **Exit criteria:** the WordPress SDK (Phase 6) contract is fully served; 100% test
  coverage on the state machine and signer.

### Phase 3 — Subscriptions & Billing (Weeks 5–6)
- Module 4: plans (Starter / Professional / Clinic / Agency / Enterprise), monthly +
  yearly cycles, trials, grace periods, auto-renewal, cancellation.
- Module 10: `PaymentGatewayInterface` with Zarinpal, NextPay, Stripe, manual-invoice
  drivers; recurring charge scheduler (queue + cron); invoice generation (PDF, tri-lingual).
- Webhook ingestion per gateway with signature verification.
- **Exit criteria:** an end-to-end paid subscription creates a license automatically and
  renews it on schedule; failed payment moves license into grace state.

### Phase 4 — Update Delivery (Week 7)
- Module 7: `/check-update` + signed, expiring `/download` URLs, version-gated by license
  status; release notes; rollback (previous versions remain downloadable); package
  checksum (SHA-256) in every update manifest.
- S3-compatible storage driver for release artifacts.
- **Exit criteria:** a WordPress site with a valid license sees and installs an update
  through the standard WP updater UI via the SDK.

### Phase 5 — Portals (Weeks 8–9)
- Module 6: Filament admin — KPI dashboard (MRR, ARR, churn, active licenses, revenue,
  growth), license/customer/payment/ticket management, product release publishing.
- Module 5: customer portal (Laravel + Vue 3 + Tailwind, RTL, dark mode, fa/en/ar) —
  licenses, downloads, activations/domain management, renewals, invoices, tickets.
- **Exit criteria:** a customer can self-serve the full lifecycle without admin help.

### Phase 6 — WordPress SDK (Week 9, parallel)
- Reusable drop-in SDK for all SignTeb plugins: `activate_license()`, `validate_license()`,
  `deactivate_license()`, `check_updates()`, `send_usage_data()`; response-signature
  verification, cached validation with TTL + grace window, WP-admin license page partial.
- **Exit criteria:** MEDORA AI and SignBot integrate with < 20 lines of glue code each.

### Phase 7 — MEDORA AI Service APIs (Week 10)
- Module 8: authenticated AI proxy endpoints — chat requests, lead scoring, chat
  summaries, PDF export, Google Sheets / CRM sync hooks; per-license token quotas and
  monthly limits enforced in Redis; usage metering feeding billing.
- **Exit criteria:** MEDORA plugin performs AI calls through SLM with quota enforcement.

### Phase 8 — Support, Reporting & Hardening (Weeks 11–12)
- Module 11: ticketing, knowledge base, announcements, customer notifications.
- Module 12: revenue / license / activation / product / growth / customer reports with
  Excel, CSV and PDF export.
- Module 9 hardening pass: security audit, anti-tamper review, suspicious-activity
  detection rules, penetration test, load test (target: 1,000 validate req/s sustained).
- Backup + disaster-recovery drills; monitoring dashboards; go-live runbook.
- **Exit criteria:** GA launch checklist signed off.

---

## Module → Phase Matrix

| # | Module                  | Phase | Status |
|---|-------------------------|-------|--------|
| 1 | Customer Management     | 1     | scaffolded |
| 2 | Product Management      | 1     | scaffolded |
| 3 | License Management      | 2     | scaffolded (core engine in this repo) |
| 4 | Subscription System     | 3     | scaffolded |
| 5 | Customer Dashboard      | 5     | planned |
| 6 | Admin Dashboard         | 5     | planned |
| 7 | Auto Update System      | 4     | scaffolded |
| 8 | MEDORA AI Integration   | 7     | API contract defined |
| 9 | Security Layer          | all   | cross-cutting; signer + middleware scaffolded |
| 10| Payment System          | 3     | interface + drivers scaffolded |
| 11| Support System          | 8     | schema defined |
| 12| Reporting               | 8     | planned |

---

## KPIs tracked from day one

MRR · ARR · churn rate · trial→paid conversion · active licenses · activations per
license · validate-API p95 latency · failed-payment recovery rate · ticket first-response
time.

## Deliverables in this repository

| Path | Content |
|------|---------|
| `docs/ARCHITECTURE.md` | System architecture, bounded contexts, security model |
| `docs/DATABASE.md` | ERD (Mermaid) + schema rationale and indexing strategy |
| `docs/DEVOPS.md` | Deployment, CI/CD, backup, monitoring, DR, scaling |
| `docs/api/openapi.yaml` | Complete OpenAPI 3.1 specification |
| `backend/` | Laravel 12 application scaffold (domain code, migrations, API) |
| `sdk/wordpress/` | Reusable WordPress SDK for all SignTeb plugins |
| `devops/` | Dockerfile, docker-compose, Nginx config, CI pipeline |
