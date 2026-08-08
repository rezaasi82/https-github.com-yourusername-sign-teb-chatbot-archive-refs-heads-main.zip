=== Medora Authority ===
Contributors: medora
Tags: ai, seo, geo, schema, knowledge-graph
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 0.15.0
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

= 0.15.0 =
* The plugin can now be built into an installable ZIP — `composer package`, or
  download the archive attached to a tagged release.
* Fixed: the dashboard stylesheet and both RTL stylesheets were built under
  names WordPress does not look for, so the dashboard loaded unstyled and an
  RTL site loaded no styles at all.
* Fixed: two screens used their own grade colour thresholds instead of the
  shared ones, so a page scoring 95 was coloured the same as one scoring 70.
* Fixed: eight form controls were not properly labelled, and the module
  switches had no accessible text for a screen reader.

= 0.14.0 =
* Persian is now complete — all 740 strings across the plugin and dashboard.
* Still needs review by a Persian speaker before you rely on it: it is complete,
  not verified.
* Arabic has been removed from the planned languages.

= 0.13.0 =
* Persian translation, first pass — 410 of 740 strings, covering the whole
  interface: menus, buttons, score dimensions, graph relationships and the
  explanatory text on each screen. Needs review by a Persian speaker before
  release; untranslated strings stay in English.
* Added tooling to merge and update translations, and a check that refuses to
  compile a translation which broke a placeholder like %s.

= 0.12.0 =
* Security review of the whole codebase — database queries, request handling,
  output escaping and uninstall cleanup. No vulnerabilities found; one
  inconsistency tightened.
* Added an automated audit that runs on every change, catching settings nothing
  reads, features advertised but not built, and data left behind on uninstall.

= 0.11.0 =
* You can now remove an entity the extractor got wrong — a menu label or stray
  phrase treated as a subject. Until now there was no way to, and those
  entities are published in your structured data.
* Removing one also stops it being extracted again. Otherwise it would simply
  come back the next time the page was analysed.
* Removed entities are listed on the Entities screen and can be restored
  individually or all at once.

= 0.10.0 =
* Added a Score tab showing where a page's score actually comes from: every
  dimension, what share of the total it is, how many points it is costing, and
  the deductions belonging to it. The data was always there; nothing displayed
  it, so "why is this page 62?" had no answer on screen.
* Agency mode adds the underlying measurements behind each dimension.
* Enterprise mode adds a background-work breakdown per queue, so a stalled
  model provider looks different from an ordinary backlog.
* Enterprise no longer claims API credentials — there is no API key system, and
  the description was wrong to say otherwise.

= 0.9.0 =
* Added the Audit log screen (Enterprise mode). The log was being written from
  day one and had no way to read it.
* It shows every change Medora made, and for the AI Writer, every generated
  sentence it kept and every one it threw away — with the reason.
* Reading it requires its own permission, separate from seeing the dashboard.

= 0.8.0 =
* Fixed: the language declared to AI crawlers came from your WordPress admin
  language, not from your content. If your site is Persian or Arabic but your
  admin is English — a very common setup — every page was being announced as
  English, and the AI Writer was told to write in English.
* Added a "Content language" setting, and a language line to llms.txt.
* Multilingual plugins can now set the language per page through a filter.

= 0.7.1 =
* Fixed: the white-label accent colour had no effect. The dashboard's own
  styling always won over it.
* Added a white-label panel under Settings (Agency mode and above) — product
  name, menu label, your company and links, accent colour. Previously this was
  only reachable from code.
* Hardened: branding URLs are validated when saved, so only real web addresses
  are stored.

= 0.7.0 =
* The Beginner / Professional / Agency / Enterprise modes now genuinely change
  the dashboard. Previously the setting was saved and ignored, so every install
  saw the full interface.
* Beginner shows the working loop and nothing else: your score, what is wrong
  with each page, and how to fix it. Higher modes add the entity explorer,
  analytics, the knowledge graph, module control and the audit log.
* Changing mode only changes what is on screen. It grants nobody extra access
  and takes none away — permissions still come from WordPress roles.
* The mode is now changeable in Settings, not only during setup.

= 0.6.0 =
* Fixed: on a translated site, every writing-brief section fell back to a
  generic heading instead of its real one, because coverage topics were
  identified by their translated name. Topics now have stable identifiers.
* The plugin is now genuinely translatable: the translation template contains
  all 662 strings, where before it was empty.
* Added translation tooling that needs only PHP — regenerate the template, and
  compile a finished .po into the files WordPress loads, including the JSON
  catalogue the dashboard needs.
* Persian and Arabic catalogues are not included yet; the groundwork for them is.

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

= 0.14.0 =
Persian is complete but unreviewed. Read it before switching your site to it.

= 0.13.0 =
Persian is available but marked as a draft. Review it before relying on it.

= 0.12.0 =
No database changes. Developer tooling and a security review; no behaviour
changes.

= 0.11.0 =
No database changes.

= 0.10.0 =
No database changes. Nothing to reconfigure.

= 0.9.0 =
No database changes. Enterprise-mode installs gain an Audit log menu item.

= 0.8.0 =
Important if your content is not in the same language as your WordPress admin.
Check Settings → Content language after upgrading.

= 0.7.1 =
Recommended for anyone white-labelling. Your accent colour will start applying
once you upgrade.

= 0.7.0 =
If you picked Beginner during setup, the dashboard will now be simpler than it
was — the setting finally applies. Change it under Settings → Dashboard.

= 0.6.0 =
Required if you run the plugin in any language other than English. No database
changes and no re-analysis needed.

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
