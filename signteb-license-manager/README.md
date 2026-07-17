# SignTeb License Manager (SLM)

The SaaS licensing, subscription, auto-update and API platform for all SignTeb
products — MEDORA AI, SignBot, SignTeb SEO Dashboard, QRCODR, and every future
product. Fully owned and branded by SignTeb; functionally comparable to Freemius /
EDD Software Licensing, built for the Iran + UAE + GCC healthcare software market.

## Repository layout

| Path | What it is |
|------|------------|
| [`ROADMAP.md`](ROADMAP.md) | 12-week phased roadmap mapping all 12 modules to milestones |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Bounded contexts, license state machine, security model |
| [`docs/DATABASE.md`](docs/DATABASE.md) | ERD (Mermaid) + full schema with indexing strategy |
| [`docs/DEVOPS.md`](docs/DEVOPS.md) | Deployment, CI/CD, backups, monitoring, DR, scaling |
| [`docs/api/openapi.yaml`](docs/api/openapi.yaml) | OpenAPI 3.1 spec for the plugin, update and AI APIs |
| [`backend/`](backend/) | Laravel 12 application (Phase 1–2 scaffold: domain code, migrations, public API) |
| [`sdk/wordpress/`](sdk/wordpress/) | Drop-in WordPress SDK for all SignTeb plugins |
| [`devops/`](devops/) | Dockerfile, docker-compose stack, Nginx config, CI pipeline |

## What is implemented in this scaffold

- **License engine (Module 3):** key generation (`MED-XXXX-XXXX-XXXX` with checksum),
  full lifecycle state machine, domain-locked activations with limits and dev-domain
  exemption, self-service transfer with monthly cap, append-only audit log.
- **Public plugin API:** `/activate`, `/validate`, `/deactivate`, `/check-update`,
  `/download` — HMAC-signed responses, rate-limited, signed short-lived download URLs.
- **Database (all modules):** migrations for customers, catalog, billing, licensing,
  usage metering and support, matching `docs/DATABASE.md`.
- **Billing foundation (Module 10):** `PaymentGatewayInterface` + Zarinpal driver with
  server-to-server verification.
- **AI quota service (Module 8):** Redis-backed atomic token buckets per license/month.
- **WordPress SDK:** `activate_license()`, `validate_license()`, `deactivate_license()`,
  `check_updates()` (wired into the native WP updater), `send_usage_data()`, response
  signature verification, offline-grace caching.

## Getting started

```bash
cd signteb-license-manager
cp backend/.env.example backend/.env   # create from Laravel skeleton
docker compose -f devops/docker-compose.yml up -d
docker compose -f devops/docker-compose.yml exec app php artisan migrate --seed
```

Next milestones are tracked in [`ROADMAP.md`](ROADMAP.md) — Phase 3 (subscriptions +
recurring billing) is the next build target.
