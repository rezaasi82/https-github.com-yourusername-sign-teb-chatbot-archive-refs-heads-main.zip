# API reference

Namespace: `medora/v1`. Base URL: `{site}/wp-json/medora/v1`.

## Authentication

| Route group                                   | Access                              |
|-----------------------------------------------|-------------------------------------|
| `/entities`, `/graph`, `/prompt/{id}`, `/search`, `/citations/{id}` (GET) | Public, read-only |
| Everything else (GET)                          | `medora_view_dashboard`             |
| `/settings`, `/modules/*`, `/crawlers` (write) | `medora_manage_settings`            |
| `/score/{id}/analyze`, `/citations` (write)    | `medora_run_analysis`               |
| `/entities/{id}` (write)                       | `medora_manage_entities`            |
| `/audit-log`                                   | `medora_view_audit_log`             |

The public endpoints are open **by design** — they are how an AI system consumes
the site's structured knowledge, and gating them behind a key would defeat the
product. They expose only published content. Anonymous GETs are rate limited to
120 requests per minute per IP (`medora_public_rate_limit`), and the whole public
surface can be closed with `add_filter( 'medora_public_api_enabled', '__return_false' )`.

Authenticated requests use the standard WordPress REST nonce (`X-WP-Nonce`), or
application passwords for machine-to-machine use.

## Knowledge API (public)

### `GET /entities`

Every entity the site covers, ranked by authority.

| Param | Type | Default | Notes |
|---|---|---|---|
| `search` | string | — | matched against the normalised name |
| `type` | string | — | Schema.org type, e.g. `MedicalCondition` |
| `min_score` | number | — | 0–100 |
| `orderby` | enum | `authority` | `authority`\|`name`\|`occurrences`\|`recent` |
| `page`, `per_page` | int | 1, 50 | `per_page` max 200 |

```json
{
  "items": [
    {
      "id": 42,
      "uid": "9f2c…",
      "name": "Fatty liver disease",
      "type": "MedicalCondition",
      "type_label": "Condition",
      "description": "Accumulation of fat in liver cells…",
      "permalink": "https://example.test/conditions/fatty-liver/",
      "same_as": ["https://www.wikidata.org/wiki/Q1058054"],
      "authority_score": 78.5,
      "occurrences": 31,
      "object": { "type": "term", "id": 17 }
    }
  ],
  "total": 214,
  "page": 1
}
```

`X-WP-Total` carries the count for pagination.

### `GET /entities/{id}`

One entity, plus its graph neighbourhood and a full authority breakdown —
six components, each with points earned, points available and a plain-language
note explaining the gap.

### `GET /graph`

| Param | Type | Default | Notes |
|---|---|---|---|
| `format` | enum | `jsonld` | `jsonld` for machines, `nodes` for visualisation |
| `limit` | int | 300 | max 2000 |

`format=jsonld` responds with `Content-Type: application/ld+json` and a
Schema.org `@graph` whose nodes reference each other by stable `@id`.
`format=nodes` returns `{ nodes, links, stats }` for a force layout.

### `GET /prompt/{id}`

The LLM-facing representation of a page.

```json
{
  "title": "Fatty liver disease: symptoms and treatment",
  "url": "https://example.test/fatty-liver/",
  "summary": "Fatty liver disease is the accumulation of fat…",
  "canonical_answer": "Fatty liver disease is reversible in most cases through…",
  "facts": ["Affects roughly 25% of adults worldwide.", "…"],
  "questions": [ { "question": "What causes it?", "answer": "…" } ],
  "context_window": "# Fatty liver disease\nSource: …",
  "entities": ["Fatty liver disease", "Hepatology", "FibroScan"]
}
```

Everything is derived **extractively** from the page's own words. That is a
product decision: a generated paraphrase would introduce claims the publisher
never made, which on a health site is a liability.

The AI Writer module (`llm`, opt-in, Agency tier) can replace the `summary` and
`canonical_answer` with model-generated prose, and fill in answers the
extractive pass left blank. It never runs during this request: the extractive
pack is saved first, `medora_prompt_pack_saved` fires, and a queued job does the
generative pass. Each generated field is checked by `Llm\Grounding` against the
source page and discarded on failure, so this endpoint's response is either the
publisher's own sentences or text verified to introduce nothing the page does
not contain. Hook `medora_prompt_pack` to substitute your own text instead.

### `GET /geo/{id}`

Passage-level retrievability for one page. Requires the GEO Optimizer module.

```json
{
  "post_id": 42,
  "passages": [
    {
      "index": 2,
      "heading": "Treatment",
      "excerpt": "Treatment. However, that is rarely the whole picture…",
      "words": 96,
      "score": 75.0,
      "issues": [
        {
          "code": "dangling_connective",
          "label": "Opens by continuing an argument that is not here",
          "fix": "Start the passage with a statement rather than \"However\"…"
        }
      ]
    }
  ],
  "clean": 8,
  "total": 14,
  "ratio": 0.5714,
  "structure": { "tables": 1, "ordered_lists": 0, "question_headings": 2, "…": 0 }
}
```

The passages are the same chunks the Vector Engine indexes — both go through one
`Chunker` — so the score here describes the text that is actually retrieved,
not a separate approximation of it.

Issue codes: `dangling_connective`, `dangling_pronoun`, `page_reference`,
`subject_absent`, `no_heading`, `too_short`.

### `GET /search`

Semantic search over the site. Requires the Vector Engine module.

| Param | Type | Notes |
|---|---|---|
| `q` | string | required |
| `limit` | int | default 10, max 50 |

### `GET /citations/{id}`

References attached to a post, formatted in `apa`, `vancouver` or `harvard`
(`?style=`), each with its evidence level and quality score.

## Dashboard API

### `GET /overview?days=30`

One request that bootstraps the entire dashboard: licence state, module
inventory, queue depth, site score, entity counts, crawler report, referral
report, vector stats and citation stats. Sections belonging to disabled modules
are omitted rather than erroring.

### `GET /score/{id}` · `GET /score` · `POST /score/{id}/analyze`

Per-page and site-wide authority. `POST …/analyze` queues by default and returns
`202`; pass `{"sync": true}` to run inline and get the result immediately (this
is what the editor's re-analyse button does).

### `GET /score/{id}/recommendations`

An ordered to-do list, sorted by **points recovered per unit of effort** rather
than by severity alone — a critical issue needing a week of rewriting ranks
below a high-severity one that takes two minutes.

### `GET /score/{id}/brief`

A writing brief — the artefact a writer works from, as opposed to the
recommendation list, which is a diagnosis.

| Param | Type | Default | Notes |
|---|---|---|---|
| `format` | enum | `json` | `markdown` renders it for pasting into a ticket |

Returns the outline (existing and missing sections, each missing one given as a
pasteable question-shaped heading with coverage hints), what the opening must
accomplish, unanswered questions, entities to introduce, an evidence target, a
word-count target derived from the gaps, and a checklist ordered unfinished
first.

Entities to introduce come from the site's own knowledge graph, so a brief never
proposes a topic the site knows nothing about.

### `POST /links/{id}` · `DELETE /links/{id}`

Apply or revert a single internal link.

```json
{ "target_id": 42, "anchor": "liver biopsy", "occurrence": 2 }
```

Constraints, all enforced server-side:

- The **target must be one `GET /links/{id}` suggested**. Anything else is
  rejected — otherwise this endpoint would be arbitrary-markup injection with a
  capability check in front of it.
- The **anchor must already exist in the prose.** Text is never inserted, only
  wrapped.
- The caller needs `edit_post` on the post, on top of `medora_run_analysis`.
  Being able to analyse must not imply being able to edit.
- Occurrences inside existing anchors, headings, `code`, `pre` and attribute
  values are never eligible. `occurrence` counts eligible positions only.

Inserted anchors carry `data-medora-link`, and the update goes through
`wp_update_post()` so a revision is always available. `DELETE` unwraps only
marked anchors — an editor's own links to the same target survive — and accepts
an optional `target_id` to revert one destination.

### `GET /schema/{id}`

The JSON-LD Medora would emit for a page, plus a validation report catching
dangling `@id` references, missing required properties and untyped nodes.

### `GET /links/{id}`

Outbound link suggestions and inbound opportunities. Anchor text is always a
phrase the source page already contains — suggesting an anchor that is not
present would push an editor toward inserting a phrase for the link's sake.

### `GET /crawlers` · `PATCH /crawlers`

Resolved per-crawler policy, a robots.txt preview, and a flag when a physical
`robots.txt` is shadowing the virtual one. `PATCH` accepts `{preset}` for the
site-wide policy or `{slug, decision}` for one crawler (`decision: "reset"`
clears an override).

### `GET /analytics` · `GET /crawler-activity`

Referral and crawl reports over a `days` window. Referral `change_percent` is
`null` rather than a fabricated number when there is no prior period.

### `GET /medical/profile?condition=` (public)

The structured clinical picture for one condition — what an answer engine
asking "what does this site know about X" should receive, as data rather than a
page it has to parse. Resolvable by any name the ontology declares, in English,
Persian or Arabic.

```json
{
  "entity": { "name": "Fatty liver disease", "type": "MedicalCondition", "…": "…" },
  "symptoms":     [ { "name": "Fatigue", "curated": true, "predicate": "signOrSymptom" } ],
  "diagnostics":  [ { "name": "FibroScan", "curated": true, "predicate": "typicalTest" } ],
  "risk_factors": [ { "name": "Diabetes mellitus", "curated": true, "predicate": "riskFactor" } ],
  "treatments":   [],
  "specialties":  [ { "name": "Hepatology", "predicate": "relevantSpecialty" } ],
  "related":      []
}
```

Returns `404` when the site does not cover the condition — the graph never
claims expertise the content does not support.

### `GET /medical/coverage`

Which curated concepts the site covers and which are gaps, plus ontology
statistics. This turns "write more content" into a named list.

### `GET /authors/{user_id}/trust`

E-E-A-T breakdown across Experience, Expertise, Authoritativeness and
Trustworthiness, with the specific gaps.

### `GET|PATCH /settings` · `PATCH /modules/{id}` · `GET|POST /license`

Configuration. `PATCH /settings` accepts only keys declared in
`Options::defaults()`. The stored embedding API key is never returned. Disabling
a module that others depend on returns `400` naming them; enabling one above the
current tier returns `402` with `required_tier`.

### `GET /security/scan` · `GET /audit-log` · `GET|POST /onboarding`

Configuration self-audit, the append-only audit trail, and the setup wizard.

## Errors

Standard WordPress REST shape:

```json
{ "code": "medora_upgrade_required", "message": "This module requires the Agency plan.", "data": { "status": 402, "required_tier": "agency" } }
```

| Code | Status | Meaning |
|---|---|---|
| `medora_forbidden` | 401/403 | missing capability |
| `medora_not_found` | 404 | unknown post, entity or crawler |
| `medora_bad_request` | 400 | invalid input, or a required module is inactive |
| `medora_upgrade_required` | 402 | licence tier too low |
| `medora_rate_limited` | 429 | public rate limit exceeded |
| `medora_license_error` | 400 | licence server rejected the operation |

## Action hooks

| Hook | Args | Fires |
|---|---|---|
| `medora_register_modules` | `ModuleRegistry` | before modules are filtered and booted |
| `medora_modules_booted` | `ModuleRegistry` | after all modules boot |
| `medora_module_toggled` | `string $id, bool $enabled` | a module is enabled or disabled |
| `medora_register_entity_extractors` | `EntityExtractor` | extractor registration |
| `medora_entities_indexed` | `WP_Post, array $results` | a post's entities were written |
| `medora_register_schema_nodes` | `SchemaGraph` | schema node registration |
| `medora_register_scorers` | `AuthorityScoreCalculator` | scorer registration |
| `medora_post_analyzed` | `WP_Post, array $result` | a page was scored |
| `medora_post_embedded` | `WP_Post, int $chunks` | embeddings written |
| `medora_prompt_pack_saved` | `array $pack, WP_Post, string $hash` | an extractive pack was persisted |
| `medora_graph_rebuilt` | `int $posts, int $edges` | full graph rebuild finished |
| `medora_ai_crawler_detected` | `array $crawler, string $decision` | before the response to a crawler |
| `medora_ai_referral_recorded` | `array $match` | an assistant referral was logged |
| `medora_medical_graph_seeded` | `array $result` | curated clinical edges were written |
| `medora_link_applied` | `WP_Post, int $targetId, string $anchor` | an internal link was inserted |
| `medora_license_status_changed` | `string $status, array $state` | licence state transition |
| `medora_settings_updated` | `array $settings` | settings written |
| `medora_onboarding_completed` | `array $settings` | wizard finished |
| `medora_job_failed` | `string $handler, Throwable` | a background job threw |
| `medora_scorer_failed` | `string $id, Throwable` | a scorer threw (analysis continues) |

## Filter hooks

| Filter | Returns | Purpose |
|---|---|---|
| `medora_module_classes` | `list<class-string>` | add or replace modules |
| `medora_option` | `mixed` | override a setting at read time |
| `medora_entity_dictionary` | `array` | contribute controlled vocabulary |
| `medora_medical_ontology` | `array` | extend the clinical vocabulary |
| `medora_medical_relations` | `array` | extend the curated disease/treatment/drug graph |
| `medora_brief_related_entities` | `array` | supply related entities for a content brief |
| `medora_brief_citation_count` | `int` | report how many citations a post carries |
| `medora_brief_citations_required` | `bool` | demand primary literature for a post |
| `medora_taxonomy_entity_type` | `string` | map a taxonomy to a Schema.org type |
| `medora_entity_candidates` | `array<string, Candidate>` | edit candidates before persistence |
| `medora_entity_salience` | `float` | adjust computed salience |
| `medora_entity_authority_score` | `float` | adjust entity authority |
| `medora_authority_score` | `float` | adjust the page score |
| `medora_topic_facets` | `array` | change expected sub-topics per type |
| `medora_schema_graph` | `list<array>` | edit the JSON-LD graph before output |
| `medora_schema_citations` | `list<array>` | attach citation nodes to an article |
| `medora_article_schema_type` | `string` | override the Article type |
| `medora_crawler_registry` | `array` | register crawlers shipped after this release |
| `medora_crawler_decision` | `string` | override access for one crawler |
| `medora_robots_directives` | `string` | edit the robots.txt block |
| `medora_llms_txt` / `medora_llms_full_txt` | `string` | edit the published documents |
| `medora_llms_txt_sections` | `array` | restructure the llms.txt index |
| `medora_prompt_pack` | `array` | replace extractive text with generated text |
| `medora_llm_provider` | `LlmProviderInterface` | swap in another text-generation backend |
| `medora_llm_request_body` | `array` | edit the outgoing Messages API request |
| `medora_llm_language` | `string` | override the language generated text is written in |
| `medora_embedding_providers` | `array` | register an embedding provider |
| `medora_vector_search` | `?array` | delegate search to an external index |
| `medora_sitemap_ping_endpoints` | `list<string>` | add IndexNow or similar |
| `medora_rest_controllers` | `list<AbstractController>` | register REST controllers |
| `medora_public_api_enabled` | `bool` | close the public knowledge API |
| `medora_public_rate_limit` | `int` | per-minute anonymous limit |
| `medora_license_endpoint` / `medora_license_tier` | `string` | point at your own entitlement service |
| `medora_branding` | `array` | force white-label values from code |
| `medora_boot_data` | `array` | extend the dashboard bootstrap payload |
| `medora_security_checks` | `array` | add configuration checks |

## Published documents

| Path | Content type | Purpose |
|---|---|---|
| `/llms.txt` | `text/markdown` | curated site map for language models |
| `/llms-full.txt` | `text/markdown` | full content bundle, token-budgeted |
| `/medora-sitemap.xml` | `application/xml` | sitemap index |
| `/medora-sitemap-{type}.{xml,json,md}` | varies | `content`, `entity` or `knowledge` |
| `/robots.txt` | `text/plain` | per-crawler policy (appended by filter) |

Sitemap priority is derived from the AI Authority Score rather than hardcoded
per post type, so a crawler with a limited budget is pointed at the pages most
worth citing.
