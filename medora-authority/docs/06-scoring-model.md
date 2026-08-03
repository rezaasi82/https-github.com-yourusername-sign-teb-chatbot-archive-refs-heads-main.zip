# The AI Authority Score

## What it measures

A single 0–100 figure answering one question: **if an AI system needed a source
on this topic, is this page a defensible choice?**

That is not the same question a traditional SEO score answers. A page can rank
well and still be uncitable — because its conclusion is buried under 800 words
of preamble, because its sections do not stand alone when retrieved
individually, or because no accountable author can be resolved from it.

## Two non-negotiable properties

**Every deduction is explained.** Scorers start at 100 and deduct, rather than
accumulating from zero. The deduction list *is* the derivation, so a score can
never be a number the UI has to reverse-engineer. Every deduction carries the
points lost, the severity, and the specific fix.

**Weights re-normalise.** Dimensions that do not apply are skipped and the
remaining weights are scaled to sum to 1. A non-medical site is never
permanently capped below 100 for lacking a clinical reviewer.

## Dimensions

| Dimension | Weight | Applies to | Question |
|---|---|---|---|
| AI Readiness | 20% | all | Can an AI system reach and parse this at all? |
| Entity Authority | 18% | all | Is the subject unambiguous and resolvable? |
| Semantic Strength | 18% | all | Does the page convey knowledge, not keywords? |
| Medical Trust | 20% | medical mode | Does the YMYL evidence chain hold up? |
| Knowledge Quality | 12% | all | Is it maintained, sourced and connected? |
| Prompt Quality | 12% | all | Is the machine-facing representation good? |
| LLM Compatibility | 14% | all | Does each passage still make sense alone? |

The weights above are the declared ones; because they are re-normalised, adding
or disabling a dimension shifts every other dimension's share of the total. A
site that turns the GEO Optimizer off does not lose 14 points — the remaining
six dimensions expand to fill the score.

### LLM Compatibility — the page as it is actually read

The only dimension that does not score the page as a document. A retriever
returns one chunk and the model answers from that chunk alone, so this dimension
asks of every passage: read cold, with nothing around it, does this say what it
is about?

| Check | Max penalty |
|---|---|
| Passages that do not stand alone | 55, scaled by the fraction that fail |
| Long page with no table, list or procedure | 20 |
| No H2/H3 headings at all | 15 |
| No heading phrased as a question | 10 |

The passage checks are lints and are tuned to under-report: a demonstrative
followed by a real noun ("This condition affects…", "این بیماری…") passes even
when the noun anchors nothing, because the rule strict enough to catch that also
fires on correct writing. Missing a case costs one deduction; a check people
learn to ignore costs the whole dimension.

Passages come from the same `Chunker` the Vector Engine embeds with, so this
scores the text that is genuinely retrieved rather than a separate model of it.

### AI Readiness — the gate

Everything else is wasted if the page is noindexed, blocked to the crawlers
that matter, or shipping broken structured data.

| Check | Max penalty |
|---|---|
| Page or site excluded from indexing | 40 / 30 |
| Recommended AI crawlers blocked | 25 (3 per crawler) |
| Schema disabled or failing validation | 20 |
| llms.txt not published | 8 |
| AI sitemap not published | 7 |

Blocking *training* crawlers is a legitimate editorial choice, so the penalty
scales with how many are blocked rather than being flat — and classic Googlebot
is never blocked by a preset, because that is an SEO own-goal.

### Entity Authority

Zero if no entity is detected at all — a page whose subject cannot be
identified cannot be matched to a query.

| Check | Penalty |
|---|---|
| No clear primary subject (top salience < 0.35) | 30 |
| No entity linked to an external identifier | 25 |
| Entities left generically typed (>80%) | 20 |
| Thin site-wide coverage of the subject | 15 |

The `sameAs` penalty is the one most sites fail. Without a Wikidata, MeSH or
official-site link, an AI system has no way to match your "Hepatology" to the
one in its own knowledge base.

### Semantic Strength

Composed from five sub-metrics, weighted toward what actually moves citation:

- **Answer readiness (28%)** — is a direct answer in the first screenful?
  Answer engines overwhelmingly quote from the opening.
- **Chunk integrity (22%)** — can each section be understood alone? A chunk
  opening with "This is why it matters" is useless when retrieved in isolation.
- **Knowledge density (20%)** — distinct concepts per word, normalised against
  the natural decay of type-token ratio with length.
- **Topic coverage (18%)** — are the facets a complete treatment needs present?
  Facet sets are question-shaped, because that is how people query assistants.
- **Context quality (12%)** — does the page define its terms, cite outward and
  carry checkable figures?

Readability is measured structurally — sentence length, paragraph length,
heading density, list usage — rather than with Flesch or Gunning Fog. Those
formulas are English-specific and depend on syllable counting that is
meaningless in Persian or Arabic.

### Medical Trust (YMYL mode only)

| Check | Penalty |
|---|---|
| Author has no stated credentials | 30 |
| No named medical reviewer | 25 |
| No clinical citations | 20 |
| Review over two years old | 15 |
| No review date | 10 |
| No author scholarly profile | 10 |
| No medical disclaimer | 10 |

A stale review date is penalised harder than a missing one is elsewhere,
because guidance changes and a two-year-old sign-off actively misleads.

### Knowledge Quality

Freshness (25 for >2 years, 12 for >1 year), outbound sources (20), internal
connectivity (18), supporting media (10) and alt text (10).

### Prompt Quality

Grades the artefact the Prompt Engine produced: canonical answer present and
correctly sized (25–60 words), at least three extractable facts, at least three
distinct questions covered, a summary, and freshness against the content hash.

## Grades

| Score | Grade | Reading |
|---|---|---|
| 90–100 | A | A model would readily cite this |
| 80–89 | B | Strong; small structural gaps |
| 65–79 | C | Usable but out-competed by better-structured sources |
| 50–64 | D | Significant barriers to citation |
| 0–49 | F | Effectively invisible to AI systems |

## Prioritisation

The Content Optimizer re-sorts deductions by **points recovered per unit of
effort**, not by severity:

| Fix location | Effort weight |
|---|---|
| Settings | 1 |
| Author profile | 2 |
| Entity editor | 2 |
| Citations | 3 |
| Content editor | 5 |

A critical issue needing a week of rewriting therefore ranks below a
high-severity one that takes two minutes in settings — which is what an editor
working a backlog actually needs.

## Caching and recomputation

Scores are cached against the content hash, so re-analysis of unchanged content
is a single indexed lookup. A nightly cron re-scores the 50 most recently
modified pages so scores also track changes in *site-wide* signals — a newly
blocked crawler, a new entity, an expired licence — and not only edits.

## Extending

Add a dimension with `medora_register_scorers`, or adjust the final figure with
the `medora_authority_score` filter. See `docs/05-developer-sdk.md`.
