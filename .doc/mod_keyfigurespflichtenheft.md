# Pflichtenheft — `mod_keyfigures`

Ein kleines, **generisches** Joomla-Site-Modul, das eine Reihe von
Kennzahlen (Zahl + Beschriftung) ausgibt — Werte wahlweise fest
eingetragen oder aus dem Inhaltsbestand errechnet — mit optionaler
Hochzähl-Animation.

Das Modul ist **nicht** an ein Template oder eine bestimmte Website
gebunden. Es bringt kein oder nur minimales, abschaltbares CSS mit;
Layout und Optik macht die einbindende Website per Template-Override.

Dieses Dokument ist die verbindliche Vorgabe für die Umsetzung. Ein
umsetzender Agent soll es ohne Rückfragen implementieren können; wo
Entscheidungen dem Maintainer obliegen, steht das in §14.

---

## 1. Zweck & Kontext

- Anwendungsfall: „In Zahlen"-Bänder auf Startseiten, Über-Seiten,
  Footer — z. B. „seit 1996 · 30 Jahre", „≈ 350 Ausgaben", „18 Themen",
  „1 400 Fachbeiträge".
- Redaktion pflegt Beschriftungen, Reihenfolge und Sichtbarkeit je
  Kennzahl selbst über die Modul-Parameter; Zahlen, die aus Inhalten
  kommen, aktualisieren sich von selbst.
- Kein Ersatz für `mod_stats` (Joomla-Kern, Server-/Site-Statistik).

## 2. Nicht-Ziele

- Keine Diagramme, Sparklines, Fortschrittsbalken.
- **Kein Freitext-SQL-Feld / kein „Query eintragen"** (Sicherheits­fläche).
- Keine geplante Neuberechnung / kein Cron / keine Verlaufshistorie.
- Kein Admin-Dashboard.
- Keine Laufzeit-Abhängigkeiten: kein Composer-Runtime-Package, kein
  jQuery, kein CDN, keine externen Assets.
- Nicht website­spezifisch: keine hartkodierten Kategorie-IDs, keine
  Projekt­farben, keine Pflicht-Stylesheet-Einbindung.

## 3. Zielplattform & Technik-Rahmen

| Punkt | Vorgabe |
|---|---|
| Joomla | 5.1 LTS **und** 6.x (aktuelle Modul-Architektur mit `services/provider.php` + DI-Helper) |
| PHP | 8.1 – 8.4, `declare(strict_types=1)`, getypte Signaturen |
| Client | `site` |
| DB-Zugriff | `Joomla\Database\DatabaseInterface` aus dem Container, ausschließlich parametrisierte Queries, `quoteName()` |
| JS | Vanilla ES2019+, als Web-Asset registriert, kein Framework |
| CSS | optional, ein kleines **opt-in** Stylesheet als Web-Asset (Website kann es `disable`n) |
| Lizenz | GNU GPL v2 or later (wie Joomla) |
| Abhängigkeiten | keine (Dev-only: PHP_CodeSniffer o. Ä. erlaubt) |

## 4. Funktionsumfang

### 4.1 Kennzahl-Quellen (`source`)

Jede Kennzahl (Subform-Zeile, §4.2) hat genau eine Quelle:

1. **`literal`** — feste Zahl, von der Redaktion eingetragen
   (`value`, z. B. `1996` oder `42`). Dezimalzahlen erlaubt.

2. **`years_since`** — ganzzahlige Differenz zwischen *jetzt* und einem
   Datum.
   - Parameter: `date` (Kalender, Pflicht), `unit`
     (`years` | `months` | `days`, Default `years`).
   - Berechnung über `Joomla\CMS\Date\Date` bzw. `DateTime::diff`,
     Zeitzone = Site-Zeitzone. Abschneiden (floor), nicht runden.
   - Negatives Ergebnis (Datum in der Zukunft) → `0` und intern
     protokollieren (kein Fehler nach außen).

3. **`content_count`** — `SELECT COUNT(*)` auf `#__content` mit Filtern:
   - `category` (Kategorie-Feld, eine ID) + `include_children`
     (`yes` | `no`, Default `yes`). Bei `yes` alle Nachfahren über die
     Nested-Set-Grenzen (`lft`/`rgt`) der gewählten Kategorie.
     Ohne `category` = alle Kategorien.
   - `state` (`published` | `published_unpublished` | `any`,
     Default `published`). `published` = `state = 1` und
     `publish_up <= now` und (`publish_down IS NULL` oder `> now`).
   - `featured` (`any` | `only` | `exclude`, Default `any`).
   - `language` (`*` + aktuelle Sprache, wenn Mehrsprachigkeit an,
     sonst egal). Default: aktuelle Sprache + `*`.
   - `access` — nur Einträge in den Zugriffsebenen des aktuellen
     Benutzers zählen (Default an; abschaltbar `count_all_access`).

4. **`category_count`** — `SELECT COUNT(*)` auf `#__categories`:
   - `parent` (Kategorie-Feld) + `include_children`
     (Default `no` → nur direkte Unterkategorien).
   - `extension` (Default `com_content`).
   - `state` (`published` | `any`, Default `published`).
   - leere Unterkategorien optional ausschließen
     (`skip_empty` `yes`|`no`, Default `no`; bei `yes` nur Kategorien
     mit ≥ 1 veröffentlichtem Beitrag).

**Erweiterbarkeit:** die Zähllogik liegt in einer Helper-Methode
`KeyfiguresHelper::resolve(figure, params, app): int|float`. Ein
`switch` über `source`. Neue Quellen ergänzt man dort; kein Plugin-Event
nötig, aber der Helper ist `public` und überschreibbar dokumentiert.

### 4.2 Modul-Parameter

**Wiederholbare Kennzahlen** — `type="subform"`, `multiple="true"`,
`layout="joomla.form.field.subform.repeatable-table"`. Feld `figures`.
Pro Zeile:

| Feld | Typ | Bemerkung |
|---|---|---|
| `label` | `text` | Anzeigetext, Freitext (kein Sprach-Key-Zwang). Pflicht. |
| `source` | `list` | `literal` \| `years_since` \| `content_count` \| `category_count` |
| `value` | `number` | `showon="source:literal"` |
| `date` | `calendar` | `showon="source:years_since"` |
| `unit` | `list` | `years`/`months`/`days`, `showon="source:years_since"` |
| `category` | `category` (`extension="com_content"`) | `showon="source:content_count"` |
| `parent` | `category` | `showon="source:category_count"` |
| `include_children` | `radio` (`yes`/`no`) | `showon="source:content_count[OR]source:category_count"` |
| `state` | `list` | `showon` wie oben |
| `featured` | `list` | `showon="source:content_count"` |
| `skip_empty` | `radio` | `showon="source:category_count"` |
| `prefix` | `text` | z. B. `≈`, `+`; leer erlaubt |
| `suffix` | `text` | z. B. `+`, `%`, ` k`; leer erlaubt |
| `link` | `url` | optional; verlinkt die Kennzahl |

**Globale Parameter** (Fieldset `params`, plus Standard-Joomla-Felder):

| Feld | Typ | Default | Bemerkung |
|---|---|---|---|
| `animate` | `radio` yes/no | `yes` | Hochzähl-Animation |
| `animate_trigger` | `list` | `in-view` | `in-view` (IntersectionObserver) \| `on-load` |
| `animate_duration` | `number` (ms) | `1600` | 200–10000 |
| `animate_easing` | `list` | `ease-out` | `linear` \| `ease-out` \| `ease-in-out` |
| `animate_from` | `number` | `0` | Startwert der Animation |
| `animate_threshold` | `number` | `0.35` | 0–1, nur `in-view` |
| `respect_reduced_motion` | `radio` | `yes` | bei `prefers-reduced-motion` keine Animation (siehe §7 — auch wenn `no`, wird die Animation für solche Nutzer *nicht* erzwungen, dieser Schalter darf sie nur zusätzlich global aus lassen) |
| `format_grouping` | `radio` | `yes` | Tausender­trennung |
| `format_decimals` | `number` | `0` | Nachkommastellen für die Anzeige |
| `format_locale` | `text` | *(leer)* | leer = Sprache aus `<html lang>` bzw. Site-Locale; sonst BCP-47 (`de-DE`) |
| Standard | — | — | `layout`, `moduleclass_sfx`, `showtitle`/Modultitel, Cache-Felder (`cache`, `cache_time`, `cachemode`) |

Das Modul **muss** mit `cachemode="static"`-kompatibel funktionieren
(keine benutzerabhängige Ausgabe außer der `access`-Zählung — wenn
`count_all_access=no`, dann `cachemode="itemid"` bzw. Doku-Hinweis).

## 5. Ausgabe / Markup-Vertrag

Der **serverseitig fertig formatierte Endwert steht immer im HTML**
(SEO, ohne JS, Screenreader). Die Animation liest nur `data-*` und
zählt hoch, danach schreibt sie denselben formatierten String zurück.

```html
<div class="mod-keyfigures{moduleclass_sfx}"
     data-duration="1600" data-easing="ease-out" data-trigger="in-view"
     data-threshold="0.35" data-locale="de-DE">
  <ul class="mod-keyfigures__list" role="list">
    <li class="mod-keyfigures__item">
      <span class="mod-keyfigures__value"
            data-target="348" data-from="0" data-decimals="0" data-grouping="1"
            data-prefix="≈" data-suffix="+"
            aria-label="≈ 348+">
        <span class="mod-keyfigures__prefix" aria-hidden="true">≈</span><!--
     --><span class="mod-keyfigures__number">348</span><!--
     --><span class="mod-keyfigures__suffix" aria-hidden="true">+</span>
      </span>
      <span class="mod-keyfigures__label">Tempest-Ausgaben</span>
    </li>
    <!-- weitere Items … -->
  </ul>
</div>
```

Regeln:

- Reihenfolge = Reihenfolge der Subform-Zeilen. Leere/ungültige Zeilen
  (kein Label, Quelle unauflösbar) werden **übersprungen**, nicht mit
  Platzhalter gerendert.
- Ist `figures` leer → das Modul gibt **nichts** aus (kein leerer
  Wrapper).
- `data-target` = die rohe Zahl (Punkt als Dezimaltrenner, keine
  Gruppierung). `data-decimals`, `data-grouping` (`0`/`1`),
  `data-prefix`, `data-suffix` steuern die JS-Formatierung identisch zur
  Serverformatierung.
- `.mod-keyfigures__number` enthält initial den **fertig formatierten**
  Wert (mit Gruppierung/Dezimalstellen gemäß Locale). Prefix/Suffix in
  eigenen Spans, `aria-hidden="true"`.
- `aria-label` auf `.mod-keyfigures__value` = `prefix + formatierter Wert
  + suffix` als ein String, damit Screenreader deterministisch vorlesen.
- Bei gesetztem `link`: `.mod-keyfigures__item` bekommt ein umschließendes
  `<a class="mod-keyfigures__link" href="…">`. Interne Ziele über
  `Route::_()`; externe unverändert mit `rel="noopener"`.
- Alle Ausgaben escaped (`$this->escape()` / `htmlspecialchars`).
- **Keine Inline-Styles.** Keine Pflicht-Klassen außer den obigen BEM-
  Namen. Ein optionales `media/mod_keyfigures/css/keyfigures.css`
  (schlichtes Flex-Row-Grundgerüst, `gap`, zentrierte Items) wird als
  Web-Asset bereitgestellt und im Layout `useStyle()`t; die einbindende
  Seite kann es via WebAssetManager abwählen. Doku dazu im README.
- Template-Override möglich unter `templates/<tpl>/html/mod_keyfigures/`.

## 6. Zählanimation (`media/mod_keyfigures/js/keyfigures.js`)

- Als Web-Asset registriert (`joomla.asset.json`), `type="module"` oder
  klassisch; nur laden, wenn `animate=yes`.
- Kein globaler Zustand; pro `.mod-keyfigures` initialisieren, mehrere
  Instanzen je Seite möglich.
- `animate_trigger`:
  - `in-view`: ein `IntersectionObserver` mit `threshold` aus
    `data-threshold`; beim ersten Sichtbarwerden animieren, danach
    `unobserve`.
  - `on-load`: direkt nach `DOMContentLoaded`.
- Tween pro `.mod-keyfigures__value`:
  `requestAnimationFrame`-Schleife von `data-from` nach `data-target`
  über `data-duration` ms mit gewählter Easing-Funktion (eigene kleine
  Easing-Funktionen, kein Lib). Jeder Frame: Wert per
  `Intl.NumberFormat(locale, { minimumFractionDigits, maximumFractionDigits,
  useGrouping })` formatieren und nur `.mod-keyfigures__number.textContent`
  setzen. Prefix/Suffix bleiben unangetastet.
- Nach Abschluss exakt den serverseitigen Endwert setzen (kein
  Rundungsdrift).
- **Idempotenz:** je Element `dataset.done = '1'` setzen; nie zweimal
  animieren. Bei `data-target === data-from` sofort fertig.
- Große Zahlen: Frame-Anzahl über `duration` begrenzt (rAF ~60 fps),
  nicht pro Ganzzahlschritt iterieren.
- **`prefers-reduced-motion: reduce`** (via `matchMedia`): **immer**
  Animation überspringen, Endwert steht ohnehin im DOM. Dieser Check
  ist bindend und unabhängig von `respect_reduced_motion`
  (der Schalter kann Animation nur *zusätzlich* global deaktivieren).
- Kein Fehler, wenn `IntersectionObserver` fehlt (uralte Browser):
  dann wie `on-load` behandeln oder einfach statisch lassen.
- Keine Layout-Sprünge: Breite darf während des Zählens wachsen; das
  ist Sache des Seiten-CSS (Doku-Hinweis: `min-width`/`tabular-nums`
  empfehlen), das Modul erzwingt nichts.

## 7. Barrierefreiheit

- Der echte Endwert ist zu jedem Zeitpunkt im DOM (kein „0", das
  hochzählt, für Screenreader).
- Der animierende Bereich liegt **nicht** in einer `aria-live`-Region;
  Zwischenwerte werden nicht announced.
- `aria-label` auf `.mod-keyfigures__value` mit dem vollständigen
  menschlichen String (Prefix + Wert + Suffix).
- Dekorative Prefix/Suffix-Spans `aria-hidden="true"`.
- `prefers-reduced-motion` respektieren (§6).
- Verlinkte Kennzahl = echtes `<a>` (Tastatur/Fokus ok).
- `role="list"` am `<ul>` (robuste SR-Semantik, da Listenstil ggf.
  entfernt wird).
- Farbkontrast/Fokusring sind Sache der einbindenden Seite (Modul
  bringt keine Farben).

## 8. Mehrsprachigkeit & Zahlenformat

- `language/en-GB/mod_keyfigures.ini` (Basis) + `mod_keyfigures.sys.ini`
  (Modulname + Beschreibung für den Installer/Modul-Manager). Weitere
  Sprachen als PRs willkommen; Repo-Struktur dafür vorsehen.
- Übersetzbare Strings nur die Parameter-Labels/Descriptions und
  Fehlermeldungen (Logging). **Anzeigetexte der Kennzahlen tippt die
  Redaktion frei** — kein Sprach-Key-Mechanismus im `label`-Feld
  (bewusst, für Wiederverwendbarkeit).
- Zahlenformat: `Intl.NumberFormat` im JS, serverseitig
  `Joomla\CMS\Language\Text` bzw. `number_format()` mit den aus
  `format_locale`/Site ermittelten Trennzeichen — **Server- und
  JS-Formatierung müssen identische Ergebnisse liefern**.

## 9. Sicherheit

- Kein SQL aus Parametern. Kategorie-IDs → `(int)`. `state`/`featured`/
  `unit` gegen feste Enums prüfen, sonst Default.
- Alle Query über `DatabaseQuery` + `bind()`/`quoteName()`.
- Ausgabe escaped.
- `link`: mit `Joomla\CMS\Filter\InputFilter`/`filter_var` als URL
  validieren; interne Pfade über `Route::_()`; externe mit
  `rel="noopener"`; kein `javascript:` o. Ä.
- Kein `eval`, kein `unserialize` auf Parametern.
- `defined('_JEXEC') or die;` in allen PHP-Dateien.

## 10. Caching & Performance

- Modul-Cache voll unterstützt (Standard-Joomla-Modul-Cache). Doku:
  errechnete Zahlen aktualisieren sich mit Ablauf des Modul-Caches.
- `cachemode`: `static` wenn `count_all_access=yes` (Default);
  andernfalls `itemid` und README-Hinweis.
- Pro `content_count`/`category_count`-Zeile eine indizierte
  `COUNT(*)`-Query. N Kennzahlen = N Queries pro ungecachtem Render —
  vertretbar; im README erwähnen.
- Keine N+1: keine Query in Schleifen über Ergebniszeilen.

## 11. Projektstruktur & Paketierung

```
mod_keyfigures/
├── mod_keyfigures.xml                 # Manifest (Joomla 5/6 Modul)
├── services/
│   └── provider.php                   # DI Service Provider
├── src/
│   ├── Dispatcher/Dispatcher.php      # optional, sonst Standard-Dispatcher
│   └── Helper/KeyfiguresHelper.php    # resolve(), Formatierung, Query-Aufbau
├── tmpl/
│   ├── default.php
│   └── default.xml
├── language/
│   └── en-GB/
│       ├── mod_keyfigures.ini
│       └── mod_keyfigures.sys.ini
├── media/
│   └── mod_keyfigures/
│       ├── joomla.asset.json
│       ├── js/keyfigures.js
│       └── css/keyfigures.css         # opt-in
├── build/
│   └── build.(sh|ps1)                 # erzeugt dist/mod_keyfigures-<version>.zip
├── tests/                             # siehe §13
├── .github/workflows/ci.yml           # phpcs + Paket-Build (optional)
├── README.md
├── CHANGELOG.md
└── LICENSE
```

- **Manifest** (`mod_keyfigures.xml`): `<extension type="module" client="site" method="upgrade">`,
  `<namespace path="src">Vendor\Module\Keyfigures</namespace>`,
  `<files>` mit `module="mod_keyfigures"`-Eintrag, `<media destination="mod_keyfigures">`,
  `<languages>`, `<config>` mit den Feldern aus §4.2,
  `<scriptfile>` nur falls Install-Logik nötig (vermutlich nicht).
- **`services/provider.php`**: registriert den Modul-Service mit dem
  Helper aus dem Container (Vorbild: `mod_articles_latest` /
  `mod_articles_news` in Joomla 5/6 Core).
- **Web-Assets** via `media/mod_keyfigures/joomla.asset.json`
  (`mod_keyfigures.script`, `mod_keyfigures.style`); im Layout mit
  `$wa->useScript('mod_keyfigures.script')` bzw. `useStyle(...)` laden.
- **Build**: reines Zippen des Modulordners (Manifest an der
  Archiv-Wurzel), Version aus dem Manifest ziehen, `dist/` erzeugen.
- **Versionierung**: SemVer, `CHANGELOG.md` nach „Keep a Changelog".
- **Update-Server** optional: `<updateservers>` im Manifest + eine
  `updates.xml` (kann später ergänzt werden; §14).

## 12. Coding-Standards

- Joomla Coding Standards (PHP_CodeSniffer, Ruleset `Joomla`) —
  `composer.json` nur mit `require-dev` für `phpcs` +
  `joomla/coding-standards`.
- `declare(strict_types=1);`, vollständige Typannotationen,
  kurze `?->`/`??` wo sinnvoll.
- Namespaces PSR-4 unter `src/`.
- Keine `Factory::getDbo()`-Altlast — DB über den Container.
- Keine statischen Helper-Aufrufe mit verstecktem Zustand; Helper
  bekommt `SiteApplication`/`DatabaseInterface` injiziert.
- Kommentar­dichte moderat, DocBlocks an öffentlichen Methoden.

## 13. Abnahmekriterien / Testfälle

**Automatisiert (soweit ohne volle Joomla-Bootstrap machbar):**

- Helper-Unit-Tests gegen Fixtures/Mocks:
  - `literal`: gibt den Wert 1:1 zurück (inkl. Dezimal).
  - `years_since`: korrekte Ganzjahre über eine Schaltjahr-/
    Jahreswechsel-Grenze; Zukunftsdatum → `0`; `unit=months/days`.
  - `content_count`: nur `state=1`; `publish_up`/`publish_down`
    berücksichtigt; `include_children=yes` zählt Nachfahren, `no` nur
    die eine Kategorie; `featured=only/exclude`; `access`-Filter.
  - `category_count`: direkte vs. rekursive Kinder; `skip_empty`.
  - Formatierung: Server- und (nachgebaute) JS-Formatierung liefern für
    dieselben Eingaben denselben String (`1234.5` → `1.234,5` bei
    `de-DE`, `grouping=1`, `decimals=1`).
- `phpcs` läuft ohne Fehler gegen das `Joomla`-Ruleset.

**Manuelle QA-Checkliste:**

1. Installiert & deinstalliert sauber auf einer Stock-Joomla-6-Seite
   (keine Restdateien, kein DB-Müll).
2. `figures` leer → gar keine Ausgabe.
3. 1 Kennzahl / viele Kennzahlen rendern korrekt und in Reihenfolge.
4. Ohne JavaScript: alle Endzahlen sofort korrekt formatiert sichtbar.
5. `animate=yes`, `in-view`: zählt genau einmal hoch, wenn das Modul in
   den Viewport scrollt; kein erneutes Zählen bei Rück-Scroll.
6. `on-load`: zählt direkt nach dem Laden.
7. `prefers-reduced-motion: reduce` (OS-Einstellung): keine Animation,
   Endwerte statisch.
8. Prefix/Suffix (`≈`, `+`, `%`) erscheinen server- wie clientseitig
   an derselben Stelle; Screenreader liest `aria-label` sinnvoll.
9. Verlinkte Kennzahl: intern (SEF-Route) und extern (`rel="noopener"`).
10. Mehrsprachige Seite: `content_count` zählt Sprache + `*`;
    Zahlenformat folgt `format_locale`/`<html lang>`.
11. Modul-Cache an: Zahl bleibt bis Cache-Ablauf stehen, dann aktuell.
12. Zwei Modul-Instanzen auf einer Seite mit verschiedenen Parametern —
    beide animieren unabhängig.
13. Template-Override unter `html/mod_keyfigures/default.php` greift.
14. RTL-Seite: Layout kippt korrekt (sofern das opt-in-CSS genutzt wird).
15. Opt-in-CSS lässt sich per WebAssetManager der Seite abschalten,
    Modul funktioniert weiter (nur ungestylt).

## 14. Offene Entscheidungen (Maintainer)

- **Name & Vendor-Namespace.** `mod_keyfigures` ist ein Vorschlag;
  ebenso denkbar `mod_figures`, `mod_countup`, `mod_metrics`. Vendor
  neutral wählen (Maintainer-Handle / Projektname), **nicht**
  „autorenforum".
- **Opt-in-CSS: ja/nein.** Empfehlung: minimales `keyfigures.css`
  mitliefern, per Web-Asset abschaltbar. Alternativ komplett CSS-los.
- **Update-Server / `updates.xml`** jetzt oder später.
- **`years_since` mit `unit=months/days`** wirklich nötig? Wenn nur
  Jahre gebraucht werden, Feld `unit` weglassen (kleinere Oberfläche).
- **Weitere Quellen** (`#__users` Anzahl, `#__contact_details`, Tags …)
  — bei Bedarf später über `KeyfiguresHelper::resolve()` ergänzen; die
  Subform ist darauf vorbereitet (nur neue `source`-Option + `showon`).
- **Zielversion Joomla:** 5.1+ und 6.x ist die Vorgabe; falls 4.x noch
  relevant ist, muss die Modul-Architektur (kein `services/provider.php`
  in 4.0–4.1) angepasst werden — Empfehlung: 4.x nicht unterstützen.
