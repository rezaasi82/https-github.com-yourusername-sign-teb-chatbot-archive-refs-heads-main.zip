# Medora Authority — documentation

An AI Authority Platform for WordPress. It makes a site legible to the systems
that answer questions: entities instead of keywords, chunks instead of pages,
and an explainable score instead of a traffic light.

## Contents

| Document | Covers |
|---|---|
| [01 — Architecture](01-architecture.md) | Composition root, module lifecycle, data flow, extension points |
| [02 — Database schema](02-database-schema.md) | ER diagram, table design decisions, indexing, migrations |
| [03 — API reference](03-api-reference.md) | REST endpoints, published documents, every hook and filter |
| [04 — Security](04-security.md) | Threat model, controls, privacy posture, configuration scanner |
| [05 — Developer SDK](05-developer-sdk.md) | Facade, recipes for extractors, scorers, schema nodes, modules |
| [06 — Scoring model](06-scoring-model.md) | What the AI Authority Score measures and how it is derived |
| [07 — White label & SaaS](07-white-label-saas.md) | Agency branding, multi-tenant deployment |

## Quick start

```bash
composer install    # dev tooling only; the plugin has no runtime dependencies
npm install
npm run build       # emits assets/js/{app,editor}.js + assets/css/*
```

Tests:

```bash
composer test              # unit suite — fast, needs no WordPress
bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
composer test:integration  # integration suite — real WordPress + MySQL
npm run check              # tsc + eslint + jest
```

Activate the plugin, then run the setup wizard (five questions, ~2 minutes). It
writes the settings, applies a crawler policy, and queues an analysis of your
most recent 200 pages so the dashboard has real numbers the first time you open
it.

## What ships where

```
medora-authority/
├── medora-authority.php    bootstrap
├── includes/               PHP — one directory per module
│   ├── Core/               container, options, schema, cron, capabilities
│   ├── Module/             module contract and registry
│   ├── Support/            text, chunking, cache, hashing, arrays
│   ├── Entity/ Graph/ Semantic/ Vector/ Prompt/   the knowledge engines
│   ├── Schema/ Llms/ Sitemap/ Crawler/            what gets published
│   ├── Score/ Content/ Linking/ Citation/ Eeat/ Medical/  analysis
│   ├── Analytics/ Security/ Performance/ License/ WhiteLabel/
│   ├── Rest/               controllers
│   ├── Admin/              menu, assets, meta box, wizard
│   └── Sdk/                the supported public facade
├── src/                    React + TypeScript dashboard
├── tests/phpunit/          unit suite (runs without WordPress)
└── docs/                   this directory
```

## Design commitments

These are the decisions the product is willing to be judged on.

**Every deduction is explained.** A score with no derivation is a vanity
metric. Each lost point names its cause, its cost and its fix.

**Nothing critical is behind an add-on.** SMS-style feature unbundling is how
competing products extract revenue; here, licence tiers gate whole modules and
never split one capability across two purchases.

**Extractive, not generative, by default.** Summaries and canonical answers are
built from the page's own sentences. A generated paraphrase would introduce
claims the publisher never made — a liability on a health site, not a feature.
With the AI Writer module off, or no key configured, the plugin makes no
outbound model call at all.

**Generation is verified, not trusted.** Turn the AI Writer on and every
generated field is still checked against the page it came from: content words
must largely appear in the source, and any figure that does not appear verbatim
is a hard rejection. Prompt instructions are a request; the check is what makes
the output publishable. Rejections land in the audit log.

**Honest numbers.** Referral analytics report a floor, not a total, because
several assistants strip the referrer. Trend percentages are `null` rather than
fabricated when there is no baseline. Crawler detection is documented as a
spoofable hint, and nothing security-relevant depends on it.

**Persian and Arabic are first-class.** Normalisation folds Arabic character
forms, tokenisation is Unicode-aware, sentence splitting handles `؟` and `۔`,
token estimation uses a script-aware divisor, and the dashboard is RTL by
construction — logical CSS properties throughout, no mirrored build.

**No runtime dependencies.** Bundling a DI or HTTP library inside a WordPress
plugin invites fatal version collisions with every other plugin that bundles the
same thing.

## Status

Version 0.1.0. See [CHANGELOG.md](../CHANGELOG.md) for what is implemented and
what is scaffolded.
