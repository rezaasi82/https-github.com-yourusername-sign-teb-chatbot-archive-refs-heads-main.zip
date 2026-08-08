# 08 — Translation and review

Target locales are **Persian (fa_IR) first, then English**. Arabic is out of
scope; the note in `bin/merge-po.php` explains what adding it would take.

The Persian catalogue is **complete and unreviewed**. All 741 strings are
translated, by an assistant rather than by a Persian-speaking editor. The
terminology is consistent and the grammar is sound, but register and idiom are
exactly what a non-native draft gets subtly wrong, and this is a medical
product whose readers are clinicians. Completeness is not correctness.

## What is already checked, and what is not

Everything mechanical is checked, and currently clean:

| Checked | By | Status |
|---|---|---|
| Placeholders match the source | `make-l10n.php`, refuses to compile | 0 mismatches |
| Every string is extractable | `make-pot.php --check` | 741 of 741 |
| Template is current | `make-pot.php --check` in CI | current |
| Arabic letterforms (ي ك ة) | `review-sheet.php` | 0 |
| Arabic-Indic digits (٠-٩) | `review-sheet.php` | 0 |
| Nothing left untranslated | `merge-po.php` | 741 of 741 |

None of that says the Persian reads well. That is the entire remaining job, and
no tool can do it — which is why the review is a person's, and why the tooling
below is aimed at making that person fast rather than at checking their work.

## Reviewing

```bash
composer i18n:review        # → dist/review-fa_IR.html
```

Open that file in a browser. It is a single self-contained page — no server, no
install, nothing uploaded.

Strings are **grouped by the screen they appear on**, because register is a
property of a screen rather than of a string: "Close" on a dialog and "Close" in
a report want different words, and that is only visible when a string sits next
to its neighbours. Each row shows the English source, the current Persian in an
editable box, the translator note where there is one, and the source reference.

Three things are worth knowing:

- **Progress is saved in the browser.** Edits and the "تأیید شد" ticks survive
  closing the tab, so the 741 strings can be done over several sittings. The
  filter dropdown narrows to what is left.
- **Placeholders are checked as you type.** A dropped `%s` is the one
  translation error that becomes a crash rather than a typo — `sprintf()` throws
  on a missing argument, so the screen goes down in Persian only. The row warns
  immediately.
- **The glossary panel is pinned at the top.** Those terms are fixed across the
  product. Changing one is a decision for every string that uses it, so note it
  rather than changing a single row.

The **دارای جانگهدار** filter is worth a pass on its own. Those are the strings
where a substituted value lands mid-sentence, and word order around it is where
a fluent-looking translation most often turns out to read badly once there is a
real number in it.

## Applying the corrections

Press **دریافت اصلاحات**. It downloads only the strings you changed, as
`medora-authority-fa_IR.review.php`. Then:

```bash
php bin/apply-review.php fa_IR path/to/medora-authority-fa_IR.review.php --dry-run
php bin/apply-review.php fa_IR path/to/medora-authority-fa_IR.review.php
composer i18n:build
```

The dry run prints every change as a before/after pair. Corrections are all
validated before anything is written, and one bad entry stops the whole run: a
half-applied review is worse than an unapplied one, because nobody can tell
which half.

## Where corrected text lives

Corrections are written to **the `.po`, and only the `.po`**.

`merge-po.php` prefers an existing `.po` translation over the map, precisely so
reviewed text survives a re-merge after a code change. That makes the `.po` the
authority and leaves `*.map.php` as what its header says it is — the unreviewed
draft it started as. Two writers for one string is how a catalogue ends up
disagreeing with itself.

So: **edit the `.po` (or the review sheet), never the map.**

## The whole loop

```
code change
   ↓  composer i18n         regenerate the template
   ↓  composer i18n:merge   carry translations across, keeping reviewer edits
   ↓  composer i18n:review  build the sheet
   ↓  (a person reads it)
   ↓  bin/apply-review.php  write corrections into the .po
   ↓  composer i18n:build   compile the .mo and the JS catalogue
   ↓  composer package      the ZIP that ships them
```

## Adding a locale

1. `php bin/merge-po.php <locale>` — writes an empty `.po` from the template.
2. Translate it, or drop a `languages/medora-authority-<locale>.map.php` and
   re-merge.
3. Add the plural rule to `PLURALS` in `bin/merge-po.php` if the language is
   not two-form. English's `n != 1` is the fallback and is wrong for many
   languages.
4. `composer i18n:build`.

A partial catalogue is a working catalogue — gettext falls back per string, so
translated strings appear and the rest stay English. There is no need to wait
for 100% before shipping a locale.
