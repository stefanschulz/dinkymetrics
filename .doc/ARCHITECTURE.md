# DinkyMetrics — Technical Architecture

Primary technical reference for developers and AI agents working on this codebase.

**Version**: 1.0.0
**Last Updated**: September 2026

---

## What this is

A Joomla **site module** (`mod_dinkymetrics`) that renders a row of key figures — a number
with a caption each — and optionally counts them up when they scroll into view. Values are
typed in or counted from `#__content` / `#__categories`.

No database tables, no admin views, no AJAX. Two media assets (one CSS file, one ES
module), no third-party JavaScript. Everything is driven by module parameters. The
original brief is in
[joomla-dinkymetrics-module-SPEC.md](joomla-dinkymetrics-module-SPEC.md) — partly
superseded, see its status note; the phased build record, including every decision
and trap, is in [WORKPLAN.md](WORKPLAN.md).

---

## File structure

```
dinkymetrics/
├── mod_dinkymetrics.xml              # Manifest: namespace, files, media, the parameter form, update server
├── services/provider.php             # DI: ModuleDispatcherFactory + HelperFactory + Module
├── src/
│   ├── Dispatcher/Dispatcher.php     # Resolves figures through the module cache, builds the cache key
│   ├── Field/
│   │   ├── DinkyfiguresField.php     # SubformField subclass; ships its own layout (see layouts/ below)
│   │   └── FigureSummary.php         # Builds the one-line row summary for the admin edit screen
│   └── Helper/
│       ├── DinkyMetricsHelper.php    # getFigures() orchestration, resolve() switch, link validation, logging
│       ├── Counter.php               # The two COUNT(*) queries — nested sets, dates, access, featured
│       ├── Formatter.php             # Number → string, ICU with a no-intl fallback (pure)
│       └── Elapsed.php               # Date parsing and whole-unit differences (pure)
├── layouts/field/subform/
│   ├── dinkymetrics.php              # Figures subform wrapper (admin edit screen only)
│   └── dinkymetrics/row.php          # One row: collapsed summary, <details> for the full fields
├── tmpl/default.php                  # The markup contract; overridable per template
├── media/mod_dinkymetrics/
│   ├── joomla.asset.json             # Declares mod_dinkymetrics.{style,script,admin-figures}
│   ├── css/{dinkymetrics,admin-figures}.css   # Site (opt-in, no colours) / admin edit screen
│   └── js/{dinkymetrics,admin-figures}.js     # The count-up / keeps a collapsed row's summary in step
├── language/{en-GB,de-DE}/           # mod_dinkymetrics.ini (+ .sys.ini)
├── tests/
│   ├── Unit/                         # Formatter + Elapsed, no Joomla needed
│   ├── Integration/counts.php        # The counting queries against the .docker fixtures
│   └── parity/                       # cases.json + parity.mjs — server ↔ browser formatting
└── build.xml                         # Phing target `package`: zip + update.xml with checksums
```

---

## Data flow

```
request
  → Dispatcher::getLayoutData()
      → ModuleHelper::moduleCache(cachemode 'id', key = module + params + Itemid + language [+ view levels])
          → DinkyMetricsHelper::getFigures(params, app)
              ├── Formatter          once per instance (locale, decimals, grouping)
              └── per subform row:
                    resolve() ──switch(source)──┬── literal      → the typed number
                                                ├── years_since  → Elapsed::parse + ::since
                                                ├── content_count→ Counter::articles()
                                                └── category_count→ Counter::categories()
                    → format, build aria-label, validate the link
  → tmpl/default.php
      → registers the web assets, prints the markup with the finished values
  → media/.../dinkymetrics.js
      → counts up to the value already in the DOM, then restores the server's own string
```

Rows without a caption and rows that resolve to `null` never reach the layout. An empty
result renders nothing at all.

---

## The invariants

Break any of these and something subtle goes wrong, so they are worth stating plainly.

1. **The finished value is always in the HTML.** The animation is decoration. Every failure
   path — reduced motion, a bad locale, no JavaScript, a hidden tab where
   `requestAnimationFrame` never fires — must leave the server-rendered number standing.
2. **Server and browser format identically.** `Formatter` and the one `Intl.NumberFormat`
   call in the JS have to agree, or the figure visibly changes when the tween ends.
   `tests/parity/cases.json` is the shared truth; both sides are tested against it. ICU
   defaults to half-even rounding and `Intl.NumberFormat` to half-away-from-zero, so the
   PHP side sets `ROUND_HALFUP` explicitly. Decimal places are a ceiling on both sides
   (`MIN_FRACTION_DIGITS=0` / `MAX_FRACTION_DIGITS=$decimals`, matching
   `Intl.NumberFormat`'s default `minimumFractionDigits` of 0) — a whole-number figure
   never grows a fake ".0" just because the instance's decimal setting serves some other,
   genuinely fractional figure in the same list.
3. **The tween ends on the server's string, not on a recomputed one.** The JS captures
   `textContent` at start and writes it back at the end; no rounding step can drift.
4. **`resolve()` dispatches on `source` only.** Joomla's subform saves *every* field of
   *every* row, hidden ones included, each with its default — a `years_since` row carries
   `featured` and `state` too. Never infer a row's meaning from which keys are present.
5. **Counting is not Joomla's category badge.** Publishing dates, access levels and the
   category's own published state all apply. See decisions E2/E3 in the workplan.

---

## Adding a new source

1. Add an `<option>` to the `source` field in `mod_dinkymetrics.xml`, plus any fields it
   needs with `showon="source:your_source"`, and the language keys in **both** languages.
2. Add a case to `DinkyMetricsHelper::resolve()` and a private method next to the existing
   ones. Return `null` when the row cannot produce a number — the caller drops it.
3. If it queries the database, put the query in `Counter` and give it explicit, typed
   parameters; whitelist enum-like values in the helper before they get there.
4. Add expected values to `.docker/fixtures.sql` and checks to
   `tests/Integration/counts.php`.

The helper is a plain `class` (not `final`) and `resolve()` is public, so a site can also
subclass it instead of patching.

---

## Traps that cost time here

Recorded so they cost nobody time twice. The full accounts are in the workplan.

- **The asset URI omits the `css`/`js` folder.** `mod_dinkymetrics/dinkymetrics.css`, not
  `mod_dinkymetrics/css/dinkymetrics.css` — Joomla inserts the folder itself. Get it wrong
  and the asset is skipped silently, with no error anywhere.
- **`joomla.asset.json` is not read automatically.** The layout must call
  `$wa->getRegistry()->addExtensionRegistryFile('mod_dinkymetrics')` first.
- **The extension namespace map is only rebuilt when it is missing.** After adding a class
  during symlinked development, delete `administrator/cache/autoload_psr4.php` or
  `ModuleDispatcherFactory` quietly falls back to the generic dispatcher and `src/` never
  runs.
- **`XML_DESCRIPTION` belongs in the `.ini` as well as the `.sys.ini`**, because the module
  edit form loads only the former.
- **A repeatable subform's clone-rename only touches elements with a `name` attribute**
  (`joomla-field-subform.js`'s `fixUniqueAttributes()` walks `row.querySelectorAll('[name]')`).
  Anything else you hand-author with an id referencing the row's group — a `calendar`
  field's button (`id="…figuresX…"`, no `name`; loses picked dates in rows added in the
  browser, hence a plain text field instead), or this module's own per-row modal id — stays
  literally `"figuresX"` in every newly added row. The modal id is instead set from
  `row.dataset.group` in `admin-figures.js`, on the bubbling `subform-row-add` event the
  component fires right after it has done its own renaming — no need to reimplement it.
- **A `requestAnimationFrame` timestamp can predate the tween's start time**, so the
  progress has to be clamped at both ends or the first frame flashes a negative number.
- **A custom form field type needs no `field/` folder.** Set `addfieldprefix` on
  `<config>` in the manifest to the field class's namespace; Joomla finds
  `<prefix>\<Ucfirst(type)>Field` through the extension's own PSR-4 map. A field that
  ships its own layout only needs to override `getLayoutPaths()` (prepend the module's
  `layouts/` folder) — `FileLayout::sublayout()` passes the same include paths down to
  sub-layouts, so one override covers the whole chain.
- **The subform's drag-to-reorder is native, not a library** — `joomla-field-subform.js`
  wires plain HTML5 `draggable` to whatever carries the `.group-move` class, inside a
  `.subform-repeatable-group` wrapper. Keep those two classes on a custom row layout and
  reordering, and its up/down-button fallback (`.group-move-up`/`.group-move-down`,
  including wrap-around at the first/last row), keep working with no extra code. Grepping
  the file for those literal class names finds nothing — they are built at runtime as
  `` `${buttonMove}-up` `` — so confirm by clicking the button, not by reading the source
  and concluding it is unwired.
- **Atum 6 defines two, non-interchangeable sets of colour tokens.** Bootstrap 5.3's own
  de-prefixed tokens (`--border-color`, `--body-color`, `--secondary-color`,
  `--tertiary-bg`, …) are redefined per `[data-bs-theme]` and are genuinely dark-mode-safe;
  the classic Sass-derived ones (`--dark`, `--gray-600`, `--primary`, …) exist only in the
  light-theme block and keep their light value under dark mode. Neither set is guessing
  material — use Bootstrap's own utility classes in the markup (`bg-body-tertiary`,
  `text-body-secondary`, `border`) instead of a hand-picked `var(--name, #fallback)`; a
  wrong guess (`--component-bg` does not exist here) fails silently to the fallback colour,
  which reads fine in light mode and can render invisible text in dark mode.
- **Every Bootstrap modal in the Joomla admin has its padding zeroed** by Atum
  (`.modal-dialog .modal-body{padding:0}`, `.modal-header{padding:0 15px}` — built for
  modals whose content is an iframe) and its fields render side-by-side unless a
  `form-vertical` ancestor is present (`.form-vertical .control-group{flex-direction:
  column}`, present on the real edit form, absent from a bespoke modal). A custom modal
  has to supply both itself.
