# Changelog

All notable changes to Medora Authority are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.16.0] — 2026-08-08

### The Persian review has tooling now

The catalogue has been marked "complete but unreviewed" since 0.14.0, which is
only useful if reviewing it is possible. Reading 741 strings in a `.po` is not.

`composer i18n:review` builds `dist/review-fa_IR.html`: one self-contained page,
no server, nothing uploaded. Strings are grouped by the screen they appear on,
because register is a property of a screen rather than of a string — "Close" on
a dialog and "Close" in a report want different words, and that is only visible
when a string sits beside its neighbours. Progress and edits are kept in the
browser so 741 strings can be done across several sittings, and placeholders are
checked as they are typed, since a dropped `%s` is the one translation error
that takes a screen down rather than just reading badly.

`bin/apply-review.php` writes the exported corrections back. Everything is
validated first and one bad entry stops the run; the reviewer fixes it in the
sheet and re-exports, so the `.po` only ever moves between complete states.
Corrections go to the `.po` alone — `merge-po.php` already prefers it over the
map, which makes the `.po` the authority and leaves `*.map.php` as the
unreviewed draft its header says it is.

Verified in a real browser end to end: 741 rows in 28 areas, edits surviving a
reload, the placeholder warning firing on a dropped argument, every filter, and
an export fed back through the CLI — where the deliberately broken edit was
refused and the good one applied.

### Changed — the glossary check stopped crying wolf

It matched the glossary term as a literal substring, so all nine hits were
false: "Medora Authority" is a proper noun that does not owe the reader
"اعتبار", and "پیوندهای داخلی" and "پیوندسازی داخلی" are both correct inflections
of "پیوند داخلی". Matching each word of the term as a prefix, and exempting the
product name, takes it to zero — the state a check has to reach before anyone
reads it. `has-placeholder` also stopped counting as a defect; it marks 73
strings, and left in the same bucket it buried the three that are actually
worth a look.

### Fixed — plural corrections were rejected

A plural entry carries its placeholder once per form, and both the sheet and
the CLI compared the joined whole, so a correct two-form Persian plural was
reported as having `%d %d`. Each form is now compared against the source
separately. Found by running the round trip rather than by reading it.

### Added

- `docs/08-translation.md` — what is checked mechanically (and is clean), what
  only a person can judge, the review loop, and where corrected text lives.

## [0.15.0] — 2026-08-08

### There is now something to install

Until this release the plugin existed only as source. The dashboard bundle, the
compiled stylesheets and the binary translation catalogues are all build
outputs, all correctly gitignored, and all required at runtime — so a checkout
zipped up would activate, show an empty dashboard and speak English on a Persian
site. `bin/package.php` builds the archive that actually installs.

The file list is an allowlist, so `src/`, `tests/`, `bin/`, `docs/`,
`node_modules/`, `vendor/`, the translation map and every dotfile are absent
because they are not named rather than because a rule excluded them. It refuses
to package a bundle older than `src/`, a catalogue older than its `.po`, or a
version that disagrees across the plugin header, `package.json`, `readme.txt`
and this file. Both refusals were checked by breaking a copy of the tree.

CI now builds the archive on every push and uploads it as a run artifact;
pushing a `medora-authority-v<version>` tag publishes it as a release, after
checking the tag against the plugin header.

### Fixed — the first real build found four defects

The toolchain had never been run. It disagreed with the PHP in four places:

- **The dashboard stylesheet never loaded.** `src/style.css` is pulled into its
  own chunk and named `./style-app` by the wp-scripts default, so the build
  emitted `assets/css/style-app.css` while `AssetManager::enqueueApp()` asked
  for `assets/css/app.css`. The dashboard would have rendered unstyled.
- **Both RTL stylesheets landed in `assets/js/`.** `wp_style_add_data( …, 'rtl',
  'replace' )` swaps `app.css` for `app-rtl.css` in the same directory, so an
  RTL site — the common case for this product, not the edge one — would have
  loaded no stylesheet at all.
- **`api.recommendations()` returned `Record<string, unknown>`** and the caller
  cast it. `Recommendations` is now declared in `src/types.ts` alongside the
  other response shapes, so the controller and the screen are checked against
  each other rather than papered over at one call site.
- **`src/api/client.test.ts` had never executed.** Jest refuses a `jest.mock()`
  factory that closes over a variable not prefixed `mock`, so the whole suite
  failed to load. It passes now, and one of its assertions was wrong: form
  encoding writes a space as `+`, so `decodeURIComponent` never round-tripped
  the Persian query it was checking.

### Fixed — two components had their own grade thresholds

`format.ts` says the grade bands exist in one place because two copies would
eventually disagree. Two components had already made their own: the passage
panel coloured at 80/55 and the content table at 65, so a page scoring 95 was
drawn in the same colour as one scoring 70. Both now call `gradeFor()`.

### Fixed — a duplicate `.medora-meter` reshaped every meter

A later block redefined the shared class for one screen's needs, silently
changing the border radius and margin of the two meters in Entities that had
nothing to do with it. The box is defined once; the spacing is scoped to the
screen that wanted it.

### Fixed — eight controls were not labelled

WordPress requires explicit `htmlFor`/`id` association rather than nesting.
Eight labels wrapped their input instead, and the module switches had no
accessible text at all — a screen reader announced a list of unnamed
checkboxes. Each switch now names the module it toggles.

### Changed

- `%s%% supported` became `%1$s%% supported`: mixing ordered and unordered
  placeholders in one string is a translation hazard. Persian follows, so the
  catalogue is 741 of 741.
- `jsdoc/require-param` is off for the TypeScript sources. Its autofix wrote 45
  empty `@param root0.value` stubs onto the destructured components in a single
  run; the types already declare what those comments repeated.
- `no-descending-specificity` is off, with the reasoning recorded in
  `.stylelintrc.json`: every pair it flags is on classes that are never applied
  to the same element, and satisfying it would mean interleaving unrelated
  components by specificity.

### Still true

PHPUnit has never run in this environment — `composer install` cannot
authenticate to github.com from here, so the PHP suite remains unexecuted and
CI is the first place it will run. The JS suite does now run: 38 tests, all
passing.

## [0.14.0] — 2026-08-03

### Persian is complete — 740 of 740

Every string in the plugin and the dashboard. Three entries stay in Latin script
on purpose: `ORCID` and `sameAs` are identifiers rather than words, and
`−%1$s %2$s` is pure formatting.

**Complete is not the same as correct.** This was written by an assistant, not
by a Persian-speaking editor. The terminology is consistent and the grammar is
sound, but register and idiom on a medical product are exactly what a non-native
draft gets subtly wrong, and clinicians read this text. The map file says so at
the top, so nobody inherits it as reviewed work.

Verified mechanically against the compiled `.mo`, read back with an independent
reader: 741 entries including the header, zero empty translations, zero
placeholder mismatches across every string and every plural form.

### Changed — Arabic is out of scope

Removed from the declared launch targets and from the plural-rule table.
Priority is Persian, then English. Arabic's six plural forms are why it would
need an explicit entry rather than the fallback if it is ever added — that is one
line, and the note explaining it stays in `merge-po.php`.

Leaving a rule in place for a locale nobody is filling in only suggests work
that is expected.

## [0.13.0] — 2026-08-03

### Added — Persian (fa_IR), first pass

**410 of 740 strings, and it needs a native review before release.** Written by
an assistant rather than a Persian-speaking editor: the terminology is
consistent and the grammar is sound, but register and idiom on a medical
product are exactly what a non-native draft gets subtly wrong, and clinicians
read this text. The header of the map file says so, so nobody inherits it as
finished work.

The 410 are the ones that make the interface Persian rather than English with
Persian labels: navigation, every module and score dimension, all actions and
states, the relationship predicates in the graph, the question-shaped headings
the brief suggests, and the explanatory line on each main screen. What is left
is longer prose. Untranslated strings stay English — gettext falls back per
string, so a partial catalogue is a working catalogue rather than a broken one.

Terminology is fixed at the top of the map so the interface reads as one voice:
اعتبار for authority, امتیاز for score, موجودیت for entity, گراف دانش for
knowledge graph, خزنده for crawler, بند for passage, استناد for citation.

### Added — `bin/merge-po.php`

`msgmerge` without gettext. The template stays the source of truth for which
strings exist and their references; translations carry across by msgid; strings
the code no longer contains are dropped, since keeping them only makes the file
look more finished than it is.

**Edits made directly in the `.po` win over the map on the next merge**, so a
reviewer correcting the draft does not have their work overwritten by it.

### Added — placeholder verification at compile time

`make-l10n.php` now refuses to compile a translation that dropped, added or
renumbered a printf placeholder, and exits non-zero so CI catches it.

This is the one translation error that is a *runtime* error rather than
cosmetic: `sprintf()` throws on a missing argument, so a translator losing a
`%s` takes that screen down in that locale only — the kind of breakage nobody
notices until a customer reports it. Verified by deliberately breaking
`"Step %1$d of %2$d"` and confirming the compile failed.

The Persian catalogue passes with zero mismatches across all 410 strings.

## [0.12.0] — 2026-08-03

A security sweep, and the audits that have been finding these bugs move out of
`/tmp` and into CI.

### Security

Swept mechanically for the patterns the brief names — SQL interpolation,
superglobal reads, output escaping, REST argument sanitising, uninstall
completeness. **No vulnerabilities.** Reported as a result rather than dressed
up as a fix:

* Every raw-string SQL call interpolates only a table name from `Tables::name()`
  — a class constant plus `$wpdb->prefix`, never input — and each carries the
  `phpcs:ignore` explaining it.
* `EntityRepository::query()`, the one query taking several user-supplied
  filters, builds placeholders and passes them through `prepare()`, whitelists
  the `ORDER BY` segment, and casts `min_score`.
* No superglobal is read without unslashing and sanitising; no variable is
  echoed without escaping; uninstall removes every option, post meta and table
  the plugin creates.

One inconsistency fixed: `min_score` was the only REST argument without a
`sanitize_callback`. It was safe — range-validated by core and cast at the
repository — but every sibling argument declares one, and a security convention
with a hole in it stops being a convention.

### Added — `bin/audit.php`

PHPCS catches style, PHPStan catches types. Neither catches the failure mode
that has produced most of this codebase's real bugs: something declared and
never consumed. Eight checks, no WP-CLI, no Composer, no database:

| Check | Catches |
|---|---|
| every setting has a consumer | a stored option nothing reads |
| every experience flag gates something | a mode promising a feature that does not exist |
| every hook is documented | an extension point nobody can find |
| superglobals are sanitised | unslashed request input |
| output is escaped | an unescaped echo |
| REST arguments declare sanitising | an endpoint trusting its input |
| uninstall removes what the plugin creates | rows left behind on delete |
| every dashboard API method has a caller | an endpoint with no way to reach it |

Runs in CI as its own job and joins `composer check`.

**The audit was tested by breaking things.** Six deliberate defects were
introduced into a copy of the tree — a dead option, a dead flag, an
undocumented hook, an unsanitised `$_GET`, an unescaped `echo`, a leaked post
meta — and the run compared against the clean tree. Five tripped; the
experience-flag check did not, because it was matching the flag's own
declaration and so could never fail. That is the exact bug class the audit
exists to catch, sitting in the audit. Fixed by excluding the declaring file,
the same way the settings check already excluded `Options.php`, and re-verified
against both trees.

A check that has never failed is not evidence of a clean codebase. It is an
untested check.

## [0.11.0] — 2026-08-03

The audit, pointed at the dashboard: unstyled classes, uncalled client methods,
unused types. One finding was real and it was a functional gap.

### Added — removing an entity

`DELETE /entities/{id}` has existed since the first release, capability-gated,
and `api.deleteEntity()` sat in the client with no caller. The entity editor
could change a description and add `sameAs` links, but a wrongly extracted
entity — a navigation label read as an organisation, a stray phrase read as a
subject — could not be removed at all. Those entities are not merely untidy:
they are **published**, in the JSON-LD `@graph` handed to every AI crawler.

But a delete button on its own would have been a trap. Extraction is
deterministic: whatever the heuristic extractor found in the text once, it finds
again on the next analysis. The entity would reappear within the hour and the
editor would rightly conclude the feature was broken.

So removal records the decision. `Entity\SuppressionList` keys on the entity
uid — `sha256(type|normalised name)`, stable across re-extraction and across
locales, which storing the *name* would not be — and `EntityExtractor` consults
it before the `medora_entity_candidates` filter runs, so a third party that
wants a suppressed entity back can add it deliberately rather than having its
addition silently dropped afterwards.

Removal is therefore a standing instruction, not a one-off act, and the UI
treats it as one:

* the removal control sits in its own marked section, separated so it cannot be
  clicked while aiming for Save, and asks for confirmation;
* the Entities screen lists what has been removed, with per-entity Restore and
  Restore all. A standing instruction nobody can see or undo is how a knowledge
  graph quietly loses a subject with no explanation;
* the list is capped at 500. The option is autoloaded on every request, so it
  cannot grow without bound — and a site suppressing hundreds of entities has an
  extraction problem that a blocklist is the wrong fix for. Refusing makes that
  visible; growing quietly would not.

`GET`/`DELETE /entities/suppressed` back the list. `DELETE /entities/{id}` takes
`suppress` (default true) so the API can still do a plain delete.

### Fixed — the unit bootstrap again

`update_option`, `delete_option` and `current_time` were missing, so any test
touching a class that *writes* a setting fataled rather than failing. Writes now
go back into the test option store, so a write-then-read test passes on its own
merits rather than because `Options` happens to cache in-process.

That is the third gap found in this bootstrap. Each one made a category of test
impossible to write, which is why they went unnoticed: nobody writes the test
that cannot run.

## [0.10.0] — 2026-08-03

I turned the dead-code audit on the feature flags I introduced in 0.7.0. Four of
the eighteen had no consumer, and three of those were advertised to users in the
mode descriptions — a promise in UI copy for behaviour that did not exist.

### Added — the score breakdown

`components` has been in every analysis response from the first release: each
dimension's score, its weight, its own deductions, and the measurements it was
computed from. Nothing rendered it. `api.postScore()` existed in the client and
was called by nothing.

That left the product's central claim only half delivered. The brief asks for
every deduction to be explained; the deductions were listed, but not what they
were deductions *from*, so "why is this page 62?" had no answer on screen.

A new **Score** tab on the Content panel answers it: every dimension with its
share of the total, the points it is costing, a meter, and its deductions nested
underneath it. Ordered by what each dimension is actually costing rather than by
declared weight — the dimension losing the most points is the one worth reading
about. Shares are recomputed against the dimensions that ran, because the server
re-normalises weights when one does not apply.

Shown from Professional mode. The Fixes tab still answers "what next", ordered
by return on effort; this answers "why", ordered by cost. Different questions,
different orderings, which is why they are separate tabs rather than one list.

### Added — measurements and queue health

* Each dimension's raw metrics, collapsed behind a disclosure, at Agency mode.
  Useful when arguing with a score, meaningless as a to-do list — which is why
  they are not in front of everyone.
* Background work broken down by queue, at Enterprise mode. The server has
  reported this split since 0.4.0 and nothing showed it, so "400 jobs pending"
  and "400 jobs pending, all of them waiting on the model provider" looked
  identical. The dashboard type did not even declare the field.

### Removed

* The `api_keys` feature flag. The public API is nonce- and rate-limited rather
  than key-based, so the flag advertised a system that does not exist. The
  Enterprise description no longer claims API credentials. Inventing the system
  to justify the copy would have been the wrong way round.

## [0.9.0] — 2026-08-03

The dead-option audit from 0.8.0, widened to hooks, capabilities, tables, jobs
and REST routes. Three findings survived triage.

### Added — the audit log has a reader

The log has been written to since the first release, has its own capability and
its own endpoint, and 0.7.0 gave it an Enterprise feature flag. Nothing ever
read it, so Enterprise mode's headline addition added nothing and every recorded
event was write-only.

That matters most for the AI Writer. Every generated sentence it accepted and
every one it discarded is recorded with the model, the support ratio and the
figures that were not in the page — and "which words on this page did a model
write?" is exactly the question a clinical reviewer asks. An answer nobody can
read is not an answer.

Entries render as sentences rather than a slug plus a JSON blob, because the
reader is a compliance reviewer rather than a developer. Unknown actions fall
through to the raw slug instead of being hidden: a log that silently omits
entries is worse than an ugly one.

The page is gated on `medora_view_audit_log` — its own capability, not a
consequence of seeing the dashboard.

### Documentation

* Thirteen hooks existed in code and appeared in no document:
  `medora_entity_updated`, `medora_entity_deleted`, `medora_citation_attached`,
  `medora_author_profile_updated`, `medora_crawler_policy_changed`,
  `medora_sitemap_updated`, `medora_robots_headers`,
  `medora_entity_type_valid`, `medora_llms_txt_per_type`,
  `medora_llms_txt_exclude`, `medora_admin_menu_title`,
  `medora_admin_page_title`, `medora_update_endpoint`. All are now in the API
  reference, along with `medora_option` and the `/audit-log` endpoint.
* **Corrected a claim from 0.7.0.** Experience mode was documented as leaving
  hidden surfaces "reachable by URL". That is wrong for admin pages: a submenu
  that is not registered is a URL WordPress declines to render. The REST API is
  what stays untouched — every endpoint remains registered and capability-gated,
  so the data behind a hidden screen is fully reachable by anyone entitled to
  it. The conclusion is unchanged and the mechanism now described accurately;
  the wording is fixed in the security doc, the class docblock, the settings
  copy and the readme.

## [0.8.0] — 2026-08-03

Found by auditing every declared option for a consumer, after the same pattern
turned up real bugs in 0.7.0 and 0.7.1. Two of the thirty-four had none.

### Fixed — the content language was taken from the admin

Schema's `inLanguage`, and the language the AI Writer was told to write in, both
came from `get_locale()` — which describes the person in wp-admin, not the
pages. A Persian clinic running an English-locale WordPress is an ordinary
setup, and it produced `inLanguage: "en-US"` on every Persian page: a wrong
signal handed to precisely the AI crawlers this product exists to signal
correctly. The same source told the AI Writer to summarise Persian content in
English.

`Support\ContentLanguage` resolves it properly, in this order:

1. the `medora_content_language` filter, which carries the post — where
   Polylang and WPML belong, because on a multilingual site the language is a
   property of the page and no site-wide setting can be right;
2. the `default_language` setting;
3. `get_locale()`, exactly as before.

It now drives `WebSiteNode`, `WebPageNode`, `PackGenerator` and a new
`Language:` line in llms.txt, which previously left a model to infer the
language from a sample of titles. It also exposes `isRtl()` derived from the
content rather than from `is_rtl()`.

`default_language` was the dead setting; it now has this job. Settings gains a
control for it.

### Changed

* `default_language` now defaults to empty, meaning "follow WordPress". A
  migration (DB 1.1.0) clears a stored `'en'`, which 0.7.x wrote at activation
  and never read — leaving it in place would have started declaring English on
  sites that are not in English.
* Removed the `llm_provider` setting. It was declared, read by nothing, and
  could only ever hold one value; `medora_llm_provider` is the swap point and
  always was.

### Fixed — the unit test bootstrap stubbed hooks out

`apply_filters()` returned its input and `do_action()` did nothing, so any
filter-driven branch was untestable and any test asserting filter behaviour
passed without running the callback. Both now dispatch through a real registry
with priority ordering, alongside `add_filter`, `add_action`, `get_locale` and
`medora_test_reset_hooks()` — which tests must call, since the registry is
global and callbacks otherwise leak between files.

This is the second harness found lying this way; the module wiring harness had
the same stubs until 0.5.0.

## [0.7.1] — 2026-08-03

### Fixed — the white-label accent colour never applied

`BrandingManager` published the chosen colour as `--medora-accent` on `:root`.
The stylesheet declares the same custom property on `.medora-app` — a nearer
ancestor, which wins — so every agency's accent colour was overridden for the
whole dashboard. The style tag was present in the markup and nothing errored,
which is why it went unnoticed.

The colour is now published as `--medora-brand-accent`, and `.medora-app` reads
it as the source of its own token:

```css
--medora-accent: var(--medora-brand-accent, #2f6df6);
```

### Added — a white-label panel

White-labelling worked but had no interface: the only ways in were the
`medora_branding` filter or a hand-written REST PATCH. Settings now has a panel
— product name, menu label, vendor, support URL, accent colour, and whether to
rewrite the Plugins screen row — shown at the Agency experience mode, which
gives the `white_label` feature flag added in 0.7.0 its consumer.

The panel states that branding forced from code by a SaaS host wins over
anything saved there, rather than leaving an agency wondering why their name
will not stick.

### Security

White-label values are now sanitised for where they end up rather than through
the generic array path. `vendor_url`, `support_url` and `logo_url` go through
`esc_url_raw`, so a `javascript:` or `data:` URI is dropped at the boundary
instead of being stored and left to every consumer to escape — these are
written into the Plugins screen's `AuthorURI` and `PluginURI`. `accent_color`
must match a hex pattern to be stored at all, which is now checked in two
places: before storage, and again before it reaches the style block.

## [0.7.0] — 2026-08-03

### Added — experience modes actually do something

`experience_mode` was collected by the setup wizard, stored, and echoed back by
the settings endpoint. Nothing read it. Beginner and Enterprise installs got a
byte-identical dashboard, so the four modes the product advertises did not
exist.

`Admin\ExperienceMode` now decides which surfaces appear:

| Mode | Adds |
|---|---|
| Beginner | the working loop only — overview, content, fixes, brief, crawlers, settings |
| Professional | entities, referral analytics, passages, links, score breakdown |
| Agency | knowledge graph, module control, white-label, raw metrics |
| Enterprise | audit log, API credentials, queue health |

It drives the admin submenu, the Content screen's sub-tabs, the Modules card,
and the setup wizard's copy and its accepted values — all from one table, so a
fifth mode is one entry rather than five edits.

**It is presentation, never authorisation.** Every hidden surface stays
reachable by URL and stays served by the REST API, because the security
boundary is `Capabilities` and a second place to get authorisation wrong is
worse than none. Unknown features are *shown*, not hidden — failing open is
right for a presentational filter and would be wrong for a permission. The
in-code documentation, the settings copy and the wizard copy all say so, so
nobody mistakes a hidden menu item for a locked door.

Two invariants are pinned by tests because they are easy to break while editing
the feature table:

* **The beginner loop is complete.** Score, problem, fix, and the settings
  needed to act — a mode that hides part of that leaves someone with no path
  forward.
* **Modes are monotonic.** Moving up never removes a screen, so "switch to
  Agency" cannot cost someone the panel they were using.

New filter `medora_experience_shows` to override a single surface.

### Changed

* The setup wizard's mode descriptions and its accepted values are both derived
  from `ExperienceMode::choices()`, so the wizard cannot describe a mode
  differently from the class implementing it.
* Settings gains a "How much to show" control, so the choice is not locked in
  at setup time.
* The unit bootstrap's `get_option()` shim now reads from
  `$GLOBALS['medora_test_options']`, letting a test stand up a configuration
  without replacing `Options` — which is `final`, correctly.
* Template regenerated: 672 strings.

## [0.6.0] — 2026-08-03

Makes the plugin actually translatable. The wiring was in place —
`load_plugin_textdomain()` and `wp_set_script_translations()` were both called —
but the translation template was a header with no strings in it, so there was
nothing for a translator to translate.

### Fixed — facet identity was locale-dependent

`TopicCoverage` keyed its coverage facets by their *translated* label, so the
same page produced `Symptoms` in English and `علائم` in Persian. Everything that
matched on the key stopped matching the moment the site was translated:

* `ContentBrief::headingFor()` compared the facet against `__($key, …)` with a
  variable key. gettext cannot extract a variable, so that call had no
  translation and always returned the English string — meaning the comparison
  was dead code and, on a translated site, **every** brief section fell through
  to the generic `"facet — title"` fallback instead of its real heading.
* `RecommendationEngine` built action codes with `sanitize_key($label)`, which
  strips non-ASCII. On a Persian site every "add a section" action collapsed to
  the same empty code.

Facets now carry stable slugs (`symptoms`, `when_to_seek_care`, …) and are
translated only at the point of display, via `TopicCoverage::label()`.
`SemanticAnalyzer` returns both forms explicitly: `missing_concepts` is display
text, `missing_facets` is identity. Nothing changes on an English site — the
slugs match what `sanitize_key()` produced before — so no re-analysis is needed.

**Breaking for extenders:** the `medora_topic_facets` filter now receives slugs
as keys rather than labels. New filter `medora_topic_facet_label` registers a
label for a custom facet.

### Added — translation toolchain (`bin/`)

Neither tool needs WP-CLI, Composer or node_modules: a translator with a
checkout and PHP can do the whole cycle.

* `bin/make-pot.php` extracts the template. PHP is read through
  `token_get_all`, so concatenation, calls inside comments, and methods that
  merely share a name with a gettext function are all handled exactly.
  **662 strings** now, from zero.
* It also lints. A call whose text domain is missing, wrong or non-literal, a
  message built from a variable, or a multi-placeholder string with no
  translators comment — each is reported rather than skipped, because a string
  that silently fails to extract is a string that silently ships untranslated.
* `bin/make-l10n.php` compiles `languages/*.po` into the two artefacts
  WordPress actually loads: binary `.mo` for PHP, and the Jed-format JSON whose
  *filename* encodes an md5 of the script path for
  `wp_set_script_translations()`. Get that hash wrong and the dashboard stays
  English while the menu around it translates.
* `composer i18n`, `i18n:check`, `i18n:build`; `i18n:check` joins
  `composer check` and runs as its own CI job.

### Fixed — build

* The CI workflow's own path filter named `ci.yml`, a file that does not exist,
  so edits to the workflow never triggered it.
* The JS extractor's first version matched call bodies with a non-greedy regex,
  which closes at the parenthesis inside `'%d thing(s) left'` and truncates the
  call to one argument — indistinguishable from a missing text domain. Eleven
  strings were being dropped. Call extents are now found by a paren scanner
  that understands string literals.

### Notes

* Compiled `.mo` and `.json` are gitignored alongside the other build outputs:
  the repository holds `.po` sources, the release artefact holds builds.
* **The fa_IR and ar catalogues are not written.** The toolchain, the template
  and the loading path are all in place and verified; what remains is the
  translation itself, which is a content task needing a native reviewer rather
  than an engineering one.

## [0.5.1] — 2026-08-03

### Fixed — analyses cached under the wrong configuration

The analysis cache was keyed on the page's content hash alone. A stored score
is a function of the *scorer set* as much as of the text, so any change to the
scoring configuration left every unedited page holding a score computed the old
way — and the site report then ranked six-dimension scores against
seven-dimension ones as though they were comparable.

The bug predates this release; adding a seventh dimension in 0.5.0 is what made
it visible. It failed silently, which is the reason it went unnoticed: nothing
errors, and no individual page looks wrong.

* `AuthorityScoreCalculator::signature()` fingerprints the scoring
  configuration — scorer ids *and* weights, since re-weighting moves every
  score without changing which scorers ran. Sorted, so a change in registration
  order (which the module dependency graph may legitimately make between
  releases) does not needlessly invalidate the whole site.
* `cacheKey()` folds that signature into the content hash, so the change is
  self-invalidating with no schema migration.
* `ReanalyzeSiteJob` re-scores the archive in batches of 100, re-queueing
  itself. Queueing one job per published post is fine on a fifty-page site and
  inserts fifty thousand rows in a single request on a large one.
* The sweep is triggered by the three events that change what a score means
  without touching content: an upgrade, a module toggle, and a general↔medical
  mode switch. Mode is the case the signature cannot catch on its own —
  `appliesTo()` is evaluated per post at score time, so switching mode changes
  which dimensions apply without changing which are registered.

`medora_settings_updated` carries the full settings array rather than the
changed keys, so the previous mode is recorded in `medora_scored_mode` and the
sweep runs only on a real transition, not on every save.

## [0.5.0] — 2026-08-03

Adds the GEO Optimizer, the last of the twenty named modules without a
counterpart in the codebase, and with it a seventh score dimension.

### Added — GEO Optimizer module (`geo`)

The module's contribution is its unit of analysis. Every other scorer reads the
page; a retriever does not. It pulls one chunk, hands that chunk to a model, and
the model answers from it alone. A page can be thorough, sourced and well-linked
while most of its paragraphs are unquotable on their own — and nothing in the
platform measured that until now.

* `Geo\PassageAnalyzer` scores every chunk on whether it survives being read
  cold. Six lints, each naming a specific edit:

  * **Dangling connective** — the passage opens with "However" / "بنابراین",
    asserting a relationship to an argument it does not contain.
  * **Dangling pronoun** — it opens with a pronoun pointing outside itself.
    `it`/`they` always flag, since they cannot introduce a noun; `this`/`این`
    flag only when standing alone, because "This condition affects…" names its
    own subject and survives retrieval.
  * **Page reference** — "as mentioned above", "در ادامه". Once retrieved, the
    passage has no above and no below.
  * **Subject absent** — the passage never names what the page is about, so
    there is nothing for a query to match against.
  * **No heading**, and **too short** to be an answer at all.

* `Geo\StructureAnalyzer` counts the surfaces an assistant can lift whole —
  tables, procedures, lists of three or more, question-form headings. Not a
  demand that pages become lists: a check that a long page which *is*
  enumerating something has said so in markup, because the markup gets quoted
  and the paragraph gets paraphrased.

* `Geo\LlmCompatibilityScorer` adds the `llm_compatibility` dimension at weight
  0.14. Its deductions name passages by index and heading, so the fix list
  points at a paragraph rather than at the page.

* `GET /geo/{id}` returns the full passage report; the Content screen gains a
  **Passages** tab showing each passage with the excerpt it was judged on.

The module writes nothing and changes no published output. It is entirely
measurement, because the edits it asks for are editorial and belong to the
author.

### Changed

* The unit-test bootstrap now provides a minimal `WP_Post`.

### Notes

* Both new word lists are folded through `Text::normalize` at comparison time
  rather than written pre-folded, so they keep matching if normalisation learns
  another character. "آیا" normalises to "ایا"; a hand-folded literal would
  silently stop matching the day that changed.
* Known limitation, accepted deliberately: a demonstrative followed by a real
  noun passes even when the noun anchors nothing ("این نشان می‌دهد"). Catching
  those needs a parser, and the pattern that catches them also fires on correct
  writing. A lint people learn to ignore is worse than one that misses cases.

## [0.4.0] — 2026-08-03

Adds the generative layer the product has so far deliberately gone without —
and the check that makes it defensible.

### Added — AI Writer module (`llm`)

* `Llm\LlmProviderInterface` with an Anthropic implementation. One synchronous
  completion, no streaming, no tool use, no conversation state: everything
  Medora generates is a short, bounded, single-turn transformation of content
  the site already published.
* `Llm\Grounding` — the reason generation is allowed at all. Every generated
  field is checked against the page it came from before it can be published:

  * **Lexical support.** Content words in the output must appear in the source.
    Paraphrase legitimately introduces some vocabulary, so this is a ratio
    (0.82 site-wide, 0.6 per sentence), not a rule.
  * **Numeric fidelity.** Any figure in the output must appear verbatim in the
    source, with zero tolerance. Persian and Arabic-Indic digits, the three
    different comma characters and trailing decimal zeros are all normalised
    first, so "۱٬۵۰۰" in the page vouches for "1500" in the output.

  Prompt instructions are a request; this is verification. Output that fails is
  discarded and the rejection is recorded in the audit log with the support
  ratio and the offending figures.
* Field-level adoption. A page can end up with a generated summary and an
  extractive canonical answer — partial adoption beats an all-or-nothing gate
  that discards three good fields because a fourth invented a number.
* Generated answers are accepted only for questions the extractive pass left
  blank, and only for questions already in the pack. Questions the model
  invents are dropped: the question set comes from the site's own headings and
  FAQ blocks, and a model adding to it would be putting words in the
  publisher's mouth in the most literal sense.
* `GeneratePackJob`, on its own `llm` queue. Generation never runs in the
  request that saved a post, and never on a page view. A provider failure
  costs the page nothing, because the extractive pack was already saved.
* New action `medora_prompt_pack_saved`; new filters `medora_llm_provider`,
  `medora_llm_request_body`, `medora_llm_language`.

### Added — dashboard and operations

* Settings → AI Writer: the opt-in, the model, and a plain statement of what
  changes when it is on.
* `JobQueue::stats()` now reports a per-queue breakdown. 400 pending jobs is a
  backlog; 400 pending jobs all in the `llm` queue is a provider that stopped
  answering, and those are different problems.
* `MEDORA_LLM_API_KEY` follows the same precedence as the embedding key —
  environment, then constant, then database — and the security scanner flags
  the database case.

### Fixed

* `Grounding` originally folded U+066B (Persian decimal separator) and U+060C
  (Arabic comma) but not U+066C (Arabic thousands separator), so "۱٬۵۰۰" parsed
  as the two figures 1 and 500 and a faithful "1500" in the output was reported
  as fabricated. Found by the new test suite.

### Notes

* Off by default, and off in a meaningful sense: with the module disabled or no
  key configured, the plugin makes no outbound model call and every summary on
  the site is the publisher's own sentences.
* The Anthropic provider calls the Messages API with `wp_remote_post` rather
  than the official SDK package. This plugin ships zero runtime PHP
  dependencies by design (docs/04-security.md): a plugin is installed by
  unzipping it, Composer is not available at the install site, and two plugins
  bundling different versions of the same package in one process is a fatal
  error nobody can debug. The request is pinned to `anthropic-version:
  2023-06-01`.

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
