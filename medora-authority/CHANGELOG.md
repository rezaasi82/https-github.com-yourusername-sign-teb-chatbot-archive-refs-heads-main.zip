# Changelog

All notable changes to Medora Authority are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] — 2026-08-02

Completes the two modules 0.1.0 shipped as "functional but minimal", without
crossing the line they were minimal to avoid.

### Added — content briefs

The Content Optimizer previously listed problems. It now produces the artefact
a writer can work from.

- `GET /score/{id}/brief` returns a full writing brief: the outline with
  existing and missing sections (as pasteable question-shaped headings, each
  with concrete coverage hints), what the opening has to accomplish, questions
  the page cannot yet answer, entities to introduce, an evidence target, and a
  word-count target derived from the gaps rather than pulled from the air.
- `?format=markdown` renders the same brief for pasting into a ticket.
- Entities to introduce come from the site's **own graph** — concepts that
  co-occur with this page's subject elsewhere on the site — so a brief never
  invents a topic the site knows nothing about.
- Ends with a checklist, unfinished items first. A checklist that opens with
  ticks buries the work.

It still does not rewrite the page. Auto-rewriting published content leaves the
byline accountable for words the author never wrote, which on a health site is
a liability rather than a feature.

### Added — link application

Internal Linking previously only suggested. It will now apply a link, through
the narrowest crossing of that line that is still useful:

- One link per call, target and anchor chosen by a human. There is no
  "apply all".
- The anchor must already exist in the prose — text is never inserted, only
  wrapped. That is what stops the feature becoming the keyword stuffing it
  exists to replace.
- The target must be one the engine actually suggested, otherwise the endpoint
  would be an arbitrary-markup injection primitive wearing a capability check.
- `edit_post` is required, on top of the Medora capability: being able to
  analyse must not imply being able to edit.
- Inserted anchors carry `data-medora-link`, so they are visible in the editor,
  revertible precisely, and audited. `wp_update_post()` leaves a revision, so
  the edit is recoverable even if revert is never called.
- `POST` and `DELETE /links/{id}` expose apply and revert.

`AnchorWrapper` was extracted as a pure, separately tested unit because it is
the only code in the plugin that rewrites customer HTML. Its 20 tests are the
most adversarial in the suite — nesting inside an existing anchor, rewriting an
`alt` attribute, linking inside `code`, matching `colon` inside `colonoscopy`,
and Unicode word boundaries on Persian, all of which the naive `str_replace`
implementation gets wrong on a live page.

### Added — dashboard test suite

`npm run check` previously invoked a Jest run with no tests. There are now real
ones, and CI runs them.

- `src/utils/format.ts` extracted, with tests covering every grade threshold,
  series collapsing, and the guards against `NaN%` and fabricated zero-fill.
- `src/api/client.test.ts` covers URL building, parameter encoding, and the
  thing that matters most: a WordPress REST error keeps its server message
  instead of degrading to "Request failed".

### Fixed

- **Duplicated series logic.** `collapseSeries` existed twice — once in
  `Overview` and once inline in `Analytics` — and the two had already begun to
  differ. Both now use the shared, tested helper.
- **Grade thresholds were duplicated** between `ScoreRing` and the server. The
  client copy now lives in one place and is tested against the same boundaries.

## [0.2.0] — 2026-08-02

Closes two of the limitations declared in 0.1.0.

### Added — integration test suite

The unit suite deliberately avoids WordPress; this covers what it could not.

- `tests/integration/` running against a real WordPress install, with
  `bin/install-wp-tests.sh` to provision it and a `MedoraTestCase` base that
  truncates Medora's tables between tests (WordPress's own rollback covers only
  core tables).
- **Repository behaviour**: upsert convergence, Arabic/Persian character
  folding, "never blank out a richer record", idempotent linking, cascade
  deletes, pagination and search.
- **Queue semantics**: atomic claiming, duplicate collapsing, back-off,
  stalled-job recovery, and that one throwing job never aborts a batch.
- **REST surface**: route registration, public-vs-capability access, that a
  draft never leaks through the public API, that undeclared settings keys are
  rejected, that the API key is never returned, dependency-aware module
  toggling, and 402 on an over-tier module.
- **Published artefacts**: schema validates cleanly and stays connected by
  `@id`, medical mode upgrades the node types, FAQ extraction, llms.txt
  exclusions, sitemaps parse as valid XML in all three formats, and robots.txt
  reflects the policy.
- CI gains an integration job on WordPress 6.4 and latest, against MySQL 8.

### Added — clinical knowledge graph

Medical Intelligence now ships the disease / treatment / drug graph.

- Six clinical predicates (`typicalTest`, `riskFactor`, `associatedAnatomy`,
  `possibleComplication`, `drug`, `relevantSpecialty`), each mapping onto a real
  Schema.org medical property so a curated edge serialises without inventing
  vocabulary.
- Ontology expanded from 19 to **42 terms** across conditions, procedures,
  drugs, anatomy, symptoms and specialties — with **65 curated relations**.
- `MedicalGraph` seeds those relations, under two rules that keep the output
  honest: an edge is written only when the site already covers *both* endpoints
  (so it never claims expertise it does not have), and curated edges are stored
  under `source = 'ontology'` so the nightly co-occurrence rebuild can never
  overwrite a clinical assertion with a statistical guess.
- `GET /medical/profile?condition=` returns a structured clinical picture —
  symptoms, treatments, diagnostics, risk factors, specialties — resolvable by
  Persian or Arabic name.
- `GET /medical/coverage` reports which curated concepts the site covers and
  which are gaps, turning "write more content" into a named list.

### Fixed

Both found by the ontology consistency tests added in this release:

- `کبد` was claimed as an alias by both `Liver` (the organ) and `Hepatology`
  (the specialty), so every mention of the liver would have been read as a
  mention of the specialty.
- `آندوسکوپی` and `اندوسکوپی` were listed as separate aliases of the same term,
  but `Text::normalize()` folds them — the second was a dead entry.

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
- **The medical ontology is a seed, not SNOMED or MeSH.** 42 entries with
  multilingual aliases and 65 curated relations as of 0.2.0, extended through
  `medora_medical_ontology` and `medora_medical_relations`.
- **Summaries are extractive, not generative,** by design. See
  `medora_prompt_pack` to substitute a model.
- **Neither the Content Optimizer nor Internal Linking rewrites prose.** The
  first produces a brief, the second wraps words already present. Both stop
  there deliberately — see 0.3.0.
