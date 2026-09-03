# DinkyMetrics

A tiny, generic Joomla **site module** (`mod_dinkymetrics`) that prints a row of key
figures — a number plus a caption each — with an optional count-up animation.

> since 1996 · **30** years  ·  ≈ **350** issues  ·  **18** topics  ·  **1,400** articles

Values are either typed in by the editorial team or counted from the site's own content,
so they keep themselves up to date. The module is not tied to any template: it ships one
small, opt-in stylesheet and no colours at all. Layout and looks are the job of the site,
through a template override.

- **Joomla:** 5.1+ and 6.x
- **PHP:** 8.1 – 8.4
- **Runtime dependencies:** none — no jQuery, no CDN, no Composer packages
- **Licence:** GPL-3.0-or-later

## Installation

Install the ZIP through **System → Install → Extensions**, then add a *DinkyMetrics*
module under **Content → Site Modules** and give it a position.

## Figures

Each row of the **Figures** list is one number on the page. A row needs a caption and a
source; the rest of the fields depend on which source is chosen.

| Source | What it counts |
|---|---|
| **Fixed number** | A number typed in, decimals allowed. |
| **Time since a date** | Whole years, months or days since a date. Truncated, never rounded up. A date in the future gives 0. |
| **Number of articles** | Articles in a category, optionally including its subcategories. |
| **Number of categories** | Categories below a chosen one, either the direct children or every level. |

Both counting sources share the same **Status** filter:

- **Published** — status published *and* within the start and finish publishing dates.
- **Published and unpublished** — by status only. The publishing dates are deliberately
  not applied, so an article whose publishing has not started still counts.
- **Any status, including trashed** — no status filter at all. It says what it does:
  trashed items are counted too.

Articles can additionally be filtered by **featured** state, and categories can skip the
empty ones. Every figure takes an optional **prefix**, **suffix** and **link**.

### Two things that surprise people

**The number is not Joomla's "Article Count".** The badge on a category page counts
articles with a published status and stops there. A figure on a public page must not
include articles whose publishing has not started, has finished, or that the visitor is
not allowed to see — so the same category can legitimately show 7 in the category list
and 4 here.

**Articles in an unpublished category do not count** as published. They are not reachable
on the site, so counting them would overstate what a visitor can actually find. With
*Any status* the category's own state is not considered.

## Numbers and languages

**Group thousands**, **decimal places** and **number format** apply to every figure of an
instance. Leave the format empty to follow the site language, or give a language tag such
as `de-DE`.

Server and browser must format identically, or a figure would visibly change when the
animation finishes. The module uses PHP's `intl` extension, which is the same ICU
implementation the browser's `Intl.NumberFormat` uses. **Without `intl`** a small fallback
covers the common European conventions; for anything else the separators may differ from
the browser's, and the module writes a warning to its log. Installing `intl` is
recommended — Joomla asks for it anyway.

## Access levels and caching

By default a figure counts only what the current visitor may see. Set **Count restricted
items** to *Yes* to count everything regardless of access level; every visitor then sees
the same number.

Computed figures refresh when the module cache expires. The cache key includes the module,
its parameters, the menu item, the language and — only while the access filter is on — the
visitor's access levels, so two visitors with different permissions never receive each
other's numbers.

Each counting figure costs one indexed `COUNT(*)` query per uncached render. Ten figures
are ten queries; with the module cache on, they run once per cache period.

## Animation

The count-up is decoration on top of a page that is already correct: **the finished number
is always in the HTML**. Without JavaScript, for a search engine, or if anything at all
goes wrong, the right figure is on screen.

It starts either when the module scrolls into view or right after the page loads, runs for
a configurable duration with one of three easings, and counts each figure exactly once.

Visitors whose system asks for reduced motion never get the animation. That is
unconditional — the **Respect reduced motion** switch can only turn the animation off for
everyone as well, never force it on someone who asked for less movement.

**Layout shift:** a number growing from 0 to 1,400 gets wider as it counts. The module
does not prevent that, because a fix depends on the site's typography. The bundled
stylesheet sets `font-variant-numeric: tabular-nums`; add a `min-width` in your own CSS
if the surrounding layout still moves.

## Markup and styling

```html
<div class="mod-dinkymetrics" data-trigger="in-view" data-duration="1600"
     data-easing="ease-out" data-threshold="0.35" data-locale="de-DE">
  <ul class="mod-dinkymetrics__list" role="list">
    <li class="mod-dinkymetrics__item">
      <span class="mod-dinkymetrics__value"
            data-target="348" data-from="0" data-decimals="0" data-grouping="1"
            data-prefix="≈" data-suffix="+" aria-label="≈ 348+">
        <span class="mod-dinkymetrics__prefix" aria-hidden="true">≈</span><!--
     --><span class="mod-dinkymetrics__number">348</span><!--
     --><span class="mod-dinkymetrics__suffix" aria-hidden="true">+</span>
      </span>
      <span class="mod-dinkymetrics__label">Issues</span>
    </li>
  </ul>
</div>
```

This is the contract the stylesheet and the animation rely on:

- The animation writes to `.mod-dinkymetrics__number` and nothing else.
- Prefix and suffix sit in their own `aria-hidden` elements; the whole figure is spelled
  out once in `aria-label`, so a screen reader reads it deterministically and never picks
  up an intermediate value of the count-up.
- The animated region is **not** a live region — intermediate values are not announced.
- A linked figure is a real `<a>`; internal targets are routed by Joomla, external ones
  get `rel="noopener noreferrer"`.
- Rows without a caption, or whose value cannot be worked out, are skipped. With no
  figures at all the module renders nothing — not even an empty wrapper.
- The animation attributes are only present on instances that actually animate, so a page
  can mix animated and static instances with one shared script.

**Own markup:** copy `tmpl/default.php` to
`templates/<your-template>/html/mod_dinkymetrics/default.php` and edit it there.

**Own styling:** the bundled stylesheet is a centred flex row and nothing else — no
colours, no font sizes. There are two ways to do without it.

The simple one is a layout override as above that just does not call `useStyle()`; then
neither the stylesheet nor, if you leave out `useScript()`, the animation is ever
requested.

To switch it off site-wide, disable the asset **after the modules have rendered** — the
module registers and enables it while it renders, which is later than a template file
executes. `onBeforeCompileHead` is the right moment, so this belongs in a small system
plugin:

```php
public function onBeforeCompileHead(): void
{
    $wa = $this->getApplication()->getDocument()->getWebAssetManager();

    if ($wa->assetExists('style', 'mod_dinkymetrics.style')) {
        $wa->disableStyle('mod_dinkymetrics.style');
    }
}
```

The `assetExists()` guard matters: on a page without a DinkyMetrics module the asset is
not registered at all, and disabling an unknown asset throws.

## Adding a source

`resolve()` in `src/Helper/DinkyMetricsHelper.php` is a switch over the figure's `source`.
A new source is a new case there plus a new option in the manifest's `source` field; the
helper is public and documented as overridable. See `.doc/ARCHITECTURE.md`.

## Development

The working tree *is* the extension — `mod_dinkymetrics.xml` sits at the repository root,
and the `.gitignore` is Joomla's own root ignore list, so the repo can be checked out into
a Joomla installation with only the module's files tracked.

```bash
composer install               # phpcs + phpunit (dev only)
composer run lint              # PSR-12, the way the Joomla CMS lints itself
composer run test              # unit tests on this machine
.docker/test.sh                # the same tests on a PHP that has intl — the run that counts
.docker/counts.sh              # the counting queries against the fixture matrix
node tests/parity/parity.mjs   # the browser side of the number-formatting parity check
phing package                  # build .releases/mod_dinkymetrics-<version>.zip + update.xml
```

Server and browser number formatting are pinned to each other by
`tests/parity/cases.json`; the PHP tests and `parity.mjs` both check against it.

A disposable Joomla stack for testing lives in `.docker/` — see `.docker/README.md`.

## Documentation

- `.doc/joomla-dinkymetrics-module-SPEC.md` — the original specification (partly
  superseded — see WORKPLAN.md for what actually shipped)
- `.doc/WORKPLAN.md` — phased build record, decisions and the traps found along the way
- `.doc/ARCHITECTURE.md` — how the pieces fit together
