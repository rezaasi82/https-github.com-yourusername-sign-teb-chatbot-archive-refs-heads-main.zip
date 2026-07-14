# SEO Director AI — Licensing Architecture & Monetization Strategy

**Doc:** 5 of 7 · Spec v1.0.0

---

## 1. Licensing Architecture

### 1.1 Components

- **License server** (`api.seodirector.app`): standalone service (outside WordPress) with endpoints `POST /v1/activate`, `POST /v1/deactivate`, `POST /v1/check`, `GET /v1/update/{slug}` (signed update packages). All responses **HMAC-signed** (per-license secret) so the plugin can verify authenticity offline-cached.
- **Plugin side** (`License\LicenseManager`): stores encrypted key + last verified payload; daily background check; `FeatureGate::allows('agency_hub')` is the *only* API modules use to check entitlements — no scattered `if ($edition === …)`.
- **Domain binding:** `sha256(home_url)` (privacy: raw domain never required for check calls after activation). Staging/dev auto-exemption: `*.local`, `*.test`, `staging.*`, `dev.*` do not consume an activation slot.
- **Self-service license transfer:** deactivate on old domain from either the old site *or* the customer portal (up to 2 moves/month without support contact) — a deliberate answer to the domain-lock-in complaints common in this market.

### 1.2 Enforcement flow (fail-soft, data-safe)

```
Active ──expiry──► Reminder (email + in-plugin notice, 14 & 3 days before)
   │
Expired (day 0) ──► GRACE (14 days): everything works, dismissible banner
   │
After grace ──► SOFT LOCK: AI insights, alerts delivery, report generation,
   │            and updates pause. Dashboards + already-stored data stay
   │            fully readable. Data is NEVER deleted.
   │
Lifetime licenses: updates forever for purchased major version per marketplace terms.
```

License-server outage ≠ punishment: cached signed payload keeps current status for up to 21 days of unreachability before any state change.

### 1.3 Auto-updates

Update manifest served by license server; packages signed (Ed25519), signature verified before `unzip`; standard `pre_set_site_transient_update_plugins` integration so updates appear natively in wp-admin. On marketplaces with their own delivery (Envato), the license key from the purchase code unlocks the same pipeline.

## 2. Pricing & Editions

| Edition | Sites | Target buyer | Launch price (annual) | Notes |
|---|---|---|---|---|
| **Starter** | 1 | solo owner | $59/yr | full MVP feature set, BYO AI key |
| **Pro** | 5 | freelancer / SMB | $149/yr | all detectors, all report formats, alert channels |
| **Agency** | Unlimited | agencies | $399/yr | hub, white label, client access |
| **Lifetime** (launch-window promo) | per-tier | early adopters | 2.5× annual | capped quantity; marketplace-friendly |
| **Enterprise / SaaS** | — | later phase | custom / MRR | hosted hub, pooled AI keys, SLA |

Regional pricing for Iranian marketplaces (Zhaket / RTL Theme): localized IRR price points set per marketplace norms; **feature parity — no crippled regional build**, and payment/AI-provider defaults adjusted for regional accessibility (BYO keys, provider-agnostic design already covers this).

**AI cost model:** self-hosted plugin = BYO API key (customer pays provider directly; zero marginal AI cost to us). SaaS/Enterprise = pooled keys with metered fair-use — priced into MRR.

## 3. SaaS Expansion Plan (phase after plugin traction)

1. **Phase S1 — Hosted Hub:** the Agency hub as a hosted app (hub no longer needs a WordPress install); client sites keep the plugin as a lightweight data agent. Same signed REST contract → no plugin rewrite.
2. **Phase S2 — Managed AI:** pooled provider keys, per-account token metering, model routing service (removes BYO-key friction; recurring revenue).
3. **Phase S3 — Full SaaS:** onboarding without WordPress (any site via GSC/GA4 OAuth only), plugin becomes optional enhanced agent (internal-link graph, content actions). Custom report domains (CNAME), team seats, roles, audit log.
4. **Architecture guardrail now:** every analyzer and the AI layer live behind interfaces with no WordPress calls inside `Analysis/` — that code lifts into the SaaS backend unchanged.

## 4. Revenue guardrails

- Refund driver #1 in this category is onboarding failure → invest in the wizard, connection health screen, and a "not connected yet" demo dataset mode.
- Renewal driver is the weekly email report → make automated reporting excellent and on by default (opt-out, not opt-in).
- Track (anonymized, opt-in telemetry): activation funnel step completion, weekly-active dashboards, feature-gate hits (which upsell moments fire) — feeds pricing iteration.
