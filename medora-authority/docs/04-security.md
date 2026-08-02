# Security

## Threat model

Medora is an admin-facing plugin that also publishes a deliberately public,
unauthenticated knowledge API. The realistic threats are therefore:

1. **Privilege escalation** — a subscriber or editor reaching settings or the
   audit log.
2. **SQL injection** — the platform writes a lot of custom SQL against custom
   tables.
3. **Stored XSS** — entity names, descriptions and citation titles come from
   third-party APIs and from user input, and are rendered in the admin.
4. **Data exposure** — the public API must never leak drafts, private posts or
   secrets.
5. **Scraping and abuse** — the public API is the cheapest way to exfiltrate a
   site's entire knowledge graph.
6. **Privacy** — crawl and referral logging touches visitor data.

## Controls

### Authorisation

Medora ships five of its own capabilities rather than reusing `manage_options`,
so an agency can grant an editor access to the authority dashboard without
granting them the whole site:

| Capability | Granted to | Gates |
|---|---|---|
| `medora_view_dashboard` | administrator, editor | all read routes |
| `medora_run_analysis` | administrator, editor | analysis, citations |
| `medora_manage_entities` | administrator, editor | entity edit/delete |
| `medora_manage_settings` | administrator | settings, modules, crawlers, licence |
| `medora_view_audit_log` | administrator | audit trail |

Every REST route declares an explicit `permission_callback`. Nothing falls back
to `is_user_logged_in()` or `__return_true` except the four public read routes,
which use a named `isPublic()` method so the intent is greppable and one filter
closes them all.

Meta box and profile writes verify a nonce **and** re-check `edit_post` /
`edit_user`. A nonce proves intent, not authority; both are required.

### SQL injection

Every query is a prepared statement. Table names cannot be bound as parameters,
so they are always produced by `Tables::name()` from a class constant — never
from request input. The one place a fragment is interpolated is the `ORDER BY`
in `EntityRepository::query()`, which maps request input through a `match`
whitelist before it reaches SQL.

`WHERE` clauses are assembled from constant strings with `%s`/`%d`/`%f`
placeholders and the values passed separately to `$wpdb->prepare()`.

### Output escaping

All PHP-rendered admin output uses `esc_html()`, `esc_attr()`, `esc_url()` or
`esc_textarea()`. Four places deliberately emit unescaped output, each annotated
with a `phpcs:ignore` and a reason:

- JSON-LD in `wp_head` — already safe JSON from `wp_json_encode()`; escaping
  would corrupt it.
- The prompt-pack `<script type="application/medora+json">` block — same.
- `llms.txt` — raw Markdown by definition.
- Sitemaps — pre-escaped per node by the renderer.

The React dashboard renders everything through JSX text nodes; there is no
`dangerouslySetInnerHTML` anywhere in `src/`.

### Data exposure

`AbstractController::resolvePost()` is the single gate for post access. Public
routes pass `publicOnly: true`, which rejects anything not published outright.
Dashboard routes fall back to `current_user_can( 'read_post' )`, so an editor
sees their own drafts and nobody else's.

`GET /settings` strips `embedding_api_key`, `install_hash` and the licence block
before responding. The licence key is only ever returned masked.

### Secrets

The embedding API key is read in this order: `MEDORA_EMBEDDING_API_KEY`
environment variable, then the constant, then the database. The security scanner
flags the database case, because a key in `wp_options` ends up in every backup
and every options export.

The audit log redacts any context key matching `key`, `token`, `secret`,
`password` or `api_key` before writing — audit context is assembled from request
payloads, so without this a settings update would persist a key in plain text.

### Abuse

Anonymous `GET` requests to `medora/v1` are limited to 120 per minute per IP,
counted in a transient keyed by IP and clock minute. Logged-in users are exempt
because the dashboard legitimately makes many requests. The limit is filterable,
and the whole public surface can be disabled.

Crawler blocking returns `403` with `X-Robots-Tag: noindex, nofollow` rather
than `404`, so the operator can tell a deliberate block from a broken URL.

### Privacy

Medora stores **no raw IP addresses and no raw user agents**, anywhere.

- `Support\Hash` produces salted HMAC-SHA256 digests using a per-install salt
  combined with `wp_salt('nonce')`. Hashes are not comparable across sites, and
  a leaked table is not a rainbow-table target.
- The analytics visitor hash additionally mixes in the current UTC date, so the
  salt rotates every 24 hours. That bounds trackability to under a day and is
  why the module needs no consent banner — it cannot build a longitudinal
  profile even in principle.
- Retention is bounded and configurable (default 180 days). A daily cron prunes
  crawl hits, referrals and audit rows past their window.
- Crawler detection is user-agent based and is treated as a **hint, not
  authentication**. User agents are trivially spoofed, so nothing security
  relevant is gated on it — it drives analytics and content negotiation only.
  Access control that must hold up is enforced through robots directives and,
  optionally, forward-confirmed reverse DNS.

### Supply chain

The plugin has **zero runtime PHP dependencies**. Composer is used only for dev
tooling. A bundled DI or HTTP library would collide with other plugins bundling
different versions of the same package — a genuine and common source of fatal
errors in the WordPress ecosystem.

Outbound HTTP is limited to three destinations, all through `wp_remote_*` (which
honours a site's proxy and blocking constants): the licence server, the
configured embedding endpoint, and CrossRef/PubMed for citation resolution.

### Uninstall

`uninstall.php` drops no data unless the operator opts in via the
`medora_delete_data_on_uninstall` option or the `MEDORA_REMOVE_ALL_DATA`
constant. Cron events and transients are always cleaned up.

## Built-in configuration scanner

`GET /security/scan` audits the deployment and reports pass/fail with a fix:

| Check | Severity | Why |
|---|---|---|
| HTTPS | high | several AI crawlers skip plain-HTTP origins |
| API key not in database | high | keeps the secret out of backups |
| No physical `robots.txt` | critical | a real file silently voids the crawler policy |
| Search engine visibility on | critical | otherwise nothing is indexable |
| PHP ≥ 8.2 | critical | supported runtime |
| `WP_DEBUG_DISPLAY` off | high | errors leak internals into indexed output |
| `DISALLOW_FILE_EDIT` set | medium | hardening |
| Retention bounded | low | prevents unbounded log growth |

Extend it with `medora_security_checks`.

## Reporting

Report suspected vulnerabilities privately to `security@medora.ai`. Please do
not open a public issue.
