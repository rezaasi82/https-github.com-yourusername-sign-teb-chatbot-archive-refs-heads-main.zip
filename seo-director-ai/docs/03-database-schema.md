# SEO Director AI — Database Design

**Doc:** 3 of 7 · Spec v1.0.0

All tables use prefix `{$wpdb->prefix}sda_`, InnoDB, `utf8mb4_unicode_520_ci`. Created via `dbDelta()` in `Activator`, migrated by versioned `Upgrader` steps (`sda_db_version` option). Every table carries `site_id BIGINT UNSIGNED NOT NULL DEFAULT 1` for multisite; composite indexes lead with `site_id`.

Design rules:

- **Time-series tables are append-only** rollups; dashboards never aggregate raw rows at request time.
- Dates stored as `DATE`/`DATETIME` in **UTC**; conversion to site timezone (incl. Jalali display for fa_IR) happens in presentation only.
- URLs stored canonicalized (scheme/host stripped, path + normalized query) in `page_path VARCHAR(750)`; full URL reconstruction via site property record. Queries/paths also carry a `..._hash BINARY(16)` (md5) column used in unique keys to keep index size sane.

---

## 1. Connections & Properties

```sql
CREATE TABLE {p}sda_connections (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL DEFAULT 1,
  service       ENUM('gsc','ga4','psi','openai','claude','gemini','gdrive') NOT NULL,
  status        ENUM('connected','error','revoked','expired') NOT NULL DEFAULT 'connected',
  account_label VARCHAR(190) NOT NULL DEFAULT '',      -- e.g. google account email (display)
  credentials   LONGTEXT NOT NULL,                     -- encrypted blob (TokenVault)
  scopes        TEXT NULL,
  last_used_at  DATETIME NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_service (site_id, service)
);

CREATE TABLE {p}sda_properties (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL DEFAULT 1,
  connection_id BIGINT UNSIGNED NOT NULL,
  service       ENUM('gsc','ga4') NOT NULL,
  external_id   VARCHAR(190) NOT NULL,   -- GSC property URI / GA4 property id
  display_name  VARCHAR(190) NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  meta          LONGTEXT NULL,           -- JSON: timezone, currency, domain-property flag…
  PRIMARY KEY (id),
  UNIQUE KEY uq_prop (site_id, service, external_id),
  KEY idx_connection (connection_id)
);
```

## 2. Search Console Facts

```sql
-- Exact site-level daily totals (never sampled/top-N — the anchor series)
CREATE TABLE {p}sda_gsc_daily_totals (
  site_id     BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  date        DATE NOT NULL,
  clicks      INT UNSIGNED NOT NULL DEFAULT 0,
  impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  ctr         DECIMAL(6,4) NOT NULL DEFAULT 0,
  position    DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (property_id, date),
  KEY idx_site_date (site_id, date)
);

-- Top-N query rows per day (default N=5000)
CREATE TABLE {p}sda_gsc_query_daily (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id     BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  date        DATE NOT NULL,
  query_hash  BINARY(16) NOT NULL,
  query       VARCHAR(750) NOT NULL,
  clicks      INT UNSIGNED NOT NULL DEFAULT 0,
  impressions INT UNSIGNED NOT NULL DEFAULT 0,
  ctr         DECIMAL(6,4) NOT NULL DEFAULT 0,
  position    DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_row (property_id, date, query_hash),
  KEY idx_query_time (property_id, query_hash, date),   -- per-keyword history scans
  KEY idx_date_clicks (property_id, date, clicks)       -- top-lists per day
);

-- Top-N page rows per day (default N=2000); same shape for page
CREATE TABLE {p}sda_gsc_page_daily (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id     BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  date        DATE NOT NULL,
  page_hash   BINARY(16) NOT NULL,
  page_path   VARCHAR(750) NOT NULL,
  clicks      INT UNSIGNED NOT NULL DEFAULT 0,
  impressions INT UNSIGNED NOT NULL DEFAULT 0,
  ctr         DECIMAL(6,4) NOT NULL DEFAULT 0,
  position    DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_row (property_id, date, page_hash),
  KEY idx_page_time (property_id, page_hash, date),
  KEY idx_date_clicks (property_id, date, clicks)
);

-- Page×Query join sample (cannibalization + per-page keyword sets), weekly grain
CREATE TABLE {p}sda_gsc_page_query_weekly (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id      BIGINT UNSIGNED NOT NULL,
  property_id  BIGINT UNSIGNED NOT NULL,
  week_start   DATE NOT NULL,
  page_hash    BINARY(16) NOT NULL,
  query_hash   BINARY(16) NOT NULL,
  page_path    VARCHAR(750) NOT NULL,
  query        VARCHAR(750) NOT NULL,
  clicks       INT UNSIGNED NOT NULL DEFAULT 0,
  impressions  INT UNSIGNED NOT NULL DEFAULT 0,
  position     DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_row (property_id, week_start, page_hash, query_hash),
  KEY idx_query (property_id, query_hash, week_start)
);

-- Low-cardinality dimensions kept fully: country & device daily
CREATE TABLE {p}sda_gsc_dimension_daily (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id     BIGINT UNSIGNED NOT NULL,
  property_id BIGINT UNSIGNED NOT NULL,
  date        DATE NOT NULL,
  dim_type    ENUM('country','device','search_appearance') NOT NULL,
  dim_value   VARCHAR(64) NOT NULL,
  clicks      INT UNSIGNED NOT NULL DEFAULT 0,
  impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
  ctr         DECIMAL(6,4) NOT NULL DEFAULT 0,
  position    DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_row (property_id, date, dim_type, dim_value)
);
```

**Rollups:** `{p}sda_gsc_query_weekly` and `{p}sda_gsc_query_monthly` (same columns as daily, `week_start`/`month_start` instead of `date`, plus `best_position DECIMAL(6,2)`), built by `RollupBuilder` after each sync; identical pair for pages. Rollups are what Winners/Losers and comparisons read.

## 3. GA4 Facts

```sql
CREATE TABLE {p}sda_ga4_daily (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id         BIGINT UNSIGNED NOT NULL,
  property_id     BIGINT UNSIGNED NOT NULL,
  date            DATE NOT NULL,
  channel         VARCHAR(64) NOT NULL,       -- session default channel group
  landing_hash    BINARY(16) NOT NULL,
  landing_path    VARCHAR(750) NOT NULL,
  sessions        INT UNSIGNED NOT NULL DEFAULT 0,
  total_users     INT UNSIGNED NOT NULL DEFAULT 0,
  engaged_sessions INT UNSIGNED NOT NULL DEFAULT 0,
  engagement_rate DECIMAL(6,4) NOT NULL DEFAULT 0,
  conversions     DECIMAL(12,2) NOT NULL DEFAULT 0,
  event_count     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_row (property_id, date, channel, landing_hash),
  KEY idx_landing (property_id, landing_hash, date),
  KEY idx_channel_date (property_id, channel, date)
);
-- + {p}sda_ga4_daily_totals (site-level, per channel) mirroring gsc_daily_totals
```

## 4. Core Web Vitals / PSI

```sql
CREATE TABLE {p}sda_psi_audits (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL,
  page_hash     BINARY(16) NOT NULL,
  page_path     VARCHAR(750) NOT NULL,
  strategy      ENUM('mobile','desktop') NOT NULL,
  audited_at    DATETIME NOT NULL,
  perf_score    TINYINT UNSIGNED NULL,         -- 0-100 lab score
  lcp_ms        INT UNSIGNED NULL,  cls  DECIMAL(6,3) NULL,  inp_ms INT UNSIGNED NULL,
  ttfb_ms       INT UNSIGNED NULL,
  field_lcp_ms  INT UNSIGNED NULL,  field_cls DECIMAL(6,3) NULL, field_inp_ms INT UNSIGNED NULL,
  cwv_status    ENUM('good','needs_improvement','poor','unknown') NOT NULL DEFAULT 'unknown',
  opportunities LONGTEXT NULL,                 -- JSON: PSI audit items → mapped WP fixes
  PRIMARY KEY (id),
  KEY idx_page_time (site_id, page_hash, strategy, audited_at)
);
```

## 5. Intelligence Layer

```sql
CREATE TABLE {p}sda_insights (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id         BIGINT UNSIGNED NOT NULL,
  type            ENUM('growth','decline','summary_weekly','summary_monthly',
                       'root_cause','recommendation','content_plan') NOT NULL,
  entity_type     ENUM('site','page','query','query_cluster') NOT NULL,
  entity_hash     BINARY(16) NULL,
  entity_label    VARCHAR(750) NULL,
  period_start    DATE NOT NULL,  period_end DATE NOT NULL,
  evidence        LONGTEXT NOT NULL,       -- JSON evidence packet (what the AI saw)
  evidence_hash   BINARY(16) NOT NULL,     -- cache key
  ai_provider     VARCHAR(32) NULL,  ai_model VARCHAR(64) NULL,
  prompt_version  VARCHAR(20) NULL,
  payload         LONGTEXT NOT NULL,       -- JSON: explanation, causes[{cause,confidence,evidence_refs,fix}], actions[]
  lang            VARCHAR(10) NOT NULL DEFAULT 'en',
  tokens_used     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cache (site_id, type, evidence_hash, lang),
  KEY idx_entity (site_id, entity_type, entity_hash, created_at)
);

CREATE TABLE {p}sda_opportunities (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id        BIGINT UNSIGNED NOT NULL,
  detector       VARCHAR(48) NOT NULL,     -- striking_distance | low_ctr | near_top3 | near_page1 |
                                           -- snippet | faq | schema | video | internal_link | local
  entity_type    ENUM('page','query','pair') NOT NULL,
  entity_hash    BINARY(16) NOT NULL,
  entity_label   VARCHAR(750) NOT NULL,
  secondary_label VARCHAR(750) NULL,       -- e.g. target query for a page opportunity
  score          DECIMAL(8,2) NOT NULL,    -- impact × headroom ÷ difficulty
  est_traffic_gain INT UNSIGNED NULL,
  difficulty     TINYINT UNSIGNED NOT NULL DEFAULT 5,   -- 1-10
  status         ENUM('open','in_roadmap','done','dismissed','stale') NOT NULL DEFAULT 'open',
  data           LONGTEXT NULL,            -- JSON detector-specific evidence
  detected_at    DATETIME NOT NULL,  refreshed_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_opp (site_id, detector, entity_hash),
  KEY idx_status_score (site_id, status, score)
);

CREATE TABLE {p}sda_roadmap_tasks (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL,
  roadmap_scope ENUM('weekly','monthly','quarterly') NOT NULL,
  period_start  DATE NOT NULL,
  title         VARCHAR(300) NOT NULL,
  description   LONGTEXT NULL,
  category      VARCHAR(48) NOT NULL,      -- content | technical | links | cwv | local | schema
  impact        TINYINT UNSIGNED NOT NULL, -- 1-10
  difficulty    TINYINT UNSIGNED NOT NULL, -- 1-10
  est_hours     DECIMAL(5,1) NULL,
  priority      SMALLINT UNSIGNED NOT NULL,
  owner_user_id BIGINT UNSIGNED NULL,
  expected_result VARCHAR(500) NULL,
  status        ENUM('todo','in_progress','done','dismissed') NOT NULL DEFAULT 'todo',
  opportunity_id BIGINT UNSIGNED NULL,     -- provenance
  insight_id     BIGINT UNSIGNED NULL,
  completed_at   DATETIME NULL,
  measured_result LONGTEXT NULL,           -- JSON: post-completion delta (feedback loop)
  created_at     DATETIME NOT NULL,  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_scope (site_id, roadmap_scope, period_start, status),
  KEY idx_owner (site_id, owner_user_id, status)
);

CREATE TABLE {p}sda_alerts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id      BIGINT UNSIGNED NOT NULL,
  rule         VARCHAR(48) NOT NULL,       -- traffic_drop | keyword_loss | ctr_drop | cwv | indexing |
                                           -- coverage | manual_action | ranking_loss | critical_issue
  severity     ENUM('critical','high','medium','low') NOT NULL,
  entity_label VARCHAR(750) NULL,
  message      TEXT NOT NULL,
  fingerprint  BINARY(16) NOT NULL,        -- dedup key (rule+entity+condition)
  status       ENUM('active','acknowledged','snoozed','resolved') NOT NULL DEFAULT 'active',
  snoozed_until DATETIME NULL,
  data         LONGTEXT NULL,
  raised_at    DATETIME NOT NULL,  resolved_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_active (site_id, fingerprint, status),   -- one active alert per condition
  KEY idx_status (site_id, status, severity, raised_at)
);

CREATE TABLE {p}sda_health_scores (
  site_id     BIGINT UNSIGNED NOT NULL,
  date        DATE NOT NULL,
  score       TINYINT UNSIGNED NOT NULL,
  components  LONGTEXT NOT NULL,           -- JSON: each sub-score + weight + inputs
  PRIMARY KEY (site_id, date)
);
```

## 6. Reports, Jobs, Agency, License

```sql
CREATE TABLE {p}sda_reports (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id      BIGINT UNSIGNED NOT NULL,
  type         ENUM('weekly','monthly','quarterly','custom') NOT NULL,
  period_start DATE NOT NULL,  period_end DATE NOT NULL,
  formats      VARCHAR(100) NOT NULL,      -- csv of generated formats: pdf,xlsx,csv,gsheet,gdoc
  storage      LONGTEXT NOT NULL,          -- JSON: file paths (protected uploads dir) / Drive file ids
  recipients   TEXT NULL,
  status       ENUM('queued','generating','sent','failed') NOT NULL,
  created_at   DATETIME NOT NULL,  sent_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_period (site_id, type, period_start)
);

CREATE TABLE {p}sda_job_state (             -- resumable sync cursors + run log
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id     BIGINT UNSIGNED NOT NULL,
  job         VARCHAR(64) NOT NULL,
  cursor      LONGTEXT NULL,                -- JSON: {property, date_from, row_offset…}
  status      ENUM('idle','running','failed','done') NOT NULL DEFAULT 'idle',
  last_run_at DATETIME NULL,  last_error TEXT NULL,  fail_count SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job (site_id, job)
);

CREATE TABLE {p}sda_agency_sites (          -- hub side
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id     BIGINT UNSIGNED NOT NULL,     -- hub blog id
  client_name VARCHAR(190) NOT NULL,
  site_url    VARCHAR(300) NOT NULL,
  pair_key_hash CHAR(64) NOT NULL,          -- sha256 of HMAC pairing secret (never plain)
  status      ENUM('pending','connected','error','paused') NOT NULL DEFAULT 'pending',
  last_seen_at DATETIME NULL,
  snapshot    LONGTEXT NULL,                -- JSON: latest pushed KPIs (health, alerts, trends)
  branding    LONGTEXT NULL,                -- JSON per-client report branding overrides
  created_at  DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_url (site_id, site_url)
);

CREATE TABLE {p}sda_license (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  site_id       BIGINT UNSIGNED NOT NULL DEFAULT 1,
  license_key   VARCHAR(190) NOT NULL,      -- stored encrypted
  edition       ENUM('starter','pro','agency','lifetime','enterprise') NOT NULL,
  status        ENUM('active','expired','grace','invalid','deactivated') NOT NULL,
  domain_hash   CHAR(64) NOT NULL,          -- sha256(home_url) — privacy: no raw domain
  expires_at    DATETIME NULL,
  last_check_at DATETIME NULL,
  server_payload LONGTEXT NULL,             -- last HMAC-verified response (cached grace source)
  PRIMARY KEY (id),
  UNIQUE KEY uq_site (site_id)
);
```

## 7. Retention & Volume Plan

| Table family | Grain | Retention (default) | Est. volume @ 1 property, N=5000 |
|---|---|---|---|
| gsc_query_daily / page_daily | day | 16 months, then pruned (rollups keep history) | ≤ ~3.4 M rows (~700 MB worst case) |
| gsc_*_weekly/monthly rollups | week/month | forever | small |
| daily_totals / dimension_daily / ga4_daily | day | forever (tiny) | small |
| psi_audits | per audit | 24 months | small |
| insights | per insight | forever (user content) | small |

Retention configurable; pruning runs weekly in a low-priority job. All row-count ceilings enforced by the sync layer, so a huge site cannot balloon the DB unexpectedly. `ANALYZE TABLE` after backfill; backfill writes use multi-row inserts in 500-row batches.
