# SLM — DevOps, Operations & Disaster Recovery

## Topology (single-host start, scale-ready)

```
Internet ──► Nginx (TLS, Let's Encrypt) ──► PHP-FPM (app)
                                        ├──► Horizon (queue workers)
                                        ├──► Scheduler (cron loop)
                                        ├──► MySQL 8
                                        └──► Redis 7 (cache, quotas, rate limits, queues)
Release artifacts / invoices ──► S3-compatible bucket (Arvan Cloud / Liara or AWS)
```

`devops/docker-compose.yml` runs the full stack locally and on a single Ubuntu host.
TLS: certbot with the `--nginx` plugin, auto-renew via systemd timer.

## CI/CD

Pipeline (`devops/ci/github-actions-ci.yml`): Pint (style) → PHPStan level 8 → Pest
(against real MySQL + Redis services) → build image → zero-downtime deploy
(new containers, health check `/up`, swap upstream, drain old).

Migrations run with `php artisan migrate --force` during deploy; every migration must be
backwards-compatible with the previous release (expand → migrate → contract pattern).

## Backups

| What | How | Frequency | Retention |
|---|---|---|---|
| MySQL | `mysqldump --single-transaction` → encrypted → S3 | hourly binlog ship + nightly full | 30 days, monthly for 12 months |
| Redis | AOF on disk (quota counters are reconstructible from MySQL) | continuous | n/a |
| Release artifacts | S3 versioned bucket, cross-region replication | on upload | indefinite |
| .env / secrets | encrypted in secret manager (SOPS + age), never in images | on change | full history |

**Restore drill: quarterly.** A backup that has never been restored is not a backup.

## Monitoring & Logging

- **Uptime/health:** `/up` endpoint probed externally (UptimeRobot / Better Stack) from
  both inside and outside Iran — the licensing endpoint must be reachable from both markets.
- **APM & errors:** Laravel Telescope (staging), Sentry (production).
- **Metrics:** Horizon dashboard for queues; MySQL slow-query log; Redis INFO scraped to
  Prometheus + Grafana (validate p95 latency, quota-check latency, queue depth).
- **Logs:** JSON to stdout → Docker log driver → Loki. Security channel (`security` log)
  captures signature failures, rate-limit hits, suspicious-activity flags; alert on spikes.

## Scalability plan

1. **Phase A (launch):** single 4 vCPU host runs everything. Validate endpoint is a
   single indexed lookup + Redis — thousands of req/min are fine.
2. **Phase B:** split MySQL to managed DB, add a second app node behind the LB;
   sessions/cache/queues already live in Redis so app nodes are stateless.
3. **Phase C:** read replica for reporting, CDN in front of `/download`, dedicated
   worker pool for AI proxying (long-lived requests isolated from licensing traffic).

## Disaster recovery

- **RPO ≤ 1 hour** (binlog shipping), **RTO ≤ 4 hours**.
- Runbook: provision fresh host from infra scripts → restore latest full + binlogs →
  point DNS (TTL 300s) → smoke-test `/v1/plugin/validate` with a canary license.
- SDK's offline-grace window (3 days) means customer sites keep working during any
  realistic recovery; communicate status via the announcements channel.

## Sanctions-resilience notes (Iran market)

- Host the licensing server on infrastructure reachable from Iran without a VPN
  (e.g. Arvan/Liara edge, or EU host with clean routing) — test from Iranian ISPs.
- Never depend on Google/AWS-only endpoints in the critical validate path.
- Stripe webhooks (UAE/GCC) and Zarinpal callbacks (Iran) terminate on separate
  routes so regional network issues can't block each other.
