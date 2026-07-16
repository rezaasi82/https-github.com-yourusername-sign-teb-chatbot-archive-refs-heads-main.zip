# SEO Director AI — Security Hardening Notes (Phase 6)

A record of the security posture verified during the Phase 6 hardening pass.
Read alongside `02-technical-architecture.md` §7.

## REST surface

- Every route declares a `permission_callback`. Cookie-authenticated SPA calls
  additionally carry the WP REST nonce (`X-WP-Nonce`).
- Two routes are intentionally public and authenticate by other means:
  - `GET /connections/google/callback` — the browser is redirected here by
    Google; the OAuth **state transient** is the authenticator (CSRF guard).
  - `POST /hub/ingest` — authenticated by **HMAC signature** over
    `{timestamp}.{body}`, with a 5-minute freshness window (replay protection),
    a constant-time compare, and per-source throttling.
- Write routes validate and sanitize every argument (`sanitize_callback`,
  `enum`, typed args). Expensive/AI routes are rate-limited.

## Secrets

- OAuth tokens, AI keys, license keys, and agency pairing keys are stored
  encrypted via `TokenVault` (libsodium/OpenSSL, key derived from WP salts).
- The settings API never echoes secret fields (agency pairing key, license
  shared secret). It returns only a `secrets_set` boolean map; updates re-read
  through the same redaction path.
- The agency pairing key is shown to the hub admin exactly once at pair time;
  the hub keeps only a sha256 fingerprint plus the encrypted key.

## Outbound requests

- All outbound HTTP goes through `RetryingHttpClient` (`wp_remote_*`), which
  enforces timeouts and bounded retries.
- Webhook alert targets are SSRF-validated before dispatch.

## Data & filesystem

- Every table is keyed by `site_id` (blog id) for multisite isolation.
- Report artifacts are streamed with an explicit content type and
  `Content-Disposition`; only whitelisted formats resolve to a path.
- Uninstall is opt-in (`delete_data_on_uninstall`); it drops tables, options,
  and the custom `sda_client` role only when the admin enabled deletion.
- "Silence is golden" `index.php` stubs ship in every code/asset directory as
  defense-in-depth against directory listing on misconfigured servers.

## Capabilities & roles

- Custom caps: `manage_sda`, `view_sda_reports`, `manage_sda_clients`.
- The agency `sda_client` role is read-only (dashboard/report viewing only),
  created on activation and removed on deactivation/uninstall.

## Internationalization

- All user-facing strings use the `seo-director-ai` text domain via the WP
  translation functions; `languages/seo-director-ai.pot` is regenerated with
  `bin/make-pot.php`.
