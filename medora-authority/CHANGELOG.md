# Changelog

All notable changes to Medora Authority are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — 2026-08-02

First cut of the platform. The architecture, data layer, published artefacts and
scoring engine are complete and working; a few modules ship as functional but
minimal implementations, listed explicitly below.

### Added — foundation

- PSR-4 autoloader with an optional Composer classmap; **zero runtime
  dependencies**.
- `Core\Container`: PSR-11-style DI with constructor autowiring, singletons,
  aliases and circular-dependency detection.
- `Module\ModuleRegistry`: two-phase module lifecycle (`register` / `boot`),
  licence and dependency gating, topological ordering, recorded skip reasons.
- Eleven custom tables under `mdra_`, applied through `dbDelta()`; additive
  migrations via `Core\Migrator`.
- Five dedicated capabilities so dashboard access does not imply
  `manage_options`.
- Durable job queue with atomic claiming, exponential back-off, stalled-job
  recovery, and a worker that self-limits on wall time and memory. `wp medora
  queue` drains it on demand.

### Added — knowledge engines

- **Entity Intelligence** — four extractors (taxonomy, author, dictionary,
  heuristic) merged by deterministic uid, with a four-signal salience model and
  a six-component entity authority score.
- **Knowledge Graph** — weighted co-occurrence triples with type-aware predicate
  inference; exports as Schema.org JSON-LD and as a force-layout node set.
- **Semantic Engine** — knowledge density, topic coverage, answer readiness,
  chunk integrity and script-independent structural readability.
- **Vector Engine** — heading-aware sentence-packing chunker, offline
  feature-hashing embeddings (no API key required) plus an OpenAI-compatible
  provider, packed float32 storage and cosine search.
- **AI Prompt Engine** — extractive summaries, canonical answers, fact sheets,
  question packs and a token-budgeted context window, published inline for
  crawlers.

### Added — published artefacts

- **llms.txt Engine** — `/llms.txt` and `/llms-full.txt` generated from live
  content, cached and invalidated on save.
- **AI Sitemap Engine** — entity-annotated sitemaps in XML, JSON and Markdown,
  with priority derived from the authority score rather than post type.
- **Schema Intelligence** — one connected `@graph` per page (Organization,
  WebSite, WebPage, Article, Breadcrumb, FAQ, Entity nodes) with `@id` merging
  and a structural validator.
- **AI Crawler Manager** — 30+ crawler registry with vendor and purpose,
  three-way policy presets plus per-crawler overrides, robots.txt management,
  pseudonymised crawl logging and a coverage report.

### Added — analysis and trust

- **AI Authority Score** — six weighted, re-normalising dimensions; every
  deduction carries points, severity and a specific fix.
- **AI Content Optimizer** — actions re-sorted by points recovered per unit of
  effort.
- **Internal Linking AI** — suggestions from vector similarity and shared
  entities; anchors are restricted to phrases the source page already contains.
- **Citation Engine** — DOI and PubMed resolution via CrossRef/NCBI, evidence-
  level classification, quality scoring, APA/Vancouver/Harvard formatting, and
  citation nodes injected into article schema.
- **E-E-A-T Engine** — eleven author-profile fields on the native user screen,
  scored across all four pillars.
- **Medical Intelligence** — seeded clinical vocabulary with Persian and Arabic
  aliases plus Wikidata identifiers, and a clinical-review meta box.
- **AI Analytics** — cookie-free referral tracking for 20 assistants, with a
  daily-rotating visitor salt.

### Added — product surface

- React 18 + TypeScript dashboard: overview, entities, knowledge graph, content,
  crawlers, analytics and settings, plus a five-step setup wizard.
- Dependency-free SVG force-directed graph and sparklines.
- RTL by construction — logical CSS properties throughout, no mirrored build.
- Editor meta box rendered server-side, useful before any JavaScript runs.
- REST API: six controllers, a public knowledge API with per-IP rate limiting,
  and a single `/overview` call that bootstraps the whole dashboard.
- Licensing with a 14-day grace period, **self-service domain transfer**, and a
  self-hosted update channel.
- White-label framework and SaaS hooks for entitlement, configuration locking
  and external vector indexes.
- Security module: append-only audit log with secret redaction, and an
  eight-check configuration scanner.

### Testing

- PHPUnit unit suite covering text processing, vector maths, chunking, citation
  formatting, schema validation, crawler/referral detection and the container.
  Runs without a WordPress install (103 assertions, all passing).
- GitHub Actions matrix across PHP 8.2/8.3/8.4 running lint, static analysis,
  tests, `tsc` and the production build.

### Known limitations

Stated plainly rather than implied:

- **Vector search is a linear scan.** Fine to ~20k chunks; past that the
  overview endpoint flags it and `medora_vector_search` delegates to an external
  index.
- **Referral analytics report a floor, not a total.** Several assistants strip
  the referrer; the UI says so rather than implying completeness.
- **Crawler detection is user-agent based** and therefore spoofable. It drives
  analytics and content negotiation only; nothing security-relevant depends on
  it. Reverse-DNS verification exists but is not run inline.
- **The medical ontology is a seed, not SNOMED or MeSH.** Roughly 20 entries
  with multilingual aliases, extended through `medora_medical_ontology`.
- **Summaries are extractive, not generative,** by design. See
  `medora_prompt_pack` to substitute a model.
- **The following modules are functional but minimal** relative to the full
  product vision: Content Optimizer (recommendations only, no rewriting),
  Internal Linking (suggestions only, no insertion), Medical Intelligence
  (vocabulary and review metadata; no disease/treatment/drug graph yet).
- **Integration tests are not yet written.** The unit suite deliberately avoids
  WordPress; database-backed repositories and REST routes need a WordPress test
  install to cover properly.
