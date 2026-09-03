# Changelog

All notable changes to `mod_dinkymetrics` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed
- The Figures list now shows a collapsed one-line summary per row (caption,
  source, short configuration) instead of every field spelled out, expanding
  for the full editor. Adding, removing and reordering a row — including
  drag-to-reorder — are unchanged Joomla subform behaviour.

## [1.0.0] - 2026-09-03

First release. A site module that prints a row of key figures — a number plus a
caption each — with an optional count-up animation.

### Added

**Figures**
- Four sources per figure: a **fixed number**, the **time since a date** in whole
  years, months or days, the **number of articles**, and the **number of
  categories**.
- Article counting honours the category (optionally including its subcategories
  through the nested set), the publishing status *and* the start and finish
  publishing dates, featured state, language on a multilingual site, and the
  visitor's access levels.
- Category counting honours the parent (direct children or every level below),
  the component the categories belong to, their status, and can skip categories
  that hold no published article.
- Every figure takes an optional prefix, suffix and link. Internal links are
  routed by Joomla, external ones get `rel="noopener noreferrer"`, and anything
  that is not a usable URL yields no link rather than a broken one.
- Rows without a caption, and rows whose value cannot be worked out, are skipped.
  With no figures at all the module renders nothing — not even an empty wrapper.

**Output**
- The markup contract from the specification: BEM classes, `role="list"`, prefix
  and suffix in their own `aria-hidden` elements, and one `aria-label` per figure
  carrying the whole thing as a single string. The finished, formatted number is
  always in the HTML, so the module is correct without JavaScript, for search
  engines and for screen readers.
- Overridable per template under `html/mod_dinkymetrics/default.php`.
- A small opt-in stylesheet — a centred flex row, no colours, no font sizes —
  that a site can switch off with `$wa->disableStyle('mod_dinkymetrics.style')`.

**Animation**
- Counts up when the module scrolls into view or right after the page loads, with
  a configurable duration, start value and one of three easings, exactly once per
  figure.
- Visitors whose system asks for reduced motion never see it, whatever the module
  is set to.
- The tween ends by restoring the server's own string, so the number cannot drift
  by a rounding step.
- Instances that do not animate carry no animation attributes, so one page can mix
  animated and static instances with a single shared script.

**Numbers**
- Thousands grouping, a fixed number of decimal places, and a number format that
  follows the site language or an explicit language tag.
- Server and browser formatting are pinned to each other, including the
  half-away-from-zero rounding that `Intl.NumberFormat` uses and ICU does not.
  Without the `intl` extension a fallback covers the common European conventions
  and logs a warning for anything else.

**Caching**
- Figures resolve through Joomla's module cache. The key covers the module, its
  parameters, the menu item, the language and — only while the access filter is on
  — the visitor's access levels, so two visitors with different permissions never
  receive each other's numbers.

**Packaging and development**
- Joomla 5.1+ / 6.x, PHP 8.1+, no runtime dependencies.
- `phing package` builds the installable ZIP and an `update.xml` with checksums.
- Unit tests for the pure logic, an integration check of the counting queries
  against a fixture matrix, and a parity fixture shared by the PHP and JavaScript
  number formatting. A disposable Joomla test stack lives in `.docker/`.

### Notes
- The **Since** field of a figure is a text field (`YYYY-MM-DD`), not a calendar.
  In a subform row added in the browser, Joomla renames the input but not the
  calendar button, so a picked date would be silently dropped.
- **Any status** really means any: trashed items are counted too.
- A figure's number is not the same as Joomla's "Article Count" badge on a
  category page, which ignores the publishing dates and access levels.
