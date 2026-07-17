# SLM — System Architecture

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.3) |
| Database | MySQL 8+ (utf8mb4) |
| Cache / quotas / rate limits | Redis 7 |
| Queue | Laravel Queue (Redis driver), Horizon |
| Admin UI | FilamentPHP 3 |
| Customer portal | Laravel + Vue 3 + Tailwind CSS (RTL, dark mode, fa/en/ar) |
| Auth | JWT (plugin/API clients) + session (portals) |
| Storage | Local + S3-compatible (release artifacts, invoices) |
| Deployment | Docker → Ubuntu + Nginx + Let's Encrypt |

## Bounded Contexts (`backend/app/Domain/*`)

```
Domain/
├── Catalog/        products, versions, releases, plans
├── Customers/      customers (doctor/clinic/hospital/company), contacts, activity log
├── Licensing/      ★ license lifecycle, keys, activations, domain lock, transfer, audit
├── Billing/        subscriptions, invoices, payments, gateways (Zarinpal/NextPay/Stripe/manual)
├── Updates/        update manifests, signed downloads, rollback
├── Usage/          MEDORA AI metering, token quotas, monthly limits
└── Support/        tickets, knowledge base, announcements
```

Rules:
- Contexts communicate through **domain events** (`LicenseActivated`, `PaymentSucceeded`,
  `SubscriptionRenewed`, `QuotaExceeded`) dispatched on Laravel's event bus; cross-context
  writes happen only in listeners, never inline.
- Controllers (HTTP) → Actions/Services (Domain) → Repositories/Eloquent (Persistence).
  No business logic in controllers or models.
- Every state change on a license writes an immutable row to `license_events` (audit log).

## License Lifecycle State Machine

```
              ┌──────────┐  payment ok   ┌─────────┐
   create ───►│ pending  ├──────────────►│ active  │◄─────────────┐
              └──────────┘               └────┬────┘              │ renew
                                              │ expiry date       │
                       admin action           ▼                   │
   ┌───────────┐  ◄──────────────  ┌──────────────────┐    ┌──────┴─────┐
   │ suspended │                   │ grace (soft-lock) ├───►│  expired   │
   └───────────┘                   └──────────────────┘    └────────────┘
        any state ── fraud/refund ──► revoked (terminal)
```

- **grace**: validation still returns `valid=true` with `grace=true`; SDK shows renewal
  notice. Length is plan-configurable (default 14 days).
- **expired / suspended / revoked**: `/validate` returns `valid=false` with a reason
  code; customer data is never deleted.

## Security Model (Module 9)

1. **Response signing** — every `/activate`, `/validate`, `/check-update` response body is
   HMAC-SHA256 signed with a per-product secret; the SDK verifies before trusting. This
   defeats local `hosts`-file spoofing of the license server (the standard nulled-plugin
   attack).
2. **JWT** for authenticated customer/admin API access; short-lived access tokens +
   rotating refresh tokens.
3. **Request signing for plugin calls** — SDK sends `X-SLM-Timestamp` + `X-SLM-Signature`
   (HMAC over method|path|timestamp|body with the license key as key material); server
   rejects skew > 5 min → replay protection.
4. **Rate limiting** — Redis-backed: per-IP on public endpoints, per-license on
   validate/usage endpoints; exponential backoff hints in `Retry-After`.
5. **Suspicious-activity detection** — queue jobs flag: activations from > N distinct IPs
   per day, impossible-travel activation pairs, checksum-invalid keys brute force,
   validate storms. Flags surface in Filament + security log channel.
6. Standard Laravel protections: CSRF (portals), prepared statements everywhere, strict
   validation FormRequests, XSS-escaped Blade/Vue output, encrypted secrets at rest
   (`encrypted` cast for gateway credentials and product signing secrets).

## Multi-region Payments

`PaymentGatewayInterface` (create → redirect/intents → verify → webhook). Driver chosen
per invoice currency/region: IRR → Zarinpal/NextPay; AED/USD → Stripe; anything → manual
invoice / bank transfer. Recurring renewals run through a scheduler job that charges
saved mandates (Stripe) or issues renewal invoices + SMS/email dunning (Iran gateways,
which have no native recurring support).

## MEDORA AI Integration (Module 8)

MEDORA plugin never talks to AI providers directly. It calls SLM
(`/api/v1/ai/chat`, `/ai/lead-score`, `/ai/summarize`) with its license key; SLM
authenticates, checks the Redis token bucket (per-license monthly token quota from the
plan), proxies to the AI backend, meters usage into `usage_records`, and returns the
result. Quota exhaustion returns `429 quota_exceeded` with reset date — the plugin
degrades gracefully.

## Update Delivery (Module 7)

`GET /check-update?product=medora-ai&version=1.2.0` → manifest (latest version for the
license's channel, release notes, requirements, SHA-256) only if license is active/grace.
`GET /download` issues a signed, 5-minute-expiring URL to the artifact on S3. Previous
releases stay available → rollback is "install version N-1" in the portal.
