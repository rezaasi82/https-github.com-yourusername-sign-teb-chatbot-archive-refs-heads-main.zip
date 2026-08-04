=== Medora Authority ===
Contributors: medora
Tags: ai, seo, geo, schema, knowledge-graph
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 0.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make your site a trusted, machine-readable knowledge source for AI search engines and large language models.

== Description ==

Medora Authority optimises a WordPress site for the systems that **answer**
questions, not just the ones that rank pages.

Ranking algorithms place a page in a list. Reasoning systems extract a claim,
check who is accountable for it, and decide whether to cite the source. Those
are different jobs, and they need different foundations: entities instead of
keywords, retrieval chunks instead of pages, and an explainable score instead of
a traffic light.

= What it does =

* **AI Crawler Manager** — detect, control and measure GPTBot, ClaudeBot,
  PerplexityBot, Google-Extended, Applebot-Extended, CCBot and 25 more. Three
  policy presets plus per-crawler overrides, written straight into robots.txt.
* **llms.txt Engine** — publish `/llms.txt` and `/llms-full.txt`, generated from
  live content so they never drift from the site.
* **AI Sitemap Engine** — entity-annotated sitemaps in XML, JSON and Markdown,
  prioritised by authority rather than by post type.
* **Schema Intelligence** — one connected JSON-LD graph per page, validated
  before it ships.
* **Knowledge Graph** — every page becomes an entity; entities connect into a
  navigable graph you can explore and export.
* **Entity Intelligence** — detect the people, conditions, procedures, products
  and places your content covers, and score your authority on each.
* **Semantic Engine** — measure knowledge density, topic coverage, answer
  readiness and whether your sections stand alone when retrieved.
* **Vector Engine** — semantic search and related content, working offline with
  no API key.
* **AI Prompt Engine** — give every page a canonical answer, fact sheet and
  question pack that assistants can quote.
* **Citation Engine** — resolve DOIs and PubMed IDs into structured, scored,
  schema-ready references.
* **E-E-A-T Engine** — capture author credentials and verifiable profiles.
* **Medical Intelligence** — YMYL mode with clinical vocabulary, reviewer
  metadata and evidence-level tracking.
* **AI Analytics** — see visits arriving from ChatGPT, Claude, Perplexity,
  Gemini and Copilot.
* **AI Authority Score** — one 0–100 figure per page, with every lost point
  explained and a specific fix attached.

= Built for Persian and Arabic =

Not translated as an afterthought. Text normalisation folds Arabic character
forms so `علي` and `علی` resolve to one entity, tokenisation and sentence
splitting are Unicode-aware, token estimation adjusts for script, and the
dashboard is RTL by construction.

= Honest about its limits =

Referral analytics report a floor, not a total, because several assistants strip
the referrer. Trend percentages show nothing rather than a fabricated number
when there is no baseline. Crawler detection is documented as a spoofable hint.
Vector search is a linear scan with a stated ceiling and an escape hatch.

= Privacy =

No raw IP addresses or user agents are stored. Visitor identifiers are salted
HMACs with a salt that rotates daily, so no longitudinal profile can be built
even in principle — which is why the analytics module needs no consent banner.
Retention is bounded and configurable.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install the ZIP.
2. Activate it through the Plugins screen.
3. Run the setup wizard — five questions, about two minutes. It configures your
   crawler policy and queues an analysis of your existing content.

== Frequently Asked Questions ==

= Do I need an API key? =

No. Semantic search, related content and internal linking all work offline using
built-in feature-hashing embeddings. Configuring an OpenAI-compatible endpoint
improves paraphrase matching, but nothing depends on it.

= Will this slow down my site? =

The dashboard bundle loads only on Medora's own admin screens and nothing is
enqueued on the front end. All analysis runs on a background queue, never on the
request that triggered it, and every expensive path short-circuits on an
unchanged content hash.

= Does it conflict with Yoast, Rank Math or SEO Press? =

No. Medora does not manage titles, meta descriptions or canonical URLs, and
emits its schema in a separately identified `@graph`. It complements a
traditional SEO plugin rather than replacing it.

= Can I block AI crawlers? =

Yes, individually or by preset. The "Selective" preset — allow search and
retrieval crawlers, block training crawlers — is what most publishers want:
stay citable, opt out of corpus collection.

= Can I move my licence to another domain? =

Yes, yourself, from the settings screen, once a month. No support ticket.

== Screenshots ==

1. Authority overview with site score, crawler activity and AI referral trend.
2. Entity explorer with the authority breakdown for a single entity.
3. Knowledge graph visualisation.
4. AI crawler policy, per vendor, with a live robots.txt preview.
5. Per-page authority in the editor, with prioritised fixes.

== Changelog ==

= 0.5.1 =
* Fixed: page scores were cached against the content alone, so any change to
  the scoring setup — an upgrade adding a dimension, a module toggle, or
  switching between general and medical mode — left unedited pages showing a
  score calculated the old way, and the site report comparing the two.
* Scores now re-calculate automatically after those changes, in the background
  and in batches.

= 0.5.0 =
* GEO Optimizer: scores every passage of a page on whether it still makes sense
  once a retriever pulls it out on its own — which is how most AI answers
  actually read your content.
* Flags passages that open with a dangling pronoun or connective, refer to
  "the table above", never name the page's subject, sit under no heading, or
  are too short to answer anything.
* Counts the structures an assistant can quote whole: tables, numbered
  procedures, lists, question-form headings.
* New "LLM Compatibility" score dimension, and a Passages tab showing each
  passage with the text it was judged on.

= 0.4.0 =
* AI Writer (opt-in, off by default): a model rewrites page summaries and
  canonical answers, and fills in answers to questions the page left blank.
* Every generated field is checked against the source page before publication.
  Text that introduces vocabulary the page does not contain is discarded, and
  any figure not present in the page is a hard rejection — the specific hazard
  on a clinical page is an invented dose or percentage.
* Rejections are recorded in the audit log with the support ratio and the
  offending figures.
* Generation runs in the background queue, never on a page view and never in
  the request that saved a post.
* Fixed a digit-normalisation gap where the Persian thousands separator split
  one figure into two, making a faithful number look fabricated.

= 0.3.0 =
* Content briefs: GET /score/{id}/brief turns the recommendation list into a
  writing brief — outline with pasteable headings, unanswered questions,
  entities to introduce, evidence target and a checklist. Also as Markdown.
* Link application: apply a single suggested internal link, chosen by an editor,
  wrapping words already in the prose. Marked, audited, revertible, and it
  leaves a revision. There is deliberately no "apply all".
* Added the dashboard test suite; "npm run check" previously ran zero tests.
* Fixed duplicated series-collapsing and grade-threshold logic that had already
  begun to diverge between two components.

= 0.2.0 =
* Added the curated clinical knowledge graph: 42 ontology terms and 65 declared
  disease/treatment/drug/symptom relations, with new /medical/profile and
  /medical/coverage endpoints.
* Added the WordPress integration test suite covering repositories, the queue,
  the REST surface and every published artefact.
* Fixed two ontology defects the new consistency tests found: "کبد" was claimed
  by both the liver (organ) and hepatology (specialty), and two Persian
  spellings of "endoscopy" were listed separately despite normalising to the
  same form.

= 0.1.0 =
* First release. See CHANGELOG.md for the full list, including known limitations.

== Upgrade Notice ==

= 0.5.1 =
Recommended for anyone on 0.5.0. Page scores re-calculate in the background on
first load after upgrading; expect the site average to move as they do.

= 0.5.0 =
Adds the GEO Optimizer and the LLM Compatibility score dimension. Existing
scores shift slightly because a seventh dimension joins the weighting. No
database changes.

= 0.4.0 =
Adds the opt-in AI Writer. Off by default; with it off, nothing is sent to a
third party and no behaviour changes. No database changes.

= 0.3.0 =
Adds content briefs and editor-approved link application. No database changes.

= 0.2.0 =
Adds the clinical knowledge graph and the integration test suite. No database
changes; curated clinical edges are seeded automatically on the next analysis.

= 0.1.0 =
Initial release.
